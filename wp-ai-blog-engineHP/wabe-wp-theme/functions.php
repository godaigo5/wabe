<?php
if (!defined('ABSPATH')) exit;

/**
 * Theme setup
 */
function wabe_theme_setup()
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('menus');

    register_nav_menus([
        'primary' => __('Primary Menu', 'wabe-wp-theme'),
    ]);
}
add_action('after_setup_theme', 'wabe_theme_setup');

/**
 * Assets
 */
function wabe_enqueue_assets()
{
    wp_enqueue_style('wabe-style', get_stylesheet_uri(), [], '1.1.0');
    wp_enqueue_script(
        'wabe-script',
        get_template_directory_uri() . '/assets/js/script.js',
        [],
        '1.1.0',
        true
    );
}
add_action('wp_enqueue_scripts', 'wabe_enqueue_assets');

/**
 * Role
 */
function wabe_add_customer_role()
{
    add_role(
        'wabe_customer',
        'WABE Customer',
        [
            'read' => true,
        ]
    );
}
add_action('after_switch_theme', 'wabe_add_customer_role');

/**
 * Helper URLs
 */
function wabe_page_url_by_slug($slug)
{
    $page = get_page_by_path($slug);
    if ($page) {
        return get_permalink($page->ID);
    }
    return home_url('/' . $slug . '/');
}

function wabe_register_url()
{
    return wabe_page_url_by_slug('register');
}

function wabe_member_url()
{
    return wabe_page_url_by_slug('member');
}

function wabe_thanks_url()
{
    return wabe_page_url_by_slug('thanks');
}

function wabe_logout_url()
{
    return wp_logout_url(home_url('/'));
}

function wabe_cta_primary_link()
{
    return esc_url(home_url('/#pricing'));
}

function wabe_cta_secondary_link()
{
    return esc_url(home_url('/#faq'));
}

/**
 * Fallback navigation
 */
function wabe_menu_fallback()
{
    echo '<ul class="menu-fallback">';

    if (is_front_page()) {
        echo '<li><a href="#features">特長</a></li>';
        echo '<li><a href="#benefits">導入メリット</a></li>';
        echo '<li><a href="#pricing">料金プラン</a></li>';
        echo '<li><a href="#guide">導入案内</a></li>';
        echo '<li><a href="#faq">よくある質問</a></li>';
    } else {
        echo '<li><a href="' . esc_url(home_url('/')) . '">トップ</a></li>';
        echo '<li><a href="' . esc_url(home_url('/free/')) . '">Free</a></li>';
        echo '<li><a href="' . esc_url(home_url('/advanced/')) . '">Advanced</a></li>';
        echo '<li><a href="' . esc_url(home_url('/pro/')) . '">Pro</a></li>';
        echo '<li><a href="' . esc_url(wabe_register_url()) . '">会員登録</a></li>';
        echo '<li><a href="' . esc_url(wabe_member_url()) . '">会員ページ</a></li>';
    }

    echo '</ul>';
}

/**
 * Stripe links
 * 本番のPayment Linkに差し替えてください
 */
if (!defined('WABE_STRIPE_FREE_URL')) {
    define('WABE_STRIPE_FREE_URL', 'free');
}
if (!defined('WABE_STRIPE_ADVANCED_MONTHLY_URL')) {
    define('WABE_STRIPE_ADVANCED_MONTHLY_URL', 'https://buy.stripe.com/test_9B69AVaca6zw6kT9PCgA800');
}
if (!defined('WABE_STRIPE_ADVANCED_YEARLY_URL')) {
    define('WABE_STRIPE_ADVANCED_YEARLY_URL', 'https://buy.stripe.com/test_fZucN72JIga638Hge0gA801');
}
if (!defined('WABE_STRIPE_ADVANCED_LIFETIME_URL')) {
    define('WABE_STRIPE_ADVANCED_LIFETIME_URL', 'https://buy.stripe.com/test_00waEZ2JI8HE24Dge0gA802');
}
if (!defined('WABE_STRIPE_PRO_MONTHLY_URL')) {
    define('WABE_STRIPE_PRO_MONTHLY_URL', 'https://buy.stripe.com/test_6oUfZj1FE1fcfVt7HugA803');
}
if (!defined('WABE_STRIPE_PRO_YEARLY_URL')) {
    define('WABE_STRIPE_PRO_YEARLY_URL', 'https://buy.stripe.com/test_fZucN72JIga638Hge0gA801');
}
if (!defined('WABE_STRIPE_PRO_LIFETIME_URL')) {
    define('WABE_STRIPE_PRO_LIFETIME_URL', 'https://buy.stripe.com/test_cNiaEZcki7DAdNld1OgA805');
}

