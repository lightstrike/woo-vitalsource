<?php
/**
 * Plugin global functions.
 *
 * @package WooVitalSource
 */

/**
 * Get an option value from Mend settings
 *
 * @param string $option_name  The key of the option to return.
 * @param mixed  $default      The default value the setting should return if no value is set.
 *
 * @return mixed|string|int|boolean
 */
function woo_vitalsource_get_setting( string $option_name, $default = '' ) {

	$section_fields = get_option( 'woo_vitalsource_settings' );

	/**
	 * Filter the section fields
	 *
	 * @param array  $section_fields The values of the fields stored for the section
	 * @param string $section_name   The name of the section
	 * @param mixed  $default        The default value for the option being retrieved
	 */
	$section_fields = apply_filters( 'woo_vitalsource_get_setting_section_fields', $section_fields, 'woo_vitalsource_settings', $default );

	/**
	 * Get the value from the stored data, or return the default
	 */
	$value = isset( $section_fields[ $option_name ] ) ? $section_fields[ $option_name ] : $default;

	/**
	 * Filter the value before returning it
	 *
	 * @param mixed  $value          The value of the field
	 * @param mixed  $default        The default value if there is no value set
	 * @param string $option_name    The name of the option
	 * @param array  $section_fields The setting values within the section
	 * @param string $section_name   The name of the section the setting belongs to
	 */
	return apply_filters( 'woo_vitalsource_get_setting_section_field_value', $value, $default, $option_name, $section_fields, 'woo_vitalsource_settings' );
}

/**
 * Uploads an image by URL to WordPress
 *
 * @param string $image_url  URL to image.
 * @param string $slug       slug for image.
 *
 * @return int
 */
function woo_vitalsource_upload_image( $image_url, $slug ) {
	//phpcs:disable WordPress.WP.AlternativeFunctions
	include_once ABSPATH . 'wp-admin/includes/image.php';
	$url        = $image_url;
	$parts      = explode( '/', getimagesize( $url )['mime'] );
	$image_type = end( $parts );
	$uniq_name  = $slug . gmdate( 'dmY' );
	$filename   = $uniq_name . '.' . $image_type;

	$upload_dir  = wp_upload_dir();
	$upload_file = $upload_dir['path'] . '/' . $filename;
	$contents    = file_get_contents( $url );
	$savefile    = fopen( $upload_file, 'w' );
	fwrite( $savefile, $contents );
	fclose( $savefile );

	$wp_filetype = wp_check_filetype( basename( $filename ), null );
	$attachment  = [
		'post_mime_type' => $wp_filetype['type'],
		'post_title'     => $filename,
		'post_content'   => '',
		'post_status'    => 'inherit',
	];

	$attachment_id   = wp_insert_attachment( $attachment, $upload_file );
	$imagenew        = get_post( $attachment_id );
	$fullsizepath    = get_attached_file( $imagenew->ID );
	$attachment_data = wp_generate_attachment_metadata( $attachment_id, $fullsizepath );
	wp_update_attachment_metadata( $attachment_id, $attachment_data );

	//phpcs:enable WordPress.WP.AlternativeFunctions
	return $attachment_id;

}
