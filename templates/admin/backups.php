<?php
/**
 * Backups Tab Template.
 *
 * @package WPCalibrate\RobotsRepair
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPCalibrate\RobotsRepair\Admin\PageController;
use WPCalibrate\RobotsRepair\Backup\BackupRepository;

$backups = BackupRepository::get_all();
?>

<div class="wpcrr-backups-container">
	<div class="wpcrr-backups-header">
		<div class="wpcrr-intro">
			<h2><?php esc_html_e( 'Robots.txt Backups History', 'wpcalibrate-robots-repair' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Review and restore previous robots.txt versions created before automatic repairs, preset resets, or custom edits.', 'wpcalibrate-robots-repair' ); ?>
			</p>
		</div>
	</div>

	<?php if ( empty( $backups ) ) : ?>
		<div class="wpcrr-empty-state">
			<div class="wpcrr-empty-icon">
				<span class="dashicons dashicons-backup"></span>
			</div>
			<h3><?php esc_html_e( 'No Backups Stored Yet', 'wpcalibrate-robots-repair' ); ?></h3>
			<p><?php esc_html_e( 'A backup is automatically generated before any write operation or repair is applied to your robots.txt file.', 'wpcalibrate-robots-repair' ); ?></p>
		</div>
	<?php else : ?>
		<div class="wpcrr-table-responsive">
			<table class="wp-list-table widefat fixed striped wpcrr-backups-table">
				<thead>
					<tr>
						<th scope="col" class="manage-column column-date"><?php esc_html_e( 'Date & Time', 'wpcalibrate-robots-repair' ); ?></th>
						<th scope="col" class="manage-column column-source"><?php esc_html_e( 'Source Type', 'wpcalibrate-robots-repair' ); ?></th>
						<th scope="col" class="manage-column column-reason"><?php esc_html_e( 'Reason / Action', 'wpcalibrate-robots-repair' ); ?></th>
						<th scope="col" class="manage-column column-hash"><?php esc_html_e( 'Content Hash', 'wpcalibrate-robots-repair' ); ?></th>
						<th scope="col" class="manage-column column-size"><?php esc_html_e( 'Size', 'wpcalibrate-robots-repair' ); ?></th>
						<th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Actions', 'wpcalibrate-robots-repair' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $backups as $b ) :
						$id        = $b['id'] ?? '';
						$time      = $b['timestamp'] ?? 0;
						$source    = $b['source_type'] ?? 'virtual';
						$reason    = $b['reason'] ?? '';
						$hash      = substr( (string) ( $b['content_hash'] ?? '' ), 0, 12 ) . '...';
						$size      = size_format( (int) ( $b['size'] ?? strlen( (string) ( $b['content'] ?? '' ) ) ) );
						$content   = (string) ( $b['content'] ?? '' );
					?>
						<tr>
							<td>
								<strong><?php echo esc_html( wp_date( 'Y-m-d H:i:s', $time ) ); ?></strong>
								<br><small class="description"><?php printf( /* translators: %s: relative time */ esc_html__( '%s ago', 'wpcalibrate-robots-repair' ), esc_html( human_time_diff( $time, time() ) ) ); ?></small>
							</td>
							<td>
								<span class="wpcrr-badge-source wpcrr-source-<?php echo esc_attr( $source ); ?>">
									<?php echo esc_html( ucfirst( $source ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $reason ); ?></td>
							<td><code><?php echo esc_html( $hash ); ?></code></td>
							<td><?php echo esc_html( $size ); ?></td>
							<td class="column-actions wpcrr-row-actions">
								<div class="wpcrr-action-buttons">
									<!-- Preview Trigger -->
									<button type="button" class="button button-small wpcrr-btn-view-backup" data-content="<?php echo esc_attr( $content ); ?>" data-title="<?php echo esc_attr( wp_date( 'Y-m-d H:i:s', $time ) ); ?>">
										<span class="dashicons dashicons-visibility"></span>
										<?php esc_html_e( 'View', 'wpcalibrate-robots-repair' ); ?>
									</button>

									<!-- Restore Form -->
									<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-robots-repair&wcrr_tab=backups' ) ); ?>" class="wpcrr-inline-form">
										<?php wp_nonce_field( PageController::NONCE_ACTION, 'wcrr_nonce' ); ?>
										<input type="hidden" name="wcrr_action" value="restore_backup" />
										<input type="hidden" name="backup_id" value="<?php echo esc_attr( $id ); ?>" />
										<input type="hidden" name="confirm_restore" value="1" />
										<button type="submit" class="button button-small button-primary wpcrr-btn-restore" onclick="return confirm(wpcrrData.strings.confirmRestore);">
											<span class="dashicons dashicons-undo"></span>
											<?php esc_html_e( 'Restore', 'wpcalibrate-robots-repair' ); ?>
										</button>
									</form>

									<!-- Delete Form -->
									<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-robots-repair&wcrr_tab=backups' ) ); ?>" class="wpcrr-inline-form">
										<?php wp_nonce_field( PageController::NONCE_ACTION, 'wcrr_nonce' ); ?>
										<input type="hidden" name="wcrr_action" value="delete_backup" />
										<input type="hidden" name="backup_id" value="<?php echo esc_attr( $id ); ?>" />
										<button type="submit" class="button button-small wpcrr-btn-delete-row" title="<?php esc_attr_e( 'Delete Backup', 'wpcalibrate-robots-repair' ); ?>" onclick="return confirm(wpcrrData.strings.confirmDelete);">
											<span class="dashicons dashicons-trash"></span>
										</button>
									</form>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- Backup Content Modal Viewer -->
		<div id="wpcrr-backup-modal" class="wpcrr-modal-backdrop" style="display: none;">
			<div class="wpcrr-modal-dialog">
				<div class="wpcrr-modal-header">
					<h3 id="wpcrr-modal-title"><?php esc_html_e( 'Backup Content', 'wpcalibrate-robots-repair' ); ?></h3>
					<button type="button" class="wpcrr-modal-close" aria-label="<?php esc_attr_e( 'Close', 'wpcalibrate-robots-repair' ); ?>">&times;</button>
				</div>
				<div class="wpcrr-modal-body">
					<pre id="wpcrr-modal-content" class="wpcrr-code-viewer"></pre>
				</div>
				<div class="wpcrr-modal-footer">
					<button type="button" class="button button-secondary wpcrr-modal-close"><?php esc_html_e( 'Close', 'wpcalibrate-robots-repair' ); ?></button>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>
