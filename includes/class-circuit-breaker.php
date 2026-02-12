<?php
/**
 * Circuit Breaker for gateway HTTP calls.
 *
 * Prevents hammering a dead gateway by tracking consecutive failures
 * and failing fast during an outage. Automatically recovers when the
 * gateway becomes reachable again.
 *
 * States:
 *   CLOSED    — Normal operation, requests pass through.
 *   OPEN      — Gateway is down, all requests fail immediately.
 *   HALF_OPEN — Cooldown expired, one probe request is allowed.
 *
 * @package WP_Pinch
 * @since   2.1.0
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * Circuit breaker for outbound HTTP calls to the AI gateway.
 */
class Circuit_Breaker {

	/**
	 * Transient key for circuit state.
	 */
	const STATE_KEY = 'wp_pinch_circuit_state';

	/**
	 * Transient key for failure count.
	 */
	const FAILURE_KEY = 'wp_pinch_circuit_failures';

	/**
	 * Transient key for the open-circuit timestamp.
	 */
	const OPEN_UNTIL_KEY = 'wp_pinch_circuit_open_until';

	/**
	 * Number of consecutive failures before opening the circuit.
	 */
	const FAILURE_THRESHOLD = 3;

	/**
	 * Cooldown period in seconds before probing.
	 */
	const COOLDOWN_SECONDS = 60;

	/**
	 * Circuit states.
	 */
	const STATE_CLOSED    = 'closed';
	const STATE_OPEN      = 'open';
	const STATE_HALF_OPEN = 'half_open';

	/**
	 * Check whether a request is allowed through the circuit.
	 *
	 * @return bool True if the request should proceed, false if it should fail fast.
	 */
	public static function is_available(): bool {
		$state = self::get_state();

		if ( self::STATE_CLOSED === $state ) {
			return true;
		}

		if ( self::STATE_OPEN === $state ) {
			$open_until = (int) get_transient( self::OPEN_UNTIL_KEY );

			// Cooldown expired — transition to half-open for a probe.
			if ( time() >= $open_until ) {
				self::set_state( self::STATE_HALF_OPEN );
				return true;
			}

			return false;
		}

		// HALF_OPEN — allow the probe request.
		return true;
	}

	/**
	 * Record a successful gateway response.
	 *
	 * Resets the circuit to CLOSED and clears the failure counter.
	 */
	public static function record_success(): void {
		delete_transient( self::FAILURE_KEY );
		delete_transient( self::OPEN_UNTIL_KEY );
		self::set_state( self::STATE_CLOSED );
	}

	/**
	 * Record a failed gateway response.
	 *
	 * Increments the failure counter. If the threshold is reached,
	 * opens the circuit for the cooldown period.
	 */
	public static function record_failure(): void {
		$failures = (int) get_transient( self::FAILURE_KEY );
		++$failures;

		// Store failure count with a generous TTL (auto-resets if gateway recovers and no one calls record_failure).
		set_transient( self::FAILURE_KEY, $failures, self::COOLDOWN_SECONDS * 3 );

		if ( $failures >= self::FAILURE_THRESHOLD ) {
			self::set_state( self::STATE_OPEN );
			set_transient( self::OPEN_UNTIL_KEY, time() + self::COOLDOWN_SECONDS, self::COOLDOWN_SECONDS + 10 );

			Audit_Table::insert(
				'circuit_open',
				'gateway',
				sprintf(
					'Circuit breaker opened after %d consecutive failures. Requests will fail fast for %d seconds.',
					$failures,
					self::COOLDOWN_SECONDS
				)
			);
		}
	}

	/**
	 * Get the number of seconds remaining before the circuit allows a probe.
	 *
	 * Returns 0 if the circuit is closed or the cooldown has expired.
	 *
	 * @return int Seconds until probe is allowed.
	 */
	public static function get_retry_after(): int {
		if ( self::STATE_OPEN !== self::get_state() ) {
			return 0;
		}

		$open_until = (int) get_transient( self::OPEN_UNTIL_KEY );
		$remaining  = $open_until - time();

		return max( 0, $remaining );
	}

	/**
	 * Get the current circuit state.
	 *
	 * @return string One of STATE_CLOSED, STATE_OPEN, STATE_HALF_OPEN.
	 */
	public static function get_state(): string {
		$state = get_transient( self::STATE_KEY );

		if ( ! in_array( $state, array( self::STATE_CLOSED, self::STATE_OPEN, self::STATE_HALF_OPEN ), true ) ) {
			return self::STATE_CLOSED;
		}

		return $state;
	}

	/**
	 * Force-reset the circuit to closed. Useful for admin "reset" actions.
	 */
	public static function reset(): void {
		delete_transient( self::STATE_KEY );
		delete_transient( self::FAILURE_KEY );
		delete_transient( self::OPEN_UNTIL_KEY );
	}

	/**
	 * Set the circuit state.
	 *
	 * @param string $state One of the STATE_* constants.
	 */
	private static function set_state( string $state ): void {
		set_transient( self::STATE_KEY, $state, self::COOLDOWN_SECONDS * 3 );
	}
}
