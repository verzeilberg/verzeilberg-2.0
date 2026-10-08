<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add access (admin/user/public) to checklists; existing checklists stay admin only';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE checklists ADD access VARCHAR(10) DEFAULT 'admin' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE checklists DROP access');
    }
}
