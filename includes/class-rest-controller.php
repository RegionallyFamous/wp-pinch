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
	 * Wire hooks.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'add_security_headers' ), 10, 3 );
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
										'rest_invalid_param',
										__( 'Message cannot be empty.', 'wp-pinch' ),
										array( 'status' => 400 )
									);
								}
								if ( mb_strlen( $value ) > self::MAX_MESSAGE_LENGTH ) {
									return new \WP_Error(
										'rest_invalid_param',
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
										'rest_invalid_param',
										__( 'Text cannot be empty.', 'wp-pinch' ),
										array( 'status' => 400 )
									);
								}
								if ( mb_strlen( $value ) > 20000 ) {
									return new \WP_Error(
										'rest_invalid_param',
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
											'rest_invalid_param',
											__( 'Message cannot be empty.', 'wp-pinch' ),
											array( 'status' => 400 )
										);
									}
									if ( mb_strlen( $value ) > self::MAX_MESSAGE_LENGTH ) {
										return new \WP_Error(
											'rest_invalid_param',
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
											'rest_invalid_param',
											__( 'Message cannot be empty.', 'wp-pinch' ),
											array( 'status' => 400 )
										);
									}
									if ( mb_strlen( $value ) > self::MAX_MESSAGE_LENGTH ) {
										return new \WP_Error(
											'rest_invalid_param',
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
							'enum'              => array( 'execute_ability', 'run_governance', 'ping' ),
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
								// Recursively sanitize all string values in the params object.
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
		if ( 0 !== strpos( $route, '/wp-pinch/' ) ) {
			return $response;
		}

		$response->header( 'X-Content-Type-Options', 'nosniff' );
		$response->header( 'X-Frame-Options', 'DENY' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow' );
		$response->header( 'Referrer-Policy', 'strict-origin-when-cross-origin' );
		$response->header( 'Permissions-Policy', 'camera=(), microphone=(), geolocation=()' );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );

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
	 * Permission callback — require edit_posts.
	 *
	 * @return bool|\WP_Error
	 */
	public static function check_permission() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error(
				'rest_forbidden',
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
		$api_token = get_option( 'wp_pinch_api_token', '' );

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
	 * Handle an incoming webhook from OpenClaw.
	 *
	 * Supports three actions:
	 * - execute_ability: Run a registered WordPress ability.
	 * - run_governance: Trigger a governance task.
	 * - ping: Health check.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_incoming_hook( \WP_REST_Request $request ) {
		$action = $request->get_param( 'action' );

		Audit_Table::insert(
			'incoming_hook',
			'webhook',
			sprintf( 'Incoming hook received: %s', $action ),
			array(
				'action'  => $action,
				'ability' => $request->get_param( 'ability' ),
				'task'    => $request->get_param( 'task' ),
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
				// require a user context. Temporarily elevate to the first admin
				// so abilities can pass their capability checks.
				$previous_user = get_current_user_id();
				$admins        = get_users(
					array(
						'role'   => 'administrator',
						'number' => 1,
						'fields' => 'ID',
					)
				);

				if ( empty( $admins ) ) {
					return new \WP_Error(
						'no_admin',
						__( 'No administrator account found to execute the ability.', 'wp-pinch' ),
						array( 'status' => 500 )
					);
				}

				wp_set_current_user( (int) $admins[0] );

				$result = wp_execute_ability( $ability_name, is_array( $params ) ? $params : array() );

				// Restore previous user context.
				wp_set_current_user( $previous_user );

				if ( is_wp_error( $result ) ) {
					return new \WP_Error(
						'ability_error',
						$result->get_error_message(),
						array( 'status' => 422 )
					);
				}

				Audit_Table::insert(
					'ability_executed',
					'incoming_hook',
					sprintf( 'Ability "%s" executed via incoming hook.', $ability_name ),
					array( 'ability' => $ability_name )
				);

				return new \WP_REST_Response(
					array(
						'status' => 'ok',
						'result' => $result,
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

				Audit_Table::insert(
					'governance_triggered',
					'incoming_hook',
					sprintf( 'Governance task "%s" triggered via incoming hook.', $task ),
					array( 'task' => $task )
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
	 * Handle chat message — forward to OpenClaw.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_chat( \WP_REST_Request $request ) {
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
		$api_token   = get_option( 'wp_pinch_api_token', '' );

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

		$response = wp_safe_remote_post(
			trailingslashit( $gateway_url ) . 'hooks/agent',
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
			Audit_Table::insert( 'gateway_error', 'chat', $response->get_error_message() );
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
		$result = array(
			'reply'       => is_string( $reply ) ? wp_kses_post( $reply ) : __( 'Received an unexpected response from the gateway.', 'wp-pinch' ),
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

		Audit_Table::insert(
			'chat_message',
			'chat',
			sprintf( 'Chat message from user #%d.', $user->ID ),
			array( 'user_id' => $user->ID )
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
		$gateway_url = get_option( 'wp_pinch_gateway_url', '' );
		$api_token   = get_option( 'wp_pinch_api_token', '' );

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

		// Strict rate limiting for public endpoint: 3 requests per minute per IP.
		// Placed after config/circuit-breaker checks so unavailable states don't consume rate limit.
		// Uses HMAC-SHA256 with wp_salt() to avoid MD5 weaknesses and prevent key prediction.
		$ip_hash = 'wp_pinch_pub_rate_' . substr( hash_hmac( 'sha256', self::get_client_ip(), wp_salt() ), 0, 16 );
		$count   = (int) get_transient( $ip_hash );

		if ( $count >= 3 ) {
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

		$response = wp_safe_remote_post(
			trailingslashit( $gateway_url ) . 'hooks/agent',
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
		$result = array(
			'reply'       => is_string( $reply ) ? wp_kses_post( $reply ) : __( 'No response received.', 'wp-pinch' ),
			'session_key' => $session_key,
		);

		/** This filter is documented in class-rest-controller.php */
		$result = apply_filters( 'wp_pinch_chat_response', $result, $data );

		Audit_Table::insert(
			'public_chat_message',
			'chat',
			'Public chat message.',
			array( 'session_prefix' => substr( $session_key, 0, 24 ) )
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
	 * Handle PinchDrop capture requests from OpenClaw channels.
	 *
	 * Auth is shared with hook receiver (Bearer token and optional HMAC headers).
	 * Uses request_id idempotency to suppress duplicate draft creation.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_pinchdrop_capture( \WP_REST_Request $request ) {
		$feature_enabled = Feature_Flags::is_enabled( 'pinchdrop_engine' );
		$setting_enabled = (bool) get_option( 'wp_pinch_pinchdrop_enabled', false );
		if ( ! $feature_enabled || ! $setting_enabled ) {
			return new \WP_Error(
				'pinchdrop_disabled',
				__( 'PinchDrop capture is currently disabled.', 'wp-pinch' ),
				array( 'status' => 404 )
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

		Audit_Table::insert(
			'pinchdrop_capture',
			'webhook',
			sprintf( 'PinchDrop capture accepted from source "%s".', $source ),
			array(
				'source'     => $source,
				'request_id' => $request_id,
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

		$previous_user = get_current_user_id();
		$admins        = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);

		if ( empty( $admins ) ) {
			return new \WP_Error(
				'no_admin',
				__( 'No administrator account found to execute the ability.', 'wp-pinch' ),
				array( 'status' => 500 )
			);
		}

		wp_set_current_user( (int) $admins[0] );
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
		foreach ( $params as $key => $value ) {
			$key = sanitize_key( $key );
			if ( is_string( $value ) ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			} elseif ( is_int( $value ) || is_float( $value ) || is_bool( $value ) ) {
				$sanitized[ $key ] = $value;
			} elseif ( is_array( $value ) ) {
				$sanitized[ $key ] = self::sanitize_params_recursive( $value, $depth + 1 );
			}
			// Silently drop objects and other non-scalar types.
		}
		return $sanitized;
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
		$api_token   = get_option( 'wp_pinch_api_token', '' );

		$result = array(
			'plugin_version' => WP_PINCH_VERSION,
			'configured'     => ! empty( $gateway_url ) && ! empty( $api_token ),
			'mcp_endpoint'   => rest_url( 'wp-pinch/mcp' ),
			'gateway'        => array(
				'connected' => false,
			),
		);

		// Only expose the gateway URL to administrators.
		if ( current_user_can( 'manage_options' ) ) {
			$result['gateway']['url'] = $gateway_url ? trailingslashit( $gateway_url ) : '';
		}

		if ( $result['configured'] ) {
			$response = wp_safe_remote_get(
				trailingslashit( $gateway_url ) . 'api/v1/status',
				array(
					'timeout' => 5,
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_token,
					),
				)
			);

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
		$configured = ! empty( get_option( 'wp_pinch_gateway_url', '' ) )
			&& ! empty( get_option( 'wp_pinch_api_token', '' ) );

		$circuit_state = Circuit_Breaker::get_state();
		$retry_after   = Circuit_Breaker::get_retry_after();

		$result = array(
			'status'     => 'ok',
			'version'    => WP_PINCH_VERSION,
			'configured' => $configured,
			'circuit'    => array(
				'state'       => $circuit_state,
				'retry_after' => $retry_after,
			),
			'timestamp'  => gmdate( 'c' ),
		);

		$response = new \WP_REST_Response( $result, 200 );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );

		return $response;
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
		$api_token   = get_option( 'wp_pinch_api_token', '' );

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

		// Read the response in chunks and forward to the client.
		$full_response    = '';
		$forwarded_events = false;

		while ( ! feof( $stream ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			$chunk = fread( $stream, 4096 );
			if ( false === $chunk || '' === $chunk ) {
				break;
			}
			$full_response .= $chunk;

			// If the gateway is sending SSE events, forward them after stripping
			// any characters that could break the SSE framing or inject headers.
			// SSE protocol data is text-only — strip NUL bytes and limit to valid SSE lines.
			if ( str_contains( $chunk, 'event:' ) || str_contains( $chunk, 'data:' ) ) {
				// Remove NUL bytes and non-printable control chars (except \n, \r, \t).
				$safe_chunk = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $chunk );
				echo $safe_chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE text protocol passthrough; control chars stripped above.
				$forwarded_events = true;
				if ( ob_get_level() ) {
					ob_flush();
				}
				flush();
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $stream );

		// If the gateway didn't stream SSE events, wrap the raw response as a single event.
		if ( ! $forwarded_events ) {
			$data  = json_decode( $full_response, true );
			$reply = $data['response'] ?? $data['message'] ?? '';
			$reply = is_string( $reply ) ? wp_kses_post( $reply ) : '';

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

		Audit_Table::insert(
			'chat_message',
			'chat',
			sprintf( 'Streamed chat message from user #%d.', $user->ID ),
			array( 'user_id' => $user->ID )
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
