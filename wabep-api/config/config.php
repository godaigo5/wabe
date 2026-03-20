<?php

declare(strict_types=1);

return [
    'db' => [
        'host'    => getenv('WABEP_DB_HOST') ?: '127.0.0.1',
        'port'    => (int)(getenv('WABEP_DB_PORT') ?: 3306),
        'name'    => getenv('WABEP_DB_NAME') ?: '',
        'user'    => getenv('WABEP_DB_USER') ?: '',
        'pass'    => getenv('WABEP_DB_PASS') ?: '',
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
];
