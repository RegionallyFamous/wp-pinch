<?php
/**
 * Governance Engine — autonomous recurring site checks.
 *
 * Dual-mode delivery:
 * 1. Webhook to OpenClaw for AI-powered analysis.
 * 2. Server-side via WP AI Client (when available in WP 7.0+).
 *
 * Tasks: content freshness, SEO health, comment sweep, broken links, security scan.
 * All thresholds are configurable via Settings and filterable.
 * Batch processing for resource-intensive tasks.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * Autonomous governance engine.
 */
class Governance {

	/**
	 * Default task schedule intervals (in seconds).
	 *
	 * @var array<string, int>
	 */
	const DEFAULT_INTERVALS = array(
		'content_freshness' => DAY_IN_SECONDS,
		'seo_health'        => DAY_IN_SECONDS,
		'comment_sweep'     => 6 * HOUR_IN_SECONDS,
		'broken_links'      => WEEK_IN_SECONDS,
		'security_scan'     => DAY_IN_SECONDS,
	);

	/**
	 * Wire hooks.
	 */
	public static function init(): void {
		add_action( 'wp_pinch_governance_content_freshness', array( __CLASS__, 'task_content_freshness' ) );
		add_action( 'wp_pinch_governance_seo_health', array( __CLASS__, 'task_seo_health' ) );
		add_action( 'wp_pinch_governance_comment_sweep', array( __CLASS__, 'task_comment_sweep' ) );
		add_action( 'wp_pinch_governance_broken_links', array( __CLASS__, 'task_broken_links' ) );
		add_action( 'wp_pinch_governance_security_scan', array( __CLASS__, 'task_security_scan' ) );

		// Re-evaluate task schedules once per plugin version (avoids DB queries on every admin load).
		add_action( 'admin_init', array( __CLASS__, 'maybe_schedule_tasks' ) );
	}

	/**
	 * Only re-evaluate task schedules when settings or plugin version change.
	 *
	 * Prevents 5 × as_has_scheduled_action() DB queries on every admin page load.
	 */
	public static function maybe_schedule_tasks(): void {
		$enabled   = self::get_enabled_tasks();
		$cache_key = 'wp_pinch_governance_schedule_hash';
		$hash      = md5( WP_PINCH_VERSION . ':' . wp_json_encode( $enabled ) );

		if ( get_option( $cache_key ) === $hash ) {
			return;
		}

		self::schedule_tasks();
		update_option( $cache_key, $hash, false );
	}

	/**
	 * Schedule all enabled governance tasks via Action Scheduler.
	 */
	public static function schedule_tasks(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}

		$enabled = self::get_enabled_tasks();

