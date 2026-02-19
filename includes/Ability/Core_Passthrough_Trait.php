<?php
/**
 * Core ability passthrough callbacks.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * Backward-compatible execute_* wrappers for non-Woo abilities.
 */
trait Core_Passthrough_Trait {

	// Content abilities — passthrough to Ability\Content_Abilities (for tests and backward compatibility).
	/** @param array<string, mixed> $input */
	public static function execute_list_posts( array $input ): array {
		return Ability\Content_Abilities::execute_list_posts( $input );
	}
	/** @param array<string, mixed> $input */
	public static function execute_get_post( array $input ): array {
		return Ability\Content_Abilities::execute_get_post( $input );
	}
	/** @param array<string, mixed> $input */
	public static function execute_create_post( array $input ): array {
		return Ability\Content_Abilities::execute_create_post( $input );
	}
	/** @param array<string, mixed> $input */
	public static function execute_update_post( array $input ): array {
		return Ability\Content_Abilities::execute_update_post( $input );
	}
	/** @param array<string, mixed> $input */
	public static function execute_delete_post( array $input ): array {
		return Ability\Content_Abilities::execute_delete_post( $input );
	}
	/** @param array<string, mixed> $input */
	public static function execute_list_taxonomies( array $input ): array {
		return Ability\Content_Abilities::execute_list_taxonomies( $input );
	}
	/** @param array<string, mixed> $input */
	public static function execute_manage_terms( array $input ): array {
		return Ability\Content_Abilities::execute_manage_terms( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_duplicate_post( array $input ): array {
		return Ability\Content_Workflow_Abilities::execute_duplicate_post( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_schedule_post( array $input ): array {
		return Ability\Content_Workflow_Abilities::execute_schedule_post( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_find_replace_content( array $input ): array {
		return Ability\Content_Workflow_Abilities::execute_find_replace_content( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_reorder_posts( array $input ): array {
		return Ability\Content_Workflow_Abilities::execute_reorder_posts( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_compare_revisions( array $input ): array {
		return Ability\Content_Workflow_Abilities::execute_compare_revisions( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_list_media( array $input ): array {
		return Ability\Media_Abilities::execute_list_media( $input );
	}
	/** @param array<string, mixed> $input */
	public static function execute_upload_media( array $input ): array {
		return Ability\Media_Abilities::execute_upload_media( $input );
	}
	/** @param array<string, mixed> $input */
	public static function execute_delete_media( array $input ): array {
		return Ability\Media_Abilities::execute_delete_media( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_set_featured_image( array $input ): array {
		return Ability\Media_Extended_Abilities::execute_set_featured_image( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_list_unused_media( array $input ): array {
		return Ability\Media_Extended_Abilities::execute_list_unused_media( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_regenerate_media_thumbnails( array $input ): array {
		return Ability\Media_Extended_Abilities::execute_regenerate_media_thumbnails( $input );
	}

	// =========================================================================
	// Execute callbacks — Users
	// =========================================================================

	/**
	 * List users. Passthrough to User_Comment_Abilities.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_list_users( array $input ): array {
		return Ability\User_Comment_Abilities::execute_list_users( $input );
	}

	/**
	 * Get a single user. Passthrough to User_Comment_Abilities.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_get_user( array $input ): array {
		return Ability\User_Comment_Abilities::execute_get_user( $input );
	}

	/**
	 * Update a user's role. Passthrough to User_Comment_Abilities.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_update_user_role( array $input ): array {
		return Ability\User_Comment_Abilities::execute_update_user_role( $input );
	}

	/**
	 * List comments. Passthrough to User_Comment_Abilities.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_list_comments( array $input ): array {
		return Ability\User_Comment_Abilities::execute_list_comments( $input );
	}

	/**
	 * Moderate a comment. Passthrough to User_Comment_Abilities.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_moderate_comment( array $input ): array {
		return Ability\User_Comment_Abilities::execute_moderate_comment( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_create_user( array $input ): array {
		return Ability\User_Comment_Extended_Abilities::execute_create_user( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_delete_user( array $input ): array {
		return Ability\User_Comment_Extended_Abilities::execute_delete_user( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_reset_user_password( array $input ): array {
		return Ability\User_Comment_Extended_Abilities::execute_reset_user_password( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_create_comment( array $input ): array {
		return Ability\User_Comment_Extended_Abilities::execute_create_comment( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_update_comment( array $input ): array {
		return Ability\User_Comment_Extended_Abilities::execute_update_comment( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_delete_comment( array $input ): array {
		return Ability\User_Comment_Extended_Abilities::execute_delete_comment( $input );
	}

	/**
	 * Get an option value. Passthrough to Settings_Abilities.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_get_option( array $input ): array {
		return Ability\Settings_Abilities::execute_get_option( $input );
	}

	/**
	 * Update an allowed option. Passthrough to Settings_Abilities.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_update_option( array $input ): array {
		return Ability\Settings_Abilities::execute_update_option( $input );
	}

	/**
	 * List installed plugins. Passthrough to Settings_Abilities.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_list_plugins( array $input ): array {
		return Ability\Settings_Abilities::execute_list_plugins( $input );
	}

	/**
	 * Activate or deactivate a plugin. Passthrough to Settings_Abilities.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_toggle_plugin( array $input ): array {
		return Ability\Settings_Abilities::execute_toggle_plugin( $input );
	}

	/**
	 * List installed themes. Passthrough to Settings_Abilities.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_list_themes( array $input ): array {
		return Ability\Settings_Abilities::execute_list_themes( $input );
	}

	/**
	 * Switch the active theme. Passthrough to Settings_Abilities.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute_switch_theme( array $input ): array {
		return Ability\Settings_Abilities::execute_switch_theme( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_manage_plugin_lifecycle( array $input ): array {
		return Ability\Extension_Lifecycle_Abilities::execute_manage_plugin_lifecycle( $input );
	}

	/** @param array<string, mixed> $input */
	public static function execute_manage_theme_lifecycle( array $input ): array {
		return Ability\Extension_Lifecycle_Abilities::execute_manage_theme_lifecycle( $input );
	}

	// =========================================================================
	// Execute callbacks — Analytics & Maintenance (passthroughs)
	// =========================================================================

	/** @see \WP_Pinch\Ability\Analytics_Abilities::execute_site_health */
	public static function execute_site_health( array $input ): array {
		return Ability\Analytics_Abilities::execute_site_health( $input );
	}

	/** @see \WP_Pinch\Ability\Analytics_Abilities::execute_content_health_report */
	public static function execute_content_health_report( array $input ): array {
		return Ability\Analytics_Abilities::execute_content_health_report( $input );
	}

	/** @see \WP_Pinch\Ability\Analytics_Abilities::execute_recent_activity */
	public static function execute_recent_activity( array $input ): array {
		return Ability\Analytics_Abilities::execute_recent_activity( $input );
	}

	/** @see \WP_Pinch\Ability\Analytics_Abilities::execute_search_content */
	public static function execute_search_content( array $input ): array {
		return Ability\Analytics_Abilities::execute_search_content( $input );
	}

	/** @see \WP_Pinch\Ability\Analytics_Abilities::execute_export_data */
	public static function execute_export_data( array $input ): array {
		return Ability\Analytics_Abilities::execute_export_data( $input );
	}

	/** @see \WP_Pinch\Ability\Analytics_Abilities::execute_site_digest */
	public static function execute_site_digest( array $input ): array {
		return Ability\Analytics_Abilities::execute_site_digest( $input );
	}

	/** @see \WP_Pinch\Ability\Analytics_Abilities::execute_related_posts */
	public static function execute_related_posts( array $input ): array {
		return Ability\Analytics_Abilities::execute_related_posts( $input );
	}

	/** @see \WP_Pinch\Ability\Analytics_Abilities::execute_synthesize */
	public static function execute_synthesize( array $input ): array {
		return Ability\Analytics_Abilities::execute_synthesize( $input );
	}

	/** @see \WP_Pinch\Ability\Analytics_Abilities::execute_analytics_narratives */
	public static function execute_analytics_narratives( array $input ): array {
		return Ability\Analytics_Abilities::execute_analytics_narratives( $input );
	}

	/** @see \WP_Pinch\Ability\Analytics_Abilities::execute_submit_conversational_form */
	public static function execute_submit_conversational_form( array $input ): array {
		return Ability\Analytics_Abilities::execute_submit_conversational_form( $input );
	}

	// =========================================================================
	// Execute callbacks — Quick-win (passthroughs)
	// =========================================================================

	/** @see \WP_Pinch\Ability\QuickWin_Abilities::execute_generate_tldr */
	public static function execute_generate_tldr( array $input ): array {
		return Ability\QuickWin_Abilities::execute_generate_tldr( $input );
	}

	/** @see \WP_Pinch\Ability\QuickWin_Abilities::execute_suggest_links */
	public static function execute_suggest_links( array $input ): array {
		return Ability\QuickWin_Abilities::execute_suggest_links( $input );
	}

	/** @see \WP_Pinch\Ability\QuickWin_Abilities::execute_suggest_terms */
	public static function execute_suggest_terms( array $input ): array {
		return Ability\QuickWin_Abilities::execute_suggest_terms( $input );
	}

	/** @see \WP_Pinch\Ability\QuickWin_Abilities::execute_quote_bank */
	public static function execute_quote_bank( array $input ): array {
		return Ability\QuickWin_Abilities::execute_quote_bank( $input );
	}

	/** @see \WP_Pinch\Ability\QuickWin_Abilities::execute_what_do_i_know */
	public static function execute_what_do_i_know( array $input ): array {
		return Ability\QuickWin_Abilities::execute_what_do_i_know( $input );
	}

	/** @see \WP_Pinch\Ability\QuickWin_Abilities::execute_project_assembly */
	public static function execute_project_assembly( array $input ): array {
		return Ability\QuickWin_Abilities::execute_project_assembly( $input );
	}

	/** @see \WP_Pinch\Ability\QuickWin_Abilities::execute_knowledge_graph */
	public static function execute_knowledge_graph( array $input ): array {
		return Ability\QuickWin_Abilities::execute_knowledge_graph( $input );
	}

	/** @see \WP_Pinch\Ability\QuickWin_Abilities::execute_find_similar */
	public static function execute_find_similar( array $input ): array {
		return Ability\QuickWin_Abilities::execute_find_similar( $input );
	}

	/** @see \WP_Pinch\Ability\QuickWin_Abilities::execute_spaced_resurfacing */
	public static function execute_spaced_resurfacing( array $input ): array {
		return Ability\QuickWin_Abilities::execute_spaced_resurfacing( $input );
	}

	/** @see \WP_Pinch\Ability\PinchDrop_Abilities::execute_pinchdrop_generate */
	public static function execute_pinchdrop_generate( array $input ): array {
		return Ability\PinchDrop_Abilities::execute_pinchdrop_generate( $input );
	}

	// =========================================================================
	// Execute callbacks — Menus, Meta, Revisions, Bulk, Cron (passthroughs)
	// =========================================================================

	/** @see \WP_Pinch\Ability\Menu_Meta_Revisions_Abilities::execute_list_menus */
	public static function execute_list_menus( array $input ): array {
		return Ability\Menu_Meta_Revisions_Abilities::execute_list_menus( $input );
	}

	/** @see \WP_Pinch\Ability\Menu_Meta_Revisions_Abilities::execute_manage_menu_item */
	public static function execute_manage_menu_item( array $input ): array {
		return Ability\Menu_Meta_Revisions_Abilities::execute_manage_menu_item( $input );
	}

	/** @see \WP_Pinch\Ability\Menu_Meta_Revisions_Abilities::execute_get_post_meta */
	public static function execute_get_post_meta( array $input ): array {
		return Ability\Menu_Meta_Revisions_Abilities::execute_get_post_meta( $input );
	}

	/** @see \WP_Pinch\Ability\Menu_Meta_Revisions_Abilities::execute_update_post_meta */
	public static function execute_update_post_meta( array $input ): array {
		return Ability\Menu_Meta_Revisions_Abilities::execute_update_post_meta( $input );
	}

	/** @see \WP_Pinch\Ability\Menu_Meta_Revisions_Abilities::execute_list_revisions */
	public static function execute_list_revisions( array $input ): array {
		return Ability\Menu_Meta_Revisions_Abilities::execute_list_revisions( $input );
	}

	/** @see \WP_Pinch\Ability\Menu_Meta_Revisions_Abilities::execute_restore_revision */
	public static function execute_restore_revision( array $input ): array {
		return Ability\Menu_Meta_Revisions_Abilities::execute_restore_revision( $input );
	}

	/** @see \WP_Pinch\Ability\Menu_Meta_Revisions_Abilities::execute_bulk_edit_posts */
	public static function execute_bulk_edit_posts( array $input ): array {
		return Ability\Menu_Meta_Revisions_Abilities::execute_bulk_edit_posts( $input );
	}

	/** @see \WP_Pinch\Ability\Menu_Meta_Revisions_Abilities::execute_list_cron_events */
	public static function execute_list_cron_events( array $input ): array {
		return Ability\Menu_Meta_Revisions_Abilities::execute_list_cron_events( $input );
	}

	/** @see \WP_Pinch\Ability\Menu_Meta_Revisions_Abilities::execute_manage_cron */
	public static function execute_manage_cron( array $input ): array {
		return Ability\Menu_Meta_Revisions_Abilities::execute_manage_cron( $input );
	}

	/** @see \WP_Pinch\Ability\System_Admin_Abilities::execute_get_transient */
	public static function execute_get_transient( array $input ): array {
		return Ability\System_Admin_Abilities::execute_get_transient( $input );
	}

	/** @see \WP_Pinch\Ability\System_Admin_Abilities::execute_set_transient */
	public static function execute_set_transient( array $input ): array {
		return Ability\System_Admin_Abilities::execute_set_transient( $input );
	}

	/** @see \WP_Pinch\Ability\System_Admin_Abilities::execute_delete_transient */
	public static function execute_delete_transient( array $input ): array {
		return Ability\System_Admin_Abilities::execute_delete_transient( $input );
	}

	/** @see \WP_Pinch\Ability\System_Admin_Abilities::execute_list_rewrite_rules */
	public static function execute_list_rewrite_rules( array $input ): array {
		return Ability\System_Admin_Abilities::execute_list_rewrite_rules( $input );
	}

	/** @see \WP_Pinch\Ability\System_Admin_Abilities::execute_flush_rewrite_rules */
	public static function execute_flush_rewrite_rules( array $input ): array {
		return Ability\System_Admin_Abilities::execute_flush_rewrite_rules( $input );
	}

	/** @see \WP_Pinch\Ability\System_Admin_Abilities::execute_maintenance_mode_status */
	public static function execute_maintenance_mode_status( array $input ): array {
		return Ability\System_Admin_Abilities::execute_maintenance_mode_status( $input );
	}

	/** @see \WP_Pinch\Ability\System_Admin_Abilities::execute_set_maintenance_mode */
	public static function execute_set_maintenance_mode( array $input ): array {
		return Ability\System_Admin_Abilities::execute_set_maintenance_mode( $input );
	}

	/** @see \WP_Pinch\Ability\System_Admin_Abilities::execute_search_replace_db_scoped */
	public static function execute_search_replace_db_scoped( array $input ): array {
		return Ability\System_Admin_Abilities::execute_search_replace_db_scoped( $input );
	}

	/** @see \WP_Pinch\Ability\System_Admin_Abilities::execute_list_language_packs */
	public static function execute_list_language_packs( array $input ): array {
		return Ability\System_Admin_Abilities::execute_list_language_packs( $input );
	}

	/** @see \WP_Pinch\Ability\System_Admin_Abilities::execute_install_language_pack */
	public static function execute_install_language_pack( array $input ): array {
		return Ability\System_Admin_Abilities::execute_install_language_pack( $input );
	}

	/** @see \WP_Pinch\Ability\System_Admin_Abilities::execute_activate_language_pack */
	public static function execute_activate_language_pack( array $input ): array {
		return Ability\System_Admin_Abilities::execute_activate_language_pack( $input );
	}

	/** @see \WP_Pinch\Ability\Site_Ops_Abilities::execute_flush_cache */
	public static function execute_flush_cache( array $input ): array {
		return Ability\Site_Ops_Abilities::execute_flush_cache( $input );
	}

	/** @see \WP_Pinch\Ability\Site_Ops_Abilities::execute_check_broken_links */
	public static function execute_check_broken_links( array $input ): array {
		return Ability\Site_Ops_Abilities::execute_check_broken_links( $input );
	}

	/** @see \WP_Pinch\Ability\Site_Ops_Abilities::execute_get_php_error_log */
	public static function execute_get_php_error_log( array $input ): array {
		return Ability\Site_Ops_Abilities::execute_get_php_error_log( $input );
	}

	/** @see \WP_Pinch\Ability\Site_Ops_Abilities::execute_list_posts_missing_meta */
	public static function execute_list_posts_missing_meta( array $input ): array {
		return Ability\Site_Ops_Abilities::execute_list_posts_missing_meta( $input );
	}

	/** @see \WP_Pinch\Ability\Site_Ops_Abilities::execute_list_custom_post_types */
	public static function execute_list_custom_post_types( array $input ): array {
		return Ability\Site_Ops_Abilities::execute_list_custom_post_types( $input );
	}

	/** @see \WP_Pinch\Ability\GhostWriter_Molt_Abilities::execute_analyze_voice */
	public static function execute_analyze_voice( array $input ): array {
		return Ability\GhostWriter_Molt_Abilities::execute_analyze_voice( $input );
	}

	/** @see \WP_Pinch\Ability\GhostWriter_Molt_Abilities::execute_list_abandoned_drafts */
	public static function execute_list_abandoned_drafts( array $input ): array {
		return Ability\GhostWriter_Molt_Abilities::execute_list_abandoned_drafts( $input );
	}

	/** @see \WP_Pinch\Ability\GhostWriter_Molt_Abilities::execute_ghostwrite */
	public static function execute_ghostwrite( array $input ): array {
		return Ability\GhostWriter_Molt_Abilities::execute_ghostwrite( $input );
	}

	/** @see \WP_Pinch\Ability\GhostWriter_Molt_Abilities::execute_molt */
	public static function execute_molt( array $input ): array {
		return Ability\GhostWriter_Molt_Abilities::execute_molt( $input );
	}
}
