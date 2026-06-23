<?php

declare(strict_types=1);

namespace Fahri\LapakId\Core;

use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

class Database
{
    private PDO $connection;

    public function __construct(?PDO $connection = null)
    {
        $this->connection = $connection ?? $this->createConnection();
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->connection->prepare($sql);

        if ($statement === false) {
            throw new RuntimeException('Failed to prepare database statement.');
        }

        foreach ($params as $key => $value) {
            $parameter = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');
            $statement->bindValue($parameter, $value, $this->resolveParamType($value));
        }

        $statement->execute();

        return $statement;
    }

    public function selectOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    public function selectAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function execute(string $sql, array $params = []): bool
    {
        return $this->query($sql, $params)->rowCount() >= 0;
    }

    public function lastInsertId(): string|false
    {
        return $this->connection->lastInsertId();
    }

    private function createConnection(): PDO
    {
        $host = Env::get('DB_HOST', '127.0.0.1');
        $port = Env::get('DB_PORT', '3306');
        $database = Env::get('DB_DATABASE', '');
        $username = Env::get('DB_USERNAME', 'root');
        $password = Env::get('DB_PASSWORD', '');
        $charset = Env::get('DB_CHARSET', 'utf8mb4');

        if ($database === '') {
            throw new RuntimeException('DB_DATABASE is not configured.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $host,
            $port,
            $database,
            $charset
        );

        try {
            return new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (Throwable $throwable) {
            throw new RuntimeException(
                'Database connection failed. Check your MySQL credentials in .env.',
                previous: $throwable
            );
        }
    }

    private function resolveParamType(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }
}
