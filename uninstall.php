<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package WPCalibrate\RobotsRepair
 */

declare(strict_types=1);

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Uninstall routine for WPCalibrate Robots Repair.
 *
 * Preserves user settings and backups by default.
 * Deletes plugin-specific data only if the administrator explicitly enabled
 * "Remove plugin data on uninstall" in plugin settings.
 *
 * NEVER touches physical robots.txt files.
 * NEVER alters WordPress Search Engine Visibility (blog_public).
 * NEVER alters data belonging to other WPCalibrate plugins.
 */
function wpcrr_uninstall_plugin(): void {
	$settings = get_option( 'wpcalibrate_robots_repair_settings', [] );

	// Only clean up options and transients if explicitly configured by administrator.
	$delete_data = ! empty( $settings['delete_data_on_uninstall'] );

	// Always clear our scheduled WP-Cron health check hook.
	wp_clear_scheduled_hook( 'wpcalibrate_robots_repair_scheduled_health_check' );

	// Always clean up transient locks.
	delete_transient( 'wpcalibrate_robots_repair_lock' );
	delete_transient( 'wpcalibrate_robots_repair_cache' );

	if ( $delete_data ) {
		delete_option( 'wpcalibrate_robots_repair_version' );
		delete_option( 'wpcalibrate_robots_repair_settings' );
		delete_option( 'wpcalibrate_robots_repair_rules' );
		delete_option( 'wpcalibrate_robots_repair_backups' );
		delete_option( 'wpcalibrate_robots_repair_last_scan' );
		delete_option( 'wpcalibrate_robots_repair_license' );
		delete_option( 'wpcalibrate_robots_repair_logs' );
	}
}

wpcrr_uninstall_plugin();
