<?php
if (!defined('ABSPATH')) exit;

$message = isset($_GET['wabe_message']) ? sanitize_text_field(wp_unslash($_GET['wabe_message'])) : '';
$opt = is_array($this->options ?? null) ? $this->options : [];

$plan           = method_exists($this, 'get_plan') ? $this->get_plan() : 'free';
$plan_label     = method_exists($this, 'get_plan_label') ? $this->get_plan_label($plan) : ucfirst((string)$plan);
$license        = method_exists($this, 'get_license_data') ? $this->get_license_data() : [];
$license_status = sanitize_text_field($license['status'] ?? 'free');
$license_checked_at = sanitize_text_field($license['checked_at'] ?? '');

$openai_masked = !empty($opt['openai_api_key'])
    ? $this->mask_api_key($opt['openai_api_key'])
    : (!empty($opt['api_key']) ? $this->mask_api_key($opt['api_key']) : '');

$gemini_masked = !empty($opt['gemini_api_key']) ? $this->mask_api_key($opt['gemini_api_key']) : '';

$ai_provider  = $opt['ai_provider'] ?? 'openai';
$openai_model = $opt['openai_model'] ?? 'gpt-4.1';
$gemini_model = $opt['gemini_model'] ?? 'gemini-2.5-flash';

$title_count_max  = method_exists($this, 'plan_title_count_max') ? (int)$this->plan_title_count_max() : 1;
$weekly_posts_max = method_exists($this, 'plan_weekly_posts_max') ? (int)$this->plan_weekly_posts_max() : 1;

$can_publish        = method_exists($this, 'plan_can_publish') ? (bool)$this->plan_can_publish() : false;
$can_use_images     = method_exists($this, 'plan_can_use_images') ? (bool)$this->plan_can_use_images() : false;
$can_use_seo        = method_exists($this, 'plan_can_use_seo') ? (bool)$this->plan_can_use_seo() : false;
$can_use_internal   = method_exists($this, 'plan_can_use_internal_links') ? (bool)$this->plan_can_use_internal_links() : false;
$can_use_external   = method_exists($this, 'plan_can_use_external_links') ? (bool)$this->plan_can_use_external_links() : false;
$can_use_prediction = method_exists($this, 'plan_can_use_topic_prediction') ? (bool)$this->plan_can_use_topic_prediction() : false;
$can_use_duplicate  = method_exists($this, 'plan_can_use_duplicate_check') ? (bool)$this->plan_can_use_duplicate_check() : false;
$can_use_outline    = method_exists($this, 'plan_can_use_outline_generator') ? (bool)$this->plan_can_use_outline_generator() : false;

$generation_count = max(1, min($title_count_max, (int)($opt['generation_count'] ?? 1)));
$heading_count    = max(1, min($title_count_max, (int)($opt['heading_count'] ?? 3)));
$tone             = $opt['tone'] ?? 'standard';
$post_status      = $opt['post_status'] ?? 'draft';
$weekly_posts     = max(1, min($weekly_posts_max, (int)($opt['weekly_posts'] ?? 1)));
$image_style      = $opt['image_style'] ?? 'modern';

$schedule_enabled         = !empty($opt['schedule_enabled']);
$enable_featured_image    = !empty($opt['enable_featured_image']) && $can_use_images;
$enable_seo               = !empty($opt['enable_seo']) && $can_use_seo;
$enable_internal_links    = !empty($opt['enable_internal_links']) && $can_use_internal;
$enable_external_links    = !empty($opt['enable_external_links']) && $can_use_external;
$enable_topic_prediction  = !empty($opt['enable_topic_prediction']) && $can_use_prediction;
$enable_duplicate_check   = !empty($opt['enable_duplicate_check']) && $can_use_duplicate;
$enable_outline_generator = !empty($opt['enable_outline_generator']) && $can_use_outline;

$topics_count = is_array($opt['topics'] ?? null) ? count($opt['topics']) : 0;
$is_ready = method_exists($this, 'is_ready_to_post') ? (bool)$this->is_ready_to_post() : false;

$next_run_display = !empty($this->next_post_date)
    ? (string)$this->next_post_date
    : __('Not scheduled', WABE_TEXTDOMAIN);

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
            return sprintf(
                __('Locked. Upgrade to %s to use this feature.', WABE_TEXTDOMAIN),
                $plan_text
            );
        }

        return __('Locked.', WABE_TEXTDOMAIN);
    }
}

if (!function_exists('wabe_settings_feature_badge')) {
    function wabe_settings_feature_badge($enabled)
    {
        if ($enabled) {
            return '<span class="wabe-pill wabe-pill-ok">' . esc_html__('Available', WABE_TEXTDOMAIN) . '</span>';
        }

        return '<span class="wabe-pill wabe-pill-lock">' . esc_html__('Locked', WABE_TEXTDOMAIN) . '</span>';
    }
}

