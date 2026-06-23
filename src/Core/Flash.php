<?php

declare(strict_types=1);

namespace Fahri\LapakId\Core;

final class Flash
{
    public static function set(string $type, string $message): void
    {
        $_SESSION['flash'][$type][] = $message;
    }

    public static function pullAll(): array
    {
        $messages = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);

        return is_array($messages) ? $messages : [];
    }
}
