<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261024170850 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE rota_audit (id INT AUTO_INCREMENT NOT NULL, week_start DATE NOT NULL, driver_key VARCHAR(80) NOT NULL, field_key VARCHAR(80) NOT NULL, day_index INT NOT NULL, old_value VARCHAR(100) NOT NULL, new_value VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, actor_id INT NOT NULL, INDEX IDX_3ED519DB10DAF24A (actor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE rota_cell (id INT AUTO_INCREMENT NOT NULL, week_start DATE NOT NULL, driver_key VARCHAR(80) NOT NULL, field_key VARCHAR(80) NOT NULL, day_index INT NOT NULL, value VARCHAR(100) NOT NULL, updated_at DATETIME NOT NULL, updated_by_id INT NOT NULL, UNIQUE INDEX uniq_rota_cell (week_start, driver_key, field_key, day_index), INDEX IDX_BA9865C9896DBBDE (updated_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE rota_audit ADD CONSTRAINT FK_3ED519DB10DAF24A FOREIGN KEY (actor_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE rota_cell ADD CONSTRAINT FK_BA9865C9896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rota_audit DROP FOREIGN KEY FK_3ED519DB10DAF24A');
        $this->addSql('ALTER TABLE rota_cell DROP FOREIGN KEY FK_BA9865C9896DBBDE');
        $this->addSql('DROP TABLE rota_audit');
        $this->addSql('DROP TABLE rota_cell');
    }
}
