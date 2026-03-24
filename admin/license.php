<?php
if (!defined('ABSPATH')) exit;

$opt = is_array($this->options ?? null) ? $this->options : [];
$license = is_array($license ?? null) ? $license : [];

$plan = method_exists($this, 'get_plan') ? $this->get_plan() : sanitize_key($opt['plan'] ?? 'free');
$plan_label = method_exists($this, 'get_plan_label') ? $this->get_plan_label($plan) : ucfirst((string) $plan);

$license_key = sanitize_text_field($opt['license_key'] ?? '');
$status = sanitize_text_field($license['status'] ?? ($opt['license_status'] ?? 'free'));
$checked_at = sanitize_text_field($license['checked_at'] ?? ($opt['license_checked_at'] ?? ''));
$expires_at = sanitize_text_field($license['expires_at'] ?? ($opt['license_expires_at'] ?? ''));
$customer_email = sanitize_text_field($license['customer_email'] ?? ($opt['license_customer_email'] ?? ''));
$remote_plan = sanitize_key($license['plan'] ?? $plan);

if (!function_exists('wabe_license_status_badge')) {
    function wabe_license_status_badge($status)
    {
        $status = strtolower((string) $status);
        $map = [
            'active'   => ['bg' => '#dcfce7', 'color' => '#166534', 'label' => __('Active', WABE_TEXTDOMAIN)],
            'valid'    => ['bg' => '#dcfce7', 'color' => '#166534', 'label' => __('Valid', WABE_TEXTDOMAIN)],
            'inactive' => ['bg' => '#f3f4f6', 'color' => '#374151', 'label' => __('Inactive', WABE_TEXTDOMAIN)],
            'expired'  => ['bg' => '#fee2e2', 'color' => '#991b1b', 'label' => __('Expired', WABE_TEXTDOMAIN)],
            'invalid'  => ['bg' => '#fee2e2', 'color' => '#991b1b', 'label' => __('Invalid', WABE_TEXTDOMAIN)],
            'free'     => ['bg' => '#e0f2fe', 'color' => '#075985', 'label' => __('Free', WABE_TEXTDOMAIN)],
        ];
        $style = $map[$status] ?? ['bg' => '#f3f4f6', 'color' => '#374151', 'label' => ucfirst($status)];

        return sprintf(
            '<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:%s;color:%s;font-weight:700;">%s</span>',
            esc_attr($style['bg']),
            esc_attr($style['color']),
            esc_html($style['label'])
        );
    }
}

if (!function_exists('wabe_license_plan_badge')) {
    function wabe_license_plan_badge($plan)
    {
        $plan = strtolower((string) $plan);
        $map = [
            'free'     => ['bg' => '#e2e8f0', 'color' => '#334155', 'label' => 'Free'],
            'advanced' => ['bg' => '#dbeafe', 'color' => '#1d4ed8', 'label' => 'Advanced'],
            'pro'      => ['bg' => '#ede9fe', 'color' => '#6d28d9', 'label' => 'Pro'],
        ];
        $style = $map[$plan] ?? ['bg' => '#e5e7eb', 'color' => '#374151', 'label' => ucfirst($plan)];

        return sprintf(
            '<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:%s;color:%s;font-weight:700;">%s</span>',
            esc_attr($style['bg']),
            esc_attr($style['color']),
            esc_html($style['label'])
        );
    }
}

