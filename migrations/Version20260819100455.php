<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260819100455 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE menu (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_7D053A93DE12AB56 (created_by), INDEX IDX_7D053A9310BC6D9F (changed_by), INDEX IDX_7D053A931F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE menu_items (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(255) NOT NULL, link VARCHAR(255) DEFAULT NULL, icon VARCHAR(255) DEFAULT NULL, sort_order INT NOT NULL, authorized_for VARCHAR(255) DEFAULT NULL, enabled TINYINT DEFAULT 1 NOT NULL, open_in_new_tab TINYINT DEFAULT 0 NOT NULL, css_class VARCHAR(50) DEFAULT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, parent_id INT DEFAULT NULL, menu_id INT DEFAULT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_70B2CA2A727ACA70 (parent_id), INDEX IDX_70B2CA2ACCD7E912 (menu_id), INDEX IDX_70B2CA2ADE12AB56 (created_by), INDEX IDX_70B2CA2A10BC6D9F (changed_by), INDEX IDX_70B2CA2A1F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE permission (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, UNIQUE INDEX UNIQ_E04992AA5E237E06 (name), INDEX IDX_E04992AADE12AB56 (created_by), INDEX IDX_E04992AA10BC6D9F (changed_by), INDEX IDX_E04992AA1F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE role (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, UNIQUE INDEX UNIQ_57698A6A5E237E06 (name), INDEX IDX_57698A6ADE12AB56 (created_by), INDEX IDX_57698A6A10BC6D9F (changed_by), INDEX IDX_57698A6A1F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE role_hierarchy (parent_role_id INT NOT NULL, child_role_id INT NOT NULL, INDEX IDX_AB8EFB72A44B56EA (parent_role_id), INDEX IDX_AB8EFB72B4B76AB7 (child_role_id), PRIMARY KEY (parent_role_id, child_role_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE role_permission (role_id INT NOT NULL, permission_id INT NOT NULL, INDEX IDX_6F7DF886D60322AC (role_id), INDEX IDX_6F7DF886FED90CCA (permission_id), PRIMARY KEY (role_id, permission_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, full_name VARCHAR(255) NOT NULL, status SMALLINT DEFAULT 1 NOT NULL, password_reset_token VARCHAR(255) DEFAULT NULL, password_reset_token_creation_date DATETIME DEFAULT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), INDEX IDX_8D93D649DE12AB56 (created_by), INDEX IDX_8D93D64910BC6D9F (changed_by), INDEX IDX_8D93D6491F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_role (user_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_2DE8C6A3A76ED395 (user_id), INDEX IDX_2DE8C6A3D60322AC (role_id), PRIMARY KEY (user_id, role_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_details (id INT AUTO_INCREMENT NOT NULL, first_name VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, birth_date DATE DEFAULT NULL, bio LONGTEXT DEFAULT NULL, catch_line VARCHAR(255) DEFAULT NULL, avatar VARCHAR(255) DEFAULT NULL, phone VARCHAR(255) DEFAULT NULL, website VARCHAR(255) DEFAULT NULL, location VARCHAR(100) DEFAULT NULL, social_links JSON DEFAULT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, user_id INT NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, UNIQUE INDEX UNIQ_2A2B1580A76ED395 (user_id), INDEX IDX_2A2B1580DE12AB56 (created_by), INDEX IDX_2A2B158010BC6D9F (changed_by), INDEX IDX_2A2B15801F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE menu ADD CONSTRAINT FK_7D053A93DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE menu ADD CONSTRAINT FK_7D053A9310BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE menu ADD CONSTRAINT FK_7D053A931F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE menu_items ADD CONSTRAINT FK_70B2CA2A727ACA70 FOREIGN KEY (parent_id) REFERENCES menu_items (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_items ADD CONSTRAINT FK_70B2CA2ACCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_items ADD CONSTRAINT FK_70B2CA2ADE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE menu_items ADD CONSTRAINT FK_70B2CA2A10BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE menu_items ADD CONSTRAINT FK_70B2CA2A1F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE permission ADD CONSTRAINT FK_E04992AADE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE permission ADD CONSTRAINT FK_E04992AA10BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE permission ADD CONSTRAINT FK_E04992AA1F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE role ADD CONSTRAINT FK_57698A6ADE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE role ADD CONSTRAINT FK_57698A6A10BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE role ADD CONSTRAINT FK_57698A6A1F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE role_hierarchy ADD CONSTRAINT FK_AB8EFB72A44B56EA FOREIGN KEY (parent_role_id) REFERENCES role (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_hierarchy ADD CONSTRAINT FK_AB8EFB72B4B76AB7 FOREIGN KEY (child_role_id) REFERENCES role (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_permission ADD CONSTRAINT FK_6F7DF886D60322AC FOREIGN KEY (role_id) REFERENCES role (id)');
        $this->addSql('ALTER TABLE role_permission ADD CONSTRAINT FK_6F7DF886FED90CCA FOREIGN KEY (permission_id) REFERENCES permission (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D64910BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D6491F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user_role ADD CONSTRAINT FK_2DE8C6A3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_role ADD CONSTRAINT FK_2DE8C6A3D60322AC FOREIGN KEY (role_id) REFERENCES role (id)');
        $this->addSql('ALTER TABLE user_details ADD CONSTRAINT FK_2A2B1580A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_details ADD CONSTRAINT FK_2A2B1580DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user_details ADD CONSTRAINT FK_2A2B158010BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user_details ADD CONSTRAINT FK_2A2B15801F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE menu DROP FOREIGN KEY FK_7D053A93DE12AB56');
        $this->addSql('ALTER TABLE menu DROP FOREIGN KEY FK_7D053A9310BC6D9F');
        $this->addSql('ALTER TABLE menu DROP FOREIGN KEY FK_7D053A931F6FA0AF');
        $this->addSql('ALTER TABLE menu_items DROP FOREIGN KEY FK_70B2CA2A727ACA70');
        $this->addSql('ALTER TABLE menu_items DROP FOREIGN KEY FK_70B2CA2ACCD7E912');
        $this->addSql('ALTER TABLE menu_items DROP FOREIGN KEY FK_70B2CA2ADE12AB56');
        $this->addSql('ALTER TABLE menu_items DROP FOREIGN KEY FK_70B2CA2A10BC6D9F');
        $this->addSql('ALTER TABLE menu_items DROP FOREIGN KEY FK_70B2CA2A1F6FA0AF');
        $this->addSql('ALTER TABLE permission DROP FOREIGN KEY FK_E04992AADE12AB56');
        $this->addSql('ALTER TABLE permission DROP FOREIGN KEY FK_E04992AA10BC6D9F');
        $this->addSql('ALTER TABLE permission DROP FOREIGN KEY FK_E04992AA1F6FA0AF');
        $this->addSql('ALTER TABLE role DROP FOREIGN KEY FK_57698A6ADE12AB56');
        $this->addSql('ALTER TABLE role DROP FOREIGN KEY FK_57698A6A10BC6D9F');
        $this->addSql('ALTER TABLE role DROP FOREIGN KEY FK_57698A6A1F6FA0AF');
        $this->addSql('ALTER TABLE role_hierarchy DROP FOREIGN KEY FK_AB8EFB72A44B56EA');
        $this->addSql('ALTER TABLE role_hierarchy DROP FOREIGN KEY FK_AB8EFB72B4B76AB7');
        $this->addSql('ALTER TABLE role_permission DROP FOREIGN KEY FK_6F7DF886D60322AC');
        $this->addSql('ALTER TABLE role_permission DROP FOREIGN KEY FK_6F7DF886FED90CCA');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649DE12AB56');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D64910BC6D9F');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D6491F6FA0AF');
        $this->addSql('ALTER TABLE user_role DROP FOREIGN KEY FK_2DE8C6A3A76ED395');
        $this->addSql('ALTER TABLE user_role DROP FOREIGN KEY FK_2DE8C6A3D60322AC');
        $this->addSql('ALTER TABLE user_details DROP FOREIGN KEY FK_2A2B1580A76ED395');
        $this->addSql('ALTER TABLE user_details DROP FOREIGN KEY FK_2A2B1580DE12AB56');
        $this->addSql('ALTER TABLE user_details DROP FOREIGN KEY FK_2A2B158010BC6D9F');
        $this->addSql('ALTER TABLE user_details DROP FOREIGN KEY FK_2A2B15801F6FA0AF');
        $this->addSql('DROP TABLE menu');
        $this->addSql('DROP TABLE menu_items');
        $this->addSql('DROP TABLE permission');
        $this->addSql('DROP TABLE role');
        $this->addSql('DROP TABLE role_hierarchy');
        $this->addSql('DROP TABLE role_permission');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_role');
        $this->addSql('DROP TABLE user_details');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