/**
 * License API URL
 * 既存のAPIに接続
 */
if (!defined('WABE_MEMBER_LICENSE_API_URL')) {
    define('WABE_MEMBER_LICENSE_API_URL', 'https://wabep-api.d-create.online/');
}

/**
 * Default domain helper
 */
function wabe_member_default_domain()
{
    $host = wp_parse_url(home_url(), PHP_URL_HOST);
    return $host ? $host : '';
}

function wabe_mask_license_key($key)
{
    $key = (string) $key;
    if ($key === '') return '';
    $len = strlen($key);
    if ($len <= 8) return str_repeat('*', $len);
    return substr($key, 0, 4) . str_repeat('*', max(4, $len - 8)) . substr($key, -4);
}

/**
 * API request helper
 */
function wabe_license_api_request($endpoint, array $body = [])
{
    $base = trailingslashit(WABE_MEMBER_LICENSE_API_URL);
    $url  = $base . ltrim($endpoint, '/');

    $response = wp_remote_post($url, [
        'timeout' => 20,
        'headers' => [
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept'       => 'application/json',
        ],
        'body' => wp_json_encode($body),
    ]);

    if (is_wp_error($response)) {
        return [
            'ok'      => false,
            'message' => 'API通信に失敗しました: ' . $response->get_error_message(),
            'data'    => [],
        ];
    }

    $code = wp_remote_retrieve_response_code($response);
    $raw  = wp_remote_retrieve_body($response);
    $json = json_decode($raw, true);

    if (!is_array($json)) {
        return [
            'ok'      => false,
            'message' => 'APIレスポンスの解析に失敗しました。',
            'data'    => [
                'http_code' => $code,
                'raw'       => $raw,
            ],
        ];
    }

    $ok = false;
    if (isset($json['success'])) {
        $ok = (bool) $json['success'];
    } elseif (isset($json['valid'])) {
        $ok = (bool) $json['valid'];
    } elseif ($code >= 200 && $code < 300) {
        $ok = true;
    }

    $message = '';
    if (!empty($json['message'])) {
        $message = (string) $json['message'];
    } elseif (!empty($json['error'])) {
        $message = (string) $json['error'];
    } else {
        $message = $ok ? '処理が完了しました。' : '処理に失敗しました。';
    }

    return [
        'ok'      => $ok,
        'message' => $message,
        'data'    => $json,
    ];
}

/**
 * Registration handler
 */
