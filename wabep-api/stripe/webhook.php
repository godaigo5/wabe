<?php

declare(strict_types=1);

ini_set('display_errors', '0');
date_default_timezone_set('Asia/Tokyo');

require_once __DIR__ . '/stripe-php/init.php';
require_once __DIR__ . '/../config/config.php';
$config = require __DIR__ . '/../config/config.php';

$stripeSecretKey = $config['stripe']['secret_key'] ?? '';
$stripeWebhookSecret = $config['stripe']['webhook_secret'] ?? '';

if ($stripeSecretKey === '' || $stripeWebhookSecret === '') {
    http_response_code(500);
    exit('Stripe keys are not configured.');
}

/**
 * =========================
 * 設定
 * =========================
 */

const DB_HOST = 'mysql328.phy.lolipop.lan';
const DB_NAME = 'LAA1305650-wabepapi';
const DB_USER = 'LAA1305650';
const DB_PASS = '8JGZMUKfyNcp1a0Y';
const DB_CHARSET = 'utf8mb4';

const LICENSE_EMAIL_FROM = 'no-reply@d-create.online';
const LICENSE_EMAIL_NAME = 'WP AI Blog Engine';

\Stripe\Stripe::setApiKey($stripeSecretKey);

/**
 * Stripe Price ID -> プラン名 の対応
 * 必ずあなたの本物のPrice IDに置き換えてください
 */
function wabe_price_plan_map(): array
{
    return [
        'price_1TClRwQOghVIYdnPrzvrJ8Aa'  => 'advanced-monthly',
        'price_1TClRJQOghVIYdnP5RxwLydi'   => 'advanced-yearly',
        'price_1TClekQOghVIYdnPCCQU3PKq' => 'advanced-lifetime',
        'price_1TClSrQOghVIYdnPUInUClyt'       => 'pro-monthly',
        'price_1TClSOQOghVIYdnPbiJssYuG'        => 'pro-yearly',
        'price_1TCleAQOghVIYdnPAuPU26lp'      => 'pro-lifetime',
    ];
}

/**
 * =========================
 * 汎用
 * =========================
 */
function wabe_json_response(int $status, array $data): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function wabe_log(string $message): void
{
    $logFile = __DIR__ . '/webhook.log';
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
}

function wabe_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
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

function wabe_generate_license_key(string $plan): string
{
    $prefixMap = [
        'free'              => 'WABE-FREE',
        'advanced-monthly'  => 'WABE-ADV-M',
        'advanced-yearly'   => 'WABE-ADV-Y',
        'advanced-lifetime' => 'WABE-ADV-L',
        'pro-monthly'       => 'WABE-PRO-M',
        'pro-yearly'        => 'WABE-PRO-Y',
        'pro-lifetime'      => 'WABE-PRO-L',
    ];

    $prefix = $prefixMap[$plan] ?? 'WABE-LIC';
    return $prefix . '-' . substr(wabe_random_string(20), 0, 4) . '-' . substr(wabe_random_string(20), 0, 4) . '-' . substr(wabe_random_string(20), 0, 4);
}

function wabe_plan_type(string $plan): string
{
    if (str_contains($plan, 'monthly')) {
        return 'monthly';
    }
    if (str_contains($plan, 'yearly')) {
        return 'yearly';
    }
    if (str_contains($plan, 'lifetime')) {
        return 'lifetime';
    }
    return 'free';
}

function wabe_plan_tier(string $plan): string
{
    if (str_starts_with($plan, 'pro')) {
        return 'pro';
    }
    if (str_starts_with($plan, 'advanced')) {
        return 'advanced';
    }
    return 'free';
}

function wabe_calc_expires_at(string $plan): ?string
{
    $now = new DateTimeImmutable('now');

    if (str_contains($plan, 'monthly')) {
        return $now->modify('+1 month')->format('Y-m-d H:i:s');
    }

    if (str_contains($plan, 'yearly')) {
        return $now->modify('+1 year')->format('Y-m-d H:i:s');
    }

    if (str_contains($plan, 'lifetime')) {
        return null;
    }

    return null;
}

function wabe_get_price_id_from_session(string $sessionId): ?string
{
    try {
        $lineItems = \Stripe\Checkout\Session::allLineItems($sessionId, [
            'limit' => 10,
        ]);

        if (!empty($lineItems->data[0]->price->id)) {
            return (string) $lineItems->data[0]->price->id;
        }
    } catch (Throwable $e) {
        wabe_log('Line item retrieval failed: ' . $e->getMessage());
    }

    return null;
}

