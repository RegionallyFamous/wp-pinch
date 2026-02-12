/**
 * k6 Load Test — WP Pinch REST API
 *
 * Usage:
 *   k6 run --env BASE_URL=http://localhost:8889 \
 *          --env WP_USER=admin \
 *          --env WP_PASS=password \
 *          tests/load/k6-chat.js
 *
 * @package
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

// Custom metrics.
const errorRate = new Rate( 'errors' );
const chatDuration = new Trend( 'chat_duration', true );
const statusDuration = new Trend( 'status_duration', true );
const healthDuration = new Trend( 'health_duration', true );

// Test configuration.
export const options = {
	stages: [
		{ duration: '10s', target: 5 }, // Ramp up to 5 users.
		{ duration: '30s', target: 5 }, // Sustained load.
		{ duration: '10s', target: 15 }, // Spike to 15 users.
		{ duration: '20s', target: 15 }, // Sustained spike.
		{ duration: '10s', target: 0 }, // Ramp down.
	],
	thresholds: {
		http_req_duration: [ 'p(95)<5000' ], // 95% of requests under 5s.
		errors: [ 'rate<0.1' ], // Less than 10% errors.
		chat_duration: [ 'p(95)<10000' ], // Chat under 10s at p95.
		status_duration: [ 'p(95)<2000' ], // Status under 2s at p95.
		health_duration: [ 'p(95)<500' ], // Health under 500ms at p95.
	},
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8889';
const WP_USER = __ENV.WP_USER || 'admin';
const WP_PASS = __ENV.WP_PASS || 'password';

/**
 * Get an authentication nonce by logging in via wp-login.php.
 */
export function setup() {
	// Get a nonce by calling the REST API with basic auth.
	const res = http.get( `${ BASE_URL }/wp-json/wp/v2/users/me`, {
		headers: {
			Authorization: 'Basic ' + __ENV.WP_AUTH_TOKEN,
		},
	} );

	// If basic auth isn't available, try application passwords or cookie auth.
	// For simplicity, we'll use the nonce from the REST API response headers.
	const nonce = res.headers[ 'X-WP-Nonce' ] || '';

	return { nonce };
}

/**
 * Main test scenario — mix of chat, status, and health requests.
 * @param data
 */
export default function ( data ) {
	const headers = {
		'Content-Type': 'application/json',
		'X-WP-Nonce': data.nonce,
	};

	const scenario = Math.random();

	if ( scenario < 0.3 ) {
		// 30% — Chat messages.
		const chatRes = http.post(
			`${ BASE_URL }/wp-json/wp-pinch/v1/chat`,
			JSON.stringify( {
				message: `Load test message at ${ new Date().toISOString() }`,
			} ),
			{ headers, tags: { endpoint: 'chat' } }
		);

		chatDuration.add( chatRes.timings.duration );
		check( chatRes, {
			'chat: status is 200 or 429': ( r ) =>
				r.status === 200 || r.status === 429,
		} );
		errorRate.add( chatRes.status >= 500 );
	} else if ( scenario < 0.6 ) {
		// 30% — Status checks.
		const statusRes = http.get(
			`${ BASE_URL }/wp-json/wp-pinch/v1/status`,
			{ headers, tags: { endpoint: 'status' } }
		);

		statusDuration.add( statusRes.timings.duration );
		check( statusRes, {
			'status: returns 200 or 429': ( r ) =>
				r.status === 200 || r.status === 429,
		} );
		errorRate.add( statusRes.status >= 500 );
	} else {
		// 40% — Health endpoint (lightweight).
		const healthRes = http.get(
			`${ BASE_URL }/wp-json/wp-pinch/v1/health`,
			{ tags: { endpoint: 'health' } }
		);

		healthDuration.add( healthRes.timings.duration );
		check( healthRes, {
			'health: returns 200': ( r ) => r.status === 200,
			'health: has status ok': ( r ) => {
				try {
					const body = JSON.parse( r.body );
					return body.status === 'ok';
				} catch ( e ) {
					return false;
				}
			},
		} );
		errorRate.add( healthRes.status >= 500 );
	}

	sleep( Math.random() * 2 + 0.5 ); // 0.5–2.5s think time.
}
