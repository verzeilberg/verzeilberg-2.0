<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make form_field_name unique per checklist (answers are stored per field_<form_field_name>)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uniq_checklist_field_form_name ON checklist_fields (check_list_id, form_field_name)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_checklist_field_form_name ON checklist_fields');
    }
}
