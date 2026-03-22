<?php
if (!defined('ABSPATH')) exit;

$opt = is_array($this->options ?? null) ? $this->options : [];
$plan = method_exists($this, 'get_plan') ? $this->get_plan() : 'free';
$plan_label = method_exists($this, 'get_plan_label') ? $this->get_plan_label($plan) : ucfirst((string) $plan);

$license = method_exists($this, 'get_license_data') ? $this->get_license_data() : [];
$license_status = sanitize_text_field($license['status'] ?? ($opt['license_status'] ?? 'free'));
$license_checked_at = sanitize_text_field($license['checked_at'] ?? ($opt['license_checked_at'] ?? ''));
$license_expires_at = sanitize_text_field($license['expires_at'] ?? ($opt['license_expires_at'] ?? ''));
$license_customer_email = sanitize_text_field($license['customer_email'] ?? ($opt['license_customer_email'] ?? ''));

$openai_masked = !empty($opt['openai_api_key']) ? $this->mask_api_key($opt['openai_api_key']) : '';
$gemini_masked = !empty($opt['gemini_api_key']) ? $this->mask_api_key($opt['gemini_api_key']) : '';

$ai_provider = $opt['ai_provider'] ?? 'openai';
$openai_model = $opt['openai_model'] ?? 'gpt-4.1';
$gemini_model = $opt['gemini_model'] ?? 'gemini-2.5-flash';

$heading_count_max = method_exists($this, 'plan_heading_count_max') ? (int) $this->plan_heading_count_max() : 1;
$weekly_posts_max = method_exists($this, 'plan_weekly_posts_max') ? (int) $this->plan_weekly_posts_max() : 1;

$can_publish = method_exists($this, 'plan_can_publish') ? (bool) $this->plan_can_publish() : false;
$can_use_images = method_exists($this, 'plan_can_use_images') ? (bool) $this->plan_can_use_images() : false;
$can_use_seo = method_exists($this, 'plan_can_use_seo') ? (bool) $this->plan_can_use_seo() : false;
$can_use_internal = method_exists($this, 'plan_can_use_internal_links') ? (bool) $this->plan_can_use_internal_links() : false;
$can_use_external = method_exists($this, 'plan_can_use_external_links') ? (bool) $this->plan_can_use_external_links() : false;
$can_use_prediction = method_exists($this, 'plan_can_use_topic_prediction') ? (bool) $this->plan_can_use_topic_prediction() : false;
$can_use_duplicate = method_exists($this, 'plan_can_use_duplicate_check') ? (bool) $this->plan_can_use_duplicate_check() : false;
$can_use_outline = method_exists($this, 'plan_can_use_outline_generator') ? (bool) $this->plan_can_use_outline_generator() : false;

$heading_count = max(1, min($heading_count_max, (int) ($opt['heading_count'] ?? 1)));
$tone = $opt['tone'] ?? 'standard';
$detail_level = $opt['detail_level'] ?? 'medium';
$generation_quality = $opt['generation_quality'] ?? 'high';
$post_status = $opt['post_status'] ?? 'draft';
$weekly_posts = max(1, min($weekly_posts_max, (int) ($opt['weekly_posts'] ?? 1)));
$image_style = $opt['image_style'] ?? 'modern';

$schedule_enabled = !empty($opt['schedule_enabled']);
$enable_featured_image = !empty($opt['enable_featured_image']) && $can_use_images;
$enable_seo = !empty($opt['enable_seo']) && $can_use_seo;
$enable_internal_links = !empty($opt['enable_internal_links']) && $can_use_internal;
$enable_external_links = !empty($opt['enable_external_links']) && $can_use_external;
$enable_topic_prediction = !empty($opt['enable_topic_prediction']) && $can_use_prediction;
$enable_duplicate_check = !empty($opt['enable_duplicate_check']) && $can_use_duplicate;
$enable_outline_generator = !empty($opt['enable_outline_generator']) && $can_use_outline;

