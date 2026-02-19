<?php
/**
 * WooCommerce registration for coupons, customers, and analytics.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

use WP_Pinch\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * Registers commercial and reporting abilities.
 */
trait Woo_Register_Commercial_Intelligence_Trait {

	/**
	 * Register coupon abilities.
	 */
	private static function register_coupons(): void {
		Abilities::register_ability(
			'wp-pinch/woo-create-coupon',
			__( 'Create WooCommerce Coupon', 'wp-pinch' ),
			__( 'Create a WooCommerce coupon with validation.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'code', 'amount' ),
				'properties' => array(
					'code'           => array( 'type' => 'string' ),
					'amount'         => array( 'type' => 'string' ),
					'discount_type'  => array(
						'type'    => 'string',
						'default' => 'fixed_cart',
						'enum'    => self::ALLOWED_COUPON_TYPES,
					),
					'usage_limit'    => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'individual_use' => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'description'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'expires_at'     => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			),
			array( 'type' => 'object' ),
			'manage_woocommerce',
			array( __CLASS__, 'execute_woo_create_coupon' )
		);

		Abilities::register_ability(
			'wp-pinch/woo-update-coupon',
			__( 'Update WooCommerce Coupon', 'wp-pinch' ),
			__( 'Update mutable fields of a WooCommerce coupon.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'coupon_id' ),
				'properties' => array(
					'coupon_id'      => array( 'type' => 'integer' ),
					'amount'         => array( 'type' => 'string' ),
					'discount_type'  => array(
						'type' => 'string',
						'enum' => self::ALLOWED_COUPON_TYPES,
					),
					'usage_limit'    => array( 'type' => 'integer' ),
					'individual_use' => array( 'type' => 'boolean' ),
					'description'    => array( 'type' => 'string' ),
					'expires_at'     => array( 'type' => 'string' ),
				),
			),
			array( 'type' => 'object' ),
			'manage_woocommerce',
			array( __CLASS__, 'execute_woo_update_coupon' )
		);

		Abilities::register_ability(
			'wp-pinch/woo-expire-coupon',
			__( 'Expire WooCommerce Coupon', 'wp-pinch' ),
			__( 'Force a coupon to expire immediately.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'coupon_id' ),
				'properties' => array( 'coupon_id' => array( 'type' => 'integer' ) ),
			),
			array( 'type' => 'object' ),
			'manage_woocommerce',
			array( __CLASS__, 'execute_woo_expire_coupon' )
		);
	}

	/**
	 * Register customer abilities.
	 */
	private static function register_customers(): void {
		Abilities::register_ability(
			'wp-pinch/woo-list-customers',
			__( 'List WooCommerce Customers', 'wp-pinch' ),
			__( 'List WooCommerce customers with balanced redaction defaults.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'per_page'                 => array(
						'type'    => 'integer',
						'default' => 20,
					),
					'page'                     => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'search'                   => array(
						'type'    => 'string',
						'default' => '',
					),
					'include_sensitive_fields' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			),
			array( 'type' => 'object' ),
			'manage_woocommerce',
			array( __CLASS__, 'execute_woo_list_customers' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/woo-get-customer',
			__( 'Get WooCommerce Customer', 'wp-pinch' ),
			__( 'Get a WooCommerce customer by user ID with safe redaction defaults.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'customer_id' ),
				'properties' => array(
					'customer_id'              => array( 'type' => 'integer' ),
					'include_sensitive_fields' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			),
			array( 'type' => 'object' ),
			'manage_woocommerce',
			array( __CLASS__, 'execute_woo_get_customer' ),
			true
		);
	}

	/**
	 * Register analytics abilities.
	 */
	private static function register_analytics(): void {
		Abilities::register_ability(
			'wp-pinch/woo-sales-summary',
			__( 'WooCommerce Sales Summary', 'wp-pinch' ),
			__( 'Summarize WooCommerce sales for day/week/month/custom range.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'window'     => array(
						'type'    => 'string',
						'default' => 'week',
						'enum'    => array( 'day', 'week', 'month', 'custom' ),
					),
					'start_date' => array(
						'type'    => 'string',
						'default' => '',
					),
					'end_date'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'max_orders' => array(
						'type'    => 'integer',
						'default' => 500,
					),
				),
			),
			array( 'type' => 'object' ),
			'view_woocommerce_reports',
			array( __CLASS__, 'execute_woo_sales_summary' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/woo-top-products',
			__( 'WooCommerce Top Products', 'wp-pinch' ),
			__( 'Return top-selling products for a date window.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'days'       => array(
						'type'    => 'integer',
						'default' => 30,
					),
					'limit'      => array(
						'type'    => 'integer',
						'default' => 10,
					),
					'max_orders' => array(
						'type'    => 'integer',
						'default' => 500,
					),
				),
			),
			array( 'type' => 'object' ),
			'view_woocommerce_reports',
			array( __CLASS__, 'execute_woo_top_products' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/woo-orders-needing-attention',
			__( 'WooCommerce Orders Needing Attention', 'wp-pinch' ),
			__( 'Find aged or potentially stuck orders that require manual review.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'older_than_hours' => array(
						'type'    => 'integer',
						'default' => 48,
					),
					'per_page'         => array(
						'type'    => 'integer',
						'default' => 100,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_shop_orders',
			array( __CLASS__, 'execute_woo_orders_needing_attention' ),
			true
		);
	}
}
