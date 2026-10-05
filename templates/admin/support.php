<?php
/**
 * Support Tab Template.
 *
 * @package WPCalibrate\RobotsRepair
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$branding_dark = WPCRR_PLUGIN_URL . 'assets/branding/icon-dark.png';
?>

<div class="wpcrr-support-container">
	<div class="wpcrr-support-header">
		<div class="wpcrr-intro">
			<h2><?php esc_html_e( 'Help, Documentation & Support', 'wpcalibrate-robots-repair' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Get expert guidance on managing WordPress robots.txt, understanding crawler instructions, and contacting the WPCalibrate team.', 'wpcalibrate-robots-repair' ); ?>
			</p>
		</div>
	</div>

	<div class="wpcrr-support-grid">
		<!-- Contact & Links Card -->
		<div class="wpcrr-panel wpcrr-contact-panel">
			<div class="wpcrr-panel-header">
				<h3>
					<span class="dashicons dashicons-businessperson"></span>
					<?php esc_html_e( 'Contact WPCalibrate', 'wpcalibrate-robots-repair' ); ?>
				</h3>
			</div>
			<div class="wpcrr-panel-body">
				<p><?php esc_html_e( 'Have a question, encountered an edge case, or need custom assistance? Reach our engineering team directly:', 'wpcalibrate-robots-repair' ); ?></p>
				
				<ul class="wpcrr-contact-list">
					<li>
						<span class="dashicons dashicons-email"></span>
						<strong><?php esc_html_e( 'Support Email:', 'wpcalibrate-robots-repair' ); ?></strong>
						<a href="mailto:support@wpcalibrate.com">support@wpcalibrate.com</a>
					</li>
					<li>
						<span class="dashicons dashicons-phone"></span>
						<strong><?php esc_html_e( 'Phone / WhatsApp:', 'wpcalibrate-robots-repair' ); ?></strong>
						<a href="https://wa.me/447474795976" target="_blank" rel="noopener noreferrer">+447474795976</a>
					</li>
					<li>
						<span class="dashicons dashicons-admin-site"></span>
						<strong><?php esc_html_e( 'Official Website:', 'wpcalibrate-robots-repair' ); ?></strong>
						<a href="https://wpcalibrate.com" target="_blank" rel="noopener noreferrer">https://wpcalibrate.com</a>
					</li>
					<li>
						<span class="dashicons dashicons-cart"></span>
						<strong><?php esc_html_e( 'Marketplace & Addons:', 'wpcalibrate-robots-repair' ); ?></strong>
						<a href="https://marketplace.wpcalibrate.com/" target="_blank" rel="noopener noreferrer">https://marketplace.wpcalibrate.com/</a>
					</li>
				</ul>
			</div>
		</div>

		<!-- Knowledge & Documentation Card -->
		<div class="wpcrr-panel wpcrr-faq-panel">
			<div class="wpcrr-panel-header">
				<h3>
					<span class="dashicons dashicons-book"></span>
					<?php esc_html_e( 'Robots.txt Essentials & Workflow', 'wpcalibrate-robots-repair' ); ?>
				</h3>
			</div>
			<div class="wpcrr-panel-body">
				<div class="wpcrr-accordion-item">
					<h4><?php esc_html_e( '1. Scan → Diagnose → Explain → Repair → Verify Workflow', 'wpcalibrate-robots-repair' ); ?></h4>
					<p><?php esc_html_e( 'Unlike basic text editors, WPCalibrate scans your public robots.txt URL, pinpoints exact syntax or crawl problems, explains the business impact in plain English, and verifies the public HTTP result after every repair.', 'wpcalibrate-robots-repair' ); ?></p>
				</div>

				<div class="wpcrr-accordion-item">
					<h4><?php esc_html_e( '2. Physical Files vs. WordPress Virtual robots.txt', 'wpcalibrate-robots-repair' ); ?></h4>
					<p><?php esc_html_e( 'If a physical "robots.txt" file exists in your website root folder, your web server (Apache/Nginx/LiteSpeed) serves it directly, bypassing WordPress entirely. If no physical file exists, WordPress dynamically serves a virtual robots.txt using the robots_txt filter hook.', 'wpcalibrate-robots-repair' ); ?></p>
				</div>

				<div class="wpcrr-accordion-item">
					<h4><?php esc_html_e( '3. Crawling vs. Indexing (Critical Principle)', 'wpcalibrate-robots-repair' ); ?></h4>
					<p><?php esc_html_e( 'A "Disallow" rule in robots.txt instructs search engine crawlers not to fetch a page. However, it does NOT guarantee that the page will be removed from search results. If other websites link to that URL, search engines may still index the link as a bare snippet. To reliably prevent indexing, use a <meta name="robots" content="noindex"> tag or require password authentication.', 'wpcalibrate-robots-repair' ); ?></p>
				</div>

				<div class="wpcrr-accordion-item">
					<h4><?php esc_html_e( '4. Never Block CSS, JavaScript, or Frontend Assets', 'wpcalibrate-robots-repair' ); ?></h4>
					<p><?php esc_html_e( 'Modern crawlers like Googlebot render your website using a full headless browser to understand layout, mobile responsiveness, and dynamic content. Blocking CSS, JS, themes, or uploads can severely hurt your search rankings.', 'wpcalibrate-robots-repair' ); ?></p>
				</div>

				<div class="wpcrr-accordion-item">
					<h4><?php esc_html_e( '5. Reverse Proxies & CDN Caching Delays', 'wpcalibrate-robots-repair' ); ?></h4>
					<p><?php esc_html_e( 'If you use Cloudflare, Sucuri, Fastly, or another CDN, a repaired robots.txt file may take several minutes to reflect publicly until edge caches expire or are purged. WPCalibrate alerts you when a public response differs from your saved configuration.', 'wpcalibrate-robots-repair' ); ?></p>
				</div>
			</div>
		</div>
	</div>
</div>
