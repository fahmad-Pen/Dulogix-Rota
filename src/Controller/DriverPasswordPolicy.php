<?php
namespace App\Controller;

final class DriverPasswordPolicy
{
    public static function valid(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[^a-zA-Z0-9]/', $password) === 1;
    }
    public const MESSAGE = 'Driver/Jumper passwords must be at least 8 characters and include a number and a special character.';
}
