<?php
namespace App\Controller;

use App\Entity\User;
use App\Entity\RotaCell;
use App\Entity\RotaAudit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ManagerAvailabilityController extends AbstractController
{
    #[Route('/rota/driver/{id}/availability', name: 'rota_driver_availability_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, CsrfTokenManagerInterface $csrf, RotaWeekLock $lock): Response
    {
        if (!SectionAccess::allowed($this->getUser(), 'rota')) throw $this->createAccessDeniedException();
        $driver = $em->getRepository(User::class)->find($id);
        if (!$driver || !in_array('ROLE_DRIVER', $driver->getRoles(), true) || in_array('ROLE_ADMIN', $driver->getRoles(), true) || in_array('ROLE_MANAGER', $driver->getRoles(), true)) throw $this->createNotFoundException();
        $tz = new \DateTimeZone('Europe/London');
        $now = new \DateTimeImmutable('now', $tz);
        $defaultWeek = $now->modify('monday next week')->setTime(0, 0);
        $requested = (string) $request->query->get('week', $defaultWeek->format('Y-m-d'));
        $week = \DateTimeImmutable::createFromFormat('!Y-m-d', $requested, $tz);
        if (!$week || $week->format('Y-m-d') !== $requested || $week->format('N') !== '1') throw $this->createNotFoundException();
        $key = 'driver-'.$driver->getId();
        $existing = $em->getRepository(RotaCell::class)->findBy(['weekStart' => $week, 'driverKey' => $key, 'fieldKey' => 'availability']);
        $cells = []; $values = array_fill(0, 7, '');
        foreach ($existing as $cell) { if ($cell->dayIndex >= 0 && $cell->dayIndex < 7) { $cells[$cell->dayIndex] = $cell; $values[$cell->dayIndex] = $cell->value; } }
        $message = '';
        if ($request->isMethod('POST')) {
            if (!$lock->canEdit($week,$this->getUser()->getId())) throw $this->createAccessDeniedException('Historical week locked');
            if (!$this->isCsrfTokenValid('manager_availability_'.$id, (string) $request->request->get('_token'))) throw $this->createAccessDeniedException('Invalid CSRF token');
            $proposed = $request->request->all('days');
            $valid = count($proposed) === 7;
            foreach (range(0, 6) as $day) if (!isset($proposed[$day]) || !in_array($proposed[$day], ['IN', 'OFF'], true)) $valid = false;
            if (!$valid) $message = '<p class="error">Choose Available or Off for all seven days.</p>';
            else {
                $changed = 0;
                foreach (range(0, 6) as $day) {
                    if ($values[$day] === $proposed[$day]) continue;
                    $cell = $cells[$day] ?? new RotaCell();
                    $cell->weekStart = $week; $cell->driverKey = $key; $cell->fieldKey = 'availability';
                    $cell->dayIndex = $day; $cell->value = $proposed[$day];
                    $cell->updatedAt = new \DateTimeImmutable(); $cell->updatedBy = $this->getUser();
                    $audit = new RotaAudit();
                    $audit->weekStart = $week; $audit->driverKey = $key; $audit->fieldKey = 'availability';
                    $audit->dayIndex = $day; $audit->oldValue = $values[$day]; $audit->newValue = $proposed[$day];
                    $audit->actor = $this->getUser(); $audit->createdAt = new \DateTimeImmutable();
                    $lock->correction($week,$this->getUser()->getId(),$driver->getDisplayName().' / availability / day '.$day.': '.$values[$day].' → '.$proposed[$day]);
                    $em->persist($cell); $em->persist($audit);
                    $values[$day] = $proposed[$day]; $changed++;
                }
                if ($changed) { $em->flush(); $lock->correction($week,$this->getUser()->getId(),'Manager availability: '.$changed.' day(s) changed for '.$driver->getDisplayName()); }
                $message = '<p class="ok">'.($changed ? 'Availability saved and changes logged.' : 'No changes needed.').'</p>';
            }
        }
        $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $rows = '';
        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $i => $name) {
            $rows .= '<div class="day"><div><strong>'.$name.'</strong><small>'.$week->modify('+'.$i.' days')->format('d M').'</small></div><div class="choices"><label><input type="radio" name="days['.$i.']" value="IN" '.($values[$i] === 'IN' ? 'checked' : '').' required> Available</label><label><input type="radio" name="days['.$i.']" value="OFF" '.($values[$i] === 'OFF' ? 'checked' : '').' required> Off</label></div></div>';
        }
        $history = $em->getRepository(RotaAudit::class)->createQueryBuilder('a')
            ->where('a.driverKey = :driver')->andWhere('a.fieldKey = :field')
            ->andWhere('a.weekStart = :week')->andWhere('a.createdAt >= :cutoff')
            ->setParameter('driver', $key)->setParameter('field', 'availability')
            ->setParameter('week', $week)->setParameter('cutoff', new \DateTimeImmutable('-3 months'))
            ->orderBy('a.createdAt', 'DESC')->addOrderBy('a.id', 'DESC')
            ->setMaxResults(100)->getQuery()->getResult();
        $historyRows = '';
        $labels = ['IN' => 'Available', 'OFF' => 'Off', '' => 'Not submitted'];
        foreach ($history as $entry) {
            $dayDate = $entry->weekStart->modify('+'.$entry->dayIndex.' days')->format('D d M Y');
            $before = $labels[$entry->oldValue] ?? $entry->oldValue;
            $after = $labels[$entry->newValue] ?? $entry->newValue;
            $historyRows .= '<tr><td>'.$e($dayDate).'</td><td>'.$e($before).'</td><td>'.$e($after).'</td><td>'.$e($entry->actor->getDisplayName()).'</td><td>'.$e($entry->createdAt->setTimezone($tz)->format('d M Y, H:i')).'</td></tr>';
        }
        $historyHtml = '<section class="card history"><h2>Change history</h2><p>Availability changes for this driver and week, retained for up to three months.</p>'.($historyRows ? '<div class="history-scroll"><table><thead><tr><th>Day</th><th>Previous</th><th>New</th><th>Changed by</th><th>When (UK)</th></tr></thead><tbody>'.$historyRows.'</tbody></table></div>' : '<p>No changes recorded for this week within the last three months.</p>').'</section>';
        $previous = $week->modify('-7 days')->format('Y-m-d');
        $next = $week->modify('+7 days')->format('Y-m-d');
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Edit driver availability · Dulogix</title><style>*{box-sizing:border-box}body{margin:0;background:#f4f7fb;color:#18304a;font:15px system-ui,sans-serif}header{background:#152d49;color:white;padding:17px}main{max-width:600px;margin:25px auto;padding:0 14px}.card{background:white;border:1px solid #dce5ef;border-radius:12px;padding:20px}.day{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #e7edf4}.day small{display:block;color:#61758a;margin-top:4px}.choices{display:flex;gap:12px}.choices label{padding:9px;border:1px solid #d5e0ed;border-radius:7px}button{background:#1a4c77;color:white;border:0;border-radius:8px;padding:13px 18px;width:100%;font-weight:bold;margin-top:18px}a{color:#195581}.ok{color:#126a44}.error{color:#a32d32}.week{display:flex;justify-content:space-between;gap:12px;margin:18px 0}.history{margin-top:16px}.history h2{margin-top:0}.history-scroll{overflow-x:auto}.history table{border-collapse:collapse;width:100%;font-size:13px}.history th,.history td{text-align:left;padding:9px 7px;border-bottom:1px solid #e7edf4;white-space:nowrap}.history th{background:#edf3fa}@media(max-width:440px){.day{align-items:flex-start;flex-direction:column}}</style></head><body><header><strong>Dulogix · Rota Management</strong></header><main><p><a href="/">← Back to weekly rota</a></p><div class="card"><h1>Edit driver availability</h1><p><strong>'.$e($driver->getDisplayName()).'</strong></p><p>Managers can edit availability after the 48-hour cutoff. Changes are recorded with your account and timestamp.</p><div class="week"><a href="?week='.$previous.'">← Previous week</a><strong>'.$week->format('d M Y').'</strong><a href="?week='.$next.'">Next week →</a></div>'.$message.'<form method="post" action="?week='.$e($requested).'"><input type="hidden" name="_token" value="'.$e($csrf->getToken('manager_availability_'.$id)->getValue()).'">'.$rows.'<button type="submit">Save availability</button></form></div>'.$historyHtml.'</main></body></html>';
        return new Response($html);
    }
}
