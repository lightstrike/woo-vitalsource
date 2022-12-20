<?php
/**
 * Integrates VitalSource actions in WooCommerce UI + functionality.
 *
 * @package WooVitalSource
 */

namespace WooVitalSource;

/**
 * Class WooCommerce_Hooks
 */
class WooCommerce_Hooks {

	/**
	 * Store URL to VS Bookshelf content if the right criteria met.
	 *
	 * @var string
	 */
	private static $vs_content_link;

	/**
	 * VitalSource constructor.
	 */
	public function __construct() {
		add_action(
			'woocommerce_single_product_summary',
			[ $this, 'replace_single_add_to_cart_button' ],
			1
		);
		add_action(
			'woocommerce_payment_successful_result',
			[ $this, 'handle_successful_purchase' ],
			10,
			2
		);
	}

	/**
	 * Prints "Register to Purchase" button
	 *
	 * @return void
	 */
	public function vs_register_to_purchase_button() {
		$button_text = __( 'Register to Purchase', 'woo-vitalsource' );
		$button_link = esc_attr( wp_registration_url() );
		$classname   = esc_attr( 'single_add_to_cart_button button alt' . ( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ) );

		// Display button.
		echo '<div class="cart"><a class="' . $classname . '" href="' . $button_link . '">' . $button_text . '</a></div>';
	}

	/**
	 * Prints "View Content" button
	 *
	 * @return void
	 */
	public function vs_view_content_button() {
		$button_text = __( 'Read Chapter', 'woo-vitalsource' );
		$button_link = esc_attr( self::$vs_content_link );
		$classname   = esc_attr( 'single_add_to_cart_button button alt' . ( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ) );

		// Display button.
		echo '<div class="cart"><a class="' . $classname . '" href="' . $button_link . '">' . $button_text . '</a></div>';
	}

	/**
	 * Replaces the "add to cart" button on VitalSource product pages based upon
	 * user access.
	 *
	 * @return void
	 */
	public function replace_single_add_to_cart_button() {
		global $product;

		// Bail if not VS product.
		$vbid = $product->get_meta( 'vbid', true );
		if ( ! $vbid ) {
			return;
		}

		$vs_instance    = VitalSource::get_instance();
		$guest_checkout = 'yes' === get_option( 'woocommerce_enable_guest_checkout' );

		if ( ! is_user_logged_in() ) {
			remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
			add_action(
				'woocommerce_single_product_summary',
				[ $this, 'vs_register_to_purchase_button' ],
				30
			);
		}

		$access_token = $vs_instance->vs_check_credentials();
		if ( false !== $access_token ) {
			$license = $vs_instance->vs_check_content_license( $access_token, $product->get_sku() );
			if ( false !== $license ) {
				self::$vs_content_link = $vs_instance->vs_redirects( $access_token, $product->get_meta( 'vbid', true ) );
				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
				add_action(
					'woocommerce_single_product_summary',
					[ $this, 'vs_view_content_button' ],
					30
				);
			}
		}

	}

	/**
	 * Checks if order for newly purchased VitalSource content
	 * and provided the customer with access credentials to the
	 * content.
	 *
	 * @param int $order_id  Order ID.
	 * @return void
	 */
	private function vs_give_user_access_to_any_purchased_content( $order_id ) {
		$order = wc_get_order( $order_id );
		$items = $order->get_items();

		$vs_instance  = VitalSource::get_instance();
		$access_token = $vs_instance->vs_check_credentials();
		if ( false === $access_token ) {
			$access_token = $vs_instance->vs_create_user_credentials();
		}

		foreach ( $items as $item ) {
			$product = $item->get_product();
			$vbid    = $product->get_meta( 'vbid', true );
			$sku     = $product->get_sku();
			if ( ! $vbid || ! $sku ) {
				continue;
			}

			$code    = $vs_instance->vs_create_code( $access_token, $sku );
			$success = $vs_instance->vs_redeem_code( $access_token, $code );
			$item->read_meta_data();
			if ( false !== $success ) {
				$now = time();
				$item->update_meta_data( 'purchased_timestamp', $now, true );
			} else {
				$item->update_meta_data( 'purchased_failed', 'TRUE', true );
			}

			$item->save();
		}
	}

	/**
	 * Callback for executed after a successful purchase.
	 *
	 * @param mixed $result
	 * @param int $order_id
	 * @return mixed
	 */
	public function handle_successful_purchase( $result, $order_id ) {
		$this->vs_give_user_access_to_any_purchased_content( $order_id );

		return $result;
	}
}
