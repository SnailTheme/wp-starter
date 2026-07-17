<?php
/**
 * Functions which enhance the theme by hooking into WordPress
 *
 * @package ST_WP_Core
 */

/**
 * /inc/ directory override core functions
 */
if ( file_exists( get_template_directory() . '/inc/template-functions.php' ) ) {
	require get_template_directory() . '/inc/template-functions.php';
}

if ( ! function_exists( 'st_wp_core_pingback_header' ) ) {
	/**
	 * Add a pingback url auto-discovery header for single posts, pages, or attachments.
	 *
	 * The newline before and after the tag keeps manually printed head markup from
	 * running into adjacent tags in the rendered source.
	 *
	 * @return void
	 */
	function st_wp_core_pingback_header(): void {
		if ( is_singular() && pings_open() ) {
			printf( "\n" . '<link rel="pingback" href="%s">' . "\n", esc_url( get_bloginfo( 'pingback_url' ) ) );
		}
	}
}
add_action( 'wp_head', 'st_wp_core_pingback_header' );

if ( ! function_exists( 'st_wp_core_allowed_html_head_tags' ) ) {
	/**
	 * Allowed tags for custom HTML in <head>.
	 *
	 * @return array
	 */
	function st_wp_core_allowed_html_head_tags(): array {
		return array(
			'script'   => array(
				'id'    => true,
				'type'  => true,
				'src'   => true,
				'async' => true,
				'defer' => true,
			),
			'style'    => array(
				'type'  => true,
				'media' => true,
			),
			'link'     => array(
				'rel'  => true,
				'href' => true,
				'type' => true,
			),
			'meta'     => array(
				'name'    => true,
				'content' => true,
				'charset' => true,
			),
			'noscript' => array(),
			'title'    => array(),
			'base'     => array(
				'href'   => true,
				'target' => true,
			),
		);
	}
}

if ( ! function_exists( 'st_wp_core_wp_kses_header_code' ) ) {
	/**
	 * Custom Sanitize for HTML `<head>`.
	 *
	 * @param string $input // Code to be sanitized.
	 * @return string
	 */
	function st_wp_core_wp_kses_header_code( string $input ): string {
		if ( current_user_can( 'unfiltered_html' ) ) {
			return $input;
		}
		return wp_kses( $input, st_wp_core_allowed_html_head_tags() );
	}
}

if ( ! function_exists( 'st_wp_core_get_block_wrapper_attributes' ) ) {
	/**
	 * Generate and return block wrapper attributes.
	 *
	 * @param string[] $extra_attributes Optional extra wrapper attributes.
	 *
	 * @return string
	 */
	function st_wp_core_get_block_wrapper_attributes( array $extra_attributes = array() ): string {
		return get_block_wrapper_attributes( $extra_attributes );
	}
}

