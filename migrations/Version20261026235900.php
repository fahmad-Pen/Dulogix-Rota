<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261026235900 extends AbstractMigration
{
    public function getDescription(): string { return 'Historical week unlock sessions and 12-month correction history'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE rota_week_unlock (id INT AUTO_INCREMENT NOT NULL, week_start DATE NOT NULL, user_id INT NOT NULL, expires_at DATETIME NOT NULL, INDEX idx_week_unlock (week_start, expires_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE rota_week_history (id INT AUTO_INCREMENT NOT NULL, week_start DATE NOT NULL, user_id INT NOT NULL, event_type VARCHAR(30) NOT NULL, details TEXT NOT NULL, created_at DATETIME NOT NULL, INDEX idx_week_history (week_start, created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE rota_week_history');
        $this->addSql('DROP TABLE rota_week_unlock');
    }
}
