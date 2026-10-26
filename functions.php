<?php
/**
 * ST WP Starter functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package ST_WP_Starter
 */

declare(strict_types=1);

/**
 * Load Composer autoloader if present
 */
if ( file_exists( get_template_directory() . '/vendor/autoload.php' ) ) {
	require_once get_template_directory() . '/vendor/autoload.php';
}

// Define theme version.
if ( ! defined( 'ST_WP_STARTER_VERSION' ) ) {
	define( 'ST_WP_STARTER_VERSION', '1.1.0' );
}

// Define theme path constant.
if ( ! defined( 'ST_WP_STARTER_THEME_PATH' ) ) {
	define( 'ST_WP_STARTER_THEME_PATH', get_template_directory() );
}

/**
 * Load early project bootstrap hooks.
 *
 * This file loads before /core/bootstrap.php, so it can filter core modules.
 */
if ( file_exists( ST_WP_STARTER_THEME_PATH . '/inc/bootstrap.php' ) ) {
	require_once ST_WP_STARTER_THEME_PATH . '/inc/bootstrap.php';
}

/**
 * Load Core Bootstrap
 *
 * Loads all core functionality from /core/ directory.
 * Core files can be overridden in /inc/ directory.
 *
 * Core files loaded via bootstrap:
 * - core.php (Theme setup & configuration)
 * - scripts.php (Script & style enqueuing)
 * - template-functions.php (Template enhancements)
 * - template-tags.php (Template helper functions)
 * - components.php (Toolkit-selected component integrations)
 * - customizer-runtime.php (Customizer runtime behavior)
 * - customizer.php (Customizer panels & settings)
 * - customizer-functions.php (Customizer override bridge)
 * - woocommerce.php (WooCommerce integration)
 * - jetpack.php (Jetpack integration)
 * - debug.php (Debug utilities, only when WP_DEBUG is true)
 */
require_once ST_WP_STARTER_THEME_PATH . '/core/bootstrap.php';

// Load theme Custom Post Type.
require_once ST_WP_STARTER_THEME_PATH . '/inc/cpt.php';

// Load theme Widgets.
require_once ST_WP_STARTER_THEME_PATH . '/inc/widgets.php';
