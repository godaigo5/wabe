<?php if (!defined('ABSPATH')) exit; $cleared=isset($_GET['cleared'])?sanitize_text_field(wp_unslash($_GET['cleared'])):''; ?>
<div class="wrap">
    <h1><?php esc_html_e('Logs',WABE_TEXTDOMAIN); ?></h1><?php if($cleared):?><div
        class="notice notice-success is-dismissible">
        <p><?php esc_html_e('Logs cleared.',WABE_TEXTDOMAIN); ?></p>
    </div><?php endif; ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
        style="margin-bottom:20px;"><input type="hidden" name="action"
            value="wabe_clear_logs"><?php wp_nonce_field('wabe_clear_logs','wabe_clear_logs_nonce'); ?><?php submit_button(__('Clear Logs',WABE_TEXTDOMAIN),'delete','',false); ?>
    </form>
    <table class="widefat striped">
        <thead>
            <tr>
                <th style="width:180px;"><?php esc_html_e('Date',WABE_TEXTDOMAIN); ?></th>
                <th style="width:100px;"><?php esc_html_e('Level',WABE_TEXTDOMAIN); ?></th>
                <th><?php esc_html_e('Message',WABE_TEXTDOMAIN); ?></th>
            </tr>
        </thead>
        <tbody><?php if(empty($logs)): ?><tr>
                <td colspan="3"><?php esc_html_e('No logs.',WABE_TEXTDOMAIN); ?></td>
            </tr><?php else: foreach($logs as $log): ?><tr>
                <td><?php echo esc_html($log['date']??''); ?></td>
                <td><?php echo esc_html(strtoupper($log['level']??'info')); ?></td>
                <td><?php echo esc_html($log['message']??''); ?></td>
            </tr><?php endforeach; endif; ?></tbody>
    </table>
</div>
