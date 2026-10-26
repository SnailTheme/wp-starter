<?php
/**
 * Head-only structured data, independent of any SEO plugin's graph.
 *
 * Register breadcrumbs before get_header(). Blocks register early providers in
 * block-bootstrap.php; providers inspect parsed attributes, never render HTML.
 * Project overrides in /inc/schema.php load before these guarded defaults.
 *
 * @package ST_WP_Core
 * @since 1.2.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( is_file( get_template_directory() . '/inc/schema.php' ) ) {
	require_once get_template_directory() . '/inc/schema.php';
}

if ( ! function_exists( 'st_wp_core_schema_state' ) ) {
	/**
	 * Return request-local state. Internal API, not persistent theme settings.
	 *
	 * @since 1.2.0
	 * @return array Registry by reference.
	 */
	function &st_wp_core_schema_state(): array {
		static $state = array(
			'nodes'       => array(),
			'faq'         => array(),
			'providers'   => array(),
			'collected'   => false,
			'flushed'     => false,
		);
		return $state;
	}
}

if ( ! function_exists( 'st_wp_core_schema_enabled' ) ) {
	/**
	 * Whether this request may publish theme-owned structured data.
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	function st_wp_core_schema_enabled(): bool {
		$post = is_singular() ? get_post( get_queried_object_id() ) : null;
		if ( is_admin() || is_feed() || is_preview()
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			|| ( function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request() )
			|| ( $post && ( ! empty( $post->post_password ) || post_password_required( $post ) ) ) ) {
			return false;
		}
		/**
		 * Disable only this theme's graph; SEO-plugin output is not modified.
		 *
		 * @since 1.2.0
		 * @param bool $enabled Whether theme schema output is enabled.
		 */
		return (bool) apply_filters( 'st_wp_core_schema_enabled', true );
	}
}

if ( ! function_exists( 'st_wp_core_schema_type_enabled' ) ) {
	/**
	 * Check the per-type output switch.
	 *
	 * @since 1.2.0
	 * @param string $type Schema type, such as FAQPage or BreadcrumbList.
	 * @return bool
	 */
	function st_wp_core_schema_type_enabled( string $type ): bool {
		/**
		 * Disable a theme schema type without affecting other publishers.
		 *
		 * @since 1.2.0
		 * @param bool   $enabled Whether this type is enabled.
		 * @param string $type    Schema type.
		 */
		return (bool) apply_filters( 'st_wp_core_schema_type_enabled', true, $type );
	}
}

if ( ! function_exists( 'st_wp_core_schema_canonical_url' ) ) {
	/**
	 * Use the queried singular permalink, never a request URL or preview URL.
	 *
	 * @since 1.2.0
	 * @return string Canonical URL, or an empty string when unavailable.
	 */
	function st_wp_core_schema_canonical_url(): string {
		if ( ! is_singular() ) {
			return '';
		}
		$url = get_permalink( get_queried_object_id() );
		return is_string( $url ) ? st_wp_core_schema_url( $url ) : '';
	}
}

if ( ! function_exists( 'st_wp_core_schema_url' ) ) {
	/**
	 * Validate an absolute HTTP(S) URL without making network requests.
	 *
	 * @since 1.2.0
	 * @param string $url Candidate URL.
	 * @return string
	 */
	function st_wp_core_schema_url( string $url ): string {
		if ( ! preg_match( '#^https?://#i', trim( $url ) ) ) {
			return '';
		}
		$url   = esc_url_raw( trim( $url ), array( 'http', 'https' ) );
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] )
			|| ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true )
			|| isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return '';
		}
		return $url;
	}
}

if ( ! function_exists( 'st_wp_core_schema_text' ) ) {
	/**
	 * Convert rich HTML to visible text without content filters or shortcodes.
	 *
	 * @since 1.2.0
	 * @param string $text Rich HTML, including escaped literal text.
	 * @return string
	 */
	function st_wp_core_schema_text( string $text ): string {
		$text = preg_replace( '#<(?:br\b[^>]*|/(?:p|div|li|h[1-6]|blockquote|ul|ol))\s*>#i', ' ', $text );
		$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return st_wp_core_schema_plain_text( $text );
	}
}

