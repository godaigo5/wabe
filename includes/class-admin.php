<?php
if (!defined('ABSPATH')) exit;
class WABE_Admin
{
    public $options = [];
    public $next_post_date = '';
    public function menu()
    {
        add_menu_page(__('WP AI Blog Engine', WABE_TEXTDOMAIN), __('WP AI Blog Engine', WABE_TEXTDOMAIN), 'manage_options', 'wabe', [$this, 'settings_page'], 'dashicons-edit-page', 26);
        add_submenu_page('wabe', __('Settings', WABE_TEXTDOMAIN), __('Settings', WABE_TEXTDOMAIN), 'manage_options', 'wabe', [$this, 'settings_page']);
        add_submenu_page('wabe', __('Topics', WABE_TEXTDOMAIN), __('Topics', WABE_TEXTDOMAIN), 'manage_options', 'wabe-topics', [$this, 'topics_page']);
        add_submenu_page('wabe', __('Logs', WABE_TEXTDOMAIN), __('Logs', WABE_TEXTDOMAIN), 'manage_options', 'wabe-logs', [$this, 'logs_page']);
        add_submenu_page('wabe', __('License', WABE_TEXTDOMAIN), __('License', WABE_TEXTDOMAIN), 'manage_options', 'wabe-license', [$this, 'license_page']);
    }
    private function guard()
    {
        if (!current_user_can('manage_options')) wp_die(__('Permission denied.', WABE_TEXTDOMAIN));
    }
    public function settings_page()
    {
        $this->guard();
        $this->options = get_option(WABE_OPTION, []);
        $n = wp_next_scheduled('wabe_generate_event');
        $this->next_post_date = $n ? wp_date('Y-m-d H:i', $n) : __('Not scheduled', WABE_TEXTDOMAIN);
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
        $logs = WABE_Logger::get_logs();
        include WABE_PATH . 'admin/logs.php';
    }
    public function license_page()
    {
        $this->guard();
        $this->options = get_option(WABE_OPTION, []);
		WABE_License::clear_cache();
        $license = WABE_License::sync(false);
        include WABE_PATH . 'admin/license.php';
    }
    public function save_settings()
    {
        $this->guard();
        check_admin_referer('wabe_save_settings', 'wabe_settings_nonce');
        $o = get_option(WABE_OPTION, []);
        $old = intval($o['weekly_posts'] ?? 1);
        $api_key = sanitize_text_field(wp_unslash($_POST['wabe_api_key'] ?? ''));
        if ($api_key !== '' && strpos($api_key, '***') === false) $o['api_key'] = $api_key;
        $o['generation_count'] = max(1, min(WABE_Plan::title_count_max(), intval($_POST['wabe_generation_count'] ?? 1)));
        $tone = sanitize_text_field(wp_unslash($_POST['wabe_tone'] ?? 'standard'));
        $o['tone'] = in_array($tone, ['standard', 'polite', 'casual'], true) ? $tone : 'standard';
        $post_status = sanitize_text_field(wp_unslash($_POST['wabe_post_status'] ?? 'draft'));
        $o['post_status'] = (WABE_Plan::can_publish() && $post_status === 'publish') ? 'publish' : 'draft';
        $o['weekly_posts'] = max(1, min(WABE_Plan::weekly_posts_max(), intval($_POST['wabe_weekly_posts'] ?? 1)));
        update_option(WABE_OPTION, $o);
        if ($old !== intval($o['weekly_posts'])) WABE_Cron::reschedule();
        wp_safe_redirect(add_query_arg(['page' => 'wabe', 'wabe_message' => rawurlencode(__('Saved', WABE_TEXTDOMAIN))], admin_url('admin.php')));
        exit;
    }
    public function save_topics()
    {
        $this->guard();
        check_admin_referer('wabe_save_topics', 'wabe_topics_nonce');
        $o = get_option(WABE_OPTION, []);
        $topics = [];
        if (!empty($_POST['topics']) && is_array($_POST['topics'])) foreach (wp_unslash($_POST['topics']) as $row) {
            $topic = sanitize_text_field($row['topic'] ?? '');
            $style = sanitize_text_field($row['style'] ?? '');
            $tone = sanitize_text_field($row['tone'] ?? 'standard');
            if ($topic === '' && $style === '') continue;
            $topics[] = ['topic' => $topic, 'style' => $style, 'tone' => in_array($tone, ['standard', 'polite', 'casual'], true) ? $tone : 'standard'];
        }
        $o['topics'] = $topics;
        update_option(WABE_OPTION, $o);
        !empty($topics) ? WABE_Cron::reschedule() : WABE_Cron::deactivate();
        wp_safe_redirect(add_query_arg(['page' => 'wabe-topics', 'saved' => 1], admin_url('admin.php')));
        exit;
    }
    public function manual_generate()
    {
        $this->guard();
        check_admin_referer('wabe_manual_generate');
        (new WABE_Generator())->run();
        wp_safe_redirect(add_query_arg(['page' => 'wabe', 'wabe_message' => rawurlencode(__('Manual generation completed', WABE_TEXTDOMAIN))], admin_url('admin.php')));
        exit;
    }
    public function clear_logs()
    {
        $this->guard();
        check_admin_referer('wabe_clear_logs', 'wabe_clear_logs_nonce');
        WABE_Logger::clear();
        wp_safe_redirect(add_query_arg(['page' => 'wabe-logs', 'cleared' => 1], admin_url('admin.php')));
        exit;
    }
    public function save_license()
    {
        $this->guard();
        check_admin_referer('wabe_save_license', 'wabe_license_nonce');
        $o = get_option(WABE_OPTION, []);
        $o['license_key'] = sanitize_text_field(wp_unslash($_POST['wabe_license_key'] ?? ''));
        update_option(WABE_OPTION, $o);
        delete_transient('wabe_license_check');
        WABE_License::sync(true);
        wp_safe_redirect(add_query_arg(['page' => 'wabe-license', 'saved' => 1], admin_url('admin.php')));
        exit;
    }
    public function generate_topics()
    {
        $this->guard();
        check_admin_referer('wabe_generate_topics', 'wabe_generate_topics_nonce');
        if (!WABE_Plan::can_use_topic_generator()) {
            wp_safe_redirect(add_query_arg(['page' => 'wabe-topics', 'wabe_error' => rawurlencode(__('This feature is Pro only.', WABE_TEXTDOMAIN))], admin_url('admin.php')));
            exit;
        }
        $keyword = sanitize_text_field(wp_unslash($_POST['wabe_seed_keyword'] ?? ''));
        $gen = WABE_Topic_Generator::generate($keyword, 5);
        $o = get_option(WABE_OPTION, []);
        $o['topics'] = array_merge($o['topics'] ?? [], $gen);
        update_option(WABE_OPTION, $o);
        WABE_Cron::reschedule();
        wp_safe_redirect(add_query_arg(['page' => 'wabe-topics', 'generated' => count($gen)], admin_url('admin.php')));
        exit;
    }
    public function is_ready_to_post()
    {
        foreach ((get_option(WABE_OPTION, [])['topics'] ?? []) as $t) {
            if (!empty($t['topic'])) return true;
        }
        return false;
    }
}
