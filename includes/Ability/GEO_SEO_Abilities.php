<?php
/**
 * GEO and SEO abilities: llms.txt generation, bulk metadata.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

defined( 'ABSPATH' ) || exit;

use WP_Pinch\Abilities;

/**
 * GEO (Generative Engine Optimization) and bulk SEO abilities.
 */
class GEO_SEO_Abilities {
	use GEO_SEO_Execute_Trait;

	/**
	 * Default path for llms.txt relative to ABSPATH.
	 */
	const LLMS_TXT_FILENAME = 'llms.txt';

	/**
	 * Maximum posts to process in one bulk-seo-meta call.
	 */
	const BULK_SEO_MAX = 50;

	/**
	 * Register GEO and SEO abilities.
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		Abilities::register_ability(
			'wp-pinch/generate-llms-txt',
			__( 'Generate llms.txt', 'wp-pinch' ),
			__( 'Generate an llms.txt file for AI crawlers (GEO). Uses site name, description, and structure. Optionally write to the site root.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'write' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'If true, write the generated content to llms.txt at the site root.', 'wp-pinch' ),
					),
				),
			),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_generate_llms_txt' )
		);

		Abilities::register_ability(
			'wp-pinch/get-llms-txt',
			__( 'Get llms.txt', 'wp-pinch' ),
			__( 'Read the current llms.txt file content from the site root, if it exists.', 'wp-pinch' ),
			array( 'type' => 'object' ),
			array( 'type' => 'object' ),
			'manage_options',
			array( __CLASS__, 'execute_get_llms_txt' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/bulk-seo-meta',
			__( 'Bulk SEO Metadata', 'wp-pinch' ),
			__( 'Generate SEO titles and meta descriptions for multiple posts. Provide post_ids or a query (post_type, limit). Optionally apply updates.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'post_ids'  => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'description' => __( 'Specific post IDs to process.', 'wp-pinch' ),
					),
					'post_type' => array(
						'type'    => 'string',
						'default' => 'post',
					),
					'limit'     => array(
						'type'    => 'integer',
						'default' => 20,
					),
					'apply'     => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'If true, update posts with generated title and meta description.', 'wp-pinch' ),
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_bulk_seo_meta' )
		);

		Abilities::register_ability(
			'wp-pinch/suggest-internal-links',
			__( 'Suggest Internal Links', 'wp-pinch' ),
			__( 'Given a post ID or search query, return topically related posts to link to (uses RAG index when enabled).', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => __( 'Post ID to suggest links for (uses its title/excerpt as query).', 'wp-pinch' ),
					),
					'query'   => array(
						'type'        => 'string',
						'description' => __( 'Search query when post_id is not provided.', 'wp-pinch' ),
					),
					'limit'   => array(
						'type'    => 'integer',
						'default' => 10,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_suggest_internal_links' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/generate-schema-markup',
			__( 'Generate Schema Markup', 'wp-pinch' ),
			__( 'Analyze post content and return JSON-LD schema (Article, Product, FAQ, HowTo, Recipe, etc.).', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'post_id' ),
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => __( 'Post ID to generate schema for.', 'wp-pinch' ),
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_generate_schema_markup' )
		);

		Abilities::register_ability(
			'wp-pinch/suggest-seo-improvements',
			__( 'Suggest SEO Improvements', 'wp-pinch' ),
			__( 'Analyze a post for inline SEO: keyword density, heading structure, and meta title/description suggestions.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'post_id' ),
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => __( 'Post ID to analyze.', 'wp-pinch' ),
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_suggest_seo_improvements' )
		);
	}
}
