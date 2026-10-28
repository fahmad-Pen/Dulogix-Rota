<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'rota_status_option')]
class RotaStatusOption
{
    #[ORM\Id]
    #[ORM\Column(length: 80)]
    public string $code = '';
    #[ORM\Column(name: 'sort_order', type: 'integer')]
    public int $sortOrder = 0;
    #[ORM\Column(type: 'boolean')]
    public bool $active = true;
}
