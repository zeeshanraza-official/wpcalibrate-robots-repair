<?php
/**
 * Master Repair Coordinator and Verification Engine.
 *
 * @package WPCalibrate\RobotsRepair
 */

declare(strict_types=1);

namespace WPCalibrate\RobotsRepair\Repair;

use WPCalibrate\RobotsRepair\Backup\BackupRepository;
use WPCalibrate\RobotsRepair\Diagnostics\PhysicalFileDetector;
use WPCalibrate\RobotsRepair\Diagnostics\PublicRobotsFetcher;
use WPCalibrate\RobotsRepair\Diagnostics\RuleParser;
use WPCalibrate\RobotsRepair\Diagnostics\Scanner;
use WPCalibrate\RobotsRepair\Diagnostics\SitemapChecker;
use WPCalibrate\RobotsRepair\Support\Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RepairManager
 *
 * Coordinates execution of safe repairs, concurrency locking, cache clearing,
 * and mandatory post-repair public verification.
 */
final class RepairManager {

	public const LOCK_KEY     = 'wpcalibrate_robots_repair_lock';
	public const LOCK_TIMEOUT = 30; // 30 seconds safety timeout.

	public const STATUS_VERIFIED       = 'Verified';
	public const STATUS_FAILED         = 'Verification Failed';
	public const STATUS_MANUAL_ACTION  = 'Manual Action Required';

	/**
	 * Apply an automatic repair action safely.
	 *
	 * @param string               $action_key Action identifier (e.g. 'apply_recommended_preset', 'remove_global_disallow', etc.).
	 * @param array<string, mixed> $params Action parameters.
	 * @param bool                 $explicit_confirmed User explicit confirmation.
	 * @return array<string, mixed> Result report.
	 */
	public static function execute_repair( string $action_key, array $params = [], bool $explicit_confirmed = false ): array {
		if ( ! $explicit_confirmed ) {
			return [
				'success'             => false,
				'error'               => __( 'Repair requires explicit confirmation.', 'wpcalibrate-robots-repair' ),
				'verification_status' => self::STATUS_MANUAL_ACTION,
			];
		}

		// Concurrency protection: acquire lock.
		if ( ! self::acquire_lock() ) {
			return [
				'success'             => false,
				'error'               => __( 'Another repair operation is currently running. Please wait a few seconds.', 'wpcalibrate-robots-repair' ),
				'verification_status' => self::STATUS_FAILED,
			];
		}

		try {
			$result = self::dispatch_repair( $action_key, $params );
		} catch ( \Throwable $e ) {
			Logger::log( 'error', 'Exception during repair execution: ' . $e->getMessage() );
			$result = [
				'success'             => false,
				'error'               => $e->getMessage(),
				'verification_status' => self::STATUS_MANUAL_ACTION,
			];
		} finally {
			self::release_lock();
		}

		return $result;
	}

