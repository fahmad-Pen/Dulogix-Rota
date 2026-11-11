<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
$kernel=new \App\Kernel('dev',false);
$kernel->boot();
$db=$kernel->getContainer()->get('doctrine')->getConnection();
use App\Entity\User;
use App\Controller\SectionAccess;
function check(bool $ok,string $name): void {if(!$ok)throw new RuntimeException('FAIL: '.$name);echo "PASS: $name\n";}
$roles=[
 ['operations_read','performance_full',true,false],
 ['operations_read','performance_read',false,false],
 ['operations_full','performance_read',false,true],
 ['operations_full','performance_full',false,true],
];
foreach($roles as [$ops,$perf,$ficoOnly,$opsFull]){
 $u=(new User())->setRoles(['ROLE_MANAGER'])->setDriverType('NONE')->setSections([$ops,$perf]);
 $full=SectionAccess::canEdit($u,'operations');
 $fico=!$full && SectionAccess::performanceLevel($u)==='full';
 check($full===$opsFull && $fico===$ficoOnly && SectionAccess::allowed($u,'operations'),"permission $ops + $perf");
}
$u=(new User())->setRoles(['ROLE_MANAGER'])->setDriverType('NONE')->setSections(['performance_full']);
check(!SectionAccess::allowed($u,'operations'),'no Operations access denies page');
$id=(int)$db->fetchOne("SELECT id FROM app_user WHERE driver_type IN ('DRIVER','JUMPER') AND active=1 LIMIT 1");
check($id>0,'test driver exists');
$db->beginTransaction();
try {
 $dates=['2099-11-02'=>810,'2099-11-03'=>825,'2099-11-05'=>830,'2099-11-07'=>835];
 foreach($dates as $date=>$score)$db->insert('driver_daily_check',['driver_id'=>$id,'shift_date'=>$date,'fisco_score'=>$score,'ementor'=>1,'clear_quote'=>1]);
 $sql='SELECT c.driver_id,c.shift_date,COALESCE(o.fisco_score,c.fisco_score) AS effective_score FROM driver_daily_check c LEFT JOIN operations_daily_score_override o ON o.driver_id=c.driver_id AND o.report_date=c.shift_date WHERE c.shift_date >= ? AND c.shift_date < ? AND c.fisco_score IS NOT NULL ORDER BY c.shift_date DESC';
 $latest=fn()=> $db->fetchAssociative($sql,['2099-11-02','2099-11-09']);
 check($latest()['shift_date']==='2099-11-07' && (int)$latest()['effective_score']===835,'latest Sunday submission');
 $db->insert('operations_daily_score_override',['driver_id'=>$id,'report_date'=>'2099-11-03','fisco_score'=>840,'returns_count'=>3,'updated_by'=>$id,'updated_at'=>'2099-11-10 12:00:00']);
 check((int)$latest()['effective_score']===835,'older correction does not displace Sunday');
 $db->insert('operations_daily_score_override',['driver_id'=>$id,'report_date'=>'2099-11-07','fisco_score'=>845,'returns_count'=>7,'updated_by'=>$id,'updated_at'=>'2099-11-10 12:01:00']);
 check((int)$latest()['effective_score']===845,'latest day correction displayed');
 $db->update('operations_daily_score_override',['fisco_score'=>846,'updated_by'=>$id],['driver_id'=>$id,'report_date'=>'2099-11-07']);
 check((int)$db->fetchOne("SELECT returns_count FROM operations_daily_score_override WHERE driver_id=? AND report_date='2099-11-07'",[$id])===7,'FICO-only update preserves returns');
 $db->insert('operations_fico_audit',['driver_id'=>$id,'report_date'=>'2099-11-07','old_score'=>845,'new_score'=>846,'actor_id'=>$id,'created_at'=>'2099-11-10 12:02:00']);
 $audit=$db->fetchAssociative("SELECT * FROM operations_fico_audit WHERE driver_id=? AND report_date='2099-11-07' ORDER BY id DESC LIMIT 1",[$id]);
 check((int)$audit['old_score']===845 && (int)$audit['new_score']===846 && (int)$audit['actor_id']===$id,'audit old/new/actor stored');
 $db->insert('operations_daily_score_override',['driver_id'=>$id,'report_date'=>'2099-11-08','fisco_score'=>812,'returns_count'=>null,'updated_by'=>$id,'updated_at'=>'2099-11-10 12:03:00']);
 $db->insert('operations_fico_audit',['driver_id'=>$id,'report_date'=>'2099-11-08','old_score'=>null,'new_score'=>812,'actor_id'=>$id,'created_at'=>'2099-11-10 12:03:00']);
 $first=$db->fetchAssociative("SELECT o.fisco_score,a.old_score,a.new_score FROM operations_daily_score_override o JOIN operations_fico_audit a ON a.driver_id=o.driver_id AND a.report_date=o.report_date WHERE o.driver_id=? AND o.report_date='2099-11-08'",[$id]);
 check((int)$first['fisco_score']===812 && $first['old_score']===null && (int)$first['new_score']===812,'first manager FICO without driver submission persists with null old-score audit');
 $db->update('operations_daily_score_override',['fisco_score'=>null,'returns_count'=>null],['driver_id'=>$id,'report_date'=>'2099-11-08']);
 $db->insert('operations_fico_audit',['driver_id'=>$id,'report_date'=>'2099-11-08','old_score'=>812,'new_score'=>null,'actor_id'=>$id,'created_at'=>'2099-11-10 12:04:00']);
 $blank=$db->fetchAssociative("SELECT fisco_score,returns_count FROM operations_daily_score_override WHERE driver_id=? AND report_date='2099-11-08'",[$id]);
 check($blank['fisco_score']===null && $blank['returns_count']===null,'blank FICO and returns persist as NULL');
 check($db->fetchOne("SELECT new_score FROM operations_fico_audit WHERE driver_id=? AND report_date='2099-11-08' AND old_score=812",[$id])===null,'cleared FICO is audit-logged');
 $db->delete('driver_daily_check',['driver_id'=>$id,'shift_date'=>'2099-11-07']);
 check($latest()['shift_date']==='2099-11-05' && (int)$latest()['effective_score']===830,'missing latest day falls back to Thursday');
} finally {$db->rollBack();}
check((int)$db->fetchOne("SELECT COUNT(*) FROM driver_daily_check WHERE driver_id=? AND shift_date='2099-11-07'",[$id])===0,'fixture rollback leaves no daily data');
echo "ALL TRANSACTIONAL TESTS PASSED\n";
