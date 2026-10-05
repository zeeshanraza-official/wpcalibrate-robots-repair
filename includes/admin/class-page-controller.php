<?php
/**
 * Master Admin Page Controller.
 *
 * @package WPCalibrate\RobotsRepair
 */

declare(strict_types=1);

namespace WPCalibrate\RobotsRepair\Admin;

use WPCalibrate\RobotsRepair\Backup\BackupRepository;
use WPCalibrate\RobotsRepair\Backup\RestoreManager;
use WPCalibrate\RobotsRepair\Diagnostics\PhysicalFileDetector;
use WPCalibrate\RobotsRepair\Diagnostics\RuleParser;
use WPCalibrate\RobotsRepair\Diagnostics\Scanner;
use WPCalibrate\RobotsRepair\Diagnostics\Validator;
use WPCalibrate\RobotsRepair\Diagnostics\WooCommerceChecker;
use WPCalibrate\RobotsRepair\License\UnconfiguredLicenseProvider;
use WPCalibrate\RobotsRepair\Repair\PresetFactory;
use WPCalibrate\RobotsRepair\Repair\RepairManager;
use WPCalibrate\RobotsRepair\Repair\RepairPreview;
use WPCalibrate\RobotsRepair\Repair\VirtualRobotsManager;
use WPCalibrate\RobotsRepair\Settings\SettingsRepository;
use WPCalibrate\RobotsRepair\Support\Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PageController
 *
 * Handles routing, security validation, form submissions, and template rendering.
 */
final class PageController {

	public const NONCE_ACTION = 'wpcalibrate_robots_repair_action';
	public const TAB_PARAM    = 'wcrr_tab';

	/**
	 * Screen hook suffix.
	 */
	private string $hook_suffix = '';

	/**
	 * Notices to display on current screen.
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $notices = [];

	/**
	 * Set hook suffix.
	 */
	public function set_hook_suffix( string $suffix ): void {
		$this->hook_suffix = $suffix;
	}

	/**
	 * Register admin_init to process POST requests before header output.
	 */
	public function init(): void {
		add_action( 'admin_init', [ $this, 'handle_form_submission' ] );
	}

	/**
	 * Handle state-changing actions.
	 */
	public function handle_form_submission(): void {
		// Verify we are on this plugin's screen.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'wpcalibrate-robots-repair' !== $page ) {
			return;
		}

		if ( empty( $_POST['wcrr_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$capability = apply_filters( 'wpcalibrate_robots_repair_capability', 'manage_options' );
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'Unauthorized: You lack permissions to modify robots.txt settings.', 'wpcalibrate-robots-repair' ) );
		}

		check_admin_referer( self::NONCE_ACTION, 'wcrr_nonce' );

		$action = sanitize_key( wp_unslash( $_POST['wcrr_action'] ) );

