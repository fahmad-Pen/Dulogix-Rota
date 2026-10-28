<?php
namespace App\Command;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name:'app:daily:prune',description:'Preview or delete daily shift checks and operations reports older than one calendar month')]
final class PruneDailyRecordsCommand extends Command {
 public function __construct(private readonly Connection $db){parent::__construct();}
 protected function configure():void{$this->addOption('execute',null,InputOption::VALUE_NONE,'Actually delete eligible records (dry run by default)');}
 protected function execute(InputInterface $input,OutputInterface $output):int{
  $cutoff=(new \DateTimeImmutable('today',new \DateTimeZone('Europe/London')))->modify('-1 month')->format('Y-m-d');
  $targets=['driver_daily_check'=>'shift_date','operations_daily'=>'report_date'];
  $counts=[];
  foreach($targets as $table=>$column){
   $counts[$table]=(int)$this->db->fetchOne("SELECT COUNT(*) FROM $table WHERE $column < :cutoff",['cutoff'=>$cutoff]);
   $output->writeln("$table: ".$counts[$table]." records before $cutoff");
  }
  if(!$input->getOption('execute')){$output->writeln('DRY RUN: no deletion. Use --execute after reviewing counts and backups.');return Command::SUCCESS;}
  $this->db->beginTransaction();
  try{foreach($targets as $table=>$column)$this->db->executeStatement("DELETE FROM $table WHERE $column < :cutoff",['cutoff'=>$cutoff]);$this->db->commit();}
  catch(\Throwable $e){$this->db->rollBack();throw $e;}
  $output->writeln('Deleted eligible daily records.');return Command::SUCCESS;
 }
}
