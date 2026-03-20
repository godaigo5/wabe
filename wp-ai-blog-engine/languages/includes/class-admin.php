<?php
if (!defined('ABSPATH')) exit;

class WABE_Admin
{
    /** @var array */
    public $options = [];

    /** @var string */
    public $next_post_date = '';

    public function __construct()
    {
        $this->options = get_option(WABE_OPTION, []);
        if (!is_array($this->options)) {
            $this->options = [];
        }

        add_action('admin_post_wabe_save_settings', [$this, 'handle_save_settings']);
        add_action('admin_post_wabe_save_topics', [$this, 'handle_save_topics']);
        add_action('admin_post_wabe_generate_now', [$this, 'handle_generate_now']);
        add_action('admin_post_wabe_generate_predicted_topics', [$this, 'handle_generate_predicted_topics']);
        add_action('admin_post_wabe_clear_logs', [$this, 'handle_clear_logs']);
        add_action('admin_post_wabe_refresh_license', [$this, 'handle_refresh_license']);
    }

    /**
     * 管理画面メニュー
     *
     * @return void
     */
    public function menu()
    {
        add_menu_page(
            __('WP AI Blog Engine', WABE_TEXTDOMAIN),
            __('WP AI Blog Engine', WABE_TEXTDOMAIN),
            'manage_options',
            'wabe',
            [$this, 'settings_page'],
            'dashicons-welcome-write-blog',
            58
        );

        add_submenu_page(
            'wabe',
            __('Settings', WABE_TEXTDOMAIN),
            __('Settings', WABE_TEXTDOMAIN),
            'manage_options',
            'wabe',
            [$this, 'settings_page']
        );

        add_submenu_page(
            'wabe',
            __('Topics', WABE_TEXTDOMAIN),
            __('Topics', WABE_TEXTDOMAIN),
            'manage_options',
            'wabe-topics',
            [$this, 'topics_page']
        );

        add_submenu_page(
            'wabe',
            __('Logs', WABE_TEXTDOMAIN),
            __('Logs', WABE_TEXTDOMAIN),
            'manage_options',
            'wabe-logs',
            [$this, 'logs_page']
        );

        add_submenu_page(
            'wabe',
            __('License', WABE_TEXTDOMAIN),
            __('License', WABE_TEXTDOMAIN),
            'manage_options',
            'wabe-license',
            [$this, 'license_page']
        );
    }

