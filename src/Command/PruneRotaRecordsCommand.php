<?php
namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:rota:prune', description: 'Preview or remove rota records older than 12 months')]
final class PruneRotaRecordsCommand extends Command
{
    public function __construct(private readonly Connection $connection) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addOption('execute', null, InputOption::VALUE_NONE, 'Delete eligible records; without this option only report counts');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tz = new \DateTimeZone('Europe/London');
        $today = new \DateTimeImmutable('today', $tz);
        // Retain the complete week containing the 12-month anniversary.
        $cutoff = $today->modify('-12 months')->modify('monday this week')->format('Y-m-d');
        $dateKeys = [
            'rota_action_availability_', 'rota_action_completion_', 'rota_action_distribution_',
            'rota_broadcast_sent_', 'rota_followup_sent_',
        ];
        $patterns = [];
        $params = ['cutoff' => $cutoff];
        foreach ($dateKeys as $i => $prefix) {
            $name = 'prefix'.$i;
            $patterns[] = '(`key` LIKE :'.$name.' AND SUBSTRING(`key`, '.(strlen($prefix) + 1).") REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' AND SUBSTRING(`key`, ".(strlen($prefix) + 1).') < :cutoff)';
            $params[$name] = $prefix.'%';
        }
        $where = implode(' OR ', $patterns);
        $cells = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM rota_cell WHERE week_start < :cutoff', ['cutoff' => $cutoff]);
        $settings = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM app_setting WHERE '.$where, $params);
        $output->writeln(sprintf('Rota retention cutoff: %s (keep weeks on/after cutoff).', $cutoff));
        $history=(int)$this->connection->fetchOne('SELECT COUNT(*) FROM rota_week_history WHERE week_start < :cutoff',['cutoff'=>$cutoff]);
        $sessions=(int)$this->connection->fetchOne('SELECT COUNT(*) FROM rota_week_unlock WHERE week_start < :cutoff',['cutoff'=>$cutoff]);
        $output->writeln(sprintf('Eligible: %d rota cells; %d weekly settings; %d historical audit events; %d old unlock sessions.', $cells, $settings, $history, $sessions));
        if (!$input->getOption('execute')) {
            $output->writeln('DRY RUN ONLY: no records deleted. Use --execute after verifying backups and retention requirements.');
            return Command::SUCCESS;
        }
        $this->connection->beginTransaction();
        try {
            $removedCells = $this->connection->executeStatement('DELETE FROM rota_cell WHERE week_start < :cutoff', ['cutoff' => $cutoff]);
            $removedSettings = $this->connection->executeStatement('DELETE FROM app_setting WHERE '.$where, $params);
            $removedHistory = $this->connection->executeStatement('DELETE FROM rota_week_history WHERE week_start < :cutoff',['cutoff'=>$cutoff]);
            $removedSessions = $this->connection->executeStatement('DELETE FROM rota_week_unlock WHERE week_start < :cutoff',['cutoff'=>$cutoff]);
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
        $output->writeln(sprintf('Deleted %d rota cells and %d weekly settings.', $removedCells, $removedSettings));
        return Command::SUCCESS;
    }
}
