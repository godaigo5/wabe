<?php

if (!defined('ABSPATH')) exit;

class WABE_Logger
{
    const OPTION_KEY = WABE_OPTION;
    const MAX_LOGS   = 200;

    /**
     * infoログ
     *
     * @param string $message
     * @param array  $context
     * @return void
     */
    public static function info($message, array $context = [])
    {
        self::write('info', $message, $context);
    }

    /**
     * warningログ
     *
     * @param string $message
     * @param array  $context
     * @return void
     */
    public static function warning($message, array $context = [])
    {
        self::write('warning', $message, $context);
    }

    /**
     * errorログ
     *
     * @param string $message
     * @param array  $context
     * @return void
     */
    public static function error($message, array $context = [])
    {
        self::write('error', $message, $context);
    }

    /**
     * debugログ
     *
     * @param string $message
     * @param array  $context
     * @return void
     */
    public static function debug($message, array $context = [])
    {
        self::write('debug', $message, $context);
    }

    /**
     * 汎用ログ書き込み
     *
     * @param string $level
     * @param string $message
     * @param array  $context
     * @return void
     */
    public static function write($level, $message, array $context = [])
    {
        $level   = self::normalize_level($level);
        $message = self::sanitize_message($message);
        $context = self::sanitize_context($context);

        $record = [
            'date'      => current_time('mysql'),
            'level'     => $level,
            'message'   => $message,
            'context'   => $context,
            'result'    => sanitize_text_field((string)($context['result'] ?? '')),
            'error'     => sanitize_text_field((string)($context['error'] ?? '')),
            'post_id'   => (int)($context['post_id'] ?? 0),
            'duration'  => self::normalize_duration($context['duration'] ?? 0),
            'source'    => sanitize_text_field((string)($context['source'] ?? '')),
        ];

        $options = get_option(self::OPTION_KEY, []);
        if (!is_array($options)) {
            $options = [];
        }

        $logs = $options['logs'] ?? [];
        if (!is_array($logs)) {
            $logs = [];
        }

        array_unshift($logs, $record);
        $options['logs'] = array_slice($logs, 0, self::MAX_LOGS);

        update_option(self::OPTION_KEY, $options);

        /**
         * 開発中に wp-content/debug.log にも出したい場合
         * WP_DEBUG が true のときのみ error_log へ出力
         */
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $line = '[WABE][' . strtoupper($level) . '] ' . $message;

            if (!empty($context)) {
                $json = wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (is_string($json) && $json !== '') {
                    $line .= ' ' . $json;
                }
            }

            error_log($line);
        }
    }

    /**
     * 管理画面用ログ取得
     *
     * @param int $limit
     * @return array
     */
    public static function get_logs($limit = 100)
    {
        $options = get_option(self::OPTION_KEY, []);
        if (!is_array($options)) {
            return [];
        }

        $logs = $options['logs'] ?? [];
        if (!is_array($logs)) {
            return [];
        }

        $normalized = [];
        foreach ($logs as $log) {
            if (!is_array($log)) {
                continue;
            }

            $normalized[] = self::normalize_record($log);
        }

        $limit = (int)$limit;
        if ($limit <= 0) {
            $limit = 100;
        }

        return array_slice($normalized, 0, $limit);
    }

    /**
     * 最新ログ1件
     *
     * @return array|null
     */
    public static function latest()
    {
        $logs = self::get_logs(1);
        return !empty($logs[0]) ? $logs[0] : null;
    }

    /**
     * ログ全削除
     *
     * @return void
     */
    public static function clear()
    {
        $options = get_option(self::OPTION_KEY, []);
        if (!is_array($options)) {
            $options = [];
        }

        $options['logs'] = [];
        update_option(self::OPTION_KEY, $options);
    }

    /**
     * 指定レベルで絞り込み
     *
     * @param string $level
     * @param int    $limit
     * @return array
     */
    public static function get_logs_by_level($level, $limit = 100)
    {
        $level = self::normalize_level($level);
        $logs  = self::get_logs(self::MAX_LOGS);

        $filtered = [];
        foreach ($logs as $log) {
            if (($log['level'] ?? '') === $level) {
                $filtered[] = $log;
            }
        }

        $limit = (int)$limit;
        if ($limit <= 0) {
            $limit = 100;
        }

        return array_slice($filtered, 0, $limit);
    }

    /**
     * 古い形式のログも吸収して正規化
     *
     * @param array $log
     * @return array
     */
    private static function normalize_record(array $log)
    {
        $context = [];
        if (!empty($log['context']) && is_array($log['context'])) {
            $context = self::sanitize_context($log['context']);
        }

        return [
            'date'     => sanitize_text_field((string)($log['date'] ?? current_time('mysql'))),
            'level'    => self::normalize_level($log['level'] ?? 'info'),
            'message'  => self::sanitize_message($log['message'] ?? ''),
            'context'  => $context,
            'result'   => sanitize_text_field((string)($log['result'] ?? ($context['result'] ?? ''))),
            'error'    => sanitize_text_field((string)($log['error'] ?? ($context['error'] ?? ''))),
            'post_id'  => (int)($log['post_id'] ?? ($context['post_id'] ?? 0)),
            'duration' => self::normalize_duration($log['duration'] ?? ($context['duration'] ?? 0)),
            'source'   => sanitize_text_field((string)($log['source'] ?? ($context['source'] ?? ''))),
        ];
    }

    /**
     * level正規化
     *
     * @param string $level
     * @return string
     */
    private static function normalize_level($level)
    {
        $level = strtolower(trim((string)$level));

        if (!in_array($level, ['debug', 'info', 'warning', 'error'], true)) {
            return 'info';
        }

        return $level;
    }

    /**
     * メッセージ整形
     *
     * @param mixed $message
     * @return string
     */
    private static function sanitize_message($message)
    {
        if (is_array($message) || is_object($message)) {
            $json = wp_json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $message = is_string($json) ? $json : '';
        }

        $message = sanitize_textarea_field((string)$message);
        return trim($message);
    }

    /**
     * context整形
     *
     * @param array $context
     * @return array
     */
    private static function sanitize_context(array $context)
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            $key = sanitize_key((string)$key);

            if ($key === '') {
                continue;
            }

            if (is_bool($value)) {
                $sanitized[$key] = $value ? '1' : '0';
                continue;
            }

            if (is_int($value) || is_float($value)) {
                $sanitized[$key] = $value;
                continue;
            }

            if (is_array($value) || is_object($value)) {
                $json = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $sanitized[$key] = is_string($json) ? $json : '';
                continue;
            }

            $sanitized[$key] = sanitize_textarea_field((string)$value);
        }

        return $sanitized;
    }

    /**
     * duration正規化
     *
     * @param mixed $duration
     * @return float
     */
    private static function normalize_duration($duration)
    {
        return round(max(0, (float)$duration), 4);
    }
}
