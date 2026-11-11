<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Audit manager FICO corrections on Daily Operations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE operations_fico_audit (id INT AUTO_INCREMENT NOT NULL, driver_id INT NOT NULL, report_date DATE NOT NULL, old_score SMALLINT NOT NULL, new_score SMALLINT NOT NULL, actor_id INT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_OPS_FICO_DRIVER_DATE (driver_id, report_date), INDEX IDX_OPS_FICO_ACTOR (actor_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE operations_fico_audit');
    }
}
