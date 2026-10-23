<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Setting: index name after quoting the reserved column name "key"';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE setting RENAME INDEX uniq_9f74b8988a90aba9 TO UNIQ_9F74B8984E645A7E');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE setting RENAME INDEX uniq_9f74b8984e645a7e TO UNIQ_9F74B8988A90ABA9');
    }
}
