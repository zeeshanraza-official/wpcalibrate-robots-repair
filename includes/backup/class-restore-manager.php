<?php
/**
 * Restore Manager for recovering robots.txt backups.
 *
 * @package WPCalibrate\RobotsRepair
 */

declare(strict_types=1);

namespace WPCalibrate\RobotsRepair\Backup;

use WPCalibrate\RobotsRepair\Diagnostics\PhysicalFileDetector;
use WPCalibrate\RobotsRepair\Diagnostics\PublicRobotsFetcher;
use WPCalibrate\RobotsRepair\Repair\PhysicalRobotsManager;
use WPCalibrate\RobotsRepair\Repair\VirtualRobotsManager;
use WPCalibrate\RobotsRepair\Support\Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RestoreManager
 *
 * Handles atomic recovery of prior backups with post-restore verification.
 */
final class RestoreManager {

	/**
	 * Restore configuration from a specific backup ID.
	 *
	 * @param string $backup_id Backup ID to restore.
	 * @param bool   $explicit_confirmed Confirmation flag.
	 * @return array<string, mixed>
	 */
	public static function restore( string $backup_id, bool $explicit_confirmed = false ): array {
		if ( ! $explicit_confirmed ) {
			return [
				'success' => false,
				'error'   => __( 'Restoring a backup requires explicit administrator confirmation.', 'wpcalibrate-robots-repair' ),
			];
		}

		$backup = BackupRepository::get( $backup_id );
		if ( null === $backup ) {
			return [
				'success' => false,
				'error'   => __( 'Selected backup record could not be found.', 'wpcalibrate-robots-repair' ),
			];
		}

		// Integrity check: verify hash.
		$expected_hash = hash( 'sha256', $backup['content'] );
		if ( $expected_hash !== $backup['content_hash'] ) {
			return [
				'success' => false,
				'error'   => __( 'Backup integrity check failed: content hash mismatch.', 'wpcalibrate-robots-repair' ),
			];
		}

		$content     = (string) $backup['content'];
		$source_type = (string) $backup['source_type'];

		// Inspect current physical state.
		$physical = PhysicalFileDetector::inspect();

		// If backup was physical OR a physical file currently exists, restore to physical.
		if ( 'physical' === $source_type || ! empty( $physical['exists'] ) ) {
			$res = PhysicalRobotsManager::write( $content, true );
			if ( ! $res['success'] ) {
				return [
					'success' => false,
					'error'   => $res['error'],
				];
			}
		} else {
			// Restore to virtual output.
			VirtualRobotsManager::save_rules( $content, 'custom' );
		}

		Logger::log(
			'info',
			sprintf( 'Restored backup %s (%s)', $backup_id, $source_type )
		);

		// Post-restore verification.
		$post_fetch = PublicRobotsFetcher::fetch();
		$norm_saved = trim( str_replace( [ "\r\n", "\r" ], "\n", $content ) );
		$norm_pub   = trim( str_replace( [ "\r\n", "\r" ], "\n", (string) $post_fetch['content'] ) );

		$verified = ( $norm_saved === $norm_pub );

		return [
			'success'             => true,
			'verified'            => $verified,
			'verification_status' => $verified ? 'Verified' : 'Verification Failed',
			'public_status'       => $post_fetch['http_status'],
		];
	}
}
