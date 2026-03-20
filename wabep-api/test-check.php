<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ch = curl_init('https://wabep-api.d-create.online/license/check');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'license_key' => $_POST['license_key'] ?? '',
        'domain'      => $_POST['domain'] ?? '',
        'plugin'      => 'wp-ai-blog-engine',
        'version'     => '1.0.0',
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
}
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>License Check Test</title>
</head>
<body>
    <form method="post">
        <div>
            <label>License Key</label><br>
            <input type="text" name="license_key" value="TEST-PRO-123">
        </div>
        <br>
        <div>
            <label>Domain</label><br>
            <input type="text" name="domain" value="test.com">
        </div>
        <br>
        <button type="submit">Check</button>
    </form>

    <?php if (!empty($result)) : ?>
        <h2>Result</h2>
        <pre><?php echo htmlspecialchars($result, ENT_QUOTES, 'UTF-8'); ?></pre>
    <?php endif; ?>
</body>
</html>