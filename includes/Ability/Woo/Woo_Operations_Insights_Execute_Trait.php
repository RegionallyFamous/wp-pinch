<?php
/**
 * WooCommerce operations and insights execute handlers.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Fulfillment, refunds, coupon, customer, and analytics execution methods.
 */
trait Woo_Operations_Insights_Execute_Trait {

	/**
	 * Add order note.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_add_order_note( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$order = wc_get_order( absint( $input['order_id'] ?? 0 ) );
		if ( ! $order ) {
			return array( 'error' => __( 'Order not found.', 'wp-pinch' ) );
		}

		$note = sanitize_text_field( (string) ( $input['note'] ?? '' ) );
		if ( '' === $note ) {
			return array( 'error' => __( 'Note is required.', 'wp-pinch' ) );
		}

		$customer_note = ! empty( $input['customer_note'] );
		$note_id       = $order->add_order_note( $note, $customer_note );
		return array(
			'added'         => true,
			'order_id'      => $order->get_id(),
			'note_id'       => absint( $note_id ),
			'customer_note' => $customer_note,
		);
	}

	/**
	 * Mark order completed safely.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_mark_fulfilled( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$order = wc_get_order( absint( $input['order_id'] ?? 0 ) );
		if ( ! $order ) {
			return array( 'error' => __( 'Order not found.', 'wp-pinch' ) );
		}

		$current_status = $order->get_status();
		if ( in_array( $current_status, array( 'completed', 'cancelled', 'refunded' ), true ) ) {
			return array( 'error' => __( 'Order cannot be marked fulfilled from its current status.', 'wp-pinch' ) );
		}

		$note_parts = array();
		$note       = sanitize_text_field( (string) ( $input['note'] ?? '' ) );
		$tracking   = sanitize_text_field( (string) ( $input['tracking_number'] ?? '' ) );
		$carrier    = sanitize_text_field( (string) ( $input['tracking_carrier'] ?? '' ) );
		if ( '' !== $note ) {
			$note_parts[] = $note;
		}
		if ( '' !== $tracking ) {
			$note_parts[] = sprintf( 'Tracking: %s', $tracking );
		}
		if ( '' !== $carrier ) {
			$note_parts[] = sprintf( 'Carrier: %s', $carrier );
		}
		if ( ! empty( $note_parts ) ) {
			$order->add_order_note( implode( ' | ', $note_parts ) );
		}

		$order->update_status( 'completed' );
		return array(
			'fulfilled'  => true,
			'order_id'   => $order->get_id(),
			'old_status' => $current_status,
			'new_status' => 'completed',
		);
	}

	/**
	 * Cancel order with guards.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_cancel_order_safe( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		if ( empty( $input['confirm'] ) ) {
			return array( 'error' => __( 'confirm=true is required to cancel an order.', 'wp-pinch' ) );
		}

		$order = wc_get_order( absint( $input['order_id'] ?? 0 ) );
		if ( ! $order ) {
			return array( 'error' => __( 'Order not found.', 'wp-pinch' ) );
		}

		$current_status = $order->get_status();
		if ( ! in_array( $current_status, self::CANCELLABLE_ORDER_STATUSES, true ) ) {
			return array( 'error' => __( 'Order status is not cancellable by this ability.', 'wp-pinch' ) );
		}

		$reason = sanitize_text_field( (string) ( $input['reason'] ?? '' ) );
		$order->update_status( 'cancelled', $reason );
		return array(
			'cancelled'  => true,
			'order_id'   => $order->get_id(),
			'old_status' => $current_status,
			'new_status' => 'cancelled',
		);
	}

	/**
	 * Create order refund.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_create_refund( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$order = wc_get_order( absint( $input['order_id'] ?? 0 ) );
		if ( ! $order ) {
			return array( 'error' => __( 'Order not found.', 'wp-pinch' ) );
		}

		$idempotency_key = sanitize_text_field( (string) ( $input['idempotency_key'] ?? '' ) );
		if ( '' !== $idempotency_key ) {
			$key = 'wp_pinch_refund_' . md5( $order->get_id() . ':' . $idempotency_key );
			if ( get_transient( $key ) ) {
				return array( 'error' => __( 'Duplicate refund request blocked by idempotency key.', 'wp-pinch' ) );
			}
			set_transient( $key, 1, 5 * MINUTE_IN_SECONDS );
		}

		$remaining = (float) $order->get_total() - (float) $order->get_total_refunded();
		$amount    = isset( $input['amount'] ) ? (float) $input['amount'] : $remaining;
		if ( $amount <= 0 ) {
			return array( 'error' => __( 'Refund amount must be greater than 0.', 'wp-pinch' ) );
		}
		if ( $amount > $remaining ) {
			return array( 'error' => __( 'Refund amount exceeds refundable total.', 'wp-pinch' ) );
		}

		$refund = wc_create_refund(
			array(
				'order_id'      => $order->get_id(),
				'amount'        => wc_format_decimal( $amount ),
				'reason'        => sanitize_text_field( (string) ( $input['reason'] ?? '' ) ),
				'restock_items' => ! empty( $input['restock_items'] ),
			)
		);
		if ( is_wp_error( $refund ) ) {
			return array( 'error' => $refund->get_error_message() );
		}
		if ( ! $refund instanceof \WC_Order_Refund ) {
			return array( 'error' => __( 'Could not create refund.', 'wp-pinch' ) );
		}

		return array(
			'refunded'  => true,
			'order_id'  => $order->get_id(),
			'refund_id' => $refund->get_id(),
			'amount'    => $refund->get_amount(),
		);
	}

	/**
	 * List refund-eligible orders.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_list_refund_eligible_orders( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$per_page = self::normalize_per_page( $input['per_page'] ?? 50, 100 );
		$page     = self::normalize_page( $input['page'] ?? 1 );

		$query = wc_get_orders(
			array(
				'type'     => 'shop_order',
				'status'   => array( 'processing', 'completed', 'on-hold' ),
				'limit'    => $per_page,
				'page'     => $page,
				'paginate' => true,
				'orderby'  => 'date',
				'order'    => 'DESC',
			)
		);

		$orders = array();
		if ( is_object( $query ) && isset( $query->orders ) && is_array( $query->orders ) ) {
			foreach ( $query->orders as $order ) {
				if ( ! $order instanceof \WC_Order ) {
					continue;
				}
				$remaining = (float) $order->get_total() - (float) $order->get_total_refunded();
				if ( $remaining <= 0 ) {
					continue;
				}
				$orders[] = array(
					'order_id'             => $order->get_id(),
					'status'               => $order->get_status(),
					'total'                => (float) $order->get_total(),
					'total_refunded'       => (float) $order->get_total_refunded(),
					'refundable_remaining' => $remaining,
				);
			}
		}

		return array(
			'orders'   => $orders,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Create coupon.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_create_coupon( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$code   = wc_format_coupon_code( (string) ( $input['code'] ?? '' ) );
		$amount = sanitize_text_field( (string) ( $input['amount'] ?? '' ) );
		if ( '' === $code || '' === $amount ) {
			return array( 'error' => __( 'Coupon code and amount are required.', 'wp-pinch' ) );
		}
		if ( wc_get_coupon_id_by_code( $code ) ) {
			return array( 'error' => __( 'Coupon code already exists.', 'wp-pinch' ) );
		}

		$discount_type = sanitize_key( (string) ( $input['discount_type'] ?? 'fixed_cart' ) );
		if ( ! in_array( $discount_type, self::ALLOWED_COUPON_TYPES, true ) ) {
			return array( 'error' => __( 'Invalid coupon discount type.', 'wp-pinch' ) );
		}

		$coupon = new \WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_amount( $amount );
		$coupon->set_discount_type( $discount_type );
		$coupon->set_usage_limit( absint( $input['usage_limit'] ?? 0 ) );
		$coupon->set_individual_use( ! empty( $input['individual_use'] ) );
		$coupon->set_description( sanitize_text_field( (string) ( $input['description'] ?? '' ) ) );

		$expires_at = sanitize_text_field( (string) ( $input['expires_at'] ?? '' ) );
		if ( '' !== $expires_at ) {
			try {
				$coupon->set_date_expires( new \WC_DateTime( $expires_at ) );
			} catch ( \Exception $e ) {
				return array( 'error' => __( 'Invalid expires_at datetime.', 'wp-pinch' ) );
			}
		}

		$coupon_id = $coupon->save();
		return array(
			'created'   => true,
			'coupon_id' => $coupon_id,
			'code'      => $code,
		);
	}

	/**
	 * Update coupon fields.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_update_coupon( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$coupon = new \WC_Coupon( absint( $input['coupon_id'] ?? 0 ) );
		if ( ! $coupon->get_id() ) {
			return array( 'error' => __( 'Coupon not found.', 'wp-pinch' ) );
		}

		if ( array_key_exists( 'amount', $input ) ) {
			$coupon->set_amount( sanitize_text_field( (string) $input['amount'] ) );
		}
		if ( array_key_exists( 'discount_type', $input ) ) {
			$discount_type = sanitize_key( (string) $input['discount_type'] );
			if ( ! in_array( $discount_type, self::ALLOWED_COUPON_TYPES, true ) ) {
				return array( 'error' => __( 'Invalid coupon discount type.', 'wp-pinch' ) );
			}
			$coupon->set_discount_type( $discount_type );
		}
		if ( array_key_exists( 'usage_limit', $input ) ) {
			$coupon->set_usage_limit( absint( $input['usage_limit'] ) );
		}
		if ( array_key_exists( 'individual_use', $input ) ) {
			$coupon->set_individual_use( ! empty( $input['individual_use'] ) );
		}
		if ( array_key_exists( 'description', $input ) ) {
			$coupon->set_description( sanitize_text_field( (string) $input['description'] ) );
		}
		if ( array_key_exists( 'expires_at', $input ) ) {
			$expires_at = sanitize_text_field( (string) $input['expires_at'] );
			if ( '' === $expires_at ) {
				$coupon->set_date_expires( null );
			} else {
				try {
					$coupon->set_date_expires( new \WC_DateTime( $expires_at ) );
				} catch ( \Exception $e ) {
					return array( 'error' => __( 'Invalid expires_at datetime.', 'wp-pinch' ) );
				}
			}
		}

		$coupon->save();
		return array(
			'updated'   => true,
			'coupon_id' => $coupon->get_id(),
			'code'      => $coupon->get_code(),
		);
	}

	/**
	 * Expire coupon.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_expire_coupon( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$coupon = new \WC_Coupon( absint( $input['coupon_id'] ?? 0 ) );
		if ( ! $coupon->get_id() ) {
			return array( 'error' => __( 'Coupon not found.', 'wp-pinch' ) );
		}

		$coupon->set_date_expires( new \WC_DateTime( gmdate( 'Y-m-d H:i:s', time() - MINUTE_IN_SECONDS ) ) );
		$coupon->save();
		return array(
			'expired'   => true,
			'coupon_id' => $coupon->get_id(),
			'code'      => $coupon->get_code(),
		);
	}

	/**
	 * List customers.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_list_customers( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$per_page          = self::normalize_per_page( $input['per_page'] ?? 20, 100 );
		$page              = self::normalize_page( $input['page'] ?? 1 );
		$include_sensitive = ! empty( $input['include_sensitive_fields'] ) && current_user_can( 'manage_options' );
		$search            = sanitize_text_field( (string) ( $input['search'] ?? '' ) );

		$args = array(
			'role__in' => array( 'customer' ),
			'number'   => $per_page,
			'paged'    => $page,
			'orderby'  => 'ID',
			'order'    => 'DESC',
		);
		if ( '' !== $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		$query     = new \WP_User_Query( $args );
		$customers = array();
		foreach ( $query->get_results() as $user ) {
			if ( ! $user instanceof \WP_User ) {
				continue;
			}
			$customers[] = self::format_customer( $user, $include_sensitive );
		}

		return array(
			'customers' => $customers,
			'total'     => (int) $query->get_total(),
			'page'      => $page,
			'per_page'  => $per_page,
		);
	}

	/**
	 * Get customer details.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_get_customer( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$user = get_user_by( 'id', absint( $input['customer_id'] ?? 0 ) );
		if ( ! $user instanceof \WP_User ) {
			return array( 'error' => __( 'Customer not found.', 'wp-pinch' ) );
		}

		$include_sensitive = ! empty( $input['include_sensitive_fields'] ) && current_user_can( 'manage_options' );
		return array(
			'customer' => self::format_customer( $user, $include_sensitive ),
		);
	}

	/**
	 * Sales summary.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_sales_summary( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$window     = sanitize_key( (string) ( $input['window'] ?? 'week' ) );
		$max_orders = min( max( 1, absint( $input['max_orders'] ?? 500 ) ), 1000 );
		$date_query = self::resolve_window_date_query( $window, $input );

		$orders = wc_get_orders(
			array(
				'type'         => 'shop_order',
				'status'       => array( 'processing', 'completed', 'on-hold' ),
				'limit'        => $max_orders,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'date_created' => $date_query,
			)
		);

		$total_orders = 0;
		$gross_total  = 0.0;
		$refund_total = 0.0;
		foreach ( $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			++$total_orders;
			$gross_total  += (float) $order->get_total();
			$refund_total += (float) $order->get_total_refunded();
		}

		return array(
			'window'       => $window,
			'orders_count' => $total_orders,
			'gross_total'  => wc_format_decimal( $gross_total, 2 ),
			'refund_total' => wc_format_decimal( $refund_total, 2 ),
			'net_total'    => wc_format_decimal( $gross_total - $refund_total, 2 ),
		);
	}

	/**
	 * Top products summary.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_top_products( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$days       = min( max( 1, absint( $input['days'] ?? 30 ) ), 365 );
		$limit      = min( max( 1, absint( $input['limit'] ?? 10 ) ), 50 );
		$max_orders = min( max( 1, absint( $input['max_orders'] ?? 500 ) ), 1000 );
		$after      = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$orders = wc_get_orders(
			array(
				'type'         => 'shop_order',
				'status'       => array( 'processing', 'completed' ),
				'limit'        => $max_orders,
				'date_created' => '>=' . $after,
				'orderby'      => 'date',
				'order'        => 'DESC',
			)
		);

		$totals = array();
		foreach ( $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			foreach ( $order->get_items() as $item ) {
				$product_id = (int) $item->get_product_id();
				if ( $product_id <= 0 ) {
					continue;
				}
				if ( ! isset( $totals[ $product_id ] ) ) {
					$totals[ $product_id ] = array(
						'product_id' => $product_id,
						'name'       => $item->get_name(),
						'quantity'   => 0,
						'revenue'    => 0.0,
					);
				}
				$totals[ $product_id ]['quantity'] += (int) $item->get_quantity();
				$totals[ $product_id ]['revenue']  += (float) $item->get_total();
			}
		}

		usort(
			$totals,
			static function ( array $a, array $b ): int {
				return $b['quantity'] <=> $a['quantity'];
			}
		);

		return array(
			'days'         => $days,
			'top_products' => array_slice( $totals, 0, $limit ),
		);
	}

	/**
	 * Orders needing attention.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_orders_needing_attention( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$older_than_hours = min( max( 1, absint( $input['older_than_hours'] ?? 48 ) ), 24 * 30 );
		$per_page         = self::normalize_per_page( $input['per_page'] ?? 100, 200 );
		$threshold_ts     = time() - ( $older_than_hours * HOUR_IN_SECONDS );

		$orders = wc_get_orders(
			array(
				'type'    => 'shop_order',
				'status'  => array( 'pending', 'on-hold', 'failed' ),
				'limit'   => $per_page,
				'orderby' => 'date',
				'order'   => 'ASC',
			)
		);

		$needs_attention = array();
		foreach ( $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			$created = $order->get_date_created();
			if ( ! $created ) {
				continue;
			}
			$created_ts = $created->getTimestamp();
			if ( $created_ts > $threshold_ts ) {
				continue;
			}
			$needs_attention[] = array(
				'order_id'       => $order->get_id(),
				'status'         => $order->get_status(),
				'total'          => (float) $order->get_total(),
				'created_at'     => $created->date( 'Y-m-d H:i:s' ),
				'hours_old'      => (int) floor( ( time() - $created_ts ) / HOUR_IN_SECONDS ),
				'refund_pending' => (float) $order->get_total() > (float) $order->get_total_refunded(),
			);
		}

		return array(
			'orders'           => $needs_attention,
			'older_than_hours' => $older_than_hours,
			'count'            => count( $needs_attention ),
		);
	}
}
