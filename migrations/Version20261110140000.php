<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261110140000 extends AbstractMigration {
 public function getDescription(): string { return 'Daily lane plan matched to drivers by Transporter ID'; }
 public function up(Schema $schema): void {
  $this->addSql('CREATE TABLE operations_lane_plan (report_date DATE NOT NULL, driver_id INT NOT NULL, color VARCHAR(30) NOT NULL, holding_time VARCHAR(30) NOT NULL, stg_location VARCHAR(40) NOT NULL, route VARCHAR(60) NOT NULL, uploaded_by INT NOT NULL, uploaded_at DATETIME NOT NULL, PRIMARY KEY(report_date, driver_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
 }
 public function down(Schema $schema): void { $this->addSql('DROP TABLE operations_lane_plan'); }
}
