<?php
/**
 * Woo passthrough methods for Abilities facade.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps Woo ability passthroughs grouped away from the core facade file.
 */
trait Woo_Passthrough_Trait {

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_list_products */
	public static function execute_woo_list_products( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_list_products( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_get_product */
	public static function execute_woo_get_product( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_get_product( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_create_product */
	public static function execute_woo_create_product( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_create_product( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_update_product */
	public static function execute_woo_update_product( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_update_product( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_delete_product */
	public static function execute_woo_delete_product( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_delete_product( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_list_orders */
	public static function execute_woo_list_orders( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_list_orders( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_get_order */
	public static function execute_woo_get_order( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_get_order( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_create_order */
	public static function execute_woo_create_order( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_create_order( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_update_order */
	public static function execute_woo_update_order( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_update_order( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_manage_order */
	public static function execute_woo_manage_order( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_manage_order( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_adjust_stock */
	public static function execute_woo_adjust_stock( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_adjust_stock( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_bulk_adjust_stock */
	public static function execute_woo_bulk_adjust_stock( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_bulk_adjust_stock( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_list_low_stock */
	public static function execute_woo_list_low_stock( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_list_low_stock( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_list_out_of_stock */
	public static function execute_woo_list_out_of_stock( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_list_out_of_stock( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_list_variations */
	public static function execute_woo_list_variations( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_list_variations( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_update_variation */
	public static function execute_woo_update_variation( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_update_variation( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_list_product_taxonomies */
	public static function execute_woo_list_product_taxonomies( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_list_product_taxonomies( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_add_order_note */
	public static function execute_woo_add_order_note( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_add_order_note( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_mark_fulfilled */
	public static function execute_woo_mark_fulfilled( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_mark_fulfilled( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_cancel_order_safe */
	public static function execute_woo_cancel_order_safe( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_cancel_order_safe( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_create_refund */
	public static function execute_woo_create_refund( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_create_refund( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_list_refund_eligible_orders */
	public static function execute_woo_list_refund_eligible_orders( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_list_refund_eligible_orders( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_create_coupon */
	public static function execute_woo_create_coupon( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_create_coupon( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_update_coupon */
	public static function execute_woo_update_coupon( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_update_coupon( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_expire_coupon */
	public static function execute_woo_expire_coupon( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_expire_coupon( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_list_customers */
	public static function execute_woo_list_customers( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_list_customers( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_get_customer */
	public static function execute_woo_get_customer( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_get_customer( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_sales_summary */
	public static function execute_woo_sales_summary( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_sales_summary( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_top_products */
	public static function execute_woo_top_products( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_top_products( $input );
	}

	/** @see \WP_Pinch\Ability\Woo_Abilities::execute_woo_orders_needing_attention */
	public static function execute_woo_orders_needing_attention( array $input ): array {
		return Ability\Woo_Abilities::execute_woo_orders_needing_attention( $input );
	}
}
