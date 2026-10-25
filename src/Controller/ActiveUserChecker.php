<?php
namespace App\Controller;
use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
final class ActiveUserChecker implements UserCheckerInterface {
    public function checkPreAuth(UserInterface $user): void {
        if ($user instanceof User && !$user->isActive()) throw new CustomUserMessageAccountStatusException('Account inactive. Contact your manager or administrator.');
    }
    public function checkPostAuth(UserInterface $user, ?\Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token = null): void { $this->checkPreAuth($user); }
}
