<?php
if (!defined('ABSPATH')) exit;

$opt = is_array($this->options ?? null) ? $this->options : [];

$plan = method_exists($this, 'get_plan') ? $this->get_plan() : 'free';
$plan_label = method_exists($this, 'get_plan_label') ? $this->get_plan_label($plan) : ucfirst((string)$plan);
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

$heading_count_max = method_exists($this, 'plan_heading_count_max') ? (int)$this->plan_heading_count_max() : 1;
$weekly_posts_max = method_exists($this, 'plan_weekly_posts_max') ? (int)$this->plan_weekly_posts_max() : 1;

$can_publish = method_exists($this, 'plan_can_publish') ? (bool)$this->plan_can_publish() : false;
$can_use_images = method_exists($this, 'plan_can_use_images') ? (bool)$this->plan_can_use_images() : false;
$can_use_seo = method_exists($this, 'plan_can_use_seo') ? (bool)$this->plan_can_use_seo() : false;
$can_use_internal = method_exists($this, 'plan_can_use_internal_links') ? (bool)$this->plan_can_use_internal_links() : false;
$can_use_external = method_exists($this, 'plan_can_use_external_links') ? (bool)$this->plan_can_use_external_links() : false;
$can_use_prediction = method_exists($this, 'plan_can_use_topic_prediction') ? (bool)$this->plan_can_use_topic_prediction() : false;
$can_use_duplicate = method_exists($this, 'plan_can_use_duplicate_check') ? (bool)$this->plan_can_use_duplicate_check() : false;
$can_use_outline = method_exists($this, 'plan_can_use_outline_generator') ? (bool)$this->plan_can_use_outline_generator() : false;

$heading_count = max(1, min($heading_count_max, (int)($opt['heading_count'] ?? 1)));
$tone = $opt['tone'] ?? 'standard';
$post_status = $opt['post_status'] ?? 'draft';
$weekly_posts = max(1, min($weekly_posts_max, (int)($opt['weekly_posts'] ?? 1)));
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
$is_ready = method_exists($this, 'is_ready_to_post') ? (bool)$this->is_ready_to_post() : false;
$next_run_display = !empty($this->next_post_date) ? (string)$this->next_post_date : __('Not scheduled', WABE_TEXTDOMAIN);

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
            return '<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#dcfce7;color:#166534;font-size:12px;font-weight:600;">' . esc_html__('Available', WABE_TEXTDOMAIN) . '</span>';
        }

        return '<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#fee2e2;color:#991b1b;font-size:12px;font-weight:600;">' . esc_html__('Locked', WABE_TEXTDOMAIN) . '</span>';
    }
}

$upgrade_target = 'Advanced';
if (in_array($plan, ['advanced'], true)) {
    $upgrade_target = 'Pro';
} elseif (in_array($plan, ['pro'], true)) {
    $upgrade_target = 'Pro';
}

