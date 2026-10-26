<?php
/**
 * Elemental heading.
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
$block_name = 'el-heading';
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

$choice         = static function ( $value, array $allowed, string $fallback ): string {
	return is_string( $value ) && in_array( $value, $allowed, true ) ? $value : $fallback;
};
$alignment      = $choice( $settings[ "{$settings_group}__alignment" ] ?? null, array( 'start', 'center', 'end' ), 'start' );
$eyebrow        = $fields[ "{$fields_group}__eyebrow" ] ?? '';
$eyebrow        = is_string( $eyebrow ) ? trim( $eyebrow ) : '';
$classes        = "{$root} {$root}--align-{$alignment}";
$style          = '';
$allowed_blocks = array( 'core/heading', 'core/paragraph' );
// Seed only new blocks. Saved native heading levels remain the author's choice.
$template = array( array( 'core/heading', array( 'level' => 2 ) ) );
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
	<?php if ( '' !== $eyebrow ) : ?>
		<p class="<?php echo esc_attr( $root ); ?>__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
	<?php endif; ?>
	<InnerBlocks className="<?php echo esc_attr( $root ); ?>__inner" allowedBlocks="<?php echo esc_attr( wp_json_encode( $allowed_blocks ) ); ?>" template="<?php echo esc_attr( wp_json_encode( $template ) ); ?>" templateLock="false" />
</div>
