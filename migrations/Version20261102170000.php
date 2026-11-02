<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store weekly Ementor scores by transporter ID';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS driver_ementor_weekly (week_start DATE NOT NULL, transporter_id VARCHAR(60) NOT NULL, ementor_score SMALLINT NOT NULL, PRIMARY KEY (week_start, transporter_id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS driver_ementor_weekly');
    }
}
