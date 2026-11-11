<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Audit manager route driver changes and swaps';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE operations_driver_change_audit (id INT AUTO_INCREMENT NOT NULL, report_date DATE NOT NULL, from_driver_id INT NOT NULL, to_driver_id INT NOT NULL, action VARCHAR(12) NOT NULL, before_data LONGTEXT NOT NULL, after_data LONGTEXT NOT NULL, actor_id INT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_DRIVER_CHANGE_DATE (report_date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE operations_driver_change_audit');
    }
}