if ( ! function_exists( 'st_wp_core_schema_plain_text' ) ) {
	/**
	 * Normalize whitespace in already-plain text without reinterpreting literals.
	 *
	 * Do not strip tags or decode entities here: a question can visibly contain
	 * literal HTML examples, ampersands, or entity notation in the rendered block.
	 *
	 * @since 1.2.0
	 * @param string $text Plain text, not HTML-escaped output.
	 * @return string
	 */
	function st_wp_core_schema_plain_text( string $text ): string {
		return trim( (string) preg_replace( '/[\s\p{Z}]+/u', ' ', $text ) );
	}
}

if ( ! function_exists( 'st_wp_core_schema_valid_value' ) ) {
	/**
	 * Accept JSON-native values only, with bounded recursion for malformed input.
	 *
	 * @since 1.2.0
	 * @param mixed $value Value to inspect.
	 * @param int   $depth Current recursion depth.
	 * @return bool
	 */
	function st_wp_core_schema_valid_value( $value, int $depth = 0 ): bool {
		if ( $depth > 64 ) {
			return false;
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( ! st_wp_core_schema_valid_value( $item, $depth + 1 ) ) {
					return false;
				}
			}
			return true;
		}
		return null === $value || is_string( $value ) || is_int( $value ) || is_bool( $value )
			|| ( is_float( $value ) && is_finite( $value ) );
	}
}

if ( ! function_exists( 'st_wp_core_schema_valid_node' ) ) {
	/**
	 * Validate a standalone graph node and all of its declared type switches.
	 *
	 * @since 1.2.0
	 * @param array $node Schema node, without an outer @context or @graph.
	 * @return bool
	 */
	function st_wp_core_schema_valid_node( array $node ): bool {
		if ( empty( $node['@type'] ) || isset( $node['@graph'] ) || isset( $node['@context'] )
			|| ! st_wp_core_schema_valid_value( $node ) ) {
			return false;
		}
		$types = is_array( $node['@type'] ) ? $node['@type'] : array( $node['@type'] );
		if ( ! array_is_list( $types ) ) {
			return false;
		}
		foreach ( $types as $type ) {
			if ( ! is_string( $type ) || ! preg_match( '/^[A-Za-z][A-Za-z0-9]*$/D', $type )
				|| ! st_wp_core_schema_type_enabled( $type ) ) {
				return false;
			}
		}
		return false !== wp_json_encode( $node );
	}
}

if ( ! function_exists( 'st_wp_core_schema_diagnostic' ) ) {
	/**
	 * Explain rejected late calls to developers without printing fallback schema.
	 *
	 * @since 1.2.0
	 * @param string $caller Calling API.
	 * @return void
	 */
	function st_wp_core_schema_diagnostic( string $caller ): void {
		_doing_it_wrong(
			esc_html( $caller ),
			'Theme schema registration must complete before the head graph is printed. Register breadcrumbs before get_header() and block providers in block-bootstrap.php.',
			'1.2.0'
		);
	}
}

