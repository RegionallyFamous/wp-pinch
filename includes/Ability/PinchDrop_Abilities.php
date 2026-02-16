<?php
/**
 * PinchDrop ability — turn rough idea text into draft content pack.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

use WP_Pinch\Abilities;
use WP_Pinch\Audit_Table;

defined( 'ABSPATH' ) || exit;

/**
 * PinchDrop ability.
 */
class PinchDrop_Abilities {

	/**
	 * Register PinchDrop ability.
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		Abilities::register_ability(
			'wp-pinch/pinchdrop-generate',
			__( 'PinchDrop Generate', 'wp-pinch' ),
			__( 'Turn rough idea text into a draft content pack (post, product update, changelog, social snippets).', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'source_text' ),
				'properties' => array(
					'source_text'   => array( 'type' => 'string' ),
					'source'        => array(
						'type'    => 'string',
						'default' => 'openclaw',
					),
					'author'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'request_id'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'audience'      => array(
						'type'    => 'string',
						'default' => '',
					),
					'tone'          => array(
						'type'    => 'string',
						'default' => '',
					),
					'save_as_draft' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'save_as_note'  => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Quick Drop: skip AI expansion; create minimal post (title + body only, no blocks).',
					),
					'output_types'  => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'string' ),
						'default' => array( 'post', 'product_update', 'changelog', 'social' ),
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_posts',
			array( __CLASS__, 'execute_pinchdrop_generate' )
		);
	}

	/**
	 * Generate PinchDrop drafts from rough source text.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_pinchdrop_generate( array $input ): array {
		$source_text = sanitize_textarea_field( (string) ( $input['source_text'] ?? '' ) );
		$source      = sanitize_key( (string) ( $input['source'] ?? 'openclaw' ) );
		$author      = sanitize_text_field( (string) ( $input['author'] ?? '' ) );
		$request_id  = sanitize_text_field( (string) ( $input['request_id'] ?? '' ) );
		$tone        = sanitize_text_field( (string) ( $input['tone'] ?? '' ) );
		$audience    = sanitize_text_field( (string) ( $input['audience'] ?? '' ) );

		if ( '' === trim( $source_text ) ) {
			return array( 'error' => __( 'Source text is required.', 'wp-pinch' ) );
		}

		if ( mb_strlen( $source_text ) > 20000 ) {
			return array( 'error' => __( 'Source text is too long (max 20,000 chars).', 'wp-pinch' ) );
		}

		$allowed_outputs = array( 'post', 'product_update', 'changelog', 'social' );
		$output_types    = array_values(
			array_intersect(
				$allowed_outputs,
				array_map( 'sanitize_key', (array) ( $input['output_types'] ?? $allowed_outputs ) )
			)
		);

		if ( empty( $output_types ) ) {
			$output_types = $allowed_outputs;
		}

		$save_as_draft = ! empty( $input['save_as_draft'] );
		$save_as_note  = ! empty( $input['save_as_note'] );

		// Quick Drop: minimal capture — title + body only, no draft pack expansion.
		if ( $save_as_note ) {
			$first_line     = preg_split( "/\r\n|\r|\n/", $source_text, 2 );
			$first_line     = trim( $first_line[0] ?? '' );
			$title          = sanitize_text_field( preg_replace( '/^[\-\*\d\.\)\s]+/', '', $first_line ) );
			$title          = wp_trim_words( $title, 15, '' );
			$title          = ( '' !== $title ) ? $title : __( 'New note', 'wp-pinch' );
			$body           = sanitize_textarea_field( $source_text );
			$minimal        = array(
				'post' => array(
					'title'   => $title,
					'content' => $body,
				),
			);
			$created_drafts = array();
			if ( $save_as_draft ) {
				$post_id = wp_insert_post(
					array(
						'post_title'   => $title,
						'post_content' => wp_kses_post( $body ),
						'post_status'  => 'draft',
						'post_type'    => 'post',
					),
					true
				);
				if ( ! is_wp_error( $post_id ) ) {
					update_post_meta( $post_id, 'wp_pinch_generated', 1 );
					update_post_meta( $post_id, 'wp_pinch_generator', 'pinchdrop' );
					update_post_meta( $post_id, 'wp_pinch_pinchdrop_source', $source );
					update_post_meta( $post_id, 'wp_pinch_pinchdrop_author', $author );
					update_post_meta( $post_id, 'wp_pinch_pinchdrop_request_id', $request_id );
					update_post_meta( $post_id, 'wp_pinch_pinchdrop_created_at', gmdate( 'c' ) );
					$created_drafts['post'] = array(
						'id'  => $post_id,
						'url' => get_edit_post_link( $post_id, '' ),
					);
				}
			}
			Audit_Table::insert(
				'pinchdrop_capture',
				'ability',
				sprintf( 'Quick Drop: minimal note saved (%s).', $save_as_draft ? 'draft created' : 'returned only' ),
				array(
					'source'      => $source,
					'request_id'  => $request_id,
					'save_drafts' => $save_as_draft,
				)
			);
			return array(
				'title'          => $title,
				'draft_pack'     => $minimal,
				'created_drafts' => $created_drafts,
				'meta'           => array(
					'source'      => $source,
					'author'      => $author,
					'request_id'  => $request_id,
					'save_drafts' => $save_as_draft,
					'quick_drop'  => true,
				),
			);
		}

		$lines = preg_split( "/\r\n|\r|\n/", $source_text );
		$lines = is_array( $lines ) ? $lines : array();
		$lines = array_values(
			array_filter(
				array_map( 'trim', $lines ),
				function ( $line ) {
					return '' !== $line;
				}
			)
		);

		$title_seed = $lines[0] ?? '';
		$title_seed = preg_replace( '/^[\-\*\d\.\)\s]+/', '', $title_seed );
		$title_seed = trim( (string) $title_seed );
		$title      = '' !== $title_seed ? wp_trim_words( $title_seed, 10, '' ) : __( 'New content idea', 'wp-pinch' );
		$title      = sanitize_text_field( $title );

		$bullet_lines = array_map(
			function ( $line ) {
				return preg_replace( '/^[\-\*\d\.\)\s]+/', '', $line );
			},
			array_slice( $lines, 0, 8 )
		);
		$bullet_lines = array_filter(
			array_map( 'trim', $bullet_lines ),
			function ( $line ) {
				return '' !== $line;
			}
		);

		if ( empty( $bullet_lines ) ) {
			$bullet_lines = array( wp_trim_words( $source_text, 25, '...' ) );
		}

		$tone_fragment     = '' !== $tone ? __( 'Tone:', 'wp-pinch' ) . ' ' . $tone : __( 'Tone: clear and practical', 'wp-pinch' );
		$audience_fragment = '' !== $audience ? __( 'Audience:', 'wp-pinch' ) . ' ' . $audience : __( 'Audience: site visitors and customers', 'wp-pinch' );

		$draft_pack = array();

		if ( in_array( 'post', $output_types, true ) ) {
			$post_content = "## Working Title\n{$title}\n\n"
				. "## Core Points\n";
			foreach ( $bullet_lines as $point ) {
				$post_content .= '- ' . $point . "\n";
			}
			$post_content .= "\n## Audience and Tone\n- {$audience_fragment}\n- {$tone_fragment}\n\n"
				. "## Suggested Structure\n1. Problem / context\n2. Key insight\n3. Practical steps\n4. Call to action\n";

			$draft_pack['post'] = array(
				'title'   => $title,
				'content' => $post_content,
			);
		}

		if ( in_array( 'product_update', $output_types, true ) ) {
			$product_title   = __( 'Product update:', 'wp-pinch' ) . ' ' . $title;
			$product_content = "## What's new\n";
			foreach ( $bullet_lines as $point ) {
				$product_content .= '- ' . $point . "\n";
			}
			$product_content .= "\n## Why this matters\n- {$audience_fragment}\n\n## Rollout notes\n- Status: Draft\n- Next step: Review and publish\n";

			$draft_pack['product_update'] = array(
				'title'   => $product_title,
				'content' => $product_content,
			);
		}

		if ( in_array( 'changelog', $output_types, true ) ) {
			$change_title    = __( 'Changelog:', 'wp-pinch' ) . ' ' . $title;
			$change_content  = "## Added\n";
			$change_content .= '- ' . ( $bullet_lines[0] ?? __( 'Initial draft entry', 'wp-pinch' ) ) . "\n\n";
			$change_content .= "## Changed\n";
			$change_content .= '- ' . ( $bullet_lines[1] ?? __( 'Refinements and improvements', 'wp-pinch' ) ) . "\n\n";
			$change_content .= "## Fixed\n";
			$change_content .= '- ' . ( $bullet_lines[2] ?? __( 'Minor stability and quality fixes', 'wp-pinch' ) ) . "\n";

			$draft_pack['changelog'] = array(
				'title'   => $change_title,
				'content' => $change_content,
			);
		}

		if ( in_array( 'social', $output_types, true ) ) {
			$social_snippets      = array(
				__( 'New:', 'wp-pinch' ) . ' ' . $title . '. ' . __( 'We just shipped improvements based on your feedback. #WordPress #OpenClaw', 'wp-pinch' ),
				__( 'Shipping update:', 'wp-pinch' ) . ' ' . $title . '. ' . __( 'More speed, clarity, and better workflow coverage.', 'wp-pinch' ),
				__( 'Behind the scenes:', 'wp-pinch' ) . ' ' . $title . ' ' . __( 'is now in progress. Want early access?', 'wp-pinch' ),
			);
			$draft_pack['social'] = array(
				'snippets' => $social_snippets,
			);
		}

		$created_drafts = array();

		if ( $save_as_draft ) {
			foreach ( $draft_pack as $type => $payload ) {
				if ( ! isset( $payload['title'], $payload['content'] ) ) {
					continue;
				}

				$post_id = wp_insert_post(
					array(
						'post_title'   => sanitize_text_field( $payload['title'] ),
						'post_content' => wp_kses_post( $payload['content'] ),
						'post_status'  => 'draft',
						'post_type'    => 'post',
					),
					true
				);

				if ( is_wp_error( $post_id ) ) {
					continue;
				}

				update_post_meta( $post_id, 'wp_pinch_generated', 1 );
				update_post_meta( $post_id, 'wp_pinch_generator', 'pinchdrop' );
				update_post_meta( $post_id, 'wp_pinch_pinchdrop_source', $source );
				update_post_meta( $post_id, 'wp_pinch_pinchdrop_author', $author );
				update_post_meta( $post_id, 'wp_pinch_pinchdrop_request_id', $request_id );
				update_post_meta( $post_id, 'wp_pinch_pinchdrop_created_at', gmdate( 'c' ) );

				$created_drafts[ $type ] = array(
					'id'  => $post_id,
					'url' => get_edit_post_link( $post_id, '' ),
				);
			}
		}

		Audit_Table::insert(
			'pinchdrop_capture',
			'ability',
			sprintf( 'PinchDrop generated %d output(s)%s.', count( $draft_pack ), $save_as_draft ? ' and saved drafts.' : '.' ),
			array(
				'source'      => $source,
				'request_id'  => $request_id,
				'save_drafts' => $save_as_draft,
				'outputs'     => array_keys( $draft_pack ),
			)
		);

		return array(
			'title'          => $title,
			'draft_pack'     => $draft_pack,
			'created_drafts' => $created_drafts,
			'meta'           => array(
				'source'      => $source,
				'author'      => $author,
				'request_id'  => $request_id,
				'save_drafts' => $save_as_draft,
			),
		);
	}
}
