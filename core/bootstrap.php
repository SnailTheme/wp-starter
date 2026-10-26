<?php
/**
 * ST WP Core Bootstrap
 *
 * This file loads all core functionality.
 * Core files provide generic, updatable functionality.
 * Override functions in /inc/ for project-specific customizations.
 *
 * Toolkit note:
 * /core/ is designed to be replaced by the ST toolkit updater. Keep
 * project-specific callbacks, enqueues, and filters in /inc/ so generated
 * themes can receive core updates without losing local work.
 *
 * @package ST_WP_Core
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define the reusable core package version. The updater checks this value.
if ( ! defined( 'ST_WP_CORE_VERSION' ) ) {
	define( 'ST_WP_CORE_VERSION', '1.1.0' );
}

// Absolute filesystem path to this /core/ directory.
if ( ! defined( 'ST_WP_CORE_PATH' ) ) {
	define( 'ST_WP_CORE_PATH', __DIR__ );
}

/**
 * Theme Naming Patterns
 *
 * Defines this theme's naming conventions.
 * Used by toolkit commands for proper pattern replacement during core updates.
 * Values here describe the generated theme layer; `core_prefix` describes the
 * names that must remain stable across all whitelabeled themes.
 *
 * @since 1.0.0
 */
if ( ! defined( 'ST_WP_CORE_THEME_PATTERNS' ) ) {
	define(
		'ST_WP_CORE_THEME_PATTERNS',
		array(
			'git_repo'         => 'snailtheme/wp-starter',
			'text_domain'      => 'st-wp-starter',
			'function_names'   => 'st_wp_starter_',
			'doc_block'        => 'ST_WP_Starter',
			'prefix_handlers'  => 'st-wp-starter-',
			'constants'        => 'ST_WP_STARTER_',
			'core_prefix'      => 'st_wp_core_',
			'block_namespace'  => 'stwp',
			'block_category'   => 'st-wp-starter',
			'theme_name'       => 'ST WP Starter',
			'theme_uri'        => 'https://github.com/snailtheme/wp-starter/',
			'github_theme_uri' => 'snailtheme/wp-starter',
			'primary_branch'   => 'main',
			'author_name'      => 'SnailTheme',
			'author_uri'       => 'https://www.snailtheme.com/',
		)
	);
}

/**
 * Load core files in order.
 *
 * Order matters:
 * - `scripts.php` loads /inc/scripts.php before defining fallback core hooks.
 * - `customizer-runtime.php` loads saved runtime behavior before the UI module.
 * - Integration files can return early when the matching plugin is inactive.
 *
 * Each core file may load its /inc/ counterpart before defining guarded
 * fallback functions. This keeps generated projects overridable while preserving
 * a replaceable core directory.
 */
$core_files = array(
	'core.php',                    // Theme setup & configuration.
	'scripts.php',                 // Script & style enqueuing.
	'template-functions.php',      // Template enhancements.
	'template-tags.php',           // Template helper functions.
	'components.php',              // Toolkit-selected component integrations.
	'customizer-runtime.php',      // Customizer runtime behavior.
	'customizer.php',              // Customizer panels & settings.
	'customizer-functions.php',    // Customizer override bridge.
	'woocommerce.php',             // WooCommerce integration.
	'jetpack.php',                 // Jetpack integration.
);

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	$core_files[] = 'debug.php'; // Debug utilities.
}

/**
 * Filters the ordered core file registry before loading.
 *
 * Return an empty array to disable all core modules, or remove individual file
 * names to disable specific modules.
 *
 * @param string[] $core_files Ordered list of core file names.
 *
 * @since 1.0.0
 */
$filtered_core_files = apply_filters( 'st_wp_core_files', $core_files );

if ( is_array( $filtered_core_files ) ) {
	$core_files = $filtered_core_files;
}

// Resolve the real core path once so filtered filenames cannot escape /core/.
$core_path         = realpath( ST_WP_CORE_PATH );
$loaded_core_files = array();

foreach ( $core_files as $file ) {
	// Ignore invalid registry entries rather than breaking theme boot.
	if ( ! is_string( $file ) || '' === trim( $file ) ) {
		continue;
	}

	$file = ltrim( $file, '/\\' );

	// Debug helpers must never load in production, even if a filter adds them.
	if ( 'debug.php' === $file && ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) ) {
		continue;
	}

	$filepath = realpath( ST_WP_CORE_PATH . '/' . $file );

	if ( false === $core_path || false === $filepath || ! is_file( $filepath ) ) {
		continue;
	}

	if ( strpos( $filepath, $core_path . DIRECTORY_SEPARATOR ) !== 0 ) {
		continue;
	}

	/**
	 * Filters whether a specific core file should load.
	 *
	 * @param bool   $should_load Whether the file should load.
	 * @param string $file        Core file name.
	 * @param string $filepath    Absolute path to the core file.
	 *
	 * @since 1.0.0
	 */
	$should_load = apply_filters( 'st_wp_core_should_load_file', true, $file, $filepath );

	if ( ! $should_load ) {
		continue;
	}

	// Load each core module once, then remember it for diagnostics/tooling.
	require_once $filepath;

	$loaded_core_files[] = $file;
}

if ( ! defined( 'ST_WP_CORE_LOADED_FILES' ) ) {
	define( 'ST_WP_CORE_LOADED_FILES', $loaded_core_files );
}

/**
 * Fires after core files are loaded
 *
 * Allows themes to hook after core initialization.
 *
 * @param string[] $loaded_core_files Loaded core file names.
 *
 * @since 1.0.0
 */
do_action( 'st_wp_core_loaded', $loaded_core_files );
