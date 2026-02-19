<?php
/**
 * WooCommerce ability shared helpers.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Shared helper methods used across Woo abilities.
 */
trait Woo_Helpers_Trait {

	/**
	 * Ensure WooCommerce is active.
	 *
	 * @return true|array<string, string>
	 */
	private static function ensure_woocommerce_active() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'error' => __( 'WooCommerce is not active.', 'wp-pinch' ) );
		}
		return true;
	}

	/**
	 * Normalize page size.
	 *
	 * @param mixed $value Raw input.
	 * @param int   $max Max cap.
	 * @return int
	 */
	private static function normalize_per_page( $value, int $max ): int {
		$per_page = absint( $value );
		if ( $per_page <= 0 ) {
			$per_page = 1;
		}
		return min( $per_page, $max );
	}

	/**
	 * Normalize page number.
	 *
	 * @param mixed $value Raw input.
	 * @return int
	 */
	private static function normalize_page( $value ): int {
		return max( 1, absint( $value ) );
	}

	/**
	 * Format product payload.
	 *
	 * @param \WC_Product $product Product instance.
	 * @return array<string, mixed>
	 */
	private static function format_product( \WC_Product $product ): array {
		return array(
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
			'manage_stock'  => $product->get_manage_stock(),
			'url'           => $product->get_permalink(),
		);
	}

	/**
	 * Format order payload with balanced redaction.
	 *
	 * @param \WC_Order $order Order.
	 * @param bool      $include_sensitive Include sensitive fields.
	 * @return array<string, mixed>
	 */
	private static function format_order( \WC_Order $order, bool $include_sensitive ): array {
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$items[] = array(
				'name'       => $item->get_name(),
				'product_id' => $item->get_product_id(),
				'quantity'   => $item->get_quantity(),
				'total'      => $item->get_total(),
				'sku'        => $product ? $product->get_sku() : '',
			);
		}

		$notes = array_map(
			static function ( $note ): array {
				return array(
					'content' => $note->comment_content,
					'date'    => $note->comment_date,
				);
			},
			wc_get_order_notes(
				array(
					'order_id' => $order->get_id(),
					'limit'    => 10,
				)
			)
		);

		$data = array(
			'order_id'       => $order->get_id(),
			'status'         => $order->get_status(),
			'total'          => $order->get_total(),
			'currency'       => $order->get_currency(),
			'date_created'   => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : null,
			'shipping'       => $order->get_shipping_total(),
			'total_refunded' => $order->get_total_refunded(),
			'items'          => $items,
			'notes'          => $notes,
			'customer'       => array(
				'id'   => $order->get_customer_id(),
				'name' => $order->get_billing_first_name(),
			),
		);

		if ( $include_sensitive ) {
			$data['customer']['email']             = $order->get_billing_email();
			$data['customer']['billing_last_name'] = $order->get_billing_last_name();
			$data['payment_method']                = $order->get_payment_method();
		} else {
			$data['customer']['email_masked'] = self::mask_email( $order->get_billing_email() );
		}

		return $data;
	}

	/**
	 * Format customer payload.
	 *
	 * @param \WP_User $user User.
	 * @param bool     $include_sensitive Include sensitive fields.
	 * @return array<string, mixed>
	 */
	private static function format_customer( \WP_User $user, bool $include_sensitive ): array {
		$data = array(
			'id'                => $user->ID,
			'username'          => $user->user_login,
			'display_name'      => $user->display_name,
			'first_name'        => get_user_meta( $user->ID, 'first_name', true ),
			'last_name_initial' => substr( (string) get_user_meta( $user->ID, 'last_name', true ), 0, 1 ),
			'email_masked'      => self::mask_email( (string) $user->user_email ),
		);

		if ( $include_sensitive ) {
			$data['email']   = $user->user_email;
			$data['billing'] = array(
				'first_name' => get_user_meta( $user->ID, 'billing_first_name', true ),
				'last_name'  => get_user_meta( $user->ID, 'billing_last_name', true ),
				'phone'      => get_user_meta( $user->ID, 'billing_phone', true ),
				'city'       => get_user_meta( $user->ID, 'billing_city', true ),
				'country'    => get_user_meta( $user->ID, 'billing_country', true ),
			);
		}

		return $data;
	}

	/**
	 * Mask email for safer defaults.
	 *
	 * @param string $email Email.
	 * @return string
	 */
	private static function mask_email( string $email ): string {
		if ( '' === $email || ! str_contains( $email, '@' ) ) {
			return '';
		}
		$parts = explode( '@', $email );
		if ( 2 !== count( $parts ) ) {
			return '';
		}
		$local = $parts[0];
		$host  = $parts[1];
		if ( strlen( $local ) <= 2 ) {
			$local = str_repeat( '*', strlen( $local ) );
		} else {
			$local = substr( $local, 0, 1 ) . str_repeat( '*', strlen( $local ) - 2 ) . substr( $local, -1 );
		}
		return $local . '@' . $host;
	}

	/**
	 * Resolve date query for sales window.
	 *
	 * @param string               $window Window key.
	 * @param array<string, mixed> $input  Input.
	 * @return string
	 */
	private static function resolve_window_date_query( string $window, array $input ): string {
		$now = time();
		if ( 'day' === $window ) {
			return '>=' . gmdate( 'Y-m-d H:i:s', $now - DAY_IN_SECONDS );
		}
		if ( 'month' === $window ) {
			return '>=' . gmdate( 'Y-m-d H:i:s', $now - ( 30 * DAY_IN_SECONDS ) );
		}
		if ( 'custom' === $window ) {
			$start = sanitize_text_field( (string) ( $input['start_date'] ?? '' ) );
			$end   = sanitize_text_field( (string) ( $input['end_date'] ?? '' ) );
			if ( '' !== $start && '' !== $end ) {
				return $start . '...' . $end;
			}
			if ( '' !== $start ) {
				return '>=' . $start;
			}
			if ( '' !== $end ) {
				return '<=' . $end;
			}
		}
		return '>=' . gmdate( 'Y-m-d H:i:s', $now - ( 7 * DAY_IN_SECONDS ) );
	}

	/**
	 * Sanitize address payload.
	 *
	 * @param array<string, mixed> $address Address input.
	 * @return array<string, string>
	 */
	private static function sanitize_address( array $address ): array {
		$allowed = array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'email', 'phone' );
		$result  = array();
		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $address ) ) {
				continue;
			}
			$result[ $key ] = sanitize_text_field( (string) $address[ $key ] );
		}
		return $result;
	}

	/**
	 * Format terms.
	 *
	 * @param mixed $terms Terms object/array.
	 * @return array<int, array<string, mixed>>
	 */
	private static function format_terms( $terms ): array {
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}
		$items = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$items[] = array(
				'id'    => $term->term_id,
				'name'  => $term->name,
				'slug'  => $term->slug,
				'count' => $term->count,
			);
		}
		return $items;
	}
}