function wabe_handle_member_auth_actions()
{
    if (is_admin()) return;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    if (empty($_POST['wabe_action'])) return;

    $action = sanitize_text_field(wp_unslash($_POST['wabe_action']));

    if ($action === 'wabe_register') {
        if (!isset($_POST['wabe_register_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wabe_register_nonce'])), 'wabe_register_action')) {
            return;
        }

        $name     = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $email    = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
        $agree    = !empty($_POST['agree']);

        $redirect_url = add_query_arg('auth', 'error', wabe_register_url());

        if ($name === '' || $email === '' || $password === '') {
            wp_safe_redirect(add_query_arg('msg', 'empty', $redirect_url));
            exit;
        }

        if (!is_email($email)) {
            wp_safe_redirect(add_query_arg('msg', 'invalid_email', $redirect_url));
            exit;
        }

        if (email_exists($email)) {
            wp_safe_redirect(add_query_arg('msg', 'exists', $redirect_url));
            exit;
        }

        if (strlen($password) < 8) {
            wp_safe_redirect(add_query_arg('msg', 'weak_password', $redirect_url));
            exit;
        }

        if (!$agree) {
            wp_safe_redirect(add_query_arg('msg', 'agree_required', $redirect_url));
            exit;
        }

        $username_base = sanitize_user(current(explode('@', $email)), true);
        if ($username_base === '') {
            $username_base = 'user';
        }

        $username = $username_base;
        $suffix = 1;
        while (username_exists($username)) {
            $username = $username_base . $suffix;
            $suffix++;
        }

        $user_id = wp_insert_user([
            'user_login'   => $username,
            'user_pass'    => $password,
            'user_email'   => $email,
            'display_name' => $name,
            'role'         => 'wabe_customer',
        ]);

        if (is_wp_error($user_id)) {
            wp_safe_redirect(add_query_arg('msg', 'register_failed', $redirect_url));
            exit;
        }

        update_user_meta($user_id, 'wabe_customer_name', $name);

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        wp_safe_redirect(add_query_arg('welcome', '1', wabe_member_url()));
        exit;
    }

    if ($action === 'wabe_login') {
        if (!isset($_POST['wabe_login_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wabe_login_nonce'])), 'wabe_login_action')) {
            return;
        }

        $email_or_login = isset($_POST['log']) ? sanitize_text_field(wp_unslash($_POST['log'])) : '';
        $password       = isset($_POST['pwd']) ? (string) wp_unslash($_POST['pwd']) : '';

        $user_login = $email_or_login;
        if (is_email($email_or_login)) {
            $user = get_user_by('email', $email_or_login);
            if ($user) {
                $user_login = $user->user_login;
            }
        }

        $creds = [
            'user_login'    => $user_login,
            'user_password' => $password,
            'remember'      => true,
        ];

        $signon = wp_signon($creds, false);

        if (is_wp_error($signon)) {
            wp_safe_redirect(add_query_arg([
                'auth' => 'error',
                'msg'  => 'login_failed',
            ], wabe_register_url()));
            exit;
        }

        wp_safe_redirect(wabe_member_url());
        exit;
    }

    if ($action === 'wabe_license_check' && is_user_logged_in()) {
        if (!isset($_POST['wabe_license_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wabe_license_nonce'])), 'wabe_license_action')) {
            return;
        }

        $user_id     = get_current_user_id();
        $license_key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';
        $domain      = isset($_POST['domain']) ? sanitize_text_field(wp_unslash($_POST['domain'])) : wabe_member_default_domain();

        if ($license_key === '') {
            wp_safe_redirect(add_query_arg('license_msg', 'empty_key', wabe_member_url()));
            exit;
        }

        $result = wabe_license_api_request('/license/check', [
            'license_key' => $license_key,
            'domain'      => $domain,
        ]);

        wabe_save_license_meta($user_id, $license_key, $result, $domain);

        wp_safe_redirect(add_query_arg('license_msg', $result['ok'] ? 'checked_ok' : 'checked_ng', wabe_member_url()));
        exit;
    }

    if ($action === 'wabe_license_activate' && is_user_logged_in()) {
        if (!isset($_POST['wabe_license_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wabe_license_nonce'])), 'wabe_license_action')) {
            return;
        }

        $user_id     = get_current_user_id();
        $license_key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';
        $domain      = isset($_POST['domain']) ? sanitize_text_field(wp_unslash($_POST['domain'])) : wabe_member_default_domain();

        if ($license_key === '') {
            wp_safe_redirect(add_query_arg('license_msg', 'empty_key', wabe_member_url()));
            exit;
        }

        $result = wabe_license_api_request('/license/activate', [
            'license_key' => $license_key,
            'domain'      => $domain,
        ]);

        wabe_save_license_meta($user_id, $license_key, $result, $domain);

        wp_safe_redirect(add_query_arg('license_msg', $result['ok'] ? 'activate_ok' : 'activate_ng', wabe_member_url()));
        exit;
    }

    if ($action === 'wabe_license_deactivate' && is_user_logged_in()) {
        if (!isset($_POST['wabe_license_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wabe_license_nonce'])), 'wabe_license_action')) {
            return;
        }

        $user_id = get_current_user_id();
        $stored  = wabe_get_user_license_data($user_id);

        $license_key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : $stored['license_key'];
        $domain      = isset($_POST['domain']) ? sanitize_text_field(wp_unslash($_POST['domain'])) : $stored['licensed_domain'];

        if ($license_key === '') {
            wp_safe_redirect(add_query_arg('license_msg', 'empty_key', wabe_member_url()));
            exit;
        }

        $result = wabe_license_api_request('/license/deactivate', [
            'license_key' => $license_key,
            'domain'      => $domain,
        ]);

        if (!empty($result['ok'])) {
            wabe_clear_license_meta($user_id);
        } else {
            wabe_save_license_meta($user_id, $license_key, $result, $domain);
        }

        wp_safe_redirect(add_query_arg('license_msg', $result['ok'] ? 'deactivate_ok' : 'deactivate_ng', wabe_member_url()));
        exit;
    }
}
add_action('template_redirect', 'wabe_handle_member_auth_actions');

