<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101120000 extends AbstractMigration
{
    public function getDescription(): string { return 'Store weekly driver Contact Compliance report metrics'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE driver_contact_compliance (week_start DATE NOT NULL, transporter_id VARCHAR(40) NOT NULL, total_addresses INT NOT NULL, total_contacts INT NOT NULL, compliance_percent DECIMAL(5,2) NOT NULL, PRIMARY KEY(week_start, transporter_id))');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE driver_contact_compliance');
    }
}
