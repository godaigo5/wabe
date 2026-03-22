<?php
if (!defined('ABSPATH')) exit;

$opt = is_array($opt ?? null) ? $opt : [];

$plan = method_exists($this, 'get_plan') ? $this->get_plan() : 'free';
$plan = in_array($plan, ['free', 'advanced', 'pro'], true) ? $plan : 'free';

$features = method_exists($this, 'get_plan_features') ? $this->get_plan_features() : [];
$ready_state = method_exists($this, 'get_ready_state') ? $this->get_ready_state() : [
    'ready' => false,
    'provider' => sanitize_key($opt['ai_provider'] ?? 'openai'),
    'has_provider_key' => false,
    'topics_count' => 0,
    'reasons' => [__('Generation is not ready.', WABE_TEXTDOMAIN)],
];
$is_ready = !empty($ready_state['ready']);

if (method_exists($this, 'get_plan_length_profile')) {
    $length_profile = $this->get_plan_length_profile($plan);
} else {
    switch ($plan) {
        case 'pro':
            $length_profile = ['band' => 5000, 'min' => 4500, 'target' => 5000, 'max' => 5300];
            break;
        case 'advanced':
            $length_profile = ['band' => 3000, 'min' => 2500, 'target' => 3000, 'max' => 3300];
            break;
        case 'free':
        default:
            $length_profile = ['band' => 1000, 'min' => 900, 'target' => 1000, 'max' => 1100];
            break;
    }
}

$title_profile = ['min' => 18, 'target_max' => 25, 'soft_max' => 27];
$heading_profile = ['min' => 12, 'target_max' => 20, 'soft_max' => 22];

$ai_provider = sanitize_key($opt['ai_provider'] ?? 'openai');
if (!in_array($ai_provider, ['openai', 'gemini'], true)) {
    $ai_provider = 'openai';
}

$openai_model = sanitize_text_field($opt['openai_model'] ?? 'gpt-4.1');
$gemini_model = sanitize_text_field($opt['gemini_model'] ?? 'gemini-2.5-flash');

$tone = sanitize_key($opt['tone'] ?? 'standard');
if (!in_array($tone, ['standard', 'polite', 'casual'], true)) {
    $tone = 'standard';
}

$post_status = sanitize_key($opt['post_status'] ?? 'draft');
if (!in_array($post_status, ['draft', 'publish'], true)) {
    $post_status = 'draft';
}

$detail_level = sanitize_key($opt['detail_level'] ?? 'medium');
if (!in_array($detail_level, ['low', 'medium', 'high'], true)) {
    $detail_level = 'medium';
}

$generation_quality = sanitize_key($opt['generation_quality'] ?? 'high');
if (!in_array($generation_quality, ['fast', 'high'], true)) {
    $generation_quality = 'high';
}

$weekly_posts_max = max(1, (int)($features['weekly_posts_max'] ?? 1));
$weekly_posts = (int)($opt['weekly_posts'] ?? 1);
$weekly_posts = max(1, min($weekly_posts_max, $weekly_posts));

$can_publish = !empty($features['can_publish']);
$can_use_images = !empty($features['can_use_images']);
$can_use_seo = !empty($features['can_use_seo']);
$can_use_internal = !empty($features['can_use_internal_links']);
$can_use_external = !empty($features['can_use_external_links']);
$can_use_predict = !empty($features['can_use_topic_prediction']);
$can_use_duplicate = !empty($features['can_use_duplicate_check']);

$image_style = sanitize_key($opt['image_style'] ?? 'modern');
if (!in_array($image_style, ['modern', 'business', 'blog', 'tech', 'luxury', 'natural'], true)) {
    $image_style = 'modern';
}
?>

