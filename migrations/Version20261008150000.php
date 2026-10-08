<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add items_per_page to checklists, existing checklists keep 20 items per page';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE checklists ADD items_per_page SMALLINT DEFAULT 20 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE checklists DROP items_per_page');
    }
}
