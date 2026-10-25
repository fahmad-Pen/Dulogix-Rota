<?php
namespace App\Controller;
use App\Entity\AppSetting;
use App\Entity\RotaCell;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class RotaDeadlinesController extends AbstractController {
 public const DEFAULTS=[
  'broadcast'=>['Wednesday','12:00'],'availability'=>['Thursday','18:00'],
  'outstanding'=>['Thursday','10:00'],'completion'=>['Friday','15:00'],
  'distribution'=>['Friday','18:00']
 ];
 private function config(EntityManagerInterface $em): array {
  $repo=$em->getRepository(AppSetting::class);$config=[];
  foreach(self::DEFAULTS as $key=>[$day,$time]){
   $saved=$repo->find('rota_deadline_'.$key);
   $data=$saved?json_decode($saved->value,true):null;
   $config[$key]=is_array($data)&&isset($data['day'],$data['time'],$data['enabled'])?$data:['day'=>$day,'time'=>$time,'enabled'=>true];
  }
  $message=$repo->find('rota_deadline_message');
  $config['message']=$message?->value??'📢 DULOGIX — Please submit your availability for next week before the deadline. If already submitted, thank you.';
  if(!str_contains($config['message'],'/driver/availability'))$config['message'].="\n\nSubmit your availability here:\nhttps://app.dulogix.co.uk/driver/availability";
  $warning=$repo->find('rota_deadline_warning');
  $config['warning']=$warning?->value??'24';
  return $config;
 }
 private function deadline(\DateTimeImmutable $week,array $item): \DateTimeImmutable {
  return $week->modify('last '.strtolower($item['day']))->setTime(...array_map('intval',explode(':',$item['time'])));
 }
 #[Route('/admin/settings/rota-deadlines',name:'admin_rota_deadlines',methods:['GET','POST'])]
 public function settings(Request $request,EntityManagerInterface $em,CsrfTokenManagerInterface $csrf): Response {
  $this->denyAccessUnlessGranted('ROLE_ADMIN');$config=$this->config($em);$error='';$saved=false;
  if($request->isMethod('POST')){
   if(!$this->isCsrfTokenValid('rota_deadlines',(string)$request->request->get('_token')))throw $this->createAccessDeniedException();
   $incoming=[];$days=['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
   foreach(self::DEFAULTS as $key=>$default){
    $day=(string)$request->request->get($key.'_day');$time=(string)$request->request->get($key.'_time');
    if(!in_array($day,$days,true)||!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',$time)){$error='Invalid day or time for '.$key;break;}
    $incoming[$key]=['day'=>$day,'time'=>$time,'enabled'=>$request->request->has($key.'_enabled')];
   }
   $message=trim((string)$request->request->get('message'));
   $warning=(int)$request->request->get('warning');
   if(mb_strlen($message)>2000||$message===''||$warning<1||$warning>168)$error='Message must be 1–2000 characters and warning period 1–168 hours.';
   if(!$error){
    foreach($incoming as $key=>$value){$s=$em->getRepository(AppSetting::class)->find('rota_deadline_'.$key)??new AppSetting();$s->key='rota_deadline_'.$key;$s->value=json_encode($value);$em->persist($s);}
    foreach(['message'=>$message,'warning'=>(string)$warning] as $key=>$value){$s=$em->getRepository(AppSetting::class)->find('rota_deadline_'.$key)??new AppSetting();$s->key='rota_deadline_'.$key;$s->value=$value;$em->persist($s);}
    $em->flush();$config=$this->config($em);$saved=true;
   }
  }
  $e=static fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
  $labels=['broadcast'=>'1. Send availability request to all drivers','outstanding'=>'2. Send final availability broadcast','availability'=>'3. Driver availability submission deadline','completion'=>'4. Complete next week’s rota','distribution'=>'5. Send individual rota cards'];
  $explanations=['broadcast'=>'Copy and send the first WhatsApp message to all drivers.','outstanding'=>'Manager sends a reminder to all drivers. Drivers who already submitted can ignore it.','availability'=>'Drivers submit their availability by this time.','completion'=>'Manager finishes allocating drivers for the upcoming week.','distribution'=>'Manager sends each driver their individual rota card.'];
  $form='<form method="post"><input type="hidden" name="_token" value="'.$e($csrf->getToken('rota_deadlines')->getValue()).'">';
  foreach($labels as $key=>$label){
   $c=$config[$key];$form.='<div class="row"><strong>'.$label.'</strong><label><input type="checkbox" name="'.$key.'_enabled"'.($c['enabled']?' checked':'').'> Enabled</label><select name="'.$key.'_day">';
   foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day)$form.='<option'.($day===$c['day']?' selected':'').'>'.$day.'</option>';
   $form.='</select><input type="time" name="'.$key.'_time" value="'.$e($c['time']).'" required><small class="explanation">'.$e($explanations[$key]).' <span class="time-preview" data-stage="'.$key.'"></span></small></div>';
  }
  $form.='<p class="clock" id="uk-clock" aria-live="off"></p><label>Dashboard warning (hours before deadline)<input type="number" min="1" max="168" name="warning" value="'.$e($config['warning']).'"></label><label>Availability broadcast message<textarea name="message" maxlength="2000" rows="5" required>'.$e($config['message']).'</textarea></label><button>Save deadlines</button></form>';
  return new Response('<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Rota deadlines · Dulogix</title><style>body{font:16px system-ui;background:#f4f7fb;color:#17314e}main{max-width:850px;margin:35px auto;padding:15px}.card{background:white;border:1px solid #dbe3ee;border-radius:12px;padding:22px}.row{display:flex;align-items:center;gap:15px;flex-wrap:wrap;padding:15px 0;border-bottom:1px solid #e5eaf0}.row strong{min-width:220px}.explanation{flex-basis:100%;color:#516780;font-size:13px;line-height:1.5;margin-left:0}.time-preview{display:block;color:#17613f;margin-top:3px}.clock{background:#edf5ff;border-radius:8px;padding:10px 12px;font-size:13px;color:#174a7d}.row{gap:10px}.row strong{min-width:290px}input,select,textarea{padding:9px;border:1px solid #bdcad8;border-radius:6px}textarea{display:block;width:100%;box-sizing:border-box}label{display:block;margin:15px 0}button{background:#174a7d;color:white;border:0;border-radius:7px;padding:12px 20px}a{color:#174a7d}</style></head><body><main><a href="/admin/settings">← Admin Settings</a><h1>Rota deadlines & reminders</h1><p>All days and times are UK local time and refer to the week before the upcoming Monday–Sunday rota.</p>'.($saved?'<p style="color:green">Settings saved.</p>':'').($error?'<p style="color:red">'.$e($error).'</p>':'').'<div class="card">'.$form.'</div></main><script>(function(){const zone="Europe/London",clock=document.getElementById("uk-clock");const format=new Intl.DateTimeFormat("en-GB",{timeZone:zone,weekday:"long",day:"numeric",month:"short",hour:"2-digit",minute:"2-digit",hour12:false});function update(){const now=new Date();clock.textContent="Current UK time: "+format.format(now)+" · Times below apply to the week before the next rota starts.";document.querySelectorAll(".time-preview").forEach(el=>{const row=el.closest(".row"),day=row.querySelector("select").value,time=row.querySelector("input[type=time]").value;el.textContent="Scheduled: "+day+" at "+time+" (UK time)";});}document.querySelectorAll("select,input[type=time]").forEach(el=>el.addEventListener("input",update));update();setInterval(update,60000);})();</script></body></html>');
 }
 #[Route('/api/rota/deadlines',name:'api_rota_deadlines',methods:['GET'])]
 public function status(EntityManagerInterface $em): Response {
  $this->denyAccessUnlessGranted('ROLE_OPERATIONS');$tz=new \DateTimeZone('Europe/London');$now=new \DateTimeImmutable('now',$tz);$week=$now->modify('monday next week')->setTime(0,0);$config=$this->config($em);
  $drivers=array_values(array_filter($em->getRepository(User::class)->findAll(),static fn(User $u)=>in_array('ROLE_DRIVER',$u->getRoles(),true)));
  $cells=$em->getRepository(RotaCell::class)->findBy(['weekStart'=>$week]);
  $counts=[];$alloc=[];$last=[];
  foreach($cells as $cell){if(!str_starts_with($cell->driverKey,'driver-')||$cell->dayIndex<0||$cell->dayIndex>6)continue;
   if($cell->fieldKey==='availability'){$counts[$cell->driverKey][$cell->dayIndex]=$cell->value;$last[$cell->driverKey][$cell->dayIndex]=$cell->updatedAt;}
   if($cell->fieldKey==='allocation'&&trim($cell->value)!=='')$alloc[$cell->driverKey][$cell->dayIndex]=true;
  }
  $outstanding=[];$partial=[];$completed=0;$allocated=0;
  $availabilityDeadline=$this->deadline($week,$config['availability']);
  foreach($drivers as $driver){$key='driver-'.$driver->getId();$n=count($counts[$key]??[]);
   if($n===7){$completed++;}elseif($n>0){$partial[]=['id'=>$driver->getId(),'name'=>$driver->getDisplayName(),'days'=>$n];}else{$outstanding[]=['id'=>$driver->getId(),'name'=>$driver->getDisplayName(),'days'=>0];}
   if(count($alloc[$key]??[])===7)$allocated++;
  }
  $deadlines=[];foreach(self::DEFAULTS as $key=>$default){$c=$config[$key];$deadlines[$key]=['enabled'=>$c['enabled'],'at'=>$this->deadline($week,$c)->format(DATE_ATOM)];}
  $confirmation=$em->getRepository(AppSetting::class)->find('rota_broadcast_sent_'.$week->format('Y-m-d'));
  $sent=$confirmation?json_decode($confirmation->value,true):null;
  $followup=$em->getRepository(AppSetting::class)->find('rota_followup_sent_'.$week->format('Y-m-d'));
  $followupSent=$followup?json_decode($followup->value,true):null;
  $checks=[];foreach(['availability','completion','distribution'] as $action){$item=$em->getRepository(AppSetting::class)->find('rota_action_'.$action.'_'.$week->format('Y-m-d'));$checks[$action]=$item?json_decode($item->value,true):null;}
  return $this->json(['week'=>$week->format('Y-m-d'),'deadlines'=>$deadlines,'warningHours'=>(int)$config['warning'],'message'=>$config['message'],'broadcastSent'=>$sent,'followupSent'=>$followupSent,'checks'=>$checks,'canOverride'=>$this->isGranted('ROLE_ADMIN'),'total'=>count($drivers),'submitted'=>$completed,'partial'=>$partial,'outstanding'=>$outstanding,'fullyAllocated'=>$allocated,'availabilityDeadline'=>$availabilityDeadline->format(DATE_ATOM)])->setPrivate()->setMaxAge(0);
 }
 #[Route('/api/rota/deadlines/check',name:'api_rota_deadline_check',methods:['POST'])]
 public function check(Request $request,EntityManagerInterface $em): Response {
  $this->denyAccessUnlessGranted('ROLE_OPERATIONS');
  if(!$this->isCsrfTokenValid('rota_edit',(string)$request->headers->get('X-CSRF-Token')))throw $this->createAccessDeniedException();
  $action=(string)$request->request->get('action');
  if(!in_array($action,['availability','completion','distribution'],true))return $this->json(['error'=>'Invalid action'],422);
  $now=new \DateTimeImmutable('now',new \DateTimeZone('Europe/London'));
  $week=$now->modify('monday next week')->format('Y-m-d');
  $key='rota_action_'.$action.'_'.$week;
  $repo=$em->getRepository(AppSetting::class);$setting=$repo->find($key);
  if($request->request->get('checked')==='1'){
   if(!$setting){$setting=new AppSetting();$setting->key=$key;}
   $setting->value=json_encode(['manager'=>$this->getUser()->getUserIdentifier(),'at'=>$now->format(DATE_ATOM)]);
   $em->persist($setting);
  }elseif($setting){$em->remove($setting);}
  $em->flush();return $this->json(['ok'=>true]);
 }
 #[Route('/api/rota/deadlines/broadcast-sent',name:'api_rota_broadcast_sent',methods:['POST'])]
 public function sent(Request $request,EntityManagerInterface $em): Response {
  $this->denyAccessUnlessGranted('ROLE_OPERATIONS');
  if(!$this->isCsrfTokenValid('rota_edit',(string)$request->headers->get('X-CSRF-Token')))throw $this->createAccessDeniedException();
  $tz=new \DateTimeZone('Europe/London');$week=(new \DateTimeImmutable('now',$tz))->modify('monday next week')->format('Y-m-d');
  $stage=(string)$request->request->get('stage','broadcast');if(!in_array($stage,['broadcast','outstanding'],true))return $this->json(['error'=>'Invalid reminder stage'],422);
  $now=new \DateTimeImmutable('now',$tz);
  $key=($stage==='broadcast'?'rota_broadcast_sent_':'rota_followup_sent_').$week;
  $repo=$em->getRepository(AppSetting::class);$setting=$repo->find($key);
  if($request->request->get('checked','1')==='0'){
   if($setting)$em->remove($setting);
  }elseif(!$setting){
   $setting=new AppSetting();$setting->key=$key;
   $setting->value=json_encode(['manager'=>$this->getUser()->getUserIdentifier(),'at'=>$now->format(DATE_ATOM)]);
   $em->persist($setting);
  }
  $em->flush();return $this->json(['ok'=>true]);
 }
}
