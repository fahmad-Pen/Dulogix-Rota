<?php

namespace App\Controller;

use App\Entity\RotaAudit;
use App\Entity\RotaAllocationOption;
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
    public function read(string $week, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_OPERATIONS');
        $date = $this->validWeek($week);
        if (!$date) return $this->json(['error' => 'Invalid ISO Monday'], 422);
        $records = $em->getRepository(RotaCell::class)->findBy(['weekStart' => $date]);
        $driverAccounts = array_values(array_filter($em->getRepository(User::class)->findAll(), static fn(User $u) => in_array($u->getDriverType(), ['DRIVER', 'JUMPER'], true) && !in_array('ROLE_ADMIN', $u->getRoles(), true) && !in_array('ROLE_MANAGER', $u->getRoles(), true)));
        return $this->json(['drivers' => array_map(static fn(User $u) => ['key' => 'driver-'.$u->getId(), 'name' => $u->getDisplayName(), 'type' => $u->getDriverType(), 'email' => $u->getEmail(), 'phone' => $u->getPhone()], $driverAccounts), 'allocationOptions' => array_map(static fn(RotaAllocationOption $o) => ['code' => $o->code, 'label' => $o->label, 'active' => $o->active], $em->getRepository(RotaAllocationOption::class)->findBy([], ['sortOrder' => 'ASC', 'code' => 'ASC'])), 'cells' => array_map(static fn(RotaCell $c) => ['driver' => $c->driverKey, 'field' => $c->fieldKey, 'day' => $c->dayIndex, 'value' => $c->value], $records)]);
    }

    #[Route('/{week}/cell', name: 'api_rota_write', requirements: ['week' => '\\d{4}-\\d{2}-\\d{2}'], methods: ['POST'])]
    public function write(string $week, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_OPERATIONS');
        if (!$this->isCsrfTokenValid('rota_edit', (string) $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }
        $date = $this->validWeek($week);
        if (!$date) return $this->json(['error' => 'Invalid ISO Monday'], 422);
        if ($date < new \DateTimeImmutable('monday this week', new \DateTimeZone('Europe/London'))) {
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
        if (!is_string($driver) || !is_string($field) || !is_int($day) || $day < 0 || $day > 6 || !is_string($value)) {
            return $this->json(['error' => 'Invalid cell'], 422);
        }
        if ($driver === 'planning') {
            if (!in_array($field, $planning, true) || ($value !== '' && (!ctype_digit($value) || strlen($value) > 7))) return $this->json(['error' => 'Invalid planning value'], 422);
        } elseif (preg_match('/^driver-([1-9][0-9]*)$/', $driver, $matches)) {
            $target = $em->getRepository(User::class)->find((int) $matches[1]);
            if (!$target || !in_array($target->getDriverType(), ['DRIVER', 'JUMPER'], true) || in_array('ROLE_ADMIN', $target->getRoles(), true) || in_array('ROLE_MANAGER', $target->getRoles(), true)) return $this->json(['error' => 'Unknown driver'], 422);
            if ($field !== 'allocation' || !in_array($value, $codes, true)) return $this->json(['error' => 'Invalid allocation'], 422);
        } else {
            return $this->json(['error' => 'Unknown driver'], 422);
        }
        /** @var User $user */
        $user = $this->getUser();
        $cell = $em->getRepository(RotaCell::class)->findOneBy(['weekStart' => $date, 'driverKey' => $driver, 'fieldKey' => $field, 'dayIndex' => $day]);
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
        return $this->json(['saved' => true, 'value' => $value]);
    }

    private function validWeek(string $week): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $week, new \DateTimeZone('Europe/London'));
        return $date && $date->format('Y-m-d') === $week && $date->format('N') === '1' ? $date : null;
    }
}
