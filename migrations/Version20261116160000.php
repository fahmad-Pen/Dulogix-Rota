<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116160000 extends AbstractMigration
{
    public function getDescription(): string { return 'Optional driver web-push subscriptions and per-instruction notification cycle'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE driver_push_vapid (id INT NOT NULL, public_key VARCHAR(255) NOT NULL, private_key VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE driver_push_subscription (id INT AUTO_INCREMENT NOT NULL, driver_id INT NOT NULL, endpoint_hash CHAR(64) NOT NULL, endpoint TEXT NOT NULL, public_key VARCHAR(255) NOT NULL, auth_token VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id), UNIQUE INDEX uniq_driver_push_endpoint (endpoint_hash), INDEX idx_driver_push_driver (driver_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE driver_push_cycle (report_date DATE NOT NULL, driver_id INT NOT NULL, pending_since DATETIME DEFAULT NULL, notified_at DATETIME DEFAULT NULL, last_ack_hash CHAR(64) DEFAULT NULL, PRIMARY KEY(report_date, driver_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE driver_push_cycle');
        $this->addSql('DROP TABLE driver_push_subscription');
        $this->addSql('DROP TABLE driver_push_vapid');
    }
}
