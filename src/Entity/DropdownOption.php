<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'dropdown_option')]
#[ORM\UniqueConstraint(name:'uniq_dropdown_list_code',columns:['list_key','code'])]
class DropdownOption {
 #[ORM\Id]
 #[ORM\GeneratedValue]
 #[ORM\Column]
 public ?int $id=null;
 #[ORM\Column(name:'list_key',length:80)]
 public string $listKey='';
 #[ORM\Column(length:24)]
 public string $code='';
 #[ORM\Column(length:100)]
 public string $label='';
 #[ORM\Column(name:'sort_order')]
 public int $sortOrder=0;
 #[ORM\Column]
 public bool $active=true;
}
