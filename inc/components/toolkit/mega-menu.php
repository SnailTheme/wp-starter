<?php
/**
 * Toolkit mega-menu integration.
 *
 * This optional component owns the primary navigation interaction, responsive
 * drawer hierarchy, and site-search controls without changing header.php. Its
 * CSS and JavaScript are registered by the core asset registry and enqueued
 * only while the configured menu location has an assigned menu.
 *
 * @package ST_WP_Starter
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'st_wp_starter_toolkit_mega_menu_location' ) ) {
	/**
	 * Return the theme location enhanced by the component.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	function st_wp_starter_toolkit_mega_menu_location(): string {
		/**
		 * Filters the navigation location enhanced by the toolkit mega menu.
		 *
		 * @param string $location Registered WordPress menu location.
		 *
		 * @since 1.1.0
		 */
		return (string) apply_filters( 'st_wp_toolkit_mega_menu_location', 'menu-1' );
	}
}

if ( ! function_exists( 'st_wp_starter_toolkit_mega_menu_is_target' ) ) {
	/**
	 * Check whether wp_nav_menu() arguments target this component.
	 *
	 * @param object|array<string,mixed> $args Menu arguments.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	function st_wp_starter_toolkit_mega_menu_is_target( object|array $args ): bool {
		$location = is_array( $args ) ? ( $args['theme_location'] ?? '' ) : ( $args->theme_location ?? '' );

		return st_wp_starter_toolkit_mega_menu_location() === $location;
	}
}

if ( ! function_exists( 'st_wp_starter_toolkit_mega_menu_args' ) ) {
	/**
	 * Prepare the target menu for isolated component markup.
	 *
	 * @param array<string,mixed> $args wp_nav_menu() arguments.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string,mixed>
	 */
	function st_wp_starter_toolkit_mega_menu_args( array $args ): array {
		if ( ! st_wp_starter_toolkit_mega_menu_is_target( $args ) ) {
			return $args;
		}

		$args['container']   = false;
		$args['fallback_cb'] = false;
		$args['menu_class']  = trim( 'st-toolkit-mega-menu__list ' . (string) ( $args['menu_class'] ?? 'menu' ) );

		return $args;
	}
}
add_filter( 'wp_nav_menu_args', 'st_wp_starter_toolkit_mega_menu_args' );

if ( ! function_exists( 'st_wp_starter_toolkit_mega_menu_prepare_items' ) ) {
	/**
	 * Classify top-level panels from their actual WordPress menu hierarchy.
	 *
	 * A panel becomes a true grid only when every direct child owns another
	 * level. Flat and mixed structures intentionally fall back to one recursive
	 * column, preventing partially configured menus from producing broken grids.
	 *
	 * @param WP_Post[] $items Menu item objects.
	 * @param stdClass  $args  wp_nav_menu() arguments.
	 *
	 * @since 1.1.0
	 *
	 * @return WP_Post[]
	 */
	function st_wp_starter_toolkit_mega_menu_prepare_items( array $items, stdClass $args ): array {
		if ( ! st_wp_starter_toolkit_mega_menu_is_target( $args ) ) {
			return $items;
		}

		$children = array();

		foreach ( $items as $item ) {
			$parent_id                = (int) $item->menu_item_parent;
			$children[ $parent_id ][] = $item;
		}

		foreach ( $items as $item ) {
			if ( 0 !== (int) $item->menu_item_parent ) {
				continue;
			}

			$direct_children = $children[ (int) $item->ID ] ?? array();

			if ( array() === $direct_children ) {
				continue;
			}

			$grouped = true;

			foreach ( $direct_children as $direct_child ) {
				if ( empty( $children[ (int) $direct_child->ID ] ) ) {
					$grouped = false;
					break;
				}
			}

			$item->classes   = is_array( $item->classes ) ? $item->classes : array();
			$item->classes[] = $grouped
				? 'st-toolkit-mega-menu__item--grouped'
				: 'st-toolkit-mega-menu__item--flat';

			$columns         = $grouped ? max( 1, min( 4, count( $direct_children ) ) ) : 1;
			$item->classes[] = 'st-toolkit-mega-menu__item--columns-' . $columns;

			if ( $grouped && 3 <= count( $direct_children ) ) {
				$item->classes[] = 'st-toolkit-mega-menu__item--full';
			}
		}

		return $items;
	}
}
add_filter( 'wp_nav_menu_objects', 'st_wp_starter_toolkit_mega_menu_prepare_items', 10, 2 );

