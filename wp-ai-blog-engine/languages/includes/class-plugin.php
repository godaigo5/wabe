<?php
if (!defined('ABSPATH')) exit;

class WABE_Plugin
{
    /** @var WABE_Admin|null */
    private $admin = null;

    public function __construct()
    {
        $this->load_dependencies();

        add_action('init', [$this, 'init']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
    }

    /**
     * 依存ファイル読込
     *
     * @return void
     */
    private function load_dependencies()
    {
        $files = [
            'includes/class-logger.php',
            'includes/class-plan.php',
            'includes/class-license.php',
            'includes/class-openai.php',
            'includes/class-gemini.php',
            'includes/class-image.php',
            'includes/class-seo.php',
            'includes/class-internal-links.php',
            'includes/class-outline-generator.php',
            'includes/class-topic-generator.php',
            'includes/class-generator.php',
            'includes/class-cron.php',
            'includes/class-admin.php',
        ];

        foreach ($files as $file) {
            $path = WABE_PATH . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }

    /**
     * 初期化
     *
     * @return void
     */
    public function init()
    {
        if (class_exists('WABE_Cron')) {
            WABE_Cron::init();
        }

        if (is_admin() && class_exists('WABE_Admin') && $this->admin === null) {
            $this->admin = new WABE_Admin();
        }
    }

    /**
     * 管理画面メニュー登録
     *
     * @return void
     */
    public function register_admin_menu()
    {
        if (!is_admin()) {
            return;
        }

        if (!class_exists('WABE_Admin')) {
            return;
        }

        if ($this->admin === null) {
            $this->admin = new WABE_Admin();
        }

        if (method_exists($this->admin, 'menu')) {
            $this->admin->menu();
        }
    }

    /**
     * 管理画面用CSS/JS
     *
     * @param string $hook_suffix
     * @return void
     */
    public function admin_enqueue_scripts($hook_suffix)
    {
        if (!is_admin()) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $screen_id = is_object($screen) && !empty($screen->id) ? (string)$screen->id : '';

        $is_wabe_screen = false;

        if (strpos((string)$hook_suffix, 'wabe') !== false) {
            $is_wabe_screen = true;
        }

        if ($screen_id !== '' && strpos($screen_id, 'wabe') !== false) {
            $is_wabe_screen = true;
        }

        if (!$is_wabe_screen) {
            return;
        }

        $css_file = WABE_PATH . 'assets/admin.css';
        $js_file  = WABE_PATH . 'assets/admin.js';

        if (file_exists($css_file)) {
            wp_enqueue_style(
                'wabe-admin',
                WABE_URL . 'assets/admin.css',
                [],
                defined('WABE_VERSION') ? WABE_VERSION : filemtime($css_file)
            );
        }

        if (file_exists($js_file)) {
            wp_enqueue_script(
                'wabe-admin',
                WABE_URL . 'assets/admin.js',
                ['jquery'],
                defined('WABE_VERSION') ? WABE_VERSION : filemtime($js_file),
                true
            );

            wp_localize_script('wabe-admin', 'wabeAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('wabe_admin'),
            ]);
        }
    }

    /**
     * 旧コード互換: 設定画面レンダリング
     *
     * @return void
     */
    public function render_settings_page()
    {
        if (!class_exists('WABE_Admin')) {
            wp_die(esc_html__('WABE_Admin class not found.', WABE_TEXTDOMAIN));
        }

        if ($this->admin === null) {
            $this->admin = new WABE_Admin();
        }

        if (method_exists($this->admin, 'settings_page')) {
            $this->admin->settings_page();
            return;
        }

        if (method_exists($this->admin, 'render_settings')) {
            $this->admin->render_settings();
            return;
        }

        wp_die(esc_html__('Settings page method not found.', WABE_TEXTDOMAIN));
    }

    /**
     * プラグイン有効化時
     *
     * @return void
     */
    public static function activate()
    {
        self::ensure_default_options();

        if (class_exists('WABE_Cron')) {
            WABE_Cron::activate();
        }

        if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'info')) {
            WABE_Logger::info('Plugin activated');
        }

        flush_rewrite_rules();
    }

    /**
     * プラグイン無効化時
     *
     * @return void
     */
    public static function deactivate()
    {
        if (class_exists('WABE_Cron')) {
            WABE_Cron::deactivate();
        }

        if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'info')) {
            WABE_Logger::info('Plugin deactivated');
        }

        flush_rewrite_rules();
    }

    /**
     * 初期オプション作成
     *
     * @return void
     */
    private static function ensure_default_options()
    {
        $options = get_option(WABE_OPTION, []);
        if (!is_array($options)) {
            $options = [];
        }

        $defaults = [
            'ai_provider'               => 'openai',
            'openai_api_key'            => '',
            'gemini_api_key'            => '',
            'openai_model'              => 'gpt-4.1',
            'gemini_model'              => 'gemini-2.5-flash',
            'generation_count'          => 1,
            'heading_count'             => 3,
            'tone'                      => 'standard',
            'post_status'               => 'draft',
            'weekly_posts'              => 1,
            'schedule_enabled'          => '0',
            'enable_featured_image'     => '0',
            'image_style'               => 'modern',
            'enable_seo'                => '0',
            'enable_internal_links'     => '0',
            'enable_external_links'     => '0',
            'enable_topic_prediction'   => '0',
            'enable_duplicate_check'    => '0',
            'enable_outline_generator'  => '0',
            'author_name'               => '',
            'site_context'              => '',
            'writing_rules'             => '',
            'seo_keyword'               => '',
            'internal_link_url'         => '',
            'external_link_url'         => '',
            'license_key'               => '',
            'topics'                    => [],
            'history'                   => [],
            'logs'                      => [],
            'plan'                      => 'free',
            'license_status'            => 'free',
            'license_checked_at'        => '',
            'license_expires_at'        => '',
            'license_customer_email'    => '',
        ];

        $merged = array_merge($defaults, $options);
        update_option(WABE_OPTION, $merged);
    }
}
