<?php
declare(strict_types=1);

// Autoload (composer) and env
require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Always return JSON
header('Content-Type: application/json');

// Normalize path (strip trailing slash except for root)
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path   = rtrim($path, '/');
$path   = ($path === '') ? '/' : $path;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Route table: method → path → handler
$routes = [
    'GET' => [
        '/' => function () {
            echo json_encode(['ok' => true, 'service' => 'wrapfield-api']);
        },
    ],
    'POST' => [
        '/createBoard'       => __DIR__ . '/../src/CreateBoard.php',
        '/joinBoard'         => __DIR__ . '/../src/JoinBoard.php',
        '/refreshConnection' => __DIR__ . '/../src/RefreshConnection.php',
    ],
];

try {
    $handler = $routes[$method][$path] ?? null;

    if (is_string($handler)) {
        // Endpoint implemented as a script
        require $handler;
        exit;
    } elseif (is_callable($handler)) {
        // Endpoint implemented inline/closure
        $handler();
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Not found', 'path' => $path, 'method' => $method]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'detail' => $e->getMessage()]);
}