if ( ! function_exists( 'st_wp_starter_toolkit_mega_menu_item_classes' ) ) {
	/**
	 * Add depth and image-mode classes below the component root.
	 *
	 * @param string[] $classes Existing item classes.
	 * @param WP_Post  $item    Menu item object.
	 * @param stdClass $args    wp_nav_menu() arguments.
	 * @param int      $depth   Item depth.
	 *
	 * @since 1.1.0
	 *
	 * @return string[]
	 */
	function st_wp_starter_toolkit_mega_menu_item_classes( array $classes, WP_Post $item, stdClass $args, int $depth ): array {
		if ( ! st_wp_starter_toolkit_mega_menu_is_target( $args ) ) {
			return $classes;
		}

		$classes[] = 'st-toolkit-mega-menu__item';
		$classes[] = 'st-toolkit-mega-menu__item--depth-' . $depth;

		$image_id = absint( get_post_meta( $item->ID, '_menu_item_image_id', true ) );

		if ( $image_id ) {
			$image_mode = (string) get_post_meta( $item->ID, '_st_toolkit_mega_menu_image_display', true );
			$image_mode = in_array( $image_mode, array( 'icon', 'card' ), true ) ? $image_mode : 'auto';
			$classes[]  = 'st-toolkit-mega-menu__item--has-image';
			$classes[]  = 'st-toolkit-mega-menu__item--image-' . $image_mode;
		}

		return array_values( array_unique( $classes ) );
	}
}
add_filter( 'nav_menu_css_class', 'st_wp_starter_toolkit_mega_menu_item_classes', 20, 4 );

if ( ! function_exists( 'st_wp_starter_toolkit_mega_menu_submenu_classes' ) ) {
	/**
	 * Add stable submenu and depth classes.
	 *
	 * @param string[] $classes Existing submenu classes.
	 * @param stdClass $args    wp_nav_menu() arguments.
	 * @param int      $depth   Submenu depth.
	 *
	 * @since 1.1.0
	 *
	 * @return string[]
	 */
	function st_wp_starter_toolkit_mega_menu_submenu_classes( array $classes, stdClass $args, int $depth ): array {
		if ( st_wp_starter_toolkit_mega_menu_is_target( $args ) ) {
			$classes[] = 'st-toolkit-mega-menu__sub-menu';
			$classes[] = 'st-toolkit-mega-menu__sub-menu--depth-' . ( $depth + 1 );
		}

		return array_values( array_unique( $classes ) );
	}
}
add_filter( 'nav_menu_submenu_css_class', 'st_wp_starter_toolkit_mega_menu_submenu_classes', 20, 3 );

if ( ! function_exists( 'st_wp_starter_toolkit_mega_menu_link_attributes' ) ) {
	/**
	 * Add a component link class without replacing project link attributes.
	 *
	 * @param array<string,string> $attributes Link attributes.
	 * @param WP_Post              $item       Menu item object.
	 * @param stdClass             $args       wp_nav_menu() arguments.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string,string>
	 */
	function st_wp_starter_toolkit_mega_menu_link_attributes( array $attributes, WP_Post $item, stdClass $args ): array {
		if ( st_wp_starter_toolkit_mega_menu_is_target( $args ) ) {
			$attributes['class'] = trim( 'st-toolkit-mega-menu__link ' . (string) ( $attributes['class'] ?? '' ) );
		}

		return $attributes;
	}
}
add_filter( 'nav_menu_link_attributes', 'st_wp_starter_toolkit_mega_menu_link_attributes', 20, 3 );

