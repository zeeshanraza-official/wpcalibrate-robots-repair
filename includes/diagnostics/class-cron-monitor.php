<?php
/**
 * Optional WP-Cron Scheduled Health Monitor.
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
 * Class CronMonitor
 *
 * Executes passive, non-destructive background health checks via WP-Cron.
 */
final class CronMonitor {

	public const CRON_HOOK = 'wpcalibrate_robots_repair_scheduled_health_check';

	/**
	 * Register hook.
	 */
	public function init(): void {
		add_action( self::CRON_HOOK, [ $this, 'run_scheduled_scan' ] );
		self::sync_schedule();
	}

	/**
	 * Synchronize schedule with user setting.
	 */
	public static function sync_schedule(): void {
		$enabled = (bool) SettingsRepository::get( 'enable_cron_monitor', false );

		if ( $enabled ) {
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
				Logger::log( 'info', 'Scheduled daily robots.txt health check registered' );
			}
		} else {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	/**
	 * Run passive scheduled scan.
	 */
	public function run_scheduled_scan(): void {
		if ( ! SettingsRepository::get( 'enable_cron_monitor', false ) ) {
			return;
		}

		Logger::log( 'info', 'WP-Cron scheduled robots.txt scan executing' );
		Scanner::run( true );
	}
}