if ( ! function_exists( 'st_wp_core_schema_store' ) ) {
	/**
	 * Replace a contribution; identical late calls are harmless no-ops.
	 *
	 * @since 1.2.0
	 * @param string $bucket   Internal contribution bucket.
	 * @param string $key      Stable caller-owned contribution key.
	 * @param array  $value    Validated contribution.
	 * @param string $caller   Public API used by the caller.
	 * @return bool
	 */
	function st_wp_core_schema_store( string $bucket, string $key, array $value, string $caller ): bool {
		$state =& st_wp_core_schema_state();
		if ( isset( $state[ $bucket ][ $key ] ) && $state[ $bucket ][ $key ] === $value ) {
			return true;
		}
		if ( $state['flushed'] ) {
			st_wp_core_schema_diagnostic( $caller );
			return false;
		}
		$state[ $bucket ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'st_wp_core_schema_add' ) ) {
	/**
	 * Register or replace one node under a stable caller-owned key.
	 *
	 * @since 1.2.0
	 * @param array  $node Schema node; @context is supplied by the head renderer.
	 * @param string $key  Contribution key. Reusing it replaces the previous node.
	 * @return bool Whether the contribution was accepted.
	 */
	function st_wp_core_schema_add( array $node, string $key ): bool {
		if ( '' === trim( $key ) || ! st_wp_core_schema_enabled() || ! st_wp_core_schema_valid_node( $node ) ) {
			return false;
		}
		return st_wp_core_schema_store( 'nodes', 'node:' . $key, $node, __FUNCTION__ );
	}
}

if ( ! function_exists( 'st_wp_core_schema_add_breadcrumbs' ) ) {
	/**
	 * Register an explicitly supplied trail before get_header(), without markup.
	 *
	 * Invalid rows are skipped and positions are numbered consecutively. A later
	 * valid trail replaces the earlier one; no hierarchy is inferred automatically.
	 *
	 * @since 1.2.0
	 * @param array $items Ordered arrays containing string name and url values.
	 * @return bool Whether a complete breadcrumb contribution was accepted.
	 */
	function st_wp_core_schema_add_breadcrumbs( array $items ): bool {
		$url = st_wp_core_schema_canonical_url();
		if ( '' === $url || ! st_wp_core_schema_enabled() || ! st_wp_core_schema_type_enabled( 'BreadcrumbList' ) ) {
			return false;
		}
		$trail = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['name'], $item['url'] )
				|| ! is_string( $item['name'] ) || ! is_string( $item['url'] ) ) {
				continue;
			}
			$name = st_wp_core_schema_plain_text( $item['name'] );
			$link = st_wp_core_schema_url( $item['url'] );
			if ( '' === $name || '' === $link ) {
				continue;
			}
			$trail[] = array(
				'@type'    => 'ListItem',
				'position' => count( $trail ) + 1,
				'name'     => $name,
				'item'     => $link,
			);
		}
		if ( ! $trail ) {
			return false;
		}
		return st_wp_core_schema_store(
			'nodes',
			'breadcrumbs',
			array(
				'@type'           => 'BreadcrumbList',
				'@id'             => $url . '#breadcrumb',
				'itemListElement' => $trail,
			),
			__FUNCTION__
		);
	}
}

if ( ! function_exists( 'st_wp_core_schema_add_faq' ) ) {
	/**
	 * Register complete, visibly rendered question/answer pairs for one source.
	 *
	 * Answers become plain text without executing any filters or shortcodes.
	 * All accepted sources are combined into one canonical FAQPage at head time.
	 *
	 * @since 1.2.0
	 * @param array  $items      Ordered pairs: question is plain text; answer is rich HTML.
	 * @param string $source_key Stable occurrence key supplied to a block provider.
	 * @return bool Whether at least one complete pair was accepted.
	 */
	function st_wp_core_schema_add_faq( array $items, string $source_key ): bool {
		if ( '' === trim( $source_key ) || '' === st_wp_core_schema_canonical_url()
			|| ! st_wp_core_schema_enabled() || ! st_wp_core_schema_type_enabled( 'FAQPage' ) ) {
			return false;
		}
		$pairs = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['question'], $item['answer'] )
				|| ! is_string( $item['question'] ) || ! is_string( $item['answer'] ) ) {
				continue;
			}
			$question = st_wp_core_schema_plain_text( $item['question'] );
			$answer   = st_wp_core_schema_text( $item['answer'] );
			if ( '' !== $question && '' !== $answer ) {
				$pairs[] = array(
					'@type'          => 'Question',
					'name'           => $question,
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $answer,
					),
				);
			}
		}
		return $pairs ? st_wp_core_schema_store( 'faq', $source_key, $pairs, __FUNCTION__ ) : false;
	}
}

