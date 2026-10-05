<?php
/**
 * Repair Preview and Diff Engine.
 *
 * @package WPCalibrate\RobotsRepair
 */

declare(strict_types=1);

namespace WPCalibrate\RobotsRepair\Repair;

use WPCalibrate\RobotsRepair\Diagnostics\RuleParser;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RepairPreview
 *
 * Generates structured before/after diffs and safety assessments.
 */
final class RepairPreview {

	/**
	 * Build comparison preview between current and proposed content.
	 *
	 * @param string $current_content Current robots.txt content.
	 * @param string $proposed_content Proposed new robots.txt content.
	 * @param string $action_title Name of the repair action.
	 * @return array<string, mixed> Structured preview report.
	 */
	public static function build( string $current_content, string $proposed_content, string $action_title = '' ): array {
		$norm_current  = str_replace( [ "\r\n", "\r" ], "\n", trim( $current_content ) );
		$norm_proposed = str_replace( [ "\r\n", "\r" ], "\n", trim( $proposed_content ) );

		$current_lines  = ( '' !== $norm_current ) ? explode( "\n", $norm_current ) : [];
		$proposed_lines = ( '' !== $norm_proposed ) ? explode( "\n", $norm_proposed ) : [];

		$diff_lines = self::compute_diff( $current_lines, $proposed_lines );

		// Parse proposed content to evaluate safety.
		$parser = new RuleParser( $proposed_content );

		$safety_rating = 'safe';
		$warnings      = [];

		if ( $parser->has_global_disallow() ) {
			$safety_rating = 'dangerous';
			$warnings[]    = __( 'Proposed configuration contains "Disallow: /" under "User-agent: *", which will block all search crawlers from the entire site.', 'wpcalibrate-robots-repair' );
		}

		$added_count   = 0;
		$removed_count = 0;
		foreach ( $diff_lines as $dl ) {
			if ( 'added' === $dl['type'] ) {
				$added_count++;
			} elseif ( 'removed' === $dl['type'] ) {
				$removed_count++;
			}
		}

		$summary = sprintf(
			/* translators: 1: additions count, 2: deletions count */
			__( 'This repair proposes %1$d addition(s) and %2$d removal(s).', 'wpcalibrate-robots-repair' ),
			$added_count,
			$removed_count
		);

		return [
			'action_title'     => $action_title,
			'current_content'  => $current_content,
			'proposed_content' => $proposed_content,
			'diff_lines'       => $diff_lines,
			'summary'          => $summary,
			'safety_rating'    => $safety_rating,
			'warnings'         => $warnings,
			'added_count'      => $added_count,
			'removed_count'    => $removed_count,
		];
	}

	/**
	 * Compute simple line-based diff representation.
	 *
	 * @param array<int, string> $old Lines of old content.
	 * @param array<int, string> $new Lines of new content.
	 * @return array<int, array<string, string>>
	 */
	private static function compute_diff( array $old, array $new ): array {
		$diff = [];

		$max = max( count( $old ), count( $new ) );
		$i   = 0;
		$j   = 0;

		$old_len = count( $old );
		$new_len = count( $new );

		while ( $i < $old_len || $j < $new_len ) {
			if ( $i < $old_len && $j < $new_len && $old[ $i ] === $new[ $j ] ) {
				$diff[] = [
					'type' => 'unchanged',
					'text' => $old[ $i ],
				];
				$i++;
				$j++;
			} elseif ( $i < $old_len && ! in_array( $old[ $i ], array_slice( $new, $j ), true ) ) {
				$diff[] = [
					'type' => 'removed',
					'text' => $old[ $i ],
				];
				$i++;
			} elseif ( $j < $new_len ) {
				$diff[] = [
					'type' => 'added',
					'text' => $new[ $j ],
				];
				$j++;
			} else {
				if ( $i < $old_len ) {
					$diff[] = [ 'type' => 'removed', 'text' => $old[ $i ] ];
					$i++;
				}
				if ( $j < $new_len ) {
					$diff[] = [ 'type' => 'added', 'text' => $new[ $j ] ];
					$j++;
				}
			}
		}

		return $diff;
	}
}
