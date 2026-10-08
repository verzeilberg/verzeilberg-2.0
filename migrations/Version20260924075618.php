<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260924075618 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blog_post_category DROP FOREIGN KEY `FK_CA275A0CA77FBEAF`');
        $this->addSql('ALTER TABLE blog_post_category DROP FOREIGN KEY `FK_CA275A0CCB76011C`');
        $this->addSql('ALTER TABLE blog_post_category ADD CONSTRAINT FK_CA275A0CA77FBEAF FOREIGN KEY (blog_post_id) REFERENCES blog_post (id)');
        $this->addSql('ALTER TABLE blog_post_category ADD CONSTRAINT FK_CA275A0CCB76011C FOREIGN KEY (blog_category_id) REFERENCES blog_category (id)');
        $this->addSql('ALTER TABLE handbal_rondes ADD keeper_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE handbal_rondes ADD CONSTRAINT FK_48CECC517B7C4783 FOREIGN KEY (keeper_id) REFERENCES handbal_keepers (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_48CECC517B7C4783 ON handbal_rondes (keeper_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blog_post_category DROP FOREIGN KEY FK_CA275A0CA77FBEAF');
        $this->addSql('ALTER TABLE blog_post_category DROP FOREIGN KEY FK_CA275A0CCB76011C');
        $this->addSql('ALTER TABLE blog_post_category ADD CONSTRAINT `FK_CA275A0CA77FBEAF` FOREIGN KEY (blog_post_id) REFERENCES blog_post (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_post_category ADD CONSTRAINT `FK_CA275A0CCB76011C` FOREIGN KEY (blog_category_id) REFERENCES blog_category (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE handbal_rondes DROP FOREIGN KEY FK_48CECC517B7C4783');
        $this->addSql('DROP INDEX IDX_48CECC517B7C4783 ON handbal_rondes');
        $this->addSql('ALTER TABLE handbal_rondes DROP keeper_id');
    }
}
