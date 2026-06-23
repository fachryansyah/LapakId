<?php

declare(strict_types=1);

namespace Fahri\LapakId\Core;

final class Auth
{
    public static function user(): ?array
    {
        $user = $_SESSION['auth_user'] ?? null;

        return is_array($user) ? $user : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        return self::check() && (self::user()['role'] ?? null) === 'admin';
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        unset($user['password']);
        $_SESSION['auth_user'] = $user;
    }

    public static function logout(): void
    {
        unset($_SESSION['auth_user']);
        session_regenerate_id(true);
    }

    public static function attempt(Database $database, string $email, string $password): bool
    {
        $user = $database->selectOne(
            'SELECT id, fullname, email, password, role, created_at '
            . 'FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1',
            ['email' => strtolower(trim($email))]
        );

        if ($user === null) {
            return false;
        }

        if (!password_verify($password, (string) $user['password'])) {
            return false;
        }

        self::login($user);

        return true;
    }

    public static function attemptAdmin(Database $database, string $email, string $password): bool
    {
        $user = $database->selectOne(
            'SELECT id, fullname, email, password, role, created_at '
            . 'FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1',
            ['email' => strtolower(trim($email))]
        );

        if ($user === null || ($user['role'] ?? '') !== 'admin') {
            return false;
        }

        if (!password_verify($password, (string) $user['password'])) {
            return false;
        }

        self::login($user);

        return true;
    }
}
