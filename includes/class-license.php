<?php

if (!defined('ABSPATH')) exit;

class WABE_License
{
	const TRANSIENT_KEY = 'wabe_license_check';
	const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
     * 保存済みライセンス情報取得
     */
	public static function get_cached()
	{
		$options = get_option(WABE_OPTION, []);
		return is_array($options['license_data'] ?? null) ? $options['license_data'] : [];
	}

	/**
     * 現在のプラン取得
     */
	public static function get_plan()
	{
		$license = self::get_cached();
		return sanitize_text_field((string)($license['plan'] ?? 'free'));
	}

	/**
     * ライセンス有効判定
     */
	public static function is_valid()
	{
		$license = self::get_cached();
		return !empty($license['valid']);
	}

	/**
     * 現在の features 配列取得
     */
	public static function get_features()
	{
		$license = self::get_cached();
		return is_array($license['features'] ?? null) ? $license['features'] : self::free_payload()['features'];
	}

	/**
     * キャッシュ削除
     */
	public static function clear_cache()
	{
		delete_transient(self::TRANSIENT_KEY);
		WABE_Logger::info('License: transient cache cleared');
	}

	/**
     * ライセンス確認同期
     * $force = true ならキャッシュ無視でAPIへ再問い合わせ
     */
	public static function sync($force = false)
	{
		$options = get_option(WABE_OPTION, []);
		$license_key = trim((string)($options['license_key'] ?? ''));

		// ライセンス未入力なら Free 扱い
		if ($license_key === '') {
			$payload = self::free_payload();
			self::store($payload);
			self::clear_cache();
			WABE_Logger::info('License: empty key, fallback to free');
			return $payload;
		}

		// キャッシュ使用
		if (!$force) {
			$cached = get_transient(self::TRANSIENT_KEY);
			WABE_Logger::info('License transient: ' . print_r($cached, true));

			if (is_array($cached) && isset($cached['plan'], $cached['features'])) {
				return $cached;
			}
		} else {
			self::clear_cache();
		}

		$domain = wp_parse_url(home_url(), PHP_URL_HOST);
		if (!$domain) {
			$domain = home_url();
		}

		$url = trailingslashit(WABE_LICENSE_API_BASE) . 'license/check';

		$response = wp_remote_post($url, [
			'timeout' => 20,
			'body' => [
				'license_key' => $license_key,
				'domain'      => $domain,
				'plugin'      => 'wp-ai-blog-engine',
				'version'     => WABE_VERSION,
			],
		]);

		if (is_wp_error($response)) {
			WABE_Logger::error('License sync failed: ' . $response->get_error_message());

			$fallback = self::get_cached();
			if (!empty($fallback)) {
				WABE_Logger::warning('License: using stored fallback payload');
				return $fallback;
			}

			return self::free_payload();
		}

		$raw = wp_remote_retrieve_body($response);
		WABE_Logger::info('License response raw: ' . $raw);

		$payload = json_decode($raw, true);

		if (!is_array($payload)) {
			WABE_Logger::error('License: invalid JSON response');
			$payload = self::free_payload();
		}

		// 念のため最低限の整形
		$payload = self::normalize_payload($payload);

		self::store($payload);
		set_transient(self::TRANSIENT_KEY, $payload, self::CACHE_TTL);

		WABE_Logger::info('License sync success: plan=' . ($payload['plan'] ?? 'free'));

		return $payload;
	}

