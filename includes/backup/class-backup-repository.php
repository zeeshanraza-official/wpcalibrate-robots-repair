<?php
/**
 * Bounded Backup Repository for robots.txt configurations.
 *
 * @package WPCalibrate\RobotsRepair
 */

declare(strict_types=1);

namespace WPCalibrate\RobotsRepair\Backup;

use WPCalibrate\RobotsRepair\Settings\SettingsRepository;
use WPCalibrate\RobotsRepair\Support\Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BackupRepository
 *
 * Manages versioned, non-autoloaded backups of robots.txt states.
 */
final class BackupRepository {

	/**
	 * Option key for backups.
	 */
	public const OPTION_KEY = 'wpcalibrate_robots_repair_backups';

	/**
	 * Create and persist a new backup record.
	 *
	 * @param string $content Content being backed up.
	 * @param string $source_type Source type: 'physical', 'virtual', 'custom', 'preset'.
	 * @param string $reason Reason or action triggering the backup.
	 * @return string Backup ID.
	 */
	public static function create_backup( string $content, string $source_type, string $reason ): string {
		if ( ! SettingsRepository::are_backups_enabled() ) {
			return '';
		}

		$backup_id = 'bk_' . gmdate( 'Ymd_His' ) . '_' . wp_generate_password( 6, false, false );
		$now       = time();
		$hash      = hash( 'sha256', $content );

		$record = [
			'id'           => $backup_id,
			'timestamp'    => $now,
			'source_type'  => sanitize_key( $source_type ),
			'content'      => $content,
			'content_hash' => $hash,
			'reason'       => sanitize_text_field( $reason ),
			'version'      => WPCRR_VERSION,
			'size'         => strlen( $content ),
		];

		$backups = self::get_all();

		// Avoid duplicate consecutive identical backups.
		if ( ! empty( $backups ) ) {
			$latest = $backups[0];
			if ( $latest['content_hash'] === $hash && $latest['source_type'] === $source_type ) {
				return $latest['id'];
			}
		}

		array_unshift( $backups, $record );

		// Bound retention per settings.
		$max = SettingsRepository::get_max_backups();
		if ( count( $backups ) > $max ) {
			$backups = array_slice( $backups, 0, $max );
		}

		update_option( self::OPTION_KEY, $backups, false );

		Logger::log(
			'info',
			sprintf( 'Created robots.txt backup %s (reason: %s)', $backup_id, $reason ),
			[ 'backup_id' => $backup_id, 'source_type' => $source_type ]
		);

		return $backup_id;
	}

	/**
	 * Get all backup records ordered newest to oldest.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_all(): array {
		$backups = get_option( self::OPTION_KEY, [] );
		return is_array( $backups ) ? $backups : [];
	}

	/**
	 * Retrieve a specific backup by ID.
	 *
	 * @param string $id Backup ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $id ): ?array {
		$backups = self::get_all();
		foreach ( $backups as $b ) {
			if ( isset( $b['id'] ) && $b['id'] === $id ) {
				return $b;
			}
		}

		return null;
	}

	/**
	 * Delete a specific backup by ID.
	 *
	 * @param string $id Backup ID.
	 * @return bool
	 */
	public static function delete( string $id ): bool {
		$backups = self::get_all();
		$found   = false;

		$filtered = array_filter(
			$backups,
			static function ( array $b ) use ( $id, &$found ): bool {
				if ( ( $b['id'] ?? '' ) === $id ) {
					$found = true;
					return false;
				}
				return true;
			}
		);

		if ( $found ) {
			update_option( self::OPTION_KEY, array_values( $filtered ), false );
			Logger::log( 'info', sprintf( 'Deleted backup %s', $id ) );
			return true;
		}

		return false;
	}

	/**
	 * Purge all backups.
	 */
	public static function clear_all(): void {
		update_option( self::OPTION_KEY, [], false );
	}
}