$upgrade_target = 'Advanced';
if (in_array($plan, ['advanced'], true)) {
    $upgrade_target = 'Pro';
} elseif (in_array($plan, ['pro'], true)) {
    $upgrade_target = 'Pro';
}
?>
<div class="wrap wabe-admin-wrap">
    <style>
        .wabe-admin-wrap {
            --wabe-primary: <?php echo esc_attr($plan_color);
                            ?>;
            --wabe-primary-soft: rgba(37, 99, 235, .08);
            --wabe-primary-soft-2: rgba(37, 99, 235, .12);
            --wabe-border: #dbe5f1;
            --wabe-text: #0f172a;
            --wabe-sub: #475569;
            --wabe-bg: #f6f8fc;
            --wabe-card: #ffffff;
            --wabe-ok: #16a34a;
            --wabe-warn: #d97706;
            --wabe-danger: #dc2626;
            --wabe-lock: #64748b;
            --wabe-info-bg: #eff6ff;
            --wabe-info-border: #bfdbfe;
            --wabe-info-text: #1d4ed8;
        }

        .wabe-admin-wrap * {
            box-sizing: border-box;
        }

        .wabe-admin-wrap {
            background: var(--wabe-bg);
            margin-left: -20px;
            padding: 0 0 24px;
        }

        .wabe-admin-wrap .wabe-shell {
            max-width: 1280px;
            padding: 0 20px;
        }

        .wabe-admin-wrap .wabe-hero {
            background:
                radial-gradient(circle at top right, rgba(255, 255, 255, .7), transparent 35%),
                linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid var(--wabe-border);
            border-radius: 24px;
            padding: 28px;
            margin: 20px 0 18px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .05);
        }

        .wabe-admin-wrap .wabe-hero-top {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: flex-start;
            flex-wrap: wrap;
        }

        .wabe-admin-wrap .wabe-title {
            margin: 0 0 8px;
            font-size: 30px;
            line-height: 1.2;
            font-weight: 800;
            color: var(--wabe-text);
        }

        .wabe-admin-wrap .wabe-desc {
            margin: 0;
            color: var(--wabe-sub);
            font-size: 14px;
            line-height: 1.8;
            max-width: 800px;
        }

        .wabe-admin-wrap .wabe-plan-stack {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 10px;
        }

        .wabe-admin-wrap .wabe-plan-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 14px;
            border-radius: 999px;
            background: var(--wabe-primary-soft);
            color: var(--wabe-primary);
            font-weight: 800;
            border: 1px solid rgba(37, 99, 235, .14);
        }

        .wabe-admin-wrap .wabe-mini-note {
            color: var(--wabe-sub);
            font-size: 12px;
            line-height: 1.6;
            text-align: right;
        }

        .wabe-admin-wrap .wabe-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-top: 20px;
        }

        .wabe-admin-wrap .wabe-stat {
            background: var(--wabe-card);
            border: 1px solid var(--wabe-border);
            border-radius: 18px;
            padding: 16px;
        }

        .wabe-admin-wrap .wabe-stat-label {
            color: var(--wabe-sub);
            font-size: 12px;
            margin-bottom: 6px;
        }

        .wabe-admin-wrap .wabe-stat-value {
            color: var(--wabe-text);
            font-size: 18px;
            font-weight: 800;
            word-break: break-word;
        }

        .wabe-admin-wrap .wabe-ok {
            color: var(--wabe-ok);
            font-weight: 800;
        }

        .wabe-admin-wrap .wabe-warn {
            color: var(--wabe-warn);
            font-weight: 800;
        }

        .wabe-admin-wrap .wabe-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.7fr) minmax(320px, 1fr);
            gap: 18px;
            align-items: start;
        }

        .wabe-admin-wrap .wabe-column {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .wabe-admin-wrap .wabe-card {
            background: var(--wabe-card);
            border: 1px solid var(--wabe-border);
            border-radius: 20px;
            padding: 22px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .03);
        }

        .wabe-admin-wrap .wabe-card h2 {
            margin: 0 0 6px;
            font-size: 19px;
            color: var(--wabe-text);
        }

        .wabe-admin-wrap .wabe-help {
            margin: 0 0 18px;
            color: var(--wabe-sub);
            font-size: 13px;
            line-height: 1.8;
        }

        .wabe-admin-wrap .wabe-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px 18px;
        }

        .wabe-admin-wrap .wabe-form-grid-1 {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }

        .wabe-admin-wrap .wabe-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .wabe-admin-wrap .wabe-field label {
            font-weight: 700;
            color: var(--wabe-text);
        }

        .wabe-admin-wrap .wabe-field input[type="text"],
        .wabe-admin-wrap .wabe-field input[type="url"],
        .wabe-admin-wrap .wabe-field input[type="number"],
        .wabe-admin-wrap .wabe-field select,
        .wabe-admin-wrap .wabe-field textarea {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 10px 12px;
            min-height: 44px;
            background: #fff;
            color: var(--wabe-text);
        }

        .wabe-admin-wrap .wabe-field textarea {
            min-height: 120px;
            resize: vertical;
        }

        .wabe-admin-wrap .wabe-field small {
            color: var(--wabe-sub);
            line-height: 1.7;
        }

        .wabe-admin-wrap .wabe-section-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .wabe-admin-wrap .wabe-inline {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .wabe-admin-wrap .wabe-switch-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .wabe-admin-wrap .wabe-switch {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px;
            border: 1px solid var(--wabe-border);
            border-radius: 14px;
            background: #fbfdff;
        }

        .wabe-admin-wrap .wabe-switch input[type="checkbox"] {
            margin-top: 3px;
            transform: scale(1.15);
        }

        .wabe-admin-wrap .wabe-switch h3 {
            margin: 0 0 4px;
            font-size: 15px;
            color: var(--wabe-text);
        }

        .wabe-admin-wrap .wabe-switch p {
            margin: 0;
            color: var(--wabe-sub);
            font-size: 13px;
            line-height: 1.7;
        }

        .wabe-admin-wrap .wabe-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .wabe-admin-wrap .wabe-pill-ok {
            color: #166534;
            background: #ecfdf5;
            border-color: #bbf7d0;
        }

        .wabe-admin-wrap .wabe-pill-lock {
            color: #475569;
            background: #f1f5f9;
            border-color: #dbe4ee;
        }

        .wabe-admin-wrap .wabe-pill-plan {
            color: var(--wabe-primary);
            background: var(--wabe-primary-soft);
            border-color: rgba(37, 99, 235, .15);
        }

        .wabe-admin-wrap .wabe-lock {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #f1f5f9;
            color: var(--wabe-lock);
            border: 1px solid #dbe4ee;
            font-size: 12px;
            font-weight: 700;
        }

        .wabe-admin-wrap .wabe-info-box {
            margin-top: 16px;
            padding: 14px 16px;
            border-radius: 14px;
            background: var(--wabe-info-bg);
            border: 1px solid var(--wabe-info-border);
        }

        .wabe-admin-wrap .wabe-info-box strong {
            display: block;
            margin-bottom: 6px;
            color: var(--wabe-info-text);
            font-size: 14px;
        }

        .wabe-admin-wrap .wabe-info-box p {
            margin: 0;
            color: #1e3a8a;
            font-size: 13px;
            line-height: 1.8;
        }

        .wabe-admin-wrap .wabe-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            margin-top: 8px;
        }

        .wabe-admin-wrap .wabe-actions .button-primary,
        .wabe-admin-wrap .wabe-actions .button {
            min-height: 42px;
            border-radius: 12px;
        }

        .wabe-admin-wrap .wabe-actions .button-primary {
            padding: 0 18px;
        }

        .wabe-admin-wrap .wabe-actions .button {
            padding: 0 16px;
            display: flex;
            align-items: center;
        }

        .wabe-admin-wrap .wabe-feature-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
            margin: 0;
        }

        .wabe-admin-wrap .wabe-feature-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #edf2f7;
        }

        .wabe-admin-wrap .wabe-feature-row:last-child {
            border-bottom: none;
        }

        .wabe-admin-wrap .wabe-feature-name {
            font-weight: 700;
            color: var(--wabe-text);
        }

        .wabe-admin-wrap .wabe-feature-sub {
            display: block;
            color: var(--wabe-sub);
            font-size: 12px;
            margin-top: 3px;
            line-height: 1.6;
        }

        .wabe-admin-wrap .wabe-list {
            margin: 0;
            padding-left: 18px;
            color: var(--wabe-sub);
            line-height: 1.9;
        }

        .wabe-admin-wrap .wabe-guide {
            border: 1px solid rgba(37, 99, 235, .18);
            background: linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
        }

        .wabe-admin-wrap .wabe-guide-box {
            padding: 14px 16px;
            border-radius: 16px;
            background: var(--wabe-primary-soft);
            border: 1px solid rgba(37, 99, 235, .16);
            margin-top: 14px;
        }

        .wabe-admin-wrap .wabe-guide-box h3 {
            margin: 0 0 6px;
            font-size: 15px;
            color: var(--wabe-text);
        }

        .wabe-admin-wrap .wabe-guide-box p {
            margin: 0;
            color: var(--wabe-sub);
            font-size: 13px;
            line-height: 1.8;
        }

        .wabe-admin-wrap .wabe-kpi-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 14px;
        }

        .wabe-admin-wrap .wabe-kpi {
            padding: 14px;
            border: 1px solid var(--wabe-border);
            border-radius: 16px;
            background: #fff;
        }

        .wabe-admin-wrap .wabe-kpi strong {
            display: block;
            font-size: 18px;
            color: var(--wabe-text);
            margin-bottom: 4px;
        }

        .wabe-admin-wrap .wabe-kpi span {
            font-size: 12px;
            color: var(--wabe-sub);
            line-height: 1.6;
        }

        .wabe-admin-wrap .wabe-notice {
            margin: 16px 0 0;
        }

        @media (max-width: 1100px) {
            .wabe-admin-wrap .wabe-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 900px) {

            .wabe-admin-wrap .wabe-stats,
            .wabe-admin-wrap .wabe-form-grid,
            .wabe-admin-wrap .wabe-kpi-grid {
                grid-template-columns: 1fr;
            }

            .wabe-admin-wrap .wabe-plan-stack {
                align-items: flex-start;
            }

            .wabe-admin-wrap .wabe-mini-note {
                text-align: left;
            }
        }
    </style>

    <div class="wabe-shell">
        <div class="wabe-hero">
            <div class="wabe-hero-top">
                <div>
                    <h1 class="wabe-title"><?php esc_html_e('WP AI Blog Engine', WABE_TEXTDOMAIN); ?></h1>
                    <p class="wabe-desc">
                        <?php esc_html_e('Generate blog titles, headings, article bodies, and featured images directly inside WordPress. This screen is optimized for product-level operation: API setup, automation, content quality, and feature gating by plan.', WABE_TEXTDOMAIN); ?>
                    </p>
                </div>

                <div class="wabe-plan-stack">
                    <div class="wabe-plan-badge">
                        <span>●</span>
                        <span><?php echo esc_html($plan_label); ?></span>
                    </div>
                    <div class="wabe-mini-note">
                        <?php
                        printf(
                            esc_html__('Current plan: %s', WABE_TEXTDOMAIN),
                            esc_html($plan_label)
                        );
                        ?>
                    </div>
                </div>
            </div>

            <div class="wabe-stats">
                <div class="wabe-stat">
                    <div class="wabe-stat-label"><?php esc_html_e('License Status', WABE_TEXTDOMAIN); ?></div>
                    <div class="wabe-stat-value"><?php echo esc_html(__($license_status ?: 'free', WABE_TEXTDOMAIN)); ?>
                    </div>
                </div>

                <div class="wabe-stat">
                    <div class="wabe-stat-label"><?php esc_html_e('Topics Ready', WABE_TEXTDOMAIN); ?></div>
                    <div class="wabe-stat-value"><?php echo esc_html($topics_count . ' / 10'); ?></div>
                </div>

                <div class="wabe-stat">
                    <div class="wabe-stat-label"><?php esc_html_e('Next Scheduled Run', WABE_TEXTDOMAIN); ?></div>
                    <div class="wabe-stat-value"><?php echo esc_html($next_run_display); ?></div>
                </div>

                <div class="wabe-stat">
                    <div class="wabe-stat-label"><?php esc_html_e('Posting Ready', WABE_TEXTDOMAIN); ?></div>
                    <div class="wabe-stat-value <?php echo $is_ready ? 'wabe-ok' : 'wabe-warn'; ?>">
                        <?php echo esc_html($is_ready ? __('Yes', WABE_TEXTDOMAIN) : __('No', WABE_TEXTDOMAIN)); ?>
                    </div>
                </div>
            </div>

            <?php if ($license_checked_at !== '') : ?>
                <p style="margin:14px 0 0;color:#64748b;font-size:12px;">
                    <?php
                    printf(
                        esc_html__('Last license check: %s', WABE_TEXTDOMAIN),
                        esc_html($license_checked_at)
                    );
                    ?>
                </p>
            <?php endif; ?>

            <?php if ($message) : ?>
                <div class="notice notice-success is-dismissible wabe-notice">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="wabe-grid">
            <div class="wabe-column">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="wabe_save_settings">
                    <?php wp_nonce_field('wabe_save_settings', 'wabe_settings_nonce'); ?>

                    <div class="wabe-card">
                        <div class="wabe-section-head">
                            <div>
                                <h2><?php esc_html_e('AI Provider & API', WABE_TEXTDOMAIN); ?></h2>
                                <p class="wabe-help" style="margin-bottom:0;">
                                    <?php esc_html_e('Choose your AI provider, set API keys, and select the model used for article generation.', WABE_TEXTDOMAIN); ?>
                                </p>
                            </div>
                            <span
                                class="wabe-pill wabe-pill-plan"><?php esc_html_e('Required Setup', WABE_TEXTDOMAIN); ?></span>
                        </div>

                        <div class="wabe-form-grid">
                            <div class="wabe-field">
                                <label
                                    for="wabe_ai_provider"><?php esc_html_e('AI Provider', WABE_TEXTDOMAIN); ?></label>
                                <select id="wabe_ai_provider" name="wabe_ai_provider">
                                    <option value="openai" <?php selected($ai_provider, 'openai'); ?>>OpenAI</option>
                                    <option value="gemini" <?php selected($ai_provider, 'gemini'); ?>>Gemini</option>
                                </select>
                                <small><?php esc_html_e('The selected provider is used for generation. You can still save both API keys.', WABE_TEXTDOMAIN); ?></small>
                            </div>

                            <div class="wabe-field">
                                <label
                                    for="wabe_openai_model"><?php esc_html_e('OpenAI Model', WABE_TEXTDOMAIN); ?></label>
                                <select id="wabe_openai_model" name="wabe_openai_model">
                                    <option value="gpt-4.1-mini" <?php selected($openai_model, 'gpt-4.1-mini'); ?>>
                                        gpt-4.1-mini</option>
                                    <option value="gpt-4.1" <?php selected($openai_model, 'gpt-4.1'); ?>>gpt-4.1
                                    </option>
                                    <option value="gpt-5-mini" <?php selected($openai_model, 'gpt-5-mini'); ?>>
                                        gpt-5-mini</option>
                                </select>
                            </div>

                            <div class="wabe-field">
                                <label
                                    for="wabe_openai_api_key"><?php esc_html_e('OpenAI API Key', WABE_TEXTDOMAIN); ?></label>
                                <input id="wabe_openai_api_key" type="text" name="wabe_openai_api_key"
                                    value="<?php echo esc_attr($openai_masked); ?>" autocomplete="off">
                                <small><?php esc_html_e('Leave the masked value as-is to keep the current key.', WABE_TEXTDOMAIN); ?></small>
                            </div>

                            <div class="wabe-field">
                                <label
                                    for="wabe_gemini_model"><?php esc_html_e('Gemini Model', WABE_TEXTDOMAIN); ?></label>
                                <select id="wabe_gemini_model" name="wabe_gemini_model">
                                    <option value="gemini-2.5-flash"
                                        <?php selected($gemini_model, 'gemini-2.5-flash'); ?>>gemini-2.5-flash</option>
                                    <option value="gemini-2.5-pro" <?php selected($gemini_model, 'gemini-2.5-pro'); ?>>
                                        gemini-2.5-pro</option>
                                </select>
                            </div>

                            <div class="wabe-field">
                                <label
                                    for="wabe_gemini_api_key"><?php esc_html_e('Gemini API Key', WABE_TEXTDOMAIN); ?></label>
                                <input id="wabe_gemini_api_key" type="text" name="wabe_gemini_api_key"
                                    value="<?php echo esc_attr($gemini_masked); ?>" autocomplete="off">
                                <small><?php esc_html_e('Gemini is also used for Imagen-based featured image generation when selected.', WABE_TEXTDOMAIN); ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="wabe-card">
                        <div class="wabe-section-head">
                            <div>
                                <h2><?php esc_html_e('Content Generation Settings', WABE_TEXTDOMAIN); ?></h2>
                                <p class="wabe-help" style="margin-bottom:0;">
                                    <?php esc_html_e('Control article output, title candidates, heading count, tone, and whether posts are saved as draft or published immediately.', WABE_TEXTDOMAIN); ?>
                                </p>
                            </div>
                            <span
                                class="wabe-pill wabe-pill-plan"><?php esc_html_e('Content Quality', WABE_TEXTDOMAIN); ?></span>
                        </div>

                        <div class="wabe-form-grid">
                            <div class="wabe-field">
                                <label
                                    for="wabe_generation_count"><?php esc_html_e('Title Candidates', WABE_TEXTDOMAIN); ?></label>
                                <input id="wabe_generation_count" type="number" min="1"
                                    max="<?php echo esc_attr($title_count_max); ?>" name="wabe_generation_count"
                                    value="<?php echo esc_attr($generation_count); ?>">
                                <small>
                                    <?php
                                    printf(
                                        esc_html__('Your current plan allows up to %d title candidates.', WABE_TEXTDOMAIN),
                                        (int)$title_count_max
                                    );
                                    ?>
                                </small>
                            </div>

                            <div class="wabe-field">
                                <label
                                    for="wabe_heading_count"><?php esc_html_e('Heading Count', WABE_TEXTDOMAIN); ?></label>
                                <input id="wabe_heading_count" type="number" min="1"
                                    max="<?php echo esc_attr($title_count_max); ?>" name="wabe_heading_count"
                                    value="<?php echo esc_attr($heading_count); ?>">
                                <small><?php esc_html_e('Used for article structure generation.', WABE_TEXTDOMAIN); ?></small>
                            </div>

                            <div class="wabe-field">
                                <label for="wabe_tone"><?php esc_html_e('Writing Tone', WABE_TEXTDOMAIN); ?></label>
                                <select id="wabe_tone" name="wabe_tone">
                                    <?php foreach (wabe_settings_tone_options() as $tone_key => $tone_label) : ?>
                                        <option value="<?php echo esc_attr($tone_key); ?>"
                                            <?php selected($tone, $tone_key); ?>>
                                            <?php echo esc_html($tone_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="wabe-field">
                                <label
                                    for="wabe_post_status"><?php esc_html_e('Post Status', WABE_TEXTDOMAIN); ?></label>
                                <select id="wabe_post_status" name="wabe_post_status">
                                    <option value="draft" <?php selected($post_status, 'draft'); ?>>
                                        <?php esc_html_e('Draft', WABE_TEXTDOMAIN); ?></option>
                                    <option value="publish" <?php selected($post_status, 'publish'); ?>
                                        <?php disabled(!$can_publish); ?>>
                                        <?php esc_html_e('Publish', WABE_TEXTDOMAIN); ?>
                                    </option>
                                </select>
                                <?php if (!$can_publish) : ?>
                                    <small><?php echo esc_html(wabe_settings_lock_text('Advanced / Pro')); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="wabe-form-grid-1" style="margin-top:16px;">
                            <div class="wabe-field">
                                <label
                                    for="wabe_author_name"><?php esc_html_e('Brand Voice / Author Identity', WABE_TEXTDOMAIN); ?></label>
                                <input id="wabe_author_name" type="text" name="wabe_author_name"
                                    value="<?php echo esc_attr($opt['author_name'] ?? ''); ?>">
                                <small><?php esc_html_e('Defines the brand voice, author persona, and writing identity used during content generation. This strongly affects tone consistency and article quality.', WABE_TEXTDOMAIN); ?></small>
                            </div>

                            <div class="wabe-field">
                                <label
                                    for="wabe_site_context"><?php esc_html_e('Site Context / Niche', WABE_TEXTDOMAIN); ?></label>
                                <textarea id="wabe_site_context"
                                    name="wabe_site_context"><?php echo esc_textarea($opt['site_context'] ?? ''); ?></textarea>
                                <small><?php esc_html_e('Describe your site, audience, niche, and product focus. This improves article relevance and consistency.', WABE_TEXTDOMAIN); ?></small>
                            </div>

                            <div class="wabe-field">
                                <label
                                    for="wabe_writing_rules"><?php esc_html_e('Writing Rules', WABE_TEXTDOMAIN); ?></label>
                                <textarea id="wabe_writing_rules"
                                    name="wabe_writing_rules"><?php echo esc_textarea($opt['writing_rules'] ?? ''); ?></textarea>
                                <small><?php esc_html_e('Examples: avoid exaggerated claims, use short paragraphs, include practical examples, write in Japanese, etc.', WABE_TEXTDOMAIN); ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="wabe-card">
                        <div class="wabe-section-head">
                            <div>
                                <h2><?php esc_html_e('Automation', WABE_TEXTDOMAIN); ?></h2>
                                <p class="wabe-help" style="margin-bottom:0;">
                                    <?php esc_html_e('Configure automatic posting. Posting requires registered topics on the Topics screen.', WABE_TEXTDOMAIN); ?>
                                </p>
                            </div>
                            <span
                                class="wabe-pill wabe-pill-plan"><?php esc_html_e('Recurring Growth', WABE_TEXTDOMAIN); ?></span>
                        </div>

                        <div class="wabe-switch-list">
                            <label class="wabe-switch">
                                <input type="checkbox" name="wabe_schedule_enabled" value="1"
                                    <?php checked($schedule_enabled); ?>>
                                <div>
                                    <h3><?php esc_html_e('Enable Automatic Generation', WABE_TEXTDOMAIN); ?></h3>
                                    <p><?php esc_html_e('When enabled, WordPress will try to generate posts automatically based on your weekly posting limit.', WABE_TEXTDOMAIN); ?>
                                    </p>
                                </div>
                            </label>
                        </div>

                        <div class="wabe-info-box">
                            <strong><?php esc_html_e('Important:', WABE_TEXTDOMAIN); ?></strong>
                            <p><?php esc_html_e('Automatic posting runs when WordPress is accessed. If nobody visits your site or dashboard, scheduled posting may not run on time.', WABE_TEXTDOMAIN); ?>
                            </p>
                        </div>

                        <div class="wabe-form-grid" style="margin-top:16px;">
                            <div class="wabe-field">
                                <label
                                    for="wabe_weekly_posts"><?php esc_html_e('Posts Per Week', WABE_TEXTDOMAIN); ?></label>
                                <input id="wabe_weekly_posts" type="number" min="1"
                                    max="<?php echo esc_attr($weekly_posts_max); ?>" name="wabe_weekly_posts"
                                    value="<?php echo esc_attr($weekly_posts); ?>">
                                <small>
                                    <?php
                                    printf(
                                        esc_html__('Your current plan allows up to %d automatic posts per week.', WABE_TEXTDOMAIN),
                                        (int)$weekly_posts_max
                                    );
                                    ?>
                                </small>
                            </div>

                            <div class="wabe-field">
                                <label><?php esc_html_e('Topic Registration Status', WABE_TEXTDOMAIN); ?></label>
                                <div class="wabe-inline">
                                    <span class="<?php echo $is_ready ? 'wabe-ok' : 'wabe-warn'; ?>">
                                        <?php echo esc_html($is_ready ? __('Ready', WABE_TEXTDOMAIN) : __('No topics found', WABE_TEXTDOMAIN)); ?>
                                    </span>
                                    <a class="button"
                                        href="<?php echo esc_url(admin_url('admin.php?page=wabe-topics')); ?>">
                                        <?php esc_html_e('Open Topics', WABE_TEXTDOMAIN); ?>
                                    </a>
                                </div>
                                <small><?php esc_html_e('You can register up to 10 topics. Automatic generation works best when topics are prepared in advance.', WABE_TEXTDOMAIN); ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="wabe-card">
                        <div class="wabe-section-head">
                            <div>
                                <h2><?php esc_html_e('Featured Image', WABE_TEXTDOMAIN); ?></h2>
                                <p class="wabe-help" style="margin-bottom:0;">
                                    <?php esc_html_e('Generate featured images automatically using OpenAI image generation or Gemini Imagen.', WABE_TEXTDOMAIN); ?>
                                </p>
                            </div>
                            <?php echo wp_kses_post(wabe_settings_feature_badge($can_use_images)); ?>
                        </div>

                        <div class="wabe-switch-list">
                            <label class="wabe-switch">
                                <input type="checkbox" name="wabe_enable_featured_image" value="1"
                                    <?php checked($enable_featured_image); ?> <?php disabled(!$can_use_images); ?>>
                                <div>
                                    <h3><?php esc_html_e('Enable Featured Image Generation', WABE_TEXTDOMAIN); ?></h3>
                                    <p>
                                        <?php esc_html_e('Automatically create and attach a featured image when a post is generated.', WABE_TEXTDOMAIN); ?>
                                        <?php if (!$can_use_images) : ?>
                                            <br><span
                                                class="wabe-lock"><?php echo esc_html(wabe_settings_lock_text('Advanced / Pro')); ?></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </label>
                        </div>

                        <div class="wabe-form-grid" style="margin-top:16px;">
                            <div class="wabe-field">
                                <label
                                    for="wabe_image_style"><?php esc_html_e('Image Style', WABE_TEXTDOMAIN); ?></label>
                                <select id="wabe_image_style" name="wabe_image_style"
                                    <?php disabled(!$can_use_images); ?>>
                                    <?php foreach (wabe_settings_style_options() as $style_key => $style_label) : ?>
                                        <option value="<?php echo esc_attr($style_key); ?>"
                                            <?php selected($image_style, $style_key); ?>>
                                            <?php echo esc_html($style_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small><?php esc_html_e('Controls the prompt style used during featured image generation.', WABE_TEXTDOMAIN); ?></small>
                            </div>

                            <div class="wabe-field">
                                <label><?php esc_html_e('Image Provider Behavior', WABE_TEXTDOMAIN); ?></label>
                                <div class="wabe-inline">
                                    <span><?php esc_html_e('Follows the selected AI Provider above.', WABE_TEXTDOMAIN); ?></span>
                                </div>
                                <small><?php esc_html_e('OpenAI uses image generation APIs. Gemini uses Imagen when available.', WABE_TEXTDOMAIN); ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="wabe-card">
                        <div class="wabe-section-head">
                            <div>
                                <h2><?php esc_html_e('SEO / Internal Links / Advanced Automation', WABE_TEXTDOMAIN); ?>
                                </h2>
                                <p class="wabe-help" style="margin-bottom:0;">
                                    <?php esc_html_e('These are the monetizable product features. They are visually grouped to make plan differences obvious for customers and easier to manage for admins.', WABE_TEXTDOMAIN); ?>
                                </p>
                            </div>
                            <span class="wabe-pill wabe-pill-plan"><?php echo esc_html($upgrade_target); ?>+</span>
                        </div>

                        <div class="wabe-switch-list">
                            <label class="wabe-switch">
                                <input type="checkbox" name="wabe_enable_seo" value="1" <?php checked($enable_seo); ?>
                                    <?php disabled(!$can_use_seo); ?>>
                                <div>
                                    <h3><?php esc_html_e('Enable SEO Optimization', WABE_TEXTDOMAIN); ?></h3>
                                    <p>
                                        <?php esc_html_e('Use SEO-oriented prompt instructions and optimization flow.', WABE_TEXTDOMAIN); ?>
                                        <?php if (!$can_use_seo) : ?>
                                            <br><span
                                                class="wabe-lock"><?php echo esc_html(wabe_settings_lock_text('Advanced / Pro')); ?></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </label>

                            <label class="wabe-switch">
                                <input type="checkbox" name="wabe_enable_internal_links" value="1"
                                    <?php checked($enable_internal_links); ?> <?php disabled(!$can_use_internal); ?>>
                                <div>
                                    <h3><?php esc_html_e('Enable Internal Links', WABE_TEXTDOMAIN); ?></h3>
                                    <p>
                                        <?php esc_html_e('Inject internal links or guidance into generated content.', WABE_TEXTDOMAIN); ?>
                                        <?php if (!$can_use_internal) : ?>
                                            <br><span
                                                class="wabe-lock"><?php echo esc_html(wabe_settings_lock_text('Pro')); ?></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </label>

                            <label class="wabe-switch">
                                <input type="checkbox" name="wabe_enable_external_links" value="1"
                                    <?php checked($enable_external_links); ?> <?php disabled(!$can_use_external); ?>>
                                <div>
                                    <h3><?php esc_html_e('Enable External Links', WABE_TEXTDOMAIN); ?></h3>
                                    <p>
                                        <?php esc_html_e('Allow trusted outbound references or product/service links in generated posts.', WABE_TEXTDOMAIN); ?>
                                        <?php if (!$can_use_external) : ?>
                                            <br><span
                                                class="wabe-lock"><?php echo esc_html(wabe_settings_lock_text('Pro')); ?></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </label>

                            <label class="wabe-switch">
                                <input type="checkbox" name="wabe_enable_topic_prediction" value="1"
                                    <?php checked($enable_topic_prediction); ?>
                                    <?php disabled(!$can_use_prediction); ?>>
                                <div>
                                    <h3><?php esc_html_e('Enable Topic Prediction', WABE_TEXTDOMAIN); ?></h3>
                                    <p>
                                        <?php esc_html_e('Use prompt enhancements for more relevant topic proposals and content direction.', WABE_TEXTDOMAIN); ?>
                                        <?php if (!$can_use_prediction) : ?>
                                            <br><span
                                                class="wabe-lock"><?php echo esc_html(wabe_settings_lock_text('Pro')); ?></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </label>

                            <label class="wabe-switch">
                                <input type="checkbox" name="wabe_enable_duplicate_check" value="1"
                                    <?php checked($enable_duplicate_check); ?> <?php disabled(!$can_use_duplicate); ?>>
                                <div>
                                    <h3><?php esc_html_e('Enable Duplicate Check', WABE_TEXTDOMAIN); ?></h3>
                                    <p>
                                        <?php esc_html_e('Prepare generated content to avoid repetitive output across topics and history.', WABE_TEXTDOMAIN); ?>
                                        <?php if (!$can_use_duplicate) : ?>
                                            <br><span
                                                class="wabe-lock"><?php echo esc_html(wabe_settings_lock_text('Pro')); ?></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </label>

                            <label class="wabe-switch">
                                <input type="checkbox" name="wabe_enable_outline_generator" value="1"
                                    <?php checked($enable_outline_generator); ?> <?php disabled(!$can_use_outline); ?>>
                                <div>
                                    <h3><?php esc_html_e('Enable Outline Generator', WABE_TEXTDOMAIN); ?></h3>
                                    <p>
                                        <?php esc_html_e('Use a more structured heading and article outline workflow before final text generation.', WABE_TEXTDOMAIN); ?>
                                        <?php if (!$can_use_outline) : ?>
                                            <br><span
                                                class="wabe-lock"><?php echo esc_html(wabe_settings_lock_text('Pro')); ?></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </label>
                        </div>

                        <div class="wabe-form-grid" style="margin-top:16px;">
                            <div class="wabe-field">
                                <label
                                    for="wabe_seo_keyword"><?php esc_html_e('Primary SEO Keyword', WABE_TEXTDOMAIN); ?></label>
                                <input id="wabe_seo_keyword" type="text" name="wabe_seo_keyword"
                                    value="<?php echo esc_attr($opt['seo_keyword'] ?? ''); ?>"
                                    <?php disabled(!$can_use_seo); ?>>
                                <small><?php esc_html_e('Optional keyword guidance for generated posts.', WABE_TEXTDOMAIN); ?></small>
                            </div>

                            <div class="wabe-field">
                                <label
                                    for="wabe_internal_link_url"><?php esc_html_e('Internal Link Base URL', WABE_TEXTDOMAIN); ?></label>
                                <input id="wabe_internal_link_url" type="url" name="wabe_internal_link_url"
                                    value="<?php echo esc_attr($opt['internal_link_url'] ?? ''); ?>"
                                    <?php disabled(!$can_use_internal); ?>>
                                <small><?php esc_html_e('Example: a category page, service page, or pillar page on your own site.', WABE_TEXTDOMAIN); ?></small>
                            </div>

                            <div class="wabe-field">
                                <label
                                    for="wabe_external_link_url"><?php esc_html_e('External Link URL', WABE_TEXTDOMAIN); ?></label>
                                <input id="wabe_external_link_url" type="url" name="wabe_external_link_url"
                                    value="<?php echo esc_attr($opt['external_link_url'] ?? ''); ?>"
                                    <?php disabled(!$can_use_external); ?>>
                                <small><?php esc_html_e('Optional trusted outbound URL used when external links are enabled.', WABE_TEXTDOMAIN); ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="wabe-actions">
                        <button type="submit"
                            class="button button-primary"><?php esc_html_e('Save Settings', WABE_TEXTDOMAIN); ?></button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wabe-license')); ?>"
                            class="button"><?php esc_html_e('Open License', WABE_TEXTDOMAIN); ?></a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wabe-topics')); ?>"
                            class="button"><?php esc_html_e('Manage Topics', WABE_TEXTDOMAIN); ?></a>
                    </div>
                </form>
            </div>

            <div class="wabe-column">
                <div class="wabe-card">
                    <div class="wabe-section-head">
                        <div>
                            <h2><?php esc_html_e('Current Plan Limits', WABE_TEXTDOMAIN); ?></h2>
                            <p class="wabe-help" style="margin-bottom:0;">
                                <?php esc_html_e('This card makes it easy to understand what is available in the current subscription tier and what should be upsold.', WABE_TEXTDOMAIN); ?>
                            </p>
                        </div>
                    </div>

                    <div class="wabe-feature-list">
                        <div class="wabe-feature-row">
                            <div>
                                <div class="wabe-feature-name"><?php esc_html_e('Title Candidates', WABE_TEXTDOMAIN); ?>
                                </div>
                                <span
                                    class="wabe-feature-sub"><?php esc_html_e('Number of generated title options per run', WABE_TEXTDOMAIN); ?></span>
                            </div>
                            <strong><?php echo esc_html($title_count_max); ?></strong>
                        </div>

                        <div class="wabe-feature-row">
                            <div>
                                <div class="wabe-feature-name"><?php esc_html_e('Weekly Posts', WABE_TEXTDOMAIN); ?>
                                </div>
                                <span
                                    class="wabe-feature-sub"><?php esc_html_e('Maximum automated posts per week', WABE_TEXTDOMAIN); ?></span>
                            </div>
                            <strong><?php echo esc_html($weekly_posts_max); ?></strong>
                        </div>

                        <div class="wabe-feature-row">
                            <div>
                                <div class="wabe-feature-name"><?php esc_html_e('Direct Publish', WABE_TEXTDOMAIN); ?>
                                </div>
                            </div>
                            <strong><?php echo esc_html(wabe_settings_bool_label($can_publish)); ?></strong>
                        </div>

                        <div class="wabe-feature-row">
                            <div>
                                <div class="wabe-feature-name"><?php esc_html_e('Featured Images', WABE_TEXTDOMAIN); ?>
                                </div>
                            </div>
                            <strong><?php echo esc_html(wabe_settings_bool_label($can_use_images)); ?></strong>
                        </div>

                        <div class="wabe-feature-row">
                            <div>
                                <div class="wabe-feature-name"><?php esc_html_e('SEO', WABE_TEXTDOMAIN); ?></div>
                            </div>
                            <strong><?php echo esc_html(wabe_settings_bool_label($can_use_seo)); ?></strong>
                        </div>

                        <div class="wabe-feature-row">
                            <div>
                                <div class="wabe-feature-name"><?php esc_html_e('Internal Links', WABE_TEXTDOMAIN); ?>
                                </div>
                            </div>
                            <strong><?php echo esc_html(wabe_settings_bool_label($can_use_internal)); ?></strong>
                        </div>

                        <div class="wabe-feature-row">
                            <div>
                                <div class="wabe-feature-name"><?php esc_html_e('External Links', WABE_TEXTDOMAIN); ?>
                                </div>
                            </div>
                            <strong><?php echo esc_html(wabe_settings_bool_label($can_use_external)); ?></strong>
                        </div>

                        <div class="wabe-feature-row">
                            <div>
                                <div class="wabe-feature-name"><?php esc_html_e('Duplicate Check', WABE_TEXTDOMAIN); ?>
                                </div>
                            </div>
                            <strong><?php echo esc_html(wabe_settings_bool_label($can_use_duplicate)); ?></strong>
                        </div>
                    </div>
                </div>

                <div class="wabe-card wabe-guide">
                    <div class="wabe-section-head">
                        <div>
                            <h2><?php esc_html_e('Plan Guide', WABE_TEXTDOMAIN); ?></h2>
                            <p class="wabe-help" style="margin-bottom:0;">
                                <?php esc_html_e('This section helps you understand what is included in your current plan and which features are available on higher plans.', WABE_TEXTDOMAIN); ?>
                            </p>
                        </div>
                        <span class="wabe-pill wabe-pill-plan"><?php echo esc_html($plan_label); ?></span>
                    </div>

                    <div class="wabe-kpi-grid">
                        <div class="wabe-kpi">
                            <strong><?php echo esc_html($weekly_posts_max); ?></strong>
                            <span><?php esc_html_e('Current weekly automation limit', WABE_TEXTDOMAIN); ?></span>
                        </div>
                        <div class="wabe-kpi">
                            <strong><?php echo esc_html($title_count_max); ?></strong>
                            <span><?php esc_html_e('Current title candidate limit', WABE_TEXTDOMAIN); ?></span>
                        </div>
                    </div>

                    <ul class="wabe-list" style="margin-top:14px;">
                        <li><?php esc_html_e('Free is suitable for basic testing with title generation, article drafts, and simple workflow checks.', WABE_TEXTDOMAIN); ?>
                        </li>
                        <li><?php esc_html_e('Advanced is suitable for users who want automatic posting, direct publishing, and featured image support.', WABE_TEXTDOMAIN); ?>
                        </li>
                        <li><?php esc_html_e('Pro is suitable for users who want stronger SEO support, internal links, topic prediction, and more advanced content automation.', WABE_TEXTDOMAIN); ?>
                        </li>
                        <li><?php esc_html_e('If a feature is shown as locked, you can continue using your current settings and enable that feature after upgrading your plan.', WABE_TEXTDOMAIN); ?>
                        </li>
                    </ul>
                </div>

                <div class="wabe-card">
                    <div class="wabe-section-head">
                        <div>
                            <h2><?php esc_html_e('Quick Actions', WABE_TEXTDOMAIN); ?></h2>
                            <p class="wabe-help" style="margin-bottom:0;">
                                <?php esc_html_e('Use these during development, QA, and demo environments.', WABE_TEXTDOMAIN); ?>
                            </p>
                        </div>
                    </div>

                    <div class="wabe-actions" style="margin-top:0;">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                            style="margin:0;">
                            <input type="hidden" name="action" value="wabe_manual_generate">
                            <?php wp_nonce_field('wabe_manual_generate', 'wabe_manual_generate_nonce'); ?>
                            <button type="submit"
                                class="button button-primary"><?php esc_html_e('Generate Now', WABE_TEXTDOMAIN); ?></button>
                        </form>

                        <a href="<?php echo esc_url(admin_url('admin.php?page=wabe-logs')); ?>"
                            class="button"><?php esc_html_e('Open Logs', WABE_TEXTDOMAIN); ?></a>
                    </div>

                    <ul class="wabe-list" style="margin-top:16px;">
                        <li><?php esc_html_e('Make sure at least one topic is registered before automatic posting.', WABE_TEXTDOMAIN); ?>
                        </li>
                        <li><?php esc_html_e('Automatic posting starts when WordPress is accessed, so low-traffic sites may experience delays.', WABE_TEXTDOMAIN); ?>
                        </li>
                        <li><?php esc_html_e('If you need more stable operation, ask your server provider or developer to set up periodic server-side access for WordPress.', WABE_TEXTDOMAIN); ?>
                        </li>
                        <li><?php esc_html_e('Use Draft mode first while testing prompts and image generation quality.', WABE_TEXTDOMAIN); ?>
                        </li>
                    </ul>
                </div>

                <div class="wabe-card">
                    <div class="wabe-section-head">
                        <div>
                            <h2><?php esc_html_e('How Automatic Posting Works', WABE_TEXTDOMAIN); ?></h2>
                            <p class="wabe-help" style="margin-bottom:0;">
                                <?php esc_html_e('This section explains why automatic posting may be delayed even when your settings are correct.', WABE_TEXTDOMAIN); ?>
                            </p>
                        </div>
                    </div>

                    <ul class="wabe-list">
                        <li><?php esc_html_e('Automatic posting runs when WordPress is accessed by a visitor or an administrator.', WABE_TEXTDOMAIN); ?>
                        </li>
                        <li><?php esc_html_e('If nobody opens the site, the scheduled posting process may not run at the expected time.', WABE_TEXTDOMAIN); ?>
                        </li>
                        <li><?php esc_html_e('This is more likely on new or low-traffic websites.', WABE_TEXTDOMAIN); ?>
                        </li>
                        <li><?php esc_html_e('For more stable operation, ask your server provider or developer to configure a server-side scheduled request for WordPress.', WABE_TEXTDOMAIN); ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
