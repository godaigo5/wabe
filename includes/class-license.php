<?php
if (!defined('ABSPATH')) exit;

class WABE_License
{
	public static function sync($force = false)
	{
		$options = get_option(WABE_OPTION, []);

		$license_key = sanitize_text_field($options['license_key'] ?? '');
		$checked_at  = sanitize_text_field($options['license_checked_at'] ?? '');
		$saved_data  = $options['license_data'] ?? [];

		if (!$force && $checked_at !== '' && is_array($saved_data)) {
			$last = strtotime($checked_at);
			if ($last && (time() - $last) < HOUR_IN_SECONDS) {
				return $saved_data;
			}
		}

		$result = self::request_license_data($license_key);

		$options['license_data'] = $result;
		$options['license_checked_at'] = current_time('mysql');
		update_option(WABE_OPTION, $options);

		return $result;
	}

	public static function get_default_payload()
	{
		return [
			'status' => 'free',
			'plan'   => 'free',
			'checked_at' => current_time('mysql'),
			'features' => [
				'weekly_posts_max'         => 1,
				'title_count_max'          => 1,
				'can_publish'              => false,
				'can_use_seo'              => false,
				'can_use_images'           => false,
				'can_use_topic_generator'  => false,
				'can_use_internal_links'   => false,
				'can_use_outline_generator' => false,
				'can_use_topic_prediction' => false,
				'can_use_duplicate_check'  => false,
				'can_use_external_links'   => false,
			],
		];
	}

	private static function request_license_data($license_key)
	{
		if ($license_key === '') {
			return self::get_default_payload();
		}

		if (!defined('WABE_LICENSE_API_URL') || WABE_LICENSE_API_URL === '') {
			return self::get_default_payload();
		}

		$response = wp_remote_post(WABE_LICENSE_API_URL, [
			'timeout' => 20,
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body' => wp_json_encode([
				'license_key' => $license_key,
				'site_url'    => home_url(),
				'plugin'      => 'wp-ai-blog-engine',
			]),
		]);

		if (is_wp_error($response)) {
			WABE_Logger::error('License HTTP error: ' . $response->get_error_message());
			return self::get_default_payload();
		}

		$status_code = (int) wp_remote_retrieve_response_code($response);
		$raw_body    = wp_remote_retrieve_body($response);
		$body        = json_decode($raw_body, true);

		if ($status_code < 200 || $status_code >= 300 || !is_array($body)) {
			WABE_Logger::warning('License API returned invalid response');
			return self::get_default_payload();
		}

		return self::normalize_payload($body);
	}

	private static function normalize_payload($body)
	{
		$default = self::get_default_payload();

		$status = sanitize_text_field($body['status'] ?? $default['status']);
		$plan   = sanitize_key($body['plan'] ?? $default['plan']);

		if (!in_array($plan, ['free', 'advanced', 'pro'], true)) {
			$plan = 'free';
		}

		$features = is_array($body['features'] ?? null) ? $body['features'] : [];
		$default_features = $default['features'];

		$normalized = [
			'status' => $status,
			'plan'   => $plan,
			'checked_at' => current_time('mysql'),
			'features' => [
				'weekly_posts_max'          => max(1, intval($features['weekly_posts_max'] ?? $default_features['weekly_posts_max'])),
				'title_count_max'           => max(1, intval($features['title_count_max'] ?? $default_features['title_count_max'])),
				'can_publish'               => !empty($features['can_publish']),
				'can_use_seo'               => !empty($features['can_use_seo']),
				'can_use_images'            => !empty($features['can_use_images']),
				'can_use_topic_generator'   => !empty($features['can_use_topic_generator']),
				'can_use_internal_links'    => !empty($features['can_use_internal_links']),
				'can_use_outline_generator' => !empty($features['can_use_outline_generator']),
				'can_use_topic_prediction'  => !empty($features['can_use_topic_prediction']),
				'can_use_duplicate_check'   => !empty($features['can_use_duplicate_check']),
				'can_use_external_links'    => !empty($features['can_use_external_links']),
			],
		];

		if ($plan === 'advanced') {
			$normalized['features']['weekly_posts_max'] = max(1, min(7, $normalized['features']['weekly_posts_max']));
			$normalized['features']['title_count_max']  = max(1, min(6, $normalized['features']['title_count_max']));
		}

		if ($plan === 'pro') {
			$normalized['features']['weekly_posts_max'] = max(1, min(7, $normalized['features']['weekly_posts_max']));
			$normalized['features']['title_count_max']  = max(1, min(6, $normalized['features']['title_count_max']));
		}

		return $normalized;
	}
}
