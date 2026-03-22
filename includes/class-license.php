<?php
if (!defined('ABSPATH')) exit;

class WABE_License
{
	const CACHE_KEY = 'wabe_license_cache';
	const CACHE_TTL = 300;
	const REMOTE_TIMEOUT = 20;

	const OPTION_LAST_SYNC   = 'wabe_license_last_sync';
	const OPTION_LAST_ERROR  = 'wabe_license_last_error';
	const OPTION_LAST_STATUS = 'wabe_license_last_status';

	/**
	 * 手動・自動同期
	 */
	public static function sync($force = false)
	{
		$cached = self::get_cached_license_data();
		if (!$force && !empty($cached)) {
			self::log('License sync cache hit: status=' . ($cached['status'] ?? '') . ' plan=' . ($cached['plan'] ?? ''));
			return $cached;
		}

		$o = get_option(WABE_OPTION, []);
		if (!is_array($o)) {
			$o = [];
		}

		$license_key = sanitize_text_field($o['license_key'] ?? '');
		if ($license_key === '') {
			$data = self::build_local_fallback_data([
				'status'      => 'inactive',
				'plan'        => 'free',
				'license_key' => '',
				'message'     => 'License key is not set.',
			]);
			self::store_license_result($data, $o);
			self::log('License sync skipped: license key is empty');
			return $data;
		}

		$remote_url = self::get_license_api_url($o);
		if ($remote_url === '') {
			$data = self::build_local_fallback_data([
				'status'      => 'inactive',
				'plan'        => 'free',
				'license_key' => $license_key,
				'message'     => 'License API URL is not configured.',
			]);
			self::store_license_result($data, $o);
			self::log('License sync failed: API URL is empty');
			return $data;
		}

		$site_url = home_url('/');
		$body = [
			'license_key' => $license_key,
			'domain'      => wp_parse_url($site_url, PHP_URL_HOST),
			'site_url'    => $site_url,
			'plugin'      => 'wp-ai-blog-engine',
			'version'     => defined('WABE_VERSION') ? WABE_VERSION : '',
		];

		self::log('License sync request URL: ' . $remote_url);
		self::log('License sync request body: ' . wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

		$response = wp_remote_post($remote_url, [
			'timeout' => self::REMOTE_TIMEOUT,
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			],
			'body' => wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
		]);

		if (is_wp_error($response)) {
			$message = $response->get_error_message();
			self::log_error('License sync WP_Error: ' . $message);

			if (!empty($cached)) {
				$cached['message'] = $message;
				$cached['checked_at'] = current_time('mysql');
				self::store_license_result($cached, $o);
				return $cached;
			}

			$data = self::build_local_fallback_data([
				'status'      => 'inactive',
				'plan'        => 'free',
				'license_key' => $license_key,
				'message'     => $message,
			]);
			self::store_license_result($data, $o);
			return $data;
		}

		$status_code = (int) wp_remote_retrieve_response_code($response);
		$raw_body = wp_remote_retrieve_body($response);

		self::log('License sync HTTP code: ' . $status_code);
		self::log('License sync raw body: ' . $raw_body);

		$decoded = json_decode($raw_body, true);

		if ($status_code < 200 || $status_code >= 300 || !is_array($decoded)) {
			$message = 'Invalid license API response.';
			if (is_array($decoded)) {
				$message = sanitize_text_field($decoded['message'] ?? $decoded['error'] ?? $message);
			}

			self::log_error('License sync invalid response: ' . $message);

			if (!empty($cached)) {
				$cached['message'] = $message;
				$cached['checked_at'] = current_time('mysql');
				self::store_license_result($cached, $o);
				return $cached;
			}

			$data = self::build_local_fallback_data([
				'status'      => 'inactive',
				'plan'        => 'free',
				'license_key' => $license_key,
				'message'     => $message,
			]);
			self::store_license_result($data, $o);
			return $data;
		}

		self::log('License sync decoded response: ' . wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

		$data = self::normalize_remote_license_response($decoded, $license_key);

		self::log('License sync normalized response: ' . wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

		self::store_license_result($data, $o);
		return $data;
	}

	/**
	 * 現在有効か
	 */
	public static function is_active()
	{
		$license = self::sync(false);
		return in_array($license['status'], ['active', 'valid', 'trial'], true);
	}

	/**
	 * 現在プラン
	 */
	public static function get_plan()
	{
		$license = self::sync(false);
		return WABE_Plan::normalize_plan($license['plan'] ?? 'free');
	}

	/**
	 * feature取得
	 */
	public static function get_features()
	{
		$license = self::sync(false);
		return WABE_Plan::normalize_features($license['features'] ?? []);
	}

	public static function get_feature($key, $default = null)
	{
		$features = self::get_features();
		return array_key_exists($key, $features) ? $features[$key] : $default;
	}

	public static function clear_cache()
	{
		delete_transient(self::CACHE_KEY);
	}

	public static function get_cached_license_data()
	{
		$cached = get_transient(self::CACHE_KEY);
		if (!is_array($cached)) {
			return [];
		}
		return self::normalize_license_data($cached);
	}

	/**
	 * 保存用API URL取得
	 */
	private static function get_license_api_url($options)
	{
		$url = '';

		if (defined('WABE_LICENSE_API_URL')) {
			$constant_value = (string) WABE_LICENSE_API_URL;
			self::log('WABE_LICENSE_API_URL defined: yes');
			self::log('WABE_LICENSE_API_URL raw: ' . $constant_value);
			$url = $constant_value;
		} else {
			self::log('WABE_LICENSE_API_URL defined: no');
		}

		if ($url === '' && !empty($options['license_api_url'])) {
			$url = (string) $options['license_api_url'];
			self::log('license_api_url option raw: ' . $url);
		}

		$url = trim($url);

		// ベースURLしか入っていない場合は自動補完
		if ($url !== '') {
			$parsed = wp_parse_url($url);
			$path = isset($parsed['path']) ? (string) $parsed['path'] : '';

			if ($path === '' || $path === '/') {
				$url = rtrim($url, '/') . '/license/check';
				self::log('License API URL auto-completed to: ' . $url);
			}
		}

		$url = esc_url_raw($url);

		self::log('License API URL final: ' . $url);

		return $url;
	}

	/**
	 * API結果を正規化
	 */
	private static function normalize_remote_license_response(array $decoded, $license_key)
	{
		$status = sanitize_key($decoded['status'] ?? 'inactive');
		$plan   = WABE_Plan::normalize_plan($decoded['plan'] ?? 'free');

		$message = sanitize_text_field(
			$decoded['message'] ?? $decoded['detail'] ?? $decoded['error'] ?? ''
		);

		$expires_at = sanitize_text_field(
			$decoded['expires_at'] ?? $decoded['expires'] ?? ''
		);

		$customer_email = sanitize_email(
			$decoded['customer_email'] ?? $decoded['email'] ?? ''
		);

		if (!empty($decoded['features']) && is_array($decoded['features'])) {
			$features = WABE_Plan::normalize_legacy_features(
				$decoded['features'],
				self::plan_based_default_features($plan)
			);
		} else {
			$features = self::plan_based_default_features($plan);
		}

		$data = [
			'status'         => $status,
			'plan'           => $plan,
			'license_key'    => sanitize_text_field($license_key),
			'features'       => $features,
			'expires_at'     => $expires_at,
			'customer_email' => $customer_email,
			'message'        => $message,
			'checked_at'     => current_time('mysql'),
		];

		return self::normalize_license_data($data);
	}

	/**
	 * ローカルfallback構築
	 */
	private static function build_local_fallback_data(array $partial = [])
	{
		$plan = WABE_Plan::normalize_plan($partial['plan'] ?? 'free');

		$data = [
			'status'         => sanitize_key($partial['status'] ?? 'free'),
			'plan'           => $plan,
			'license_key'    => sanitize_text_field($partial['license_key'] ?? ''),
			'features'       => self::plan_based_default_features($plan),
			'expires_at'     => sanitize_text_field($partial['expires_at'] ?? ''),
			'customer_email' => sanitize_email($partial['customer_email'] ?? ''),
			'message'        => sanitize_text_field($partial['message'] ?? ''),
			'checked_at'     => current_time('mysql'),
		];

		return self::normalize_license_data($data);
	}

	/**
	 * 正規化
	 */
	private static function normalize_license_data($data)
	{
		if (!is_array($data)) {
			$data = [];
		}

		$plan = WABE_Plan::normalize_plan($data['plan'] ?? 'free');
		$features_fallback = self::plan_based_default_features($plan);

		$normalized = [
			'status'         => sanitize_key($data['status'] ?? 'free'),
			'plan'           => $plan,
			'license_key'    => sanitize_text_field($data['license_key'] ?? ''),
			'features'       => WABE_Plan::normalize_legacy_features($data['features'] ?? [], $features_fallback),
			'expires_at'     => sanitize_text_field($data['expires_at'] ?? ''),
			'customer_email' => sanitize_email($data['customer_email'] ?? ''),
			'message'        => sanitize_text_field($data['message'] ?? ''),
			'checked_at'     => sanitize_text_field($data['checked_at'] ?? current_time('mysql')),
		];

		return $normalized;
	}

	/**
	 * キャッシュとoption反映
	 */
	private static function store_license_result(array $data, array $options)
	{
		$data = self::normalize_license_data($data);

		set_transient(self::CACHE_KEY, $data, self::CACHE_TTL);
		update_option(self::OPTION_LAST_SYNC, $data['checked_at']);
		update_option(self::OPTION_LAST_ERROR, $data['message']);
		update_option(self::OPTION_LAST_STATUS, $data['status']);

		$options['plan']                   = $data['plan'];
		$options['license_status']         = $data['status'];
		$options['license_checked_at']     = $data['checked_at'];
		$options['license_expires_at']     = $data['expires_at'];
		$options['license_customer_email'] = $data['customer_email'];

		update_option(WABE_OPTION, $options);

		self::log('License sync final: status=' . $data['status'] . ' plan=' . $data['plan'] . ' email=' . ($data['customer_email'] ?: ''));
	}

	/**
	 * プラン既定feature
	 */
	private static function plan_based_default_features($plan)
	{
		$plan = WABE_Plan::normalize_plan($plan);
		$matrix = WABE_Plan::plan_matrix();
		return $matrix[$plan] ?? WABE_Plan::default_features();
	}

	private static function log($message)
	{
		if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'info')) {
			WABE_Logger::info($message);
		}
	}

	private static function log_error($message)
	{
		if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'error')) {
			WABE_Logger::error($message);
			return;
		}

		self::log($message);
	}
}