/**
 * Access control
 */
function wabe_member_page_guard()
{
    if (is_admin()) return;

    if (is_page('member') && !is_user_logged_in()) {
        wp_safe_redirect(wabe_register_url());
        exit;
    }
}
add_action('template_redirect', 'wabe_member_page_guard', 1);

/**
 * Flash messages
 */
function wabe_auth_message()
{
    if (empty($_GET['msg'])) return '';

    $msg = sanitize_text_field(wp_unslash($_GET['msg']));
    $map = [
        'empty'          => '未入力の項目があります。',
        'invalid_email'  => 'メールアドレスの形式が正しくありません。',
        'exists'         => 'そのメールアドレスは既に登録されています。',
        'weak_password'  => 'パスワードは8文字以上で入力してください。',
        'agree_required' => '利用規約への同意が必要です。',
        'register_failed' => '会員登録に失敗しました。',
        'login_failed'   => 'ログインに失敗しました。入力内容をご確認ください。',
    ];

    return isset($map[$msg]) ? $map[$msg] : '';
}

function wabe_license_message()
{
    if (empty($_GET['license_msg'])) return '';

    $msg = sanitize_text_field(wp_unslash($_GET['license_msg']));
    $map = [
        'empty_key'     => 'ライセンスキーを入力してください。',
        'checked_ok'    => 'ライセンス情報を確認しました。',
        'checked_ng'    => 'ライセンス確認に失敗しました。',
        'activate_ok'   => 'ライセンスを有効化しました。',
        'activate_ng'   => 'ライセンス有効化に失敗しました。',
        'deactivate_ok' => 'ライセンスを無効化しました。',
        'deactivate_ng' => 'ライセンス無効化に失敗しました。',
    ];

    return isset($map[$msg]) ? $map[$msg] : '';
}


/**
 * User licenses (multi-license support)
 */
