<?php

namespace App\Controller;

use App\Entity\RotaAudit;
use App\Entity\RotaAllocationOption;
use App\Entity\RotaStatusOption;
use App\Entity\RotaCell;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/rota')]
final class RotaApiController extends AbstractController
{
    #[Route('/{week}', name: 'api_rota_read', requirements: ['week' => '\\d{4}-\\d{2}-\\d{2}'], methods: ['GET'])]
    public function read(string $week, EntityManagerInterface $em, \Doctrine\DBAL\Connection $db, RotaWeekLock $lock): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_OPERATIONS');
        $date = $this->validWeek($week);
        if (!$date) return $this->json(['error' => 'Invalid ISO Monday'], 422);
        $records = $em->getRepository(RotaCell::class)->findBy(['weekStart' => $date]);
        $driverAccounts = array_values(array_filter($em->getRepository(User::class)->findAll(), static fn(User $u) => in_array($u->getDriverType(), ['DRIVER', 'JUMPER'], true) && !in_array('ROLE_ADMIN', $u->getRoles(), true) && !in_array('ROLE_MANAGER', $u->getRoles(), true)));
        $requirements = $db->fetchAllAssociative('SELECT id, name FROM driver_requirement ORDER BY sort_order, id');
        $completed = [];
        foreach ($db->fetchAllAssociative('SELECT driver_id, requirement_id FROM driver_requirement_completion') as $item) $completed[(int)$item['driver_id']][(int)$item['requirement_id']] = true;
        return $this->json(['drivers' => array_map(static fn(User $u) => ['key' => 'driver-'.$u->getId(), 'name' => $u->getDisplayName(), 'type' => $u->getDriverType(), 'email' => $u->getEmail(),  'phone' => $u->getPhone(), 'training' => array_map(static fn(array $r) => ['name' => $r['name'], 'done' => isset($completed[$u->getId()][(int)$r['id']])], $requirements)], $driverAccounts), 'statusOptions' => array_map(static fn(RotaStatusOption $o) => ['code' => $o->code, 'active' => $o->active], $em->getRepository(RotaStatusOption::class)->findBy([], ['sortOrder' => 'ASC', 'code' => 'ASC'])), 'allocationOptions' => array_map(static fn(RotaAllocationOption $o) => ['code' => $o->code, 'label' => $o->label, 'active' => $o->active, 'countsHeadcount' => $o->countsHeadcount, 'countsDrivingDays' => $o->countsDrivingDays], $em->getRepository(RotaAllocationOption::class)->findBy([], ['sortOrder' => 'ASC', 'code' => 'ASC'])), 'canEdit' => $lock->canEdit($date, $this->getUser()->getId()), 'historical' => $lock->historical($date), 'cells' => array_map(static fn(RotaCell $c) => ['driver' => $c->driverKey, 'field' => $c->fieldKey, 'day' => $c->dayIndex, 'value' => $c->value], $records)]);
    }

    #[Route('/{week}/cell', name: 'api_rota_write', requirements: ['week' => '\\d{4}-\\d{2}-\\d{2}'], methods: ['POST'])]
    public function write(string $week, Request $request, EntityManagerInterface $em, RotaWeekLock $lock): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_OPERATIONS');
        if (!$this->isCsrfTokenValid('rota_edit', (string) $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }
        $date = $this->validWeek($week);
        if (!$date) return $this->json(['error' => 'Invalid ISO Monday'], 422);
        if (!$lock->canEdit($date, $this->getUser()->getId())) {
            return $this->json(['error' => 'Historical weeks are locked'], 423);
        }
        try { $data = $request->toArray(); } catch (\Throwable) { return $this->json(['error' => 'Invalid JSON'], 400); }
        $driver = $data['driver'] ?? '';
        $field = $data['field'] ?? '';
        $day = $data['day'] ?? null;
        $value = $data['value'] ?? null;
        $planning = ['Route Target', 'Current Demand Signal', 'ECP', 'Required drivers', 'DA Capacity', 'Standby'];
        $codes = array_map(static fn(RotaAllocationOption $o) => $o->code, $em->getRepository(RotaAllocationOption::class)->findBy(['active' => true]));
        $codes[] = '';
        $statusCodes = array_map(static fn(RotaStatusOption $o) => $o->code, $em->getRepository(RotaStatusOption::class)->findBy(['active' => true]));
        $statusCodes[] = '';
        if (!is_string($driver) || !is_string($field) || !is_int($day) || $day < 0 || $day > 6 || !is_string($value)) {
            return $this->json(['error' => 'Invalid cell'], 422);
        }
        if ($driver === 'planning') {
            if (!in_array($field, $planning, true) || ($value !== '' && (!ctype_digit($value) || strlen($value) > 7))) return $this->json(['error' => 'Invalid planning value'], 422);
        } elseif (preg_match('/^driver-([1-9][0-9]*)$/', $driver, $matches)) {
            $target = $em->getRepository(User::class)->find((int) $matches[1]);
            if (!$target || !in_array($target->getDriverType(), ['DRIVER', 'JUMPER'], true) || in_array('ROLE_ADMIN', $target->getRoles(), true) || in_array('ROLE_MANAGER', $target->getRoles(), true)) return $this->json(['error' => 'Unknown driver'], 422);
            if (($field === 'allocation' && !in_array($value, $codes, true)) || ($field === 'status' && !in_array($value, $statusCodes, true)) || ($field === 'availability' && !in_array($value, ['IN', 'OFF'], true)) || !in_array($field, ['allocation', 'status', 'availability'], true)) return $this->json(['error' => 'Invalid allocation, status or availability'], 422);
        } else {
            return $this->json(['error' => 'Unknown driver'], 422);
        }
        /** @var User $user */
        $user = $this->getUser();
        $cell = $em->getRepository(RotaCell::class)->findOneBy(['weekStart' => $date, 'driverKey' => $driver, 'fieldKey' => $field, 'dayIndex' => $day]);
        if ($field === 'allocation' && $value !== '') {
            $drivingCodes = [];
            foreach ($em->getRepository(RotaAllocationOption::class)->findAll() as $option) {
                if ($option->countsDrivingDays) $drivingCodes[] = $option->code;
            }
            if (in_array($value, $drivingCodes, true)) {
                $selectedDate = $date->modify('+'.$day.' days');
                $start = $selectedDate->modify('-6 days');
                $end = $selectedDate->modify('+6 days');
                $nearby = $em->getRepository(RotaCell::class)->createQueryBuilder('c')
                    ->where('c.driverKey = :driver')
                    ->andWhere('c.fieldKey = :field')
                    ->andWhere('c.weekStart BETWEEN :start AND :end')
                    ->setParameter('driver', $driver)
                    ->setParameter('field', 'allocation')
                    ->setParameter('start', $start->modify('monday this week'))
                    ->setParameter('end', $end->modify('monday this week'))
                    ->getQuery()->getResult();
                $assigned = [];
                foreach ($nearby as $existing) {
                    $dayDate = $existing->weekStart->modify('+'.$existing->dayIndex.' days')->format('Y-m-d');
                    $assigned[$dayDate] = in_array($existing->value, $drivingCodes, true);
                }
                $assigned[$selectedDate->format('Y-m-d')] = true;
                $run = 0;
                for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 day')) {
                    $run = ($assigned[$cursor->format('Y-m-d')] ?? false) ? $run + 1 : 0;
                    if ($run > 6) {
                        return $this->json(['error' => 'Allocation not allowed: this would create more than six consecutive driving days. A non-driving day is required.'], 422);
                    }
                }
            }
        }
        $old = $cell?->value ?? '';
        if ($old === $value) return $this->json(['saved' => true, 'unchanged' => true]);
        if (!$cell) {
            $cell = new RotaCell();
            $cell->weekStart = $date;
            $cell->driverKey = $driver;
            $cell->fieldKey = $field;
            $cell->dayIndex = $day;
        }
        $cell->value = $value;
        $cell->updatedBy = $user;
        $cell->updatedAt = new \DateTimeImmutable();
        $audit = new RotaAudit();
        $audit->weekStart = $date;
        $audit->driverKey = $driver;
        $audit->fieldKey = $field;
        $audit->dayIndex = $day;
        $audit->oldValue = $old;
        $audit->newValue = $value;
        $audit->actor = $user;
        $audit->createdAt = new \DateTimeImmutable();
        $em->persist($cell);
        $em->persist($audit);
        $em->flush();
        $lock->correction($date, $user->getId(), $driver.' / '.$field.' / day '.$day.': '.$old.' → '.$value);
        return $this->json(['saved' => true, 'value' => $value]);
    }

    private function validWeek(string $week): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $week, new \DateTimeZone('Europe/London'));
        return $date && $date->format('Y-m-d') === $week && $date->format('N') === '1' ? $date : null;
    }
}
