<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261029100000 extends AbstractMigration {
 public function getDescription():string{return 'Permanent default van associations for drivers';}
 public function up(Schema $schema):void {
  $this->addSql('CREATE TABLE operations_fixed_van (driver_id INT NOT NULL, van_registration VARCHAR(24) NOT NULL, UNIQUE INDEX uniq_fixed_van (van_registration), PRIMARY KEY(driver_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
 }
 public function down(Schema $schema):void{$this->addSql('DROP TABLE operations_fixed_van');}
}
