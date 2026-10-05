<?php
/**
 * Validator and Severity Engine for robots.txt rules.
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
 * Class Validator
 *
 * Evaluates parsed robots.txt directives and classifies findings by severity:
 * Healthy, Warning, Critical.
 */
final class Validator {

	public const SEVERITY_CRITICAL = 'critical';
	public const SEVERITY_WARNING  = 'warning';
	public const SEVERITY_HEALTHY  = 'healthy';
	public const SEVERITY_INFO     = 'info';

	/**
	 * Run validation checks against a parsed robots.txt instance.
	 *
	 * @param RuleParser $parser Parsed rules instance.
	 * @return array<int, array<string, mixed>> List of structured findings.
	 */
	public static function validate( RuleParser $parser ): array {
		$findings = [];
		$content  = $parser->get_raw_content();

		// Check 1: Empty robots.txt.
		if ( '' === trim( $content ) ) {
			$findings[] = [
				'id'                 => 'empty_content',
				'title'              => __( 'Empty robots.txt Content', 'wpcalibrate-robots-repair' ),
				'severity'           => self::SEVERITY_WARNING,
				'category'           => 'syntax',
				'explanation'        => __( 'Your robots.txt file is completely empty. While crawlers will treat this as full permission to crawl everything, having standard crawl rules and a sitemap declaration is strongly recommended.', 'wpcalibrate-robots-repair' ),
				'evidence'           => __( 'File contains 0 directives.', 'wpcalibrate-robots-repair' ),
				'likely_cause'       => __( 'Newly created file, empty virtual output, or previous accidental wipe.', 'wpcalibrate-robots-repair' ),
				'recommended_action' => __( 'Apply the Recommended WordPress preset to establish standard crawl directives and declare your sitemap.', 'wpcalibrate-robots-repair' ),
				'can_auto_repair'    => true,
				'repair_action'      => 'apply_recommended_preset',
			];
			return $findings;
		}

		// Check 2: Control characters or null bytes.
		if ( preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $content ) ) {
			$findings[] = [
				'id'                 => 'binary_control_chars',
				'title'              => __( 'Invalid Binary / Control Characters Detected', 'wpcalibrate-robots-repair' ),
				'severity'           => self::SEVERITY_CRITICAL,
				'category'           => 'syntax',
				'explanation'        => __( 'Your robots.txt contains binary control characters or null bytes. Search engine crawlers may fail to parse this file entirely, leading to unpredictable crawl behavior.', 'wpcalibrate-robots-repair' ),
				'evidence'           => __( 'Detected non-printable ASCII control characters.', 'wpcalibrate-robots-repair' ),
				'likely_cause'       => __( 'File corruption, copy-paste encoding issue, or binary write error.', 'wpcalibrate-robots-repair' ),
				'recommended_action' => __( 'Reset or clean the file content to ensure clean UTF-8 text.', 'wpcalibrate-robots-repair' ),
				'can_auto_repair'    => true,
				'repair_action'      => 'apply_recommended_preset',
			];
		}

		// Check 3: File size (> 500 KB limit per Google REP guidelines).
		$size_bytes = strlen( $content );
		if ( $size_bytes > 512 * 1024 ) {
			$findings[] = [
				'id'                 => 'oversized_file',
				'title'              => __( 'Robots.txt Exceeds Recommended Size (512 KB)', 'wpcalibrate-robots-repair' ),
				'severity'           => self::SEVERITY_WARNING,
				'category'           => 'syntax',
				'explanation'        => __( 'Major search engines (including Google) truncate robots.txt files larger than 512 KB. Rules beyond this limit will be ignored.', 'wpcalibrate-robots-repair' ),
				'evidence'           => sprintf(
					/* translators: %s: file size formatted */
					__( 'File size is %s.', 'wpcalibrate-robots-repair' ),
					size_format( $size_bytes )
				),
				'likely_cause'       => __( 'Excessive individual URL disallows instead of path patterns.', 'wpcalibrate-robots-repair' ),
				'recommended_action' => __( 'Consolidate repetitive directives into wildcard patterns or path prefixes.', 'wpcalibrate-robots-repair' ),
				'can_auto_repair'    => false,
				'repair_action'      => '',
			];
		}