$topics_count = is_array($opt['topics'] ?? null) ? count($opt['topics']) : 0;
$is_ready = method_exists($this, 'is_ready_to_post') ? (bool) $this->is_ready_to_post() : false;
$next_run_display = !empty($this->next_post_date) ? (string) $this->next_post_date : __('Not scheduled', WABE_TEXTDOMAIN);

$plan_colors = [
    'free'     => '#64748b',
    'advanced' => '#2563eb',
    'pro'      => '#7c3aed',
];
$plan_color = $plan_colors[$plan] ?? '#2563eb';

if (!function_exists('wabe_settings_bool_label')) {
    function wabe_settings_bool_label($value)
    {
        return $value ? __('Yes', WABE_TEXTDOMAIN) : __('No', WABE_TEXTDOMAIN);
    }
}

if (!function_exists('wabe_settings_tone_options')) {
    function wabe_settings_tone_options()
    {
        return [
            'standard' => __('Standard', WABE_TEXTDOMAIN),
            'polite'   => __('Polite', WABE_TEXTDOMAIN),
            'casual'   => __('Casual', WABE_TEXTDOMAIN),
        ];
    }
}

if (!function_exists('wabe_settings_style_options')) {
    function wabe_settings_style_options()
    {
        return [
            'modern'   => __('Modern', WABE_TEXTDOMAIN),
            'business' => __('Business', WABE_TEXTDOMAIN),
            'blog'     => __('Blog', WABE_TEXTDOMAIN),
            'tech'     => __('Tech', WABE_TEXTDOMAIN),
            'luxury'   => __('Luxury', WABE_TEXTDOMAIN),
            'natural'  => __('Natural', WABE_TEXTDOMAIN),
        ];
    }
}

if (!function_exists('wabe_settings_lock_text')) {
    function wabe_settings_lock_text($plan_text = '')
    {
        if ($plan_text !== '') {
            return sprintf(__('Locked. Upgrade to %s to use this feature.', WABE_TEXTDOMAIN), $plan_text);
        }
        return __('Locked.', WABE_TEXTDOMAIN);
    }
}

if (!function_exists('wabe_settings_feature_badge')) {
    function wabe_settings_feature_badge($enabled)
    {
        if ($enabled) {
            return '<span class="wabe-badge wabe-badge-success">' . esc_html__('Available', WABE_TEXTDOMAIN) . '</span>';
        }
        return '<span class="wabe-badge wabe-badge-muted">' . esc_html__('Locked', WABE_TEXTDOMAIN) . '</span>';
    }
}

$tone_options = wabe_settings_tone_options();
$style_options = wabe_settings_style_options();
?>

