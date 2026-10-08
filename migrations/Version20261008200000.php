<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Steam game library: the profile and its games';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE steam_account (id INT AUTO_INCREMENT NOT NULL, steam_id VARCHAR(20) NOT NULL, persona_name VARCHAR(255) DEFAULT NULL, avatar_url VARCHAR(500) DEFAULT NULL, profile_url VARCHAR(500) DEFAULT NULL, currently_playing VARCHAR(255) DEFAULT NULL, last_synced_at DATETIME DEFAULT NULL, last_sync_error LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_F1D0B734F3FD4ECA (steam_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE steam_game (id INT AUTO_INCREMENT NOT NULL, app_id INT NOT NULL, name VARCHAR(255) NOT NULL, playtime_forever INT DEFAULT 0 NOT NULL, playtime_two_weeks INT DEFAULT 0 NOT NULL, last_played_at DATETIME DEFAULT NULL, icon_hash VARCHAR(64) DEFAULT NULL, header_image VARCHAR(500) DEFAULT NULL, short_description LONGTEXT DEFAULT NULL, genres JSON NOT NULL, developers JSON NOT NULL, publishers JSON NOT NULL, release_date VARCHAR(50) DEFAULT NULL, website VARCHAR(500) DEFAULT NULL, metacritic_score INT DEFAULT NULL, details_fetched_at DATETIME DEFAULT NULL, achievements_total INT DEFAULT NULL, achievements_unlocked INT DEFAULT NULL, achievements_playtime INT DEFAULT NULL, hidden TINYINT DEFAULT 0 NOT NULL, added_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_EA3787B47987212D (app_id), INDEX idx_steam_game_last_played (last_played_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE steam_account');
        $this->addSql('DROP TABLE steam_game');
    }
}