		// Check 4: Syntax errors on individual lines.
		$parsed_lines = $parser->get_parsed_lines();
		foreach ( $parsed_lines as $line ) {
			if ( ! $line['is_valid_syntax'] ) {
				$findings[] = [
					'id'                 => 'syntax_error_line_' . $line['line_number'],
					'title'              => sprintf(
						/* translators: %d: line number */
						__( 'Syntax Error on Line %d', 'wpcalibrate-robots-repair' ),
						$line['line_number']
					),
					'severity'           => self::SEVERITY_WARNING,
					'category'           => 'syntax',
					'explanation'        => (string) $line['syntax_error'],
					'evidence'           => sprintf( 'Line %d: "%s"', $line['line_number'], esc_html( $line['raw'] ) ),
					'likely_cause'       => __( 'Typo or missing colon delimiter.', 'wpcalibrate-robots-repair' ),
					'recommended_action' => __( 'Correct the directive syntax or remove the invalid line.', 'wpcalibrate-robots-repair' ),
					'can_auto_repair'    => true,
					'repair_action'      => 'fix_syntax_errors',
				];
			}
		}

		// Check 5: Orphaned rules (Allow/Disallow before any User-agent).
		$orphaned = $parser->get_orphaned_rules();
		if ( ! empty( $orphaned ) ) {
			$lines_list = implode( ', ', array_column( $orphaned, 'line_number' ) );
			$findings[] = [
				'id'                 => 'orphaned_rules',
				'title'              => __( 'Directives Missing User-agent Context', 'wpcalibrate-robots-repair' ),
				'severity'           => self::SEVERITY_WARNING,
				'category'           => 'syntax',
				'explanation'        => __( 'Allow or Disallow directives appear before any User-agent declaration. Crawlers will ignore these directives because they do not know which bot they apply to.', 'wpcalibrate-robots-repair' ),
				'evidence'           => sprintf(
					/* translators: %s: line numbers */
					__( 'Affected line(s): %s', 'wpcalibrate-robots-repair' ),
					$lines_list
				),
				'likely_cause'       => __( 'Rules placed at the very top of the file without a User-agent header.', 'wpcalibrate-robots-repair' ),
				'recommended_action' => __( 'Add a "User-agent: *" directive above these rules.', 'wpcalibrate-robots-repair' ),
				'can_auto_repair'    => true,
				'repair_action'      => 'wrap_in_user_agent_all',
			];
		}

		// Check 6: Dangerous global Disallow: / under User-agent: *
		if ( $parser->has_global_disallow() ) {
			$findings[] = [
				'id'                 => 'dangerous_global_disallow',
				'title'              => __( 'Site-Wide Crawl Block ("Disallow: /") Active', 'wpcalibrate-robots-repair' ),
				'severity'           => self::SEVERITY_CRITICAL,
				'category'           => 'crawl_blocking',
				'explanation'        => __( 'A "Disallow: /" directive is set for all search crawlers ("User-agent: *"). This instructs search engines not to crawl any page on your website. Note: robots.txt controls crawling, not indexing; existing URLs may remain in search indexes as blank snippets unless a noindex meta tag or password protection is used.', 'wpcalibrate-robots-repair' ),
				'evidence'           => 'User-agent: * -> Disallow: /',
				'likely_cause'       => __( 'Staging site setting left active, or WordPress "Discourage search engines from indexing this site" was enabled.', 'wpcalibrate-robots-repair' ),
				'recommended_action' => __( 'Remove the site-wide Disallow: / block and restore standard crawl access.', 'wpcalibrate-robots-repair' ),
				'can_auto_repair'    => true,
				'repair_action'      => 'remove_global_disallow',
			];
		}

