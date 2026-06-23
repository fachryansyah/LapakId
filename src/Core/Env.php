<?php

declare(strict_types=1);

namespace Fahri\LapakId\Core;

class Env
{
    public static function load(string $filePath): void
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if ($trimmedLine === '' || str_starts_with($trimmedLine, '#')) {
                continue;
            }

            [$name, $value] = array_pad(explode('=', $trimmedLine, 2), 2, '');
            $name = trim($name);

            if ($name === '') {
                continue;
            }

            $value = self::normalizeValue($value);

            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv(sprintf('%s=%s', $name, $value));
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    private static function normalizeValue(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, '\'') && str_ends_with($value, '\''))
        ) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
