<?php
/**
 * Master Plugin Coordinator.
 *
 * @package WPCalibrate\RobotsRepair
 */

declare(strict_types=1);

namespace WPCalibrate\RobotsRepair;

use WPCalibrate\RobotsRepair\Admin\Assets;
use WPCalibrate\RobotsRepair\Admin\Menu;
use WPCalibrate\RobotsRepair\Admin\PageController;
use WPCalibrate\RobotsRepair\Diagnostics\CronMonitor;
use WPCalibrate\RobotsRepair\Repair\VirtualRobotsManager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 *
 * Singleton entrypoint for coordinating services and registering WordPress hooks.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 */
	private static ?Plugin $instance = null;

	/**
	 * Page Controller service.
	 */
	private PageController $page_controller;

	/**
	 * Menu registration service.
	 */
	private Menu $menu;

	/**
	 * Admin Assets service.
	 */
	private Assets $assets;

	/**
	 * Virtual robots.txt manager.
	 */
	private VirtualRobotsManager $virtual_manager;

	/**
	 * Cron health monitor.
	 */
	private CronMonitor $cron_monitor;

	/**
	 * Get singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->page_controller = new PageController();
		$this->menu            = new Menu( $this->page_controller );
		$this->assets          = new Assets();
		$this->virtual_manager = new VirtualRobotsManager();
		$this->cron_monitor    = new CronMonitor();
	}

	/**
	 * Initialize all plugin subsystems and hooks.
	 */
	public function init(): void {
		// Load text domain for translations.
		add_action( 'init', [ $this, 'load_textdomain' ] );

		// Check for database upgrades in admin.
		add_action( 'admin_init', [ Upgrader::class, 'check_and_upgrade' ] );

		// Initialize admin components.
		if ( is_admin() ) {
			$this->menu->init();
			$this->assets->init();
			$this->page_controller->init();
		}

		// Initialize virtual robots filter (runs both on frontend robots.txt and admin).
		$this->virtual_manager->init();

		// Initialize background WP-Cron monitor.
		$this->cron_monitor->init();
	}

	/**
	 * Load plugin localization text domain.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wpcalibrate-robots-repair',
			false,
			dirname( WPCRR_PLUGIN_BASENAME ) . '/languages/'
		);
	}
}
