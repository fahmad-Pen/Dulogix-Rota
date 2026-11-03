<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102020000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE driver_dwc_unmatched (week_start DATE NOT NULL, transporter_id VARCHAR(40) NOT NULL, ignored TINYINT(1) NOT NULL DEFAULT 0, PRIMARY KEY (week_start, transporter_id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE driver_dwc_unmatched');
    }
}
