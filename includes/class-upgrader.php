<?php
/**
 * Handles database/data upgrades and migrations.
 *
 * @package WPCalibrate\RobotsRepair
 */

declare(strict_types=1);

namespace WPCalibrate\RobotsRepair;

use WPCalibrate\RobotsRepair\Support\Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Upgrader
 */
final class Upgrader {

	/**
	 * Check installed version and run any pending migrations.
	 */
	public static function check_and_upgrade(): void {
		$installed_version = get_option( 'wpcalibrate_robots_repair_version', '0.0.0' );

		if ( version_compare( (string) $installed_version, WPCRR_VERSION, '<' ) ) {
			self::migrate( (string) $installed_version, WPCRR_VERSION );
		}
	}

	/**
	 * Execute version migrations idempotently.
	 *
	 * @param string $from_version Old version.
	 * @param string $to_version New version.
	 */
	private static function migrate( string $from_version, string $to_version ): void {
		// Log migration start.
		Logger::log(
			'info',
			sprintf( 'Starting plugin upgrade from version %s to %s', $from_version, $to_version )
		);

		// If from_version is 0.0.0, this is initial installation.
		if ( '0.0.0' === $from_version ) {
			update_option( 'wpcalibrate_robots_repair_version', $to_version, true );
			return;
		}

		// Example idempotent migration steps for future versions:
		// if ( version_compare( $from_version, '1.1.0', '<' ) ) { ... }

		// Update stored version upon successful completion.
		update_option( 'wpcalibrate_robots_repair_version', $to_version, true );

		Logger::log(
			'info',
			sprintf( 'Plugin upgrade to %s completed successfully', $to_version )
		);
	}
}
