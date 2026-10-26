<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261029110000 extends AbstractMigration {
 public function getDescription():string{return 'Daily driver pre-shift and post-shift records';}
 public function up(Schema $schema):void{$this->addSql('CREATE TABLE driver_daily_check (id INT AUTO_INCREMENT NOT NULL, shift_date DATE NOT NULL, driver_id INT NOT NULL, ementor TINYINT(1) NOT NULL DEFAULT 0, clear_quote TINYINT(1) NOT NULL DEFAULT 0, fisco_score SMALLINT DEFAULT NULL, returns_count INT DEFAULT NULL, pre_updated_at DATETIME DEFAULT NULL, post_updated_at DATETIME DEFAULT NULL, UNIQUE INDEX uniq_driver_shift_date (driver_id, shift_date), INDEX idx_driver_shift_retention (shift_date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');}
 public function down(Schema $schema):void{$this->addSql('DROP TABLE driver_daily_check');}
}
