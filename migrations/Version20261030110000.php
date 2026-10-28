<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261030110000 extends AbstractMigration {
    public function getDescription(): string { return 'Keep vehicles and mark grounded ones unavailable for operations'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE vehicle ADD grounded TINYINT(1) NOT NULL DEFAULT 0'); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE vehicle DROP grounded'); }
}
