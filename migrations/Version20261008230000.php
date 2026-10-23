<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Steam: game reviews (rating 1-5 and text)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE steam_game_review (id INT AUTO_INCREMENT NOT NULL, rating SMALLINT NOT NULL, body LONGTEXT NOT NULL, published TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE steam_game ADD review_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE steam_game ADD CONSTRAINT FK_EA3787B43E2E969B FOREIGN KEY (review_id) REFERENCES steam_game_review (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EA3787B43E2E969B ON steam_game (review_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        // The foreign key on steam_game first, then the table it points to
        $this->addSql('ALTER TABLE steam_game DROP FOREIGN KEY FK_EA3787B43E2E969B');
        $this->addSql('DROP INDEX UNIQ_EA3787B43E2E969B ON steam_game');
        $this->addSql('ALTER TABLE steam_game DROP review_id');
        $this->addSql('DROP TABLE steam_game_review');
    }
}
