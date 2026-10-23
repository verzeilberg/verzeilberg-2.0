<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261021153736 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Two-factor login: authenticator secret, backup codes and trusted device version on user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD totp_secret VARCHAR(255) DEFAULT NULL, ADD backup_codes JSON DEFAULT NULL, ADD trusted_token_version INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP totp_secret, DROP backup_codes, DROP trusted_token_version');
    }
}
