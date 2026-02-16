<?php
/**
 * REST Controller — chat proxy and status endpoints.
 *
 * POST /wp-json/wp-pinch/v1/chat  — forwards message to OpenClaw.
 * GET  /wp-json/wp-pinch/v1/status — pings the OpenClaw gateway.
 *
 * Both require edit_posts capability and are rate-limited.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/** Chat proxy, status, and related REST endpoints. */
class Rest_Controller {

	/**
	 * Maximum allowed message length in characters.
	 */
	const MAX_MESSAGE_LENGTH = 4000;

	/**
	 * Maximum session_key length (prevents transient/cache key abuse).
	 */
	const MAX_SESSION_KEY_LENGTH = 128;

	/**
	 * Default rate limit (requests per minute). Mirrors Rest\Helpers::DEFAULT_RATE_LIMIT.
	 */
	const DEFAULT_RATE_LIMIT = 10;

	/**
	 * Trace ID for the current REST request (set in rest_request_before_callbacks).
	 *
	 * @var string|null
	 */
	public static $current_trace_id = null;

	/**
	 * Wire hooks.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'ensure_trace_id' ), 10, 3 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'add_security_headers' ), 10, 3 );
	}

	/**
	 * Ensure the request has a trace ID and store it for audit log correlation.
	 *
	 * @param \WP_REST_Response|\WP_HTTP_Response|\WP_Error $response Response to send.
	 * @param array                                        $handler  Route handler.
	 * @param \WP_REST_Request                             $request  Request.
	 * @return \WP_REST_Response|\WP_HTTP_Response|\WP_Error
	 */
	public static function ensure_trace_id( $response, array $handler, \WP_REST_Request $request ) {
		$route = $request->get_route();
		if ( ! str_starts_with( $route, '/wp-pinch/' ) ) {
			return $response;
		}

		$tid = $request->get_header( 'X-WP-Pinch-Trace-Id' );
		if ( empty( $tid ) || ! is_string( $tid ) ) {
			$tid = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'wp-pinch-', true );
			$request->set_header( 'X-WP-Pinch-Trace-Id', $tid );
		}

		self::$current_trace_id = $tid;

