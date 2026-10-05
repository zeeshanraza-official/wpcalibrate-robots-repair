<?php
/**
 * Dedicated Robots.txt Rule Parser conforming to RFC 9309 (REP).
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
 * Class RuleParser
 *
 * Non-destructive, line-aware parser for robots.txt files.
 * Preserves comments, whitespace, unknown directives, and formatting.
 */
final class RuleParser {

	/**
	 * Recognized standard and common directives (case-insensitive).
	 */
	public const STANDARD_DIRECTIVES = [
		'user-agent',
		'allow',
		'disallow',
		'sitemap',
		'crawl-delay',
		'host',
	];

	/**
	 * Parsed lines.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $parsed_lines = [];

	/**
	 * Grouped user-agent sections.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $groups = [];

	/**
	 * All sitemap directives found.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $sitemaps = [];

	/**
	 * Global disallow found flag.
	 */
	private bool $has_global_disallow = false;

	/**
	 * Lines appearing before any user-agent context.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $orphaned_rules = [];

	/**
	 * Raw content passed in.
	 */
	private string $raw_content = '';

	/**
	 * Constructor.
	 *
	 * @param string $content Raw robots.txt text.
	 */
	public function __construct( string $content = '' ) {
		if ( '' !== $content ) {
			$this->parse( $content );
		}
	}

	/**
	 * Parse raw robots.txt content into structured records.
	 *
	 * @param string $content Raw content.
	 * @return self
	 */
	public function parse( string $content ): self {
		$this->raw_content         = $content;
		$this->parsed_lines        = [];
		$this->groups              = [];
		$this->sitemaps            = [];
		$this->orphaned_rules      = [];
		$this->has_global_disallow = false;

		// Normalize CRLF / CR to LF for internal parsing.
		$normalized = str_replace( [ "\r\n", "\r" ], "\n", $content );
		$lines      = explode( "\n", $normalized );

		$current_group = null;
		$in_group      = false;

		foreach ( $lines as $index => $raw_line ) {
			$line_number = $index + 1;
			$line_record = $this->parse_line( $raw_line, $line_number );
			$this->parsed_lines[] = $line_record;

			// Handle sitemaps separately as they are site-wide.
			if ( 'sitemap' === $line_record['directive'] ) {
				$this->sitemaps[] = $line_record;
			}

			// Grouping logic according to RFC 9309:
			if ( 'user-agent' === $line_record['directive'] ) {
				$ua_value = $line_record['value'];

				if ( ! $in_group || null === $current_group ) {
					// Start new group.
					$current_group = [
						'user_agents' => [ $ua_value ],
						'lines'       => [ $line_record ],
						'rules'       => [],
						'start_line'  => $line_number,
					];
					$in_group      = true;
				} else {
					// Consecutive User-agent lines in the same group header.
					if ( empty( $current_group['rules'] ) ) {
						$current_group['user_agents'][] = $ua_value;
						$current_group['lines'][]       = $line_record;
					} else {
						// Group had rules already, so this starts a new group.
						$this->groups[] = $current_group;
						$current_group  = [
							'user_agents' => [ $ua_value ],
							'lines'       => [ $line_record ],
							'rules'       => [],
							'start_line'  => $line_number,
						];
					}
				}
			} elseif ( in_array( $line_record['directive'], [ 'allow', 'disallow' ], true ) ) {
				if ( $in_group && null !== $current_group ) {
					$current_group['rules'][] = $line_record;
					$current_group['lines'][] = $line_record;

					// Check for dangerous global Disallow: /
					if ( 'disallow' === $line_record['directive'] && '/' === $line_record['value'] ) {
						if ( in_array( '*', $current_group['user_agents'], true ) ) {
							$this->has_global_disallow = true;
						}
					}
				} else {
					// Directive without preceding User-agent.
					$this->orphaned_rules[] = $line_record;
				}
			} elseif ( 'directive' === $line_record['type'] || 'unknown' === $line_record['type'] ) {
				if ( $in_group && null !== $current_group && 'sitemap' !== $line_record['directive'] ) {
					$current_group['lines'][] = $line_record;
				}
			}
		}

		if ( null !== $current_group ) {
			$this->groups[] = $current_group;
		}

		return $this;
	}