function wabe_find_plan_by_price_id(?string $priceId): ?string
{
    if (!$priceId) {
        return null;
    }

    $map = wabe_price_plan_map();
    return $map[$priceId] ?? null;
}

function wabe_order_exists(string $checkoutSessionId): bool
{
    $pdo = wabe_db();

    $stmt = $pdo->prepare('SELECT id FROM stripe_orders WHERE checkout_session_id = :checkout_session_id LIMIT 1');
    $stmt->execute([
        ':checkout_session_id' => $checkoutSessionId,
    ]);

    return (bool) $stmt->fetch();
}

function wabe_insert_order(array $order): int
{
    $pdo = wabe_db();

    $sql = "
        INSERT INTO stripe_orders (
            checkout_session_id,
            payment_intent_id,
            subscription_id,
            customer_id,
            customer_email,
            customer_name,
            payment_link_id,
            price_id,
            plan,
            amount_total,
            currency,
            payment_status,
            stripe_created_at,
            raw_payload,
            created_at,
            updated_at
        ) VALUES (
            :checkout_session_id,
            :payment_intent_id,
            :subscription_id,
            :customer_id,
            :customer_email,
            :customer_name,
            :payment_link_id,
            :price_id,
            :plan,
            :amount_total,
            :currency,
            :payment_status,
            :stripe_created_at,
            :raw_payload,
            NOW(),
            NOW()
        )
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':checkout_session_id' => $order['checkout_session_id'],
        ':payment_intent_id'   => $order['payment_intent_id'],
        ':subscription_id'     => $order['subscription_id'],
        ':customer_id'         => $order['customer_id'],
        ':customer_email'      => $order['customer_email'],
        ':customer_name'       => $order['customer_name'],
        ':payment_link_id'     => $order['payment_link_id'],
        ':price_id'            => $order['price_id'],
        ':plan'                => $order['plan'],
        ':amount_total'        => $order['amount_total'],
        ':currency'            => $order['currency'],
        ':payment_status'      => $order['payment_status'],
        ':stripe_created_at'   => $order['stripe_created_at'],
        ':raw_payload'         => $order['raw_payload'],
    ]);

    return (int) $pdo->lastInsertId();
}

function wabe_insert_license(array $license): int
{
    $pdo = wabe_db();

    $sql = "
        INSERT INTO licenses (
            order_id,
            license_key,
            plan,
            tier,
            billing_type,
            customer_email,
            customer_name,
            status,
            max_activations,
            current_activations,
            expires_at,
            created_at,
            updated_at
        ) VALUES (
            :order_id,
            :license_key,
            :plan,
            :tier,
            :billing_type,
            :customer_email,
            :customer_name,
            :status,
            :max_activations,
            :current_activations,
            :expires_at,
            NOW(),
            NOW()
        )
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':order_id'             => $license['order_id'],
        ':license_key'          => $license['license_key'],
        ':plan'                 => $license['plan'],
        ':tier'                 => $license['tier'],
        ':billing_type'         => $license['billing_type'],
        ':customer_email'       => $license['customer_email'],
        ':customer_name'        => $license['customer_name'],
        ':status'               => $license['status'],
        ':max_activations'      => $license['max_activations'],
        ':current_activations'  => $license['current_activations'],
        ':expires_at'           => $license['expires_at'],
    ]);

    return (int) $pdo->lastInsertId();
}

function wabe_send_license_email(string $toEmail, string $customerName, string $licenseKey, string $plan): bool
{
    if ($toEmail === '') {
        return false;
    }

    $subject = '【WP AI Blog Engine】ライセンスキーのお知らせ';

    $body = [];
    $body[] = $customerName !== '' ? $customerName . ' 様' : 'お客様';
    $body[] = '';
    $body[] = 'この度は WP AI Blog Engine をご購入いただきありがとうございます。';
    $body[] = '';
    $body[] = 'ご購入プラン: ' . $plan;
    $body[] = 'ライセンスキー: ' . $licenseKey;
    $body[] = '';
    $body[] = 'プラグイン管理画面にライセンスキーを入力してご利用ください。';
    $body[] = '';
    $body[] = '会員ページ: https://wabe.d-create.online/member/';
    $body[] = '';
    $body[] = '※このメールは自動送信です。';

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'From: ' . LICENSE_EMAIL_NAME . ' <' . LICENSE_EMAIL_FROM . '>';

    return mail($toEmail, mb_encode_mimeheader($subject, 'UTF-8'), implode("\n", $body), implode("\r\n", $headers));
}

