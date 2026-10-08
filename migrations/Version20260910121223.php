<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260910121223 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE event_image (event_id INT NOT NULL, blog_image_id INT NOT NULL, INDEX IDX_8426B57371F7E88B (event_id), INDEX IDX_8426B57349F8014A (blog_image_id), PRIMARY KEY (event_id, blog_image_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE event_image ADD CONSTRAINT FK_8426B57371F7E88B FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_image ADD CONSTRAINT FK_8426B57349F8014A FOREIGN KEY (blog_image_id) REFERENCES blog_image (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE events DROP FOREIGN KEY `FK_5387574A3DA5256D`');
        $this->addSql('DROP INDEX IDX_5387574A3DA5256D ON events');
        $this->addSql('ALTER TABLE events DROP image_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event_image DROP FOREIGN KEY FK_8426B57371F7E88B');
        $this->addSql('ALTER TABLE event_image DROP FOREIGN KEY FK_8426B57349F8014A');
        $this->addSql('DROP TABLE event_image');
        $this->addSql('ALTER TABLE events ADD image_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT `FK_5387574A3DA5256D` FOREIGN KEY (image_id) REFERENCES blog_image (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_5387574A3DA5256D ON events (image_id)');
    }
}
