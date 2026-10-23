<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009112110 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Route permissions point to a Permission (was free text) and match route names; role permissions cascade on delete; adds admin.access';
    }

    public function up(Schema $schema): void
    {
        // Old rules stored URL paths and were matched against route names (and the listener was never
        // registered), so none of them ever applied; they can't be converted to the new required permission_id
        $this->addSql('DELETE FROM route_permission');

        // Needed to enter the admin as a non-admin; ROLE_ADMIN doesn't need it
        $this->addSql("INSERT INTO permission (name, description, date_created, deleted) SELECT 'admin.access', 'Enter the admin panel (dashboard)', NOW(), 0 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'admin.access')");

        $this->addSql('ALTER TABLE role_permission DROP FOREIGN KEY `FK_6F7DF886D60322AC`');
        $this->addSql('ALTER TABLE role_permission DROP FOREIGN KEY `FK_6F7DF886FED90CCA`');
        $this->addSql('ALTER TABLE role_permission ADD CONSTRAINT FK_6F7DF886D60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_permission ADD CONSTRAINT FK_6F7DF886FED90CCA FOREIGN KEY (permission_id) REFERENCES permission (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE route_permission ADD permission_id INT NOT NULL, DROP permission_name, DROP priority');
        $this->addSql('ALTER TABLE route_permission ADD CONSTRAINT FK_EE3E584DFED90CCA FOREIGN KEY (permission_id) REFERENCES permission (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_EE3E584DFED90CCA ON route_permission (permission_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE role_permission DROP FOREIGN KEY FK_6F7DF886D60322AC');
        $this->addSql('ALTER TABLE role_permission DROP FOREIGN KEY FK_6F7DF886FED90CCA');
        $this->addSql('ALTER TABLE role_permission ADD CONSTRAINT `FK_6F7DF886D60322AC` FOREIGN KEY (role_id) REFERENCES role (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE role_permission ADD CONSTRAINT `FK_6F7DF886FED90CCA` FOREIGN KEY (permission_id) REFERENCES permission (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE route_permission DROP FOREIGN KEY FK_EE3E584DFED90CCA');
        $this->addSql('DROP INDEX IDX_EE3E584DFED90CCA ON route_permission');
        $this->addSql('DELETE FROM route_permission');
        $this->addSql('ALTER TABLE route_permission ADD permission_name VARCHAR(100) NOT NULL, ADD priority INT DEFAULT 0 NOT NULL, DROP permission_id');
    }
}
