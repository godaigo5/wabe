<?php
if (!defined('ABSPATH')) exit;

$opt = isset($this->options) && is_array($this->options) ? $this->options : [];

$license = method_exists($this, 'get_license_data') ? $this->get_license_data() : [];
$plan = method_exists($this, 'get_plan') ? $this->get_plan() : sanitize_key($opt['plan'] ?? 'free');
$plan_label = method_exists($this, 'get_plan_label') ? $this->get_plan_label($plan) : ucfirst((string) $plan);

$license_status = sanitize_text_field($license['status'] ?? ($opt['license_status'] ?? 'inactive'));
$license_checked_at = sanitize_text_field($license['checked_at'] ?? ($opt['license_checked_at'] ?? ''));
$license_customer_email = sanitize_text_field($license['customer_email'] ?? ($opt['license_customer_email'] ?? ''));

$ai_provider = sanitize_key($opt['ai_provider'] ?? 'openai');
$openai_model = sanitize_text_field($opt['openai_model'] ?? 'gpt-4.1');
$gemini_model = sanitize_text_field($opt['gemini_model'] ?? 'gemini-2.5-flash');
$pollinations_image_model = sanitize_text_field($opt['pollinations_image_model'] ?? 'flux');

$heading_count = max(1, (int) ($opt['heading_count'] ?? 3));
$detail_level = sanitize_key($opt['detail_level'] ?? 'medium');
$generation_quality = sanitize_key($opt['generation_quality'] ?? 'high');
$tone = sanitize_key($opt['tone'] ?? 'standard');
$post_status = sanitize_key($opt['post_status'] ?? 'draft');
$weekly_posts = max(1, (int) ($opt['weekly_posts'] ?? 1));
$author_name = sanitize_text_field($opt['author_name'] ?? '');

$site_context = '';
if (class_exists('WABE_Utils') && method_exists('WABE_Utils', 'wabe_maybe_base64_decode')) {
    $site_context = (string) WABE_Utils::wabe_maybe_base64_decode($opt['site_context'] ?? '');
} else {
    $site_context = (string) ($opt['site_context'] ?? '');
}

$writing_rules = '';
if (class_exists('WABE_Utils') && method_exists('WABE_Utils', 'wabe_maybe_base64_decode')) {
    $writing_rules = (string) WABE_Utils::wabe_maybe_base64_decode($opt['writing_rules'] ?? '');
} else {
    $writing_rules = (string) ($opt['writing_rules'] ?? '');
}

$image_style = sanitize_key($opt['image_style'] ?? 'modern');

$schedule_enabled = !empty($opt['schedule_enabled']);
$enable_featured_image = !empty($opt['enable_featured_image']);
$enable_inline_unsplash = !empty($opt['enable_inline_unsplash']);
$enable_seo = !empty($opt['enable_seo']);
$enable_internal_links = !empty($opt['enable_internal_links']);
$enable_external_links = !empty($opt['enable_external_links']);
$enable_topic_prediction = !empty($opt['enable_topic_prediction']);
$enable_duplicate_check = !empty($opt['enable_duplicate_check']);
$enable_outline_generator = !empty($opt['enable_outline_generator']);

$openai_masked = !empty($opt['openai_api_key']) && method_exists($this, 'mask_api_key') ? $this->mask_api_key($opt['openai_api_key']) : '';
$gemini_masked = !empty($opt['gemini_api_key']) && method_exists($this, 'mask_api_key') ? $this->mask_api_key($opt['gemini_api_key']) : '';
$pollinations_masked = !empty($opt['pollinations_api_key']) && method_exists($this, 'mask_api_key') ? $this->mask_api_key($opt['pollinations_api_key']) : '';
$unsplash_masked = !empty($opt['unsplash_access_key']) && method_exists($this, 'mask_api_key') ? $this->mask_api_key($opt['unsplash_access_key']) : '';

$features = method_exists($this, 'get_plan_features') ? $this->get_plan_features() : [];
$weekly_posts_max = max(1, (int) ($features['weekly_posts_max'] ?? 1));
$heading_count_max = max(1, (int) ($features['heading_count_max'] ?? 3));

