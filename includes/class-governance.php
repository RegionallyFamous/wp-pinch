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
		'content_freshness'  => DAY_IN_SECONDS,
		'seo_health'         => DAY_IN_SECONDS,
		'comment_sweep'      => 6 * HOUR_IN_SECONDS,
		'broken_links'       => WEEK_IN_SECONDS,
		'security_scan'      => DAY_IN_SECONDS,
		'draft_necromancer'  => WEEK_IN_SECONDS,
		'spaced_resurfacing' => DAY_IN_SECONDS,
		'tide_report'        => DAY_IN_SECONDS,
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
		add_action( 'wp_pinch_governance_draft_necromancer', array( __CLASS__, 'task_draft_necromancer' ) );
		add_action( 'wp_pinch_governance_spaced_resurfacing', array( __CLASS__, 'task_spaced_resurfacing' ) );
		add_action( 'wp_pinch_governance_tide_report', array( __CLASS__, 'task_tide_report' ) );

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
		$findings = self::get_content_freshness_findings();
		if ( empty( $findings ) ) {
			return;
		}
		$threshold_days = (int) apply_filters( 'wp_pinch_freshness_threshold', 180 );
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
		$findings = self::get_seo_health_findings();
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
		$findings = self::get_comment_sweep_findings();
		$pending  = $findings['pending_comments'] ?? array();
		$spam     = $findings['spam_count'] ?? 0;
		if ( empty( $pending ) && 0 === $spam ) {
			return;
		}
		self::deliver_findings(
			'comment_sweep',
			$findings,
			sprintf(
				'%d comments awaiting moderation, %d in spam.',
				count( $pending ),
				$spam
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
				'no_found_rows'  => true,
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

	/**
	 * Draft necromancer — surface abandoned drafts worth resurrecting.
	 *
	 * Requires the ghost_writer feature flag to be enabled.
	 * Finds drafts across all authors, ranks them by resurrection potential,
	 * and delivers findings so OpenClaw can remind authors to finish their work.
	 *
	 * @since 2.3.0
	 */
	public static function task_draft_necromancer(): void {
		if ( ! Feature_Flags::is_enabled( 'ghost_writer' ) ) {
			return;
		}
		$findings = self::get_draft_necromancer_findings();
		if ( empty( $findings ) ) {
			return;
		}
		self::deliver_findings(
			'draft_necromancer',
			$findings,
			sprintf(
				/* translators: %d: number of abandoned drafts */
				'%d abandoned drafts found worth resurrecting. The draft graveyard has company.',
				count( $findings )
			)
		);
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
			'content_freshness'  => __( 'Content Freshness — flag posts not updated in 180+ days', 'wp-pinch' ),
			'seo_health'         => __( 'SEO Health — check titles, alt text, content length', 'wp-pinch' ),
			'comment_sweep'      => __( 'Comment Sweep — pending moderation and spam count', 'wp-pinch' ),
			'broken_links'       => __( 'Broken Links — check for dead links in content', 'wp-pinch' ),
			'security_scan'      => __( 'Security Scan — outdated software, debug mode, file editing', 'wp-pinch' ),
			'draft_necromancer'  => __( 'Draft Necromancer — surface abandoned drafts worth resurrecting', 'wp-pinch' ),
			'spaced_resurfacing' => __( 'Spaced Resurfacing — notes not updated in N days (revisit list)', 'wp-pinch' ),
			'tide_report'        => __( 'Tide Report — daily digest: drafts, SEO, comments in one webhook', 'wp-pinch' ),
		);
	}

	/**
	 * Spaced Resurfacing — posts not updated in N days (optionally by category/tag).
	 */
	public static function task_spaced_resurfacing(): void {
		$days     = (int) apply_filters( 'wp_pinch_spaced_resurfacing_days', 30 );
		$findings = self::get_spaced_resurfacing_findings( $days, '', '', 100 );
		if ( empty( $findings ) ) {
			return;
		}
		self::deliver_findings(
			'spaced_resurfacing',
			$findings,
			sprintf(
				/* translators: %1$d: number of posts, %2$d: days */
				__( '%1$d posts have not been updated in over %2$d days.', 'wp-pinch' ),
				count( $findings ),
				$days
			)
		);
	}

	/**
	 * Get spaced resurfacing findings (posts not updated in N days). Public for ability use.
	 *
	 * @param int    $days     Minimum days since last update.
	 * @param string $category Optional category slug.
	 * @param string $tag      Optional tag slug.
	 * @param int    $limit    Max number of posts.
	 * @return array List of post summaries (id, title, url, modified).
	 */
	public static function get_spaced_resurfacing_findings( int $days = 30, string $category = '', string $tag = '', int $limit = 50 ): array {
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$args   = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'date_query'     => array(
				array(
					'column' => 'post_modified_gmt',
					'before' => $cutoff,
				),
			),
			'orderby'        => 'modified',
			'order'          => 'ASC',
		);
		if ( '' !== $category ) {
			$args['category_name'] = $category;
		}
		if ( '' !== $tag ) {
			$args['tag'] = $tag;
		}
		$args['no_found_rows'] = true;
		$posts                 = get_posts( $args );
		$out                   = array();
		foreach ( $posts as $post ) {
			$out[] = array(
				'post_id'  => $post->ID,
				'title'    => $post->post_title,
				'url'      => get_permalink( $post->ID ),
				'modified' => $post->post_modified,
			);
		}
		return $out;
	}

	/**
	 * Tide Report — bundle findings from content freshness, SEO, comments (and optionally drafts) into one webhook.
	 *
	 * Delivers "here's what needs attention" to Slack/Telegram. Fits resurface-and-remind.
	 */
	public static function task_tide_report(): void {
		$bundle = array();

		$freshness = self::get_content_freshness_findings();
		if ( ! empty( $freshness ) ) {
			$bundle['content_freshness'] = $freshness;
		}

		$seo = self::get_seo_health_findings();
		if ( ! empty( $seo ) ) {
			$bundle['seo_health'] = $seo;
		}

		$comments = self::get_comment_sweep_findings();
		if ( ! empty( $comments['pending_comments'] ) || ( isset( $comments['spam_count'] ) && $comments['spam_count'] > 0 ) ) {
			$bundle['comment_sweep'] = $comments;
		}

		if ( Feature_Flags::is_enabled( 'ghost_writer' ) ) {
			$drafts = self::get_draft_necromancer_findings();
			if ( ! empty( $drafts ) ) {
				$bundle['draft_necromancer'] = $drafts;
			}
		}

		$spaced = self::get_spaced_resurfacing_findings( 30, '', '', 50 );
		if ( ! empty( $spaced ) ) {
			$bundle['spaced_resurfacing'] = $spaced;
		}

		if ( empty( $bundle ) ) {
			return;
		}

		$parts = array();
		if ( ! empty( $bundle['content_freshness'] ) ) {
			$parts[] = count( $bundle['content_freshness'] ) . ' stale posts';
		}
		if ( ! empty( $bundle['seo_health'] ) ) {
			$parts[] = count( $bundle['seo_health'] ) . ' SEO issues';
		}
		if ( ! empty( $bundle['comment_sweep'] ) ) {
			$pending = count( $bundle['comment_sweep']['pending_comments'] ?? array() );
			$spam    = $bundle['comment_sweep']['spam_count'] ?? 0;
			$parts[] = $pending . ' pending, ' . $spam . ' spam';
		}
		if ( ! empty( $bundle['draft_necromancer'] ) ) {
			$parts[] = count( $bundle['draft_necromancer'] ) . ' drafts worth resurrecting';
		}
		if ( ! empty( $bundle['spaced_resurfacing'] ) ) {
			$parts[] = count( $bundle['spaced_resurfacing'] ) . ' notes to resurface';
		}
		$summary = __( 'Tide Report: ', 'wp-pinch' ) . implode( '; ', $parts ) . '.';

		self::deliver_findings( 'tide_report', $bundle, $summary );
	}

	/**
	 * Return content freshness findings (no delivery).
	 *
	 * @return array
	 */
	private static function get_content_freshness_findings(): array {
		$threshold_days = (int) apply_filters( 'wp_pinch_freshness_threshold', 180 );
		$cutoff         = gmdate( 'Y-m-d H:i:s', time() - ( $threshold_days * DAY_IN_SECONDS ) );
		$page           = 1;
		$findings       = array();
		do {
			$stale = get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'paged'          => $page,
					'no_found_rows'  => true,
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
					'days_stale'    => (int) floor( ( time() - strtotime( $post->post_modified_gmt ) ) / DAY_IN_SECONDS ),
					'url'           => get_permalink( $post->ID ),
				);
			}
			$stale_count = count( $stale );
			++$page;
		} while ( 100 === $stale_count && $page <= 10 );
		return $findings;
	}

	/**
	 * Return SEO health findings (no delivery).
	 *
	 * @return array
	 */
	private static function get_seo_health_findings(): array {
		$findings = array();
		$page     = 1;
		do {
			$posts = get_posts(
				array(
					'post_type'      => array( 'post', 'page' ),
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'paged'          => $page,
					'no_found_rows'  => true,
				)
			);
			foreach ( $posts as $post ) {
				$issues = array();
				if ( mb_strlen( $post->post_title ) < 20 ) {
					$issues[] = 'Title is shorter than 20 characters.';
				}
				if ( mb_strlen( $post->post_title ) > 60 ) {
					$issues[] = 'Title exceeds 60 characters (may truncate in SERPs).';
				}
				$stripped   = wp_strip_all_tags( $post->post_content );
				$word_count = preg_match_all( '/[\w\p{L}\p{N}]+/u', $stripped );
				if ( $word_count < 100 ) {
					$issues[] = 'Content has fewer than 100 words.';
				}
				if ( ! has_post_thumbnail( $post->ID ) ) {
					$issues[] = 'No featured image set.';
				}
				preg_match_all( '/<img[^>]+>/i', $post->post_content, $img_matches );
				foreach ( $img_matches[0] ?? array() as $img ) {
					if ( ! preg_match( '/\balt\s*=\s*["\']/', $img ) ) {
						$issues[] = 'Image found without alt attribute.';
						break;
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
		} while ( 100 === $posts_count && $page <= 10 );
		return $findings;
	}

	/**
	 * Return comment sweep findings (no delivery).
	 *
	 * @return array
	 */
	private static function get_comment_sweep_findings(): array {
		$pending    = get_comments(
			array(
				'status' => 'hold',
				'number' => 50,
			)
		);
		$spam_count = (int) wp_count_comments()->spam;
		return array(
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
	}

	/**
	 * Return draft necromancer findings (no delivery).
	 *
	 * @return array
	 */
	private static function get_draft_necromancer_findings(): array {
		$drafts = Ghost_Writer::assess_drafts();
		if ( empty( $drafts ) ) {
			return array();
		}
		return array_values(
			array_filter(
				$drafts,
				function ( $draft ) {
					return $draft['resurrection_score'] >= 20;
				}
			)
		);
	}

	// =========================================================================
	// Content health (for content-health-report ability)
	// =========================================================================

	/**
	 * Posts/pages with at least one image missing alt text.
	 *
	 * @param int $limit Max number of posts to return. Default 50.
	 * @return array<int, array{post_id: int, title: string, url: string}>
	 */
	public static function get_missing_alt_findings( int $limit = 50 ): array {
		$findings = array();
		$posts    = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => min( $limit, 200 ),
				'no_found_rows'  => true,
			)
		);
		foreach ( $posts as $post ) {
			preg_match_all( '/<img[^>]+>/i', $post->post_content, $img_matches );
			foreach ( $img_matches[0] ?? array() as $img ) {
				if ( ! preg_match( '/\balt\s*=\s*["\']/', $img ) ) {
					$findings[] = array(
						'post_id' => $post->ID,
						'title'   => $post->post_title,
						'url'     => get_permalink( $post->ID ),
					);
					break;
				}
			}
		}
		return $findings;
	}

	/**
	 * Internal links (same site) that point to non-existent or unpublished content.
	 *
	 * @param int $limit Max number of broken links to return. Default 50.
	 * @return array<int, array{post_id: int, title: string, url: string, link_url: string, reason: string}>
	 */
	public static function get_broken_internal_links_findings( int $limit = 50 ): array {
		$findings  = array();
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$posts     = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'no_found_rows'  => true,
			)
		);
		foreach ( $posts as $post ) {
			if ( count( $findings ) >= $limit ) {
				break;
			}
			preg_match_all( '/href=["\']([^"\']+)["\']/i', $post->post_content, $matches );
			$urls = array_unique( $matches[1] ?? array() );
			foreach ( $urls as $link_url ) {
				if ( count( $findings ) >= $limit ) {
					break;
				}
				$link_url = trim( $link_url );
				if ( preg_match( '/^(#|mailto:|tel:|javascript:|data:)/i', $link_url ) ) {
					continue;
				}
				$absolute = $link_url;
				if ( ! preg_match( '/^https?:\/\//', $link_url ) ) {
					$absolute = home_url( $link_url );
				}
				$link_host = wp_parse_url( $absolute, PHP_URL_HOST );
				if ( $link_host !== $home_host ) {
					continue;
				}
				$post_id = url_to_postid( $absolute );
				if ( 0 === $post_id ) {
					$findings[] = array(
						'post_id'  => $post->ID,
						'title'    => $post->post_title,
						'url'      => get_permalink( $post->ID ),
						'link_url' => $link_url,
						'reason'   => 'target_not_found',
					);
				} else {
					$target = get_post( $post_id );
					if ( ! $target || 'publish' !== $target->post_status ) {
						$findings[] = array(
							'post_id'  => $post->ID,
							'title'    => $post->post_title,
							'url'      => get_permalink( $post->ID ),
							'link_url' => $link_url,
							'reason'   => 'target_not_published',
						);
					}
				}
			}
		}
		return $findings;
	}

	/**
	 * Posts with word count below threshold (thin content).
	 *
	 * @param int $min_words Minimum words to not be considered thin. Default 300.
	 * @param int $limit     Max number of posts to return. Default 50.
	 * @return array<int, array{post_id: int, title: string, url: string, word_count: int}>
	 */
	public static function get_thin_content_findings( int $min_words = 300, int $limit = 50 ): array {
		$findings = array();
		$posts    = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => min( $limit, 200 ),
				'no_found_rows'  => true,
			)
		);
		foreach ( $posts as $post ) {
			$stripped   = wp_strip_all_tags( $post->post_content );
			$word_count = 0;
			if ( preg_match_all( '/[\w\p{L}\p{N}]+/u', $stripped, $m ) ) {
				$word_count = count( $m[0] );
			}
			if ( $word_count < $min_words ) {
				$findings[] = array(
					'post_id'    => $post->ID,
					'title'      => $post->post_title,
					'url'        => get_permalink( $post->ID ),
					'word_count' => $word_count,
				);
			}
		}
		return $findings;
	}

	/**
	 * Media attachments not attached to any post (post_parent = 0).
	 *
	 * @param int $limit Max number of attachment IDs to return. Default 50.
	 * @return array<int, array{attachment_id: int, url: string, title: string}>
	 */
	public static function get_orphaned_media_findings( int $limit = 50 ): array {
		$findings    = array();
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => min( $limit, 200 ),
				'post_parent'    => 0,
				'no_found_rows'  => true,
			)
		);
		foreach ( $attachments as $att ) {
			$findings[] = array(
				'attachment_id' => $att->ID,
				'url'           => wp_get_attachment_url( $att->ID ),
				'title'         => $att->post_title,
			);
		}
		return $findings;
	}
}