<div class="wrap">
    <h1><?php echo esc_html__('WP AI Blog Engine Settings', WABE_TEXTDOMAIN); ?></h1>

    <?php if (!empty($_GET['message'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html(wp_unslash($_GET['message'])); ?></p>
        </div>
    <?php endif; ?>

    <div class="postbox" style="padding:20px;margin-bottom:24px;">
        <h2 style="margin:0 0 14px 0;"><?php echo esc_html__('Current Plan Summary', WABE_TEXTDOMAIN); ?></h2>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
            <div style="border:1px solid #e5e7eb;border-radius:10px;padding:14px;background:#fff;">
                <div style="font-size:12px;color:#64748b;margin-bottom:6px;">
                    <?php echo esc_html__('Plan', WABE_TEXTDOMAIN); ?></div>
                <div style="font-size:22px;font-weight:700;"><?php echo esc_html(ucfirst($plan)); ?></div>
            </div>

            <div style="border:1px solid #e5e7eb;border-radius:10px;padding:14px;background:#fff;">
                <div style="font-size:12px;color:#64748b;margin-bottom:6px;">
                    <?php echo esc_html__('Article Length Band', WABE_TEXTDOMAIN); ?></div>
                <div style="font-size:22px;font-weight:700;">
                    <?php echo esc_html(number_format_i18n($length_profile['band'])); ?></div>
            </div>

            <div style="border:1px solid #e5e7eb;border-radius:10px;padding:14px;background:#fff;">
                <div style="font-size:12px;color:#64748b;margin-bottom:6px;">
                    <?php echo esc_html__('Valid Body Range', WABE_TEXTDOMAIN); ?></div>
                <div style="font-size:20px;font-weight:700;">
                    <?php echo esc_html(number_format_i18n($length_profile['min']) . ' - ' . number_format_i18n($length_profile['max'])); ?>
                </div>
            </div>

            <div style="border:1px solid #e5e7eb;border-radius:10px;padding:14px;background:#fff;">
                <div style="font-size:12px;color:#64748b;margin-bottom:6px;">
                    <?php echo esc_html__('Ready Status', WABE_TEXTDOMAIN); ?></div>
                <div style="font-size:22px;font-weight:700;color:<?php echo $is_ready ? '#16a34a' : '#dc2626'; ?>;">
                    <?php echo $is_ready ? esc_html__('Ready', WABE_TEXTDOMAIN) : esc_html__('Not Ready', WABE_TEXTDOMAIN); ?>
                </div>
            </div>
        </div>

        <div style="margin-top:14px;padding:12px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;">
            <div style="margin-bottom:6px;font-weight:600;"><?php echo esc_html__('Content Rules', WABE_TEXTDOMAIN); ?>
            </div>
            <div><?php echo esc_html__('Title: 18-25 characters target, up to 27 allowed.', WABE_TEXTDOMAIN); ?></div>
            <div><?php echo esc_html__('H2 Heading: 12-20 characters target, up to 22 allowed.', WABE_TEXTDOMAIN); ?>
            </div>
            <div><?php echo esc_html__('Body length includes headings and body text.', WABE_TEXTDOMAIN); ?></div>
        </div>

        <?php if (!$is_ready) : ?>
            <div style="margin-top:14px;padding:14px 16px;border:1px solid #fecaca;background:#fef2f2;border-radius:10px;">
                <div style="font-weight:700;margin-bottom:8px;color:#991b1b;">
                    <?php echo esc_html__('Why Generate Now is disabled', WABE_TEXTDOMAIN); ?>
                </div>

                <ul style="margin:0 0 0 18px;color:#7f1d1d;">
                    <?php foreach (($ready_state['reasons'] ?? []) as $reason) : ?>
                        <li><?php echo esc_html($reason); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="wabe_save_settings">
        <?php wp_nonce_field('wabe_save_settings', 'wabe_settings_nonce'); ?>

        <div class="postbox" style="padding:20px;margin-bottom:24px;">
            <h2 style="margin:0 0 14px 0;"><?php echo esc_html__('AI Provider Settings', WABE_TEXTDOMAIN); ?></h2>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php echo esc_html__('AI Provider', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <select name="ai_provider">
                            <option value="openai" <?php selected($ai_provider, 'openai'); ?>>OpenAI</option>
                            <option value="gemini" <?php selected($ai_provider, 'gemini'); ?>>Gemini</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('OpenAI API Key', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <input type="text" name="openai_api_key"
                            value="<?php echo esc_attr(!empty($opt['openai_api_key']) ? $this->mask_secret_value($opt['openai_api_key']) : ''); ?>"
                            class="regular-text" autocomplete="off">
                        <p class="description">
                            <?php echo esc_html__('Get your OpenAI API key from ', WABE_TEXTDOMAIN); ?><a
                                href="https://platform.openai.com/api-keys"
                                target="_blank">https://platform.openai.com/api-keys</a>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('OpenAI Model', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <input type="text" name="openai_model" value="<?php echo esc_attr($openai_model); ?>"
                            class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('Gemini API Key', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <input type="text" name="gemini_api_key"
                            value="<?php echo esc_attr(!empty($opt['gemini_api_key']) ? $this->mask_secret_value($opt['gemini_api_key']) : ''); ?>"
                            class="regular-text" autocomplete="off">
                        <p class="description">
                            <?php echo esc_html__('Get your Gemini API key from ', WABE_TEXTDOMAIN); ?><a
                                href="https://aistudio.google.com/app/u/2/api-keys"
                                target="_blank">https://aistudio.google.com/app/u/2/api-keys</a>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('Gemini Model', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <input type="text" name="gemini_model" value="<?php echo esc_attr($gemini_model); ?>"
                            class="regular-text">
                    </td>
                </tr>
            </table>
        </div>

        <div class="postbox" style="padding:20px;margin-bottom:24px;">
            <h2 style="margin:0 0 14px 0;"><?php echo esc_html__('Generation Settings', WABE_TEXTDOMAIN); ?></h2>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php echo esc_html__('Article Length Band', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <input type="text"
                            value="<?php echo esc_attr(number_format_i18n($length_profile['band']) . ' (' . number_format_i18n($length_profile['min']) . ' - ' . number_format_i18n($length_profile['max']) . ')'); ?>"
                            class="regular-text" disabled>
                        <p class="description">
                            <?php echo esc_html__('This is fixed automatically by the current plan.', WABE_TEXTDOMAIN); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('Detail Level', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <select name="detail_level">
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
                    <th scope="row"><?php echo esc_html__('Generation Quality', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <select name="generation_quality">
                            <option value="fast" <?php selected($generation_quality, 'fast'); ?>>
                                <?php echo esc_html__('Fast', WABE_TEXTDOMAIN); ?></option>
                            <option value="high" <?php selected($generation_quality, 'high'); ?>>
                                <?php echo esc_html__('High Quality', WABE_TEXTDOMAIN); ?></option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('Tone', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <select name="tone">
                            <option value="standard" <?php selected($tone, 'standard'); ?>>
                                <?php echo esc_html__('Standard', WABE_TEXTDOMAIN); ?></option>
                            <option value="polite" <?php selected($tone, 'polite'); ?>>
                                <?php echo esc_html__('Polite', WABE_TEXTDOMAIN); ?></option>
                            <option value="casual" <?php selected($tone, 'casual'); ?>>
                                <?php echo esc_html__('Casual', WABE_TEXTDOMAIN); ?></option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('Post Status', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <select name="post_status">
                            <option value="draft" <?php selected($post_status, 'draft'); ?>>
                                <?php echo esc_html__('Draft', WABE_TEXTDOMAIN); ?></option>
                            <option value="publish" <?php selected($post_status, 'publish'); ?>
                                <?php disabled(!$can_publish); ?>>
                                <?php echo esc_html__('Publish', WABE_TEXTDOMAIN); ?>
                            </option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('Weekly Posts', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <input type="number" name="weekly_posts" min="1"
                            max="<?php echo esc_attr((string)$weekly_posts_max); ?>"
                            value="<?php echo esc_attr((string)$weekly_posts); ?>" class="small-text">
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('Schedule Enabled', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="schedule_enabled" value="1"
                                <?php checked(!empty($opt['schedule_enabled'])); ?>>
                            <?php echo esc_html__('Enable scheduled generation', WABE_TEXTDOMAIN); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="postbox" style="padding:20px;margin-bottom:24px;">
            <h2 style="margin:0 0 14px 0;"><?php echo esc_html__('Content Settings', WABE_TEXTDOMAIN); ?></h2>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php echo esc_html__('Author Name', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <input type="text" name="author_name"
                            value="<?php echo esc_attr((string)($opt['author_name'] ?? '')); ?>" class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('Site Context', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <textarea name="site_context" rows="8"
                            class="large-text code"><?php echo esc_textarea(WABE_Utils::wabe_maybe_base64_decode($opt['site_context'])); ?></textarea>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('Writing Rules', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <textarea name="writing_rules" rows="8"
                            class="large-text code"><?php echo esc_textarea(WABE_Utils::wabe_maybe_base64_decode($opt['writing_rules'])); ?></textarea>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('SEO Keyword', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <input type="text" name="seo_keyword"
                            value="<?php echo esc_attr((string)($opt['seo_keyword'] ?? '')); ?>" class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('Internal Link URL', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <input type="url" name="internal_link_url"
                            value="<?php echo esc_attr((string)($opt['internal_link_url'] ?? '')); ?>"
                            class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('External Link URL', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <input type="url" name="external_link_url"
                            value="<?php echo esc_attr((string)($opt['external_link_url'] ?? '')); ?>"
                            class="regular-text">
                    </td>
                </tr>
            </table>
        </div>

        <div class="postbox" style="padding:20px;margin-bottom:24px;">
            <h2 style="margin:0 0 14px 0;"><?php echo esc_html__('Feature Settings', WABE_TEXTDOMAIN); ?></h2>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php echo esc_html__('Featured Image', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_featured_image" value="1"
                                <?php checked(!empty($opt['enable_featured_image']) && $can_use_images); ?>
                                <?php disabled(!$can_use_images); ?>>
                            <?php echo esc_html__('Enable featured image generation', WABE_TEXTDOMAIN); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('Image Style', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <select name="image_style" <?php disabled(!$can_use_images); ?>>
                            <option value="modern" <?php selected($image_style, 'modern'); ?>>Modern</option>
                            <option value="business" <?php selected($image_style, 'business'); ?>>Business</option>
                            <option value="blog" <?php selected($image_style, 'blog'); ?>>Blog</option>
                            <option value="tech" <?php selected($image_style, 'tech'); ?>>Tech</option>
                            <option value="luxury" <?php selected($image_style, 'luxury'); ?>>Luxury</option>
                            <option value="natural" <?php selected($image_style, 'natural'); ?>>Natural</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('SEO', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_seo" value="1"
                                <?php checked(!empty($opt['enable_seo']) && $can_use_seo); ?>
                                <?php disabled(!$can_use_seo); ?>>
                            <?php echo esc_html__('Enable SEO metadata', WABE_TEXTDOMAIN); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('Internal Links', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_internal_links" value="1"
                                <?php checked(!empty($opt['enable_internal_links']) && $can_use_internal); ?>
                                <?php disabled(!$can_use_internal); ?>>
                            <?php echo esc_html__('Enable internal links', WABE_TEXTDOMAIN); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('External Links', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_external_links" value="1"
                                <?php checked(!empty($opt['enable_external_links']) && $can_use_external); ?>
                                <?php disabled(!$can_use_external); ?>>
                            <?php echo esc_html__('Enable external links', WABE_TEXTDOMAIN); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('Topic Prediction', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_topic_prediction" value="1"
                                <?php checked(!empty($opt['enable_topic_prediction']) && $can_use_predict); ?>
                                <?php disabled(!$can_use_predict); ?>>
                            <?php echo esc_html__('Enable topic prediction', WABE_TEXTDOMAIN); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('Duplicate Check', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_duplicate_check" value="1"
                                <?php checked(!empty($opt['enable_duplicate_check']) && $can_use_duplicate); ?>
                                <?php disabled(!$can_use_duplicate); ?>>
                            <?php echo esc_html__('Enable duplicate check', WABE_TEXTDOMAIN); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('License Key', WABE_TEXTDOMAIN); ?></th>
                    <td>
                        <input type="text" name="license_key"
                            value="<?php echo esc_attr((string)($opt['license_key'] ?? '')); ?>" class="regular-text">
                    </td>
                </tr>
            </table>
        </div>

        <div style="margin-bottom:24px;">
            <?php submit_button(__('Save Settings', WABE_TEXTDOMAIN), 'primary', 'submit', false); ?>
        </div>
    </form>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function encodeBase64Unicode(str) {
                try {
                    return btoa(unescape(encodeURIComponent(str)));
                } catch (e) {
                    return str;
                }
            }
            // 保存時にエンコード
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function() {
                    const context = document.querySelector('textarea[name="site_context"]');
                    const rules = document.querySelector('textarea[name="writing_rules"]');

                    if (context) {
                        context.value = encodeBase64Unicode(context.value);
                    }
                    if (rules) {
                        rules.value = encodeBase64Unicode(rules.value);
                    }
                });
            }
        });
    </script>

    <div class="postbox" style="padding:20px;margin-bottom:24px;">
        <h2 style="margin:0 0 14px 0;"><?php echo esc_html__('Quick Actions', WABE_TEXTDOMAIN); ?></h2>

        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                <input type="hidden" name="action" value="wabe_generate_now">
                <?php wp_nonce_field('wabe_generate_now', 'wabe_generate_now_nonce'); ?>
                <button type="submit" class="button button-primary" <?php disabled(!$is_ready); ?>>
                    <?php echo esc_html__('Generate Now', WABE_TEXTDOMAIN); ?>
                </button>
            </form>

            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wabe-topics')); ?>">
                <?php echo esc_html__('Open Topics', WABE_TEXTDOMAIN); ?>
            </a>

            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wabe-history')); ?>">
                <?php echo esc_html__('Open History', WABE_TEXTDOMAIN); ?>
            </a>
        </div>

        <p class="description" style="margin-top:12px;">
            <?php echo esc_html__('Generate Now becomes available when at least one topic exists and the API key for the selected provider is configured.', WABE_TEXTDOMAIN); ?>
        </p>
    </div>
</div>