$can_publish = !empty($features['can_publish']);
$can_use_images = !empty($features['can_use_images']);
$can_use_seo = !empty($features['can_use_seo']);
$can_use_internal = !empty($features['can_use_internal_links']);
$can_use_external = !empty($features['can_use_external_links']);
$can_use_prediction = !empty($features['can_use_topic_prediction']);
$can_use_duplicate = !empty($features['can_use_duplicate_check']);
$can_use_outline = !empty($features['can_use_outline_generator']);

$weekly_posts = min($weekly_posts, $weekly_posts_max);
$heading_count = min($heading_count, $heading_count_max);

if ($post_status === 'publish' && !$can_publish) {
    $post_status = 'draft';
}

$topics = is_array($opt['topics'] ?? null) ? $opt['topics'] : [];
$topics_count = count($topics);

$is_ready = method_exists($this, 'is_ready_to_post') ? (bool) $this->is_ready_to_post() : true;
$next_run_display = !empty($this->next_post_date) ? (string) $this->next_post_date : __('Not scheduled', WABE_TEXTDOMAIN);

$plan_colors = [
    'free'     => '#64748b',
    'advanced' => '#2563eb',
    'pro'      => '#7c3aed',
];
$plan_color = $plan_colors[$plan] ?? '#2563eb';

if (!function_exists('wabe_settings_feature_badge')) {
    function wabe_settings_feature_badge($enabled)
    {
        if ($enabled) {
            return '<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#dcfce7;color:#166534;font-size:12px;font-weight:700;">' . esc_html__('Available', WABE_TEXTDOMAIN) . '</span>';
        }
        return '<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#fee2e2;color:#991b1b;font-size:12px;font-weight:700;">' . esc_html__('Locked', WABE_TEXTDOMAIN) . '</span>';
    }
}

if (!function_exists('wabe_settings_lock_text')) {
    function wabe_settings_lock_text($plan_name = '')
    {
        if ($plan_name !== '') {
            return sprintf(__('Locked. Upgrade to %s to use this feature.', WABE_TEXTDOMAIN), $plan_name);
        }
        return __('Locked.', WABE_TEXTDOMAIN);
    }
}
?>