if ( ! function_exists( 'st_wp_core_schema_register_block_provider' ) ) {
	/**
	 * Register an early metadata collector from a trusted block-bootstrap.php.
	 *
	 * Contract: callable(array $parsed_block, int $post_id, string $source_key): void.
	 * Providers must not render blocks, execute shortcodes, or change WordPress/ACF
	 * ambient context. Use explicit raw data and call st_wp_core_schema_add_faq().
	 *
	 * @since 1.2.0
	 * @param string   $block_name Registered namespace/name.
	 * @param callable $provider   Metadata-only collector.
	 * @return void
	 */
	function st_wp_core_schema_register_block_provider( string $block_name, callable $provider ): void {
		$state =& st_wp_core_schema_state();
		if ( ! preg_match( '#^[a-z0-9][a-z0-9-]*/[a-z0-9][a-z0-9-]*$#D', $block_name ) ) {
			return;
		}
		if ( isset( $state['providers'][ $block_name ] ) && $state['providers'][ $block_name ] === $provider ) {
			return;
		}
		if ( $state['flushed'] ) {
			st_wp_core_schema_diagnostic( __FUNCTION__ );
			return;
		}
		$state['providers'][ $block_name ] = $provider;
	}
}

if ( ! function_exists( 'st_wp_core_schema_visible_viewports' ) ) {
	/**
	 * Restrict a branch to viewports where its registered blocks remain visible.
	 *
	 * Null means unrestricted; an empty array means no visible viewport remains.
	 * Match core's visibility filter without rendering blocks or storing CSS rules.
	 *
	 * @since 1.2.0
	 * @param array         $block     Parsed block with a validated name.
	 * @param string[]|null $viewports Ancestor visibility, or null when unrestricted.
	 * @return string[]|null Remaining viewports, or null when unrestricted.
	 */
	function st_wp_core_schema_visible_viewports( array $block, ?array $viewports ): ?array {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( $block['blockName'] );
		if ( ! $type ) {
			return $viewports;
		}
		$visibility = $block['attrs']['metadata']['blockVisibility'] ?? null;
		// Core honors explicit hiding even after the block disables visibility support.
		if ( false === $visibility ) {
			return array();
		}
		if ( ! block_has_support( $type, 'visibility', true ) || ! is_array( $visibility )
			|| empty( $visibility['viewport'] ) || ! is_array( $visibility['viewport'] ) ) {
			return $viewports;
		}
		$queries = WP_Theme_JSON::get_viewport_media_queries(
			wp_get_global_settings( array( 'viewport' ) ),
			array( 'include_desktop' => true )
		);
		$known   = array();
		$hidden  = array();
		foreach ( array_keys( $queries ) as $state ) {
			$viewport = ltrim( $state, '@' );
			$known[]  = $viewport;
			if ( false === ( $visibility['viewport'][ $viewport ] ?? null ) ) {
				$hidden[] = $viewport;
			}
		}
		return $hidden ? array_values( array_diff( $viewports ?? $known, $hidden ) ) : $viewports;
	}
}

if ( ! function_exists( 'st_wp_core_schema_synced_viewport_root' ) ) {
	/**
	 * Require a certified containing root before inheriting reference viewports.
	 *
	 * Synced patterns have no implicit HTML wrapper. Core applies their viewport
	 * classes to the first emitted tag, not every sibling. Do not predict dynamic
	 * output: uncertified or multi-root occurrences cannot safely publish schema.
	 *
	 * @since 1.2.0
	 * @param array $blocks    Parsed reference content.
	 * @param array $reference Parsed core/block occurrence.
	 * @param int   $post_id   Synced pattern ID.
	 * @return bool Whether a registered single root has a certified output contract.
	 */
	function st_wp_core_schema_synced_viewport_root( array $blocks, array $reference, int $post_id ): bool {
		$roots = array();
		foreach ( $blocks as $root ) {
			if ( is_array( $root ) && null === ( $root['blockName'] ?? null )
				&& isset( $root['innerHTML'] ) && is_string( $root['innerHTML'] )
				&& '' === trim( $root['innerHTML'] ) && empty( $root['innerBlocks'] ) ) {
				continue;
			}
			$roots[] = $root;
		}
		if ( 1 !== count( $roots ) || ! is_array( $roots[0] ) || ! is_string( $roots[0]['blockName'] ?? null )
			|| ! WP_Block_Type_Registry::get_instance()->get_registered( $roots[0]['blockName'] ) ) {
			return false;
		}
		/**
		 * Certify that the root emits one containing HTML element (or no content).
		 *
		 * Default false: providers/bootstrap may certify their known root contracts.
		 * Inspect metadata only; never render HTML, invoke providers, or store styles.
		 *
		 * @since 1.2.0
		 * @param bool  $certified Whether the root has the required output contract.
		 * @param array $root      Sole non-whitespace parsed root, registered in WordPress.
		 * @param array $reference Parsed core/block occurrence carrying the viewports.
		 * @param int   $post_id   Synced pattern ID.
		 */
		return true === apply_filters( 'st_wp_core_schema_synced_viewport_root', false, $roots[0], $reference, $post_id );
	}
}

