<?php
/**
 * Unified robots.txt Diagnostics Scanner.
 *
 * @package WPCalibrate\RobotsRepair
 */

declare(strict_types=1);

namespace WPCalibrate\RobotsRepair\Diagnostics;

use WPCalibrate\RobotsRepair\Support\Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Scanner
 *
 * Orchestrates full local, filesystem, and public network diagnostics.
 */
final class Scanner {

	/**
	 * Option key for caching the latest scan result.
	 */
	public const LAST_SCAN_OPTION = 'wpcalibrate_robots_repair_last_scan';

	/**
	 * Run a full scan and compile all findings.
	 *
	 * @param bool $force_refresh Skip transient cache if set.
	 * @return array<string, mixed> Complete scan results payload.
	 */
	public static function run( bool $force_refresh = true ): array {
		Logger::log( 'info', 'Initiating robots.txt diagnostic scan' );

		// 1. Fetch public HTTP response.
		$public_info = PublicRobotsFetcher::fetch();

		// 2. Inspect physical file in web root.
		$physical_info = PhysicalFileDetector::inspect();

		// 3. Inspect SEO plugins.
		$seo_info = SeoPluginDetector::detect();

		// 4. Retrieve current stored virtual rules (if any).
		$stored_rules           = get_option( 'wpcalibrate_robots_repair_rules', [] );
		$plugin_virtual_content = is_array( $stored_rules ) ? (string) ( $stored_rules['virtual_content'] ?? '' ) : '';

		// 5. Determine source.
		$source_info = SourceDetector::determine_source(
			$physical_info,
			$public_info,
			$seo_info,
			$plugin_virtual_content
		);

		// Determine which content to parse: prefer public content if 200 OK, otherwise physical file.
		$content_to_parse = $public_info['content'];
		if ( 200 !== $public_info['http_status'] && ! empty( $physical_info['content'] ) ) {
			$content_to_parse = $physical_info['content'];
		}

		// 6. Parse rules.
		$parser = new RuleParser( $content_to_parse );

		// 7. Core rule validator findings.
		$all_findings = [];

		// Check for HTTP-level failures first.
		if ( ! empty( $public_info['error'] ) ) {
			$all_findings[] = [
				'id'                 => 'public_fetch_failed',
				'title'              => __( 'robots.txt URL Unreachable via HTTP', 'wpcalibrate-robots-repair' ),
				'severity'           => Validator::SEVERITY_CRITICAL,
				'category'           => 'access',
				'explanation'        => sprintf(
					/* translators: 1: error message */
					__( 'WordPress could not connect to its own public robots.txt URL: %s. If crawlers encounter connection failures or DNS errors, they may temporarily pause crawling or treat your site as unavailable.', 'wpcalibrate-robots-repair' ),
					$public_info['error']
				),
				'evidence'           => sprintf( '%s -> %s', $public_info['url'], $public_info['error'] ),
				'likely_cause'       => __( 'Loopback request failure, local DNS configuration, or strict firewall/WAF.', 'wpcalibrate-robots-repair' ),
				'recommended_action' => __( 'Check server loopback connectivity and ensure firewall allows public HTTP requests to robots.txt.', 'wpcalibrate-robots-repair' ),
				'can_auto_repair'    => false,
				'repair_action'      => '',
			];
		} elseif ( 200 !== $public_info['http_status'] ) {
			$severity = ( 404 === $public_info['http_status'] ) ? Validator::SEVERITY_WARNING : Validator::SEVERITY_CRITICAL;
			$all_findings[] = [
				'id'                 => 'http_status_error_' . $public_info['http_status'],
				'title'              => sprintf(
					/* translators: %d: http code */
					__( 'Public robots.txt Returned HTTP Error %d', 'wpcalibrate-robots-repair' ),
					$public_info['http_status']
				),
				'severity'           => $severity,
				'category'           => 'access',
				'explanation'        => sprintf(
					/* translators: %d: http code */
					__( 'The public robots.txt endpoint returned an HTTP status code of %d instead of 200 OK. Standard crawlers require HTTP 200 to read rules.', 'wpcalibrate-robots-repair' ),
					$public_info['http_status']
				),
				'evidence'           => sprintf( 'HTTP %d from %s', $public_info['http_status'], $public_info['url'] ),
				'likely_cause'       => __( 'Server rewrite misconfiguration, missing physical file when rewrites disabled, or server-level 403/500 error.', 'wpcalibrate-robots-repair' ),
				'recommended_action' => __( 'Verify WordPress rewrite rules in .htaccess / nginx and ensure robots.txt route is reachable.', 'wpcalibrate-robots-repair' ),
				'can_auto_repair'    => false,
				'repair_action'      => '',
			];
		} elseif ( $public_info['is_html'] ) {
			$all_findings[] = [
				'id'                 => 'html_response_detected',
				'title'              => __( 'robots.txt Returned HTML Document Instead of Plain Text', 'wpcalibrate-robots-repair' ),
				'severity'           => Validator::SEVERITY_CRITICAL,
				'category'           => 'access',
				'explanation'        => __( 'The public URL returned an HTML web page (such as a theme 404 template or a login screen) instead of plain-text robots directives. Crawlers cannot parse HTML as robots.txt.', 'wpcalibrate-robots-repair' ),
				'evidence'           => sprintf( 'Content-Type: %s, starts with HTML markup.', $public_info['content_type'] ),
				'likely_cause'       => __( 'Theme catch-all 404 handler, maintenance mode page, or security plugin block.', 'wpcalibrate-robots-repair' ),
				'recommended_action' => __( 'Ensure robots.txt returns Content-Type text/plain with standard crawler syntax.', 'wpcalibrate-robots-repair' ),
				'can_auto_repair'    => true,
				'repair_action'      => 'apply_recommended_preset',
			];
		} else {
			// Content is 200 OK plain text, run syntax and rule validation.
			$rule_findings = Validator::validate( $parser );
			$all_findings  = array_merge( $all_findings, $rule_findings );
		}

		// 8. Physical file specific findings.
		if ( ! empty( $physical_info['exists'] ) ) {
			if ( ! $physical_info['is_readable'] ) {
				$all_findings[] = [
					'id'                 => 'physical_unreadable',
					'title'              => __( 'Physical robots.txt File Exists but is Unreadable', 'wpcalibrate-robots-repair' ),
					'severity'           => Validator::SEVERITY_CRITICAL,
					'category'           => 'filesystem',
					'explanation'        => __( 'A physical robots.txt file exists in the website root directory, but WordPress has insufficient file permissions to read its content.', 'wpcalibrate-robots-repair' ),
					'evidence'           => sprintf( 'File permissions: %s', $physical_info['permissions'] ),
					'likely_cause'       => __( 'Restricted filesystem permissions or differing file ownership.', 'wpcalibrate-robots-repair' ),
					'recommended_action' => __( 'Adjust file permissions (recommended 0644) so the web server can read it.', 'wpcalibrate-robots-repair' ),
					'can_auto_repair'    => false,
					'repair_action'      => '',
				];
			} elseif ( ! $physical_info['is_writable'] ) {
				$all_findings[] = [
					'id'                 => 'physical_not_writable',
					'title'              => __( 'Physical robots.txt is Not Writable by WordPress', 'wpcalibrate-robots-repair' ),
					'severity'           => Validator::SEVERITY_INFO,
					'category'           => 'filesystem',
					'explanation'        => __( 'A physical robots.txt file exists in your web root, but it is read-only. Automatic repairs to this physical file will require file permissions or FTP credentials.', 'wpcalibrate-robots-repair' ),
					'evidence'           => sprintf( 'File permissions: %s (is_writable: false)', $physical_info['permissions'] ),
					'likely_cause'       => __( 'File owned by another system user (e.g. root/ftp) or set to read-only 0444.', 'wpcalibrate-robots-repair' ),
					'recommended_action' => __( 'Make the file writable (0644) if you wish to repair it via the dashboard, or edit it via SFTP.', 'wpcalibrate-robots-repair' ),
					'can_auto_repair'    => false,
					'repair_action'      => '',
				];
			}
		}

		// 9. Search Engine Visibility inspection.
		$has_global_disallow = $parser->has_global_disallow();
		$visibility_report   = SearchVisibilityChecker::inspect( $has_global_disallow );
		if ( ! empty( $visibility_report['findings'] ) ) {
			$all_findings = array_merge( $all_findings, $visibility_report['findings'] );
		}

		// 10. Sitemap verification.
		$declared_sitemap_urls = [];
		foreach ( $parser->get_sitemaps() as $sm_rec ) {
			$declared_sitemap_urls[] = $sm_rec['value'];
		}
		$sitemap_report = SitemapChecker::check_sitemaps( $declared_sitemap_urls );

		// Check if declared sitemaps failed accessibility.
		foreach ( $sitemap_report['candidates'] as $candidate ) {
			if ( ! $candidate['is_accessible'] && in_array( $candidate['url'], $declared_sitemap_urls, true ) ) {
				$all_findings[] = [
					'id'                 => 'sitemap_inaccessible_' . md5( $candidate['url'] ),
					'title'              => __( 'Declared Sitemap is Inaccessible (HTTP Error / Not Found)', 'wpcalibrate-robots-repair' ),
					'severity'           => Validator::SEVERITY_WARNING,
					'category'           => 'sitemaps',
					'explanation'        => sprintf(
						/* translators: 1: url, 2: error */
						__( 'The sitemap URL declared in robots.txt ("%1$s") failed verification: %2$s. Search engine bots attempting to fetch this sitemap will encounter an error.', 'wpcalibrate-robots-repair' ),
						$candidate['url'],
						$candidate['error']
					),
					'evidence'           => sprintf( '%s -> %s', $candidate['url'], $candidate['error'] ),
					'likely_cause'       => __( 'Sitemap plugin disabled, URL changed, or 404 error.', 'wpcalibrate-robots-repair' ),
					'recommended_action' => __( 'Update robots.txt with a reachable sitemap URL or check your sitemap generation plugin.', 'wpcalibrate-robots-repair' ),
					'can_auto_repair'    => ( null !== $sitemap_report['primary_sitemap'] ),
					'repair_action'      => 'update_sitemap_to_primary',
				];
			}
		}

		// 11. WooCommerce checks (if active).
		$wc_findings = WooCommerceChecker::inspect_existing_rules( $parser );
		if ( ! empty( $wc_findings ) ) {
			$all_findings = array_merge( $all_findings, $wc_findings );
		}

		// Calculate overall health status.
		$overall_status = Validator::calculate_overall_status( $all_findings );

		$result = [
			'timestamp'       => time(),
			'overall_status'  => $overall_status,
			'public_info'     => $public_info,
			'physical_info'   => $physical_info,
			'source_info'     => $source_info,
			'seo_info'        => $seo_info,
			'visibility_info' => $visibility_report,
			'sitemap_info'    => $sitemap_report,
			'findings'        => $all_findings,
			'parsed_rules'    => [
				'lines_count'  => count( $parser->get_parsed_lines() ),
				'groups_count' => count( $parser->get_groups() ),
				'sitemaps'     => $parser->get_sitemaps(),
			],
			'raw_content'     => $content_to_parse,
		];

		// Persist latest scan result.
		update_option( self::LAST_SCAN_OPTION, $result, false );

		Logger::log(
			'info',
			sprintf( 'Scan complete. Status: %s, Findings count: %d', $overall_status, count( $all_findings ) )
		);

		return $result;
	}

	/**
	 * Retrieve the last saved scan result, or run a new scan if empty.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_last_scan_or_run(): array {
		$saved = get_option( self::LAST_SCAN_OPTION, [] );
		if ( is_array( $saved ) && ! empty( $saved['timestamp'] ) ) {
			return $saved;
		}

		return self::run();
	}
}
