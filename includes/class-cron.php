<?php
if (!defined('ABSPATH')) exit;

class WABE_Cron
{
    const HOOK = 'wabe_cron_generate';

    public static function init()
    {
        add_filter('cron_schedules', [__CLASS__, 'add_schedules']);
        add_action(self::HOOK, [__CLASS__, 'run']);
        self::maybe_schedule();
    }

    public static function add_schedules($schedules)
    {
        if (!isset($schedules['wabe_hourly'])) {
            $schedules['wabe_hourly'] = [
                'interval' => HOUR_IN_SECONDS,
                'display'  => __('Once Hourly (WABE)', WABE_TEXTDOMAIN),
            ];
        }

        if (!isset($schedules['wabe_daily'])) {
            $schedules['wabe_daily'] = [
                'interval' => DAY_IN_SECONDS,
                'display'  => __('Once Daily (WABE)', WABE_TEXTDOMAIN),
            ];
        }

        return $schedules;
    }

    public static function maybe_schedule()
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + 300, 'wabe_daily', self::HOOK);
        }
    }

    public static function clear()
    {
        $timestamp = wp_next_scheduled(self::HOOK);

        while ($timestamp) {
            wp_unschedule_event($timestamp, self::HOOK);
            $timestamp = wp_next_scheduled(self::HOOK);
        }
    }

    public static function reschedule()
    {
        self::clear();
        self::maybe_schedule();
    }

    public static function run()
    {
        try {
            $generator = new WABE_Generator();
            $generator->run();
            WABE_Logger::info('Cron: generation completed');
        } catch (Throwable $e) {
            WABE_Logger::error('Cron Error: ' . $e->getMessage());
        }
    }
}
