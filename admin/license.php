<?php
if (!defined('ABSPATH')) exit;

$message = isset($_GET['wabe_message']) ? sanitize_text_field(wp_unslash($_GET['wabe_message'])) : '';
$opt     = $this->options;
$license = WABE_License::sync(false);
$plan    = WABE_Plan::get_plan();

$license_key = $opt['license_key'] ?? '';
$status      = $license['status'] ?? 'inactive';
$features    = $license['features'] ?? [];
?>
<div class="wrap">
    <h1><?php esc_html_e('License', WABE_TEXTDOMAIN); ?></h1>

    <?php if ($message): ?>
    <div class="notice notice-success is-dismissible">
        <p><?php echo esc_html($message); ?></p>
    </div>
    <?php endif; ?>

    <div class="card" style="padding:16px;max-width:1100px;margin-bottom:20px;">
        <h2><?php esc_html_e('Current License Status', WABE_TEXTDOMAIN); ?></h2>

        <p>
            <strong><?php esc_html_e('Current Plan:', WABE_TEXTDOMAIN); ?></strong>
            <?php echo esc_html(ucfirst($plan)); ?>
        </p>

        <p>
            <strong><?php esc_html_e('Status:', WABE_TEXTDOMAIN); ?></strong>
            <?php echo esc_html($status); ?>
        </p>

        <?php if (!empty($license['checked_at'])): ?>
        <p>
            <strong><?php esc_html_e('Last Checked:', WABE_TEXTDOMAIN); ?></strong>
            <?php echo esc_html($license['checked_at']); ?>
        </p>
        <?php endif; ?>
    </div>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:1100px;">
        <input type="hidden" name="action" value="wabe_save_license">
        <?php wp_nonce_field('wabe_save_license', 'wabe_license_nonce'); ?>

        <table class="form-table">
            <tr>
                <th>
                    <label for="wabe_license_key"><?php esc_html_e('License Key', WABE_TEXTDOMAIN); ?></label>
                </th>
                <td>
                    <input id="wabe_license_key" type="text" name="wabe_license_key" class="regular-text"
                        value="<?php echo esc_attr($license_key); ?>">
                    <p class="description">
                        <?php esc_html_e('Enter your license key and save it.', WABE_TEXTDOMAIN); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Save License Key', WABE_TEXTDOMAIN)); ?>
    </form>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:1100px;">
        <input type="hidden" name="action" value="wabe_sync_license">
        <?php wp_nonce_field('wabe_sync_license', 'wabe_sync_license_nonce'); ?>
        <?php submit_button(__('Sync License', WABE_TEXTDOMAIN), 'secondary'); ?>
    </form>

    <hr>

    <h2><?php esc_html_e('Plan Features', WABE_TEXTDOMAIN); ?></h2>

    <table class="widefat striped" style="max-width:1100px;">
        <thead>
            <tr>
                <th><?php esc_html_e('Feature', WABE_TEXTDOMAIN); ?></th>
                <th style="width:180px;"><?php esc_html_e('Value', WABE_TEXTDOMAIN); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?php esc_html_e('Weekly Posts Max', WABE_TEXTDOMAIN); ?></td>
                <td><?php echo esc_html($features['weekly_posts_max'] ?? 1); ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e('Heading Count Max', WABE_TEXTDOMAIN); ?></td>
                <td><?php echo esc_html($features['title_count_max'] ?? 1); ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e('Can Publish', WABE_TEXTDOMAIN); ?></td>
                <td><?php echo !empty($features['can_publish']) ? esc_html__('Yes', WABE_TEXTDOMAIN) : esc_html__('No', WABE_TEXTDOMAIN); ?>
                </td>
            </tr>
            <tr>
                <td><?php esc_html_e('SEO', WABE_TEXTDOMAIN); ?></td>
                <td><?php echo !empty($features['can_use_seo']) ? esc_html__('Yes', WABE_TEXTDOMAIN) : esc_html__('No', WABE_TEXTDOMAIN); ?>
                </td>
            </tr>
            <tr>
                <td><?php esc_html_e('Images', WABE_TEXTDOMAIN); ?></td>
                <td><?php echo !empty($features['can_use_images']) ? esc_html__('Yes', WABE_TEXTDOMAIN) : esc_html__('No', WABE_TEXTDOMAIN); ?>
                </td>
            </tr>
            <tr>
                <td><?php esc_html_e('Topic Generator', WABE_TEXTDOMAIN); ?></td>
                <td><?php echo !empty($features['can_use_topic_generator']) ? esc_html__('Yes', WABE_TEXTDOMAIN) : esc_html__('No', WABE_TEXTDOMAIN); ?>
                </td>
            </tr>
            <tr>
                <td><?php esc_html_e('Internal Links', WABE_TEXTDOMAIN); ?></td>
                <td><?php echo !empty($features['can_use_internal_links']) ? esc_html__('Yes', WABE_TEXTDOMAIN) : esc_html__('No', WABE_TEXTDOMAIN); ?>
                </td>
            </tr>
            <tr>
                <td><?php esc_html_e('Outline Generator', WABE_TEXTDOMAIN); ?></td>
                <td><?php echo !empty($features['can_use_outline_generator']) ? esc_html__('Yes', WABE_TEXTDOMAIN) : esc_html__('No', WABE_TEXTDOMAIN); ?>
                </td>
            </tr>
        </tbody>
    </table>
</div>
