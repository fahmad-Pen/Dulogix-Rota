<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261024235900 extends AbstractMigration {
 public function getDescription(): string {return 'Reusable dropdown options and app settings';}
 public function up(Schema $schema): void {
  $this->addSql("CREATE TABLE app_setting (`key` VARCHAR(100) NOT NULL, value LONGTEXT NOT NULL, PRIMARY KEY(`key`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
  $this->addSql("CREATE TABLE dropdown_option (id INT AUTO_INCREMENT NOT NULL, list_key VARCHAR(80) NOT NULL, code VARCHAR(24) NOT NULL, label VARCHAR(100) NOT NULL, sort_order INT NOT NULL, active TINYINT(1) NOT NULL, UNIQUE INDEX uniq_dropdown_list_code (list_key, code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
 }
 public function down(Schema $schema): void {
  $this->addSql('DROP TABLE dropdown_option');
  $this->addSql('DROP TABLE app_setting');
 }
}
