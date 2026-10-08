<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260819114732 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE blog_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, slug VARCHAR(255) NOT NULL, enabled TINYINT DEFAULT 1 NOT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, UNIQUE INDEX UNIQ_72113DE6989D9B62 (slug), INDEX IDX_72113DE6DE12AB56 (created_by), INDEX IDX_72113DE610BC6D9F (changed_by), INDEX IDX_72113DE61F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE blog_comment (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, is_approved TINYINT DEFAULT 0 NOT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, blog_post_id INT NOT NULL, user_id INT DEFAULT NULL, parent_id INT DEFAULT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_7882EFEFA77FBEAF (blog_post_id), INDEX IDX_7882EFEFA76ED395 (user_id), INDEX IDX_7882EFEF727ACA70 (parent_id), INDEX IDX_7882EFEFDE12AB56 (created_by), INDEX IDX_7882EFEF10BC6D9F (changed_by), INDEX IDX_7882EFEF1F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE blog_file (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, filename VARCHAR(255) NOT NULL, path VARCHAR(500) NOT NULL, mime_type VARCHAR(100) DEFAULT NULL, file_size INT DEFAULT NULL, is_public TINYINT DEFAULT 0 NOT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_6C4FBA80DE12AB56 (created_by), INDEX IDX_6C4FBA8010BC6D9F (changed_by), INDEX IDX_6C4FBA801F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE blog_image (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) DEFAULT NULL, alt VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, sort_order INT DEFAULT 0 NOT NULL, filename VARCHAR(255) NOT NULL, path VARCHAR(500) NOT NULL, mime_type VARCHAR(100) DEFAULT NULL, file_size INT DEFAULT NULL, image_type VARCHAR(50) DEFAULT NULL, width INT DEFAULT NULL, height INT DEFAULT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_35D24797DE12AB56 (created_by), INDEX IDX_35D2479710BC6D9F (changed_by), INDEX IDX_35D247971F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE blog_post (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, intro_text LONGTEXT NOT NULL, content LONGTEXT NOT NULL, status SMALLINT DEFAULT 0 NOT NULL, date_online DATE DEFAULT NULL, date_offline DATE DEFAULT NULL, time_online TIME DEFAULT NULL, time_offline TIME DEFAULT NULL, twittered TINYINT DEFAULT 0 NOT NULL, tweet_id VARCHAR(255) DEFAULT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, UNIQUE INDEX UNIQ_BA5AE01D989D9B62 (slug), INDEX IDX_BA5AE01DDE12AB56 (created_by), INDEX IDX_BA5AE01D10BC6D9F (changed_by), INDEX IDX_BA5AE01D1F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE blog_post_category (blog_post_id INT NOT NULL, blog_category_id INT NOT NULL, INDEX IDX_CA275A0CA77FBEAF (blog_post_id), INDEX IDX_CA275A0CCB76011C (blog_category_id), PRIMARY KEY (blog_post_id, blog_category_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE blog_post_image (blog_post_id INT NOT NULL, blog_image_id INT NOT NULL, INDEX IDX_B4E0AA59A77FBEAF (blog_post_id), INDEX IDX_B4E0AA5949F8014A (blog_image_id), PRIMARY KEY (blog_post_id, blog_image_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE blog_post_file (blog_post_id INT NOT NULL, blog_file_id INT NOT NULL, INDEX IDX_E0B4B954A77FBEAF (blog_post_id), INDEX IDX_E0B4B9547F3DC4EF (blog_file_id), PRIMARY KEY (blog_post_id, blog_file_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE blog_post_youtube (blog_post_id INT NOT NULL, blog_you_tube_id INT NOT NULL, INDEX IDX_974CC180A77FBEAF (blog_post_id), INDEX IDX_974CC18014C42E1D (blog_you_tube_id), PRIMARY KEY (blog_post_id, blog_you_tube_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE blog_youtube (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) DEFAULT NULL, video_id VARCHAR(255) NOT NULL, playlist_id VARCHAR(50) DEFAULT NULL, sort_order INT DEFAULT 0 NOT NULL, auto_play TINYINT DEFAULT 1 NOT NULL, show_controls TINYINT DEFAULT 0 NOT NULL, show_info TINYINT DEFAULT 0 NOT NULL, modest_branding TINYINT DEFAULT 0 NOT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_1C8E24B7DE12AB56 (created_by), INDEX IDX_1C8E24B710BC6D9F (changed_by), INDEX IDX_1C8E24B71F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE blog_category ADD CONSTRAINT FK_72113DE6DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE blog_category ADD CONSTRAINT FK_72113DE610BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE blog_category ADD CONSTRAINT FK_72113DE61F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE blog_comment ADD CONSTRAINT FK_7882EFEFA77FBEAF FOREIGN KEY (blog_post_id) REFERENCES blog_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_comment ADD CONSTRAINT FK_7882EFEFA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE blog_comment ADD CONSTRAINT FK_7882EFEF727ACA70 FOREIGN KEY (parent_id) REFERENCES blog_comment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_comment ADD CONSTRAINT FK_7882EFEFDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE blog_comment ADD CONSTRAINT FK_7882EFEF10BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE blog_comment ADD CONSTRAINT FK_7882EFEF1F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE blog_file ADD CONSTRAINT FK_6C4FBA80DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE blog_file ADD CONSTRAINT FK_6C4FBA8010BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE blog_file ADD CONSTRAINT FK_6C4FBA801F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE blog_image ADD CONSTRAINT FK_35D24797DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE blog_image ADD CONSTRAINT FK_35D2479710BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE blog_image ADD CONSTRAINT FK_35D247971F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE blog_post ADD CONSTRAINT FK_BA5AE01DDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE blog_post ADD CONSTRAINT FK_BA5AE01D10BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE blog_post ADD CONSTRAINT FK_BA5AE01D1F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE blog_post_category ADD CONSTRAINT FK_CA275A0CA77FBEAF FOREIGN KEY (blog_post_id) REFERENCES blog_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_post_category ADD CONSTRAINT FK_CA275A0CCB76011C FOREIGN KEY (blog_category_id) REFERENCES blog_category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_post_image ADD CONSTRAINT FK_B4E0AA59A77FBEAF FOREIGN KEY (blog_post_id) REFERENCES blog_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_post_image ADD CONSTRAINT FK_B4E0AA5949F8014A FOREIGN KEY (blog_image_id) REFERENCES blog_image (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_post_file ADD CONSTRAINT FK_E0B4B954A77FBEAF FOREIGN KEY (blog_post_id) REFERENCES blog_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_post_file ADD CONSTRAINT FK_E0B4B9547F3DC4EF FOREIGN KEY (blog_file_id) REFERENCES blog_file (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_post_youtube ADD CONSTRAINT FK_974CC180A77FBEAF FOREIGN KEY (blog_post_id) REFERENCES blog_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_post_youtube ADD CONSTRAINT FK_974CC18014C42E1D FOREIGN KEY (blog_you_tube_id) REFERENCES blog_youtube (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_youtube ADD CONSTRAINT FK_1C8E24B7DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE blog_youtube ADD CONSTRAINT FK_1C8E24B710BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE blog_youtube ADD CONSTRAINT FK_1C8E24B71F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blog_category DROP FOREIGN KEY FK_72113DE6DE12AB56');
        $this->addSql('ALTER TABLE blog_category DROP FOREIGN KEY FK_72113DE610BC6D9F');
        $this->addSql('ALTER TABLE blog_category DROP FOREIGN KEY FK_72113DE61F6FA0AF');
        $this->addSql('ALTER TABLE blog_comment DROP FOREIGN KEY FK_7882EFEFA77FBEAF');
        $this->addSql('ALTER TABLE blog_comment DROP FOREIGN KEY FK_7882EFEFA76ED395');
        $this->addSql('ALTER TABLE blog_comment DROP FOREIGN KEY FK_7882EFEF727ACA70');
        $this->addSql('ALTER TABLE blog_comment DROP FOREIGN KEY FK_7882EFEFDE12AB56');
        $this->addSql('ALTER TABLE blog_comment DROP FOREIGN KEY FK_7882EFEF10BC6D9F');
        $this->addSql('ALTER TABLE blog_comment DROP FOREIGN KEY FK_7882EFEF1F6FA0AF');
        $this->addSql('ALTER TABLE blog_file DROP FOREIGN KEY FK_6C4FBA80DE12AB56');
        $this->addSql('ALTER TABLE blog_file DROP FOREIGN KEY FK_6C4FBA8010BC6D9F');
        $this->addSql('ALTER TABLE blog_file DROP FOREIGN KEY FK_6C4FBA801F6FA0AF');
        $this->addSql('ALTER TABLE blog_image DROP FOREIGN KEY FK_35D24797DE12AB56');
        $this->addSql('ALTER TABLE blog_image DROP FOREIGN KEY FK_35D2479710BC6D9F');
        $this->addSql('ALTER TABLE blog_image DROP FOREIGN KEY FK_35D247971F6FA0AF');
        $this->addSql('ALTER TABLE blog_post DROP FOREIGN KEY FK_BA5AE01DDE12AB56');
        $this->addSql('ALTER TABLE blog_post DROP FOREIGN KEY FK_BA5AE01D10BC6D9F');
        $this->addSql('ALTER TABLE blog_post DROP FOREIGN KEY FK_BA5AE01D1F6FA0AF');
        $this->addSql('ALTER TABLE blog_post_category DROP FOREIGN KEY FK_CA275A0CA77FBEAF');
        $this->addSql('ALTER TABLE blog_post_category DROP FOREIGN KEY FK_CA275A0CCB76011C');
        $this->addSql('ALTER TABLE blog_post_image DROP FOREIGN KEY FK_B4E0AA59A77FBEAF');
        $this->addSql('ALTER TABLE blog_post_image DROP FOREIGN KEY FK_B4E0AA5949F8014A');
        $this->addSql('ALTER TABLE blog_post_file DROP FOREIGN KEY FK_E0B4B954A77FBEAF');
        $this->addSql('ALTER TABLE blog_post_file DROP FOREIGN KEY FK_E0B4B9547F3DC4EF');
        $this->addSql('ALTER TABLE blog_post_youtube DROP FOREIGN KEY FK_974CC180A77FBEAF');
        $this->addSql('ALTER TABLE blog_post_youtube DROP FOREIGN KEY FK_974CC18014C42E1D');
        $this->addSql('ALTER TABLE blog_youtube DROP FOREIGN KEY FK_1C8E24B7DE12AB56');
        $this->addSql('ALTER TABLE blog_youtube DROP FOREIGN KEY FK_1C8E24B710BC6D9F');
        $this->addSql('ALTER TABLE blog_youtube DROP FOREIGN KEY FK_1C8E24B71F6FA0AF');
        $this->addSql('DROP TABLE blog_category');
        $this->addSql('DROP TABLE blog_comment');
        $this->addSql('DROP TABLE blog_file');
        $this->addSql('DROP TABLE blog_image');
        $this->addSql('DROP TABLE blog_post');
        $this->addSql('DROP TABLE blog_post_category');
        $this->addSql('DROP TABLE blog_post_image');
        $this->addSql('DROP TABLE blog_post_file');
        $this->addSql('DROP TABLE blog_post_youtube');
        $this->addSql('DROP TABLE blog_youtube');
    }
}
