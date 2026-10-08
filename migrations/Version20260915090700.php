<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260915090700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE strava_access_token (id INT AUTO_INCREMENT NOT NULL, expires_at DATETIME NOT NULL, refresh_token VARCHAR(255) NOT NULL, access_token VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE strava_activities (id INT AUTO_INCREMENT NOT NULL, activity_id VARCHAR(255) NOT NULL, athlete_id INT NOT NULL, name VARCHAR(255) NOT NULL, distance DOUBLE PRECISION NOT NULL, moving_time INT NOT NULL, elapsed_time INT NOT NULL, total_elevation_gain DOUBLE PRECISION NOT NULL, type VARCHAR(255) NOT NULL, workout_type INT DEFAULT NULL, start_date DATETIME NOT NULL, start_date_local DATETIME NOT NULL, timezone VARCHAR(255) NOT NULL, start_lat DOUBLE PRECISION DEFAULT NULL, start_lng DOUBLE PRECISION DEFAULT NULL, end_lat DOUBLE PRECISION DEFAULT NULL, end_lng DOUBLE PRECISION DEFAULT NULL, summary_polyline LONGTEXT DEFAULT NULL, average_speed DOUBLE PRECISION DEFAULT NULL, average_speed_time TIME DEFAULT NULL, max_speed DOUBLE PRECISION DEFAULT NULL, average_heartrate DOUBLE PRECISION DEFAULT NULL, max_heartrate DOUBLE PRECISION DEFAULT NULL, elev_high DOUBLE PRECISION DEFAULT NULL, elev_low DOUBLE PRECISION DEFAULT NULL, description LONGTEXT DEFAULT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, import_log_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_5E45F3F981C06096 (activity_id), INDEX IDX_5E45F3F99656E79E (import_log_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE strava_activity_import_log (id INT AUTO_INCREMENT NOT NULL, import_date DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE strava_rounds (id INT AUTO_INCREMENT NOT NULL, distance DOUBLE PRECISION NOT NULL, elapsed_time INT NOT NULL, elevation_difference DOUBLE PRECISION DEFAULT NULL, moving_time INT NOT NULL, split INT NOT NULL, average_speed DOUBLE PRECISION DEFAULT NULL, average_heartrate DOUBLE PRECISION DEFAULT NULL, pace_zone INT DEFAULT NULL, date_created DATETIME NOT NULL, activity_id INT NOT NULL, INDEX IDX_76B59FEC81C06096 (activity_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE strava_activities ADD CONSTRAINT FK_5E45F3F99656E79E FOREIGN KEY (import_log_id) REFERENCES strava_activity_import_log (id)');
        $this->addSql('ALTER TABLE strava_rounds ADD CONSTRAINT FK_76B59FEC81C06096 FOREIGN KEY (activity_id) REFERENCES strava_activities (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE strava_activities DROP FOREIGN KEY FK_5E45F3F99656E79E');
        $this->addSql('ALTER TABLE strava_rounds DROP FOREIGN KEY FK_76B59FEC81C06096');
        $this->addSql('DROP TABLE strava_access_token');
        $this->addSql('DROP TABLE strava_activities');
        $this->addSql('DROP TABLE strava_activity_import_log');
        $this->addSql('DROP TABLE strava_rounds');
    }
}
