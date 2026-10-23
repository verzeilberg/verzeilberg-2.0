<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Users confirm their email address after registering (existing users count as confirmed), and can switch off notification mails';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD email_verified_at DATETIME DEFAULT NULL, ADD email_verification_token VARCHAR(255) DEFAULT NULL, ADD email_notifications TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('UPDATE user SET email_verified_at = date_created');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP email_verified_at, DROP email_verification_token, DROP email_notifications');
    }
}
