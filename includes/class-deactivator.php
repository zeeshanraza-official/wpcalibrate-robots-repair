<?php
/**
 * Fired during plugin deactivation.
 *
 * @package WPCalibrate\RobotsRepair
 */

declare(strict_types=1);

namespace WPCalibrate\RobotsRepair;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deactivator
 *
 * Handles cleanup upon plugin deactivation.
 */
final class Deactivator {

	/**
	 * Run deactivation logic.
	 *
	 * @param bool $network_wide Whether deactivated network-wide in Multisite.
	 */
	public static function deactivate( bool $network_wide = false ): void {
		// Clear scheduled cron events owned by this plugin.
		wp_clear_scheduled_hook( 'wpcalibrate_robots_repair_scheduled_health_check' );

		// Clear transient locks.
		delete_transient( 'wpcalibrate_robots_repair_lock' );
		delete_transient( 'wpcalibrate_robots_repair_cache' );

		// Virtual robots modifications automatically cease when plugin is inactive.
		// Settings and backups are preserved by default.
		// Physical robots.txt is NEVER touched or deleted on deactivation.
	}
}