	/**
	 * Internal dispatcher for repair operations.
	 *
	 * @param string               $action_key
	 * @param array<string, mixed> $params
	 * @return array<string, mixed>
	 */
	private static function dispatch_repair( string $action_key, array $params ): array {
		$physical_info = PhysicalFileDetector::inspect();
		$current_text  = ! empty( $physical_info['content'] ) ? (string) $physical_info['content'] : '';

		if ( '' === $current_text ) {
			$virtual_data = VirtualRobotsManager::get_rules();
			$current_text = (string) ( $virtual_data['virtual_content'] ?? '' );
		}

		$proposed_text = '';
		$target_mode   = 'recommended';

		switch ( $action_key ) {
			case 'apply_recommended_preset':
				$proposed_text = PresetFactory::generate( 'recommended' );
				$target_mode   = 'recommended';
				break;

			case 'apply_woocommerce_preset':
				$proposed_text = PresetFactory::generate( 'woocommerce' );
				$target_mode   = 'woocommerce';
				break;

			case 'save_custom_content':
				$proposed_text = (string) ( $params['content'] ?? '' );
				$target_mode   = 'custom';
				break;

			case 'remove_global_disallow':
				$proposed_text = self::generate_content_without_global_disallow( $current_text );
				$target_mode   = 'custom';
				break;

			case 'remove_duplicates':
				$proposed_text = self::generate_content_without_duplicates( $current_text );
				$target_mode   = 'custom';
				break;

			case 'add_sitemap_directive':
			case 'update_sitemap_to_primary':
				$sm_report     = SitemapChecker::check_sitemaps();
				$sitemap_url   = $sm_report['primary_sitemap'] ?: home_url( '/wp-sitemap.xml' );
				$proposed_text = self::generate_content_with_sitemap( $current_text, $sitemap_url );
				$target_mode   = 'custom';
				break;

			case 'remove_asset_blocks':
				$proposed_text = self::generate_content_without_asset_blocks( $current_text );
				$target_mode   = 'custom';
				break;

			case 'remove_commercial_block':
				$proposed_text = self::generate_content_without_commercial_blocks( $current_text );
				$target_mode   = 'custom';
				break;

			default:
				return [
					'success'             => false,
					'error'               => sprintf(
						/* translators: %s: action key */
						__( 'Unknown repair action: %s', 'wpcalibrate-robots-repair' ),
						esc_html( $action_key )
					),
					'verification_status' => self::STATUS_FAILED,
				];
		}

		// Perform write to physical file if physical file exists, otherwise write to virtual options.
		$is_physical = ! empty( $physical_info['exists'] );
		$backup_id   = '';

		if ( $is_physical ) {
			// Write physical file.
			$write_res = PhysicalRobotsManager::write( $proposed_text, true );
			if ( ! $write_res['success'] ) {
				return [
					'success'             => false,
					'error'               => $write_res['error'],
					'verification_status' => self::STATUS_MANUAL_ACTION,
				];
			}
			$backup_id = $write_res['backup_id'];
		} else {
			// Create backup of current virtual content first.
			if ( '' !== $current_text ) {
				$backup_id = BackupRepository::create_backup(
					$current_text,
					'virtual',
					sprintf( 'Pre-repair backup for %s', $action_key )
				);
			}
			VirtualRobotsManager::save_rules( $proposed_text, $target_mode );
		}

		// Purge legitimate caches.
		self::purge_caches();

		// MANDATORY post-repair public verification.
		$verification = self::verify_public_response( $proposed_text );

		// Rerun full scanner to update stored state.
		Scanner::run( true );

		return [
			'success'             => true,
			'action'              => $action_key,
			'backup_id'           => $backup_id,
			'verification_status' => $verification['status'],
			'verification_note'   => $verification['note'],
			'is_verified'         => $verification['is_verified'],
			'public_status'       => $verification['http_status'],
		];
	}

	/**
	 * Verify that public /robots.txt reflects the expected repaired content.
	 *
	 * @param string $expected_content Expected content.
	 * @return array<string, mixed>
	 */
	public static function verify_public_response( string $expected_content ): array {
		$post_fetch = PublicRobotsFetcher::fetch();

		if ( 200 !== $post_fetch['http_status'] ) {
			return [
				'is_verified' => false,
				'status'      => self::STATUS_FAILED,
				'http_status' => $post_fetch['http_status'],
				'note'        => sprintf(
					/* translators: %d: http status */
					__( 'Public endpoint returned HTTP %d after repair.', 'wpcalibrate-robots-repair' ),
					$post_fetch['http_status']
				),
			];
		}

		$norm_expected = trim( str_replace( [ "\r\n", "\r" ], "\n", $expected_content ) );
		$norm_actual   = trim( str_replace( [ "\r\n", "\r" ], "\n", (string) $post_fetch['content'] ) );

		if ( $norm_expected === $norm_actual ) {
			return [
				'is_verified' => true,
				'status'      => self::STATUS_VERIFIED,
				'http_status' => 200,
				'note'        => __( 'Public robots.txt response exactly matches expected repaired content.', 'wpcalibrate-robots-repair' ),
			];
		}

		// Content mismatch: changes saved locally but public endpoint is serving different output (CDN / Cache).
		return [
			'is_verified' => false,
			'status'      => self::STATUS_FAILED,
			'http_status' => 200,
			'note'        => __( 'Repair was saved locally, but the public /robots.txt response has not updated yet. A reverse proxy, CDN, or server-level page cache may be serving a cached version.', 'wpcalibrate-robots-repair' ),
		];
	}

	/**
	 * Purge legitimate caches through standard APIs.
	 */
	public static function purge_caches(): void {
		// WordPress Object Cache.
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		// LiteSpeed Cache.
		if ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
		}

