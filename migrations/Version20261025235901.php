<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261025235901 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mark allocation types that count toward operational headcount; preserve IN as the initial counted type.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rota_allocation_option ADD counts_headcount TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql("UPDATE rota_allocation_option SET counts_headcount = 1 WHERE code = 'IN'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rota_allocation_option DROP counts_headcount');
    }
}
