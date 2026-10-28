<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name: 'rota_allocation_option')]
class RotaAllocationOption
{
    #[ORM\Id]
    #[ORM\Column(length: 24)]
    public string $code = '';
    #[ORM\Column(length: 80)]
    public string $label = '';
    #[ORM\Column(name: 'sort_order', type: 'integer')]
    public int $sortOrder = 0;
    #[ORM\Column(type: 'boolean')]
    public bool $active = true;
    #[ORM\Column(name: 'counts_headcount', type: 'boolean', options: ['default' => false])]
    public bool $countsHeadcount = false;
    #[ORM\Column(name: 'counts_driving_days', type: 'boolean', options: ['default' => false])]
    public bool $countsDrivingDays = false;
}
