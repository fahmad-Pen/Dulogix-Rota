<?php
namespace App\Controller;

use App\Entity\AppSetting;
use App\Entity\RotaCell;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class RotaDistributionController extends AbstractController
{
    private function week(string $week): ?\DateTimeImmutable
    {
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$week,new \DateTimeZone('Europe/London'));
        return $date && $date->format('Y-m-d')===$week && $date->format('N')==='1' ? $date : null;
    }
    private function signature(EntityManagerInterface $em, \DateTimeImmutable $week, int $id): string
    {
        $cells=$em->getRepository(RotaCell::class)->findBy(['weekStart'=>$week,'driverKey'=>'driver-'.$id,'fieldKey'=>'allocation']);
        $values=array_fill(0,7,'');
        foreach($cells as $cell) if($cell->dayIndex>=0 && $cell->dayIndex<7) $values[$cell->dayIndex]=$cell->value;
        return hash('sha256',json_encode($values,JSON_THROW_ON_ERROR));
    }
    private function key(string $week,int $id): string {return 'rota_sent_'.$week.'_'.$id;}
    #[Route('/api/rota/{week}/distribution',methods:['GET'])]
    public function list(string $week,EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_OPERATIONS');
        $date=$this->week($week);
        if(!$date)return $this->json(['error'=>'Invalid week'],422);
        $result=[];
        foreach($em->getRepository(User::class)->findAll() as $driver){
            if(!in_array($driver->getDriverType(),['DRIVER','JUMPER'],true)||in_array('ROLE_ADMIN',$driver->getRoles(),true)||in_array('ROLE_MANAGER',$driver->getRoles(),true))continue;
            $saved=$em->getRepository(AppSetting::class)->find($this->key($week,$driver->getId()));
            $data=$saved?json_decode($saved->value,true):null;
            $status=!$data?'not-sent':(($data['signature']??'')===$this->signature($em,$date,$driver->getId())?'sent':'resend');
            $result['driver-'.$driver->getId()]=['status'=>$status,'at'=>$data['at']??null,'by'=>$data['by']??null];
        }
        return $this->json(['drivers'=>$result]);
    }
    #[Route('/api/rota/{week}/distribution/{id}',requirements:['id'=>'\\d+'],methods:['POST'])]
    public function confirm(string $week,int $id,Request $request,EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_OPERATIONS');
        if(!$this->isCsrfTokenValid('rota_edit',(string)$request->headers->get('X-CSRF-Token')))return $this->json(['error'=>'Invalid CSRF token'],403);
        $date=$this->week($week);
        if(!$date)return $this->json(['error'=>'Invalid week'],422);
        $driver=$em->getRepository(User::class)->find($id);
        if(!$driver||!in_array($driver->getDriverType(),['DRIVER','JUMPER'],true))return $this->json(['error'=>'Driver not found'],404);
        $key=$this->key($week,$id);
        $setting=$em->getRepository(AppSetting::class)->find($key)??new AppSetting();
        $setting->key=$key;
        $setting->value=json_encode(['signature'=>$this->signature($em,$date,$id),'at'=>(new \DateTimeImmutable('now',new \DateTimeZone('Europe/London')))->format(DATE_ATOM),'by'=>$this->getUser()->getUserIdentifier()],JSON_THROW_ON_ERROR);
        $em->persist($setting);
        // Distribution state is a single snapshot per driver/week, not a change audit.
        $cutoff=(new \DateTimeImmutable('today'))->modify('-12 months')->format('Y-m-d');
        $em->createQuery('DELETE FROM App\\Entity\\AppSetting s WHERE s.key LIKE :prefix AND s.key < :cutoff')->setParameter('prefix','rota_sent_%')->setParameter('cutoff','rota_sent_'.$cutoff.'_')->execute();
        $em->flush();
        return $this->json(['status'=>'sent']);
    }
}
