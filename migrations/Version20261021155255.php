<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261021155255 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Two-factor login by email (the default for every user), or by authenticator app: method, emailed code and its time';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD two_factor_method VARCHAR(10) DEFAULT \'email\' NOT NULL, ADD email_auth_code VARCHAR(10) DEFAULT NULL, ADD email_auth_code_created_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP two_factor_method, DROP email_auth_code, DROP email_auth_code_created_at');
    }
}
