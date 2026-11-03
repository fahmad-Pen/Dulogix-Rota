<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101150000 extends AbstractMigration
{
    public function getDescription(): string { return 'Store weekly POD metrics and unmatched IDs without PDF files'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE driver_pod_report (week_start DATE NOT NULL, transporter_id VARCHAR(40) NOT NULL, report_position INT NOT NULL, opportunities INT NOT NULL, success INT NOT NULL, bypass INT NOT NULL, rejects INT NOT NULL, blurry_photo INT NOT NULL, no_package INT NOT NULL, package_in_car INT NOT NULL, package_too_close INT NOT NULL, photo_too_dark INT NOT NULL, PRIMARY KEY(week_start, transporter_id))');
        $this->addSql('CREATE TABLE driver_pod_unmatched (week_start DATE NOT NULL, transporter_id VARCHAR(40) NOT NULL, ignored TINYINT(1) NOT NULL DEFAULT 0, PRIMARY KEY(week_start, transporter_id))');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE driver_pod_unmatched');
        $this->addSql('DROP TABLE driver_pod_report');
    }
}
