<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261102010000 extends AbstractMigration
{
    public function getDescription(): string { return 'Weekly DWC and IADC compliance percentages'; }
    public function isTransactional(): bool { return false; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE driver_dwc_iadc (week_start DATE NOT NULL, transporter_id VARCHAR(40) NOT NULL, report_position INT NOT NULL, dwc_percent DECIMAL(6,2) NOT NULL, iadc_percent DECIMAL(6,2) NOT NULL, PRIMARY KEY(week_start, transporter_id))');
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE driver_dwc_iadc'); }
}
