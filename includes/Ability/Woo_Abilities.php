<?php
/**
 * WooCommerce abilities — list products, manage order (registered only when WooCommerce is active).
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

use WP_Pinch\Abilities;
use WP_Pinch\Audit_Table;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce abilities.
 */
class Woo_Abilities {

	/**
	 * Register WooCommerce abilities when WooCommerce is active.
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) || ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		Abilities::register_ability(
			'wp-pinch/woo-list-products',
			__( 'List WooCommerce Products', 'wp-pinch' ),
			__( 'List WooCommerce products with optional filters.', 'wp-pinch' ),
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
						'type'        => 'string',
						'default'     => '',
						'description' => 'Product category slug.',
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
			'wp-pinch/woo-manage-order',
			__( 'Manage WooCommerce Order', 'wp-pinch' ),
			__( 'Get details or update the status of a WooCommerce order.', 'wp-pinch' ),
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
						'type'        => 'string',
						'default'     => '',
						'description' => 'New order status (for update_status). e.g. processing, completed, on-hold, cancelled.',
					),
					'note'     => array(
						'type'        => 'string',
						'default'     => '',
						'description' => 'Optional order note to add.',
					),
				),
			),
			array( 'type' => 'object' ),
			'edit_shop_orders',
			array( __CLASS__, 'execute_woo_manage_order' )
		);
	}

	/**
	 * List WooCommerce products.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_list_products( array $input ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'error' => __( 'WooCommerce is not active.', 'wp-pinch' ) );
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => sanitize_key( $input['status'] ?? 'publish' ),
			'posts_per_page' => min( absint( $input['per_page'] ?? 20 ), 100 ),
			'paged'          => absint( $input['page'] ?? 1 ),
		);

		if ( 'any' === $args['post_status'] ) {
			$args['post_status'] = array( 'publish', 'draft', 'pending', 'private' );
		}

		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}

		if ( ! empty( $input['category'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => sanitize_text_field( $input['category'] ),
				),
			);
		}

		$query           = new \WP_Query( $args );
		$products        = array();
		$product_objects = array();
		$all_cat_ids     = array();

		foreach ( $query->posts as $post ) {
			$product = wc_get_product( $post->ID );
			if ( ! $product ) {
				continue;
			}
			$product_objects[] = $product;
			$cat_ids           = $product->get_category_ids();
			if ( ! empty( $cat_ids ) ) {
				$all_cat_ids = array_merge( $all_cat_ids, $cat_ids );
			}
		}

		$cat_name_map = array();
		$all_cat_ids  = array_unique( array_filter( $all_cat_ids ) );
		if ( ! empty( $all_cat_ids ) ) {
			$cat_terms = get_terms(
				array(
					'include'    => $all_cat_ids,
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
				)
			);
			if ( ! is_wp_error( $cat_terms ) ) {
				foreach ( $cat_terms as $term ) {
					$cat_name_map[ $term->term_id ] = $term->name;
				}
			}
		}

		foreach ( $product_objects as $product ) {
			$cat_names = array();
			foreach ( $product->get_category_ids() as $cid ) {
				if ( isset( $cat_name_map[ $cid ] ) ) {
					$cat_names[] = $cat_name_map[ $cid ];
				}
			}
			$products[] = array(
				'id'            => $product->get_id(),
				'name'          => $product->get_name(),
				'slug'          => $product->get_slug(),
				'type'          => $product->get_type(),
				'status'        => $product->get_status(),
				'sku'           => $product->get_sku(),
				'price'         => $product->get_price(),
				'regular_price' => $product->get_regular_price(),
				'sale_price'    => $product->get_sale_price(),
				'stock_status'  => $product->get_stock_status(),
				'stock_qty'     => $product->get_stock_quantity(),
				'categories'    => $cat_names,
				'url'           => $product->get_permalink(),
			);
		}

		return array(
			'products'    => $products,
			'total'       => $query->found_posts,
			'total_pages' => $query->max_num_pages,
			'page'        => $args['paged'],
		);
	}

	/**
	 * Get or manage a WooCommerce order.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_manage_order( array $input ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'error' => __( 'WooCommerce is not active.', 'wp-pinch' ) );
		}

		$order_id = absint( $input['order_id'] );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return array( 'error' => __( 'Order not found.', 'wp-pinch' ) );
		}

		$action = sanitize_key( $input['action'] ?? 'get' );

		if ( ! empty( $input['note'] ) ) {
			$order->add_order_note( sanitize_text_field( $input['note'] ) );
		}

		if ( 'update_status' === $action ) {
			$new_status = sanitize_key( $input['status'] ?? '' );
			if ( '' === $new_status ) {
				return array( 'error' => __( 'Status is required for update_status action.', 'wp-pinch' ) );
			}
			$old_status = $order->get_status();
			$order->update_status( $new_status );
			Audit_Table::insert(
				'woo_order_updated',
				'ability',
				sprintf( 'Order #%d status changed from %s to %s.', $order_id, $old_status, $new_status ),
				array(
					'order_id'   => $order_id,
					'old_status' => $old_status,
					'new_status' => $new_status,
				)
			);
			return array(
				'order_id'   => $order_id,
				'old_status' => $old_status,
				'new_status' => $new_status,
				'updated'    => true,
			);
		}

		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'name'     => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'total'    => $item->get_total(),
				'sku'      => $item->get_product() ? $item->get_product()->get_sku() : '',
			);
		}

		return array(
			'order_id'     => $order_id,
			'status'       => $order->get_status(),
			'total'        => $order->get_total(),
			'currency'     => $order->get_currency(),
			'date_created' => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : null,
			'customer'     => array(
				'id'   => $order->get_customer_id(),
				'name' => $order->get_billing_first_name(),
			),
			'items'        => $items,
			'shipping'     => $order->get_shipping_total(),
			'notes'        => array_map(
				function ( $note ) {
					return array(
						'content' => $note->comment_content,
						'date'    => $note->comment_date,
					);
				},
				wc_get_order_notes(
					array(
						'order_id' => $order_id,
						'limit'    => 10,
					)
				)
			),
		);
	}
}