		// WP Rocket.
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		// W3 Total Cache.
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}

		// WP Super Cache.
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
	}

	/**
	 * Remove Disallow: / directive from User-agent: * section.
	 */
	private static function generate_content_without_global_disallow( string $content ): string {
		$parser = new RuleParser( $content );
		$lines  = $parser->get_parsed_lines();
		$output = [];

		$in_wildcard_ua = false;

		foreach ( $lines as $line ) {
			if ( 'user-agent' === $line['directive'] ) {
				$in_wildcard_ua = ( '*' === $line['value'] );
				$output[]       = $line['raw'];
			} elseif ( $in_wildcard_ua && 'disallow' === $line['directive'] && '/' === $line['value'] ) {
				// Replace Disallow: / with standard WordPress administrative paths.
				$output[] = 'Disallow: /wp-admin/';
				$output[] = 'Allow: /wp-admin/admin-ajax.php';
			} else {
				$output[] = $line['raw'];
			}
		}

		return implode( "\n", $output ) . "\n";
	}

	/**
	 * Remove duplicate directives from content.
	 */
	private static function generate_content_without_duplicates( string $content ): string {
		$parser = new RuleParser( $content );
		$lines  = $parser->get_parsed_lines();
		$output = [];

		$seen_rules = [];

		foreach ( $lines as $line ) {
			if ( in_array( $line['directive'], [ 'allow', 'disallow', 'sitemap' ], true ) ) {
				$key = $line['directive'] . ':' . strtolower( $line['value'] );
				if ( isset( $seen_rules[ $key ] ) ) {
					continue; // Skip duplicate.
				}
				$seen_rules[ $key ] = true;
			}
			$output[] = $line['raw'];
		}

		return implode( "\n", $output ) . "\n";
	}

	/**
	 * Add or update sitemap directive.
	 */
	private static function generate_content_with_sitemap( string $content, string $sitemap_url ): string {
		$clean_content = rtrim( $content );
		// If already has sitemap directive, append or update.
		$has_sitemap = str_contains( strtolower( $clean_content ), 'sitemap:' );

		if ( $has_sitemap ) {
			// Replace existing sitemaps with the new primary one.
			$lines  = explode( "\n", str_replace( [ "\r\n", "\r" ], "\n", $clean_content ) );
			$output = [];
			$added  = false;
			foreach ( $lines as $l ) {
				if ( str_starts_with( strtolower( trim( $l ) ), 'sitemap:' ) ) {
					if ( ! $added ) {
						$output[] = 'Sitemap: ' . esc_url_raw( $sitemap_url );
						$added    = true;
					}
				} else {
					$output[] = $l;
				}
			}
			return implode( "\n", $output ) . "\n";
		}

		return $clean_content . "\n\nSitemap: " . esc_url_raw( $sitemap_url ) . "\n";
	}

	/**
	 * Remove CSS/JS/Theme/Plugin resource blocking rules.
	 */
	private static function generate_content_without_asset_blocks( string $content ): string {
		$parser = new RuleParser( $content );
		$lines  = $parser->get_parsed_lines();
		$output = [];

		foreach ( $lines as $line ) {
			if ( 'disallow' === $line['directive'] ) {
				$val = strtolower( $line['value'] );
				if (
					str_contains( $val, 'wp-content/themes' ) ||
					str_contains( $val, 'wp-content/plugins' ) ||
					str_contains( $val, '.css' ) ||
					str_contains( $val, '.js' ) ||
					'/wp-includes/' === $val
				) {
					continue; // Omit harmful asset blocks.
				}
			}
			$output[] = $line['raw'];
		}

		return implode( "\n", $output ) . "\n";
	}

	/**
	 * Remove commercial catalog blocks.
	 */
	private static function generate_content_without_commercial_blocks( string $content ): string {
		$parser = new RuleParser( $content );
		$lines  = $parser->get_parsed_lines();
		$output = [];

		foreach ( $lines as $line ) {
			if ( 'disallow' === $line['directive'] ) {
				$val = strtolower( $line['value'] );
				if ( '/shop/' === $val || '/shop' === $val || str_starts_with( $val, '/product' ) ) {
					continue; // Omit commercial catalog blocks.
				}
			}
			$output[] = $line['raw'];
		}

		return implode( "\n", $output ) . "\n";
	}

	/**
	 * Acquire temporary concurrency lock.
	 */
	private static function acquire_lock(): bool {
		$lock = get_transient( self::LOCK_KEY );
		if ( false !== $lock ) {
			return false;
		}

		return set_transient( self::LOCK_KEY, time(), self::LOCK_TIMEOUT );
	}

	/**
	 * Release temporary concurrency lock.
	 */
	private static function release_lock(): void {
		delete_transient( self::LOCK_KEY );
	}
}
