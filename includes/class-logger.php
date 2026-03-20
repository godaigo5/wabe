<?php
if (!defined('ABSPATH')) exit;
class WABE_Logger
{
    const OPTION_KEY = 'wabe_logs';
    const MAX_LOGS = 100;
    static function log($t)
    {
        self::write('info', $t);
    }
    static function info($t)
    {
        self::write('info', $t);
    }
    static function warning($t)
    {
        self::write('warning', $t);
    }
    static function error($t)
    {
        self::write('error', $t);
    }
    private static function write($level, $text)
    {
        $logs = get_option(self::OPTION_KEY, []);
        if (!is_array($logs)) $logs = [];
        $logs[] = ['date' => current_time('mysql'), 'level' => sanitize_text_field((string)$level), 'message' => sanitize_textarea_field((string)$text)];
        if (count($logs) > self::MAX_LOGS) $logs = array_slice($logs, -self::MAX_LOGS);
        update_option(self::OPTION_KEY, $logs, false);
    }
    static function get_logs()
    {
        $logs = get_option(self::OPTION_KEY, []);
        return is_array($logs) ? array_reverse($logs) : [];
    }
    static function clear()
    {
        delete_option(self::OPTION_KEY);
    }
}
