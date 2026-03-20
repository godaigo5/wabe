<?php
if (!defined('ABSPATH')) exit;

class WABE_Plan
{
	const PLAN_FREE     = 'free';
	const PLAN_ADVANCED = 'advanced';
	const PLAN_PRO      = 'pro';

	/**
	 * デフォルトfeature
	 *
	 * @return array
	 */
	public static function default_features()
	{
		return [
			'weekly_posts_max'         => 1,
			'title_count_max'          => 1,
			'heading_count_max'        => 1,
			'can_publish'              => false,
			'can_use_seo'              => false,
			'can_use_images'           => false,
			'can_use_internal_links'   => false,
			'can_use_external_links'   => false,
			'can_use_topic_prediction' => false,
			'can_use_duplicate_check'  => false,
			'can_use_outline_generator' => false,
		];
	}

	/**
	 * プラン別機能定義
	 *
	 * 仕様書準拠:
	 * Free     : 週1 / 見出し1
	 * Advanced : 週1〜7 / 見出し1〜6
	 * Pro      : Advanced + 重複チェック/内部外部リンク/題材予測
	 *
	 * @return array
	 */
	public static function plan_matrix()
	{
		$base = self::default_features();

		return [
			self::PLAN_FREE => array_merge($base, [
				'weekly_posts_max'          => 1,
				'title_count_max'           => 1,
				'heading_count_max'         => 1,
				'can_publish'               => false,
				'can_use_seo'               => false,
				'can_use_images'            => false,
				'can_use_internal_links'    => false,
				'can_use_external_links'    => false,
				'can_use_topic_prediction'  => false,
				'can_use_duplicate_check'   => false,
				'can_use_outline_generator' => false,
			]),
			self::PLAN_ADVANCED => array_merge($base, [
				'weekly_posts_max'          => 7,
				'title_count_max'           => 1,
				'heading_count_max'         => 6,
				'can_publish'               => true,
				'can_use_seo'               => true,
				'can_use_images'            => true,
				'can_use_internal_links'    => false,
				'can_use_external_links'    => false,
				'can_use_topic_prediction'  => false,
				'can_use_duplicate_check'   => false,
				'can_use_outline_generator' => false,
			]),
			self::PLAN_PRO => array_merge($base, [
				'weekly_posts_max'          => 7,
				'title_count_max'           => 1,
				'heading_count_max'         => 6,
				'can_publish'               => true,
				'can_use_seo'               => true,
				'can_use_images'            => true,
				'can_use_internal_links'    => true,
				'can_use_external_links'    => true,
				'can_use_topic_prediction'  => true,
				'can_use_duplicate_check'   => true,
				'can_use_outline_generator' => true,
			]),
		];
	}

	/**
	 * プラン名の正規化
	 *
	 * @param mixed $plan
	 * @return string
	 */
	public static function normalize_plan($plan)
	{
		$plan = sanitize_key((string)$plan);

		if (!in_array($plan, [self::PLAN_FREE, self::PLAN_ADVANCED, self::PLAN_PRO], true)) {
			return self::PLAN_FREE;
		}

		return $plan;
	}

	/**
	 * 現在プラン取得
	 *
	 * @return string
	 */
	public static function get_plan()
	{
		if (class_exists('WABE_License') && method_exists('WABE_License', 'get_cached_license_data')) {
			$license = WABE_License::get_cached_license_data();
			if (!empty($license)) {
				return self::normalize_plan($license['plan'] ?? self::PLAN_FREE);
			}
		}

		if (class_exists('WABE_License') && method_exists('WABE_License', 'sync')) {
			$license = WABE_License::sync(false);
			return self::normalize_plan($license['plan'] ?? self::PLAN_FREE);
		}

		$o = get_option(WABE_OPTION, []);
		return self::normalize_plan($o['plan'] ?? self::PLAN_FREE);
	}

	/**
	 * 現在feature一式取得
	 *
	 * @return array
	 */
	public static function get_features()
	{
		$plan     = self::get_plan();
		$matrix   = self::plan_matrix();
		$features = $matrix[$plan] ?? self::default_features();

		if (class_exists('WABE_License') && method_exists('WABE_License', 'get_cached_license_data')) {
			$license = WABE_License::get_cached_license_data();

			if (!empty($license['features']) && is_array($license['features'])) {
				$features = self::normalize_legacy_features($license['features'], $features);
			}
		}

		return $features;
	}

