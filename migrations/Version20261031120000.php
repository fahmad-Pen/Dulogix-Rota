<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional unique transporter ID to existing driver accounts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD transporter_id VARCHAR(40) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_APP_USER_TRANSPORTER_ID ON app_user (transporter_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_APP_USER_TRANSPORTER_ID ON app_user');
        $this->addSql('ALTER TABLE app_user DROP transporter_id');
    }
}
