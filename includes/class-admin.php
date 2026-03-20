<?php

if (!defined('ABSPATH')) exit;

class WABE_Admin
{
    public $options = [];
    public $next_post_date = '';

    public function menu()
    {
        add_menu_page(
            __('WP AI Blog Engine', WABE_TEXTDOMAIN),
            __('WP AI Blog Engine', WABE_TEXTDOMAIN),
            'manage_options',
            'wabe',
            [$this, 'settings_page'],
            'dashicons-edit-page',
            26
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

        add_action('admin_post_wabe_save_settings', [$this, 'save_settings']);
        add_action('admin_post_wabe_manual_generate', [$this, 'manual_generate']);
        add_action('admin_post_wabe_save_topics', [$this, 'save_topics']);
        add_action('admin_post_wabe_save_license', [$this, 'save_license']);
        add_action('admin_post_wabe_sync_license', [$this, 'sync_license']);
    }

    private function guard()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', WABE_TEXTDOMAIN));
        }
    }

    public function settings_page()
    {
        $this->guard();
        $this->options = get_option(WABE_OPTION, []);
        $this->next_post_date = $this->get_next_post_date();

        include WABE_PATH . 'admin/settings.php';
    }

    public function topics_page()
    {
        $this->guard();
        $this->options = get_option(WABE_OPTION, []);

        $topics = $this->options['topics'] ?? [];
        $history = $this->options['history'] ?? [];

        include WABE_PATH . 'admin/topics.php';
    }

    public function logs_page()
    {
        $this->guard();
        $logs = class_exists('WABE_Logger') && method_exists('WABE_Logger', 'get_logs')
            ? WABE_Logger::get_logs()
            : $this->get_logs();

        include WABE_PATH . 'admin/logs.php';
    }

    public function license_page()
    {
        $this->guard();
        $this->options = get_option(WABE_OPTION, []);

        if (class_exists('WABE_License') && method_exists('WABE_License', 'clear_cache')) {
            WABE_License::clear_cache();
        }

        $license = class_exists('WABE_License') && method_exists('WABE_License', 'sync')
            ? WABE_License::sync(false)
            : [];

        include WABE_PATH . 'admin/license.php';
    }

    public function save_settings()
    {
        $this->guard();
        check_admin_referer('wabe_save_settings', 'wabe_settings_nonce');

        $o   = get_option(WABE_OPTION, []);
        $old = (int)($o['weekly_posts'] ?? 1);

        $provider = sanitize_key(wp_unslash($_POST['wabe_ai_provider'] ?? 'openai'));
        $o['ai_provider'] = in_array($provider, ['openai', 'gemini'], true) ? $provider : 'openai';

        $openai_api_key = sanitize_text_field(wp_unslash($_POST['wabe_openai_api_key'] ?? ''));
        if ($openai_api_key !== '' && strpos($openai_api_key, '***') === false) {
            $o['openai_api_key'] = $openai_api_key;
        }

        if (empty($o['openai_api_key']) && !empty($o['api_key'])) {
            $o['openai_api_key'] = sanitize_text_field((string)$o['api_key']);
        }

        $gemini_api_key = sanitize_text_field(wp_unslash($_POST['wabe_gemini_api_key'] ?? ''));
        if ($gemini_api_key !== '' && strpos($gemini_api_key, '***') === false) {
            $o['gemini_api_key'] = $gemini_api_key;
        }

        $allowed_openai_models = [
            'gpt-4.1-mini',
            'gpt-4.1',
            'gpt-5-mini',
        ];
        $openai_model = sanitize_text_field(wp_unslash($_POST['wabe_openai_model'] ?? 'gpt-4.1'));
        $o['openai_model'] = in_array($openai_model, $allowed_openai_models, true) ? $openai_model : 'gpt-4.1';

        $allowed_gemini_models = [
            'gemini-2.5-flash',
            'gemini-2.5-pro',
        ];
        $gemini_model = sanitize_text_field(wp_unslash($_POST['wabe_gemini_model'] ?? 'gemini-2.5-flash'));
        $o['gemini_model'] = in_array($gemini_model, $allowed_gemini_models, true) ? $gemini_model : 'gemini-2.5-flash';

        $title_max = $this->plan_title_count_max();
        $weekly_max = $this->plan_weekly_posts_max();

        $o['generation_count'] = max(1, min($title_max, (int)($_POST['wabe_generation_count'] ?? 1)));
        $o['heading_count']    = max(1, min($title_max, (int)($_POST['wabe_heading_count'] ?? 3)));

        $tone = sanitize_text_field(wp_unslash($_POST['wabe_tone'] ?? 'standard'));
        $o['tone'] = in_array($tone, ['standard', 'polite', 'casual'], true) ? $tone : 'standard';

        $post_status = sanitize_text_field(wp_unslash($_POST['wabe_post_status'] ?? 'draft'));
        $o['post_status'] = ($this->plan_can_publish() && $post_status === 'publish') ? 'publish' : 'draft';

        $o['weekly_posts'] = max(1, min($weekly_max, (int)($_POST['wabe_weekly_posts'] ?? 1)));

        $allowed_image_styles = ['modern', 'business', 'blog', 'tech', 'luxury', 'natural'];
        $image_style = sanitize_key(wp_unslash($_POST['wabe_image_style'] ?? 'modern'));
        $o['image_style'] = in_array($image_style, $allowed_image_styles, true) ? $image_style : 'modern';

        $o['enable_featured_image'] = ($this->plan_can_use_images() && !empty($_POST['wabe_enable_featured_image'])) ? '1' : '0';
        $o['enable_seo']            = ($this->plan_can_use_seo() && !empty($_POST['wabe_enable_seo'])) ? '1' : '0';
        $o['enable_internal_links'] = ($this->plan_can_use_internal_links() && !empty($_POST['wabe_enable_internal_links'])) ? '1' : '0';
        $o['enable_external_links'] = ($this->plan_can_use_external_links() && !empty($_POST['wabe_enable_external_links'])) ? '1' : '0';
        $o['enable_topic_prediction'] = ($this->plan_can_use_topic_prediction() && !empty($_POST['wabe_enable_topic_prediction'])) ? '1' : '0';
        $o['enable_duplicate_check']  = ($this->plan_can_use_duplicate_check() && !empty($_POST['wabe_enable_duplicate_check'])) ? '1' : '0';
        $o['enable_outline_generator'] = ($this->plan_can_use_outline_generator() && !empty($_POST['wabe_enable_outline_generator'])) ? '1' : '0';

        $o['author_name'] = sanitize_text_field(wp_unslash($_POST['wabe_author_name'] ?? ''));
        $o['site_context'] = sanitize_textarea_field(wp_unslash($_POST['wabe_site_context'] ?? ''));
        $o['writing_rules'] = sanitize_textarea_field(wp_unslash($_POST['wabe_writing_rules'] ?? ''));

        $o['seo_keyword'] = sanitize_text_field(wp_unslash($_POST['wabe_seo_keyword'] ?? ''));
        $o['internal_link_url'] = esc_url_raw(wp_unslash($_POST['wabe_internal_link_url'] ?? ''));
        $o['external_link_url'] = esc_url_raw(wp_unslash($_POST['wabe_external_link_url'] ?? ''));

        $schedule_enabled = !empty($_POST['wabe_schedule_enabled']) ? '1' : '0';
        $o['schedule_enabled'] = $schedule_enabled;

        update_option(WABE_OPTION, $o);

        if (class_exists('WABE_Cron')) {
            if ($schedule_enabled === '1') {
                if ($old !== (int)$o['weekly_posts']) {
                    if (method_exists('WABE_Cron', 'reschedule')) {
                        WABE_Cron::reschedule();
                    }
                } else {
                    if (method_exists('WABE_Cron', 'reschedule')) {
                        WABE_Cron::reschedule();
                    }
                }
            } else {
                if (method_exists('WABE_Cron', 'deactivate')) {
                    WABE_Cron::deactivate();
                }
            }
        }

        $this->add_log('Settings saved');
        wp_safe_redirect(add_query_arg(
            [
                'page' => 'wabe',
                'wabe_message' => rawurlencode(__('Settings saved.', WABE_TEXTDOMAIN)),
            ],
            admin_url('admin.php')
        ));
        exit;
    }

    public function manual_generate()
    {
        $this->guard();
        check_admin_referer('wabe_manual_generate', 'wabe_manual_generate_nonce');

        $result = false;

        if (class_exists('WABE_Generator')) {
            $generator = new WABE_Generator();

            if (method_exists($generator, 'run')) {
                $result = $generator->run();
            } else {
                $this->add_log('Manual generation failed: run() method not found.');
            }
        } else {
            $this->add_log('Manual generation failed: WABE_Generator class not found.');
        }

        $message = $result
            ? __('Post generated successfully.', WABE_TEXTDOMAIN)
            : __('Failed to generate post.', WABE_TEXTDOMAIN);

        $this->add_log($result ? 'Manual generation executed' : 'Manual generation failed');

        wp_safe_redirect(add_query_arg(
            [
                'page' => 'wabe',
                'wabe_message' => rawurlencode($message),
            ],
            admin_url('admin.php')
        ));
        exit;
    }

    public function save_topics()
    {
        $this->guard();
        check_admin_referer('wabe_save_topics', 'wabe_topics_nonce');

        $o = get_option(WABE_OPTION, []);
        $raw_topics = $_POST['wabe_topics'] ?? [];
        $topics = [];

        if (is_array($raw_topics)) {
            foreach ($raw_topics as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $topic = sanitize_text_field(wp_unslash($row['topic'] ?? ''));
                $style = sanitize_text_field(wp_unslash($row['style'] ?? 'normal'));
                $tone  = sanitize_text_field(wp_unslash($row['tone'] ?? 'standard'));

                if ($topic === '') {
                    continue;
                }

                if (!in_array($tone, ['standard', 'polite', 'casual'], true)) {
                    $tone = 'standard';
                }

                if ($style === '') {
                    $style = 'normal';
                }

                $topics[] = [
                    'topic' => $topic,
                    'style' => $style,
                    'tone'  => $tone,
                ];
            }
        }

        $o['topics'] = array_slice($topics, 0, 10);
        update_option(WABE_OPTION, $o);

        if (class_exists('WABE_Cron')) {
            if (!empty($o['schedule_enabled']) && method_exists('WABE_Cron', 'reschedule')) {
                WABE_Cron::reschedule();
            } elseif (empty($o['schedule_enabled']) && method_exists('WABE_Cron', 'deactivate')) {
                WABE_Cron::deactivate();
            }
        }

        $this->add_log('Topics saved');

        wp_safe_redirect(add_query_arg(
            [
                'page' => 'wabe-topics',
                'wabe_message' => rawurlencode(__('Topics saved.', WABE_TEXTDOMAIN)),
            ],
            admin_url('admin.php')
        ));
        exit;
    }

    public function save_license()
    {
        $this->guard();
        check_admin_referer('wabe_save_license', 'wabe_license_nonce');

        $o = get_option(WABE_OPTION, []);
        $o['license_key'] = sanitize_text_field(wp_unslash($_POST['wabe_license_key'] ?? ''));
        update_option(WABE_OPTION, $o);

        $this->add_log('License key saved');

        wp_safe_redirect(add_query_arg(
            [
                'page' => 'wabe-license',
                'wabe_message' => rawurlencode(__('License key saved.', WABE_TEXTDOMAIN)),
            ],
            admin_url('admin.php')
        ));
        exit;
    }

    public function sync_license()
    {
        $this->guard();
        check_admin_referer('wabe_sync_license', 'wabe_sync_license_nonce');

        $status = 'unknown';

        if (class_exists('WABE_License') && method_exists('WABE_License', 'sync')) {
            $result = WABE_License::sync(true);
            $status = sanitize_text_field($result['status'] ?? 'unknown');
        }

        $this->add_log('License synced: ' . $status);

        wp_safe_redirect(add_query_arg(
            [
                'page' => 'wabe-license',
                'wabe_message' => rawurlencode(__('License synced.', WABE_TEXTDOMAIN)),
            ],
            admin_url('admin.php')
        ));
        exit;
    }

    public function is_ready_to_post()
    {
        $topics = $this->options['topics'] ?? [];
        return !empty($topics);
    }

    public function get_logs()
    {
        $this->options = get_option(WABE_OPTION, []);
        $logs = $this->options['logs'] ?? [];
        return is_array($logs) ? $logs : [];
    }

    public function mask_api_key($key)
    {
        $key = (string)$key;
        if ($key === '') {
            return '';
        }

        $len = mb_strlen($key);
        if ($len <= 8) {
            return str_repeat('*', max(8, $len));
        }

        return mb_substr($key, 0, 4) . str_repeat('*', max(8, $len - 8)) . mb_substr($key, -4);
    }

    public function get_plan()
    {
        if (class_exists('WABE_Plan') && method_exists('WABE_Plan', 'get_plan')) {
            return (string)WABE_Plan::get_plan();
        }

        if (class_exists('WABE_License') && method_exists('WABE_License', 'sync')) {
            $license = WABE_License::sync(false);
            return sanitize_key($license['plan'] ?? 'free');
        }

        return 'free';
    }

    public function get_license_data()
    {
        if (class_exists('WABE_License') && method_exists('WABE_License', 'sync')) {
            return WABE_License::sync(false);
        }

        return [];
    }

    public function get_plan_label($plan = '')
    {
        if ($plan === '') {
            $plan = $this->get_plan();
        }

        $labels = [
            'free'     => __('Free', WABE_TEXTDOMAIN),
            'advanced' => __('Advanced', WABE_TEXTDOMAIN),
            'pro'      => __('Pro', WABE_TEXTDOMAIN),
        ];

        return $labels[$plan] ?? ucfirst($plan);
    }

    public function plan_title_count_max()
    {
        if (class_exists('WABE_Plan') && method_exists('WABE_Plan', 'title_count_max')) {
            return max(1, (int)WABE_Plan::title_count_max());
        }

        $license = $this->get_license_data();
        return max(1, (int)($license['features']['title_count_max'] ?? 1));
    }

    public function plan_weekly_posts_max()
    {
        if (class_exists('WABE_Plan') && method_exists('WABE_Plan', 'weekly_posts_max')) {
            return max(1, (int)WABE_Plan::weekly_posts_max());
        }

        $license = $this->get_license_data();
        return max(1, (int)($license['features']['weekly_posts_max'] ?? 1));
    }

    public function plan_can_publish()
    {
        return $this->plan_bool('can_publish');
    }

    public function plan_can_use_images()
    {
        return $this->plan_bool('can_use_images');
    }

    public function plan_can_use_seo()
    {
        return $this->plan_bool('can_use_seo');
    }

    public function plan_can_use_internal_links()
    {
        return $this->plan_bool('can_use_internal_links');
    }

    public function plan_can_use_external_links()
    {
        return $this->plan_bool('can_use_external_links');
    }

    public function plan_can_use_topic_prediction()
    {
        return $this->plan_bool('can_use_topic_prediction');
    }

    public function plan_can_use_duplicate_check()
    {
        return $this->plan_bool('can_use_duplicate_check');
    }

    public function plan_can_use_outline_generator()
    {
        return $this->plan_bool('can_use_outline_generator');
    }

    public function field_lock_text($allowed, $upgrade_plan = 'Pro')
    {
        if ($allowed) {
            return '';
        }

        return sprintf(
            /* translators: %s: plan name */
            __('Locked. Upgrade to %s to use this feature.', WABE_TEXTDOMAIN),
            $upgrade_plan
        );
    }

    private function plan_bool($key)
    {
        if (class_exists('WABE_Plan') && method_exists('WABE_Plan', $key)) {
            return (bool)call_user_func(['WABE_Plan', $key]);
        }

        $license = $this->get_license_data();
        return !empty($license['features'][$key]);
    }

    private function add_log($message)
    {
        if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'info')) {
            WABE_Logger::info($message);
        }

        $o = get_option(WABE_OPTION, []);
        $logs = $o['logs'] ?? [];

        if (!is_array($logs)) {
            $logs = [];
        }

        array_unshift($logs, [
            'date'    => current_time('mysql'),
            'message' => sanitize_text_field($message),
        ]);

        $o['logs'] = array_slice($logs, 0, 100);
        update_option(WABE_OPTION, $o);
    }

    private function get_next_post_date()
    {
        if (!empty($this->options['schedule_enabled']) && class_exists('WABE_Cron')) {
            if (defined('WABE_Cron::HOOK')) {
                $timestamp = wp_next_scheduled(WABE_Cron::HOOK);
            } else {
                $timestamp = wp_next_scheduled('wabe_generate_event');
            }
        } else {
            $timestamp = wp_next_scheduled('wabe_generate_event');
            if (!$timestamp && class_exists('WABE_Cron') && defined('WABE_Cron::HOOK')) {
                $timestamp = wp_next_scheduled(WABE_Cron::HOOK);
            }
        }

        if (!$timestamp) {
            return __('Not scheduled', WABE_TEXTDOMAIN);
        }

        return wp_date('Y-m-d H:i:s', $timestamp);
    }

    public function render_settings()
    {
        $this->settings_page();
    }

    public function render_settings_page()
    {
        $this->settings_page();
    }
}
