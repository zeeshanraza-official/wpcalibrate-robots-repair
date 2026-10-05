<?php
/**
 * Settings Tab Template.
 *
 * @package WPCalibrate\RobotsRepair
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPCalibrate\RobotsRepair\Admin\PageController;
use WPCalibrate\RobotsRepair\Diagnostics\WooCommerceChecker;
use WPCalibrate\RobotsRepair\Settings\SettingsRepository;
use WPCalibrate\RobotsRepair\Support\Logger;

$settings = SettingsRepository::get_all();
$logs     = Logger::get_logs();
$is_wc    = WooCommerceChecker::is_active();
?>

<div class="wpcrr-settings-container">
	<div class="wpcrr-settings-header">
		<div class="wpcrr-intro">
			<h2><?php esc_html_e( 'Plugin Settings', 'wpcalibrate-robots-repair' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Configure robots.txt management behavior, backup retention limits, background monitoring, and diagnostic logs.', 'wpcalibrate-robots-repair' ); ?>
			</p>
		</div>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-robots-repair&wcrr_tab=settings' ) ); ?>" class="wpcrr-form">
		<?php wp_nonce_field( PageController::NONCE_ACTION, 'wcrr_nonce' ); ?>
		<input type="hidden" name="wcrr_action" value="save_settings" />

		<table class="form-table wpcrr-form-table" role="presentation">
			<tbody>
				<!-- Management Mode -->
				<tr>
					<th scope="row">
						<label for="wpcrr-mode"><?php esc_html_e( 'Default Preset Mode', 'wpcalibrate-robots-repair' ); ?></label>
					</th>
					<td>
						<select name="wpcrr_settings[management_mode]" id="wpcrr-mode" class="regular-text">
							<option value="recommended" <?php selected( $settings['management_mode'], 'recommended' ); ?>>
								<?php esc_html_e( 'Recommended WordPress (Conservative standard)', 'wpcalibrate-robots-repair' ); ?>
							</option>
							<option value="woocommerce" <?php selected( $settings['management_mode'], 'woocommerce' ); ?> <?php disabled( ! $is_wc ); ?>>
								<?php esc_html_e( 'WooCommerce Store Optimized', 'wpcalibrate-robots-repair' ); ?>
								<?php if ( ! $is_wc ) : ?> (<?php esc_html_e( 'Requires WooCommerce', 'wpcalibrate-robots-repair' ); ?>)<?php endif; ?>
							</option>
							<option value="custom" <?php selected( $settings['management_mode'], 'custom' ); ?>>
								<?php esc_html_e( 'Custom (Manual control)', 'wpcalibrate-robots-repair' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'The baseline rules template loaded when resetting or repairing your robots.txt.', 'wpcalibrate-robots-repair' ); ?>
						</p>
					</td>
				</tr>

				<!-- Auto Sitemap -->
				<tr>
					<th scope="row"><?php esc_html_e( 'XML Sitemap Integration', 'wpcalibrate-robots-repair' ); ?></th>
					<td>
						<fieldset>
							<label for="wpcrr-auto-sitemap">
								<input type="checkbox" name="wpcrr_settings[auto_sitemap]" id="wpcrr-auto-sitemap" value="1" <?php checked( $settings['auto_sitemap'], true ); ?> />
								<?php esc_html_e( 'Automatically include detected XML sitemap in robots.txt presets', 'wpcalibrate-robots-repair' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>

				<!-- Sitemap URL Override -->
				<tr>
					<th scope="row">
						<label for="wpcrr-sitemap-override"><?php esc_html_e( 'Sitemap URL Override (Optional)', 'wpcalibrate-robots-repair' ); ?></label>
					</th>
					<td>
						<input type="url" name="wpcrr_settings[sitemap_url_override]" id="wpcrr-sitemap-override" value="<?php echo esc_url( $settings['sitemap_url_override'] ); ?>" class="large-text" placeholder="https://example.com/sitemap_index.xml" />
						<p class="description">
							<?php esc_html_e( 'Leave blank to use auto-detected WordPress Core or SEO plugin sitemaps.', 'wpcalibrate-robots-repair' ); ?>
						</p>
					</td>
				</tr>

				<!-- Enable Backups -->
				<tr>
					<th scope="row"><?php esc_html_e( 'Automated Backups', 'wpcalibrate-robots-repair' ); ?></th>
					<td>
						<fieldset>
							<label for="wpcrr-enable-backups">
								<input type="checkbox" name="wpcrr_settings[enable_backups]" id="wpcrr-enable-backups" value="1" <?php checked( $settings['enable_backups'], true ); ?> />
								<?php esc_html_e( 'Create an automatic backup before any write operation or repair', 'wpcalibrate-robots-repair' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>

				<!-- Max Backups Retained -->
				<tr>
					<th scope="row">
						<label for="wpcrr-max-backups"><?php esc_html_e( 'Maximum Backups Retained', 'wpcalibrate-robots-repair' ); ?></label>
					</th>
					<td>
						<input type="number" name="wpcrr_settings[max_backups]" id="wpcrr-max-backups" value="<?php echo (int) $settings['max_backups']; ?>" min="1" max="50" class="small-text" />
						<p class="description">
							<?php esc_html_e( 'Older backups beyond this limit are automatically pruned (range: 1 - 50).', 'wpcalibrate-robots-repair' ); ?>
						</p>
					</td>
				</tr>

				<!-- Optional Scheduled Health Check -->
				<tr>
					<th scope="row"><?php esc_html_e( 'Scheduled Health Check', 'wpcalibrate-robots-repair' ); ?></th>
					<td>
						<fieldset>
							<label for="wpcrr-cron-monitor">
								<input type="checkbox" name="wpcrr_settings[enable_cron_monitor]" id="wpcrr-cron-monitor" value="1" <?php checked( $settings['enable_cron_monitor'], true ); ?> />
								<?php esc_html_e( 'Run a passive daily WP-Cron check to monitor public robots.txt availability', 'wpcalibrate-robots-repair' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'WP-Cron execution depends on site traffic unless a server cron triggers wp-cron.php. This background task never modifies your robots.txt file automatically.', 'wpcalibrate-robots-repair' ); ?>
							</p>
						</fieldset>
					</td>
				</tr>

				<!-- Diagnostic Logging -->
				<tr>
					<th scope="row"><?php esc_html_e( 'Diagnostic Logging', 'wpcalibrate-robots-repair' ); ?></th>
					<td>
						<fieldset>
							<label for="wpcrr-enable-logging">
								<input type="checkbox" name="wpcrr_settings[enable_logging]" id="wpcrr-enable-logging" value="1" <?php checked( $settings['enable_logging'], true ); ?> />
								<?php esc_html_e( 'Enable detailed diagnostic event logging (records up to 50 recent events)', 'wpcalibrate-robots-repair' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>

				<!-- Data Retention on Uninstall -->
				<tr>
					<th scope="row"><?php esc_html_e( 'Data Removal on Uninstall', 'wpcalibrate-robots-repair' ); ?></th>
					<td>
						<fieldset>
							<label for="wpcrr-delete-uninstall">
								<input type="checkbox" name="wpcrr_settings[delete_data_on_uninstall]" id="wpcrr-delete-uninstall" value="1" <?php checked( $settings['delete_data_on_uninstall'], true ); ?> />
								<span class="wpcrr-text-danger"><?php esc_html_e( 'Remove plugin options and backups upon uninstall', 'wpcalibrate-robots-repair' ); ?></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'By default, plugin settings and backups are preserved. Physical robots.txt files on the server are NEVER deleted during uninstall under any circumstances.', 'wpcalibrate-robots-repair' ); ?>
							</p>
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'wpcalibrate-robots-repair' ); ?>
			</button>
		</p>
	</form>

	<!-- Diagnostic Event Logs Section -->
	<?php if ( ! empty( $settings['enable_logging'] ) || ! empty( $logs ) ) : ?>
		<hr class="wpcrr-divider" />
		<div class="wpcrr-logs-section">
			<div class="wpcrr-logs-header">
				<h3><?php esc_html_e( 'Recent Diagnostic Logs', 'wpcalibrate-robots-repair' ); ?></h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-robots-repair&wcrr_tab=settings' ) ); ?>" class="wpcrr-inline-form">
					<?php wp_nonce_field( PageController::NONCE_ACTION, 'wcrr_nonce' ); ?>
					<input type="hidden" name="wcrr_action" value="clear_logs" />
					<button type="submit" class="button button-small button-secondary">
						<?php esc_html_e( 'Clear Logs', 'wpcalibrate-robots-repair' ); ?>
					</button>
				</form>
			</div>

			<?php if ( empty( $logs ) ) : ?>
				<p class="description"><?php esc_html_e( 'No log entries recorded yet.', 'wpcalibrate-robots-repair' ); ?></p>
			<?php else : ?>
				<div class="wpcrr-log-viewer">
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th style="width: 160px;"><?php esc_html_e( 'Time', 'wpcalibrate-robots-repair' ); ?></th>
								<th style="width: 90px;"><?php esc_html_e( 'Level', 'wpcalibrate-robots-repair' ); ?></th>
								<th><?php esc_html_e( 'Message', 'wpcalibrate-robots-repair' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $logs as $l ) : ?>
								<tr>
									<td><small><?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) ( $l['timestamp'] ?? 0 ) ) ); ?></small></td>
									<td>
										<span class="wpcrr-badge-log wpcrr-log-<?php echo esc_attr( $l['level'] ?? 'info' ); ?>">
											<?php echo esc_html( strtoupper( (string) ( $l['level'] ?? 'INFO' ) ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $l['message'] ?? '' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
