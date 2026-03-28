<?php
if (!defined('ABSPATH')) exit;

class WABE_Sitemap
{
    /**
     * 初期化
     *
     * @return void
     */
    public static function init()
    {
        add_filter('robots_txt', [__CLASS__, 'append_sitemap_to_robots'], 10, 2);

        add_action('transition_post_status', [__CLASS__, 'handle_post_status_change'], 10, 3);
        add_action('deleted_post', [__CLASS__, 'handle_post_deleted'], 10, 1);
    }

    /**
     * robots.txt に Sitemap を追記
     *
     * @param string $output
     * @param bool   $public
     * @return string
     */
    public static function append_sitemap_to_robots($output, $public)
    {
        if (!$public) {
            return $output;
        }

        $sitemap_url = self::get_sitemap_url();
        if ($sitemap_url === '') {
            return $output;
        }

        if (strpos($output, $sitemap_url) !== false) {
            return $output;
        }

        $line = 'Sitemap: ' . esc_url_raw($sitemap_url);

        $output = rtrim((string) $output);
        if ($output !== '') {
            $output .= "\n";
        }

        $output .= $line . "\n";

        return $output;
    }

    /**
     * 投稿ステータス変更時
     *
     * @param string  $new_status
     * @param string  $old_status
     * @param WP_Post $post
     * @return void
     */
    public static function handle_post_status_change($new_status, $old_status, $post)
    {
        if (!($post instanceof WP_Post)) {
            return;
        }

        if ($post->post_type !== 'post') {
            return;
        }

        if ($new_status !== 'publish') {
            return;
        }

        // 初回公開・再公開どちらでもログは残す
        $post_url    = get_permalink($post->ID);
        $sitemap_url = self::get_sitemap_url();

        if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'info')) {
            WABE_Logger::info(
                sprintf(
                    'Google discovery ready. Post published: %s | sitemap: %s',
                    $post_url ? $post_url : '(unknown)',
                    $sitemap_url !== '' ? $sitemap_url : '(not found)'
                )
            );
        }

        /**
         * 補足:
         * Google の旧 sitemap ping endpoint は廃止済みのため、
         * ここでは ping せず、sitemap / robots.txt ベースでの発見に寄せる。
         */
    }

    /**
     * 投稿削除時
     *
     * @param int $post_id
     * @return void
     */
    public static function handle_post_deleted($post_id)
    {
        $post_id = absint($post_id);
        if ($post_id <= 0) {
            return;
        }

        if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'info')) {
            WABE_Logger::info(
                sprintf(
                    'Post deleted. Sitemap will be updated by WordPress/SEO plugin if applicable. Post ID: %d',
                    $post_id
                )
            );
        }
    }

    /**
     * サイトマップURL取得
     *
     * 優先順:
     * 1) Yoast SEO
     * 2) Rank Math
     * 3) WordPress Core sitemap
     * 4) 一般的な候補URLの存在確認
     *
     * @return string
     */
    public static function get_sitemap_url()
    {
        // Yoast SEO
        if (defined('WPSEO_VERSION')) {
            return home_url('/sitemap_index.xml');
        }

        // Rank Math
        if (defined('RANK_MATH_VERSION')) {
            return home_url('/sitemap_index.xml');
        }

        // WordPress core sitemap
        if (function_exists('wp_sitemaps_get_server')) {
            return home_url('/wp-sitemap.xml');
        }

        // フォールバック候補
        $candidates = [
            home_url('/wp-sitemap.xml'),
            home_url('/sitemap_index.xml'),
            home_url('/sitemap.xml'),
        ];

        foreach ($candidates as $candidate) {
            if (self::url_exists($candidate)) {
                return $candidate;
            }
        }

        return home_url('/wp-sitemap.xml');
    }

    /**
     * URLの存在をざっくり確認
     *
     * @param string $url
     * @return bool
     */
    private static function url_exists($url)
    {
        if (!function_exists('wp_remote_head')) {
            return false;
        }

        $response = wp_remote_head($url, [
            'timeout'     => 5,
            'redirection' => 3,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        return ($code >= 200 && $code < 400);
    }

    /**
     * 管理画面などで使う情報取得
     *
     * @return array
     */
    public static function get_status()
    {
        $sitemap_url = self::get_sitemap_url();
        $robots_url  = home_url('/robots.txt');

        return [
            'sitemap_url' => $sitemap_url,
            'robots_url'  => $robots_url,
            'enabled'     => ($sitemap_url !== ''),
        ];
    }
}
