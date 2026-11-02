<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Correct Negative Feedback scoring colours: 5 green, 0 red, intermediate amber';
    }

    public function up(Schema $schema): void
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['driver_performance_scoring_rules'])) {
            return;
        }
        $json = $this->connection->fetchOne('SELECT rules_json FROM driver_performance_scoring_rules WHERE id = 1');
        if (!$json) return;
        $rules = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
        $colors = $rules['cdf_colors'] ?? [];
        $green = $colors['green_color'] ?? '#c6efce';
        $amber = $colors['amber_color'] ?? '#ffeb9c';
        $red = $colors['red_color'] ?? '#ffc7ce';
        $bands = $rules['cdf_bands'] ?? [[8000,0],[7000,1],[6000,1.5],[5000,2.5],[4500,4]];
        foreach ($bands as [$threshold, $points]) {
            $rules['condition_colours']['cdf'][(string)(float)$threshold] = (float)$points >= 5 ? $green : ((float)$points <= 0 ? $red : $amber);
        }
        $rules['cdf_colors']['green_below'] = 5;
        $rules['cdf_colors']['red_above'] = 0;
        $this->connection->executeStatement('UPDATE driver_performance_scoring_rules SET rules_json = ? WHERE id = 1', [json_encode($rules, JSON_THROW_ON_ERROR)]);
    }

    public function down(Schema $schema): void
    {
        // Existing custom colours cannot be reconstructed reliably.
    }
}
