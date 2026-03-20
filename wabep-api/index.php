<?php

declare(strict_types=1);

use WABEP\Database;
use WABEP\LicenseService;
use WABEP\Response;
use WABEP\StripeWebhookService;

require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Response.php';
require_once __DIR__ . '/src/LicenseService.php';
require_once __DIR__ . '/src/StripeWebhookService.php';

$config = require __DIR__ . '/config/config.php';

$pdo = Database::pdo();

$licenseService = new LicenseService(
    $pdo,
    is_array($config['api'] ?? null) ? $config['api'] : []
);

$stripeWebhookService = new StripeWebhookService(
    $pdo,
    is_array($config['stripe'] ?? null) ? $config['stripe'] : [],
    is_array($config['api'] ?? null) ? $config['api'] : []
);

$response = new Response();

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

if ($basePath !== '' && $basePath !== '/' && str_starts_with($path, $basePath)) {
    $path = substr($path, strlen($basePath));
}

$path = '/' . ltrim((string)$path, '/');
$path = rtrim($path, '/');

if ($path === '') {
    $path = '/';
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($path === '/') {
    $response->json([
        'ok'       => true,
        'service'  => 'WABEP License API',
        'version'  => '1.0.0',
        'endpoints' => [
            'GET /health',
            'POST /license/check',
            'POST /license/activate',
            'POST /license/deactivate',
            'POST /stripe/webhook',
        ],
    ]);
    exit;
}

if ($path === '/health') {
    $response->json([
        'ok'        => true,
        'service'   => 'WABEP License API',
        'time'      => gmdate('Y-m-d H:i:s'),
        'timestamp' => time(),
    ]);
    exit;
}

if ($path === '/stripe/webhook') {
    if ($method !== 'POST') {
        $response->json([
            'ok'      => false,
            'message' => 'Method not allowed',
        ], 405);
        exit;
    }

    $rawBody = file_get_contents('php://input');
    if (!is_string($rawBody)) {
        $rawBody = '';
    }

    $signature = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

    $result = $stripeWebhookService->handle($rawBody, $signature);
    $statusCode = !empty($result['ok']) ? 200 : 400;

    $response->json($result, $statusCode);
    exit;
}

if ($method !== 'POST') {
    $response->json([
        'ok'       => false,
        'message'  => 'Method not allowed',
        'path'     => $path,
        'uri'      => $uri,
        'basePath' => $basePath,
    ], 405);
    exit;
}

$input = $_POST;

if (empty($input)) {
    $raw = file_get_contents('php://input');
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }
}

if (!is_array($input)) {
    $input = [];
}

switch ($path) {
    case '/license/check':
        $result = $licenseService->check($input);
        $response->json($result);
        break;

    case '/license/activate':
        $result = $licenseService->activate($input);
        $response->json($result);
        break;

    case '/license/deactivate':
        $result = $licenseService->deactivate($input);
        $response->json($result);
        break;

    default:
        $response->json([
            'ok'       => false,
            'message'  => 'Not found',
            'path'     => $path,
            'uri'      => $uri,
            'basePath' => $basePath,
        ], 404);
        break;
}
