<?php
/**
 * Elemental FAQ: native exclusive disclosures, with no JavaScript dependency.
 *
 * @var array $block Block attributes.
 * @var bool $is_preview Editor preview state.
 * @package ST_WP_Starter
 */

declare(strict_types=1);

$normalize  = require __DIR__ . '/block-data.php';
$faq        = $normalize( $block );
$root       = $faq['root'];
$is_preview = ! empty( $is_preview );
// ACF replaces its cached block ID with the editor client ID in each preview.
// Keep that ID in the group so cached identical blocks stay isolated, and add
// a UUID for repeated frontend/synced renders where ACF IDs can be identical.
$group_name         = $root . '-' . (string) ( $block['id'] ?? '' ) . '-' . wp_generate_uuid4();
$wrapper_attributes = $is_preview
	? 'class="' . esc_attr( $root ) . '"'
	: st_wp_core_get_block_wrapper_attributes( array( 'class' => $root ) );
if ( array() === $faq['items'] && ! $is_preview ) {
	return;
}
?>
<div <?php echo wp_kses_post( $wrapper_attributes ); ?>>
	<?php if ( $is_preview && ( $faq['incomplete'] > 0 || array() === $faq['items'] ) ) : ?>
		<p class="<?php echo esc_attr( $root ); ?>__notice"><?php esc_html_e( 'Add a question and answer to each FAQ item. Incomplete items are not published.', 'st-wp-starter' ); ?></p>
	<?php endif; ?>
	<?php foreach ( $faq['items'] as $index => $item ) : ?>
		<details class="<?php echo esc_attr( $root ); ?>__item" name="<?php echo esc_attr( $group_name ); ?>" <?php echo 0 === $index && $faq['first_open'] ? 'open="open"' : ''; ?>>
				<?php
				echo '<summary class="' . esc_attr( $root ) . '__question">' . htmlspecialchars( $item['question'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true ) . '</summary>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Double-encoding preserves literal entity text while escaping all HTML.
				?>
			<div class="<?php echo esc_attr( $root ); ?>__answer"><?php echo wp_kses_post( $item['answer'] ); ?></div>
		</details>
	<?php endforeach; ?>
</div>
