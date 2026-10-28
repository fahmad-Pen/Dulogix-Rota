<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261025235902 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Configure allocation types that count towards consecutive driving days.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rota_allocation_option ADD counts_driving_days TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql("UPDATE rota_allocation_option SET counts_driving_days = 1 WHERE code IN ('IN', 'HD')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rota_allocation_option DROP counts_driving_days');
    }
}
