<?php
/**
 * Safe Physical File Manager using WP_Filesystem.
 *
 * @package WPCalibrate\RobotsRepair
 */

declare(strict_types=1);

namespace WPCalibrate\RobotsRepair\Repair;

use WPCalibrate\RobotsRepair\Backup\BackupRepository;
use WPCalibrate\RobotsRepair\Diagnostics\PhysicalFileDetector;
use WPCalibrate\RobotsRepair\Support\Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PhysicalRobotsManager
 *
 * Performs safe write, backup, and delete operations on physical robots.txt.
 * Target path is strictly restricted to ABSPATH . 'robots.txt'.
 */
final class PhysicalRobotsManager {

	/**
	 * Write content to physical robots.txt file with safety guarantees.
	 *
	 * @param string $new_content Content to write.
	 * @param bool   $explicit_confirmed Explicit confirmation flag from admin.
	 * @return array<string, mixed> Result report.
	 */
	public static function write( string $new_content, bool $explicit_confirmed = false ): array {
		if ( ! $explicit_confirmed ) {
			return [
				'success' => false,
				'error'   => __( 'Physical file modification requires explicit administrator confirmation.', 'wpcalibrate-robots-repair' ),
			];
		}

		$path = PhysicalFileDetector::get_canonical_path();

		if ( ! PhysicalFileDetector::is_path_safe( $path ) ) {
			return [
				'success' => false,
				'error'   => __( 'Security violation: target path outside WordPress root.', 'wpcalibrate-robots-repair' ),
			];
		}

		// Never follow symlinks blindly.
		if ( file_exists( $path ) && is_link( $path ) ) {
			return [
				'success' => false,
				'error'   => __( 'robots.txt is a symbolic link. Automated editing of symlinks is disabled for security.', 'wpcalibrate-robots-repair' ),
			];
		}

		// Create backup of existing content before any modification.
		$existing_content = file_exists( $path ) ? (string) @file_get_contents( $path ) : '';
		$backup_id        = '';
		if ( '' !== $existing_content ) {
			$backup_id = BackupRepository::create_backup(
				$existing_content,
				'physical',
				__( 'Pre-write physical robots.txt backup', 'wpcalibrate-robots-repair' )
			);
		}

		// Initialize WP_Filesystem.
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Set credentials for direct filesystem access where possible.
		$creds = request_filesystem_credentials( admin_url( 'admin.php' ), '', false, ABSPATH, null );
		if ( false === $creds || ! WP_Filesystem( $creds ) ) {
			return [
				'success'   => false,
				'error'     => __( 'Filesystem credentials required or WP_Filesystem unavailable. Please check server permissions.', 'wpcalibrate-robots-repair' ),
				'backup_id' => $backup_id,
			];
		}

		// Write content with standard 0644 permissions (never 0777!).
		$written = $wp_filesystem->put_contents( $path, $new_content, FS_CHMOD_FILE );

		if ( ! $written ) {
			Logger::log( 'error', 'Failed writing physical robots.txt file via WP_Filesystem' );
			return [
				'success'   => false,
				'error'     => __( 'Unable to write to physical robots.txt. Check folder permissions.', 'wpcalibrate-robots-repair' ),
				'backup_id' => $backup_id,
			];
		}

		Logger::log(
			'info',
			'Successfully wrote physical robots.txt file',
			[ 'bytes' => strlen( $new_content ), 'backup_id' => $backup_id ]
		);

		return [
			'success'   => true,
			'error'     => '',
			'backup_id' => $backup_id,
		];
	}

	/**
	 * Safely remove physical robots.txt file with explicit confirmation.
	 *
	 * Allows WordPress virtual robots.txt to take effect.
	 *
	 * @param bool $explicit_confirmed Explicit confirmation flag.
	 * @return array<string, mixed>
	 */
	public static function delete( bool $explicit_confirmed = false ): array {
		if ( ! $explicit_confirmed ) {
			return [
				'success' => false,
				'error'   => __( 'Deleting the physical file requires explicit administrator confirmation.', 'wpcalibrate-robots-repair' ),
			];
		}

		$path = PhysicalFileDetector::get_canonical_path();

		if ( ! file_exists( $path ) ) {
			return [
				'success' => true,
				'error'   => '',
			];
		}

		if ( ! PhysicalFileDetector::is_path_safe( $path ) ) {
			return [
				'success' => false,
				'error'   => __( 'Security violation: unsafe file path.', 'wpcalibrate-robots-repair' ),
			];
		}

		// Backup before deletion.
		$existing_content = (string) @file_get_contents( $path );
		$backup_id        = BackupRepository::create_backup(
			$existing_content,
			'physical',
			__( 'Backup before physical robots.txt deletion', 'wpcalibrate-robots-repair' )
		);

		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$creds = request_filesystem_credentials( admin_url( 'admin.php' ), '', false, ABSPATH, null );
		if ( false === $creds || ! WP_Filesystem( $creds ) ) {
			return [
				'success'   => false,
				'error'     => __( 'Filesystem credentials required to remove physical file.', 'wpcalibrate-robots-repair' ),
				'backup_id' => $backup_id,
			];
		}

		$deleted = $wp_filesystem->delete( $path );

		if ( ! $deleted ) {
			return [
				'success'   => false,
				'error'     => __( 'Could not delete physical robots.txt file.', 'wpcalibrate-robots-repair' ),
				'backup_id' => $backup_id,
			];
		}

		Logger::log( 'info', 'Deleted physical robots.txt file to allow virtual output' );

		return [
			'success'   => true,
			'error'     => '',
			'backup_id' => $backup_id,
		];
	}
}
