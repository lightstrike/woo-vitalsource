<?php
/**
 * Workhorse for all VitalSource actions.
 *
 * @package WooVitalSource
 */

namespace WooVitalSource;

use SimpleXMLElement;

/**
 * Class VitalSource
 */
class VitalSource {
	/**
	 * Sole instance of VitalSource.
	 *
	 * @var VitalSource
	 */
	private static $instance = null;

	/**
	 * VitalSource constructor.
	 */
	private function __construct() {
		add_action( 'wp_ajax_import_vs_content', [ $this, 'import_vs_content' ] );
		add_action( 'wp_ajax_nopriv_import_vs_content', [ $this, 'import_vs_content' ] );
	}

	/**
	 * Returns instance of VitalSource.
	 *
	 * @return VitalSource
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Returns the VS API Key.
	 *
	 * @return string
	 */
	private function get_api_key() {
		$sandbox_mode = woo_vitalsource_get_setting( 'sandbox_mode' );
		if ( 'on' === $sandbox_mode ) {
			return woo_vitalsource_get_setting( 'vs_test_api_key' );
		}

		return woo_vitalsource_get_setting( 'vs_prod_api_key' );
	}

	/**
	 * Import VS Product action callback.
	 * Imports VitalSource Products from API as WooCommerce products.
	 *
	 * @return void
	 */
	public function import_vs_content() {
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vs-import-button' ) ) {
			wp_send_json_error(
				[ 'message' => 'Not authorized to execute this action.' ],
				401
			);
		}

		$vs_products = $this->query_for_vs_products();

		$imported = 0;
		$updated  = 0;
		$trashed  = 0;
		$all_skus = [];
		$saved    = [];
		foreach ( $vs_products as $vs_product ) {
			$variant_index = array_search( 'Single', array_column( $vs_product['variants'], 'type' ), true );
			$title         = $vs_product['title'];
			$vbid          = $vs_product['vbid'];
			$slug          = strtolower( trim( preg_replace( '/[^A-Za-z0-9-]+/', '-', $title ) ) );
			$product       = null;
			$existing_id   = 0;
			$price         = 0;
			$sku           = '';
			if ( false !== $variant_index ) {
				$variant     = $vs_product['variants'][ $variant_index ];
				$sku         = $variant['sku'];
				$all_skus[]  = $sku;
				$existing_id = wc_get_product_id_by_sku( $sku );

				foreach ( $variant['prices'] as $price_object ) {
					if ( 'USD' === $price_object['currency'] && 'digital-list-price' === $price_object['type'] ) {
						$price = $price_object['value'];
						break;
					}
				}
			}

			if ( $existing_id ) {
				$product = wc_get_product( $existing_id );
				$updated++;
			} else {
				$product = new \WC_Product_Simple();
				$imported++;
			}

			$product->set_name( $title ); // product title.
			$product->set_slug( $slug );
			$product->set_sold_individually( true );
			$product->set_regular_price( $price ); // in current shop currency.
			$product->update_meta_data( 'vbid', $vbid );

			if ( ! empty( $sku ) ) {
				$product->set_sku( $sku );
			}

			if ( ! empty( $vs_product['resource_links']['cover_image'] ) ) {
				$image_url     = $vs_product['resource_links']['cover_image'];
				$attachment_id = woo_vitalsource_upload_image( $image_url );
				$product->set_image_id( $attachment_id );
			}

			$saved[] = $product->save();
		}

		$only_vs_products = woo_vitalsource_get_setting( 'only_vs_products' );
		if ( 'on' === $only_vs_products ) {
			$query_args    = [
				'limit' => -1,
				'exclude' => $saved,
			];
			$product_query = new \WC_Product_Query( $query_args );
			$all_products  = $product_query->get_products();
			foreach ( $all_products as $store_product ) {
				if ( ! in_array( $store_product->get_sku(), $all_skus, true ) ) {
					wp_trash_post( $store_product->get_id() );
					$trashed++;
				}
			}
		}

