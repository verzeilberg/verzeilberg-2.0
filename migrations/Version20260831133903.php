<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260831133903 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE email_account ADD smtp_host VARCHAR(255) DEFAULT NULL, ADD smtp_port INT DEFAULT NULL, ADD smtp_encryption VARCHAR(50) DEFAULT NULL, ADD smtp_username VARCHAR(255) DEFAULT NULL, ADD smtp_password VARCHAR(255) DEFAULT NULL, ADD use_imap_for_smtp TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE email_account DROP smtp_host, DROP smtp_port, DROP smtp_encryption, DROP smtp_username, DROP smtp_password, DROP use_imap_for_smtp');
    }
}
