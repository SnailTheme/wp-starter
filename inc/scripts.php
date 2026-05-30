<?php
/**
 * Theme-specific scripts and styles.
 *
 * This file is part of the generated/whitelabeled theme layer. It owns visual
 * theme assets such as main.css, editor.css, and WooCommerce styling. Reusable
 * asset loader behavior remains in /core/scripts.php.
 *
 * @package ST_WP_Starter
 */

/**
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │                                                                         │
 * │  IMPORTANT: DO NOT MODIFY /core/scripts.php                             │
 * │  Add theme-specific enqueue logic here instead                          │
 * │                                                                         │
 * └─────────────────────────────────────────────────────────────────────────┘
 */

if ( ! function_exists( 'st_wp_starter_get_asset_version' ) ) {
	/**
	 * Return a built asset version.
	 *
	 * File timestamps are used for cache busting when the compiled asset exists.
	 * The theme version is a safe fallback during first setup or before assets
	 * have been generated.
	 *
	 * @param string $relative_path Asset path relative to the theme root.
	 *
	 * @return string
	 */
	function st_wp_starter_get_asset_version( string $relative_path ): string {
		$asset_path = get_template_directory() . $relative_path;
		$filemtime  = file_exists( $asset_path ) ? filemtime( $asset_path ) : false;

		return $filemtime ? (string) $filemtime : st_wp_core_get_theme_version();
	}
}

if ( ! function_exists( 'st_wp_starter_asset_exists' ) ) {
	/**
	 * Check whether a built asset exists.
	 *
	 * Use this before enqueuing optional compiled assets so a fresh checkout does
	 * not fatal before `npm run build` has produced assets/css and assets/js.
	 *
	 * @param string $relative_path Asset path relative to the theme root.
	 *
	 * @return bool
	 */
	function st_wp_starter_asset_exists( string $relative_path ): bool {
		return file_exists( get_template_directory() . $relative_path );
	}
}

if ( ! function_exists( 'st_wp_starter_asset_has_content' ) ) {
	/**
	 * Check whether a built asset exists and has content.
	 *
	 * This is used for optional scripts such as admin.js. A source file may exist
	 * only as documentation, in which case Vite may not emit a useful JS bundle.
	 *
	 * @param string $relative_path Asset path relative to the theme root.
	 *
	 * @return bool
	 */
	function st_wp_starter_asset_has_content( string $relative_path ): bool {
		$asset_path = get_template_directory() . $relative_path;

		return file_exists( $asset_path ) && filesize( $asset_path ) > 0;
	}
}

if ( ! function_exists( 'st_wp_starter_theme_scripts' ) ) {
	/**
	 * Enqueue theme front-end scripts and styles.
	 *
	 * This is where generated projects normally customize front-end asset
	 * loading. Keep the generic filenames (`main.scss`, `main.js`) and adjust
	 * this callback or hook additional callbacks to `st_wp_core_enqueue_scripts`.
	 *
	 * @return void
	 */
	function st_wp_starter_theme_scripts(): void {
		// style.css remains the WordPress theme metadata stylesheet.
		wp_enqueue_style(
			'st-wp-starter-style',
			get_stylesheet_uri(),
			array(),
			st_wp_core_get_theme_version()
		);

		// main.scss -> assets/css/main.min.css via Vite.
		if ( st_wp_starter_asset_exists( '/assets/css/main.min.css' ) ) {
			wp_enqueue_style(
				'st-wp-starter-main',
				get_template_directory_uri() . '/assets/css/main.min.css',
				array(),
				st_wp_starter_get_asset_version( '/assets/css/main.min.css' )
			);
		}

		// main.js -> assets/js/main.min.js via Vite.
		if ( st_wp_starter_asset_has_content( '/assets/js/main.min.js' ) ) {
			wp_enqueue_script(
				'st-wp-starter-main',
				get_template_directory_uri() . '/assets/js/main.min.js',
				array(),
				st_wp_starter_get_asset_version( '/assets/js/main.min.js' ),
				true
			);
		}
	}
}
add_action( 'st_wp_core_enqueue_scripts', 'st_wp_starter_theme_scripts', 5 );