		wp_send_json( compact( 'imported', 'updated', 'trashed' ) );
	}

	/**
	 * Confirms if the current users has credentials to access the VS Bookshelf.
	 *
	 * @return bool
	 */
	public function vs_check_credentials() {
		// Bail if user not authenticated.
		if ( ! is_user_logged_in() ) {
			return false;
		}
		// Bail if no API key.
		$api_key = $this->get_api_key();
		if ( ! $api_key ) {
			return false;
		}

		$reference = 'CR_USER_' . get_current_user_id();
		$response  = wp_remote_post(
			'https://api.vitalsource.com/v3/credentials.xml',
			[
				'headers' => [
					'X-VitalSource-API-Key' => $api_key,
					'Content-Type'          => 'application/xml',
				],
				'body'    => trim(
					"<?xml version=\"1.0\" encoding=\"UTF-8\"?><credentials><credential reference=\"{$reference}\" /></credentials>"
				),
			]
		);

		// Check response for access token and returned it if retrieved.
		$credentials = new SimpleXMLElement( wp_remote_retrieve_body( $response ) );
		if ( ! empty( $credentials[0]->credential['access-token'] ) ) {
			return $credentials[0]->credential['access-token'];
		}

		// Return false if no access token found.
		return false;
	}

	/**
	 * Creates user's VS access token.
	 *
	 * @return string|false
	 */
	public function vs_create_user_credentials() {
		// Bail if user not authenticated.
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Bail if no API key.
		$api_key = $this->get_api_key();
		if ( ! $api_key ) {
			return false;
		}

		// Get user info.
		$user       = wp_get_current_user();
		$reference  = 'CR_USER_' . $user->ID;
		$first_name = $user->first_name;
		$last_name  = $user->last_name;
		$email      = $user->email;

		$response = wp_remote_post(
			'https://api.vitalsource.com/v3/users.xml',
			[
				'headers' => [
					'X-VitalSource-API-Key' => $api_key,
					'Content-Type'          => 'application/xml',
				],
				'body'    => trim(
					"<user><reference>{$reference}</reference><first-name>{$first_name}</first-name><last-name>{$last_name}</last-name><email>{$email}</email></user>"
				),
			]
		);

		// Check response for access token and returned it if retrieved.
		$user = new SimpleXMLElement( wp_remote_retrieve_body( $response ) );
		if ( ! empty( $user->{'access-token'} ) ) {
			return $user->{'access-token'};
		}

		// Return false if no access token found.
		return false;
	}

	/**
	 * Checks if the provided user has access to the specified content.
	 *
	 * @param string $access_token  User Access token.
	 * @param string $sku           Content SKU.
	 * @return array|false
	 */
	public function vs_check_content_license( string $access_token, string $sku ) {
		// Bail if no API key.
		$api_key = $this->get_api_key();
		if ( ! $api_key ) {
			return false;
		}

		$response = wp_remote_get(
			"https://api.vitalsource.com/v3/licenses.xml?sku={$sku}&license_type=online",
			[
				'headers' => [
					'X-VitalSource-API-Key'      => $api_key,
					'X-VitalSource-Access-Token' => $access_token,
					'Content-Type'               => 'application/xml',
				],
			]
		);

		// Check response for content license and returned it if retrieved.
		$licenses = new SimpleXMLElement( wp_remote_retrieve_body( $response ) );
		if ( ! empty( $licenses[0]->license['expiration'] ) ) {
			return $licenses[0]->license;
		}

		// Return false if no license found.
		return false;
	}

	/**
	 * Creates license code to specified content for provided user.
	 *
	 * @param string $access_token  User Access Token.
	 * @param string $sku           Content SKU.
	 * @return string|false
	 */
	public function vs_create_code( string $access_token, string $sku ) {
		// Bail if no API key.
		$api_key = $this->get_api_key();
		if ( ! $api_key ) {
			return false;
		}

		$response = wp_remote_post(
			'https://api.vitalsource.com/v3/codes.xml',
			[
				'headers' => [
					'X-VitalSource-API-Key'      => $api_key,
					'X-VitalSource-Access-Token' => $access_token,
					'Content-Type'               => 'application/xml',
				],
				'body'    => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><codes sku=\"{$sku}\" license-type=\"perpetual\" online-license-type=\"perpetual\" num-codes=\"1\" />",
			]
		);

		// Check response for code and returned it if retrieved.
		$codes = new SimpleXMLElement( wp_remote_retrieve_body( $response ) );
		if ( ! empty( $codes[0]->code ) ) {
			return $codes[0]->code;
		}

		// Return false if no code found.
		return false;
	}

	/**
	 * Redeems license code for license to content.
	 *
	 * @param string $access_token  User access token.
	 * @param string $code          Content license access code.
	 * @return array|false
	 */
	public function vs_redeem_code( string $access_token, string $code ) {
		// Bail if no API key.
		$api_key = $this->get_api_key();
		if ( ! $api_key ) {
			return false;
		}

		$response = wp_remote_post(
			'https://api.vitalsource.com/v3/redemptions.xml',
			[
				'headers' => [
					'X-VitalSource-API-Key'      => $api_key,
					'X-VitalSource-Access-Token' => $access_token,
					'Content-Type'               => 'application/xml',
				],
				'body'    => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><redemption><code>{$code}</code></redemption>",
			]
		);

		// Check if response is valid.
		$library = new SimpleXMLElement( wp_remote_retrieve_body( $response ) );
		if ( ! empty( $library[0]->item ) ) {
			return $library[0]->item;
		}

		// Return false if not.
		return false;
	}

	/**
	 * Redirect URL to specified content for provided user.
	 *
	 * @param string $access_token  User access token.
	 * @param string $vbid          Content VBID.
	 * @return string|false
	 */
	public function vs_redirects( string $access_token, string $vbid ) {
		// Bail if no API key.
		$api_key = $this->get_api_key();
		if ( ! $api_key ) {
			return false;
		}

		$response = wp_remote_post(
			'https://api.vitalsource.com/v3/redirects.xml',
			[
				'headers' => [
					'X-VitalSource-API-Key'      => $api_key,
					'X-VitalSource-Access-Token' => $access_token,
					'Content-Type'               => 'application/xml',
				],
				'body'    => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><redirect><destination>https://online.vitalsource.com/books/{$vbid}</destination><brand>online.vitalsource.com</brand></redirect>",
			]
		);

		// Check response for URL to content and returned it if retrieved.
		$redirect = new SimpleXMLElement( wp_remote_retrieve_body( $response ) );
		if ( ! empty( $redirect['auto-signin'] ) && ! empty( $redirect['auto-signin'] ) ) {
			return $redirect['auto-signin']->__toString();
		}

		// Return false if no URL found.
		return false;
	}

	/**
	 * Queries VS Bookshelf for all products.
	 *
	 * @return array
	 */
	public function query_for_vs_products() {
		// Bail if no API key.
		$api_key = $this->get_api_key();
		if ( ! $api_key ) {
			return false;
		}

		$response = wp_remote_get(
			'https://api.vitalsource.com/v4/products',
			[
				'headers' => [
					'X-VitalSource-API-Key' => $api_key,
					'Content-Type'          => 'application/json',
				],
			]
		);
		if ( ! empty( $response ) && ! empty( $response['body'] ) ) {
			$body = json_decode( $response['body'], true );
			return $body['items'];
		}

		return [];
	}
}
