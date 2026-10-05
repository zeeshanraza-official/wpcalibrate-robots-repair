<?php
/**
 * Admin Menu Registration with Defensive Parent Menu Coexistence.
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
 * Class Menu
 *
 * Registers the WPCalibrate parent menu defensively and adds the isolated
 * Robots Repair submenu.
 */
final class Menu {

	public const PARENT_SLUG  = 'wpcalibrate';
	public const SUBMENU_SLUG = 'wpcalibrate-robots-repair';
	public const CAPABILITY   = 'manage_options';

	/**
	 * Page controller instance.
	 */
	private PageController $page_controller;

	/**
	 * Constructor.
	 *
	 * @param PageController $page_controller
	 */
	public function __construct( PageController $page_controller ) {
		$this->page_controller = $page_controller;
	}

	/**
	 * Register hooks.
	 */
	public function init(): void {
		// Priority 9 to allow parent menu establishment or reuse across plugins.
		add_action( 'admin_menu', [ $this, 'register_menu' ], 9 );
		add_action( 'admin_head', [ $this, 'render_menu_icon_styles' ] );
	}

	/**
	 * Defensive admin menu registration.
	 */
	public function register_menu(): void {
		$capability = apply_filters( 'wpcalibrate_robots_repair_capability', self::CAPABILITY );

		if ( ! current_user_can( $capability ) ) {
			return;
		}

		global $admin_page_hooks;

		// 1. Check if WPCalibrate parent menu has already been registered by another WPCalibrate plugin.
		if ( ! isset( $admin_page_hooks[ self::PARENT_SLUG ] ) ) {
			// Use official WPCalibrate white brand icon for dark WordPress admin menu background.
			$menu_icon = WPCRR_PLUGIN_URL . 'assets/branding/icon-white.png';

			add_menu_page(
				'WPCalibrate',
				'WPCalibrate',
				$capability,
				self::PARENT_SLUG,
				[ $this, 'render_parent_portal' ],
				$menu_icon,
				59
			);
		}

		// 2. Register Robots Repair submenu page.
		$hook_suffix = add_submenu_page(
			self::PARENT_SLUG,
			__( 'WPCalibrate Robots Repair', 'wpcalibrate-robots-repair' ),
			__( 'Robots Repair', 'wpcalibrate-robots-repair' ),
			$capability,
			self::SUBMENU_SLUG,
			[ $this->page_controller, 'render' ]
		);

		// Pass hook suffix to assets controller.
		if ( $hook_suffix ) {
			$this->page_controller->set_hook_suffix( $hook_suffix );
		}
	}

	/**
	 * Fallback portal view if parent WPCalibrate menu is clicked directly.
	 */
	public function render_parent_portal(): void {
		$target = admin_url( 'admin.php?page=' . self::SUBMENU_SLUG );
		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Output CSS in admin head to properly size and align the custom WPCalibrate admin menu icon.
	 */
	public function render_menu_icon_styles(): void {
		?>
		<style type="text/css">
			#adminmenu .toplevel_page_wpcalibrate div.wp-menu-image img,
			#adminmenu .menu-top.toplevel_page_wpcalibrate .wp-menu-image img {
				max-width: 20px !important;
				max-height: 20px !important;
				padding-top: 7px !important;
				opacity: 0.85;
				transition: opacity 0.15s ease-in-out;
			}
			#adminmenu .toplevel_page_wpcalibrate:hover div.wp-menu-image img,
			#adminmenu .toplevel_page_wpcalibrate.wp-has-current-submenu div.wp-menu-image img,
			#adminmenu .toplevel_page_wpcalibrate.current div.wp-menu-image img {
				opacity: 1 !important;
			}
		</style>
		<?php
	}
}

