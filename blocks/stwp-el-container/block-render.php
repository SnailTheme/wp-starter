<?php
/**
 * Elemental container.
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
$block_name = 'el-container';
if ( 2 === count( $parts ) && '' !== $parts[0] && '' !== $parts[1] ) {
	$namespace  = sanitize_key( $parts[0] );
	$block_name = sanitize_key( $parts[1] );
} elseif ( '' !== $parts[0] ) {
	$block_name = sanitize_key( $parts[0] );
}
$root           = "{$namespace}-{$block_name}";
$is_preview     = ! empty( $is_preview );
$settings_group = "{$namespace}-{$block_name}-settings";
$settings       = get_field( $settings_group );
$settings       = is_array( $settings ) ? $settings : array();

$choice       = static function ( $value, array $allowed, string $fallback ): string {
	return is_string( $value ) && in_array( $value, $allowed, true ) ? $value : $fallback;
};
$width        = $choice( $settings[ "{$settings_group}__width" ] ?? null, array( 'content', 'wide', 'full', 'custom' ), 'content' );
$position     = $choice( $settings[ "{$settings_group}__position" ] ?? null, array( 'start', 'center', 'end' ), 'center' );
$custom_width = $settings[ "{$settings_group}__custom_width" ] ?? '';
// Accept one finite positive CSS length, never expressions, declarations, or URLs.
$custom_width = is_string( $custom_width ) ? trim( $custom_width ) : '';
if ( ! preg_match( '/^(?:\\d+(?:\\.\\d+)?|\\.\\d+)(?:px|rem|em|ch|%|vw)$/D', $custom_width ) || ! is_finite( (float) $custom_width ) || (float) $custom_width <= 0 ) {
	$custom_width = '60rem';
}
$classes        = "{$root} {$root}--width-{$width} {$root}--position-{$position}";
$style          = 'custom' === $width ? "--{$root}-custom-width:{$custom_width};" : '';
$allowed_blocks = array_map(
	static fn ( string $name ): string => "{$namespace}/{$name}",
	array( 'el-container', 'el-heading', 'el-text', 'el-columns', 'el-grid', 'el-content-with-image' )
);
$template       = array( array( "{$namespace}/el-heading", array() ), array( "{$namespace}/el-text", array() ) );
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
	<InnerBlocks className="<?php echo esc_attr( $root ); ?>__inner" allowedBlocks="<?php echo esc_attr( wp_json_encode( $allowed_blocks ) ); ?>" template="<?php echo esc_attr( wp_json_encode( $template ) ); ?>" templateLock="false" />
</div>
