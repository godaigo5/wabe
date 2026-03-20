<?php
if (!defined('ABSPATH')) exit;

$saved = isset($_GET['saved']) ? sanitize_text_field(wp_unslash($_GET['saved'])) : '';
$key   = $this->options['license_key'] ?? '';

$license  = WABE_License::sync(false);
$features = WABE_Plan::feature_map();
$current_plan = WABE_Plan::get_plan();
?>

<div class="wrap">
    <h1><?php esc_html_e('License', WABE_TEXTDOMAIN); ?></h1>

    <?php if ($saved): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('License saved and synchronized.', WABE_TEXTDOMAIN); ?></p>
        </div>
    <?php endif; ?>

    <div class="card" style="max-width: 1000px; padding: 20px; margin-bottom: 20px;">
        <h2><?php esc_html_e('Current Plan:', WABE_TEXTDOMAIN); ?> <?php echo esc_html(WABE_Plan::plan_label()); ?></h2>
        <p>
            <?php
            if ($current_plan === 'free') {
                esc_html_e('You are currently using the Free plan. Upgrade to unlock more automation features.', WABE_TEXTDOMAIN);
            } elseif ($current_plan === 'advanced') {
                esc_html_e('You are using the Advanced plan. Upgrade to Pro for full automation features.', WABE_TEXTDOMAIN);
            } else {
                esc_html_e('You are using the Pro plan with all core automation features enabled.', WABE_TEXTDOMAIN);
            }
            ?>
        </p>
    </div>

    <h2><?php esc_html_e('Plan Comparison', WABE_TEXTDOMAIN); ?></h2>

    <table class="widefat striped" style="max-width: 1100px; margin-bottom: 24px;">
        <thead>
            <tr>
                <th><?php esc_html_e('Feature', WABE_TEXTDOMAIN); ?></th>
                <th><?php esc_html_e('Free', WABE_TEXTDOMAIN); ?></th>
                <th><?php esc_html_e('Advanced', WABE_TEXTDOMAIN); ?></th>
                <th><?php esc_html_e('Pro', WABE_TEXTDOMAIN); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?php esc_html_e('Weekly Post Limit', WABE_TEXTDOMAIN); ?></td>
                <td>1</td>
                <td>7</td>
                <td>7</td>
            </tr>
            <tr>
                <td><?php esc_html_e('Title Generation Count', WABE_TEXTDOMAIN); ?></td>
                <td>1</td>
                <td>6</td>
                <td>6</td>
            </tr>
            <tr>
                <td><?php esc_html_e('Auto Publish', WABE_TEXTDOMAIN); ?></td>
                <td>✗</td>
                <td>✓</td>
                <td>✓</td>
            </tr>
            <tr>
                <td><?php esc_html_e('SEO Optimization', WABE_TEXTDOMAIN); ?></td>
                <td>✗</td>
                <td>✓</td>
                <td>✓</td>
            </tr>
            <tr>
                <td><?php esc_html_e('Featured Image Generation', WABE_TEXTDOMAIN); ?></td>
                <td>✗</td>
                <td>✓</td>
                <td>✓</td>
            </tr>
            <tr>
                <td><?php esc_html_e('AI Topic Generator', WABE_TEXTDOMAIN); ?></td>
                <td>✗</td>
                <td>✗</td>
                <td>✓</td>
            </tr>
            <tr>
                <td><?php esc_html_e('Internal Link Generator', WABE_TEXTDOMAIN); ?></td>
                <td>✗</td>
                <td>✗</td>
                <td>✓</td>
            </tr>
            <tr>
                <td><?php esc_html_e('Article Outline Generator', WABE_TEXTDOMAIN); ?></td>
                <td>✗</td>
                <td>✗</td>
                <td>✓</td>
            </tr>
        </tbody>
    </table>

    <div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom: 32px;">
        <div class="card" style="padding:20px; min-width:260px;">
            <h3><?php esc_html_e('Free', WABE_TEXTDOMAIN); ?></h3>
            <p><strong><?php echo WABE_Plan::format_price(0); ?></strong></p>
            <p><?php esc_html_e('Best for testing the plugin.', WABE_TEXTDOMAIN); ?></p>
        </div>

        <div class="card" style="padding:20px; min-width:260px;">
            <h3><?php esc_html_e('Advanced', WABE_TEXTDOMAIN); ?></h3>
            <p><strong><?php echo WABE_Plan::format_price(79); ?></strong></p>
            <p><?php esc_html_e('For bloggers who want automation, SEO, and images.', WABE_TEXTDOMAIN); ?></p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url(WABE_BUY_ADVANCED_URL); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('Buy Advanced', WABE_TEXTDOMAIN); ?>
                </a>
            </p>
			<p><?php echo strpos(get_locale(), 'ja') === 0 ? "※実際の請求はUSDで行われます": ""; ?></p>
        </div>

        <div class="card" style="padding:20px; min-width:260px;">
            <h3><?php esc_html_e('Pro', WABE_TEXTDOMAIN); ?></h3>
            <p><strong><?php echo WABE_Plan::format_price(179); ?></strong></p>
            <p><?php esc_html_e('For full AI automation with topic generation, internal links, and outlines.', WABE_TEXTDOMAIN); ?></p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url(WABE_BUY_PRO_URL); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('Buy Pro', WABE_TEXTDOMAIN); ?>
                </a>
            </p>
			<p><?php echo strpos(get_locale(), 'ja') === 0 ? "※実際の請求はUSDで行われます": ""; ?></p>
        </div>
    </div>

    <h2><?php esc_html_e('Activate License', WABE_TEXTDOMAIN); ?></h2>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="wabe_save_license">
        <?php wp_nonce_field('wabe_save_license', 'wabe_license_nonce'); ?>

        <table class="form-table">
            <tr>
                <th>
                    <label for="wabe_license_key"><?php esc_html_e('License Key', WABE_TEXTDOMAIN); ?></label>
                </th>
                <td>
                    <input
                        id="wabe_license_key"
                        type="text"
                        name="wabe_license_key"
                        class="regular-text"
                        value="<?php echo esc_attr($key); ?>"
                    >
                    <p class="description">
                        <?php esc_html_e('After purchase, paste your license key here and save.', WABE_TEXTDOMAIN); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Save License', WABE_TEXTDOMAIN)); ?>
    </form>

    <h2><?php esc_html_e('Current License Data', WABE_TEXTDOMAIN); ?></h2>
	<?php
	$plan = WABE_Plan::get_plan();
	$status = $license['status'] ?? 'inactive';

	function wabe_license_status_text($plan, $status) {
		if ($plan === 'free') {
			return esc_html__('Free plan in use', WABE_TEXTDOMAIN);
		}

		switch ($status) {
			case 'active':
				return esc_html__('License active', WABE_TEXTDOMAIN);
			case 'expired':
				return esc_html__('License expired', WABE_TEXTDOMAIN);
			case 'inactive':
				return esc_html__('License inactive', WABE_TEXTDOMAIN);
			case 'blocked':
				return esc_html__('License blocked', WABE_TEXTDOMAIN);
			default:
				return esc_html__('Unknown', WABE_TEXTDOMAIN);
		}
	}
	?>
    <table class="widefat striped" style="max-width:900px;">
        <tbody>
            <tr>
                <th style="width:220px;"><?php esc_html_e('Current Plan:', WABE_TEXTDOMAIN); ?></th>
                <td><?php echo esc_html(WABE_Plan::plan_label()); ?></td>
            </tr>
			<tr>
				<th><?php esc_html_e('License Status', WABE_TEXTDOMAIN); ?></th>
				<td><?php echo wabe_license_status_text($plan, $status); ?></td>
			</tr>
            <tr>
                <th><?php esc_html_e('Domain', WABE_TEXTDOMAIN); ?></th>
                <td><?php echo esc_html($license['domain'] ?? '-'); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Expires At', WABE_TEXTDOMAIN); ?></th>
                <td><?php echo !empty($license['expires_at']) ? esc_html((string)$license['expires_at']) : esc_html__('Never', WABE_TEXTDOMAIN); ?></td>
            </tr>
        </tbody>
    </table>

    <h2><?php esc_html_e('License Features', WABE_TEXTDOMAIN); ?></h2>

    <table class="widefat striped" style="max-width:900px;">
        <thead>
            <tr>
                <th><?php esc_html_e('Feature', WABE_TEXTDOMAIN); ?></th>
                <th><?php esc_html_e('Status', WABE_TEXTDOMAIN); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($features as $feature): ?>
                <tr>
                    <td><?php echo esc_html($feature['label']); ?></td>
                    <td>
                        <?php
                        if ($feature['type'] === 'bool') {
                            echo WABE_Plan::bool_label($feature['value']);
                        } else {
                            echo esc_html((string)$feature['value']);
                        }
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>