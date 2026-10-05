<?php
/**
 * SEO Plugin Conflict and Integration Detector.
 *
 * @package WPCalibrate\RobotsRepair
 */

declare(strict_types=1);

namespace WPCalibrate\RobotsRepair\Diagnostics;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SeoPluginDetector
 *
 * Identifies active SEO plugins and inspects whether third-party filters
 * are attached to the core WordPress robots_txt filter hook.
 */
final class SeoPluginDetector {

	/**
	 * Detect active SEO plugins and hook conflicts.
	 *
	 * @return array<string, mixed>
	 */
	public static function detect(): array {
		$active_seo_plugins = [];

		// 1. Yoast SEO.
		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ) {
			$active_seo_plugins['yoast'] = [
				'name'    => 'Yoast SEO',
				'version' => defined( 'WPSEO_VERSION' ) ? WPSEO_VERSION : '',
				'slug'    => 'wordpress-seo',
			];
		}

		// 2. Rank Math.
		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
			$active_seo_plugins['rank_math'] = [
				'name'    => 'Rank Math SEO',
				'version' => defined( 'RANK_MATH_VERSION' ) ? RANK_MATH_VERSION : '',
				'slug'    => 'seo-by-rank-math',
			];
		}

		// 3. All in One SEO (AIOSEO).
		if ( defined( 'AIOSEO_VERSION' ) || class_exists( 'AIOSEO\\Plugin\\AIOSEO' ) ) {
			$active_seo_plugins['aioseo'] = [
				'name'    => 'All in One SEO',
				'version' => defined( 'AIOSEO_VERSION' ) ? AIOSEO_VERSION : '',
				'slug'    => 'all-in-one-seo-pack',
			];
		}

		// 4. SEOPress.
		if ( defined( 'SEOPRESS_VERSION' ) || function_exists( 'seopress_init' ) ) {
			$active_seo_plugins['seopress'] = [
				'name'    => 'SEOPress',
				'version' => defined( 'SEOPRESS_VERSION' ) ? SEOPRESS_VERSION : '',
				'slug'    => 'wp-seopress',
			];
		}

		// 5. The SEO Framework.
		if ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) || function_exists( 'the_seo_framework' ) ) {
			$active_seo_plugins['the_seo_framework'] = [
				'name'    => 'The SEO Framework',
				'version' => defined( 'THE_SEO_FRAMEWORK_VERSION' ) ? THE_SEO_FRAMEWORK_VERSION : '',
				'slug'    => 'autodescription',
			];
		}

		// Inspect robots_txt filter hooks.
		global $wp_filter;
		$third_party_filters = [];

		if ( isset( $wp_filter['robots_txt'] ) ) {
			foreach ( $wp_filter['robots_txt']->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $cb_key => $cb_data ) {
					// Exclude core WordPress and our own plugin callback.
					if ( str_contains( (string) $cb_key, 'WPCalibrate\\RobotsRepair' ) ) {
						continue;
					}

					$desc = self::describe_callback( $cb_data['function'] );
					$third_party_filters[] = [
						'priority' => $priority,
						'callback' => $desc,
					];
				}
			}
		}

		$has_active = ! empty( $active_seo_plugins );
		$has_filter = ! empty( $third_party_filters );

		$recommendation = '';
		if ( $has_active && $has_filter ) {
			$names = implode( ', ', array_column( $active_seo_plugins, 'name' ) );
			$recommendation = sprintf(
				/* translators: %s: plugin names */
				__( 'Detected active SEO plugin (%s) filtering robots.txt. Ensure robots.txt is managed in either WPCalibrate or your SEO plugin to avoid duplicate or clashing crawl directives.', 'wpcalibrate-robots-repair' ),
				$names
			);
		} elseif ( $has_active ) {
			$names = implode( ', ', array_column( $active_seo_plugins, 'name' ) );
			$recommendation = sprintf(
				/* translators: %s: plugin names */
				__( '%s is active. WPCalibrate can manage robots.txt cleanly without conflicting.', 'wpcalibrate-robots-repair' ),
				$names
			);
		}

		return [
			'has_seo_plugin'        => $has_active,
			'seo_plugins'           => $active_seo_plugins,
			'has_filter_conflict'   => $has_filter,
			'third_party_filters'   => $third_party_filters,
			'recommendation'        => $recommendation,
		];
	}

	/**
	 * Convert callable into human-readable description.
	 *
	 * @param mixed $callback Callable.
	 * @return string
	 */
	private static function describe_callback( mixed $callback ): string {
		if ( is_string( $callback ) ) {
			return $callback;
		}

		if ( is_array( $callback ) ) {
			$class = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
			$method = (string) ( $callback[1] ?? '' );
			return $class . '::' . $method;
		}

		if ( $callback instanceof \Closure ) {
			return 'Closure';
		}

		return 'Unknown Callback';
	}
}
