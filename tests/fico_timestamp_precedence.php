<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
$kernel=new \App\Kernel('dev',false);$kernel->boot();
$db=$kernel->getContainer()->get('doctrine')->getConnection();
$id=(int)$db->fetchOne("SELECT id FROM app_user WHERE driver_type IN ('DRIVER','JUMPER') AND active=1 LIMIT 1");
if(!$id)throw new RuntimeException('No driver fixture');
$db->beginTransaction();
try{
 $day='2099-11-11';
 $db->insert('operations_daily_score_override',['driver_id'=>$id,'report_date'=>$day,'fisco_score'=>810,'updated_by'=>$id,'updated_at'=>'2099-11-11 10:00:00']);
 $db->insert('driver_daily_check',['driver_id'=>$id,'shift_date'=>$day,'fisco_score'=>825,'post_updated_at'=>'2099-11-11 11:00:00','ementor'=>0,'clear_quote'=>0]);
 $sql='SELECT CASE WHEN o.updated_at IS NULL OR (c.post_updated_at IS NOT NULL AND c.post_updated_at > o.updated_at) THEN c.fisco_score ELSE COALESCE(o.fisco_score,c.fisco_score) END AS score FROM driver_daily_check c LEFT JOIN operations_daily_score_override o ON o.driver_id=c.driver_id AND o.report_date=c.shift_date WHERE c.driver_id=? AND c.shift_date=?';
 if((int)$db->fetchOne($sql,[$id,$day])!==825)throw new RuntimeException('Driver later score did not win');
 echo "PASS: driver later submission wins\n";
 $db->update('operations_daily_score_override',['fisco_score'=>830,'updated_at'=>'2099-11-11 12:00:00'],['driver_id'=>$id,'report_date'=>$day]);
 if((int)$db->fetchOne($sql,[$id,$day])!==830)throw new RuntimeException('Manager later score did not win');
 echo "PASS: manager later correction wins\n";
}finally{$db->rollBack();}
echo "PASS: fixture rolled back\n";
