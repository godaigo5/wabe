<?php
if (!defined('ABSPATH')) exit;

class WABE_Admin
{
    private $options = [];
    public $next_post_date = '';

    public function __construct()
    {
        $this->options = get_option(WABE_OPTION, []);
        if (!is_array($this->options)) {
            $this->options = [];
        }

        add_action('admin_post_wabe_save_settings', [$this, 'handle_save_settings']);
        add_action('admin_post_wabe_save_topics', [$this, 'handle_save_topics']);
        add_action('admin_post_wabe_save_license_key', [$this, 'handle_save_license_key']);
        add_action('admin_post_wabe_generate_now', [$this, 'handle_generate_now']);
        add_action('admin_post_wabe_generate_predicted_topics', [$this, 'handle_generate_predicted_topics']);
        add_action('admin_post_wabe_clear_logs', [$this, 'handle_clear_logs']);
        add_action('admin_post_wabe_refresh_license', [$this, 'handle_refresh_license']);

        $this->next_post_date = $this->get_next_post_date();
    }

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

    private function guard()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', WABE_TEXTDOMAIN));
        }
    }

    private function reload_options()
    {
        $this->options = get_option(WABE_OPTION, []);
        if (!is_array($this->options)) {
            $this->options = [];
        }
    }

    public function settings_page()
    {
        $this->guard();
        $this->reload_options();

        if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'info')) {
            WABE_Logger::info('DEBUG: settings_page reached');
            WABE_Logger::info('DEBUG: option keys = ' . implode(',', array_keys($this->options)));
            WABE_Logger::info('DEBUG: gemini_api_key length = ' . strlen((string) ($this->options['gemini_api_key'] ?? '')));
            WABE_Logger::info('DEBUG: unsplash_access_key length = ' . strlen((string) ($this->options['unsplash_access_key'] ?? '')));
        }

        include WABE_PATH . 'admin/settings.php';
    }

    public function topics_page()
    {
        $this->guard();
        $this->reload_options();
        include WABE_PATH . 'admin/topics.php';
    }

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

    public function license_page()
    {
        $this->guard();
        $this->reload_options();
        $license = $this->get_license_data();
        include WABE_PATH . 'admin/license.php';
    }

    public function handle_save_settings()
    {
        $this->guard();
        check_admin_referer('wabe_save_settings');

        $posted = isset($_POST[WABE_OPTION]) && is_array($_POST[WABE_OPTION])
            ? wp_unslash($_POST[WABE_OPTION])
            : [];

        $old = get_option(WABE_OPTION, []);
        if (!is_array($old)) {
            $old = [];
        }

        $plan = $this->get_plan();
        $features = $this->get_plan_features();

        $new = $old;

        $new['ai_provider'] = in_array(($posted['ai_provider'] ?? 'openai'), ['openai', 'gemini'], true)
            ? sanitize_key($posted['ai_provider'])
            : 'openai';

        $new['openai_api_key'] = sanitize_text_field($posted['openai_api_key'] ?? '');
        $new['gemini_api_key'] = sanitize_text_field($posted['gemini_api_key'] ?? '');
        $new['pollinations_api_key'] = sanitize_text_field($posted['pollinations_api_key'] ?? '');

        // ここで Unsplash Access Key を保存
        $new['unsplash_access_key'] = sanitize_text_field($posted['unsplash_access_key'] ?? '');

        $new['openai_model'] = sanitize_text_field($posted['openai_model'] ?? 'gpt-4.1');
        $new['gemini_model'] = sanitize_text_field($posted['gemini_model'] ?? 'gemini-2.5-flash');
        $new['pollinations_image_model'] = sanitize_text_field($posted['pollinations_image_model'] ?? 'flux');

        $new['heading_count'] = max(1, min(
            (int) ($features['heading_count_max'] ?? 3),
            (int) ($posted['heading_count'] ?? 3)
        ));

        $allowed_tones = ['standard', 'professional', 'casual', 'friendly', 'formal'];
        $new['tone'] = in_array(($posted['tone'] ?? 'standard'), $allowed_tones, true)
            ? sanitize_key($posted['tone'])
            : 'standard';

        $allowed_detail_levels = ['low', 'medium', 'high'];
        $new['detail_level'] = in_array(($posted['detail_level'] ?? 'medium'), $allowed_detail_levels, true)
            ? sanitize_key($posted['detail_level'])
            : 'medium';

        $allowed_quality = ['standard', 'high'];
        $new['generation_quality'] = in_array(($posted['generation_quality'] ?? 'high'), $allowed_quality, true)
            ? sanitize_key($posted['generation_quality'])
            : 'high';

        $allowed_styles = ['modern', 'business', 'blog', 'tech', 'luxury', 'natural'];
        $new['image_style'] = in_array(($posted['image_style'] ?? 'modern'), $allowed_styles, true)
            ? sanitize_key($posted['image_style'])
            : 'modern';

        $new['author_name'] = sanitize_text_field($posted['author_name'] ?? '');

        $new['site_context'] = isset($posted['site_context']) ? (string) $posted['site_context'] : '';
        $new['writing_rules'] = isset($posted['writing_rules']) ? (string) $posted['writing_rules'] : '';

        $new['weekly_posts'] = max(1, min(
            (int) ($features['weekly_posts_max'] ?? 1),
            (int) ($posted['weekly_posts'] ?? 1)
        ));

        $requested_status = sanitize_key($posted['post_status'] ?? 'draft');
        if ($requested_status === 'publish' && !empty($features['can_publish'])) {
            $new['post_status'] = 'publish';
        } else {
            $new['post_status'] = 'draft';
        }

        $new['plan'] = $plan;

        $new['schedule_enabled'] = !empty($posted['schedule_enabled']) ? 1 : 0;

        $new['enable_featured_image'] = (!empty($posted['enable_featured_image']) && !empty($features['can_use_images'])) ? 1 : 0;
        $new['enable_inline_unsplash'] = (!empty($posted['enable_inline_unsplash']) && !empty($features['can_use_images'])) ? 1 : 0;

        $new['enable_seo'] = (!empty($posted['enable_seo']) && !empty($features['can_use_seo'])) ? 1 : 0;
        $new['enable_internal_links'] = (!empty($posted['enable_internal_links']) && !empty($features['can_use_internal_links'])) ? 1 : 0;
        $new['enable_external_links'] = (!empty($posted['enable_external_links']) && !empty($features['can_use_external_links'])) ? 1 : 0;
        $new['enable_topic_prediction'] = (!empty($posted['enable_topic_prediction']) && !empty($features['can_use_topic_prediction'])) ? 1 : 0;
        $new['enable_duplicate_check'] = (!empty($posted['enable_duplicate_check']) && !empty($features['can_use_duplicate_check'])) ? 1 : 0;
        $new['enable_outline_generator'] = (!empty($posted['enable_outline_generator']) && !empty($features['can_use_outline_generator'])) ? 1 : 0;

        update_option(WABE_OPTION, $new);
        $this->options = $new;

        if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'info')) {
            WABE_Logger::info('Settings saved.');
            WABE_Logger::info('DEBUG: option keys = ' . implode(',', array_keys($new)));
            WABE_Logger::info('DEBUG: gemini_api_key length = ' . strlen((string) ($new['gemini_api_key'] ?? '')));
            WABE_Logger::info('DEBUG: unsplash_access_key length = ' . strlen((string) ($new['unsplash_access_key'] ?? '')));
        }

        if (class_exists('WABE_Cron') && method_exists('WABE_Cron', 'reschedule')) {
            WABE_Cron::reschedule();
        } else {
            wp_clear_scheduled_hook('wabe_cron_generate');
            if (!empty($new['schedule_enabled'])) {
                wp_schedule_event(time() + 300, 'hourly', 'wabe_cron_generate');
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=wabe&updated=1'));
        exit;
    }

    public function handle_save_topics()
    {
        $this->guard();

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'wabe_save_topics')) {
            wp_die(esc_html__('Security check failed.', WABE_TEXTDOMAIN));
        }

        $this->reload_options();

        $posted_topics = isset($_POST['topics']) && is_array($_POST['topics'])
            ? wp_unslash($_POST['topics'])
            : [];

        $topics = [];
        $default_tone = sanitize_key($this->options['tone'] ?? 'standard');

        foreach ($posted_topics as $topic_text) {
            $topic_text = trim(wp_strip_all_tags((string) $topic_text));

            if ($topic_text === '') {
                continue;
            }

            $topics[] = [
                'topic' => $topic_text,
                'tone'  => $default_tone,
                'style' => 'standard',
            ];
        }

        $topics = array_slice(array_values($topics), 0, 10);
        $this->options['topics'] = $topics;

        if (!isset($this->options['history']) || !is_array($this->options['history'])) {
            $this->options['history'] = [];
        }

        update_option(WABE_OPTION, $this->options);

        wp_safe_redirect(admin_url('admin.php?page=wabe-topics&updated=1'));
        exit;
    }

    public function handle_save_license_key()
    {
        $this->guard();

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'wabe_save_license_key')) {
            wp_die(esc_html__('Security check failed.', WABE_TEXTDOMAIN));
        }

        $license_key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';

        $this->options['license_key'] = $license_key;
        update_option(WABE_OPTION, $this->options);

        if (class_exists('WABE_License')) {
            $license = new WABE_License();
            if (class_exists('WABE_License') && method_exists('WABE_License', 'clear_cache')) {
                WABE_License::clear_cache();
            }
            if (class_exists('WABE_License') && method_exists('WABE_License', 'sync')) {
                WABE_License::sync(true);
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=wabe-license&updated=1'));
        exit;
    }

    public function handle_generate_now()
    {
        $this->guard();

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'wabe_generate_now')) {
            wp_die(esc_html__('Security check failed.', WABE_TEXTDOMAIN));
        }

        if (class_exists('WABE_Generator')) {
            $generator = new WABE_Generator();
            if (method_exists($generator, 'run')) {
                $generator->run();
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=wabe&generated=1'));
        exit;
    }

    public function handle_generate_predicted_topics()
    {
        $this->guard();

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'wabe_generate_predicted_topics')) {
            wp_die(esc_html__('Security check failed.', WABE_TEXTDOMAIN));
        }

        if (class_exists('WABE_Topic_Generator')) {
            $topic_generator = new WABE_Topic_Generator();
            if (class_exists('WABE_Topic_Generator') && method_exists('WABE_Topic_Generator', 'append_predicted_topics')) {
                WABE_Topic_Generator::append_predicted_topics(5);
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=wabe-topics&predicted=1'));
        exit;
    }

    public function handle_clear_logs()
    {
        $this->guard();

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'wabe_clear_logs')) {
            wp_die(esc_html__('Security check failed.', WABE_TEXTDOMAIN));
        }

        if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'clear_logs')) {
            WABE_Logger::clear_logs();
        }

        wp_safe_redirect(admin_url('admin.php?page=wabe-logs&cleared=1'));
        exit;
    }

    public function handle_refresh_license()
    {
        $this->guard();

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'wabe_refresh_license')) {
            wp_die(esc_html__('Security check failed.', WABE_TEXTDOMAIN));
        }

        if (class_exists('WABE_License')) {
            if (method_exists('WABE_License', 'clear_cache')) {
                WABE_License::clear_cache();
            }

            if (method_exists('WABE_License', 'sync')) {
                $result = WABE_License::sync(true);

                if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'info')) {
                    WABE_Logger::info('License refresh result: ' . wp_json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                }
            }
        }

        $this->reload_options();

        wp_safe_redirect(admin_url('admin.php?page=wabe-license&refreshed=1'));
        exit;
    }

    public function get_plan()
    {
        if (class_exists('WABE_License') && method_exists('WABE_License', 'get_plan')) {
            $plan = WABE_License::get_plan();

            if (is_string($plan) && $plan !== '') {
                return sanitize_key($plan);
            }
        }

        return sanitize_key($this->options['plan'] ?? 'free');
    }

    public function get_plan_label($plan)
    {
        $plan = sanitize_key((string) $plan);

        switch ($plan) {
            case 'advanced':
                return 'Advanced';
            case 'pro':
                return 'Pro';
            case 'free':
            default:
                return 'Free';
        }
    }

    public function get_plan_features()
    {
        $plan = $this->get_plan();

        $matrix = [
            'free' => [
                'weekly_posts_max'            => 1,
                'heading_count_max'           => 3,
                'can_publish'                 => true,
                'can_use_images'              => true,
                'can_use_seo'                 => false,
                'can_use_internal_links'      => false,
                'can_use_external_links'      => false,
                'can_use_topic_prediction'    => false,
                'can_use_duplicate_check'     => false,
                'can_use_outline_generator'   => false,
            ],
            'advanced' => [
                'weekly_posts_max'            => 7,
                'heading_count_max'           => 6,
                'can_publish'                 => true,
                'can_use_images'              => true,
                'can_use_seo'                 => true,
                'can_use_internal_links'      => true,
                'can_use_external_links'      => true,
                'can_use_topic_prediction'    => true,
                'can_use_duplicate_check'     => true,
                'can_use_outline_generator'   => true,
            ],
            'pro' => [
                'weekly_posts_max'            => 9999,
                'heading_count_max'           => 6,
                'can_publish'                 => true,
                'can_use_images'              => true,
                'can_use_seo'                 => true,
                'can_use_internal_links'      => true,
                'can_use_external_links'      => true,
                'can_use_topic_prediction'    => true,
                'can_use_duplicate_check'     => true,
                'can_use_outline_generator'   => true,
            ],
        ];

        return $matrix[$plan] ?? $matrix['free'];
    }

    public function get_license_data()
    {
        $opt = get_option(WABE_OPTION, []);
        if (!is_array($opt)) {
            $opt = [];
        }

        $plan = sanitize_key($opt['plan'] ?? 'free');

        if (class_exists('WABE_License') && method_exists('WABE_License', 'get_cached_license_data')) {
            $cached = WABE_License::get_cached_license_data();

            if (is_array($cached) && !empty($cached)) {
                return [
                    'status'         => sanitize_text_field($cached['status'] ?? ($opt['license_status'] ?? 'inactive')),
                    'plan'           => sanitize_key($cached['plan'] ?? $plan),
                    'checked_at'     => sanitize_text_field($cached['checked_at'] ?? ($opt['license_checked_at'] ?? '')),
                    'expires_at'     => sanitize_text_field($cached['expires_at'] ?? ($opt['license_expires_at'] ?? '')),
                    'customer_email' => sanitize_text_field($cached['customer_email'] ?? ($opt['license_customer_email'] ?? '')),
                    'license_key'    => sanitize_text_field($cached['license_key'] ?? ($opt['license_key'] ?? '')),
                    'message'        => sanitize_text_field($cached['message'] ?? ''),
                ];
            }
        }

        return [
            'status'         => sanitize_text_field($opt['license_status'] ?? 'inactive'),
            'plan'           => $plan,
            'checked_at'     => sanitize_text_field($opt['license_checked_at'] ?? ''),
            'expires_at'     => sanitize_text_field($opt['license_expires_at'] ?? ''),
            'customer_email' => sanitize_text_field($opt['license_customer_email'] ?? ''),
            'license_key'    => sanitize_text_field($opt['license_key'] ?? ''),
            'message'        => '',
        ];
    }

    public function is_ready_to_post()
    {
        $provider = sanitize_key($this->options['ai_provider'] ?? 'openai');

        if ($provider === 'gemini') {
            return !empty($this->options['gemini_api_key']);
        }

        return !empty($this->options['openai_api_key']);
    }

    public function mask_api_key($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $len = strlen($value);
        if ($len <= 10) {
            return str_repeat('*', $len);
        }

        $start = substr($value, 0, 6);
        $end = substr($value, -6);
        return $start . str_repeat('*', max(4, $len - 12)) . $end;
    }

    private function get_next_post_date()
    {
        $timestamp = wp_next_scheduled('wabe_cron_generate');
        if (!$timestamp) {
            return '';
        }

        return wp_date('Y-m-d H:i:s', $timestamp);
    }

    private function get_logs_fallback()
    {
        if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'get_logs')) {
            return WABE_Logger::get_logs();
        }
        return [];
    }
}
