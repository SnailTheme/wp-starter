<?php
/**
 * ST WP Starter core functions & overrides
 *
 * @package ST_WP_Starter
 */

if ( ! function_exists( 'st_wp_starter_setup' ) ) {
	/**
	 *  Sets up theme defaults and registers support for various WordPress features.
	 *
	 *  Note that this function is hooked into the after_setup_theme hook, which
	 *  runs before the init hook. The init hook is too late for some features, such
	 *  as indicating support for post thumbnails.
	 *
	 * @return void
	 */
	function st_wp_starter_setup(): void {
		/*
		* Make theme available for translation.
		* Translations can be filed in the /languages/ directory.
		* If you're building a theme based on ST WP Starter, use a find and replace
		* to change 'st-wp-starter' to the name of your theme in all the template files.
		*/
		load_theme_textdomain( 'st-wp-starter', get_template_directory() . '/languages' );

		// Add default posts and comments RSS feed links to head.
		add_theme_support( 'automatic-feed-links' );

		/*
		* Let WordPress manage the document title.
		* By adding theme support, we declare that this theme does not use a
		* hard-coded <title> tag in the document head, and expect WordPress to
		* provide it for us.
		*/
		add_theme_support( 'title-tag' );

		/*
		* Enable support for Post Thumbnails on posts and pages.
		*
		* @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
		*/
		add_theme_support( 'post-thumbnails' );

		// This theme uses wp_nav_menu() in one location.
		register_nav_menus(
			array(
				'menu-1' => esc_html__( 'Primary', 'st-wp-starter' ),
			)
		);

		/*
		* Switch default core markup for search form, comment form, and comments
		* to output valid HTML5.
		*/
		add_theme_support(
			'html5',
			array(
				'search-form',
				'comment-form',
				'comment-list',
				'gallery',
				'caption',
				'style',
				'script',
			)
		);

		// Add theme support for selective refresh for widgets.
		add_theme_support( 'customize-selective-refresh-widgets' );

		/**
		 * Add support for core custom logo.
		 *
		 * @link https://codex.wordpress.org/Theme_Logo
		 */
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 250,
				'width'       => 250,
				'flex-width'  => true,
				'flex-height' => true,
			)
		);
	}
}
add_action( 'after_setup_theme', 'st_wp_starter_setup' );


if ( ! function_exists( 'st_wp_starter_content_width' ) ) {
	/**
	 *  Set the content width in pixels, based on the theme's design and stylesheet.
	 *
	 *  Priority 0 to make it available to lower priority callbacks.
	 *
	 * @return void
	 * @global int $content_width
	 */
	function st_wp_starter_content_width(): void {
		$GLOBALS['content_width'] = apply_filters( 'st_wp_starter_content_width', 640 );
	}
}
add_action( 'after_setup_theme', 'st_wp_starter_content_width', 0 );


if ( function_exists( 'acf_add_options_page' )
	&& ! function_exists( 'st_wp_starter_acf_options_page_init' ) ) {
	/**
	 * Theme General Settings page.
	 *
	 * @return void
	 */
	function st_wp_starter_acf_options_page_init(): void {
		acf_add_options_page(
			array(
				'page_title' => __( 'Theme General Settings', 'st-wp-starter' ),
				'menu_title' => __( 'Theme Settings', 'st-wp-starter' ),
				'post_id'    => 'options',
				'redirect'   => false,
			)
		);
	}
}
if ( function_exists( 'acf_add_options_page' ) ) {
	add_action( 'acf/init', 'st_wp_starter_acf_options_page_init' );
}


if ( ! function_exists( 'st_wp_starter_add_theme_block_categories' ) ) {
	/**
	 * Register the theme Gutenberg block category.
	 *
	 * @param array $categories Current list of categories.
	 *
	 * @return array
	 */
	function st_wp_starter_add_theme_block_categories( array $categories ): array {
		$block_category = 'st-wp-starter';

		if ( defined( 'ST_WP_CORE_THEME_PATTERNS' ) && isset( ST_WP_CORE_THEME_PATTERNS['block_category'] ) ) {
			$block_category = (string) ST_WP_CORE_THEME_PATTERNS['block_category'];
		}

		$theme_category = array(
			'slug'  => $block_category,
			'title' => __( 'ST WP Starter Blocks', 'st-wp-starter' ),
			'icon'  => 'admin-appearance',
		);
		array_unshift( $categories, $theme_category );

		return $categories;
	}
}
add_filter( 'block_categories_all', 'st_wp_starter_add_theme_block_categories', 10, 2 );
