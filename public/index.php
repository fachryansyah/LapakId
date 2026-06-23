<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
    $requestedFile = realpath(__DIR__ . $path);

    if (
        $requestedFile !== false
        && is_file($requestedFile)
        && str_starts_with($requestedFile, __DIR__ . DIRECTORY_SEPARATOR)
    ) {
        return false;
    }
}

use Phroute\Phroute\Dispatcher;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/bootstrap/app.php';

$router = require dirname(__DIR__) . '/routes/web.php';
$dispatcher = new Dispatcher($router->getData());

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?: '/';

try {
    $response = $dispatcher->dispatch($requestMethod, $path);
    echo $response;
} catch (Throwable $exception) {
    http_response_code(404);
    echo '<h1>404 Not Found</h1>';
    echo '<p>' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
}
