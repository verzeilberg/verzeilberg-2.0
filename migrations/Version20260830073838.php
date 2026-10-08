<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260830073838 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE route_permission (id INT AUTO_INCREMENT NOT NULL, route_pattern VARCHAR(255) NOT NULL, permission_name VARCHAR(100) NOT NULL, methods JSON DEFAULT NULL, enabled TINYINT DEFAULT 1 NOT NULL, priority INT DEFAULT 0 NOT NULL, description LONGTEXT DEFAULT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_EE3E584DDE12AB56 (created_by), INDEX IDX_EE3E584D10BC6D9F (changed_by), INDEX IDX_EE3E584D1F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE route_permission ADD CONSTRAINT FK_EE3E584DDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE route_permission ADD CONSTRAINT FK_EE3E584D10BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE route_permission ADD CONSTRAINT FK_EE3E584D1F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE route_permission DROP FOREIGN KEY FK_EE3E584DDE12AB56');
        $this->addSql('ALTER TABLE route_permission DROP FOREIGN KEY FK_EE3E584D10BC6D9F');
        $this->addSql('ALTER TABLE route_permission DROP FOREIGN KEY FK_EE3E584D1F6FA0AF');
        $this->addSql('DROP TABLE route_permission');
    }
}
