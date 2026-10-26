<?php
/**
 * WooCommerce integration — theme-owned setup.
 *
 * This file is project-owned and safe to edit. The matching core file
 * (core/woocommerce.php) handles plugin detection, the st_wp_core_enable_woocommerce
 * gate, and reusable compatibility behavior. Everything here is a theme design
 * decision:
 *
 * - WooCommerce theme support (image sizes, product grid shape).
 * - Content wrapper markup (replaces WooCommerce's default wrappers).
 * - Related products count and column layout.
 * - Cart link HTML and header cart HTML.
 * - AJAX cart fragment update tied to the cart link markup.
 *
 * To customize WooCommerce behavior in a generated theme, edit this file.
 * Do not edit core/woocommerce.php.
 *
 * Hook registrations for all theme-owned callbacks are collected at the bottom
 * of this file and registered only after core/woocommerce.php fires
 * st_wp_core_woocommerce_loaded. That keeps the st_wp_core_enable_woocommerce filter
 * responsible for the complete WooCommerce integration, not only the core
 * compatibility layer.
 *
 * @link https://woocommerce.com/
 * @link https://docs.woocommerce.com/document/third-party-custom-theme-compatibility/
 *
 * @package ST_WP_Starter
 */

if ( ! function_exists( 'st_wp_starter_woocommerce_setup' ) ) {
	/**
	 * WooCommerce theme support.
	 *
	 * Declares WooCommerce support with theme-specific image sizes and product
	 * grid defaults. Adjust these values to match the design:
	 *
	 * - thumbnail_image_width: catalog/archive product image width.
	 * - single_image_width: single product page main image width.
	 * - product_grid: controls the default and limits for columns/rows in the
	 *   Shop page [products] shortcode and block.
	 *
	 * Gallery zoom, lightbox, and slider are also enabled here.
	 *
	 * @link https://github.com/woocommerce/woocommerce/wiki/Declaring-WooCommerce-support-in-themes
	 * @link https://github.com/woocommerce/woocommerce/wiki/Enabling-product-gallery-features-(zoom,-swipe,-lightbox)
	 *
	 * @return void
	 */
	function st_wp_starter_woocommerce_setup(): void {
		add_theme_support(
			'woocommerce',
			array(
				'thumbnail_image_width' => 150,
				'single_image_width'    => 300,
				'product_grid'          => array(
					'default_rows'    => 3,
					'min_rows'        => 1,
					'default_columns' => 4,
					'min_columns'     => 1,
					'max_columns'     => 6,
				),
			)
		);
		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );
	}
}


if ( ! function_exists( 'st_wp_starter_woocommerce_disable_default_styles' ) ) {
	/**
	 * Disable bundled WooCommerce styles when the project CSS was built.
	 *
	 * The Bare profile intentionally has no WooCommerce stylesheet, so it keeps
	 * the plugin defaults. Blueprint or a customized project can provide
	 * assets/css/woocommerce.min.css; in that case the generated stylesheet is
	 * enqueued from /inc/scripts.php and can safely replace the bundled styles.
	 *
	 * @param bool $disable_default_styles Existing decision from earlier filters.
	 * @return bool True when a project replacement stylesheet is available.
	 */
	function st_wp_starter_woocommerce_disable_default_styles( bool $disable_default_styles ): bool {
		return $disable_default_styles || is_file( get_template_directory() . '/assets/css/woocommerce.min.css' );
	}
}


if ( ! function_exists( 'st_wp_starter_woocommerce_wrapper_before' ) ) {
	/**
	 * Before Content — opening WooCommerce content wrapper.
	 *
	 * WooCommerce's default wrappers are removed by
	 * st_wp_core_woocommerce_remove_default_wrappers() in core/woocommerce.php so
	 * the theme can supply its own markup that matches the rest of the templates.
	 *
	 * Adjust the element, ID, and class to match the theme's layout. The ID
	 * 'primary' and class 'site-main' match the structure used in index.php,
	 * archive.php, single.php, and page.php.
	 *
	 * @return void
	 */
	function st_wp_starter_woocommerce_wrapper_before(): void {
		?>
		<main id="primary" class="site-main">
		<?php
	}
}


if ( ! function_exists( 'st_wp_starter_woocommerce_wrapper_after' ) ) {
	/**
	 * After Content — closing WooCommerce content wrapper.
	 *
	 * Closes the wrapper opened by st_wp_starter_woocommerce_wrapper_before().
	 *
	 * @return void
	 */
	function st_wp_starter_woocommerce_wrapper_after(): void {
		?>
		</main><!-- #primary -->
		<?php
	}
}


if ( ! function_exists( 'st_wp_starter_woocommerce_related_products_args' ) ) {
	/**
	 * Related Products display arguments.
	 *
	 * Controls how many related products appear on a single product page and
	 * how they are laid out. Adjust posts_per_page and columns to match the
	 * theme's product grid design.
	 *
	 * @param array $args Related products query and display args.
	 * @return array Modified args.
	 */
	function st_wp_starter_woocommerce_related_products_args( array $args ): array {
		$defaults = array(
			'posts_per_page' => 3,
			'columns'        => 3,
		);

		return wp_parse_args( $defaults, $args );
	}
}


