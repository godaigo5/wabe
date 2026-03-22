<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO(
        $config['db_dsn'],
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $input = json_decode(file_get_contents('php://input'), true);

    $plan  = strtolower(trim($input['plan'] ?? ''));
    $email = trim($input['email'] ?? '');

    if (!in_array($plan, ['advanced', 'pro'], true)) {
        echo json_encode([
            'ok' => false,
            'error' => 'plan must be advanced or pro'
        ]);
        exit;
    }

    if ($email === '') {
        echo json_encode([
            'ok' => false,
            'error' => 'email required'
        ]);
        exit;
    }

    // ライセンスキー生成
    $licenseKey = strtoupper($plan) . '-' . bin2hex(random_bytes(8));

    // 有効期限（テストなので無期限）
    $expiresAt = null;

    // ドメイン制限
    $domainLimit = 1;

    // DB保存
    $stmt = $pdo->prepare("
        INSERT INTO licenses (
            license_key,
            plan,
            status,
            domain_limit,
            expires_at,
            customer_email,
            created_at
        ) VALUES (
            :license_key,
            :plan,
            'active',
            :domain_limit,
            :expires_at,
            :email,
            NOW()
        )
    ");

    $stmt->execute([
        ':license_key'  => $licenseKey,
        ':plan'         => $plan,
        ':domain_limit' => $domainLimit,
        ':expires_at'   => $expiresAt,
        ':email'        => $email,
    ]);

    echo json_encode([
        'ok' => true,
        'license_key' => $licenseKey,
        'plan' => $plan,
        'message' => 'Test license issued'
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
