<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261030100000 extends AbstractMigration {
 public function getDescription(): string { return 'Admin-managed WhatsApp quick message templates'; }
 public function up(Schema $schema): void {
  $this->addSql('CREATE TABLE whatsapp_quick_message (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(80) NOT NULL, message TEXT NOT NULL, sort_order INT NOT NULL DEFAULT 0, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
 }
 public function down(Schema $schema): void { $this->addSql('DROP TABLE whatsapp_quick_message'); }
}
