<?php
/**
 * Custom Theme Walker - usage optional.
 *  Do not add to this file. Each class should be its own file.
 *
 * @package ST_WP_Core
 */

/**
 * How to override this class:
 *
 * 1. Create file: /inc/classes/class-st-wp-core-walker-nav-menu.php
 * 2. Define your class (the autoloader checks /inc/classes/ first):
 *
 * Example:
 * class ST_WP_Core_Walker_Nav_Menu extends Walker_Nav_Menu {
 *     public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
 *         // Your custom implementation
 *         // You can call parent::start_el() or write completely custom logic
 *     }
 * }
 */

/**
 *  Adds support for:
 *  - "Unlink" => will convert a-tags into simple spans.
 *  - "Image"   => Custom Image Upload that will render inside the menu-item.
 *
 * Custom Metas used:
 *  - unlink
 *  - image
 *
 * @package ST_WP_Core
 */
if ( ! class_exists( 'ST_WP_Core_Walker_Nav_Menu' ) ) {
	/**
	 * Core walker with support for unlinked menu items and menu images.
	 */
	class ST_WP_Core_Walker_Nav_Menu extends Walker_Nav_Menu {

		/**
		 * Start the element output.
		 *
		 * @param string   $output Used to append additional content (passed by reference).
		 * @param WP_Post  $item Menu item data object.
		 * @param int      $depth Depth of menu item. Used for padding.
		 * @param stdClass $args An object of wp_nav_menu() arguments.
		 * @param int      $id Current item ID.
		 *
		 * @see Walker_Nav_Menu::start_el() for the original.
		 */
		public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {

			// Ensure $args is an object for compatibility.
			if ( is_array( $args ) ) {
				$args = (object) $args;
			}

			/* ───── 1. Prep the <li> wrapper exactly like core does ───── */
			$indent = ( $depth ) ? str_repeat( "\t", $depth ) : '';

			$classes   = empty( $item->classes ) ? array() : (array) $item->classes;
			$classes[] = 'menu-item-' . $item->ID;

			$classnames = join(
				' ',
				apply_filters(
					'nav_menu_css_class',
					array_filter( $classes ),
					$item,
					$args,
					$depth
				)
			);
			$classnames = $classnames ? ' class="' . esc_attr( $classnames ) . '"' : '';

			$item_id = apply_filters(
				'nav_menu_item_id',
				'menu-item-' . $item->ID,
				$item,
				$args,
				$depth
			);
			$item_id = $item_id ? ' id="' . esc_attr( $item_id ) . '"' : '';

			$output .= $indent . '<li' . $item_id . $classnames . '>';

			/* ───── 2. Get our custom meta ───── */
			$unlink   = get_post_meta( $item->ID, '_menu_item_unlink', true );
			$image_id = get_post_meta( $item->ID, '_menu_item_image_id', true );

			/* ───── 3. Build link (or span) attributes ───── */
			$atts = array(
				'title' => ! empty( $item->attr_title ) ? $item->attr_title : '',
				'class' => 'menu-link',
			);

			if ( ! $unlink ) { // normal <a> tag.
				$atts['href']   = ! empty( $item->url ) ? $item->url : '';
				$atts['target'] = ! empty( $item->target ) ? $item->target : '';
				$atts['rel']    = ! empty( $item->xfn ) ? $item->xfn : '';
			} else { // <span> tag.
				$item->classes[] = 'menu-item-unlinked';
			}

			/** Let plugins/themes add/alter attributes */
			$atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args, $depth );

			/* Core helper to turn the array into a string */
			$attributes = $this->build_atts( $atts );

			/* ───── 4. Build the link text, including image if any ───── */
			$title = apply_filters( 'the_title', $item->title, $item->ID );
			$title = apply_filters( 'nav_menu_item_title', $title, $item, $args, $depth );

			$image_html = '';
			if ( $image_id ) {
				$image_html      = wp_get_attachment_image(
					$image_id,
					'full', // can change thumbnail size here.
					false,
					array(
						'class' => 'menu-item-image',
						'alt'   => wp_strip_all_tags( $title ),
					)
				);
				$item->classes[] = 'has-image';
			}

			$item_output  = $args->before;
			$tag          = $unlink ? 'span' : 'a';
			$item_output .= '<' . $tag . $attributes . '>';
			$item_output .= $args->link_before . $image_html;
			$item_output .= '<span class="menu-item-text">' . $title . '</span>';
			$item_output .= $args->link_after;
			$item_output .= '</' . $tag . '>';
			$item_output .= $args->after;

			$output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
		}
	}
}
