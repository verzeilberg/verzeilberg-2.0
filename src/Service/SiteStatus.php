<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Agenda;
use App\Entity\User;
use App\EventListener\DataRetentionListener;
use App\Mailer\DeferredMailer;
use App\Repository\AgendaRepository;
use App\Repository\Steam\SteamAccountRepository;
use App\Repository\Strava\AccessTokenRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\DependencyFactory;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * What the admin status page checks: the server has no shell (FTP only), so this page is the way to see whether
 * PHP, the database, mail, the background jobs and the folders are in order, and which errors were logged.
 */
class SiteStatus
{
    public const OK = 'ok';
    public const WARN = 'warn';
    public const FAIL = 'fail';
    public const INFO = 'info';

    /** PHP extensions => what breaks without them */
    private const EXTENSIONS = [
        'pdo_mysql' => 'de database',
        'intl' => 'datums en vertalingen',
        'mbstring' => 'teksten',
        'sodium' => 'versleutelde wachtwoorden en tokens',
        'gd' => 'verkleinen van foto\'s en linkvoorbeelden',
        'imap' => 'de mailbox in het beheer',
    ];

    /** Folders PHP must be able to write to */
    private const WRITABLE = ['var', 'var/cache', 'var/log', 'var/storage', 'public/uploads', 'public/share-images'];

    private const LOG_LEVELS = ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    /** Only the end of each log file is read: a log can be far larger than PHP's memory limit */
    private const LOG_TAIL_BYTES = 1024 * 1024;

    public function __construct(
        private Connection $connection,
        #[Autowire(service: 'doctrine.migrations.dependency_factory')] private DependencyFactory $migrations,
        private SteamAccountRepository $steamAccountRepository,
        private AgendaRepository $agendaRepository,
        private AccessTokenRepository $stravaTokenRepository,
        private UserRepository $userRepository,
        private CacheItemPoolInterface $cache,
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        #[Autowire('%kernel.logs_dir%')] private string $logsDir,
        #[Autowire('%kernel.environment%')] private string $environment,
        #[Autowire('%kernel.debug%')] private bool $debug,
        #[Autowire('%kernel.secret%')] private string $secret,
        #[Autowire('%env(MAILER_DSN)%')] private string $mailerDsn,
    ) {
    }

    /** @return array<string, list<array{label: string, state: string, detail: string}>> checks per section */
    public function checks(): array
    {
        return [
            'Server' => $this->serverChecks(),
            'Site' => $this->siteChecks(),
            'Achtergrondtaken' => $this->jobChecks(),
            'Gebruikers' => $this->userChecks(),
        ];
    }

    /**
     * The newest errors from the log files (production writes a file per day, see monolog.yaml).
     *
     * @return list<array{time: string, level: string, message: string}>
     */
    public function recentErrors(int $limit = 15): array
    {
        $files = glob($this->logsDir . '/' . $this->environment . '*.log') ?: [];
        rsort($files);

        $errors = [];
        foreach ($files as $file) {
            foreach (array_reverse($this->lastLines($file)) as $line) {
                $entry = json_decode($line, true);
                if (!is_array($entry) || !in_array($entry['level_name'] ?? '', self::LOG_LEVELS, true)) {
                    continue;
                }
                $errors[] = [
                    'time' => (string) ($entry['datetime'] ?? ''),
                    'level' => (string) $entry['level_name'],
                    'message' => mb_strimwidth((string) ($entry['message'] ?? ''), 0, 500, '…'),
                ];
                if (count($errors) >= $limit) {
                    return $errors;
                }
            }
        }

        return $errors;
    }

    /** @return list<string> the complete lines in the last LOG_TAIL_BYTES of the file */
    private function lastLines(string $file): array
    {
        $handle = @fopen($file, 'r');
        if ($handle === false) {
            return [];
        }
        $size = (int) filesize($file);
        $start = max(0, $size - self::LOG_TAIL_BYTES);
        fseek($handle, $start);
        $tail = (string) stream_get_contents($handle);
        fclose($handle);

        $lines = explode("\n", $tail);
        if ($start > 0) {
            // The first line was cut off halfway
            array_shift($lines);
        }

        return array_values(array_filter($lines, fn (string $line) => $line !== ''));
    }

