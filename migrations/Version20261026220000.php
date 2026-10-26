<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026220000 extends AbstractMigration
{
    public function getDescription(): string { return 'Configurable driver rota status dropdown'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE rota_status_option (code VARCHAR(80) NOT NULL, sort_order INT NOT NULL, active TINYINT(1) NOT NULL, PRIMARY KEY(code))');
        foreach (['Sick', 'Car Breakdown', 'Emergency', 'No Show', 'Full Shift', 'Half Shift'] as $i => $status) {
            $this->addSql('INSERT INTO rota_status_option (code, sort_order, active) VALUES (:code, :sort_order, 1)', ['code' => $status, 'sort_order' => $i + 1]);
        }
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE rota_status_option'); }
}
