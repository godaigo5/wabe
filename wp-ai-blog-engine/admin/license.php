<?php
if (!defined('ABSPATH')) exit;

$plan = method_exists($this, 'get_plan') ? (string)$this->get_plan() : 'free';
$plan_label = method_exists($this, 'get_plan_label') ? $this->get_plan_label($plan) : ucfirst($plan);
$license = method_exists($this, 'get_license_data') ? $this->get_license_data() : [];

$status = sanitize_text_field($license['status'] ?? 'inactive');
$checked_at = sanitize_text_field($license['checked_at'] ?? '');
$license_key = sanitize_text_field($license['license_key'] ?? ($this->options['license_key'] ?? ''));

$weekly_posts_max = method_exists($this, 'plan_weekly_posts_max') ? (int)$this->plan_weekly_posts_max() : 1;
$heading_count_max = method_exists($this, 'plan_title_count_max') ? (int)$this->plan_title_count_max() : 1;
$can_publish = method_exists($this, 'plan_can_publish') ? (bool)$this->plan_can_publish() : false;
$can_use_seo = method_exists($this, 'plan_can_use_seo') ? (bool)$this->plan_can_use_seo() : false;
$can_use_images = method_exists($this, 'plan_can_use_images') ? (bool)$this->plan_can_use_images() : false;
$can_use_topic_generator = method_exists($this, 'plan_can_use_topic_prediction') ? (bool)$this->plan_can_use_topic_prediction() : false;
$can_use_internal_links = method_exists($this, 'plan_can_use_internal_links') ? (bool)$this->plan_can_use_internal_links() : false;
$can_use_outline_generator = method_exists($this, 'plan_can_use_outline_generator') ? (bool)$this->plan_can_use_outline_generator() : false;

if (!function_exists('wabe_license_bool_label')) {
    function wabe_license_bool_label($value)
    {
        return $value ? __('Yes', WABE_TEXTDOMAIN) : __('No', WABE_TEXTDOMAIN);
    }
}
?>
<div class="wrap">
    <h1><?php esc_html_e('License', WABE_TEXTDOMAIN); ?></h1>

    <div class="card" style="max-width:900px;padding:20px;">
        <h2 style="margin-top:0;"><?php esc_html_e('Current License Status', WABE_TEXTDOMAIN); ?></h2>

        <p>
            <?php
            printf(
                esc_html__('Current Plan: %s', WABE_TEXTDOMAIN),
                esc_html($plan_label)
            );
            ?>
        </p>

        <p>
            <?php
            printf(
                esc_html__('Status: %s', WABE_TEXTDOMAIN),
                esc_html(__($status, WABE_TEXTDOMAIN))
            );
            ?>
        </p>

        <p>
            <?php
            printf(
                esc_html__('Last Checked: %s', WABE_TEXTDOMAIN),
                esc_html($checked_at !== '' ? $checked_at : __('Not checked yet', WABE_TEXTDOMAIN))
            );
            ?>
        </p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:18px;">
            <input type="hidden" name="action" value="wabe_save_license_key">
            <?php wp_nonce_field('wabe_save_license_key', 'wabe_license_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="wabe_license_key"><?php esc_html_e('License Key', WABE_TEXTDOMAIN); ?></label>
                    </th>
                    <td>
                        <input id="wabe_license_key" type="text" name="wabe_license_key"
                            value="<?php echo esc_attr($license_key); ?>" class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Enter your license key and save it.', WABE_TEXTDOMAIN); ?></p>
                    </td>
                </tr>
            </table>

            <p class="submit" style="display:flex;gap:10px;flex-wrap:wrap;">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Save License Key', WABE_TEXTDOMAIN); ?>
                </button>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wabe_sync_license'), 'wabe_sync_license')); ?>"
                    class="button">
                    <?php esc_html_e('Sync License', WABE_TEXTDOMAIN); ?>
                </a>
            </p>
        </form>
    </div>

    <div class="card" style="max-width:900px;padding:20px;margin-top:20px;">
        <h2 style="margin-top:0;"><?php esc_html_e('Plan Features', WABE_TEXTDOMAIN); ?></h2>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Feature', WABE_TEXTDOMAIN); ?></th>
                    <th><?php esc_html_e('Value', WABE_TEXTDOMAIN); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php esc_html_e('Weekly Posts Max', WABE_TEXTDOMAIN); ?></td>
                    <td><?php echo esc_html($weekly_posts_max); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Heading Count Max', WABE_TEXTDOMAIN); ?></td>
                    <td><?php echo esc_html($heading_count_max); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Can Publish', WABE_TEXTDOMAIN); ?></td>
                    <td><?php echo esc_html(wabe_license_bool_label($can_publish)); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('SEO', WABE_TEXTDOMAIN); ?></td>
                    <td><?php echo esc_html(wabe_license_bool_label($can_use_seo)); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Images', WABE_TEXTDOMAIN); ?></td>
                    <td><?php echo esc_html(wabe_license_bool_label($can_use_images)); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Topic Generator', WABE_TEXTDOMAIN); ?></td>
                    <td><?php echo esc_html(wabe_license_bool_label($can_use_topic_generator)); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Internal Links', WABE_TEXTDOMAIN); ?></td>
                    <td><?php echo esc_html(wabe_license_bool_label($can_use_internal_links)); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Outline Generator', WABE_TEXTDOMAIN); ?></td>
                    <td><?php echo esc_html(wabe_license_bool_label($can_use_outline_generator)); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
