<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class Version20261022100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Events get a URL slug (/event/nobelhorst-run-2026 instead of /event/99); existing events get one by the rule of EventSlugListener';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE events ADD slug VARCHAR(255) DEFAULT NULL');

        // Same rule as App\EventListener\EventSlugListener, repeated here so this migration doesn't change with it
        $slugger = new AsciiSlugger();
        $taken = [];
        foreach ($this->connection->fetchAllAssociative('SELECT id, title, event_start_date FROM events ORDER BY id') as $row) {
            $year = substr((string) $row['event_start_date'], 0, 4);
            $base = (string) $slugger->slug((string) $row['title'])->lower();
            if (!str_contains($base, $year)) {
                $base = ltrim($base . '-' . $year, '-');
            }
            $slug = $base;
            for ($i = 2; isset($taken[$slug]); ++$i) {
                $slug = $base . '-' . $i;
            }
            $taken[$slug] = true;
            $this->addSql('UPDATE events SET slug = ? WHERE id = ?', [$slug, (int) $row['id']]);
        }

        $this->addSql('ALTER TABLE events MODIFY slug VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5387574A989D9B62 ON events (slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_5387574A989D9B62 ON events');
        $this->addSql('ALTER TABLE events DROP slug');
    }
}
