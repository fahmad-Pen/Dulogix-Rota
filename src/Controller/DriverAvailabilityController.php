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
        $currentWeek = $now->modify('monday next week')->setTime(0, 0);
        // Show the next two weeks still open for driver planning.
        if ($now >= $currentWeek->modify('-3 days')) {
            $currentWeek = $currentWeek->modify('+7 days');
        }
        $nextWeek = $currentWeek->modify('+7 days');
        $requestedWeek = (string) $request->query->get('week', '');
        $week = in_array($requestedWeek, [$currentWeek->format('Y-m-d'), $nextWeek->format('Y-m-d')], true)
            ? new \DateTimeImmutable($requestedWeek.' 00:00:00', $tz)
            : $currentWeek;
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
        $cutoff = $week->modify('-3 days'); // Friday 00:00 before the Monday-starting week, UK time.
        $editable = static fn(int $day): bool => $now < $cutoff;
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
                    if (!$editable($day) && $proposed[$day] !== $values[$day]) $blocked = true;
                }
                if ($blocked) {
                    $message = '<p class="error">Availability is locked from Friday 00:00 (UK time). Contact your manager to request a change.</p>';
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
        $tabs = '<nav class="week-tabs" aria-label="Availability weeks">';
        foreach ([$currentWeek, $nextWeek] as $tabWeek) {
            $selected = $tabWeek->format('Y-m-d') === $week->format('Y-m-d');
            $tabs .= '<a class="week-tab'.($selected ? ' selected' : '').'" href="/driver/availability?week='.$tabWeek->format('Y-m-d').'"'.($selected ? ' aria-current="page"' : '').'>Week commencing <strong>'.$tabWeek->format('j F').'</strong><small>Open</small></a>';
        }
        $tabs .= '</nav>';
        foreach ($days as $i => $day) {
            $date = $week->modify('+'.$i.' days')->format('d M');
            $value = $values[$i];
            $locked = !$editable($i);
            $disabled = ($locked || $preview) ? ' disabled' : '';
            $status = $locked ? ($value === '' ? '<small class="locked">Late submission · manager confirmation required</small>' : '<small class="locked">Locked · contact manager for changes</small>') : '<small class="open">Editable until '.$cutoff->format('D d M, H:i').'</small>';
            $rows .= '<div class="day"><div class="label"><strong>'.$day.'</strong><small>'.$date.'</small>'.$status.'</div><div class="choices"><label><input type="radio" name="days['.$i.']" value="IN"'.($value === 'IN' ? ' checked' : '').$disabled.' required> Available</label><label><input type="radio" name="days['.$i.']" value="OFF"'.($value === 'OFF' ? ' checked' : '').$disabled.' required> Off</label>';
            if ($locked && $value !== '') $rows .= '<input type="hidden" name="days['.$i.']" value="'.$e($value).'">';
            $rows .= '</div></div>';
        }
        $button = $preview ? '<p class="rules"><strong>Administrator preview · read-only.</strong> This shows the driver layout without accessing or changing any driver data. <a href="/">Return to rota</a></p>' : '<button type="submit">'.($submitted ? 'Save availability changes' : 'Submit my week').'</button>';
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dulogix · My availability</title><link rel="icon" type="image/png" href="/favicon.png"><link rel="stylesheet" href="/driver-availability.css?v=1"></head><body><header><a href="/account"><img src="/dulogix-logo.png" alt="Dulogix" class="availability-logo"></a>'.SectionAccess::navigation($this->getUser()).'</header><main><h1>My availability</h1>'.$tabs.'<p class="week-heading">You are submitting availability for '.$week->format('j').' to '.$week->modify('+6 days')->format('j F Y').'</p><div class="card"><div class="rules"><strong>Availability rules</strong><p>Choose Available or Off for all seven days when submitting your week.</p><p>You can submit or change availability for the entire week until Thursday 23:59 (UK time) before that week starts. All seven days lock from Friday 00:00.</p><p><strong>After the Friday cutoff, contact your manager directly to request any change.</strong> A change is not approved until your manager confirms it.</p></div>'.$message.'<form method="post" action="/driver/availability?week='.$week->format('Y-m-d').'"><input type="hidden" name="_token" value="'.$e($csrf->getToken('driver_availability')->getValue()).'">'.$rows.$button.'</form></div></main></body></html>';
        return new Response($html);
    }
}