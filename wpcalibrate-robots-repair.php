<?php
/**
 * Plugin Name:       WPCalibrate Robots Repair
 * Plugin URI:        https://wpcalibrate.com
 * Description:       Diagnoses, explains, safely repairs, manages, and verifies WordPress robots.txt issues. Scan, diagnose, preview changes, create backups, and verify public crawl health.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            WPCalibrate
 * Author URI:        https://wpcalibrate.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpcalibrate-robots-repair
 * Domain Path:       /languages
 * Update URI:        https://github.com/zeeshanraza-official/wpcalibrate-robots-repair
 * GitHub Plugin URI: zeeshanraza-official/wpcalibrate-robots-repair
 * Primary Branch:    main
 * Release Asset:     true
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
 * Plugin Version and Metadata Constants.
 */
if ( ! defined( 'WPCRR_VERSION' ) ) {
	define( 'WPCRR_VERSION', '1.0.1' );
}
if ( ! defined( 'WPCRR_MIN_PHP_VERSION' ) ) {
	define( 'WPCRR_MIN_PHP_VERSION', '8.2' );
}
if ( ! defined( 'WPCRR_MIN_WP_VERSION' ) ) {
	define( 'WPCRR_MIN_WP_VERSION', '6.0' );
}
if ( ! defined( 'WPCRR_PLUGIN_FILE' ) ) {
	define( 'WPCRR_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'WPCRR_PLUGIN_DIR' ) ) {
	define( 'WPCRR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WPCRR_PLUGIN_URL' ) ) {
	define( 'WPCRR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'WPCRR_PLUGIN_BASENAME' ) ) {
	define( 'WPCRR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

/**
 * PSR-4-compatible Autoloader for WPCalibrate\RobotsRepair namespace.
 */
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'WPCalibrate\\RobotsRepair\\';
		$len    = strlen( $prefix );

		if ( 0 !== strncmp( $prefix, $class, $len ) ) {
			return;
		}

		$relative_class = substr( $class, $len );
		$parts          = explode( '\\', $relative_class );
		$class_name     = array_pop( $parts );

		// Convert class name to WordPress standard filename: class-kebab-case.php or interface-kebab-case.php.
		$kebab_name = strtolower( (string) preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $class_name ) );
		$kebab_name = str_replace( '_', '-', $kebab_name );

		$file_candidates = [];
		if ( str_ends_with( $class_name, 'Interface' ) ) {
			$base = substr( $class_name, 0, -9 );
			$kebab_base = strtolower( (string) preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $base ) );
			$kebab_base = str_replace( '_', '-', $kebab_base );
			$file_candidates[] = 'interface-' . $kebab_base . '.php';
			$file_candidates[] = 'interface-' . $kebab_name . '.php';
		} else {
			$file_candidates[] = 'class-' . $kebab_name . '.php';
			if ( str_contains( $kebab_name, 'woo-commerce' ) ) {
				$file_candidates[] = 'class-' . str_replace( 'woo-commerce', 'woocommerce', $kebab_name ) . '.php';
			}
		}

		$subpath = '';
		if ( ! empty( $parts ) ) {
			$subpath = strtolower( implode( '/', $parts ) ) . '/';
		}

		foreach ( $file_candidates as $file_name ) {
			$filepath = WPCRR_PLUGIN_DIR . 'includes/' . $subpath . $file_name;
			if ( file_exists( $filepath ) ) {
				require_once $filepath;
				return;
			}
		}

		// Fallback for direct filename match.
		$direct_path = WPCRR_PLUGIN_DIR . 'includes/' . $subpath . $class_name . '.php';
		if ( file_exists( $direct_path ) ) {
			require_once $direct_path;
		}
	}
);

/**
 * Compatibility check before running plugin.
 */
function wpcrr_is_environment_compatible(): bool {
	if ( version_compare( PHP_VERSION, WPCRR_MIN_PHP_VERSION, '<' ) ) {
		return false;
	}

	global $wp_version;
	if ( version_compare( $wp_version, WPCRR_MIN_WP_VERSION, '<' ) ) {
		return false;
	}

	return true;
}

/**
 * Admin notice if environment requirements are not met.
 */
function wpcrr_admin_requirements_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$message = sprintf(
		/* translators: 1: Current PHP version, 2: Required PHP version, 3: Current WP version, 4: Required WP version */
		esc_html__( 'WPCalibrate Robots Repair requires at least PHP %2$s (detected %1$s) and WordPress %4$s (detected %3$s). The plugin has been disabled to prevent errors.', 'wpcalibrate-robots-repair' ),
		PHP_VERSION,
		WPCRR_MIN_PHP_VERSION,
		$GLOBALS['wp_version'] ?? 'unknown',
		WPCRR_MIN_WP_VERSION
	);

	printf( '<div class="notice notice-error"><p>%s</p></div>', $message );
}

// Check environment compatibility.
if ( ! wpcrr_is_environment_compatible() ) {
	add_action( 'admin_notices', __NAMESPACE__ . '\\wpcrr_admin_requirements_notice' );
	return;
}

/**
 * Plugin activation hook.
 */
register_activation_hook(
	__FILE__,
	static function ( bool $network_wide = false ): void {
		Activator::activate( $network_wide );
	}
);

/**
 * Plugin deactivation hook.
 */
register_deactivation_hook(
	__FILE__,
	static function ( bool $network_wide = false ): void {
		Deactivator::deactivate( $network_wide );
	}
);

/**
 * Initialize the plugin.
 */
function wpcrr_run_plugin(): void {
	$plugin = Plugin::get_instance();
	$plugin->init();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\wpcrr_run_plugin' );
