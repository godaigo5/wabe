<?php
if (!defined('ABSPATH')) exit;

class WABE_Plan
{
	public static function get_license_data()
	{
		$license = WABE_License::sync(false);

		if (!is_array($license)) {
			$license = WABE_License::get_default_payload();
		}

		if (empty($license['features']) || !is_array($license['features'])) {
			$default = WABE_License::get_default_payload();
			$license['features'] = $default['features'];
		}

		return $license;
	}

	public static function get_plan()
	{
		$license = self::get_license_data();
		$plan = sanitize_key($license['plan'] ?? 'free');

		if (!in_array($plan, ['free', 'advanced', 'pro'], true)) {
			$plan = 'free';
		}

		return $plan;
	}

	public static function is_free()
	{
		return self::get_plan() === 'free';
	}

	public static function is_advanced()
	{
		return self::get_plan() === 'advanced';
	}

	public static function is_pro()
	{
		return self::get_plan() === 'pro';
	}

	public static function feature($key, $default = null)
	{
		$license  = self::get_license_data();
		$features = $license['features'] ?? [];

		if (array_key_exists($key, $features)) {
			return $features[$key];
		}

		return $default;
	}

	public static function weekly_posts_max()
	{
		return max(1, intval(self::feature('weekly_posts_max', 1)));
	}

	public static function title_count_max()
	{
		return max(1, intval(self::feature('title_count_max', 1)));
	}

	public static function can_publish()
	{
		return !empty(self::feature('can_publish', false));
	}

	public static function can_use_seo()
	{
		return !empty(self::feature('can_use_seo', false));
	}

	public static function can_use_images()
	{
		return !empty(self::feature('can_use_images', false));
	}

	public static function can_use_topic_generator()
	{
		return !empty(self::feature('can_use_topic_generator', false));
	}

	public static function can_use_internal_links()
	{
		return !empty(self::feature('can_use_internal_links', false));
	}

	public static function can_use_outline_generator()
	{
		return !empty(self::feature('can_use_outline_generator', false));
	}

	public static function can_use_topic_prediction()
	{
		return !empty(self::feature('can_use_topic_prediction', false));
	}

	public static function can_use_duplicate_check()
	{
		return !empty(self::feature('can_use_duplicate_check', false));
	}

	public static function can_use_external_links()
	{
		return !empty(self::feature('can_use_external_links', false));
	}
}
