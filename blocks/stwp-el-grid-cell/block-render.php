<?php
/**
 * Elemental grid cell.
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
$block_name = 'el-grid-cell';
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

$integer        = static function ( $value, int $min, int $max, int $fallback ): int {
	$validated = filter_var( $value, FILTER_VALIDATE_INT );
	return false === $validated ? $fallback : max( $min, min( $max, $validated ) );
};
$span           = $integer( $settings[ "{$settings_group}__span" ] ?? null, 1, 6, 1 );
$classes        = "{$root} {$root}--span-{$span}";
$style          = "--{$root}-span:{$span};";
$allowed_blocks = array_map(
	static fn ( string $name ): string => "{$namespace}/{$name}",
	array( 'el-container', 'el-heading', 'el-text', 'el-columns', 'el-grid', 'el-content-with-image' )
);
$template       = array( array( "{$namespace}/el-text", array() ) );
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
