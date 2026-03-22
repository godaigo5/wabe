<?php

declare(strict_types=1);

$config = require __DIR__ . '/config/config.php';
session_start();

$apiConfig = isset($config['api']) && is_array($config['api']) ? $config['api'] : array();
$secret    = isset($apiConfig['secret']) ? (string) $apiConfig['secret'] : '';

if (!isset($_SESSION['test_issue_csrf'])) {
    $_SESSION['test_issue_csrf'] = bin2hex(random_bytes(16));
}

$message = '';
$error = '';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $csrf = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
        if ($csrf === '' || !hash_equals($_SESSION['test_issue_csrf'], $csrf)) {
            throw new RuntimeException('不正なリクエストです。ページを再読み込みしてもう一度お試しください。');
        }

        $token  = isset($_POST['token']) ? trim((string) $_POST['token']) : '';
        $plan   = isset($_POST['plan']) ? strtolower(trim((string) $_POST['plan'])) : '';
        $email  = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
        $domain = isset($_POST['domain']) ? trim((string) $_POST['domain']) : '';

        if ($secret === '') {
            throw new RuntimeException('API secret が設定されていません。config.php を確認してください。');
        }
        if ($token === '' || !hash_equals($secret, $token)) {
            throw new RuntimeException('認証トークンが正しくありません。');
        }

        if (!in_array($plan, array('advanced', 'pro'), true)) {
            throw new RuntimeException('プランは advanced または pro を選択してください。');
        }

        if ($email === '') {
            throw new RuntimeException('メールアドレスを入力してください。');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('メールアドレスの形式が正しくありません。');
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $config['db']['host'],
            $config['db']['name'],
            $config['db']['charset']
        );

        $pdo = new PDO(
            $dsn,
            $config['db']['user'],
            $config['db']['pass'],
            array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            )
        );

        $licenseKey  = generate_license_key($plan);
        $domainLimit = isset($apiConfig['domain_limit_default']) ? (int) $apiConfig['domain_limit_default'] : 1;
        if ($domainLimit < 1) {
            $domainLimit = 1;
        }

        $normalizedDomain = normalize_domain($domain);

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO licenses (
                license_key,
                plan,
                status,
                domain_limit,
                expires_at,
                customer_email,
                created_at,
                last_checked_at
            ) VALUES (
                :license_key,
                :plan,
                :status,
                :domain_limit,
                :expires_at,
                :customer_email,
                NOW(),
                NULL
            )'
        );

        $stmt->execute(array(
            ':license_key'    => $licenseKey,
            ':plan'           => $plan,
            ':status'         => 'active',
            ':domain_limit'   => $domainLimit,
            ':expires_at'     => null,
            ':customer_email' => $email,
        ));

        $licenseId = (int) $pdo->lastInsertId();

        if ($normalizedDomain !== '') {
            $stmt = $pdo->prepare(
                'INSERT INTO license_activations (
                    license_id,
                    domain,
                    plugin,
                    version,
                    activated_at,
                    last_seen_at
                ) VALUES (
                    :license_id,
                    :domain,
                    :plugin,
                    :version,
                    NOW(),
                    NOW()
                )'
            );

            $stmt->execute(array(
                ':license_id' => $licenseId,
                ':domain'     => $normalizedDomain,
                ':plugin'     => isset($apiConfig['plugin_slug']) ? (string) $apiConfig['plugin_slug'] : 'wp-ai-blog-engine',
                ':version'    => 'test',
            ));
        }

        $pdo->commit();

        $result = array(
            'license_id'  => $licenseId,
            'license_key' => $licenseKey,
            'plan'        => $plan,
            'status'      => 'active',
            'email'       => $email,
            'domain'      => $normalizedDomain,
            'features'    => features_for_plan($plan),
        );

        $message = 'テストライセンスを発行しました。';
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function old_post($key, $default = '')
{
    return isset($_POST[$key]) ? (string) $_POST[$key] : $default;
}

