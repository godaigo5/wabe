<?php

declare(strict_types=1);

namespace WABEP;

use PDO;
use Throwable;
use DateTimeImmutable;
use DateTimeZone;

class StripeWebhookService
{
    private PDO $pdo;
    private array $config;
    private array $apiConfig;

    public function __construct(PDO $pdo, array $config, array $apiConfig)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->apiConfig = $apiConfig;
    }

    public function handle(string $rawBody, string $stripeSignature): array
    {
        if ($rawBody === '') {
            return [
                'ok' => false,
                'message' => 'Empty request body.',
            ];
        }

        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            return [
                'ok' => false,
                'message' => 'Invalid JSON payload.',
            ];
        }

        if (!$this->verifySignature($rawBody, $stripeSignature)) {
            return [
                'ok' => false,
                'message' => 'Invalid Stripe signature.',
            ];
        }

        $type = (string)($event['type'] ?? '');
        $data = $event['data']['object'] ?? [];

        try {
            switch ($type) {
                case 'checkout.session.completed':
                    return $this->handleCheckoutSessionCompleted($data, $event);

                case 'invoice.payment_succeeded':
                    return $this->handleInvoicePaymentSucceeded($data, $event);

                case 'customer.subscription.deleted':
                    return $this->handleSubscriptionDeleted($data, $event);

                case 'charge.refunded':
                    return $this->handleChargeRefunded($data, $event);

                default:
                    return [
                        'ok' => true,
                        'message' => 'Event ignored.',
                        'event_type' => $type,
                    ];
            }
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Webhook exception: ' . $e->getMessage(),
                'event_type' => $type,
            ];
        }
    }

    private function handleCheckoutSessionCompleted(array $session, array $event): array
    {
        $eventId = (string)($event['id'] ?? '');
        if ($eventId !== '' && $this->eventAlreadyProcessed($eventId)) {
            return [
                'ok' => true,
                'message' => 'Event already processed.',
                'event_type' => 'checkout.session.completed',
            ];
        }

        $priceId = $this->extractPriceIdFromSession($session);
        $mapping = $this->priceMapping($priceId);

        if (empty($mapping['plan'])) {
            return [
                'ok' => false,
                'message' => 'Price ID mapping not found.',
                'price_id' => $priceId,
            ];
        }

        $plan = $this->normalizePlan((string)$mapping['plan']);
        $billing = strtolower(trim((string)($mapping['billing'] ?? 'monthly')));

        $customerEmail = $this->extractCustomerEmail($session);
        if ($customerEmail === '') {
            return [
                'ok' => false,
                'message' => 'Customer email not found.',
            ];
        }

        $existing = $this->findOrderByCheckoutSessionId((string)($session['id'] ?? ''));
        if ($existing) {
            return [
                'ok' => true,
                'message' => 'Checkout session already handled.',
                'license_key' => (string)($existing['license_key'] ?? ''),
            ];
        }

        $licenseKey = $this->generateUniqueLicenseKey($plan);
        $expiresAt  = $this->calculateExpiresAtFromBilling($billing);
        $domainLimit = (int)($this->apiConfig['domain_limit_default'] ?? 1);

        $stmt = $this->pdo->prepare(
            'INSERT INTO licenses
            (license_key, plan, status, domain_limit, customer_email, expires_at, created_at, updated_at)
            VALUES
            (:license_key, :plan, :status, :domain_limit, :customer_email, :expires_at, NOW(), NOW())'
        );

        $stmt->execute([
            ':license_key'    => $licenseKey,
            ':plan'           => $plan,
            ':status'         => 'active',
            ':domain_limit'   => $domainLimit,
            ':customer_email' => $customerEmail,
            ':expires_at'     => $expiresAt,
        ]);

        $licenseId = (int)$this->pdo->lastInsertId();

        $this->upsertOrder([
            'stripe_event_id'            => $eventId,
            'stripe_checkout_session_id' => (string)($session['id'] ?? ''),
            'stripe_payment_intent_id'   => (string)($session['payment_intent'] ?? ''),
            'stripe_subscription_id'     => (string)($session['subscription'] ?? ''),
            'stripe_customer_id'         => (string)($session['customer'] ?? ''),
            'customer_email'             => $customerEmail,
            'plan'                       => $plan,
            'billing_cycle'              => $billing,
            'price_id'                   => $priceId,
            'amount_total'               => (int)($session['amount_total'] ?? 0),
            'currency'                   => strtolower((string)($session['currency'] ?? '')),
            'payment_status'             => (string)($session['payment_status'] ?? ''),
            'license_id'                 => $licenseId,
            'license_key'                => $licenseKey,
            'status'                     => 'paid',
        ]);

        return [
            'ok' => true,
            'message' => 'License issued successfully.',
            'license_key' => $licenseKey,
            'plan' => $plan,
            'billing' => $billing,
            'customer_email' => $customerEmail,
            'expires_at' => $expiresAt,
        ];
    }

    private function handleInvoicePaymentSucceeded(array $invoice, array $event): array
    {
        $eventId = (string)($event['id'] ?? '');
        if ($eventId !== '' && $this->eventAlreadyProcessed($eventId)) {
            return [
                'ok' => true,
                'message' => 'Event already processed.',
                'event_type' => 'invoice.payment_succeeded',
            ];
        }

        $subscriptionId = (string)($invoice['subscription'] ?? '');
        if ($subscriptionId === '') {
            return [
                'ok' => true,
                'message' => 'No subscription id. Ignored.',
            ];
        }

        $priceId = $this->extractPriceIdFromInvoice($invoice);
        $mapping = $this->priceMapping($priceId);
        $billing = strtolower(trim((string)($mapping['billing'] ?? 'monthly')));

        $expiresAt = $this->extractInvoicePeriodEnd($invoice);
        if ($expiresAt === null) {
            $expiresAt = $this->calculateExpiresAtFromBilling($billing);
        }

        $order = $this->findOrderBySubscriptionId($subscriptionId);
        if (!$order) {
            return [
                'ok' => false,
                'message' => 'Order not found for subscription.',
                'stripe_subscription_id' => $subscriptionId,
            ];
        }

        if (!empty($order['license_id'])) {
            $stmt = $this->pdo->prepare(
                'UPDATE licenses
                 SET status = :status,
                     expires_at = :expires_at,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                ':status'     => 'active',
                ':expires_at' => $expiresAt,
                ':id'         => (int)$order['license_id'],
            ]);
        }

        $this->upsertOrder([
            'stripe_event_id'            => $eventId,
            'stripe_checkout_session_id' => (string)($order['stripe_checkout_session_id'] ?? ''),
            'stripe_payment_intent_id'   => (string)($invoice['payment_intent'] ?? ''),
            'stripe_subscription_id'     => $subscriptionId,
            'stripe_customer_id'         => (string)($invoice['customer'] ?? ''),
            'customer_email'             => (string)($order['customer_email'] ?? ''),
            'plan'                       => (string)($order['plan'] ?? 'free'),
            'billing_cycle'              => $billing,
            'price_id'                   => $priceId,
            'amount_total'               => (int)($invoice['amount_paid'] ?? 0),
            'currency'                   => strtolower((string)($invoice['currency'] ?? '')),
            'payment_status'             => 'paid',
            'license_id'                 => (int)($order['license_id'] ?? 0),
            'license_key'                => (string)($order['license_key'] ?? ''),
            'status'                     => 'paid',
        ]);

        return [
            'ok' => true,
            'message' => 'Subscription renewal applied.',
            'stripe_subscription_id' => $subscriptionId,
            'expires_at' => $expiresAt,
        ];
    }

    private function handleSubscriptionDeleted(array $subscription, array $event): array
    {
        $eventId = (string)($event['id'] ?? '');
        if ($eventId !== '' && $this->eventAlreadyProcessed($eventId)) {
            return [
                'ok' => true,
                'message' => 'Event already processed.',
                'event_type' => 'customer.subscription.deleted',
            ];
        }

        $subscriptionId = (string)($subscription['id'] ?? '');
        if ($subscriptionId === '') {
            return [
                'ok' => true,
                'message' => 'No subscription id. Ignored.',
            ];
        }

        $order = $this->findOrderBySubscriptionId($subscriptionId);
        if (!$order) {
            return [
                'ok' => true,
                'message' => 'Order not found. Ignored.',
                'stripe_subscription_id' => $subscriptionId,
            ];
        }

        if (!empty($order['license_id'])) {
            $license = $this->findLicenseById((int)$order['license_id']);

            if ($license) {
                $expiresAt = !empty($license['expires_at']) ? (string)$license['expires_at'] : null;

                if ($expiresAt !== null && $expiresAt !== '' && strtotime($expiresAt) >= time()) {
                    $stmt = $this->pdo->prepare(
                        'UPDATE licenses
                         SET status = :status, updated_at = NOW()
                         WHERE id = :id'
                    );
                    $stmt->execute([
                        ':status' => 'canceled',
                        ':id'     => (int)$order['license_id'],
                    ]);
                } else {
                    $stmt = $this->pdo->prepare(
                        'UPDATE licenses
                         SET status = :status, updated_at = NOW()
                         WHERE id = :id'
                    );
                    $stmt->execute([
                        ':status' => 'inactive',
                        ':id'     => (int)$order['license_id'],
                    ]);
                }
            }
        }

        $this->upsertOrder([
            'stripe_event_id'            => $eventId,
            'stripe_checkout_session_id' => (string)($order['stripe_checkout_session_id'] ?? ''),
            'stripe_payment_intent_id'   => '',
            'stripe_subscription_id'     => $subscriptionId,
            'stripe_customer_id'         => (string)($subscription['customer'] ?? ''),
            'customer_email'             => (string)($order['customer_email'] ?? ''),
            'plan'                       => (string)($order['plan'] ?? 'free'),
            'billing_cycle'              => (string)($order['billing_cycle'] ?? ''),
            'price_id'                   => (string)($order['price_id'] ?? ''),
            'amount_total'               => 0,
            'currency'                   => '',
            'payment_status'             => 'canceled',
            'license_id'                 => (int)($order['license_id'] ?? 0),
            'license_key'                => (string)($order['license_key'] ?? ''),
            'status'                     => 'canceled',
        ]);

        return [
            'ok' => true,
            'message' => 'Subscription canceled. License remains usable until current expiry if not expired.',
            'stripe_subscription_id' => $subscriptionId,
        ];
    }

    private function handleChargeRefunded(array $charge, array $event): array
    {
        $eventId = (string)($event['id'] ?? '');
        if ($eventId !== '' && $this->eventAlreadyProcessed($eventId)) {
            return [
                'ok' => true,
                'message' => 'Event already processed.',
                'event_type' => 'charge.refunded',
            ];
        }

        $paymentIntentId = (string)($charge['payment_intent'] ?? '');
        if ($paymentIntentId === '') {
            return [
                'ok' => true,
                'message' => 'No payment intent id. Ignored.',
            ];
        }

        $order = $this->findOrderByPaymentIntentId($paymentIntentId);
        if (!$order) {
            return [
                'ok' => true,
                'message' => 'Order not found. Ignored.',
                'stripe_payment_intent_id' => $paymentIntentId,
            ];
        }

        if (!empty($order['license_id'])) {
            $stmt = $this->pdo->prepare(
                'UPDATE licenses
                 SET status = :status, updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                ':status' => 'inactive',
                ':id'     => (int)$order['license_id'],
            ]);
        }

        $this->upsertOrder([
            'stripe_event_id'            => $eventId,
            'stripe_checkout_session_id' => (string)($order['stripe_checkout_session_id'] ?? ''),
            'stripe_payment_intent_id'   => $paymentIntentId,
            'stripe_subscription_id'     => (string)($order['stripe_subscription_id'] ?? ''),
            'stripe_customer_id'         => (string)($charge['customer'] ?? ''),
            'customer_email'             => (string)($order['customer_email'] ?? ''),
            'plan'                       => (string)($order['plan'] ?? 'free'),
            'billing_cycle'              => (string)($order['billing_cycle'] ?? ''),
            'price_id'                   => (string)($order['price_id'] ?? ''),
            'amount_total'               => -1 * (int)($charge['amount_refunded'] ?? 0),
            'currency'                   => strtolower((string)($charge['currency'] ?? '')),
            'payment_status'             => 'refunded',
            'license_id'                 => (int)($order['license_id'] ?? 0),
            'license_key'                => (string)($order['license_key'] ?? ''),
            'status'                     => 'refunded',
        ]);

        return [
            'ok' => true,
            'message' => 'Refund processed and license deactivated.',
            'stripe_payment_intent_id' => $paymentIntentId,
        ];
    }

    private function verifySignature(string $payload, string $signatureHeader): bool
    {
        $secret = (string)($this->config['webhook_secret'] ?? '');
        if ($secret === '' || $signatureHeader === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $segment) {
            $pair = explode('=', trim($segment), 2);
            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }

        $timestamp = (string)($parts['t'] ?? '');
        $signature = (string)($parts['v1'] ?? '');

        if ($timestamp === '' || $signature === '') {
            return false;
        }

        if (!ctype_digit($timestamp)) {
            return false;
        }

        if (abs(time() - (int)$timestamp) > 300) {
            return false;
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expected, $signature);
    }

    private function upsertOrder(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO stripe_orders
            (stripe_event_id, stripe_checkout_session_id, stripe_payment_intent_id, stripe_subscription_id,
             stripe_customer_id, customer_email, plan, billing_cycle, price_id, amount_total, currency,
             payment_status, license_id, license_key, status, created_at, updated_at)
            VALUES
            (:stripe_event_id, :stripe_checkout_session_id, :stripe_payment_intent_id, :stripe_subscription_id,
             :stripe_customer_id, :customer_email, :plan, :billing_cycle, :price_id, :amount_total, :currency,
             :payment_status, :license_id, :license_key, :status, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
             stripe_payment_intent_id = VALUES(stripe_payment_intent_id),
             stripe_subscription_id = VALUES(stripe_subscription_id),
             stripe_customer_id = VALUES(stripe_customer_id),
             customer_email = VALUES(customer_email),
             plan = VALUES(plan),
             billing_cycle = VALUES(billing_cycle),
             price_id = VALUES(price_id),
             amount_total = VALUES(amount_total),
             currency = VALUES(currency),
             payment_status = VALUES(payment_status),
             license_id = VALUES(license_id),
             license_key = VALUES(license_key),
             status = VALUES(status),
             updated_at = NOW()'
        );

        $licenseId = (int)($data['license_id'] ?? 0);

        $stmt->execute([
            ':stripe_event_id'            => (string)($data['stripe_event_id'] ?? ''),
            ':stripe_checkout_session_id' => (string)($data['stripe_checkout_session_id'] ?? ''),
            ':stripe_payment_intent_id'   => (string)($data['stripe_payment_intent_id'] ?? ''),
            ':stripe_subscription_id'     => (string)($data['stripe_subscription_id'] ?? ''),
            ':stripe_customer_id'         => (string)($data['stripe_customer_id'] ?? ''),
            ':customer_email'             => (string)($data['customer_email'] ?? ''),
            ':plan'                       => $this->normalizePlan((string)($data['plan'] ?? 'free')),
            ':billing_cycle'              => (string)($data['billing_cycle'] ?? ''),
            ':price_id'                   => (string)($data['price_id'] ?? ''),
            ':amount_total'               => (int)($data['amount_total'] ?? 0),
            ':currency'                   => strtolower((string)($data['currency'] ?? '')),
            ':payment_status'             => (string)($data['payment_status'] ?? ''),
            ':license_id'                 => $licenseId > 0 ? $licenseId : null,
            ':license_key'                => (string)($data['license_key'] ?? ''),
            ':status'                     => (string)($data['status'] ?? ''),
        ]);
    }

    private function eventAlreadyProcessed(string $eventId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM stripe_orders WHERE stripe_event_id = :stripe_event_id LIMIT 1'
        );
        $stmt->execute([':stripe_event_id' => $eventId]);

        return (bool)$stmt->fetchColumn();
    }

    private function findOrderBySubscriptionId(string $subscriptionId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM stripe_orders
             WHERE stripe_subscription_id = :stripe_subscription_id
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([':stripe_subscription_id' => $subscriptionId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function findOrderByPaymentIntentId(string $paymentIntentId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM stripe_orders
             WHERE stripe_payment_intent_id = :stripe_payment_intent_id
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([':stripe_payment_intent_id' => $paymentIntentId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function findOrderByCheckoutSessionId(string $checkoutSessionId): ?array
    {
        if ($checkoutSessionId === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM stripe_orders
             WHERE stripe_checkout_session_id = :stripe_checkout_session_id
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([':stripe_checkout_session_id' => $checkoutSessionId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function findLicenseById(int $licenseId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM licenses WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $licenseId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function extractCustomerEmail(array $session): string
    {
        $email = (string)($session['customer_details']['email'] ?? '');
        if ($email !== '') {
            return strtolower(trim($email));
        }

        $email = (string)($session['customer_email'] ?? '');
        if ($email !== '') {
            return strtolower(trim($email));
        }

        return '';
    }

    private function extractPriceIdFromSession(array $session): string
    {
        if (!empty($session['metadata']['price_id'])) {
            return (string)$session['metadata']['price_id'];
        }

        if (!empty($session['display_items'][0]['price']['id'])) {
            return (string)$session['display_items'][0]['price']['id'];
        }

        if (!empty($session['line_items'][0]['price']['id'])) {
            return (string)$session['line_items'][0]['price']['id'];
        }

        return '';
    }

    private function extractPriceIdFromInvoice(array $invoice): string
    {
        if (!empty($invoice['lines']['data'][0]['price']['id'])) {
            return (string)$invoice['lines']['data'][0]['price']['id'];
        }

        return '';
    }

    private function extractInvoicePeriodEnd(array $invoice): ?string
    {
        if (!empty($invoice['lines']['data'][0]['period']['end'])) {
            $timestamp = (int)$invoice['lines']['data'][0]['period']['end'];
            if ($timestamp > 0) {
                return gmdate('Y-m-d H:i:s', $timestamp);
            }
        }

        return null;
    }

    private function priceMapping(string $priceId): array
    {
        $prices = $this->config['prices'] ?? [];
        if (!is_array($prices)) {
            return [];
        }

        return isset($prices[$priceId]) && is_array($prices[$priceId]) ? $prices[$priceId] : [];
    }

    private function calculateExpiresAtFromBilling(string $billing): ?string
    {
        $billing = strtolower(trim($billing));

        if (in_array($billing, ['lifetime', 'outright'], true)) {
            return null;
        }

        $base = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if (in_array($billing, ['yearly', 'annual'], true)) {
            return $base->modify('+1 year')->format('Y-m-d H:i:s');
        }

        return $base->modify('+1 month')->format('Y-m-d H:i:s');
    }

    private function generateUniqueLicenseKey(string $plan): string
    {
        $prefixMap = [
            'free'     => 'WABE-FREE',
            'advanced' => 'WABE-ADV',
            'pro'      => 'WABE-PRO',
        ];

        $prefix = $prefixMap[$plan] ?? 'WABE';

        do {
            $key = sprintf(
                '%s-%s-%s-%s',
                $prefix,
                strtoupper(bin2hex(random_bytes(3))),
                strtoupper(bin2hex(random_bytes(3))),
                strtoupper(bin2hex(random_bytes(3)))
            );
        } while ($this->licenseKeyExists($key));

        return $key;
    }

    private function licenseKeyExists(string $licenseKey): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM licenses WHERE license_key = :license_key LIMIT 1'
        );
        $stmt->execute([':license_key' => $licenseKey]);

        return (bool)$stmt->fetchColumn();
    }

    private function normalizePlan(string $plan): string
    {
        $plan = strtolower(trim($plan));
        return in_array($plan, ['free', 'advanced', 'pro'], true) ? $plan : 'free';
    }
}
