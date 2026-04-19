<?php
/**
 * Disable WordPress emojis Customizer feature.
 *
 * @package ST_WP_Core
 */

if ( ! function_exists( 'st_wp_core_customizer_register_disable_emojis' ) ) {
	/**
	 * Register the Disable Emojis Customizer control.
	 *
	 * @param WP_Customize_Manager $wp_customize The WP_Customize_Manager instance.
	 *
	 * @return void
	 */
	function st_wp_core_customizer_register_disable_emojis( WP_Customize_Manager $wp_customize ): void {
		$wp_customize->add_setting(
			'st_wp_core_disable_emojis',
			array(
				'default'   => true,
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			'st_wp_core_disable_emojis_control',
			array(
				'label'    => __( 'Disable Emojis', 'st-wp-starter' ),
				'section'  => 'st_wp_core_options',
				'settings' => 'st_wp_core_disable_emojis',
				'type'     => 'checkbox',
				'priority' => 10,
			)
		);
	}
}

if ( ! function_exists( 'st_wp_core_disable_wordpress_core_emojis' ) ) {
	/**
	 * Disable WordPress Core Emojis.
	 *
	 * Uses theme customizer setting `st_wp_core_disable_emojis`.
	 *
	 * @return void
	 */
	function st_wp_core_disable_wordpress_core_emojis(): void {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

		add_filter( 'tiny_mce_plugins', 'st_wp_core_remove_tinymce_emoji' );
		add_filter( 'wp_resource_hints', 'st_wp_core_remove_emoji_prefetch', 10, 2 );
	}
}

if ( ! function_exists( 'st_wp_core_remove_tinymce_emoji' ) ) {
	/**
	 * Remove TinyMCE emoji plugin.
	 *
	 * @param array $plugins TinyMCE plugins array.
	 *
	 * @return array
	 */
	function st_wp_core_remove_tinymce_emoji( $plugins ): array {
		if ( is_array( $plugins ) ) {
			return array_diff( $plugins, array( 'wpemoji' ) );
		}

		return array();
	}
}

if ( ! function_exists( 'st_wp_core_remove_emoji_prefetch' ) ) {
	/**
	 * Remove emoji CDN hostname from DNS prefetching hints.
	 *
	 * @param array  $urls          URLs to print for resource hints.
	 * @param string $relation_type The relation type the URLs are printed.
	 *
	 * @return array
	 */
	function st_wp_core_remove_emoji_prefetch( array $urls, string $relation_type ): array {
		if ( 'dns-prefetch' === $relation_type ) {
			$emoji_svg_url = 'https://s.w.org/images/core/emoji/2/svg/';
			$urls          = array_diff( $urls, array( $emoji_svg_url ) );
		}

		return $urls;
	}
}

if ( ! function_exists( 'st_wp_core_customizer_boot_disable_emojis' ) ) {
	/**
	 * Boot the Disable Emojis runtime behavior.
	 *
	 * @return void
	 */
	function st_wp_core_customizer_boot_disable_emojis(): void {
		if ( ! get_theme_mod( 'st_wp_core_disable_emojis', true ) ) {
			return;
		}

		add_action( 'init', 'st_wp_core_disable_wordpress_core_emojis' );
	}
}
