<?php
/**
 * Register the FAQ's head-only provider after this block is registered.
 *
 * @var WP_Block_Type $block_type Successfully registered type supplied by core.
 * @package ST_WP_Starter
 */

declare(strict_types=1);

$normalize = require __DIR__ . '/block-data.php';
$namespace = explode( '/', $block_type->name, 2 )[0];
$roots     = array( $block_type->name, $namespace . '/el-wrapper', $namespace . '/el-container' );

// These renderers produce one outer root (or no FAQ output), never siblings.
// Certify only their names; arbitrary dynamic output cannot be predicted early.
add_filter(
	'st_wp_core_schema_synced_viewport_root',
	static fn( bool $supported, array $root ): bool => $supported || in_array( $root['blockName'] ?? null, $roots, true ),
	10,
	2
);

st_wp_core_schema_register_block_provider(
	$block_type->name,
	static function ( array $parsed_block, int $post_id, string $source_key ) use ( $normalize ): void {
		$attributes         = $parsed_block['attrs'] ?? array();
		$attributes['name'] = $parsed_block['blockName'];
		$faq                = $normalize( $attributes );
		if ( $faq['schema'] ) {
			$items = array_map(
				static fn ( array $item ): array => array(
					'question' => $item['question'],
					'answer'   => $item['answer'],
				),
				$faq['items']
			);
			st_wp_core_schema_add_faq( $items, $source_key );
		}
	}
);
