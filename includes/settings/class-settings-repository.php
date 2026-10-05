<?php
/**
 * Settings Repository for WPCalibrate Robots Repair.
 *
 * @package WPCalibrate\RobotsRepair
 */

declare(strict_types=1);

namespace WPCalibrate\RobotsRepair\Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SettingsRepository
 */
final class SettingsRepository {

	/**
	 * Option key for settings.
	 */
	public const OPTION_KEY = 'wpcalibrate_robots_repair_settings';

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		return [
			'management_mode'          => 'recommended',
			'auto_sitemap'             => true,
			'sitemap_url_override'     => '',
			'enable_backups'           => true,
			'max_backups'              => 10,
			'enable_logging'           => false,
			'enable_cron_monitor'      => false,
			'delete_data_on_uninstall' => false,
		];
	}

	/**
	 * Initialize defaults if option does not exist.
	 */
	public static function init_defaults(): void {
		$existing = get_option( self::OPTION_KEY, null );
		if ( null === $existing || false === $existing ) {
			add_option( self::OPTION_KEY, self::get_defaults(), '', 'yes' );
		}
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all(): array {
		$saved = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}

		return wp_parse_args( $saved, self::get_defaults() );
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Optional fallback.
	 * @return mixed
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		$all = self::get_all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Update settings after validating and sanitizing.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return bool True on success.
	 */
	public static function update_all( array $input ): bool {
		$sanitized = self::sanitize( $input );
		return update_option( self::OPTION_KEY, $sanitized, true );
	}

	/**
	 * Sanitize and validate settings array.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed> Sanitized settings.
	 */
	public static function sanitize( array $input ): array {
		$defaults = self::get_defaults();
		$output   = [];

		// Management mode: 'recommended', 'woocommerce', 'custom'.
		$allowed_modes = [ 'recommended', 'woocommerce', 'custom' ];
		$mode          = isset( $input['management_mode'] ) ? sanitize_key( (string) $input['management_mode'] ) : $defaults['management_mode'];
		$output['management_mode'] = in_array( $mode, $allowed_modes, true ) ? $mode : $defaults['management_mode'];

		// Auto sitemap boolean.
		$output['auto_sitemap'] = ! empty( $input['auto_sitemap'] );

		// Sitemap URL override (optional).
		if ( ! empty( $input['sitemap_url_override'] ) ) {
			$url = esc_url_raw( trim( (string) $input['sitemap_url_override'] ) );
			// Only allow http or https.
			$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
			if ( in_array( $scheme, [ 'http', 'https' ], true ) ) {
				$output['sitemap_url_override'] = $url;
			} else {
				$output['sitemap_url_override'] = '';
			}
		} else {
			$output['sitemap_url_override'] = '';
		}

		// Backups enabled.
		$output['enable_backups'] = ! empty( $input['enable_backups'] );

		// Max backups: integer between 1 and 50.
		$max_backups = isset( $input['max_backups'] ) ? (int) $input['max_backups'] : 10;
		if ( $max_backups < 1 ) {
			$max_backups = 1;
		} elseif ( $max_backups > 50 ) {
			$max_backups = 50;
		}
		$output['max_backups'] = $max_backups;

		// Diagnostic logging.
		$output['enable_logging'] = ! empty( $input['enable_logging'] );

		// Cron health check.
		$output['enable_cron_monitor'] = ! empty( $input['enable_cron_monitor'] );

		// Delete data on uninstall.
		$output['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );

		return $output;
	}

	/**
	 * Check if logging is enabled.
	 */
	public static function is_logging_enabled(): bool {
		return (bool) self::get( 'enable_logging', false );
	}

	/**
	 * Check if backups are enabled.
	 */
	public static function are_backups_enabled(): bool {
		return (bool) self::get( 'enable_backups', true );
	}

	/**
	 * Get max backups to retain.
	 */
	public static function get_max_backups(): int {
		return (int) self::get( 'max_backups', 10 );
	}

	/**
	 * Get active management mode.
	 */
	public static function get_management_mode(): string {
		return (string) self::get( 'management_mode', 'recommended' );
	}
}
