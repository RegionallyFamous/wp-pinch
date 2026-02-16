<?php
/**
 * Ghost Writer and Molt abilities (conditional on feature flags).
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

use WP_Pinch\Abilities;
use WP_Pinch\Feature_Flags;
use WP_Pinch\Ghost_Writer;
use WP_Pinch\Molt;

defined( 'ABSPATH' ) || exit;

/**
 * Ghost Writer and Molt abilities.
 */
class GhostWriter_Molt_Abilities {

	/**
	 * Register Ghost Writer abilities when ghost_writer flag is on; register Molt when molt flag is on.
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		if ( Feature_Flags::is_enabled( 'ghost_writer' ) ) {
			Abilities::register_ability(
				'wp-pinch/analyze-voice',
				__( 'Analyze Author Voice', 'wp-pinch' ),
				__( 'Analyze an author\'s published posts and build a writing voice profile.', 'wp-pinch' ),
				array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'        => 'integer',
							'default'     => 0,
							'description' => 'User ID to analyze. Defaults to the current user.',
						),
					),
				),
				array( 'type' => 'object' ),
				'edit_posts',
				array( __CLASS__, 'execute_analyze_voice' )
			);

			Abilities::register_ability(
				'wp-pinch/list-abandoned-drafts',
				__( 'List Abandoned Drafts', 'wp-pinch' ),
				__( 'Find abandoned drafts ranked by resurrection potential. Your draft graveyard, sorted by who still has a pulse.', 'wp-pinch' ),
				array(
					'type'       => 'object',
					'properties' => array(
						'days'    => array(
							'type'        => 'integer',
							'default'     => 0,
							'description' => 'Minimum days since last modification. 0 = use global threshold.',
						),
						'user_id' => array(
							'type'        => 'integer',
							'default'     => 0,
							'description' => 'Scope to a single author. 0 = all authors.',
						),
					),
				),
				array( 'type' => 'object' ),
				'edit_posts',
				array( __CLASS__, 'execute_list_abandoned_drafts' ),
				true
			);

			Abilities::register_ability(
				'wp-pinch/ghostwrite',
				__( 'Ghostwrite Draft', 'wp-pinch' ),
				__( 'Complete an abandoned draft in the original author\'s voice using their voice profile.', 'wp-pinch' ),
				array(
					'type'       => 'object',
					'required'   => array( 'post_id' ),
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'ID of the draft post to complete.',
						),
						'apply'   => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => 'Whether to save the generated content directly to the draft.',
						),
					),
				),
				array( 'type' => 'object' ),
				'edit_posts',
				array( __CLASS__, 'execute_ghostwrite' )
			);
		}

		if ( Feature_Flags::is_enabled( 'molt' ) ) {
			Abilities::register_ability(
				'wp-pinch/molt',
				__( 'Molt Content', 'wp-pinch' ),
				__( 'Repackage a post into multiple formats: social, email snippet, FAQ block, thread, summary, meta description, pull quote, key takeaways, CTA variants.', 'wp-pinch' ),
				array(
					'type'       => 'object',
					'required'   => array( 'post_id' ),
					'properties' => array(
						'post_id'      => array(
							'type'        => 'integer',
							'description' => 'ID of the post to repackage.',
						),
						'output_types' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'default'     => array(),
							'description' => 'Format keys to generate. Empty = all formats.',
						),
					),
				),
				array( 'type' => 'object' ),
				'edit_posts',
				array( __CLASS__, 'execute_molt' )
			);
		}
	}

	/**
	 * Execute the analyze-voice ability.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_analyze_voice( array $input ): array {
		$user_id = absint( $input['user_id'] ?? 0 );
		if ( 0 === $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( get_current_user_id() !== $user_id && ! current_user_can( 'edit_others_posts' ) ) {
			return array( 'error' => __( 'You do not have permission to analyze another author\'s voice.', 'wp-pinch' ) );
		}
		$profile = Ghost_Writer::analyze_voice( $user_id );
		if ( is_wp_error( $profile ) ) {
			return array( 'error' => $profile->get_error_message() );
		}
		return array(
			'user_id'             => $user_id,
			'post_count_analyzed' => $profile['post_count_analyzed'],
			'voice'               => $profile['voice'],
			'metrics'             => $profile['metrics'],
		);
	}

	/**
	 * Execute the list-abandoned-drafts ability.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_list_abandoned_drafts( array $input ): array {
		$user_id = absint( $input['user_id'] ?? 0 );
		if ( $user_id > 0 && get_current_user_id() !== $user_id && ! current_user_can( 'edit_others_posts' ) ) {
			return array( 'error' => __( 'You do not have permission to view another author\'s drafts.', 'wp-pinch' ) );
		}
		if ( 0 === $user_id && ! current_user_can( 'edit_others_posts' ) ) {
			$user_id = get_current_user_id();
		}
		$drafts = Ghost_Writer::assess_drafts( $user_id );
		return array(
			'count'  => count( $drafts ),
			'drafts' => $drafts,
		);
	}

	/**
	 * Execute the ghostwrite ability.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_ghostwrite( array $input ): array {
		$post_id = absint( $input['post_id'] ?? 0 );
		$apply   = ! empty( $input['apply'] );
		if ( ! $post_id ) {
			return array( 'error' => __( 'A valid post_id is required.', 'wp-pinch' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'error' => __( 'You do not have permission to edit this post.', 'wp-pinch' ) );
		}
		$result = Ghost_Writer::ghostwrite( $post_id, $apply );
		if ( is_wp_error( $result ) ) {
			return array( 'error' => $result->get_error_message() );
		}
		return $result;
	}

	/**
	 * Execute the molt ability.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_molt( array $input ): array {
		$post_id = absint( $input['post_id'] ?? 0 );
		if ( ! $post_id ) {
			return array( 'error' => __( 'A valid post_id is required.', 'wp-pinch' ) );
		}
		if ( ! current_user_can( 'read_post', $post_id ) ) {
			return array( 'error' => __( 'You do not have permission to read this post.', 'wp-pinch' ) );
		}
		$output_types = isset( $input['output_types'] ) && is_array( $input['output_types'] )
			? array_map( 'sanitize_key', $input['output_types'] )
			: array();
		$result       = Molt::molt( $post_id, $output_types );
		if ( is_wp_error( $result ) ) {
			return array( 'error' => $result->get_error_message() );
		}
		return $result;
	}
}
