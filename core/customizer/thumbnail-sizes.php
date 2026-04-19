<?php
/**
 * Thumbnail sizes Customizer feature.
 *
 * @package ST_WP_Core
 */

if ( ! function_exists( 'st_wp_core_scan_and_store_thumbnail_sizes' ) ) {
	/**
	 * Scan all registered thumbnail sizes and persist them for Customizer controls.
	 *
	 * @return void
	 */
	function st_wp_core_scan_and_store_thumbnail_sizes(): void {
		$existing_sizes = get_option( 'st_wp_core_all_thumbnail_sizes', array() );
		$new_sizes      = array();

		global $_wp_additional_image_sizes;

		foreach ( get_intermediate_image_sizes() as $size ) {
			if ( isset( $_wp_additional_image_sizes[ $size ] ) ) {
				$new_sizes[ $size ] = array(
					'width'  => $_wp_additional_image_sizes[ $size ]['width'],
					'height' => $_wp_additional_image_sizes[ $size ]['height'],
					'crop'   => $_wp_additional_image_sizes[ $size ]['crop'],
				);
			} else {
				$new_sizes[ $size ] = array(
					'width'  => get_option( "{$size}_size_w" ),
					'height' => get_option( "{$size}_size_h" ),
					'crop'   => get_option( "{$size}_crop" ),
				);
			}
		}

		update_option( 'st_wp_core_all_thumbnail_sizes', array_merge( $existing_sizes, $new_sizes ) );
	}
}

if ( ! function_exists( 'st_wp_core_customizer_register_thumbnail_sizes' ) ) {
	/**
	 * Register thumbnail size Customizer controls.
	 *
	 * @param WP_Customize_Manager $wp_customize The WP_Customize_Manager instance.
	 *
	 * @return void
	 */
	function st_wp_core_customizer_register_thumbnail_sizes( WP_Customize_Manager $wp_customize ): void {
		st_wp_core_scan_and_store_thumbnail_sizes();

		$thumbnail_sizes = get_option( 'st_wp_core_all_thumbnail_sizes', array() );

		foreach ( $thumbnail_sizes as $size => $size_data ) {
			$setting_id = 'st_wp_core_disable_thumbnail_' . $size;

			$wp_customize->add_setting(
				$setting_id,
				array(
					'default'   => false,
					'transport' => 'refresh',
				)
			);

			$wp_customize->add_control(
				$setting_id . '_control',
				array(
					// translators: %1$s: Thumbnail size name, %2$d: Width in pixels, %3$d: Height in pixels.
					'label'    => sprintf( __( 'Disable %1$s (%2$d×%3$d)', 'st-wp-starter' ), $size, $size_data['width'], $size_data['height'] ),
					'section'  => 'st_wp_core_thumbnails',
					'settings' => $setting_id,
					'type'     => 'checkbox',
					'priority' => 10,
				)
			);
		}
	}
}

if ( ! function_exists( 'st_wp_core_manage_thumbnail_sizes' ) ) {
	/**
	 * Manage thumbnail sizes: remove disabled sizes and register enabled ones.
	 *
	 * @return void
	 */
	function st_wp_core_manage_thumbnail_sizes(): void {
		$stores_persistent_sizes = get_option( 'st_wp_core_all_thumbnail_sizes', array() );

		foreach ( $stores_persistent_sizes as $size => $attributes ) {
			$size_setting_name = 'st_wp_core_disable_thumbnail_' . $size;
			$is_disabled       = get_theme_mod( $size_setting_name, false );

			if ( ! $is_disabled ) {
				if ( ! empty( $attributes['width'] ) || ! empty( $attributes['height'] ) ) {
					add_image_size(
						$size,
						(int) $attributes['width'],
						(int) $attributes['height'],
						(bool) $attributes['crop']
					);
				}
			} else {
				remove_image_size( $size );
			}
		}
	}
}

if ( ! function_exists( 'st_wp_core_prevent_disabled_thumbnail_creation' ) ) {
	/**
	 * Prevent creation of disabled thumbnail sizes at the image editor level.
	 *
	 * @param array $sizes Array of image sizes to create.
	 *
	 * @return array
	 */
	function st_wp_core_prevent_disabled_thumbnail_creation( array $sizes ): array {
		$stores_persistent_sizes = get_option( 'st_wp_core_all_thumbnail_sizes', array() );

		foreach ( $stores_persistent_sizes as $size => $attributes ) {
			$size_setting_name = 'st_wp_core_disable_thumbnail_' . $size;
			$is_disabled       = get_theme_mod( $size_setting_name, false );

			if ( $is_disabled && isset( $sizes[ $size ] ) ) {
				unset( $sizes[ $size ] );
			}
		}

		return $sizes;
	}
}

if ( ! function_exists( 'st_wp_core_disable_thumbnail_sizes' ) ) {
	/**
	 * Handle disabling sizes defined from Customizer.
	 *
	 * @param array $sizes Current sizes available.
	 *
	 * @return array
	 */
	function st_wp_core_disable_thumbnail_sizes( array $sizes ): array {
		$stores_persistent_sizes = get_option( 'st_wp_core_all_thumbnail_sizes', array() );

		foreach ( $stores_persistent_sizes as $size => $attributes ) {
			$size_setting_name = 'st_wp_core_disable_thumbnail_' . $size;
			$size_setting      = get_theme_mod( $size_setting_name, false );

			if ( $size_setting && in_array( $size, $sizes, true ) ) {
				$sizes = array_values( array_diff( $sizes, array( $size ) ) );
			}
		}

		return $sizes;
	}
}

if ( ! function_exists( 'st_wp_core_customizer_boot_thumbnail_sizes' ) ) {
	/**
	 * Boot thumbnail size runtime behavior.
	 *
	 * @return void
	 */
	function st_wp_core_customizer_boot_thumbnail_sizes(): void {
		add_action( 'after_setup_theme', 'st_wp_core_manage_thumbnail_sizes', 99 );

		add_filter( 'intermediate_image_sizes', 'st_wp_core_disable_thumbnail_sizes' );
		add_filter( 'intermediate_image_sizes_advanced', 'st_wp_core_disable_thumbnail_sizes' );
		add_filter( 'intermediate_image_sizes_advanced', 'st_wp_core_prevent_disabled_thumbnail_creation', 10 );

		if ( class_exists( 'WooCommerce' ) ) {
			add_filter( 'woocommerce_regenerate_images_intermediate_image_sizes', 'st_wp_core_disable_thumbnail_sizes' );
			add_filter( 'woocommerce_image_sizes_to_resize', 'st_wp_core_prevent_disabled_thumbnail_creation', 10 );
		}
	}
}
