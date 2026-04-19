<?php
/**
 * HTML header code Customizer feature.
 *
 * @package ST_WP_Core
 */

if ( ! function_exists( 'st_wp_core_customizer_register_html_header_code' ) ) {
	/**
	 * Register HTML Header Code Customizer control.
	 *
	 * @param WP_Customize_Manager $wp_customize The WP_Customize_Manager instance.
	 *
	 * @return void
	 */
	function st_wp_core_customizer_register_html_header_code( WP_Customize_Manager $wp_customize ): void {
		$wp_customize->add_setting(
			'st_wp_core_html_header_code',
			array(
				'default'           => '',
				'sanitize_callback' => 'st_wp_core_wp_kses_header_code',
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Code_Editor_Control(
				$wp_customize,
				'st_wp_core_html_header_code_control',
				array(
					'label'     => __( 'HTML Header Code', 'st-wp-starter' ),
					'section'   => 'st_wp_core_custom_code',
					'settings'  => 'st_wp_core_html_header_code',
					'code_type' => 'text/html',
				)
			)
		);
	}
}

if ( ! function_exists( 'st_wp_core_add_html_header_code' ) ) {
	/**
	 * Output custom HTML header code inside <head>.
	 *
	 * @return void
	 */
	function st_wp_core_add_html_header_code(): void {
		$header_code = trim( get_theme_mod( 'st_wp_core_html_header_code', '' ) );

		if ( '' === $header_code ) {
			return;
		}

		echo "\n" . $header_code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized on input via st_wp_core_wp_kses_header_code().
	}
}

if ( ! function_exists( 'st_wp_core_customizer_boot_html_header_code' ) ) {
	/**
	 * Boot HTML Header Code runtime behavior.
	 *
	 * @return void
	 */
	function st_wp_core_customizer_boot_html_header_code(): void {
		if ( ! get_theme_mod( 'st_wp_core_html_header_code', '' ) ) {
			return;
		}

		add_action( 'wp_head', 'st_wp_core_add_html_header_code' );
	}
}