if ( ! function_exists( 'st_wp_core_schema_walk_blocks' ) ) {
	/**
	 * Visit owned providers only, preserving occurrence order and synced cycles.
	 *
	 * @since 1.2.0
	 * @param array         $blocks    Parsed blocks, never rendered.
	 * @param int           $post_id   Content owner, including a synced pattern's wp_block ID.
	 * @param string        $path      Deterministic root/occurrence path.
	 * @param int[]         $refs      Synced ancestors on this branch, not global deduplication.
	 * @param int           $depth     Bounded recursion depth.
	 * @param string[]|null $viewports Remaining visible ancestor viewports.
	 * @return void
	 */
	function st_wp_core_schema_walk_blocks( array $blocks, int $post_id, string $path, array $refs = array(), int $depth = 0, ?array $viewports = null ): void {
		if ( $depth > 100 || array() === $viewports ) {
			return;
		}
		$state =& st_wp_core_schema_state();
		foreach ( $blocks as $index => $block ) {
			if ( ! is_array( $block ) || ! is_string( $block['blockName'] ?? null ) || 'core/query' === $block['blockName'] ) {
				continue;
			}
			$name       = $block['blockName'];
			$source_key = $path . '/' . $index;
			if ( 'core/block' === $name ) {
				$local_viewports = st_wp_core_schema_visible_viewports( $block, null );
				if ( array() === $local_viewports && false === ( $block['attrs']['metadata']['blockVisibility'] ?? null ) ) {
					continue;
				}
				$ref = $block['attrs']['ref'] ?? null;
				if ( ! is_int( $ref ) || $ref <= 0 || in_array( $ref, $refs, true ) ) {
					continue;
				}
				$synced = get_post( $ref );
				if ( ! $synced || 'wp_block' !== $synced->post_type || 'publish' !== $synced->post_status
					|| ! empty( $synced->post_password ) || post_password_required( $synced ) ) {
					continue;
				}
				$children = parse_blocks( $synced->post_content );
				if ( null !== $local_viewports && ! st_wp_core_schema_synced_viewport_root( $children, $block, $ref ) ) {
					_doing_it_wrong( __FUNCTION__, 'Viewport-conditioned synced pattern schema requires one certified outer block root. Wrap its content in a single root and certify it with st_wp_core_schema_synced_viewport_root; schema for this reference was skipped.', '1.2.0' );
					continue;
				}
				$block_viewports = null === $local_viewports ? $viewports : ( null === $viewports ? $local_viewports : array_values( array_intersect( $viewports, $local_viewports ) ) );
				st_wp_core_schema_walk_blocks( $children, $ref, $source_key . '/ref:' . $ref, array_merge( $refs, array( $ref ) ), $depth + 1, $block_viewports );
				continue;
			}
			$block_viewports = st_wp_core_schema_visible_viewports( $block, $viewports );
			if ( array() === $block_viewports ) {
				continue;
			}
			if ( isset( $state['providers'][ $name ] ) ) {
				// A failed provider cannot leave a partial contribution in the graph.
				$before_nodes = $state['nodes'];
				$before_faq   = $state['faq'];
				try {
					$state['providers'][ $name ]( $block, $post_id, $source_key );
				} catch ( Throwable $exception ) {
					$state['nodes'] = $before_nodes;
					$state['faq']   = $before_faq;
					_doing_it_wrong( __FUNCTION__, 'A theme schema block provider failed; its contribution was skipped.', '1.2.0' );
				}
			}
			if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				st_wp_core_schema_walk_blocks( $block['innerBlocks'], $post_id, $source_key, $refs, $depth + 1, $block_viewports );
			}
		}
	}
}