	/**
     * ライセンス有効化
     */
	public static function activate_remote()
	{
		$options = get_option(WABE_OPTION, []);
		$license_key = trim((string)($options['license_key'] ?? ''));

		if ($license_key === '') {
			WABE_Logger::warning('License activate skipped: empty key');
			return self::free_payload();
		}

		self::clear_cache();

		$domain = wp_parse_url(home_url(), PHP_URL_HOST);
		if (!$domain) {
			$domain = home_url();
		}

		$url = trailingslashit(WABE_LICENSE_API_BASE) . 'license/activate';

		$response = wp_remote_post($url, [
			'timeout' => 20,
			'body' => [
				'license_key' => $license_key,
				'domain'      => $domain,
				'plugin'      => 'wp-ai-blog-engine',
				'version'     => WABE_VERSION,
			],
		]);

		if (is_wp_error($response)) {
			WABE_Logger::error('License activate failed: ' . $response->get_error_message());
			return self::free_payload();
		}

		$raw = wp_remote_retrieve_body($response);
		WABE_Logger::info('License activate raw: ' . $raw);

		$payload = json_decode($raw, true);
		if (!is_array($payload)) {
			WABE_Logger::error('License activate invalid JSON');
			return self::free_payload();
		}

		$payload = self::normalize_payload($payload);

		self::store($payload);
		set_transient(self::TRANSIENT_KEY, $payload, self::CACHE_TTL);

		return $payload;
	}

	/**
     * ライセンス無効化
     */
	public static function deactivate_remote()
	{
		$options = get_option(WABE_OPTION, []);
		$license_key = trim((string)($options['license_key'] ?? ''));

		self::clear_cache();

		if ($license_key === '') {
			WABE_Logger::warning('License deactivate skipped: empty key');
			$payload = self::free_payload();
			self::store($payload);
			return $payload;
		}

		$domain = wp_parse_url(home_url(), PHP_URL_HOST);
		if (!$domain) {
			$domain = home_url();
		}

		$url = trailingslashit(WABE_LICENSE_API_BASE) . 'license/deactivate';

		$response = wp_remote_post($url, [
			'timeout' => 20,
			'body' => [
				'license_key' => $license_key,
				'domain'      => $domain,
			],
		]);

		if (is_wp_error($response)) {
			WABE_Logger::error('License deactivate failed: ' . $response->get_error_message());
			$payload = self::free_payload();
			self::store($payload);
			return $payload;
		}

		$raw = wp_remote_retrieve_body($response);
		WABE_Logger::info('License deactivate raw: ' . $raw);

		$payload = json_decode($raw, true);
		if (!is_array($payload)) {
			$payload = self::free_payload();
		}

		$payload = self::normalize_payload($payload);

		// 無効化後は free 相当に寄せる
		if (empty($payload['valid'])) {
			$payload = self::free_payload();
		}

		self::store($payload);

		return $payload;
	}

	/**
     * payloadをoptionへ保存
     */
	private static function store(array $payload)
	{
		$options = get_option(WABE_OPTION, []);
		$options['license_data'] = $payload;
		$options['license_checked_at'] = current_time('mysql');

		if (!empty($payload['plan'])) {
			$options['license_plan'] = sanitize_text_field((string)$payload['plan']);
		}

		if (!empty($payload['status'])) {
			$options['license_status'] = sanitize_text_field((string)$payload['status']);
		}

		update_option(WABE_OPTION, $options);

		WABE_Logger::info('License stored: plan=' . ($payload['plan'] ?? 'free'));
	}

	/**
     * 不正・不足データを最低限補完
     */
	private static function normalize_payload(array $payload)
	{
		$free = self::free_payload();

		if (!isset($payload['ok'])) {
			$payload['ok'] = false;
		}

		if (!isset($payload['valid'])) {
			$payload['valid'] = false;
		}

		if (empty($payload['plan'])) {
			$payload['plan'] = $free['plan'];
		}

		if (empty($payload['status'])) {
			$payload['status'] = $free['status'];
		}

		if (!isset($payload['features']) || !is_array($payload['features'])) {
			$payload['features'] = $free['features'];
		} else {
			$payload['features'] = wp_parse_args($payload['features'], $free['features']);
		}

		return $payload;
	}

	/**
     * Freeプランの標準payload
     */
	private static function free_payload()
	{
		return [
			'ok' => true,
			'valid' => true,
			'plan' => 'free',
			'status' => 'active',
			'features' => [
				'weekly_posts_max' => 1,
				'title_count_max' => 1,
				'can_publish' => false,
				'can_use_seo' => false,
				'can_use_images' => false,
				'can_use_topic_generator' => false,
				'can_use_internal_links' => false,
				'can_use_outline_generator' => false,
			],
		];
	}
}