if ( ! function_exists( 'st_wp_core_nav_menu_add_screen_option_columns' ) ) {
	/**
	 * Adds extra check-boxes to Appearance ▸ Menus ▸ Screen Options
	 * – Unlink (text with no anchor)
	 * – Image  (media uploader)
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	function st_wp_core_nav_menu_add_screen_option_columns( $cols ) {
		$cols['unlink'] = __( 'Unlink', 'st-wp-starter' );
		$cols['image']  = __( 'Image', 'st-wp-starter' );
		return $cols;
	}
}
if ( apply_filters( 'st_wp_core_enable_nav_menu_fields', true ) ) {
	add_filter( 'manage_nav-menus_columns', 'st_wp_core_nav_menu_add_screen_option_columns', 20 );
}

if ( ! function_exists( 'st_wp_core_nav_menu_custom_fields' ) ) {
	/**
	 * Output the extra controls inside each menu-item box
	 *
	 * @param int $item_id Menu item ID.
	 * @return void
	 */
	function st_wp_core_nav_menu_custom_fields( $item_id ) {
		// fetch saved values (if any).
		$unlink   = get_post_meta( $item_id, '_menu_item_unlink', true );
		$image_id = get_post_meta( $item_id, '_menu_item_image_id', true );
		$preview  = $image_id ? wp_get_attachment_image( $image_id, 'thumbnail' ) : '';

		/* ---------- Unlink (checkbox) ---------- */
		?>
		<p class="field-unlink description description-thin"><!-- class matches Screen-Option slug -->
			<label for="edit-menu-item-unlink-<?php echo esc_attr( $item_id ); ?>">
				<input type="checkbox"
						id="edit-menu-item-unlink-<?php echo esc_attr( $item_id ); ?>"
						name="menu-item-unlink[<?php echo esc_attr( $item_id ); ?>]"
						value="1" <?php checked( $unlink ); ?> />
				<?php esc_html_e( 'Unlink', 'st-wp-starter' ); ?>
			</label>
		</p>
		<?php

		/* ---------- Image uploader ---------- */
		$has_image = (bool) $image_id;
		?>
		<p class="field-image description description-wide">
			<label>
				<?php esc_html_e( 'Image', 'st-wp-starter' ); ?>
			</label><br>

			<!-- hidden ID -->
			<input type="hidden"
					class="menu-item-image-id"
					name="menu-item-image[<?php echo esc_attr( $item_id ); ?>]"
					value="<?php echo esc_attr( $image_id ); ?>"/>

			<!-- preview / actions -->
			<span class="menu-image-wrapper <?php echo $has_image ? 'has-image' : ''; ?>">
				<span class="menu-image-preview"><?php echo wp_kses_post( $preview ); ?></span>

				<button type="button"
						class="button button-small upload-menu-image">
					<?php
					echo esc_html(
						$has_image
							? __( 'Change', 'st-wp-starter' )
							: __( 'Set image', 'st-wp-starter' )
					);
					?>
				</button>

				<button type="button"
						class="button button-small remove-menu-image"
						style="<?php echo $has_image ? '' : 'display:none'; ?>">
					<?php esc_html_e( 'Remove', 'st-wp-starter' ); ?>
				</button>
			</span>
		</p>
		<?php
	}
}
if ( apply_filters( 'st_wp_core_enable_nav_menu_fields', true ) ) {
	add_action( 'wp_nav_menu_item_custom_fields', 'st_wp_core_nav_menu_custom_fields', 10, 2 );
}

if ( ! function_exists( 'st_wp_core_nav_menu_save_custom_fields' ) ) {
	/**
	 * Save the two custom fields when the menu is saved
	 *
	 * @param int $menu_id Menu ID.
	 * @param int $menu_item_db_id Menu item DB ID.
	 * @return void
	 */
	function st_wp_core_nav_menu_save_custom_fields( $menu_id, $menu_item_db_id ) {
		/*
		 * Programmatic menu updates do not submit the custom-fields form. Return
		 * before nonce validation so WP-CLI, imports, and integrations can use
		 * wp_update_nav_menu_item() without impersonating a wp-admin request.
		 * The image input is rendered for every item during a normal Save Menu
		 * request, so its array is a reliable signal that this form was submitted.
		 */
		if ( ! isset( $_POST['menu-item-image'] ) && ! isset( $_POST['menu-item-unlink'] ) ) {
			return;
		}

		/*
		 * If this is the full "Save Menu" form submission, the nonce we want
		 * **is present** and should be checked.
		 * During the AJAX 'add-menu-item' call the field is absent, so we
		 * just skip this block (core has already verified a different nonce).
		 */
		if ( wp_doing_ajax() ) {
			check_ajax_referer( 'add-menu_item', 'menu-settings-column-nonce' );
		} else {
			check_admin_referer( 'update-nav_menu', 'update-nav-menu-nonce' );
		}

		/* -------- Unlink checkbox -------- */
		if ( isset( $_POST['menu-item-unlink'][ $menu_item_db_id ] ) ) {
			update_post_meta( $menu_item_db_id, '_menu_item_unlink', 1 );
		} else {
			delete_post_meta( $menu_item_db_id, '_menu_item_unlink' );
		}

		/* -------- Image ID field --------- */
		if ( isset( $_POST['menu-item-image'][ $menu_item_db_id ] ) ) {
			$image_id = absint( $_POST['menu-item-image'][ $menu_item_db_id ] );

			if ( $image_id ) {
				update_post_meta( $menu_item_db_id, '_menu_item_image_id', $image_id );
			} else {
				delete_post_meta( $menu_item_db_id, '_menu_item_image_id' );
			}
		}
	}
}
if ( apply_filters( 'st_wp_core_enable_nav_menu_fields', true ) ) {
	add_action( 'wp_update_nav_menu_item', 'st_wp_core_nav_menu_save_custom_fields', 10, 2 );
}
