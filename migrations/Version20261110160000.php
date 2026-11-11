<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow FICO audit to record a first manager score without a previous driver submission';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE operations_fico_audit MODIFY old_score SMALLINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE operations_fico_audit SET old_score = 0 WHERE old_score IS NULL');
        $this->addSql('ALTER TABLE operations_fico_audit MODIFY old_score SMALLINT NOT NULL');
    }
}