if ( ! function_exists( 'st_wp_starter_toolkit_mega_menu_item_media' ) ) {
	/**
	 * Render optional menu-item media inside component controls.
	 *
	 * @param WP_Post $item Menu item object.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	function st_wp_starter_toolkit_mega_menu_item_media( WP_Post $item ): string {
		$image_id = absint( get_post_meta( $item->ID, '_menu_item_image_id', true ) );

		if ( ! $image_id ) {
			return '';
		}

		return (string) wp_get_attachment_image(
			$image_id,
			'full',
			false,
			array(
				'class'       => 'menu-item-image st-toolkit-mega-menu__image',
				'alt'         => '',
				'aria-hidden' => 'true',
			)
		);
	}
}

if ( ! function_exists( 'st_wp_starter_toolkit_mega_menu_parent_output' ) ) {
	/**
	 * Replace parent links with disclosure controls or desktop headings.
	 *
	 * Top-level parents are buttons at every viewport. Deeper parents become
	 * expanded headings on desktop and full-row drawer buttons on mobile.
	 *
	 * @param string   $item_output Existing walker output.
	 * @param WP_Post  $item        Menu item object.
	 * @param int      $depth       Item depth.
	 * @param stdClass $args        wp_nav_menu() arguments.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	function st_wp_starter_toolkit_mega_menu_parent_output( string $item_output, WP_Post $item, int $depth, stdClass $args ): string {
		if (
			! st_wp_starter_toolkit_mega_menu_is_target( $args )
			|| ! in_array( 'menu-item-has-children', (array) $item->classes, true )
		) {
			return $item_output;
		}

		$title = esc_html( wp_strip_all_tags( (string) $item->title ) );
		$media = st_wp_starter_toolkit_mega_menu_item_media( $item );
		$icon  = '<span class="st-toolkit-mega-menu__trigger-icon" aria-hidden="true"></span>';

		if ( 0 === $depth ) {
			return sprintf(
				'<button class="st-toolkit-mega-menu__trigger" type="button" aria-expanded="false">%1$s<span class="st-toolkit-mega-menu__trigger-label">%2$s</span>%3$s</button>',
				$media,
				$title,
				$icon
			);
		}

		return sprintf(
			'<span class="st-toolkit-mega-menu__heading">%1$s<span class="st-toolkit-mega-menu__heading-label">%2$s</span></span><button class="st-toolkit-mega-menu__drawer-trigger" type="button" aria-expanded="false">%1$s<span class="st-toolkit-mega-menu__trigger-label">%2$s</span>%3$s</button>',
			$media,
			$title,
			$icon
		);
	}
}
add_filter( 'walker_nav_menu_start_el', 'st_wp_starter_toolkit_mega_menu_parent_output', 20, 4 );

if ( ! function_exists( 'st_wp_starter_toolkit_mega_menu_search_enabled' ) ) {
	/**
	 * Check whether the component should render site search.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	function st_wp_starter_toolkit_mega_menu_search_enabled(): bool {
		/**
		 * Filters whether the mega-menu header includes site search.
		 *
		 * @param bool $enabled Search enabled state.
		 *
		 * @since 1.1.0
		 */
		return (bool) apply_filters( 'st_wp_toolkit_mega_menu_search_enabled', true );
	}
}

