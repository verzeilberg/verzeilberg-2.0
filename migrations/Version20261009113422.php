<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009113422 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Checklists get an owner; permissions checklist.own and checklist.manage, and a route rule that opens the checklist admin for checklist.own';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE checklists ADD owner_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE checklists ADD CONSTRAINT FK_B0839B3F7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_B0839B3F7E3C61F9 ON checklists (owner_id)');

        // Existing checklists keep no owner: they stay site checklists, managed by admins
        $permissions = [
            'checklist.own' => 'Make checklists and manage your own (from the profile page)',
            'checklist.manage' => 'Manage every checklist, the field types and the shared answers',
        ];
        foreach ($permissions as $name => $description) {
            $this->addSql(
                'INSERT INTO permission (name, description, date_created, deleted) SELECT :name, :description, NOW(), 0 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = :name)',
                ['name' => $name, 'description' => $description],
            );
        }

        // Opens the checklist admin for checklist.own; which checklists they see is checked in the controller
        $this->addSql("INSERT INTO route_permission (route_pattern, permission_id, methods, enabled, description, date_created, deleted) SELECT 'admin_checklist_*', id, NULL, 1, 'Own checklists (only their own; checklist.manage for all)', NOW(), 0 FROM permission WHERE name = 'checklist.own' AND NOT EXISTS (SELECT 1 FROM route_permission WHERE route_pattern = 'admin_checklist_*')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM permission WHERE name IN ('checklist.own', 'checklist.manage')");
        $this->addSql('ALTER TABLE checklists DROP FOREIGN KEY FK_B0839B3F7E3C61F9');
        $this->addSql('DROP INDEX IDX_B0839B3F7E3C61F9 ON checklists');
        $this->addSql('ALTER TABLE checklists DROP owner_id');
    }
}