function wabe_get_user_licenses($user_id = 0)
{
    $user_id = $user_id ? absint($user_id) : get_current_user_id();
    $licenses = get_user_meta($user_id, 'wabe_member_licenses', true);

    if (!is_array($licenses)) {
        $licenses = [];
    }

    $normalized = [];
    foreach ($licenses as $row) {
        if (!is_array($row)) {
            continue;
        }

        $license_key = isset($row['license_key']) ? sanitize_text_field((string) $row['license_key']) : '';
        if ($license_key === '') {
            continue;
        }

        $normalized[] = [
            'license_key'       => $license_key,
            'license_plan'      => isset($row['license_plan']) ? sanitize_text_field((string) $row['license_plan']) : '',
            'license_status'    => isset($row['license_status']) ? sanitize_text_field((string) $row['license_status']) : '',
            'licensed_domain'   => isset($row['licensed_domain']) ? sanitize_text_field((string) $row['licensed_domain']) : '',
            'license_message'   => isset($row['license_message']) ? sanitize_text_field((string) $row['license_message']) : '',
            'license_checked'   => isset($row['license_checked']) ? sanitize_text_field((string) $row['license_checked']) : '',
            'license_active_at' => isset($row['license_active_at']) ? sanitize_text_field((string) $row['license_active_at']) : '',
        ];
    }

    if (empty($normalized)) {
        $legacy = [
            'license_key'       => (string) get_user_meta($user_id, 'wabe_license_key', true),
            'license_plan'      => (string) get_user_meta($user_id, 'wabe_license_plan', true),
            'license_status'    => (string) get_user_meta($user_id, 'wabe_license_status', true),
            'licensed_domain'   => (string) get_user_meta($user_id, 'wabe_licensed_domain', true),
            'license_message'   => (string) get_user_meta($user_id, 'wabe_license_message', true),
            'license_checked'   => (string) get_user_meta($user_id, 'wabe_license_checked', true),
            'license_active_at' => (string) get_user_meta($user_id, 'wabe_license_active_at', true),
        ];

        if ($legacy['license_key'] !== '') {
            $normalized[] = $legacy;
            update_user_meta($user_id, 'wabe_member_licenses', $normalized);
        }
    }

    usort($normalized, function ($a, $b) {
        return strcmp((string) ($b['license_checked'] ?? ''), (string) ($a['license_checked'] ?? ''));
    });

    return $normalized;
}

function wabe_update_user_license_list($user_id, $license_key, array $api_result = [], $domain = '')
{
    $user_id     = absint($user_id);
    $license_key = sanitize_text_field((string) $license_key);
    $domain      = sanitize_text_field((string) $domain);

    if ($user_id <= 0 || $license_key === '') {
        return;
    }

    $licenses = wabe_get_user_licenses($user_id);
    $data = isset($api_result['data']) && is_array($api_result['data']) ? $api_result['data'] : [];

    $plan = '';
    if (!empty($data['plan'])) {
        $plan = sanitize_text_field($data['plan']);
    } elseif (!empty($data['tier'])) {
        $plan = sanitize_text_field($data['tier']);
    }

    $status = '';
    if (!empty($data['status'])) {
        $status = sanitize_text_field($data['status']);
    } else {
        $status = !empty($api_result['ok']) ? 'active' : 'invalid';
    }

    $new_row = [
        'license_key'       => $license_key,
        'license_plan'      => $plan,
        'license_status'    => $status,
        'licensed_domain'   => $domain,
        'license_message'   => sanitize_text_field($api_result['message'] ?? ''),
        'license_checked'   => current_time('mysql'),
        'license_active_at' => !empty($api_result['ok']) ? current_time('mysql') : '',
    ];

    $updated = false;
    foreach ($licenses as $index => $row) {
        if (($row['license_key'] ?? '') === $license_key) {
            $licenses[$index] = array_merge($row, $new_row);
            if (empty($licenses[$index]['license_active_at']) && !empty($row['license_active_at'])) {
                $licenses[$index]['license_active_at'] = $row['license_active_at'];
            }
            if (!empty($api_result['ok'])) {
                $licenses[$index]['license_active_at'] = current_time('mysql');
            }
            $updated = true;
            break;
        }
    }

    if (!$updated) {
        $licenses[] = $new_row;
    }

    update_user_meta($user_id, 'wabe_member_licenses', array_values($licenses));
}

