<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
$kernel=new \App\Kernel('dev',false);
$kernel->boot();
$db=$kernel->getContainer()->get('doctrine')->getConnection();
$id=(int)$db->fetchOne('SELECT id FROM app_user WHERE active=1 AND driver_type IN (\'DRIVER\',\'JUMPER\') LIMIT 1');
if(!$id)throw new RuntimeException('No active driver available for test');
$db->beginTransaction();
try {
 $date='2099-12-17';
 $db->insert('operations_lane_publication',['report_date'=>$date,'driver_id'=>$id,'holding_time'=>'10:30','route'=>'CA_A232','stg_location'=>'A.9','van_registration'=>'AB12 CDE','published_by'=>$id,'published_at'=>'2099-12-17 08:00:00']);
 $row=$db->fetchAssociative('SELECT holding_time,route,stg_location,van_registration FROM operations_lane_publication WHERE report_date=:day AND driver_id=:id',['day'=>$date,'id'=>$id]);
 if(!$row || $row['holding_time']!=='10:30' || $row['route']!=='CA_A232' || $row['stg_location']!=='A.9' || $row['van_registration']!=='AB12 CDE')throw new RuntimeException('Published assignment mismatch');
 echo "PASS: full assignment persisted and readable for the correct driver/date\n";
 $other=$db->fetchOne('SELECT COUNT(*) FROM operations_lane_publication WHERE report_date=:day AND driver_id=:id',['day'=>'2099-12-18','id'=>$id]);
 if((int)$other!==0)throw new RuntimeException('Date isolation failed');
 echo "PASS: publication is isolated by date\n";
 $db->update('operations_lane_publication',['route'=>'CA_A233'],['report_date'=>$date,'driver_id'=>$id]);
 if($db->fetchOne('SELECT route FROM operations_lane_publication WHERE report_date=:day AND driver_id=:id',['day'=>$date,'id'=>$id])!=='CA_A233')throw new RuntimeException('Republish update failed');
 echo "PASS: published assignment can be replaced\n";
} finally { $db->rollBack(); }
echo "PASS: test data rolled back\n";
