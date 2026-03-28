<?php
if (!defined('ABSPATH')) exit;
?>
<footer class="wabe-site-footer">
  <div class="wabe-footer-inner">
    <div class="wabe-footer-grid">
      <div class="wabe-footer-brand">
        <h2 class="wabe-footer-title">WP AI Blog Engine</h2>
        <p class="wabe-footer-text">
          タイトル・見出し・本文・画像生成まで対応。<br>
          WordPressの記事更新を、AIで続けやすくするためのプラグインです。
        </p>
        <div class="wabe-footer-cta">
          <a href="<?php echo esc_url(home_url('/free')); ?>"
            class="wabe-footer-btn wabe-footer-btn-secondary">無料で試す</a>
          <a href="<?php echo esc_url(home_url('/advanced')); ?>"
            class="wabe-footer-btn wabe-footer-btn-primary">Advancedを見る</a>
        </div>
      </div>

      <div>
        <h3 class="wabe-footer-heading">サイト</h3>
        <ul class="wabe-footer-links">
          <li><a href="<?php echo esc_url(home_url('/')); ?>">トップ</a></li>
          <li><a href="<?php echo esc_url(home_url('/#features')); ?>">特長</a></li>
          <li><a href="<?php echo esc_url(home_url('/#flow')); ?>">使い方</a></li>
          <li><a href="<?php echo esc_url(home_url('/#pricing')); ?>">料金プラン</a></li>
          <li><a href="<?php echo esc_url(home_url('/#faq')); ?>">よくある質問</a></li>
        </ul>
      </div>

      <div>
        <h3 class="wabe-footer-heading">プラン</h3>
        <ul class="wabe-footer-links">
          <li><a href="<?php echo esc_url(home_url('/free')); ?>">Free</a></li>
          <li><a href="<?php echo esc_url(home_url('/advanced')); ?>">Advanced</a></li>
          <li><a href="<?php echo esc_url(home_url('/pro')); ?>">Pro</a></li>
          <li><a href="<?php echo esc_url(home_url('/member')); ?>">会員ページ</a></li>
          <li><a href="<?php echo esc_url(home_url('/register')); ?>">会員登録</a></li>
        </ul>
      </div>

      <div>
        <h3 class="wabe-footer-heading">導入前の確認</h3>
        <ul class="wabe-footer-links">
          <li><a href="<?php echo esc_url(home_url('/#faq')); ?>">購入前の不安を解消</a></li>
          <li><a href="<?php echo esc_url(home_url('/#pricing')); ?>">プラン比較を見る</a></li>
          <li><a href="<?php echo esc_url(home_url('/advanced')); ?>">主力プランを見る</a></li>
          <li><a href="<?php echo esc_url(home_url('/pro')); ?>">上位プランを見る</a></li>
        </ul>
      </div>
    </div>

    <div class="wabe-footer-bottom">
      <div class="wabe-footer-copy">
        © <?php echo esc_html(date_i18n('Y')); ?> WP AI Blog Engine / D-CREATE
      </div>
      <div class="wabe-footer-bottom-links">
        <a href="<?php echo esc_url(home_url('/')); ?>">Home</a>
        <a href="<?php echo esc_url(home_url('/member')); ?>">Member</a>
        <a href="<?php echo esc_url(home_url('/register')); ?>">Register</a>
      </div>
    </div>
  </div>
</footer>
<?php wp_footer(); ?>
</body>

</html>
