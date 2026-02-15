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

/**
 * REST API endpoints for the chat block and status checks.
 */
class Rest_Controller {

	/**
	 * Default rate limit: requests per minute per user.
	 * Overridden by the wp_pinch_rate_limit option when set.
	 */
	const DEFAULT_RATE_LIMIT = 10;

	/**
	 * Maximum allowed message length in characters.
	 */
	const MAX_MESSAGE_LENGTH = 4000;

	/**
	 * Maximum session_key length (prevents transient/cache key abuse).
	 */
	const MAX_SESSION_KEY_LENGTH = 128;

	/**
	 * Maximum keys per level in params (webhook/pinchdrop) to limit DoS.
	 */
	const MAX_PARAMS_KEYS_PER_LEVEL = 100;

	/**
	 * Ability names that count toward the daily write budget (create/update/delete/mutate).
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
					'callback'            => array( __CLASS__, 'handle_chat' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
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
				'schema' => array( __CLASS__, 'get_chat_schema' ),
			)
		);

		register_rest_route(
			'wp-pinch/v1',
			'/status',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'handle_status' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
				),
				'schema' => array( __CLASS__, 'get_status_schema' ),
			)
		);

		// List abilities for discovery (non-MCP clients).
		register_rest_route(
			'wp-pinch/v1',
			'/abilities',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'handle_list_abilities' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
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
					'callback'            => array( __CLASS__, 'handle_health' ),
					'permission_callback' => '__return_true',
				),
				'schema' => array( __CLASS__, 'get_health_schema' ),
			)
		);

		// Session reset — generates a fresh session key for chat.
		register_rest_route(
			'wp-pinch/v1',
			'/session/reset',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_session_reset' ),
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
						'callback'            => array( __CLASS__, 'handle_ghostwrite' ),
						'permission_callback' => array( __CLASS__, 'check_permission' ),
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
						'callback'            => array( __CLASS__, 'handle_molt' ),
						'permission_callback' => array( __CLASS__, 'check_permission' ),
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
					'callback'            => array( __CLASS__, 'handle_web_clipper_capture' ),
					'permission_callback' => array( __CLASS__, 'check_capture_token' ),
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
					'callback'            => array( __CLASS__, 'handle_pinchdrop_capture' ),
					'permission_callback' => array( __CLASS__, 'check_hook_token' ),
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
								return self::sanitize_params_recursive( $value );
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
						'callback'            => array( __CLASS__, 'handle_public_chat' ),
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
						'callback'            => array( __CLASS__, 'handle_chat_stream' ),
						'permission_callback' => array( __CLASS__, 'check_permission' ),
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
					'callback'            => array( __CLASS__, 'handle_incoming_hook' ),
					'permission_callback' => array( __CLASS__, 'check_hook_token' ),
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
								return self::sanitize_params_recursive( $value );
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
									$params  = isset( $item['params'] ) && is_array( $item['params'] ) ? self::sanitize_params_recursive( $item['params'] ) : array();
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
					'callback'            => array( __CLASS__, 'handle_preview_approve' ),
					'permission_callback' => array( __CLASS__, 'check_hook_token' ),
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
			$limit   = max( 1, (int) get_option( 'wp_pinch_rate_limit', self::DEFAULT_RATE_LIMIT ) );
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

	// =========================================================================
	// REST Schemas
	// =========================================================================

	/**
	 * Schema for the /chat endpoint.
	 *
	 * @return array JSON Schema.
	 */
	public static function get_chat_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'wp-pinch-chat',
			'type'       => 'object',
			'properties' => array(
				'reply'       => array(
					'description' => __( 'The AI assistant reply text.', 'wp-pinch' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'session_key' => array(
					'description' => __( 'Session key for conversation continuity.', 'wp-pinch' ),
					'type'        => 'string',
					'readonly'    => true,
				),
			),
		);
	}