if (!function_exists('wabe_license_mask_key')) {
    function wabe_license_mask_key($value)
    {
        $value = trim((string) $value);
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
    <h1><?php esc_html_e('License', WABE_TEXTDOMAIN); ?></h1>

    <?php if (!empty($_GET['message'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html(wp_unslash($_GET['message'])); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['updated'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('License information has been updated.', WABE_TEXTDOMAIN); ?></p>
        </div>
    <?php endif; ?>

    <div class="postbox" style="padding:20px;margin-top:20px;">
        <h2 style="margin-top:0;"><?php esc_html_e('License Key', WABE_TEXTDOMAIN); ?></h2>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="wabe_save_license_key">
            <?php wp_nonce_field('wabe_save_license_key'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="license_key"><?php esc_html_e('License Key', WABE_TEXTDOMAIN); ?></label>
                    </th>
                    <td>
                        <input type="text" class="regular-text" style="min-width:420px;" id="license_key"
                            name="license_key" value="<?php echo esc_attr($license_key); ?>"
                            placeholder="<?php esc_attr_e('Enter your license key', WABE_TEXTDOMAIN); ?>">
                        <p class="description">
                            <?php esc_html_e('Enter the license key you received after purchase.', WABE_TEXTDOMAIN); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Save License Key', WABE_TEXTDOMAIN), 'primary', 'submit', false); ?>
        </form>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
            style="display:inline-block;margin-top:8px;">
            <input type="hidden" name="action" value="wabe_refresh_license">
            <?php wp_nonce_field('wabe_refresh_license'); ?>
            <?php submit_button(__('Refresh License Information', WABE_TEXTDOMAIN), 'secondary', 'submit', false); ?>
        </form>
    </div>

    <div class="postbox" style="padding:20px;margin-top:20px;">
        <h2 style="margin-top:0;"><?php esc_html_e('Current License Status', WABE_TEXTDOMAIN); ?></h2>

        <table class="widefat striped" style="max-width:900px;">
            <tbody>
                <tr>
                    <th style="width:260px;"><?php esc_html_e('Current Plan', WABE_TEXTDOMAIN); ?></th>
                    <td><?php echo wp_kses_post(wabe_license_plan_badge($plan)); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('API Plan', WABE_TEXTDOMAIN); ?></th>
                    <td><?php echo wp_kses_post(wabe_license_plan_badge($remote_plan)); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('License Status', WABE_TEXTDOMAIN); ?></th>
                    <td><?php echo wp_kses_post(wabe_license_status_badge($status)); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('License Key', WABE_TEXTDOMAIN); ?></th>
                    <td><code><?php echo esc_html(wabe_license_mask_key($license_key)); ?></code></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Customer Email Address', WABE_TEXTDOMAIN); ?></th>
                    <td><?php echo esc_html($customer_email !== '' ? $customer_email : '—'); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Checked At', WABE_TEXTDOMAIN); ?></th>
                    <td><?php echo esc_html($checked_at !== '' ? $checked_at : '—'); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Expires At', WABE_TEXTDOMAIN); ?></th>
                    <td><?php echo esc_html($expires_at !== '' ? $expires_at : '—'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="postbox" style="padding:20px;margin-top:20px;">
        <h2 style="margin-top:0;"><?php esc_html_e('Plan Comparison', WABE_TEXTDOMAIN); ?></h2>

        <table class="widefat striped" style="max-width:900px;">
            <thead>
                <tr>
                    <th><?php esc_html_e('Feature', WABE_TEXTDOMAIN); ?></th>
                    <th>Free</th>
                    <th>Advanced</th>
                    <th>Pro</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php esc_html_e('Weekly posts', WABE_TEXTDOMAIN); ?></td>
                    <td>1</td>
                    <td>1 - 7</td>
                    <td>1 - 7</td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Heading count', WABE_TEXTDOMAIN); ?></td>
                    <td>1</td>
                    <td>1 - 6</td>
                    <td>1 - 6</td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Publish', WABE_TEXTDOMAIN); ?></td>
                    <td>—</td>
                    <td>✓</td>
                    <td>✓</td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Image generation', WABE_TEXTDOMAIN); ?></td>
                    <td>—</td>
                    <td>✓</td>
                    <td>✓</td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Internal links', WABE_TEXTDOMAIN); ?></td>
                    <td>—</td>
                    <td>—</td>
                    <td>✓</td>
                </tr>
                <tr>
                    <td><?php esc_html_e('External links', WABE_TEXTDOMAIN); ?></td>
                    <td>—</td>
                    <td>—</td>
                    <td>✓</td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Topic prediction', WABE_TEXTDOMAIN); ?></td>
                    <td>—</td>
                    <td>—</td>
                    <td>✓</td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Duplicate check', WABE_TEXTDOMAIN); ?></td>
                    <td>—</td>
                    <td>—</td>
                    <td>✓</td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Outline generator', WABE_TEXTDOMAIN); ?></td>
                    <td>—</td>
                    <td>—</td>
                    <td>✓</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
