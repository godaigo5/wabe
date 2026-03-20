<?php
if (!defined('ABSPATH')) exit;

class WABE_Logger
{
    public static function info($message)
    {
        self::write('INFO', $message);
    }

    public static function warning($message)
    {
        self::write('WARNING', $message);
    }

    public static function error($message)
    {
        self::write('ERROR', $message);
    }

    private static function write($level, $message)
    {
        $line = sprintf(
            '[%s] [%s] %s',
            current_time('mysql'),
            sanitize_text_field($level),
            sanitize_text_field((string)$message)
        );

        error_log('WABE ' . $line);

        $options = get_option(WABE_OPTION, []);
        $logs = $options['logs'] ?? [];

        if (!is_array($logs)) {
            $logs = [];
        }

        array_unshift($logs, [
            'date' => current_time('mysql'),
            'message' => sprintf('[%s] %s', sanitize_text_field($level), sanitize_text_field((string)$message)),
        ]);

        $options['logs'] = array_slice($logs, 0, 200);
        update_option(WABE_OPTION, $options);
    }
}
