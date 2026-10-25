<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'rota_audit')]
class RotaAudit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(type: 'date_immutable')]
    public \DateTimeImmutable $weekStart;

    #[ORM\Column(length: 80)]
    public string $driverKey = '';

    #[ORM\Column(length: 80)]
    public string $fieldKey = '';

    #[ORM\Column]
    public int $dayIndex = 0;

    #[ORM\Column(length: 100)]
    public string $oldValue = '';

    #[ORM\Column(length: 100)]
    public string $newValue = '';

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $actor;
}
