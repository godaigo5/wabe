<?php

if (!defined('ABSPATH')) exit;

class WABE_Plan
{
	/**
     * 現在のライセンスデータ取得
     */
	public static function license_data()
	{
		return WABE_License::get_cached();
	}

	/**
     * 現在のプラン名
     */
	public static function get_plan()
	{
		$license = self::license_data();
		return sanitize_text_field((string)($license['plan'] ?? 'free'));
	}

	/**
     * Free判定
     */
	public static function is_free()
	{
		return self::get_plan() === 'free';
	}

	/**
     * Advanced判定
     */
	public static function is_advanced()
	{
		return self::get_plan() === 'advanced';
	}

	/**
     * Pro判定
     */
	public static function is_pro()
	{
		return self::get_plan() === 'pro';
	}

	/**
     * ライセンスの features 配列取得
     */
	public static function features()
	{
		return WABE_License::get_features();
	}

	/**
     * 週間投稿上限
     */
	public static function weekly_posts_max()
	{
		$features = self::features();
		return max(1, intval($features['weekly_posts_max'] ?? 1));
	}

	/**
     * タイトル生成上限
     */
	public static function title_count_max()
	{
		$features = self::features();
		return max(1, intval($features['title_count_max'] ?? 1));
	}

	/**
     * 記事公開可否
     */
	public static function can_publish()
	{
		$features = self::features();
		return !empty($features['can_publish']);
	}

	/**
     * SEO利用可否
     */
	public static function can_use_seo()
	{
		$features = self::features();
		return !empty($features['can_use_seo']);
	}

	/**
     * 画像生成利用可否
     */
	public static function can_use_images()
	{
		$features = self::features();
		return !empty($features['can_use_images']);
	}

	/**
     * AI題材生成利用可否
     */
	public static function can_use_topic_generator()
	{
		$features = self::features();
		return !empty($features['can_use_topic_generator']);
	}

	/**
     * 内部リンク生成利用可否
     */
	public static function can_use_internal_links()
	{
		$features = self::features();
		return !empty($features['can_use_internal_links']);
	}

	/**
     * 記事構成生成利用可否
     */
	public static function can_use_outline_generator()
	{
		$features = self::features();
		return !empty($features['can_use_outline_generator']);
	}

	/**
     * プラン表示名
     */
	public static function plan_label()
	{
		$plan = self::get_plan();

		switch ($plan) {
			case 'pro':
				return 'Pro';
			case 'advanced':
				return 'Advanced';
			case 'free':
			default:
				return 'Free';
		}
	}

	/**
     * 管理画面での機能一覧（表示用）
     */
	public static function feature_map()
	{
		return [
			'weekly_posts_max' => [
				'label' => __('Weekly Post Limit', WABE_TEXTDOMAIN),
				'value' => self::weekly_posts_max(),
				'type'  => 'number',
			],
			'title_count_max' => [
				'label' => __('Title Generation Count', WABE_TEXTDOMAIN),
				'value' => self::title_count_max(),
				'type'  => 'number',
			],
			'can_publish' => [
				'label' => __('Auto Publish', WABE_TEXTDOMAIN),
				'value' => self::can_publish(),
				'type'  => 'bool',
			],
			'can_use_seo' => [
				'label' => __('SEO Optimization', WABE_TEXTDOMAIN),
				'value' => self::can_use_seo(),
				'type'  => 'bool',
			],
			'can_use_images' => [
				'label' => __('Featured Image Generation', WABE_TEXTDOMAIN),
				'value' => self::can_use_images(),
				'type'  => 'bool',
			],
			'can_use_topic_generator' => [
				'label' => __('AI Topic Generator', WABE_TEXTDOMAIN),
				'value' => self::can_use_topic_generator(),
				'type'  => 'bool',
			],
			'can_use_internal_links' => [
				'label' => __('Internal Link Generator', WABE_TEXTDOMAIN),
				'value' => self::can_use_internal_links(),
				'type'  => 'bool',
			],
			'can_use_outline_generator' => [
				'label' => __('Article Outline Generator', WABE_TEXTDOMAIN),
				'value' => self::can_use_outline_generator(),
				'type'  => 'bool',
			],
		];
	}

	/**
     * bool表示をテキスト化
     */
	public static function bool_label($enabled)
	{
		return $enabled
			? '✓ ' . esc_html__('Enabled', WABE_TEXTDOMAIN)
			: '✗ ' . esc_html__('Disabled', WABE_TEXTDOMAIN);
	}

	/**
     * 記事公開ステータスをプランに応じて補正
     */
	public static function normalize_post_status($status)
	{
		$status = sanitize_text_field((string)$status);

		if (!self::can_publish()) {
			return 'draft';
		}

		return in_array($status, ['draft', 'publish'], true) ? $status : 'draft';
	}

	/**
     * 週間投稿数をプランに応じて補正
     */
	public static function normalize_weekly_posts($count)
	{
		$count = max(1, intval($count));
		return min($count, self::weekly_posts_max());
	}

	/**
     * タイトル生成数をプランに応じて補正
     */
	public static function normalize_title_count($count)
	{
		$count = max(1, intval($count));
		return min($count, self::title_count_max());
	}
	
	/**
     * 通貨判定
     */
	public static function currency()
	{
		$locale = get_locale();

		if (strpos($locale, 'ja') === 0) {
			return 'JPY';
		}

		return 'USD';
	}
	
	/**
     * 為替レート取得関数
     */
	public static function get_exchange_rate()
	{
		// キャッシュ取得
		$rate = get_transient('wabe_usd_jpy_rate');

		if ($rate !== false) {
			return floatval($rate);
		}
		// API
		$response = wp_remote_get('https://open.er-api.com/v6/latest/USD', [
			'timeout' => 10,
		]);
		if (is_wp_error($response)) {
			return 150;
		}
		$body = json_decode(wp_remote_retrieve_body($response), true);
		if (!empty($body['rates']['JPY'])) {
			$rate = floatval($body['rates']['JPY']);
			// 12時間キャッシュ
			set_transient('wabe_usd_jpy_rate', $rate, 12 * HOUR_IN_SECONDS);
			return $rate;
		}
		return 150;
	}
	
	/**
     * 価格フォーマット
     */
	public static function format_price($usd_price)
	{
		$currency = self::currency();

		if ($currency === 'JPY') {

			$rate = self::get_exchange_rate();
			$yen = intval($usd_price * $rate);

			return '¥' . number_format($yen) . ' / 年';
		}

		return '$' . number_format($usd_price) . ' / year';
	}
}