<div class="wrap">
    <h1><?php echo esc_html__('WP AI Blog Engine Settings', WABE_TEXTDOMAIN); ?></h1>

    <?php if (!empty($_GET['updated'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Settings saved.', WABE_TEXTDOMAIN); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['generated'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('One post was generated.', WABE_TEXTDOMAIN); ?></p>
        </div>
    <?php endif; ?>

    <div style="margin:20px 0;padding:18px 20px;border:1px solid #e5e7eb;border-radius:14px;background:#fff;">
        <div style="display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;">
            <div>
                <div style="font-size:12px;color:#64748b;margin-bottom:6px;">
                    <?php echo esc_html__('Current Plan', WABE_TEXTDOMAIN); ?></div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <span
                        style="display:inline-block;padding:6px 12px;border-radius:999px;background:<?php echo esc_attr($plan_color); ?>;color:#fff;font-weight:700;">
                        <?php echo esc_html($plan_label); ?>
                    </span>
                    <span style="color:#475569;">
                        <?php echo esc_html__('License status:', WABE_TEXTDOMAIN); ?>
                        <strong><?php echo esc_html($license_status); ?></strong>
                    </span>
                </div>

                <?php if ($license_customer_email !== '') : ?>
                    <div style="margin-top:8px;color:#64748b;font-size:13px;">
                        <?php echo esc_html__('Customer email:', WABE_TEXTDOMAIN); ?>
                        <?php echo esc_html($license_customer_email); ?>
                    </div>
                <?php endif; ?>

                <?php if ($license_checked_at !== '') : ?>
                    <div style="margin-top:4px;color:#64748b;font-size:13px;">
                        <?php echo esc_html__('Last checked:', WABE_TEXTDOMAIN); ?>
                        <?php echo esc_html($license_checked_at); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div style="text-align:right;">
                <div style="font-size:12px;color:#64748b;margin-bottom:6px;">
                    <?php echo esc_html__('Posting Status', WABE_TEXTDOMAIN); ?></div>
                <div style="font-size:14px;color:#111827;margin-bottom:4px;">
                    <?php echo esc_html__('Queued topics:', WABE_TEXTDOMAIN); ?>
                    <strong><?php echo (int) $topics_count; ?></strong> / 10
                </div>
                <div style="font-size:14px;color:#111827;">
                    <?php echo esc_html__('Next run:', WABE_TEXTDOMAIN); ?>
                    <strong><?php echo esc_html($next_run_display); ?></strong>
                </div>
            </div>
        </div>
    </div>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('wabe_save_settings'); ?>
        <input type="hidden" name="action" value="wabe_save_settings">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px;">
                <h2 style="margin-top:0;"><?php echo esc_html__('AI Settings', WABE_TEXTDOMAIN); ?></h2>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label
                                for="wabe_ai_provider"><?php echo esc_html__('AI Provider', WABE_TEXTDOMAIN); ?></label>
                        </th>
                        <td>
                            <select id="wabe_ai_provider" name="<?php echo esc_attr(WABE_OPTION); ?>[ai_provider]">
                                <option value="openai" <?php selected($ai_provider, 'openai'); ?>>OpenAI</option>
                                <option value="gemini" <?php selected($ai_provider, 'gemini'); ?>>Gemini</option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label
                                for="wabe_openai_api_key"><?php echo esc_html__('OpenAI API Key', WABE_TEXTDOMAIN); ?></label>
                        </th>
                        <td>
                            <input id="wabe_openai_api_key" type="text" class="regular-text"
                                name="<?php echo esc_attr(WABE_OPTION); ?>[openai_api_key]"
                                value="<?php echo esc_attr($opt['openai_api_key'] ?? ''); ?>">
                            <?php if ($openai_masked !== '') : ?>
                                <p class="description"><?php echo esc_html__('Current:', WABE_TEXTDOMAIN); ?>
                                    <?php echo esc_html($openai_masked); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label
                                for="wabe_openai_model"><?php echo esc_html__('OpenAI Model', WABE_TEXTDOMAIN); ?></label>
                        </th>
                        <td>
                            <select id="wabe_openai_model" name="<?php echo esc_attr(WABE_OPTION); ?>[openai_model]">
                                <option value="gpt-4.1-mini" <?php selected($openai_model, 'gpt-4.1-mini'); ?>>
                                    gpt-4.1-mini</option>
                                <option value="gpt-4.1" <?php selected($openai_model, 'gpt-4.1'); ?>>gpt-4.1</option>
                                <option value="gpt-5-mini" <?php selected($openai_model, 'gpt-5-mini'); ?>>gpt-5-mini
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label
                                for="wabe_gemini_api_key"><?php echo esc_html__('Gemini API Key', WABE_TEXTDOMAIN); ?></label>
                        </th>
                        <td>
                            <input id="wabe_gemini_api_key" type="text" class="regular-text"
                                name="<?php echo esc_attr(WABE_OPTION); ?>[gemini_api_key]"
                                value="<?php echo esc_attr($opt['gemini_api_key'] ?? ''); ?>">
                            <?php if ($gemini_masked !== '') : ?>
                                <p class="description"><?php echo esc_html__('Current:', WABE_TEXTDOMAIN); ?>
                                    <?php echo esc_html($gemini_masked); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label
                                for="wabe_gemini_model"><?php echo esc_html__('Gemini Model', WABE_TEXTDOMAIN); ?></label>
                        </th>
                        <td>
                            <select id="wabe_gemini_model" name="<?php echo esc_attr(WABE_OPTION); ?>[gemini_model]">
                                <option value="gemini-2.5-flash" <?php selected($gemini_model, 'gemini-2.5-flash'); ?>>
                                    gemini-2.5-flash</option>
                                <option value="gemini-2.5-pro" <?php selected($gemini_model, 'gemini-2.5-pro'); ?>>
                                    gemini-2.5-pro</option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>

            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px;">
                <h2 style="margin-top:0;"><?php echo esc_html__('Image Settings', WABE_TEXTDOMAIN); ?></h2>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label
                                for="wabe_pollinations_api_key"><?php echo esc_html__('Pollinations API Key', WABE_TEXTDOMAIN); ?></label>
                        </th>
                        <td>
                            <input id="wabe_pollinations_api_key" type="text" class="regular-text"
                                name="<?php echo esc_attr(WABE_OPTION); ?>[pollinations_api_key]"
                                value="<?php echo esc_attr($opt['pollinations_api_key'] ?? ''); ?>">
                            <?php if ($pollinations_masked !== '') : ?>
                                <p class="description"><?php echo esc_html__('Current:', WABE_TEXTDOMAIN); ?>
                                    <?php echo esc_html($pollinations_masked); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label
                                for="wabe_pollinations_image_model"><?php echo esc_html__('Pollinations Image Model', WABE_TEXTDOMAIN); ?></label>
                        </th>
                        <td>
                            <input id="wabe_pollinations_image_model" type="text" class="regular-text"
                                name="<?php echo esc_attr(WABE_OPTION); ?>[pollinations_image_model]"
                                value="<?php echo esc_attr($pollinations_image_model); ?>">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label
                                for="wabe_unsplash_access_key"><?php echo esc_html__('Unsplash Access Key', WABE_TEXTDOMAIN); ?></label>
                        </th>
                        <td>
                            <input id="wabe_unsplash_access_key" type="text" class="regular-text"
                                name="<?php echo esc_attr(WABE_OPTION); ?>[unsplash_access_key]"
                                value="<?php echo esc_attr($opt['unsplash_access_key'] ?? ''); ?>">
                            <?php if ($unsplash_masked !== '') : ?>
                                <p class="description"><?php echo esc_html__('Current:', WABE_TEXTDOMAIN); ?>
                                    <?php echo esc_html($unsplash_masked); ?></p>
                            <?php endif; ?>
                            <p class="description">
                                <?php echo esc_html__('Used to insert inline article images from Unsplash. Please use your own API key.', WABE_TEXTDOMAIN); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label
                                for="wabe_image_style"><?php echo esc_html__('Image Style', WABE_TEXTDOMAIN); ?></label>
                        </th>
                        <td>
                            <select id="wabe_image_style" name="<?php echo esc_attr(WABE_OPTION); ?>[image_style]">
                                <option value="modern" <?php selected($image_style, 'modern'); ?>>
                                    <?php echo esc_html__('Modern', WABE_TEXTDOMAIN); ?></option>
                                <option value="business" <?php selected($image_style, 'business'); ?>>
                                    <?php echo esc_html__('Business', WABE_TEXTDOMAIN); ?></option>
                                <option value="blog" <?php selected($image_style, 'blog'); ?>>
                                    <?php echo esc_html__('Blog', WABE_TEXTDOMAIN); ?></option>
                                <option value="tech" <?php selected($image_style, 'tech'); ?>>
                                    <?php echo esc_html__('Tech', WABE_TEXTDOMAIN); ?></option>
                                <option value="luxury" <?php selected($image_style, 'luxury'); ?>>
                                    <?php echo esc_html__('Luxury', WABE_TEXTDOMAIN); ?></option>
                                <option value="natural" <?php selected($image_style, 'natural'); ?>>
                                    <?php echo esc_html__('Natural', WABE_TEXTDOMAIN); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php echo esc_html__('Featured Image', WABE_TEXTDOMAIN); ?></th>
                        <td>
                            <label>
                                <input type="checkbox"
                                    name="<?php echo esc_attr(WABE_OPTION); ?>[enable_featured_image]" value="1"
                                    <?php checked($enable_featured_image); ?> <?php disabled(!$can_use_images); ?>>
                                <?php echo esc_html__('Enable featured image generation', WABE_TEXTDOMAIN); ?>
                            </label>
                            <div style="margin-top:8px;"><?php echo wabe_settings_feature_badge($can_use_images); ?>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php echo esc_html__('Inline Unsplash Images', WABE_TEXTDOMAIN); ?></th>
                        <td>
                            <label>
                                <input type="checkbox"
                                    name="<?php echo esc_attr(WABE_OPTION); ?>[enable_inline_unsplash]" value="1"
                                    <?php checked($enable_inline_unsplash); ?> <?php disabled(!$can_use_images); ?>>
                                <?php echo esc_html__('Insert Unsplash images inside article body', WABE_TEXTDOMAIN); ?>
                            </label>
                            <p class="description">
                                <?php echo esc_html__('Free: 1 image / Advanced: multiple images / Pro: more images', WABE_TEXTDOMAIN); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div style="margin-top:20px;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px;">
            <h2 style="margin-top:0;"><?php echo esc_html__('Content Settings', WABE_TEXTDOMAIN); ?></h2>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label
                            for="wabe_heading_count"><?php echo esc_html__('Heading Count', WABE_TEXTDOMAIN); ?></label>
                    </th>
                    <td>
                        <input id="wabe_heading_count" type="number" min="1"
                            max="<?php echo (int) $heading_count_max; ?>"
                            name="<?php echo esc_attr(WABE_OPTION); ?>[heading_count]"
                            value="<?php echo (int) $heading_count; ?>">
                        <p class="description">
                            <?php echo esc_html__('Maximum allowed by current plan:', WABE_TEXTDOMAIN); ?>
                            <?php echo (int) $heading_count_max; ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="wabe_tone"><?php echo esc_html__('Tone', WABE_TEXTDOMAIN); ?></label>
                    </th>
                    <td>
                        <select id="wabe_tone" name="<?php echo esc_attr(WABE_OPTION); ?>[tone]">
                            <option value="standard" <?php selected($tone, 'standard'); ?>>
                                <?php echo esc_html__('Standard', WABE_TEXTDOMAIN); ?></option>
                            <option value="professional" <?php selected($tone, 'professional'); ?>>
                                <?php echo esc_html__('Professional', WABE_TEXTDOMAIN); ?></option>
                            <option value="casual" <?php selected($tone, 'casual'); ?>>
                                <?php echo esc_html__('Casual', WABE_TEXTDOMAIN); ?></option>
                            <option value="friendly" <?php selected($tone, 'friendly'); ?>>
                                <?php echo esc_html__('Friendly', WABE_TEXTDOMAIN); ?></option>
                            <option value="formal" <?php selected($tone, 'formal'); ?>>
                                <?php echo esc_html__('Formal', WABE_TEXTDOMAIN); ?></option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label
                            for="wabe_detail_level"><?php echo esc_html__('Detail Level', WABE_TEXTDOMAIN); ?></label>
                    </th>
                    <td>
                        <select id="wabe_detail_level" name="<?php echo esc_attr(WABE_OPTION); ?>[detail_level]">
                            <option value="low" <?php selected($detail_level, 'low'); ?>>
                                <?php echo esc_html__('Low', WABE_TEXTDOMAIN); ?></option>
                            <option value="medium" <?php selected($detail_level, 'medium'); ?>>
                                <?php echo esc_html__('Medium', WABE_TEXTDOMAIN); ?></option>
                            <option value="high" <?php selected($detail_level, 'high'); ?>>
                                <?php echo esc_html__('High', WABE_TEXTDOMAIN); ?></option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label
                            for="wabe_generation_quality"><?php echo esc_html__('Generation Quality', WABE_TEXTDOMAIN); ?></label>
                    </th>
                    <td>
                        <select id="wabe_generation_quality"
                            name="<?php echo esc_attr(WABE_OPTION); ?>[generation_quality]">
                            <option value="standard" <?php selected($generation_quality, 'standard'); ?>>
                                <?php echo esc_html__('Standard', WABE_TEXTDOMAIN); ?></option>
                            <option value="high" <?php selected($generation_quality, 'high'); ?>>
                                <?php echo esc_html__('High', WABE_TEXTDOMAIN); ?></option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="wabe_author_name"><?php echo esc_html__('Author Name', WABE_TEXTDOMAIN); ?></label>
                    </th>
                    <td>
                        <input id="wabe_author_name" type="text" class="regular-text"
                            name="<?php echo esc_attr(WABE_OPTION); ?>[author_name]"
                            value="<?php echo esc_attr($author_name); ?>">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label
                            for="wabe_site_context"><?php echo esc_html__('Site Context', WABE_TEXTDOMAIN); ?></label>
                    </th>
                    <td>
                        <textarea id="wabe_site_context" name="<?php echo esc_attr(WABE_OPTION); ?>[site_context]"
                            rows="6" class="large-text"><?php echo esc_textarea($site_context); ?></textarea>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label
                            for="wabe_writing_rules"><?php echo esc_html__('Writing Rules', WABE_TEXTDOMAIN); ?></label>
                    </th>
                    <td>
                        <textarea id="wabe_writing_rules" name="<?php echo esc_attr(WABE_OPTION); ?>[writing_rules]"
                            rows="6" class="large-text"><?php echo esc_textarea($writing_rules); ?></textarea>
                    </td>
                </tr>
            </table>
        </div>

        <div style="margin-top:20px;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px;">
            <h2 style="margin-top:0;"><?php echo esc_html__('Posting Settings', WABE_TEXTDOMAIN); ?></h2>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php echo esc_html__('Schedule', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr(WABE_OPTION); ?>[schedule_enabled]"
                                value="1" <?php checked($schedule_enabled); ?>>
                            <?php echo esc_html__('Enable automatic posting schedule', WABE_TEXTDOMAIN); ?>
                        </label>
                        <p class="description">
                            <?php echo esc_html__('WP-Cron runs when WordPress is accessed. If your site has low traffic, scheduled posting may be delayed.', WABE_TEXTDOMAIN); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label
                            for="wabe_weekly_posts"><?php echo esc_html__('Weekly Posts', WABE_TEXTDOMAIN); ?></label>
                    </th>
                    <td>
                        <input id="wabe_weekly_posts" type="number" min="1" max="<?php echo (int) $weekly_posts_max; ?>"
                            name="<?php echo esc_attr(WABE_OPTION); ?>[weekly_posts]"
                            value="<?php echo (int) $weekly_posts; ?>">
                        <p class="description">
                            <?php echo esc_html__('Maximum allowed by current plan:', WABE_TEXTDOMAIN); ?>
                            <?php echo (int) $weekly_posts_max; ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="wabe_post_status"><?php echo esc_html__('Post Status', WABE_TEXTDOMAIN); ?></label>
                    </th>
                    <td>
                        <select id="wabe_post_status" name="<?php echo esc_attr(WABE_OPTION); ?>[post_status]">
                            <option value="draft" <?php selected($post_status, 'draft'); ?>>
                                <?php echo esc_html__('Draft', WABE_TEXTDOMAIN); ?></option>
                            <option value="publish" <?php selected($post_status, 'publish'); ?>
                                <?php disabled(!$can_publish); ?>><?php echo esc_html__('Publish', WABE_TEXTDOMAIN); ?>
                            </option>
                        </select>
                        <?php if (!$can_publish) : ?>
                            <p class="description"><?php echo esc_html(wabe_settings_lock_text('Advanced')); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <div style="margin-top:20px;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px;">
            <h2 style="margin-top:0;"><?php echo esc_html__('Feature Settings', WABE_TEXTDOMAIN); ?></h2>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php echo esc_html__('SEO', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr(WABE_OPTION); ?>[enable_seo]" value="1"
                                <?php checked($enable_seo); ?> <?php disabled(!$can_use_seo); ?>>
                            <?php echo esc_html__('Enable SEO features', WABE_TEXTDOMAIN); ?>
                        </label>
                        <div style="margin-top:8px;"><?php echo wabe_settings_feature_badge($can_use_seo); ?></div>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('Internal Links', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr(WABE_OPTION); ?>[enable_internal_links]"
                                value="1" <?php checked($enable_internal_links); ?>
                                <?php disabled(!$can_use_internal); ?>>
                            <?php echo esc_html__('Enable internal link suggestions', WABE_TEXTDOMAIN); ?>
                        </label>
                        <div style="margin-top:8px;"><?php echo wabe_settings_feature_badge($can_use_internal); ?></div>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('External Links', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr(WABE_OPTION); ?>[enable_external_links]"
                                value="1" <?php checked($enable_external_links); ?>
                                <?php disabled(!$can_use_external); ?>>
                            <?php echo esc_html__('Enable external link suggestions', WABE_TEXTDOMAIN); ?>
                        </label>
                        <div style="margin-top:8px;"><?php echo wabe_settings_feature_badge($can_use_external); ?></div>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('Topic Prediction', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr(WABE_OPTION); ?>[enable_topic_prediction]"
                                value="1" <?php checked($enable_topic_prediction); ?>
                                <?php disabled(!$can_use_prediction); ?>>
                            <?php echo esc_html__('Enable topic prediction', WABE_TEXTDOMAIN); ?>
                        </label>
                        <div style="margin-top:8px;"><?php echo wabe_settings_feature_badge($can_use_prediction); ?>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('Duplicate Check', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr(WABE_OPTION); ?>[enable_duplicate_check]"
                                value="1" <?php checked($enable_duplicate_check); ?>
                                <?php disabled(!$can_use_duplicate); ?>>
                            <?php echo esc_html__('Enable duplicate topic check', WABE_TEXTDOMAIN); ?>
                        </label>
                        <div style="margin-top:8px;"><?php echo wabe_settings_feature_badge($can_use_duplicate); ?>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('Outline Generator', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr(WABE_OPTION); ?>[enable_outline_generator]"
                                value="1" <?php checked($enable_outline_generator); ?>
                                <?php disabled(!$can_use_outline); ?>>
                            <?php echo esc_html__('Enable outline generator', WABE_TEXTDOMAIN); ?>
                        </label>
                        <div style="margin-top:8px;"><?php echo wabe_settings_feature_badge($can_use_outline); ?></div>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary button-large">
                <?php echo esc_html__('Save Settings', WABE_TEXTDOMAIN); ?>
            </button>
        </p>
    </form>

    <div style="margin-top:20px;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px;">
        <h2 style="margin-top:0;"><?php echo esc_html__('Manual Generate', WABE_TEXTDOMAIN); ?></h2>

        <p style="margin-bottom:12px;color:#475569;">
            <?php echo esc_html__('Generate one post immediately from the first queued topic.', WABE_TEXTDOMAIN); ?>
        </p>

        <p style="font-size:13px;color:#64748b;margin:0 0 12px 0;">
            <?php echo esc_html__('Queued topics:', WABE_TEXTDOMAIN); ?>
            <strong><?php echo (int) $topics_count; ?></strong>
        </p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
            <?php wp_nonce_field('wabe_generate_now'); ?>
            <input type="hidden" name="action" value="wabe_generate_now">

            <button type="submit" class="button button-primary button-large"
                <?php disabled(!$is_ready || $topics_count < 1); ?>>
                <?php echo esc_html__('Generate Now', WABE_TEXTDOMAIN); ?>
            </button>
        </form>

        <?php if (!$is_ready) : ?>
            <p class="description" style="margin-top:10px;">
                <?php echo esc_html__('Set your API key first before generating a post.', WABE_TEXTDOMAIN); ?>
            </p>
        <?php elseif ($topics_count < 1) : ?>
            <p class="description" style="margin-top:10px;">
                <?php echo esc_html__('Add at least one topic to the queue before generating.', WABE_TEXTDOMAIN); ?>
            </p>
        <?php endif; ?>
    </div>
</div>
