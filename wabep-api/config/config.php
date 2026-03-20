<?php

declare(strict_types=1);

return [
    'db' => [
        'host'    => getenv('WABEP_DB_HOST') ?: 'mysql328.phy.lolipop.lan',
        'port'    => (int)(getenv('WABEP_DB_PORT') ?: 3306),
        'name'    => getenv('WABEP_DB_NAME') ?: 'LAA1305650-wabepapi',
        'user'    => getenv('WABEP_DB_USER') ?: 'LAA1305650',
        'pass'    => getenv('WABEP_DB_PASS') ?: '8JGZMUKfyNcp1a0Y',
        'charset' => getenv('WABEP_DB_CHARSET') ?: 'utf8mb4',
    ],

    'api' => [
        'plugin_slug'          => 'wp-ai-blog-engine',
        'secret'               => getenv('WABEP_API_SECRET') ?: 'change-this-secret-immediately',
        'allow_test_keys'      => true,
        'test_keys'            => [
            'TEST-FREE-123'     => 'free',
            'TEST-ADVANCED-123' => 'advanced',
            'TEST-PRO-123'      => 'pro',
        ],
        'allowed_plans'        => ['free', 'advanced', 'pro'],
        'domain_limit_default' => 1,
    ],

    'stripe' => [
        'secret_key'     => getenv('WABEP_STRIPE_SECRET_KEY') ?: '',
        'webhook_secret' => getenv('WABEP_STRIPE_WEBHOOK_SECRET') ?: '',
        'currency'       => 'usd',

        /*
         | 価格設計（USD）
         |
         | Advanced:
         |   Monthly  = $12
         |   Yearly   = $79
         |   Lifetime = $199
         |
         | Pro:
         |   Monthly  = $24
         |   Yearly   = $159
         |   Lifetime = $399
         */
        'prices' => [
            'price_1TClRwQOghVIYdnPrzvrJ8Aa' => [
                'plan'    => 'advanced',
                'billing' => 'monthly',
            ],
            'price_1TClRJQOghVIYdnP5RxwLydi' => [
                'plan'    => 'advanced',
                'billing' => 'yearly',
            ],
            'price_1TClekQOghVIYdnPCCQU3PKq' => [
                'plan'    => 'advanced',
                'billing' => 'lifetime',
            ],

            'price_1TClSrQOghVIYdnPUInUClyt' => [
                'plan'    => 'pro',
                'billing' => 'monthly',
            ],
            'price_1TClSOQOghVIYdnPbiJssYuG' => [
                'plan'    => 'pro',
                'billing' => 'yearly',
            ],
            'price_1TCleAQOghVIYdnPAuPU26lp' => [
                'plan'    => 'pro',
                'billing' => 'lifetime',
            ],
        ],
    ],
];
