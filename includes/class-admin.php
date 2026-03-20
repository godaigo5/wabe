<?php
if (!defined('ABSPATH')) exit;

class WABE_Admin
{
    public $options = [];
    public $next_post_date = '';

    public function __construct()
    {
        $this->options = get_option(WABE_OPTION, []);
        $this->next_post_date = $this->get_next_post_date();

        add_action('admin_post_wabe_save_settings', [$this, 'save_settings']);
        add_action('admin_post_wabe_manual_generate', [$this, 'manual_generate']);
        add_action('admin_post_wabe_save_topics', [$this, 'save_topics']);
        add_action('admin_post_wabe_save_license', [$this, 'save_license']);
        add_action('admin_post_wabe_sync_license', [$this, 'sync_license']);
    }

    public function render_settings()
    {
        $this->guard();
        $this->options = get_option(WABE_OPTION, []);
        $this->next_post_date = $this->get_next_post_date();
        require WABE_PATH . 'admin/settings.php';
    }

    public function render_topics()
    {
        $this->guard();
        $this->options = get_option(WABE_OPTION, []);
        require WABE_PATH . 'admin/topics.php';
    }

    public function render_logs()
    {
        $this->guard();
        $this->options = get_option(WABE_OPTION, []);

        if (file_exists(WABE_PATH . 'admin/logs.php')) {
            require WABE_PATH . 'admin/logs.php';
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Logs', WABE_TEXTDOMAIN) . '</h1>';
        echo '<div class="card" style="padding:16px;">';
        echo '<p>' . esc_html__('admin/logs.php was not found.', WABE_TEXTDOMAIN) . '</p>';
        echo '</div>';
        echo '</div>';
    }

    public function render_license()
    {
        $this->guard();
        $this->options = get_option(WABE_OPTION, []);

        if (file_exists(WABE_PATH . 'admin/license.php')) {
            require WABE_PATH . 'admin/license.php';
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('License', WABE_TEXTDOMAIN) . '</h1>';
        echo '<div class="card" style="padding:16px;">';
        echo '<p>' . esc_html__('admin/license.php was not found.', WABE_TEXTDOMAIN) . '</p>';
        echo '</div>';
        echo '</div>';
    }

    public function save_settings()
    {
        $this->guard();
        check_admin_referer('wabe_save_settings', 'wabe_settings_nonce');

        $o   = get_option(WABE_OPTION, []);
        $old = intval($o['weekly_posts'] ?? 1);

        $provider = sanitize_key(wp_unslash($_POST['wabe_ai_provider'] ?? 'openai'));
        $o['ai_provider'] = in_array($provider, ['openai', 'gemini'], true) ? $provider : 'openai';

        $openai_api_key = sanitize_text_field(wp_unslash($_POST['wabe_openai_api_key'] ?? ''));
        if ($openai_api_key !== '' && strpos($openai_api_key, '***') === false) {
            $o['openai_api_key'] = $openai_api_key;
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

        $o['generation_count'] = max(1, min(WABE_Plan::title_count_max(), intval($_POST['wabe_generation_count'] ?? 1)));
        $o['heading_count'] = max(1, min(WABE_Plan::title_count_max(), intval($_POST['wabe_heading_count'] ?? 1)));

        $tone = sanitize_text_field(wp_unslash($_POST['wabe_tone'] ?? 'standard'));
        $o['tone'] = in_array($tone, ['standard', 'polite', 'casual'], true) ? $tone : 'standard';

        $post_status = sanitize_text_field(wp_unslash($_POST['wabe_post_status'] ?? 'draft'));
        $o['post_status'] = (WABE_Plan::can_publish() && $post_status === 'publish') ? 'publish' : 'draft';

        $o['weekly_posts'] = max(1, min(WABE_Plan::weekly_posts_max(), intval($_POST['wabe_weekly_posts'] ?? 1)));

        $allowed_image_styles = ['modern', 'business', 'blog', 'tech'];
        $image_style = sanitize_key(wp_unslash($_POST['wabe_image_style'] ?? 'modern'));
        $o['image_style'] = in_array($image_style, $allowed_image_styles, true) ? $image_style : 'modern';

        $o['enable_topic_prediction'] = !empty($_POST['wabe_enable_topic_prediction']) ? '1' : '0';
        $o['enable_duplicate_check']  = !empty($_POST['wabe_enable_duplicate_check']) ? '1' : '0';
        $o['enable_external_links']   = !empty($_POST['wabe_enable_external_links']) ? '1' : '0';
        $o['enable_featured_image']   = !empty($_POST['wabe_enable_featured_image']) ? '1' : '0';

        update_option(WABE_OPTION, $o);

        if ($old !== intval($o['weekly_posts'])) {
            WABE_Cron::reschedule();
        }

        $this->add_log('Settings saved');

        wp_safe_redirect(add_query_arg([
            'page' => 'wabe',
            'wabe_message' => rawurlencode(__('Saved', WABE_TEXTDOMAIN)),
        ], admin_url('admin.php')));
        exit;
    }

    public function manual_generate()
    {
        $this->guard();
        check_admin_referer('wabe_manual_generate');

        $generator = new WABE_Generator();
        $result = $generator->run();

        $message = $result
            ? __('Post generated successfully.', WABE_TEXTDOMAIN)
            : __('Failed to generate post.', WABE_TEXTDOMAIN);

        $this->add_log($result ? 'Manual generation executed' : 'Manual generation failed');

        wp_safe_redirect(add_query_arg([
            'page' => 'wabe',
            'wabe_message' => rawurlencode($message),
        ], admin_url('admin.php')));
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

        $this->add_log('Topics saved');

        wp_safe_redirect(add_query_arg([
            'page' => 'wabe-topics',
            'wabe_message' => rawurlencode(__('Topics saved.', WABE_TEXTDOMAIN)),
        ], admin_url('admin.php')));
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

        wp_safe_redirect(add_query_arg([
            'page' => 'wabe-license',
            'wabe_message' => rawurlencode(__('License key saved.', WABE_TEXTDOMAIN)),
        ], admin_url('admin.php')));
        exit;
    }

    public function sync_license()
    {
        $this->guard();
        check_admin_referer('wabe_sync_license', 'wabe_sync_license_nonce');

        $result = WABE_License::sync(true);
        $status = sanitize_text_field($result['status'] ?? 'unknown');

        $this->add_log('License synced: ' . $status);

        wp_safe_redirect(add_query_arg([
            'page' => 'wabe-license',
            'wabe_message' => rawurlencode(__('License synced.', WABE_TEXTDOMAIN)),
        ], admin_url('admin.php')));
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

    private function add_log($message)
    {
        $o = get_option(WABE_OPTION, []);
        $logs = $o['logs'] ?? [];

        if (!is_array($logs)) {
            $logs = [];
        }

        array_unshift($logs, [
            'date' => current_time('mysql'),
            'message' => sanitize_text_field($message),
        ]);

        $o['logs'] = array_slice($logs, 0, 100);
        update_option(WABE_OPTION, $o);
    }

    private function get_next_post_date()
    {
        $timestamp = wp_next_scheduled(WABE_Cron::HOOK);

        if (!$timestamp) {
            return __('Not scheduled', WABE_TEXTDOMAIN);
        }

        return wp_date('Y-m-d H:i:s', $timestamp);
    }

    private function guard()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', WABE_TEXTDOMAIN));
        }
    }
}
