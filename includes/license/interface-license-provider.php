<?php
/**
 * License Provider Interface.
 *
 * @package WPCalibrate\RobotsRepair
 */

declare(strict_types=1);

namespace WPCalibrate\RobotsRepair\License;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface LicenseProviderInterface
 *
 * Contract for licensing providers (unconfigured, EDD, WooCommerce API, etc.).
 */
interface LicenseProviderInterface {

	/**
	 * Get current license status: 'unconfigured', 'inactive', 'active', 'expired'.
	 */
	public function get_status(): string;

	/**
	 * Activate license key.
	 *
	 * @param string $key License key.
	 * @return array<string, mixed> Activation response.
	 */
	public function activate( string $key ): array;

	/**
	 * Deactivate license key.
	 *
	 * @return array<string, mixed> Deactivation response.
	 */
	public function deactivate(): array;

	/**
	 * Get masked license key for display.
	 */
	public function get_masked_key(): string;

	/**
	 * Get full details for UI rendering.
	 *
	 * @return array<string, mixed>
	 */
	public function get_details(): array;
}
