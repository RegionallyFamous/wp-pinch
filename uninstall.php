<?php
/**
 * WP Pinch uninstall script.
 *
 * Runs when the plugin is deleted via the WordPress admin.
 * Removes all plugin options and the custom audit log table.
 *
 * @package WP_Pinch
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove all options.
$wp_pinch_options = array(
	'wp_pinch_gateway_url',
	'wp_pinch_api_token',
	'wp_pinch_webhook_events',
	'wp_pinch_governance_tasks',
	'wp_pinch_governance_mode',
	'wp_pinch_rate_limit',
	'wp_pinch_version',
	'wp_pinch_governance_schedule_hash',
);

foreach ( $wp_pinch_options as $wp_pinch_option ) {
	delete_option( $wp_pinch_option );
}

// Clean up transients.
delete_transient( 'wp_pinch_webhook_counter' );

global $wpdb;

// Clean up per-user REST rate-limit transients.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_wp_pinch_rest_rate_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_wp_pinch_rest_rate_' ) . '%'
	)
);

// Clean up ability cache transients.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_wp_pinch_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_wp_pinch_' ) . '%'
	)
);

// Drop the audit log table.
$wp_pinch_table = $wpdb->prefix . 'wp_pinch_audit_log';
$wpdb->query( "DROP TABLE IF EXISTS {$wp_pinch_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Clean up user meta (dismissible notice state).
delete_metadata( 'user', 0, 'wp_pinch_dismissed_config_notice', '', true );

// Clean up any remaining Action Scheduler jobs.
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'wp_pinch_governance_content_freshness' );
	as_unschedule_all_actions( 'wp_pinch_governance_seo_health' );
	as_unschedule_all_actions( 'wp_pinch_governance_comment_sweep' );
	as_unschedule_all_actions( 'wp_pinch_governance_broken_links' );
	as_unschedule_all_actions( 'wp_pinch_governance_security_scan' );
	as_unschedule_all_actions( 'wp_pinch_audit_cleanup' );
	as_unschedule_all_actions( 'wp_pinch_retry_webhook' );
}
