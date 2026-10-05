<?php
/**
 * WooCommerce Checker and Preset Rule Generator.
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
 * Class WooCommerceChecker
 *
 * Safely inspects WooCommerce presence and permalinks for robots.txt rules.
 * Never queries orders, modifies products, or touches WooCommerce sessions.
 */
final class WooCommerceChecker {

	/**
	 * Check if WooCommerce is installed and active.
	 */
	public static function is_active(): bool {
		return class_exists( 'WooCommerce' ) || function_exists( 'WC' );
	}

	/**
	 * Get WooCommerce version string if active.
	 */
	public static function get_version(): string {
		if ( ! self::is_active() ) {
			return '';
		}

		if ( defined( 'WC_VERSION' ) ) {
			return WC_VERSION;
		}

		return 'Active';
	}

	/**
	 * Get recommended Disallow paths for active WooCommerce transactional endpoints.
	 *
	 * Dynamically queries configured page permalinks to avoid guessing URL structures.
	 *
	 * @return array<int, array<string, string>> Array of rules with path and justification.
	 */
	public static function get_transactional_rules(): array {
		if ( ! self::is_active() ) {
			return [];
		}

		$rules = [];

		// 1. Cart Page.
		$cart_path = self::get_page_path( 'cart', '/cart/' );
		$rules[]   = [
			'path'          => $cart_path,
			'justification' => __( 'Cart contains dynamic user-specific shopping session data that should not be crawled or indexed.', 'wpcalibrate-robots-repair' ),
		];

		// 2. Checkout Page.
		$checkout_path = self::get_page_path( 'checkout', '/checkout/' );
		$rules[]       = [
			'path'          => $checkout_path,
			'justification' => __( 'Checkout contains transactional payment screens that do not provide indexable content.', 'wpcalibrate-robots-repair' ),
		];

		// 3. My Account Page.
		$account_path = self::get_page_path( 'myaccount', '/my-account/' );
		$rules[]      = [
			'path'          => $account_path,
			'justification' => __( 'My Account contains customer account dashboards and private login views.', 'wpcalibrate-robots-repair' ),
		];

		return $rules;
	}

	/**
	 * Inspect existing rules for accidental blocking of indexable commercial content.
	 *
	 * @param RuleParser $parser Parsed rules.
	 * @return array<int, array<string, mixed>> Findings if commercial pages blocked.
	 */
	public static function inspect_existing_rules( RuleParser $parser ): array {
		if ( ! self::is_active() ) {
			return [];
		}

		$findings = [];
		$groups   = $parser->get_groups();

		foreach ( $groups as $group ) {
			foreach ( $group['rules'] as $rule ) {
				if ( 'disallow' !== $rule['directive'] ) {
					continue;
				}

				$val = strtolower( $rule['value'] );

				// Check for accidental blocking of shop or products.
				if ( '/shop/' === $val || '/shop' === $val || str_starts_with( $val, '/product' ) ) {
					$findings[] = [
						'id'                 => 'wc_commercial_blocked_' . $rule['line_number'],
						'title'              => __( 'WooCommerce Catalog or Products Blocked from Crawling', 'wpcalibrate-robots-repair' ),
						'severity'           => Validator::SEVERITY_CRITICAL,
						'category'           => 'crawl_blocking',
						'explanation'        => sprintf(
							/* translators: 1: line number, 2: path */
							__( 'Line %1$d disallows "%2$s". This prevents search engines from crawling your shop or product catalog, leading to loss of organic search visibility and revenue.', 'wpcalibrate-robots-repair' ),
							$rule['line_number'],
							$rule['value']
						),
						'evidence'           => sprintf( 'Line %d: %s', $rule['line_number'], $rule['raw'] ),
						'likely_cause'       => __( 'Accidental crawl restriction intended for staging or testing.', 'wpcalibrate-robots-repair' ),
						'recommended_action' => __( 'Remove the Disallow directive for shop and product paths.', 'wpcalibrate-robots-repair' ),
						'can_auto_repair'    => true,
						'repair_action'      => 'remove_commercial_block',
					];
				}
			}
		}

		return $findings;
	}

	/**
	 * Safely resolve relative path for a WooCommerce endpoint.
	 *
	 * @param string $page_key WooCommerce page key: 'cart', 'checkout', 'myaccount'.
	 * @param string $fallback Fallback relative path.
	 * @return string Relative URL path.
	 */
	private static function get_page_path( string $page_key, string $fallback ): string {
		if ( function_exists( 'wc_get_page_id' ) && function_exists( 'get_permalink' ) ) {
			$page_id = wc_get_page_id( $page_key );
			if ( $page_id > 0 ) {
				$permalink = get_permalink( $page_id );
				if ( ! empty( $permalink ) && ! is_wp_error( $permalink ) ) {
					$parsed = wp_parse_url( $permalink );
					if ( ! empty( $parsed['path'] ) ) {
						return trailingslashit( $parsed['path'] );
					}
				}
			}
		}

		return $fallback;
	}
}
