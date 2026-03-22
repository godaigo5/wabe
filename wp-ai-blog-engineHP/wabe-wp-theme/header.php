<?php if (!defined('ABSPATH')) exit; ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header" id="top">
  <div class="container header-inner">
    <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="WP AI Blog Engine トップへ">
      <span class="brand-mark">AI</span>
      <span>WP AI Blog Engine</span>
    </a>

    <button class="menu-toggle" id="menuToggle" aria-label="メニューを開く" aria-expanded="false" aria-controls="globalNav">☰</button>

    <?php
    wp_nav_menu([
      'theme_location' => 'primary',
      'container'      => false,
      'menu_class'     => 'nav',
      'menu_id'        => 'globalNav',
      'fallback_cb'    => 'wabe_menu_fallback',
      'depth'          => 1,
    ]);
    ?>

    <div class="header-cta">
      <a class="btn btn-secondary" href="<?php echo esc_url(wabe_cta_secondary_link()); ?>">購入前の不安を解消</a>
      <a class="btn btn-primary" href="<?php echo esc_url(wabe_cta_primary_link()); ?>">料金プランを見る</a>
    </div>
  </div>
</header>