		switch ( $action ) {
			case 'scan':
				Scanner::run( true );
				$this->redirect_with_notice( 'scan_done', __( 'robots.txt diagnostic scan completed successfully.', 'wpcalibrate-robots-repair' ), 'success', 'overview' );
				break;

			case 'apply_repair':
				$repair_action = isset( $_POST['repair_action_id'] ) ? sanitize_key( wp_unslash( $_POST['repair_action_id'] ) ) : '';
				$confirmed     = ! empty( $_POST['confirm_repair'] );

				if ( ! $confirmed ) {
					$this->redirect_with_notice( 'repair_err', __( 'Repair cancelled: explicit confirmation was not checked.', 'wpcalibrate-robots-repair' ), 'error', 'diagnostics' );
					return;
				}

				$repair_res = RepairManager::execute_repair( $repair_action, [], true );

				if ( $repair_res['success'] ) {
					$msg = sprintf(
						/* translators: 1: status, 2: note */
						__( 'Repair applied! Public Verification: %1$s. %2$s', 'wpcalibrate-robots-repair' ),
						$repair_res['verification_status'],
						$repair_res['verification_note'] ?? ''
					);
					$type = $repair_res['is_verified'] ? 'success' : 'warning';
					$this->redirect_with_notice( 'repair_done', $msg, $type, 'overview' );
				} else {
					$msg = sprintf(
						/* translators: %s: error */
						__( 'Repair could not be completed: %s', 'wpcalibrate-robots-repair' ),
						$repair_res['error'] ?? __( 'Unknown error', 'wpcalibrate-robots-repair' )
					);
					$this->redirect_with_notice( 'repair_err', $msg, 'error', 'diagnostics' );
				}
				break;

			case 'save_editor':
				$content   = isset( $_POST['robots_content'] ) ? wp_unslash( (string) $_POST['robots_content'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$confirmed = ! empty( $_POST['confirm_dangerous_save'] );

				// Validate syntax and dangerous directives.
				$parser = new RuleParser( $content );
				if ( $parser->has_global_disallow() && ! $confirmed ) {
					$this->redirect_with_notice(
						'editor_danger',
						__( 'Action halted: your content contains "Disallow: /" under "User-agent: *", which blocks all search engines from crawling your site. Please check the confirmation checkbox if this is intentional.', 'wpcalibrate-robots-repair' ),
						'error',
						'editor'
					);
					return;
				}

				$repair_res = RepairManager::execute_repair( 'save_custom_content', [ 'content' => $content ], true );

				if ( $repair_res['success'] ) {
					$msg = sprintf(
						/* translators: 1: status, 2: note */
						__( 'Rules saved! Public Verification: %1$s. %2$s', 'wpcalibrate-robots-repair' ),
						$repair_res['verification_status'],
						$repair_res['verification_note'] ?? ''
					);
					$type = $repair_res['is_verified'] ? 'success' : 'warning';
					$this->redirect_with_notice( 'editor_saved', $msg, $type, 'editor' );
				} else {
					$this->redirect_with_notice( 'editor_err', $repair_res['error'], 'error', 'editor' );
				}
				break;

			case 'restore_backup':
				$backup_id = isset( $_POST['backup_id'] ) ? sanitize_text_field( wp_unslash( $_POST['backup_id'] ) ) : '';
				$confirmed = ! empty( $_POST['confirm_restore'] );

				if ( ! $confirmed ) {
					$this->redirect_with_notice( 'restore_err', __( 'Restore requires explicit confirmation.', 'wpcalibrate-robots-repair' ), 'error', 'backups' );
					return;
				}

				$restore_res = RestoreManager::restore( $backup_id, true );

				if ( $restore_res['success'] ) {
					$msg = sprintf(
						/* translators: %s: status */
						__( 'Backup successfully restored! Public Verification: %s', 'wpcalibrate-robots-repair' ),
						$restore_res['verification_status']
					);
					$type = $restore_res['verified'] ? 'success' : 'warning';
					$this->redirect_with_notice( 'restored', $msg, $type, 'backups' );
				} else {
					$this->redirect_with_notice( 'restore_err', $restore_res['error'], 'error', 'backups' );
				}
				break;

			case 'delete_backup':
				$backup_id = isset( $_POST['backup_id'] ) ? sanitize_text_field( wp_unslash( $_POST['backup_id'] ) ) : '';
				BackupRepository::delete( $backup_id );
				$this->redirect_with_notice( 'deleted', __( 'Backup record removed.', 'wpcalibrate-robots-repair' ), 'success', 'backups' );
				break;

			case 'save_settings':
				$raw_settings = isset( $_POST['wpcrr_settings'] ) && is_array( $_POST['wpcrr_settings'] ) ? wp_unslash( $_POST['wpcrr_settings'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				SettingsRepository::update_all( $raw_settings );

				// Synchronize cron schedule if setting changed.
				\WPCalibrate\RobotsRepair\Diagnostics\CronMonitor::sync_schedule();

				$this->redirect_with_notice( 'settings_saved', __( 'Plugin settings updated successfully.', 'wpcalibrate-robots-repair' ), 'success', 'settings' );
				break;

			case 'save_license':
				$key      = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
				$provider = new UnconfiguredLicenseProvider();
				$res      = $provider->activate( $key );

				$this->redirect_with_notice( 'license_info', $res['message'], 'info', 'license' );
				break;

			case 'clear_logs':
				Logger::clear_logs();
				$this->redirect_with_notice( 'logs_cleared', __( 'Diagnostic log entries cleared.', 'wpcalibrate-robots-repair' ), 'success', 'settings' );
				break;
		}
	}

	/**
	 * Render the complete admin screen with tabs.
	 */
	public function render(): void {
		$capability = apply_filters( 'wpcalibrate_robots_repair_capability', 'manage_options' );
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wpcalibrate-robots-repair' ) );
		}

		$current_tab = isset( $_GET[ self::TAB_PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::TAB_PARAM ] ) ) : 'overview';
		$valid_tabs  = [ 'overview', 'diagnostics', 'editor', 'backups', 'settings', 'license', 'support' ];

		if ( ! in_array( $current_tab, $valid_tabs, true ) ) {
			$current_tab = 'overview';
		}

		// Retrieve latest scan data.
		$scan_data = Scanner::get_last_scan_or_run();

		// Handle transient flash notices.
		$notice_msg  = get_transient( 'wpcrr_notice_msg' );
		$notice_type = get_transient( 'wpcrr_notice_type' ) ?: 'info';
		if ( $notice_msg ) {
			delete_transient( 'wpcrr_notice_msg' );
			delete_transient( 'wpcrr_notice_type' );
			$this->notices[] = [
				'message' => (string) $notice_msg,
				'type'    => (string) $notice_type,
			];
		}

		$branding_dark  = WPCRR_PLUGIN_URL . 'assets/branding/icon-dark.png';
		$branding_white = WPCRR_PLUGIN_URL . 'assets/branding/icon-white.png';

		?>
		<div class="wrap wpcrr-wrap">
			<!-- Header -->
			<header class="wpcrr-header">
				<div class="wpcrr-header-brand">
					<img src="<?php echo esc_url( $branding_dark ); ?>" alt="WPCalibrate" class="wpcrr-brand-icon" />
					<div class="wpcrr-header-text">
						<h1 class="wpcrr-title"><?php esc_html_e( 'WPCalibrate Robots Repair', 'wpcalibrate-robots-repair' ); ?></h1>
						<span class="wpcrr-version-badge">v<?php echo esc_html( WPCRR_VERSION ); ?></span>
					</div>
				</div>
				<div class="wpcrr-header-actions">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-robots-repair&wcrr_tab=overview' ) ); ?>">
						<?php wp_nonce_field( self::NONCE_ACTION, 'wcrr_nonce' ); ?>
						<input type="hidden" name="wcrr_action" value="scan" />
						<button type="submit" class="button button-primary wpcrr-btn-scan">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Scan Robots.txt', 'wpcalibrate-robots-repair' ); ?>
						</button>
					</form>
				</div>
			</header>

			<!-- Admin Notices -->
			<?php foreach ( $this->notices as $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible wpcrr-notice">
					<p><?php echo esc_html( $notice['message'] ); ?></p>
				</div>
			<?php endforeach; ?>

			<!-- Navigation Tabs -->
			<nav class="nav-tab-wrapper wpcrr-tabs-nav" aria-label="<?php esc_attr_e( 'Plugin Tabs', 'wpcalibrate-robots-repair' ); ?>">
				<a href="<?php echo esc_url( $this->get_tab_url( 'overview' ) ); ?>" class="nav-tab <?php echo 'overview' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-dashboard"></span>
					<?php esc_html_e( 'Overview', 'wpcalibrate-robots-repair' ); ?>
				</a>
				<a href="<?php echo esc_url( $this->get_tab_url( 'diagnostics' ) ); ?>" class="nav-tab <?php echo 'diagnostics' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-shield"></span>
					<?php esc_html_e( 'Diagnostics', 'wpcalibrate-robots-repair' ); ?>
					<?php
					$findings_count = count( $scan_data['findings'] ?? [] );
					if ( $findings_count > 0 ) :
						?>
						<span class="wpcrr-badge-count wpcrr-badge-<?php echo esc_attr( $scan_data['overall_status'] ?? 'info' ); ?>"><?php echo (int) $findings_count; ?></span>
					<?php endif; ?>
				</a>
				<a href="<?php echo esc_url( $this->get_tab_url( 'editor' ) ); ?>" class="nav-tab <?php echo 'editor' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-edit"></span>
					<?php esc_html_e( 'Editor & Presets', 'wpcalibrate-robots-repair' ); ?>
				</a>
				<a href="<?php echo esc_url( $this->get_tab_url( 'backups' ) ); ?>" class="nav-tab <?php echo 'backups' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-backup"></span>
					<?php esc_html_e( 'Backups', 'wpcalibrate-robots-repair' ); ?>
				</a>
				<a href="<?php echo esc_url( $this->get_tab_url( 'settings' ) ); ?>" class="nav-tab <?php echo 'settings' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-admin-settings"></span>
					<?php esc_html_e( 'Settings', 'wpcalibrate-robots-repair' ); ?>
				</a>
				<a href="<?php echo esc_url( $this->get_tab_url( 'license' ) ); ?>" class="nav-tab <?php echo 'license' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-awards"></span>
					<?php esc_html_e( 'License', 'wpcalibrate-robots-repair' ); ?>
				</a>
				<a href="<?php echo esc_url( $this->get_tab_url( 'support' ) ); ?>" class="nav-tab <?php echo 'support' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-sos"></span>
					<?php esc_html_e( 'Support', 'wpcalibrate-robots-repair' ); ?>
				</a>
			</nav>

			<!-- Tab Content View -->
			<main class="wpcrr-tab-content">
				<?php
				$template_file = WPCRR_PLUGIN_DIR . 'templates/admin/' . $current_tab . '.php';
				if ( file_exists( $template_file ) ) {
					include $template_file;
				} else {
					echo '<p>' . esc_html__( 'Template not found.', 'wpcalibrate-robots-repair' ) . '</p>';
				}
				?>
			</main>
		</div>
		<?php
	}

	/**
	 * Build URL for a specific tab without colliding with other plugins.
	 */
	public function get_tab_url( string $tab ): string {
		return add_query_arg(
			[
				'page'           => 'wpcalibrate-robots-repair',
				self::TAB_PARAM  => sanitize_key( $tab ),
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Helper for redirect with transient flash notice.
	 */
	private function redirect_with_notice( string $key, string $message, string $type = 'info', string $tab = 'overview' ): void {
		set_transient( 'wpcrr_notice_msg', $message, 45 );
		set_transient( 'wpcrr_notice_type', $type, 45 );

		$url = $this->get_tab_url( $tab );
		wp_safe_redirect( $url );
		exit;
	}
}
