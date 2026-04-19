<?php
/**
 * Jetpack Compatibility File
 *
 * Integration bootstrapping only. This file handles plugin detection, loads
 * the theme-owned /inc/jetpack.php, applies the st_wp_core_enable_jetpack filter,
 * and then fires a core action that lets the theme layer register its hooks.
 * Theme design decisions (stylesheet handle, CSS selectors, Infinite Scroll
 * markup) live in /inc/jetpack.php so they can be edited per project without
 * touching the core package.
 *
 * @link https://jetpack.com/
 *
 * @package ST_WP_Core
 */

/**
 * Only proceed if Jetpack is active.
 */
if ( ! defined( 'JETPACK__VERSION' ) ) {
	return;
}

/**
 * Load theme-owned Jetpack callbacks.
 *
 * /inc/jetpack.php defines and registers all project-specific Jetpack
 * behaviors: Infinite Scroll setup, Responsive Videos, Content Options
 * (including the stylesheet handle), and the Infinite Scroll render callback.
 * Loading it here ensures those functions are available before any hook fires.
 */
if ( file_exists( get_template_directory() . '/inc/jetpack.php' ) ) {
	require get_template_directory() . '/inc/jetpack.php';
}

if ( ! apply_filters( 'st_wp_core_enable_jetpack', true ) ) {
	return;
}

/**
 * Fires after Jetpack is active and the core Jetpack module is enabled.
 *
 * Theme-owned Jetpack callbacks should register from /inc/jetpack.php on this
 * action. Keeping this as a generic core signal avoids hardcoding generated
 * theme function names in /core/ while still letting st_wp_core_enable_jetpack
 * disable the whole integration.
 *
 * @since 1.0.0
 */
do_action( 'st_wp_core_jetpack_loaded' );
