<?php
/**
 * Unconfigured License Provider.
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
 * Class UnconfiguredLicenseProvider
 *
 * Implements clean license handling when no remote provider endpoint is connected.
 * Never fakes activation, never contacts fake URLs.
 */
final class UnconfiguredLicenseProvider implements LicenseProviderInterface {

	public const OPTION_KEY = 'wpcalibrate_robots_repair_license';

	public function get_status(): string {
		return 'unconfigured';
	}

	public function activate( string $key ): array {
		$sanitized_key = sanitize_text_field( trim( $key ) );

		// Save the key safely locally, but state clearly that provider is unconfigured.
		$stored = [
			'key'          => $sanitized_key,
			'status'       => 'unconfigured',
			'activated_at' => null,
			'expires_at'   => null,
		];
		update_option( self::OPTION_KEY, $stored, false );

		return [
			'success' => false,
			'status'  => 'unconfigured',
			'message' => __( 'License service not configured. Remote activation will be enabled once the WPCalibrate licensing provider is connected.', 'wpcalibrate-robots-repair' ),
		];
	}

	public function deactivate(): array {
		delete_option( self::OPTION_KEY );
		return [
			'success' => true,
			'status'  => 'unconfigured',
			'message' => __( 'License information cleared.', 'wpcalibrate-robots-repair' ),
		];
	}

	public function get_masked_key(): string {
		$stored = get_option( self::OPTION_KEY, [] );
		$key    = is_array( $stored ) ? (string) ( $stored['key'] ?? '' ) : '';

		if ( '' === $key ) {
			return '';
		}

		$len = strlen( $key );
		if ( $len <= 8 ) {
			return str_repeat( '*', $len );
		}

		return substr( $key, 0, 4 ) . str_repeat( '•', max( 4, $len - 8 ) ) . substr( $key, -4 );
	}

	public function get_details(): array {
		return [
			'status'       => 'unconfigured',
			'status_label' => __( 'Service Not Configured', 'wpcalibrate-robots-repair' ),
			'masked_key'   => $this->get_masked_key(),
			'expires_at'   => null,
			'renewal_url'  => 'https://marketplace.wpcalibrate.com/',
			'message'      => __( 'Licensing endpoint has not yet been connected. All core diagnostic and repair features are fully operational.', 'wpcalibrate-robots-repair' ),
		];
	}
}
