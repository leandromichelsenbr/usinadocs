<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Authentication;

final class Roles
{
    public static function canEdit(?array $user): bool
    {
        return in_array($user['role'] ?? null, ['editor', 'administrator'], true);
    }

    public static function canManageUsers(?array $user): bool
    {
        return ($user['role'] ?? null) === 'administrator';
    }
}
