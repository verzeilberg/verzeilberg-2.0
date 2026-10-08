<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the unused public and autocomplete columns from checklists';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE checklists DROP public, DROP autocomplete');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE checklists ADD public SMALLINT DEFAULT 2 NOT NULL, ADD autocomplete SMALLINT DEFAULT 1 NOT NULL');
    }
}
