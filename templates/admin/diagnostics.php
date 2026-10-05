<?php
/**
 * Diagnostics Tab Template.
 *
 * @package WPCalibrate\RobotsRepair
 *
 * @var array<string, mixed> $scan_data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPCalibrate\RobotsRepair\Admin\PageController;

$findings = $scan_data['findings'] ?? [];
$status   = $scan_data['overall_status'] ?? 'healthy';
?>

<div class="wpcrr-diagnostics-container">
	<div class="wpcrr-diagnostics-header">
		<div class="wpcrr-intro">
			<h2><?php esc_html_e( 'Robots.txt Diagnostic Findings', 'wpcalibrate-robots-repair' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Review each identified crawl-control condition, understand its search engine impact, and apply safe repairs with before/after verification.', 'wpcalibrate-robots-repair' ); ?>
			</p>
		</div>
		<div class="wpcrr-header-actions">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-robots-repair&wcrr_tab=diagnostics' ) ); ?>">
				<?php wp_nonce_field( PageController::NONCE_ACTION, 'wcrr_nonce' ); ?>
				<input type="hidden" name="wcrr_action" value="scan" />
				<button type="submit" class="button button-secondary">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Re-Scan Now', 'wpcalibrate-robots-repair' ); ?>
				</button>
			</form>
		</div>
	</div>

	<?php if ( empty( $findings ) ) : ?>
		<div class="wpcrr-empty-state">
			<div class="wpcrr-empty-icon">
				<span class="dashicons dashicons-yes-alt"></span>
			</div>
			<h3><?php esc_html_e( 'No Issues Detected!', 'wpcalibrate-robots-repair' ); ?></h3>
			<p><?php esc_html_e( 'Your robots.txt syntax is valid, reachable, and contains standard directives without dangerous blocks or broken sitemaps.', 'wpcalibrate-robots-repair' ); ?></p>
			<div class="wpcrr-notice-disclaimer">
				<em><?php esc_html_e( 'Note: A "Healthy" robots.txt status means crawlers can access your site without configuration errors; it does not guarantee ranking positions or deindexing.', 'wpcalibrate-robots-repair' ); ?></em>
			</div>
		</div>
	<?php else : ?>
		<div class="wpcrr-findings-list">
			<?php foreach ( $findings as $finding ) :
				$sev = $finding['severity'] ?? 'info';
				$sev_classes = [
					'critical' => 'wpcrr-finding-critical',
					'warning'  => 'wpcrr-finding-warning',
					'info'     => 'wpcrr-finding-info',
				];
				$sev_badge = [
					'critical' => __( 'Critical', 'wpcalibrate-robots-repair' ),
					'warning'  => __( 'Warning', 'wpcalibrate-robots-repair' ),
					'info'     => __( 'Notice', 'wpcalibrate-robots-repair' ),
				];
			?>
				<div class="wpcrr-finding-card <?php echo esc_attr( $sev_classes[ $sev ] ?? '' ); ?>" id="finding-<?php echo esc_attr( $finding['id'] ); ?>">
					<div class="wpcrr-finding-head">
						<div class="wpcrr-finding-title-row">
							<span class="wpcrr-severity-pill wpcrr-sev-<?php echo esc_attr( $sev ); ?>">
								<?php echo esc_html( $sev_badge[ $sev ] ?? $sev ); ?>
							</span>
							<h3 class="wpcrr-finding-title"><?php echo esc_html( $finding['title'] ); ?></h3>
						</div>
					</div>

					<div class="wpcrr-finding-body">
						<div class="wpcrr-finding-section">
							<span class="wpcrr-finding-label"><?php esc_html_e( 'Explanation:', 'wpcalibrate-robots-repair' ); ?></span>
							<p class="wpcrr-finding-text"><?php echo esc_html( $finding['explanation'] ); ?></p>
						</div>

						<div class="wpcrr-finding-grid">
							<div class="wpcrr-finding-col">
								<span class="wpcrr-finding-label"><?php esc_html_e( 'Evidence:', 'wpcalibrate-robots-repair' ); ?></span>
								<code class="wpcrr-evidence-box"><?php echo esc_html( $finding['evidence'] ); ?></code>
							</div>
							<div class="wpcrr-finding-col">
								<span class="wpcrr-finding-label"><?php esc_html_e( 'Likely Cause:', 'wpcalibrate-robots-repair' ); ?></span>
								<p class="wpcrr-finding-subtext"><?php echo esc_html( $finding['likely_cause'] ); ?></p>
							</div>
						</div>

						<div class="wpcrr-finding-section wpcrr-recommendation-box">
							<span class="wpcrr-finding-label"><?php esc_html_e( 'Recommended Action:', 'wpcalibrate-robots-repair' ); ?></span>
							<p><?php echo esc_html( $finding['recommended_action'] ); ?></p>
						</div>
					</div>

					<?php if ( ! empty( $finding['can_auto_repair'] ) && ! empty( $finding['repair_action'] ) ) : ?>
						<div class="wpcrr-finding-footer">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-robots-repair&wcrr_tab=diagnostics' ) ); ?>" class="wpcrr-repair-form">
								<?php wp_nonce_field( PageController::NONCE_ACTION, 'wcrr_nonce' ); ?>
								<input type="hidden" name="wcrr_action" value="apply_repair" />
								<input type="hidden" name="repair_action_id" value="<?php echo esc_attr( $finding['repair_action'] ); ?>" />
								
								<label class="wpcrr-confirm-label">
									<input type="checkbox" name="confirm_repair" value="1" required />
									<?php esc_html_e( 'I confirm this repair (a backup of current rules will be saved before writing).', 'wpcalibrate-robots-repair' ); ?>
								</label>

								<button type="submit" class="button button-primary wpcrr-btn-repair">
									<span class="dashicons dashicons-hammer"></span>
									<?php esc_html_e( 'Apply Safe Repair', 'wpcalibrate-robots-repair' ); ?>
								</button>
							</form>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
