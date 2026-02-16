<?php
/**
 * User and comment abilities — list users, get user, update role, list comments, moderate comment.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

use WP_Pinch\Abilities;
use WP_Pinch\Audit_Table;

defined( 'ABSPATH' ) || exit;

/**
 * User and comment abilities.
 */
class User_Comment_Abilities {

	/**
	 * Register user and comment abilities.
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		Abilities::register_ability(
			'wp-pinch/list-users',
			__( 'List Users', 'wp-pinch' ),
			__( 'List site users with optional role filter.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'role'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
					),
				),
			),
			array( 'type' => 'object' ),
			'list_users',
			array( __CLASS__, 'execute_list_users' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/get-user',
			__( 'Get User', 'wp-pinch' ),
			__( 'Retrieve a single user by ID.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'id' ),
				'properties' => array(
					'id' => array( 'type' => 'integer' ),
				),
			),
			array( 'type' => 'object' ),
			'list_users',
			array( __CLASS__, 'execute_get_user' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/update-user-role',
			__( 'Update User Role', 'wp-pinch' ),
			__( 'Change a user\'s role.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'id', 'role' ),
				'properties' => array(
					'id'   => array( 'type' => 'integer' ),
					'role' => array( 'type' => 'string' ),
				),
			),
			array( 'type' => 'object' ),
			'promote_users',
			array( __CLASS__, 'execute_update_user_role' )
		);

		Abilities::register_ability(
			'wp-pinch/list-comments',
			__( 'List Comments', 'wp-pinch' ),
			__( 'List comments with optional status filter.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'status'   => array(
						'type'    => 'string',
						'default' => 'all',
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'post_id'  => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			),
			array( 'type' => 'object' ),
			'moderate_comments',
			array( __CLASS__, 'execute_list_comments' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/moderate-comment',
			__( 'Moderate Comment', 'wp-pinch' ),
			__( 'Approve, spam, or trash a comment.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'id', 'status' ),
				'properties' => array(
					'id'     => array( 'type' => 'integer' ),
					'status' => array(
						'type' => 'string',
						'enum' => array( 'approve', 'hold', 'spam', 'trash' ),
					),
				),
			),
			array( 'type' => 'object' ),
			'moderate_comments',
			array( __CLASS__, 'execute_moderate_comment' )
		);
	}

	/**
	 * List users.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_list_users( array $input ): array {
		$args = array(
			'number' => max( 1, min( absint( $input['per_page'] ?? 20 ), 100 ) ),
			'paged'  => max( 1, absint( $input['page'] ?? 1 ) ),
		);

		if ( ! empty( $input['role'] ) ) {
			$args['role'] = sanitize_key( $input['role'] );
		}

		$user_query = new \WP_User_Query( $args );

		$users = array_map(
			function ( $user ) {
				return array(
					'id'           => $user->ID,
					'login'        => $user->user_login,
					'display_name' => $user->display_name,
					'roles'        => $user->roles,
					'registered'   => $user->user_registered,
				);
			},
			$user_query->get_results()
		);

		return array(
			'users' => $users,
			'total' => $user_query->get_total(),
		);
	}

	/**
	 * Get a single user.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_get_user( array $input ): array {
		$user = get_userdata( absint( $input['id'] ) );
		if ( ! $user ) {
			return array( 'error' => __( 'User not found.', 'wp-pinch' ) );
		}

		return array(
			'id'           => $user->ID,
			'login'        => $user->user_login,
			'display_name' => $user->display_name,
			'roles'        => $user->roles,
			'registered'   => $user->user_registered,
			'posts_count'  => count_user_posts( $user->ID ),
		);
	}

	/**
	 * Update a user's role.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_update_user_role( array $input ): array {
		$user = get_userdata( absint( $input['id'] ) );
		if ( ! $user ) {
			return array( 'error' => __( 'User not found.', 'wp-pinch' ) );
		}

		$role = sanitize_key( $input['role'] );
		if ( ! wp_roles()->is_role( $role ) ) {
			return array( 'error' => __( 'Invalid role.', 'wp-pinch' ) );
		}

		if ( 'administrator' === $role ) {
			return array( 'error' => __( 'The "administrator" role cannot be assigned via abilities.', 'wp-pinch' ) );
		}

		/** @var string[] $blocked_roles */
		$blocked_roles = apply_filters( 'wp_pinch_blocked_roles', array() );

