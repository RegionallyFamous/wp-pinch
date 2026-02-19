<?php
/**
 * WooCommerce registration for inventory and operations.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

use WP_Pinch\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * Registers inventory, fulfillment, and refund abilities.
 */
trait Woo_Register_Inventory_Operations_Trait {

	/**
	 * Register inventory abilities.
	 */
	private static function register_inventory(): void {
		Abilities::register_ability(
			'wp-pinch/woo-adjust-stock',
			__( 'Adjust WooCommerce Stock', 'wp-pinch' ),
			__( 'Adjust stock quantity for a single product by a delta.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'product_id', 'quantity_delta' ),
				'properties' => array(
					'product_id'      => array( 'type' => 'integer' ),
					'quantity_delta'  => array( 'type' => 'integer' ),
					'reason'          => array(
						'type'    => 'string',
						'default' => '',
					),
					'manage_stock_on' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_products',
			array( __CLASS__, 'execute_woo_adjust_stock' )
		);

		Abilities::register_ability(
			'wp-pinch/woo-bulk-adjust-stock',
			__( 'Bulk Adjust WooCommerce Stock', 'wp-pinch' ),
			__( 'Adjust stock quantity for multiple products.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'adjustments' ),
				'properties' => array( 'adjustments' => array( 'type' => 'array' ) ),
			),
			array( 'type' => 'object' ),
			'edit_products',
			array( __CLASS__, 'execute_woo_bulk_adjust_stock' )
		);

		Abilities::register_ability(
			'wp-pinch/woo-list-low-stock',
			__( 'List WooCommerce Low Stock Products', 'wp-pinch' ),
			__( 'List products below a given stock threshold.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'threshold' => array(
						'type'    => 'integer',
						'default' => 2,
					),
					'per_page'  => array(
						'type'    => 'integer',
						'default' => 50,
					),
					'page'      => array(
						'type'    => 'integer',
						'default' => 1,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_products',
			array( __CLASS__, 'execute_woo_list_low_stock' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/woo-list-out-of-stock',
			__( 'List WooCommerce Out of Stock Products', 'wp-pinch' ),
			__( 'List out-of-stock products.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'per_page' => array(
						'type'    => 'integer',
						'default' => 50,
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_products',
			array( __CLASS__, 'execute_woo_list_out_of_stock' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/woo-list-variations',
			__( 'List WooCommerce Variations', 'wp-pinch' ),
			__( 'List variations for a variable product.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'product_id' ),
				'properties' => array(
					'product_id' => array( 'type' => 'integer' ),
					'per_page'   => array(
						'type'    => 'integer',
						'default' => 100,
					),
					'page'       => array(
						'type'    => 'integer',
						'default' => 1,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_products',
			array( __CLASS__, 'execute_woo_list_variations' ),
			true
		);

		Abilities::register_ability(
			'wp-pinch/woo-update-variation',
			__( 'Update WooCommerce Variation', 'wp-pinch' ),
			__( 'Update mutable fields for a product variation.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'variation_id' ),
				'properties' => array(
					'variation_id'   => array( 'type' => 'integer' ),
					'regular_price'  => array( 'type' => 'string' ),
					'sale_price'     => array( 'type' => 'string' ),
					'stock_quantity' => array( 'type' => 'integer' ),
					'stock_status'   => array(
						'type' => 'string',
						'enum' => array( 'instock', 'outofstock', 'onbackorder' ),
					),
					'manage_stock'   => array( 'type' => 'boolean' ),
					'status'         => array(
						'type' => 'string',
						'enum' => self::ALLOWED_PRODUCT_STATUSES,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_products',
			array( __CLASS__, 'execute_woo_update_variation' )
		);

		Abilities::register_ability(
			'wp-pinch/woo-list-product-taxonomies',
			__( 'List WooCommerce Product Taxonomies', 'wp-pinch' ),
			__( 'List product categories, tags, and global attributes.', 'wp-pinch' ),
			array( 'type' => 'object' ),
			array( 'type' => 'object' ),
			'edit_products',
			array( __CLASS__, 'execute_woo_list_product_taxonomies' ),
			true
		);
	}

	/**
	 * Register fulfillment and refund abilities.
	 */
	private static function register_fulfillment_refunds(): void {
		Abilities::register_ability(
			'wp-pinch/woo-add-order-note',
			__( 'Add WooCommerce Order Note', 'wp-pinch' ),
			__( 'Add an internal or customer-visible note to an order.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'order_id', 'note' ),
				'properties' => array(
					'order_id'      => array( 'type' => 'integer' ),
					'note'          => array( 'type' => 'string' ),
					'customer_note' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_shop_orders',
			array( __CLASS__, 'execute_woo_add_order_note' )
		);

		Abilities::register_ability(
			'wp-pinch/woo-mark-fulfilled',
			__( 'Mark WooCommerce Order Fulfilled', 'wp-pinch' ),
			__( 'Safely mark an order as completed with optional fulfillment note.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'order_id' ),
				'properties' => array(
					'order_id'         => array( 'type' => 'integer' ),
					'tracking_number'  => array(
						'type'    => 'string',
						'default' => '',
					),
					'tracking_carrier' => array(
						'type'    => 'string',
						'default' => '',
					),
					'note'             => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_shop_orders',
			array( __CLASS__, 'execute_woo_mark_fulfilled' )
		);

		Abilities::register_ability(
			'wp-pinch/woo-cancel-order-safe',
			__( 'Cancel WooCommerce Order Safely', 'wp-pinch' ),
			__( 'Cancel an order only from supported pre-fulfillment statuses.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'order_id', 'confirm' ),
				'properties' => array(
					'order_id' => array( 'type' => 'integer' ),
					'confirm'  => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'reason'   => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_shop_orders',
			array( __CLASS__, 'execute_woo_cancel_order_safe' )
		);

		Abilities::register_ability(
			'wp-pinch/woo-create-refund',
			__( 'Create WooCommerce Refund', 'wp-pinch' ),
			__( 'Create a full or partial refund with safety guards.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'required'   => array( 'order_id' ),
				'properties' => array(
					'order_id'        => array( 'type' => 'integer' ),
					'amount'          => array( 'type' => 'number' ),
					'reason'          => array(
						'type'    => 'string',
						'default' => '',
					),
					'restock_items'   => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'idempotency_key' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_shop_orders',
			array( __CLASS__, 'execute_woo_create_refund' )
		);

		Abilities::register_ability(
			'wp-pinch/woo-list-refund-eligible-orders',
			__( 'List Refund Eligible WooCommerce Orders', 'wp-pinch' ),
			__( 'List orders with refundable remaining amount.', 'wp-pinch' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'per_page' => array(
						'type'    => 'integer',
						'default' => 50,
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_shop_orders',
			array( __CLASS__, 'execute_woo_list_refund_eligible_orders' ),
			true
		);
	}
}
