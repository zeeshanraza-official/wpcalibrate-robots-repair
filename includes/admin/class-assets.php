<?php
/**
 * Admin Assets Enqueue Manager.
 *
 * @package WPCalibrate\RobotsRepair
 */

declare(strict_types=1);

namespace WPCalibrate\RobotsRepair\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets
 *
 * Enqueues CSS and JavaScript strictly on the Robots Repair admin screen.
 */
final class Assets {

	/**
	 * Hook suffix for our admin screen.
	 */
	private string $hook_suffix = '';

	/**
	 * Set screen hook suffix.
	 */
	public function set_hook_suffix( string $suffix ): void {
		$this->hook_suffix = $suffix;
	}

	/**
	 * Register hook.
	 */
	public function init(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue assets only on plugin page.
	 *
	 * @param string $hook Screen hook.
	 */
	public function enqueue_assets( string $hook ): void {
		// Strict check: only load on this plugin's screen.
		$is_our_page = ( $hook === $this->hook_suffix );
		if ( ! $is_our_page ) {
			// Secondary safety check for differing screen prefix formats.
			$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'wpcalibrate-robots-repair' !== $current_page ) {
				return;
			}
		}

		// Enqueue admin styles.
		wp_enqueue_style(
			'wpcalibrate-robots-repair-admin',
			WPCRR_PLUGIN_URL . 'assets/css/admin.css',
			[],
			WPCRR_VERSION
		);

		// Enqueue admin script.
		wp_enqueue_script(
			'wpcalibrate-robots-repair-admin',
			WPCRR_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			WPCRR_VERSION,
			true
		);

		// Localize script with translations, endpoints, and nonces.
		wp_localize_script(
			'wpcalibrate-robots-repair-admin',
			'wpcrrData',
			[
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'restUrl'   => esc_url_raw( rest_url( 'wpcalibrate-robots-repair/v1/' ) ),
				'nonce'     => wp_create_nonce( 'wpcalibrate_robots_repair_action' ),
				'isRtl'     => is_rtl(),
				'strings'   => [
					'confirmRepair'   => __( 'Are you sure you want to apply this repair? A backup of your current configuration will be created before any changes are made.', 'wpcalibrate-robots-repair' ),
					'confirmRestore'  => __( 'Are you sure you want to restore this backup? Your current configuration will be replaced.', 'wpcalibrate-robots-repair' ),
					'confirmDelete'   => __( 'Are you sure you want to delete this backup record?', 'wpcalibrate-robots-repair' ),
					'scanning'        => __( 'Scanning public robots.txt response...', 'wpcalibrate-robots-repair' ),
					'scanComplete'    => __( 'Scan complete.', 'wpcalibrate-robots-repair' ),
					'repairing'       => __( 'Applying repair and verifying public response...', 'wpcalibrate-robots-repair' ),
					'restoring'       => __( 'Restoring configuration...', 'wpcalibrate-robots-repair' ),
					'errorOccurred'   => __( 'An unexpected error occurred. Please check logs.', 'wpcalibrate-robots-repair' ),
					'copySuccess'     => __( 'Robots.txt content copied to clipboard.', 'wpcalibrate-robots-repair' ),
					'dangerDisallow'  => __( 'WARNING: Your content contains "Disallow: /" under "User-agent: *". This will block all search engines from crawling your entire website. Are you sure you wish to proceed?', 'wpcalibrate-robots-repair' ),
				],
			]
		);
	}
}
