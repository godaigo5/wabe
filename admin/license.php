<?php
if (!defined('ABSPATH')) exit;

$opt = is_array($this->options ?? null) ? $this->options : [];
$license = is_array($license ?? null) ? $license : [];

$plan = method_exists($this, 'get_plan') ? $this->get_plan() : sanitize_key($opt['plan'] ?? 'free');
$plan_label = method_exists($this, 'get_plan_label') ? $this->get_plan_label($plan) : ucfirst((string)$plan);

$license_key = sanitize_text_field($opt['license_key'] ?? '');
$status = sanitize_text_field($license['status'] ?? ($opt['license_status'] ?? 'free'));
$checked_at = sanitize_text_field($license['checked_at'] ?? ($opt['license_checked_at'] ?? ''));
$expires_at = sanitize_text_field($license['expires_at'] ?? ($opt['license_expires_at'] ?? ''));
$customer_email = sanitize_text_field($license['customer_email'] ?? ($opt['license_customer_email'] ?? ''));
$remote_plan = sanitize_key($license['plan'] ?? $plan);

if (!function_exists('wabe_license_status_badge')) {
    function wabe_license_status_badge($status)
    {
        $status = strtolower((string)$status);

        $map = [
            'active' => ['bg' => '#dcfce7', 'color' => '#166534', 'label' => __('Active', WABE_TEXTDOMAIN)],
            'valid' => ['bg' => '#dcfce7', 'color' => '#166534', 'label' => __('Valid', WABE_TEXTDOMAIN)],
            'inactive' => ['bg' => '#f3f4f6', 'color' => '#374151', 'label' => __('Inactive', WABE_TEXTDOMAIN)],
            'expired' => ['bg' => '#fee2e2', 'color' => '#991b1b', 'label' => __('Expired', WABE_TEXTDOMAIN)],
            'invalid' => ['bg' => '#fee2e2', 'color' => '#991b1b', 'label' => __('Invalid', WABE_TEXTDOMAIN)],
            'free' => ['bg' => '#e0f2fe', 'color' => '#075985', 'label' => __('Free', WABE_TEXTDOMAIN)],
        ];

        $style = $map[$status] ?? ['bg' => '#f3f4f6', 'color' => '#374151', 'label' => ucfirst($status)];

        return sprintf(
            '<span style="display:inline-block;padding:5px 12px;border-radius:999px;background:%s;color:%s;font-size:12px;font-weight:600;">%s</span>',
            esc_attr($style['bg']),
            esc_attr($style['color']),
            esc_html($style['label'])
        );
    }
}

if (!function_exists('wabe_license_plan_badge')) {
    function wabe_license_plan_badge($plan)
    {
        $plan = strtolower((string)$plan);

        $map = [
            'free' => ['bg' => '#e2e8f0', 'color' => '#334155', 'label' => 'Free'],
            'advanced' => ['bg' => '#dbeafe', 'color' => '#1d4ed8', 'label' => 'Advanced'],
            'pro' => ['bg' => '#ede9fe', 'color' => '#6d28d9', 'label' => 'Pro'],
        ];

        $style = $map[$plan] ?? ['bg' => '#e5e7eb', 'color' => '#374151', 'label' => ucfirst($plan)];

        return sprintf(
            '<span style="display:inline-block;padding:6px 12px;border-radius:999px;background:%s;color:%s;font-size:12px;font-weight:700;">%s</span>',
            esc_attr($style['bg']),
            esc_attr($style['color']),
            esc_html($style['label'])
        );
    }
}

