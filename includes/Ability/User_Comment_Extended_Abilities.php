<?php
/**
 * Extended user and comment abilities.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

defined( 'ABSPATH' ) || exit;

use WP_Pinch\Abilities;
use WP_Pinch\Audit_Table;

/**
 * User lifecycle and expanded comment CRUD.
 */
class User_Comment_Extended_Abilities {

	/**
	 * Register extended user/comment abilities.
	 */
	public static function register(): void {
		Abilities::register_ability(
			'wp-pinch/create-user',
			__( 'Create User', 'wp-pinch' ),
			__( 'Create a new user with a safe role.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'login', 'email' ),
				'properties' => array(
					'login'        => array( 'type' => 'string' ),
					'email'        => array( 'type' => 'string' ),
					'role'         => array(
						'type'    => 'string',
						'default' => 'subscriber',
					),
					'password'     => array( 'type' => 'string' ),
					'display_name' => array( 'type' => 'string' ),
				),
			),
			array( 'type' => 'object' ),
			'create_users',
			array( __CLASS__, 'execute_create_user' )
		);

		Abilities::register_ability(
			'wp-pinch/delete-user',
			__( 'Delete User', 'wp-pinch' ),
			__( 'Delete a user account with explicit confirmation.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'user_id', 'confirm' ),
				'properties' => array(
					'user_id'  => array( 'type' => 'integer' ),
					'reassign' => array(
						'type'        => 'integer',
						'description' => 'Optional user ID to reassign content.',
					),
					'confirm'  => array(
						'type'        => 'boolean',
						'description' => 'Must be true to proceed.',
					),
				),
			),
			array( 'type' => 'object' ),
			'delete_users',
			array( __CLASS__, 'execute_delete_user' )
		);

		Abilities::register_ability(
			'wp-pinch/reset-user-password',
			__( 'Reset User Password', 'wp-pinch' ),
			__( 'Reset a user password, optionally returning the generated value.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'user_id' ),
				'properties' => array(
					'user_id'         => array( 'type' => 'integer' ),
					'new_password'    => array( 'type' => 'string' ),
					'return_password' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_users',
			array( __CLASS__, 'execute_reset_user_password' )
		);

		Abilities::register_ability(
			'wp-pinch/create-comment',
			__( 'Create Comment', 'wp-pinch' ),
			__( 'Create a comment on a post.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'post_id', 'content' ),
				'properties' => array(
					'post_id'      => array( 'type' => 'integer' ),
					'content'      => array( 'type' => 'string' ),
					'author_name'  => array( 'type' => 'string' ),
					'author_email' => array( 'type' => 'string' ),
					'author_url'   => array( 'type' => 'string' ),
					'parent'       => array( 'type' => 'integer' ),
					'status'       => array(
						'type'    => 'string',
						'default' => 'hold',
						'enum'    => array( 'approve', 'hold', 'spam', 'trash' ),
					),
				),
			),
			array( 'type' => 'object' ),
			'moderate_comments',
			array( __CLASS__, 'execute_create_comment' )
		);

		Abilities::register_ability(
			'wp-pinch/update-comment',
			__( 'Update Comment', 'wp-pinch' ),
			__( 'Update comment content, author fields, or moderation status.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'id' ),
				'properties' => array(
					'id'           => array( 'type' => 'integer' ),
					'content'      => array( 'type' => 'string' ),
					'author_name'  => array( 'type' => 'string' ),
					'author_email' => array( 'type' => 'string' ),
					'author_url'   => array( 'type' => 'string' ),
					'status'       => array(
						'type' => 'string',
						'enum' => array( 'approve', 'hold', 'spam', 'trash' ),
					),
				),
			),
			array( 'type' => 'object' ),
			'moderate_comments',
			array( __CLASS__, 'execute_update_comment' )
		);

		Abilities::register_ability(
			'wp-pinch/delete-comment',
			__( 'Delete Comment', 'wp-pinch' ),
			__( 'Delete or trash a comment.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'id' ),
				'properties' => array(
					'id'    => array( 'type' => 'integer' ),
					'force' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			),
			array( 'type' => 'object' ),
			'moderate_comments',
			array( __CLASS__, 'execute_delete_comment' )
		);
	}

	/**
	 * Create user.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_create_user( array $input ): array {
		$login = sanitize_user( (string) ( $input['login'] ?? '' ), true );
		$email = sanitize_email( (string) ( $input['email'] ?? '' ) );
		$role  = sanitize_key( (string) ( $input['role'] ?? 'subscriber' ) );

		if ( '' === $login || '' === $email || ! is_email( $email ) ) {
			return array( 'error' => __( 'Valid login and email are required.', 'wp-pinch' ) );
		}
		if ( username_exists( $login ) || email_exists( $email ) ) {
			return array( 'error' => __( 'User login or email already exists.', 'wp-pinch' ) );
		}
		if ( ! wp_roles()->is_role( $role ) ) {
			return array( 'error' => __( 'Invalid role.', 'wp-pinch' ) );
		}
		if ( 'administrator' === $role ) {
			return array( 'error' => __( 'Administrator role cannot be assigned via this ability.', 'wp-pinch' ) );
		}
		$role_obj = get_role( $role );
		if ( $role_obj ) {
			foreach ( Abilities::DANGEROUS_CAPABILITIES as $cap ) {
				if ( $role_obj->has_cap( $cap ) ) {
					return array(
						'error' => __( 'Role has administrative capabilities and cannot be assigned via this ability.', 'wp-pinch' ),
					);
				}
			}
		}

		$password = (string) ( $input['password'] ?? '' );
		if ( '' === $password ) {
			$password = wp_generate_password( 24, true, true );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'role'         => $role,
				'user_pass'    => $password,
				'display_name' => sanitize_text_field( (string) ( $input['display_name'] ?? $login ) ),
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return array( 'error' => $user_id->get_error_message() );
		}

		Audit_Table::insert(
			'user_created',
			'ability',
			sprintf( 'User #%d created via ability.', (int) $user_id ),
			array(
				'user_id' => (int) $user_id,
				'role'    => $role,
			)
		);

		return array(
			'id'      => (int) $user_id,
			'login'   => $login,
			'email'   => $email,
			'role'    => $role,
			'created' => true,
		);
	}

	/**
	 * Delete user with guardrails.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_delete_user( array $input ): array {
		require_once ABSPATH . 'wp-admin/includes/user.php';

		$user_id  = absint( $input['user_id'] ?? 0 );
		$reassign = absint( $input['reassign'] ?? 0 );
		$confirm  = ! empty( $input['confirm'] );

		if ( ! $confirm ) {
			return array( 'error' => __( 'confirm=true is required.', 'wp-pinch' ) );
		}
		if ( get_current_user_id() === $user_id ) {
			return array( 'error' => __( 'Cannot delete your own account via ability.', 'wp-pinch' ) );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array( 'error' => __( 'User not found.', 'wp-pinch' ) );
		}
		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return array( 'error' => __( 'Cannot delete an administrator via ability.', 'wp-pinch' ) );
		}
		foreach ( Abilities::DANGEROUS_CAPABILITIES as $cap ) {
			if ( user_can( $user, $cap ) ) {
				return array( 'error' => __( 'Cannot delete a user with administrative capabilities via this ability.', 'wp-pinch' ) );
			}
		}

		if ( $reassign > 0 && ! get_userdata( $reassign ) ) {
			return array( 'error' => __( 'reassign user not found.', 'wp-pinch' ) );
		}

		$deleted = wp_delete_user( $user_id, $reassign > 0 ? $reassign : null );
		if ( ! $deleted ) {
			return array( 'error' => __( 'Failed to delete user.', 'wp-pinch' ) );
		}

		Audit_Table::insert(
			'user_deleted',
			'ability',
			sprintf( 'User #%d deleted via ability.', $user_id ),
			array(
				'user_id'  => $user_id,
				'reassign' => $reassign,
			)
		);

		return array(
			'user_id' => $user_id,
			'deleted' => true,
		);
	}

	/**
	 * Reset user password.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_reset_user_password( array $input ): array {
		$user_id         = absint( $input['user_id'] ?? 0 );
		$return_password = ! empty( $input['return_password'] );
		$password        = (string) ( $input['new_password'] ?? '' );

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array( 'error' => __( 'User not found.', 'wp-pinch' ) );
		}

		$generated = false;
		if ( '' === $password ) {
			$password  = wp_generate_password( 24, true, true );
			$generated = true;
		}

		wp_set_password( $password, $user_id );

		Audit_Table::insert(
			'user_password_reset',
			'ability',
			sprintf( 'Password reset for user #%d.', $user_id ),
			array(
				'user_id'   => $user_id,
				'generated' => $generated,
			)
		);

		$result = array(
			'user_id'   => $user_id,
			'reset'     => true,
			'generated' => $generated,
		);
		if ( $return_password ) {
			$result['password'] = $password;
		}
		return $result;
	}

	/**
	 * Create comment.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_create_comment( array $input ): array {
		$post_id = absint( $input['post_id'] ?? 0 );
		$content = trim( (string) ( $input['content'] ?? '' ) );
		if ( $post_id < 1 || '' === $content || ! get_post( $post_id ) ) {
			return array( 'error' => __( 'Valid post_id and content are required.', 'wp-pinch' ) );
		}

		$comment_id = wp_new_comment(
			array(
				'comment_post_ID'      => $post_id,
				'comment_content'      => wp_kses_post( $content ),
				'comment_author'       => sanitize_text_field( (string) ( $input['author_name'] ?? '' ) ),
				'comment_author_email' => sanitize_email( (string) ( $input['author_email'] ?? '' ) ),
				'comment_author_url'   => esc_url_raw( (string) ( $input['author_url'] ?? '' ) ),
				'comment_parent'       => absint( $input['parent'] ?? 0 ),
				'user_id'              => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $comment_id ) ) {
			return array( 'error' => $comment_id->get_error_message() );
		}

		$status = sanitize_key( (string) ( $input['status'] ?? 'hold' ) );
		if ( ! self::set_comment_status_safe( (int) $comment_id, $status ) ) {
			return array( 'error' => __( 'Invalid comment status.', 'wp-pinch' ) );
		}

		Audit_Table::insert(
			'comment_created',
			'ability',
			sprintf( 'Comment #%d created.', (int) $comment_id ),
			array(
				'comment_id' => (int) $comment_id,
				'post_id'    => $post_id,
			)
		);

		return array(
			'id'      => (int) $comment_id,
			'post_id' => $post_id,
			'created' => true,
		);
	}

	/**
	 * Update comment.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_update_comment( array $input ): array {
		$id = absint( $input['id'] ?? 0 );
		if ( $id < 1 || ! get_comment( $id ) ) {
			return array( 'error' => __( 'Comment not found.', 'wp-pinch' ) );
		}

		$comment_data = array( 'comment_ID' => $id );
		if ( isset( $input['content'] ) ) {
			$comment_data['comment_content'] = wp_kses_post( (string) $input['content'] );
		}
		if ( isset( $input['author_name'] ) ) {
			$comment_data['comment_author'] = sanitize_text_field( (string) $input['author_name'] );
		}
		if ( isset( $input['author_email'] ) ) {
			$comment_data['comment_author_email'] = sanitize_email( (string) $input['author_email'] );
		}
		if ( isset( $input['author_url'] ) ) {
			$comment_data['comment_author_url'] = esc_url_raw( (string) $input['author_url'] );
		}

		$updated = wp_update_comment( $comment_data, true );
		if ( is_wp_error( $updated ) ) {
			return array( 'error' => $updated->get_error_message() );
		}

		if ( isset( $input['status'] ) ) {
			if ( ! self::set_comment_status_safe( $id, sanitize_key( (string) $input['status'] ) ) ) {
				return array( 'error' => __( 'Invalid comment status.', 'wp-pinch' ) );
			}
		}

		Audit_Table::insert(
			'comment_updated',
			'ability',
			sprintf( 'Comment #%d updated.', $id ),
			array( 'comment_id' => $id )
		);

		return array(
			'id'      => $id,
			'updated' => true,
		);
	}

	/**
	 * Delete or trash comment.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_delete_comment( array $input ): array {
		$id    = absint( $input['id'] ?? 0 );
		$force = ! empty( $input['force'] );

		if ( $id < 1 || ! get_comment( $id ) ) {
			return array( 'error' => __( 'Comment not found.', 'wp-pinch' ) );
		}

		$ok = $force ? wp_delete_comment( $id, true ) : wp_trash_comment( $id );
		if ( ! $ok ) {
			return array( 'error' => __( 'Failed to delete comment.', 'wp-pinch' ) );
		}

		Audit_Table::insert(
			'comment_deleted',
			'ability',
			sprintf( 'Comment #%d %s.', $id, $force ? 'deleted' : 'trashed' ),
			array(
				'comment_id' => $id,
				'force'      => $force,
			)
		);

		return array(
			'id'      => $id,
			'deleted' => true,
			'force'   => $force,
		);
	}

	/**
	 * Safely map and set comment status.
	 *
	 * @param int    $comment_id Comment ID.
	 * @param string $status     Requested status.
	 * @return bool True when status is valid and set.
	 */
	private static function set_comment_status_safe( int $comment_id, string $status ): bool {
		$status_map = array(
			'approve' => '1',
			'hold'    => '0',
			'spam'    => 'spam',
			'trash'   => 'trash',
		);

		if ( ! isset( $status_map[ $status ] ) ) {
			return false;
		}
		wp_set_comment_status( $comment_id, $status_map[ $status ] );
		return true;
	}
}