if ( ! function_exists( 'st_wp_starter_woocommerce_cart_link' ) ) {
	/**
	 * Cart Link.
	 *
	 * Outputs the mini cart anchor used in the site header. The outermost element
	 * uses the class 'cart-contents', which is also the AJAX fragment selector
	 * in st_wp_starter_woocommerce_cart_link_fragment() below. If you change the
	 * element or class, update the fragment key in that function to match.
	 *
	 * To add the cart link to the header, call this function from header.php:
	 *
	 *     if ( function_exists( 'st_wp_starter_woocommerce_cart_link' ) ) {
	 *         st_wp_starter_woocommerce_cart_link();
	 *     }
	 *
	 * @return void
	 */
	function st_wp_starter_woocommerce_cart_link(): void {
		?>
		<a class="cart-contents" href="<?php echo esc_url( wc_get_cart_url() ); ?>" title="<?php esc_attr_e( 'View your shopping cart', 'st-wp-starter' ); ?>">
			<?php
			$item_count_text = sprintf(
				/* translators: number of items in the mini cart. */
				_n( '%d item', '%d items', WC()->cart->get_cart_contents_count(), 'st-wp-starter' ),
				WC()->cart->get_cart_contents_count()
			);
			?>
			<span class="amount"><?php echo wp_kses_data( WC()->cart->get_cart_subtotal() ); ?></span> <span class="count"><?php echo esc_html( $item_count_text ); ?></span>
		</a>
		<?php
	}
}


if ( ! function_exists( 'st_wp_starter_woocommerce_header_cart' ) ) {
	/**
	 * Display Header Cart.
	 *
	 * Outputs the full mini cart widget used in the site header: the cart link
	 * and the WooCommerce Cart widget side by side in a list.
	 *
	 * To add the header cart to the header, call this function from header.php:
	 *
	 *     if ( function_exists( 'st_wp_starter_woocommerce_header_cart' ) ) {
	 *         st_wp_starter_woocommerce_header_cart();
	 *     }
	 *
	 * The 'site-header-cart' list ID and the 'current-menu-item' class are used
	 * for CSS targeting. Adjust them to match the theme's navigation styles.
	 *
	 * @return void
	 */
	function st_wp_starter_woocommerce_header_cart(): void {
		$class = is_cart() ? 'current-menu-item' : '';
		?>
		<ul id="site-header-cart" class="site-header-cart">
			<li class="<?php echo esc_attr( $class ); ?>">
				<?php st_wp_starter_woocommerce_cart_link(); ?>
			</li>
			<li>
				<?php the_widget( 'WC_Widget_Cart', array( 'title' => '' ) ); ?>
			</li>
		</ul>
		<?php
	}
}


if ( ! function_exists( 'st_wp_starter_woocommerce_cart_link_fragment' ) ) {
	/**
	 * Cart Fragments.
	 *
	 * Captures the cart link markup as a fragment so the header cart updates
	 * via AJAX when products are added without a full page reload.
	 *
	 * The 'a.cart-contents' key is a CSS selector that WooCommerce uses to
	 * replace the matching element in the DOM. It must match the outermost
	 * element output by st_wp_starter_woocommerce_cart_link(). If you change
	 * the element or class there, update the key here to match.
	 *
	 * @param array $fragments Fragments to refresh via AJAX.
	 * @return array Fragments to refresh via AJAX.
	 */
	function st_wp_starter_woocommerce_cart_link_fragment( array $fragments ): array {
		ob_start();
		st_wp_starter_woocommerce_cart_link();
		$fragments['a.cart-contents'] = ob_get_clean();

		return $fragments;
	}
}

if ( ! function_exists( 'st_wp_starter_register_woocommerce_hooks' ) ) {
	/**
	 * Register theme-owned WooCommerce hooks.
	 *
	 * This function runs only after core/woocommerce.php fires
	 * st_wp_core_woocommerce_loaded, which means:
	 *
	 * - WooCommerce is active.
	 * - The core WooCommerce module was not disabled with st_wp_core_enable_woocommerce.
	 *
	 * Add additional theme-owned WooCommerce hooks here so the same core gate
	 * controls the complete integration.
	 *
	 * @return void
	 */
	function st_wp_starter_register_woocommerce_hooks(): void {
		add_action( 'after_setup_theme', 'st_wp_starter_woocommerce_setup' );
		add_action( 'woocommerce_before_main_content', 'st_wp_starter_woocommerce_wrapper_before' );
		add_action( 'woocommerce_after_main_content', 'st_wp_starter_woocommerce_wrapper_after' );
		add_filter( 'st_wp_core_disable_woocommerce_default_styles', 'st_wp_starter_woocommerce_disable_default_styles' );
		add_filter( 'woocommerce_output_related_products_args', 'st_wp_starter_woocommerce_related_products_args' );
		add_filter( 'woocommerce_add_to_cart_fragments', 'st_wp_starter_woocommerce_cart_link_fragment' );
	}
}
add_action( 'st_wp_core_woocommerce_loaded', 'st_wp_starter_register_woocommerce_hooks' );