$tone_options = wabe_settings_tone_options();
$style_options = wabe_settings_style_options();
?>
<div class="wrap">
    <h1><?php echo esc_html__('WP AI Blog Engine Settings', WABE_TEXTDOMAIN); ?></h1>

    <?php if (!empty($_GET['wabe_message'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html(wp_unslash($_GET['wabe_message'])); ?></p>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:start;max-width:1400px;">
        <div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="wabe_save_settings">
                <?php wp_nonce_field('wabe_save_settings', 'wabe_settings_nonce'); ?>

                <div class="postbox" style="padding:20px;margin-bottom:24px;">
                    <h2 class="hndle" style="margin:0 0 16px 0;">
                        <?php echo esc_html__('AI Provider', WABE_TEXTDOMAIN); ?></h2>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><?php echo esc_html__('AI Provider', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <select name="ai_provider">
                                        <option value="openai" <?php selected($ai_provider, 'openai'); ?>>OpenAI
                                        </option>
                                        <option value="gemini" <?php selected($ai_provider, 'gemini'); ?>>Gemini
                                        </option>
                                    </select>
                                    <p class="description">
                                        <?php echo esc_html__('Choose which AI provider will be used for article generation.', WABE_TEXTDOMAIN); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php echo esc_html__('OpenAI API Key', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <input type="text" name="openai_api_key"
                                        value="<?php echo esc_attr($openai_masked); ?>" class="regular-text"
                                        autocomplete="off" placeholder="sk-...">
                                    <p class="description">
                                        <?php echo esc_html__('If a masked value is displayed, leaving it as-is will keep the current key.', WABE_TEXTDOMAIN); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php echo esc_html__('OpenAI Model', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <select name="openai_model">
                                        <option value="gpt-4.1-mini" <?php selected($openai_model, 'gpt-4.1-mini'); ?>>
                                            gpt-4.1-mini</option>
                                        <option value="gpt-4.1" <?php selected($openai_model, 'gpt-4.1'); ?>>gpt-4.1
                                        </option>
                                        <option value="gpt-5-mini" <?php selected($openai_model, 'gpt-5-mini'); ?>>
                                            gpt-5-mini</option>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php echo esc_html__('Gemini API Key', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <input type="text" name="gemini_api_key"
                                        value="<?php echo esc_attr($gemini_masked); ?>" class="regular-text"
                                        autocomplete="off" placeholder="AIza...">
                                    <p class="description">
                                        <?php echo esc_html__('If a masked value is displayed, leaving it as-is will keep the current key.', WABE_TEXTDOMAIN); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php echo esc_html__('Gemini Model', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <select name="gemini_model">
                                        <option value="gemini-2.5-flash"
                                            <?php selected($gemini_model, 'gemini-2.5-flash'); ?>>gemini-2.5-flash
                                        </option>
                                        <option value="gemini-2.5-pro"
                                            <?php selected($gemini_model, 'gemini-2.5-pro'); ?>>gemini-2.5-pro</option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="postbox" style="padding:20px;margin-bottom:24px;">
                    <h2 class="hndle" style="margin:0 0 16px 0;">
                        <?php echo esc_html__('Generation Settings', WABE_TEXTDOMAIN); ?></h2>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Heading Count', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <select name="heading_count">
                                        <?php for ($i = 1; $i <= $heading_count_max; $i++) : ?>
                                            <option value="<?php echo esc_attr((string)$i); ?>"
                                                <?php selected($heading_count, $i); ?>>
                                                <?php echo esc_html((string)$i); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <p class="description">
                                        <?php
                                        echo esc_html(
                                            sprintf(
                                                __('Current plan allows 1 to %d headings.', WABE_TEXTDOMAIN),
                                                (int)$heading_count_max
                                            )
                                        );
                                        ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php echo esc_html__('Tone', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <select name="tone">
                                        <?php foreach ($tone_options as $tone_key => $tone_label) : ?>
                                            <option value="<?php echo esc_attr($tone_key); ?>"
                                                <?php selected($tone, $tone_key); ?>>
                                                <?php echo esc_html($tone_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php echo esc_html__('Post Status', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <select name="post_status">
                                        <option value="draft" <?php selected($post_status, 'draft'); ?>>
                                            <?php echo esc_html__('Draft', WABE_TEXTDOMAIN); ?>
                                        </option>
                                        <option value="publish" <?php selected($post_status, 'publish'); ?>
                                            <?php disabled(!$can_publish); ?>>
                                            <?php echo esc_html__('Publish', WABE_TEXTDOMAIN); ?>
                                        </option>
                                    </select>
                                    <?php if (!$can_publish) : ?>
                                        <p class="description"><?php echo esc_html(wabe_settings_lock_text('Advanced')); ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php echo esc_html__('Weekly Posts', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <select name="weekly_posts">
                                        <?php for ($i = 1; $i <= $weekly_posts_max; $i++) : ?>
                                            <option value="<?php echo esc_attr((string)$i); ?>"
                                                <?php selected($weekly_posts, $i); ?>>
                                                <?php echo esc_html((string)$i); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <p class="description">
                                        <?php
                                        echo esc_html(
                                            sprintf(
                                                __('Current plan allows 1 to %d automatic posts per week.', WABE_TEXTDOMAIN),
                                                (int)$weekly_posts_max
                                            )
                                        );
                                        ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <?php echo esc_html__('Enable Auto Posting Schedule', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="schedule_enabled" value="1"
                                            <?php checked($schedule_enabled); ?>>
                                        <?php echo esc_html__('Enable automatic generation schedule', WABE_TEXTDOMAIN); ?>
                                    </label>
                                    <p class="description">
                                        <?php echo esc_html__('WordPress Cron runs when your WordPress site is accessed. On low-traffic sites, automatic posting may be delayed. For stable operation, setting a real server cron is recommended.', WABE_TEXTDOMAIN); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php echo esc_html__('Next Scheduled Run', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <strong><?php echo esc_html($next_run_display); ?></strong>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php echo esc_html__('Ready Status', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <strong><?php echo esc_html($is_ready ? __('Ready', WABE_TEXTDOMAIN) : __('API key required', WABE_TEXTDOMAIN)); ?></strong>
                                    <p class="description">
                                        <?php echo esc_html__('The selected provider must have a valid API key saved before generation can run.', WABE_TEXTDOMAIN); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="postbox" style="padding:20px;margin-bottom:24px;">
                    <h2 class="hndle" style="margin:0 0 16px 0;">
                        <?php echo esc_html__('Content Settings', WABE_TEXTDOMAIN); ?></h2>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Author Name', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <input type="text" name="author_name"
                                        value="<?php echo esc_attr($opt['author_name'] ?? ''); ?>" class="regular-text">
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php echo esc_html__('Site Context', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <textarea name="site_context" rows="5"
                                        class="large-text"><?php echo esc_textarea($opt['site_context'] ?? ''); ?></textarea>
                                    <p class="description">
                                        <?php echo esc_html__('Describe the purpose, audience, and direction of the site so the AI can generate more suitable articles.', WABE_TEXTDOMAIN); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php echo esc_html__('Writing Rules', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <textarea name="writing_rules" rows="5"
                                        class="large-text"><?php echo esc_textarea($opt['writing_rules'] ?? ''); ?></textarea>
                                    <p class="description">
                                        <?php echo esc_html__('Example: sentence length, tone policy, forbidden expressions, CTA style, formatting policy.', WABE_TEXTDOMAIN); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="postbox" style="padding:20px;margin-bottom:24px;">
                    <h2 class="hndle" style="margin:0 0 16px 0;">
                        <?php echo esc_html__('Pro / Advanced Features', WABE_TEXTDOMAIN); ?></h2>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <?php echo esc_html__('Featured Image Generation', WABE_TEXTDOMAIN); ?>
                                    <div style="margin-top:6px;">
                                        <?php echo wp_kses_post(wabe_settings_feature_badge($can_use_images)); ?></div>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enable_featured_image" value="1"
                                            <?php checked($enable_featured_image); ?>
                                            <?php disabled(!$can_use_images); ?>>
                                        <?php echo esc_html__('Automatically generate a featured image', WABE_TEXTDOMAIN); ?>
                                    </label>

                                    <div style="margin-top:10px;">
                                        <select name="image_style" <?php disabled(!$can_use_images); ?>>
                                            <?php foreach ($style_options as $style_key => $style_label) : ?>
                                                <option value="<?php echo esc_attr($style_key); ?>"
                                                    <?php selected($image_style, $style_key); ?>>
                                                    <?php echo esc_html($style_label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <?php if (!$can_use_images) : ?>
                                        <p class="description"><?php echo esc_html(wabe_settings_lock_text('Advanced')); ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <?php echo esc_html__('SEO Support', WABE_TEXTDOMAIN); ?>
                                    <div style="margin-top:6px;">
                                        <?php echo wp_kses_post(wabe_settings_feature_badge($can_use_seo)); ?></div>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enable_seo" value="1"
                                            <?php checked($enable_seo); ?> <?php disabled(!$can_use_seo); ?>>
                                        <?php echo esc_html__('Enable SEO metadata support', WABE_TEXTDOMAIN); ?>
                                    </label>

                                    <div style="margin-top:10px;">
                                        <input type="text" name="seo_keyword"
                                            value="<?php echo esc_attr($opt['seo_keyword'] ?? ''); ?>"
                                            class="regular-text" <?php disabled(!$can_use_seo); ?>>
                                    </div>

                                    <?php if (!$can_use_seo) : ?>
                                        <p class="description"><?php echo esc_html(wabe_settings_lock_text('Advanced')); ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <?php echo esc_html__('Internal Link Support', WABE_TEXTDOMAIN); ?>
                                    <div style="margin-top:6px;">
                                        <?php echo wp_kses_post(wabe_settings_feature_badge($can_use_internal)); ?>
                                    </div>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enable_internal_links" value="1"
                                            <?php checked($enable_internal_links); ?>
                                            <?php disabled(!$can_use_internal); ?>>
                                        <?php echo esc_html__('Insert internal link guidance into generated content', WABE_TEXTDOMAIN); ?>
                                    </label>

                                    <div style="margin-top:10px;">
                                        <input type="url" name="internal_link_url"
                                            value="<?php echo esc_attr($opt['internal_link_url'] ?? ''); ?>"
                                            class="regular-text" placeholder="https://example.com/internal-page"
                                            <?php disabled(!$can_use_internal); ?>>
                                    </div>

                                    <?php if (!$can_use_internal) : ?>
                                        <p class="description"><?php echo esc_html(wabe_settings_lock_text('Pro')); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <?php echo esc_html__('External Link Support', WABE_TEXTDOMAIN); ?>
                                    <div style="margin-top:6px;">
                                        <?php echo wp_kses_post(wabe_settings_feature_badge($can_use_external)); ?>
                                    </div>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enable_external_links" value="1"
                                            <?php checked($enable_external_links); ?>
                                            <?php disabled(!$can_use_external); ?>>
                                        <?php echo esc_html__('Append an external reference link block', WABE_TEXTDOMAIN); ?>
                                    </label>

                                    <div style="margin-top:10px;">
                                        <input type="url" name="external_link_url"
                                            value="<?php echo esc_attr($opt['external_link_url'] ?? ''); ?>"
                                            class="regular-text" placeholder="https://example.com/reference"
                                            <?php disabled(!$can_use_external); ?>>
                                    </div>

                                    <?php if (!$can_use_external) : ?>
                                        <p class="description"><?php echo esc_html(wabe_settings_lock_text('Pro')); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <?php echo esc_html__('Duplicate Check', WABE_TEXTDOMAIN); ?>
                                    <div style="margin-top:6px;">
                                        <?php echo wp_kses_post(wabe_settings_feature_badge($can_use_duplicate)); ?>
                                    </div>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enable_duplicate_check" value="1"
                                            <?php checked($enable_duplicate_check); ?>
                                            <?php disabled(!$can_use_duplicate); ?>>
                                        <?php echo esc_html__('Skip generation when a similar post already exists', WABE_TEXTDOMAIN); ?>
                                    </label>

                                    <?php if (!$can_use_duplicate) : ?>
                                        <p class="description"><?php echo esc_html(wabe_settings_lock_text('Pro')); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <?php echo esc_html__('Topic Prediction', WABE_TEXTDOMAIN); ?>
                                    <div style="margin-top:6px;">
                                        <?php echo wp_kses_post(wabe_settings_feature_badge($can_use_prediction)); ?>
                                    </div>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enable_topic_prediction" value="1"
                                            <?php checked($enable_topic_prediction); ?>
                                            <?php disabled(!$can_use_prediction); ?>>
                                        <?php echo esc_html__('Predict topic ideas based on site trends and auto-fill the queue when needed', WABE_TEXTDOMAIN); ?>
                                    </label>

                                    <?php if (!$can_use_prediction) : ?>
                                        <p class="description"><?php echo esc_html(wabe_settings_lock_text('Pro')); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <?php echo esc_html__('Outline Generator', WABE_TEXTDOMAIN); ?>
                                    <div style="margin-top:6px;">
                                        <?php echo wp_kses_post(wabe_settings_feature_badge($can_use_outline)); ?></div>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enable_outline_generator" value="1"
                                            <?php checked($enable_outline_generator); ?>
                                            <?php disabled(!$can_use_outline); ?>>
                                        <?php echo esc_html__('Enable outline generation helper for article structure', WABE_TEXTDOMAIN); ?>
                                    </label>

                                    <?php if (!$can_use_outline) : ?>
                                        <p class="description"><?php echo esc_html(wabe_settings_lock_text('Pro')); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="postbox" style="padding:20px;margin-bottom:24px;">
                    <h2 class="hndle" style="margin:0 0 16px 0;"><?php echo esc_html__('License', WABE_TEXTDOMAIN); ?>
                    </h2>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><?php echo esc_html__('License Key', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <input type="text" name="license_key"
                                        value="<?php echo esc_attr($opt['license_key'] ?? ''); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('License Status', WABE_TEXTDOMAIN); ?></th>
                                <td><?php echo esc_html($license_status !== '' ? $license_status : 'free'); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Checked At', WABE_TEXTDOMAIN); ?></th>
                                <td><?php echo esc_html($license_checked_at !== '' ? $license_checked_at : '—'); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Expires At', WABE_TEXTDOMAIN); ?></th>
                                <td><?php echo esc_html($license_expires_at !== '' ? $license_expires_at : '—'); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Customer Email', WABE_TEXTDOMAIN); ?></th>
                                <td><?php echo esc_html($license_customer_email !== '' ? $license_customer_email : '—'); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wabe-license')); ?>">
                            <?php echo esc_html__('Open License Page', WABE_TEXTDOMAIN); ?>
                        </a>
                    </p>
                </div>

                <p>
                    <button type="submit" class="button button-primary button-large">
                        <?php echo esc_html__('Save Settings', WABE_TEXTDOMAIN); ?>
                    </button>
                </p>
            </form>
        </div>

        <div>
            <div class="postbox"
                style="padding:20px;margin-bottom:24px;border-top:4px solid <?php echo esc_attr($plan_color); ?>;">
                <h2 class="hndle" style="margin:0 0 14px 0;"><?php echo esc_html__('Current Plan', WABE_TEXTDOMAIN); ?>
                </h2>

                <p style="margin:0 0 10px 0;">
                    <strong style="font-size:18px;"><?php echo esc_html($plan_label); ?></strong>
                </p>

                <ul style="margin:0;padding-left:18px;">
                    <li><?php echo esc_html(sprintf(__('Weekly auto posts: up to %d', WABE_TEXTDOMAIN), (int)$weekly_posts_max)); ?>
                    </li>
                    <li><?php echo esc_html(sprintf(__('Heading count: up to %d', WABE_TEXTDOMAIN), (int)$heading_count_max)); ?>
                    </li>
                    <li><?php echo esc_html__('Topic queue capacity: 10', WABE_TEXTDOMAIN); ?></li>
                    <li><?php echo esc_html(sprintf(__('Publish available: %s', WABE_TEXTDOMAIN), wabe_settings_bool_label($can_publish))); ?>
                    </li>
                    <li><?php echo esc_html(sprintf(__('Image generation: %s', WABE_TEXTDOMAIN), wabe_settings_bool_label($can_use_images))); ?>
                    </li>
                    <li><?php echo esc_html(sprintf(__('SEO support: %s', WABE_TEXTDOMAIN), wabe_settings_bool_label($can_use_seo))); ?>
                    </li>
                    <li><?php echo esc_html(sprintf(__('Internal links: %s', WABE_TEXTDOMAIN), wabe_settings_bool_label($can_use_internal))); ?>
                    </li>
                    <li><?php echo esc_html(sprintf(__('External links: %s', WABE_TEXTDOMAIN), wabe_settings_bool_label($can_use_external))); ?>
                    </li>
                    <li><?php echo esc_html(sprintf(__('Topic prediction: %s', WABE_TEXTDOMAIN), wabe_settings_bool_label($can_use_prediction))); ?>
                    </li>
                    <li><?php echo esc_html(sprintf(__('Duplicate check: %s', WABE_TEXTDOMAIN), wabe_settings_bool_label($can_use_duplicate))); ?>
                    </li>
                </ul>

                <?php if ($plan !== 'pro') : ?>
                    <p style="margin-top:14px;">
                        <a class="button button-secondary"
                            href="<?php echo esc_url(admin_url('admin.php?page=wabe-license')); ?>">
                            <?php echo esc_html(sprintf(__('Compare plans / Upgrade to %s', WABE_TEXTDOMAIN), $upgrade_target)); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>

            <div class="postbox" style="padding:20px;margin-bottom:24px;">
                <h2 class="hndle" style="margin:0 0 14px 0;"><?php echo esc_html__('Quick Actions', WABE_TEXTDOMAIN); ?>
                </h2>

                <p style="margin-bottom:12px;">
                    <?php echo esc_html(sprintf(__('Queued topics: %d / 10', WABE_TEXTDOMAIN), (int)$topics_count)); ?>
                </p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                    style="margin-bottom:12px;">
                    <input type="hidden" name="action" value="wabe_generate_now">
                    <?php wp_nonce_field('wabe_generate_now', 'wabe_generate_now_nonce'); ?>
                    <button type="submit" class="button button-primary"
                        <?php disabled(!$is_ready || $topics_count < 1); ?>>
                        <?php echo esc_html__('Generate Now', WABE_TEXTDOMAIN); ?>
                    </button>
                </form>

                <p class="description" style="margin-top:0;">
                    <?php echo esc_html__('If there is at least one topic in the queue, a post will be generated immediately using the selected AI provider.', WABE_TEXTDOMAIN); ?>
                </p>

                <p style="margin-top:16px;">
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wabe-topics')); ?>">
                        <?php echo esc_html__('Open Topics Page', WABE_TEXTDOMAIN); ?>
                    </a>
                </p>
            </div>

            <div class="postbox" style="padding:20px;margin-bottom:24px;">
                <h2 class="hndle" style="margin:0 0 14px 0;">
                    <?php echo esc_html__('Plan Comparison', WABE_TEXTDOMAIN); ?></h2>

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
                            <td><?php echo esc_html__('$12/mo  $79/yr  $199 lifetime', WABE_TEXTDOMAIN); ?></td>
                            <td><?php echo esc_html__('$24/mo  $159/yr  $399 lifetime', WABE_TEXTDOMAIN); ?></td>
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
                            <td><?php echo esc_html__('Publish mode', WABE_TEXTDOMAIN); ?></td>
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
                    </tbody>
                </table>
            </div>

            <div class="postbox" style="padding:20px;">
                <h2 class="hndle" style="margin:0 0 14px 0;">
                    <?php echo esc_html__('Important Note', WABE_TEXTDOMAIN); ?></h2>
                <p style="margin:0;">
                    <?php echo esc_html__('Automatic posting depends on WordPress Cron. If your site has low traffic, scheduled execution may be delayed until someone opens the site.', WABE_TEXTDOMAIN); ?>
                </p>
            </div>
        </div>
    </div>
</div>
