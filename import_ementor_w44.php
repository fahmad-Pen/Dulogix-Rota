<?php
require '/app/vendor/autoload.php';
$kernel = new App\Kernel('dev', true);
$kernel->boot();
$db = $kernel->getContainer()->get('doctrine')->getConnection();
$db->executeStatement('CREATE TABLE IF NOT EXISTS driver_ementor_weekly (week_start DATE NOT NULL, transporter_id VARCHAR(60) NOT NULL, ementor_score SMALLINT NOT NULL, PRIMARY KEY (week_start, transporter_id))');
$lines = file('/app/ementor_week44.tsv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$db->beginTransaction();
try {
 foreach ($lines as $line) {
  [$id,$score] = preg_split('/\s+/', trim($line));
  if (!preg_match('/^[A-Z0-9]{8,40}$/D',$id) || !ctype_digit($score) || (int)$score > 1000) throw new RuntimeException('Invalid row');
  $db->executeStatement('INSERT INTO driver_ementor_weekly (week_start,transporter_id,ementor_score) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE ementor_score=VALUES(ementor_score)', ['2026-10-26',$id,(int)$score]);
 }
 $db->commit();
} catch(Throwable $e) {$db->rollBack();throw $e;}
echo 'Imported '.count($lines).' Ementor scores; stored '.$db->fetchOne('SELECT COUNT(*) FROM driver_ementor_weekly WHERE week_start = ?', ['2026-10-26']).' for week 44'.PHP_EOL;
