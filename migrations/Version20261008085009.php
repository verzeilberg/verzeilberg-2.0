<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008085009 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agenda module: agendas (colour, public, iCal feed token) and their items';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE agenda (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, color VARCHAR(7) NOT NULL, is_public TINYINT DEFAULT 0 NOT NULL, feed_token VARCHAR(64) NOT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, UNIQUE INDEX UNIQ_2CEDC877AAB8F287 (feed_token), INDEX IDX_2CEDC877DE12AB56 (created_by), INDEX IDX_2CEDC87710BC6D9F (changed_by), INDEX IDX_2CEDC8771F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE agenda_item (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, start_at DATETIME NOT NULL, end_at DATETIME NOT NULL, all_day TINYINT DEFAULT 0 NOT NULL, uid VARCHAR(255) DEFAULT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, agenda_id INT NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_223E876EEA67784A (agenda_id), INDEX IDX_223E876EDE12AB56 (created_by), INDEX IDX_223E876E10BC6D9F (changed_by), INDEX IDX_223E876E1F6FA0AF (deleted_by), INDEX idx_agenda_item_period (start_at, end_at), UNIQUE INDEX uniq_agenda_item_uid (agenda_id, uid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE agenda ADD CONSTRAINT FK_2CEDC877DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE agenda ADD CONSTRAINT FK_2CEDC87710BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE agenda ADD CONSTRAINT FK_2CEDC8771F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE agenda_item ADD CONSTRAINT FK_223E876EEA67784A FOREIGN KEY (agenda_id) REFERENCES agenda (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE agenda_item ADD CONSTRAINT FK_223E876EDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE agenda_item ADD CONSTRAINT FK_223E876E10BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE agenda_item ADD CONSTRAINT FK_223E876E1F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE agenda DROP FOREIGN KEY FK_2CEDC877DE12AB56');
        $this->addSql('ALTER TABLE agenda DROP FOREIGN KEY FK_2CEDC87710BC6D9F');
        $this->addSql('ALTER TABLE agenda DROP FOREIGN KEY FK_2CEDC8771F6FA0AF');
        $this->addSql('ALTER TABLE agenda_item DROP FOREIGN KEY FK_223E876EEA67784A');
        $this->addSql('ALTER TABLE agenda_item DROP FOREIGN KEY FK_223E876EDE12AB56');
        $this->addSql('ALTER TABLE agenda_item DROP FOREIGN KEY FK_223E876E10BC6D9F');
        $this->addSql('ALTER TABLE agenda_item DROP FOREIGN KEY FK_223E876E1F6FA0AF');
        $this->addSql('DROP TABLE agenda');
        $this->addSql('DROP TABLE agenda_item');
    }
}
