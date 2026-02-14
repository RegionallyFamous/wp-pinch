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

/**
 * Clean up all WP Pinch data for a single site.
 *
 * Extracted into a function so it can be called per-site on multisite.
 */
function wp_pinch_cleanup_site() {
	global $wpdb;

	// Remove all options.
	$wp_pinch_options = array(
		// Core connection.
		'wp_pinch_gateway_url',
		'wp_pinch_api_token',
		'wp_pinch_api_disabled',
		'wp_pinch_read_only_mode',
		'wp_pinch_gateway_reply_strict_sanitize',
		'wp_pinch_agent_id',
		'wp_pinch_rate_limit',
		'wp_pinch_daily_write_cap',
		'wp_pinch_daily_write_alert_email',
		'wp_pinch_daily_write_alert_threshold',
		'wp_pinch_version',
		'wp_pinch_wizard_completed',

		// Webhook settings.
		'wp_pinch_webhook_events',
		'wp_pinch_webhook_channel',
		'wp_pinch_webhook_to',
		'wp_pinch_webhook_deliver',
		'wp_pinch_webhook_model',
		'wp_pinch_webhook_thinking',
		'wp_pinch_webhook_timeout',
		'wp_pinch_webhook_wake_modes',
		'wp_pinch_webhook_endpoint_types',

		// Chat settings.
		'wp_pinch_chat_model',
		'wp_pinch_chat_thinking',
		'wp_pinch_chat_timeout',
		'wp_pinch_chat_placeholder',
		'wp_pinch_session_idle_minutes',

		// Governance settings.
		'wp_pinch_governance_tasks',
		'wp_pinch_governance_mode',
		'wp_pinch_governance_schedule_hash',

		// Feature flags & abilities.
		'wp_pinch_feature_flags',
		'wp_pinch_disabled_abilities',

		// Circuit breaker.
		'wp_pinch_circuit_last_opened_at',

		// Ghost Writer.
		'wp_pinch_ghost_writer_threshold',

		// OpenClaw role.
		'wp_pinch_openclaw_user_id',
		'wp_pinch_openclaw_capability_groups',

		// Approval queue.
		'wp_pinch_approval_queue',
	);

	foreach ( $wp_pinch_options as $wp_pinch_option ) {
		delete_option( $wp_pinch_option );
	}

	// Clean up transients.
	delete_transient( 'wp_pinch_webhook_counter' );

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

	// Clean up user meta (dismissible notice state + voice profiles).
	delete_metadata( 'user', 0, 'wp_pinch_dismissed_config_notice', '', true );
	delete_metadata( 'user', 0, 'wp_pinch_voice_profile', '', true );

	// Remove the OpenClaw agent role.
	remove_role( 'openclaw_agent' );

	// Clean up any remaining Action Scheduler jobs.
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( 'wp_pinch_governance_content_freshness' );
		as_unschedule_all_actions( 'wp_pinch_governance_seo_health' );
		as_unschedule_all_actions( 'wp_pinch_governance_comment_sweep' );
		as_unschedule_all_actions( 'wp_pinch_governance_broken_links' );
		as_unschedule_all_actions( 'wp_pinch_governance_security_scan' );
		as_unschedule_all_actions( 'wp_pinch_audit_cleanup' );
		as_unschedule_all_actions( 'wp_pinch_retry_webhook' );
		as_unschedule_all_actions( 'wp_pinch_governance_draft_necromancer' );
	}
}

// Handle multisite: iterate all sites. Single site: clean up once.
if ( is_multisite() ) {
	$wp_pinch_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $wp_pinch_sites as $wp_pinch_site_id ) {
		switch_to_blog( $wp_pinch_site_id );
		wp_pinch_cleanup_site();
		restore_current_blog();
	}
} else {
	wp_pinch_cleanup_site();
}
