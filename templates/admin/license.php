<?php
/**
 * License Tab Template.
 *
 * @package WPCalibrate\RobotsRepair
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPCalibrate\RobotsRepair\Admin\PageController;
use WPCalibrate\RobotsRepair\License\UnconfiguredLicenseProvider;

$provider = new UnconfiguredLicenseProvider();
$details  = $provider->get_details();
?>

<div class="wpcrr-license-container">
	<div class="wpcrr-license-header">
		<div class="wpcrr-intro">
			<h2><?php esc_html_e( 'Plugin License & Subscription', 'wpcalibrate-robots-repair' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Manage your WPCalibrate Robots Repair license key for automated updates, pro features, and premium priority support.', 'wpcalibrate-robots-repair' ); ?>
			</p>
		</div>
	</div>

	<!-- Status Card -->
	<div class="wpcrr-license-card">
		<div class="wpcrr-license-status-badge">
			<span class="wpcrr-pill wpcrr-pill-secondary">
				<span class="dashicons dashicons-info"></span>
				<?php echo esc_html( $details['status_label'] ); ?>
			</span>
		</div>

		<div class="wpcrr-license-message">
			<p>
				<strong><?php esc_html_e( 'Licensing Service Status:', 'wpcalibrate-robots-repair' ); ?></strong>
				<?php echo esc_html( $details['message'] ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'You do not need an active license key to use the core diagnostic scanner, rule parser, presets, backups, or safe repair engine.', 'wpcalibrate-robots-repair' ); ?>
			</p>
		</div>

		<!-- License Activation Form -->
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-robots-repair&wcrr_tab=license' ) ); ?>" class="wpcrr-license-form">
			<?php wp_nonce_field( PageController::NONCE_ACTION, 'wcrr_nonce' ); ?>
			<input type="hidden" name="wcrr_action" value="save_license" />

			<div class="wpcrr-license-input-group">
				<label for="wpcrr-license-key"><strong><?php esc_html_e( 'License Key', 'wpcalibrate-robots-repair' ); ?></strong></label>
				<input
					type="password"
					name="license_key"
					id="wpcrr-license-key"
					class="regular-text code"
					placeholder="WPCRR-XXXX-XXXX-XXXX"
					value="<?php echo esc_attr( $details['masked_key'] ); ?>"
					autocomplete="off"
				/>
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save & Activate', 'wpcalibrate-robots-repair' ); ?>
				</button>
			</div>
		</form>

		<div class="wpcrr-license-meta-links">
			<p>
				<?php esc_html_e( 'Need a license or renewals?', 'wpcalibrate-robots-repair' ); ?>
				<a href="<?php echo esc_url( $details['renewal_url'] ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Visit WPCalibrate Marketplace', 'wpcalibrate-robots-repair' ); ?>
					<span class="dashicons dashicons-external"></span>
				</a>
			</p>
		</div>
	</div>
</div>