if ( ! function_exists( 'st_wp_starter_editor_styles' ) ) {
	/**
	 * Enqueue theme styles for the WordPress post/page editor canvas.
	 *
	 * Block API v3 uses an iframe for the editor canvas. Editor content styles
	 * must load through block asset APIs, not through `admin_enqueue_scripts`,
	 * otherwise WordPress warns that the stylesheet was added incorrectly.
	 *
	 * @return void
	 */
	function st_wp_starter_editor_styles(): void {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! st_wp_starter_asset_exists( '/assets/css/editor.min.css' ) ) {
			return;
		}

		wp_enqueue_style(
			'st-wp-starter-editor',
			get_template_directory_uri() . '/assets/css/editor.min.css',
			array(),
			st_wp_starter_get_asset_version( '/assets/css/editor.min.css' )
		);
	}
}
add_action( 'enqueue_block_assets', 'st_wp_starter_editor_styles' );

if ( ! function_exists( 'st_wp_starter_admin_scripts' ) ) {
	/**
	 * Enqueue project-owned wp-admin behavior.
	 *
	 * Editor canvas styles load from `st_wp_starter_editor_styles()` via
	 * `enqueue_block_assets`. This callback is only for wp-admin UI scripts.
	 *
	 * @return void
	 */
	function st_wp_starter_admin_scripts(): void {
		// admin.js is optional and only enqueued when the generated file has code.
		if ( st_wp_starter_asset_has_content( '/assets/js/admin.min.js' ) ) {
			wp_enqueue_script(
				'st-wp-starter-admin',
				get_template_directory_uri() . '/assets/js/admin.min.js',
				array( 'jquery' ),
				st_wp_starter_get_asset_version( '/assets/js/admin.min.js' ),
				true
			);
		}
	}
}
add_action( 'admin_enqueue_scripts', 'st_wp_starter_admin_scripts' );

if ( ! function_exists( 'st_wp_starter_woocommerce_scripts' ) ) {
	/**
	 * WooCommerce specific scripts & stylesheets.
	 *
	 * Visual WooCommerce overrides belong to the generated theme. The reusable
	 * WooCommerce support hooks remain in /core/woocommerce.php.
	 *
	 * @return void
	 */
	function st_wp_starter_woocommerce_scripts(): void {
		if ( ! class_exists( 'WooCommerce' ) || ! apply_filters( 'st_wp_core_enable_woocommerce', true ) ) {
			return;
		}

		if ( ! st_wp_starter_asset_exists( '/assets/css/woocommerce.min.css' ) ) {
			return;
		}

		wp_enqueue_style(
			'st-wp-starter-woocommerce',
			get_template_directory_uri() . '/assets/css/woocommerce.min.css',
			array(),
			st_wp_starter_get_asset_version( '/assets/css/woocommerce.min.css' )
		);

		// WooCommerce star ratings expect the plugin's bundled star font.
		if ( function_exists( 'WC' ) && WC() ) {
			$font_path   = WC()->plugin_url() . '/assets/fonts/';
			$inline_font = '@font-face {
				font-family: "star";
				src: url("' . $font_path . 'star.eot");
				src: url("' . $font_path . 'star.eot?#iefix") format("embedded-opentype"),
					url("' . $font_path . 'star.woff") format("woff"),
					url("' . $font_path . 'star.ttf") format("truetype"),
					url("' . $font_path . 'star.svg#star") format("svg");
				font-weight: normal;
				font-style: normal;
			}';

			wp_add_inline_style( 'st-wp-starter-woocommerce', $inline_font );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'st_wp_starter_woocommerce_scripts' );

if ( ! function_exists( 'st_wp_starter_custom_scripts' ) ) {
	/**
	 * Custom Scripts & Styles
	 *
	 * Add your custom scripts and styles here.
	 * This function is called on the st_wp_core_enqueue_scripts action.
	 *
	 * WHEN TO USE THIS:
	 * - Adding third-party libraries (not managed via /assets/ directories)
	 * - Conditional script loading based on page templates or post types
	 * - Scripts that require specific dependencies
	 * - Any custom wp_enqueue_script() or wp_enqueue_style() calls
	 *
	 * EXAMPLES:
	 * - Load scripts only on specific pages
	 * - Add Google Fonts, Analytics, or other third-party services
	 * - Enqueue registered scripts conditionally
	 *
	 * @return void
	 */
	function st_wp_starter_custom_scripts(): void {
		/**
		 * ──────────────────────────────────────────────────────────────────────
		 * ADD YOUR CUSTOM SCRIPTS/STYLES BELOW
		 * ──────────────────────────────────────────────────────────────────────
		 */
	}
}
add_action( 'st_wp_core_enqueue_scripts', 'st_wp_starter_custom_scripts' );
