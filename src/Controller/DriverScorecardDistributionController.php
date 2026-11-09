<?php
namespace App\Controller;
use App\Entity\AppSetting;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
final class DriverScorecardDistributionController extends AbstractController {
 private function validWeek(string $week): bool { $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$week);return $d && $d->format('Y-m-d')===$week && $d->format('N')==='1'; }
 private function key(string $week,int $id): string {return 'scorecard_sent_'.$week.'_'.$id;}
 #[Route('/api/driver-performance/{week}/distribution',methods:['GET'])]
 public function list(string $week,EntityManagerInterface $em): JsonResponse {
  $this->denyAccessUnlessGranted('ROLE_OPERATIONS');
  if (SectionAccess::performanceLevel($this->getUser()) === 'none') throw $this->createAccessDeniedException('Driver Performance access is not assigned.');
  if(!$this->validWeek($week))return $this->json(['error'=>'Invalid week'],422);
  $result=[];
  foreach($em->getRepository(User::class)->findAll() as $driver){
   if(!in_array($driver->getDriverType(),['DRIVER','JUMPER'],true))continue;
   $saved=$em->getRepository(AppSetting::class)->find($this->key($week,$driver->getId()));
   $data=$saved?json_decode($saved->value,true):null;
   $result[(string)$driver->getId()]=['status'=>$data?'sent':'not-sent','signature'=>$data['signature']??null,'at'=>$data['at']??null,'by'=>$data['by']??null];
  }
  return $this->json(['drivers'=>$result]);
 }
 #[Route('/api/driver-performance/{week}/distribution/{id}',requirements:['id'=>'\\d+'],methods:['POST'])]
 public function confirm(string $week,int $id,Request $request,EntityManagerInterface $em): JsonResponse {
  $this->denyAccessUnlessGranted('ROLE_OPERATIONS');
  if (SectionAccess::performanceLevel($this->getUser()) === 'none') throw $this->createAccessDeniedException('Driver Performance access is not assigned.');
  if(!$this->validWeek($week))return $this->json(['error'=>'Invalid week'],422);
  if(!$this->isCsrfTokenValid('scorecard_distribution',(string)$request->headers->get('X-CSRF-Token')))return $this->json(['error'=>'Invalid CSRF token'],403);
  $driver=$em->getRepository(User::class)->find($id);
  if(!$driver||!in_array($driver->getDriverType(),['DRIVER','JUMPER'],true))return $this->json(['error'=>'Driver not found'],404);
  try{$payload=$request->toArray();}catch(\Throwable){return $this->json(['error'=>'Invalid JSON'],400);}
  $signature=$payload['signature']??null;
  if(!is_string($signature)||!preg_match('/^[a-f0-9]{64}$/',$signature))return $this->json(['error'=>'Invalid scorecard signature'],422);
  $key=$this->key($week,$id);
  $setting=$em->getRepository(AppSetting::class)->find($key)??new AppSetting();
  $setting->key=$key;
  $setting->value=json_encode(['signature'=>$signature,'at'=>(new \DateTimeImmutable('now',new \DateTimeZone('Europe/London')))->format(DATE_ATOM),'by'=>$this->getUser()->getUserIdentifier()],JSON_THROW_ON_ERROR);
  $em->persist($setting);$em->flush();
  return $this->json(['status'=>'sent']);
 }
}
