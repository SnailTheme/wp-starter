<?php
/**
 * WooCommerce Compatibility File
 *
 * Integration bootstrapping and generic compatibility behavior only. Theme design
 * decisions (wrapper markup, cart HTML, image sizes, product grid,
 * related-product count) live in /inc/woocommerce.php.
 *
 * The st_wp_core_enable_woocommerce filter gates both the core-owned behaviors
 * below and the theme-owned hooks registered by /inc/woocommerce.php through
 * the st_wp_core_woocommerce_loaded action.
 *
 * @link https://woocommerce.com/
 *
 * @package ST_WP_Core
 */

/**
 * Only proceed if WooCommerce is active.
 *
 * Theme-specific WooCommerce styling is enqueued from /inc/scripts.php.
 */
if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

/**
 * Load theme-owned WooCommerce callbacks.
 *
 * /inc/woocommerce.php defines and registers all project-specific WooCommerce
 * behaviors: theme support, content wrappers, cart markup, header cart, cart
 * AJAX fragments, and related-products display settings. Loading it here
 * ensures those functions are available before any hook fires.
 */
if ( file_exists( get_template_directory() . '/inc/woocommerce.php' ) ) {
	require get_template_directory() . '/inc/woocommerce.php';
}

// Disable the core WooCommerce compatibility module from /inc/bootstrap.php.
if ( ! apply_filters( 'st_wp_core_enable_woocommerce', true ) ) {
	return;
}

add_action( 'after_setup_theme', 'st_wp_core_woocommerce_disable_default_styles' );
add_action( 'after_setup_theme', 'st_wp_core_woocommerce_remove_default_wrappers' );
add_filter( 'body_class', 'st_wp_core_woocommerce_active_body_class' );


if ( ! function_exists( 'st_wp_core_woocommerce_disable_default_styles' ) ) {
	/**
	 * Disable the default WooCommerce stylesheet.
	 *
	 * Removing the default WooCommerce stylesheet and using a project-built
	 * stylesheet protects the theme during WooCommerce core updates.
	 *
	 * @link https://docs.woocommerce.com/document/disable-the-default-stylesheet/
	 *
	 * @return void
	 */
	function st_wp_core_woocommerce_disable_default_styles(): void {
		add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );
	}
}


if ( ! function_exists( 'st_wp_core_woocommerce_remove_default_wrappers' ) ) {
	/**
	 * Remove default WooCommerce content wrappers.
	 *
	 * WooCommerce outputs its own before/after wrappers that do not match this
	 * theme's markup. They are removed here so the theme-owned wrappers in
	 * /inc/woocommerce.php can attach on the same hooks without duplication.
	 *
	 * @return void
	 */
	function st_wp_core_woocommerce_remove_default_wrappers(): void {
		remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
		remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
	}
}


if ( ! function_exists( 'st_wp_core_woocommerce_active_body_class' ) ) {
	/**
	 * Add 'woocommerce-active' class to the body tag.
	 *
	 * Lets CSS target WooCommerce-enabled pages without a PHP conditional.
	 *
	 * @param array $classes CSS classes applied to the body tag.
	 * @return array Modified classes.
	 */
	function st_wp_core_woocommerce_active_body_class( array $classes ): array {
		$classes[] = 'woocommerce-active';

		return $classes;
	}
}

/**
 * Fires after WooCommerce is active and the core WooCommerce module is enabled.
 *
 * Theme-owned WooCommerce callbacks should register from /inc/woocommerce.php
 * on this action. Keeping this as a generic core signal avoids hardcoding
 * generated theme function names in /core/ while still letting
 * st_wp_core_enable_woocommerce disable the whole integration.
 *
 * @since 1.0.0
 */
do_action( 'st_wp_core_woocommerce_loaded' );
