<?php
/**
 * Fired during plugin activation.
 *
 * @package WPCalibrate\RobotsRepair
 */

declare(strict_types=1);

namespace WPCalibrate\RobotsRepair;

use WPCalibrate\RobotsRepair\Settings\SettingsRepository;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Activator
 *
 * Handles initialization upon plugin activation.
 */
final class Activator {

	/**
	 * Run activation logic.
	 *
	 * @param bool $network_wide Whether activated network-wide in Multisite.
	 */
	public static function activate( bool $network_wide = false ): void {
		// Environment check.
		if ( version_compare( PHP_VERSION, WPCRR_MIN_PHP_VERSION, '<' ) ) {
			deactivate_plugins( WPCRR_PLUGIN_BASENAME );
			wp_die(
				esc_html(
					sprintf(
						/* translators: 1: Current PHP version, 2: Required PHP version */
						__( 'WPCalibrate Robots Repair requires PHP %2$s or higher. Your server is running PHP %1$s.', 'wpcalibrate-robots-repair' ),
						PHP_VERSION,
						WPCRR_MIN_PHP_VERSION
					)
				)
			);
		}

		// Initialize default settings if not already present.
		SettingsRepository::init_defaults();

		// Set installed version.
		$installed_version = get_option( 'wpcalibrate_robots_repair_version' );
		if ( empty( $installed_version ) ) {
			add_option( 'wpcalibrate_robots_repair_version', WPCRR_VERSION, '', 'yes' );
		} else {
			// Trigger upgrade routine if versions differ.
			Upgrader::check_and_upgrade();
		}

		// Ensure non-autoloaded options exist without overwriting.
		if ( false === get_option( 'wpcalibrate_robots_repair_backups' ) ) {
			add_option( 'wpcalibrate_robots_repair_backups', [], '', 'no' );
		}

		if ( false === get_option( 'wpcalibrate_robots_repair_last_scan' ) ) {
			add_option( 'wpcalibrate_robots_repair_last_scan', [], '', 'no' );
		}

		if ( false === get_option( 'wpcalibrate_robots_repair_logs' ) ) {
			add_option( 'wpcalibrate_robots_repair_logs', [], '', 'no' );
		}

		// Never create or overwrite physical robots.txt.
		// Never alter WordPress search engine visibility.
		// Never contact external servers.
	}
}