function generate_license_key($plan)
{
    $prefix = strtoupper((string) $plan);
    return $prefix . '-' . strtoupper(bin2hex(random_bytes(8))) . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function normalize_domain($domain)
{
    $domain = trim((string) $domain);
    if ($domain === '') {
        return '';
    }

    if (filter_var($domain, FILTER_VALIDATE_URL)) {
        $host = parse_url($domain, PHP_URL_HOST);
        return $host ? strtolower((string) $host) : '';
    }

    $domain = preg_replace('#^https?://#i', '', $domain);
    $domain = preg_replace('#/.*$#', '', (string) $domain);

    return strtolower(trim((string) $domain));
}

function features_for_plan($plan)
{
    $plan = strtolower(trim((string) $plan));

    switch ($plan) {
        case 'pro':
            return array(
                'weekly_posts_max'          => 7,
                'title_count_max'           => 1,
                'heading_count_max'         => 6,
                'can_publish'               => true,
                'can_use_seo'               => true,
                'can_use_images'            => true,
                'can_use_internal_links'    => true,
                'can_use_external_links'    => true,
                'can_use_topic_prediction'  => true,
                'can_use_duplicate_check'   => true,
                'can_use_outline_generator' => true,
            );

        case 'advanced':
            return array(
                'weekly_posts_max'          => 7,
                'title_count_max'           => 1,
                'heading_count_max'         => 6,
                'can_publish'               => true,
                'can_use_seo'               => true,
                'can_use_images'            => true,
                'can_use_internal_links'    => false,
                'can_use_external_links'    => false,
                'can_use_topic_prediction'  => false,
                'can_use_duplicate_check'   => false,
                'can_use_outline_generator' => false,
            );

        default:
            return array(
                'weekly_posts_max'          => 1,
                'title_count_max'           => 1,
                'heading_count_max'         => 1,
                'can_publish'               => false,
                'can_use_seo'               => false,
                'can_use_images'            => false,
                'can_use_internal_links'    => false,
                'can_use_external_links'    => false,
                'can_use_topic_prediction'  => false,
                'can_use_duplicate_check'   => false,
                'can_use_outline_generator' => false,
            );
    }
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>テストライセンス発行</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            margin: 0;
            padding: 24px;
            background: #f5f7fb;
            color: #1f2937;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Hiragino Sans", "Yu Gothic", sans-serif;
        }

        .wrap {
            max-width: 920px;
            margin: 0 auto;
        }

        .card {
            background: #ffffff;
            border: 1px solid #dbe3ef;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            padding: 28px;
            margin-bottom: 20px;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .lead {
            margin: 0 0 20px;
            color: #4b5563;
            line-height: 1.7;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            line-height: 1.6;
        }

        .alert-success {
            background: #ecfdf3;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .field {
            margin-bottom: 18px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            height: 46px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 0 14px;
            font-size: 14px;
            box-sizing: border-box;
            background: #fff;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .hint {
            margin-top: 6px;
            font-size: 12px;
            color: #6b7280;
            line-height: 1.5;
        }

        .actions {
            margin-top: 8px;
        }

        button {
            appearance: none;
            border: none;
            background: #2563eb;
            color: #fff;
            height: 46px;
            padding: 0 18px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .result-box {
            background: #0f172a;
            color: #e5e7eb;
            border-radius: 12px;
            padding: 18px;
            overflow-x: auto;
        }

        .license-key {
            display: inline-block;
            font-size: 18px;
            font-weight: 800;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            padding: 12px 14px;
            border-radius: 10px;
            word-break: break-all;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            padding: 10px 8px;
            font-size: 14px;
        }

        th {
            width: 240px;
            color: #374151;
            font-weight: 700;
            background: #f8fafc;
        }

        @media (max-width: 760px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .card {
                padding: 18px;
            }

            h1 {
                font-size: 24px;
            }

            th,
            td {
                display: block;
                width: 100%;
                box-sizing: border-box;
            }

            th {
                border-bottom: none;
                padding-bottom: 4px;
            }

            td {
                padding-top: 0;
                margin-bottom: 8px;
            }
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="card">
            <h1>テストライセンス発行</h1>
            <p class="lead">
                Advanced / Pro のテスト用ライセンスを発行する画面です。<br>
                発行後、表示されたライセンスキーを WordPress プラグイン管理画面に貼り付けて動作確認してください。
            </p>

            <?php if ($message !== ''): ?>
                <div class="alert alert-success"><?php echo h($message); ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?php echo h($error); ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <input type="hidden" name="csrf" value="<?php echo h($_SESSION['test_issue_csrf']); ?>">

                <div class="grid">
                    <div class="field">
                        <label for="token">認証トークン</label>
                        <input type="password" id="token" name="token" value="<?php echo h(old_post('token')); ?>"
                            required>
                        <div class="hint">config.php の api.secret を入力してください。</div>
                    </div>

                    <div class="field">
                        <label for="plan">プラン</label>
                        <select id="plan" name="plan" required>
                            <option value="">選択してください</option>
                            <option value="advanced" <?php echo old_post('plan') === 'advanced' ? 'selected' : ''; ?>>
                                Advanced</option>
                            <option value="pro" <?php echo old_post('plan') === 'pro' ? 'selected' : ''; ?>>Pro</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="email">メールアドレス</label>
                        <input type="email" id="email" name="email" value="<?php echo h(old_post('email')); ?>"
                            required>
                        <div class="hint">licenses.customer_email に保存されます。</div>
                    </div>

                    <div class="field">
                        <label for="domain">ドメイン（任意）</label>
                        <input type="text" id="domain" name="domain" value="<?php echo h(old_post('domain')); ?>"
                            placeholder="example.com または https://example.com">
                        <div class="hint">入力すると license_activations にも登録します。</div>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit">ライセンスを発行</button>
                </div>
            </form>
        </div>

        <?php if (is_array($result)): ?>
            <div class="card">
                <h2 style="margin-top:0;">発行結果</h2>

                <p><strong>ライセンスキー</strong></p>
                <div class="license-key"><?php echo h($result['license_key']); ?></div>

                <div style="height: 18px;"></div>

                <table>
                    <tr>
                        <th>ライセンスID</th>
                        <td><?php echo h($result['license_id']); ?></td>
                    </tr>
                    <tr>
                        <th>プラン</th>
                        <td><?php echo h($result['plan']); ?></td>
                    </tr>
                    <tr>
                        <th>状態</th>
                        <td><?php echo h($result['status']); ?></td>
                    </tr>
                    <tr>
                        <th>メールアドレス</th>
                        <td><?php echo h($result['email']); ?></td>
                    </tr>
                    <tr>
                        <th>ドメイン</th>
                        <td><?php echo h($result['domain'] !== '' ? $result['domain'] : '未指定'); ?></td>
                    </tr>
                </table>

                <div style="height: 18px;"></div>

                <h3>機能一覧</h3>
                <div class="result-box">
                    <?php echo h(json_encode($result['features'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
