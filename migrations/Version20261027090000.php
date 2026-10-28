<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261027090000 extends AbstractMigration {
    public function getDescription(): string { return 'Vehicle register with unique registration and fuel type'; }
    public function up(Schema $schema): void {
        $this->addSql("CREATE TABLE vehicle (id INT AUTO_INCREMENT NOT NULL, registration VARCHAR(24) NOT NULL, vehicle_type VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_VEHICLE_REG (registration), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE vehicle'); }
}
