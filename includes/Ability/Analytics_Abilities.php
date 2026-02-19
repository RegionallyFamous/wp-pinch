<?php
/**
 * Analytics and maintenance abilities — site health, content health, search, export, digest, related posts, synthesize.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

use WP_Pinch\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * Analytics abilities.
 */
class Analytics_Abilities {
	use Analytics_Execute_Trait;

	/**
	 * Register analytics abilities.
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		Abilities::register_ability(
			'wp-pinch/site-health',
			__( 'Site Health', 'wp-pinch' ),
			__( 'Get site health summary: PHP, WordPress, database, disk usage.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => new \stdClass(),
			),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_site_health' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/content-health-report',
			__( 'Content Health Report', 'wp-pinch' ),
			__( 'Get a content health report: missing alt text, broken internal links, thin content, orphaned media.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'limit'     => array(
						'type'        => 'integer',
						'default'     => 50,
						'description' => 'Max items per category (1–100).',
					),
					'min_words' => array(
						'type'        => 'integer',
						'default'     => 300,
						'description' => 'Minimum word count to not flag as thin content.',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_content_health_report' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/recent-activity',
			__( 'Recent Activity', 'wp-pinch' ),
			__( 'Get recent posts, comments, and user registrations.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'limit' => array(
						'type'    => 'integer',
						'default' => 10,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_recent_activity' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/search-content',
			__( 'Search Content', 'wp-pinch' ),
			__( 'Full-text search across posts, pages, and custom post types.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'query' ),
				'properties' => array(
					'query'     => array( 'type' => 'string' ),
					'post_type' => array(
						'type'    => 'string',
						'default' => 'any',
					),
					'per_page'  => array(
						'type'    => 'integer',
						'default' => 20,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_search_content' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/export-data',
			__( 'Export Data', 'wp-pinch' ),
			__( 'Export post, user, or comment data as JSON.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'type' ),
				'properties' => array(
					'type'     => array(
						'type' => 'string',
						'enum' => array( 'posts', 'users', 'comments' ),
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 100,
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
					),
				),
			),
			array( 'type' => 'object' ),
			'export',
			array( __CLASS__, 'execute_export_data' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/site-digest',
			__( 'Memory Bait (Site Digest)', 'wp-pinch' ),
			__( 'Compact export of recent posts: title, excerpt, and key taxonomy terms. For agent memory-core or system prompt so the agent knows your site.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'per_page'  => array(
						'type'    => 'integer',
						'default' => 10,
					),
					'post_type' => array(
						'type'    => 'string',
						'default' => 'post',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_site_digest' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/related-posts',
			__( 'Echo Net (Related Posts)', 'wp-pinch' ),
			__( 'Given a post ID, return posts that link to it (backlinks) or share taxonomy terms. Enables "you wrote about X before" and graph-like discovery.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'post_id' ),
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Post ID to find related posts for.',
					),
					'limit'   => array(
						'type'    => 'integer',
						'default' => 20,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_related_posts' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/synthesize',
			__( 'Weave (Synthesize)', 'wp-pinch' ),
			__( 'Given a query, search posts, fetch matching content, and return a payload for LLM synthesis. First-draft synthesis; human refines.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'query' ),
				'properties' => array(
					'query'     => array( 'type' => 'string' ),
					'per_page'  => array(
						'type'    => 'integer',
						'default' => 10,
					),
					'post_type' => array(
						'type'    => 'string',
						'default' => 'post',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_synthesize' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/analytics-narratives',
			__( 'Analytics Narratives', 'wp-pinch' ),
			__( 'Turn site digest or recent activity data into a brief narrative: what happened this week, what is new.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'source' => array(
						'type'        => 'string',
						'default'     => 'site_digest',
						'description' => __( 'Data source: site_digest or recent_activity.', 'wp-pinch' ),
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_analytics_narratives' )
		);

		Abilities::register_ability(
			'wp-pinch/submit-conversational-form',
			__( 'Submit Conversational Form', 'wp-pinch' ),
			__( 'Submit collected form data from a conversation. Provide fields (name/value pairs) and optionally a webhook URL to POST the payload to.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'fields' ),
				'properties' => array(
					'fields'      => array(
						'type'        => 'array',
						'description' => __( 'Form fields: array of objects with "name" and "value".', 'wp-pinch' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'name'  => array( 'type' => 'string' ),
								'value' => array( 'type' => 'string' ),
							),
						),
					),
					'webhook_url' => array(
						'type'        => 'string',
						'description' => __( 'Optional URL to POST the form payload to (JSON).', 'wp-pinch' ),
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_submit_conversational_form' )
		);
	}
}
