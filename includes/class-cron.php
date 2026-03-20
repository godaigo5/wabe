<?php

if (!defined('ABSPATH')) exit;

class WABE_Cron
{
    const HOOK = 'wabe_generate_event';

    const LOCK_KEY              = 'wabe_cron_lock';
    const LAST_RUN_KEY          = 'wabe_cron_last_run';
    const LAST_SUCCESS_KEY      = 'wabe_cron_last_success';
    const LAST_RESULT_KEY       = 'wabe_cron_last_result';
    const LAST_ERROR_KEY        = 'wabe_cron_last_error';
    const LAST_POST_ID_KEY      = 'wabe_cron_last_post_id';
    const LAST_DURATION_KEY     = 'wabe_cron_last_duration';
    const NEXT_SCHEDULE_CACHE   = 'wabe_cron_next_schedule_cache';

    const LOCK_TTL = 15 * MINUTE_IN_SECONDS;

    /**
     * フック登録
     */
    public static function init()
    {
        add_filter('cron_schedules', [__CLASS__, 'add_schedule']);
        add_action(self::HOOK, [__CLASS__, 'run']);
    }

    /**
     * プラグイン有効化時などに呼ぶ
     */
    public static function activate()
    {
        self::init();
        self::reschedule();
    }

    /**
     * プラグイン停止時などに呼ぶ
     */
    public static function deactivate()
    {
        self::clear();
        self::delete_lock();
        delete_transient(self::NEXT_SCHEDULE_CACHE);
    }

    /**
     * 互換用
     */
    public static function clear()
    {
        $timestamp = wp_next_scheduled(self::HOOK);

        while ($timestamp) {
            wp_unschedule_event($timestamp, self::HOOK);
            $timestamp = wp_next_scheduled(self::HOOK);
        }

        delete_transient(self::NEXT_SCHEDULE_CACHE);
    }

    /**
     * 互換用
     */
    public static function maybe_schedule()
    {
        if (!self::is_schedule_enabled()) {
            WABE_Logger::info('Cron: scheduling skipped because schedule_enabled is off.');
            self::clear();
            return;
        }

        if (!self::has_topics()) {
            WABE_Logger::warning('Cron: scheduling skipped because no topics are registered.');
            self::clear();
            return;
        }

        $recurrence = 'wabe_dynamic';
        $next = wp_next_scheduled(self::HOOK);

        if (!$next) {
            $first_timestamp = time() + self::get_initial_delay_seconds();
            wp_schedule_event($first_timestamp, $recurrence, self::HOOK);

            $scheduled = wp_next_scheduled(self::HOOK);
            if ($scheduled) {
                set_transient(self::NEXT_SCHEDULE_CACHE, $scheduled, HOUR_IN_SECONDS);
            }

            WABE_Logger::info('Cron: scheduled at ' . wp_date('Y-m-d H:i:s', $first_timestamp));
        }
    }

    /**
     * 再スケジュール
     */
    public static function reschedule()
    {
        self::clear();
        self::maybe_schedule();
    }

    /**
     * Cron間隔追加
     */
    public static function add_schedule($schedules)
    {
        if (!is_array($schedules)) {
            $schedules = [];
        }

        $weekly_posts = self::get_weekly_posts();
        $interval     = self::get_interval_seconds($weekly_posts);

        $schedules['wabe_dynamic'] = [
            'interval' => $interval,
            'display'  => __('WP AI Blog Engine Dynamic Schedule', WABE_TEXTDOMAIN),
        ];

        return $schedules;
    }

    /**
     * 実行本体
     */
    public static function run()
    {
        $started_at = microtime(true);

        if (!self::is_schedule_enabled()) {
            self::record_result([
                'result'   => 'skipped',
                'message'  => 'Schedule is disabled.',
                'error'    => '',
                'post_id'  => 0,
                'duration' => self::elapsed($started_at),
            ]);

            WABE_Logger::info('Cron: skipped because schedule_enabled is off.');
            self::clear();
            return;
        }

        if (!self::has_topics()) {
            self::record_result([
                'result'   => 'skipped',
                'message'  => 'No topics registered.',
                'error'    => '',
                'post_id'  => 0,
                'duration' => self::elapsed($started_at),
            ]);

            WABE_Logger::warning('Cron: skipped because no topics are registered.');
            self::clear();
            return;
        }

        if (self::is_locked()) {
            self::record_result([
                'result'   => 'skipped_locked',
                'message'  => 'Skipped due to active execution lock.',
                'error'    => '',
                'post_id'  => 0,
                'duration' => self::elapsed($started_at),
            ]);

            WABE_Logger::warning('Cron: skipped because another process is running.');
            return;
        }

        self::set_lock();

        try {
            update_option(self::LAST_RUN_KEY, current_time('mysql'));

            if (!class_exists('WABE_Generator')) {
                throw new Exception('WABE_Generator class not found.');
            }

            $generator = new WABE_Generator();
            $result    = $generator->run();

            if ($result) {
                self::record_result([
                    'result'   => 'success',
                    'message'  => 'Generation completed successfully.',
                    'error'    => '',
                    'post_id'  => (int)$result,
                    'duration' => self::elapsed($started_at),
                ]);

                WABE_Logger::info('Cron: generation completed. post_id=' . (int)$result);
            } else {
                self::record_result([
                    'result'   => 'failed',
                    'message'  => 'Generation skipped or failed.',
                    'error'    => '',
                    'post_id'  => 0,
                    'duration' => self::elapsed($started_at),
                ]);

                WABE_Logger::warning('Cron: generation skipped or failed.');
            }
        } catch (Throwable $e) {
            self::record_result([
                'result'   => 'error',
                'message'  => 'Cron exception occurred.',
                'error'    => $e->getMessage(),
                'post_id'  => 0,
                'duration' => self::elapsed($started_at),
            ]);

            WABE_Logger::error('Cron Error: ' . $e->getMessage());
        } finally {
            self::delete_lock();

            $next = wp_next_scheduled(self::HOOK);
            if ($next) {
                set_transient(self::NEXT_SCHEDULE_CACHE, $next, HOUR_IN_SECONDS);
            }
        }
    }

