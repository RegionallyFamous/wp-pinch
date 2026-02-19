<?php
/**
 * WooCommerce registration for products and orders.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

use WP_Pinch\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * Registers product and order abilities.
 */
trait Woo_Register_Products_Orders_Trait {

	/**
	 * Register product abilities.
	 */
	private static function register_products(): void {
		Abilities::register_ability(
			'wp-pinch/woo-list-products',
			__( 'List WooCommerce Products', 'wp-pinch' ),
			__( 'List WooCommerce products with filters and pagination.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'status'   => array(
						'type'    => 'string',
						'default' => 'publish',
						'enum'    => array( 'publish', 'draft', 'pending', 'private', 'any' ),
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'category' => array(
						'type'    => 'string',
						'default' => '',
					),
					'search'   => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_products',
			array( __CLASS__, 'execute_woo_list_products' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/woo-get-product',
			__( 'Get WooCommerce Product', 'wp-pinch' ),
			__( 'Get a WooCommerce product by ID.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'product_id' ),
				'properties' => array( 'product_id' => array( 'type' => 'integer' ) ),
			),
			array( 'type' => 'object' ),
			'edit_products',
			array( __CLASS__, 'execute_woo_get_product' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/woo-create-product',
			__( 'Create WooCommerce Product', 'wp-pinch' ),
			__( 'Create a WooCommerce product with safe defaults.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'name' ),
				'properties' => array(
					'name'              => array( 'type' => 'string' ),
					'type'              => array(
						'type'    => 'string',
						'default' => 'simple',
						'enum'    => array( 'simple', 'variable' ),
					),
					'status'            => array(
						'type'    => 'string',
						'default' => 'draft',
						'enum'    => self::ALLOWED_PRODUCT_STATUSES,
					),
					'sku'               => array(
						'type'    => 'string',
						'default' => '',
					),
					'regular_price'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'sale_price'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'description'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'short_description' => array(
						'type'    => 'string',
						'default' => '',
					),
					'manage_stock'      => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'stock_quantity'    => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			),
			array( 'type' => 'object' ),
			'publish_products',
			array( __CLASS__, 'execute_woo_create_product' )
		);

		Abilities::register_ability(
			'wp-pinch/woo-update-product',
			__( 'Update WooCommerce Product', 'wp-pinch' ),
			__( 'Update mutable WooCommerce product fields.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'product_id' ),
				'properties' => array(
					'product_id'        => array( 'type' => 'integer' ),
					'name'              => array( 'type' => 'string' ),
					'status'            => array(
						'type' => 'string',
						'enum' => self::ALLOWED_PRODUCT_STATUSES,
					),
					'sku'               => array( 'type' => 'string' ),
					'regular_price'     => array( 'type' => 'string' ),
					'sale_price'        => array( 'type' => 'string' ),
					'description'       => array( 'type' => 'string' ),
					'short_description' => array( 'type' => 'string' ),
					'manage_stock'      => array( 'type' => 'boolean' ),
					'stock_quantity'    => array( 'type' => 'integer' ),
				),
			),
			array( 'type' => 'object' ),
			'edit_products',
			array( __CLASS__, 'execute_woo_update_product' )
		);

		Abilities::register_ability(
			'wp-pinch/woo-delete-product',
			__( 'Delete WooCommerce Product', 'wp-pinch' ),
			__( 'Delete or trash a WooCommerce product.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'product_id' ),
				'properties' => array(
					'product_id' => array( 'type' => 'integer' ),
					'force'      => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			),
			array( 'type' => 'object' ),
			'delete_products',
			array( __CLASS__, 'execute_woo_delete_product' )
		);
	}

	/**
	 * Register order abilities.
	 */
	private static function register_orders(): void {
		Abilities::register_ability(
			'wp-pinch/woo-list-orders',
			__( 'List WooCommerce Orders', 'wp-pinch' ),
			__( 'List WooCommerce orders with filters and pagination.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'status'    => array(
						'type'    => 'string',
						'default' => 'any',
					),
					'per_page'  => array(
						'type'    => 'integer',
						'default' => 20,
					),
					'page'      => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'search'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'after'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'before'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'customer'  => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'max_pages' => array(
						'type'    => 'integer',
						'default' => 5,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_shop_orders',
			array( __CLASS__, 'execute_woo_list_orders' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/woo-get-order',
			__( 'Get WooCommerce Order', 'wp-pinch' ),
			__( 'Get a WooCommerce order by ID.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'order_id' ),
				'properties' => array(
					'order_id'                 => array( 'type' => 'integer' ),
					'include_sensitive_fields' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_shop_orders',
			array( __CLASS__, 'execute_woo_get_order' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/woo-create-order',
			__( 'Create WooCommerce Order', 'wp-pinch' ),
			__( 'Create a WooCommerce order from product line items.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'line_items' ),
				'properties' => array(
					'customer_id' => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'status'      => array(
						'type'    => 'string',
						'default' => 'pending',
						'enum'    => self::ALLOWED_ORDER_STATUSES,
					),
					'line_items'  => array( 'type' => 'array' ),
					'billing'     => array( 'type' => 'object' ),
					'shipping'    => array( 'type' => 'object' ),
					'note'        => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_shop_orders',
			array( __CLASS__, 'execute_woo_create_order' )
		);

		Abilities::register_ability(
			'wp-pinch/woo-update-order',
			__( 'Update WooCommerce Order', 'wp-pinch' ),
			__( 'Update mutable WooCommerce order fields safely.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'order_id' ),
				'properties' => array(
					'order_id' => array( 'type' => 'integer' ),
					'status'   => array(
						'type' => 'string',
						'enum' => self::ALLOWED_ORDER_STATUSES,
					),
					'note'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'billing'  => array( 'type' => 'object' ),
					'shipping' => array( 'type' => 'object' ),
				),
			),
			array( 'type' => 'object' ),
			'edit_shop_orders',
			array( __CLASS__, 'execute_woo_update_order' )
		);

		// Legacy compatibility ability.
		Abilities::register_ability(
			'wp-pinch/woo-manage-order',
			__( 'Manage WooCommerce Order', 'wp-pinch' ),
			__( 'Compatibility alias for order get/update status workflows.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'order_id' ),
				'properties' => array(
					'order_id' => array( 'type' => 'integer' ),
					'action'   => array(
						'type'    => 'string',
						'default' => 'get',
						'enum'    => array( 'get', 'update_status' ),
					),
					'status'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'note'     => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_shop_orders',
			array( __CLASS__, 'execute_woo_manage_order' )
		);
	}
}
