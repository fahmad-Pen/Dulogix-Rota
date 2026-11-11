<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OperationsDriverSwapController extends AbstractController
{
    /** @return array<int, string> */
    public static function resolveVans(int $from, int $to, bool $swap, array $fixed, array $oldVans): array
    {
        $sourceFixed = isset($fixed[$from]);
        $targetFixed = isset($fixed[$to]);
        // When one driver has a fixed van, that van stays with its driver
        // and the other driver's existing non-fixed van stays with them.
        // Only exchange vans when neither driver has a fixed van.
        if ($swap) {
            if ($sourceFixed || $targetFixed) {
                return [
                    $from => $sourceFixed ? $fixed[$from] : ($oldVans[$from] ?? ''),
                    $to => $targetFixed ? $fixed[$to] : ($oldVans[$to] ?? ''),
                ];
            }
            return [$from => $oldVans[$to] ?? '', $to => $oldVans[$from] ?? ''];
        }
        return [
            $from => $sourceFixed ? $fixed[$from] : '',
            $to => $targetFixed ? $fixed[$to] : ($sourceFixed
                ? ($oldVans[$to] ?? '')
                : (($oldVans[$from] ?? '') ?: ($oldVans[$to] ?? ''))),
        ];
    }

    /** @return array<int, ?string> */
    public static function resolveStartTimes(int $from, int $to, bool $swap, array $oldTimes): array
    {
        return [
            $from => $swap ? ($oldTimes[$to] ?? null) : null,
            $to => $oldTimes[$from] ?? null,
        ];
    }

    #[Route('/operations/change-driver', name: 'operations_change_driver', methods: ['POST'])]
    public function change(Request $request, Connection $db, EntityManagerInterface $em): Response
    {
        if (!SectionAccess::canEdit($this->getUser(), 'operations')) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('operations_report', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid form token.');
        }
        $day = (string) $request->request->get('date', '');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $day);
        if (!$date || $date->format('Y-m-d') !== $day) {
            throw $this->createNotFoundException('Invalid date.');
        }
        $from = (int) $request->request->get('from_driver', 0);
        $to = (int) $request->request->get('to_driver', 0);
        if ($from <= 0 || $to <= 0 || $from === $to) {
            $this->addFlash('lane_error', 'Choose a different replacement driver.');
            return $this->redirectToRoute('operations_report', ['date' => $day]);
        }
        $drivers = [];
        foreach ([$from, $to] as $id) {
            $user = $em->getRepository(User::class)->find($id);
            if (!$user || !$user->isActive() || !in_array($user->getDriverType(), ['DRIVER', 'JUMPER'], true)
                || in_array('ROLE_MANAGER', $user->getRoles(), true) || in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                throw $this->createAccessDeniedException('Invalid driver.');
            }
            $drivers[$id] = $user;
        }
        $db->beginTransaction();
        try {
            // Lock the date's plan to prevent overlapping manager changes.
            $db->fetchAllAssociative('SELECT driver_id FROM operations_lane_plan WHERE report_date = ? FOR UPDATE', [$day]);
            $plans = [];
            foreach ($db->fetchAllAssociative('SELECT * FROM operations_lane_plan WHERE report_date = ? AND driver_id IN (?, ?)', [$day, $from, $to]) as $plan) {
                $plans[(int) $plan['driver_id']] = $plan;
            }
            if (!isset($plans[$from])) {
                throw new \DomainException('The selected driver no longer has a route. Refresh the page.');
            }
            $swap = isset($plans[$to]);
            $fixed = [];
            foreach ($db->fetchAllAssociative('SELECT driver_id, van_registration FROM operations_fixed_van') as $item) {
                $fixed[(int) $item['driver_id']] = (string) $item['van_registration'];
            }
            $daily = [];
            $oldTimes = [];
            foreach ($db->fetchAllAssociative('SELECT driver_id,van_registration,start_time FROM operations_daily WHERE report_date=?', [$day]) as $item) {
                $daily[(int) $item['driver_id']] = (string) ($item['van_registration'] ?? '');
                $oldTimes[(int) $item['driver_id']] = $item['start_time'];
            }
            $newTimes = self::resolveStartTimes($from, $to, $swap, $oldTimes);
            $oldVans = [
                $from => ($daily[$from] ?? '') ?: ($fixed[$from] ?? ''),
                $to => ($daily[$to] ?? '') ?: ($fixed[$to] ?? ''),
            ];
            $newVans = self::resolveVans($from, $to, $swap, $fixed, $oldVans);
            // Fixed vans cannot be assigned to another driver, and daily vans must remain unique.
            foreach ($newVans as $id => $van) {
                if ($van === '') {
                    continue;
                }
                foreach ($fixed as $owner => $fixedVan) {
                    if ($owner !== $id && $fixedVan === $van) {
                        throw new \DomainException('A fixed van cannot be transferred to another driver.');
                    }
                }
                foreach ($daily as $owner => $dailyVan) {
                    if (!in_array($owner, [$from, $to], true) && $dailyVan === $van) {
                        throw new \DomainException('Van '.$van.' is already assigned to another driver.');
                    }
                }
            }
            if ($newVans[$from] !== '' && $newVans[$from] === $newVans[$to]) {
                throw new \DomainException('Both drivers cannot have the same van.');
            }
            $published = (int) $db->fetchOne('SELECT COUNT(*) FROM operations_lane_publication WHERE report_date=?', [$day]) > 0;
            $db->delete('operations_lane_plan', ['report_date' => $day, 'driver_id' => $from]);
            if ($swap) {
                $db->delete('operations_lane_plan', ['report_date' => $day, 'driver_id' => $to]);
            }
            $actor = (int) $this->getUser()->getId();
            $now = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/London')))->format('Y-m-d H:i:s');
            foreach ([$from => ($swap ? $plans[$to] : null), $to => $plans[$from]] as $id => $plan) {
                if ($plan === null) {
                    continue;
                }
                $db->insert('operations_lane_plan', [
                    'report_date' => $day, 'driver_id' => $id, 'color' => $plan['color'],
                    'holding_time' => $plan['holding_time'], 'stg_location' => $plan['stg_location'],
                    'route' => $plan['route'], 'uploaded_by' => $actor, 'uploaded_at' => $now,
                ]);
            }
            // A van can be unique per date. Release both old registrations first,
            // then assign the new ones; otherwise swapping A's van to B can collide
            // with B's still-present old row before the loop reaches B.
            // Route start times move with route assignments; comments remain with drivers.
            $existingDaily = [];
            foreach ([$from, $to] as $id) {
                $existingDaily[$id] = $db->fetchOne(
                    'SELECT id FROM operations_daily WHERE report_date=? AND driver_id=? FOR UPDATE',
                    [$day, $id]
                );
            }
            foreach ($existingDaily as $id => $existing) {
                if ($existing !== false) {
                    $db->update('operations_daily', ['van_registration' => null, 'start_time' => $newTimes[$id]], ['id' => $existing]);
                }
            }
            foreach ($newVans as $id => $van) {
                if ($existingDaily[$id] !== false) {
                    if ($van !== '') {
                        $db->update('operations_daily', ['van_registration' => $van], ['id' => $existingDaily[$id]]);
                    }
                } elseif ($van !== '' || $newTimes[$id] !== null) {
                    $db->insert('operations_daily', [
                        'report_date' => $day, 'driver_id' => $id, 'van_registration' => $van,
                        'start_time' => $newTimes[$id], 'comments' => '',
                    ]);
                }
            }
            if ($published) {
                foreach ([$from, $to] as $id) {
                    $db->delete('operations_lane_publication', ['report_date' => $day, 'driver_id' => $id]);
                    $plan = $id === $to ? $plans[$from] : ($swap ? $plans[$to] : null);
                    if ($plan === null) {
                        continue;
                    }
                    $db->insert('operations_lane_publication', [
                        'report_date' => $day, 'driver_id' => $id,
                        'holding_time' => $plan['holding_time'] ?: null,
                        'route' => $plan['route'] ?: null,
                        'stg_location' => $plan['stg_location'] ?: null,
                        'van_registration' => $newVans[$id] ?: null,
                        'published_by' => $actor, 'published_at' => $now,
                    ]);
                }
            }
            $db->insert('operations_driver_change_audit', [
                'report_date' => $day, 'from_driver_id' => $from, 'to_driver_id' => $to,
                'action' => $swap ? 'swap' : 'change', 'before_data' => json_encode([
                    'assignments' => [$from => $plans[$from], $to => $plans[$to] ?? null],
                    'vans' => $oldVans,
                    'start_times' => [$from => $oldTimes[$from] ?? null, $to => $oldTimes[$to] ?? null],
                ], JSON_THROW_ON_ERROR),
                'after_data' => json_encode([
                    'assignments' => [$from => $swap ? $plans[$to] : null, $to => $plans[$from]],
                    'vans' => $newVans,
                    'start_times' => $newTimes,
                ], JSON_THROW_ON_ERROR),
                'actor_id' => $actor, 'created_at' => $now,
            ]);
            $db->commit();
            $this->addFlash('lane_success', ($swap ? 'Swapped routes for ' : 'Changed route driver from ')
                .$drivers[$from]->getDisplayName().' and '.$drivers[$to]->getDisplayName()
                .($published ? '. Published assignments updated.' : '. Publish when ready.'));
        } catch (\DomainException $e) {
            $db->rollBack();
            $this->addFlash('lane_error', $e->getMessage());
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        return $this->redirectToRoute('operations_report', ['date' => $day]);
    }
}
