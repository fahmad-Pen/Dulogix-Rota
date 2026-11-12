<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261112120000 extends AbstractMigration {
 public function getDescription(): string { return 'Save weekly Top 5 threshold and manager-selected drivers'; }
 public function up(Schema $schema): void {
  $this->addSql('CREATE TABLE driver_performance_top_five (week_start DATE NOT NULL, min_delivered INT NOT NULL DEFAULT 0, selected_ids_json LONGTEXT NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(week_start)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
 }
 public function down(Schema $schema): void { $this->addSql('DROP TABLE driver_performance_top_five'); }
}
