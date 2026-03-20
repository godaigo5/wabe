<?php
return [
    'db' => [
        'host' => 'mysql328.phy.lolipop.lan',
        'port' => 3306,
        'name' => 'LAA1305650-wabepapi',
        'user' => 'LAA1305650',
        'pass' => '8JGZMUKfyNcp1a0Y',
        'charset' => 'utf8mb4',
    ],
    'api' => [
        'plugin_slug' => 'wp-ai-blog-engine',
        'secret' => 'wabep-super-secret-2026-very-long-random',
        'allow_test_keys' => true,
        'test_keys' => [
            'TEST-FREE-123' => 'free',
            'TEST-ADVANCED-123' => 'advanced',
            'TEST-PRO-123' => 'pro',
        ],
        'allowed_plans' => ['free', 'advanced', 'pro'],
        'domain_limit_default' => 1,
    ],
];
