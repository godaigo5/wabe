<?php
/*
Template Name: Register / Login
*/
if (!defined('ABSPATH')) exit;

if (is_user_logged_in()) {
    wp_safe_redirect(wabe_member_url());
    exit;
}

get_header();
$auth_message = wabe_auth_message();
?>

<main class="wabe-auth-page">
    <section class="wabe-auth-hero">
        <div class="wabe-container">
            <h1>会員登録 / ログイン</h1>
            <p>WP AI Blog Engine の購入・ライセンス管理は会員ページから行えます。</p>
        </div>
    </section>

    <section class="wabe-auth-section">
        <div class="wabe-container">
            <?php if ($auth_message) : ?>
                <div class="wabe-alert wabe-alert-error">
                    <?php echo esc_html($auth_message); ?>
                </div>
            <?php endif; ?>

            <div class="wabe-auth-grid">
                <div class="wabe-auth-card">
                    <h2>新規会員登録</h2>
                    <p>購入前に会員登録しておくと、購入後の管理がスムーズです。</p>

                    <form method="post" class="wabe-form">
                        <?php wp_nonce_field('wabe_register_action', 'wabe_register_nonce'); ?>
                        <input type="hidden" name="wabe_action" value="wabe_register">

                        <label>
                            <span>お名前</span>
                            <input type="text" name="name" required>
                        </label>

                        <label>
                            <span>メールアドレス</span>
                            <input type="email" name="email" required>
                        </label>

                        <label>
                            <span>パスワード</span>
                            <input type="password" name="password" minlength="8" required>
                        </label>

                        <label class="wabe-checkbox">
                            <input type="checkbox" name="agree" value="1" required>
                            <span>利用規約・プライバシーポリシーに同意します</span>
                        </label>

                        <button type="submit" class="wabe-btn wabe-btn-primary">会員登録する</button>
                    </form>
                </div>

                <div class="wabe-auth-card">
                    <h2>ログイン</h2>
                    <p>すでに会員の方はこちらからログインしてください。</p>

                    <form method="post" class="wabe-form">
                        <?php wp_nonce_field('wabe_login_action', 'wabe_login_nonce'); ?>
                        <input type="hidden" name="wabe_action" value="wabe_login">

                        <label>
                            <span>メールアドレス または ユーザー名</span>
                            <input type="text" name="log" required>
                        </label>

                        <label>
                            <span>パスワード</span>
                            <input type="password" name="pwd" required>
                        </label>

                        <button type="submit" class="wabe-btn wabe-btn-secondary">ログインする</button>
                    </form>
                </div>
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

    .wabe-auth-page {
        background: #f6f8fc;
        padding-bottom: 80px
    }

    .wabe-auth-hero {
        padding: 70px 0 30px
    }

    .wabe-auth-hero h1 {
        font-size: 38px;
        line-height: 1.2;
        margin: 0 0 12px;
        color: #0f172a
    }

    .wabe-auth-hero p {
        font-size: 16px;
        color: #475569;
        margin: 0
    }

    .wabe-auth-section {
        padding: 10px 0 0
    }

    .wabe-auth-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 24px
    }

    .wabe-auth-card {
        background: #fff;
        border: 1px solid #dbe4f0;
        border-radius: 18px;
        padding: 28px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, .06)
    }

    .wabe-auth-card h2 {
        margin: 0 0 10px;
        font-size: 24px;
        color: #0f172a
    }

    .wabe-auth-card p {
        margin: 0 0 20px;
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

    .wabe-form input[type="text"],
    .wabe-form input[type="email"],
    .wabe-form input[type="password"] {
        width: 100%;
        padding: 14px 16px;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        background: #fff;
        font-size: 15px
    }

    .wabe-checkbox {
        display: flex !important;
        align-items: flex-start;
        gap: 10px
    }

    .wabe-checkbox input {
        margin-top: 3px
    }

    .wabe-btn {
        display: inline-flex;
        justify-content: center;
        align-items: center;
        min-height: 48px;
        padding: 0 18px;
        border: none;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 700;
        cursor: pointer
    }

    .wabe-btn-primary {
        background: #2563eb;
        color: #fff
    }

    .wabe-btn-secondary {
        background: #0f172a;
        color: #fff
    }

    .wabe-alert {
        padding: 14px 16px;
        border-radius: 12px;
        margin-bottom: 20px
    }

    .wabe-alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca
    }

    @media (max-width: 900px) {
        .wabe-auth-grid {
            grid-template-columns: 1fr
        }

        .wabe-auth-hero h1 {
            font-size: 30px
        }
    }
</style>

<?php get_footer(); ?>