function wabe_remove_user_license($user_id, $license_key)
{
    $user_id     = absint($user_id);
    $license_key = sanitize_text_field((string) $license_key);

    if ($user_id <= 0 || $license_key === '') {
        return;
    }

    $licenses = wabe_get_user_licenses($user_id);
    $filtered = [];

    foreach ($licenses as $row) {
        if (($row['license_key'] ?? '') === $license_key) {
            continue;
        }
        $filtered[] = $row;
    }

    update_user_meta($user_id, 'wabe_member_licenses', array_values($filtered));
}

/**
 * Current user main license meta
 */
function wabe_get_user_license_data($user_id = 0)
{
    $user_id = $user_id ? absint($user_id) : get_current_user_id();
    $licenses = wabe_get_user_licenses($user_id);
    $primary = [];

    foreach ($licenses as $row) {
        if (($row['license_status'] ?? '') === 'active') {
            $primary = $row;
            break;
        }
    }

    if (empty($primary) && !empty($licenses[0])) {
        $primary = $licenses[0];
    }

    return [
        'license_key'       => (string) ($primary['license_key'] ?? get_user_meta($user_id, 'wabe_license_key', true)),
        'license_plan'      => (string) ($primary['license_plan'] ?? get_user_meta($user_id, 'wabe_license_plan', true)),
        'license_status'    => (string) ($primary['license_status'] ?? get_user_meta($user_id, 'wabe_license_status', true)),
        'licensed_domain'   => (string) ($primary['licensed_domain'] ?? get_user_meta($user_id, 'wabe_licensed_domain', true)),
        'license_message'   => (string) ($primary['license_message'] ?? get_user_meta($user_id, 'wabe_license_message', true)),
        'license_checked'   => (string) ($primary['license_checked'] ?? get_user_meta($user_id, 'wabe_license_checked', true)),
        'license_active_at' => (string) ($primary['license_active_at'] ?? get_user_meta($user_id, 'wabe_license_active_at', true)),
    ];
}

/**
 * Save license meta
 */
function wabe_save_license_meta($user_id, $license_key, array $api_result = [], $domain = '')
{
    $data = isset($api_result['data']) && is_array($api_result['data']) ? $api_result['data'] : [];

    $plan = '';
    if (!empty($data['plan'])) {
        $plan = sanitize_text_field($data['plan']);
    } elseif (!empty($data['tier'])) {
        $plan = sanitize_text_field($data['tier']);
    }

    $status = '';
    if (!empty($data['status'])) {
        $status = sanitize_text_field($data['status']);
    } else {
        $status = !empty($api_result['ok']) ? 'active' : 'invalid';
    }

    update_user_meta($user_id, 'wabe_license_key', sanitize_text_field($license_key));
    update_user_meta($user_id, 'wabe_license_plan', $plan);
    update_user_meta($user_id, 'wabe_license_status', $status);
    update_user_meta($user_id, 'wabe_licensed_domain', sanitize_text_field($domain));
    update_user_meta($user_id, 'wabe_license_message', sanitize_text_field($api_result['message'] ?? ''));
    update_user_meta($user_id, 'wabe_license_checked', current_time('mysql'));

    if (!empty($api_result['ok'])) {
        update_user_meta($user_id, 'wabe_license_active_at', current_time('mysql'));
    }

    wabe_update_user_license_list($user_id, $license_key, $api_result, $domain);
}

function wabe_clear_license_meta($user_id)
{
    delete_user_meta($user_id, 'wabe_license_key');
    delete_user_meta($user_id, 'wabe_license_plan');
    delete_user_meta($user_id, 'wabe_license_status');
    delete_user_meta($user_id, 'wabe_licensed_domain');
    delete_user_meta($user_id, 'wabe_license_message');
    delete_user_meta($user_id, 'wabe_license_checked');
    delete_user_meta($user_id, 'wabe_license_active_at');
}