<div class="wrap">
    <h1><?php esc_html_e('WP AI Blog Engine Settings', WABE_TEXTDOMAIN); ?></h1>

    <?php if (!empty($_GET['message'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html(wp_unslash($_GET['message'])); ?></p>
        </div>
    <?php endif; ?>

    <div class="notice notice-info" style="margin-top:16px;">
        <p>
            <strong><?php esc_html_e('Current plan', WABE_TEXTDOMAIN); ?>:</strong>
            <span
                style="display:inline-block;padding:4px 10px;border-radius:999px;background:<?php echo esc_attr($plan_color); ?>15;color:<?php echo esc_attr($plan_color); ?>;font-weight:700;">
                <?php echo esc_html($plan_label); ?>
            </span>
        </p>
        <p>
            <strong><?php esc_html_e('License status', WABE_TEXTDOMAIN); ?>:</strong>
            <?php echo esc_html($license_status !== '' ? $license_status : '—'); ?>
            <?php if ($license_checked_at !== '') : ?>
                <span style="margin-left:10px;color:#64748b;">
                    <?php echo esc_html($license_checked_at); ?>
                </span>
            <?php endif; ?>
        </p>
        <?php if ($license_customer_email !== '') : ?>
            <p><strong><?php esc_html_e('Customer email', WABE_TEXTDOMAIN); ?>:</strong>
                <?php echo esc_html($license_customer_email); ?></p>
        <?php endif; ?>
        <?php if ($license_expires_at !== '') : ?>
            <p><strong><?php esc_html_e('Expires at', WABE_TEXTDOMAIN); ?>:</strong>
                <?php echo esc_html($license_expires_at); ?></p>
        <?php endif; ?>
    </div>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:20px;">
        <input type="hidden" name="action" value="wabe_save_settings">
        <?php wp_nonce_field('wabe_save_settings', 'wabe_settings_nonce'); ?>

        <div class="postbox" style="padding:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('API Settings', WABE_TEXTDOMAIN); ?></h2>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label
                            for="ai_provider"><?php esc_html_e('AI Provider', WABE_TEXTDOMAIN); ?></label></th>
                    <td>
                        <select name="ai_provider" id="ai_provider">
                            <option value="openai" <?php selected($ai_provider, 'openai'); ?>>OpenAI</option>
                            <option value="gemini" <?php selected($ai_provider, 'gemini'); ?>>Gemini</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label
                            for="openai_api_key"><?php esc_html_e('OpenAI API Key', WABE_TEXTDOMAIN); ?></label></th>
                    <td>
                        <input type="password" class="regular-text" name="openai_api_key" id="openai_api_key" value=""
                            placeholder="<?php echo esc_attr($openai_masked !== '' ? $openai_masked : __('Enter API key', WABE_TEXTDOMAIN)); ?>">
                        <p class="description"><?php esc_html_e('Get your OpenAI API key.', WABE_TEXTDOMAIN); ?> <a
                                href="https://platform.openai.com/api-keys" target="_blank"
                                rel="noopener noreferrer"><?php esc_html_e('OpenAI API Keys', WABE_TEXTDOMAIN); ?></a>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label
                            for="gemini_api_key"><?php esc_html_e('Gemini API Key', WABE_TEXTDOMAIN); ?></label></th>
                    <td>
                        <input type="password" class="regular-text" name="gemini_api_key" id="gemini_api_key" value=""
                            placeholder="<?php echo esc_attr($gemini_masked !== '' ? $gemini_masked : __('Enter API key', WABE_TEXTDOMAIN)); ?>">
                        <p class="description"><?php esc_html_e('Get your Gemini API key.', WABE_TEXTDOMAIN); ?> <a
                                href="https://aistudio.google.com/app/u/2/api-keys" target="_blank"
                                rel="noopener noreferrer"><?php esc_html_e('Google AI Studio', WABE_TEXTDOMAIN); ?></a>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label
                            for="openai_model"><?php esc_html_e('OpenAI Model', WABE_TEXTDOMAIN); ?></label></th>
                    <td>
                        <select name="openai_model" id="openai_model">
                            <option value="gpt-4.1-mini" <?php selected($openai_model, 'gpt-4.1-mini'); ?>>gpt-4.1-mini
                            </option>
                            <option value="gpt-4.1" <?php selected($openai_model, 'gpt-4.1'); ?>>gpt-4.1</option>
                            <option value="gpt-5-mini" <?php selected($openai_model, 'gpt-5-mini'); ?>>gpt-5-mini
                            </option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label
                            for="gemini_model"><?php esc_html_e('Gemini Model', WABE_TEXTDOMAIN); ?></label></th>
                    <td>
                        <select name="gemini_model" id="gemini_model">
                            <option value="gemini-2.5-flash" <?php selected($gemini_model, 'gemini-2.5-flash'); ?>>
                                gemini-2.5-flash</option>
                            <option value="gemini-2.5-pro" <?php selected($gemini_model, 'gemini-2.5-pro'); ?>>
                                gemini-2.5-pro</option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <div class="postbox" style="padding:20px;margin-top:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Writing Settings', WABE_TEXTDOMAIN); ?></h2>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="tone"><?php esc_html_e('Tone', WABE_TEXTDOMAIN); ?></label></th>
                    <td>
                        <select name="tone" id="tone">
                            <?php foreach ($tone_options as $tone_key => $tone_label) : ?>
                                <option value="<?php echo esc_attr($tone_key); ?>" <?php selected($tone, $tone_key); ?>>
                                    <?php echo esc_html($tone_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label
                            for="detail_level"><?php esc_html_e('Detail Level', WABE_TEXTDOMAIN); ?></label></th>
                    <td>
                        <select name="detail_level" id="detail_level">
                            <option value="low" <?php selected($detail_level, 'low'); ?>>
                                <?php esc_html_e('Low', WABE_TEXTDOMAIN); ?></option>
                            <option value="medium" <?php selected($detail_level, 'medium'); ?>>
                                <?php esc_html_e('Medium', WABE_TEXTDOMAIN); ?></option>
                            <option value="high" <?php selected($detail_level, 'high'); ?>>
                                <?php esc_html_e('High', WABE_TEXTDOMAIN); ?></option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label
                            for="generation_quality"><?php esc_html_e('Generation Quality', WABE_TEXTDOMAIN); ?></label>
                    </th>
                    <td>
                        <select name="generation_quality" id="generation_quality">
                            <option value="fast" <?php selected($generation_quality, 'fast'); ?>>
                                <?php esc_html_e('Fast', WABE_TEXTDOMAIN); ?></option>
                            <option value="high" <?php selected($generation_quality, 'high'); ?>>
                                <?php esc_html_e('High', WABE_TEXTDOMAIN); ?></option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label
                            for="author_name"><?php esc_html_e('Author Name', WABE_TEXTDOMAIN); ?></label></th>
                    <td>
                        <input type="text" class="regular-text" name="author_name" id="author_name"
                            value="<?php echo esc_attr($opt['author_name'] ?? ''); ?>">
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label
                            for="site_context"><?php esc_html_e('Site Context', WABE_TEXTDOMAIN); ?></label></th>
                    <td>
                        <textarea name="site_context" id="site_context" class="large-text"
                            rows="6"><?php echo esc_textarea(class_exists('WABE_Utils') ? WABE_Utils::wabe_maybe_base64_decode($opt['site_context'] ?? '') : ($opt['site_context'] ?? '')); ?></textarea>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label
                            for="writing_rules"><?php esc_html_e('Writing Rules', WABE_TEXTDOMAIN); ?></label></th>
                    <td>
                        <textarea name="writing_rules" id="writing_rules" class="large-text"
                            rows="6"><?php echo esc_textarea(class_exists('WABE_Utils') ? WABE_Utils::wabe_maybe_base64_decode($opt['writing_rules'] ?? '') : ($opt['writing_rules'] ?? '')); ?></textarea>
                    </td>
                </tr>
            </table>
        </div>

        <div class="postbox" style="padding:20px;margin-top:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Feature Settings', WABE_TEXTDOMAIN); ?></h2>

            <p style="margin-bottom:16px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=wabe-license')); ?>"
                    class="button button-secondary">
                    <?php esc_html_e('Open License Page', WABE_TEXTDOMAIN); ?>
                </a>
                <span style="margin-left:10px;color:#64748b;">
                    <?php esc_html_e('License key can now be entered on the License page.', WABE_TEXTDOMAIN); ?>
                </span>
            </p>

            <table class="widefat striped" style="max-width:900px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Feature', WABE_TEXTDOMAIN); ?></th>
                        <th><?php esc_html_e('Status', WABE_TEXTDOMAIN); ?></th>
                        <th><?php esc_html_e('Setting', WABE_TEXTDOMAIN); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php esc_html_e('Post Status', WABE_TEXTDOMAIN); ?></td>
                        <td><?php echo wp_kses_post(wabe_settings_feature_badge($can_publish)); ?></td>
                        <td>
                            <select name="post_status">
                                <option value="draft" <?php selected($post_status, 'draft'); ?>>
                                    <?php esc_html_e('Draft', WABE_TEXTDOMAIN); ?></option>
                                <option value="publish" <?php selected($post_status, 'publish'); ?>
                                    <?php disabled(!$can_publish); ?>><?php esc_html_e('Publish', WABE_TEXTDOMAIN); ?>
                                </option>
                            </select>
                            <?php if (!$can_publish) : ?>
                                <p class="description"><?php echo esc_html(wabe_settings_lock_text('Advanced')); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <td><?php esc_html_e('Weekly Posts', WABE_TEXTDOMAIN); ?></td>
                        <td><?php echo esc_html($weekly_posts_max); ?></td>
                        <td>
                            <input type="number" min="1" max="<?php echo esc_attr($weekly_posts_max); ?>"
                                name="weekly_posts" value="<?php echo esc_attr($weekly_posts); ?>">
                        </td>
                    </tr>

                    <tr>
                        <td><?php esc_html_e('Featured Image', WABE_TEXTDOMAIN); ?></td>
                        <td><?php echo wp_kses_post(wabe_settings_feature_badge($can_use_images)); ?></td>
                        <td>
                            <label><input type="checkbox" name="enable_featured_image" value="1"
                                    <?php checked($enable_featured_image); ?> <?php disabled(!$can_use_images); ?>>
                                <?php esc_html_e('Enable', WABE_TEXTDOMAIN); ?></label>
                            <?php if (!$can_use_images) : ?>
                                <p class="description"><?php echo esc_html(wabe_settings_lock_text('Advanced')); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <td><?php esc_html_e('Image Style', WABE_TEXTDOMAIN); ?></td>
                        <td><?php echo wp_kses_post(wabe_settings_feature_badge($can_use_images)); ?></td>
                        <td>
                            <select name="image_style" <?php disabled(!$can_use_images); ?>>
                                <?php foreach ($style_options as $style_key => $style_label) : ?>
                                    <option value="<?php echo esc_attr($style_key); ?>"
                                        <?php selected($image_style, $style_key); ?>>
                                        <?php echo esc_html($style_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <td><?php esc_html_e('SEO', WABE_TEXTDOMAIN); ?></td>
                        <td><?php echo wp_kses_post(wabe_settings_feature_badge($can_use_seo)); ?></td>
                        <td>
                            <label><input type="checkbox" name="enable_seo" value="1" <?php checked($enable_seo); ?>
                                    <?php disabled(!$can_use_seo); ?>>
                                <?php esc_html_e('Enable', WABE_TEXTDOMAIN); ?></label>
                        </td>
                    </tr>

                    <tr>
                        <td><?php esc_html_e('Internal Links', WABE_TEXTDOMAIN); ?></td>
                        <td><?php echo wp_kses_post(wabe_settings_feature_badge($can_use_internal)); ?></td>
                        <td>
                            <label><input type="checkbox" name="enable_internal_links" value="1"
                                    <?php checked($enable_internal_links); ?> <?php disabled(!$can_use_internal); ?>>
                                <?php esc_html_e('Enable', WABE_TEXTDOMAIN); ?></label>
                        </td>
                    </tr>

                    <tr>
                        <td><?php esc_html_e('External Links', WABE_TEXTDOMAIN); ?></td>
                        <td><?php echo wp_kses_post(wabe_settings_feature_badge($can_use_external)); ?></td>
                        <td>
                            <label><input type="checkbox" name="enable_external_links" value="1"
                                    <?php checked($enable_external_links); ?> <?php disabled(!$can_use_external); ?>>
                                <?php esc_html_e('Enable', WABE_TEXTDOMAIN); ?></label>
                        </td>
                    </tr>

                    <tr>
                        <td><?php esc_html_e('Topic Prediction', WABE_TEXTDOMAIN); ?></td>
                        <td><?php echo wp_kses_post(wabe_settings_feature_badge($can_use_prediction)); ?></td>
                        <td>
                            <label><input type="checkbox" name="enable_topic_prediction" value="1"
                                    <?php checked($enable_topic_prediction); ?>
                                    <?php disabled(!$can_use_prediction); ?>>
                                <?php esc_html_e('Enable', WABE_TEXTDOMAIN); ?></label>
                        </td>
                    </tr>

                    <tr>
                        <td><?php esc_html_e('Duplicate Check', WABE_TEXTDOMAIN); ?></td>
                        <td><?php echo wp_kses_post(wabe_settings_feature_badge($can_use_duplicate)); ?></td>
                        <td>
                            <label><input type="checkbox" name="enable_duplicate_check" value="1"
                                    <?php checked($enable_duplicate_check); ?> <?php disabled(!$can_use_duplicate); ?>>
                                <?php esc_html_e('Enable', WABE_TEXTDOMAIN); ?></label>
                        </td>
                    </tr>

                    <tr>
                        <td><?php esc_html_e('Outline Generator', WABE_TEXTDOMAIN); ?></td>
                        <td><?php echo wp_kses_post(wabe_settings_feature_badge($can_use_outline)); ?></td>
                        <td>
                            <label><input type="checkbox" name="enable_outline_generator" value="1"
                                    <?php checked($enable_outline_generator); ?> <?php disabled(!$can_use_outline); ?>>
                                <?php esc_html_e('Enable', WABE_TEXTDOMAIN); ?></label>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="postbox" style="padding:20px;margin-top:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Schedule', WABE_TEXTDOMAIN); ?></h2>
            <p>
                <label>
                    <input type="checkbox" name="schedule_enabled" value="1" <?php checked($schedule_enabled); ?>>
                    <?php esc_html_e('Enable automatic posting schedule', WABE_TEXTDOMAIN); ?>
                </label>
            </p>
            <p class="description">
                <?php esc_html_e('Automatic posting in WordPress may not run unless your site is accessed. For stable operation, consider setting a real server cron.', WABE_TEXTDOMAIN); ?>
            </p>
            <p><strong><?php esc_html_e('Next run', WABE_TEXTDOMAIN); ?>:</strong>
                <?php echo esc_html($next_run_display); ?></p>
            <p><strong><?php esc_html_e('Topics count', WABE_TEXTDOMAIN); ?>:</strong>
                <?php echo esc_html((string) $topics_count); ?> / 10</p>
            <p><strong><?php esc_html_e('Ready to generate', WABE_TEXTDOMAIN); ?>:</strong>
                <?php echo esc_html(wabe_settings_bool_label($is_ready)); ?></p>
            <div class="postbox" style="padding:20px;margin-top:20px;">
                <h2 style="margin-top:0;"><?php esc_html_e('Generate Now', WABE_TEXTDOMAIN); ?></h2>

                <p style="margin-bottom:12px;">
                    <?php esc_html_e('Generate a post immediately using your current settings and queued topics.', WABE_TEXTDOMAIN); ?>
                </p>

                <?php if (!$is_ready) : ?>
                    <div class="notice notice-warning inline">
                        <p><?php esc_html_e('Generation is not ready yet. Please check your API key, queued topics, and required settings.', WABE_TEXTDOMAIN); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                    style="margin-top:12px;">
                    <input type="hidden" name="action" value="wabe_generate_now">
                    <?php wp_nonce_field('wabe_generate_now', 'wabe_generate_now_nonce'); ?>

                    <p>
                        <button type="submit" class="button button-primary button-large" <?php disabled(!$is_ready); ?>>
                            <?php esc_html_e('Generate Now', WABE_TEXTDOMAIN); ?>
                        </button>
                    </p>
                </form>

                <p class="description">
                    <?php esc_html_e('This runs one immediate generation job outside the normal schedule.', WABE_TEXTDOMAIN); ?>
                </p>
            </div>
        </div>

        <?php submit_button(__('Save Settings', WABE_TEXTDOMAIN)); ?>
    </form>
</div>
