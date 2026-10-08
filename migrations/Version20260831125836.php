<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260831125836 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE email_account (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, email VARCHAR(255) NOT NULL, imap_host VARCHAR(255) NOT NULL, imap_port INT NOT NULL, encryption VARCHAR(50) NOT NULL, username VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, sent_folder VARCHAR(100) DEFAULT NULL, drafts_folder VARCHAR(100) DEFAULT NULL, trash_folder VARCHAR(100) DEFAULT NULL, enabled TINYINT DEFAULT 1 NOT NULL, last_synced_at DATETIME DEFAULT NULL, total_messages INT DEFAULT 0 NOT NULL, unread_messages INT DEFAULT 0 NOT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_C0F63E6BDE12AB56 (created_by), INDEX IDX_C0F63E6B10BC6D9F (changed_by), INDEX IDX_C0F63E6B1F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE email_message (id INT AUTO_INCREMENT NOT NULL, folder VARCHAR(100) NOT NULL, uid VARCHAR(100) NOT NULL, message_id VARCHAR(500) DEFAULT NULL, subject LONGTEXT DEFAULT NULL, from_address LONGTEXT DEFAULT NULL, from_name VARCHAR(255) DEFAULT NULL, to_addresses LONGTEXT DEFAULT NULL, cc_addresses LONGTEXT DEFAULT NULL, bcc_addresses LONGTEXT DEFAULT NULL, sent_at DATETIME DEFAULT NULL, received_at DATETIME DEFAULT NULL, body_text LONGTEXT DEFAULT NULL, body_html LONGTEXT DEFAULT NULL, attachments JSON DEFAULT NULL, is_read TINYINT DEFAULT 0 NOT NULL, is_flagged TINYINT DEFAULT 0 NOT NULL, is_answered TINYINT DEFAULT 0 NOT NULL, is_deleted TINYINT DEFAULT 0 NOT NULL, is_draft TINYINT DEFAULT 0 NOT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, account_id INT NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_B7D58B09B6B5FBA (account_id), INDEX IDX_B7D58B0DE12AB56 (created_by), INDEX IDX_B7D58B010BC6D9F (changed_by), INDEX IDX_B7D58B01F6FA0AF (deleted_by), INDEX IDX_B7D58B09B6B5FBAECA209CD (account_id, folder), INDEX IDX_B7D58B0539B0606 (uid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE email_account ADD CONSTRAINT FK_C0F63E6BDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_account ADD CONSTRAINT FK_C0F63E6B10BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_account ADD CONSTRAINT FK_C0F63E6B1F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_message ADD CONSTRAINT FK_B7D58B09B6B5FBA FOREIGN KEY (account_id) REFERENCES email_account (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE email_message ADD CONSTRAINT FK_B7D58B0DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_message ADD CONSTRAINT FK_B7D58B010BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_message ADD CONSTRAINT FK_B7D58B01F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE email_account DROP FOREIGN KEY FK_C0F63E6BDE12AB56');
        $this->addSql('ALTER TABLE email_account DROP FOREIGN KEY FK_C0F63E6B10BC6D9F');
        $this->addSql('ALTER TABLE email_account DROP FOREIGN KEY FK_C0F63E6B1F6FA0AF');
        $this->addSql('ALTER TABLE email_message DROP FOREIGN KEY FK_B7D58B09B6B5FBA');
        $this->addSql('ALTER TABLE email_message DROP FOREIGN KEY FK_B7D58B0DE12AB56');
        $this->addSql('ALTER TABLE email_message DROP FOREIGN KEY FK_B7D58B010BC6D9F');
        $this->addSql('ALTER TABLE email_message DROP FOREIGN KEY FK_B7D58B01F6FA0AF');
        $this->addSql('DROP TABLE email_account');
        $this->addSql('DROP TABLE email_message');
    }
}
