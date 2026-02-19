<?php
/**
 * WooCommerce ability registration composition trait.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Registers WooCommerce abilities by composing domain traits.
 */
trait Woo_Register_Trait {
	use Woo_Register_Products_Orders_Trait;
	use Woo_Register_Inventory_Operations_Trait;
	use Woo_Register_Commercial_Intelligence_Trait;
}
