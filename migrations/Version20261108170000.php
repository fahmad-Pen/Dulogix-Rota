<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261108170000 extends AbstractMigration
{
    public function getDescription(): string { return 'Manager overrides for weekly Ementor scores'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE driver_ementor_overrides (driver_id INT NOT NULL, week_start DATE NOT NULL, score DECIMAL(6,2) NOT NULL, updated_by INT NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(driver_id, week_start))');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE driver_ementor_overrides');
    }
}
