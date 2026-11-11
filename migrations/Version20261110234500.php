<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261110234500 extends AbstractMigration {
 public function getDescription(): string { return 'Allow cleared manager FICO scores in audit'; }
 public function up(Schema $schema): void { $this->addSql('ALTER TABLE operations_fico_audit MODIFY new_score SMALLINT DEFAULT NULL'); }
 public function down(Schema $schema): void {
  $this->addSql('UPDATE operations_fico_audit SET new_score=0 WHERE new_score IS NULL');
  $this->addSql('ALTER TABLE operations_fico_audit MODIFY new_score SMALLINT NOT NULL');
 }
}