if ( ! function_exists( 'st_wp_starter_toolkit_mega_menu_icon' ) ) {
	/**
	 * Return one component-owned interface icon.
	 *
	 * @param string $icon Icon name.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	function st_wp_starter_toolkit_mega_menu_icon( string $icon ): string {
		$paths = array(
			'back'   => '<path d="m15 18-6-6 6-6"/>',
			'close'  => '<path d="m6 6 12 12M18 6 6 18"/>',
			'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
		);

		if ( ! isset( $paths[ $icon ] ) ) {
			return '';
		}

		return sprintf(
			'<svg class="st-toolkit-mega-menu__interface-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">%s</svg>',
			$paths[ $icon ]
		);
	}
}

if ( ! function_exists( 'st_wp_starter_toolkit_mega_menu_wrap' ) ) {
	/**
	 * Wrap the menu tree with drawer, backdrop, and search controls.
	 *
	 * @param string   $menu Rendered menu list.
	 * @param stdClass $args wp_nav_menu() arguments.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	function st_wp_starter_toolkit_mega_menu_wrap( string $menu, stdClass $args ): string {
		if ( ! st_wp_starter_toolkit_mega_menu_is_target( $args ) || '' === trim( $menu ) ) {
			return $menu;
		}

		$drawer_label = esc_html__( 'Primary menu', 'st-wp-starter' );
		$close_label  = esc_html__( 'Close menu', 'st-wp-starter' );
		$search       = '';

		if ( st_wp_starter_toolkit_mega_menu_search_enabled() ) {
			/**
			 * Filters the site-search placeholder used by the mega menu.
			 *
			 * @param string $placeholder Search field placeholder.
			 *
			 * @since 1.1.0
			 */
			$placeholder = (string) apply_filters(
				'st_wp_toolkit_mega_menu_search_placeholder',
				__( 'Search this site', 'st-wp-starter' )
			);

			$search = sprintf(
				'<button class="st-toolkit-mega-menu__search-toggle" type="button" aria-expanded="false" aria-controls="st-toolkit-mega-menu-search"><span class="screen-reader-text">%1$s</span>%2$s</button><form class="st-toolkit-mega-menu__search" id="st-toolkit-mega-menu-search" role="search" method="get" action="%3$s"><label class="screen-reader-text" for="st-toolkit-mega-menu-search-field">%1$s</label><input class="st-toolkit-mega-menu__search-input" id="st-toolkit-mega-menu-search-field" type="search" name="s" value="%4$s" placeholder="%5$s" autocomplete="off"><button class="st-toolkit-mega-menu__search-submit" type="submit"><span class="screen-reader-text">%1$s</span>%2$s</button><button class="st-toolkit-mega-menu__search-close" type="button"><span class="screen-reader-text">%6$s</span>%7$s</button></form>',
				esc_html__( 'Search', 'st-wp-starter' ),
				st_wp_starter_toolkit_mega_menu_icon( 'search' ),
				esc_url( home_url( '/' ) ),
				esc_attr( get_search_query() ),
				esc_attr( $placeholder ),
				esc_html__( 'Close search', 'st-wp-starter' ),
				st_wp_starter_toolkit_mega_menu_icon( 'close' )
			);
		}

		return sprintf(
			'<div class="st-toolkit-mega-menu" data-st-toolkit-mega-menu><span class="st-toolkit-mega-menu__breakpoint" aria-hidden="true"></span><div class="st-toolkit-mega-menu__backdrop" data-st-toolkit-mega-menu-close aria-hidden="true"></div><div class="st-toolkit-mega-menu__drawer" aria-label="%1$s"><div class="st-toolkit-mega-menu__drawer-header"><span class="st-toolkit-mega-menu__drawer-title">%1$s</span><button class="st-toolkit-mega-menu__drawer-close" type="button"><span class="screen-reader-text">%2$s</span>%3$s</button></div>%4$s</div>%5$s</div>',
			$drawer_label,
			$close_label,
			st_wp_starter_toolkit_mega_menu_icon( 'close' ),
			$menu,
			$search
		);
	}
}
add_filter( 'wp_nav_menu', 'st_wp_starter_toolkit_mega_menu_wrap', 20, 2 );

if ( ! function_exists( 'st_wp_starter_toolkit_mega_menu_body_classes' ) ) {
	/**
	 * Add the component scope only while its target location is populated.
	 *
	 * @param string[] $classes Body classes.
	 *
	 * @since 1.1.0
	 *
	 * @return string[]
	 */
	function st_wp_starter_toolkit_mega_menu_body_classes( array $classes ): array {
		if ( has_nav_menu( st_wp_starter_toolkit_mega_menu_location() ) ) {
			$classes[] = 'st-toolkit-mega-menu-enabled';
		}

		return $classes;
	}
}
add_filter( 'body_class', 'st_wp_starter_toolkit_mega_menu_body_classes' );

