<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260831104551 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE email_log (id INT AUTO_INCREMENT NOT NULL, recipient_email VARCHAR(255) NOT NULL, recipient_name VARCHAR(255) DEFAULT NULL, sender_email VARCHAR(255) DEFAULT NULL, sender_name VARCHAR(255) DEFAULT NULL, subject VARCHAR(500) NOT NULL, body_text LONGTEXT NOT NULL, body_html LONGTEXT DEFAULT NULL, status VARCHAR(50) DEFAULT \'pending\' NOT NULL, error_message LONGTEXT DEFAULT NULL, sent_at DATETIME DEFAULT NULL, retry_count INT DEFAULT 0 NOT NULL, template_name VARCHAR(100) DEFAULT NULL, template_data JSON DEFAULT NULL, message_id VARCHAR(100) DEFAULT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_6FB4883DE12AB56 (created_by), INDEX IDX_6FB488310BC6D9F (changed_by), INDEX IDX_6FB48831F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE email_template (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, label VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, sender_email VARCHAR(255) DEFAULT NULL, sender_name VARCHAR(255) DEFAULT NULL, subject VARCHAR(500) NOT NULL, body_text LONGTEXT NOT NULL, body_html LONGTEXT DEFAULT NULL, enabled TINYINT DEFAULT 1 NOT NULL, variables JSON DEFAULT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, UNIQUE INDEX UNIQ_9C0600CA5E237E06 (name), INDEX IDX_9C0600CADE12AB56 (created_by), INDEX IDX_9C0600CA10BC6D9F (changed_by), INDEX IDX_9C0600CA1F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE email_log ADD CONSTRAINT FK_6FB4883DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_log ADD CONSTRAINT FK_6FB488310BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_log ADD CONSTRAINT FK_6FB48831F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_template ADD CONSTRAINT FK_9C0600CADE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_template ADD CONSTRAINT FK_9C0600CA10BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_template ADD CONSTRAINT FK_9C0600CA1F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE email_log DROP FOREIGN KEY FK_6FB4883DE12AB56');
        $this->addSql('ALTER TABLE email_log DROP FOREIGN KEY FK_6FB488310BC6D9F');
        $this->addSql('ALTER TABLE email_log DROP FOREIGN KEY FK_6FB48831F6FA0AF');
        $this->addSql('ALTER TABLE email_template DROP FOREIGN KEY FK_9C0600CADE12AB56');
        $this->addSql('ALTER TABLE email_template DROP FOREIGN KEY FK_9C0600CA10BC6D9F');
        $this->addSql('ALTER TABLE email_template DROP FOREIGN KEY FK_9C0600CA1F6FA0AF');
        $this->addSql('DROP TABLE email_log');
        $this->addSql('DROP TABLE email_template');
    }
}
