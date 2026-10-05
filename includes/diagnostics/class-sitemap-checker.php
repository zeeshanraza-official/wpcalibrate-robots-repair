<?php
/**
 * Sitemap Discovery and HTTP Verification Service.
 *
 * @package WPCalibrate\RobotsRepair
 */

declare(strict_types=1);

namespace WPCalibrate\RobotsRepair\Diagnostics;

use WPCalibrate\RobotsRepair\Settings\SettingsRepository;
use WPCalibrate\RobotsRepair\Support\Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SitemapChecker
 *
 * Detects, validates, and checks live accessibility of sitemaps without SSRF risk.
 */
final class SitemapChecker {

	/**
	 * Timeout for sitemap validation requests.
	 */
	public const TIMEOUT_SECONDS = 6;

	/**
	 * Inspect sitemaps from declared rules, plugins, core, and settings.
	 *
	 * @param array<int, string> $declared_sitemaps Sitemaps parsed from robots.txt.
	 * @return array<string, mixed> Sitemaps report with verified primary sitemap.
	 */
	public static function check_sitemaps( array $declared_sitemaps = [] ): array {
		$candidates = self::discover_candidates( $declared_sitemaps );
		$results    = [];
		$primary    = null;

		foreach ( $candidates as $candidate ) {
			$url    = $candidate['url'];
			$source = $candidate['source'];

			$check_result = self::verify_sitemap_accessibility( $url );
			$check_result['source'] = $source;

			$results[] = $check_result;

			// First accessible sitemap becomes the primary candidate.
			if ( null === $primary && $check_result['is_accessible'] ) {
				$primary = $url;
			}
		}

		return [
			'candidates'       => $results,
			'primary_sitemap'  => $primary,
			'has_declared'     => ! empty( $declared_sitemaps ),
			'core_sitemaps_on' => function_exists( 'wp_sitemaps_enabled' ) && wp_sitemaps_enabled(),
		];
	}

	/**
	 * Discover potential sitemap URLs in prioritized order.
	 *
	 * @param array<int, string> $declared_sitemaps Sitemaps from robots.txt.
	 * @return array<int, array<string, string>>
	 */
	public static function discover_candidates( array $declared_sitemaps = [] ): array {
		$candidates = [];
		$seen       = [];

		// 1. Administrator-configured sitemap URL override.
		$override = trim( (string) SettingsRepository::get( 'sitemap_url_override', '' ) );
		if ( '' !== $override && ! isset( $seen[ $override ] ) ) {
			$candidates[]     = [ 'url' => $override, 'source' => __( 'Admin Settings Override', 'wpcalibrate-robots-repair' ) ];
			$seen[ $override ] = true;
		}

		// 2. Existing declared sitemaps in robots.txt.
		foreach ( $declared_sitemaps as $url ) {
			$clean = trim( $url );
			if ( '' !== $clean && ! isset( $seen[ $clean ] ) ) {
				$candidates[]   = [ 'url' => $clean, 'source' => __( 'Declared in robots.txt', 'wpcalibrate-robots-repair' ) ];
				$seen[ $clean ] = true;
			}
		}

		// 3. Active SEO plugin sitemaps.
		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
			$seo_url = home_url( '/sitemap_index.xml' );
			if ( ! isset( $seen[ $seo_url ] ) ) {
				$candidates[]     = [ 'url' => $seo_url, 'source' => __( 'SEO Plugin Sitemap', 'wpcalibrate-robots-repair' ) ];
				$seen[ $seo_url ] = true;
			}
		} elseif ( defined( 'AIOSEO_VERSION' ) ) {
			$seo_url = home_url( '/sitemap.xml' );
			if ( ! isset( $seen[ $seo_url ] ) ) {
				$candidates[]     = [ 'url' => $seo_url, 'source' => __( 'AIOSEO Sitemap', 'wpcalibrate-robots-repair' ) ];
				$seen[ $seo_url ] = true;
			}
		}

		// 4. WordPress Core Sitemap (WP 5.5+).
		if ( function_exists( 'wp_sitemaps_enabled' ) && wp_sitemaps_enabled() ) {
			$core_url = home_url( '/wp-sitemap.xml' );
			if ( ! isset( $seen[ $core_url ] ) ) {
				$candidates[]      = [ 'url' => $core_url, 'source' => __( 'WordPress Core Sitemap', 'wpcalibrate-robots-repair' ) ];
				$seen[ $core_url ] = true;
			}
		}

		return $candidates;
	}

	/**
	 * Verify that a sitemap URL is accessible over HTTP/HTTPS.
	 *
	 * @param string $url URL to test.
	 * @return array<string, mixed>
	 */
	public static function verify_sitemap_accessibility( string $url ): array {
		$parsed = wp_parse_url( $url );
		$scheme = strtolower( $parsed['scheme'] ?? '' );
		$host   = strtolower( $parsed['host'] ?? '' );
		$home   = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

		$is_same_origin = ( $host === $home );

		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			return [
				'url'            => $url,
				'is_accessible'  => false,
				'http_status'    => 0,
				'error'          => __( 'Invalid URL scheme. Must be http or https.', 'wpcalibrate-robots-repair' ),
				'content_type'   => '',
				'is_same_origin' => false,
			];
		}

		$args = [
			'timeout'     => self::TIMEOUT_SECONDS,
			'redirection' => 2,
			'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
			'user-agent'  => 'WPCalibrate-Robots-Repair/' . WPCRR_VERSION,
			'headers'     => [
				'Range' => 'bytes=0-1024', // Do not download giant sitemap files.
			],
		];

		$response = wp_safe_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return [
				'url'            => $url,
				'is_accessible'  => false,
				'http_status'    => 0,
				'error'          => $response->get_error_message(),
				'content_type'   => '',
				'is_same_origin' => $is_same_origin,
			];
		}

		$status_code  = wp_remote_retrieve_response_code( $response );
		$content_type = wp_remote_retrieve_header( $response, 'content-type' ) ?: '';
		$is_ok        = ( 200 === $status_code || 206 === $status_code );

		return [
			'url'            => $url,
			'is_accessible'  => $is_ok,
			'http_status'    => $status_code,
			'error'          => $is_ok ? '' : sprintf( 'HTTP %d', $status_code ),
			'content_type'   => (string) $content_type,
			'is_same_origin' => $is_same_origin,
		];
	}
}
