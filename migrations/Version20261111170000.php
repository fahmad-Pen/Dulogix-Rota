<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261111170000 extends AbstractMigration {
 public function getDescription():string{return 'Record driver acknowledgement of published instructions';}
 public function up(Schema $schema):void {
  $this->addSql('CREATE TABLE operations_instruction_ack (report_date DATE NOT NULL, driver_id INT NOT NULL, instruction_hash CHAR(64) NOT NULL, instruction_snapshot LONGTEXT NOT NULL, acknowledged_at DATETIME NOT NULL, PRIMARY KEY(report_date, driver_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
 }
 public function down(Schema $schema):void {$this->addSql('DROP TABLE operations_instruction_ack');}
}