    /**
     * 次回実行日時
     */
    public static function get_next_run_datetime()
    {
        $timestamp = wp_next_scheduled(self::HOOK);

        if (!$timestamp) {
            $cached = get_transient(self::NEXT_SCHEDULE_CACHE);
            if ($cached) {
                $timestamp = (int)$cached;
            }
        }

        if (!$timestamp) {
            return '';
        }

        return wp_date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * 管理画面表示用ステータス
     */
    public static function get_status()
    {
        return [
            'enabled'        => self::is_schedule_enabled(),
            'has_topics'     => self::has_topics(),
            'is_locked'      => self::is_locked(),
            'weekly_posts'   => self::get_weekly_posts(),
            'interval'       => self::get_interval_seconds(self::get_weekly_posts()),
            'next_run'       => self::get_next_run_datetime(),
            'last_run'       => get_option(self::LAST_RUN_KEY, ''),
            'last_success'   => get_option(self::LAST_SUCCESS_KEY, ''),
            'last_result'    => get_option(self::LAST_RESULT_KEY, ''),
            'last_error'     => get_option(self::LAST_ERROR_KEY, ''),
            'last_post_id'   => (int)get_option(self::LAST_POST_ID_KEY, 0),
            'last_duration'  => (float)get_option(self::LAST_DURATION_KEY, 0),
        ];
    }

    /**
     * 状態保存
     */
    private static function record_result(array $data)
    {
        $result   = sanitize_text_field($data['result'] ?? '');
        $message  = sanitize_text_field($data['message'] ?? '');
        $error    = sanitize_text_field($data['error'] ?? '');
        $post_id  = (int)($data['post_id'] ?? 0);
        $duration = (float)($data['duration'] ?? 0);

        update_option(self::LAST_RESULT_KEY, $result);
        update_option(self::LAST_ERROR_KEY, $error);
        update_option(self::LAST_POST_ID_KEY, $post_id);
        update_option(self::LAST_DURATION_KEY, $duration);

        if ($result === 'success') {
            update_option(self::LAST_SUCCESS_KEY, current_time('mysql'));
        }

        $options = get_option(WABE_OPTION, []);
        if (!is_array($options)) {
            $options = [];
        }

        $logs = $options['logs'] ?? [];
        if (!is_array($logs)) {
            $logs = [];
        }

        array_unshift($logs, [
            'date'     => current_time('mysql'),
            'message'  => $message,
            'result'   => $result,
            'error'    => $error,
            'post_id'  => $post_id,
            'duration' => $duration,
        ]);

        $options['logs'] = array_slice($logs, 0, 100);
        update_option(WABE_OPTION, $options);
    }

    /**
     * ロック中か
     */
    private static function is_locked()
    {
        $lock = get_transient(self::LOCK_KEY);

        if (!$lock || !is_array($lock)) {
            return false;
        }

        $locked_at = (int)($lock['timestamp'] ?? 0);
        if ($locked_at <= 0) {
            return false;
        }

        if ((time() - $locked_at) > self::LOCK_TTL) {
            self::delete_lock();
            return false;
        }

        return true;
    }

    /**
     * 実行ロック設定
     */
    private static function set_lock()
    {
        $lock = [
            'timestamp' => time(),
            'datetime'  => current_time('mysql'),
        ];

        set_transient(self::LOCK_KEY, $lock, self::LOCK_TTL);
    }

    /**
     * ロック解除
     */
    private static function delete_lock()
    {
        delete_transient(self::LOCK_KEY);
    }

    /**
     * 初回遅延
     */
    private static function get_initial_delay_seconds()
    {
        return 5 * MINUTE_IN_SECONDS;
    }

    /**
     * 題材があるか
     */
    private static function has_topics()
    {
        $options = get_option(WABE_OPTION, []);
        $topics  = $options['topics'] ?? [];

        return is_array($topics) && !empty($topics);
    }

    /**
     * 自動投稿有効か
     */
    private static function is_schedule_enabled()
    {
        $options = get_option(WABE_OPTION, []);
        return !empty($options['schedule_enabled']) && (string)$options['schedule_enabled'] === '1';
    }

    /**
     * 週投稿数
     */
    private static function get_weekly_posts()
    {
        $options = get_option(WABE_OPTION, []);
        $weekly_posts = (int)($options['weekly_posts'] ?? 1);

        if (class_exists('WABE_Plan') && method_exists('WABE_Plan', 'weekly_posts_max')) {
            $plan_max = max(1, (int)WABE_Plan::weekly_posts_max());
            $weekly_posts = min($weekly_posts, $plan_max);
        }

        if ($weekly_posts < 1) {
            $weekly_posts = 1;
        }

        if ($weekly_posts > 7) {
            $weekly_posts = 7;
        }

        return $weekly_posts;
    }

    /**
     * 実行間隔秒
     */
    private static function get_interval_seconds($weekly_posts)
    {
        $week_seconds = 7 * DAY_IN_SECONDS;
        $interval = (int)floor($week_seconds / max(1, (int)$weekly_posts));

        if ($interval < HOUR_IN_SECONDS) {
            $interval = HOUR_IN_SECONDS;
        }

        return $interval;
    }

    /**
     * 実行時間
     */
    private static function elapsed($started_at)
    {
        return round(max(0, microtime(true) - (float)$started_at), 4);
    }
}
