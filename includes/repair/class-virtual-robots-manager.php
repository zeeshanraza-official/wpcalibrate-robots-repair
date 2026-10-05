<?php
/**
 * Virtual robots.txt manager using core WordPress robots_txt filter.
 *
 * @package WPCalibrate\RobotsRepair
 */

declare(strict_types=1);

namespace WPCalibrate\RobotsRepair\Repair;

use WPCalibrate\RobotsRepair\Support\Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VirtualRobotsManager
 *
 * Intercepts core do_robots() via robots_txt filter when plugin management is active.
 */
final class VirtualRobotsManager {

	public const OPTION_KEY = 'wpcalibrate_robots_repair_rules';

	/**
	 * Register hooks.
	 */
	public function init(): void {
		// Hook late (priority 99) to provide deterministic plugin output.
		add_filter( 'robots_txt', [ $this, 'filter_robots_txt' ], 99, 2 );
	}

	/**
	 * Filter callback for WordPress robots_txt.
	 *
	 * @param string   $output Original robots.txt output.
	 * @param bool|int $public Whether site is marked public in Reading settings.
	 * @return string Modified robots.txt output.
	 */
	public function filter_robots_txt( string $output, mixed $public ): string {
		$rules = self::get_rules();

		// If no plugin-managed content is set, pass through unchanged.
		if ( empty( $rules['virtual_content'] ) ) {
			return $output;
		}

		// Return deterministic plugin-managed virtual content.
		return (string) $rules['virtual_content'];
	}

	/**
	 * Retrieve saved plugin virtual rules.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_rules(): array {
		$data = get_option( self::OPTION_KEY, [] );
		return is_array( $data ) ? $data : [];
	}

	/**
	 * Save new virtual robots.txt rules.
	 *
	 * @param string $content Content to serve virtually.
	 * @param string $mode Preset mode: 'recommended', 'woocommerce', 'custom'.
	 * @return bool
	 */
	public static function save_rules( string $content, string $mode = 'custom' ): bool {
		$payload = [
			'virtual_content' => $content,
			'mode'            => sanitize_key( $mode ),
			'updated_at'      => time(),
			'version'         => WPCRR_VERSION,
		];

		$saved = update_option( self::OPTION_KEY, $payload, true );

		Logger::log(
			'info',
			sprintf( 'Saved plugin virtual robots.txt rules (%s mode)', $mode )
		);

		return $saved;
	}

	/**
	 * Remove plugin virtual rules and revert to WordPress core handling.
	 */
	public static function reset_rules(): bool {
		delete_option( self::OPTION_KEY );
		Logger::log( 'info', 'Reset plugin virtual robots.txt rules to core default' );
		return true;
	}
}