    /** @return list<array{label: string, state: string, detail: string}> */
    private function serverChecks(): array
    {
        $checks = [$this->check(
            'PHP',
            version_compare(PHP_VERSION, '8.4.0', '>=') ? self::OK : self::FAIL,
            PHP_VERSION . (version_compare(PHP_VERSION, '8.4.0', '>=') ? '' : ': de site heeft 8.4 of nieuwer nodig')
        )];

        foreach (self::EXTENSIONS as $extension => $usedFor) {
            $checks[] = extension_loaded($extension)
                ? $this->check('Extensie ' . $extension, self::OK, 'Geïnstalleerd')
                : $this->check('Extensie ' . $extension, $extension === 'pdo_mysql' || $extension === 'sodium' ? self::FAIL : self::WARN, 'Ontbreekt: nodig voor ' . $usedFor . '. Vraag de hostingpartij om deze aan te zetten.');
        }

        $checks[] = $this->check('Uploadlimieten', self::INFO, sprintf(
            'upload_max_filesize %s, post_max_size %s, memory_limit %s',
            ini_get('upload_max_filesize'),
            ini_get('post_max_size'),
            ini_get('memory_limit')
        ));

        $free = function_exists('disk_free_space') ? @disk_free_space($this->projectDir) : false;
        $checks[] = $free === false
            ? $this->check('Vrije schijfruimte', self::INFO, 'De host geeft dit niet door')
            : $this->check('Vrije schijfruimte', $free < 500 * 1024 ** 2 ? self::WARN : self::OK, $this->bytes((int) $free));
        $checks[] = $this->check('Uploads', self::INFO, $this->bytes($this->folderSize($this->projectDir . '/public/uploads')) . ' in public/uploads');

        return $checks;
    }

    /** @return list<array{label: string, state: string, detail: string}> */
    private function siteChecks(): array
    {
        $checks = [];
        $checks[] = $this->environment === 'prod' && !$this->debug
            ? $this->check('Omgeving', self::OK, 'Productie')
            : $this->check('Omgeving', self::WARN, sprintf('%s%s: bezoekers kunnen foutdetails zien', $this->environment, $this->debug ? ', debug aan' : ''));

        $checks[] = strlen($this->secret) >= 16
            ? $this->check('APP_SECRET', self::OK, 'Ingesteld (laat hem zo: opgeslagen wachtwoorden en tokens zijn ermee versleuteld)')
            : $this->check('APP_SECRET', self::FAIL, 'Leeg of te kort');

        $scheme = strtolower((string) strtok($this->mailerDsn, ':'));
        $checks[] = $scheme === '' || $scheme === 'null'
            ? $this->check('E-mail', self::FAIL, 'MAILER_DSN verstuurt niets: inlogcodes en meldingen komen niet aan')
            : $this->mailCheck($scheme);

        try {
            $checks[] = $this->check('Database', self::OK, 'Verbonden, versie ' . $this->connection->fetchOne('SELECT VERSION()'));
            $new = count($this->migrations->getMigrationStatusCalculator()->getNewMigrations());
            $checks[] = $new === 0
                ? $this->check('Databasestructuur', self::OK, 'Bijgewerkt')
                : $this->check('Databasestructuur', self::FAIL, sprintf('%d wijziging(en) nog niet doorgevoerd. Maak de SQL lokaal aan (doctrine:migrations:migrate --write-sql) en voer die uit in phpMyAdmin.', $new));
        } catch (\Throwable $e) {
            $checks[] = $this->check('Database', self::FAIL, 'Geen verbinding: ' . $e->getMessage());
        }

        foreach (self::WRITABLE as $folder) {
            $path = $this->projectDir . '/' . $folder;
            $checks[] = match (true) {
                is_dir($path) && is_writable($path) => $this->check('Schrijfbaar ' . $folder . '/', self::OK, 'Ja'),
                // The site creates these folders when it first needs them
                !is_dir($path) && is_writable(dirname($path)) => $this->check('Schrijfbaar ' . $folder . '/', self::INFO, 'Bestaat nog niet; wordt aangemaakt zodra nodig'),
                default => $this->check('Schrijfbaar ' . $folder . '/', self::FAIL, (is_dir($path) ? 'Nee' : 'Ontbreekt en kan niet worden aangemaakt') . ': geef de webserver schrijfrechten (FTP: rechten 775)'),
            };
        }

        return $checks;
    }

