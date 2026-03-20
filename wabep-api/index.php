<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/src/Database.php';
require __DIR__ . '/src/LicenseService.php';
require __DIR__ . '/src/Response.php';

$config = require __DIR__ . '/config/config.php';

$db = new \WABEP\Database($config['db']);
$pdo = $db->pdo();

$service = new \WABEP\LicenseService($pdo, $config['api']);
$response = new \WABEP\Response();

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';

$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($basePath !== '' && $basePath !== '/' && str_starts_with($path, $basePath)) {
    $path = substr($path, strlen($basePath));
}

$path = '/' . ltrim((string) $path, '/');
$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($path === '/') {
    $response->json([
        'ok' => true,
        'service' => 'WABEP License API',
        'message' => 'Use /health or /license/check',
    ]);
    exit;
}

if ($path === '/health') {
    $response->json([
        'ok' => true,
        'service' => 'WABEP License API',
        'time' => date('Y-m-d H:i:s'),
    ]);
    exit;
}

if ($method !== 'POST') {
    $response->json([
        'ok' => false,
        'message' => 'Method not allowed',
        'path' => $path,
        'uri' => $uri,
        'basePath' => $basePath,
    ], 405);
    exit;
}

$input = $_POST;

if (empty($input)) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

switch ($path) {
    case '/license/check':
        $result = $service->check($input);
        $response->json($result);
        break;

    case '/license/activate':
        $result = $service->activate($input);
        $response->json($result);
        break;

    case '/license/deactivate':
        $result = $service->deactivate($input);
        $response->json($result);
        break;

    default:
        $response->json([
            'ok' => false,
            'message' => 'Not found',
            'path' => $path,
            'uri' => $uri,
            'basePath' => $basePath,
        ], 404);
        break;
}