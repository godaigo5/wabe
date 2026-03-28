<?php
/*
Template Name: Member Page
*/
if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    wp_safe_redirect(wabe_register_url());
    exit;
}

get_header();

$user = wp_get_current_user();
$license = wabe_get_user_license_data($user->ID);
$licenses = function_exists('wabe_get_user_licenses') ? wabe_get_user_licenses($user->ID) : [];
$license_message = wabe_license_message();
$default_domain = !empty($license['licensed_domain']) ? $license['licensed_domain'] : wabe_member_default_domain();

if (!function_exists('wabe_member_plan_label')) {
    function wabe_member_plan_label($plan)
    {
        $plan = strtolower((string) $plan);
        $map = [
            'free' => 'Free',
            'advanced' => 'Advanced',
            'pro' => 'Pro',
            'advanced-monthly' => 'Advanced 月額',
            'advanced-yearly' => 'Advanced 年額',
            'advanced-lifetime' => 'Advanced 買い切り',
            'pro-monthly' => 'Pro 月額',
            'pro-yearly' => 'Pro 年額',
            'pro-lifetime' => 'Pro 買い切り',
        ];
        return $map[$plan] ?? ($plan !== '' ? ucfirst($plan) : '未登録');
    }
}

if (!function_exists('wabe_member_status_badge')) {
    function wabe_member_status_badge($status)
    {
        $status = strtolower((string) $status);
        $map = [
            'active'   => ['label' => '有効',   'bg' => '#dcfce7', 'color' => '#166534'],
            'valid'    => ['label' => '確認済み', 'bg' => '#dbeafe', 'color' => '#1d4ed8'],
            'inactive' => ['label' => '未使用', 'bg' => '#f3f4f6', 'color' => '#374151'],
            'invalid'  => ['label' => '無効',   'bg' => '#fee2e2', 'color' => '#991b1b'],
            'expired'  => ['label' => '期限切れ', 'bg' => '#fee2e2', 'color' => '#991b1b'],
        ];
        $item = $map[$status] ?? ['label' => ($status !== '' ? $status : '未確認'), 'bg' => '#f3f4f6', 'color' => '#374151'];

        return '<span class="wabe-status-badge" style="background:' . esc_attr($item['bg']) . ';color:' . esc_attr($item['color']) . ';">' . esc_html($item['label']) . '</span>';
    }
}
?>