if ( ! function_exists( 'st_wp_core_schema_collect_blocks' ) ) {
	/**
	 * Collect the queried singular content once, without changing the global post.
	 *
	 * WordPress's generate_postdata() returns content-pagination data without
	 * setup_postdata(), rendering blocks, or applying the_content. Splitting the
	 * raw block tree ourselves would wrongly include FAQs from other pages, and
	 * would mishandle a nextpage boundary nested inside a layout block.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	function st_wp_core_schema_collect_blocks(): void {
		$state =& st_wp_core_schema_state();
		if ( $state['collected'] || $state['flushed'] || ! st_wp_core_schema_enabled() || ! is_singular() ) {
			return;
		}
		$state['collected'] = true;
		$post_id            = get_queried_object_id();
		$post               = get_post( $post_id );
		if ( $post && $state['providers'] ) {
			$elements = generate_postdata( $post );
			$pages    = is_array( $elements ) ? ( $elements['pages'] ?? array() ) : array();
			if ( ! is_array( $pages ) || ! array_is_list( $pages ) || ! $pages ) {
				return;
			}
			// Match get_the_content(): an excessive page selects the final page.
			$page    = max( 1, min( (int) ( $elements['page'] ?? 1 ), count( $pages ) ) );
			$content = $pages[ $page - 1 ];
			if ( ! is_string( $content ) ) {
				return;
			}
			$source = 'post:' . $post_id;
			if ( count( $pages ) > 1 ) {
				$source .= '/page:' . $page;
			}
			st_wp_core_schema_walk_blocks( parse_blocks( $content ), $post_id, $source, array( $post_id ) );
		}
	}
}

if ( ! function_exists( 'st_wp_core_schema_print' ) ) {
	/**
	 * Emit one safely encoded theme graph, late in wp_head and never in the footer.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	function st_wp_core_schema_print(): void {
		$state =& st_wp_core_schema_state();
		if ( $state['flushed'] ) {
			return;
		}
		st_wp_core_schema_collect_blocks();
		$state['flushed'] = true;
		if ( ! st_wp_core_schema_enabled() ) {
			return;
		}
		$graph = array_values( $state['nodes'] );
		if ( $state['faq'] && st_wp_core_schema_type_enabled( 'FAQPage' ) ) {
			$questions = array();
			$seen      = array();
			foreach ( $state['faq'] as $pairs ) {
				foreach ( $pairs as $pair ) {
					$fingerprint = wp_json_encode( $pair );
					if ( ! isset( $seen[ $fingerprint ] ) ) {
						$seen[ $fingerprint ] = true;
						$questions[]          = $pair;
					}
				}
			}
			$graph[] = array(
				'@type'      => 'FAQPage',
				'@id'        => st_wp_core_schema_canonical_url() . '#faq',
				'mainEntity' => $questions,
			);
		}
		/**
		 * Filter the final array of theme nodes, not another publisher's graph.
		 *
		 * The head is already closed for registration. Return an empty array to
		 * suppress output. Non-array, malformed, and disabled nodes are discarded.
		 *
		 * @since 1.2.0
		 * @param array $graph Final theme-owned graph nodes.
		 */
		$graph = apply_filters( 'st_wp_core_schema_graph', $graph );
		if ( ! is_array( $graph ) ) {
			return;
		}
		$graph = array_values( array_filter( $graph, static fn( $node ): bool => is_array( $node ) && st_wp_core_schema_valid_node( $node ) ) );
		if ( ! $graph ) {
			return;
		}
		$json = wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			),
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
		if ( false !== $json ) {
			// JSON hex encoding above prevents script termination and HTML injection.
			echo "\n" . '<script type="application/ld+json" id="st-wp-core-schema">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}

add_action( 'wp_head', 'st_wp_core_schema_print', PHP_INT_MAX );
