<?php
/**
 * Initializes the Mend Settings page.
 *
 * @package WooVitalSource
 */

namespace WooVitalSource;

/**
 * Class Settings
 */
class Settings {

	/**
	 * SettingsRegistry instance
	 *
	 * @var SettingsRegistry
	 */
	public $settings_api;

	/**
	 * WP_ENVIRONMENT_TYPE
	 *
	 * @var string The WordPress environment.
	 */
	protected $wp_environment;

	/**
	 * Initialize the WPGraphQL Settings Pages
	 *
	 * @return void
	 */
	public function init() {
		$this->wp_environment = $this->get_wp_environment();
		$this->settings_api   = new Settings_Registry();
		add_action( 'admin_menu', [ $this, 'add_options_page' ] );
		add_action( 'init', [ $this, 'register_settings' ] );
		add_action( 'admin_init', [ $this, 'initialize_settings_page' ] );
		add_action( 'woo_vitalsource_settings_form_bottom', [ $this, 'render_action_buttons' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_action_scripts' ] );
	}

	/**
	 * Return the environment. Default to production.
	 *
	 * @return string The environment set using WP_ENVIRONMENT_TYPE.
	 */
	protected function get_wp_environment() {
		if ( function_exists( 'wp_get_environment_type' ) ) {
			return wp_get_environment_type();
		}

		return 'production';
	}

	/**
	 * Add the options page to the WP Admin
	 *
	 * @return void
	 */
	public function add_options_page() {
		add_options_page(
			__( 'WooVitalSource General Settings', 'woo-vitalsource' ),
			__( 'WooVitalSource Settings', 'woo-vitalsource' ),
			'manage_options',
			'woo-vitalsource-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Registers the initial settings for WPGraphQL
	 *
	 * @return void
	 */
	public function register_settings() {
		$this->settings_api->register_section(
			'woo_vitalsource_settings',
			[
				'title' => __( 'WooVitalSource Settings', 'woo-vitalsource' ),
			]
		);

		$this->settings_api->register_fields(
			'woo_vitalsource_settings',
			[
				[
					'name'    => 'vs_prod_api_key',
					'label'   => __( 'VitalSource Prod API Key', 'woo-vitalsource' ),
					'desc'    => __( 'API key for gaining access to live VitalSource Bookshelf', 'woo-vitalsource' ),
					'type'    => 'password',
					'default' => '',
				],
				[
					'name'    => 'vs_test_api_key',
					'label'   => __( 'VitalSource Test API Key', 'woo-vitalsource' ),
					'desc'    => __( 'API key for gaining access to test VitalSource Bookshelf', 'woo-vitalsource' ),
					'type'    => 'password',
					'default' => '',
				],
				[
					'name'    => 'sandbox_mode',
					'label'   => __( 'Sandbox Mode', 'woo-vitalsource' ),
					'desc'    => __( 'If checked, the Test API will be used for all actions to make to VitalSource.', 'woo-vitalsource' ),
					'type'    => 'checkbox',
					'default' => 'off',
				],
				[
					'name'    => 'only_vs_products',
					'label'   => __( 'Only VitalSource Products', 'woo-vitalsource' ),
					'desc'    => __( 'If checked, any product not found on the VitalSource Bookshelf will be put in the trash can.', 'woo-vitalsource' ),
					'type'    => 'checkbox',
					'default' => 'off',
				],
				[
					'name'        => 'default_chapter_price',
					'label'       => __( 'Default Price', 'woo-vitalsource' ),
					'desc'        => __( 'Default Price of Chapters', 'woo-vitalsource' ),
					'type'        => 'text',
					'default'     => '',
					'placeholder' => 'ex. 1.00',
				],
				[
					'name'        => 'platform_fee',
					'label'       => __( 'Platform Fee Percentage', 'woo-vitalsource' ),
					'desc'        => __( 'An extra fee applied at checkout.', 'woo-vitalsource' ),
					'type'        => 'text',
					'default'     => '',
					'placeholder' => 'ex. 0.15',
				],
			]
		);

		// Action to hook into to register settings.
		do_action( 'woo_vitalsource_register_settings', $this );

	}

	/**
	 * Initialize the settings admin page
	 *
	 * @return void
	 */
	public function initialize_settings_page() {
		$this->settings_api->admin_init();
	}

	/**
	 * Renders action buttons.
	 *
	 * @return void
	 */
	public function render_action_buttons() {
		$nonce = wp_create_nonce( 'vs-import-button' );
		?>
			<div style="padding-left: 10px">
				<button id="woo-vitalsource-import" class="button button-secondary" data-nonce="<?php echo esc_attr( $nonce ); ?>">Import Products</button>
			</div>
		<?php
	}

	/**
	 * Enqueue settings page JS scripts.
	 *
	 * @param string $hook  Page slug name.
	 * @return void
	 */
	public function enqueue_action_scripts( $hook ) {
		if ( 'settings_page_woo-vitalsource-settings' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'woo_vitalsource_action_scripts', plugin_file_url( 'js/settings.js' ), [], false, true );
		wp_localize_script( 'woo_vitalsource_action_scripts', 'wpAjax', [ 'url' => admin_url( 'admin-ajax.php' ) ] );
	}

	/**
	 * Render the settings page in the admin
	 *
	 * @return void
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<?php
			settings_errors();
			$this->settings_api->show_navigation();
			$this->settings_api->show_forms();
			?>
		</div>
		<?php
	}
}
