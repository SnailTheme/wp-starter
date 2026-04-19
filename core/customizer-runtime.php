<?php
/**
 * ST WP Starter Theme Customizer Runtime
 *
 * Loads enabled Customizer feature modules and boots their runtime behavior.
 * Runtime stays separate from `customizer.php` so saved theme mods continue to
 * affect production even when a project disables the Customizer UI file.
 *
 * @package ST_WP_Core
 */

/**
 * Runtime Customizer overrides must load before feature files.
 *
 * Add project-owned replacement callbacks or filters in /inc/customizer-functions.php.
 * Those definitions load before the core feature files register fallback
 * functions, which keeps the runtime layer pluggable without editing /core/.
 */
if ( file_exists( get_template_directory() . '/inc/customizer-functions.php' ) ) {
	require_once get_template_directory() . '/inc/customizer-functions.php';
}

if ( ! function_exists( 'st_wp_core_get_customizer_feature_registry' ) ) {
	/**
	 * Return the registered Customizer feature modules.
	 *
	 * Each feature owns its Customizer controls and its runtime behavior.
	 * Registry keys:
	 * - file: PHP file under /core/customizer/.
	 * - filter: feature-level enable/disable hook.
	 * - section: Customizer UI section group.
	 * - register: callback that adds controls to the Customizer UI.
	 * - runtime: callback that applies saved theme mods on the site.
	 *
	 * To disable one feature completely, return false on its `filter`. To add a
	 * new reusable core feature, add a registry entry and matching feature file.
	 *
	 * @return array<string, array<string, string>>
	 */
	function st_wp_core_get_customizer_feature_registry(): array {
		$features = array(
			'disable_emojis'         => array(
				'file'     => 'disable-emojis.php',
				'filter'   => 'st_wp_core_enable_customizer_disable_emojis',
				'section'  => 'options',
				'register' => 'st_wp_core_customizer_register_disable_emojis',
				'runtime'  => 'st_wp_core_customizer_boot_disable_emojis',
			),
			'animatecss'             => array(
				'file'     => 'animatecss.php',
				'filter'   => 'st_wp_core_enable_customizer_animatecss',
				'section'  => 'options',
				'register' => 'st_wp_core_customizer_register_animatecss',
				'runtime'  => 'st_wp_core_customizer_boot_animatecss',
			),
			'disable_users_rest_api' => array(
				'file'     => 'disable-users-rest-api.php',
				'filter'   => 'st_wp_core_enable_customizer_disable_users_rest_api',
				'section'  => 'security',
				'register' => 'st_wp_core_customizer_register_disable_users_rest_api',
				'runtime'  => 'st_wp_core_customizer_boot_disable_users_rest_api',
			),
			'disable_wp_version'     => array(
				'file'     => 'disable-wp-version.php',
				'filter'   => 'st_wp_core_enable_customizer_disable_wp_version',
				'section'  => 'security',
				'register' => 'st_wp_core_customizer_register_disable_wp_version',
				'runtime'  => 'st_wp_core_customizer_boot_disable_wp_version',
			),
			'thumbnail_sizes'        => array(
				'file'     => 'thumbnail-sizes.php',
				'filter'   => 'st_wp_core_enable_customizer_thumbnail_sizes',
				'section'  => 'thumbnails',
				'register' => 'st_wp_core_customizer_register_thumbnail_sizes',
				'runtime'  => 'st_wp_core_customizer_boot_thumbnail_sizes',
			),
			'html_header_code'       => array(
				'file'     => 'html-header-code.php',
				'filter'   => 'st_wp_core_enable_customizer_html_header_code',
				'section'  => 'custom_code',
				'register' => 'st_wp_core_customizer_register_html_header_code',
				'runtime'  => 'st_wp_core_customizer_boot_html_header_code',
			),
		);

		return apply_filters( 'st_wp_core_customizer_features', $features );
	}
}

if ( ! function_exists( 'st_wp_core_load_customizer_features' ) ) {
	/**
	 * Load enabled Customizer feature files.
	 *
	 * Feature files are restricted to /core/customizer/ so a filtered registry
	 * cannot require arbitrary files. Disabled features are skipped before their
	 * PHP file loads, which removes both UI and runtime callbacks.
	 *
	 * @return array<string, array<string, string>>
	 */
	function st_wp_core_load_customizer_features(): array {
		$features        = st_wp_core_get_customizer_feature_registry();
		$feature_path    = realpath( __DIR__ . '/customizer' );
		$loaded_features = array();

		foreach ( $features as $feature_name => $feature ) {
			// Invalid registry rows are ignored so one bad entry does not break boot.
			if ( ! is_array( $feature ) || empty( $feature['file'] ) || empty( $feature['filter'] ) ) {
				continue;
			}

			// This is the single switch for both runtime and UI for a feature.
			if ( ! apply_filters( $feature['filter'], true ) ) {
				continue;
			}

			$file_path = realpath( __DIR__ . '/customizer/' . ltrim( $feature['file'], '/\\' ) );

			if ( false === $feature_path || false === $file_path || ! is_file( $file_path ) ) {
				continue;
			}

			if ( strpos( $file_path, $feature_path . DIRECTORY_SEPARATOR ) !== 0 ) {
				continue;
			}

			// Load the feature file before the UI asks for its register callback.
			require_once $file_path;

			$loaded_features[ $feature_name ] = $feature;
		}

		return $loaded_features;
	}
}

if ( ! function_exists( 'st_wp_core_get_loaded_customizer_features' ) ) {
	/**
	 * Return enabled and loaded Customizer feature modules.
	 *
	 * @return array<string, array<string, string>>
	 */
	function st_wp_core_get_loaded_customizer_features(): array {
		global $st_wp_core_customizer_features;

		return is_array( $st_wp_core_customizer_features ) ? $st_wp_core_customizer_features : array();
	}
}

if ( ! function_exists( 'st_wp_core_boot_customizer_feature_runtime' ) ) {
	/**
	 * Boot runtime behavior for enabled Customizer features.
	 *
	 * Runtime callbacks should read saved theme mods and attach normal WordPress
	 * hooks/filters. They should not create Customizer panels or controls.
	 *
	 * @param array<string, array<string, string>> $features Loaded Customizer features.
	 *
	 * @return void
	 */
	function st_wp_core_boot_customizer_feature_runtime( array $features ): void {
		foreach ( $features as $feature ) {
			if ( empty( $feature['runtime'] ) || ! is_callable( $feature['runtime'] ) ) {
				continue;
			}

			call_user_func( $feature['runtime'] );
		}
	}
}

// Load and boot the runtime immediately as part of the core bootstrap.
$st_wp_core_customizer_features = st_wp_core_load_customizer_features();

st_wp_core_boot_customizer_feature_runtime( $st_wp_core_customizer_features );
