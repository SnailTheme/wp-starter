<?php
/**
 * ST WP Starter Theme Customizer
 *
 * This file owns the Customizer UI only: panels, sections, controls, and preview
 * JS. Saved-setting runtime behavior lives in customizer-runtime.php so it can
 * keep working when projects remove this UI module through `st_wp_core_files`.
 *
 * @package ST_WP_Core
 */

/**
 * Load project-owned Customizer UI overrides before fallback functions.
 *
 * Use /inc/customizer.php for generated-theme UI changes. Use
 * /inc/customizer-functions.php for runtime callbacks and feature overrides.
 */
if ( file_exists( get_template_directory() . '/inc/customizer.php' ) ) {
	require_once get_template_directory() . '/inc/customizer.php';
}

if ( ! function_exists( 'st_wp_core_get_customizer_sections' ) ) {
	/**
	 * Return Customizer parent sections.
	 *
	 * Empty sections are skipped when no enabled feature belongs to them.
	 * Developers can rename, reprioritize, or remove sections with the
	 * `st_wp_core_customizer_sections` filter.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function st_wp_core_get_customizer_sections(): array {
		$sections = array(
			'options'     => array(
				'id'       => 'st_wp_core_options',
				'title'    => __( 'Options', 'st-wp-starter' ),
				'priority' => 31,
			),
			'security'    => array(
				'id'       => 'st_wp_core_security',
				'title'    => __( 'Security', 'st-wp-starter' ),
				'priority' => 32,
			),
			'thumbnails'  => array(
				'id'       => 'st_wp_core_thumbnails',
				'title'    => __( 'Thumbnails', 'st-wp-starter' ),
				'priority' => 33,
			),
			'custom_code' => array(
				'id'       => 'st_wp_core_custom_code',
				'title'    => __( 'Custom Code', 'st-wp-starter' ),
				'priority' => 33,
			),
		);

		return apply_filters( 'st_wp_core_customizer_sections', $sections );
	}
}

if ( ! function_exists( 'st_wp_core_customize_register' ) ) {
	/**
	 * Theme Settings Customizer.
	 *
	 * Builds the parent panel, then only adds section groups that still have at
	 * least one enabled feature. This keeps empty groups such as "Security" from
	 * showing when all features inside that group are disabled.
	 *
	 * @param WP_Customize_Manager $wp_customize The WP_Customize_Manager instance.
	 *
	 * @return void
	 */
	function st_wp_core_customize_register( WP_Customize_Manager $wp_customize ): void {
		$features         = function_exists( 'st_wp_core_get_loaded_customizer_features' ) ? st_wp_core_get_loaded_customizer_features() : array();
		$sections         = st_wp_core_get_customizer_sections();
		$section_features = array();

		// Group enabled feature callbacks under their configured UI section.
		foreach ( $features as $feature ) {
			if ( empty( $feature['section'] ) || empty( $feature['register'] ) || ! is_callable( $feature['register'] ) ) {
				continue;
			}

			if ( empty( $sections[ $feature['section'] ] ) ) {
				continue;
			}

			$section_features[ $feature['section'] ][] = $feature;
		}

		if ( empty( $section_features ) ) {
			return;
		}

		// Add main panel - "Theme Settings". Sections are added below as needed.
		$wp_customize->add_panel(
			'st_wp_core_theme_settings_panel',
			array(
				'title'    => __( 'Theme Settings', 'st-wp-starter' ),
				'priority' => 30,
			)
		);

		foreach ( $sections as $section_name => $section ) {
			// Do not render a parent section unless at least one child exists.
			if ( empty( $section_features[ $section_name ] ) ) {
				continue;
			}

			$wp_customize->add_section(
				$section['id'],
				array(
					'title'    => $section['title'],
					'priority' => $section['priority'],
					'panel'    => 'st_wp_core_theme_settings_panel',
				)
			);

			// Let each enabled feature register its own setting/control pair.
			foreach ( $section_features[ $section_name ] as $feature ) {
				call_user_func( $feature['register'], $wp_customize );
			}
		}
	}
}

if ( ! function_exists( 'st_wp_core_customize_preview_js' ) ) {
	/**
	 * Enqueue the core Customizer preview script.
	 *
	 * This script belongs to the reusable core layer because it is loaded by
	 * /core/customizer.php. Projects that need their own Customizer preview JS
	 * should enqueue a separate project-owned script from /inc/customizer.php or
	 * another /inc/ callback.
	 *
	 * Add Bind JS handlers to make Theme Customizer preview reload changes asynchronously.
	 * This runs only when the Customizer UI shell itself is enabled.
	 *
	 * @return void
	 */
	function st_wp_core_customize_preview_js(): void {
		$core_customizer_script_path = get_template_directory() . '/assets/js/core/customizer.min.js';

		if ( ! file_exists( $core_customizer_script_path ) ) {
			return;
		}

		wp_enqueue_script(
			'st-wp-core-customizer',
			get_template_directory_uri() . '/assets/js/core/customizer.min.js',
			array( 'customize-preview' ),
			filemtime( $core_customizer_script_path ),
			true
		);
	}
}

// This filter hides the Customizer UI shell only; feature runtime filters live
// in customizer-runtime.php and continue to apply saved settings.
if ( apply_filters( 'st_wp_core_enable_customizer', true ) ) {
	add_action( 'customize_register', 'st_wp_core_customize_register' );
	add_action( 'customize_preview_init', 'st_wp_core_customize_preview_js' );
}
