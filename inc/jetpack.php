<?php
/**
 * Jetpack integration — theme-owned setup.
 *
 * This file is project-owned and safe to edit. The matching core file
 * (core/jetpack.php) handles plugin detection and the st_wp_core_enable_jetpack
 * gate; this file owns the theme design decisions:
 *
 * - Which stylesheet handle Jetpack attaches its inline CSS to (Content Options).
 * - Which CSS selectors map to post-detail elements.
 * - Which template part renders Infinite Scroll results.
 * - Which container and footer element Infinite Scroll targets.
 *
 * To customize Jetpack behavior in a generated theme, edit this file.
 * Do not edit core/jetpack.php.
 *
 * @link https://jetpack.com/support/infinite-scroll/
 * @link https://jetpack.com/support/responsive-videos/
 * @link https://jetpack.com/support/content-options/
 *
 * @package ST_WP_Starter
 */

if ( ! function_exists( 'st_wp_starter_jetpack_setup' ) ) {
	/**
	 * Register Jetpack theme support.
	 *
	 * Hooked to after_setup_theme by st_wp_starter_register_jetpack_hooks() after
	 * core/jetpack.php confirms Jetpack is active and the module is enabled.
	 *
	 * Infinite Scroll — Jetpack appends new posts inside the element matching
	 * 'container'. 'footer' is the ID of the element Jetpack watches to detect
	 * the bottom of the page. 'render' is the callback that outputs each batch.
	 *
	 * Responsive Videos — wraps oEmbed video output in a fluid container so
	 * videos scale with the layout.
	 *
	 * Content Options — Jetpack injects hide rules as inline CSS attached to
	 * the stylesheet handle named in 'stylesheet'. This value must match the
	 * handle passed to wp_enqueue_style() for the primary stylesheet — see
	 * inc/scripts.php. The CSS selectors must match the markup used in
	 * template-parts/content.php and its variants.
	 *
	 * @return void
	 */
	function st_wp_starter_jetpack_setup(): void {
		// Infinite Scroll support.
		add_theme_support(
			'infinite-scroll',
			array(
				'container' => 'main',
				'render'    => 'st_wp_starter_infinite_scroll_render',
				'footer'    => 'page',
			)
		);

		// Responsive Videos support.
		add_theme_support( 'jetpack-responsive-videos' );

		// Content Options support.
		// 'stylesheet' must match the handle used in wp_enqueue_style() for
		// the primary theme stylesheet in inc/scripts.php. Jetpack calls
		// wp_add_inline_style() on this handle to hide toggled post details.
		add_theme_support(
			'jetpack-content-options',
			array(
				'post-details'    => array(
					'stylesheet' => 'st-wp-starter-style',
					'date'       => '.posted-on',
					'categories' => '.cat-links',
					'tags'       => '.tags-links',
					'author'     => '.byline',
					'comment'    => '.comments-link',
				),
				'featured-images' => array(
					'archive' => true,
					'post'    => true,
					'page'    => true,
				),
			)
		);
	}
}

if ( ! function_exists( 'st_wp_starter_infinite_scroll_render' ) ) {
	/**
	 * Render callback for Jetpack Infinite Scroll.
	 *
	 * Jetpack calls this function to output each new batch of posts appended
	 * during infinite scroll. Adjust the template-part slug here if the theme
	 * uses a different content partial structure.
	 *
	 * @return void
	 */
	function st_wp_starter_infinite_scroll_render(): void {
		while ( have_posts() ) {
			the_post();
			if ( is_search() ) :
				get_template_part( 'template-parts/content', 'search' );
			else :
				get_template_part( 'template-parts/content', get_post_type() );
			endif;
		}
	}
}

if ( ! function_exists( 'st_wp_starter_register_jetpack_hooks' ) ) {
	/**
	 * Register theme-owned Jetpack hooks.
	 *
	 * This function runs only after core/jetpack.php fires
	 * st_wp_core_jetpack_loaded, which means:
	 *
	 * - Jetpack is active.
	 * - The core Jetpack module was not disabled with st_wp_core_enable_jetpack.
	 *
	 * Add additional theme-owned Jetpack hooks here so the same core gate
	 * controls the complete integration.
	 *
	 * @return void
	 */
	function st_wp_starter_register_jetpack_hooks(): void {
		add_action( 'after_setup_theme', 'st_wp_starter_jetpack_setup' );
	}
}
add_action( 'st_wp_core_jetpack_loaded', 'st_wp_starter_register_jetpack_hooks' );