		if ( in_array( $role, $blocked_roles, true ) ) {
			return array(
				'error' => sprintf(
					/* translators: %s: role slug */
					__( 'The "%s" role cannot be assigned via abilities.', 'wp-pinch' ),
					$role
				),
			);
		}

		if ( get_current_user_id() === $user->ID ) {
			return array( 'error' => __( 'Cannot modify your own role.', 'wp-pinch' ) );
		}

		$role_obj = get_role( $role );
		if ( $role_obj ) {
			foreach ( Abilities::DANGEROUS_CAPABILITIES as $cap ) {
				if ( $role_obj->has_cap( $cap ) ) {
					return array(
						'error' => sprintf(
							/* translators: %s: role slug */
							__( 'The "%s" role has administrative capabilities and cannot be assigned via abilities.', 'wp-pinch' ),
							$role
						),
					);
				}
			}
		}

		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return array( 'error' => __( 'Cannot modify an administrator\'s role via abilities.', 'wp-pinch' ) );
		}

		$user->set_role( $role );

		Audit_Table::insert(
			'user_role_changed',
			'ability',
			sprintf( 'User #%d role changed to %s.', $user->ID, $role ),
			array(
				'user_id' => $user->ID,
				'role'    => $role,
			)
		);

		return array(
			'id'      => $user->ID,
			'role'    => $role,
			'updated' => true,
		);
	}

	/**
	 * List comments.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_list_comments( array $input ): array {
		$args = array(
			'number' => max( 1, min( absint( $input['per_page'] ?? 20 ), 100 ) ),
			'paged'  => max( 1, absint( $input['page'] ?? 1 ) ),
		);

		$status = sanitize_key( $input['status'] ?? 'all' );
		if ( 'all' !== $status ) {
			$args['status'] = $status;
		}

		if ( ! empty( $input['post_id'] ) ) {
			$args['post_id'] = absint( $input['post_id'] );
		}

		$comments = get_comments( $args );

		$count_args = $args;
		unset( $count_args['number'], $count_args['paged'] );
		$total = get_comments( array_merge( $count_args, array( 'count' => true ) ) );

		return array(
			'comments' => array_map(
				function ( $c ) {
					return array(
						'id'      => (int) $c->comment_ID,
						'post_id' => (int) $c->comment_post_ID,
						'author'  => $c->comment_author,
						'content' => wp_trim_words( $c->comment_content, 30 ),
						'status'  => wp_get_comment_status( $c ),
						'date'    => $c->comment_date,
					);
				},
				$comments
			),
			'total'    => (int) $total,
		);
	}

	/**
	 * Moderate a comment.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_moderate_comment( array $input ): array {
		$id     = absint( $input['id'] );
		$status = sanitize_key( $input['status'] );

		if ( ! get_comment( $id ) ) {
			return array( 'error' => __( 'Comment not found.', 'wp-pinch' ) );
		}

		$status_map = array(
			'approve' => '1',
			'hold'    => '0',
			'spam'    => 'spam',
			'trash'   => 'trash',
		);

		if ( ! isset( $status_map[ $status ] ) ) {
			return array( 'error' => __( 'Invalid status.', 'wp-pinch' ) );
		}

		$result = wp_set_comment_status( $id, $status_map[ $status ] );

		if ( ! $result ) {
			return array( 'error' => __( 'Failed to moderate comment.', 'wp-pinch' ) );
		}

		Audit_Table::insert(
			'comment_moderated',
			'ability',
			sprintf( 'Comment #%d set to %s.', $id, $status ),
			array(
				'comment_id' => $id,
				'status'     => $status,
			)
		);

		return array(
			'id'        => $id,
			'status'    => $status,
			'moderated' => true,
		);
	}
}