if ( ! function_exists( 'st_wp_starter_toolkit_mega_menu_assets' ) ) {
	/**
	 * Enqueue registered component assets and retire legacy navigation behavior.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	function st_wp_starter_toolkit_mega_menu_assets(): void {
		if ( ! has_nav_menu( st_wp_starter_toolkit_mega_menu_location() ) ) {
			return;
		}

		$handle      = 'toolkit.mega-menu';
		$style_path  = get_template_directory() . '/assets/css/styles-register/toolkit/mega-menu.min.css';
		$script_path = get_template_directory() . '/assets/js/scripts-register/toolkit/mega-menu.min.js';
		$style_url   = get_template_directory_uri() . '/assets/css/styles-register/toolkit/mega-menu.min.css';
		$script_url  = get_template_directory_uri() . '/assets/js/scripts-register/toolkit/mega-menu.min.js';
		$style_time  = is_file( $style_path ) ? filemtime( $style_path ) : false;
		$script_time = is_file( $script_path ) ? filemtime( $script_path ) : false;

		wp_dequeue_script( 'navigation' );

		if ( false !== $style_time ) {
			if ( ! wp_style_is( $handle, 'registered' ) ) {
				wp_register_style( $handle, $style_url, array(), (string) $style_time );
			}

			wp_enqueue_style( $handle );
		}

		if ( false !== $script_time && 0 < (int) filesize( $script_path ) ) {
			if ( ! wp_script_is( $handle, 'registered' ) ) {
				wp_register_script( $handle, $script_url, array(), (string) $script_time, true );
			}

			wp_script_add_data( $handle, 'strategy', 'defer' );
			wp_enqueue_script( $handle );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'st_wp_starter_toolkit_mega_menu_assets', 30 );

if ( ! function_exists( 'st_wp_starter_toolkit_mega_menu_admin_field' ) ) {
	/**
	 * Render the component-specific menu-image presentation selector.
	 *
	 * @param int $item_id Menu item ID.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	function st_wp_starter_toolkit_mega_menu_admin_field( int $item_id ): void {
		$value = (string) get_post_meta( $item_id, '_st_toolkit_mega_menu_image_display', true );
		$value = in_array( $value, array( 'icon', 'card' ), true ) ? $value : 'auto';
		?>
		<p class="field-st-toolkit-mega-menu-image-display description description-wide">
			<label for="edit-menu-item-st-toolkit-mega-menu-image-display-<?php echo esc_attr( $item_id ); ?>">
				<?php esc_html_e( 'Mega-menu image display', 'st-wp-starter' ); ?><br>
				<select
					id="edit-menu-item-st-toolkit-mega-menu-image-display-<?php echo esc_attr( $item_id ); ?>"
					name="st-toolkit-mega-menu-image-display[<?php echo esc_attr( $item_id ); ?>]"
				>
					<option value="auto" <?php selected( 'auto', $value ); ?>><?php esc_html_e( 'Auto', 'st-wp-starter' ); ?></option>
					<option value="icon" <?php selected( 'icon', $value ); ?>><?php esc_html_e( 'Icon', 'st-wp-starter' ); ?></option>
					<option value="card" <?php selected( 'card', $value ); ?>><?php esc_html_e( 'Card', 'st-wp-starter' ); ?></option>
				</select>
			</label>
		</p>
		<?php
	}
}
add_action( 'wp_nav_menu_item_custom_fields', 'st_wp_starter_toolkit_mega_menu_admin_field', 20 );

if ( ! function_exists( 'st_wp_starter_toolkit_mega_menu_save_admin_field' ) ) {
	/**
	 * Save the menu-image presentation selector from a real menu form request.
	 *
	 * @param int $menu_id         Menu ID.
	 * @param int $menu_item_db_id Menu item ID.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	function st_wp_starter_toolkit_mega_menu_save_admin_field( int $menu_id, int $menu_item_db_id ): void {
		unset( $menu_id );

		if ( ! isset( $_POST['st-toolkit-mega-menu-image-display'] ) ) {
			return;
		}

		check_admin_referer( 'update-nav_menu', 'update-nav-menu-nonce' );

		if ( ! is_array( $_POST['st-toolkit-mega-menu-image-display'] ) ) {
			return;
		}

		$value = isset( $_POST['st-toolkit-mega-menu-image-display'][ $menu_item_db_id ] )
			? sanitize_key( wp_unslash( $_POST['st-toolkit-mega-menu-image-display'][ $menu_item_db_id ] ) )
			: 'auto';

		if ( in_array( $value, array( 'icon', 'card' ), true ) ) {
			update_post_meta( $menu_item_db_id, '_st_toolkit_mega_menu_image_display', $value );
			return;
		}

		delete_post_meta( $menu_item_db_id, '_st_toolkit_mega_menu_image_display' );
	}
}
add_action( 'wp_update_nav_menu_item', 'st_wp_starter_toolkit_mega_menu_save_admin_field', 20, 2 );
