<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email = '';

    #[ORM\Column(length: 80, unique: true)]
    private string $username = '';

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 16, options: ['default' => 'DRIVER'])]
    private string $driverType = 'DRIVER';

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 120)]
    private string $displayName = '';

    public function getDriverType(): string { return $this->driverType; }
    public function setDriverType(string $type): self { if (!in_array($type, ['MANAGER', 'DRIVER', 'JUMPER'], true)) throw new \InvalidArgumentException('Invalid type'); $this->driverType = $type; return $this; }
    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = mb_strtolower(trim($email)); return $this; }
    public function getUsername(): string { return $this->username; }
    public function setUsername(string $username): self { $this->username = mb_strtolower(trim($username)); return $this; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): self { $this->phone = $phone; return $this; }
    public function getUserIdentifier(): string { return $this->username; }
    public function getRoles(): array {
        $roles = [...$this->roles, 'ROLE_USER'];
        // Legacy driver accounts may have only ROLE_USER despite being classified as drivers.
        // Never infer driver access for administrators or managers.
        if (in_array($this->driverType, ['DRIVER', 'JUMPER'], true)
            && !in_array('ROLE_ADMIN', $roles, true)
            && !in_array('ROLE_MANAGER', $roles, true)) {
            $roles[] = 'ROLE_DRIVER';
        }
        return array_values(array_unique($roles));
    }
    public function setRoles(array $roles): self { $this->roles = $roles; return $this; }
    public function getPassword(): ?string { return $this->password; }
    public function setPassword(string $hash): self { $this->password = $hash; return $this; }
    public function getDisplayName(): string { return $this->displayName; }
    public function setDisplayName(string $name): self { $this->displayName = $name; return $this; }
    public function eraseCredentials(): void {}
}
