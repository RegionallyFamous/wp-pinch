<?php
/**
 * WooCommerce inventory execute handlers.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

use WP_Pinch\Audit_Table;

defined( 'ABSPATH' ) || exit;

/**
 * Inventory and variation execution methods.
 */
trait Woo_Inventory_Execute_Trait {

	/**
	 * Adjust stock by delta.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_adjust_stock( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$product_id = absint( $input['product_id'] ?? 0 );
		$delta      = (int) ( $input['quantity_delta'] ?? 0 );
		if ( 0 === $delta ) {
			return array( 'error' => __( 'quantity_delta cannot be 0.', 'wp-pinch' ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array( 'error' => __( 'Product not found.', 'wp-pinch' ) );
		}

		if ( ! empty( $input['manage_stock_on'] ) ) {
			$product->set_manage_stock( true );
			$product->save();
		}

		$op        = $delta > 0 ? 'increase' : 'decrease';
		$new_stock = wc_update_product_stock( $product, abs( $delta ), $op, true );
		if ( false === $new_stock ) {
			return array( 'error' => __( 'Could not adjust stock.', 'wp-pinch' ) );
		}

		Audit_Table::insert(
			'woo_stock_adjusted',
			'ability',
			sprintf( 'Stock adjusted for product #%d.', $product_id ),
			array(
				'product_id' => $product_id,
				'delta'      => $delta,
				'reason'     => sanitize_text_field( (string) ( $input['reason'] ?? '' ) ),
			)
		);

		return array(
			'updated'        => true,
			'product_id'     => $product_id,
			'quantity_delta' => $delta,
			'new_stock'      => (int) $new_stock,
		);
	}

	/**
	 * Bulk stock adjust.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_bulk_adjust_stock( array $input ): array {
		$adjustments = $input['adjustments'] ?? array();
		if ( ! is_array( $adjustments ) || empty( $adjustments ) ) {
			return array( 'error' => __( 'adjustments must be a non-empty array.', 'wp-pinch' ) );
		}

		$updated = array();
		$errors  = array();
		foreach ( $adjustments as $index => $adjustment ) {
			if ( ! is_array( $adjustment ) ) {
				$errors[] = array(
					'index' => $index,
					'error' => 'Invalid adjustment payload.',
				);
				continue;
			}
			$result = self::execute_woo_adjust_stock( $adjustment );
			if ( isset( $result['error'] ) ) {
				$errors[] = array(
					'index' => $index,
					'error' => $result['error'],
				);
				continue;
			}
			$updated[] = $result;
		}

		return array(
			'updated' => $updated,
			'errors'  => $errors,
			'total'   => count( $adjustments ),
		);
	}

	/**
	 * List low stock products.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_list_low_stock( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$threshold = absint( $input['threshold'] ?? 2 );
		$per_page  = self::normalize_per_page( $input['per_page'] ?? 50, 100 );
		$page      = self::normalize_page( $input['page'] ?? 1 );

		$query = wc_get_products(
			array(
				'limit'        => $per_page,
				'page'         => $page,
				'paginate'     => true,
				'status'       => array( 'publish', 'private' ),
				'stock_status' => array( 'instock', 'onbackorder' ),
				'orderby'      => 'ID',
				'order'        => 'DESC',
			)
		);

		$products = array();
		if ( is_object( $query ) && isset( $query->products ) && is_array( $query->products ) ) {
			foreach ( $query->products as $product ) {
				if ( ! $product instanceof \WC_Product ) {
					continue;
				}
				$stock = (int) $product->get_stock_quantity();
				if ( $product->managing_stock() && $stock <= $threshold ) {
					$products[] = self::format_product( $product );
				}
			}
		}

		return array(
			'products'  => $products,
			'threshold' => $threshold,
			'page'      => $page,
			'per_page'  => $per_page,
		);
	}

	/**
	 * List out-of-stock products.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_list_out_of_stock( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$per_page = self::normalize_per_page( $input['per_page'] ?? 50, 100 );
		$page     = self::normalize_page( $input['page'] ?? 1 );
		$query    = wc_get_products(
			array(
				'limit'        => $per_page,
				'page'         => $page,
				'paginate'     => true,
				'stock_status' => 'outofstock',
				'status'       => array( 'publish', 'private' ),
			)
		);

		$products = array();
		if ( is_object( $query ) && isset( $query->products ) && is_array( $query->products ) ) {
			foreach ( $query->products as $product ) {
				if ( $product instanceof \WC_Product ) {
					$products[] = self::format_product( $product );
				}
			}
		}

		return array(
			'products' => $products,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * List variations by variable product.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_list_variations( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$product = wc_get_product( absint( $input['product_id'] ?? 0 ) );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return array( 'error' => __( 'Variable product not found.', 'wp-pinch' ) );
		}

		$per_page   = self::normalize_per_page( $input['per_page'] ?? 100, 100 );
		$page       = self::normalize_page( $input['page'] ?? 1 );
		$offset     = ( $page - 1 ) * $per_page;
		$children   = array_slice( $product->get_children(), $offset, $per_page );
		$variations = array();
		foreach ( $children as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation ) {
				continue;
			}
			$variations[] = array(
				'id'             => $variation->get_id(),
				'sku'            => $variation->get_sku(),
				'regular_price'  => $variation->get_regular_price(),
				'sale_price'     => $variation->get_sale_price(),
				'stock_status'   => $variation->get_stock_status(),
				'stock_quantity' => $variation->get_stock_quantity(),
				'attributes'     => $variation->get_attributes(),
			);
		}

		return array(
			'product_id' => $product->get_id(),
			'variations' => $variations,
			'total'      => count( $product->get_children() ),
			'page'       => $page,
			'per_page'   => $per_page,
		);
	}

	/**
	 * Update variation fields.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_update_variation( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		$variation = wc_get_product( absint( $input['variation_id'] ?? 0 ) );
		if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
			return array( 'error' => __( 'Variation not found.', 'wp-pinch' ) );
		}

		if ( array_key_exists( 'regular_price', $input ) ) {
			$variation->set_regular_price( wc_format_decimal( $input['regular_price'] ) );
		}
		if ( array_key_exists( 'sale_price', $input ) ) {
			$variation->set_sale_price( wc_format_decimal( $input['sale_price'] ) );
		}
		if ( array_key_exists( 'stock_quantity', $input ) ) {
			$variation->set_stock_quantity( absint( $input['stock_quantity'] ) );
		}
		if ( array_key_exists( 'stock_status', $input ) ) {
			$variation->set_stock_status( sanitize_key( (string) $input['stock_status'] ) );
		}
		if ( array_key_exists( 'manage_stock', $input ) ) {
			$variation->set_manage_stock( ! empty( $input['manage_stock'] ) );
		}
		if ( array_key_exists( 'status', $input ) ) {
			$status = sanitize_key( (string) $input['status'] );
			if ( ! in_array( $status, self::ALLOWED_PRODUCT_STATUSES, true ) ) {
				return array( 'error' => __( 'Invalid variation status.', 'wp-pinch' ) );
			}
			$variation->set_status( $status );
		}

		$variation->save();
		return array(
			'updated'   => true,
			'variation' => array(
				'id'             => $variation->get_id(),
				'regular_price'  => $variation->get_regular_price(),
				'sale_price'     => $variation->get_sale_price(),
				'stock_status'   => $variation->get_stock_status(),
				'stock_quantity' => $variation->get_stock_quantity(),
			),
		);
	}

	/**
	 * List product categories/tags/attributes.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function execute_woo_list_product_taxonomies( array $input ): array {
		$check = self::ensure_woocommerce_active();
		if ( is_array( $check ) ) {
			return $check;
		}

		unset( $input );
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 100,
			)
		);
		$tags       = get_terms(
			array(
				'taxonomy'   => 'product_tag',
				'hide_empty' => false,
				'number'     => 100,
			)
		);
		$attributes = function_exists( 'wc_get_attribute_taxonomies' ) ? wc_get_attribute_taxonomies() : array();

		return array(
			'categories' => self::format_terms( $categories ),
			'tags'       => self::format_terms( $tags ),
			'attributes' => $attributes,
		);
	}
}
