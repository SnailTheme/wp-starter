<?php
/**
 * ST WP Starter scripts functions
 *
 * Enqueue / Register scripts and styles
 * Core only owns reusable hooks and assets here. Theme/project assets are
 * enqueued from /inc/scripts.php, which loads before these fallback functions.
 *
 * @package ST_WP_Core
 */

/**
 * Load project-owned script hooks before guarded core fallback functions.
 *
 * This lets a generated theme enqueue `main.css`, `main.js`, analytics, or
 * other local assets without editing this replaceable core file.
 */
if ( file_exists( get_template_directory() . '/inc/scripts.php' ) ) {
	require get_template_directory() . '/inc/scripts.php';
}

if ( ! function_exists( 'st_wp_core_scripts' ) ) {
	/**
	 * Enqueue core front-end scripts and styles.
	 *
	 * The core front-end hook intentionally stays small. It keeps WordPress's
	 * `comment-reply` support and then fires `st_wp_core_enqueue_scripts`, which is
	 * where /inc/scripts.php attaches project-owned theme assets.
	 *
	 * @return void
	 */
	function st_wp_core_scripts(): void {
		// WordPress needs this script only on singular posts/pages with threaded comments.
		if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}

		// Public extension point for generated themes and projects.
		do_action( 'st_wp_core_enqueue_scripts' );
	}
}
add_action( 'wp_enqueue_scripts', 'st_wp_core_scripts' );


if ( ! function_exists( 'st_wp_core_admin_scripts' ) ) {
	/**
	 * Enqueue core admin assets when a core feature needs them.
	 *
	 * How to use the filters:
	 * - `st_wp_core_enable_admin_assets` disables optional core admin styling only.
	 * - `st_wp_core_enable_plugin_suggestions_notice` controls notice dismiss JS.
	 * - `st_wp_core_enable_nav_menu_fields` controls nav-menu media JS.
	 *
	 * The nav-menu image feature must keep working even when admin styling is
	 * disabled, so this function checks each feature separately.
	 *
	 * @param string $hook // caller hook string.
	 *
	 * @return void
	 */
	function st_wp_core_admin_scripts( string $hook ): void {
		$core_admin_style_path  = get_template_directory() . '/assets/css/core/admin.min.css';
		$core_admin_script_path = get_template_directory() . '/assets/js/core/admin.min.js';
		$enable_admin_assets    = apply_filters( 'st_wp_core_enable_admin_assets', true );
		$enable_plugin_notice   = apply_filters( 'st_wp_core_enable_plugin_suggestions_notice', true );
		$enable_nav_menu_fields = apply_filters( 'st_wp_core_enable_nav_menu_fields', true );
		$script_deps            = array( 'jquery' );
		$enqueue_core_script    = false;

		// Optional core admin CSS: notices, Customizer control polish, etc.
		if ( $enable_admin_assets && file_exists( $core_admin_style_path ) ) {
			wp_enqueue_style(
				'st-wp-core-admin-styles',
				get_template_directory_uri() . '/assets/css/core/admin.min.css',
				array(),
				filemtime( $core_admin_style_path ),
				false
			);
		}

		// Plugin suggestion notices need AJAX data to persist "Don't remind me".
		if ( $enable_plugin_notice ) {
			$enqueue_core_script = true;
		}

		// Nav menu image fields require wp.media on Appearance > Menus only.
		if ( 'nav-menus.php' === $hook && $enable_nav_menu_fields ) {
			wp_enqueue_media();
			$script_deps[]       = 'media-editor';
			$enqueue_core_script = true;
		}

		// Avoid loading an empty core admin script on screens that do not need it.
		if ( ! $enqueue_core_script || ! file_exists( $core_admin_script_path ) ) {
			return;
		}

		wp_enqueue_script(
			'st-wp-core-admin-scripts',
			get_template_directory_uri() . '/assets/js/core/admin.min.js',
			array_unique( $script_deps ),
			filemtime( $core_admin_script_path ),
			true
		);

		// Localize only when the plugin notice feature is enabled.
		if ( $enable_plugin_notice ) {
			wp_localize_script(
				'st-wp-core-admin-scripts',
				'st_wp_core_plugin_notice',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'st_wp_core_ignore_plugin_notice' ),
				)
			);
		}
	}
}
add_action( 'admin_enqueue_scripts', 'st_wp_core_admin_scripts' );

if ( ! function_exists( 'st_wp_core_enable_separate_core_block_assets' ) ) {
	/**
	 * Block Styles - Loading Enhancement
	 *  Only Load Styles for used blocks
	 *  since v5.8
	 *
	 * Ref_01: https://stackoverflow.com/a/76836510/22644768
	 * Ref_02: https://make.wordpress.org/core/2021/07/01/block-styles-loading-enhancements-in-wordpress-5-8/
	 *
	 * @return bool
	 */
	function st_wp_core_enable_separate_core_block_assets(): bool {
		return true;
	}
}
add_filter( 'should_load_separate_core_block_assets', 'st_wp_core_enable_separate_core_block_assets' );
