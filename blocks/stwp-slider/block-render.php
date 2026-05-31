<?php
/**
 * Block template file: block-render.php
 *
 * @var array $block The block settings and attributes.
 *
 * @var string $content The block inner HTML (empty).
 * @var bool $is_preview True during AJAX preview.
 * @var (int|string) $post_id The post ID this block is saved to.
 *
 * @package ST_WP_Starter
 */

// Derive the block namespace from theme patterns so generated themes can use a custom namespace.
$block_namespace = '';
if ( defined( 'ST_WP_CORE_THEME_PATTERNS' ) && is_array( ST_WP_CORE_THEME_PATTERNS ) && ! empty( ST_WP_CORE_THEME_PATTERNS['block_namespace'] ) ) {
	$block_namespace = (string) ST_WP_CORE_THEME_PATTERNS['block_namespace'];
}

$registered_block_name = isset( $block['name'] ) ? (string) $block['name'] : '';
$block_name_parts      = explode( '/', $registered_block_name, 2 );

if ( 2 === count( $block_name_parts ) ) {
	$block_namespace = $block_namespace ? $block_namespace : $block_name_parts[0];
	$block_name      = $block_name_parts[1];
} else {
	$block_namespace = $block_namespace ? $block_namespace : sanitize_key( get_template() );
	$block_name      = $registered_block_name;
}

// Create id attribute allowing for custom "anchor" value.
$block_id = $block_name . '-section-' . $block['id'];

if ( ! empty( $block['anchor'] ) ) {
	$block_id = $block['anchor'];
}

// Create class attribute allowing for custom "className" and "align" values.
$classes = "{$block_name}-section builder-section";
if ( ! empty( $block['className'] ) ) {
	$classes .= ' ' . $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$classes .= ' align' . $block['align'];
}

// ACF Fields.
$fields_group   = "{$block_namespace}-{$block_name}-fields";
$settings_group = "{$block_namespace}-{$block_name}-settings";
$fields         = get_field( $fields_group );
$settings       = get_field( $settings_group );
$fields         = is_array( $fields ) ? $fields : array();
$settings       = is_array( $settings ) ? $settings : array();
$style          = sanitize_key( (string) ( $settings[ "{$settings_group}__style" ] ?? 'horizontal' ) );
if ( ! in_array( $style, array( 'horizontal', 'vertical' ), true ) ) {
	$style = 'horizontal';
}

$items = $fields[ "{$fields_group}-items" ] ?? array();
$items = is_array( $items ) ? $items : array();

$classes .= " {$block_name}-section--{$style}";
// Keep Splide options on the block markup so every instance can use its own settings.
$splide_classes = "splide {$block_name}-section-splide enter-view";
$splide_options = array(
	'type'       => 'loop',
	'perPage'    => 1,
	'perMove'    => 1,
	'focus'      => 'center',
	'rewind'     => false,
	'pagination' => false,
	'arrows'     => true,
);

if ( 'vertical' === $style ) {
	$splide_options['direction'] = 'ttb';
	$splide_options['height']    = '500px';
}

$splide_options_json = wp_json_encode( $splide_options );
$splide_options_json = $splide_options_json ? $splide_options_json : '{}';

// Do something for Editor Preview only.
if ( $is_preview ) {
	$test = 'something...';
}

// Image used for the block preview.
if ( isset( $block['data']['preview_image_help'] ) ) :
	$file_url = str_replace( esc_url( get_stylesheet_directory() ), '', __DIR__, ); ?>
	<img src="
	<?php
	echo esc_url( get_stylesheet_directory_uri() )
	. esc_url( $file_url ) . '/'
	. wp_kses_post( $block['data']['preview_image_help'] )
	?>
	" style="width:100%; height:auto;" alt="preview_image_help"/>
	<?php
else :
	$wrapper_attributes = st_wp_core_get_block_wrapper_attributes(
		array(
			'id'    => $block_id,
			'class' => $classes,
		)
	);
	?>
	<section <?php echo wp_kses_post( $wrapper_attributes ); ?>>
		<div class="container">
			<div class="<?php echo esc_attr( $block_name ); ?>-section-wrapper">
				<div id="splide-<?php echo esc_attr( $block_id ); ?>"
					class="<?php echo esc_attr( $splide_classes ); ?>"
					data-splide="<?php echo esc_attr( $splide_options_json ); ?>">
					<div class="splide__track <?php echo esc_html( $block_name ); ?>-section-splide__track">
						<ul class="splide__list <?php echo esc_html( $block_name ); ?>-section-splide__list">
							<?php
							foreach ( $items as $key => $item ) :
								$item_title = $item[ "{$fields_group}-item__title" ] ?? null;
								$item_image = $item[ "{$fields_group}-item__image" ] ?? null;
								?>
								<li class="splide__slide <?php echo esc_html( $block_name ); ?>-section-splide__slide">
									<?php
									if ( $item_title ) :
										?>
										<h4 class="<?php echo esc_html( $block_name ); ?>-section-splide__title animate__animated opacity-0"
											data-animate-class="animate__fadeIn"
											style="animation-delay: <?php echo esc_html( 200 + ( 100 * $key ) ); ?>ms">
											<?php echo wp_kses_post( $item_title ); ?>
										</h4>
										<?php
									endif;
									?>

									<?php
									if ( $item_image ) :
										?>
										<div class="<?php echo esc_html( $block_name ); ?>-section-splide__image animate__animated opacity-0"
											data-animate-class="animate__fadeIn"
											style="animation-delay: <?php echo esc_html( 100 + ( 100 * $key ) ); ?>ms">
											<?php echo wp_kses_post( st_wp_core_generate_img( $item_image ) ); ?>
										</div>
										<?php
									endif;
									?>
								</li>
								<?php
							endforeach;
							?>
						</ul>
					</div>

					<div class="splide__arrows <?php echo esc_html( $block_name ); ?>-section-splide__arrows">
						<button class="splide__arrow <?php echo esc_html( $block_name ); ?>-section-splide__arrow splide__arrow--prev <?php echo esc_html( $block_name ); ?>-section-splide__arrow--prev animate__animated opacity-0" type="button" aria-label="<?php esc_html_e( 'Previous slide', 'st-wp-starter' ); ?>"
								data-animate-class="animate__fadeIn"
								style="animation-delay: 400ms"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" width="40" height="40" focusable="false"><path d="m15.5 0.932-4.3 4.38 14.5 14.6-14.5 14.5 4.3 4.4 14.6-14.6 4.4-4.3-4.4-4.4-14.6-14.6z"></path></svg></button>

						<button class="splide__arrow <?php echo esc_html( $block_name ); ?>-section-splide__arrow splide__arrow--next <?php echo esc_html( $block_name ); ?>-section-splide__arrow--next animate__animated opacity-0" type="button" aria-label="<?php esc_html_e( 'Next slide', 'st-wp-starter' ); ?>"
								data-animate-class="animate__fadeIn"
								style="animation-delay: 450ms"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" width="40" height="40" focusable="false"><path d="m15.5 0.932-4.3 4.38 14.5 14.6-14.5 14.5 4.3 4.4 14.6-14.6 4.4-4.3-4.4-4.4-14.6-14.6z"></path></svg></button>
					</div>
				</div>
			</div>
		</div>
	</section>
	<?php
endif; ?>
