<?php
/**
 * Public robots.txt HTTP fetcher with strict SSRF protections.
 *
 * @package WPCalibrate\RobotsRepair
 */

declare(strict_types=1);

namespace WPCalibrate\RobotsRepair\Diagnostics;

use WPCalibrate\RobotsRepair\Support\Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PublicRobotsFetcher
 *
 * Requests the public /robots.txt using the WordPress HTTP API.
 * Target is derived exclusively from the canonical site URL to prevent SSRF.
 */
final class PublicRobotsFetcher {

	/**
	 * Default timeout in seconds for public HTTP scan.
	 */
	public const TIMEOUT_SECONDS = 10;

	/**
	 * Maximum response size to read (1 MB).
	 */
	public const MAX_RESPONSE_BYTES = 1048576;

	/**
	 * Fetch the public /robots.txt URL and inspect response.
	 *
	 * @return array<string, mixed> Structured response report.
	 */
	public static function fetch(): array {
		$target_url = self::get_canonical_robots_url();

		// SSRF Guard: Validate scheme and host strictly against home_url.
		if ( ! self::is_safe_self_target( $target_url ) ) {
			Logger::log( 'error', 'SSRF Guard blocked unsafe robots fetch URL', [ 'url' => $target_url ] );
			return [
				'success'       => false,
				'url'           => $target_url,
				'http_status'   => 0,
				'error'         => __( 'Invalid or unsafe robots.txt URL.', 'wpcalibrate-robots-repair' ),
				'content'       => '',
				'content_type'  => '',
				'response_size' => 0,
				'duration_ms'   => 0,
				'is_html'       => false,
				'headers'       => [],
			];
		}

		$start_time = microtime( true );

		$args = [
			'timeout'     => self::TIMEOUT_SECONDS,
			'redirection' => 3,
			'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // Allow testing on local dev SSL setups if needed.
			'user-agent'  => 'WPCalibrate-Robots-Repair/' . WPCRR_VERSION . '; ' . home_url(),
			'headers'     => [
				'Accept'     => 'text/plain, text/*, */*',
				'Cache-Control' => 'no-cache, no-store, must-revalidate',
				'Pragma'     => 'no-cache',
			],
		];

		// Use wp_safe_remote_get to enforce core IP and scheme safety checks.
		$response = wp_safe_remote_get( $target_url, $args );
		$duration = (int) round( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$error_code    = $response->get_error_code();

			Logger::log(
				'warning',
				sprintf( 'Public robots.txt fetch error: %s (%s)', $error_message, $error_code ),
				[ 'url' => $target_url ]
			);

			return [
				'success'       => false,
				'url'           => $target_url,
				'http_status'   => 0,
				'error'         => $error_message,
				'error_code'    => $error_code,
				'content'       => '',
				'content_type'  => '',
				'response_size' => 0,
				'duration_ms'   => $duration,
				'is_html'       => false,
				'headers'       => [],
			];
		}

		$status_code  = wp_remote_retrieve_response_code( $response );
		$content_type = wp_remote_retrieve_header( $response, 'content-type' ) ?: '';
		$body         = wp_remote_retrieve_body( $response );
		$headers      = wp_remote_retrieve_headers( $response )->getAll();

		// Truncate if response body exceeds max limit.
		if ( strlen( $body ) > self::MAX_RESPONSE_BYTES ) {
			$body = substr( $body, 0, self::MAX_RESPONSE_BYTES );
		}

		// Check if response is unexpectedly HTML (e.g. 404 template, login wall, or WAF block).
		$is_html = self::detect_html( $body, $content_type );

		return [
			'success'       => ( 200 === $status_code && ! $is_html ),
			'url'           => $target_url,
			'http_status'   => $status_code,
			'error'         => '',
			'content'       => $body,
			'content_type'  => (string) $content_type,
			'response_size' => strlen( $body ),
			'duration_ms'   => $duration,
			'is_html'       => $is_html,
			'headers'       => $headers,
		];
	}

	/**
	 * Derive the canonical public robots.txt URL from home_url.
	 */
	public static function get_canonical_robots_url(): string {
		$home = home_url();
		return trailingslashit( $home ) . 'robots.txt';
	}

	/**
	 * Verify that target URL strictly matches site scheme, host, and path.
	 *
	 * @param string $url URL to verify.
	 * @return bool
	 */
	public static function is_safe_self_target( string $url ): bool {
		$parsed_target = wp_parse_url( $url );
		$parsed_home   = wp_parse_url( home_url() );

		if ( empty( $parsed_target['host'] ) || empty( $parsed_home['host'] ) ) {
			return false;
		}

		// Scheme must be http or https.
		if ( ! in_array( strtolower( $parsed_target['scheme'] ?? '' ), [ 'http', 'https' ], true ) ) {
			return false;
		}

		// Host must strictly match home_url host.
		if ( strtolower( $parsed_target['host'] ) !== strtolower( $parsed_home['host'] ) ) {
			return false;
		}

		// Path must end with /robots.txt
		$path = $parsed_target['path'] ?? '';
		if ( ! str_ends_with( strtolower( $path ), 'robots.txt' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Detect if a response body appears to be HTML rather than plain text robots directives.
	 *
	 * @param string $body Response body.
	 * @param string $content_type Content-Type header.
	 * @return bool
	 */
	private static function detect_html( string $body, string $content_type ): bool {
		if ( str_contains( strtolower( $content_type ), 'text/html' ) ) {
			return true;
		}

		$trimmed = ltrim( $body );
		if (
			str_starts_with( strtolower( $trimmed ), '<!doctype html' ) ||
			str_starts_with( strtolower( $trimmed ), '<html' ) ||
			str_starts_with( strtolower( $trimmed ), '<head' ) ||
			str_starts_with( strtolower( $trimmed ), '<div' )
		) {
			return true;
		}

		return false;
	}
}
