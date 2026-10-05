<?php
/**
 * Diagnostic logger service.
 *
 * @package WPCalibrate\RobotsRepair
 */

declare(strict_types=1);

namespace WPCalibrate\RobotsRepair\Support;

use WPCalibrate\RobotsRepair\Settings\SettingsRepository;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Logger
 *
 * Lightweight, bounded diagnostic logger.
 * Never stores license keys, passwords, credentials, or sensitive auth headers.
 */
final class Logger {

	/**
	 * Maximum number of logs retained.
	 */
	public const MAX_ENTRIES = 50;

	/**
	 * Option key for logs.
	 */
	public const OPTION_KEY = 'wpcalibrate_robots_repair_logs';

	/**
	 * Record a diagnostic log entry.
	 *
	 * @param string $level Log level: 'info', 'warning', 'error', 'debug'.
	 * @param string $message Plain text message.
	 * @param array<string, mixed> $context Additional structured context.
	 */
	public static function log( string $level, string $message, array $context = [] ): void {
		// Check if logging is enabled unless it's an error.
		if ( 'error' !== $level && ! SettingsRepository::is_logging_enabled() ) {
			return;
		}

		$clean_message = self::sanitize_message( $message );
		$clean_context = self::sanitize_context( $context );

		$entry = [
			'id'        => 'log_' . wp_generate_password( 8, false ),
			'timestamp' => time(),
			'level'     => sanitize_key( $level ),
			'message'   => $clean_message,
			'context'   => $clean_context,
		];

		$logs = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $logs ) ) {
			$logs = [];
		}

		array_unshift( $logs, $entry );

		// Bound to MAX_ENTRIES.
		if ( count( $logs ) > self::MAX_ENTRIES ) {
			$logs = array_slice( $logs, 0, self::MAX_ENTRIES );
		}

		update_option( self::OPTION_KEY, $logs, false );
	}

	/**
	 * Retrieve all stored log entries.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_logs(): array {
		$logs = get_option( self::OPTION_KEY, [] );
		return is_array( $logs ) ? $logs : [];
	}

	/**
	 * Clear all stored logs.
	 */
	public static function clear_logs(): void {
		update_option( self::OPTION_KEY, [], false );
	}

	/**
	 * Sanitize log message to remove sensitive details.
	 *
	 * @param string $message Raw message.
	 * @return string Sanitized message.
	 */
	private static function sanitize_message( string $message ): string {
		// Mask server root paths if ABSPATH is defined.
		if ( defined( 'ABSPATH' ) && '' !== ABSPATH ) {
			$message = str_replace( ABSPATH, '[WP_ROOT]/', $message );
		}

		// Mask authorization/bearer tokens.
		$message = (string) preg_replace( '/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i', 'Bearer [REDACTED]', $message );

		// Mask license keys.
		$message = (string) preg_replace( '/[a-f0-9]{32,64}/i', '[KEY_REDACTED]', $message );

		return wp_strip_all_tags( $message );
	}

	/**
	 * Sanitize context array to strip potential secrets.
	 *
	 * @param array<string, mixed> $context Raw context.
	 * @return array<string, mixed> Sanitized context.
	 */
	private static function sanitize_context( array $context ): array {
		$forbidden_keys = [ 'password', 'pass', 'token', 'secret', 'key', 'license', 'authorization', 'cookie' ];
		$sanitized      = [];

		foreach ( $context as $k => $v ) {
			$k_lower = strtolower( (string) $k );
			if ( in_array( $k_lower, $forbidden_keys, true ) ) {
				$sanitized[ $k ] = '[REDACTED]';
				continue;
			}

			if ( is_array( $v ) ) {
				$sanitized[ $k ] = self::sanitize_context( $v );
			} elseif ( is_scalar( $v ) ) {
				$sanitized[ $k ] = self::sanitize_message( (string) $v );
			}
		}

		return $sanitized;
	}
}
