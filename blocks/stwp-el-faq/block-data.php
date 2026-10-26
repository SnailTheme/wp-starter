<?php
/**
 * Read raw block fields without replacing the ambient ACF block/post context.
 *
 * Both the head provider and render template use this normalizer. WYSIWYG
 * formatting deliberately excludes acf_the_content, shortcodes and embeds.
 * Returning a closure keeps helpers local even when this block is installed
 * more than once under different destination slugs.
 *
 * @package ST_WP_Starter
 */

declare(strict_types=1);

return static function ( array $attributes ): array {
	$patterns       = defined( 'ST_WP_CORE_THEME_PATTERNS' ) ? ST_WP_CORE_THEME_PATTERNS : array();
	$name           = (string) ( $attributes['name'] ?? ( $patterns['block_namespace'] ?? 'stwp' ) . '/el-faq' );
	$root           = str_replace( '/', '-', $name );
	$fields_group   = $root . '-fields';
	$settings_group = $root . '-settings';
	$meta_id        = 'stwp_el_faq_' . wp_generate_uuid4();
	$data           = is_array( $attributes['data'] ?? null ) ? $attributes['data'] : array();
	// Unformatted ACF group/repeater values are keyed by field keys, not names.
	// Resolve them from the installed definition (including deterministic keys)
	// without invoking WYSIWYG formatting filters.
	$named_values = static function ( array $values, array $definitions ) use ( &$named_values ): array {
		$result = array();
		foreach ( $definitions as $field ) {
			$value = $values[ $field['key'] ] ?? $values[ $field['name'] ] ?? null;
			if ( 'group' === $field['type'] ) {
				$children = acf_get_fields( $field );
				$value    = $named_values( is_array( $value ) ? $value : array(), is_array( $children ) ? $children : array() );
			} elseif ( 'repeater' === $field['type'] && is_array( $value ) ) {
				$children = acf_get_fields( $field );
				$children = is_array( $children ) ? $children : array();
				$value    = array_map( static fn ( $row ): array => $named_values( is_array( $row ) ? $row : array(), $children ), $value );
			}
			$result[ $field['name'] ] = $value;
		}
		return $result;
	};
	acf_setup_meta( $data, $meta_id, false );
	try {
		$read     = static function ( string $name ) use ( $meta_id, $named_values ): array {
			$field    = get_field_object( $name, $meta_id, false, false );
			$value    = get_field( $name, $meta_id, false );
			$children = $field ? acf_get_fields( $field ) : array();
			return $field && is_array( $value ) ? $named_values( $value, is_array( $children ) ? $children : array() ) : array();
		};
		$fields   = $read( $fields_group );
		$settings = $read( $settings_group );
	} finally {
		acf_reset_meta( $meta_id );
	}
	$fields     = is_array( $fields ) ? $fields : array();
	$settings   = is_array( $settings ) ? $settings : array();
	$rows       = $fields[ $fields_group . '-items' ] ?? array();
	$rows       = is_array( $rows ) ? $rows : array();
	$items      = array();
	$incomplete = 0;
	$allowed    = array(
		'p'                                               => array(),
		'br'                                              => array(),
		'strong'                                          => array(),
		'b'                                               => array(),
		'em'                                              => array(),
		'i'                                               => array(),
		'u'                                               => array(),
		's'                                               => array(),
		'ul'                                              => array(),
		'ol'                                              => array( 'start' => true ),
		'li'                                              => array(),
		'blockquote'                                      => array(),
		'a'                                               => array(
			'href'  => true,
			'title' => true,
		),
		'code'                                            => array(),
	);
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			++$incomplete;
			continue;
		}
		$question = $row[ $fields_group . '-item__question' ] ?? '';
		$answer   = $row[ $fields_group . '-item__answer' ] ?? '';
		// Questions are plain text: preserve literal tags and entity notation.
		$question = is_string( $question ) ? $question : '';
		$question = trim( (string) preg_replace( '/[\s\p{Z}]+/u', ' ', $question ) );
		$answer   = is_string( $answer ) ? trim( wp_kses( wpautop( $answer ), $allowed ) ) : '';
		// Separate block boundaries before stripping tags so paragraphs and list
		// items remain separate words in the structured answer as well.
		$text = preg_replace( '#<(?:br\b[^>]*|/(?:p|li|ul|ol|blockquote))>#i', ' ', $answer );
		$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', str_replace( "\xc2\xa0", ' ', $text ) ) );
		if ( '' === $question || '' === $text ) {
			++$incomplete;
			continue;
		}
		$items[] = array(
			'question' => $question,
			'answer'   => $answer,
			'text'     => $text,
		);
	}
	$enabled = static fn ( $value ): bool => in_array( $value, array( true, 1, '1' ), true );
	return array(
		'root'       => $root,
		'items'      => $items,
		'incomplete' => $incomplete,
		'schema'     => $enabled( $settings[ $settings_group . '__schema_enabled' ] ?? true ),
		'first_open' => $enabled( $settings[ $settings_group . '__first_open' ] ?? true ),
	);
};
