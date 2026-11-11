<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store date-scoped driver lane assignments explicitly published by Operations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE operations_lane_publication (report_date DATE NOT NULL, driver_id INT NOT NULL, holding_time VARCHAR(32) DEFAULT NULL, route VARCHAR(100) DEFAULT NULL, stg_location VARCHAR(100) DEFAULT NULL, van_registration VARCHAR(100) DEFAULT NULL, published_by INT NOT NULL, published_at DATETIME NOT NULL, PRIMARY KEY(report_date, driver_id), INDEX IDX_LANE_PUBLICATION_DRIVER (driver_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE operations_lane_publication');
    }
}
