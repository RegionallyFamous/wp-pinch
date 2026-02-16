<?php
/**
 * REST daily write budget and alerting.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Write budget helpers for REST hook handler.
 */
class Write_Budget {

	/**
	 * Ability names that count toward the daily write budget.
	 *
	 * @var string[]
	 */
	private static $write_abilities = array(
		'wp-pinch/create-post',
		'wp-pinch/update-post',
		'wp-pinch/delete-post',
		'wp-pinch/manage-terms',
		'wp-pinch/upload-media',
		'wp-pinch/delete-media',
		'wp-pinch/update-user-role',
		'wp-pinch/moderate-comment',
		'wp-pinch/update-option',
		'wp-pinch/toggle-plugin',
		'wp-pinch/switch-theme',
		'wp-pinch/export-data',
		'wp-pinch/pinchdrop-generate',
		'wp-pinch/manage-menu-item',
		'wp-pinch/update-post-meta',
		'wp-pinch/restore-revision',
		'wp-pinch/bulk-edit-posts',
		'wp-pinch/manage-cron',
		'wp-pinch/ghostwrite',
		'wp-pinch/molt',
		'wp-pinch/woo-manage-order',
	);

	/**
	 * Ability names that count as writes. Filterable.
	 *
	 * @return string[]
	 */
	public static function get_write_ability_names(): array {
		return apply_filters( 'wp_pinch_write_abilities', self::$write_abilities );
	}

	/**
	 * Whether the given ability name counts as a write.
	 *
	 * @param string $ability_name Ability name.
	 * @return bool
	 */
	public static function is_write_ability( string $ability_name ): bool {
		return in_array( $ability_name, self::get_write_ability_names(), true );
	}

	/**
	 * Transient/cache key for daily write count (date-based).
	 *
	 * @return string
	 */
	public static function daily_write_count_key(): string {
		return 'wp_pinch_daily_writes_' . gmdate( 'Y-m-d' );
	}

	/**
	 * Current daily write count.
	 *
	 * @return int
	 */
	public static function get_daily_write_count(): int {
		$key = self::daily_write_count_key();
		if ( wp_using_ext_object_cache() ) {
			return (int) wp_cache_get( $key, 'wp-pinch' );
		}
		return (int) get_transient( $key );
	}

	/**
	 * Increment daily write count.
	 */
	public static function increment_daily_write_count(): void {
		$key   = self::daily_write_count_key();
		$count = self::get_daily_write_count();
		$ttl   = strtotime( 'tomorrow midnight', time() ) - time();
		$ttl   = max( 3600, $ttl );
		if ( wp_using_ext_object_cache() ) {
			if ( 0 === $count ) {
				wp_cache_set( $key, 1, 'wp-pinch', $ttl );
			} else {
				wp_cache_incr( $key, 1, 'wp-pinch' );
			}
		} else {
			set_transient( $key, $count + 1, $ttl );
		}
	}

	/**
	 * Check if one more write would exceed the daily cap.
	 *
	 * @return \WP_Error|null Error if over cap, null if allowed.
	 */
	public static function check_daily_write_budget(): ?\WP_Error {
		$cap = (int) get_option( 'wp_pinch_daily_write_cap', 0 );
		if ( $cap <= 0 ) {
			return null;
		}
		$count = self::get_daily_write_count();
		if ( $count >= $cap ) {
			return new \WP_Error(
				'daily_write_budget_exceeded',
				__( 'Daily write budget exceeded. Try again tomorrow or increase the limit in WP Pinch settings.', 'wp-pinch' ),
				array( 'status' => 429 )
			);
		}
		return null;
	}

	/**
	 * Send at most one alert per day when usage reaches threshold.
	 */
	public static function maybe_send_daily_write_alert(): void {
		$cap       = (int) get_option( 'wp_pinch_daily_write_cap', 0 );
		$email     = get_option( 'wp_pinch_daily_write_alert_email', '' );
		$threshold = (int) get_option( 'wp_pinch_daily_write_alert_threshold', 80 );
		if ( $cap <= 0 || '' === sanitize_email( $email ) || $threshold < 1 || $threshold > 100 ) {
			return;
		}
		$count = self::get_daily_write_count();
		$pct   = (int) floor( ( $count / $cap ) * 100 );
		if ( $pct < $threshold ) {
			return;
		}
		$alert_key = 'wp_pinch_daily_alert_sent_' . gmdate( 'Y-m-d' );
		if ( wp_using_ext_object_cache() ) {
			if ( wp_cache_get( $alert_key, 'wp-pinch' ) ) {
				return;
			}
			wp_cache_set( $alert_key, 1, 'wp-pinch', DAY_IN_SECONDS );
		} else {
			if ( get_transient( $alert_key ) ) {
				return;
			}
			set_transient( $alert_key, 1, DAY_IN_SECONDS );
		}
		$subject = sprintf(
			/* translators: 1: site name, 2: percentage */
			__( '[%1$s] WP Pinch daily write usage at %2$d%%', 'wp-pinch' ),
			get_bloginfo( 'name' ),
			$pct
		);
		$message = sprintf(
			/* translators: 1: count, 2: cap, 3: percentage */
			__( 'WP Pinch has used %1$d of %2$d daily write operations (%3$d%%). Adjust the limit or alert threshold in WP Pinch → Connection if needed.', 'wp-pinch' ),
			$count,
			$cap,
			$pct
		);
		wp_mail( $email, $subject, $message );
	}
}