	/**
	 * Parse an individual line into structured data.
	 *
	 * @param string $raw Raw line text.
	 * @param int    $line_number Line index.
	 * @return array<string, mixed>
	 */
	private function parse_line( string $raw, int $line_number ): array {
		$trimmed = trim( $raw );

		// Blank line.
		if ( '' === $trimmed ) {
			return [
				'line_number'        => $line_number,
				'raw'                => $raw,
				'type'               => 'blank',
				'directive'          => '',
				'value'              => '',
				'comment'            => '',
				'has_inline_comment' => false,
				'is_valid_syntax'    => true,
				'syntax_error'       => null,
			];
		}

		// Comment line.
		if ( str_starts_with( $trimmed, '#' ) ) {
			return [
				'line_number'        => $line_number,
				'raw'                => $raw,
				'type'               => 'comment',
				'directive'          => '',
				'value'              => '',
				'comment'            => ltrim( $trimmed, '# ' ),
				'has_inline_comment' => false,
				'is_valid_syntax'    => true,
				'syntax_error'       => null,
			];
		}

		// Check for inline comments.
		$comment            = '';
		$has_inline_comment = false;
		$content_part       = $trimmed;

		$hash_pos = strpos( $trimmed, '#' );
		if ( false !== $hash_pos ) {
			$content_part       = trim( substr( $trimmed, 0, $hash_pos ) );
			$comment            = trim( substr( $trimmed, $hash_pos + 1 ) );
			$has_inline_comment = true;
		}

		// Directive split by first colon.
		$colon_pos = strpos( $content_part, ':' );
		if ( false === $colon_pos ) {
			// No colon found on a non-comment, non-blank line => syntax error!
			return [
				'line_number'        => $line_number,
				'raw'                => $raw,
				'type'               => 'malformed',
				'directive'          => '',
				'value'              => $content_part,
				'comment'            => $comment,
				'has_inline_comment' => $has_inline_comment,
				'is_valid_syntax'    => false,
				'syntax_error'       => __( 'Missing colon delimiter in directive line.', 'wpcalibrate-robots-repair' ),
			];
		}

		$directive_raw = trim( substr( $content_part, 0, $colon_pos ) );
		$value_raw     = trim( substr( $content_part, $colon_pos + 1 ) );
		$directive_key = strtolower( $directive_raw );

		$type = in_array( $directive_key, self::STANDARD_DIRECTIVES, true ) ? 'directive' : 'unknown';

		// Syntax validation for specific directives.
		$is_valid_syntax = true;
		$syntax_error    = null;

		if ( 'user-agent' === $directive_key && '' === $value_raw ) {
			$is_valid_syntax = false;
			$syntax_error    = __( 'User-agent directive cannot have an empty value.', 'wpcalibrate-robots-repair' );
		}

		if ( 'sitemap' === $directive_key && '' === $value_raw ) {
			$is_valid_syntax = false;
			$syntax_error    = __( 'Sitemap directive cannot have an empty value.', 'wpcalibrate-robots-repair' );
		}

		return [
			'line_number'        => $line_number,
			'raw'                => $raw,
			'type'               => $type,
			'directive'          => $directive_key,
			'directive_raw'      => $directive_raw,
			'value'              => $value_raw,
			'comment'            => $comment,
			'has_inline_comment' => $has_inline_comment,
			'is_valid_syntax'    => $is_valid_syntax,
			'syntax_error'       => $syntax_error,
		];
	}

	/**
	 * Get all parsed lines.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_parsed_lines(): array {
		return $this->parsed_lines;
	}

	/**
	 * Get grouped user agent sections.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_groups(): array {
		return $this->groups;
	}

	/**
	 * Get sitemap declarations.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_sitemaps(): array {
		return $this->sitemaps;
	}

	/**
	 * Get rules appearing before any User-agent line.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_orphaned_rules(): array {
		return $this->orphaned_rules;
	}

	/**
	 * Check if dangerous Disallow: / exists under User-agent: *.
	 */
	public function has_global_disallow(): bool {
		return $this->has_global_disallow;
	}

	/**
	 * Get raw content.
	 */
	public function get_raw_content(): string {
		return $this->raw_content;
	}
}
