<?php
/**
 * Physical robots.txt file detector and filesystem safety inspector.
 *
 * @package WPCalibrate\RobotsRepair
 */

declare(strict_types=1);

namespace WPCalibrate\RobotsRepair\Diagnostics;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PhysicalFileDetector
 *
 * Detects presence, permissions, and properties of a physical robots.txt file
 * in the site's web root. Never accepts user-supplied paths.
 */
final class PhysicalFileDetector {

	/**
	 * Get the canonical path for physical robots.txt in ABSPATH.
	 */
	public static function get_canonical_path(): string {
		return wp_normalize_path( ABSPATH . 'robots.txt' );
	}

	/**
	 * Inspect the physical robots.txt file.
	 *
	 * @return array<string, mixed> Detailed physical file report.
	 */
	public static function inspect(): array {
		$target_path = self::get_canonical_path();

		// Security: verify target cannot escape ABSPATH web root.
		if ( ! self::is_path_safe( $target_path ) ) {
			return [
				'exists'        => false,
				'is_safe'       => false,
				'error'         => __( 'Insecure path detected outside WordPress root.', 'wpcalibrate-robots-repair' ),
				'is_readable'   => false,
				'is_writable'   => false,
				'is_symlink'    => false,
				'size'          => 0,
				'content'       => '',
				'permissions'   => '',
				'modified_time' => 0,
			];
		}

		$exists = file_exists( $target_path );
		if ( ! $exists ) {
			// Check if directory itself is writable so we know if creation is possible.
			$dir_writable = is_writable( ABSPATH );
			return [
				'exists'             => false,
				'is_safe'            => true,
				'error'              => '',
				'is_readable'        => false,
				'is_writable'        => $dir_writable,
				'is_symlink'         => false,
				'size'               => 0,
				'content'            => '',
				'permissions'        => '',
				'modified_time'      => 0,
				'directory_writable' => $dir_writable,
			];
		}

		$is_symlink  = is_link( $target_path );
		$is_readable = is_readable( $target_path );
		$is_writable = is_writable( $target_path );
		$size        = (int) @filesize( $target_path );
		$mtime       = (int) @filemtime( $target_path );
		$perms       = substr( sprintf( '%o', (int) @fileperms( $target_path ) ), -4 );

		$content = '';
		if ( $is_readable && ! $is_symlink ) {
			$raw = @file_get_contents( $target_path );
			if ( false !== $raw ) {
				$content = $raw;
			}
		}

		return [
			'exists'             => true,
			'is_safe'            => true,
			'error'              => '',
			'path'               => $target_path,
			'is_readable'        => $is_readable,
			'is_writable'        => $is_writable,
			'is_symlink'         => $is_symlink,
			'size'               => $size,
			'content'            => $content,
			'permissions'        => $perms,
			'modified_time'      => $mtime,
			'directory_writable' => is_writable( ABSPATH ),
		];
	}

	/**
	 * Verify that target path resolves strictly within the ABSPATH directory.
	 *
	 * @param string $path Path to check.
	 * @return bool
	 */
	public static function is_path_safe( string $path ): bool {
		$norm_path = wp_normalize_path( $path );
		$norm_root = wp_normalize_path( ABSPATH );

		// Reject path traversal tokens.
		if ( str_contains( $norm_path, '..' ) ) {
			return false;
		}

		// Ensure path begins with normalized ABSPATH.
		if ( ! str_starts_with( $norm_path, $norm_root ) ) {
			return false;
		}

		// Ensure filename is strictly 'robots.txt'.
		if ( basename( $norm_path ) !== 'robots.txt' ) {
			return false;
		}

		return true;
	}
}
