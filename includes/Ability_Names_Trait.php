<?php
/**
 * Ability name catalog trait.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the ability name catalog modular.
 */
trait Ability_Names_Trait {

	/**
	 * Get the list of ability names registered by this plugin.
	 *
	 * @return string[]
	 */
	public static function get_ability_names(): array {
		$abilities = self::get_core_ability_names();

		// WooCommerce abilities — only registered when WooCommerce is active.
		if ( class_exists( 'WooCommerce' ) ) {
			$abilities = array_merge( $abilities, self::get_woo_ability_names() );
		}

		return $abilities;
	}

	/**
	 * Core ability names available without WooCommerce.
	 *
	 * @return string[]
	 */
	private static function get_core_ability_names(): array {
		return array(
			// Content.
			'wp-pinch/list-posts',
			'wp-pinch/get-post',
			'wp-pinch/create-post',
			'wp-pinch/update-post',
			'wp-pinch/delete-post',
			'wp-pinch/list-taxonomies',
			'wp-pinch/manage-terms',
			'wp-pinch/duplicate-post',
			'wp-pinch/schedule-post',
			'wp-pinch/find-replace-content',
			'wp-pinch/reorder-posts',
			'wp-pinch/compare-revisions',

			// Media.
			'wp-pinch/list-media',
			'wp-pinch/upload-media',
			'wp-pinch/delete-media',
			'wp-pinch/set-featured-image',
			'wp-pinch/list-unused-media',
			'wp-pinch/regenerate-media-thumbnails',

			// Users.
			'wp-pinch/list-users',
			'wp-pinch/get-user',
			'wp-pinch/update-user-role',
			'wp-pinch/create-user',
			'wp-pinch/delete-user',
			'wp-pinch/reset-user-password',

			// Comments.
			'wp-pinch/list-comments',
			'wp-pinch/moderate-comment',
			'wp-pinch/create-comment',
			'wp-pinch/update-comment',
			'wp-pinch/delete-comment',

			// Settings.
			'wp-pinch/get-option',
			'wp-pinch/update-option',

			// Plugins & Themes.
			'wp-pinch/list-plugins',
			'wp-pinch/toggle-plugin',
			'wp-pinch/list-themes',
			'wp-pinch/switch-theme',
			'wp-pinch/manage-plugin-lifecycle',
			'wp-pinch/manage-theme-lifecycle',

			// Analytics & Maintenance.
			'wp-pinch/site-health',
			'wp-pinch/content-health-report',
			'wp-pinch/recent-activity',
			'wp-pinch/search-content',
			'wp-pinch/export-data',
			'wp-pinch/site-digest',
			'wp-pinch/related-posts',
			'wp-pinch/synthesize',
			'wp-pinch/analytics-narratives',
			'wp-pinch/submit-conversational-form',
			'wp-pinch/generate-tldr',
			'wp-pinch/suggest-links',
			'wp-pinch/suggest-terms',
			'wp-pinch/quote-bank',
			'wp-pinch/what-do-i-know',
			'wp-pinch/project-assembly',
			'wp-pinch/spaced-resurfacing',
			'wp-pinch/find-similar',
			'wp-pinch/knowledge-graph',
			'wp-pinch/pinchdrop-generate',

			// Navigation Menus.
			'wp-pinch/list-menus',
			'wp-pinch/manage-menu-item',

			// Post Meta.
			'wp-pinch/get-post-meta',
			'wp-pinch/update-post-meta',

			// Revisions.
			'wp-pinch/list-revisions',
			'wp-pinch/restore-revision',

			// Bulk Operations.
			'wp-pinch/bulk-edit-posts',

			// Cron Management.
			'wp-pinch/list-cron-events',
			'wp-pinch/manage-cron',
			'wp-pinch/get-transient',
			'wp-pinch/set-transient',
			'wp-pinch/delete-transient',
			'wp-pinch/list-rewrite-rules',
			'wp-pinch/flush-rewrite-rules',
			'wp-pinch/maintenance-mode-status',
			'wp-pinch/set-maintenance-mode',
			'wp-pinch/search-replace-db-scoped',
			'wp-pinch/list-language-packs',
			'wp-pinch/install-language-pack',
			'wp-pinch/activate-language-pack',
			'wp-pinch/flush-cache',
			'wp-pinch/check-broken-links',
			'wp-pinch/get-php-error-log',
			'wp-pinch/list-posts-missing-meta',
			'wp-pinch/list-custom-post-types',

			// GEO & SEO.
			'wp-pinch/generate-llms-txt',
			'wp-pinch/get-llms-txt',
			'wp-pinch/bulk-seo-meta',
			'wp-pinch/suggest-internal-links',
			'wp-pinch/generate-schema-markup',
			'wp-pinch/suggest-seo-improvements',
		);
	}

	/**
	 * WooCommerce-only ability names.
	 *
	 * @return string[]
	 */
	private static function get_woo_ability_names(): array {
		return array(
			'wp-pinch/woo-list-products',
			'wp-pinch/woo-get-product',
			'wp-pinch/woo-create-product',
			'wp-pinch/woo-update-product',
			'wp-pinch/woo-delete-product',
			'wp-pinch/woo-list-orders',
			'wp-pinch/woo-get-order',
			'wp-pinch/woo-create-order',
			'wp-pinch/woo-update-order',
			'wp-pinch/woo-manage-order',
			'wp-pinch/woo-adjust-stock',
			'wp-pinch/woo-bulk-adjust-stock',
			'wp-pinch/woo-list-low-stock',
			'wp-pinch/woo-list-out-of-stock',
			'wp-pinch/woo-list-variations',
			'wp-pinch/woo-update-variation',
			'wp-pinch/woo-list-product-taxonomies',
			'wp-pinch/woo-add-order-note',
			'wp-pinch/woo-mark-fulfilled',
			'wp-pinch/woo-cancel-order-safe',
			'wp-pinch/woo-create-refund',
			'wp-pinch/woo-list-refund-eligible-orders',
			'wp-pinch/woo-create-coupon',
			'wp-pinch/woo-update-coupon',
			'wp-pinch/woo-expire-coupon',
			'wp-pinch/woo-list-customers',
			'wp-pinch/woo-get-customer',
			'wp-pinch/woo-sales-summary',
			'wp-pinch/woo-top-products',
			'wp-pinch/woo-orders-needing-attention',
		);
	}
}