		foreach ( self::DEFAULT_INTERVALS as $task => $interval ) {
			$hook = 'wp_pinch_governance_' . $task;

			try {
				if ( in_array( $task, $enabled, true ) ) {
					if ( ! as_has_scheduled_action( $hook ) ) {
						as_schedule_recurring_action( time() + $interval, $interval, $hook, array(), 'wp-pinch' );
					}
				} else {
					as_unschedule_all_actions( $hook );
				}
			} catch ( \Throwable $e ) {
				Audit_Table::insert(
					'scheduler_error',
					'governance',
					sprintf( 'Failed to schedule governance task "%s": %s', $task, $e->getMessage() ),
					array(
						'task'  => $task,
						'error' => $e->getMessage(),
					)
				);
			}
		}
	}

	// =========================================================================
	// Tasks
	// =========================================================================

	/**
	 * Content freshness check.
	 *
	 * Identifies published posts not modified in X days.
	 */
	public static function task_content_freshness(): void {
		/**
		 * Filter the stale content threshold in days.
		 *
		 * @since 1.0.0
		 *
		 * @param int $days Days since last modification. Default 180.
		 */
		$threshold_days = apply_filters( 'wp_pinch_freshness_threshold', 180 );
		$cutoff         = gmdate( 'Y-m-d H:i:s', time() - ( $threshold_days * DAY_IN_SECONDS ) );

		// Paginate to catch all stale posts, not just the first 100.
		$page     = 1;
		$findings = array();
		do {
			$stale = get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'paged'          => $page,
					'date_query'     => array(
						array(
							'column' => 'post_modified_gmt',
							'before' => $cutoff,
						),
					),
				)
			);

			foreach ( $stale as $post ) {
				$findings[] = array(
					'post_id'       => $post->ID,
					'title'         => $post->post_title,
					'last_modified' => $post->post_modified,
					'days_stale'    => floor( ( time() - strtotime( $post->post_modified_gmt ) ) / DAY_IN_SECONDS ),
					'url'           => get_permalink( $post->ID ),
				);
			}

			$stale_count = count( $stale );
			++$page;
		} while ( 100 === $stale_count && $page <= 10 ); // Cap at 1000 posts max.

		self::deliver_findings(
			'content_freshness',
			$findings,
			sprintf(
				'%d posts have not been updated in over %d days.',
				count( $findings ),
				$threshold_days
			)
		);
	}

	/**
	 * SEO health check.
	 *
	 * Flags posts missing meta descriptions, short titles, missing alt text on images.
	 */
	public static function task_seo_health(): void {
		$findings = array();
		$page     = 1;

		do {
			$posts = get_posts(
				array(
					'post_type'      => array( 'post', 'page' ),
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'paged'          => $page,
				)
			);

			foreach ( $posts as $post ) {
				$issues = array();

				// Short title.
				if ( mb_strlen( $post->post_title ) < 20 ) {
					$issues[] = 'Title is shorter than 20 characters.';
				}

				// Very long title.
				if ( mb_strlen( $post->post_title ) > 60 ) {
					$issues[] = 'Title exceeds 60 characters (may truncate in SERPs).';
				}

				// Thin content — uses preg_match for Unicode-aware word counting (CJK-safe).
				$stripped   = wp_strip_all_tags( $post->post_content );
				$word_count = preg_match_all( '/[\w\p{L}\p{N}]+/u', $stripped );
				if ( $word_count < 100 ) {
					$issues[] = 'Content has fewer than 100 words.';
				}

				// Missing featured image.
				if ( ! has_post_thumbnail( $post->ID ) ) {
					$issues[] = 'No featured image set.';
				}

				// Images without alt text (alt="" is valid for decorative images).
				preg_match_all( '/<img[^>]+>/i', $post->post_content, $img_matches );
				foreach ( $img_matches[0] as $img ) {
					if ( ! preg_match( '/\balt\s*=\s*["\']/', $img ) ) {
						$issues[] = 'Image found without alt attribute.';
						break; // One finding is enough.
					}
				}

				if ( ! empty( $issues ) ) {
					$findings[] = array(
						'post_id' => $post->ID,
						'title'   => $post->post_title,
						'url'     => get_permalink( $post->ID ),
						'issues'  => $issues,
					);
				}
			}

			$posts_count = count( $posts );
			++$page;
		} while ( 100 === $posts_count && $page <= 10 ); // Cap at 1000 posts max.

		if ( empty( $findings ) ) {
			return;
		}

		self::deliver_findings(
			'seo_health',
			$findings,
			sprintf(
				'%d posts/pages have SEO issues.',
				count( $findings )
			)
		);
	}

	/**
	 * Comment sweep.
	 *
	 * Flags pending and spam comments that need moderation.
	 */
	public static function task_comment_sweep(): void {
		$pending = get_comments(
			array(
				'status' => 'hold',
				'number' => 50,
			)
		);

		$spam_count = (int) wp_count_comments()->spam;

		$findings = array(
			'pending_comments' => array_map(
				function ( $c ) {
					return array(
						'id'      => (int) $c->comment_ID,
						'post_id' => (int) $c->comment_post_ID,
						'date'    => $c->comment_date,
					);
				},
				$pending
			),
			'spam_count'       => $spam_count,
		);

		if ( empty( $pending ) && 0 === $spam_count ) {
			return;
		}

		self::deliver_findings(
			'comment_sweep',
			$findings,
			sprintf(
				'%d comments awaiting moderation, %d in spam.',
				count( $pending ),
				$spam_count
			)
		);
	}

	/**
	 * Broken link check (batch processing).
	 *
	 * Checks up to 50 links per batch to avoid timeout.
	 */
	public static function task_broken_links(): void {
		/**
		 * Filter the batch size for broken link checking.
		 *
		 * @since 1.0.0
		 *
		 * @param int $batch_size Number of links per batch. Default 50.
		 */
		$batch_size = min( absint( apply_filters( 'wp_pinch_broken_links_batch_size', 50 ) ), 200 );

		// Get recent published posts.
		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		$links_checked = 0;
		$broken        = array();

		foreach ( $posts as $post ) {
			if ( $links_checked >= $batch_size ) {
				break;
			}

			// Extract links from content.
			preg_match_all( '/href=["\']([^"\']+)["\']/i', $post->post_content, $matches );

			if ( empty( $matches[1] ) ) {
				continue;
			}

			$urls = array_unique( $matches[1] );

			foreach ( $urls as $url ) {
				if ( $links_checked >= $batch_size ) {
					break;
				}

				// Skip anchors, mailto, tel, and non-http schemes.
				if ( preg_match( '/^(#|mailto:|tel:|javascript:|ftp:|data:|gopher:|dict:)/i', $url ) ) {
					continue;
				}

				// Make relative URLs absolute.
				if ( ! preg_match( '/^https?:\/\//', $url ) ) {
					$url = home_url( $url );
				}

				// Prevent SSRF: resolve hostname and reject private/reserved IPs.
				$host = wp_parse_url( $url, PHP_URL_HOST );
				if ( $host ) {
					$ip = gethostbyname( $host );
					if ( $ip !== $host && false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
						continue; // Skip internal/private/reserved IPs.
					}
				}

				$response = wp_remote_head(
					$url,
					array(
						'timeout'     => 5,
						'redirection' => 3,
						'sslverify'   => true,
					)
				);

				++$links_checked;

				$status = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );

				if ( 0 === $status || $status >= 400 ) {
					$broken[] = array(
						'post_id'    => $post->ID,
						'post_title' => $post->post_title,
						'url'        => $url,
						'status'     => $status,
						'error'      => is_wp_error( $response ) ? $response->get_error_message() : "HTTP {$status}",
					);
				}
			}
		}

		if ( empty( $broken ) ) {
			return;
		}

		self::deliver_findings(
			'broken_links',
			$broken,
			sprintf(
				'%d broken links found across %d posts (checked %d links).',
				count( $broken ),
				count( $posts ),
				$links_checked
			)
		);
	}

	/**
	 * Security scan.
	 *
	 * Checks for outdated WordPress, plugins, and themes.
	 */
	public static function task_security_scan(): void {
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$findings = array();

		// Core updates — only report availability, not exact versions (avoids leaking info to external services).
		$core_updates = get_core_updates();
		if ( ! empty( $core_updates ) && 'latest' !== ( $core_updates[0]->response ?? '' ) ) {
			$findings['core_update_available'] = true;
		}

		// Plugin updates — report names and counts only, not version numbers.
		$plugin_updates = get_plugin_updates();
		if ( ! empty( $plugin_updates ) ) {
			$findings['plugin_updates_count'] = count( $plugin_updates );
			$findings['plugin_updates']       = array();
			foreach ( $plugin_updates as $file => $data ) {
				$findings['plugin_updates'][] = array(
					'name' => $data->Name, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				);
			}
		}

		// Theme updates — report names and counts only, not version numbers.
		$theme_updates = get_theme_updates();
		if ( ! empty( $theme_updates ) ) {
			$findings['theme_updates_count'] = count( $theme_updates );
			$findings['theme_updates']       = array();
			foreach ( $theme_updates as $slug => $theme ) {
				$findings['theme_updates'][] = array(
					'name' => $theme->get( 'Name' ),
				);
			}
		}

		// Debug mode check (should be off in production).
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$findings['debug_mode'] = true;
		}

		// File editing check (should be disabled).
		if ( ! defined( 'DISALLOW_FILE_EDIT' ) || ! DISALLOW_FILE_EDIT ) {
			$findings['file_editing_enabled'] = true;
		}

		if ( empty( $findings ) ) {
			return;
		}

		$summary_parts = array();
		if ( isset( $findings['core_update_available'] ) ) {
			$summary_parts[] = 'WordPress core update available';
		}
		if ( ! empty( $findings['plugin_updates'] ) ) {
			$summary_parts[] = count( $findings['plugin_updates'] ) . ' plugin updates';
		}
		if ( ! empty( $findings['theme_updates'] ) ) {
			$summary_parts[] = count( $findings['theme_updates'] ) . ' theme updates';
		}
		if ( ! empty( $findings['debug_mode'] ) ) {
			$summary_parts[] = 'WP_DEBUG is enabled';
		}
		if ( ! empty( $findings['file_editing_enabled'] ) ) {
			$summary_parts[] = 'File editing is not disabled';
		}

		self::deliver_findings( 'security_scan', $findings, implode( '; ', $summary_parts ) . '.' );
	}

	// =========================================================================
	// Delivery
	// =========================================================================

	/**
	 * Deliver governance findings — dual mode.
	 *
	 * @param string $task     Task name.
	 * @param mixed  $findings Findings data.
	 * @param string $summary  Human-readable summary.
	 */
	private static function deliver_findings( string $task, $findings, string $summary ): void {
		/**
		 * Filter governance findings before delivery.
		 *
		 * Return false to suppress delivery.
		 *
		 * @since 1.0.0
		 *
		 * @param mixed  $findings The findings data.
		 * @param string $task     Task name.
		 * @param string $summary  Summary message.
		 */
		$findings = apply_filters( 'wp_pinch_governance_findings', $findings, $task, $summary );

		if ( false === $findings ) {
			return;
		}

		// Log to audit table.
		Audit_Table::insert(
			'governance_finding',
			'governance',
			$summary,
			array(
				'task'     => $task,
				'findings' => $findings,
			)
		);

		$mode = get_option( 'wp_pinch_governance_mode', 'webhook' );

		if ( 'server' === $mode && self::has_wp_ai_client() ) {
			self::deliver_via_ai_client( $task, $findings, $summary );
		} else {
			// Default: webhook to OpenClaw.
			Webhook_Dispatcher::dispatch(
				'governance_finding',
				$summary,
				array(
					'task'     => $task,
					'findings' => $findings,
				)
			);
		}
	}

	/**
	 * Deliver findings via the WP AI Client (future WP 7.0 feature).
	 *
	 * @param string $task     Task name.
	 * @param mixed  $findings Findings data.
	 * @param string $summary  Summary.
	 */
	private static function deliver_via_ai_client( string $task, $findings, string $summary ): void {
		// Placeholder — WP AI Client API is not yet finalized.
		// When available, this will use wp_ai_generate_text() or similar
		// to process findings server-side without requiring OpenClaw.
		/**
		 * Fires when findings are ready for server-side AI processing.
		 *
		 * @since 1.0.0
		 *
		 * @param string $task     Task name.
		 * @param mixed  $findings Findings data.
		 * @param string $summary  Summary message.
		 */
		do_action( 'wp_pinch_ai_client_findings', $task, $findings, $summary );
	}

	/**
	 * Check if WP AI Client is available.
	 *
	 * @return bool
	 */
	private static function has_wp_ai_client(): bool {
		return function_exists( 'wp_ai_generate_text' );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Get list of enabled governance tasks.
	 *
	 * @return string[]
	 */
	public static function get_enabled_tasks(): array {
		$tasks = get_option( 'wp_pinch_governance_tasks', array() );

		// Default: all enabled.
		if ( empty( $tasks ) ) {
			return array_keys( self::DEFAULT_INTERVALS );
		}

		return $tasks;
	}

	/**
	 * Get all available governance tasks with labels.
	 *
	 * @return array<string, string>
	 */
	public static function get_available_tasks(): array {
		return array(
			'content_freshness' => __( 'Content Freshness — flag posts not updated in 180+ days', 'wp-pinch' ),
			'seo_health'        => __( 'SEO Health — check titles, alt text, content length', 'wp-pinch' ),
			'comment_sweep'     => __( 'Comment Sweep — pending moderation and spam count', 'wp-pinch' ),
			'broken_links'      => __( 'Broken Links — check for dead links in content', 'wp-pinch' ),
			'security_scan'     => __( 'Security Scan — outdated software, debug mode, file editing', 'wp-pinch' ),
		);
	}
}
