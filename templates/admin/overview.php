<?php
/**
 * Overview Tab Template.
 *
 * @package WPCalibrate\RobotsRepair
 *
 * @var array<string, mixed> $scan_data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status       = $scan_data['overall_status'] ?? 'healthy';
$public_info  = $scan_data['public_info'] ?? [];
$source_info  = $scan_data['source_info'] ?? [];
$vis_info     = $scan_data['visibility_info'] ?? [];
$sitemap_info = $scan_data['sitemap_info'] ?? [];
$findings     = $scan_data['findings'] ?? [];
$timestamp    = $scan_data['timestamp'] ?? time();

$status_labels = [
	'healthy'  => __( 'Healthy', 'wpcalibrate-robots-repair' ),
	'warning'  => __( 'Warning', 'wpcalibrate-robots-repair' ),
	'critical' => __( 'Critical', 'wpcalibrate-robots-repair' ),
];
$status_label  = $status_labels[ $status ] ?? __( 'Unknown', 'wpcalibrate-robots-repair' );

$critical_count = 0;
$warning_count  = 0;
foreach ( $findings as $f ) {
	if ( 'critical' === ( $f['severity'] ?? '' ) ) {
		$critical_count++;
	} elseif ( 'warning' === ( $f['severity'] ?? '' ) ) {
		$warning_count++;
	}
}
?>

<div class="wpcrr-overview-container">
	<!-- Top Metric Cards Grid -->
	<div class="wpcrr-cards-grid">
		<!-- Overall Health Status Card -->
		<div class="wpcrr-card wpcrr-card-status wpcrr-status-<?php echo esc_attr( $status ); ?>">
			<div class="wpcrr-card-icon">
				<?php if ( 'healthy' === $status ) : ?>
					<span class="dashicons dashicons-yes-alt"></span>
				<?php elseif ( 'warning' === $status ) : ?>
					<span class="dashicons dashicons-warning"></span>
				<?php else : ?>
					<span class="dashicons dashicons-dismiss"></span>
				<?php endif; ?>
			</div>
			<div class="wpcrr-card-body">
				<h3 class="wpcrr-card-label"><?php esc_html_e( 'Crawl Health Status', 'wpcalibrate-robots-repair' ); ?></h3>
				<div class="wpcrr-card-value"><?php echo esc_html( $status_label ); ?></div>
				<p class="wpcrr-card-meta">
					<?php
					printf(
						/* translators: 1: critical count, 2: warning count */
						esc_html__( '%1$d critical, %2$d warning(s)', 'wpcalibrate-robots-repair' ),
						$critical_count,
						$warning_count
					);
					?>
				</p>
			</div>
		</div>

		<!-- Public Endpoint Card -->
		<div class="wpcrr-card">
			<div class="wpcrr-card-icon">
				<span class="dashicons dashicons-admin-links"></span>
			</div>
			<div class="wpcrr-card-body">
				<h3 class="wpcrr-card-label"><?php esc_html_e( 'Public Endpoint', 'wpcalibrate-robots-repair' ); ?></h3>
				<div class="wpcrr-card-value">
					<?php if ( 200 === ( $public_info['http_status'] ?? 0 ) ) : ?>
						<span class="wpcrr-pill wpcrr-pill-success">HTTP 200 OK</span>
					<?php else : ?>
						<span class="wpcrr-pill wpcrr-pill-error">HTTP <?php echo (int) ( $public_info['http_status'] ?? 0 ); ?></span>
					<?php endif; ?>
				</div>
				<p class="wpcrr-card-meta">
					<a href="<?php echo esc_url( $public_info['url'] ?? home_url( '/robots.txt' ) ); ?>" target="_blank" rel="noopener noreferrer" class="wpcrr-link-external">
						<?php echo esc_html( wp_parse_url( $public_info['url'] ?? '', PHP_URL_PATH ) ?: '/robots.txt' ); ?>
						<span class="dashicons dashicons-external"></span>
					</a>
					(<?php echo (int) ( $public_info['duration_ms'] ?? 0 ); ?>ms)
				</p>
			</div>
		</div>

		<!-- Detected Source Card -->
		<div class="wpcrr-card">
			<div class="wpcrr-card-icon">
				<span class="dashicons dashicons-category"></span>
			</div>
			<div class="wpcrr-card-body">
				<h3 class="wpcrr-card-label"><?php esc_html_e( 'Output Source', 'wpcalibrate-robots-repair' ); ?></h3>
				<div class="wpcrr-card-value-sm"><?php echo esc_html( $source_info['label'] ?? __( 'Unknown', 'wpcalibrate-robots-repair' ) ); ?></div>
				<p class="wpcrr-card-meta">
					<?php if ( ! empty( $source_info['cdn_detected'] ) ) : ?>
						<span class="wpcrr-tag-cdn"><?php esc_html_e( 'CDN/WAF Active', 'wpcalibrate-robots-repair' ); ?></span>
					<?php else : ?>
						<?php esc_html_e( 'Direct Origin', 'wpcalibrate-robots-repair' ); ?>
					<?php endif; ?>
				</p>
			</div>
		</div>

		<!-- Search Visibility Card -->
		<div class="wpcrr-card">
			<div class="wpcrr-card-icon">
				<span class="dashicons dashicons-visibility"></span>
			</div>
			<div class="wpcrr-card-body">
				<h3 class="wpcrr-card-label"><?php esc_html_e( 'Search Engine Visibility', 'wpcalibrate-robots-repair' ); ?></h3>
				<div class="wpcrr-card-value">
					<?php if ( ! empty( $vis_info['is_discouraged'] ) ) : ?>
						<span class="wpcrr-pill wpcrr-pill-warning"><?php esc_html_e( 'Discouraged', 'wpcalibrate-robots-repair' ); ?></span>
					<?php else : ?>
						<span class="wpcrr-pill wpcrr-pill-success"><?php esc_html_e( 'Public', 'wpcalibrate-robots-repair' ); ?></span>
					<?php endif; ?>
				</div>
				<p class="wpcrr-card-meta">
					<a href="<?php echo esc_url( admin_url( 'options-reading.php' ) ); ?>"><?php esc_html_e( 'Settings → Reading', 'wpcalibrate-robots-repair' ); ?></a>
				</p>
			</div>
		</div>
	</div>

	<!-- Critical Issues Alert Banner (if any) -->
	<?php if ( $critical_count > 0 ) : ?>
		<div class="wpcrr-alert-banner wpcrr-alert-critical" role="alert">
			<div class="wpcrr-alert-icon">
				<span class="dashicons dashicons-warning"></span>
			</div>
			<div class="wpcrr-alert-content">
				<h4><?php esc_html_e( 'Critical Crawl Problem Detected!', 'wpcalibrate-robots-repair' ); ?></h4>
				<p><?php esc_html_e( 'Your website has critical robots.txt issues that may be preventing search engines from crawling normal web pages or serving an invalid error response.', 'wpcalibrate-robots-repair' ); ?></p>
			</div>
			<div class="wpcrr-alert-action">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-robots-repair&wcrr_tab=diagnostics' ) ); ?>" class="button button-primary button-hero">
					<?php esc_html_e( 'Review & Repair Findings', 'wpcalibrate-robots-repair' ); ?>
				</a>
			</div>
		</div>
	<?php endif; ?>

	<!-- Split Panel: Live Preview & Inspection Summary -->
	<div class="wpcrr-panels-split">
		<!-- Left Panel: Live robots.txt Output -->
		<div class="wpcrr-panel wpcrr-panel-preview">
			<div class="wpcrr-panel-header">
				<h3>
					<span class="dashicons dashicons-media-text"></span>
					<?php esc_html_e( 'Public /robots.txt Content', 'wpcalibrate-robots-repair' ); ?>
				</h3>
				<div class="wpcrr-panel-actions">
					<button type="button" class="button button-secondary wpcrr-btn-copy" data-target="#wpcrr-public-raw">
						<span class="dashicons dashicons-admin-page"></span>
						<?php esc_html_e( 'Copy Content', 'wpcalibrate-robots-repair' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-robots-repair&wcrr_tab=editor' ) ); ?>" class="button button-secondary">
						<span class="dashicons dashicons-edit"></span>
						<?php esc_html_e( 'Edit Rules', 'wpcalibrate-robots-repair' ); ?>
					</a>
				</div>
			</div>
			<div class="wpcrr-panel-body">
				<textarea id="wpcrr-public-raw" class="wpcrr-code-viewer" readonly rows="14"><?php echo esc_textarea( $scan_data['raw_content'] ?? '' ); ?></textarea>
			</div>
			<div class="wpcrr-panel-footer">
				<span class="wpcrr-timestamp">
					<?php
					printf(
						/* translators: %s: relative time */
						esc_html__( 'Last scanned %s ago', 'wpcalibrate-robots-repair' ),
						human_time_diff( $timestamp, time() )
					);
					?>
				</span>
			</div>
		</div>

		<!-- Right Panel: Environment & Sitemap State -->
		<div class="wpcrr-panel wpcrr-panel-details">
			<div class="wpcrr-panel-header">
				<h3>
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'Crawl Environment Summary', 'wpcalibrate-robots-repair' ); ?>
				</h3>
			</div>
			<div class="wpcrr-panel-body">
				<ul class="wpcrr-summary-list">
					<li>
						<strong><?php esc_html_e( 'Canonical robots.txt URL:', 'wpcalibrate-robots-repair' ); ?></strong>
						<code><?php echo esc_html( $public_info['url'] ?? home_url( '/robots.txt' ) ); ?></code>
					</li>
					<li>
						<strong><?php esc_html_e( 'Active Output Source:', 'wpcalibrate-robots-repair' ); ?></strong>
						<span><?php echo esc_html( $source_info['evidence'] ?? '' ); ?></span>
					</li>
					<li>
						<strong><?php esc_html_e( 'Physical File on Disk:', 'wpcalibrate-robots-repair' ); ?></strong>
						<?php if ( ! empty( $scan_data['physical_info']['exists'] ) ) : ?>
							<span class="wpcrr-text-warning">
								<?php esc_html_e( 'Present (takes precedence over WordPress virtual output)', 'wpcalibrate-robots-repair' ); ?>
								(<?php echo esc_html( $scan_data['physical_info']['permissions'] ?? '' ); ?>)
							</span>
						<?php else : ?>
							<span class="wpcrr-text-success"><?php esc_html_e( 'None (managed cleanly via WordPress virtual engine)', 'wpcalibrate-robots-repair' ); ?></span>
						<?php endif; ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Primary XML Sitemap:', 'wpcalibrate-robots-repair' ); ?></strong>
						<?php if ( ! empty( $sitemap_info['primary_sitemap'] ) ) : ?>
							<a href="<?php echo esc_url( $sitemap_info['primary_sitemap'] ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( $sitemap_info['primary_sitemap'] ); ?>
								<span class="dashicons dashicons-external"></span>
							</a>
						<?php else : ?>
							<span class="wpcrr-text-warning"><?php esc_html_e( 'No reachable sitemap detected', 'wpcalibrate-robots-repair' ); ?></span>
						<?php endif; ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'SEO Plugins Detected:', 'wpcalibrate-robots-repair' ); ?></strong>
						<?php if ( ! empty( $scan_data['seo_info']['has_seo_plugin'] ) ) : ?>
							<span><?php echo esc_html( implode( ', ', array_column( $scan_data['seo_info']['seo_plugins'], 'name' ) ) ); ?></span>
						<?php else : ?>
							<span><?php esc_html_e( 'None', 'wpcalibrate-robots-repair' ); ?></span>
						<?php endif; ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'WooCommerce Integration:', 'wpcalibrate-robots-repair' ); ?></strong>
						<?php if ( \WPCalibrate\RobotsRepair\Diagnostics\WooCommerceChecker::is_active() ) : ?>
							<span class="wpcrr-text-success"><?php esc_html_e( 'Active (WooCommerce Preset available)', 'wpcalibrate-robots-repair' ); ?></span>
						<?php else : ?>
							<span><?php esc_html_e( 'Not installed / inactive', 'wpcalibrate-robots-repair' ); ?></span>
						<?php endif; ?>
					</li>
				</ul>
			</div>
			<div class="wpcrr-panel-footer">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-robots-repair&wcrr_tab=diagnostics' ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'View All Findings & Explanations →', 'wpcalibrate-robots-repair' ); ?>
				</a>
			</div>
		</div>
	</div>
</div>