    /** @return list<array{label: string, state: string, detail: string}> */
    private function jobChecks(): array
    {
        $checks = [];

        $lastPurge = $this->cache->getItem(DataRetentionListener::CACHE_KEY)->get();
        $checks[] = is_int($lastPurge)
            ? $this->check('Oude persoonsgegevens verwijderen', self::OK, 'Laatst uitgevoerd ' . $this->ago($lastPurge) . ' (dagelijks, automatisch)')
            : $this->check('Oude persoonsgegevens verwijderen', self::INFO, 'Draait automatisch na het volgende paginabezoek');

        $steam = $this->steamAccountRepository->findOneBy([]);
        if ($steam === null) {
            $checks[] = $this->check('Steam-synchronisatie', self::INFO, 'Nog geen Steam-account');
        } elseif ($steam->getLastSyncError()) {
            $checks[] = $this->check('Steam-synchronisatie', self::FAIL, 'Laatste synchronisatie mislukt: ' . $steam->getLastSyncError());
        } else {
            $synced = $steam->getLastSyncedAt();
            $checks[] = $synced !== null && $synced > new \DateTimeImmutable('-3 hours')
                ? $this->check('Steam-synchronisatie', self::OK, 'Laatste synchronisatie ' . $this->ago($synced->getTimestamp()))
                : $this->check('Steam-synchronisatie', self::WARN, ($synced ? 'Laatste synchronisatie ' . $this->ago($synced->getTimestamp()) : 'Nog nooit gesynchroniseerd') . ': de cronjob van elk uur (app:steam:sync) lijkt niet te draaien. Synchroniseer handmatig onder Steam.');
        }

        $googleAgendas = array_filter($this->agendaRepository->findActive(), fn (Agenda $agenda) => $agenda->getGoogleAccount() !== null);
        foreach ($googleAgendas as $agenda) {
            $label = 'Google-agenda "' . $agenda->getTitle() . '"';
            $synced = $agenda->getLastSyncedAt();
            $checks[] = match (true) {
                (bool) $agenda->getLastSyncError() => $this->check($label, self::FAIL, 'Laatste synchronisatie mislukt: ' . $agenda->getLastSyncError()),
                $synced !== null && $synced > new \DateTimeImmutable('-1 hour') => $this->check($label, self::OK, 'Laatste synchronisatie ' . $this->ago($synced->getTimestamp())),
                default => $this->check($label, self::WARN, ($synced ? 'Laatste synchronisatie ' . $this->ago($synced->getTimestamp()) : 'Nog nooit gesynchroniseerd') . ': de cronjob (app:agenda:sync-google, elke 15 minuten) lijkt niet te draaien'),
            };
        }
        if ($googleAgendas === []) {
            $checks[] = $this->check('Google-agenda\'s', self::INFO, 'Geen agenda gekoppeld aan Google');
        }

        $token = $this->stravaTokenRepository->findLatest();
        $checks[] = match (true) {
            $token === null => $this->check('Strava', self::WARN, 'Niet verbonden: koppel onder Strava'),
            $token->getRefreshToken() === '' => $this->check('Strava', self::WARN, 'Het opgeslagen token is niet leesbaar (APP_SECRET gewijzigd): koppel opnieuw onder Strava'),
            default => $this->check('Strava', self::OK, 'Verbonden'),
        };

        return $checks;
    }

    /** @return array{label: string, state: string, detail: string} whether the last mail the site sent arrived at the mail server */
    private function mailCheck(string $scheme): array
    {
        $lastSent = $this->cache->getItem(DeferredMailer::LAST_SENT_KEY)->get();
        $lastFailure = $this->cache->getItem(DeferredMailer::LAST_FAILURE_KEY)->get();

        if (is_array($lastFailure) && (!is_int($lastSent) || $lastFailure['time'] > $lastSent)) {
            return $this->check('E-mail', self::FAIL, sprintf('De laatste mail (%s) kon niet worden verstuurd: %s', $this->ago($lastFailure['time']), $lastFailure['error']));
        }

        return $this->check('E-mail', self::OK, 'Verstuurt via ' . $scheme
            . (is_int($lastSent) ? ', laatste mail ' . $this->ago($lastSent) . ' verstuurd' : '')
            . '. Gebruik "Stuur testmail" om het zeker te weten.');
    }

    /** @return list<array{label: string, state: string, detail: string}> */
    private function userChecks(): array
    {
        $waiting = $this->userRepository->countPendingApproval();
        $unconfirmed = $this->userRepository->count(['status' => User::STATUS_PENDING, 'emailVerifiedAt' => null, 'deleted' => false]);

        return [
            $waiting > 0
                ? $this->check('Registraties', self::WARN, sprintf('%d registratie(s) wachten op goedkeuring onder Gebruikers', $waiting))
                : $this->check('Registraties', self::OK, 'Niets te beoordelen'),
            $this->check('Onbevestigde registraties', self::INFO, sprintf('%d account(s) wachten tot het e-mailadres is bevestigd; na 30 dagen worden ze verwijderd', $unconfirmed)),
        ];
    }

    /** @return array{label: string, state: string, detail: string} */
    private function check(string $label, string $state, string $detail): array
    {
        return ['label' => $label, 'state' => $state, 'detail' => $detail];
    }

    private function folderSize(string $folder): int
    {
        if (!is_dir($folder)) {
            return 0;
        }
        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $size += $file->isFile() ? $file->getSize() : 0;
        }

        return $size;
    }

    private function bytes(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return round($bytes, 1) . ' ' . $unit;
            }
            $bytes /= 1024;
        }

        return '';
    }

    private function ago(int $timestamp): string
    {
        $minutes = intdiv(max(0, time() - $timestamp), 60);

        return match (true) {
            $minutes < 2 => 'zojuist',
            $minutes < 120 => $minutes . ' minuten geleden',
            $minutes < 2880 => intdiv($minutes, 60) . ' uur geleden',
            default => intdiv($minutes, 1440) . ' dagen geleden',
        };
    }
}
