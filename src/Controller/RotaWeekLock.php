<?php
namespace App\Controller;

use Doctrine\DBAL\Connection;

final class RotaWeekLock
{
    public function __construct(private readonly Connection $db) {}
    public function historical(\DateTimeImmutable $week): bool
    {
        $tz = new \DateTimeZone('Europe/London');
        return new \DateTimeImmutable('now', $tz) >= $week->setTimezone($tz)->modify('+14 days')->setTime(0, 0);
    }
    public function session(\DateTimeImmutable $week): ?array
    {
        return $this->db->fetchAssociative('SELECT id,user_id,expires_at FROM rota_week_unlock WHERE week_start = :week AND expires_at > UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1', ['week'=>$week->format('Y-m-d')]) ?: null;
    }
    public function canEdit(\DateTimeImmutable $week, int $userId): bool
    {
        if (!$this->historical($week)) return true;
        $session=$this->session($week);
        return $session !== null && (int)$session['user_id'] === $userId;
    }
    public function correction(\DateTimeImmutable $week, int $userId, string $details): void
    {
        if (!$this->historical($week)) return;
        $this->db->insert('rota_week_history',['week_start'=>$week->format('Y-m-d'),'user_id'=>$userId,'event_type'=>'correction','details'=>$details,'created_at'=>gmdate('Y-m-d H:i:s')]);
    }
}
