<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Contact messages remember when an admin read them (unread ones are counted in the admin menu); existing messages count as read';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact ADD read_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE contact SET read_at = date_created');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact DROP read_at');
    }
}
