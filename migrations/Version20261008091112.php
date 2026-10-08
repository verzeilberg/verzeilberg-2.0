<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008091112 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Google Calendar: connected Google accounts (encrypted tokens) and agendas linked to a Google calendar';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE google_account (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, access_token LONGTEXT NOT NULL, refresh_token LONGTEXT NOT NULL, access_token_expires_at DATETIME NOT NULL, connected_at DATETIME NOT NULL, last_error LONGTEXT DEFAULT NULL, connected_by INT DEFAULT NULL, UNIQUE INDEX UNIQ_83726B22E7927C74 (email), INDEX IDX_83726B22F238F2B5 (connected_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE google_account ADD CONSTRAINT FK_83726B22F238F2B5 FOREIGN KEY (connected_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE agenda ADD google_calendar_id VARCHAR(255) DEFAULT NULL, ADD last_synced_at DATETIME DEFAULT NULL, ADD last_sync_error LONGTEXT DEFAULT NULL, ADD google_account_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE agenda ADD CONSTRAINT FK_2CEDC877658AF541 FOREIGN KEY (google_account_id) REFERENCES google_account (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_2CEDC877658AF541 ON agenda (google_account_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE google_account DROP FOREIGN KEY FK_83726B22F238F2B5');
        $this->addSql('DROP TABLE google_account');
        $this->addSql('ALTER TABLE agenda DROP FOREIGN KEY FK_2CEDC877658AF541');
        $this->addSql('DROP INDEX IDX_2CEDC877658AF541 ON agenda');
        $this->addSql('ALTER TABLE agenda DROP google_calendar_id, DROP last_synced_at, DROP last_sync_error, DROP google_account_id');
    }
}
