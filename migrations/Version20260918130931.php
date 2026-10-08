<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260918130931 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE handbal_keepers (id INT AUTO_INCREMENT NOT NULL, naam VARCHAR(255) NOT NULL, rugnummer VARCHAR(10) DEFAULT NULL, actief TINYINT DEFAULT 1 NOT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_E5475A46DE12AB56 (created_by), INDEX IDX_E5475A4610BC6D9F (changed_by), INDEX IDX_E5475A461F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE handbal_rondes (id INT AUTO_INCREMENT NOT NULL, datum DATE NOT NULL, omschrijving VARCHAR(255) DEFAULT NULL, actief TINYINT DEFAULT 1 NOT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, seizoen_id INT NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_48CECC5185357F53 (seizoen_id), INDEX IDX_48CECC51DE12AB56 (created_by), INDEX IDX_48CECC5110BC6D9F (changed_by), INDEX IDX_48CECC511F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE handbal_scores (id INT AUTO_INCREMENT NOT NULL, gescored TINYINT DEFAULT 0 NOT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, speler_id INT DEFAULT NULL, keeper_id INT NOT NULL, ronde_id INT NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_E6754A522C2B5A45 (speler_id), INDEX IDX_E6754A527B7C4783 (keeper_id), INDEX IDX_E6754A523D1DD13C (ronde_id), INDEX IDX_E6754A52DE12AB56 (created_by), INDEX IDX_E6754A5210BC6D9F (changed_by), INDEX IDX_E6754A521F6FA0AF (deleted_by), UNIQUE INDEX unique_speler_ronde (speler_id, ronde_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE handbal_seizoenen (id INT AUTO_INCREMENT NOT NULL, naam VARCHAR(255) NOT NULL, start_datum DATE NOT NULL, eind_datum DATE NOT NULL, actief TINYINT DEFAULT 1 NOT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_3C679AB3DE12AB56 (created_by), INDEX IDX_3C679AB310BC6D9F (changed_by), INDEX IDX_3C679AB31F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE handbal_spelers (id INT AUTO_INCREMENT NOT NULL, naam VARCHAR(255) NOT NULL, rugnummer VARCHAR(10) DEFAULT NULL, actief TINYINT DEFAULT 1 NOT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_F8A2F3E0DE12AB56 (created_by), INDEX IDX_F8A2F3E010BC6D9F (changed_by), INDEX IDX_F8A2F3E01F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE handbal_keepers ADD CONSTRAINT FK_E5475A46DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE handbal_keepers ADD CONSTRAINT FK_E5475A4610BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE handbal_keepers ADD CONSTRAINT FK_E5475A461F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE handbal_rondes ADD CONSTRAINT FK_48CECC5185357F53 FOREIGN KEY (seizoen_id) REFERENCES handbal_seizoenen (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE handbal_rondes ADD CONSTRAINT FK_48CECC51DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE handbal_rondes ADD CONSTRAINT FK_48CECC5110BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE handbal_rondes ADD CONSTRAINT FK_48CECC511F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE handbal_scores ADD CONSTRAINT FK_E6754A522C2B5A45 FOREIGN KEY (speler_id) REFERENCES handbal_spelers (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE handbal_scores ADD CONSTRAINT FK_E6754A527B7C4783 FOREIGN KEY (keeper_id) REFERENCES handbal_keepers (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE handbal_scores ADD CONSTRAINT FK_E6754A523D1DD13C FOREIGN KEY (ronde_id) REFERENCES handbal_rondes (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE handbal_scores ADD CONSTRAINT FK_E6754A52DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE handbal_scores ADD CONSTRAINT FK_E6754A5210BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE handbal_scores ADD CONSTRAINT FK_E6754A521F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE handbal_seizoenen ADD CONSTRAINT FK_3C679AB3DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE handbal_seizoenen ADD CONSTRAINT FK_3C679AB310BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE handbal_seizoenen ADD CONSTRAINT FK_3C679AB31F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE handbal_spelers ADD CONSTRAINT FK_F8A2F3E0DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE handbal_spelers ADD CONSTRAINT FK_F8A2F3E010BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE handbal_spelers ADD CONSTRAINT FK_F8A2F3E01F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE handbal_keepers DROP FOREIGN KEY FK_E5475A46DE12AB56');
        $this->addSql('ALTER TABLE handbal_keepers DROP FOREIGN KEY FK_E5475A4610BC6D9F');
        $this->addSql('ALTER TABLE handbal_keepers DROP FOREIGN KEY FK_E5475A461F6FA0AF');
        $this->addSql('ALTER TABLE handbal_rondes DROP FOREIGN KEY FK_48CECC5185357F53');
        $this->addSql('ALTER TABLE handbal_rondes DROP FOREIGN KEY FK_48CECC51DE12AB56');
        $this->addSql('ALTER TABLE handbal_rondes DROP FOREIGN KEY FK_48CECC5110BC6D9F');
        $this->addSql('ALTER TABLE handbal_rondes DROP FOREIGN KEY FK_48CECC511F6FA0AF');
        $this->addSql('ALTER TABLE handbal_scores DROP FOREIGN KEY FK_E6754A522C2B5A45');
        $this->addSql('ALTER TABLE handbal_scores DROP FOREIGN KEY FK_E6754A527B7C4783');
        $this->addSql('ALTER TABLE handbal_scores DROP FOREIGN KEY FK_E6754A523D1DD13C');
        $this->addSql('ALTER TABLE handbal_scores DROP FOREIGN KEY FK_E6754A52DE12AB56');
        $this->addSql('ALTER TABLE handbal_scores DROP FOREIGN KEY FK_E6754A5210BC6D9F');
        $this->addSql('ALTER TABLE handbal_scores DROP FOREIGN KEY FK_E6754A521F6FA0AF');
        $this->addSql('ALTER TABLE handbal_seizoenen DROP FOREIGN KEY FK_3C679AB3DE12AB56');
        $this->addSql('ALTER TABLE handbal_seizoenen DROP FOREIGN KEY FK_3C679AB310BC6D9F');
        $this->addSql('ALTER TABLE handbal_seizoenen DROP FOREIGN KEY FK_3C679AB31F6FA0AF');
        $this->addSql('ALTER TABLE handbal_spelers DROP FOREIGN KEY FK_F8A2F3E0DE12AB56');
        $this->addSql('ALTER TABLE handbal_spelers DROP FOREIGN KEY FK_F8A2F3E010BC6D9F');
        $this->addSql('ALTER TABLE handbal_spelers DROP FOREIGN KEY FK_F8A2F3E01F6FA0AF');
        $this->addSql('DROP TABLE handbal_keepers');
        $this->addSql('DROP TABLE handbal_rondes');
        $this->addSql('DROP TABLE handbal_scores');
        $this->addSql('DROP TABLE handbal_seizoenen');
        $this->addSql('DROP TABLE handbal_spelers');
    }
}