	/**
	 * Schema for the /status endpoint.
	 *
	 * @return array JSON Schema.
	 */
	public static function get_status_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'wp-pinch-status',
			'type'       => 'object',
			'properties' => array(
				'plugin_version' => array(
					'description' => __( 'Current WP Pinch plugin version.', 'wp-pinch' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'configured'     => array(
					'description' => __( 'Whether the plugin is configured with gateway URL and API token.', 'wp-pinch' ),
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'mcp_endpoint'   => array(
					'description' => __( 'The MCP server REST endpoint URL.', 'wp-pinch' ),
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'gateway'        => array(
					'description' => __( 'Gateway connection status.', 'wp-pinch' ),
					'type'        => 'object',
					'readonly'    => true,
					'properties'  => array(
						'connected' => array(
							'type' => 'boolean',
						),
						'status'    => array(
							'type' => 'integer',
						),
					),
				),
			),
		);
	}

	/**
	 * Schema for the /health endpoint.
	 *
	 * @return array JSON Schema.
	 */
	public static function get_health_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'wp-pinch-health',
			'type'       => 'object',
			'properties' => array(
				'status'     => array(
					'description' => __( 'Health status (ok).', 'wp-pinch' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'version'    => array(
					'description' => __( 'Plugin version.', 'wp-pinch' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'configured' => array(
					'description' => __( 'Whether gateway URL and token are configured.', 'wp-pinch' ),
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'rate_limit' => array(
					'description' => __( 'Rate limit config (requests per minute).', 'wp-pinch' ),
					'type'        => 'object',
					'readonly'    => true,
					'properties'  => array(
						'limit' => array( 'type' => 'integer' ),
					),
				),
				'circuit'    => array(
					'description' => __( 'Circuit breaker state.', 'wp-pinch' ),
					'type'        => 'object',
					'readonly'    => true,
					'properties'  => array(
						'state'           => array( 'type' => 'string' ),
						'retry_after'     => array( 'type' => 'integer' ),
						'last_failure_at' => array(
							'type'   => array( 'string', 'null' ),
							'format' => 'date-time',
						),
					),
				),
				'timestamp'  => array(
					'description' => __( 'ISO 8601 timestamp.', 'wp-pinch' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'readonly'    => true,
				),
			),
		);
	}

	/**
	 * Permission callback — require edit_posts.
	 *
	 * @return bool|\WP_Error
	 */
	public static function check_permission() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error(
				'capability_denied',
				__( 'You do not have permission to use WP Pinch.', 'wp-pinch' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Permission callback for the incoming webhook endpoint.
	 *
	 * Validates the request via Bearer token matching the stored API token,
	 * or via HMAC-SHA256 signature when webhook signatures are enabled.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return true|\WP_Error
	 */
	public static function check_hook_token( \WP_REST_Request $request ) {
		$api_token = \WP_Pinch\Settings::get_api_token();

		if ( empty( $api_token ) ) {
			return new \WP_Error(
				'not_configured',
				__( 'WP Pinch API token is not configured.', 'wp-pinch' ),
				array( 'status' => 503 )
			);
		}

		// Check Bearer token.
		$auth_header = $request->get_header( 'authorization' );
		if ( $auth_header && preg_match( '/^Bearer\s+(.+)$/i', $auth_header, $matches ) ) {
			if ( hash_equals( $api_token, $matches[1] ) ) {
				return true;
			}
		}

		// Check X-OpenClaw-Token header (alternative).
		$openclaw_token = $request->get_header( 'x_openclaw_token' );
		if ( $openclaw_token && hash_equals( $api_token, $openclaw_token ) ) {
			return true;
		}

		// Check HMAC-SHA256 signature.
		if ( Feature_Flags::is_enabled( 'webhook_signatures' ) ) {
			$signature = $request->get_header( 'x_wp_pinch_signature' );
			$timestamp = $request->get_header( 'x_wp_pinch_timestamp' );
			if ( $signature && $timestamp ) {
				// Reject non-numeric timestamps to prevent type-juggling attacks.
				if ( ! ctype_digit( $timestamp ) ) {
					return new \WP_Error(
						'invalid_timestamp',
						__( 'Invalid signature timestamp.', 'wp-pinch' ),
						array( 'status' => 400 )
					);
				}

				$body     = $request->get_body();
				$expected = 'v1=' . hash_hmac( 'sha256', $timestamp . '.' . $body, $api_token );
				if ( hash_equals( $expected, $signature ) ) {
					// Reject if timestamp is more than 5 minutes old.
					if ( abs( time() - (int) $timestamp ) <= 300 ) {
						return true;
					}
				}
			}
		}

		return new \WP_Error(
			'rest_forbidden',
			__( 'Invalid or missing authentication token.', 'wp-pinch' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Permission callback for Web Clipper capture endpoint.
	 *
	 * Validates token from query param `token` or header X-WP-Pinch-Capture-Token
	 * against the option wp_pinch_capture_token.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return true|\WP_Error
	 */
	public static function check_capture_token( \WP_REST_Request $request ) {
		$stored = get_option( 'wp_pinch_capture_token', '' );
		if ( empty( $stored ) ) {
			return new \WP_Error(
				'capture_not_configured',
				__( 'Web Clipper capture token is not configured.', 'wp-pinch' ),
				array( 'status' => 503 )
			);
		}

		$token = $request->get_param( 'token' );
		if ( is_string( $token ) && hash_equals( $stored, $token ) ) {
			return true;
		}

		$header = $request->get_header( 'x_wp_pinch_capture_token' );
		if ( '' !== $header && hash_equals( $stored, $header ) ) {
			return true;
		}

		return new \WP_Error(
			'rest_forbidden',
			__( 'Invalid or missing capture token.', 'wp-pinch' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Handle an incoming webhook from OpenClaw.
	 *
	 * Supports four actions:
	 * - execute_ability: Run a registered WordPress ability.
	 * - execute_batch: Run up to 10 abilities in sequence; body.batch = [{ ability, params }, ...].
	 * - run_governance: Trigger a governance task.
	 * - ping: Health check.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_incoming_hook( \WP_REST_Request $request ) {
		if ( Plugin::is_api_disabled() ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'api_disabled',
					'message' => __( 'API access is currently disabled.', 'wp-pinch' ),
				),
				503
			);
		}

		$action = $request->get_param( 'action' );

		$trace_id = self::get_trace_id();
		Audit_Table::insert(
			'incoming_hook',
			'webhook',
			sprintf( 'Incoming hook received: %s', $action ),
			array_merge(
				array(
					'action'  => $action,
					'ability' => $request->get_param( 'ability' ),
					'task'    => $request->get_param( 'task' ),
				),
				array_filter( array( 'trace_id' => $trace_id ) )
			)
		);

		switch ( $action ) {
			case 'ping':
				return new \WP_REST_Response(
					array(
						'status'  => 'ok',
						'version' => WP_PINCH_VERSION,
						'time'    => gmdate( 'c' ),
					),
					200
				);

			case 'execute_ability':
				$ability_name = $request->get_param( 'ability' );
				$params       = $request->get_param( 'params' );

				if ( empty( $ability_name ) ) {
					return new \WP_Error(
						'missing_ability',
						__( 'The "ability" parameter is required for execute_ability action.', 'wp-pinch' ),
						array( 'status' => 400 )
					);
				}

				// Check if the ability is registered and not disabled.
				$ability_names = Abilities::get_ability_names();
				if ( ! in_array( $ability_name, $ability_names, true ) ) {
					return new \WP_Error(
						'unknown_ability',
						/* translators: %s: ability name */
						sprintf( __( 'Unknown ability: %s', 'wp-pinch' ), $ability_name ),
						array( 'status' => 404 )
					);
				}

				$disabled = get_option( 'wp_pinch_disabled_abilities', array() );
				if ( in_array( $ability_name, $disabled, true ) ) {
					return new \WP_Error(
						'ability_disabled',
						/* translators: %s: ability name */
						sprintf( __( 'Ability "%s" is currently disabled.', 'wp-pinch' ), $ability_name ),
						array( 'status' => 403 )
					);
				}

				// When approval workflow is on, queue destructive abilities instead of executing.
				if ( Approval_Queue::requires_approval( $ability_name ) ) {
					$item_id = Approval_Queue::queue(
						$ability_name,
						is_array( $params ) ? $params : array(),
						$trace_id
					);
					Audit_Table::insert(
						'ability_queued',
						'incoming_hook',
						sprintf( 'Ability "%s" queued for approval (id: %s).', $ability_name, $item_id ),
						array(
							'ability'  => $ability_name,
							'queue_id' => $item_id,
						)
					);
					return new \WP_REST_Response(
						array(
							'status'   => 'queued',
							'message'  => __( 'Ability queued for approval. An administrator must approve it in WP Pinch → Approvals.', 'wp-pinch' ),
							'queue_id' => $item_id,
						),
						202
					);
				}

				// Daily write budget: reject write abilities if cap exceeded.
				if ( self::is_write_ability( $ability_name ) ) {
					$budget_error = self::check_daily_write_budget();
					if ( $budget_error instanceof \WP_Error ) {
						return new \WP_REST_Response(
							array(
								'code'    => $budget_error->get_error_code(),
								'message' => $budget_error->get_error_message(),
							),
							429
						);
					}
				}

				// Execute the ability via the WordPress Abilities API.
				if ( ! function_exists( 'wp_execute_ability' ) ) {
					return new \WP_Error(
						'abilities_unavailable',
						__( 'WordPress Abilities API is not available.', 'wp-pinch' ),
						array( 'status' => 500 )
					);
				}

				// Incoming hooks are authenticated via Bearer token / HMAC,
				// but wp_execute_ability checks WordPress capabilities which
				// require a user context. Use the designated OpenClaw agent
				// user or fall back to the first administrator.
				$previous_user  = get_current_user_id();
				$execution_user = OpenClaw_Role::get_execution_user_id();

				if ( 0 === $execution_user ) {
					return new \WP_Error(
						'no_execution_user',
						__( 'No user found to execute the ability. Create an OpenClaw agent user or ensure an administrator exists.', 'wp-pinch' ),
						array( 'status' => 500 )
					);
				}

				wp_set_current_user( $execution_user );
				Webhook_Dispatcher::set_skip_webhooks_this_request( true );

				try {
					$result = wp_execute_ability( $ability_name, is_array( $params ) ? $params : array() );
				} finally {
					Webhook_Dispatcher::set_skip_webhooks_this_request( false );
				}

				// Restore previous user context.
				wp_set_current_user( $previous_user );

				if ( is_wp_error( $result ) ) {
					return new \WP_Error(
						'ability_error',
						$result->get_error_message(),
						array( 'status' => 422 )
					);
				}

				if ( self::is_write_ability( $ability_name ) ) {
					self::increment_daily_write_count();
					self::maybe_send_daily_write_alert();
				}

				$trace_id        = self::get_trace_id();
				$request_summary = self::sanitize_audit_params( $params );
				$result_summary  = self::sanitize_audit_result( $result );
				Audit_Table::insert(
					'ability_executed',
					'incoming_hook',
					sprintf( 'Ability "%s" executed via incoming hook.', $ability_name ),
					array_merge(
						array(
							'ability'         => $ability_name,
							'request_summary' => $request_summary,
							'result_summary'  => $result_summary,
						),
						array_filter( array( 'trace_id' => $trace_id ) )
					)
				);

				return new \WP_REST_Response(
					array(
						'status' => 'ok',
						'result' => $result,
					),
					200
				);

			case 'execute_batch':
				$batch = $request->get_param( 'batch' );
				if ( ! is_array( $batch ) || empty( $batch ) ) {
					return new \WP_Error(
						'validation_error',
						__( 'The "batch" parameter must be a non-empty array of { ability, params }.', 'wp-pinch' ),
						array( 'status' => 400 )
					);
				}

				$execution_user = OpenClaw_Role::get_execution_user_id();
				if ( 0 === $execution_user ) {
					return new \WP_Error(
						'no_execution_user',
						__( 'No user found to run abilities. Create an OpenClaw agent user or ensure an administrator exists.', 'wp-pinch' ),
						array( 'status' => 503 )
					);
				}

				$previous_user = get_current_user_id();
				wp_set_current_user( $execution_user );
				Webhook_Dispatcher::set_skip_webhooks_this_request( true );

				$results = array();
				try {
					foreach ( $batch as $item ) {
						$ability_name = isset( $item['ability'] ) ? trim( (string) $item['ability'] ) : '';
						$params       = isset( $item['params'] ) && is_array( $item['params'] ) ? $item['params'] : array();

						if ( '' === $ability_name ) {
							$results[] = array(
								'success' => false,
								'error'   => __( 'Missing ability name.', 'wp-pinch' ),
							);
							continue;
						}

						if ( ! function_exists( 'wp_execute_ability' ) ) {
							$results[] = array(
								'success' => false,
								'error'   => __( 'Abilities API not available.', 'wp-pinch' ),
							);
							break;
						}

						if ( self::is_write_ability( $ability_name ) ) {
							$budget_error = self::check_daily_write_budget();
							if ( $budget_error instanceof \WP_Error ) {
								return new \WP_REST_Response(
									array(
										'code'    => $budget_error->get_error_code(),
										'message' => $budget_error->get_error_message(),
										'partial' => $results,
									),
									429
								);
							}
						}

						$result = wp_execute_ability( $ability_name, $params );

						if ( is_wp_error( $result ) ) {
							$results[] = array(
								'success' => false,
								'error'   => $result->get_error_message(),
								'code'    => $result->get_error_code(),
							);
						} else {
							if ( self::is_write_ability( $ability_name ) ) {
								self::increment_daily_write_count();
								self::maybe_send_daily_write_alert();
							}
							$results[] = array(
								'success' => true,
								'result'  => $result,
							);
						}
					}
				} finally {
					Webhook_Dispatcher::set_skip_webhooks_this_request( false );
				}

				wp_set_current_user( $previous_user );

				$trace_id = self::get_trace_id();
				Audit_Table::insert(
					'batch_executed',
					'incoming_hook',
					sprintf( 'Batch of %d ability calls executed via incoming hook.', count( $batch ) ),
					array_merge(
						array( 'count' => count( $results ) ),
						array_filter( array( 'trace_id' => $trace_id ) )
					)
				);

				return new \WP_REST_Response(
					array(
						'status'  => 'ok',
						'results' => $results,
					),
					200
				);

			case 'run_governance':
				$task = $request->get_param( 'task' );

				if ( empty( $task ) ) {
					return new \WP_Error(
						'missing_task',
						__( 'The "task" parameter is required for run_governance action.', 'wp-pinch' ),
						array( 'status' => 400 )
					);
				}

				$available_tasks = Governance::get_available_tasks();
				if ( ! array_key_exists( $task, $available_tasks ) ) {
					return new \WP_Error(
						'unknown_task',
						/* translators: %s: governance task name */
						sprintf( __( 'Unknown governance task: %s', 'wp-pinch' ), $task ),
						array( 'status' => 404 )
					);
				}

				$method = 'task_' . $task;
				if ( ! method_exists( Governance::class, $method ) ) {
					return new \WP_Error(
						'task_unavailable',
						__( 'Governance task method is not available.', 'wp-pinch' ),
						array( 'status' => 500 )
					);
				}

				// Run the governance task synchronously.
				Governance::$method();

				$trace_id = self::get_trace_id();
				Audit_Table::insert(
					'governance_triggered',
					'incoming_hook',
					sprintf( 'Governance task "%s" triggered via incoming hook.', $task ),
					array_merge(
						array( 'task' => $task ),
						array_filter( array( 'trace_id' => $trace_id ) )
					)
				);

				return new \WP_REST_Response(
					array(
						'status'  => 'ok',
						'task'    => $task,
						'message' => sprintf(
							/* translators: %s: governance task name */
							__( 'Governance task "%s" executed.', 'wp-pinch' ),
							$task
						),
					),
					200
				);

			default:
				return new \WP_Error(
					'unknown_action',
					/* translators: %s: action name */
					sprintf( __( 'Unknown action: %s', 'wp-pinch' ), $action ),
					array( 'status' => 400 )
				);
		}
	}

	/**
	 * Approve and publish a draft post from preview (draft-first workflow).
	 *
	 * Requires hook token auth and that the execution user can edit the post.
	 *
	 * @param \WP_REST_Request $request REST request with post_id.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_preview_approve( \WP_REST_Request $request ) {
		if ( Plugin::is_api_disabled() ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'api_disabled',
					'message' => __( 'API access is currently disabled.', 'wp-pinch' ),
				),
				503
			);
		}

		$post_id = absint( $request->get_param( 'post_id' ) );
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || wp_is_post_revision( $post ) ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'not_found',
					'message' => __( 'Post not found.', 'wp-pinch' ),
				),
				404
			);
		}

		$allowed_statuses = array( 'draft', 'pending', 'future', 'private' );
		if ( ! in_array( $post->post_status, $allowed_statuses, true ) ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'invalid_status',
					'message' => __( 'Post is already published or cannot be approved.', 'wp-pinch' ),
				),
				400
			);
		}

		$execution_user = OpenClaw_Role::get_execution_user_id();
		if ( 0 === $execution_user ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'no_execution_user',
					'message' => __( 'No user found to perform the action.', 'wp-pinch' ),
				),
				503
			);
		}

		$previous_user = get_current_user_id();
		wp_set_current_user( $execution_user );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_set_current_user( $previous_user );
			return new \WP_REST_Response(
				array(
					'code'    => 'forbidden',
					'message' => __( 'You do not have permission to publish this post.', 'wp-pinch' ),
				),
				403
			);
		}

		$updated = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			),
			true
		);
		wp_set_current_user( $previous_user );

		if ( is_wp_error( $updated ) ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'update_failed',
					'message' => $updated->get_error_message(),
				),
				500
			);
		}

		Audit_Table::insert(
			'preview_approved',
			'rest',
			sprintf( 'Post #%d approved and published from preview.', $post_id ),
			array( 'post_id' => $post_id )
		);

		return new \WP_REST_Response(
			array(
				'status'    => 'ok',
				'post_id'   => $post_id,
				'url'       => get_permalink( $post_id ),
				'published' => true,
			),
			200
		);
	}

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

		if ( ! self::check_rate_limit() ) {
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
		$api_token   = \WP_Pinch\Settings::get_api_token();

		if ( empty( $gateway_url ) || empty( $api_token ) ) {
			return new \WP_Error(
				'not_configured',
				__( 'WP Pinch is not configured. Please set your Gateway URL and API token in the WP Pinch settings.', 'wp-pinch' ),
				array( 'status' => 503 )
			);
		}

		// Circuit breaker — fail fast if the gateway is known to be down.
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

		$message = $request->get_param( 'message' );
		$user    = wp_get_current_user();

		// Allow user-scoped session keys for multi-session support.
		// The key must start with the user's prefix to prevent cross-user hijacking.
		$user_prefix   = 'wp-pinch-chat-' . $user->ID;
		$requested_key = sanitize_key( $request->get_param( 'session_key' ) );
		$session_key   = ( '' !== $requested_key && str_starts_with( $requested_key, $user_prefix ) )
			? $requested_key
			: $user_prefix;

		$payload = array(
			'message'    => $message,
			'name'       => 'WordPress',
			'sessionKey' => $session_key,
			'wakeMode'   => 'now',
		);

		// Add optional agent routing when configured.
		$agent_id = get_option( 'wp_pinch_agent_id', '' );
		if ( '' !== $agent_id ) {
			$payload['agentId'] = sanitize_text_field( $agent_id );
		}

		// Chat-specific model config (separate from webhook settings).
		$chat_model    = get_option( 'wp_pinch_chat_model', '' );
		$chat_thinking = get_option( 'wp_pinch_chat_thinking', '' );
		$chat_timeout  = (int) get_option( 'wp_pinch_chat_timeout', 0 );

		// Per-request overrides from the block (admin-configured).
		$req_model = $request->get_param( 'model' );
		$req_agent = $request->get_param( 'agent_id' );

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

		// Per-block agent ID overrides the global setting.
		if ( $req_agent ) {
			$payload['agentId'] = sanitize_text_field( $req_agent );
		}

		/**
		 * Filter the chat payload before sending to OpenClaw.
		 *
		 * @since 1.0.0
		 *
		 * @param array            $payload The chat payload.
		 * @param \WP_REST_Request $request The REST request.
		 */
		$payload = apply_filters( 'wp_pinch_chat_payload', $payload, $request );

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
			// Log the real error server-side; return a generic message to the client.
			$trace_id = self::get_trace_id();
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
				/* translators: %d: HTTP status code returned by the OpenClaw gateway. */
				sprintf( __( 'OpenClaw returned HTTP %d.', 'wp-pinch' ), $status ),
				array( 'status' => 502 )
			);
		}

		Circuit_Breaker::record_success();

		// Only return expected string responses; never forward raw gateway body.
		$reply  = $data['response'] ?? $data['message'] ?? null;
		$reply  = is_string( $reply ) ? self::cap_chat_reply( $reply ) : null;
		$result = array(
			'reply'       => is_string( $reply ) ? self::sanitize_gateway_reply( $reply ) : __( 'Received an unexpected response from the gateway.', 'wp-pinch' ),
			'session_key' => $session_key,
		);

		/**
		 * Filter the chat response before returning to the client.
		 *
		 * @since 1.0.0
		 *
		 * @param array $result The response data.
		 * @param array $data   Raw OpenClaw response.
		 */
		$result = apply_filters( 'wp_pinch_chat_response', $result, $data );

		$trace_id = self::get_trace_id();
		Audit_Table::insert(
			'chat_message',
			'chat',
			sprintf( 'Chat message from user #%d.', $user->ID ),
			array_merge(
				array( 'user_id' => $user->ID ),
				array_filter( array( 'trace_id' => $trace_id ) )
			)
		);

		$response_obj = new \WP_REST_Response( $result, 200 );

		// Forward token usage from gateway when available.
		if ( isset( $data['usage'] ) && is_array( $data['usage'] ) ) {
			$response_obj->header( 'X-Token-Usage', wp_json_encode( $data['usage'] ) );
		}

		return $response_obj;
	}

	/**
	 * Handle a public (unauthenticated) chat message.
	 *
	 * Applies stricter rate limiting and uses anonymous session keys.
	 * The payload includes a publicChat flag so OpenClaw can restrict tool access.
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
		$api_token   = \WP_Pinch\Settings::get_api_token();

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

		// Configurable rate limiting for public endpoint (requests per minute per IP).
		// Placed after config/circuit-breaker checks so unavailable states don't consume rate limit.
		$limit   = max( 1, min( 60, (int) get_option( 'wp_pinch_public_chat_rate_limit', 3 ) ) );
		$ip_hash = 'wp_pinch_pub_rate_' . substr( hash_hmac( 'sha256', self::get_client_ip(), wp_salt() ), 0, 16 );
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

		// Increment the rate counter. Always use set_transient() to remain
		// compatible with sites using an external object cache (Redis, Memcached).
		// This resets the 60-second TTL window on each request, which is acceptable
		// for a low-volume public endpoint.
		set_transient( $ip_hash, $count + 1, 60 );

		$message     = $request->get_param( 'message' );
		$session_key = $request->get_param( 'session_key' );

		// Validate session key: must start with public prefix, max 64 chars, alphanumeric + hyphens.
		if ( ! is_string( $session_key ) || ! preg_match( '/^wp-pinch-public-[a-zA-Z0-9-]{1,48}$/', $session_key ) ) {
			$session_key = 'wp-pinch-public-' . wp_generate_password( 16, false, false );
		}

		$payload = array(
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

		// Chat-specific model config (separate from webhook settings).
		$chat_model    = get_option( 'wp_pinch_chat_model', '' );
		$chat_thinking = get_option( 'wp_pinch_chat_thinking', '' );
		$chat_timeout  = (int) get_option( 'wp_pinch_chat_timeout', 0 );

		// Per-request overrides from the block (admin-configured).
		$req_model = $request->get_param( 'model' );
		$req_agent = $request->get_param( 'agent_id' );

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

		// Per-block agent ID overrides the global setting.
		if ( $req_agent ) {
			$payload['agentId'] = sanitize_text_field( $req_agent );
		}

		/** This filter is documented in class-rest-controller.php */
		$payload = apply_filters( 'wp_pinch_chat_payload', $payload, $request );

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

		$reply  = $data['response'] ?? $data['message'] ?? null;
		$reply  = is_string( $reply ) ? self::cap_chat_reply( $reply ) : null;
		$result = array(
			'reply'       => is_string( $reply ) ? self::sanitize_gateway_reply( $reply ) : __( 'No response received.', 'wp-pinch' ),
			'session_key' => $session_key,
		);

		/** This filter is documented in class-rest-controller.php */
		$result = apply_filters( 'wp_pinch_chat_response', $result, $data );

		$trace_id = self::get_trace_id();
		Audit_Table::insert(
			'public_chat_message',
			'chat',
			'Public chat message.',
			array_merge(
				array( 'session_prefix' => substr( $session_key, 0, 24 ) ),
				array_filter( array( 'trace_id' => $trace_id ) )
			)
		);

		$response_obj = new \WP_REST_Response( $result, 200 );

		// Forward token usage from gateway when available.
		if ( isset( $data['usage'] ) && is_array( $data['usage'] ) ) {
			$response_obj->header( 'X-Token-Usage', wp_json_encode( $data['usage'] ) );
		}

		return $response_obj;
	}

	/**
	 * Handle a session reset request.
	 *
	 * Generates a fresh session key for the current user or anonymous visitor.
	 * Logged-in users receive a user-scoped key; anonymous users receive a
	 * random public session key.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function handle_session_reset( \WP_REST_Request $request ) {
		if ( is_user_logged_in() ) {
			$session_key = 'wp-pinch-chat-' . get_current_user_id() . '-' . time();
		} else {
			$session_key = 'wp-pinch-public-' . wp_generate_password( 16, false, false );
		}

		// Security headers are applied automatically via the rest_post_dispatch filter.
		return new \WP_REST_Response(
			array( 'session_key' => $session_key ),
			200
		);
	}

	/**
	 * Handle Web Clipper one-shot capture (token-protected).
	 *
	 * Creates a minimal draft post from text; optional url/title. Rate-limited and audit-logged.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_web_clipper_capture( \WP_REST_Request $request ) {
		if ( Plugin::is_api_disabled() ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'api_disabled',
					'message' => __( 'API access is currently disabled.', 'wp-pinch' ),
				),
				503
			);
		}

		// Rate limit by IP.
		$rate_key = 'wp_pinch_clipper_rate_' . substr( hash_hmac( 'sha256', self::get_client_ip(), wp_salt() ), 0, 16 );
		$rate     = (int) get_transient( $rate_key );
		if ( $rate >= 30 ) {
			$response = new \WP_REST_Response(
				array(
					'code'    => 'rate_limited',
					'message' => __( 'Too many capture requests. Please retry shortly.', 'wp-pinch' ),
				),
				429
			);
			$response->header( 'Retry-After', '60' );
			return $response;
		}
		set_transient( $rate_key, $rate + 1, 60 );

		$text  = trim( (string) $request->get_param( 'text' ) );
		$url   = is_string( $request->get_param( 'url' ) ) ? $request->get_param( 'url' ) : '';
		$title = is_string( $request->get_param( 'title' ) ) ? trim( $request->get_param( 'title' ) ) : '';

		if ( '' === $title && '' !== $url ) {
			$host  = wp_parse_url( $url, PHP_URL_HOST );
			$title = ( is_string( $host ) && '' !== $host ) ? $host : __( 'Captured link', 'wp-pinch' );
		}
		if ( '' === $title ) {
			$title = _x( 'Captured note', 'Web Clipper default post title', 'wp-pinch' );
		}

		$content = $text;
		if ( '' !== $url ) {
			$content = '<p><a href="' . esc_url( $url ) . '">' . esc_html( $url ) . "</a></p>\n\n" . $content;
		}

		$admins    = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		$author_id = ! empty( $admins ) ? (int) $admins[0] : 0;
		if ( 0 === $author_id ) {
			return new \WP_Error(
				'no_author',
				__( 'No administrator found to create the post.', 'wp-pinch' ),
				array( 'status' => 500 )
			);
		}

		$post_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'draft',
				'post_author'  => $author_id,
				'post_type'    => 'post',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return new \WP_Error(
				'create_failed',
				$post_id->get_error_message(),
				array( 'status' => 500 )
			);
		}

		$trace_id = self::get_trace_id();
		Audit_Table::insert(
			'web_clipper_capture',
			'rest',
			sprintf( 'Web Clipper capture created post %d.', $post_id ),
			array_merge(
				array(
					'post_id' => $post_id,
					'url'     => $url,
				),
				array_filter( array( 'trace_id' => $trace_id ) )
			)
		);

		return new \WP_REST_Response(
			array(
				'status'   => 'ok',
				'post_id'  => $post_id,
				'edit_url' => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
			),
			201
		);
	}

	/**
	 * Handle PinchDrop capture requests from OpenClaw channels.
	 *
	 * Auth is shared with hook receiver (Bearer token and optional HMAC headers).
	 * Uses request_id idempotency to suppress duplicate draft creation.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_pinchdrop_capture( \WP_REST_Request $request ) {
		if ( Plugin::is_api_disabled() ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'api_disabled',
					'message' => __( 'API access is currently disabled.', 'wp-pinch' ),
				),
				503
			);
		}

		$feature_enabled = Feature_Flags::is_enabled( 'pinchdrop_engine' );
		$setting_enabled = (bool) get_option( 'wp_pinch_pinchdrop_enabled', false );
		if ( ! $feature_enabled || ! $setting_enabled ) {
			return new \WP_Error(
				'pinchdrop_disabled',
				__( 'PinchDrop capture is currently disabled.', 'wp-pinch' ),
				array( 'status' => 503 )
			);
		}

		$allowed_sources_raw = (string) get_option( 'wp_pinch_pinchdrop_allowed_sources', '' );
		$allowed_sources     = array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( ',', $allowed_sources_raw ) ) ) );

		$source = sanitize_key( (string) $request->get_param( 'source' ) );
		if ( ! empty( $allowed_sources ) && ! in_array( $source, $allowed_sources, true ) ) {
			return new \WP_Error(
				'invalid_source',
				__( 'Source is not allowlisted for PinchDrop.', 'wp-pinch' ),
				array( 'status' => 403 )
			);
		}

		// Lightweight endpoint-specific rate limiting by source + client IP.
		$rate_key = 'wp_pinch_pdrop_rate_' . substr( hash_hmac( 'sha256', $source . '|' . self::get_client_ip(), wp_salt() ), 0, 16 );
		$rate     = (int) get_transient( $rate_key );
		if ( $rate >= 20 ) {
			$response = new \WP_REST_Response(
				array(
					'code'    => 'rate_limited',
					'message' => __( 'Too many capture requests. Please retry shortly.', 'wp-pinch' ),
				),
				429
			);
			$response->header( 'Retry-After', '60' );
			return $response;
		}
		set_transient( $rate_key, $rate + 1, 60 );

		$request_id = sanitize_text_field( (string) $request->get_param( 'request_id' ) );
		$idem_key   = '';
		if ( '' !== $request_id ) {
			$idem_key = 'wp_pinch_pdrop_idem_' . substr( hash_hmac( 'sha256', $request_id, wp_salt() ), 0, 32 );
			$cached   = get_transient( $idem_key );
			if ( is_array( $cached ) ) {
				$cached['deduplicated'] = true;
				return new \WP_REST_Response( $cached, 200 );
			}
		}

		$options = $request->get_param( 'options' );
		$options = is_array( $options ) ? $options : array();

		$default_outputs = get_option( 'wp_pinch_pinchdrop_default_outputs', array( 'post', 'product_update', 'changelog', 'social' ) );
		if ( ! is_array( $default_outputs ) || empty( $default_outputs ) ) {
			$default_outputs = array( 'post', 'product_update', 'changelog', 'social' );
		}

		$payload = array(
			'source_text'   => sanitize_textarea_field( (string) $request->get_param( 'text' ) ),
			'source'        => $source,
			'author'        => sanitize_text_field( (string) $request->get_param( 'author' ) ),
			'request_id'    => $request_id,
			'tone'          => sanitize_text_field( (string) ( $options['tone'] ?? '' ) ),
			'audience'      => sanitize_text_field( (string) ( $options['audience'] ?? '' ) ),
			'output_types'  => array_map( 'sanitize_key', (array) ( $options['output_types'] ?? $default_outputs ) ),
			'save_as_draft' => isset( $options['save_as_draft'] ) ? (bool) $options['save_as_draft'] : (bool) get_option( 'wp_pinch_pinchdrop_auto_save_drafts', true ),
			'save_as_note'  => ! empty( $options['save_as_note'] ),
		);

		$result = self::execute_ability_as_admin( 'wp-pinch/pinchdrop-generate', $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response_data = array(
			'status'       => 'ok',
			'request_id'   => $request_id,
			'source'       => $source,
			'deduplicated' => false,
			'result'       => $result,
		);

		if ( '' !== $idem_key ) {
			set_transient( $idem_key, $response_data, 15 * MINUTE_IN_SECONDS );
		}

		$trace_id = self::get_trace_id();
		Audit_Table::insert(
			'pinchdrop_capture',
			'webhook',
			sprintf( 'PinchDrop capture accepted from source "%s".', $source ),
			array_merge(
				array(
					'source'     => $source,
					'request_id' => $request_id,
				),
				array_filter( array( 'trace_id' => $trace_id ) )
			)
		);

		return new \WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Execute an ability in an administrator context for trusted system hooks.
	 *
	 * @param string $ability_name Ability name.
	 * @param array  $params       Ability params.
	 * @return array|\WP_Error
	 */
	private static function execute_ability_as_admin( string $ability_name, array $params ) {
		if ( ! function_exists( 'wp_execute_ability' ) ) {
			return new \WP_Error(
				'abilities_unavailable',
				__( 'WordPress Abilities API is not available.', 'wp-pinch' ),
				array( 'status' => 500 )
			);
		}

		$ability_names = Abilities::get_ability_names();
		if ( ! in_array( $ability_name, $ability_names, true ) ) {
			return new \WP_Error(
				'unknown_ability',
				__( 'Requested ability is not registered.', 'wp-pinch' ),
				array( 'status' => 404 )
			);
		}

		$disabled = get_option( 'wp_pinch_disabled_abilities', array() );
		if ( is_array( $disabled ) && in_array( $ability_name, $disabled, true ) ) {
			return new \WP_Error(
				'ability_disabled',
				__( 'Requested ability is currently disabled.', 'wp-pinch' ),
				array( 'status' => 403 )
			);
		}

		$previous_user  = get_current_user_id();
		$execution_user = OpenClaw_Role::get_execution_user_id();

		if ( 0 === $execution_user ) {
			return new \WP_Error(
				'no_execution_user',
				__( 'No user found to execute the ability. Create an OpenClaw agent user or ensure an administrator exists.', 'wp-pinch' ),
				array( 'status' => 500 )
			);
		}

		wp_set_current_user( $execution_user );
		$result = wp_execute_ability( $ability_name, $params );
		wp_set_current_user( $previous_user );

		if ( is_wp_error( $result ) ) {
			return new \WP_Error(
				'ability_error',
				$result->get_error_message(),
				array( 'status' => 422 )
			);
		}

		return $result;
	}

	/**
	 * Recursively sanitize ability params from incoming hooks.
	 *
	 * Strings are sanitized via sanitize_text_field(). Booleans, integers,
	 * and floats are cast to their native types. Nested arrays are processed
	 * recursively. Depth is capped to prevent stack overflow on malicious input.
	 *
	 * @param array $params Params to sanitize.
	 * @param int   $depth  Current recursion depth (max 5).
	 * @return array Sanitized params.
	 */
	private static function sanitize_params_recursive( array $params, int $depth = 0 ): array {
		if ( $depth > 5 ) {
			return array();
		}

		$sanitized = array();
		$count     = 0;
		foreach ( $params as $key => $value ) {
			if ( $count >= self::MAX_PARAMS_KEYS_PER_LEVEL ) {
				break;
			}
			$key = sanitize_key( $key );
			if ( is_string( $value ) ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			} elseif ( is_int( $value ) || is_float( $value ) || is_bool( $value ) ) {
				$sanitized[ $key ] = $value;
			} elseif ( is_array( $value ) ) {
				$sanitized[ $key ] = self::sanitize_params_recursive( $value, $depth + 1 );
			}
			// Silently drop objects and other non-scalar types.
			++$count;
		}
		return $sanitized;
	}

	/**
	 * Process SSE stream buffer: sanitize gateway "reply" in data lines and return safe output.
	 *
	 * Splits buffer into lines; for each "data: " line that is JSON with a "reply" key,
	 * sanitizes the reply with wp_kses_post() and re-encodes. Returns output to send
	 * and the remaining (incomplete) buffer for the next chunk.
	 *
	 * @param string $buffer Accumulated SSE stream text (after control-char strip).
	 * @return array{0: string, 1: string} [ output to echo, remaining buffer ].
	 */
	private static function process_sse_buffer( string $buffer ): array {
		$buffer = str_replace( "\r\n", "\n", $buffer );
		$parts  = explode( "\n", $buffer );
		// Last segment may be incomplete (no trailing newline); keep for next chunk.
		$carry  = array_pop( $parts );
		$output = array();

		foreach ( $parts as $line ) {
			if ( str_starts_with( $line, 'data:' ) ) {
				$payload = trim( substr( $line, 5 ) );
				if ( '' !== $payload && '{}' !== $payload ) {
					$decoded = json_decode( $payload, true );
					if ( is_array( $decoded ) && array_key_exists( 'reply', $decoded ) && is_string( $decoded['reply'] ) ) {
						$decoded['reply'] = self::sanitize_gateway_reply( $decoded['reply'] );
						$payload          = wp_json_encode( $decoded );
					}
				}
				$output[] = 'data: ' . $payload;
			} else {
				$output[] = $line;
			}
		}

		$out = implode( "\n", $output );
		return array( '' !== $out ? $out . "\n" : '', $carry ?? '' );
	}

	/**
	 * Cap chat reply length to the configured maximum (resource exhaustion protection).
	 *
	 * @param string $reply Raw reply from gateway.
	 * @return string Reply, possibly truncated with a notice.
	 */
	private static function cap_chat_reply( string $reply ): string {
		$max = (int) get_option( 'wp_pinch_chat_max_response_length', 200000 );
		if ( $max <= 0 || strlen( $reply ) <= $max ) {
			return $reply;
		}
		return substr( $reply, 0, $max ) . "\n\n[" . __( 'Response truncated for length.', 'wp-pinch' ) . ']';
	}

	/**
	 * Get the client IP address, respecting common proxy headers.
	 *
	 * @return string Client IP address.
	 */
	private static function get_client_ip(): string {
		$headers = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				// X-Forwarded-For may contain multiple IPs — take the first.
				if ( str_contains( $ip, ',' ) ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '0.0.0.0';
	}

	/**
	 * Handle status check — ping the OpenClaw gateway.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public static function handle_status( \WP_REST_Request $request ): \WP_REST_Response {
		if ( Plugin::is_api_disabled() ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'api_disabled',
					'message' => __( 'API access is currently disabled.', 'wp-pinch' ),
				),
				503
			);
		}

		if ( ! self::check_rate_limit() ) {
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
		$api_token   = \WP_Pinch\Settings::get_api_token();

		$result = array(
			'plugin_version' => WP_PINCH_VERSION,
			'configured'     => ! empty( $gateway_url ) && ! empty( $api_token ),
			'mcp_endpoint'   => rest_url( 'wp-pinch/mcp' ),
			'rate_limit'     => array(
				'limit' => max( 1, (int) get_option( 'wp_pinch_rate_limit', self::DEFAULT_RATE_LIMIT ) ),
			),
			'circuit'        => array(
				'state'           => Circuit_Breaker::get_state(),
				'retry_after'     => Circuit_Breaker::get_retry_after(),
				'last_failure_at' => Circuit_Breaker::get_last_failure_at(),
			),
			'gateway'        => array(
				'connected' => false,
			),
		);

		// Only expose the gateway URL and diagnostics to administrators.
		if ( current_user_can( 'manage_options' ) ) {
			$result['gateway']['url'] = $gateway_url ? trailingslashit( $gateway_url ) : '';
			$result['diagnostics']    = self::get_diagnostics();
		}

		if ( $result['configured'] ) {
			$status_url = trailingslashit( $gateway_url ) . 'api/v1/status';
			if ( wp_http_validate_url( $status_url ) ) {
				$response = wp_safe_remote_get(
					$status_url,
					array(
						'timeout' => 5,
						'headers' => array(
							'Authorization' => 'Bearer ' . $api_token,
						),
					)
				);
			} else {
				$response = new \WP_Error( 'invalid_gateway', __( 'Gateway URL failed security validation.', 'wp-pinch' ) );
			}

			if ( ! is_wp_error( $response ) ) {
				$code                           = wp_remote_retrieve_response_code( $response );
				$result['gateway']['connected'] = ( $code >= 200 && $code < 300 );
				$result['gateway']['status']    = $code;
			}
		}

		return new \WP_REST_Response( $result, 200 );
	}

	// =========================================================================
	// Health Endpoint
	// =========================================================================

	/**
	 * Lightweight public health check — no authentication required.
	 *
	 * Returns plugin status, circuit breaker state, and basic config info.
	 * Does NOT expose credentials or sensitive data.
	 *
	 * @return \WP_REST_Response
	 */
	public static function handle_health(): \WP_REST_Response {
		if ( Plugin::is_api_disabled() ) {
			return new \WP_REST_Response(
				array(
					'status'  => 'disabled',
					'code'    => 'api_disabled',
					'message' => __( 'API access is currently disabled.', 'wp-pinch' ),
					'version' => WP_PINCH_VERSION,
				),
				503
			);
		}

		$configured = ! empty( get_option( 'wp_pinch_gateway_url', '' ) )
			&& ! empty( \WP_Pinch\Settings::get_api_token() );

		$circuit_state = Circuit_Breaker::get_state();
		$retry_after   = Circuit_Breaker::get_retry_after();

		$result = array(
			'status'     => 'ok',
			'version'    => WP_PINCH_VERSION,
			'configured' => $configured,
			'rate_limit' => array(
				'limit' => max( 1, (int) get_option( 'wp_pinch_rate_limit', self::DEFAULT_RATE_LIMIT ) ),
			),
			'circuit'    => array(
				'state'           => $circuit_state,
				'retry_after'     => $retry_after,
				'last_failure_at' => Circuit_Breaker::get_last_failure_at(),
			),
			'timestamp'  => gmdate( 'c' ),
		);

		$response = new \WP_REST_Response( $result, 200 );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );

		return $response;
	}

	/**
	 * WordPress-aware diagnostics (manage_options only).
	 *
	 * Includes PHP version, plugin/theme update counts (no version leak),
	 * DB size estimate, disk space when safe, cron health, and error log tail.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_diagnostics(): array {
		$out = array(
			'php_version' => PHP_VERSION,
		);

		// Plugin/theme update counts — names only, no version numbers.
		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		if ( function_exists( 'wp_get_plugin_data' ) ) {
			wp_clean_plugins_cache();
		}
		$plugin_updates              = function_exists( 'get_plugin_updates' ) ? get_plugin_updates() : array();
		$theme_updates               = function_exists( 'wp_get_themes' ) ? get_theme_updates() : array();
		$out['plugin_updates_count'] = is_array( $plugin_updates ) ? count( $plugin_updates ) : 0;
		$out['theme_updates_count']  = is_array( $theme_updates ) ? count( $theme_updates ) : 0;

		// Database size estimate (cached 5 minutes).
		global $wpdb;
		if ( ! empty( $wpdb->dbname ) ) {
			$cache_key = 'db_size_' . $wpdb->dbname;
			$size      = wp_cache_get( $cache_key, 'wp_pinch_diagnostics' );
			if ( false === $size ) {
				$size = $wpdb->get_var(
					$wpdb->prepare(
						'SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = %s',
						$wpdb->dbname
					)
				);
				wp_cache_set( $cache_key, $size, 'wp_pinch_diagnostics', 300 );
			}
			$out['db_size_bytes'] = $size ? (int) $size : null;
		}

		// Disk space (only when ABSPATH is a normal path; avoid leaking mount points).
		$abspath = defined( 'ABSPATH' ) ? ABSPATH : '';
		if ( '' !== $abspath && function_exists( 'disk_free_space' ) && function_exists( 'disk_total_space' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- disk_* can fail on NFS/remote mounts.
			$free = @disk_free_space( $abspath );
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- disk_* can fail on NFS/remote mounts.
			$total = @disk_total_space( $abspath );
			if ( false !== $free && false !== $total && $total > 0 ) {
				$out['disk_free_bytes']  = $free;
				$out['disk_total_bytes'] = $total;
			}
		}

		// Cron health: next scheduled run or disabled.
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$out['cron'] = array(
				'status'  => 'disabled',
				'message' => 'WP-Cron is disabled (DISABLE_WP_CRON).',
			);
		} else {
			$crons = _get_cron_array();
			$next  = null;
			if ( is_array( $crons ) ) {
				foreach ( $crons as $timestamp => $hooks ) {
					if ( $timestamp > time() ) {
						$next = $timestamp;
						break;
					}
				}
			}
			$out['cron'] = array(
				'status'       => 'active',
				'next_run_gmt' => $next ? gmdate( 'c', $next ) : null,
			);
		}

		// Error log tail: last N lines, truncated (only when WP_DEBUG_LOG and file readable).
		$out['error_log_tail'] = array();
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG && defined( 'WP_CONTENT_DIR' ) ) {
			$log_file = WP_CONTENT_DIR . '/debug.log';
			if ( is_readable( $log_file ) && is_file( $log_file ) ) {
				$lines = array();
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reading debug.log; WP_Filesystem may require credentials.
				$handle = @fopen( $log_file, 'r' );
				if ( $handle ) {
					// Read last ~20 lines by seeking near end and reading chunks.
					$size  = fstat( $handle )['size'] ?? 0;
					$chunk = min( 8192, max( 512, (int) $size ) );
					if ( $size > $chunk ) {
						fseek( $handle, -$chunk, SEEK_END );
						fgets( $handle ); // Drop partial first line.
					}
					// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- Standard pattern for reading file lines.
					while ( ( $line = fgets( $handle ) ) !== false ) {
						$lines[] = $line;
					}
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Paired with fopen above.
					fclose( $handle );
					$lines                 = array_slice( $lines, -20 );
					$max_len               = 200;
					$out['error_log_tail'] = array_map(
						function ( $l ) use ( $max_len ) {
							$l = trim( $l );
							return mb_strlen( $l ) > $max_len ? mb_substr( $l, 0, $max_len ) . '…' : $l;
						},
						$lines
					);
				}
			}
		}

		return $out;
	}

	/**
	 * Build the site capability manifest for agent discovery.
	 *
	 * Returns post types, taxonomies, active plugin slugs (no versions), and feature flags.
	 * Filterable via wp_pinch_manifest.
	 *
	 * @return array{ post_types: string[], taxonomies: string[], plugins: string[], features: array<string, bool> }
	 */
	public static function get_site_manifest(): array {
		$post_types = array_keys( get_post_types( array( 'public' => true ), 'names' ) );
		$taxonomies = array_keys( get_taxonomies( array( 'public' => true ), 'names' ) );
		$active     = get_option( 'active_plugins', array() );
		$plugins    = array();
		foreach ( $active as $path ) {
			$slug = dirname( $path );
			if ( '.' === $slug ) {
				$slug = pathinfo( $path, PATHINFO_FILENAME );
			}
			$plugins[] = $slug;
		}
		$plugins  = array_values( array_unique( $plugins ) );
		$features = Feature_Flags::get_all();

		$manifest = array(
			'post_types' => $post_types,
			'taxonomies' => $taxonomies,
			'plugins'    => $plugins,
			'features'   => $features,
		);

		/**
		 * Filter the site capability manifest (post types, taxonomies, plugins, features).
		 *
		 * Allows other plugins (e.g. WooCommerce) to add or modify manifest data
		 * for agent discovery.
		 *
		 * @param array $manifest { post_types, taxonomies, plugins, features }
		 */
		return apply_filters( 'wp_pinch_manifest', $manifest );
	}

	/**
	 * List WP Pinch abilities for discovery (non-MCP clients).
	 *
	 * Returns ability names and site manifest. Full schema is available via MCP or core wp-abilities endpoint.
	 *
	 * @return \WP_REST_Response
	 */
	public static function handle_list_abilities(): \WP_REST_Response {
		$names = function_exists( 'wp_pinch_get_ability_names' ) ? wp_pinch_get_ability_names() : array();
		$list  = array();

		foreach ( $names as $name ) {
			$list[] = array( 'name' => $name );
		}

		$body = array(
			'abilities' => $list,
			'site'      => self::get_site_manifest(),
		);

		return new \WP_REST_Response( $body, 200 );
	}

	// =========================================================================
	// SSE Streaming
	// =========================================================================

	/**
	 * Handle chat message via Server-Sent Events streaming.
	 *
	 * Sends a request to the gateway and streams the response back
	 * to the client as SSE events. Falls back to buffered response
	 * if the gateway doesn't support streaming.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_chat_stream( \WP_REST_Request $request ) {
		if ( ! self::check_rate_limit() ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'rate_limited',
					'message' => __( 'Too many requests. Please wait a moment.', 'wp-pinch' ),
				),
				429
			);
		}

		// Circuit breaker check.
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
		$api_token   = \WP_Pinch\Settings::get_api_token();

		if ( empty( $gateway_url ) || empty( $api_token ) ) {
			return new \WP_Error(
				'not_configured',
				__( 'WP Pinch is not configured.', 'wp-pinch' ),
				array( 'status' => 503 )
			);
		}

		$message = $request->get_param( 'message' );
		$user    = wp_get_current_user();

		$user_prefix   = 'wp-pinch-chat-' . $user->ID;
		$requested_key = sanitize_key( $request->get_param( 'session_key' ) );
		$session_key   = ( '' !== $requested_key && str_starts_with( $requested_key, $user_prefix ) )
			? $requested_key
			: $user_prefix;

		$payload = array(
			'message'    => $message,
			'name'       => 'WordPress',
			'sessionKey' => $session_key,
			'wakeMode'   => 'now',
			'stream'     => true,
		);

		// Add optional agent routing when configured.
		$agent_id = get_option( 'wp_pinch_agent_id', '' );
		if ( '' !== $agent_id ) {
			$payload['agentId'] = sanitize_text_field( $agent_id );
		}

		// Chat-specific model config (separate from webhook settings).
		$chat_model    = get_option( 'wp_pinch_chat_model', '' );
		$chat_thinking = get_option( 'wp_pinch_chat_thinking', '' );
		$chat_timeout  = (int) get_option( 'wp_pinch_chat_timeout', 0 );

		// Per-request overrides from the block (admin-configured).
		$req_model = $request->get_param( 'model' );
		$req_agent = $request->get_param( 'agent_id' );

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

		// Per-block agent ID overrides the global setting.
		if ( $req_agent ) {
			$payload['agentId'] = sanitize_text_field( $req_agent );
		}

		/** This filter is documented in class-rest-controller.php */
		$payload = apply_filters( 'wp_pinch_chat_payload', $payload, $request );

		// Optional: cap concurrent SSE streams per IP to prevent connection flooding.
		$sse_max_per_ip = (int) get_option( 'wp_pinch_sse_max_connections_per_ip', 5 );
		if ( $sse_max_per_ip > 0 ) {
			$sse_key   = 'wp_pinch_sse_' . substr( hash_hmac( 'sha256', self::get_client_ip(), wp_salt() ), 0, 16 );
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

		// Set SSE headers and flush.
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' ); // Disable nginx buffering.

		if ( ob_get_level() ) {
			ob_end_flush();
		}

		// Open a streaming connection to the gateway.
		$request_headers = "Content-Type: application/json\r\n"
			. "Authorization: Bearer {$api_token}\r\n"
			. "Accept: text/event-stream\r\n";

		$context = stream_context_create(
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

		$stream_url = trailingslashit( $gateway_url ) . 'hooks/agent';

		// Validate the URL before opening the stream to prevent SSRF.
		// fopen() bypasses wp_safe_remote_* so we must check manually.
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

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.PHP.NoSilencedErrors.Discouraged -- fopen is used for streaming and error is handled below.
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

		// Check the HTTP response status from the stream metadata.
		// fopen() succeeding only means TCP connected — the gateway may still return an error.
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
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
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

		// Read the response in chunks; buffer by line, sanitize "reply" in data lines, then forward.
		$full_response    = '';
		$forwarded_events = false;
		$sse_buffer       = '';
		$max_response_len = (int) get_option( 'wp_pinch_chat_max_response_length', 200000 );
		if ( $max_response_len <= 0 ) {
			$max_response_len = 0;
		}

		while ( ! feof( $stream ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
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

			// Strip NUL and control chars, then process line-by-line and sanitize reply in data payloads.
			$clean                    = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $chunk );
			$sse_buffer              .= $clean;
			list( $out, $sse_buffer ) = self::process_sse_buffer( $sse_buffer );
			if ( '' !== $out ) {
				echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE text; reply sanitized in process_sse_buffer.
				$forwarded_events = true;
				if ( ob_get_level() ) {
					ob_flush();
				}
				flush();
			}
		}

		// Process remaining buffer (treat as final line so any complete "data: " line gets sanitized).
		if ( '' !== $sse_buffer ) {
			list( $out, $_ ) = self::process_sse_buffer( $sse_buffer . "\n" );
			if ( '' !== $out ) {
				echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- reply sanitized in process_sse_buffer.
				$forwarded_events = true;
			} else {
				echo $sse_buffer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partial line; control chars already stripped.
				$forwarded_events = true;
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $stream );

		// If the gateway didn't stream SSE events, wrap the raw response as a single event.
		if ( ! $forwarded_events ) {
			$data  = json_decode( $full_response, true );
			$reply = $data['response'] ?? $data['message'] ?? '';
			$reply = is_string( $reply ) ? self::sanitize_gateway_reply( $reply ) : '';

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

		$trace_id = self::get_trace_id();
		Audit_Table::insert(
			'chat_message',
			'chat',
			sprintf( 'Streamed chat message from user #%d.', $user->ID ),
			array_merge(
				array( 'user_id' => $user->ID ),
				array_filter( array( 'trace_id' => $trace_id ) )
			)
		);

		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}
		exit;
	}

	// =========================================================================
	// Rate Limiting
	// =========================================================================

	/**
	 * Per-user rate limiting for REST endpoints.
	 *
	 * Uses wp_cache (object cache) when a persistent backend is available
	 * for better performance and atomic increments. Falls back to transients.
	 *
	 * @return bool
	 */

	// =========================================================================
	// Ghost Writer endpoint
	// =========================================================================

	/**
	 * Handle Ghost Writer requests (list drafts or trigger ghostwriting).
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_ghostwrite( \WP_REST_Request $request ) {
		if ( Plugin::is_api_disabled() ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'api_disabled',
					'message' => __( 'API access is currently disabled.', 'wp-pinch' ),
				),
				503
			);
		}

		if ( ! self::check_rate_limit() ) {
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

		$action  = $request->get_param( 'action' );
		$post_id = (int) $request->get_param( 'post_id' );

		if ( 'list' === $action ) {
			$user_id = get_current_user_id();

			// Only show own drafts unless user can edit others.
			$scope  = current_user_can( 'edit_others_posts' ) ? 0 : $user_id;
			$drafts = Ghost_Writer::assess_drafts( $scope );

			if ( empty( $drafts ) ) {
				return new \WP_REST_Response(
					array(
						'reply' => __( 'No abandoned drafts found. Your draft graveyard is empty — either you finish what you start, or you never start at all.', 'wp-pinch' ),
					),
					200
				);
			}

			$lines = array();
			foreach ( array_slice( $drafts, 0, 10 ) as $draft ) {
				$lines[] = sprintf(
					'#%d — "%s" (%d words, %d%% done, %d days old, score: %d)',
					$draft['post_id'],
					$draft['title'],
					$draft['word_count'],
					$draft['estimated_completion'],
					$draft['days_abandoned'],
					$draft['resurrection_score']
				);
			}

			$reply = sprintf(
				/* translators: %d: number of drafts */
				__( "Found %d abandoned drafts:\n\n", 'wp-pinch' ),
				count( $drafts )
			) . implode( "\n", $lines );

			if ( count( $drafts ) > 10 ) {
				$reply .= "\n\n" . sprintf(
					/* translators: %d: number of additional drafts not shown in the list */
					__( '...and %d more. Use /ghostwrite [post_id] to resurrect one.', 'wp-pinch' ),
					count( $drafts ) - 10
				);
			} else {
				$reply .= "\n\n" . __( 'Use /ghostwrite [post_id] to resurrect one.', 'wp-pinch' );
			}

			return new \WP_REST_Response( array( 'reply' => $reply ), 200 );
		}

		if ( 'write' === $action ) {
			if ( $post_id < 1 ) {
				return new \WP_Error(
					'missing_post_id',
					__( 'Please specify a post ID. Usage: /ghostwrite 123', 'wp-pinch' ),
					array( 'status' => 400 )
				);
			}

			$result = Ghost_Writer::ghostwrite( $post_id, true );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$reply = sprintf(
				/* translators: 1: post title, 2: edit URL */
				__( "Draft \"%1\$s\" has been resurrected. The ghost writer has spoken in your voice.\n\nEdit it here: %2\$s", 'wp-pinch' ),
				$result['title'],
				$result['edit_url']
			);

			return new \WP_REST_Response( array( 'reply' => $reply ), 200 );
		}

		return new \WP_Error(
			'invalid_action',
			__( 'Invalid action. Use "list" or "write".', 'wp-pinch' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Handle Molt requests — repackage post into multiple output formats.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_molt( \WP_REST_Request $request ) {
		if ( Plugin::is_api_disabled() ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'api_disabled',
					'message' => __( 'API access is currently disabled.', 'wp-pinch' ),
				),
				503
			);
		}

		if ( ! self::check_rate_limit() ) {
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

		$post_id      = (int) $request->get_param( 'post_id' );
		$output_types = $request->get_param( 'output_types' );
		$output_types = is_array( $output_types ) ? $output_types : array();

		if ( $post_id < 1 ) {
			return new \WP_Error(
				'missing_post_id',
				__( 'Please specify a post ID. Usage: /molt 123', 'wp-pinch' ),
				array( 'status' => 400 )
			);
		}

		if ( ! current_user_can( 'read_post', $post_id ) ) {
			return new \WP_Error(
				'forbidden',
				__( 'You do not have permission to read this post.', 'wp-pinch' ),
				array( 'status' => 403 )
			);
		}

		$result = Molt::molt( $post_id, $output_types );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$reply = Molt::format_for_chat( $result );

		return new \WP_REST_Response(
			array(
				'output' => $result,
				'reply'  => $reply,
			),
			200
		);
	}

	/**
	 * Build a sanitized summary of params for audit context (no sensitive or huge values).
	 *
	 * @param array $params Request params.
	 * @return array<string, mixed> Keys and truncated scalars only.
	 */
	private static function sanitize_audit_params( array $params ): array {
		$out = array();
		$max = 5;
		foreach ( $params as $k => $v ) {
			if ( $max-- <= 0 ) {
				break;
			}
			$key = sanitize_key( (string) $k );
			if ( '' === $key ) {
				continue;
			}
			if ( is_scalar( $v ) ) {
				$s           = (string) $v;
				$out[ $key ] = mb_strlen( $s ) > 80 ? mb_substr( $s, 0, 80 ) . '…' : $s;
			} elseif ( is_array( $v ) ) {
				$out[ $key ] = array_keys( $v );
			} else {
				$out[ $key ] = gettype( $v );
			}
		}
		return $out;
	}

	/**
	 * Build a sanitized summary of ability result for audit context.
	 *
	 * @param mixed $result Ability return value (array or scalar).
	 * @return array<string, mixed>|array Empty or small summary.
	 */
	private static function sanitize_audit_result( $result ): array {
		if ( ! is_array( $result ) ) {
			return array( 'type' => gettype( $result ) );
		}
		$out = array();
		if ( isset( $result['post_id'] ) ) {
			$out['post_id'] = (int) $result['post_id'];
		}
		if ( isset( $result['id'] ) && ! isset( $out['post_id'] ) ) {
			$out['id'] = (int) $result['id'];
		}
		if ( count( $out ) < 3 && ! empty( $result ) ) {
			$out['keys'] = array_slice( array_keys( $result ), 0, 10 );
		}
		return $out;
	}

	/**
	 * Sanitize gateway reply for safe output. When strict option is on, strips comments and instruction-like text and disallows iframe/object/embed/form.
	 *
	 * @param string $reply Raw reply from gateway.
	 * @return string Sanitized reply.
	 */
	private static function sanitize_gateway_reply( string $reply ): string {
		if ( '' === trim( $reply ) ) {
			return $reply;
		}
		if ( ! (bool) get_option( 'wp_pinch_gateway_reply_strict_sanitize', false ) ) {
			return wp_kses_post( $reply );
		}
		// Strip HTML comments to reduce instruction-injection surface.
		$reply = preg_replace( '/<!--.*?-->/s', '', $reply );
		// Redact instruction-like lines (same patterns as content sent to LLMs).
		$reply = Prompt_Sanitizer::sanitize( $reply );
		// Stricter allowed HTML: post tags minus iframe, object, embed, form.
		$allowed = wp_kses_allowed_html( 'post' );
		unset( $allowed['iframe'], $allowed['object'], $allowed['embed'], $allowed['form'] );
		return wp_kses( $reply, $allowed );
	}

	/**
	 * Ability names that count as writes for the daily budget. Filterable.
	 *
	 * @return string[]
	 */
	private static function get_write_ability_names(): array {
		return apply_filters( 'wp_pinch_write_abilities', self::$write_abilities );
	}

	private static function is_write_ability( string $ability_name ): bool {
		return in_array( $ability_name, self::get_write_ability_names(), true );
	}

	/**
	 * Transient key for daily write count (date-based, resets at midnight UTC).
	 *
	 * @return string
	 */
	private static function daily_write_count_key(): string {
		return 'wp_pinch_daily_writes_' . gmdate( 'Y-m-d' );
	}

	private static function get_daily_write_count(): int {
		$key = self::daily_write_count_key();
		if ( wp_using_ext_object_cache() ) {
			return (int) wp_cache_get( $key, 'wp-pinch' );
		}
		return (int) get_transient( $key );
	}

	private static function increment_daily_write_count(): void {
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
	 * Check if executing one more write would exceed the daily cap.
	 *
	 * @return \WP_Error|null Error if over cap, null if allowed.
	 */
	private static function check_daily_write_budget(): ?\WP_Error {
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
	 * Send at most one alert per day when usage reaches the configured threshold.
	 */
	private static function maybe_send_daily_write_alert(): void {
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

	private static function check_rate_limit(): bool {
		$user_id = get_current_user_id();
		$key     = 'wp_pinch_rest_rate_' . $user_id;
		$limit   = max( 1, (int) get_option( 'wp_pinch_rate_limit', self::DEFAULT_RATE_LIMIT ) );

		// Prefer object cache when a persistent backend (Redis, Memcached) is available.
		if ( wp_using_ext_object_cache() ) {
			$count = (int) wp_cache_get( $key, 'wp-pinch' );

			if ( $count >= $limit ) {
				return false;
			}

			if ( 0 === $count ) {
				wp_cache_set( $key, 1, 'wp-pinch', 60 );
			} else {
				wp_cache_incr( $key, 1, 'wp-pinch' );
			}

			return true;
		}

		// Fallback to transients.
		$count = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return false;
		}

		if ( 0 === $count ) {
			set_transient( $key, 1, 60 );
		} else {
			$option_key = '_transient_' . $key;
			update_option( $option_key, $count + 1, false );
		}

		return true;
	}
}
