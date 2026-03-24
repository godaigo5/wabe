<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/config.php';

date_default_timezone_set((string)($config['app']['timezone'] ?? 'Asia/Tokyo'));

$debug = (bool)($config['app']['debug'] ?? false);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

require_once __DIR__ . '/stripe-php/init.php';

$stripeSecretKey     = (string)($config['stripe']['secret_key'] ?? '');
$stripeWebhookSecret = (string)($config['stripe']['webhook_secret'] ?? '');

$dbHost    = (string)($config['db']['host'] ?? '');
$dbPort    = (int)($config['db']['port'] ?? 3306);
$dbName    = (string)($config['db']['name'] ?? '');
$dbUser    = (string)($config['db']['user'] ?? '');
$dbPass    = (string)($config['db']['pass'] ?? '');
$dbCharset = (string)($config['db']['charset'] ?? 'utf8mb4');

$mailFromEmail = (string)($config['mail']['from_email'] ?? 'no-reply@d-create.online');
$mailFromName  = (string)($config['mail']['from_name'] ?? 'WP AI Blog Engine');

if ($stripeSecretKey === '' || $stripeWebhookSecret === '') {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Stripe config is empty.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

\Stripe\Stripe::setApiKey($stripeSecretKey);

/**
 * =========================
 * 共通
 * =========================
 */
function wabe_log(string $message): void
{
    $file = __DIR__ . '/webhook.log';
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($file, $line, FILE_APPEND);
}

function wabe_json_response(int $status, array $data): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function wabe_db(): PDO
{
    static $pdo = null;

    global $dbHost, $dbPort, $dbName, $dbUser, $dbPass, $dbCharset;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $dbHost,
        $dbPort,
        $dbName,
        $dbCharset
    );

    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function wabe_table_exists(string $table): bool
{
    $pdo = wabe_db();
    $stmt = $pdo->prepare('SHOW TABLES LIKE :table');
    $stmt->execute([
        ':table' => $table,
    ]);

    return (bool)$stmt->fetchColumn();
}

function wabe_get_table_columns(string $table): array
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $pdo = wabe_db();
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    $rows = $stmt->fetchAll();

    $columns = [];
    foreach ($rows as $row) {
        if (!empty($row['Field'])) {
            $columns[] = $row['Field'];
        }
    }

    $cache[$table] = $columns;
    return $columns;
}

function wabe_random_string(int $length = 24): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max   = strlen($chars) - 1;
    $out   = '';

    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, $max)];
    }

    return $out;
}

function wabe_generate_license_key(string $plan, string $billingCycle): string
{
    if ($plan === 'advanced') {
        $prefix = 'WABE-ADV';
    } elseif ($plan === 'pro') {
        $prefix = 'WABE-PRO';
    } else {
        $prefix = 'WABE-FREE';
    }

    if ($billingCycle === 'monthly') {
        $cycle = 'M';
    } elseif ($billingCycle === 'yearly') {
        $cycle = 'Y';
    } elseif ($billingCycle === 'lifetime') {
        $cycle = 'L';
    } else {
        $cycle = 'F';
    }

    return $prefix . '-' . $cycle . '-' .
        substr(wabe_random_string(12), 0, 4) . '-' .
        substr(wabe_random_string(12), 0, 4) . '-' .
        substr(wabe_random_string(12), 0, 4);
}

function wabe_calc_expires_at(?string $billingCycle): ?string
{
    $now = new DateTimeImmutable('now');

    if ($billingCycle === 'monthly') {
        return $now->modify('+1 month')->format('Y-m-d H:i:s');
    }

    if ($billingCycle === 'yearly') {
        return $now->modify('+1 year')->format('Y-m-d H:i:s');
    }

    if ($billingCycle === 'lifetime' || $billingCycle === 'free') {
        return null;
    }

    return null;
}

function wabe_price_map(array $config): array
{
    $prices = $config['stripe']['prices'] ?? [];
    $mapped = [];

    foreach ($prices as $priceId => $row) {
        $mapped[$priceId] = [
            'plan' => (string)($row['plan'] ?? ''),
            'billing_cycle' => (string)($row['billing'] ?? ''),
        ];
    }

    return $mapped;
}