	/**
	 * 任意feature取得
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	public static function get_feature($key, $default = null)
	{
		$features = self::get_features();

		if (!array_key_exists($key, $features)) {
			return $default;
		}

		return $features[$key];
	}

	/**
	 * feature群の正規化
	 *
	 * @param mixed $features
	 * @param array|null $fallback
	 * @return array
	 */
	public static function normalize_features($features, $fallback = null)
	{
		$base = is_array($fallback) ? $fallback : self::default_features();

		if (!is_array($features)) {
			return $base;
		}

		$normalized = $base;

		foreach (self::default_features() as $key => $default) {
			if (!array_key_exists($key, $features)) {
				continue;
			}

			if (is_bool($default)) {
				$normalized[$key] = self::to_bool($features[$key]);
			} else {
				$normalized[$key] = max(0, (int)$features[$key]);
			}
		}

		return $normalized;
	}

	/**
	 * 旧featureキーからの移行も吸収
	 *
	 * @param mixed $features
	 * @param array|null $fallback
	 * @return array
	 */
	public static function normalize_legacy_features($features, $fallback = null)
	{
		$base = is_array($features) ? $features : [];

		$map = [
			'can_use_outline'   => 'can_use_outline_generator',
			'can_outline'       => 'can_use_outline_generator',
			'can_use_image'     => 'can_use_images',
			'can_use_internal'  => 'can_use_internal_links',
			'can_use_external'  => 'can_use_external_links',
			'can_use_prediction' => 'can_use_topic_prediction',
			'can_duplicate_check' => 'can_use_duplicate_check',
			'weekly_post_max'   => 'weekly_posts_max',
			'title_max'         => 'title_count_max',
			'heading_max'       => 'heading_count_max',
		];

		foreach ($map as $old => $new) {
			if (array_key_exists($old, $base) && !array_key_exists($new, $base)) {
				$base[$new] = $base[$old];
			}
		}

		return self::normalize_features($base, $fallback);
	}

	public static function weekly_posts_max()
	{
		return max(1, (int)self::get_feature('weekly_posts_max', 1));
	}

	public static function title_count_max()
	{
		return max(1, (int)self::get_feature('title_count_max', 1));
	}

	public static function heading_count_max()
	{
		return max(1, (int)self::get_feature('heading_count_max', self::title_count_max()));
	}

	public static function can_publish()
	{
		return (bool)self::get_feature('can_publish', false);
	}

	public static function can_use_seo()
	{
		return (bool)self::get_feature('can_use_seo', false);
	}

	public static function can_use_images()
	{
		return (bool)self::get_feature('can_use_images', false);
	}

	public static function can_use_internal_links()
	{
		return (bool)self::get_feature('can_use_internal_links', false);
	}

	public static function can_use_external_links()
	{
		return (bool)self::get_feature('can_use_external_links', false);
	}

	public static function can_use_topic_prediction()
	{
		return (bool)self::get_feature('can_use_topic_prediction', false);
	}

	public static function can_use_duplicate_check()
	{
		return (bool)self::get_feature('can_use_duplicate_check', false);
	}

	public static function can_use_outline_generator()
	{
		return (bool)self::get_feature('can_use_outline_generator', false);
	}

	public static function get_plan_label($plan = '')
	{
		$plan = $plan !== '' ? self::normalize_plan($plan) : self::get_plan();

		$labels = [
			self::PLAN_FREE     => 'Free',
			self::PLAN_ADVANCED => 'Advanced',
			self::PLAN_PRO      => 'Pro',
		];

		return $labels[$plan] ?? 'Free';
	}

	/**
	 * 値をboolへ
	 *
	 * @param mixed $value
	 * @return bool
	 */
	private static function to_bool($value)
	{
		if (is_bool($value)) {
			return $value;
		}

		if (is_numeric($value)) {
			return ((int)$value) === 1;
		}

		$value = strtolower(trim((string)$value));

		return in_array($value, ['1', 'true', 'yes', 'on', 'enabled', 'active'], true);
	}
}
