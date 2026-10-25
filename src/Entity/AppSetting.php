<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'app_setting')]
class AppSetting {
 #[ORM\Id]
 #[ORM\Column(name:'`key`',length:100)]
 public string $key='';
 #[ORM\Column(type:'text')]
 public string $value='';
}
