<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260827135023 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // First drop FK from events table referencing event_categories
        $this->addSql('ALTER TABLE events DROP FOREIGN KEY `FK_5387574A12469DE2`');
        // Then drop FKs from event_categories table
        $this->addSql('ALTER TABLE event_categories DROP FOREIGN KEY `FK_621D9F4710BC6D9F`');
        $this->addSql('ALTER TABLE event_categories DROP FOREIGN KEY `FK_621D9F471F6FA0AF`');
        $this->addSql('ALTER TABLE event_categories DROP FOREIGN KEY `FK_621D9F4793CB796C`');
        $this->addSql('ALTER TABLE event_categories DROP FOREIGN KEY `FK_621D9F47DE12AB56`');
        // Now we can drop the table
        $this->addSql('DROP TABLE event_categories');
        // Add new FK to blog_category
        $this->addSql('ALTER TABLE events ADD CONSTRAINT FK_5387574A12469DE2 FOREIGN KEY (category_id) REFERENCES blog_category (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE event_categories (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, file_id INT DEFAULT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_621D9F4710BC6D9F (changed_by), INDEX IDX_621D9F471F6FA0AF (deleted_by), INDEX IDX_621D9F47DE12AB56 (created_by), UNIQUE INDEX UNIQ_621D9F4793CB796C (file_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE event_categories ADD CONSTRAINT `FK_621D9F4710BC6D9F` FOREIGN KEY (changed_by) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE event_categories ADD CONSTRAINT `FK_621D9F471F6FA0AF` FOREIGN KEY (deleted_by) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE event_categories ADD CONSTRAINT `FK_621D9F4793CB796C` FOREIGN KEY (file_id) REFERENCES blog_file (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE event_categories ADD CONSTRAINT `FK_621D9F47DE12AB56` FOREIGN KEY (created_by) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE events DROP FOREIGN KEY FK_5387574A12469DE2');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT `FK_5387574A12469DE2` FOREIGN KEY (category_id) REFERENCES event_categories (id) ON UPDATE NO ACTION ON DELETE SET NULL');
    }
}
