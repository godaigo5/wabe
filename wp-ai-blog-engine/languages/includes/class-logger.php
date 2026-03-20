<?php
if (!defined('ABSPATH')) exit;

class WABE_Logger
{
    const OPTION_KEY = WABE_OPTION;
    const MAX_LOGS   = 300;

    /**
     * infoログ
     *
     * @param string $message
     * @return void
     */
    public static function info($message)
    {
        self::add_log('info', $message);
    }

    /**
     * warningログ
     *
     * @param string $message
     * @return void
     */
    public static function warning($message)
    {
        self::add_log('warning', $message);
    }

    /**
     * errorログ
     *
     * @param string $message
     * @return void
     */
    public static function error($message)
    {
        self::add_log('error', $message);
    }

    /**
     * デバッグログ
     *
     * @param string $message
     * @return void
     */
    public static function debug($message)
    {
        self::add_log('debug', $message);
    }

    /**
     * ログ追加
     *
     * @param string $level
     * @param string $message
     * @return void
     */
    public static function add_log($level, $message)
    {
        $level = sanitize_key((string)$level);
        $message = sanitize_text_field((string)$message);

        if ($message === '') {
            return;
        }

        $options = get_option(self::OPTION_KEY, []);
        if (!is_array($options)) {
            $options = [];
        }

        $logs = $options['logs'] ?? [];
        if (!is_array($logs)) {
            $logs = [];
        }

        array_unshift($logs, [
            'time'    => current_time('mysql'),
            'level'   => $level,
            'message' => $message,
        ]);

        $options['logs'] = array_slice($logs, 0, self::MAX_LOGS);
        update_option(self::OPTION_KEY, $options);
    }

    /**
     * ログ取得
     *
     * @return array
     */
    public static function get_logs()
    {
        $options = get_option(self::OPTION_KEY, []);
        if (!is_array($options)) {
            return [];
        }

        $logs = $options['logs'] ?? [];
        return is_array($logs) ? $logs : [];
    }

    /**
     * ログ全削除
     *
     * @return void
     */
    public static function clear_logs()
    {
        $options = get_option(self::OPTION_KEY, []);
        if (!is_array($options)) {
            $options = [];
        }

        $options['logs'] = [];
        update_option(self::OPTION_KEY, $options);
    }
}
