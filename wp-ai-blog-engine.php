<?php
/*
Plugin Name: WP AI Blog Engine
Description: AI-powered blog automation for WordPress with licensing, SEO, images, and Pro automation features.
Version: 1.0.0
Author: D-CREATE
Text Domain: wp-ai-blog-engine
Domain Path: /languages
*/
if (!defined('ABSPATH')) exit;
define('WABE_VERSION', '1.0.0');
define('WABE_PATH', plugin_dir_path(__FILE__));
define('WABE_URL', plugin_dir_url(__FILE__));
define('WABE_OPTION', 'wabe_options');
define('WABE_TEXTDOMAIN', 'wp-ai-blog-engine');
define('WABE_LICENSE_API_BASE', 'https://wabep-api.d-create.online');
define('WABE_UPGRADE_URL', 'https://d-create.online/wp-ai-blog-engine/');
define('WABE_BUY_ADVANCED_URL', 'https://d-create.online/wp-ai-blog-engine/buy?plan=advanced');
define('WABE_BUY_PRO_URL', 'https://d-create.online/wp-ai-blog-engine/buy?plan=pro');
require_once WABE_PATH . 'includes/class-plugin.php';
function wabe_run_plugin()
{
    (new WABE_Plugin())->run();
}
wabe_run_plugin();
