<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add layout_columns (1 or 2) to checklists, existing checklists keep 2 columns';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE checklists ADD layout_columns SMALLINT DEFAULT 2 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE checklists DROP layout_columns');
    }
}
