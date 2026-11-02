<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Preserve source row order for imported CC reports';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE driver_contact_compliance ADD report_position INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE driver_contact_compliance DROP report_position');
    }
}
