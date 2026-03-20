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
            [$this, 'render_admin_page'],
            'dashicons-edit',
            26
        );
    }

    public function render_admin_page()
    {
        $admin = new WABE_Admin();
        $admin->render();
    }

    public function maybe_create_default_options()
    {
        $defaults = [
            // ===== AI設定 =====
            'ai_provider'    => 'openai',

            'openai_api_key' => '',
            'gemini_api_key' => '',

            'openai_model'   => 'gpt-4.1-mini',
            'gemini_model'   => 'gemini-2.5-flash',

            // ===== 生成設定 =====
            'generation_count' => 1,
            'tone'             => 'standard',
            'post_status'      => 'draft',
            'weekly_posts'     => 1,

            // ===== データ =====
            'topics'  => [],
            'history' => [],

            // ===== ライセンス =====
            'license_key'         => '',
            'license_data'        => [],
            'license_checked_at'  => '',
        ];

        $current = get_option(WABE_OPTION, false);

        if ($current === false) {
            add_option(WABE_OPTION, $defaults);
            return;
        }

        if (is_array($current)) {
            // 不足キーだけ補完（完全上書きはしない）
            $current = wp_parse_args($current, $defaults);
            update_option(WABE_OPTION, $current);
        }
    }
}
