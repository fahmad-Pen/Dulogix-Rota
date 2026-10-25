<?php

namespace App\Controller;

use App\Entity\RotaCell;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class RotaWhatsAppController extends AbstractController
{
    #[Route('/api/whatsapp/general/{id}', name: 'api_whatsapp_general', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function general(int $id, \Symfony\Component\HttpFoundation\Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_OPERATIONS');
        if (!$this->isCsrfTokenValid('rota_edit', (string) $request->headers->get('X-CSRF-Token'))) return $this->json(['error' => 'Invalid CSRF token'], 403);
        try { $data = $request->toArray(); } catch (\Throwable) { return $this->json(['error' => 'Invalid JSON'], 400); }
        $message = $data['message'] ?? null;
        if (!is_string($message) || !trim($message) || mb_strlen($message) > 2000) return $this->json(['error' => 'Enter a message up to 2000 characters'], 422);
        $driver = $em->getRepository(User::class)->find($id);
        if (!$driver || !in_array('ROLE_DRIVER', $driver->getRoles(), true)) return $this->json(['error' => 'Driver not found'], 404);
        $phone = preg_replace('/[^0-9+]/', '', (string) $driver->getPhone());
        if (str_starts_with($phone, '00')) $phone = '+'.substr($phone, 2);
        if (!preg_match('/^\\+[1-9][0-9]{7,14}$/', $phone)) return $this->json(['error' => 'Driver needs an international-format phone number'], 422);
        return $this->json(['url' => 'https://wa.me/'.substr($phone, 1).'?text='.rawurlencode(trim($message)), 'status' => 'draft_only']);
    }

    #[Route('/api/rota/{week}/whatsapp/{id}', name: 'api_rota_whatsapp', requirements: ['week' => '\\d{4}-\\d{2}-\\d{2}', 'id' => '\\d+'], methods: ['GET'])]
    public function link(string $week, int $id, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_OPERATIONS');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $week, new \DateTimeZone('Europe/London'));
        if (!$date || $date->format('Y-m-d') !== $week || $date->format('N') !== '1') {
            return $this->json(['error' => 'Invalid week'], 422);
        }
        $today = new \DateTimeImmutable('now', new \DateTimeZone('Europe/London'));
        $nextMonday = $today->modify('monday next week')->setTime(0, 0);
        if ($date->format('Y-m-d') !== $nextMonday->format('Y-m-d')) {
            return $this->json(['error' => 'Only the coming week can be shared'], 422);
        }
        $driver = $em->getRepository(User::class)->find($id);
        if (!$driver || !in_array('ROLE_DRIVER', $driver->getRoles(), true)) {
            return $this->json(['error' => 'Driver not found'], 404);
        }
        $phone = preg_replace('/[^0-9+]/', '', (string) $driver->getPhone());
        if (str_starts_with($phone, '00')) $phone = '+'.substr($phone, 2);
        if (!preg_match('/^\+[1-9][0-9]{7,14}$/', $phone)) {
            return $this->json(['error' => 'Enter the driver phone in international format, e.g. +447...'], 422);
        }
        $cells = $em->getRepository(RotaCell::class)->findBy([
            'weekStart' => $date, 'driverKey' => 'driver-'.$id, 'fieldKey' => 'allocation',
        ]);
        $allocations = array_fill(0, 7, '');
        foreach ($cells as $cell) {
            if ($cell->dayIndex >= 0 && $cell->dayIndex <= 6) $allocations[$cell->dayIndex] = $cell->value;
        }
        // Blank days remain visible as unassigned on the shared rota.
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $lines = ['Dulogix - Weekly rota', $date->format('d M').' - '.$date->modify('+6 days')->format('d M Y'), 'Driver: '.$driver->getDisplayName(), ''];
        foreach ($days as $i => $day) $lines[] = $day.': '.($allocations[$i] === '' ? 'Not assigned' : $allocations[$i]);
        $lines[] = '';
        $lines[] = 'Please contact operations if you need to discuss an allocation.';
        return $this->json(['url' => 'https://wa.me/'.substr($phone, 1).'?text='.rawurlencode(implode("\n", $lines)), 'status' => 'draft_only', 'driverName' => $driver->getDisplayName(), 'weekStart' => $week, 'allocations' => $allocations, 'phone' => substr($phone, 1)]);
    }
}
