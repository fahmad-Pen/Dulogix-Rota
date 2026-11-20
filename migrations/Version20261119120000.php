<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record weekly driver coaching completion';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE driver_performance_coaching (week_start DATE NOT NULL, transporter_id VARCHAR(40) NOT NULL, done TINYINT(1) NOT NULL DEFAULT 0, updated_at DATETIME NOT NULL, PRIMARY KEY(week_start, transporter_id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE driver_performance_coaching');
    }
}
