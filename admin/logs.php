<?php
if (!defined('ABSPATH')) exit;

$logs = $this->get_logs();
?>
<div class="wrap">
    <h1><?php esc_html_e('Logs', WABE_TEXTDOMAIN); ?></h1>

    <?php if (empty($logs)): ?>
        <div class="card" style="padding:16px;max-width:1100px;">
            <p><?php esc_html_e('No logs yet.', WABE_TEXTDOMAIN); ?></p>
        </div>
    <?php else: ?>
        <table class="widefat striped" style="max-width:1100px;">
            <thead>
                <tr>
                    <th style="width:200px;"><?php esc_html_e('Date', WABE_TEXTDOMAIN); ?></th>
                    <th><?php esc_html_e('Message', WABE_TEXTDOMAIN); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row['date'] ?? ''); ?></td>
                        <td><?php echo esc_html($row['message'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
