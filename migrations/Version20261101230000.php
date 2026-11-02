<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store editable Driver Performance category weights';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE driver_performance_scoring_rules (id INT NOT NULL PRIMARY KEY, rules_json LONGTEXT NOT NULL, updated_by INT DEFAULT NULL, updated_at DATETIME NOT NULL)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE driver_performance_scoring_rules');
    }
}
