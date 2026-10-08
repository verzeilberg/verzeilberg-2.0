<?php

declare(strict_types=1);

namespace App\Service\Google;

use App\Entity\GoogleAccount;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Google OAuth 2.0 and the (read-only) Google Calendar API, over plain HTTP.
 *
 * @see https://developers.google.com/identity/protocols/oauth2/web-server
 * @see https://developers.google.com/calendar/api/v3/reference
 */
class GoogleCalendarClient
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';
    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';
    private const API_URL = 'https://www.googleapis.com/calendar/v3';
    /** Read the calendars, and the e-mail address to recognise the account */
    private const SCOPES = 'openid email https://www.googleapis.com/auth/calendar.readonly';

    public function __construct(
        private HttpClientInterface $httpClient,
        private TokenEncryptor $encryptor,
        private EntityManagerInterface $em,
        #[Autowire('%env(GOOGLE_CLIENT_ID)%')] private string $clientId,
        #[Autowire('%env(GOOGLE_CLIENT_SECRET)%')] private string $clientSecret,
    ) {
    }

    /** Whether GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET are set */
    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    /** Where to send the admin to give access; $state protects the callback against CSRF */
    public function getAuthorizationUrl(string $redirectUri, string $state): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPES,
            // offline + consent: Google always returns a refresh token, so the site can sync without the admin
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /**
     * Exchanges the code from the callback for tokens and returns the (new or updated) connected account.
     *
     * @throws GoogleApiException
     */
    public function connect(string $code, string $redirectUri): GoogleAccount
    {
        $tokens = $this->requestToken([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);
        if (empty($tokens['refresh_token'])) {
            throw new GoogleApiException('Google did not return a refresh token. Remove the site\'s access in your Google account settings and connect again.');
        }

        $userInfo = $this->get(self::USERINFO_URL, (string) $tokens['access_token']);
        $email = (string) ($userInfo['email'] ?? '');
        if ($email === '') {
            throw new GoogleApiException('Google did not return the e-mail address of the account.');
        }

        $accessToken = $this->encryptor->encrypt((string) $tokens['access_token']);
        $refreshToken = $this->encryptor->encrypt((string) $tokens['refresh_token']);
        $expiresAt = $this->expiresAt($tokens);

        $account = $this->em->getRepository(GoogleAccount::class)->findOneBy(['email' => $email]);
        if ($account) {
            $account->reconnect($accessToken, $refreshToken, $expiresAt);
        } else {
            $account = new GoogleAccount($email, $accessToken, $refreshToken, $expiresAt);
            $this->em->persist($account);
        }

        return $account;
    }

    /**
     * The calendars of the account: id, name, colour, and whether it is the account's main calendar.
     *
     * @return list<array{id: string, summary: string, color: string, primary: bool, accessRole: string}>
     *
     * @throws GoogleApiException
     */
    public function listCalendars(GoogleAccount $account): array
    {
        $calendars = [];
        foreach ($this->paginate($account, self::API_URL . '/users/me/calendarList', ['minAccessRole' => 'reader']) as $calendar) {
            $calendars[] = [
                'id' => (string) $calendar['id'],
                'summary' => (string) ($calendar['summaryOverride'] ?? $calendar['summary'] ?? $calendar['id']),
                'color' => (string) ($calendar['backgroundColor'] ?? '#4285f4'),
                'primary' => (bool) ($calendar['primary'] ?? false),
                'accessRole' => (string) ($calendar['accessRole'] ?? 'reader'),
            ];
        }

        return $calendars;
    }

    /**
     * The events of a calendar in [from, to). Recurring events are returned as separate occurrences.
     *
     * @return list<array<string, mixed>>
     *
     * @throws GoogleApiException
     */
    public function listEvents(GoogleAccount $account, string $calendarId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->paginate($account, self::API_URL . '/calendars/' . rawurlencode($calendarId) . '/events', [
            'singleEvents' => 'true',
            'orderBy' => 'startTime',
            'timeMin' => $from->format(\DateTimeInterface::RFC3339),
            'timeMax' => $to->format(\DateTimeInterface::RFC3339),
            'maxResults' => 2500,
        ]);
    }

    /** Withdraws the site's access at Google (best effort: the account is removed here anyway) */
    public function revoke(GoogleAccount $account): void
    {
        try {
            $this->httpClient->request('POST', self::REVOKE_URL, [
                'body' => ['token' => $this->encryptor->decrypt($account->getEncryptedRefreshToken())],
                'timeout' => 10,
            ])->getStatusCode();
        } catch (\Throwable) {
            // Already revoked or Google unreachable: nothing more to do
        }
    }

    /**
     * A valid access token; renewed with the refresh token when it (almost) expired.
     *
     * @throws GoogleApiException when Google refuses the refresh token (access withdrawn)
     */
    private function getAccessToken(GoogleAccount $account): string
    {
        if ($account->getAccessTokenExpiresAt() > new \DateTimeImmutable('+60 seconds')) {
            return $this->encryptor->decrypt($account->getEncryptedAccessToken());
        }

        try {
            $tokens = $this->requestToken([
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->encryptor->decrypt($account->getEncryptedRefreshToken()),
            ]);
        } catch (GoogleApiException $e) {
            $account->setLastError('Access was refused by Google; connect the account again. (' . $e->getMessage() . ')');
            $this->em->flush();
            throw $e;
        }
        $account->updateAccessToken($this->encryptor->encrypt((string) $tokens['access_token']), $this->expiresAt($tokens));
        $this->em->flush();

        return (string) $tokens['access_token'];
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return list<array<string, mixed>>
     */
    private function paginate(GoogleAccount $account, string $url, array $parameters): array
    {
        $items = [];
        $pageToken = null;
        do {
            $page = $this->get($url, $this->getAccessToken($account), $parameters + ($pageToken ? ['pageToken' => $pageToken] : []));
            foreach ($page['items'] ?? [] as $item) {
                $items[] = $item;
            }
            $pageToken = $page['nextPageToken'] ?? null;
        } while ($pageToken !== null);

        return $items;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    private function get(string $url, string $accessToken, array $query = []): array
    {
        return $this->send('GET', $url, ['auth_bearer' => $accessToken, 'query' => $query]);
    }

    /**
     * @param array<string, string> $parameters
     *
     * @return array<string, mixed>
     */
    private function requestToken(array $parameters): array
    {
        $tokens = $this->send('POST', self::TOKEN_URL, ['body' => $parameters + [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]]);
        if (empty($tokens['access_token'])) {
            throw new GoogleApiException('Google did not return an access token.');
        }

        return $tokens;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function send(string $method, string $url, array $options): array
    {
        try {
            $response = $this->httpClient->request($method, $url, $options + ['timeout' => 20]);
            $status = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (HttpExceptionInterface $e) {
            throw new GoogleApiException('Google could not be reached: ' . $e->getMessage(), 0, $e);
        }

        if ($status >= 400) {
            $message = is_array($data['error'] ?? null) ? ($data['error']['message'] ?? '') : ($data['error_description'] ?? $data['error'] ?? '');
            throw new GoogleApiException(sprintf('Google returned an error (HTTP %d): %s', $status, (string) $message));
        }

        return $data;
    }

    /** @param array<string, mixed> $tokens */
    private function expiresAt(array $tokens): \DateTimeImmutable
    {
        return new \DateTimeImmutable('+' . max(60, (int) ($tokens['expires_in'] ?? 3600)) . ' seconds');
    }
}
