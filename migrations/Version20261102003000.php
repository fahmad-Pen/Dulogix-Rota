<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102003000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store weekly manually entered Netradyne violations by driver';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE driver_netradyne_weekly (week_start DATE NOT NULL, transporter_id VARCHAR(40) NOT NULL, d INT NOT NULL DEFAULT 0, fd INT NOT NULL DEFAULT 0, ss INT NOT NULL DEFAULT 0, speed INT NOT NULL DEFAULT 0, tl INT NOT NULL DEFAULT 0, updated_by INT NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(week_start, transporter_id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE driver_netradyne_weekly');
    }
}
