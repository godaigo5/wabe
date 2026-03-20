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
    }

    public function render()
    {
        require WABE_PATH . 'admin/settings.php';
    }

    private function guard()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', WABE_TEXTDOMAIN));
        }
    }

    public function is_ready_to_post()
    {
        $topics = $this->options['topics'] ?? [];
        return !empty($topics);
    }

    private function get_next_post_date()
    {
        $timestamp = wp_next_scheduled('wabe_cron_generate');
        if (!$timestamp) {
            return __('Not scheduled', WABE_TEXTDOMAIN);
        }

        return wp_date('Y-m-d H:i:s', $timestamp);
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

        $allowed_openai_models = ['gpt-4.1-mini', 'gpt-4.1', 'gpt-5-mini'];
        $openai_model = sanitize_text_field(wp_unslash($_POST['wabe_openai_model'] ?? 'gpt-4.1-mini'));
        $o['openai_model'] = in_array($openai_model, $allowed_openai_models, true) ? $openai_model : 'gpt-4.1-mini';

        $allowed_gemini_models = ['gemini-2.5-flash', 'gemini-2.5-pro'];
        $gemini_model = sanitize_text_field(wp_unslash($_POST['wabe_gemini_model'] ?? 'gemini-2.5-flash'));
        $o['gemini_model'] = in_array($gemini_model, $allowed_gemini_models, true) ? $gemini_model : 'gemini-2.5-flash';

        $o['generation_count'] = max(1, min(WABE_Plan::title_count_max(), intval($_POST['wabe_generation_count'] ?? 1)));

        $tone = sanitize_text_field(wp_unslash($_POST['wabe_tone'] ?? 'standard'));
        $o['tone'] = in_array($tone, ['standard', 'polite', 'casual'], true) ? $tone : 'standard';

        $post_status = sanitize_text_field(wp_unslash($_POST['wabe_post_status'] ?? 'draft'));
        $o['post_status'] = (WABE_Plan::can_publish() && $post_status === 'publish') ? 'publish' : 'draft';

        $o['weekly_posts'] = max(1, min(WABE_Plan::weekly_posts_max(), intval($_POST['wabe_weekly_posts'] ?? 1)));

        update_option(WABE_OPTION, $o);

        if ($old !== intval($o['weekly_posts'])) {
            WABE_Cron::reschedule();
        }

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

        wp_safe_redirect(add_query_arg([
            'page' => 'wabe',
            'wabe_message' => rawurlencode($message),
        ], admin_url('admin.php')));
        exit;
    }
}
