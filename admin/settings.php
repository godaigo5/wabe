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
            return '<span style="display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;background:#dcfce7;color:#166534;">' . esc_html__('Available', WABE_TEXTDOMAIN) . '</span>';
        }

        return '<span style="display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;background:#fee2e2;color:#991b1b;">' . esc_html__('Locked', WABE_TEXTDOMAIN) . '</span>';
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
    <h1 style="margin-bottom:16px;"><?php esc_html_e('WP AI Blog Engine', WABE_TEXTDOMAIN); ?></h1>

    <?php if (!empty($_GET['message']) && $_GET['message'] === 'saved') : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Settings saved.', WABE_TEXTDOMAIN); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['message']) && $_GET['message'] === 'generated') : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Article generation completed.', WABE_TEXTDOMAIN); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['message']) && $_GET['message'] === 'error') : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php esc_html_e('An error occurred. Please check the logs.', WABE_TEXTDOMAIN); ?></p>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:20px;align-items:start;">
        <div>
            <form id="wabe-settings-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                style="display:flex;flex-direction:column;gap:20px;">
                <input type="hidden" name="action" value="wabe_save_settings">
                <?php wp_nonce_field('wabe_save_settings'); ?>

                <div
                    style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.04);">
                    <div
                        style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
                        <div>
                            <h2 style="margin:0 0 6px;font-size:20px;">
                                <?php esc_html_e('License & Plan', WABE_TEXTDOMAIN); ?></h2>
                            <p style="margin:0;color:#6b7280;">
                                <?php esc_html_e('Current license status and active plan.', WABE_TEXTDOMAIN); ?>
                            </p>
                        </div>
                        <span
                            style="display:inline-flex;align-items:center;padding:8px 14px;border-radius:999px;font-weight:700;color:#fff;background:<?php echo esc_attr($plan_color); ?>;">
                            <?php echo esc_html($plan_label); ?>
                        </span>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-top:20px;">
                        <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:16px;">
                            <div style="font-size:12px;color:#6b7280;margin-bottom:6px;">
                                <?php esc_html_e('License status', WABE_TEXTDOMAIN); ?></div>
                            <div style="font-size:16px;font-weight:700;">
                                <?php esc_html_e($license_status, WABE_TEXTDOMAIN); ?></div>
                        </div>
                        <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:16px;">
                            <div style="font-size:12px;color:#6b7280;margin-bottom:6px;">
                                <?php esc_html_e('Customer email', WABE_TEXTDOMAIN); ?></div>
                            <div style="font-size:16px;font-weight:700;word-break:break-word;">
                                <?php echo $license_customer_email !== '' ? esc_html($license_customer_email) : '—'; ?>
                            </div>
                        </div>
                        <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:16px;">
                            <div style="font-size:12px;color:#6b7280;margin-bottom:6px;">
                                <?php esc_html_e('Last checked', WABE_TEXTDOMAIN); ?></div>
                            <div style="font-size:16px;font-weight:700;">
                                <?php echo $license_checked_at !== '' ? esc_html($license_checked_at) : '—'; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($plan === 'free' && !empty($opt['license_key']) && $license_status === 'free') : ?>
                        <div
                            style="margin-top:16px;padding:14px 16px;border-radius:12px;background:#fff7ed;border:1px solid #fdba74;color:#9a3412;">
                            <strong><?php esc_html_e('Note:', WABE_TEXTDOMAIN); ?></strong>
                            <?php esc_html_e('Domain activation may not be completed yet. After registering your domain on the member page, click "Refresh License Information".', WABE_TEXTDOMAIN); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div
                    style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.04);">
                    <h2 style="margin:0 0 6px;font-size:20px;"><?php esc_html_e('AI Provider', WABE_TEXTDOMAIN); ?></h2>
                    <p style="margin:0 0 20px;color:#6b7280;">
                        <?php esc_html_e('Choose the provider and model used to generate articles.', WABE_TEXTDOMAIN); ?>
                    </p>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label
                                        for="wabe_ai_provider"><?php esc_html_e('Provider', WABE_TEXTDOMAIN); ?></label>
                                </th>
                                <td>
                                    <select id="wabe_ai_provider"
                                        name="<?php echo esc_attr(WABE_OPTION); ?>[ai_provider]">
                                        <option value="openai" <?php selected($ai_provider, 'openai'); ?>>OpenAI
                                        </option>
                                        <option value="gemini" <?php selected($ai_provider, 'gemini'); ?>>Gemini
                                        </option>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label
                                        for="wabe_openai_api_key"><?php esc_html_e('OpenAI API Key', WABE_TEXTDOMAIN); ?></label>
                                </th>
                                <td>
                                    <input id="wabe_openai_api_key" type="text" class="regular-text"
                                        name="<?php echo esc_attr(WABE_OPTION); ?>[openai_api_key]"
                                        value="<?php echo esc_attr($opt['openai_api_key'] ?? ''); ?>"
                                        autocomplete="off">
                                    <p class="description">
                                        <?php esc_html_e('Used for article generation and image generation.', WABE_TEXTDOMAIN); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label
                                        for="wabe_openai_model"><?php esc_html_e('OpenAI Model', WABE_TEXTDOMAIN); ?></label>
                                </th>
                                <td>
                                    <select id="wabe_openai_model"
                                        name="<?php echo esc_attr(WABE_OPTION); ?>[openai_model]">
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
                                <th scope="row">
                                    <label
                                        for="wabe_gemini_api_key"><?php esc_html_e('Gemini API Key', WABE_TEXTDOMAIN); ?></label>
                                </th>
                                <td>
                                    <input id="wabe_gemini_api_key" type="text" class="regular-text"
                                        name="<?php echo esc_attr(WABE_OPTION); ?>[gemini_api_key]"
                                        value="<?php echo esc_attr($opt['gemini_api_key'] ?? ''); ?>"
                                        autocomplete="off">
                                    <p class="description">
                                        <?php esc_html_e('Used for article generation and image generation.', WABE_TEXTDOMAIN); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label
                                        for="wabe_gemini_model"><?php esc_html_e('Gemini Model', WABE_TEXTDOMAIN); ?></label>
                                </th>
                                <td>
                                    <select id="wabe_gemini_model"
                                        name="<?php echo esc_attr(WABE_OPTION); ?>[gemini_model]">
                                        <option value="gemini-2.5-flash"
                                            <?php selected($gemini_model, 'gemini-2.5-flash'); ?>>gemini-2.5-flash
                                        </option>
                                        <option value="gemini-2.5-pro"
                                            <?php selected($gemini_model, 'gemini-2.5-pro'); ?>>gemini-2.5-pro</option>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label
                                        for="wabe_pollinations_api_key"><?php esc_html_e('Pollinations API Key', WABE_TEXTDOMAIN); ?></label>
                                </th>
                                <td>
                                    <input id="wabe_pollinations_api_key" type="text" class="regular-text"
                                        name="<?php echo esc_attr(WABE_OPTION); ?>[pollinations_api_key]"
                                        value="<?php echo esc_attr($opt['pollinations_api_key'] ?? ''); ?>"
                                        autocomplete="off">
                                    <p class="description">
                                        <?php esc_html_e('Used only for image generation if you enable it in your implementation.', WABE_TEXTDOMAIN); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label
                                        for="wabe_pollinations_image_model"><?php esc_html_e('Pollinations Image Model', WABE_TEXTDOMAIN); ?></label>
                                </th>
                                <td>
                                    <input id="wabe_pollinations_image_model" type="text" class="regular-text"
                                        name="<?php echo esc_attr(WABE_OPTION); ?>[pollinations_image_model]"
                                        value="<?php echo esc_attr($pollinations_image_model); ?>">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.04);">
                    <h2 style="margin:0 0 6px;font-size:20px;"><?php esc_html_e('Writing Settings', WABE_TEXTDOMAIN); ?>
                    </h2>
                    <p style="margin:0 0 20px;color:#6b7280;">
                        <?php esc_html_e('Configure article structure and generation behavior.', WABE_TEXTDOMAIN); ?>
                    </p>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="wabe_heading_count">
                                        <?php esc_html_e('Heading Count', WABE_TEXTDOMAIN); ?>
                                    </label>
                                </th>
                                <td>
                                    <select id="wabe_heading_count"
                                        name="<?php echo esc_attr(WABE_OPTION); ?>[heading_count]">
                                        <?php for ($i = 1; $i <= $heading_count_max; $i++) : ?>
                                            <option value="<?php echo esc_attr($i); ?>"
                                                <?php selected($heading_count, $i); ?>>
                                                <?php echo esc_html($i); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <p class="description">
                                        <?php
                                        echo esc_html(
                                            sprintf(
                                                __('Current plan allows up to %d headings.', WABE_TEXTDOMAIN),
                                                $heading_count_max
                                            )
                                        );
                                        ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="wabe_tone">
                                        <?php esc_html_e('Tone', WABE_TEXTDOMAIN); ?>
                                    </label>
                                </th>
                                <td>
                                    <select id="wabe_tone" name="<?php echo esc_attr(WABE_OPTION); ?>[tone]">
                                        <option value="standard" <?php selected($tone, 'standard'); ?>>
                                            <?php esc_html_e('Standard', WABE_TEXTDOMAIN); ?></option>
                                        <option value="professional" <?php selected($tone, 'professional'); ?>>
                                            <?php esc_html_e('Professional', WABE_TEXTDOMAIN); ?></option>
                                        <option value="casual" <?php selected($tone, 'casual'); ?>>
                                            <?php esc_html_e('Casual', WABE_TEXTDOMAIN); ?></option>
                                        <option value="friendly" <?php selected($tone, 'friendly'); ?>>
                                            <?php esc_html_e('Friendly', WABE_TEXTDOMAIN); ?></option>
                                        <option value="formal" <?php selected($tone, 'formal'); ?>>
                                            <?php esc_html_e('Formal', WABE_TEXTDOMAIN); ?></option>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="wabe_detail_level">
                                        <?php esc_html_e('Detail Level', WABE_TEXTDOMAIN); ?>
                                    </label>
                                </th>
                                <td>
                                    <select id="wabe_detail_level"
                                        name="<?php echo esc_attr(WABE_OPTION); ?>[detail_level]">
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
                                <th scope="row">
                                    <label for="wabe_generation_quality">
                                        <?php esc_html_e('Generation Quality', WABE_TEXTDOMAIN); ?>
                                    </label>
                                </th>
                                <td>
                                    <select id="wabe_generation_quality"
                                        name="<?php echo esc_attr(WABE_OPTION); ?>[generation_quality]">
                                        <option value="standard" <?php selected($generation_quality, 'standard'); ?>>
                                            <?php esc_html_e('Standard', WABE_TEXTDOMAIN); ?></option>
                                        <option value="high" <?php selected($generation_quality, 'high'); ?>>
                                            <?php esc_html_e('High', WABE_TEXTDOMAIN); ?></option>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="wabe_author_name">
                                        <?php esc_html_e('Author Name', WABE_TEXTDOMAIN); ?>
                                    </label>
                                </th>
                                <td>
                                    <input id="wabe_author_name" type="text" class="regular-text"
                                        name="<?php echo esc_attr(WABE_OPTION); ?>[author_name]"
                                        value="<?php echo esc_attr($author_name); ?>">
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="wabe_post_status">
                                        <?php esc_html_e('Post Status', WABE_TEXTDOMAIN); ?>
                                    </label>
                                </th>
                                <td>
                                    <select id="wabe_post_status"
                                        name="<?php echo esc_attr(WABE_OPTION); ?>[post_status]">
                                        <option value="draft" <?php selected($post_status, 'draft'); ?>>
                                            <?php esc_html_e('Draft', WABE_TEXTDOMAIN); ?></option>
                                        <option value="publish" <?php selected($post_status, 'publish'); ?>
                                            <?php disabled(!$can_publish); ?>>
                                            <?php esc_html_e('Publish', WABE_TEXTDOMAIN); ?>
                                        </option>
                                    </select>
                                    <?php if (!$can_publish) : ?>
                                        <p class="description"><?php echo esc_html(wabe_settings_lock_text('Advanced')); ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.04);">
                    <h2 style="margin:0 0 6px;font-size:20px;">
                        <?php esc_html_e('Site Context & Writing Rules', WABE_TEXTDOMAIN); ?></h2>


                    <input type="hidden" id="wabe_site_context_hidden"
                        name="<?php echo esc_attr(WABE_OPTION); ?>[site_context]" value="">
                    <input type="hidden" id="wabe_writing_rules_hidden"
                        name="<?php echo esc_attr(WABE_OPTION); ?>[writing_rules]" value="">

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label
                                        for="wabe_site_context_visible"><?php esc_html_e('Site Context', WABE_TEXTDOMAIN); ?></label>
                                </th>
                                <td>
                                    <textarea id="wabe_site_context_visible" class="large-text" rows="8"
                                        data-base64-source="site_context"
                                        placeholder="<?php echo esc_attr__('Describe your site, target audience, expertise, positioning, and important context for article generation.', WABE_TEXTDOMAIN); ?>"><?php echo esc_textarea($site_context); ?></textarea>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label
                                        for="wabe_writing_rules_visible"><?php esc_html_e('Writing Rules', WABE_TEXTDOMAIN); ?></label>
                                </th>
                                <td>
                                    <textarea id="wabe_writing_rules_visible" class="large-text" rows="8"
                                        data-base64-source="writing_rules"
                                        placeholder="<?php echo esc_attr__('Write tone, formatting, prohibited expressions, CTA policy, style guide, and other writing instructions here.', WABE_TEXTDOMAIN); ?>"><?php echo esc_textarea($writing_rules); ?></textarea>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.04);">
                    <h2 style="margin:0 0 6px;font-size:20px;"><?php esc_html_e('Images', WABE_TEXTDOMAIN); ?></h2>
                    <p style="margin:0 0 20px;color:#6b7280;">
                        <?php esc_html_e('Configure featured images and inline Unsplash images.', WABE_TEXTDOMAIN); ?>
                    </p>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label
                                        for="wabe_unsplash_access_key"><?php esc_html_e('Unsplash Access Key', WABE_TEXTDOMAIN); ?></label>
                                </th>
                                <td>
                                    <input id="wabe_unsplash_access_key" type="text" class="regular-text"
                                        name="<?php echo esc_attr(WABE_OPTION); ?>[unsplash_access_key]"
                                        value="<?php echo esc_attr($opt['unsplash_access_key'] ?? ''); ?>"
                                        autocomplete="off">
                                    <?php if ($unsplash_masked) : ?>
                                        <p class="description">
                                            <?php echo esc_html(sprintf(__('Current: %s', WABE_TEXTDOMAIN), $unsplash_masked)); ?>
                                        </p>
                                    <?php endif; ?>
                                    <p class="description">
                                        <?php esc_html_e('Used for inline article images. Secret Key is not required here.', WABE_TEXTDOMAIN); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label
                                        for="wabe_image_style"><?php esc_html_e('Image Style', WABE_TEXTDOMAIN); ?></label>
                                </th>
                                <td>
                                    <select id="wabe_image_style"
                                        name="<?php echo esc_attr(WABE_OPTION); ?>[image_style]">
                                        <option value="modern" <?php selected($image_style, 'modern'); ?>>
                                            <?php esc_html_e('Modern', WABE_TEXTDOMAIN); ?></option>
                                        <option value="business" <?php selected($image_style, 'business'); ?>>
                                            <?php esc_html_e('Business', WABE_TEXTDOMAIN); ?></option>
                                        <option value="blog" <?php selected($image_style, 'blog'); ?>>
                                            <?php esc_html_e('Blog', WABE_TEXTDOMAIN); ?></option>
                                        <option value="tech" <?php selected($image_style, 'tech'); ?>>
                                            <?php esc_html_e('Tech', WABE_TEXTDOMAIN); ?></option>
                                        <option value="luxury" <?php selected($image_style, 'luxury'); ?>>
                                            <?php esc_html_e('Luxury', WABE_TEXTDOMAIN); ?></option>
                                        <option value="natural" <?php selected($image_style, 'natural'); ?>>
                                            <?php esc_html_e('Natural', WABE_TEXTDOMAIN); ?></option>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e('Featured Image', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                            name="<?php echo esc_attr(WABE_OPTION); ?>[enable_featured_image]" value="1"
                                            <?php checked($enable_featured_image); ?>
                                            <?php disabled(!$can_use_images); ?>>
                                        <?php esc_html_e('Enable featured image generation', WABE_TEXTDOMAIN); ?>
                                    </label>
                                    <div style="margin-top:8px;">
                                        <?php echo wabe_settings_feature_badge($can_use_images); ?></div>
                                    <?php if (!$can_use_images) : ?>
                                        <p class="description"><?php echo esc_html(wabe_settings_lock_text('Advanced')); ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e('Inline Unsplash Images', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                            name="<?php echo esc_attr(WABE_OPTION); ?>[enable_inline_unsplash]"
                                            value="1" <?php checked($enable_inline_unsplash); ?>
                                            <?php disabled(!$can_use_images); ?>>
                                        <?php esc_html_e('Insert images after headings', WABE_TEXTDOMAIN); ?>
                                    </label>
                                    <div style="margin-top:8px;">
                                        <?php echo wabe_settings_feature_badge($can_use_images); ?></div>
                                    <?php if (!$can_use_images) : ?>
                                        <p class="description"><?php echo esc_html(wabe_settings_lock_text('Advanced')); ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.04);">
                    <h2 style="margin:0 0 6px;font-size:20px;">
                        <?php esc_html_e('Automation & Features', WABE_TEXTDOMAIN); ?></h2>
                    <p style="margin:0 0 20px;color:#6b7280;">
                        <?php esc_html_e('Control schedule and advanced article features.', WABE_TEXTDOMAIN); ?>
                    </p>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><?php esc_html_e('Automatic Posting', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                            name="<?php echo esc_attr(WABE_OPTION); ?>[schedule_enabled]" value="1"
                                            <?php checked($schedule_enabled); ?>>
                                        <?php esc_html_e('Enable automatic posting', WABE_TEXTDOMAIN); ?>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e('WordPress automatic posting runs when your site receives visits. If your site has very few visits, scheduled posting may be delayed.', WABE_TEXTDOMAIN); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label
                                        for="wabe_weekly_posts"><?php esc_html_e('Posts Per Week', WABE_TEXTDOMAIN); ?></label>
                                </th>
                                <td>
                                    <select id="wabe_weekly_posts"
                                        name="<?php echo esc_attr(WABE_OPTION); ?>[weekly_posts]">
                                        <?php for ($i = 1; $i <= $weekly_posts_max; $i++) : ?>
                                            <option value="<?php echo esc_attr($i); ?>"
                                                <?php selected($weekly_posts, $i); ?>>
                                                <?php echo esc_html($i); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <p class="description">
                                        <?php
                                        echo esc_html(
                                            sprintf(
                                                __('Current plan allows up to %d posts per week.', WABE_TEXTDOMAIN),
                                                $weekly_posts_max
                                            )
                                        );
                                        ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e('SEO', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="<?php echo esc_attr(WABE_OPTION); ?>[enable_seo]"
                                            value="1" <?php checked($enable_seo); ?> <?php disabled(!$can_use_seo); ?>>
                                        <?php esc_html_e('Enable SEO support', WABE_TEXTDOMAIN); ?>
                                    </label>
                                    <div style="margin-top:8px;">
                                        <?php echo wabe_settings_feature_badge($can_use_seo); ?></div>
                                    <?php if (!$can_use_seo) : ?>
                                        <p class="description"><?php echo esc_html(wabe_settings_lock_text('Advanced')); ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e('Internal Links', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                            name="<?php echo esc_attr(WABE_OPTION); ?>[enable_internal_links]" value="1"
                                            <?php checked($enable_internal_links); ?>
                                            <?php disabled(!$can_use_internal); ?>>
                                        <?php esc_html_e('Enable internal links', WABE_TEXTDOMAIN); ?>
                                    </label>
                                    <div style="margin-top:8px;">
                                        <?php echo wabe_settings_feature_badge($can_use_internal); ?></div>
                                    <?php if (!$can_use_internal) : ?>
                                        <p class="description"><?php echo esc_html(wabe_settings_lock_text('Pro')); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e('External Links', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                            name="<?php echo esc_attr(WABE_OPTION); ?>[enable_external_links]" value="1"
                                            <?php checked($enable_external_links); ?>
                                            <?php disabled(!$can_use_external); ?>>
                                        <?php esc_html_e('Enable external links', WABE_TEXTDOMAIN); ?>
                                    </label>
                                    <div style="margin-top:8px;">
                                        <?php echo wabe_settings_feature_badge($can_use_external); ?></div>
                                    <?php if (!$can_use_external) : ?>
                                        <p class="description"><?php echo esc_html(wabe_settings_lock_text('Pro')); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e('Topic Prediction', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                            name="<?php echo esc_attr(WABE_OPTION); ?>[enable_topic_prediction]"
                                            value="1" <?php checked($enable_topic_prediction); ?>
                                            <?php disabled(!$can_use_prediction); ?>>
                                        <?php esc_html_e('Enable topic prediction', WABE_TEXTDOMAIN); ?>
                                    </label>
                                    <div style="margin-top:8px;">
                                        <?php echo wabe_settings_feature_badge($can_use_prediction); ?></div>
                                    <?php if (!$can_use_prediction) : ?>
                                        <p class="description"><?php echo esc_html(wabe_settings_lock_text('Pro')); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e('Duplicate Check', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                            name="<?php echo esc_attr(WABE_OPTION); ?>[enable_duplicate_check]"
                                            value="1" <?php checked($enable_duplicate_check); ?>
                                            <?php disabled(!$can_use_duplicate); ?>>
                                        <?php esc_html_e('Enable duplicate check', WABE_TEXTDOMAIN); ?>
                                    </label>
                                    <div style="margin-top:8px;">
                                        <?php echo wabe_settings_feature_badge($can_use_duplicate); ?></div>
                                    <?php if (!$can_use_duplicate) : ?>
                                        <p class="description"><?php echo esc_html(wabe_settings_lock_text('Pro')); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e('Outline Generator', WABE_TEXTDOMAIN); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                            name="<?php echo esc_attr(WABE_OPTION); ?>[enable_outline_generator]"
                                            value="1" <?php checked($enable_outline_generator); ?>
                                            <?php disabled(!$can_use_outline); ?>>
                                        <?php esc_html_e('Enable outline generator', WABE_TEXTDOMAIN); ?>
                                    </label>
                                    <div style="margin-top:8px;">
                                        <?php echo wabe_settings_feature_badge($can_use_outline); ?></div>
                                    <?php if (!$can_use_outline) : ?>
                                        <p class="description"><?php echo esc_html(wabe_settings_lock_text('Pro')); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="display:flex;gap:12px;align-items:center;">
                    <?php submit_button(__('Save Settings', WABE_TEXTDOMAIN), 'primary', 'submit', false); ?>
                    <span id="wabe-base64-status" style="color:#6b7280;"></span>
                </div>
            </form>
        </div>

        <div style="display:flex;flex-direction:column;gap:20px;">
            <div
                style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.04);">
                <h2 style="margin:0 0 6px;font-size:20px;"><?php esc_html_e('Generate Now', WABE_TEXTDOMAIN); ?></h2>
                <p style="margin:0 0 16px;color:#6b7280;">
                    <?php esc_html_e('Test article generation immediately with the current settings.', WABE_TEXTDOMAIN); ?>
                </p>

                <div style="display:grid;gap:12px;">
                    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:14px;">
                        <div style="font-size:12px;color:#6b7280;margin-bottom:6px;">
                            <?php esc_html_e('Queued topics', WABE_TEXTDOMAIN); ?></div>
                        <div style="font-size:18px;font-weight:700;"><?php echo esc_html($topics_count); ?> / 10</div>
                    </div>

                    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:14px;">
                        <div style="font-size:12px;color:#6b7280;margin-bottom:6px;">
                            <?php esc_html_e('Next scheduled run', WABE_TEXTDOMAIN); ?></div>
                        <div style="font-size:16px;font-weight:700;"><?php echo esc_html($next_run_display); ?></div>
                    </div>

                    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:14px;">
                        <div style="font-size:12px;color:#6b7280;margin-bottom:6px;">
                            <?php esc_html_e('Ready status', WABE_TEXTDOMAIN); ?></div>
                        <div style="font-size:16px;font-weight:700;">
                            <?php echo $is_ready ? esc_html__('Ready', WABE_TEXTDOMAIN) : esc_html__('Not ready', WABE_TEXTDOMAIN); ?>
                        </div>
                    </div>
                </div>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                    style="margin-top:16px;">
                    <input type="hidden" name="action" value="wabe_generate_now">
                    <?php wp_nonce_field('wabe_generate_now'); ?>
                    <?php submit_button(__('Generate Now', WABE_TEXTDOMAIN), 'secondary', 'submit', false, $is_ready ? [] : ['disabled' => 'disabled']); ?>
                </form>
            </div>

            <div
                style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.04);">
                <h2 style="margin:0 0 6px;font-size:20px;"><?php esc_html_e('Plan Summary', WABE_TEXTDOMAIN); ?></h2>
                <p style="margin:0 0 16px;color:#6b7280;">
                    <?php esc_html_e('Current plan limits and available features.', WABE_TEXTDOMAIN); ?>
                </p>

                <table style="width:100%;border-collapse:collapse;">
                    <tbody>
                        <tr>
                            <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;">
                                <?php esc_html_e('Posts per week', WABE_TEXTDOMAIN); ?></td>
                            <td
                                style="padding:10px 0;border-bottom:1px solid #e5e7eb;text-align:right;font-weight:700;">
                                <?php echo esc_html($weekly_posts_max); ?></td>
                        </tr>
                        <tr>
                            <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;">
                                <?php esc_html_e('Heading count', WABE_TEXTDOMAIN); ?></td>
                            <td
                                style="padding:10px 0;border-bottom:1px solid #e5e7eb;text-align:right;font-weight:700;">
                                <?php echo esc_html($heading_count_max); ?></td>
                        </tr>
                        <tr>
                            <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;">
                                <?php esc_html_e('Publish posts', WABE_TEXTDOMAIN); ?></td>
                            <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;text-align:right;">
                                <?php echo $can_publish ? '✓' : '—'; ?></td>
                        </tr>
                        <tr>
                            <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;">
                                <?php esc_html_e('Images', WABE_TEXTDOMAIN); ?></td>
                            <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;text-align:right;">
                                <?php echo $can_use_images ? '✓' : '—'; ?></td>
                        </tr>
                        <tr>
                            <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;">
                                <?php esc_html_e('SEO', WABE_TEXTDOMAIN); ?></td>
                            <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;text-align:right;">
                                <?php echo $can_use_seo ? '✓' : '—'; ?></td>
                        </tr>
                        <tr>
                            <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;">
                                <?php esc_html_e('Internal links', WABE_TEXTDOMAIN); ?></td>
                            <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;text-align:right;">
                                <?php echo $can_use_internal ? '✓' : '—'; ?></td>
                        </tr>
                        <tr>
                            <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;">
                                <?php esc_html_e('External links', WABE_TEXTDOMAIN); ?></td>
                            <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;text-align:right;">
                                <?php echo $can_use_external ? '✓' : '—'; ?></td>
                        </tr>
                        <tr>
                            <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;">
                                <?php esc_html_e('Topic prediction', WABE_TEXTDOMAIN); ?></td>
                            <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;text-align:right;">
                                <?php echo $can_use_prediction ? '✓' : '—'; ?></td>
                        </tr>
                        <tr>
                            <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;">
                                <?php esc_html_e('Duplicate check', WABE_TEXTDOMAIN); ?></td>
                            <td style="padding:10px 0;border-bottom:1px solid #e5e7eb;text-align:right;">
                                <?php echo $can_use_duplicate ? '✓' : '—'; ?></td>
                        </tr>
                        <tr>
                            <td style="padding:10px 0;"><?php esc_html_e('Outline generator', WABE_TEXTDOMAIN); ?></td>
                            <td style="padding:10px 0;text-align:right;"><?php echo $can_use_outline ? '✓' : '—'; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        var form = document.getElementById('wabe-settings-form');
        if (!form) return;

        var visibleSiteContext = document.getElementById('wabe_site_context_visible');
        var visibleWritingRules = document.getElementById('wabe_writing_rules_visible');
        var hiddenSiteContext = document.getElementById('wabe_site_context_hidden');
        var hiddenWritingRules = document.getElementById('wabe_writing_rules_hidden');
        var status = document.getElementById('wabe-base64-status');

        function utf8ToBase64(str) {
            try {
                return btoa(unescape(encodeURIComponent(str)));
            } catch (e) {
                return '';
            }
        }

        form.addEventListener('submit', function() {
            if (status) {
                status.textContent =
                    '<?php echo esc_js(__('Encoding long text fields...', WABE_TEXTDOMAIN)); ?>';
            }

            if (hiddenSiteContext && visibleSiteContext) {
                hiddenSiteContext.value = utf8ToBase64(visibleSiteContext.value || '');
            }

            if (hiddenWritingRules && visibleWritingRules) {
                hiddenWritingRules.value = utf8ToBase64(visibleWritingRules.value || '');
            }

            if (status) {
                status.textContent = '<?php echo esc_js(__('Encoded and submitting...', WABE_TEXTDOMAIN)); ?>';
            }
        });
    })();
</script>
