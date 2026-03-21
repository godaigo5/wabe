<?php
if (!defined('ABSPATH')) exit;

$logs = is_array($logs ?? null) ? $logs : [];

if (!function_exists('wabe_log_level_label')) {
    function wabe_log_level_label($level)
    {
        $level = strtolower((string)$level);

        $map = [
            'info'    => __('Info', WABE_TEXTDOMAIN),
            'warning' => __('Warning', WABE_TEXTDOMAIN),
            'error'   => __('Error', WABE_TEXTDOMAIN),
            'debug'   => __('Debug', WABE_TEXTDOMAIN),
        ];

        return $map[$level] ?? ucfirst($level);
    }
}

if (!function_exists('wabe_log_level_badge')) {
    function wabe_log_level_badge($level)
    {
        $level = strtolower((string)$level);

        $styles = [
            'info' => ['bg' => '#dbeafe', 'color' => '#1d4ed8'],
            'warning' => ['bg' => '#fef3c7', 'color' => '#92400e'],
            'error' => ['bg' => '#fee2e2', 'color' => '#b91c1c'],
            'debug' => ['bg' => '#e5e7eb', 'color' => '#374151'],
        ];

        $style = $styles[$level] ?? ['bg' => '#e5e7eb', 'color' => '#374151'];

        return sprintf(
            '<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:%s;color:%s;font-size:12px;font-weight:600;">%s</span>',
            esc_attr($style['bg']),
            esc_attr($style['color']),
            esc_html(wabe_log_level_label($level))
        );
    }
}
?>
<div class="wrap">
    <h1><?php echo esc_html__('Logs', WABE_TEXTDOMAIN); ?></h1>

    <?php if (!empty($_GET['wabe_message'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html(wp_unslash($_GET['wabe_message'])); ?></p>
        </div>
    <?php endif; ?>

    <div class="postbox" style="padding:20px;max-width:1200px;margin-bottom:24px;">
        <h2 style="margin:0 0 14px 0;"><?php echo esc_html__('Log Overview', WABE_TEXTDOMAIN); ?></h2>

        <p style="margin:0 0 16px 0;">
            <?php echo esc_html__('Stored logs', WABE_TEXTDOMAIN); ?>: <?php echo esc_html((string)count($logs)); ?>
        </p>

        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                <input type="hidden" name="action" value="wabe_clear_logs">
                <?php wp_nonce_field('wabe_clear_logs', 'wabe_clear_logs_nonce'); ?>
                <button type="submit" class="button button-secondary"
                    onclick="return confirm('<?php echo esc_js(__('Are you sure you want to clear all logs?', WABE_TEXTDOMAIN)); ?>');"
                    <?php disabled(empty($logs)); ?>>
                    <?php echo esc_html__('Clear Logs', WABE_TEXTDOMAIN); ?>
                </button>
            </form>

            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wabe')); ?>">
                <?php echo esc_html__('Back to Settings', WABE_TEXTDOMAIN); ?>
            </a>
        </div>
    </div>

    <div class="postbox" style="padding:20px;max-width:1200px;">
        <h2 style="margin:0 0 14px 0;"><?php echo esc_html__('Log Entries', WABE_TEXTDOMAIN); ?></h2>

        <?php if (empty($logs)) : ?>
            <p><?php echo esc_html__('No logs found.', WABE_TEXTDOMAIN); ?></p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th style="width:180px;"><?php echo esc_html__('Time', WABE_TEXTDOMAIN); ?></th>
                        <th style="width:140px;"><?php echo esc_html__('Level', WABE_TEXTDOMAIN); ?></th>
                        <th><?php echo esc_html__('Message', WABE_TEXTDOMAIN); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $row) : ?>
                        <?php
                        $time = sanitize_text_field($row['time'] ?? '');
                        $level = sanitize_key($row['level'] ?? 'info');
                        $message = sanitize_text_field($row['message'] ?? '');
                        ?>
                        <tr>
                            <td><?php echo esc_html($time !== '' ? $time : '—'); ?></td>
                            <td><?php echo wp_kses_post(wabe_log_level_badge($level)); ?></td>
                            <td style="word-break:break-word;"><?php echo esc_html($message !== '' ? $message : '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
