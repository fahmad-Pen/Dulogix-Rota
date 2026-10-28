<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261027091000 extends AbstractMigration {
    public function getDescription(): string { return 'Allow separate accounts to share an email address while keeping usernames unique'; }
    public function up(Schema $schema): void { $this->addSql('DROP INDEX uniq_user_email ON app_user'); }
    public function down(Schema $schema): void { $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON app_user (email)'); }
}
