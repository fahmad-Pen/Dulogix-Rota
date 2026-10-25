<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'rota_cell')]
#[ORM\UniqueConstraint(name: 'uniq_rota_cell', columns: ['week_start', 'driver_key', 'field_key', 'day_index'])]
class RotaCell
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
    public string $value = '';

    #[ORM\Column]
    public \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $updatedBy;
}
