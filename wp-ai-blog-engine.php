<?php

/**
 * Plugin Name: WP AI Blog Engine
 * Plugin URI: https://d-create.online/
 * Description: AI-powered blog generation plugin for WordPress with OpenAI / Gemini support and license-based plan control.
 * Version: 1.0.0
 * Author: D-CREATE
 * Author URI: https://d-create.online/
 * Text Domain: wp-ai-blog-engine
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

define('WABE_VERSION', '1.0.0');
define('WABE_FILE', __FILE__);
define('WABE_PATH', plugin_dir_path(__FILE__));
define('WABE_URL', plugin_dir_url(__FILE__));
define('WABE_TEXTDOMAIN', 'wp-ai-blog-engine');
define('WABE_OPTION', 'wabe_options');

/**
 * ライセンスAPIのURLを使う場合はここを本番URLに変更
 * 未設定でもFreeとして動作します
 */
if (!defined('WABE_LICENSE_API_URL')) {
    define('WABE_LICENSE_API_URL', 'https://wabep-api.d-create.online/');
}

require_once WABE_PATH . 'includes/class-plugin.php';

function wabe_boot()
{
    static $plugin = null;

    if ($plugin === null) {
        $plugin = new WABE_Plugin();
    }

    return $plugin;
}

wabe_boot();

register_activation_hook(__FILE__, 'wabe_activate');
function wabe_activate()
{
    if (!class_exists('WABE_Plugin')) {
        require_once WABE_PATH . 'includes/class-plugin.php';
    }

    $plugin = new WABE_Plugin();
    $plugin->activate();

    if (class_exists('WABE_Cron')) {
        WABE_Cron::reschedule();
    }

    flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'wabe_deactivate');
function wabe_deactivate()
{
    if (class_exists('WABE_Cron')) {
        WABE_Cron::clear();
    }

    flush_rewrite_rules();
}
