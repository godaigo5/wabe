<?php
if (!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
  <?php wp_body_open(); ?>
  <header class="wabe-site-header">
    <div class="wabe-header-inner">
      <a href="<?php echo esc_url(home_url('/')); ?>" class="wabe-brand" aria-label="<?php bloginfo('name'); ?>">
        <span class="wabe-brand-mark">AI</span>
        <span class="wabe-brand-text">
          <span class="wabe-brand-title">WP AI Blog Engine</span>
          <span class="wabe-brand-sub">WordPress向けAI記事作成プラグイン</span>
        </span>
      </a>

      <button class="wabe-menu-toggle" type="button" aria-expanded="false" aria-controls="wabeHeaderNav"
        data-wabe-menu-toggle>
        ☰
      </button>

      <div class="wabe-header-nav-wrap" id="wabeHeaderNav">
        <nav class="wabe-global-nav" aria-label="Global Navigation">
          <ul>
            <li><a href="<?php echo esc_url(home_url('/#problems')); ?>">お悩み</a></li>
            <li><a href="<?php echo esc_url(home_url('/#features')); ?>">特長</a></li>
            <li><a href="<?php echo esc_url(home_url('/#flow')); ?>">使い方</a></li>
            <li><a href="<?php echo esc_url(home_url('/#pricing')); ?>">料金</a></li>
            <li><a href="<?php echo esc_url(home_url('/#faq')); ?>">FAQ</a></li>
          </ul>
        </nav>

        <div class="wabe-header-actions">
          <a class="wabe-btn wabe-btn-secondary" href="<?php echo esc_url(home_url('/free')); ?>">無料で試す</a>
          <a class="wabe-btn wabe-btn-primary"
            href="<?php echo esc_url(home_url('/advanced')); ?>">Advancedを見る</a>
        </div>
      </div>
    </div>
  </header>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var toggle = document.querySelector('[data-wabe-menu-toggle]');
      var nav = document.getElementById('wabeHeaderNav');
      if (!toggle || !nav) return;

      toggle.addEventListener('click', function() {
        var isOpen = nav.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        toggle.textContent = isOpen ? '×' : '☰';
      });

      nav.querySelectorAll('a').forEach(function(link) {
        link.addEventListener('click', function() {
          if (window.innerWidth <= 991) {
            nav.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.textContent = '☰';
          }
        });
      });
    });
  </script>