    /**
     * 権限チェック
     *
     * @return void
     */
    private function guard()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', WABE_TEXTDOMAIN));
        }
    }

    /**
     * 設定ページ
     *
     * @return void
     */
    public function settings_page()
    {
        $this->guard();
        $this->reload_options();
        $this->next_post_date = $this->get_next_post_date();
        include WABE_PATH . 'admin/settings.php';
    }

    /**
     * 題材ページ
     *
     * @return void
     */
    public function topics_page()
    {
        $this->guard();
        $this->reload_options();
        include WABE_PATH . 'admin/topics.php';
    }

    /**
     * ログページ
     *
     * @return void
     */
    public function logs_page()
    {
        $this->guard();

        $logs = [];
        if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'get_logs')) {
            $logs = WABE_Logger::get_logs();
        } else {
            $logs = $this->get_logs_fallback();
        }

        include WABE_PATH . 'admin/logs.php';
    }

    /**
     * ライセンスページ
     *
     * @return void
     */
    public function license_page()
    {
        $this->guard();
        $this->reload_options();

        $license = $this->get_license_data();

        include WABE_PATH . 'admin/license.php';
    }

    /**
     * 設定保存
     *
     * @return void
     */
    public function handle_save_settings()
    {
        $this->guard();

        check_admin_referer('wabe_save_settings', 'wabe_settings_nonce');

        $current = get_option(WABE_OPTION, []);
        if (!is_array($current)) {
            $current = [];
        }

        $features = $this->get_plan_features();

        $weekly_posts_max  = max(1, (int)($features['weekly_posts_max'] ?? 1));
        $heading_count_max = max(1, (int)($features['heading_count_max'] ?? ($features['title_count_max'] ?? 1)));
        $can_publish       = !empty($features['can_publish']);
        $can_use_images    = !empty($features['can_use_images']);
        $can_use_seo       = !empty($features['can_use_seo']);
        $can_use_internal  = !empty($features['can_use_internal_links']);
        $can_use_external  = !empty($features['can_use_external_links']);
        $can_use_predict   = !empty($features['can_use_topic_prediction']);
        $can_use_duplicate = !empty($features['can_use_duplicate_check']);
        $can_use_outline   = !empty($features['can_use_outline_generator']);

        $ai_provider = isset($_POST['ai_provider']) ? sanitize_key(wp_unslash($_POST['ai_provider'])) : 'openai';
        if (!in_array($ai_provider, ['openai', 'gemini'], true)) {
            $ai_provider = 'openai';
        }

        $openai_model = isset($_POST['openai_model']) ? sanitize_text_field(wp_unslash($_POST['openai_model'])) : 'gpt-4.1';
        $gemini_model = isset($_POST['gemini_model']) ? sanitize_text_field(wp_unslash($_POST['gemini_model'])) : 'gemini-2.5-flash';

        $tone = isset($_POST['tone']) ? sanitize_key(wp_unslash($_POST['tone'])) : 'standard';
        if (!in_array($tone, ['standard', 'polite', 'casual'], true)) {
            $tone = 'standard';
        }

        $post_status = isset($_POST['post_status']) ? sanitize_key(wp_unslash($_POST['post_status'])) : 'draft';
        if (!in_array($post_status, ['draft', 'publish'], true)) {
            $post_status = 'draft';
        }
        if ($post_status === 'publish' && !$can_publish) {
            $post_status = 'draft';
        }

        $weekly_posts = isset($_POST['weekly_posts']) ? (int)$_POST['weekly_posts'] : 1;
        $weekly_posts = max(1, min($weekly_posts_max, $weekly_posts));

        $heading_count = isset($_POST['heading_count']) ? (int)$_POST['heading_count'] : 1;
        $heading_count = max(1, min($heading_count_max, $heading_count));

        $schedule_enabled = !empty($_POST['schedule_enabled']) ? '1' : '0';
        $enable_featured_image = (!empty($_POST['enable_featured_image']) && $can_use_images) ? '1' : '0';
        $enable_seo = (!empty($_POST['enable_seo']) && $can_use_seo) ? '1' : '0';
        $enable_internal_links = (!empty($_POST['enable_internal_links']) && $can_use_internal) ? '1' : '0';
        $enable_external_links = (!empty($_POST['enable_external_links']) && $can_use_external) ? '1' : '0';
        $enable_topic_prediction = (!empty($_POST['enable_topic_prediction']) && $can_use_predict) ? '1' : '0';
        $enable_duplicate_check = (!empty($_POST['enable_duplicate_check']) && $can_use_duplicate) ? '1' : '0';
        $enable_outline_generator = (!empty($_POST['enable_outline_generator']) && $can_use_outline) ? '1' : '0';

        $image_style = isset($_POST['image_style']) ? sanitize_key(wp_unslash($_POST['image_style'])) : 'modern';
        if (!in_array($image_style, ['modern', 'business', 'blog', 'tech', 'luxury', 'natural'], true)) {
            $image_style = 'modern';
        }

        $new = [
            'ai_provider'              => $ai_provider,
            'openai_api_key'           => $this->resolve_secret_field('openai_api_key', $current),
            'gemini_api_key'           => $this->resolve_secret_field('gemini_api_key', $current),
            'openai_model'             => $openai_model,
            'gemini_model'             => $gemini_model,
            'heading_count'            => $heading_count,
            'tone'                     => $tone,
            'post_status'              => $post_status,
            'weekly_posts'             => $weekly_posts,
            'schedule_enabled'         => $schedule_enabled,
            'enable_featured_image'    => $enable_featured_image,
            'image_style'              => $image_style,
            'enable_seo'               => $enable_seo,
            'enable_internal_links'    => $enable_internal_links,
            'enable_external_links'    => $enable_external_links,
            'enable_topic_prediction'  => $enable_topic_prediction,
            'enable_duplicate_check'   => $enable_duplicate_check,
            'enable_outline_generator' => $enable_outline_generator,
            'author_name'              => isset($_POST['author_name']) ? sanitize_text_field(wp_unslash($_POST['author_name'])) : '',
            'site_context'             => isset($_POST['site_context']) ? sanitize_textarea_field(wp_unslash($_POST['site_context'])) : '',
            'writing_rules'            => isset($_POST['writing_rules']) ? sanitize_textarea_field(wp_unslash($_POST['writing_rules'])) : '',
            'seo_keyword'              => isset($_POST['seo_keyword']) ? sanitize_text_field(wp_unslash($_POST['seo_keyword'])) : '',
            'internal_link_url'        => isset($_POST['internal_link_url']) ? esc_url_raw(wp_unslash($_POST['internal_link_url'])) : '',
            'external_link_url'        => isset($_POST['external_link_url']) ? esc_url_raw(wp_unslash($_POST['external_link_url'])) : '',
            'license_key'              => isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : ($current['license_key'] ?? ''),
        ];

        $merged = array_merge($current, $new);
        update_option(WABE_OPTION, $merged);

        $this->reschedule_cron($weekly_posts, $schedule_enabled === '1');

        if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'info')) {
            WABE_Logger::info('Settings saved.');
        }

        $this->redirect_with_message(
            admin_url('admin.php?page=wabe'),
            __('Settings saved.', WABE_TEXTDOMAIN)
        );
    }

    /**
     * 題材保存
     *
     * @return void
     */
    public function handle_save_topics()
    {
        $this->guard();

        check_admin_referer('wabe_save_topics', 'wabe_topics_nonce');

        $current = get_option(WABE_OPTION, []);
        if (!is_array($current)) {
            $current = [];
        }

        $raw_topics = isset($_POST['wabe_topics']) ? wp_unslash($_POST['wabe_topics']) : [];
        $topics = [];

        if (is_array($raw_topics)) {
            foreach ($raw_topics as $row) {
                $topic = sanitize_text_field($row['topic'] ?? '');
                $style = sanitize_key($row['style'] ?? 'normal');
                $tone  = sanitize_key($row['tone'] ?? 'standard');

                if ($topic === '') {
                    continue;
                }

                if (!in_array($style, ['normal', 'how-to', 'review', 'news', 'list'], true)) {
                    $style = 'normal';
                }

                if (!in_array($tone, ['standard', 'polite', 'casual'], true)) {
                    $tone = 'standard';
                }

                $topics[] = [
                    'topic' => $topic,
                    'style' => $style,
                    'tone'  => $tone,
                ];

                if (count($topics) >= 10) {
                    break;
                }
            }
        }

        $current['topics'] = $topics;
        update_option(WABE_OPTION, $current);

        if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'info')) {
            WABE_Logger::info('Topics saved. Count=' . count($topics));
        }

        $this->redirect_with_message(
            admin_url('admin.php?page=wabe-topics'),
            __('Topics saved.', WABE_TEXTDOMAIN)
        );
    }

    /**
     * 今すぐ生成
     *
     * @return void
     */
    public function handle_generate_now()
    {
        $this->guard();

        check_admin_referer('wabe_generate_now', 'wabe_generate_now_nonce');

        if (!class_exists('WABE_Generator')) {
            $this->redirect_with_message(
                admin_url('admin.php?page=wabe'),
                __('Generator class not found.', WABE_TEXTDOMAIN)
            );
        }

        $generator = new WABE_Generator();
        $post_id = $generator->run();

        if ($post_id) {
            $message = sprintf(__('Post generated successfully. Post ID: %d', WABE_TEXTDOMAIN), (int)$post_id);
        } else {
            $message = __('Generation finished, but no post was created.', WABE_TEXTDOMAIN);
        }

        $this->redirect_with_message(
            admin_url('admin.php?page=wabe'),
            $message
        );
    }

    /**
     * 題材予測して追加
     *
     * @return void
     */
    public function handle_generate_predicted_topics()
    {
        $this->guard();

        check_admin_referer('wabe_generate_predicted_topics', 'wabe_generate_predicted_topics_nonce');

        if (!class_exists('WABE_Plan') || !WABE_Plan::can_use_topic_prediction()) {
            $this->redirect_with_message(
                admin_url('admin.php?page=wabe-topics'),
                __('This feature is available on the Pro plan only.', WABE_TEXTDOMAIN)
            );
        }

        if (!class_exists('WABE_Topic_Generator')) {
            $this->redirect_with_message(
                admin_url('admin.php?page=wabe-topics'),
                __('Topic generator class not found.', WABE_TEXTDOMAIN)
            );
        }

        $count = WABE_Topic_Generator::append_predicted_topics(5);

        $this->redirect_with_message(
            admin_url('admin.php?page=wabe-topics'),
            sprintf(__('Predicted topics added: %d', WABE_TEXTDOMAIN), (int)$count)
        );
    }

    /**
     * ログ削除
     *
     * @return void
     */
    public function handle_clear_logs()
    {
        $this->guard();

        check_admin_referer('wabe_clear_logs', 'wabe_clear_logs_nonce');

        if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'clear_logs')) {
            WABE_Logger::clear_logs();
        } else {
            $current = get_option(WABE_OPTION, []);
            if (!is_array($current)) {
                $current = [];
            }
            $current['logs'] = [];
            update_option(WABE_OPTION, $current);
        }

        $this->redirect_with_message(
            admin_url('admin.php?page=wabe-logs'),
            __('Logs cleared.', WABE_TEXTDOMAIN)
        );
    }

    /**
     * ライセンス再同期
     *
     * @return void
     */
    public function handle_refresh_license()
    {
        $this->guard();

        check_admin_referer('wabe_refresh_license', 'wabe_refresh_license_nonce');

        if (class_exists('WABE_License') && method_exists('WABE_License', 'clear_cache')) {
            WABE_License::clear_cache();
        }

        if (class_exists('WABE_License') && method_exists('WABE_License', 'sync')) {
            WABE_License::sync(true);
        }

        $this->redirect_with_message(
            admin_url('admin.php?page=wabe-license'),
            __('License information refreshed.', WABE_TEXTDOMAIN)
        );
    }

    /**
     * オプション再読込
     *
     * @return void
     */
    private function reload_options()
    {
        $this->options = get_option(WABE_OPTION, []);
        if (!is_array($this->options)) {
            $this->options = [];
        }
    }

    /**
     * 次回実行日時取得
     *
     * @return string
     */
    public function get_next_post_date()
    {
        $timestamp = wp_next_scheduled('wabe_cron_generate');

        if (!$timestamp) {
            return __('Not scheduled', WABE_TEXTDOMAIN);
        }

        return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }

    /**
     * プラン取得
     *
     * @return string
     */
    public function get_plan()
    {
        if (class_exists('WABE_Plan') && method_exists('WABE_Plan', 'get_plan')) {
            return WABE_Plan::get_plan();
        }

        return sanitize_key($this->options['plan'] ?? 'free');
    }

    /**
     * プラン表示名
     *
     * @param string $plan
     * @return string
     */
    public function get_plan_label($plan = '')
    {
        if (class_exists('WABE_Plan') && method_exists('WABE_Plan', 'get_plan_label')) {
            return WABE_Plan::get_plan_label($plan);
        }

        $plan = $plan !== '' ? $plan : $this->get_plan();

        $map = [
            'free'     => 'Free',
            'advanced' => 'Advanced',
            'pro'      => 'Pro',
        ];

        return $map[$plan] ?? 'Free';
    }

    /**
     * ライセンスデータ取得
     *
     * @return array
     */
    public function get_license_data()
    {
        $license = [];

        if (class_exists('WABE_License') && method_exists('WABE_License', 'get_cached_license_data')) {
            $license = WABE_License::get_cached_license_data();
        }

        if (empty($license) && class_exists('WABE_License') && method_exists('WABE_License', 'sync')) {
            $license = WABE_License::sync(false);
        }

        if (!is_array($license)) {
            $license = [];
        }

        return $license;
    }

    /**
     * 現在プランのfeature取得
     *
     * @return array
     */
    public function get_plan_features()
    {
        if (class_exists('WABE_Plan') && method_exists('WABE_Plan', 'get_features')) {
            return WABE_Plan::get_features();
        }

        return [
            'weekly_posts_max'          => 1,
            'title_count_max'           => 1,
            'heading_count_max'         => 1,
            'can_publish'               => false,
            'can_use_seo'               => false,
            'can_use_images'            => false,
            'can_use_internal_links'    => false,
            'can_use_external_links'    => false,
            'can_use_topic_prediction'  => false,
            'can_use_duplicate_check'   => false,
            'can_use_outline_generator' => false,
        ];
    }

    public function plan_weekly_posts_max()
    {
        $f = $this->get_plan_features();
        return max(1, (int)($f['weekly_posts_max'] ?? 1));
    }

    public function plan_heading_count_max()
    {
        $f = $this->get_plan_features();
        return max(1, (int)($f['heading_count_max'] ?? ($f['title_count_max'] ?? 1)));
    }

    public function plan_title_count_max()
    {
        $f = $this->get_plan_features();
        return max(1, (int)($f['title_count_max'] ?? 1));
    }

    public function plan_can_publish()
    {
        $f = $this->get_plan_features();
        return !empty($f['can_publish']);
    }

    public function plan_can_use_images()
    {
        $f = $this->get_plan_features();
        return !empty($f['can_use_images']);
    }

    public function plan_can_use_seo()
    {
        $f = $this->get_plan_features();
        return !empty($f['can_use_seo']);
    }

    public function plan_can_use_internal_links()
    {
        $f = $this->get_plan_features();
        return !empty($f['can_use_internal_links']);
    }

    public function plan_can_use_external_links()
    {
        $f = $this->get_plan_features();
        return !empty($f['can_use_external_links']);
    }

    public function plan_can_use_topic_prediction()
    {
        $f = $this->get_plan_features();
        return !empty($f['can_use_topic_prediction']);
    }

    public function plan_can_use_duplicate_check()
    {
        $f = $this->get_plan_features();
        return !empty($f['can_use_duplicate_check']);
    }

    public function plan_can_use_outline_generator()
    {
        $f = $this->get_plan_features();
        return !empty($f['can_use_outline_generator']);
    }

    /**
     * 投稿可能状態か
     *
     * @return bool
     */
    public function is_ready_to_post()
    {
        $provider = sanitize_key($this->options['ai_provider'] ?? 'openai');

        if ($provider === 'gemini') {
            return !empty($this->options['gemini_api_key']);
        }

        return !empty($this->options['openai_api_key']);
    }

    /**
     * APIキーをマスク
     *
     * @param string $value
     * @return string
     */
    public function mask_api_key($value)
    {
        $value = trim((string)$value);
        $len = strlen($value);

        if ($len <= 8) {
            return str_repeat('*', max(4, $len));
        }

        return substr($value, 0, 4) . str_repeat('*', $len - 8) . substr($value, -4);
    }

    /**
     * 伏字入力考慮で秘密値保存
     *
     * @param string $key
     * @param array $current
     * @return string
     */
    private function resolve_secret_field($key, array $current)
    {
        $raw = isset($_POST[$key]) ? trim((string)wp_unslash($_POST[$key])) : '';

        if ($raw === '') {
            return (string)($current[$key] ?? '');
        }

        if (strpos($raw, '*') !== false) {
            return (string)($current[$key] ?? '');
        }

        return sanitize_text_field($raw);
    }

    /**
     * Cron再設定
     *
     * @param int  $weekly_posts
     * @param bool $enabled
     * @return void
     */
    private function reschedule_cron($weekly_posts, $enabled)
    {
        $hook = 'wabe_cron_generate';

        $next = wp_next_scheduled($hook);
        while ($next) {
            wp_unschedule_event($next, $hook);
            $next = wp_next_scheduled($hook);
        }

        if (!$enabled) {
            return;
        }

        $weekly_posts = max(1, min(7, (int)$weekly_posts));
        $interval = (int) floor(WEEK_IN_SECONDS / $weekly_posts);
        if ($interval < HOUR_IN_SECONDS) {
            $interval = HOUR_IN_SECONDS;
        }

        add_filter('cron_schedules', function ($schedules) use ($interval) {
            $schedules['wabe_dynamic_interval'] = [
                'interval' => $interval,
                'display'  => __('WP AI Blog Engine Dynamic Schedule', WABE_TEXTDOMAIN),
            ];
            return $schedules;
        });

        wp_schedule_event(time() + MINUTE_IN_SECONDS, 'wabe_dynamic_interval', $hook);
    }

    /**
     * ログfallback
     *
     * @return array
     */
    private function get_logs_fallback()
    {
        $this->reload_options();
        $logs = $this->options['logs'] ?? [];
        return is_array($logs) ? $logs : [];
    }

    /**
     * リダイレクト
     *
     * @param string $url
     * @param string $message
     * @return void
     */
    private function redirect_with_message($url, $message)
    {
        $url = add_query_arg('wabe_message', rawurlencode($message), $url);
        wp_safe_redirect($url);
        exit;
    }
}