		// Check 7: Groups analysis (Duplicates, Contradictions, Asset blocking).
		$groups = $parser->get_groups();
		foreach ( $groups as $group_idx => $group ) {
			$ua_str = implode( ', ', $group['user_agents'] );

			$seen_rules = [];
			$allows     = [];
			$disallows  = [];

			foreach ( $group['rules'] as $rule ) {
				$dir_key = $rule['directive'] . ':' . $rule['value'];

				// Duplicate exact directive in same group.
				if ( isset( $seen_rules[ $dir_key ] ) ) {
					$findings[] = [
						'id'                 => sprintf( 'duplicate_rule_%d_%d', $seen_rules[ $dir_key ], $rule['line_number'] ),
						'title'              => __( 'Duplicate Directive Found', 'wpcalibrate-robots-repair' ),
						'severity'           => self::SEVERITY_WARNING,
						'category'           => 'duplicates',
						'explanation'        => sprintf(
							/* translators: 1: directive, 2: group */
							__( 'The directive "%1$s" is declared multiple times under "%2$s". Duplicate rules are redundant and clutter the configuration.', 'wpcalibrate-robots-repair' ),
							$rule['raw'],
							$ua_str
						),
						'evidence'           => sprintf( 'Line %d and Line %d', $seen_rules[ $dir_key ], $rule['line_number'] ),
						'likely_cause'       => __( 'Accidental duplicate addition during manual edits or multiple plugins adding identical rules.', 'wpcalibrate-robots-repair' ),
						'recommended_action' => __( 'Remove the duplicate directive line.', 'wpcalibrate-robots-repair' ),
						'can_auto_repair'    => true,
						'repair_action'      => 'remove_duplicates',
					];
				} else {
					$seen_rules[ $dir_key ] = $rule['line_number'];
				}

				if ( 'allow' === $rule['directive'] ) {
					$allows[ $rule['value'] ] = $rule['line_number'];
				} elseif ( 'disallow' === $rule['directive'] ) {
					$disallows[ $rule['value'] ] = $rule['line_number'];

					// Asset blocking check: CSS, JS, uploads, themes, plugins.
					$val = strtolower( $rule['value'] );
					if (
						str_contains( $val, 'wp-content/themes' ) ||
						str_contains( $val, 'wp-content/plugins' ) ||
						str_contains( $val, '.css' ) ||
						str_contains( $val, '.js' ) ||
						( '/wp-includes/' === $val && ! isset( $allows['/wp-includes/js/'] ) )
					) {
						$findings[] = [
							'id'                 => 'asset_blocking_' . $rule['line_number'],
							'title'              => __( 'Potentially Harmful Resource/Asset Blocking', 'wpcalibrate-robots-repair' ),
							'severity'           => self::SEVERITY_WARNING,
							'category'           => 'asset_blocking',
							'explanation'        => sprintf(
								/* translators: %s: line number */
								__( 'Line %s blocks CSS, JavaScript, themes, or plugin assets. Modern search engines need access to styles and scripts to render and evaluate your website correctly. Blocking them can hurt search rankings.', 'wpcalibrate-robots-repair' ),
								$rule['line_number']
							),
							'evidence'           => sprintf( 'Line %d: %s', $rule['line_number'], $rule['raw'] ),
							'likely_cause'       => __( 'Outdated security advice or legacy SEO rule templates from before 2014.', 'wpcalibrate-robots-repair' ),
							'recommended_action' => __( 'Remove restrictions on frontend resources and styling assets.', 'wpcalibrate-robots-repair' ),
							'can_auto_repair'    => true,
							'repair_action'      => 'remove_asset_blocks',
						];
					}
				}
			}

			// Contradictory rules (exact same path allowed and disallowed in same group).
			foreach ( $allows as $path => $allow_line ) {
				if ( isset( $disallows[ $path ] ) ) {
					$findings[] = [
						'id'                 => sprintf( 'contradiction_%d_%d', $allow_line, $disallows[ $path ] ),
						'title'              => __( 'Contradictory Allow and Disallow Directives', 'wpcalibrate-robots-repair' ),
						'severity'           => self::SEVERITY_WARNING,
						'category'           => 'syntax',
						'explanation'        => sprintf(
							/* translators: 1: path, 2: group */
							__( 'Path "%1$s" is both Allowed (line %2$d) and Disallowed (line %3$d) in "%4$s". While RFC 9309 prioritizes Allow when rule lengths match, contradictory rules cause confusion and crawler inconsistency.', 'wpcalibrate-robots-repair' ),
							$path,
							$allow_line,
							$disallows[ $path ],
							$ua_str
						),
						'evidence'           => sprintf( 'Allow line %d vs Disallow line %d', $allow_line, $disallows[ $path ] ),
						'likely_cause'       => __( 'Conflicting plugins or copy-paste errors.', 'wpcalibrate-robots-repair' ),
						'recommended_action' => __( 'Remove either the redundant Allow or Disallow directive.', 'wpcalibrate-robots-repair' ),
						'can_auto_repair'    => true,
						'repair_action'      => 'resolve_contradiction',
					];
				}
			}
		}

