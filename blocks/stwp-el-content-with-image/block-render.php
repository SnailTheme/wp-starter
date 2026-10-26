<?php
/**
 * Elemental content with image.
 *
 * @var array $block Block attributes.
 * @var bool  $is_preview Editor preview state.
 * @package ST_WP_Starter
 */

declare(strict_types=1);

$patterns   = defined( 'ST_WP_CORE_THEME_PATTERNS' ) && is_array( ST_WP_CORE_THEME_PATTERNS ) ? ST_WP_CORE_THEME_PATTERNS : array();
$parts      = explode( '/', (string) ( $block['name'] ?? '' ), 2 );
$namespace  = sanitize_key( (string) ( $patterns['block_namespace'] ?? 'stwp' ) );
$namespace  = $namespace ? $namespace : 'stwp';
$block_name = 'el-content-with-image';
if ( 2 === count( $parts ) && '' !== $parts[0] && '' !== $parts[1] ) {
	$namespace  = sanitize_key( $parts[0] );
	$block_name = sanitize_key( $parts[1] );
} elseif ( '' !== $parts[0] ) {
	$block_name = sanitize_key( $parts[0] );
}
$root           = "{$namespace}-{$block_name}";
$is_preview     = ! empty( $is_preview );
$fields_group   = "{$namespace}-{$block_name}-fields";
$fields         = get_field( $fields_group );
$fields         = is_array( $fields ) ? $fields : array();
$settings_group = "{$namespace}-{$block_name}-settings";
$settings       = get_field( $settings_group );
$settings       = is_array( $settings ) ? $settings : array();

$choice    = static function ( $value, array $allowed, string $fallback ): string {
	return is_string( $value ) && in_array( $value, $allowed, true ) ? $value : $fallback;
};
$side      = $choice( $settings[ "{$settings_group}__image_position" ] ?? null, array( 'left', 'right' ), 'right' );
$alignment = $choice( $settings[ "{$settings_group}__alignment" ] ?? null, array( 'start', 'center', 'end' ), 'center' );
$gap       = $choice( $settings[ "{$settings_group}__gap" ] ?? null, array( 'none', 'small', 'medium', 'large' ), 'medium' );
$collapse  = $choice( $settings[ "{$settings_group}__collapse" ] ?? null, array( '40', '48', '64' ), '48' );
$image_id  = $fields[ "{$fields_group}__image" ] ?? 0;
$image_id  = is_scalar( $image_id ) ? absint( $image_id ) : 0;
$image     = $image_id ? st_wp_core_generate_img( $image_id, 'large', 'low', "{$root}__image" ) : '';
$classes   = "{$root} {$root}--image-{$side} {$root}--align-{$alignment} {$root}--gap-{$gap} {$root}--collapse-{$collapse}";
if ( '' !== $image || $is_preview ) {
	$classes .= " {$root}--has-media";
}
$style          = '';
$allowed_blocks = array( 'core/heading', 'core/paragraph', 'core/list', 'core/buttons', "{$namespace}/el-heading", "{$namespace}/el-text" );
$template       = array( array( 'core/heading', array( 'level' => 2 ) ), array( 'core/paragraph', array() ) );
// WordPress already applies native supports to the editor's outer block.
$wrapper_attributes = $is_preview
	? 'class="' . esc_attr( $classes ) . '" style="' . esc_attr( $style ) . '"'
	: st_wp_core_get_block_wrapper_attributes(
		array(
			'class' => $classes,
			'style' => $style,
		)
	);
?>
<div <?php echo wp_kses_post( $wrapper_attributes ); ?>>
	<div class="<?php echo esc_attr( $root ); ?>__layout">
		<div class="<?php echo esc_attr( $root ); ?>__content">
			<InnerBlocks className="<?php echo esc_attr( $root ); ?>__inner" allowedBlocks="<?php echo esc_attr( wp_json_encode( $allowed_blocks ) ); ?>" template="<?php echo esc_attr( wp_json_encode( $template ) ); ?>" templateLock="false" />
		</div>
		<?php if ( '' !== $image ) : ?>
			<div class="<?php echo esc_attr( $root ); ?>__media"><?php echo wp_kses_post( $image ); ?></div>
		<?php elseif ( $is_preview ) : ?>
			<div class="<?php echo esc_attr( $root ); ?>__media <?php echo esc_attr( $root ); ?>__placeholder">
				<?php esc_html_e( 'Choose an image in Fields.', 'st-wp-starter' ); ?>
			</div>
		<?php endif; ?>
	</div>
</div>
