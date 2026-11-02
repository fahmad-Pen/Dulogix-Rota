<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261101220000 extends AbstractMigration
{
 public function getDescription(): string { return 'Weekly DSP summary metrics and unmatched transporter IDs'; }
 public function up(Schema $schema): void {
  $this->addSql('CREATE TABLE driver_dsp_summary (week_start DATE NOT NULL, transporter_id VARCHAR(40) NOT NULL, report_position INT NOT NULL, delivered INT NOT NULL, dcr DECIMAL(6,2) NOT NULL, dsc_dpmo INT NOT NULL, lor_dpmo INT NOT NULL, pod DECIMAL(6,2) NOT NULL, cc DECIMAL(6,2) NOT NULL, ce INT NOT NULL, cdf_dpmo INT DEFAULT NULL, psb DECIMAL(9,2) DEFAULT NULL, PRIMARY KEY(week_start, transporter_id))');
  $this->addSql('CREATE TABLE driver_dsp_unmatched (week_start DATE NOT NULL, transporter_id VARCHAR(40) NOT NULL, ignored TINYINT(1) NOT NULL DEFAULT 0, PRIMARY KEY(week_start, transporter_id))');
 }
 public function down(Schema $schema): void {
  $this->addSql('DROP TABLE driver_dsp_unmatched');
  $this->addSql('DROP TABLE driver_dsp_summary');
 }
}
