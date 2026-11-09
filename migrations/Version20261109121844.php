<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109121844 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Videos with a blog post that only logged in visitors can watch (files in var/storage/blog-videos)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE blog_video (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) DEFAULT NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255) DEFAULT NULL, mime_type VARCHAR(100) NOT NULL, file_size BIGINT NOT NULL, sort_order INT DEFAULT 0 NOT NULL, date_created DATETIME NOT NULL, blog_post_id INT NOT NULL, INDEX IDX_8C2899E4A77FBEAF (blog_post_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE blog_video ADD CONSTRAINT FK_8C2899E4A77FBEAF FOREIGN KEY (blog_post_id) REFERENCES blog_post (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog_video DROP FOREIGN KEY FK_8C2899E4A77FBEAF');
        $this->addSql('DROP TABLE blog_video');
    }
}
