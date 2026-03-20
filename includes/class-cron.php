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
        $weekly_posts = self::get_weekly_posts();
        $interval = self::get_interval_seconds($weekly_posts);

        $schedules['wabe_dynamic'] = [
            'interval' => $interval,
            'display'  => __('WP AI Blog Engine Dynamic Schedule', WABE_TEXTDOMAIN),
        ];

        return $schedules;
    }

    public static function maybe_schedule()
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + 300, 'wabe_dynamic', self::HOOK);
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
            $result = $generator->run();

            if ($result) {
                WABE_Logger::info('Cron: generation completed');
            } else {
                WABE_Logger::warning('Cron: generation skipped or failed');
            }
        } catch (Throwable $e) {
            WABE_Logger::error('Cron Error: ' . $e->getMessage());
        }
    }

    private static function get_weekly_posts()
    {
        $options = get_option(WABE_OPTION, []);
        $weekly_posts = intval($options['weekly_posts'] ?? 1);

        if ($weekly_posts < 1) {
            $weekly_posts = 1;
        }

        if ($weekly_posts > 7) {
            $weekly_posts = 7;
        }

        return $weekly_posts;
    }

    private static function get_interval_seconds($weekly_posts)
    {
        $week_seconds = 7 * DAY_IN_SECONDS;
        $interval = (int) floor($week_seconds / max(1, $weekly_posts));

        if ($interval < HOUR_IN_SECONDS) {
            $interval = HOUR_IN_SECONDS;
        }

        return $interval;
    }
}
