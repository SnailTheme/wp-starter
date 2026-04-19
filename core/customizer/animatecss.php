<?php
/**
 * AnimateCSS Customizer feature.
 *
 * @package ST_WP_Core
 */

if ( ! function_exists( 'st_wp_core_customizer_register_animatecss' ) ) {
	/**
	 * Register the AnimateCSS Customizer control.
	 *
	 * @param WP_Customize_Manager $wp_customize The WP_Customize_Manager instance.
	 *
	 * @return void
	 */
	function st_wp_core_customizer_register_animatecss( WP_Customize_Manager $wp_customize ): void {
		$wp_customize->add_setting(
			'st_wp_core_enable_animatecss',
			array(
				'default'   => false,
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			'st_wp_core_enable_animatecss_control',
			array(
				'label'    => __( 'Enable AnimateCSS', 'st-wp-starter' ),
				'section'  => 'st_wp_core_options',
				'settings' => 'st_wp_core_enable_animatecss',
				'type'     => 'checkbox',
				'priority' => 11,
			)
		);
	}
}

if ( ! function_exists( 'st_wp_core_enable_animatecss' ) ) {
	/**
	 * Enqueue Stylesheet / Scripts for AnimateCSS support.
	 *
	 * Uses theme customizer setting `st_wp_core_enable_animatecss`.
	 *
	 * @return void
	 */
	function st_wp_core_enable_animatecss(): void {
		wp_enqueue_style( 'plugins.animatecss.animate' );
		wp_enqueue_script( 'plugins.animatecss.animate' );
	}
}

if ( ! function_exists( 'st_wp_core_customizer_boot_animatecss' ) ) {
	/**
	 * Boot AnimateCSS runtime behavior.
	 *
	 * @return void
	 */
	function st_wp_core_customizer_boot_animatecss(): void {
		if ( ! get_theme_mod( 'st_wp_core_enable_animatecss', false ) ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', 'st_wp_core_enable_animatecss' );
	}
}
