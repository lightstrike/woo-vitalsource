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
		add_action( 'woocommerce_cart_calculate_fees', [ $this, 'woocommerce_cart_calculate_fees' ], 30, 6 );
		add_action( 'woocommerce_thankyou', [ $this, 'redirect_to_chapter' ] );
	}

	/**
	 * Prints "Register to Purchase" button
	 *
	 * @return void
	 */
	public function vs_register_to_purchase_button() {
		$button_text = __( 'Register to Purchase', 'woo-vitalsource' );
		$button_link = home_url( '/#register' );
		$classname   = 'single_add_to_cart_button button alt' . ( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' );

		// Display button.
		echo '<div class="cart"><a class="' . esc_attr( $classname ) . '" href="' . esc_attr( $button_link ) . '">' . esc_attr( $button_text ) . '</a></div>';
	}

	/**
	 * Prints "View Content" button
	 *
	 * @return void
	 */
	public function vs_view_content_button() {
		$button_text = __( 'Read Chapter', 'woo-vitalsource' );
		$button_link = self::$vs_content_link;
		$classname   = 'single_add_to_cart_button button alt' . ( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' );

		// Display button.
		echo '<div class="cart"><a class="' . esc_attr( $classname ) . '" href="' . esc_attr( $button_link ) . '">' . esc_attr( $button_text ) . '</a></div>';
	}

	/**
	 * Return true if customer purchased product recently.
	 *
	 * @param array   $product_ids   Product IDs.
	 * @param integer $customer_ids  Customer ID.
	 * @return bool
	 */
	private function bought_item_recently( $product_ids, $customer_id ) {
		$bought = false;

		// Set HERE ine the array your specific target product IDs.

		// Get all customer orders.
		$customer_orders = get_posts(
			[
				'numberposts' => 1,
				'meta_key'    => '_customer_user', // WPCS: slow query ok.
				'meta_value'  => $customer_id, // WPCS: slow query ok.
				'post_type'   => 'shop_order', // WC orders post type.
				'post_status' => 'wc-completed', // Only orders with status "completed".
				'date'        => [
					[
						'after'     => 'January 5th, 2023',
						'inclusive' => true,
					],
				],
			]
		);
		foreach ( $customer_orders as $customer_order ) {
			// Updated compatibility with WooCommerce 3+.
			$order    = wc_get_order( $customer_order );
			$order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;

			// Iterating through each current customer products bought in the order.
			foreach ( $order->get_items() as $item ) {
				// WC 3+ compatibility.
				if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
					$product_id = $item['product_id'];
				} else {
					$product_id = $item->get_product_id();
				}

				// Your condition related to your 2 specific products Ids.
				if ( in_array( $product_id, $product_ids, true ) ) {
					$bought = true;
				}
			}
		}
		return $bought;
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

		$user          = wp_get_current_user();
		$roles         = (array) $user->roles;
		$is_instructor = in_array( 'instructor', $roles, true );

		$access_token = $vs_instance->vs_check_credentials();
		if ( false !== $access_token ) {
			$license = $vs_instance->vs_check_content_license( $access_token, $product->get_sku() );
			if ( false !== $license ) {
				self::$vs_content_link = $vs_instance->vs_redirects( $access_token, $product->get_meta( 'vbid', true ) );
				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
				if ( ! $is_instructor && ! current_user_can( 'manage_options' ) ) {
					remove_action( 'sc_get_download_pdf', 'sc_show_download_link' );
				}
				add_action(
					'woocommerce_single_product_summary',
					[ $this, 'vs_view_content_button' ],
					30
				);
				return;
			}
		}

		$already_purchased_item = $this->bought_item_recently( [ $product->get_id() ], $user->ID );
		if ( $is_instructor || $already_purchased_item ) {
			if ( false === $access_token ) {
				$access_token = $vs_instance->vs_create_user_credentials();
			}

			$success = $vs_instance->vs_fulfillment_sampling( $access_token, $product->get_sku() );
			if ( false !== $success ) {
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
	 * @param mixed $result    Resulting order.
	 * @param int   $order_id  Order ID.
	 * @return mixed
	 */
	public function handle_successful_purchase( $result, $order_id ) {
		$this->vs_give_user_access_to_any_purchased_content( $order_id );

		return $result;
	}

	/**
	 * Applies platform fee to cart total.
	 *
	 * @return void
	 */
	public function woocommerce_cart_calculate_fees() {
		if ( \is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		$platform_fee = woo_vitalsource_get_setting( 'platform_fee' );
		if ( empty( $platform_fee ) ) {
			$platform_fee = 0.15;
		}

		$fee_amount = 0;
		foreach ( \WC()->cart->get_cart_contents() as $cart_item_key => $values ) {
			$fee_amount += floatval( $values['line_subtotal'] ) * $platform_fee;
		}

		if ( 0 < $fee_amount ) {
			\WC()->cart->add_fee( __( 'Platform fee: ', 'woo-vitalsource' ), $fee_amount );
		}
	}

	/**
	 * Redirects user back to recently purchased product page.
	 *
	 * @param int $order_id  Order ID.
	 * @return void
	 */
	public function redirect_to_chapter( $order_id ) {
		$order = \wc_get_order( $order_id );
		if ( $order->has_status( 'failed' ) ) {
			return;
		}

		$items = $order->get_items();
		if ( empty( $items ) ) {
			return;
		}

		$product = array_pop( $items )->get_product();
		wp_safe_redirect( $product->get_permalink() );

	}
}
