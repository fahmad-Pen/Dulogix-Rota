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
  $menuCss=SectionAccess::CSS;
  $html=<<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dulogix · Daily Shift Check</title><link rel="icon" href="/favicon.png"><style>
*{box-sizing:border-box}body{margin:0;background:#f4f7fb;color:#18304a;font:16px system-ui,sans-serif}header{background:#152d49;color:white;padding:13px 16px;display:flex;justify-content:space-between;align-items:center;gap:10px}header a{color:white}header img{height:36px;background:white;border-radius:5px;padding:3px}nav{display:flex;gap:15px;flex-wrap:wrap}main{max-width:610px;margin:24px auto;padding:0 12px}.card{background:white;border:1px solid #dce5ef;border-radius:12px;padding:20px}h1{font-size:26px;margin:0 0 18px}h2{font-size:20px;margin:22px 0 12px}.choices{display:flex;gap:10px;flex-wrap:wrap}.choices label{padding:12px;border:1px solid #d5e0ed;border-radius:8px;display:flex;gap:8px;align-items:center}input{accent-color:#205e94}input[type=date],input[type=number]{padding:10px;border:1px solid #c7d4e3;border-radius:7px;font-size:16px}input[type=number]{width:120px}.field{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:14px 0;border-bottom:1px solid #e6edf5}.state{color:#426b88;min-height:25px}.muted{color:#61758a;font-size:14px}body{background:linear-gradient(145deg,#eaf5ff,#f4f7fb 50%,#f0fdf4)}main{max-width:680px}.card{box-shadow:0 14px 40px rgba(21,45,73,.09);padding:26px}h1,h2{color:#12375a}.choices label{flex:1;min-width:170px;min-height:75px;padding:17px;border:2px solid #b9dcf5;background:#e9f5ff;color:#174d77;border-radius:14px;font-weight:700;cursor:pointer}.choices label:nth-child(2){background:#f0eaff;border-color:#d6c5fa;color:#56348d}.choices label:has(input:checked){background:#c9f4db;border-color:#24975b;color:#125e37}.choices input{width:22px;height:22px;accent-color:#198754}.field{background:#f8fbff;border:1px solid #e0ebf6;border-radius:12px;padding:16px;margin:10px 0}#shift-date{background:#edf7ff;border-color:#a9d5f5}button{padding:12px 16px;background:#166a9e;color:white;border:0;border-radius:8px;font-weight:700;cursor:pointer}button:disabled{opacity:.55;cursor:not-allowed}.muted{background:#ecfdf3;color:#226a43;border-radius:8px;padding:10px 12px}@media(max-width:400px){.field{align-items:flex-start;flex-direction:column}}
.driver-welcome{margin:0 0 8px;color:#176a9e;font-size:18px;font-weight:700}.route-card{margin:20px 0;padding:18px;background:#fff1f2;border:1px solid #f1b7bb;border-radius:12px;color:#522d36}.route-card h2{margin:0 0 12px;color:#80333d}.route-card p{margin:8px 0;line-height:1.5}.route-card strong{color:#752d36}.route-card small{color:#73545b}.route-card.is-withdrawn{background:#fff0f0;border-color:#d33d47;color:#93222c}.route-card.is-withdrawn h2,.route-card.is-withdrawn #shift-route-message{color:#93222c;font-weight:750}.route-card.is-read{background:#eafaf0;border-color:#68bc88;color:#155b37}.route-card.is-read h2,.route-card.is-read strong{color:#155b37}.instruction-alert{font-weight:750;background:#ffe4b0;color:#754300;border-radius:8px;padding:10px 12px;margin:12px 0}.instruction-read{background:#d2f5dd;color:#155b37}.route-card .instruction-value-changed,.route-card.is-read .instruction-value-changed{color:#c02432!important;font-weight:800!important}.instruction-changes{padding-left:20px;line-height:1.7}.instruction-ack{width:100%;margin:12px 0;background:#146a45}</style>{$menuCss}</head><body><header><a href="/driver/daily-check"><img src="/dulogix-logo.png" alt="Dulogix"></a>{$navigation}</header><main><div class="card"><p class="driver-welcome">Welcome, {$driverName}!</p><h1>Daily Shift Check</h1><label>Shift Date <input id="shift-date" type="date" value="$date" min="$min" max="$date"></label><section id="shift-route-card" class="route-card" hidden><h2>Your route assignment</h2><p id="shift-route-message"></p><p id="shift-route-van"></p><div id="shift-manager-comments" hidden><strong>Manager Comments</strong><p id="shift-manager-comments-text" style="white-space:pre-wrap;overflow-wrap:anywhere"></p></div><div id="shift-instruction-status" class="instruction-alert" role="status"></div><ul id="shift-instruction-changes" class="instruction-changes" hidden></ul><button type="button" id="shift-ack" class="instruction-ack" hidden>Understood — Instructions Read</button><small>Published by Operations for your selected shift date.</small></section><h2>Pre-Shift</h2><div class="choices"><label><input id="shift-ementor" type="checkbox"> Ementor</label><label><input id="shift-clear-quote" type="checkbox"> Clear Quote</label></div><p id="shift-pre-state" class="state" aria-live="polite">Loading…</p><button type="button" id="shift-save-pre">Save &amp; Confirm Pre-Shift</button><h2>Post-Shift / Shift End</h2><div class="field"><label for="shift-fisco">FICO Score (0–850)</label><input id="shift-fisco" type="number" min="0" max="850" step="1" inputmode="numeric"></div><div class="field"><label for="shift-returns">No. of Returns</label><input id="shift-returns" type="number" min="0" step="1" inputmode="numeric"></div><p id="shift-post-state" class="state" aria-live="polite">Loading…</p><button type="button" id="shift-save-post">Save &amp; Confirm Post-Shift</button><p class="muted">Each section can be submitted once, for today only. Review your answers before confirming; saved entries cannot be changed.</p></div></main><script>window.DULOGIX_SHIFT_TOKEN="$token";</script><script src="/driver-daily-check.js?v=9"></script></body></html>
HTML;
  return new Response($html);
 }
 #[Route('/driver/daily-check/data',name:'driver_daily_check_data',methods:['GET','POST'])]
 public function check(Request $request,Connection $db):JsonResponse
 {
  $this->denyAccessUnlessGranted('ROLE_DRIVER');
  $driver=$this->getUser();$id=$driver->getId();
  $tz=new \DateTimeZone('Europe/London');
  $today=new \DateTimeImmutable('today',$tz);
  $day=(string)($request->isMethod('POST')?$request->request->get('date',$today->format('Y-m-d')):$request->query->get('date',$today->format('Y-m-d')));
  $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$day,$tz);
  if(!$date||$date->format('Y-m-d')!==$day||$date>$today||$date<$today->modify('-1 month'))
   return new JsonResponse(['ok'=>false,'error'=>'Date is outside the allowed one-month period.'],422);
  $row=$db->fetchAssociative('SELECT ementor,clear_quote,fisco_score,returns_count,pre_updated_at,post_updated_at FROM driver_daily_check WHERE driver_id=:id AND shift_date=:day',['id'=>$id,'day'=>$day]);
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
   return new JsonResponse(['ok'=>true,'acknowledgement'=>$ack,'data'=>$row?:['ementor'=>0,'clear_quote'=>0,'fisco_score'=>null,'returns_count'=>null,'pre_updated_at'=>null,'post_updated_at'=>null],'assignment'=>$assignment?:null,'assignment_withdrawn'=>$withdrawn]);
  }
  if((string)$request->request->get('section')==='ack'){
   if(!$this->isCsrfTokenValid('driver_daily_check',(string)$request->request->get('_token')))return new JsonResponse(['ok'=>false,'error'=>'Invalid security token.'],403);
   $assignment=$db->fetchAssociative('SELECT p.holding_time,p.route,p.stg_location,p.van_registration,d.comments AS manager_comments FROM operations_lane_publication p LEFT JOIN operations_daily d ON d.report_date=p.report_date AND d.driver_id=p.driver_id WHERE p.report_date=:day AND p.driver_id=:id',['day'=>$day,'id'=>$id]);
   if(!$assignment)return new JsonResponse(['ok'=>false,'error'=>'No published instructions for this date.'],409);
   $snapshot=self::instructionSnapshot($assignment);
   $hash=self::instructionHash($snapshot);
   if(!hash_equals($hash,(string)$request->request->get('instruction_hash','')))return new JsonResponse(['ok'=>false,'error'=>'Instructions changed. Refresh and review the latest version.'],409);
   $db->executeStatement('INSERT INTO operations_instruction_ack (report_date,driver_id,instruction_hash,instruction_snapshot,acknowledged_at) VALUES (:day,:id,:hash,:snapshot,:time) ON DUPLICATE KEY UPDATE instruction_hash=VALUES(instruction_hash),instruction_snapshot=VALUES(instruction_snapshot),acknowledged_at=VALUES(acknowledged_at)', ['day'=>$day,'id'=>$id,'hash'=>$hash,'snapshot'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'time'=>(new \DateTimeImmutable('now',$tz))->format('Y-m-d H:i:s')]);
   return new JsonResponse(['ok'=>true]);
  }
  if($day!==$today->format('Y-m-d'))return new JsonResponse(['ok'=>false,'error'=>'Only today can be submitted. Past dates are read-only.'],403);
  if(!$this->isCsrfTokenValid('driver_daily_check',(string)$request->request->get('_token')))return new JsonResponse(['ok'=>false,'error'=>'Invalid security token. Refresh and retry.'],403);
  $section=(string)$request->request->get('section');
  if(!in_array($section,['pre','post'],true))return new JsonResponse(['ok'=>false,'error'=>'Invalid section'],422);
  if($section==='pre'){
   $values=['ementor'=>$request->request->get('ementor')==='1'?1:0,'clear_quote'=>$request->request->get('clear_quote')==='1'?1:0,'pre_updated_at'=>(new \DateTimeImmutable('now',$tz))->format('Y-m-d H:i:s')];
  }else{
   $score=trim((string)$request->request->get('fisco_score',''));
   $returns=trim((string)$request->request->get('returns_count',''));
   if($score===''||$returns==='')return new JsonResponse(['ok'=>false,'error'=>'Both FICO Score and No. of Returns are required.'],422);
   if(($score!==''&&(!preg_match('/^\d{1,3}$/D',$score)||(int)$score>850))||($returns!==''&&(!preg_match('/^\d{1,9}$/D',$returns))))
    return new JsonResponse(['ok'=>false,'error'=>'FICO Score must be 0–850; returns must be a non-negative whole number.'],422);
   $values=['fisco_score'=>$score===''?null:(int)$score,'returns_count'=>$returns===''?null:(int)$returns,'post_updated_at'=>(new \DateTimeImmutable('now',$tz))->format('Y-m-d H:i:s')];
  }
  // A transaction serializes simultaneous submissions from the same driver.
  $db->beginTransaction();
  try {
   $locked=$db->fetchAssociative('SELECT pre_updated_at,post_updated_at FROM driver_daily_check WHERE driver_id=:id AND shift_date=:day FOR UPDATE',['id'=>$id,'day'=>$day]);
   $column=$section==='pre'?'pre_updated_at':'post_updated_at';
   if($locked&&$locked[$column]!==null){
    $db->rollBack();
    return new JsonResponse(['ok'=>false,'error'=>'This section has already been submitted and cannot be changed.'],409);
   }
   if($locked)$db->update('driver_daily_check',$values,['driver_id'=>$id,'shift_date'=>$day]);
   else $db->insert('driver_daily_check',['driver_id'=>$id,'shift_date'=>$day]+$values);
   $db->commit();
  }catch(\Throwable $e){$db->rollBack();throw $e;}
  return new JsonResponse(['ok'=>true,'section'=>$section,'saved_at'=>$values[$column]]);
 }
}
