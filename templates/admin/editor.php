<?php
/**
 * Editor and Presets Tab Template.
 *
 * @package WPCalibrate\RobotsRepair
 *
 * @var array<string, mixed> $scan_data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPCalibrate\RobotsRepair\Admin\PageController;
use WPCalibrate\RobotsRepair\Diagnostics\WooCommerceChecker;
use WPCalibrate\RobotsRepair\Repair\PresetFactory;

$raw_content = $scan_data['raw_content'] ?? '';
$is_wc       = WooCommerceChecker::is_active();
$preset_wp   = PresetFactory::generate( 'recommended' );
$preset_wc   = $is_wc ? PresetFactory::generate( 'woocommerce' ) : '';
$source_lbl  = $scan_data['source_info']['label'] ?? __( 'Unknown', 'wpcalibrate-robots-repair' );
?>

<div class="wpcrr-editor-container">
	<div class="wpcrr-editor-header">
		<div class="wpcrr-intro">
			<h2><?php esc_html_e( 'Robots.txt Editor & Presets', 'wpcalibrate-robots-repair' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Directly edit your crawl rules or load conservative, search-engine compliant presets. Changes are validated and backed up automatically.', 'wpcalibrate-robots-repair' ); ?>
			</p>
		</div>
		<div class="wpcrr-source-badge">
			<span><?php esc_html_e( 'Current Output Mode:', 'wpcalibrate-robots-repair' ); ?></span>
			<strong><?php echo esc_html( $source_lbl ); ?></strong>
		</div>
	</div>

	<!-- Presets Quick Loader -->
	<div class="wpcrr-presets-bar">
		<span class="wpcrr-presets-label"><?php esc_html_e( 'Load Preset Template:', 'wpcalibrate-robots-repair' ); ?></span>
		
		<button type="button" class="button button-secondary wpcrr-preset-btn" data-preset="<?php echo esc_attr( $preset_wp ); ?>">
			<span class="dashicons dashicons-wordpress"></span>
			<?php esc_html_e( 'Recommended WordPress', 'wpcalibrate-robots-repair' ); ?>
		</button>

		<?php if ( $is_wc ) : ?>
			<button type="button" class="button button-secondary wpcrr-preset-btn" data-preset="<?php echo esc_attr( $preset_wc ); ?>">
				<span class="dashicons dashicons-cart"></span>
				<?php esc_html_e( 'WooCommerce Store Preset', 'wpcalibrate-robots-repair' ); ?>
			</button>
		<?php else : ?>
			<button type="button" class="button button-secondary" disabled title="<?php esc_attr_e( 'WooCommerce is not installed or active on this site.', 'wpcalibrate-robots-repair' ); ?>">
				<span class="dashicons dashicons-cart"></span>
				<?php esc_html_e( 'WooCommerce Store Preset (Inactive)', 'wpcalibrate-robots-repair' ); ?>
			</button>
		<?php endif; ?>

		<button type="button" class="button button-link wpcrr-btn-reset-rules" data-original="<?php echo esc_attr( $raw_content ); ?>">
			<?php esc_html_e( 'Reset to Currently Active', 'wpcalibrate-robots-repair' ); ?>
		</button>
	</div>

	<!-- Editor Form -->
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-robots-repair&wcrr_tab=editor' ) ); ?>" id="wpcrr-editor-form">
		<?php wp_nonce_field( PageController::NONCE_ACTION, 'wcrr_nonce' ); ?>
		<input type="hidden" name="wcrr_action" value="save_editor" />

		<div class="wpcrr-editor-wrapper">
			<div class="wpcrr-editor-toolbar">
				<span class="wpcrr-editor-status-text" id="wpcrr-syntax-hint">
					<?php esc_html_e( 'Ready. Edit directives line by line.', 'wpcalibrate-robots-repair' ); ?>
				</span>
				<div class="wpcrr-toolbar-right">
					<button type="button" class="button button-small wpcrr-btn-copy" data-target="#wpcrr-editor-textarea">
						<?php esc_html_e( 'Copy to Clipboard', 'wpcalibrate-robots-repair' ); ?>
					</button>
				</div>
			</div>

			<div class="wpcrr-code-editor-area">
				<textarea name="robots_content" id="wpcrr-editor-textarea" class="wpcrr-textarea-code" rows="18" spellcheck="false"><?php echo esc_textarea( $raw_content ); ?></textarea>
			</div>

			<!-- Dynamic Live Warnings Box -->
			<div id="wpcrr-live-warning" class="wpcrr-editor-warning-box" style="display: none;">
				<span class="dashicons dashicons-warning"></span>
				<div class="wpcrr-warning-content">
					<strong><?php esc_html_e( 'High Risk Warning: Site-Wide Disallow Detected', 'wpcalibrate-robots-repair' ); ?></strong>
					<p><?php esc_html_e( 'You have "Disallow: /" under "User-agent: *". This will instruct all search crawlers not to crawl any content on your website.', 'wpcalibrate-robots-repair' ); ?></p>
				</div>
			</div>

			<!-- Dangerous Rule Confirmation Checkbox -->
			<div id="wpcrr-danger-confirm-row" class="wpcrr-confirm-box" style="display: none;">
				<label>
					<input type="checkbox" name="confirm_dangerous_save" value="1" />
					<strong><?php esc_html_e( 'I understand this Disallow: / directive blocks all search engines from crawling my website, and I explicitly confirm saving it.', 'wpcalibrate-robots-repair' ); ?></strong>
				</label>
			</div>

			<div class="wpcrr-editor-actions">
				<button type="submit" class="button button-primary button-large" id="wpcrr-btn-save-rules">
					<span class="dashicons dashicons-saved"></span>
					<?php esc_html_e( 'Save & Verify Public Response', 'wpcalibrate-robots-repair' ); ?>
				</button>
				<span class="description wpcrr-save-note">
					<?php esc_html_e( 'Saving will create an automatic backup, write changes, flush caches, and verify the live /robots.txt response.', 'wpcalibrate-robots-repair' ); ?>
				</span>
			</div>
		</div>
	</form>
</div>
