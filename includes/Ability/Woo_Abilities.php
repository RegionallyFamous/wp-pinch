<?php
/**
 * WooCommerce abilities.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce abilities.
 */
class Woo_Abilities {
	use Woo_Helpers_Trait;
	use Woo_Inventory_Execute_Trait;
	use Woo_Operations_Insights_Execute_Trait;
	use Woo_Products_Orders_Execute_Trait;
	use Woo_Register_Trait;

	/**
	 * Allowed order statuses.
	 *
	 * @var array<int, string>
	 */
	private const ALLOWED_ORDER_STATUSES = array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' );

	/**
	 * Cancellable statuses.
	 *
	 * @var array<int, string>
	 */
	private const CANCELLABLE_ORDER_STATUSES = array( 'pending', 'on-hold', 'processing', 'failed' );

	/**
	 * Allowed product statuses.
	 *
	 * @var array<int, string>
	 */
	private const ALLOWED_PRODUCT_STATUSES = array( 'draft', 'pending', 'private', 'publish' );

	/**
	 * Allowed coupon types.
	 *
	 * @var array<int, string>
	 */
	private const ALLOWED_COUPON_TYPES = array( 'fixed_cart', 'percent', 'fixed_product' );

	/**
	 * Register WooCommerce abilities when WooCommerce is active.
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) || ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		self::register_products();
		self::register_orders();
		self::register_inventory();
		self::register_fulfillment_refunds();
		self::register_coupons();
		self::register_customers();
		self::register_analytics();
	}
}
