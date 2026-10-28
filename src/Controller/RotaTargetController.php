<?php
namespace App\Controller;
use App\Entity\RotaCell;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/rota/{week}/targets',requirements:['week'=>'\\d{4}-\\d{2}-\\d{2}'])]
final class RotaTargetController extends AbstractController {
 private const FIELDS=['Route Target','Current Demand Signal','ECP','Required drivers','DA Capacity','Standby'];
 private function week(string $week):?\DateTimeImmutable {
  $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$week,new \DateTimeZone('Europe/London'));
  return $d&&$d->format('Y-m-d')===$week&&$d->format('N')==='1'?$d:null;
 }
 #[Route('',name:'api_rota_targets_save',methods:['POST'])]
 public function save(string $week,Request $request,EntityManagerInterface $em,RotaWeekLock $lock):JsonResponse {
  $this->denyAccessUnlessGranted('ROLE_OPERATIONS');
  if(!$this->isCsrfTokenValid('rota_edit',(string)$request->headers->get('X-CSRF-Token')))return $this->json(['error'=>'Session expired; refresh page'],403);
  $date=$this->week($week);if(!$date)return $this->json(['error'=>'Invalid week'],422);
  if(!$lock->canEdit($date,$this->getUser()->getId()))return $this->json(['error'=>'Historical weeks locked'],423);
  try{$data=$request->toArray();}catch(\Throwable){return $this->json(['error'=>'Invalid request'],400);}
  $values=$data['values']??null;$expected=$data['expected']??null;
  if(!is_array($values)||!is_array($expected)||count($values)!==count(self::FIELDS)||count($expected)!==count(self::FIELDS))return $this->json(['error'=>'All planning fields required'],422);
  foreach(self::FIELDS as $field) {
   if(!isset($values[$field],$expected[$field])||!is_array($values[$field])||!is_array($expected[$field])||array_keys($values[$field])!==range(0,6)||array_keys($expected[$field])!==range(0,6))return $this->json(['error'=>'Seven values required for '.$field],422);
   foreach([...$values[$field],...$expected[$field]] as $v)if(!is_string($v)||($v!==''&&(!ctype_digit($v)||strlen($v)>7)))return $this->json(['error'=>'Use non-negative whole numbers'],422);
  }
  $conn=$em->getConnection();$conn->beginTransaction();
  try {
   $changed=0;
   foreach(self::FIELDS as $field)for($day=0;$day<7;$day++){
    $cell=$em->getRepository(RotaCell::class)->findOneBy(['weekStart'=>$date,'driverKey'=>'planning','fieldKey'=>$field,'dayIndex'=>$day]);
    $old=$cell?->value??'';
    if($old!==$expected[$field][$day]){$conn->rollBack();return $this->json(['error'=>'Another manager updated planning. Refresh before saving.'],409);}
    $new=$values[$field][$day];if($old===$new)continue;
    if(!$cell){$cell=new RotaCell();$cell->weekStart=$date;$cell->driverKey='planning';$cell->fieldKey=$field;$cell->dayIndex=$day;}
    $lock->correction($date,$this->getUser()->getId(),$field.' / day '.$day.': '.$old.' → '.$new);
    $cell->value=$new;$cell->updatedBy=$this->getUser();$cell->updatedAt=new \DateTimeImmutable();$em->persist($cell);
    $changed++;
   }
   $em->flush();$conn->commit();return $this->json(['saved'=>true,'changed'=>$changed]);
  }catch(\Throwable $e){if($conn->isTransactionActive())$conn->rollBack();error_log('Planning batch: '.$e->getMessage());return $this->json(['error'=>'Unable to save planning'],500);}
 }
}
