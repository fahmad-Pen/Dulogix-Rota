<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261108190000 extends AbstractMigration {
 public function getDescription(): string { return 'Preserve manager corrections separately from driver daily shift submissions'; }
 public function up(Schema $schema): void {
  $this->addSql('CREATE TABLE operations_daily_score_override (driver_id INT NOT NULL, report_date DATE NOT NULL, fisco_score SMALLINT DEFAULT NULL, returns_count INT DEFAULT NULL, updated_by INT NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(driver_id, report_date)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
 }
 public function down(Schema $schema): void { $this->addSql('DROP TABLE operations_daily_score_override'); }
}