function wabe_find_plan_data(?string $priceId, array $config): ?array
{
    if (!$priceId) {
        return null;
    }

    $map = wabe_price_map($config);
    return $map[$priceId] ?? null;
}

function wabe_get_price_id_from_session(string $sessionId): ?string
{
    try {
        $lineItems = \Stripe\Checkout\Session::allLineItems($sessionId, [
            'limit' => 10,
        ]);

        if (!empty($lineItems->data[0]->price->id)) {
            return (string)$lineItems->data[0]->price->id;
        }
    } catch (Throwable $e) {
        wabe_log('Line items fetch failed: ' . $e->getMessage());
    }

    return null;
}

/**
 * =========================
 * stripe_orders
 * =========================
 */
function wabe_order_exists_by_event(string $eventId): bool
{
    $pdo = wabe_db();

    $stmt = $pdo->prepare("
        SELECT id
        FROM stripe_orders
        WHERE stripe_event_id = :stripe_event_id
        LIMIT 1
    ");
    $stmt->execute([
        ':stripe_event_id' => $eventId,
    ]);

    return (bool)$stmt->fetch();
}

function wabe_order_exists_by_session(string $checkoutSessionId): bool
{
    $pdo = wabe_db();

    $stmt = $pdo->prepare("
        SELECT id
        FROM stripe_orders
        WHERE stripe_checkout_session_id = :stripe_checkout_session_id
        LIMIT 1
    ");
    $stmt->execute([
        ':stripe_checkout_session_id' => $checkoutSessionId,
    ]);

    return (bool)$stmt->fetch();
}

function wabe_insert_order(array $data): int
{
    $pdo = wabe_db();

    $sql = "
        INSERT INTO stripe_orders (
            stripe_event_id,
            stripe_checkout_session_id,
            stripe_payment_intent_id,
            stripe_subscription_id,
            stripe_customer_id,
            customer_email,
            plan,
            billing_cycle,
            price_id,
            amount_total,
            currency,
            payment_status,
            license_id,
            license_key,
            status,
            created_at,
            updated_at
        ) VALUES (
            :stripe_event_id,
            :stripe_checkout_session_id,
            :stripe_payment_intent_id,
            :stripe_subscription_id,
            :stripe_customer_id,
            :customer_email,
            :plan,
            :billing_cycle,
            :price_id,
            :amount_total,
            :currency,
            :payment_status,
            :license_id,
            :license_key,
            :status,
            NOW(),
            NOW()
        )
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':stripe_event_id'            => $data['stripe_event_id'],
        ':stripe_checkout_session_id' => $data['stripe_checkout_session_id'],
        ':stripe_payment_intent_id'   => $data['stripe_payment_intent_id'],
        ':stripe_subscription_id'     => $data['stripe_subscription_id'],
        ':stripe_customer_id'         => $data['stripe_customer_id'],
        ':customer_email'             => $data['customer_email'],
        ':plan'                       => $data['plan'],
        ':billing_cycle'              => $data['billing_cycle'],
        ':price_id'                   => $data['price_id'],
        ':amount_total'               => $data['amount_total'],
        ':currency'                   => $data['currency'],
        ':payment_status'             => $data['payment_status'],
        ':license_id'                 => $data['license_id'],
        ':license_key'                => $data['license_key'],
        ':status'                     => $data['status'],
    ]);

    return (int)$pdo->lastInsertId();
}

