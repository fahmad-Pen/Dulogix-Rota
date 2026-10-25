<?php
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261024223000 extends AbstractMigration
{
    public function getDescription(): string { return 'Configurable driver allocation options'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE rota_allocation_option (code VARCHAR(24) NOT NULL, label VARCHAR(80) NOT NULL, sort_order INT NOT NULL, active TINYINT(1) NOT NULL, PRIMARY KEY(code))');
        $this->addSql("INSERT INTO rota_allocation_option (code,label,sort_order,active) VALUES ('HD','Half Day',1,1),('FD','Full Day',2,1),('OFFICE','Office',3,1),('STANDBY','Standby',4,1),('RA','Ride Along',5,1)");
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE rota_allocation_option'); }
}