/**
 * =========================
 * メイン処理
 * =========================
 */
$payload    = @file_get_contents('php://input');
$sigHeader  = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

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
    switch ($event->type) {
        case 'checkout.session.completed':
            /** @var \Stripe\Checkout\Session $session */
            $session = $event->data->object;

            $checkoutSessionId = (string) ($session->id ?? '');
            if ($checkoutSessionId === '') {
                wabe_json_response(400, [
                    'success' => false,
                    'message' => 'Session ID not found.',
                ]);
            }

            if (wabe_order_exists($checkoutSessionId)) {
                wabe_log('Already processed session: ' . $checkoutSessionId);
                wabe_json_response(200, [
                    'success' => true,
                    'message' => 'Already processed.',
                ]);
            }

            $paymentStatus = (string) ($session->payment_status ?? '');
            $status        = (string) ($session->status ?? '');

            if ($paymentStatus !== 'paid' || $status !== 'complete') {
                wabe_log('Session not paid/complete: ' . $checkoutSessionId . ' payment_status=' . $paymentStatus . ' status=' . $status);
                wabe_json_response(200, [
                    'success' => true,
                    'message' => 'Ignored because payment is not completed yet.',
                ]);
            }

            $priceId = wabe_get_price_id_from_session($checkoutSessionId);
            $plan    = wabe_find_plan_by_price_id($priceId);

            if (!$plan) {
                wabe_log('Unknown plan. session=' . $checkoutSessionId . ' price_id=' . (string)$priceId);
                wabe_json_response(400, [
                    'success' => false,
                    'message' => 'Unknown price_id. Please update price map.',
                    'price_id' => $priceId,
                ]);
            }

            $customerEmail = (string) (
                $session->customer_details->email
                ?? $session->customer_email
                ?? ''
            );

            $customerName = (string) (
                $session->customer_details->name
                ?? ''
            );

            $orderId = wabe_insert_order([
                'checkout_session_id' => $checkoutSessionId,
                'payment_intent_id'   => (string) ($session->payment_intent ?? ''),
                'subscription_id'     => (string) ($session->subscription ?? ''),
                'customer_id'         => (string) ($session->customer ?? ''),
                'customer_email'      => $customerEmail,
                'customer_name'       => $customerName,
                'payment_link_id'     => (string) ($session->payment_link ?? ''),
                'price_id'            => (string) ($priceId ?? ''),
                'plan'                => $plan,
                'amount_total'        => (int) ($session->amount_total ?? 0),
                'currency'            => strtoupper((string) ($session->currency ?? 'JPY')),
                'payment_status'      => $paymentStatus,
                'stripe_created_at'   => isset($session->created) ? date('Y-m-d H:i:s', (int)$session->created) : date('Y-m-d H:i:s'),
                'raw_payload'         => $payload,
            ]);

            $licenseKey = wabe_generate_license_key($plan);

            $licenseId = wabe_insert_license([
                'order_id'             => $orderId,
                'license_key'          => $licenseKey,
                'plan'                 => $plan,
                'tier'                 => wabe_plan_tier($plan),
                'billing_type'         => wabe_plan_type($plan),
                'customer_email'       => $customerEmail,
                'customer_name'        => $customerName,
                'status'               => 'active',
                'max_activations'      => 1,
                'current_activations'  => 0,
                'expires_at'           => wabe_calc_expires_at($plan),
            ]);

            wabe_send_license_email($customerEmail, $customerName, $licenseKey, $plan);

            wabe_log('Processed session=' . $checkoutSessionId . ' order_id=' . $orderId . ' license_id=' . $licenseId . ' plan=' . $plan);

            wabe_json_response(200, [
                'success'    => true,
                'message'    => 'Webhook processed successfully.',
                'order_id'   => $orderId,
                'license_id' => $licenseId,
                'plan'       => $plan,
            ]);
            break;

        default:
            wabe_log('Unhandled event type: ' . $event->type);
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
