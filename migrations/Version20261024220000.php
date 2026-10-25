<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261024220000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add operational type to user accounts'; }
    public function up(Schema $schema): void { $this->addSql("ALTER TABLE app_user ADD driver_type VARCHAR(16) NOT NULL DEFAULT 'DRIVER'"); $this->addSql("UPDATE app_user SET driver_type = 'MANAGER' WHERE JSON_CONTAINS(roles, '\"ROLE_MANAGER\"') = 1"); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE app_user DROP driver_type'); }
}
