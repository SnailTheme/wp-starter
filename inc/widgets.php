<?php
/**
 * ST WP Starter widgets functions
 *
 * Register widget area
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 *
 * @package ST_WP_Starter
 */

if ( ! function_exists( 'st_wp_starter_widgets_init' ) ) {
	/**
	 * Register sidebar
	 *
	 * @return void
	 */
	function st_wp_starter_widgets_init(): void {
		register_sidebar(
			array(
				'name'          => esc_html__( 'Sidebar', 'st-wp-starter' ),
				'id'            => 'sidebar-1',
				'description'   => esc_html__( 'Add widgets here.', 'st-wp-starter' ),
				'before_widget' => '<section id="%1$s" class="widget %2$s">',
				'after_widget'  => '</section>',
				'before_title'  => '<h2 class="widget-title">',
				'after_title'   => '</h2>',
			)
		);
	}
}
add_action( 'widgets_init', 'st_wp_starter_widgets_init' );
