<?php

/**
 * Template Name: Thanks Page
 */

if (!defined('ABSPATH')) exit;

get_header();

$plan = isset($_GET['plan']) ? sanitize_text_field(wp_unslash($_GET['plan'])) : '';
$order_id = isset($_GET['order_id']) ? sanitize_text_field(wp_unslash($_GET['order_id'])) : '';

if (!function_exists('wabe_thanks_plan_label')) {
    function wabe_thanks_plan_label($plan)
    {
        $map = [
            'free'               => 'Free',
            'advanced-monthly'   => 'Advanced 月額',
            'advanced-yearly'    => 'Advanced 年額',
            'advanced-lifetime'  => 'Advanced 買い切り',
            'pro-monthly'        => 'Pro 月額',
            'pro-yearly'         => 'Pro 年額',
            'pro-lifetime'       => 'Pro 買い切り',
        ];

        return $map[$plan] ?? '';
    }
}

$plan_label = wabe_thanks_plan_label($plan);
?>

<main class="wabe-thanks-page">
    <section class="wabe-thanks-hero">
        <div class="wabe-thanks-wrap">
            <span class="wabe-thanks-badge">THANK YOU</span>
            <h1 class="wabe-thanks-title">お申し込みありがとうございます</h1>
            <p class="wabe-thanks-lead">
                決済を受け付けました。<br>
                ライセンスキーは、確認後に登録メールアドレス宛へ送信されます。
            </p>
        </div>
    </section>

    <section>
        <div class="wabe-thanks-wrap">
            <div class="wabe-thanks-grid">
                <div class="wabe-card">
                    <h2>次の流れ</h2>

                    <ul class="wabe-info-list">
                        <?php if ($plan_label !== '') : ?>
                            <li>
                                <strong>ご購入プラン</strong>
                                <span><?php echo esc_html($plan_label); ?></span>
                            </li>
                        <?php endif; ?>

                        <?php if ($order_id !== '') : ?>
                            <li>
                                <strong>受付番号</strong>
                                <span><?php echo esc_html($order_id); ?></span>
                            </li>
                        <?php endif; ?>
                    </ul>

                    <ol class="wabe-step-list">
                        <li data-step="1">メールを確認し、ライセンスキーの案内をご確認ください。</li>
                        <li data-step="2">会員ページにログインし、対象ドメインを有効化してください。</li>
                        <li data-step="3">プラグインのライセンス画面にライセンスキーを入力してください。</li>
                        <li data-step="4">必要に応じて「ライセンス情報を再取得」を押して反映を確認してください。</li>
                    </ol>

                    <div class="wabe-note">
                        購入直後は、メール送信や反映に少し時間がかかる場合があります。
                        数分ほど待ってからご確認ください。
                    </div>

                    <div class="wabe-help">
                        <strong>メールが届かない場合</strong>
                        <p>
                            迷惑メールフォルダをご確認ください。見つからない場合は、
                            会員ページにログインしてライセンス情報をご確認ください。
                        </p>
                    </div>

                    <div class="wabe-actions">
                        <a class="wabe-btn wabe-btn--primary"
                            href="<?php echo esc_url(home_url('/member/')); ?>">会員ページへ</a>
                        <a class="wabe-btn wabe-btn--secondary" href="<?php echo esc_url(home_url('/')); ?>">トップへ戻る</a>
                    </div>
                </div>

                <div class="wabe-card wabe-mini-card">
                    <span class="wabe-mini-card__status">ご案内中</span>
                    <h2>購入後のポイント</h2>
                    <p>
                        このページでは、購入後に迷いやすいポイントだけをまとめています。
                        実際の利用開始までは、次の3点を押さえておけば大丈夫です。
                    </p>

                    <div class="wabe-mini-card__check">
                        <div>
                            <strong>1. メールを確認</strong>
                            <p>ライセンスキーの案内は登録メールアドレス宛に送信されます。</p>
                        </div>
                        <div>
                            <strong>2. ドメインを有効化</strong>
                            <p>会員ページで対象ドメインを有効化してからご利用ください。</p>
                        </div>
                        <div>
                            <strong>3. プラグインに入力</strong>
                            <p>ライセンス画面へ入力後、状態を更新すれば利用開始できます。</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php get_footer(); ?>
