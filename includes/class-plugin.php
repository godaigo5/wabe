<?php
if (!defined('ABSPATH')) exit;

class WABE_Plugin
{
    public function __construct()
    {
        $this->load_dependencies();

        add_action('init', [$this, 'init']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'maybe_create_default_options']);
    }

    private function load_dependencies()
    {
        require_once WABE_PATH . 'includes/class-openai.php';
        require_once WABE_PATH . 'includes/class-gemini.php';
        require_once WABE_PATH . 'includes/class-generator.php';
        require_once WABE_PATH . 'includes/class-admin.php';
        require_once WABE_PATH . 'includes/class-cron.php';
        require_once WABE_PATH . 'includes/class-logger.php';
        require_once WABE_PATH . 'includes/class-plan.php';
        require_once WABE_PATH . 'includes/class-license.php';
        require_once WABE_PATH . 'includes/class-image.php';

        if (file_exists(WABE_PATH . 'includes/class-seo.php')) {
            require_once WABE_PATH . 'includes/class-seo.php';
        }

        if (file_exists(WABE_PATH . 'includes/class-internal-links.php')) {
            require_once WABE_PATH . 'includes/class-internal-links.php';
        }

        if (file_exists(WABE_PATH . 'includes/class-outline-generator.php')) {
            require_once WABE_PATH . 'includes/class-outline-generator.php';
        }

        if (file_exists(WABE_PATH . 'includes/class-topic-predictor.php')) {
            require_once WABE_PATH . 'includes/class-topic-predictor.php';
        }
    }

    public function init()
    {
        WABE_Cron::init();
    }

    public function admin_menu()
    {
        add_menu_page(
            'WP AI Blog Engine',
            'WP AI Blog',
            'manage_options',
            'wabe',
            [$this, 'render_settings_page'],
            'dashicons-edit',
            26
        );

        add_submenu_page(
            'wabe',
            __('Settings', WABE_TEXTDOMAIN),
            __('Settings', WABE_TEXTDOMAIN),
            'manage_options',
            'wabe',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'wabe',
            __('Topics', WABE_TEXTDOMAIN),
            __('Topics', WABE_TEXTDOMAIN),
            'manage_options',
            'wabe-topics',
            [$this, 'render_topics_page']
        );

        add_submenu_page(
            'wabe',
            __('Logs', WABE_TEXTDOMAIN),
            __('Logs', WABE_TEXTDOMAIN),
            'manage_options',
            'wabe-logs',
            [$this, 'render_logs_page']
        );

        add_submenu_page(
            'wabe',
            __('License', WABE_TEXTDOMAIN),
            __('License', WABE_TEXTDOMAIN),
            'manage_options',
            'wabe-license',
            [$this, 'render_license_page']
        );
    }

    public function render_settings_page()
    {
        $admin = new WABE_Admin();
        $admin->render_settings();
    }

    public function render_topics_page()
    {
        $admin = new WABE_Admin();
        $admin->render_topics();
    }

    public function render_logs_page()
    {
        $admin = new WABE_Admin();
        $admin->render_logs();
    }

    public function render_license_page()
    {
        $admin = new WABE_Admin();
        $admin->render_license();
    }

    public function maybe_create_default_options()
    {
        $defaults = [
            'ai_provider' => 'openai',

            'openai_api_key' => '',
            'gemini_api_key' => '',

            'openai_model' => 'gpt-4.1',
            'gemini_model' => 'gemini-2.5-flash',

            'generation_count' => 1,
            'heading_count' => 1,
            'tone' => 'standard',
            'post_status' => 'draft',
            'weekly_posts' => 1,

            'image_style' => 'modern',
            'enable_featured_image' => '0',

            'topics' => [],
            'history' => [],
            'logs' => [],

            'enable_topic_prediction' => '0',
            'enable_duplicate_check' => '0',
            'enable_external_links' => '0',

            'license_key' => '',
            'license_data' => [],
            'license_checked_at' => '',
        ];

        $current = get_option(WABE_OPTION, false);

        if ($current === false) {
            add_option(WABE_OPTION, $defaults);
            return;
        }

        if (is_array($current)) {
            $current = wp_parse_args($current, $defaults);
            update_option(WABE_OPTION, $current);
        }
    }
}
