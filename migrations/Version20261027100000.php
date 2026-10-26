<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261027100000 extends AbstractMigration {
 public function getDescription(): string { return 'Permanent driver training checklist definitions and completion audit'; }
 public function up(Schema $schema): void {
  $this->addSql("CREATE TABLE driver_requirement (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, sort_order INT NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB");
  $this->addSql("CREATE TABLE driver_requirement_completion (requirement_id INT NOT NULL, driver_id INT NOT NULL, completed_at DATETIME NOT NULL, completed_by_id INT NOT NULL, PRIMARY KEY(requirement_id,driver_id), INDEX IDX_DRC_DRIVER (driver_id), INDEX IDX_DRC_ACTOR (completed_by_id), CONSTRAINT FK_DRC_REQ FOREIGN KEY (requirement_id) REFERENCES driver_requirement (id), CONSTRAINT FK_DRC_DRIVER FOREIGN KEY (driver_id) REFERENCES app_user (id), CONSTRAINT FK_DRC_ACTOR FOREIGN KEY (completed_by_id) REFERENCES app_user (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB");
  $this->addSql("CREATE TABLE driver_requirement_event (id INT AUTO_INCREMENT NOT NULL, requirement_id INT NOT NULL, driver_id INT NOT NULL, actor_id INT NOT NULL, action VARCHAR(12) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_DRE_REQ (requirement_id), INDEX IDX_DRE_DRIVER (driver_id), INDEX IDX_DRE_ACTOR (actor_id), PRIMARY KEY(id), CONSTRAINT FK_DRE_REQ FOREIGN KEY (requirement_id) REFERENCES driver_requirement (id), CONSTRAINT FK_DRE_DRIVER FOREIGN KEY (driver_id) REFERENCES app_user (id), CONSTRAINT FK_DRE_ACTOR FOREIGN KEY (actor_id) REFERENCES app_user (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB");
  foreach (['WISE','Cognition','Ementor','Clear Quote','Badge'] as $i=>$name) $this->addSql("INSERT INTO driver_requirement (name,sort_order,created_at) VALUES ('".$name."',".($i+1).",NOW())");
 }
 public function down(Schema $schema): void { $this->addSql('DROP TABLE driver_requirement_event');$this->addSql('DROP TABLE driver_requirement_completion');$this->addSql('DROP TABLE driver_requirement'); }
}
