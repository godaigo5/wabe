<?php
if (!defined('ABSPATH')) exit;
?>
<footer class="site-footer wabe-footer">
    <div class="wabe-footer__inner">
        <div class="wabe-footer__grid">
            <div class="wabe-footer__brand">
                <h2>WP AI Blog Engine</h2>
                <p>
                    WordPressの記事更新を、AIでもっと続けやすく。
                    タイトル・見出し・本文・画像生成まで対応し、
                    日々の運用負担を減らすためのWordPressプラグインです。
                </p>
            </div>

            <div>
                <p class="wabe-footer__nav-title">メニュー</p>
                <ul class="wabe-footer__nav">
                    <li><a href="<?php echo esc_url(home_url('/#pain')); ?>">お悩み</a></li>
                    <li><a href="<?php echo esc_url(home_url('/#features')); ?>">特長</a></li>
                    <li><a href="<?php echo esc_url(home_url('/#flow')); ?>">使い方</a></li>
                    <li><a href="<?php echo esc_url(home_url('/#pricing')); ?>">料金</a></li>
                    <li><a href="<?php echo esc_url(home_url('/#faq')); ?>">FAQ</a></li>
                </ul>
            </div>

            <div>
                <p class="wabe-footer__nav-title">はじめる</p>
                <div class="wabe-footer__cta">
                    <a class="wabe-footer__btn wabe-footer__btn--primary"
                        href="<?php echo esc_url(home_url('/free/')); ?>">無料で試す</a>
                    <a class="wabe-footer__btn wabe-footer__btn--secondary"
                        href="<?php echo esc_url(home_url('/advanced/')); ?>">Advancedを見る</a>
                </div>
            </div>
        </div>

        <div class="wabe-footer__bottom">
            <p>© <?php echo esc_html(date('Y')); ?> WP AI Blog Engine. All rights reserved.</p>
            <p>AIで記事更新を、もっと続けやすく。</p>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>

</html>
