<?php
/**
 * Disable WordPress version Customizer feature.
 *
 * @package ST_WP_Core
 */

if ( ! function_exists( 'st_wp_core_customizer_register_disable_wp_version' ) ) {
	/**
	 * Register the Disable WordPress Version Customizer control.
	 *
	 * @param WP_Customize_Manager $wp_customize The WP_Customize_Manager instance.
	 *
	 * @return void
	 */
	function st_wp_core_customizer_register_disable_wp_version( WP_Customize_Manager $wp_customize ): void {
		$wp_customize->add_setting(
			'st_wp_core_disable_wp_version',
			array(
				'default'   => false,
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			'st_wp_core_disable_wp_version_control',
			array(
				'label'    => __( 'Disable WordPress Version', 'st-wp-starter' ),
				'section'  => 'st_wp_core_security',
				'settings' => 'st_wp_core_disable_wp_version',
				'type'     => 'checkbox',
				'priority' => 11,
			)
		);
	}
}

if ( ! function_exists( 'st_wp_core_remove_wp_version_from_assets' ) ) {
	/**
	 * Remove WordPress version query string from scripts and styles.
	 *
	 * @param string $src Style or script source URL.
	 *
	 * @return string
	 */
	function st_wp_core_remove_wp_version_from_assets( string $src ): string {
		$wp_version = get_bloginfo( 'version' );

		if ( str_contains( $src, 'ver=' ) ) {
			$original_version = wp_parse_url( $src, PHP_URL_QUERY );
			parse_str( $original_version, $query_params );

			if ( isset( $query_params['ver'] ) && $query_params['ver'] === $wp_version ) {
				$src = add_query_arg( 'ver', st_wp_core_get_theme_version(), remove_query_arg( 'ver', $src ) );
			}
		}

		return $src;
	}
}

if ( ! function_exists( 'st_wp_core_disable_wp_version' ) ) {
	/**
	 * Remove WordPress version output.
	 *
	 * @return void
	 */
	function st_wp_core_disable_wp_version(): void {
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );
		add_filter( 'style_loader_src', 'st_wp_core_remove_wp_version_from_assets', 9999 );
		add_filter( 'script_loader_src', 'st_wp_core_remove_wp_version_from_assets', 9999 );
	}
}

if ( ! function_exists( 'st_wp_core_customizer_boot_disable_wp_version' ) ) {
	/**
	 * Boot Disable WordPress Version runtime behavior.
	 *
	 * @return void
	 */
	function st_wp_core_customizer_boot_disable_wp_version(): void {
		if ( ! get_theme_mod( 'st_wp_core_disable_wp_version', false ) ) {
			return;
		}

		add_action( 'init', 'st_wp_core_disable_wp_version' );
	}
}
