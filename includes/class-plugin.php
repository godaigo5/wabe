<?php
if (!defined('ABSPATH')) exit;
foreach (['logger', 'license', 'plan', 'openai', 'image', 'seo', 'topic-generator', 'internal-links', 'outline-generator', 'generator', 'cron', 'admin'] as $f) {
    require_once WABE_PATH . 'includes/class-' . $f . '.php';
}
class WABE_Plugin
{
    public function run()
    {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('init', [$this, 'bootstrap'], 5);
        register_activation_hook(dirname(__FILE__, 2) . '/wp-ai-blog-engine.php', [$this, 'activate']);
        register_deactivation_hook(dirname(__FILE__, 2) . '/wp-ai-blog-engine.php', ['WABE_Cron', 'deactivate']);
    }
    public function load_textdomain()
    {
        load_plugin_textdomain(WABE_TEXTDOMAIN, false, dirname(plugin_basename(dirname(__FILE__, 2) . '/wp-ai-blog-engine.php')) . '/languages');
    }
    public function activate()
    {
        $this->maybe_create_default_options();
        WABE_Cron::deactivate();
    }
    public function bootstrap()
    {
        $this->maybe_create_default_options();
        $admin = new WABE_Admin();
        if (is_admin()) {
            add_action('admin_menu', [$admin, 'menu']);
            add_action('admin_post_wabe_save_settings', [$admin, 'save_settings']);
            add_action('admin_post_wabe_save_topics', [$admin, 'save_topics']);
            add_action('admin_post_wabe_manual_generate', [$admin, 'manual_generate']);
            add_action('admin_post_wabe_clear_logs', [$admin, 'clear_logs']);
            add_action('admin_post_wabe_save_license', [$admin, 'save_license']);
            add_action('admin_post_wabe_generate_topics', [$admin, 'generate_topics']);
        }
        add_filter('cron_schedules', [new WABE_Cron(), 'schedule']);
        add_action('wabe_generate_event', ['WABE_Cron', 'execute']);
        add_action('init', ['WABE_Cron', 'register'], 20);
    }
    private function maybe_create_default_options()
    {
        $d = ['api_key' => '', 'generation_count' => 1, 'tone' => 'standard', 'post_status' => 'draft', 'weekly_posts' => 1, 'topics' => [], 'history' => [], 'license_key' => '', 'license_data' => [], 'license_checked_at' => ''];
        $c = get_option(WABE_OPTION, false);
        if ($c === false) {
            add_option(WABE_OPTION, $d);
            return;
        }
        if (is_array($c)) update_option(WABE_OPTION, wp_parse_args($c, $d));
    }
}
