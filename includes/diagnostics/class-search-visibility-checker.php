<?php
/**
 * Search Engine Visibility diagnostic checker.
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
 * Class SearchVisibilityChecker
 *
 * Inspects WordPress Settings -> Reading (blog_public) and analyzes its
 * interaction with robots.txt directives and noindex meta output.
 */
final class SearchVisibilityChecker {

	/**
	 * Inspect WordPress Search Engine Visibility setting.
	 *
	 * @param bool $has_global_disallow Whether robots.txt currently disallows all crawlers.
	 * @return array<string, mixed>
	 */
	public static function inspect( bool $has_global_disallow = false ): array {
		$blog_public = (int) get_option( 'blog_public', 1 );
		$is_discouraged = ( 0 === $blog_public );

		$findings = [];
		$status   = 'healthy';

		if ( $is_discouraged ) {
			$status     = 'warning';
			$findings[] = [
				'id'                 => 'blog_public_discouraged',
				'title'              => __( 'WordPress Search Engine Visibility is Set to "Discourage"', 'wpcalibrate-robots-repair' ),
				'severity'           => Validator::SEVERITY_WARNING,
				'category'           => 'crawl_blocking',
				'explanation'        => __( 'In WordPress Settings → Reading, "Discourage search engines from indexing this site" is checked. WordPress natively outputs Disallow: / in virtual robots.txt AND injects a "noindex, nofollow" meta tag in your website header. If this is a live public website, search engines will drop your pages from search results.', 'wpcalibrate-robots-repair' ),
				'evidence'           => 'WordPress option blog_public = 0',
				'likely_cause'       => __( 'Setting left active from development/staging phase or intentionally disabled by administrator.', 'wpcalibrate-robots-repair' ),
				'recommended_action' => __( 'If you want search engines to index your website, uncheck "Discourage search engines" in Settings → Reading.', 'wpcalibrate-robots-repair' ),
				'can_auto_repair'    => false, // Do not alter blog_public automatically per strict requirement!
				'repair_action'      => '',
			];
		} elseif ( $has_global_disallow ) {
			// Mismatch! Site is set to Public, but robots.txt has Disallow: /
			$status     = 'critical';
			$findings[] = [
				'id'                 => 'public_intent_mismatch',
				'title'              => __( 'Mismatch: Site is Set to Public but robots.txt Blocks Crawlers', 'wpcalibrate-robots-repair' ),
				'severity'           => Validator::SEVERITY_CRITICAL,
				'category'           => 'crawl_blocking',
				'explanation'        => __( 'Your WordPress Reading settings indicate this is a public website (blog_public = 1), but your robots.txt contains a site-wide "Disallow: /". This contradiction prevents search engines from crawling your content.', 'wpcalibrate-robots-repair' ),
				'evidence'           => 'blog_public = 1 with User-agent: * Disallow: / in robots.txt',
				'likely_cause'       => __( 'Static robots.txt file left behind from staging or legacy configuration.', 'wpcalibrate-robots-repair' ),
				'recommended_action' => __( 'Repair robots.txt to remove the Disallow: / block.', 'wpcalibrate-robots-repair' ),
				'can_auto_repair'    => true,
				'repair_action'      => 'remove_global_disallow',
			];
		}

		return [
			'blog_public'    => $blog_public,
			'is_discouraged' => $is_discouraged,
			'status'         => $status,
			'findings'       => $findings,
		];
	}
}
