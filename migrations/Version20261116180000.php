<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track individual driver shift checks without locking unanswered fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE driver_daily_check ADD ementor_submitted_at DATETIME DEFAULT NULL, ADD clear_quote_submitted_at DATETIME DEFAULT NULL, ADD fisco_submitted_at DATETIME DEFAULT NULL, ADD returns_submitted_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE driver_daily_check SET ementor_submitted_at = CASE WHEN ementor = 1 THEN pre_updated_at ELSE NULL END, clear_quote_submitted_at = CASE WHEN clear_quote = 1 THEN pre_updated_at ELSE NULL END, fisco_submitted_at = CASE WHEN fisco_score IS NOT NULL THEN post_updated_at ELSE NULL END, returns_submitted_at = CASE WHEN returns_count IS NOT NULL THEN post_updated_at ELSE NULL END');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE driver_daily_check DROP ementor_submitted_at, DROP clear_quote_submitted_at, DROP fisco_submitted_at, DROP returns_submitted_at');
    }
}
