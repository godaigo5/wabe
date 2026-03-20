<?php
if (!defined('ABSPATH')) exit;
class WABE_Cron
{
    public function schedule($schedules)
    {
        $o = get_option(WABE_OPTION, []);
        $weekly = max(1, min(WABE_Plan::weekly_posts_max(), intval($o['weekly_posts'] ?? 1)));
        $schedules['wabe_schedule'] = ['interval' => intval((DAY_IN_SECONDS * 7) / $weekly), 'display' => __('WABE Auto Post', WABE_TEXTDOMAIN)];
        return $schedules;
    }
    static function register()
    {
        $o = get_option(WABE_OPTION, []);
        if (empty($o['topics'])) return;
        if (!wp_next_scheduled('wabe_generate_event')) wp_schedule_event(time(), 'wabe_schedule', 'wabe_generate_event');
    }
    static function reschedule()
    {
        wp_clear_scheduled_hook('wabe_generate_event');
        self::register();
    }
    static function deactivate()
    {
        wp_clear_scheduled_hook('wabe_generate_event');
    }
    static function execute()
    {
        if (get_transient('wabe_cron_lock')) return;
        set_transient('wabe_cron_lock', 1, 60);
        try {
            (new WABE_Generator())->run();
            $o = get_option(WABE_OPTION, []);
            if (empty($o['topics'])) self::deactivate();
        } catch (Throwable $e) {
            WABE_Logger::error('Cron error: ' . $e->getMessage());
        }
        delete_transient('wabe_cron_lock');
    }
}
