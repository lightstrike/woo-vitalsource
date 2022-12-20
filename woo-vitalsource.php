<?php
/**
 * Plugin Name: WooVitalSource
 * Plugin URI: http://www.axistaylor.com
 * Description: Integrates VitalSource API w/ WooCommerce to provide seller's content in the VS store.
 * Version: 1.0.0
 * Author: Geoff Taylor
 * Author URI: http://www.axistaylor.com
 * Text Domain: woo-vital-source
 *
 * @package WooVitalSource
 */

namespace WooVitalSource;

/**
 * Set plugin constants.
 *
 * @return void
 */
function constants() {
	if ( ! defined( 'WOO_VITALSOURCE_VERSION' ) ) {
		define( 'WOO_VITALSOURCE_VERSION', '1.0.0' );
	}
	if ( ! defined( 'WOO_VITALSOURCE_DIR' ) ) {
		define( 'WOO_VITALSOURCE_DIR', \plugin_dir_path( __FILE__ ) );
	}
}

/**
 * Returns path to plugin root directory.
 *
 * @return string
 */
function get_plugin_directory() {
	return trailingslashit( WOO_VITALSOURCE_DIR );
}

/**
 * Returns path to plugin "includes" directory.
 *
 * @return string
 */
function get_includes_directory() {
	return trailingslashit( WOO_VITALSOURCE_DIR ) . 'includes/';
}

/**
 * Returns path to plugin "assets" directory.
 *
 * @return string
 */
function get_assets_directory() {
	return trailingslashit( WOO_VITALSOURCE_DIR ) . 'assets/';
}

/**
 * Returns path to plugin "vendor" directory.
 *
 * @return string
 */
function get_vendor_directory() {
	return trailingslashit( WOO_VITALSOURCE_DIR ) . 'vendor/';
}

/**
 * Returns url to a plugin file.
 *
 * @param string $filepath  Relative path to plugin file.
 *
 * @return string
 */
function plugin_file_url( $filepath ) {
	return plugins_url( $filepath, __FILE__ );
}

/**
 * Initializes plugin.
 */
function init() {
	constants();

	// Include plugin class and initialize plugin.
	require_once get_includes_directory() . 'class-woo-vitalsource.php';
	new Woo_VitalSource();

	// Load access functions.
	require_once get_plugin_directory() . 'access-functions.php';
}
init();

/**
 * Plugin activation callback
 *
 * @return void
 */
function on_activate() {
	do_action( 'woo_vitalsource_activated' );
}
register_activation_hook( __FILE__, '\WooVitalSource\on_activate' );
