<?php
namespace App\Controller;
use App\Entity\AppSetting;
use App\Entity\RotaAudit;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class RotaAvailabilityReviewController extends AbstractController {
 private function week(): \DateTimeImmutable {return (new \DateTimeImmutable('now',new \DateTimeZone('Europe/London')))->modify('monday next week')->setTime(0,0);}
 private function pending(EntityManagerInterface $em): array {
  $week=$this->week();$audits=$em->getRepository(RotaAudit::class)->findBy(['weekStart'=>$week,'fieldKey'=>'availability'],['id'=>'ASC']);
  $byDriver=[];$repo=$em->getRepository(AppSetting::class);
  foreach($audits as $a){
   if(!preg_match('/^driver-(\d+)$/',$a->driverKey,$m)||$a->actor->getId()!==(int)$m[1])continue;
   // An empty previous value means the driver's first submission, not a change.
   if($a->oldValue === '')continue;
   $byDriver[$a->driverKey][]=$a;
  }
  $result=[];$days=['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
  foreach($byDriver as $key=>$items){
   $latest=end($items)->id;
   $reviewed=(int)($repo->find('rota_availability_review_'.$week->format('Ymd').'_'.$key)?->value??0);
   $changes=array_values(array_filter($items,static fn(RotaAudit $a)=>$a->id>$reviewed));
   if(!$changes)continue;
   $driver=$em->getRepository(User::class)->find((int)substr($key,7));
   if(!$driver)continue;
   $lines=[];foreach($changes as $a)$lines[]=['day'=>$days[$a->dayIndex]??'?','old'=>$a->oldValue?:'Not set','new'=>$a->newValue,'at'=>$a->createdAt->format(DATE_ATOM)];
   $result[]=['driverKey'=>$key,'name'=>$driver->getDisplayName(),'latestId'=>$latest,'changes'=>$lines,'count'=>count($changes)];
  }
  return $result;
 }
 #[Route('/api/rota/availability-reviews',name:'api_availability_reviews',methods:['GET'])]
 public function list(EntityManagerInterface $em): Response {
  $this->denyAccessUnlessGranted('ROLE_OPERATIONS');
  return $this->json(['week'=>$this->week()->format('Y-m-d'),'pending'=>$this->pending($em)])->setPrivate()->setMaxAge(0);
 }
 #[Route('/api/rota/availability-reviews/{id}',name:'api_availability_review_ack',requirements:['id'=>'\d+'],methods:['POST'])]
 public function acknowledge(int $id,Request $request,EntityManagerInterface $em): Response {
  $this->denyAccessUnlessGranted('ROLE_OPERATIONS');
  if(!$this->isCsrfTokenValid('rota_edit',(string)$request->headers->get('X-CSRF-Token')))return $this->json(['error'=>'Security token expired. Refresh the rota page and retry.'],403);
  try { $driverKey='driver-'.$id;$pending=$this->pending($em);$match=null;
  foreach($pending as $entry)if($entry['driverKey']===$driverKey)$match=$entry;
  if(!$match)return $this->json(['error'=>'No outstanding change'],404);
  $input=$request->toArray();$reviewedId=$input['latestId']??null;
  if(!is_int($reviewedId)||$reviewedId!==$match['latestId'])return $this->json(['error'=>'Availability changed again. Refresh and review the latest changes.'],409);
  $week=$this->week();$key='rota_availability_review_'.$week->format('Ymd').'_'.$driverKey;
  $setting=$em->getRepository(AppSetting::class)->find($key)??new AppSetting();$setting->key=$key;$setting->value=(string)$reviewedId;$em->persist($setting);
  try {$em->flush();} catch (\Throwable $e) {error_log('Dulogix availability review: '.$e->getMessage());return $this->json(['error'=>'Could not save review confirmation. Please contact the administrator.'],500);}
  return $this->json(['ok'=>true]);
  } catch (\Throwable $e) {error_log('Dulogix review endpoint: '.$e->getMessage());return $this->json(['error'=>'Unable to record review (server error). Please contact support.'],500);}
 }
}
