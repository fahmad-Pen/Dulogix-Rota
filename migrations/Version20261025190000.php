<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261025190000 extends AbstractMigration {
    public function getDescription(): string { return 'Allow driver accounts to be activated and deactivated'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE app_user ADD active TINYINT(1) DEFAULT 1 NOT NULL'); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE app_user DROP active'); }
}
