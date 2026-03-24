<?php

declare(strict_types=1);

/**
 * Public config loader for WABEP API
 *
 * Priority:
 * 1) Environment variables
 * 2) config.local.php
 * 3) Safe defaults
 */

$local = [];
$localFile = __DIR__ . '/config.local.php';

if (is_file($localFile)) {
    $loaded = require $localFile;
    if (is_array($loaded)) {
        $local = $loaded;
    }
}

return [
    'app' => [
        'env' => getenv('WABEP_APP_ENV')
            ?: ($local['app']['env'] ?? 'production'),

        'debug' => getenv('WABEP_APP_DEBUG') !== false
            ? filter_var(getenv('WABEP_APP_DEBUG'), FILTER_VALIDATE_BOOLEAN)
            : (bool)($local['app']['debug'] ?? false),

        'timezone' => getenv('WABEP_APP_TIMEZONE')
            ?: ($local['app']['timezone'] ?? 'Asia/Tokyo'),
    ],

    'db' => [
        'host' => getenv('WABEP_DB_HOST')
            ?: ($local['db']['host'] ?? ''),

        'port' => (int)(getenv('WABEP_DB_PORT')
            ?: ($local['db']['port'] ?? 3306)),

        'name' => getenv('WABEP_DB_NAME')
            ?: ($local['db']['name'] ?? ''),

        'user' => getenv('WABEP_DB_USER')
            ?: ($local['db']['user'] ?? ''),

        'pass' => getenv('WABEP_DB_PASS')
            ?: ($local['db']['pass'] ?? ''),

        'charset' => getenv('WABEP_DB_CHARSET')
            ?: ($local['db']['charset'] ?? 'utf8mb4'),
    ],

    'api' => [
        'plugin_slug' => getenv('WABEP_PLUGIN_SLUG')
            ?: ($local['api']['plugin_slug'] ?? 'wp-ai-blog-engine'),

        'secret' => getenv('WABEP_API_SECRET')
            ?: ($local['api']['secret'] ?? ''),

        'allow_test_keys' => isset($local['api']['allow_test_keys'])
            ? (bool)$local['api']['allow_test_keys']
            : true,

        'test_keys' => $local['api']['test_keys'] ?? [
            'TEST-FREE-123' => 'free',
            'TEST-ADVANCED-123' => 'advanced',
            'TEST-PRO-123' => 'pro',
        ],

        'allowed_plans' => $local['api']['allowed_plans'] ?? ['free', 'advanced', 'pro'],
        'domain_limit_default' => (int)($local['api']['domain_limit_default'] ?? 1),
    ],

    'stripe' => [
        'secret_key' => getenv('WABEP_STRIPE_SECRET_KEY')
            ?: ($local['stripe']['secret_key'] ?? ''),

        'webhook_secret' => getenv('WABEP_STRIPE_WEBHOOK_SECRET')
            ?: ($local['stripe']['webhook_secret'] ?? ''),

        'currency' => getenv('WABEP_STRIPE_CURRENCY')
            ?: ($local['stripe']['currency'] ?? 'usd'),

        /**
         * price_id => [plan, billing]
         * billing: monthly / yearly / lifetime / free
         */
        'prices' => $local['stripe']['prices'] ?? [
            // Advanced
            'price_xxxxxxxxx_advanced_monthly' => [
                'plan' => 'advanced',
                'billing' => 'monthly',
            ],
            'price_xxxxxxxxx_advanced_yearly' => [
                'plan' => 'advanced',
                'billing' => 'yearly',
            ],
            'price_xxxxxxxxx_advanced_lifetime' => [
                'plan' => 'advanced',
                'billing' => 'lifetime',
            ],

            // Pro
            'price_xxxxxxxxx_pro_monthly' => [
                'plan' => 'pro',
                'billing' => 'monthly',
            ],
            'price_xxxxxxxxx_pro_yearly' => [
                'plan' => 'pro',
                'billing' => 'yearly',
            ],
            'price_xxxxxxxxx_pro_lifetime' => [
                'plan' => 'pro',
                'billing' => 'lifetime',
            ],
        ],
    ],

    'mail' => [
        'from_email' => getenv('WABEP_MAIL_FROM_EMAIL')
            ?: ($local['mail']['from_email'] ?? 'no-reply@d-create.online'),

        'from_name' => getenv('WABEP_MAIL_FROM_NAME')
            ?: ($local['mail']['from_name'] ?? 'WP AI Blog Engine'),
    ],
];