<main class="wabe-member-page">
    <section class="wabe-member-hero">
        <div class="wabe-container">
            <div class="wabe-member-hero-inner">
                <div>
                    <span class="wabe-member-hero-badge">MEMBER PAGE</span>
                    <h1>会員ページ</h1>
                    <p><?php echo esc_html($user->display_name ?: $user->user_email); ?> さん、ようこそ。</p>
                </div>
                <div class="wabe-member-actions">
                    <a class="wabe-btn wabe-btn-light" href="<?php echo esc_url(wabe_logout_url()); ?>">ログアウト</a>
                </div>
            </div>
        </div>
    </section>

    <section class="wabe-member-section">
        <div class="wabe-container">
            <?php if (!empty($_GET['welcome'])) : ?>
                <div class="wabe-alert wabe-alert-success">会員登録が完了しました。次にプラン購入またはライセンス登録を行ってください。</div>
            <?php endif; ?>

            <?php if ($license_message) : ?>
                <div
                    class="wabe-alert <?php echo (strpos($license_message, '失敗') !== false || strpos($license_message, '入力') !== false) ? 'wabe-alert-error' : 'wabe-alert-success'; ?>">
                    <?php echo esc_html($license_message); ?>
                </div>
            <?php endif; ?>

            <div class="wabe-member-grid">
                <div class="wabe-panel">
                    <h2>アカウント情報</h2>
                    <dl class="wabe-meta">
                        <div>
                            <dt>お名前</dt>
                            <dd><?php echo esc_html($user->display_name ?: '-'); ?></dd>
                        </div>
                        <div>
                            <dt>メールアドレス</dt>
                            <dd><?php echo esc_html($user->user_email); ?></dd>
                        </div>
                        <div>
                            <dt>会員種別</dt>
                            <dd><?php echo esc_html(implode(', ', $user->roles)); ?></dd>
                        </div>
                        <div>
                            <dt>登録日</dt>
                            <dd><?php echo esc_html(mysql2date('Y-m-d H:i', $user->user_registered)); ?></dd>
                        </div>
                    </dl>
                </div>

                <div class="wabe-panel">
                    <h2>現在のメインライセンス</h2>
                    <dl class="wabe-meta">
                        <div>
                            <dt>ライセンスキー</dt>
                            <dd><?php echo esc_html(!empty($license['license_key']) ? wabe_mask_license_key($license['license_key']) : '未登録'); ?>
                            </dd>
                        </div>
                        <div>
                            <dt>プラン</dt>
                            <dd><?php echo esc_html(!empty($license['license_plan']) ? wabe_member_plan_label($license['license_plan']) : '未登録'); ?>
                            </dd>
                        </div>
                        <div>
                            <dt>状態</dt>
                            <dd><?php echo wabe_member_status_badge($license['license_status'] ?? ''); ?></dd>
                        </div>
                        <div>
                            <dt>使用ドメイン</dt>
                            <dd><?php echo esc_html(!empty($license['licensed_domain']) ? $license['licensed_domain'] : '-'); ?>
                            </dd>
                        </div>
                        <div>
                            <dt>最終チェック</dt>
                            <dd><?php echo esc_html(!empty($license['license_checked']) ? $license['license_checked'] : '-'); ?>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="wabe-panel">
                <h2>プランを購入する</h2>
                <p class="wabe-muted">希望プランを選択してください。購入後、会員ページでライセンスを有効化できます。</p>

                <div class="wabe-pricing-grid">
                    <div class="wabe-price-card">
                        <h3>Free</h3>
                        <p>まずは無料で試したい方向け</p>
                        <a class="wabe-btn wabe-btn-outline"
                            href="<?php echo esc_url(WABE_STRIPE_FREE_URL); ?>">Freeを見る</a>
                    </div>

                    <div class="wabe-price-card wabe-price-card-featured">
                        <span class="wabe-price-card-badge">おすすめ</span>
                        <h3>Advanced</h3>
                        <p>中級者・実運用向け</p>
                        <div class="wabe-inline-buttons">
                            <a class="wabe-btn wabe-btn-primary"
                                href="<?php echo esc_url(WABE_STRIPE_ADVANCED_MONTHLY_URL); ?>">月額</a>
                            <a class="wabe-btn wabe-btn-outline"
                                href="<?php echo esc_url(WABE_STRIPE_ADVANCED_YEARLY_URL); ?>">年額</a>
                            <a class="wabe-btn wabe-btn-outline"
                                href="<?php echo esc_url(WABE_STRIPE_ADVANCED_LIFETIME_URL); ?>">買い切り</a>
                        </div>
                    </div>

                    <div class="wabe-price-card">
                        <h3>Pro</h3>
                        <p>販売・本格運用向け</p>
                        <div class="wabe-inline-buttons">
                            <a class="wabe-btn wabe-btn-primary"
                                href="<?php echo esc_url(WABE_STRIPE_PRO_MONTHLY_URL); ?>">月額</a>
                            <a class="wabe-btn wabe-btn-outline"
                                href="<?php echo esc_url(WABE_STRIPE_PRO_YEARLY_URL); ?>">年額</a>
                            <a class="wabe-btn wabe-btn-outline"
                                href="<?php echo esc_url(WABE_STRIPE_PRO_LIFETIME_URL); ?>">買い切り</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wabe-member-grid">
                <div class="wabe-panel">
                    <h2>ライセンス確認 / 有効化</h2>
                    <p class="wabe-muted">決済後に発行されたライセンスキーを入力してください。複数のライセンスキーを登録できます。</p>

                    <form method="post" class="wabe-form">
                        <?php wp_nonce_field('wabe_license_action', 'wabe_license_nonce'); ?>
                        <label>
                            <span>ライセンスキー</span>
                            <input type="text" name="license_key" value="" placeholder="例: WABE-PRO-XXXX-XXXX">
                        </label>

                        <label>
                            <span>使用ドメイン</span>
                            <input type="text" name="domain" value="<?php echo esc_attr($default_domain); ?>"
                                placeholder="example.com">
                        </label>

                        <div class="wabe-inline-buttons">
                            <button class="wabe-btn wabe-btn-outline" type="submit" name="wabe_action"
                                value="wabe_license_check">確認する</button>
                            <button class="wabe-btn wabe-btn-primary" type="submit" name="wabe_action"
                                value="wabe_license_activate">有効化する</button>
                        </div>
                    </form>
                </div>

                <div class="wabe-panel">
                    <h2>導入の流れ</h2>
                    <ol class="wabe-steps">
                        <li>会員登録を行う</li>
                        <li>会員ページから希望プランを購入する</li>
                        <li>決済後に発行されたライセンスキーを受け取る</li>
                        <li>このページでライセンスキーを入力し、有効化する</li>
                        <li>WordPressプラグイン管理画面にライセンスキーを入力して利用開始</li>
                    </ol>
                </div>
            </div>

            <div class="wabe-panel">
                <div class="wabe-panel-headline">
                    <div>
                        <h2>登録済みライセンス一覧</h2>
                        <p class="wabe-muted">1つのアカウントで複数のライセンスキーを管理できます。</p>
                    </div>
                    <div class="wabe-license-count"><?php echo esc_html(count($licenses)); ?>件</div>
                </div>

                <?php if (!empty($licenses)) : ?>
                    <div class="wabe-license-table-wrap">
                        <table class="wabe-license-table">
                            <thead>
                                <tr>
                                    <th>ライセンスキー</th>
                                    <th>プラン</th>
                                    <th>状態</th>
                                    <th>使用ドメイン</th>
                                    <th>最終チェック</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($licenses as $row) : ?>
                                    <?php
                                    $row_key = (string) ($row['license_key'] ?? '');
                                    $row_domain = (string) ($row['licensed_domain'] ?? '');
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html($row_key); ?>
                                        </td>
                                        <td><?php echo esc_html(wabe_member_plan_label($row['license_plan'] ?? '')); ?></td>
                                        <td><?php echo wabe_member_status_badge($row['license_status'] ?? ''); ?></td>
                                        <td><?php echo esc_html($row_domain !== '' ? $row_domain : '-'); ?></td>
                                        <td><?php echo esc_html(!empty($row['license_checked']) ? $row['license_checked'] : '-'); ?>
                                        </td>
                                        <td>
                                            <div class="wabe-row-actions">
                                                <form method="post" class="wabe-row-form">
                                                    <?php wp_nonce_field('wabe_license_action', 'wabe_license_nonce'); ?>
                                                    <input type="hidden" name="license_key"
                                                        value="<?php echo esc_attr($row_key); ?>">
                                                    <input type="hidden" name="domain"
                                                        value="<?php echo esc_attr($row_domain !== '' ? $row_domain : $default_domain); ?>">
                                                    <button class="wabe-btn wabe-btn-primary wabe-btn-small" type="submit"
                                                        name="wabe_action" value="wabe_license_activate">有効化</button>
                                                </form>

                                                <form method="post" class="wabe-row-form">
                                                    <?php wp_nonce_field('wabe_license_action', 'wabe_license_nonce'); ?>
                                                    <input type="hidden" name="license_key"
                                                        value="<?php echo esc_attr($row_key); ?>">
                                                    <input type="hidden" name="domain"
                                                        value="<?php echo esc_attr($row_domain !== '' ? $row_domain : $default_domain); ?>">
                                                    <button class="wabe-btn wabe-btn-danger wabe-btn-small" type="submit"
                                                        name="wabe_action" value="wabe_license_deactivate"
                                                        onclick="return confirm('このライセンスを無効化しますか？');">無効化</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else : ?>
                    <div class="wabe-empty-state">
                        まだライセンスは登録されていません。購入後、上のフォームからライセンスキーを追加してください。
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php get_footer(); ?>