if (!function_exists('wabe_license_mask_key')) {
    function wabe_license_mask_key($value)
    {
        $value = trim((string)$value);

        if ($value === '') {
            return '—';
        }

        $len = strlen($value);
        if ($len <= 8) {
            return str_repeat('*', max(4, $len));
        }

        return substr($value, 0, 4) . str_repeat('*', max(4, $len - 8)) . substr($value, -4);
    }
}
?>
<div class="wrap">
    <h1><?php echo esc_html__('License', WABE_TEXTDOMAIN); ?></h1>

    <?php if (!empty($_GET['wabe_message'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html(wp_unslash($_GET['wabe_message'])); ?></p>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:24px;align-items:start;max-width:1400px;">
        <div>
            <div class="postbox" style="padding:20px;margin-bottom:24px;">
                <h2 style="margin:0 0 16px 0;"><?php echo esc_html__('Current License Status', WABE_TEXTDOMAIN); ?></h2>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Current Plan', WABE_TEXTDOMAIN); ?></th>
                            <td><?php echo wp_kses_post(wabe_license_plan_badge($plan)); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Remote Plan', WABE_TEXTDOMAIN); ?></th>
                            <td><?php echo wp_kses_post(wabe_license_plan_badge($remote_plan)); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('License Status', WABE_TEXTDOMAIN); ?></th>
                            <td><?php echo wp_kses_post(wabe_license_status_badge($status)); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('License Key', WABE_TEXTDOMAIN); ?></th>
                            <td><code><?php echo esc_html(wabe_license_mask_key($license_key)); ?></code></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Customer Email', WABE_TEXTDOMAIN); ?></th>
                            <td><?php echo esc_html($customer_email !== '' ? $customer_email : '—'); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Checked At', WABE_TEXTDOMAIN); ?></th>
                            <td><?php echo esc_html($checked_at !== '' ? $checked_at : '—'); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Expires At', WABE_TEXTDOMAIN); ?></th>
                            <td><?php echo esc_html($expires_at !== '' ? $expires_at : '—'); ?></td>
                        </tr>
                    </tbody>
                </table>

                <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:18px;">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                        <input type="hidden" name="action" value="wabe_refresh_license">
                        <?php wp_nonce_field('wabe_refresh_license', 'wabe_refresh_license_nonce'); ?>
                        <button type="submit"
                            class="button button-primary"><?php echo esc_html__('Refresh License', WABE_TEXTDOMAIN); ?></button>
                    </form>

                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wabe')); ?>">
                        <?php echo esc_html__('Back to Settings', WABE_TEXTDOMAIN); ?>
                    </a>
                </div>
            </div>

            <div class="postbox" style="padding:20px;margin-bottom:24px;">
                <h2 style="margin:0 0 16px 0;"><?php echo esc_html__('Plan Comparison', WABE_TEXTDOMAIN); ?></h2>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Feature', WABE_TEXTDOMAIN); ?></th>
                            <th>Free</th>
                            <th>Advanced</th>
                            <th>Pro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo esc_html__('Price', WABE_TEXTDOMAIN); ?></td>
                            <td><?php echo esc_html__('Free', WABE_TEXTDOMAIN); ?></td>
                            <td><?php echo esc_html__('$12/mo $79/yr $199 lifetime', WABE_TEXTDOMAIN); ?></td>
                            <td><?php echo esc_html__('$24/mo $159/yr $399 lifetime', WABE_TEXTDOMAIN); ?></td>
                        </tr>
                        <tr>
                            <td><?php echo esc_html__('Automatic posts per week', WABE_TEXTDOMAIN); ?></td>
                            <td>1</td>
                            <td>1 - 7</td>
                            <td>1 - 7</td>
                        </tr>
                        <tr>
                            <td><?php echo esc_html__('Heading count', WABE_TEXTDOMAIN); ?></td>
                            <td>1</td>
                            <td>1 - 6</td>
                            <td>1 - 6</td>
                        </tr>
                        <tr>
                            <td><?php echo esc_html__('Post status', WABE_TEXTDOMAIN); ?></td>
                            <td><?php echo esc_html__('Draft only', WABE_TEXTDOMAIN); ?></td>
                            <td><?php echo esc_html__('Draft / Publish', WABE_TEXTDOMAIN); ?></td>
                            <td><?php echo esc_html__('Draft / Publish', WABE_TEXTDOMAIN); ?></td>
                        </tr>
                        <tr>
                            <td><?php echo esc_html__('Featured image generation', WABE_TEXTDOMAIN); ?></td>
                            <td>—</td>
                            <td>✓</td>
                            <td>✓</td>
                        </tr>
                        <tr>
                            <td><?php echo esc_html__('SEO support', WABE_TEXTDOMAIN); ?></td>
                            <td>—</td>
                            <td>✓</td>
                            <td>✓</td>
                        </tr>
                        <tr>
                            <td><?php echo esc_html__('Duplicate check', WABE_TEXTDOMAIN); ?></td>
                            <td>—</td>
                            <td>—</td>
                            <td>✓</td>
                        </tr>
                        <tr>
                            <td><?php echo esc_html__('Internal links', WABE_TEXTDOMAIN); ?></td>
                            <td>—</td>
                            <td>—</td>
                            <td>✓</td>
                        </tr>
                        <tr>
                            <td><?php echo esc_html__('External links', WABE_TEXTDOMAIN); ?></td>
                            <td>—</td>
                            <td>—</td>
                            <td>✓</td>
                        </tr>
                        <tr>
                            <td><?php echo esc_html__('Topic prediction', WABE_TEXTDOMAIN); ?></td>
                            <td>—</td>
                            <td>—</td>
                            <td>✓</td>
                        </tr>
                        <tr>
                            <td><?php echo esc_html__('Outline generator', WABE_TEXTDOMAIN); ?></td>
                            <td>—</td>
                            <td>—</td>
                            <td>✓</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <div class="postbox" style="padding:20px;margin-bottom:24px;">
                <h2 style="margin:0 0 16px 0;">
                    <?php echo esc_html__('What to Check When License Sync Fails', WABE_TEXTDOMAIN); ?></h2>

                <ul style="padding-left:18px;margin:0;">
                    <li style="margin-bottom:10px;">
                        <?php echo esc_html__('Confirm that the saved license key is correct.', WABE_TEXTDOMAIN); ?>
                    </li>
                    <li style="margin-bottom:10px;">
                        <?php echo esc_html__('Check whether your API server is reachable from WordPress.', WABE_TEXTDOMAIN); ?>
                    </li>
                    <li style="margin-bottom:10px;">
                        <?php echo esc_html__('Check the Stripe Webhook processing result on the API side.', WABE_TEXTDOMAIN); ?>
                    </li>
                    <li><?php echo esc_html__('Review the plugin logs for detailed sync errors.', WABE_TEXTDOMAIN); ?>
                    </li>
                </ul>

                <p style="margin-top:16px;">
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wabe-logs')); ?>">
                        <?php echo esc_html__('Open Logs', WABE_TEXTDOMAIN); ?>
                    </a>
                </p>
            </div>

            <div class="postbox" style="padding:20px;">
                <h2 style="margin:0 0 16px 0;"><?php echo esc_html__('Sales Page / Upgrade', WABE_TEXTDOMAIN); ?></h2>

                <p style="margin-top:0;">
                    <?php echo esc_html__('Upgrade links can be placed here later. For now, direct users to your sales site or customer portal.', WABE_TEXTDOMAIN); ?>
                </p>

                <p style="margin-bottom:0;">
                    <a class="button button-secondary" href="http://wabe.d-create.online/" target="_blank"
                        rel="noopener noreferrer">
                        <?php echo esc_html__('Open Sales Site', WABE_TEXTDOMAIN); ?>
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>
