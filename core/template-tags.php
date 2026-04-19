<?php
/**
 * Core template tags for this theme
 *
 * DO NOT MODIFY these functions - they are core utilities.
 * For custom template tags, see /inc/template-tags.php
 *
 * @package ST_WP_Core
 */

/**
 * /inc/ directory override core functions
 */
if ( file_exists( get_template_directory() . '/inc/template-tags.php' ) ) {
	require get_template_directory() . '/inc/template-tags.php';
}

if ( ! function_exists( 'st_wp_core_generate_img' ) ) :

	/**
	 * Function generates retina-ready image tags.
	 *  - Accepts either an attachment ID or ACF Image array.
	 *  - Uses array data to avoid extra DB calls when possible.
	 *
	 * @param int|array $attachment attachment ID or ACF Pro Image Array.
	 * @param string    $size attachment size.
	 * @param string    $fetch_priority image fetch priority instruction.
	 * @param string    $classes any extra classes.
	 *
	 * @return string
	 */
	function st_wp_core_generate_img( $attachment, $size = 'full', $fetch_priority = 'low', $classes = '' ): string {
		if ( is_array( $attachment ) && isset( $attachment['ID'] ) ) {
			// Extract ID from ACF image array.
			$attachment_id = $attachment['ID'];
		} else {
			// Otherwise assume it's a direct ID.
			$attachment_id = $attachment;
		}

		if ( ! $attachment_id ) {
			return '';
		}

		$image_src = wp_get_attachment_image_src( $attachment_id, $size );
		if ( ! $image_src ) {
			return '';
		}

		$alt         = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		$image_class = 'wp-image-' . $attachment_id;
		$url         = $image_src[0];
		$width       = $image_src[1];
		$height      = $image_src[2];

		// Build figure classes: base + size + custom classes.
		$figure_class = 'wp-block-image size-' . esc_attr( $size ) . ( $classes ? ' ' . esc_attr( $classes ) : '' );

		// SVGs are vector — WordPress never generates size variants for them.
		if ( get_post_mime_type( $attachment_id ) === 'image/svg+xml' ) {
			return sprintf(
				'<figure class="%s"><img fetchpriority="%s" decoding="async" src="%s" alt="%s" class="%s" /></figure>',
				esc_attr( $figure_class ),
				esc_attr( $fetch_priority ),
				esc_url( $url ),
				esc_attr( $alt ),
				esc_attr( $image_class )
			);
		}

		$srcset      = wp_get_attachment_image_srcset( $attachment_id, $size );
		$sizes       = wp_get_attachment_image_sizes( $attachment_id, $size );
		$srcset_attr = $srcset ? sprintf( ' srcset="%s" sizes="%s"', esc_attr( $srcset ), esc_attr( $sizes ) ) : '';

		return sprintf(
			'<figure class="%s"><img fetchpriority="%s" decoding="async" width="%d" height="%d" src="%s" alt="%s" class="%s"%s /></figure>',
			esc_attr( $figure_class ),
			esc_attr( $fetch_priority ),
			esc_attr( $width ),
			esc_attr( $height ),
			esc_url( $url ),
			esc_attr( $alt ),
			esc_attr( $image_class ),
			$srcset_attr
		);
	}
endif;

if ( ! function_exists( 'st_wp_core_generate_sprite_svg' ) ) :

	/**
	 *  Outputs or returns SVG Icon from set sprite
	 *
	 *  Can dynamically set sprite source used when called
	 *  sprite source has to be uploaded to /assets/images/icons/ folder
	 *
	 * @param string $sprite_name sprite icon name.
	 * @param int    $svg_width sprite icon width.
	 * @param int    $svg_height sprite icon height.
	 * @param string $sprite_source sprite source file.
	 * @param bool   $should_return Whether to return the SVG instead of echoing it. Default false.
	 * @param bool   $aria_hidden Whether to hide from screen readers (true for decorative icons).
	 * @param string $aria_label Accessible label for meaningful icons (used when aria_hidden is false).
	 * @param string $role ARIA role for meaningful icons (default: 'img').
	 *
	 * @return string|null Returns sanitized SVG HTML if $should_return is true, null otherwise.
	 *
	 * @throws Exception Throws error if sprite image directory is incorrect.
	 */
	function st_wp_core_generate_sprite_svg(
		string $sprite_name,
		int $svg_width = 24,
		int $svg_height = 24,
		string $sprite_source = '/assets/images/icons/icons.svg',
		bool $should_return = false,
		bool $aria_hidden = true,
		string $aria_label = '',
		string $role = 'img'
	): ?string {

		// Detect if $sprite_source contains '/images/'.
		if ( str_contains( $sprite_source, '/images/' ) ) {
			// Get the substring after '/images/'.
			$sprite_source = substr( $sprite_source, strpos( $sprite_source, '/assets/images/icons/' ) );
		} else {
			throw new Exception( 'Sprite Source Dir Incorrect! Upload to /assets/images/icons/' );
		}

		$svg = get_stylesheet_directory_uri() . '/' . $sprite_source . '?ver=' . filemtime( get_template_directory() . '/' . $sprite_source ) . '#' . $sprite_name;

		$svg_attributes = 'class="svg-icon ' . $sprite_name . '" width="' . $svg_width . '" height="' . $svg_height . '"';

		if ( $aria_hidden ) {
			$svg_attributes .= ' aria-hidden="true"';
		} elseif ( ! empty( $aria_label ) ) {
			$svg_attributes .= ' role="' . esc_attr( $role ) . '" aria-label="' . esc_attr( $aria_label ) . '"';
		}

		$icon_html = '<svg ' . $svg_attributes . '><use xlink:href="' . $svg . '"></use></svg>';

		// Define allowed attributes for SVG.
		$allowed_html = array(
			'svg' => array(
				'class'       => true,
				'width'       => true,
				'height'      => true,
				'aria-hidden' => true,
				'aria-label'  => true,
				'role'        => true,
			),
			'use' => array(
				'xlink:href' => true,
			),
		);

		if ( $should_return ) {
			return wp_kses( $icon_html, $allowed_html );
		}

		echo wp_kses( $icon_html, $allowed_html );
		return null;
	}
endif;
