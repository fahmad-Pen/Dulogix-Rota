<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure persisted VAPID keys table exists even if earlier push migration was already applied';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS driver_push_vapid (id INT NOT NULL, public_key VARCHAR(255) NOT NULL, private_key VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // Preserve VAPID identity and existing phone subscriptions during rollback.
    }
}
