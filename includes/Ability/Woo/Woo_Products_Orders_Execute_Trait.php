<?php
/**
 * WooCommerce product and order execute handlers.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

use WP_Pinch\Audit_Table;

defined( 'ABSPATH' ) || exit;

/**
 * Product and order ability execution methods.
 */
trait Woo_Products_Orders_Execute_Trait {

	/**
	 * List WooCommerce products.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_list_products( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => sanitize_key( $input['status'] ?? 'publish' ),
			'posts_per_page' => self::normalize_per_page( $input['per_page'] ?? 20, 100 ),
			'paged'          => self::normalize_page( $input['page'] ?? 1 ),
			'no_found_rows'  => false,
		);

		if ( 'any' === $args['post_status'] ) {
			$args['post_status'] = array( 'publish', 'draft', 'pending', 'private' );
		}

		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( (string) $input['search'] );
		}

		if ( ! empty( $input['category'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => sanitize_text_field( (string) $input['category'] ),
				),
			);
		}

		$query    = new \WP_Query( $args );
		$products = array();
		foreach ( $query->posts as $post ) {
			$product = wc_get_product( $post->ID );
			if ( ! $product ) {
				continue;
			}
			$products[] = self::format_product( $product );
		}

		return array(
			'products'    => $products,
			'total'       => $query->found_posts,
			'total_pages' => $query->max_num_pages,
			'page'        => (int) $args['paged'],
		);
	}

	/**
	 * Get a product.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_get_product( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$product = wc_get_product( absint( $input['product_id'] ?? 0 ) );
		if ( ! $product ) {
			return array( 'error' => __( 'Product not found.', 'wp-pinch' ) );
		}
		return array( 'product' => self::format_product( $product ) );
	}

	/**
	 * Create a product.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_create_product( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$name = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
		if ( '' === $name ) {
			return array( 'error' => __( 'Product name is required.', 'wp-pinch' ) );
		}

		$type   = sanitize_key( (string) ( $input['type'] ?? 'simple' ) );
		$status = sanitize_key( (string) ( $input['status'] ?? 'draft' ) );
		if ( ! in_array( $status, self::ALLOWED_PRODUCT_STATUSES, true ) ) {
			return array( 'error' => __( 'Invalid product status.', 'wp-pinch' ) );
		}

		$product = 'variable' === $type ? new \WC_Product_Variable() : new \WC_Product_Simple();
		$product->set_name( $name );
		$product->set_status( $status );
		$product->set_sku( sanitize_text_field( (string) ( $input['sku'] ?? '' ) ) );
		$product->set_regular_price( wc_format_decimal( $input['regular_price'] ?? '' ) );
		$product->set_sale_price( wc_format_decimal( $input['sale_price'] ?? '' ) );
		$product->set_description( wp_kses_post( (string) ( $input['description'] ?? '' ) ) );
		$product->set_short_description( wp_kses_post( (string) ( $input['short_description'] ?? '' ) ) );
		$product->set_manage_stock( ! empty( $input['manage_stock'] ) );
		if ( ! empty( $input['manage_stock'] ) ) {
			$product->set_stock_quantity( absint( $input['stock_quantity'] ?? 0 ) );
		}
		$product_id = $product->save();

		Audit_Table::insert(
			'woo_product_created',
			'ability',
			sprintf( 'Product #%d created.', $product_id ),
			array( 'product_id' => $product_id )
		);

		return array(
			'created' => true,
			'product' => self::format_product( wc_get_product( $product_id ) ),
		);
	}

	/**
	 * Update product fields.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_update_product( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$product = wc_get_product( absint( $input['product_id'] ?? 0 ) );
		if ( ! $product ) {
			return array( 'error' => __( 'Product not found.', 'wp-pinch' ) );
		}

		$changed = array();
		if ( array_key_exists( 'name', $input ) ) {
			$product->set_name( sanitize_text_field( (string) $input['name'] ) );
			$changed[] = 'name';
		}
		if ( array_key_exists( 'status', $input ) ) {
			$status = sanitize_key( (string) $input['status'] );
			if ( ! in_array( $status, self::ALLOWED_PRODUCT_STATUSES, true ) ) {
				return array( 'error' => __( 'Invalid product status.', 'wp-pinch' ) );
			}
			$product->set_status( $status );
			$changed[] = 'status';
		}
		if ( array_key_exists( 'sku', $input ) ) {
			$product->set_sku( sanitize_text_field( (string) $input['sku'] ) );
			$changed[] = 'sku';
		}
		if ( array_key_exists( 'regular_price', $input ) ) {
			$product->set_regular_price( wc_format_decimal( $input['regular_price'] ) );
			$changed[] = 'regular_price';
		}
		if ( array_key_exists( 'sale_price', $input ) ) {
			$product->set_sale_price( wc_format_decimal( $input['sale_price'] ) );
			$changed[] = 'sale_price';
		}
		if ( array_key_exists( 'description', $input ) ) {
			$product->set_description( wp_kses_post( (string) $input['description'] ) );
			$changed[] = 'description';
		}
		if ( array_key_exists( 'short_description', $input ) ) {
			$product->set_short_description( wp_kses_post( (string) $input['short_description'] ) );
			$changed[] = 'short_description';
		}
		if ( array_key_exists( 'manage_stock', $input ) ) {
			$product->set_manage_stock( ! empty( $input['manage_stock'] ) );
			$changed[] = 'manage_stock';
		}
		if ( array_key_exists( 'stock_quantity', $input ) ) {
			$product->set_stock_quantity( absint( $input['stock_quantity'] ) );
			$changed[] = 'stock_quantity';
		}

		$product->save();
		Audit_Table::insert(
			'woo_product_updated',
			'ability',
			sprintf( 'Product #%d updated.', $product->get_id() ),
			array(
				'product_id' => $product->get_id(),
				'changed'    => $changed,
			)
		);

		return array(
			'updated' => true,
			'product' => self::format_product( $product ),
		);
	}

	/**
	 * Delete/Trash product.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_delete_product( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$product_id = absint( $input['product_id'] ?? 0 );
		$force      = ! empty( $input['force'] );
		$post       = get_post( $product_id );
		if ( ! $post || 'product' !== $post->post_type ) {
			return array( 'error' => __( 'Product not found.', 'wp-pinch' ) );
		}

		$deleted = wp_delete_post( $product_id, $force );
		if ( ! $deleted ) {
			return array( 'error' => __( 'Failed to delete product.', 'wp-pinch' ) );
		}

		Audit_Table::insert(
			'woo_product_deleted',
			'ability',
			sprintf( 'Product #%d deleted.', $product_id ),
			array(
				'product_id' => $product_id,
				'force'      => $force,
			)
		);

		return array(
			'deleted'    => true,
			'product_id' => $product_id,
			'force'      => $force,
		);
	}

	/**
	 * List orders with filter/pagination.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_list_orders( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$per_page = self::normalize_per_page( $input['per_page'] ?? 20, 100 );
		$page     = self::normalize_page( $input['page'] ?? 1 );
		$status   = sanitize_key( (string) ( $input['status'] ?? 'any' ) );

		if ( 'any' === $status || '' === $status ) {
			$status = array_map(
				static function ( string $key ): string {
					return str_replace( 'wc-', '', $key );
				},
				array_keys( wc_get_order_statuses() )
			);
		} else {
			$status = array( $status );
		}

		$args = array(
			'type'     => 'shop_order',
			'status'   => $status,
			'limit'    => $per_page,
			'page'     => $page,
			'paginate' => true,
			'orderby'  => 'date',
			'order'    => 'DESC',
		);

		if ( ! empty( $input['customer'] ) ) {
			$args['customer'] = absint( $input['customer'] );
		}
		if ( ! empty( $input['search'] ) ) {
			$args['search'] = sanitize_text_field( (string) $input['search'] );
		}
		if ( ! empty( $input['after'] ) ) {
			$args['date_created'] = '>=' . sanitize_text_field( (string) $input['after'] );
		}
		if ( ! empty( $input['before'] ) ) {
			$before = sanitize_text_field( (string) $input['before'] );
			if ( ! empty( $args['date_created'] ) ) {
				$args['date_created'] = (string) $args['date_created'] . '...' . $before;
			} else {
				$args['date_created'] = '<=' . $before;
			}
		}

		$results = wc_get_orders( $args );
		if ( ! is_object( $results ) || ! isset( $results->orders ) ) {
			return array( 'error' => __( 'Could not load orders.', 'wp-pinch' ) );
		}

		$orders = array();
		foreach ( $results->orders as $order ) {
			if ( $order instanceof \WC_Order ) {
				$orders[] = self::format_order( $order, false );
			}
		}

		return array(
			'orders'      => $orders,
			'total'       => absint( $results->total ),
			'total_pages' => absint( $results->max_num_pages ),
			'page'        => $page,
			'per_page'    => $per_page,
		);
	}

	/**
	 * Get order payload.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_get_order( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$order = wc_get_order( absint( $input['order_id'] ?? 0 ) );
		if ( ! $order ) {
			return array( 'error' => __( 'Order not found.', 'wp-pinch' ) );
		}

		$include_sensitive = ! empty( $input['include_sensitive_fields'] ) && current_user_can( 'manage_options' );
		return array( 'order' => self::format_order( $order, $include_sensitive ) );
	}

	/**
	 * Create order from line items.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_create_order( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$line_items = $input['line_items'] ?? array();
		if ( ! is_array( $line_items ) || empty( $line_items ) ) {
			return array( 'error' => __( 'line_items is required.', 'wp-pinch' ) );
		}

		$order = wc_create_order(
			array(
				'customer_id' => absint( $input['customer_id'] ?? 0 ),
			)
		);
		if ( ! $order ) {
			return array( 'error' => __( 'Could not create order.', 'wp-pinch' ) );
		}

		foreach ( $line_items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$product_id = absint( $item['product_id'] ?? 0 );
			$quantity   = max( 1, absint( $item['quantity'] ?? 1 ) );
			$product    = wc_get_product( $product_id );
			if ( ! $product ) {
				/* translators: %d: WooCommerce product ID. */
				return array( 'error' => sprintf( __( 'Product %d not found.', 'wp-pinch' ), $product_id ) );
			}
			$order->add_product( $product, $quantity );
		}

		if ( ! empty( $input['billing'] ) && is_array( $input['billing'] ) ) {
			$order->set_address( self::sanitize_address( $input['billing'] ), 'billing' );
		}
		if ( ! empty( $input['shipping'] ) && is_array( $input['shipping'] ) ) {
			$order->set_address( self::sanitize_address( $input['shipping'] ), 'shipping' );
		}
		if ( ! empty( $input['note'] ) ) {
			$order->add_order_note( sanitize_text_field( (string) $input['note'] ) );
		}

		$status = sanitize_key( (string) ( $input['status'] ?? 'pending' ) );
		if ( ! in_array( $status, self::ALLOWED_ORDER_STATUSES, true ) ) {
			return array( 'error' => __( 'Invalid order status.', 'wp-pinch' ) );
		}

		$order->calculate_totals( true );
		$order->set_status( $status );
		$order->save();

		Audit_Table::insert(
			'woo_order_created',
			'ability',
			sprintf( 'Order #%d created.', $order->get_id() ),
			array( 'order_id' => $order->get_id() )
		);

		return array(
			'created' => true,
			'order'   => self::format_order( $order, false ),
		);
	}

	/**
	 * Update mutable order fields.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_update_order( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$order = wc_get_order( absint( $input['order_id'] ?? 0 ) );
		if ( ! $order ) {
			return array( 'error' => __( 'Order not found.', 'wp-pinch' ) );
		}

		$old_status = $order->get_status();
		$changed    = array();
		if ( array_key_exists( 'status', $input ) ) {
			$new_status = sanitize_key( (string) $input['status'] );
			if ( ! in_array( $new_status, self::ALLOWED_ORDER_STATUSES, true ) ) {
				return array( 'error' => __( 'Invalid order status.', 'wp-pinch' ) );
			}
			$order->set_status( $new_status );
			$changed[] = 'status';
		}
		if ( ! empty( $input['note'] ) ) {
			$order->add_order_note( sanitize_text_field( (string) $input['note'] ) );
			$changed[] = 'note';
		}
		if ( ! empty( $input['billing'] ) && is_array( $input['billing'] ) ) {
			$order->set_address( self::sanitize_address( $input['billing'] ), 'billing' );
			$changed[] = 'billing';
		}
		if ( ! empty( $input['shipping'] ) && is_array( $input['shipping'] ) ) {
			$order->set_address( self::sanitize_address( $input['shipping'] ), 'shipping' );
			$changed[] = 'shipping';
		}

		$order->save();
		Audit_Table::insert(
			'woo_order_updated',
			'ability',
			sprintf( 'Order #%d updated.', $order->get_id() ),
			array(
				'order_id'   => $order->get_id(),
				'old_status' => $old_status,
				'new_status' => $order->get_status(),
				'changed'    => $changed,
			)
		);

		return array(
			'updated' => true,
			'order'   => self::format_order( $order, false ),
		);
	}

	/**
	 * Compatibility alias for old order ability.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_manage_order( array $input ): array {
		$action = sanitize_key( (string) ( $input['action'] ?? 'get' ) );
		if ( 'update_status' === $action ) {
			return self::execute_woo_update_order(
				array(
					'order_id' => absint( $input['order_id'] ?? 0 ),
					'status'   => sanitize_key( (string) ( $input['status'] ?? '' ) ),
					'note'     => sanitize_text_field( (string) ( $input['note'] ?? '' ) ),
				)
			);
		}

		return self::execute_woo_get_order(
			array(
				'order_id' => absint( $input['order_id'] ?? 0 ),
			)
		);
	}
}
