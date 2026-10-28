<?php
namespace App\Controller;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class RotaWeekLockController extends AbstractController
{
    #[Route('/api/rota/{week}/lock', name:'rota_week_lock', requirements:['week'=>'\\d{4}-\\d{2}-\\d{2}'], methods:['GET','POST'])]
    public function lock(string $week, Request $request, RotaWeekLock $lock, Connection $db): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_OPERATIONS');
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$week,new \DateTimeZone('Europe/London'));
        if (!$date || $date->format('Y-m-d')!==$week || $date->format('N')!=='1') return $this->json(['error'=>'Invalid week'],422);
        $user=$this->getUser();$uid=$user->getId();
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rota_edit',(string)$request->headers->get('X-CSRF-Token'))) return $this->json(['error'=>'Invalid CSRF'],403);
            if (!$lock->historical($date)) return $this->json(['error'=>'Week is not locked'],422);
            $payload=$request->toArray();$action=$payload['action']??'';
            $session=$lock->session($date);
            if ($action==='unlock') {
                $reason=trim((string)($payload['reason']??''));
                if (mb_strlen($reason)<5 || mb_strlen($reason)>500) return $this->json(['error'=>'Reason must be 5–500 characters'],422);
                if ($session) return $this->json(['error'=>'Week is already unlocked by a manager'],409);
                $db->beginTransaction();
                try {
                    $db->executeStatement('DELETE FROM rota_week_unlock WHERE week_start = :week',['week'=>$week]);
                    $db->insert('rota_week_unlock',['week_start'=>$week,'user_id'=>$uid,'expires_at'=>(new \DateTimeImmutable('+30 minutes',new \DateTimeZone('UTC')))->format('Y-m-d H:i:s')]);
                    $db->insert('rota_week_history',['week_start'=>$week,'user_id'=>$uid,'event_type'=>'unlock','details'=>$reason,'created_at'=>gmdate('Y-m-d H:i:s')]);
                    $db->commit();
                } catch (\Throwable $e) {$db->rollBack();throw $e;}
            } elseif ($action==='relock') {
                if (!$session || (int)$session['user_id']!==$uid) return $this->json(['error'=>'Only the unlocking manager can relock'],403);
                $db->executeStatement('DELETE FROM rota_week_unlock WHERE week_start = :week',['week'=>$week]);
                $db->insert('rota_week_history',['week_start'=>$week,'user_id'=>$uid,'event_type'=>'relock','details'=>'Locked manually','created_at'=>gmdate('Y-m-d H:i:s')]);
            } else return $this->json(['error'=>'Invalid action'],422);
        }
        $session=$lock->session($date);
        $rows=$db->fetchAllAssociative('SELECT h.event_type,h.details,h.created_at,u.display_name AS actor FROM rota_week_history h LEFT JOIN app_user u ON u.id=h.user_id WHERE h.week_start=:week ORDER BY h.id DESC LIMIT 200',['week'=>$week]);
        return $this->json(['historical'=>$lock->historical($date),'canEdit'=>$lock->canEdit($date,$uid),'unlockedByMe'=>$session && (int)$session['user_id']===$uid,'expiresAt'=>$session['expires_at']??null,'history'=>$rows]);
    }
}
