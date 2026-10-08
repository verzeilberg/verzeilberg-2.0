<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260831135508 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE email_log DROP FOREIGN KEY `FK_6FB488310BC6D9F`');
        $this->addSql('ALTER TABLE email_log DROP FOREIGN KEY `FK_6FB48831F6FA0AF`');
        $this->addSql('ALTER TABLE email_log DROP FOREIGN KEY `FK_6FB4883DE12AB56`');
        $this->addSql('ALTER TABLE email_template DROP FOREIGN KEY `FK_9C0600CA10BC6D9F`');
        $this->addSql('ALTER TABLE email_template DROP FOREIGN KEY `FK_9C0600CA1F6FA0AF`');
        $this->addSql('ALTER TABLE email_template DROP FOREIGN KEY `FK_9C0600CADE12AB56`');
        $this->addSql('DROP TABLE email_log');
        $this->addSql('DROP TABLE email_template');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE email_log (id INT AUTO_INCREMENT NOT NULL, recipient_email VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, recipient_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, sender_email VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, sender_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, subject VARCHAR(500) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, body_text LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, body_html LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, status VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT \'pending\' NOT NULL COLLATE `utf8mb4_0900_ai_ci`, error_message LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, sent_at DATETIME DEFAULT NULL, retry_count INT DEFAULT 0 NOT NULL, template_name VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, template_data JSON DEFAULT NULL, message_id VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_6FB488310BC6D9F (changed_by), INDEX IDX_6FB48831F6FA0AF (deleted_by), INDEX IDX_6FB4883DE12AB56 (created_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE email_template (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, label VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, sender_email VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, sender_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, subject VARCHAR(500) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, body_text LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, body_html LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, enabled TINYINT DEFAULT 1 NOT NULL, variables JSON DEFAULT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_9C0600CA10BC6D9F (changed_by), INDEX IDX_9C0600CA1F6FA0AF (deleted_by), INDEX IDX_9C0600CADE12AB56 (created_by), UNIQUE INDEX UNIQ_9C0600CA5E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE email_log ADD CONSTRAINT `FK_6FB488310BC6D9F` FOREIGN KEY (changed_by) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_log ADD CONSTRAINT `FK_6FB48831F6FA0AF` FOREIGN KEY (deleted_by) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_log ADD CONSTRAINT `FK_6FB4883DE12AB56` FOREIGN KEY (created_by) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_template ADD CONSTRAINT `FK_9C0600CA10BC6D9F` FOREIGN KEY (changed_by) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_template ADD CONSTRAINT `FK_9C0600CA1F6FA0AF` FOREIGN KEY (deleted_by) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_template ADD CONSTRAINT `FK_9C0600CADE12AB56` FOREIGN KEY (created_by) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL');
    }
}
