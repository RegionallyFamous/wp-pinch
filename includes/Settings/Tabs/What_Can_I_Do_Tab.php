<?php
/**
 * "What can I pinch?" tab — discoverability of main features.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Settings\Tabs;

defined( 'ABSPATH' ) || exit;

/**
 * What can I do tab.
 */
class What_Can_I_Do_Tab {

	/**
	 * Render the tab content.
	 */
	public static function render(): void {
		$wiki = 'https://github.com/RegionallyFamous/wp-pinch/wiki';
		?>
		<div class="wp-pinch-what-can-i-do">
			<p><?php esc_html_e( 'Here\'s what this lobster can pinch for you:', 'wp-pinch' ); ?></p>
			<ul>
				<li>
					<strong><?php esc_html_e( 'Capture from channels (PinchDrop)', 'wp-pinch' ); ?></strong>
					— <?php esc_html_e( 'Send ideas from WhatsApp, Telegram, Slack, or Discord; turn them into draft packs or quick notes.', 'wp-pinch' ); ?>
					<a href="<?php echo esc_url( $wiki . '/PinchDrop' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'PinchDrop guide', 'wp-pinch' ); ?> &rarr;</a>
				</li>
				<li>
					<strong><?php esc_html_e( 'Chat with your site (block)', 'wp-pinch' ); ?></strong>
					— <?php esc_html_e( 'Add a chat block to any post or page so visitors (or you) can talk to your site.', 'wp-pinch' ); ?>
					<a href="<?php echo esc_url( $wiki . '/Chat-Block' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Chat block', 'wp-pinch' ); ?> &rarr;</a>
				</li>
				<li>
					<strong><?php esc_html_e( 'Daily digest (Tide Report)', 'wp-pinch' ); ?></strong>
					— <?php esc_html_e( 'Governance findings (stale posts, SEO, drafts) bundled into one daily webhook.', 'wp-pinch' ); ?>
					<a href="<?php echo esc_url( $wiki . '/Configuration' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Configuration', 'wp-pinch' ); ?> &rarr;</a>
				</li>
				<li>
					<strong><?php esc_html_e( 'Synthesize across posts (Weave)', 'wp-pinch' ); ?></strong>
					— <?php esc_html_e( 'Search content and get a payload ready for synthesis; build answers from your existing posts.', 'wp-pinch' ); ?>
					<a href="<?php echo esc_url( $wiki . '/Abilities-Reference' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Abilities Reference', 'wp-pinch' ); ?> &rarr;</a>
				</li>
				<li>
					<strong><?php esc_html_e( 'Quick tools', 'wp-pinch' ); ?></strong>
					— <?php esc_html_e( 'TL;DR on publish, Link Suggester (suggest internal links), Quote Bank (extract notable sentences).', 'wp-pinch' ); ?>
					<a href="<?php echo esc_url( $wiki . '/Abilities-Reference' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Abilities Reference', 'wp-pinch' ); ?> &rarr;</a>
				</li>
			</ul>
			<p>
				<a href="<?php echo esc_url( $wiki . '/Abilities-Reference' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Full Abilities Reference (the whole claw)', 'wp-pinch' ); ?> &rarr;</a>
			</p>
		</div>
		<?php
	}
}
