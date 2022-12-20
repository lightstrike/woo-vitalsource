<?php
/**
 * Plugin entrypoint. Initialize plugin.
 *
 * @package WooVitalSource
 */

namespace WooVitalSource;

/**
 * Woo_VitalSource class.
 */
class Woo_VitalSource {
	/**
	 * Loads include files and adds hooks.
	 */
	public function __construct() {
		$this->includes();
		$this->init();

		add_action( 'after_setup_theme', [ $this, 'init_admin' ] );
	}

	/**
	 * Load include files
	 *
	 * @return void
	 */
	public function includes() {
		require_once get_includes_directory() . 'class-settings-registry.php';
		require_once get_includes_directory() . 'class-settings.php';
		require_once get_includes_directory() . 'class-vitalsource.php';
		require_once get_includes_directory() . 'class-woocommerce-hooks.php';
	}

	/**
	 * Initialize Woo_VitalSource core functionality.
	 *
	 * @return void
	 */
	public function init() {
		VitalSource::get_instance();
		new WooCommerce_Hooks();
	}

	/**
	 * Initialize admin page and settings.
	 *
	 * @return void
	 */
	public function init_admin() {
		$this->settings = new Settings();
		$this->settings->init();
	}
}
