<?php
namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Routing\Attribute\Route;

final class DriverDailyCheckController extends AbstractController
{
 private static function instructionSnapshot(array $assignment):array {
  return ['holding_time'=>(string)($assignment['holding_time']??''),'route'=>(string)($assignment['route']??''),'stg_location'=>(string)($assignment['stg_location']??''),'van_registration'=>(string)($assignment['van_registration']??''),'manager_comments'=>(string)($assignment['manager_comments']??'')];
 }
 private static function instructionHash(array $snapshot):string {return hash('sha256',json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
 #[Route('/driver/daily-check',name:'driver_daily_check',methods:['GET'])]
 public function page(CsrfTokenManagerInterface $csrf):Response {
  $this->denyAccessUnlessGranted('ROLE_DRIVER');
  $tz=new \DateTimeZone('Europe/London');$today=new \DateTimeImmutable('today',$tz);
  $token=htmlspecialchars($csrf->getToken('driver_daily_check')->getValue(),ENT_QUOTES,'UTF-8');
  $date=$today->format('Y-m-d');$min=$today->modify('-1 month')->format('Y-m-d');
  $driverName=htmlspecialchars($this->getUser()->getDisplayName(),ENT_QUOTES,'UTF-8');
  $navigation=SectionAccess::navigation($this->getUser());

  $html=<<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dulogix · Daily Shift Check</title><link rel="icon" href="/favicon.png"><link rel="stylesheet" href="/driver-daily-check.css?v=1"></head><body><header><a href="/driver/daily-check"><img src="/dulogix-logo.png" alt="Dulogix"></a>{$navigation}</header><main data-shift-token="{$token}"><div class="card"><p class="driver-welcome">Welcome, {$driverName}!</p><h1>Daily Shift Check</h1><label>Shift Date <input id="shift-date" type="date" value="$date" min="$min" max="$date"></label><section id="shift-route-card" class="route-card" hidden><h2>Your route assignment</h2><p id="shift-route-message"></p><p id="shift-route-van"></p><div id="shift-manager-comments" hidden><strong>Manager Comments</strong><p id="shift-manager-comments-text" ></p></div><div id="shift-instruction-status" class="instruction-alert" role="status"></div><ul id="shift-instruction-changes" class="instruction-changes" hidden></ul><button type="button" id="shift-ack" class="instruction-ack" hidden>Understood — Instructions Read</button><small>Published by Operations for your selected shift date.</small></section><h2>Pre-Shift</h2><div class="choices"><label><input id="shift-ementor" type="checkbox"> Ementor</label><label><input id="shift-clear-quote" type="checkbox"> Clear Quote</label></div><p id="shift-pre-state" class="state" aria-live="polite">Loading…</p><button type="button" id="shift-save-pre">Save &amp; Confirm Pre-Shift</button><h2>Post-Shift / Shift End</h2><div class="field"><label for="shift-fisco">FICO Score (0–850)</label><input id="shift-fisco" type="number" min="0" max="850" step="1" inputmode="numeric"></div><div class="field"><label for="shift-returns">No. of Returns</label><input id="shift-returns" type="number" min="0" step="1" inputmode="numeric"></div><p id="shift-post-state" class="state" aria-live="polite">Loading…</p><button type="button" id="shift-save-post">Save &amp; Confirm Post-Shift</button><p class="muted">Only checked or filled items are submitted and locked. Unchecked or empty items stay available for later today.</p></div></main><script src="/driver-daily-check.js?v=15"></script></body></html>
HTML;
  return new Response($html);
 }
 #[Route('/driver/daily-check/data',name:'driver_daily_check_data',methods:['GET','POST'])]
 public function check(Request $request,Connection $db,DriverPushNotifier $pushNotifier):JsonResponse
 {
  $this->denyAccessUnlessGranted('ROLE_DRIVER');
  $driver=$this->getUser();$id=$driver->getId();
  $tz=new \DateTimeZone('Europe/London');
  $today=new \DateTimeImmutable('today',$tz);
  $day=(string)($request->isMethod('POST')?$request->request->get('date',$today->format('Y-m-d')):$request->query->get('date',$today->format('Y-m-d')));
  $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$day,$tz);
  if(!$date||$date->format('Y-m-d')!==$day||$date>$today||$date<$today->modify('-1 month'))
   return new JsonResponse(['ok'=>false,'error'=>'Date is outside the allowed one-month period.'],422);
  $row=$db->fetchAssociative('SELECT ementor,clear_quote,fisco_score,returns_count,pre_updated_at,post_updated_at,ementor_submitted_at,clear_quote_submitted_at,fisco_submitted_at,returns_submitted_at FROM driver_daily_check WHERE driver_id=:id AND shift_date=:day',['id'=>$id,'day'=>$day]);
  if($request->isMethod('GET')){
   $assignment=$db->fetchAssociative('SELECT p.holding_time,p.route,p.stg_location,p.van_registration,p.published_at,d.comments AS manager_comments FROM operations_lane_publication p LEFT JOIN operations_daily d ON d.report_date=p.report_date AND d.driver_id=p.driver_id WHERE p.report_date=:day AND p.driver_id=:id',['day'=>$day,'id'=>$id]);
   $withdrawn=false;
   if(!$assignment){
    // The swap audit retains the withdrawal even when the publication row was removed.
    $withdrawn=(bool)$db->fetchOne("SELECT 1 FROM operations_driver_change_audit WHERE report_date=:day AND from_driver_id=:id AND action='change' ORDER BY id DESC LIMIT 1",['day'=>$day,'id'=>$id]);
   }
   $ack=null;
   if($assignment){
    $snapshot=self::instructionSnapshot($assignment);
    $previous=$db->fetchAssociative('SELECT instruction_hash,instruction_snapshot,acknowledged_at FROM operations_instruction_ack WHERE report_date=:day AND driver_id=:id',['day'=>$day,'id'=>$id]);
    $hash=self::instructionHash($snapshot);
    $changes=[];
    if($previous&&$previous['instruction_hash']!==$hash){
     $prior=json_decode((string)$previous['instruction_snapshot'],true)?:[];
     foreach($snapshot as $key=>$value)if(($prior[$key]??'')!==$value)$changes[]=$key;
    }
    $ack=['read'=>(bool)$previous&&hash_equals((string)$previous['instruction_hash'],$hash),'updated'=>!empty($changes),'changed_fields'=>$changes,'acknowledged_at'=>$previous['acknowledged_at']??null,'instruction_hash'=>$hash];
   }
   return new JsonResponse(['ok'=>true,'acknowledgement'=>$ack,'data'=>$row?:['ementor'=>0,'clear_quote'=>0,'fisco_score'=>null,'returns_count'=>null,'pre_updated_at'=>null,'post_updated_at'=>null,'ementor_submitted_at'=>null,'clear_quote_submitted_at'=>null,'fisco_submitted_at'=>null,'returns_submitted_at'=>null],'assignment'=>$assignment?:null,'assignment_withdrawn'=>$withdrawn]);
  }
  if((string)$request->request->get('section')==='ack'){
   if(!$this->isCsrfTokenValid('driver_daily_check',(string)$request->request->get('_token')))return new JsonResponse(['ok'=>false,'error'=>'Invalid security token.'],403);
   $assignment=$db->fetchAssociative('SELECT p.holding_time,p.route,p.stg_location,p.van_registration,d.comments AS manager_comments FROM operations_lane_publication p LEFT JOIN operations_daily d ON d.report_date=p.report_date AND d.driver_id=p.driver_id WHERE p.report_date=:day AND p.driver_id=:id',['day'=>$day,'id'=>$id]);
   if(!$assignment)return new JsonResponse(['ok'=>false,'error'=>'No published instructions for this date.'],409);
   $snapshot=self::instructionSnapshot($assignment);
   $hash=self::instructionHash($snapshot);
   if(!hash_equals($hash,(string)$request->request->get('instruction_hash','')))return new JsonResponse(['ok'=>false,'error'=>'Instructions changed. Refresh and review the latest version.'],409);
   $db->executeStatement('INSERT INTO operations_instruction_ack (report_date,driver_id,instruction_hash,instruction_snapshot,acknowledged_at) VALUES (:day,:id,:hash,:snapshot,:time) ON DUPLICATE KEY UPDATE instruction_hash=VALUES(instruction_hash),instruction_snapshot=VALUES(instruction_snapshot),acknowledged_at=VALUES(acknowledged_at)', ['day'=>$day,'id'=>$id,'hash'=>$hash,'snapshot'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'time'=>(new \DateTimeImmutable('now',$tz))->format('Y-m-d H:i:s')]);
   try { $pushNotifier->acknowledged($day, (int) $id); } catch (\Throwable $e) { /* Push alerts must never block instruction acknowledgement. */ }
   return new JsonResponse(['ok'=>true]);
  }
  if($day!==$today->format('Y-m-d'))return new JsonResponse(['ok'=>false,'error'=>'Only today can be submitted. Past dates are read-only.'],403);
  if(!$this->isCsrfTokenValid('driver_daily_check',(string)$request->request->get('_token')))return new JsonResponse(['ok'=>false,'error'=>'Invalid security token. Refresh and retry.'],403);
  $section=(string)$request->request->get('section');
  if(!in_array($section,['pre','post'],true))return new JsonResponse(['ok'=>false,'error'=>'Invalid section'],422);
  $now=(new \DateTimeImmutable('now',$tz))->format('Y-m-d H:i:s');
  $submitted=[];
  if($section==='pre'){
   if($request->request->get('ementor')==='1')$submitted['ementor']=['value'=>1,'stamp'=>'ementor_submitted_at'];
   if($request->request->get('clear_quote')==='1')$submitted['clear_quote']=['value'=>1,'stamp'=>'clear_quote_submitted_at'];
  }else{
   foreach(['fisco_score'=>['stamp'=>'fisco_submitted_at','max'=>850,'digits'=>3],'returns_count'=>['stamp'=>'returns_submitted_at','max'=>999999999,'digits'=>9]] as $field=>$rule){
    $value=trim((string)$request->request->get($field,''));
    if($value==='')continue;
    if(!preg_match('/^\d{1,'.$rule['digits'].'}$/D',$value)||(int)$value>$rule['max'])
     return new JsonResponse(['ok'=>false,'error'=>$field==='fisco_score'?'FICO Score must be 0–850.':'Returns must be a non-negative whole number.'],422);
    $submitted[$field]=['value'=>(int)$value,'stamp'=>$rule['stamp']];
   }
  }
  if(!$submitted)return new JsonResponse(['ok'=>false,'error'=>'Check at least one outstanding item or enter a value before submitting.'],422);
  $db->beginTransaction();
  try{
   // Ensure the driver/date row exists, then lock it before checking each field.
   $db->executeStatement('INSERT IGNORE INTO driver_daily_check (driver_id,shift_date) VALUES (:id,:day)',['id'=>$id,'day'=>$day]);
   $locked=$db->fetchAssociative('SELECT ementor_submitted_at,clear_quote_submitted_at,fisco_submitted_at,returns_submitted_at FROM driver_daily_check WHERE driver_id=:id AND shift_date=:day FOR UPDATE',['id'=>$id,'day'=>$day]);
   $values=[];
   foreach($submitted as $field=>$item){
    if($locked[$item['stamp']]!==null)continue;
    $values[$field]=$item['value'];
    $values[$item['stamp']]=$now;
   }
   if(!$values){$db->rollBack();return new JsonResponse(['ok'=>false,'error'=>'These items were already submitted. Refresh to see their status.'],409);}
   // Preserve legacy section timestamps for existing Operations reporting.
   $values[$section==='pre'?'pre_updated_at':'post_updated_at']=$now;
   $db->update('driver_daily_check',$values,['driver_id'=>$id,'shift_date'=>$day]);
   $db->commit();
  }catch(\Throwable $e){$db->rollBack();throw $e;}
  return new JsonResponse(['ok'=>true,'section'=>$section,'saved_at'=>$now,'submitted'=>array_keys($submitted)]);

 }
}