		return $response;
	}

	/**
	 * Get the trace ID for the current REST request (for audit log context).
	 *
	 * @return string|null
	 */
	public static function get_trace_id(): ?string {
		return self::$current_trace_id;
	}

	/**
	 * Register REST routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'wp-pinch/v1',
			'/chat',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( \WP_Pinch\Rest\Chat::class, 'handle_chat' ),
					'permission_callback' => array( \WP_Pinch\Rest\Auth::class, 'check_permission' ),
					'args'                => array(
						'message'     => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function ( $value ) {
								if ( ! is_string( $value ) || '' === trim( $value ) ) {
									return new \WP_Error(
										'validation_error',
										__( 'Message cannot be empty.', 'wp-pinch' ),
										array( 'status' => 400 )
									);
								}
								if ( mb_strlen( $value ) > self::MAX_MESSAGE_LENGTH ) {
									return new \WP_Error(
										'validation_error',
										__( 'Message is too long.', 'wp-pinch' ),
										array( 'status' => 400 )
									);
								}
								return true;
							},
						),
						'session_key' => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => function ( $value ) {
								if ( is_string( $value ) && mb_strlen( $value ) > self::MAX_SESSION_KEY_LENGTH ) {
									return new \WP_Error(
										'validation_error',
										__( 'Session key is too long.', 'wp-pinch' ),
										array( 'status' => 400 )
									);
								}
								return true;
							},
						),
						'model'       => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'agent_id'    => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				'schema' => array( \WP_Pinch\Rest\Schemas::class, 'get_chat_schema' ),
			)
		);

		register_rest_route(
			'wp-pinch/v1',
			'/status',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( \WP_Pinch\Rest\Status::class, 'handle_status' ),
					'permission_callback' => array( \WP_Pinch\Rest\Auth::class, 'check_permission' ),
				),
				'schema' => array( \WP_Pinch\Rest\Schemas::class, 'get_status_schema' ),
			)
		);

		// List abilities for discovery (non-MCP clients).
		register_rest_route(
			'wp-pinch/v1',
			'/abilities',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( \WP_Pinch\Rest\Status::class, 'handle_list_abilities' ),
					'permission_callback' => array( \WP_Pinch\Rest\Auth::class, 'check_permission' ),
				),
			)
		);

		// Public lightweight health check (no auth required).
		register_rest_route(
			'wp-pinch/v1',
			'/health',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( \WP_Pinch\Rest\Status::class, 'handle_health' ),
					'permission_callback' => '__return_true',
				),
				'schema' => array( \WP_Pinch\Rest\Schemas::class, 'get_health_schema' ),
			)
		);

		// Session reset — generates a fresh session key for chat.
		register_rest_route(
			'wp-pinch/v1',
			'/session/reset',
			array(
				'methods'             => 'POST',
				'callback'            => array( \WP_Pinch\Rest\Chat::class, 'handle_session_reset' ),
				'permission_callback' => function () {
					if ( is_user_logged_in() ) {
						return current_user_can( 'edit_posts' );
					}
					return Feature_Flags::is_enabled( 'public_chat' );
				},
			)
		);

		// Ghost Writer endpoint (list abandoned drafts / trigger ghostwriting).
		if ( Feature_Flags::is_enabled( 'ghost_writer' ) ) {
			register_rest_route(
				'wp-pinch/v1',
				'/ghostwrite',
				array(
					array(
						'methods'             => \WP_REST_Server::CREATABLE,
						'callback'            => array( \WP_Pinch\Rest\Ghostwrite::class, 'handle_ghostwrite' ),
						'permission_callback' => array( \WP_Pinch\Rest\Auth::class, 'check_permission' ),
						'args'                => array(
							'action'  => array(
								'required'          => true,
								'type'              => 'string',
								'enum'              => array( 'list', 'write' ),
								'sanitize_callback' => 'sanitize_key',
							),
							'post_id' => array(
								'type'              => 'integer',
								'default'           => 0,
								'sanitize_callback' => 'absint',
							),
						),
					),
				)
			);
		}

		// Molt endpoint — repackage post into multiple formats.
		if ( Feature_Flags::is_enabled( 'molt' ) ) {
			register_rest_route(
				'wp-pinch/v1',
				'/molt',
				array(
					array(
						'methods'             => \WP_REST_Server::CREATABLE,
						'callback'            => array( \WP_Pinch\Rest\Molt::class, 'handle_molt' ),
						'permission_callback' => array( \WP_Pinch\Rest\Auth::class, 'check_permission' ),
						'args'                => array(
							'post_id'      => array(
								'required'          => true,
								'type'              => 'integer',
								'sanitize_callback' => 'absint',
								'validate_callback' => function ( $value ) {
									$v = absint( $value );
									if ( $v < 1 ) {
										return new \WP_Error(
											'validation_error',
											__( 'Post ID must be a positive integer.', 'wp-pinch' ),
											array( 'status' => 400 )
										);
									}
									return true;
								},
							),
							'output_types' => array(
								'type'              => 'array',
								'items'             => array( 'type' => 'string' ),
								'default'           => array(),
								'sanitize_callback' => function ( $value ) {
									if ( ! is_array( $value ) ) {
										return array();
									}
									return array_map( 'sanitize_key', $value );
								},
							),
						),
					),
				)
			);
		}

		// Web Clipper: token-protected one-shot capture from browser (bookmarklet).
		register_rest_route(
			'wp-pinch/v1',
			'/capture',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( \WP_Pinch\Rest\Capture::class, 'handle_web_clipper_capture' ),
					'permission_callback' => array( \WP_Pinch\Rest\Auth::class, 'check_capture_token' ),
					'args'                => array(
						'text'         => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
							'validate_callback' => function ( $value ) {
								if ( ! is_string( $value ) || '' === trim( $value ) ) {
									return new \WP_Error(
										'validation_error',
										__( 'Text cannot be empty.', 'wp-pinch' ),
										array( 'status' => 400 )
									);
								}
								if ( mb_strlen( $value ) > 50000 ) {
									return new \WP_Error(
										'validation_error',
										__( 'Text is too long.', 'wp-pinch' ),
										array( 'status' => 400 )
									);
								}
								return true;
							},
						),
						'url'          => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'esc_url_raw',
						),
						'title'        => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'save_as_note' => array(
							'type'              => 'boolean',
							'default'           => true,
							'sanitize_callback' => 'rest_sanitize_boolean',
						),
					),
				),
			)
		);

		// PinchDrop capture endpoint (channel-agnostic inbound ideas).
		register_rest_route(
			'wp-pinch/v1',
			'/pinchdrop/capture',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( \WP_Pinch\Rest\Capture::class, 'handle_pinchdrop_capture' ),
					'permission_callback' => array( \WP_Pinch\Rest\Auth::class, 'check_hook_token' ),
					'args'                => array(
						'text'       => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
							'validate_callback' => function ( $value ) {
								if ( ! is_string( $value ) || '' === trim( $value ) ) {
									return new \WP_Error(
										'validation_error',
										__( 'Text cannot be empty.', 'wp-pinch' ),
										array( 'status' => 400 )
									);
								}
								if ( mb_strlen( $value ) > 20000 ) {
									return new \WP_Error(
										'validation_error',
										__( 'Text is too long.', 'wp-pinch' ),
										array( 'status' => 400 )
									);
								}
								return true;
							},
						),
						'source'     => array(
							'type'              => 'string',
							'default'           => 'openclaw',
							'sanitize_callback' => 'sanitize_key',
						),
						'author'     => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'request_id' => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'options'    => array(
							'type'              => 'object',
							'default'           => array(),
							'sanitize_callback' => function ( $value ) {
								if ( ! is_array( $value ) ) {
									return array();
								}
								return \WP_Pinch\Rest\Helpers::sanitize_params_recursive( $value );
							},
						),
					),
				),
			)
		);

		// Public chat endpoint (no auth required, strict rate limiting).
		if ( Feature_Flags::is_enabled( 'public_chat' ) ) {
			register_rest_route(
				'wp-pinch/v1',
				'/chat/public',
				array(
					array(
						'methods'             => \WP_REST_Server::CREATABLE,
						'callback'            => array( \WP_Pinch\Rest\Chat::class, 'handle_public_chat' ),
						'permission_callback' => '__return_true',
						'args'                => array(
							'message'     => array(
								'required'          => true,
								'type'              => 'string',
								'sanitize_callback' => 'sanitize_text_field',
								'validate_callback' => function ( $value ) {
									if ( ! is_string( $value ) || '' === trim( $value ) ) {
										return new \WP_Error(
											'validation_error',
											__( 'Message cannot be empty.', 'wp-pinch' ),
											array( 'status' => 400 )
										);
									}
									if ( mb_strlen( $value ) > self::MAX_MESSAGE_LENGTH ) {
										return new \WP_Error(
											'validation_error',
											__( 'Message is too long.', 'wp-pinch' ),
											array( 'status' => 400 )
										);
									}
									return true;
								},
							),
							'session_key' => array(
								'type'              => 'string',
								'default'           => '',
								'sanitize_callback' => 'sanitize_key',
								'validate_callback' => function ( $value ) {
									if ( is_string( $value ) && mb_strlen( $value ) > self::MAX_SESSION_KEY_LENGTH ) {
										return new \WP_Error(
											'validation_error',
											__( 'Session key is too long.', 'wp-pinch' ),
											array( 'status' => 400 )
										);
									}
									return true;
								},
							),
							'model'       => array(
								'type'              => 'string',
								'default'           => '',
								'sanitize_callback' => 'sanitize_text_field',
							),
							'agent_id'    => array(
								'type'              => 'string',
								'default'           => '',
								'sanitize_callback' => 'sanitize_text_field',
							),
						),
					),
				)
			);
		}

		// SSE streaming chat endpoint.
		if ( Feature_Flags::is_enabled( 'streaming_chat' ) ) {
			register_rest_route(
				'wp-pinch/v1',
				'/chat/stream',
				array(
					array(
						'methods'             => \WP_REST_Server::CREATABLE,
						'callback'            => array( \WP_Pinch\Rest\Chat::class, 'handle_chat_stream' ),
						'permission_callback' => array( \WP_Pinch\Rest\Auth::class, 'check_permission' ),
						'args'                => array(
							'message'     => array(
								'required'          => true,
								'type'              => 'string',
								'sanitize_callback' => 'sanitize_text_field',
								'validate_callback' => function ( $value ) {
									if ( ! is_string( $value ) || '' === trim( $value ) ) {
										return new \WP_Error(
											'validation_error',
											__( 'Message cannot be empty.', 'wp-pinch' ),
											array( 'status' => 400 )
										);
									}
									if ( mb_strlen( $value ) > self::MAX_MESSAGE_LENGTH ) {
										return new \WP_Error(
											'validation_error',
											__( 'Message is too long.', 'wp-pinch' ),
											array( 'status' => 400 )
										);
									}
									return true;
								},
							),
							'session_key' => array(
								'type'              => 'string',
								'default'           => '',
								'sanitize_callback' => 'sanitize_key',
								'validate_callback' => function ( $value ) {
									if ( is_string( $value ) && mb_strlen( $value ) > self::MAX_SESSION_KEY_LENGTH ) {
										return new \WP_Error(
											'validation_error',
											__( 'Session key is too long.', 'wp-pinch' ),
											array( 'status' => 400 )
										);
									}
									return true;
								},
							),
							'model'       => array(
								'type'              => 'string',
								'default'           => '',
								'sanitize_callback' => 'sanitize_text_field',
							),
							'agent_id'    => array(
								'type'              => 'string',
								'default'           => '',
								'sanitize_callback' => 'sanitize_text_field',
							),
						),
					),
				)
			);
		}

		// Incoming webhook receiver — lets OpenClaw trigger abilities.
		register_rest_route(
			'wp-pinch/v1',
			'/hooks/receive',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( \WP_Pinch\Rest\Incoming_Hook::class, 'handle_incoming_hook' ),
					'permission_callback' => array( \WP_Pinch\Rest\Auth::class, 'check_hook_token' ),
					'args'                => array(
						'action'  => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
							'enum'              => array( 'execute_ability', 'execute_batch', 'run_governance', 'ping' ),
						),
						'ability' => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'params'  => array(
							'type'              => 'object',
							'default'           => array(),
							'sanitize_callback' => function ( $value ) {
								if ( ! is_array( $value ) ) {
									return array();
								}
								return \WP_Pinch\Rest\Helpers::sanitize_params_recursive( $value );
							},
						),
						'task'    => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_key',
						),
						'batch'   => array(
							'type'              => 'array',
							'default'           => array(),
							'sanitize_callback' => function ( $value ) {
								if ( ! is_array( $value ) ) {
									return array();
								}
								$max = 10;
								$out = array();
								foreach ( array_slice( $value, 0, $max ) as $item ) {
									if ( ! is_array( $item ) ) {
										continue;
									}
									$ability = isset( $item['ability'] ) ? sanitize_text_field( (string) $item['ability'] ) : '';
									$params  = isset( $item['params'] ) && is_array( $item['params'] ) ? \WP_Pinch\Rest\Helpers::sanitize_params_recursive( $item['params'] ) : array();
									$out[]   = array(
										'ability' => $ability,
										'params'  => $params,
									);
								}
								return $out;
							},
						),
					),
				),
			)
		);

		// Draft-first: approve and publish a post from preview (capability-gated).
		register_rest_route(
			'wp-pinch/v1',
			'/preview-approve',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( \WP_Pinch\Rest\Preview_Approve::class, 'handle_preview_approve' ),
					'permission_callback' => array( \WP_Pinch\Rest\Auth::class, 'check_hook_token' ),
					'args'                => array(
						'post_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => function ( $value ) {
								if ( absint( $value ) < 1 ) {
									return new \WP_Error(
										'validation_error',
										__( 'post_id must be a positive integer.', 'wp-pinch' ),
										array( 'status' => 400 )
									);
								}
								return true;
							},
						),
					),
				),
			)
		);
	}

	// =========================================================================
	// Security Headers
	// =========================================================================

	/**
	 * Add security headers to WP Pinch REST responses.
	 *
	 * Includes standard security headers plus rate-limit information
	 * so clients can self-throttle before hitting a 429.
	 *
	 * @param \WP_REST_Response $response Result to send to the client.
	 * @param \WP_REST_Server   $server   Server instance.
	 * @param \WP_REST_Request  $request  Request used to generate the response.
	 * @return \WP_REST_Response
	 */
	public static function add_security_headers( \WP_REST_Response $response, \WP_REST_Server $server, \WP_REST_Request $request ): \WP_REST_Response {
		$route = $request->get_route();

		// Only apply to our own endpoints.
		if ( ! str_starts_with( $route, '/wp-pinch/' ) ) {
			return $response;
		}

		$trace_id = $request->get_header( 'X-WP-Pinch-Trace-Id' );
		if ( ! empty( $trace_id ) ) {
			$response->header( 'X-WP-Pinch-Trace-Id', $trace_id );
		}

		$response->header( 'X-Content-Type-Options', 'nosniff' );
		$response->header( 'X-Frame-Options', 'DENY' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow' );
		$response->header( 'Referrer-Policy', 'strict-origin-when-cross-origin' );
		$response->header( 'Permissions-Policy', 'camera=(), microphone=(), geolocation=()' );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );
		$response->header( 'Cross-Origin-Opener-Policy', 'same-origin' );
		$response->header( 'Cross-Origin-Resource-Policy', 'same-origin' );
		$response->header( 'Content-Security-Policy', "frame-ancestors 'none'" );

		if ( is_ssl() ) {
			$response->header( 'Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload' );
		}

		// Rate limit headers — let clients self-throttle.
		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			$limit   = max( 1, (int) get_option( 'wp_pinch_rate_limit', \WP_Pinch\Rest\Helpers::DEFAULT_RATE_LIMIT ) );
			$key     = 'wp_pinch_rest_rate_' . $user_id;
			$used    = 0;
			$reset   = time() + 60; // Default: window resets in 60s.

			if ( wp_using_ext_object_cache() ) {
				$used = (int) wp_cache_get( $key, 'wp-pinch' );
			} else {
				$used = (int) get_transient( $key );
				// Estimate reset time from transient timeout.
				$timeout = (int) get_option( '_transient_timeout_' . $key );
				if ( $timeout > 0 ) {
					$reset = $timeout;
				}
			}

			$remaining = max( 0, $limit - $used );
			$response->header( 'X-RateLimit-Limit', (string) $limit );
			$response->header( 'X-RateLimit-Remaining', (string) $remaining );
			$response->header( 'X-RateLimit-Reset', (string) $reset );
		}

		return $response;
	}
}
