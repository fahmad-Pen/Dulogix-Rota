<?php
namespace App\Controller;

use App\Entity\RotaAudit;
use App\Entity\RotaCell;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class DriverAvailabilityController extends AbstractController
{
    #[Route('/driver/availability', name: 'driver_availability', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em, CsrfTokenManagerInterface $csrf): Response
    {
        $preview = $this->isGranted('ROLE_ADMIN') && $request->query->get('preview') === '1';
        if (!$preview) $this->denyAccessUnlessGranted('ROLE_DRIVER');
        if ($preview && $request->isMethod('POST')) throw $this->createAccessDeniedException('Preview is read-only');
        /** @var User $driver */
        $driver = $this->getUser();
        $tz = new \DateTimeZone('Europe/London');
        $now = new \DateTimeImmutable('now', $tz);
        $week = $now->modify('monday next week')->setTime(0, 0);
        $key = 'driver-'.$driver->getId();
        $repo = $em->getRepository(RotaCell::class);
        $existing = $preview ? [] : $repo->findBy(['weekStart' => $week, 'driverKey' => $key, 'fieldKey' => 'availability']);
        $cells = [];
        $values = array_fill(0, 7, '');
        foreach ($existing as $cell) {
            if ($cell->dayIndex < 0 || $cell->dayIndex > 6) continue;
            $cells[$cell->dayIndex] = $cell;
            $values[$cell->dayIndex] = $cell->value;
        }
        $editable = static fn(int $day): bool => $now < $week->modify('+'.$day.' days')->modify('-48 hours');
        $submitted = count($cells) === 7;
        $message = '';

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('driver_availability', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid form token');
            }
            $proposed = $request->request->all('days');
            $valid = count($proposed) === 7;
            foreach (range(0, 6) as $day) {
                if (!isset($proposed[$day]) || !in_array($proposed[$day], ['IN', 'OFF'], true)) $valid = false;
            }
            if (!$valid) {
                $message = '<p class="error">Choose Available or Off for all seven days.</p>';
            } else {
                $blocked = false;
                foreach (range(0, 6) as $day) {
                    if (!$editable($day) && $values[$day] !== '' && $proposed[$day] !== $values[$day]) $blocked = true;
                }
                if ($blocked) {
                    $message = '<p class="error">An existing locked selection cannot be changed online. Contact your manager to request a change.</p>';
                } else {
                    $changed = 0;
                    $late = 0;
                    foreach (range(0, 6) as $day) {
                        if ($values[$day] === $proposed[$day]) continue;
                        $cell = $cells[$day] ?? new RotaCell();
                        $old = $values[$day];
                        if ($old === '' && !$editable($day)) $late++;
                        $cell->weekStart = $week;
                        $cell->driverKey = $key;
                        $cell->fieldKey = 'availability';
                        $cell->dayIndex = $day;
                        $cell->value = $proposed[$day];
                        $cell->updatedAt = new \DateTimeImmutable();
                        $cell->updatedBy = $driver;
                        $audit = new RotaAudit();
                        $audit->weekStart = $week;
                        $audit->driverKey = $key;
                        $audit->fieldKey = 'availability';
                        $audit->dayIndex = $day;
                        $audit->oldValue = $old;
                        $audit->newValue = ($old === '' && !$editable($day)) ? $proposed[$day].' (LATE - MANAGER CONFIRMATION REQUIRED)' : $proposed[$day];
                        $audit->actor = $driver;
                        $audit->createdAt = new \DateTimeImmutable();
                        $em->persist($cell);
                        $em->persist($audit);
                        $values[$day] = $proposed[$day];
                        $changed++;
                    }
                    if ($changed) $em->flush();
                    $submitted = true;
                    $message = '<p class="ok">'.($changed ? 'Availability saved successfully.' : 'No changes needed.').'</p>';
                    if ($late) $message .= '<p class="error">'.$late.' late first-time selection(s) recorded after the cutoff. Contact your manager for confirmation; this is not automatic approval.</p>';
                }
            }
        }

        $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $rows = '';
        foreach ($days as $i => $day) {
            $date = $week->modify('+'.$i.' days')->format('d M');
            $value = $values[$i];
            $locked = !$editable($i);
            $disabled = (($locked && $value !== '') || $preview) ? ' disabled' : '';
            $status = $locked ? ($value === '' ? '<small class="locked">Late submission · manager confirmation required</small>' : '<small class="locked">Locked · contact manager for changes</small>') : '<small class="open">Editable until '.$week->modify('+'.$i.' days')->modify('-48 hours')->format('D d M, H:i').'</small>';
            $rows .= '<div class="day"><div class="label"><strong>'.$day.'</strong><small>'.$date.'</small>'.$status.'</div><div class="choices"><label><input type="radio" name="days['.$i.']" value="IN"'.($value === 'IN' ? ' checked' : '').$disabled.' required> Available</label><label><input type="radio" name="days['.$i.']" value="OFF"'.($value === 'OFF' ? ' checked' : '').$disabled.' required> Off</label>';
            if ($locked && $value !== '') $rows .= '<input type="hidden" name="days['.$i.']" value="'.$e($value).'">';
            $rows .= '</div></div>';
        }
        $button = $preview ? '<p class="rules"><strong>Administrator preview · read-only.</strong> This shows the driver layout without accessing or changing any driver data. <a href="/">Return to rota</a></p>' : '<button type="submit">'.($submitted ? 'Save availability changes' : 'Submit my week').'</button>';
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dulogix · My availability</title><link rel="icon" type="image/png" href="/favicon.png"><style>*{box-sizing:border-box}body{margin:0;background:#f4f7fb;color:#18304a;font:15px system-ui,sans-serif}header{background:#152d49;color:#fff;padding:13px 16px;display:flex;justify-content:space-between;gap:12px}a{color:inherit}main{max-width:570px;margin:18px auto;padding:0 12px}h1{font-size:23px;margin:0 0 5px}p{line-height:1.5}.muted,small{color:#61758a}.card{background:#fff;border:1px solid #dce5ef;border-radius:12px;padding:16px}.rules{background:#eef5ff;border-left:4px solid #1a4c77;border-radius:6px;padding:12px 14px;margin-bottom:16px}.rules p{margin:8px 0}.day{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #e7edf4}.label{min-width:130px}.label small{display:block;margin-top:3px}.label .locked{color:#a32d32}.label .open{color:#126a44}.choices{display:flex;gap:8px;flex-wrap:wrap}.choices label{display:flex;align-items:center;gap:4px;padding:9px;border:1px solid #d5e0ed;border-radius:8px;cursor:pointer}.choices input{accent-color:#205e94}button{background:#1a4c77;color:#fff;border:0;border-radius:8px;padding:13px 18px;width:100%;font-size:16px;font-weight:700;margin-top:16px}.ok{color:#126a44}.error{color:#a32d32}body{background:linear-gradient(145deg,#eaf5ff,#f4f7fb 50%,#f0fdf4)}main{max-width:680px}.card{padding:24px;box-shadow:0 14px 40px rgba(21,45,73,.09)}h1{color:#12375a}.rules{background:#eaf3ff;border-left-color:#327bb5}.day{background:#f8fbff;border:1px solid #e2ebf5;border-radius:12px;padding:14px;margin:10px 0}.choices label{min-width:95px;justify-content:center;border:2px solid #b8dbf5;background:#e9f5ff;color:#17577c;font-weight:700;padding:12px;border-radius:12px}.choices label:nth-child(2){background:#fff1e9;border-color:#f5d3ba;color:#985019}.choices label:has(input:checked){background:#c9f4db;border-color:#24975b;color:#125e37}.choices label:nth-child(2):has(input:checked){background:#ffe0df;border-color:#df7974;color:#8b2929}.choices label:has(input:disabled){cursor:not-allowed;opacity:.8}.choices input{width:18px;height:18px;accent-color:#198754}button{background:#166a9e;box-shadow:0 4px 12px rgba(22,106,158,.18)}@media(max-width:440px){.day{align-items:flex-start;flex-direction:column}.choices{width:100%}.choices label{flex:1}}</style>'.SectionAccess::CSS.'</head><body><header><a href="/account"><img src="/dulogix-logo.png" alt="Dulogix" style="height:34px;width:auto;background:white;border-radius:4px;padding:2px"></a>'.SectionAccess::navigation($this->getUser()).'</header><main><h1>My availability</h1><p class="muted">Week '.$week->format('d M').' – '.$week->modify('+6 days')->format('d M Y').'</p><div class="card"><div class="rules"><strong>Availability rules</strong><p>Choose Available or Off for all seven days when submitting your week.</p><p>You can update each day until 48 hours before that day begins (midnight, UK time).</p><p><strong>Within 48 hours, contact your manager directly to request any change.</strong> A change is not approved until your manager confirms it.</p></div>'.$message.'<form method="post"><input type="hidden" name="_token" value="'.$e($csrf->getToken('driver_availability')->getValue()).'">'.$rows.$button.'</form></div></main></body></html>';
        return new Response($html);
    }
}
