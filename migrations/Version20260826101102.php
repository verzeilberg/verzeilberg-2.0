<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260826101102 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE setting (id INT AUTO_INCREMENT NOT NULL, `key` VARCHAR(100) NOT NULL, value LONGTEXT DEFAULT NULL, description VARCHAR(255) DEFAULT NULL, type VARCHAR(50) DEFAULT \'string\' NOT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, UNIQUE INDEX UNIQ_9F74B8988A90ABA9 (`key`), INDEX IDX_9F74B898DE12AB56 (created_by), INDEX IDX_9F74B89810BC6D9F (changed_by), INDEX IDX_9F74B8981F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE setting ADD CONSTRAINT FK_9F74B898DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE setting ADD CONSTRAINT FK_9F74B89810BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE setting ADD CONSTRAINT FK_9F74B8981F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, slug VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, module VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, enabled TINYINT DEFAULT 1 NOT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, UNIQUE INDEX UNIQ_64C19C1989D9B62 (slug), INDEX IDX_64C19C11F6FA0AF (deleted_by), INDEX IDX_64C19C110BC6D9F (changed_by), INDEX IDX_64C19C1DE12AB56 (created_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT `FK_64C19C110BC6D9F` FOREIGN KEY (changed_by) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT `FK_64C19C11F6FA0AF` FOREIGN KEY (deleted_by) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT `FK_64C19C1DE12AB56` FOREIGN KEY (created_by) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE setting DROP FOREIGN KEY FK_9F74B898DE12AB56');
        $this->addSql('ALTER TABLE setting DROP FOREIGN KEY FK_9F74B89810BC6D9F');
        $this->addSql('ALTER TABLE setting DROP FOREIGN KEY FK_9F74B8981F6FA0AF');
        $this->addSql('DROP TABLE setting');
        $this->addSql('ALTER TABLE blog_category DROP FOREIGN KEY FK_72113DE6DE12AB56');
        $this->addSql('ALTER TABLE blog_category DROP FOREIGN KEY FK_72113DE610BC6D9F');
        $this->addSql('ALTER TABLE blog_category DROP FOREIGN KEY FK_72113DE61F6FA0AF');
        $this->addSql('ALTER TABLE blog_category ADD module VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE blog_category RENAME INDEX idx_72113de61f6fa0af TO IDX_64C19C11F6FA0AF');
        $this->addSql('ALTER TABLE blog_category RENAME INDEX idx_72113de6de12ab56 TO IDX_64C19C1DE12AB56');
        $this->addSql('ALTER TABLE blog_category RENAME INDEX uniq_72113de6989d9b62 TO UNIQ_64C19C1989D9B62');
        $this->addSql('ALTER TABLE blog_category RENAME INDEX idx_72113de610bc6d9f TO IDX_64C19C110BC6D9F');
        $this->addSql('ALTER TABLE blog_post_category DROP FOREIGN KEY FK_CA275A0CA77FBEAF');
        $this->addSql('ALTER TABLE blog_post_category DROP FOREIGN KEY FK_CA275A0CCB76011C');
        $this->addSql('ALTER TABLE blog_post_category ADD CONSTRAINT `FK_CA275A0CA77FBEAF` FOREIGN KEY (blog_post_id) REFERENCES blog_post (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_post_category ADD CONSTRAINT `FK_CA275A0C12469DE2` FOREIGN KEY (blog_category_id) REFERENCES category (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_post_category RENAME INDEX idx_ca275a0ccb76011c TO IDX_CA275A0C12469DE2');
    }
}
