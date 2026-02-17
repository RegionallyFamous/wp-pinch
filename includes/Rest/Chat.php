<?php
/**
 * REST handlers for chat, public chat, session reset, and chat stream.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Rest;

use WP_Pinch\Audit_Table;
use WP_Pinch\Circuit_Breaker;
use WP_Pinch\Feature_Flags;
use WP_Pinch\Plugin;
use WP_Pinch\RAG_Index;
use WP_Pinch\Rest_Controller;
use WP_Pinch\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Chat REST handlers.
 */
class Chat {

	/**
	 * Handle chat message — forward to OpenClaw.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_chat( \WP_REST_Request $request ) {
		if ( Plugin::is_api_disabled() ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'api_disabled',
					'message' => __( 'API access is currently disabled.', 'wp-pinch' ),
				),
				503
			);
		}
		if ( ! Helpers::check_rate_limit() ) {
			$response = new \WP_REST_Response(
				array(
					'code'    => 'rate_limited',
					'message' => __( 'Too many requests. Please wait a moment.', 'wp-pinch' ),
				),
				429
			);
			$response->header( 'Retry-After', '60' );
			return $response;
		}
		$gateway_url = get_option( 'wp_pinch_gateway_url', '' );
		$api_token   = Settings::get_api_token();
		if ( empty( $gateway_url ) || empty( $api_token ) ) {
			return new \WP_Error(
				'not_configured',
				__( 'WP Pinch is not configured. Please set your Gateway URL and API token in the WP Pinch settings.', 'wp-pinch' ),
				array( 'status' => 503 )
			);
		}
		if ( Feature_Flags::is_enabled( 'circuit_breaker' ) && ! Circuit_Breaker::is_available() ) {
			$retry    = Circuit_Breaker::get_retry_after();
			$response = new \WP_REST_Response(
				array(
					'code'    => 'gateway_unavailable',
					'message' => __( 'The AI gateway is temporarily unavailable. Please try again shortly.', 'wp-pinch' ),
				),
				503
			);
			$response->header( 'Retry-After', (string) max( 1, $retry ) );
			return $response;
		}
		$message       = $request->get_param( 'message' );
		$user          = wp_get_current_user();
		$user_prefix   = 'wp-pinch-chat-' . $user->ID;
		$requested_key = sanitize_key( $request->get_param( 'session_key' ) );
		$session_key   = ( '' !== $requested_key && str_starts_with( $requested_key, $user_prefix ) )
			? $requested_key
			: $user_prefix;
		$payload       = array(
			'message'    => $message,
			'name'       => 'WordPress',
			'sessionKey' => $session_key,
			'wakeMode'   => 'now',
		);
		$agent_id      = get_option( 'wp_pinch_agent_id', '' );
		if ( '' !== $agent_id ) {
			$payload['agentId'] = sanitize_text_field( $agent_id );
		}
		$chat_model    = get_option( 'wp_pinch_chat_model', '' );
		$chat_thinking = get_option( 'wp_pinch_chat_thinking', '' );
		$chat_timeout  = (int) get_option( 'wp_pinch_chat_timeout', 0 );
		$req_model     = $request->get_param( 'model' );
		$req_agent     = $request->get_param( 'agent_id' );
		if ( $req_model ) {
			$payload['model'] = sanitize_text_field( $req_model );
		} elseif ( '' !== $chat_model ) {
			$payload['model'] = $chat_model;
		}
		if ( '' !== $chat_thinking ) {
			$payload['thinking'] = $chat_thinking;
		}
		if ( $chat_timeout > 0 ) {
			$payload['timeoutSeconds'] = $chat_timeout;
		}
		if ( $req_agent ) {
			$payload['agentId'] = sanitize_text_field( $req_agent );
		}
		$payload  = apply_filters( 'wp_pinch_chat_payload', $payload, $request );
		$chat_url = trailingslashit( $gateway_url ) . 'hooks/agent';
		if ( ! wp_http_validate_url( $chat_url ) ) {
			return new \WP_Error(
				'invalid_gateway',
				__( 'Gateway URL failed security validation.', 'wp-pinch' ),
				array( 'status' => 502 )
			);
		}
		$response = wp_safe_remote_post(
			$chat_url,
			array(
				'timeout' => max( 15, $chat_timeout ),
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_token,
				),
				'body'    => wp_json_encode( $payload ),
			)
		);
		if ( is_wp_error( $response ) ) {
			Circuit_Breaker::record_failure();
			$trace_id = Rest_Controller::get_trace_id();
			Audit_Table::insert(
				'gateway_error',
				'chat',
				$response->get_error_message(),
				array_filter( array( 'trace_id' => $trace_id ) )
			);
			return new \WP_Error(
				'gateway_error',
				__( 'Unable to reach the AI gateway. Please try again later.', 'wp-pinch' ),
				array( 'status' => 502 )
			);
		}
		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		$data   = json_decode( $body, true );
		if ( $status < 200 || $status >= 300 ) {
			Circuit_Breaker::record_failure();
			return new \WP_Error(
				'gateway_error',
				sprintf(
					/* translators: %d: HTTP status code returned by OpenClaw gateway. */
					__( 'OpenClaw returned HTTP %d.', 'wp-pinch' ),
					$status
				),
				array( 'status' => 502 )
			);
		}
		Circuit_Breaker::record_success();
		$reply    = $data['response'] ?? $data['message'] ?? null;
		$reply    = is_string( $reply ) ? Helpers::cap_chat_reply( $reply ) : null;
		$result   = array(
			'reply'       => is_string( $reply ) ? Helpers::sanitize_gateway_reply( $reply ) : __( 'Received an unexpected response from the gateway.', 'wp-pinch' ),
			'session_key' => $session_key,
		);
		$result   = apply_filters( 'wp_pinch_chat_response', $result, $data );
		$trace_id = Rest_Controller::get_trace_id();
		Audit_Table::insert(
			'chat_message',
			'chat',
			sprintf( 'Chat message from user #%d.', $user->ID ),
			array_merge( array( 'user_id' => $user->ID ), array_filter( array( 'trace_id' => $trace_id ) ) )
		);
		$response_obj = new \WP_REST_Response( $result, 200 );
		if ( isset( $data['usage'] ) && is_array( $data['usage'] ) ) {
			$response_obj->header( 'X-Token-Usage', wp_json_encode( $data['usage'] ) );
		}
		return $response_obj;
	}

	/**
	 * Handle a public (unauthenticated) chat message.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_public_chat( \WP_REST_Request $request ) {
		if ( Plugin::is_api_disabled() ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'api_disabled',
					'message' => __( 'API access is currently disabled.', 'wp-pinch' ),
				),
				503
			);
		}
		$gateway_url = get_option( 'wp_pinch_gateway_url', '' );
		$api_token   = Settings::get_api_token();
		if ( empty( $gateway_url ) || empty( $api_token ) ) {
			return new \WP_Error(
				'not_configured',
				__( 'Chat is not available at this time.', 'wp-pinch' ),
				array( 'status' => 503 )
			);
		}
		if ( Feature_Flags::is_enabled( 'circuit_breaker' ) && ! Circuit_Breaker::is_available() ) {
			$retry    = Circuit_Breaker::get_retry_after();
			$response = new \WP_REST_Response(
				array(
					'code'    => 'gateway_unavailable',
					'message' => __( 'Chat is temporarily unavailable. Please try again shortly.', 'wp-pinch' ),
				),
				503
			);
			$response->header( 'Retry-After', (string) max( 1, $retry ) );
			return $response;
		}
		$limit   = max( 1, min( 60, (int) get_option( 'wp_pinch_public_chat_rate_limit', 3 ) ) );
		$ip_hash = 'wp_pinch_pub_rate_' . substr( hash_hmac( 'sha256', Helpers::get_client_ip(), wp_salt() ), 0, 16 );
		$count   = (int) get_transient( $ip_hash );
		if ( $count >= $limit ) {
			$response = new \WP_REST_Response(
				array(
					'code'    => 'rate_limited',
					'message' => __( 'Too many requests. Please wait a moment.', 'wp-pinch' ),
				),
				429
			);
			$response->header( 'Retry-After', '60' );
			return $response;
		}
		set_transient( $ip_hash, $count + 1, 60 );
		$message     = $request->get_param( 'message' );
		$session_key = $request->get_param( 'session_key' );
		if ( ! is_string( $session_key ) || ! preg_match( '/^wp-pinch-public-[a-zA-Z0-9-]{1,48}$/', $session_key ) ) {
			$session_key = 'wp-pinch-public-' . wp_generate_password( 16, false, false );
		}
		$payload  = array(
			'message'    => $message,
			'name'       => 'WordPress',
			'sessionKey' => $session_key,
			'wakeMode'   => 'now',
			'metadata'   => array(
				'publicChat' => true,
				'site_url'   => home_url(),
			),
		);
		$agent_id = get_option( 'wp_pinch_agent_id', '' );
		if ( '' !== $agent_id ) {
			$payload['agentId'] = sanitize_text_field( $agent_id );
		}
		$chat_model    = get_option( 'wp_pinch_chat_model', '' );
		$chat_thinking = get_option( 'wp_pinch_chat_thinking', '' );
		$chat_timeout  = (int) get_option( 'wp_pinch_chat_timeout', 0 );
		$req_model     = $request->get_param( 'model' );
		$req_agent     = $request->get_param( 'agent_id' );
		if ( $req_model ) {
			$payload['model'] = sanitize_text_field( $req_model );
		} elseif ( '' !== $chat_model ) {
			$payload['model'] = $chat_model;
		}
		if ( '' !== $chat_thinking ) {
			$payload['thinking'] = $chat_thinking;
		}
		if ( $chat_timeout > 0 ) {
			$payload['timeoutSeconds'] = $chat_timeout;
		}
		if ( $req_agent ) {
			$payload['agentId'] = sanitize_text_field( $req_agent );
		}
		$payload         = apply_filters( 'wp_pinch_chat_payload', $payload, $request );
		$public_chat_url = trailingslashit( $gateway_url ) . 'hooks/agent';
		if ( ! wp_http_validate_url( $public_chat_url ) ) {
			return new \WP_Error(
				'invalid_gateway',
				__( 'Gateway URL failed security validation.', 'wp-pinch' ),
				array( 'status' => 502 )
			);
		}
		$response = wp_safe_remote_post(
			$public_chat_url,
			array(
				'timeout' => max( 15, $chat_timeout ),
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_token,
				),
				'body'    => wp_json_encode( $payload ),
			)
		);
		if ( is_wp_error( $response ) ) {
			Circuit_Breaker::record_failure();
			return new \WP_Error(
				'gateway_error',
				__( 'Unable to process your request. Please try again later.', 'wp-pinch' ),
				array( 'status' => 502 )
			);
		}
		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		$data   = json_decode( $body, true );
		if ( $status < 200 || $status >= 300 ) {
			Circuit_Breaker::record_failure();
			return new \WP_Error(
				'gateway_error',
				__( 'Chat is temporarily unavailable.', 'wp-pinch' ),
				array( 'status' => 502 )
			);
		}
		Circuit_Breaker::record_success();
		$reply    = $data['response'] ?? $data['message'] ?? null;
		$reply    = is_string( $reply ) ? Helpers::cap_chat_reply( $reply ) : null;
		$result   = array(
			'reply'       => is_string( $reply ) ? Helpers::sanitize_gateway_reply( $reply ) : __( 'No response received.', 'wp-pinch' ),
			'session_key' => $session_key,
		);
		$result   = apply_filters( 'wp_pinch_chat_response', $result, $data );
		$trace_id = Rest_Controller::get_trace_id();
		Audit_Table::insert(
			'public_chat_message',
			'chat',
			'Public chat message.',
			array_merge( array( 'session_prefix' => substr( $session_key, 0, 24 ) ), array_filter( array( 'trace_id' => $trace_id ) ) )
		);
		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * Handle session reset — generate a fresh session key for chat.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function handle_session_reset( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! is_user_logged_in() ) {
			$limit = (int) apply_filters( 'wp_pinch_session_reset_rate_limit', 10 );
			if ( $limit > 0 && ! Helpers::check_ip_rate_limit( 'wp_pinch_session_reset_', $limit ) ) {
				$response = new \WP_REST_Response(
					array(
						'code'    => 'rate_limited',
						'message' => __( 'Too many requests. Please wait a moment.', 'wp-pinch' ),
					),
					429
				);
				$response->header( 'Retry-After', '60' );
				return $response;
			}
		}
		if ( is_user_logged_in() ) {
			$session_key = 'wp-pinch-chat-' . get_current_user_id() . '-' . time();
		} else {
			$session_key = 'wp-pinch-public-' . wp_generate_password( 16, false, false );
		}
		return new \WP_REST_Response( array( 'session_key' => $session_key ), 200 );
	}

	/**
	 * Handle chat message via Server-Sent Events streaming.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error|null Null when streaming (exit after output).
	 */
	public static function handle_chat_stream( \WP_REST_Request $request ) {
		if ( ! Helpers::check_rate_limit() ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'rate_limited',
					'message' => __( 'Too many requests. Please wait a moment.', 'wp-pinch' ),
				),
				429
			);
		}
		if ( Feature_Flags::is_enabled( 'circuit_breaker' ) && ! Circuit_Breaker::is_available() ) {
			$retry    = Circuit_Breaker::get_retry_after();
			$response = new \WP_REST_Response(
				array(
					'code'    => 'gateway_unavailable',
					'message' => __( 'The AI gateway is temporarily unavailable.', 'wp-pinch' ),
				),
				503
			);
			$response->header( 'Retry-After', (string) max( 1, $retry ) );
			return $response;
		}
		$gateway_url = get_option( 'wp_pinch_gateway_url', '' );
		$api_token   = Settings::get_api_token();
		if ( empty( $gateway_url ) || empty( $api_token ) ) {
			return new \WP_Error(
				'not_configured',
				__( 'WP Pinch is not configured.', 'wp-pinch' ),
				array( 'status' => 503 )
			);
		}
		$message       = $request->get_param( 'message' );
		$user          = wp_get_current_user();
		$user_prefix   = 'wp-pinch-chat-' . $user->ID;
		$requested_key = sanitize_key( $request->get_param( 'session_key' ) );
		$session_key   = ( '' !== $requested_key && str_starts_with( $requested_key, $user_prefix ) )
			? $requested_key
			: $user_prefix;
		$payload       = array(
			'message'    => $message,
			'name'       => 'WordPress',
			'sessionKey' => $session_key,
			'wakeMode'   => 'now',
			'stream'     => true,
		);
		$agent_id      = get_option( 'wp_pinch_agent_id', '' );
		if ( '' !== $agent_id ) {
			$payload['agentId'] = sanitize_text_field( $agent_id );
		}
		$chat_model    = get_option( 'wp_pinch_chat_model', '' );
		$chat_thinking = get_option( 'wp_pinch_chat_thinking', '' );
		$chat_timeout  = (int) get_option( 'wp_pinch_chat_timeout', 0 );
		$req_model     = $request->get_param( 'model' );
		$req_agent     = $request->get_param( 'agent_id' );
		if ( $req_model ) {
			$payload['model'] = sanitize_text_field( $req_model );
		} elseif ( '' !== $chat_model ) {
			$payload['model'] = $chat_model;
		}
		if ( '' !== $chat_thinking ) {
			$payload['thinking'] = $chat_thinking;
		}
		if ( $chat_timeout > 0 ) {
			$payload['timeoutSeconds'] = $chat_timeout;
		}
		if ( $req_agent ) {
			$payload['agentId'] = sanitize_text_field( $req_agent );
		}
		// RAG: inject relevant site chunks so the model can answer from content.
		if ( RAG_Index::is_available() && is_string( $message ) && '' !== trim( $message ) ) {
			$chunks = RAG_Index::get_relevant_chunks( $message, 5 );
			if ( ! empty( $chunks ) ) {
				$context_parts = array();
				foreach ( $chunks as $c ) {
					$context_parts[] = sprintf( '[Post #%d: %s] %s', $c['post_id'], $c['title'], $c['content'] );
				}
				$payload['message'] = sprintf(
					"Use the following site content to answer the user. Cite post IDs when relevant.\n\nSITE CONTENT:\n%s\n\nUSER QUESTION: %s",
					implode( "\n\n", $context_parts ),
					$message
				);
			}
		}
		$payload        = apply_filters( 'wp_pinch_chat_payload', $payload, $request );
		$sse_max_per_ip = (int) get_option( 'wp_pinch_sse_max_connections_per_ip', 5 );
		if ( $sse_max_per_ip > 0 ) {
			$sse_key   = 'wp_pinch_sse_' . substr( hash_hmac( 'sha256', Helpers::get_client_ip(), wp_salt() ), 0, 16 );
			$sse_count = (int) get_transient( $sse_key );
			if ( $sse_count >= $sse_max_per_ip ) {
				return new \WP_REST_Response(
					array(
						'code'    => 'rate_limited',
						'message' => __( 'Too many streaming connections. Please close other chat windows and try again.', 'wp-pinch' ),
					),
					429
				);
			}
			set_transient( $sse_key, $sse_count + 1, 120 );
			register_shutdown_function(
				function () use ( $sse_key ) {
					$n = (int) get_transient( $sse_key );
					set_transient( $sse_key, max( 0, $n - 1 ), 120 );
				}
			);
		}
		// Cap how long a single SSE request can run; prevents stale connections from consuming resources.
		set_time_limit( (int) apply_filters( 'wp_pinch_sse_timeout_seconds', 300 ) );
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' );
		if ( ob_get_level() ) {
			ob_end_flush();
		}
		$request_headers = "Content-Type: application/json\r\n"
			. "Authorization: Bearer {$api_token}\r\n"
			. "Accept: text/event-stream\r\n";
		$context         = stream_context_create(
			array(
				'http' => array(
					'method'  => 'POST',
					'header'  => $request_headers,
					'content' => wp_json_encode( $payload ),
					'timeout' => max( 30, $chat_timeout ),
				),
				'ssl'  => array(
					'verify_peer'      => true,
					'verify_peer_name' => true,
				),
			)
		);
		$stream_url      = trailingslashit( $gateway_url ) . 'hooks/agent';
		if ( ! wp_http_validate_url( $stream_url ) ) {
			echo "event: error\n";
			echo 'data: ' . wp_json_encode( array( 'message' => __( 'Gateway URL failed security validation.', 'wp-pinch' ) ) ) . "\n\n";
			echo "event: done\ndata: {}\n\n";
			flush();
			if ( function_exists( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
			}
			exit;
		}
		$stream = @fopen( $stream_url, 'r', false, $context );
		if ( ! $stream ) {
			Circuit_Breaker::record_failure();
			echo "event: error\n";
			echo 'data: ' . wp_json_encode( array( 'message' => __( 'Unable to reach the AI gateway.', 'wp-pinch' ) ) ) . "\n\n";
			echo "event: done\ndata: {}\n\n";
			flush();
			if ( function_exists( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
			}
			exit;
		}
		$meta          = stream_get_meta_data( $stream );
		$stream_status = 0;
		if ( ! empty( $meta['wrapper_data'] ) && is_array( $meta['wrapper_data'] ) ) {
			foreach ( $meta['wrapper_data'] as $header_line ) {
				if ( preg_match( '/^HTTP\/\S+\s+(\d{3})/', $header_line, $matches ) ) {
					$stream_status = (int) $matches[1];
				}
			}
		}
		if ( $stream_status >= 400 ) {
			fclose( $stream );
			Circuit_Breaker::record_failure();
			echo "event: error\n";
			echo 'data: ' . wp_json_encode( array( 'message' => __( 'The AI gateway returned an error.', 'wp-pinch' ) ) ) . "\n\n";
			echo "event: done\ndata: {}\n\n";
			flush();
			if ( function_exists( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
			}
			exit;
		}
		Circuit_Breaker::record_success();
		$full_response    = '';
		$forwarded_events = false;
		$sse_buffer       = '';
		$max_response_len = (int) get_option( 'wp_pinch_chat_max_response_length', 200000 );
		if ( $max_response_len <= 0 ) {
			$max_response_len = 0;
		}
		while ( ! feof( $stream ) ) {
			$chunk = fread( $stream, 4096 );
			if ( false === $chunk || '' === $chunk ) {
				break;
			}
			$full_response .= $chunk;
			if ( $max_response_len > 0 && strlen( $full_response ) > $max_response_len ) {
				echo "event: error\n";
				echo 'data: ' . wp_json_encode( array( 'message' => __( 'Response too large. Try a shorter message.', 'wp-pinch' ) ) ) . "\n\n";
				break;
			}
			$clean                    = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $chunk );
			$sse_buffer              .= $clean;
			list( $out, $sse_buffer ) = Helpers::process_sse_buffer( $sse_buffer );
			if ( '' !== $out ) {
				echo $out;
				$forwarded_events = true;
				if ( ob_get_level() ) {
					ob_flush();
				}
				flush();
			}
		}
		if ( '' !== $sse_buffer ) {
			list( $out, $_ ) = Helpers::process_sse_buffer( $sse_buffer . "\n" );
			if ( '' !== $out ) {
				echo $out;
				$forwarded_events = true;
			} else {
				echo $sse_buffer;
				$forwarded_events = true;
			}
		}
		fclose( $stream );
		if ( ! $forwarded_events ) {
			$data  = json_decode( $full_response, true );
			$reply = $data['response'] ?? $data['message'] ?? '';
			$reply = is_string( $reply ) ? Helpers::sanitize_gateway_reply( $reply ) : '';
			echo "event: message\n";
			echo 'data: ' . wp_json_encode(
				array(
					'reply'       => $reply,
					'session_key' => $session_key,
				)
			) . "\n\n";
		}
		echo "event: done\ndata: {}\n\n";
		if ( ob_get_level() ) {
			ob_flush();
		}
		flush();
		$trace_id = Rest_Controller::get_trace_id();
		Audit_Table::insert(
			'chat_message',
			'chat',
			sprintf( 'Streamed chat message from user #%d.', $user->ID ),
			array_merge( array( 'user_id' => $user->ID ), array_filter( array( 'trace_id' => $trace_id ) ) )
		);
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}
		exit;
	}
}