function wabe_update_order_license(int $orderId, ?int $licenseId, ?string $licenseKey): void
{
    $pdo = wabe_db();

    $stmt = $pdo->prepare("
        UPDATE stripe_orders
        SET license_id = :license_id,
            license_key = :license_key,
            updated_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([
        ':license_id'  => $licenseId,
        ':license_key' => $licenseKey,
        ':id'          => $orderId,
    ]);
}

/**
 * =========================
 * licenses
 * =========================
 */
function wabe_insert_license_dynamic(array $payload): ?int
{
    if (!wabe_table_exists('licenses')) {
        wabe_log('licenses table not found. skip insert.');
        return null;
    }

    $columns = wabe_get_table_columns('licenses');
    if (empty($columns)) {
        wabe_log('licenses columns not found. skip insert.');
        return null;
    }

    $map = [
        'license_key'         => $payload['license_key'] ?? null,
        'plan'                => $payload['plan'] ?? null,
        'status'              => $payload['status'] ?? 'active',
        'customer_email'      => $payload['customer_email'] ?? null,
        'customer_name'       => $payload['customer_name'] ?? null,
        'expires_at'          => $payload['expires_at'] ?? null,
        'billing_cycle'       => $payload['billing_cycle'] ?? null,
        'tier'                => $payload['plan'] ?? null,
        'domain_limit'        => 1,
        'site_limit'          => 1,
        'max_activations'     => 1,
        'current_activations' => 0,
        'order_id'            => $payload['order_id'] ?? null,
        'stripe_order_id'     => $payload['order_id'] ?? null,
        'stripe_customer_id'  => $payload['stripe_customer_id'] ?? null,
        'created_at'          => date('Y-m-d H:i:s'),
        'updated_at'          => date('Y-m-d H:i:s'),
    ];

    $insert = [];
    foreach ($map as $column => $value) {
        if (in_array($column, $columns, true)) {
            $insert[$column] = $value;
        }
    }

    if (empty($insert)) {
        wabe_log('No matching columns for licenses insert.');
        return null;
    }

    $colSql = [];
    $valSql = [];
    $binds  = [];

    foreach ($insert as $column => $value) {
        $colSql[] = "`{$column}`";
        $valSql[] = ':' . $column;
        $binds[':' . $column] = $value;
    }

    $sql = sprintf(
        'INSERT INTO `licenses` (%s) VALUES (%s)',
        implode(', ', $colSql),
        implode(', ', $valSql)
    );

    $pdo = wabe_db();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($binds);

    return (int)$pdo->lastInsertId();
}

/**
 * =========================
 * mail
 * =========================
 */
function wabe_send_license_email(
    string $toEmail,
    string $licenseKey,
    string $plan,
    string $billingCycle
): bool {
    global $mailFromEmail, $mailFromName;

    if ($toEmail === '') {
        return false;
    }

    $subject = '【WP AI Blog Engine】ライセンスキーのお知らせ';

    $body = [];
    $body[] = 'WP AI Blog Engine をご購入いただきありがとうございます。';
    $body[] = '';
    $body[] = 'プラン: ' . $plan;
    $body[] = '契約種別: ' . $billingCycle;
    $body[] = 'ライセンスキー: ' . $licenseKey;
    $body[] = '';
    $body[] = '会員ページ: https://wabe.d-create.online/member/';
    $body[] = 'プラグイン管理画面でもライセンス登録が可能です。';
    $body[] = '';
    $body[] = '※このメールは自動送信です。';

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'From: ' . $mailFromName . ' <' . $mailFromEmail . '>';

    return mail(
        $toEmail,
        mb_encode_mimeheader($subject, 'UTF-8'),
        implode("\n", $body),
        implode("\r\n", $headers)
    );
}

/**
 * =========================
 * main
 * =========================
 */
$payload   = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if ($payload === false || $payload === '') {
    wabe_json_response(400, [
        'success' => false,
        'message' => 'Empty payload.',
    ]);
}

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sigHeader,
        $stripeWebhookSecret
    );
} catch (\UnexpectedValueException $e) {
    wabe_log('Invalid payload: ' . $e->getMessage());
    wabe_json_response(400, [
        'success' => false,
        'message' => 'Invalid payload.',
    ]);
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    wabe_log('Invalid signature: ' . $e->getMessage());
    wabe_json_response(400, [
        'success' => false,
        'message' => 'Invalid signature.',
    ]);
} catch (Throwable $e) {
    wabe_log('Webhook verify error: ' . $e->getMessage());
    wabe_json_response(400, [
        'success' => false,
        'message' => 'Webhook verification failed.',
    ]);
}