		// Check 8: Sitemap directives.
		$sitemaps = $parser->get_sitemaps();
		if ( empty( $sitemaps ) ) {
			$findings[] = [
				'id'                 => 'missing_sitemap',
				'title'              => __( 'No Sitemap Declaration in robots.txt', 'wpcalibrate-robots-repair' ),
				'severity'           => self::SEVERITY_WARNING,
				'category'           => 'sitemaps',
				'explanation'        => __( 'Your robots.txt does not declare any XML sitemap. Listing your sitemap in robots.txt helps all legitimate search engine crawlers discover and index your content efficiently.', 'wpcalibrate-robots-repair' ),
				'evidence'           => __( '0 Sitemap directives detected in robots.txt.', 'wpcalibrate-robots-repair' ),
				'likely_cause'       => __( 'robots.txt was generated without a sitemap reference.', 'wpcalibrate-robots-repair' ),
				'recommended_action' => __( 'Add your primary XML sitemap URL to robots.txt.', 'wpcalibrate-robots-repair' ),
				'can_auto_repair'    => true,
				'repair_action'      => 'add_sitemap_directive',
			];
		} else {
			$seen_sitemaps = [];
			foreach ( $sitemaps as $sitemap_rec ) {
				$url    = $sitemap_rec['value'];
				$scheme = wp_parse_url( $url, PHP_URL_SCHEME );

				if ( ! in_array( strtolower( (string) $scheme ), [ 'http', 'https' ], true ) ) {
					$findings[] = [
						'id'                 => 'invalid_sitemap_scheme_' . $sitemap_rec['line_number'],
						'title'              => __( 'Invalid Sitemap URL Scheme', 'wpcalibrate-robots-repair' ),
						'severity'           => self::SEVERITY_WARNING,
						'category'           => 'sitemaps',
						'explanation'        => __( 'Sitemap directives must specify a fully-qualified absolute URL starting with http:// or https://. Relative paths or non-HTTP schemes are ignored by crawlers.', 'wpcalibrate-robots-repair' ),
						'evidence'           => sprintf( 'Line %d: %s', $sitemap_rec['line_number'], $sitemap_rec['raw'] ),
						'likely_cause'       => __( 'Relative path entered instead of a full canonical URL.', 'wpcalibrate-robots-repair' ),
						'recommended_action' => __( 'Update the sitemap directive with the complete https:// URL.', 'wpcalibrate-robots-repair' ),
						'can_auto_repair'    => true,
						'repair_action'      => 'fix_sitemap_url',
					];
				}

				if ( in_array( $url, $seen_sitemaps, true ) ) {
					$findings[] = [
						'id'                 => 'duplicate_sitemap_' . $sitemap_rec['line_number'],
						'title'              => __( 'Duplicate Sitemap Declaration', 'wpcalibrate-robots-repair' ),
						'severity'           => self::SEVERITY_WARNING,
						'category'           => 'sitemaps',
						'explanation'        => sprintf(
							/* translators: %s: sitemap url */
							__( 'The sitemap "%s" is declared more than once in your robots.txt file.', 'wpcalibrate-robots-repair' ),
							$url
						),
						'evidence'           => sprintf( 'Line %d: %s', $sitemap_rec['line_number'], $sitemap_rec['raw'] ),
						'likely_cause'       => __( 'Multiple sitemap additions.', 'wpcalibrate-robots-repair' ),
						'recommended_action' => __( 'Remove redundant sitemap declarations.', 'wpcalibrate-robots-repair' ),
						'can_auto_repair'    => true,
						'repair_action'      => 'remove_duplicates',
					];
				} else {
					$seen_sitemaps[] = $url;
				}
			}
		}

		return $findings;
	}

	/**
	 * Compute overall severity status from findings.
	 *
	 * @param array<int, array<string, mixed>> $findings List of findings.
	 * @return string 'critical' | 'warning' | 'healthy'
	 */
	public static function calculate_overall_status( array $findings ): string {
		$has_critical = false;
		$has_warning  = false;

		foreach ( $findings as $f ) {
			if ( self::SEVERITY_CRITICAL === ( $f['severity'] ?? '' ) ) {
				$has_critical = true;
				break;
			}
			if ( self::SEVERITY_WARNING === ( $f['severity'] ?? '' ) ) {
				$has_warning = true;
			}
		}

		if ( $has_critical ) {
			return self::SEVERITY_CRITICAL;
		}

		if ( $has_warning ) {
			return self::SEVERITY_WARNING;
		}

		return self::SEVERITY_HEALTHY;
	}
}
