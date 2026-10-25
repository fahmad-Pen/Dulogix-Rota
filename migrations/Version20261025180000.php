<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261025180000 extends AbstractMigration
{
    public function getDescription(): string { return 'Assign application sections to managers, preserving existing manager rota access'; }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE app_user ADD sections JSON DEFAULT NULL COMMENT '(DC2Type:json)'");
        $this->addSql("UPDATE app_user SET sections = '[\"rota\"]' WHERE JSON_CONTAINS(roles, '\"ROLE_MANAGER\"') = 1");
        $this->addSql("UPDATE app_user SET sections = '[]' WHERE sections IS NULL");
        $this->addSql("ALTER TABLE app_user MODIFY sections JSON DEFAULT '[]' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP sections');
    }
}
