<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261025034722 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_setting (`key` VARCHAR(100) NOT NULL, value LONGTEXT NOT NULL, PRIMARY KEY (`key`)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE dropdown_option (id INT AUTO_INCREMENT NOT NULL, list_key VARCHAR(80) NOT NULL, code VARCHAR(24) NOT NULL, label VARCHAR(100) NOT NULL, sort_order INT NOT NULL, active TINYINT NOT NULL, UNIQUE INDEX uniq_dropdown_list_code (list_key, code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE rota_allocation_option (code VARCHAR(24) NOT NULL, label VARCHAR(80) NOT NULL, sort_order INT NOT NULL, active TINYINT NOT NULL, PRIMARY KEY (code)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE app_user ADD driver_type VARCHAR(16) DEFAULT \'DRIVER\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE app_setting');
        $this->addSql('DROP TABLE dropdown_option');
        $this->addSql('DROP TABLE rota_allocation_option');
        $this->addSql('ALTER TABLE app_user DROP driver_type');
    }
}
