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
$license_message = wabe_license_message();
$default_domain = $license['licensed_domain'] ? $license['licensed_domain'] : wabe_member_default_domain();
?>

<main class="wabe-member-page">
    <section class="wabe-member-hero">
        <div class="wabe-container">
            <div class="wabe-member-hero-inner">
                <div>
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
                    <h2>現在のライセンス</h2>
                    <dl class="wabe-meta">
                        <div>
                            <dt>ライセンスキー</dt>
                            <dd><?php echo esc_html($license['license_key'] ? wabe_mask_license_key($license['license_key']) : '未登録'); ?>
                            </dd>
                        </div>
                        <div>
                            <dt>プラン</dt>
                            <dd><?php echo esc_html($license['license_plan'] ?: '未登録'); ?></dd>
                        </div>
                        <div>
                            <dt>状態</dt>
                            <dd><?php echo esc_html($license['license_status'] ?: '未確認'); ?></dd>
                        </div>
                        <div>
                            <dt>使用ドメイン</dt>
                            <dd><?php echo esc_html($license['licensed_domain'] ?: '-'); ?></dd>
                        </div>
                        <div>
                            <dt>最終チェック</dt>
                            <dd><?php echo esc_html($license['license_checked'] ?: '-'); ?></dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="wabe-panel">
                <h2>プランを購入する</h2>
                <p class="wabe-muted">Stripe Payment Link を本番URLへ差し替えてご利用ください。</p>

                <div class="wabe-pricing-grid">
                    <div class="wabe-price-card">
                        <h3>Free</h3>
                        <p>まずは無料で試したい方向け</p>
                        <a class="wabe-btn wabe-btn-outline"
                            href="<?php echo esc_url(WABE_STRIPE_FREE_URL); ?>">Freeを見る</a>
                    </div>

                    <div class="wabe-price-card">
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
                    <p class="wabe-muted">決済後に発行されたライセンスキーを入力してください。</p>

                    <form method="post" class="wabe-form">
                        <?php wp_nonce_field('wabe_license_action', 'wabe_license_nonce'); ?>
                        <label>
                            <span>ライセンスキー</span>
                            <input type="text" name="license_key"
                                value="<?php echo esc_attr($license['license_key']); ?>"
                                placeholder="例: WABE-PRO-XXXX-XXXX">
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
                    <h2>ライセンス無効化</h2>
                    <p class="wabe-muted">別サイトへ移行する場合などに使用してください。</p>

                    <form method="post" class="wabe-form">
                        <?php wp_nonce_field('wabe_license_action', 'wabe_license_nonce'); ?>
                        <label>
                            <span>ライセンスキー</span>
                            <input type="text" name="license_key"
                                value="<?php echo esc_attr($license['license_key']); ?>"
                                placeholder="例: WABE-PRO-XXXX-XXXX">
                        </label>

                        <label>
                            <span>使用ドメイン</span>
                            <input type="text" name="domain" value="<?php echo esc_attr($default_domain); ?>"
                                placeholder="example.com">
                        </label>

                        <button class="wabe-btn wabe-btn-danger" type="submit" name="wabe_action"
                            value="wabe_license_deactivate">無効化する</button>
                    </form>
                </div>
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
    </section>
</main>

<style>
    .wabe-container {
        max-width: 1100px;
        margin: 0 auto;
        padding: 0 20px
    }

    .wabe-member-page {
        background: #f6f8fc;
        padding-bottom: 80px
    }

    .wabe-member-hero {
        padding: 56px 0 22px;
        background: linear-gradient(135deg, #0f172a, #1d4ed8)
    }

    .wabe-member-hero-inner {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px
    }

    .wabe-member-hero h1 {
        margin: 0 0 8px;
        color: #fff;
        font-size: 36px
    }

    .wabe-member-hero p {
        margin: 0;
        color: rgba(255, 255, 255, .85)
    }

    .wabe-member-actions {
        display: flex;
        gap: 12px
    }

    .wabe-member-section {
        padding: 28px 0 0
    }

    .wabe-member-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 24px;
        margin-bottom: 24px
    }

    .wabe-panel {
        background: #fff;
        border: 1px solid #dbe4f0;
        border-radius: 18px;
        padding: 28px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, .06);
        margin-bottom: 24px
    }

    .wabe-panel h2 {
        margin: 0 0 10px;
        font-size: 24px;
        color: #0f172a
    }

    .wabe-muted {
        margin: 0 0 18px;
        color: #64748b
    }

    .wabe-meta {
        display: grid;
        gap: 14px;
        margin: 0
    }

    .wabe-meta div {
        display: grid;
        grid-template-columns: 140px 1fr;
        gap: 14px;
        padding-bottom: 12px;
        border-bottom: 1px solid #eef2f7
    }

    .wabe-meta dt {
        font-weight: 700;
        color: #334155
    }

    .wabe-meta dd {
        margin: 0;
        color: #0f172a
    }

    .wabe-pricing-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px
    }

    .wabe-price-card {
        border: 1px solid #dbe4f0;
        border-radius: 16px;
        padding: 22px;
        background: #f8fbff
    }

    .wabe-price-card h3 {
        margin: 0 0 8px;
        font-size: 22px
    }

    .wabe-price-card p {
        margin: 0 0 16px;
        color: #64748b
    }

    .wabe-form {
        display: grid;
        gap: 16px
    }

    .wabe-form label {
        display: grid;
        gap: 8px
    }

    .wabe-form span {
        font-weight: 700;
        color: #1e293b
    }

    .wabe-form input[type="text"] {
        width: 100%;
        padding: 14px 16px;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        background: #fff;
        font-size: 15px
    }

    .wabe-inline-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 10px
    }

    .wabe-btn {
        display: inline-flex;
        justify-content: center;
        align-items: center;
        min-height: 46px;
        padding: 0 16px;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 700;
        cursor: pointer;
        border: none
    }

    .wabe-btn-primary {
        background: #2563eb;
        color: #fff
    }

    .wabe-btn-outline {
        background: #fff;
        color: #0f172a;
        border: 1px solid #cbd5e1
    }

    .wabe-btn-danger {
        background: #dc2626;
        color: #fff
    }

    .wabe-btn-light {
        background: #fff;
        color: #0f172a
    }

    .wabe-alert {
        padding: 14px 16px;
        border-radius: 12px;
        margin-bottom: 20px
    }

    .wabe-alert-success {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #bbf7d0
    }

    .wabe-alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca
    }

    .wabe-steps {
        margin: 0;
        padding-left: 20px;
        color: #1e293b;
        display: grid;
        gap: 10px
    }

    @media (max-width: 980px) {

        .wabe-member-grid,
        .wabe-pricing-grid {
            grid-template-columns: 1fr
        }

        .wabe-member-hero-inner {
            flex-direction: column;
            align-items: flex-start
        }

        .wabe-meta div {
            grid-template-columns: 1fr
        }
    }
</style>

<?php get_footer(); ?>
