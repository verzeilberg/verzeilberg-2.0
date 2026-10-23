<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Checklists get a default sort order for their item lists, on up to three levels (empty: creation date, as until now)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE checklists ADD default_order JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE checklists DROP default_order');
    }
}
