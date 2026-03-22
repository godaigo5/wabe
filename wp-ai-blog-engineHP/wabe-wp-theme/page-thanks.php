<?php
/*
Template Name: Thanks Page
*/
if (!defined('ABSPATH')) exit;

get_header();

$plan = isset($_GET['plan']) ? sanitize_text_field(wp_unslash($_GET['plan'])) : '';
$session_id = isset($_GET['session_id']) ? sanitize_text_field(wp_unslash($_GET['session_id'])) : '';

function wabe_thanks_plan_label($plan)
{
    $map = [
        'free'              => 'Free',
        'advanced-monthly'  => 'Advanced 月額',
        'advanced-yearly'   => 'Advanced 年額',
        'advanced-lifetime' => 'Advanced 買い切り',
        'pro-monthly'       => 'Pro 月額',
        'pro-yearly'        => 'Pro 年額',
        'pro-lifetime'      => 'Pro 買い切り',
    ];

    return $map[$plan] ?? 'プラン不明';
}

$plan_label = wabe_thanks_plan_label($plan);
?>

<main class="wabe-thanks-page">
    <section class="wabe-thanks-section">
        <div class="wabe-container">
            <div class="wabe-thanks-card">
                <div class="wabe-thanks-badge">THANK YOU</div>

                <h1>お申し込みありがとうございます</h1>

                <p class="wabe-lead">
                    決済情報を受け付けました。現在、ご注文内容を確認中です。
                </p>

                <div class="wabe-info-box">
                    <dl class="wabe-info-list">
                        <div>
                            <dt>ご購入プラン</dt>
                            <dd><?php echo esc_html($plan_label); ?></dd>
                        </div>

                        <?php if ($session_id !== '') : ?>
                            <div>
                                <dt>受付番号</dt>
                                <dd><?php echo esc_html($session_id); ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </div>

                <div class="wabe-message-box">
                    <h2>次の流れ</h2>
                    <ol>
                        <li>Stripe からの決済完了通知をサーバーで確認します</li>
                        <li>ライセンスキーを自動発行します</li>
                        <li>登録メールアドレスへライセンスキーを送信します</li>
                        <li>会員ページまたはプラグイン管理画面でライセンスを登録してください</li>
                    </ol>
                </div>

                <div class="wabe-note">
                    <p>
                        数分待ってもメールが届かない場合は、迷惑メールフォルダをご確認のうえ、会員ページからご確認ください。
                    </p>
                </div>

                <div class="wabe-actions">
                    <a class="wabe-btn wabe-btn-primary" href="<?php echo esc_url(home_url('/member/')); ?>">
                        会員ページへ
                    </a>
                    <a class="wabe-btn wabe-btn-secondary" href="<?php echo esc_url(home_url('/')); ?>">
                        トップへ戻る
                    </a>
                </div>
            </div>
        </div>
    </section>
</main>

<style>
    .wabe-thanks-page {
        background: linear-gradient(180deg, #f8fbff 0%, #eef5ff 100%);
        padding: 80px 20px;
    }

    .wabe-container {
        max-width: 900px;
        margin: 0 auto;
    }

    .wabe-thanks-card {
        background: #fff;
        border: 1px solid #dbe7ff;
        border-radius: 24px;
        box-shadow: 0 20px 50px rgba(25, 80, 180, 0.08);
        padding: 48px 36px;
    }

    .wabe-thanks-badge {
        display: inline-block;
        background: #e8f1ff;
        color: #2563eb;
        font-weight: 700;
        font-size: 12px;
        letter-spacing: .08em;
        padding: 8px 12px;
        border-radius: 999px;
        margin-bottom: 18px;
    }

    .wabe-thanks-card h1 {
        margin: 0 0 14px;
        font-size: 34px;
        line-height: 1.3;
        color: #0f172a;
    }

    .wabe-lead {
        margin: 0 0 24px;
        font-size: 16px;
        color: #475569;
    }

    .wabe-info-box,
    .wabe-message-box,
    .wabe-note {
        background: #f8fbff;
        border: 1px solid #dbe7ff;
        border-radius: 18px;
        padding: 22px;
        margin-bottom: 20px;
    }

    .wabe-info-list {
        margin: 0;
    }

    .wabe-info-list div {
        display: grid;
        grid-template-columns: 160px 1fr;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid #e5eefc;
    }

    .wabe-info-list div:last-child {
        border-bottom: none;
    }

    .wabe-info-list dt {
        font-weight: 700;
        color: #334155;
    }

    .wabe-info-list dd {
        margin: 0;
        color: #0f172a;
        word-break: break-all;
    }

    .wabe-message-box h2 {
        margin: 0 0 14px;
        font-size: 20px;
        color: #0f172a;
    }

    .wabe-message-box ol {
        margin: 0;
        padding-left: 20px;
        color: #334155;
    }

    .wabe-message-box li {
        margin-bottom: 10px;
    }

    .wabe-note p {
        margin: 0;
        color: #475569;
    }

    .wabe-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 28px;
    }

    .wabe-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 48px;
        padding: 0 20px;
        border-radius: 12px;
        font-weight: 700;
        text-decoration: none;
        transition: .2s ease;
    }

    .wabe-btn-primary {
        background: #2563eb;
        color: #fff;
    }

    .wabe-btn-primary:hover {
        background: #1d4ed8;
    }

    .wabe-btn-secondary {
        background: #fff;
        color: #0f172a;
        border: 1px solid #cbd5e1;
    }

    .wabe-btn-secondary:hover {
        background: #f8fafc;
    }

    @media (max-width: 640px) {
        .wabe-thanks-card {
            padding: 32px 20px;
        }

        .wabe-thanks-card h1 {
            font-size: 28px;
        }

        .wabe-info-list div {
            grid-template-columns: 1fr;
            gap: 6px;
        }

        .wabe-actions {
            flex-direction: column;
        }
    }
</style>

<?php get_footer(); ?>
