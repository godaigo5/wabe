<?php
if (!defined('ABSPATH')) exit;

function wabe_theme_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    register_nav_menus([
        'primary' => 'Primary Menu',
    ]);
}
add_action('after_setup_theme', 'wabe_theme_setup');

function wabe_enqueue_assets() {
    wp_enqueue_style('wabe-style', get_stylesheet_uri(), [], '1.0.0');
    wp_enqueue_script('wabe-script', get_template_directory_uri() . '/assets/js/script.js', [], '1.0.0', true);
}
add_action('wp_enqueue_scripts', 'wabe_enqueue_assets');

function wabe_menu_fallback() {
    echo '<nav class="nav" id="globalNav">';
    if (is_front_page()) {
        echo '<a href="#features">特長</a>';
        echo '<a href="#benefits">導入メリット</a>';
        echo '<a href="#pricing">料金プラン</a>';
        echo '<a href="#guide">導入案内</a>';
        echo '<a href="#faq">よくある質問</a>';
    } else {
        echo '<a href="' . esc_url(home_url('/')) . '">トップ</a>';
        echo '<a href="' . esc_url(home_url('/free/')) . '">Free</a>';
        echo '<a href="' . esc_url(home_url('/advanced/')) . '">Advanced</a>';
        echo '<a href="' . esc_url(home_url('/pro/')) . '">Pro</a>';
    }
    echo '</nav>';
}

function wabe_cta_primary_link() {
    return esc_url(home_url('/#pricing'));
}

function wabe_cta_secondary_link() {
    return esc_url(home_url('/#faq'));
}
