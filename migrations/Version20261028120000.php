<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028120000 extends AbstractMigration
{
    public function getDescription(): string { return 'Daily operations report with unique van per date'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE operations_daily (id INT AUTO_INCREMENT NOT NULL, report_date DATE NOT NULL, driver_id INT NOT NULL, van_registration VARCHAR(24) DEFAULT NULL, start_time VARCHAR(5) DEFAULT NULL, comments TEXT NOT NULL, UNIQUE INDEX uniq_operations_driver_day (report_date, driver_id), UNIQUE INDEX uniq_operations_van_day (report_date, van_registration), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE operations_daily'); }
}
