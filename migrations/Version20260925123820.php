<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260925123820 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE answers (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(255) NOT NULL, value VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE answers_given (id INT AUTO_INCREMENT NOT NULL, answer LONGTEXT DEFAULT NULL, checklist_id INT NOT NULL, checklist_item_id INT NOT NULL, checklist_field_id INT NOT NULL, answer_id INT DEFAULT NULL, INDEX IDX_393EA645B16D08A7 (checklist_id), INDEX IDX_393EA6457E0892A4 (checklist_item_id), INDEX IDX_393EA64519337B72 (checklist_field_id), INDEX IDX_393EA645AA334807 (answer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE checklist_field_types (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, form_type VARCHAR(50) NOT NULL, UNIQUE INDEX UNIQ_645BAF5B5E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE checklist_fields (id INT AUTO_INCREMENT NOT NULL, `order` INT DEFAULT NULL, show_in_overview TINYINT DEFAULT 0 NOT NULL, name VARCHAR(255) NOT NULL, intro_text LONGTEXT DEFAULT NULL, form_field_name VARCHAR(255) NOT NULL, required TINYINT DEFAULT NULL, options LONGTEXT DEFAULT NULL, required_message VARCHAR(255) DEFAULT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, check_list_id INT NOT NULL, check_list_field_type_id INT DEFAULT NULL, parent_id INT DEFAULT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_1FB0F4DC7BB2580B (check_list_id), INDEX IDX_1FB0F4DCF3E3FDB9 (check_list_field_type_id), INDEX IDX_1FB0F4DC727ACA70 (parent_id), INDEX IDX_1FB0F4DCDE12AB56 (created_by), INDEX IDX_1FB0F4DC10BC6D9F (changed_by), INDEX IDX_1FB0F4DC1F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE checklist_field_answers (checklist_field_id INT NOT NULL, answer_id INT NOT NULL, INDEX IDX_4D4CCFC19337B72 (checklist_field_id), INDEX IDX_4D4CCFCAA334807 (answer_id), PRIMARY KEY (checklist_field_id, answer_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE checklist_items (id INT AUTO_INCREMENT NOT NULL, item_content LONGTEXT DEFAULT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, check_list_id INT NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_DFF66E937BB2580B (check_list_id), INDEX IDX_DFF66E93DE12AB56 (created_by), INDEX IDX_DFF66E9310BC6D9F (changed_by), INDEX IDX_DFF66E931F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE checklists (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, public SMALLINT DEFAULT 2 NOT NULL, autocomplete SMALLINT DEFAULT 1 NOT NULL, date_created DATETIME NOT NULL, date_changed DATETIME DEFAULT NULL, date_deleted DATETIME DEFAULT NULL, deleted TINYINT DEFAULT 0 NOT NULL, created_by INT DEFAULT NULL, changed_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, UNIQUE INDEX UNIQ_B0839B3F5E237E06 (name), INDEX IDX_B0839B3FDE12AB56 (created_by), INDEX IDX_B0839B3F10BC6D9F (changed_by), INDEX IDX_B0839B3F1F6FA0AF (deleted_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE answers_given ADD CONSTRAINT FK_393EA645B16D08A7 FOREIGN KEY (checklist_id) REFERENCES checklists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE answers_given ADD CONSTRAINT FK_393EA6457E0892A4 FOREIGN KEY (checklist_item_id) REFERENCES checklist_items (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE answers_given ADD CONSTRAINT FK_393EA64519337B72 FOREIGN KEY (checklist_field_id) REFERENCES checklist_fields (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE answers_given ADD CONSTRAINT FK_393EA645AA334807 FOREIGN KEY (answer_id) REFERENCES answers (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE checklist_fields ADD CONSTRAINT FK_1FB0F4DC7BB2580B FOREIGN KEY (check_list_id) REFERENCES checklists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE checklist_fields ADD CONSTRAINT FK_1FB0F4DCF3E3FDB9 FOREIGN KEY (check_list_field_type_id) REFERENCES checklist_field_types (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE checklist_fields ADD CONSTRAINT FK_1FB0F4DC727ACA70 FOREIGN KEY (parent_id) REFERENCES checklist_fields (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE checklist_fields ADD CONSTRAINT FK_1FB0F4DCDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE checklist_fields ADD CONSTRAINT FK_1FB0F4DC10BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE checklist_fields ADD CONSTRAINT FK_1FB0F4DC1F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE checklist_field_answers ADD CONSTRAINT FK_4D4CCFC19337B72 FOREIGN KEY (checklist_field_id) REFERENCES checklist_fields (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE checklist_field_answers ADD CONSTRAINT FK_4D4CCFCAA334807 FOREIGN KEY (answer_id) REFERENCES answers (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE checklist_items ADD CONSTRAINT FK_DFF66E937BB2580B FOREIGN KEY (check_list_id) REFERENCES checklists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE checklist_items ADD CONSTRAINT FK_DFF66E93DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE checklist_items ADD CONSTRAINT FK_DFF66E9310BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE checklist_items ADD CONSTRAINT FK_DFF66E931F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE checklists ADD CONSTRAINT FK_B0839B3FDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE checklists ADD CONSTRAINT FK_B0839B3F10BC6D9F FOREIGN KEY (changed_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE checklists ADD CONSTRAINT FK_B0839B3F1F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE answers_given DROP FOREIGN KEY FK_393EA645B16D08A7');
        $this->addSql('ALTER TABLE answers_given DROP FOREIGN KEY FK_393EA6457E0892A4');
        $this->addSql('ALTER TABLE answers_given DROP FOREIGN KEY FK_393EA64519337B72');
        $this->addSql('ALTER TABLE answers_given DROP FOREIGN KEY FK_393EA645AA334807');
        $this->addSql('ALTER TABLE checklist_fields DROP FOREIGN KEY FK_1FB0F4DC7BB2580B');
        $this->addSql('ALTER TABLE checklist_fields DROP FOREIGN KEY FK_1FB0F4DCF3E3FDB9');
        $this->addSql('ALTER TABLE checklist_fields DROP FOREIGN KEY FK_1FB0F4DC727ACA70');
        $this->addSql('ALTER TABLE checklist_fields DROP FOREIGN KEY FK_1FB0F4DCDE12AB56');
        $this->addSql('ALTER TABLE checklist_fields DROP FOREIGN KEY FK_1FB0F4DC10BC6D9F');
        $this->addSql('ALTER TABLE checklist_fields DROP FOREIGN KEY FK_1FB0F4DC1F6FA0AF');
        $this->addSql('ALTER TABLE checklist_field_answers DROP FOREIGN KEY FK_4D4CCFC19337B72');
        $this->addSql('ALTER TABLE checklist_field_answers DROP FOREIGN KEY FK_4D4CCFCAA334807');
        $this->addSql('ALTER TABLE checklist_items DROP FOREIGN KEY FK_DFF66E937BB2580B');
        $this->addSql('ALTER TABLE checklist_items DROP FOREIGN KEY FK_DFF66E93DE12AB56');
        $this->addSql('ALTER TABLE checklist_items DROP FOREIGN KEY FK_DFF66E9310BC6D9F');
        $this->addSql('ALTER TABLE checklist_items DROP FOREIGN KEY FK_DFF66E931F6FA0AF');
        $this->addSql('ALTER TABLE checklists DROP FOREIGN KEY FK_B0839B3FDE12AB56');
        $this->addSql('ALTER TABLE checklists DROP FOREIGN KEY FK_B0839B3F10BC6D9F');
        $this->addSql('ALTER TABLE checklists DROP FOREIGN KEY FK_B0839B3F1F6FA0AF');
        $this->addSql('DROP TABLE answers');
        $this->addSql('DROP TABLE answers_given');
        $this->addSql('DROP TABLE checklist_field_types');
        $this->addSql('DROP TABLE checklist_fields');
        $this->addSql('DROP TABLE checklist_field_answers');
        $this->addSql('DROP TABLE checklist_items');
        $this->addSql('DROP TABLE checklists');
    }
}