try {
    wabe_log('Event received: ' . $event->type . ' / ' . $event->id);

    switch ($event->type) {
        case 'checkout.session.completed':
            /** @var \Stripe\Checkout\Session $session */
            $session = $event->data->object;

            $eventId           = (string)($event->id ?? '');
            $checkoutSessionId = (string)($session->id ?? '');
            $paymentStatus     = (string)($session->payment_status ?? '');
            $sessionStatus     = (string)($session->status ?? '');

            if ($eventId === '' || $checkoutSessionId === '') {
                wabe_log('Missing event or session ID.');
                wabe_json_response(400, [
                    'success' => false,
                    'message' => 'Missing event/session ID.',
                ]);
            }

            if (wabe_order_exists_by_event($eventId) || wabe_order_exists_by_session($checkoutSessionId)) {
                wabe_log('Already processed. event=' . $eventId . ' session=' . $checkoutSessionId);
                wabe_json_response(200, [
                    'success' => true,
                    'message' => 'Already processed.',
                ]);
            }

            if ($paymentStatus !== 'paid' || $sessionStatus !== 'complete') {
                wabe_log('Ignored unpaid/incomplete session. payment_status=' . $paymentStatus . ' status=' . $sessionStatus);
                wabe_json_response(200, [
                    'success' => true,
                    'message' => 'Ignored because payment is not completed.',
                ]);
            }

            $priceId  = wabe_get_price_id_from_session($checkoutSessionId);
            $planData = wabe_find_plan_data($priceId, $config);

            if (!$planData) {
                wabe_log('Unknown price_id: ' . (string)$priceId);
                wabe_json_response(400, [
                    'success' => false,
                    'message' => 'Unknown price_id. Update config.local.php stripe.prices.',
                    'price_id' => $priceId,
                ]);
            }

            $plan         = (string)$planData['plan'];
            $billingCycle = (string)$planData['billing_cycle'];

            $customerEmail = (string)(
                $session->customer_details->email
                ?? $session->customer_email
                ?? ''
            );

            $customerName = (string)(
                $session->customer_details->name
                ?? ''
            );

            $licenseKey = wabe_generate_license_key($plan, $billingCycle);

            $orderId = wabe_insert_order([
                'stripe_event_id'            => $eventId,
                'stripe_checkout_session_id' => $checkoutSessionId,
                'stripe_payment_intent_id'   => (string)($session->payment_intent ?? ''),
                'stripe_subscription_id'     => (string)($session->subscription ?? ''),
                'stripe_customer_id'         => (string)($session->customer ?? ''),
                'customer_email'             => $customerEmail,
                'plan'                       => $plan,
                'billing_cycle'              => $billingCycle,
                'price_id'                   => (string)$priceId,
                'amount_total'               => (int)($session->amount_total ?? 0),
                'currency'                   => strtoupper((string)($session->currency ?? 'USD')),
                'payment_status'             => $paymentStatus,
                'license_id'                 => null,
                'license_key'                => $licenseKey,
                'status'                     => 'paid',
            ]);

            $licenseId = wabe_insert_license_dynamic([
                'order_id'           => $orderId,
                'license_key'        => $licenseKey,
                'plan'               => $plan,
                'billing_cycle'      => $billingCycle,
                'status'             => 'active',
                'customer_email'     => $customerEmail,
                'customer_name'      => $customerName,
                'expires_at'         => wabe_calc_expires_at($billingCycle),
                'stripe_customer_id' => (string)($session->customer ?? ''),
            ]);

            wabe_update_order_license($orderId, $licenseId, $licenseKey);

            $mailSent = wabe_send_license_email(
                $customerEmail,
                $licenseKey,
                $plan,
                $billingCycle
            );

            wabe_log(
                'Processed session=' . $checkoutSessionId .
                    ' order_id=' . $orderId .
                    ' license_id=' . (string)$licenseId .
                    ' plan=' . $plan .
                    ' billing_cycle=' . $billingCycle .
                    ' price_id=' . (string)$priceId .
                    ' mail=' . ($mailSent ? 'sent' : 'failed')
            );

            wabe_json_response(200, [
                'success'       => true,
                'message'       => 'Webhook processed successfully.',
                'order_id'      => $orderId,
                'license_id'    => $licenseId,
                'license_key'   => $licenseKey,
                'plan'          => $plan,
                'billing_cycle' => $billingCycle,
            ]);
            break;

        default:
            wabe_log('Ignored event type: ' . $event->type);
            wabe_json_response(200, [
                'success' => true,
                'message' => 'Event ignored.',
                'type'    => $event->type,
            ]);
    }
} catch (Throwable $e) {
    wabe_log('Processing error: ' . $e->getMessage());
    wabe_json_response(500, [
        'success' => false,
        'message' => 'Webhook processing error.',
        'error'   => $e->getMessage(),
    ]);
}
