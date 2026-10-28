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
 #[Route('/driver/daily-check',name:'driver_daily_check',methods:['GET'])]
 public function page(CsrfTokenManagerInterface $csrf):Response {
  $this->denyAccessUnlessGranted('ROLE_DRIVER');
  $tz=new \DateTimeZone('Europe/London');$today=new \DateTimeImmutable('today',$tz);
  $token=htmlspecialchars($csrf->getToken('driver_daily_check')->getValue(),ENT_QUOTES,'UTF-8');
  $date=$today->format('Y-m-d');$min=$today->modify('-1 month')->format('Y-m-d');
  $navigation=SectionAccess::navigation($this->getUser());
  $menuCss=SectionAccess::CSS;
  $html=<<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dulogix · Daily Shift Check</title><link rel="icon" href="/favicon.png"><style>
*{box-sizing:border-box}body{margin:0;background:#f4f7fb;color:#18304a;font:16px system-ui,sans-serif}header{background:#152d49;color:white;padding:13px 16px;display:flex;justify-content:space-between;align-items:center;gap:10px}header a{color:white}header img{height:36px;background:white;border-radius:5px;padding:3px}nav{display:flex;gap:15px;flex-wrap:wrap}main{max-width:610px;margin:24px auto;padding:0 12px}.card{background:white;border:1px solid #dce5ef;border-radius:12px;padding:20px}h1{font-size:26px;margin:0 0 18px}h2{font-size:20px;margin:22px 0 12px}.choices{display:flex;gap:10px;flex-wrap:wrap}.choices label{padding:12px;border:1px solid #d5e0ed;border-radius:8px;display:flex;gap:8px;align-items:center}input{accent-color:#205e94}input[type=date],input[type=number]{padding:10px;border:1px solid #c7d4e3;border-radius:7px;font-size:16px}input[type=number]{width:120px}.field{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:14px 0;border-bottom:1px solid #e6edf5}.state{color:#426b88;min-height:25px}.muted{color:#61758a;font-size:14px}body{background:linear-gradient(145deg,#eaf5ff,#f4f7fb 50%,#f0fdf4)}main{max-width:680px}.card{box-shadow:0 14px 40px rgba(21,45,73,.09);padding:26px}h1,h2{color:#12375a}.choices label{flex:1;min-width:170px;min-height:75px;padding:17px;border:2px solid #b9dcf5;background:#e9f5ff;color:#174d77;border-radius:14px;font-weight:700;cursor:pointer}.choices label:nth-child(2){background:#f0eaff;border-color:#d6c5fa;color:#56348d}.choices label:has(input:checked){background:#c9f4db;border-color:#24975b;color:#125e37}.choices input{width:22px;height:22px;accent-color:#198754}.field{background:#f8fbff;border:1px solid #e0ebf6;border-radius:12px;padding:16px;margin:10px 0}#shift-date{background:#edf7ff;border-color:#a9d5f5}.muted{background:#ecfdf3;color:#226a43;border-radius:8px;padding:10px 12px}@media(max-width:400px){.field{align-items:flex-start;flex-direction:column}}
</style>{$menuCss}</head><body><header><a href="/driver/daily-check"><img src="/dulogix-logo.png" alt="Dulogix"></a>{$navigation}</header><main><div class="card"><h1>Daily Shift Check</h1><label>Shift Date <input id="shift-date" type="date" value="$date" min="$min" max="$date"></label><h2>Pre-Shift</h2><div class="choices"><label><input id="shift-ementor" type="checkbox"> Ementor</label><label><input id="shift-clear-quote" type="checkbox"> Clear Quote</label></div><p id="shift-pre-state" class="state" aria-live="polite">Loading…</p><h2>Post-Shift / Shift End</h2><div class="field"><label for="shift-fisco">Fisco Score (0–850)</label><input id="shift-fisco" type="number" min="0" max="850" step="1" inputmode="numeric"></div><div class="field"><label for="shift-returns">No. of Returns</label><input id="shift-returns" type="number" min="0" step="1" inputmode="numeric"></div><p id="shift-post-state" class="state" aria-live="polite">Loading…</p><p class="muted">Changes save automatically.</p></div></main><script>window.DULOGIX_SHIFT_TOKEN="$token";</script><script src="/driver-daily-check.js?v=2"></script></body></html>
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
  if($request->isMethod('GET'))return new JsonResponse(['ok'=>true,'data'=>$row?:['ementor'=>0,'clear_quote'=>0,'fisco_score'=>null,'returns_count'=>null,'pre_updated_at'=>null,'post_updated_at'=>null]]);
  if(!$this->isCsrfTokenValid('driver_daily_check',(string)$request->request->get('_token')))return new JsonResponse(['ok'=>false,'error'=>'Invalid security token. Refresh and retry.'],403);
  $section=(string)$request->request->get('section');
  if(!in_array($section,['pre','post'],true))return new JsonResponse(['ok'=>false,'error'=>'Invalid section'],422);
  if($section==='pre'){
   $values=['ementor'=>$request->request->get('ementor')==='1'?1:0,'clear_quote'=>$request->request->get('clear_quote')==='1'?1:0,'pre_updated_at'=>(new \DateTimeImmutable('now',$tz))->format('Y-m-d H:i:s')];
  }else{
   $score=trim((string)$request->request->get('fisco_score',''));
   $returns=trim((string)$request->request->get('returns_count',''));
   if(($score!==''&&(!preg_match('/^\d{1,3}$/D',$score)||(int)$score>850))||($returns!==''&&(!preg_match('/^\d{1,9}$/D',$returns))))
    return new JsonResponse(['ok'=>false,'error'=>'Fisco Score must be 0–850; returns must be a non-negative whole number.'],422);
   $values=['fisco_score'=>$score===''?null:(int)$score,'returns_count'=>$returns===''?null:(int)$returns,'post_updated_at'=>(new \DateTimeImmutable('now',$tz))->format('Y-m-d H:i:s')];
  }
  if($row)$db->update('driver_daily_check',$values,['driver_id'=>$id,'shift_date'=>$day]);
  else $db->insert('driver_daily_check',['driver_id'=>$id,'shift_date'=>$day]+$values);
  return new JsonResponse(['ok'=>true,'section'=>$section,'saved_at'=>(new \DateTimeImmutable('now',$tz))->format('H:i')]);
 }
}
