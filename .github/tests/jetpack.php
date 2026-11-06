#!/usr/bin/env php
<?php
/**
 * Standalone Jetpack integration regression. Run: php .github/tests/jetpack.php
 *
 * WordPress stubs below cover only the calls used by the integration. Template
 * IDs are read from their actual inline HTML, without booting WordPress/Jetpack.
 * Requires PHP's DOM extension; no Composer dependencies or database are used.
 *
 * @package ST_WP_Starter
 */

declare(strict_types=1);

$test_hooks    = array();
$test_support  = array();
$test_enabled  = true;
$test_posts    = array();
$test_position = 0;
$test_search   = false;
$test_parts    = array();
$test_failures = 0;
$test_checks   = 0;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress stubs must retain the API names called by the integration.
/**
 * Record a WordPress action using the integration's default priority.
 *
 * @param string $hook     Action name.
 * @param string $callback Callback function name.
 * @return void
 */
function add_action( string $hook, string $callback ): void {
	$GLOBALS['test_hooks'][ $hook ][ $callback ] = $callback;
}

/**
 * Dispatch a recorded action without arguments, as used by this integration.
 *
 * @param string $hook Action name.
 * @return void
 */
function do_action( string $hook ): void {
	foreach ( $GLOBALS['test_hooks'][ $hook ] ?? array() as $callback ) {
		$callback();
	}
}

/**
 * Supply the fixture's Jetpack opt-out value or leave other filters unchanged.
 *
 * @param string $hook  Filter name.
 * @param bool   $value Unfiltered value.
 * @return bool Filtered value.
 */
function apply_filters( string $hook, bool $value ): bool {
	return 'st_wp_core_enable_jetpack' === $hook ? $GLOBALS['test_enabled'] : $value;
}

/**
 * Locate the theme from this standalone test, independently of the working directory.
 *
 * @return string Absolute theme directory.
 */
function get_template_directory(): string {
	return dirname( __DIR__, 2 );
}

/**
 * Capture theme support options without requiring WordPress.
 *
 * @param string $feature Feature name.
 * @param mixed  ...$args Optional feature configuration.
 * @return void
 */
function add_theme_support( string $feature, mixed ...$args ): void {
	$GLOBALS['test_support'][ $feature ] = $args[0] ?? true;
}

/**
 * Report whether the fixture has another post to render.
 *
 * @return bool Whether posts remain.
 */
function have_posts(): bool {
	return $GLOBALS['test_position'] < count( $GLOBALS['test_posts'] );
}

/**
 * Advance the fixture's loop position to the next post.
 *
 * @return void
 */
function the_post(): void {
	++$GLOBALS['test_position'];
}

/**
 * Return the query context selected by the current fixture.
 *
 * @return bool Whether this batch represents search results.
 */
function is_search(): bool {
	return $GLOBALS['test_search'];
}

/**
 * Return the type of the fixture post selected by the_post().
 *
 * @return string Current post type.
 */
function get_post_type(): string {
	return $GLOBALS['test_posts'][ $GLOBALS['test_position'] - 1 ];
}

/**
 * Record template requests instead of executing WordPress-dependent partials.
 *
 * @param string $slug Template part base path.
 * @param string $name Template variant.
 * @return void
 */
function get_template_part( string $slug, string $name ): void {
	$GLOBALS['test_parts'][] = array( $slug, $name );
}
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound

/**
 * Count a strict assertion and report mismatches to the CLI error stream.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Observed value.
 * @param string $message  Failure context.
 * @return void
 */
function st_wp_starter_test_same( mixed $expected, mixed $actual, string $message ): void {
	++$GLOBALS['test_checks'];
	if ( $expected !== $actual ) {
		++$GLOBALS['test_failures'];
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Standalone CLI assertion diagnostics need native streams and literal value dumps, not WordPress APIs.
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL . 'Expected: ' . var_export( $expected, true ) . PHP_EOL . 'Actual: ' . var_export( $actual, true ) . PHP_EOL );
	}
}

/**
 * Read literal template markup without interpreting embedded PHP as HTML.
 *
 * @param string $file Template path relative to the theme root.
 * @return DOMDocument Parsed literal markup.
 * @throws RuntimeException If the template cannot be read.
 */
function st_wp_starter_test_template_dom( string $file ): DOMDocument {
	$source = file_get_contents( get_template_directory() . '/' . $file );
	if ( false === $source ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI-only diagnostic uses a test-owned filename, not HTML output.
		throw new RuntimeException( 'Cannot read template: ' . $file );
	}
	$html = '';
	foreach ( token_get_all( $source ) as $token ) {
		if ( is_array( $token ) && T_INLINE_HTML === $token[0] ) {
			$html .= $token[1];
		}
	}

	$previous = libxml_use_internal_errors( true );
	try {
		$dom = new DOMDocument();
		$dom->loadHTML( $html, LIBXML_NONET );
		return $dom;
	} finally {
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
	}
}

// Exercise the real core bridge, including its inactive-plugin and opt-out gates.
$test_bridge = get_template_directory() . '/core/jetpack.php';
require $test_bridge;
st_wp_starter_test_same( array(), $test_hooks, 'Inactive Jetpack must not register hooks.' );
st_wp_starter_test_same( false, function_exists( 'st_wp_starter_jetpack_setup' ), 'Inactive Jetpack must not load theme callbacks.' );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- The real core bridge checks this exact Jetpack plugin constant.
define( 'JETPACK__VERSION', 'test-stub' );
$test_enabled = false;
require $test_bridge;
do_action( 'after_setup_theme' );
st_wp_starter_test_same( array(), $test_support, 'Disabled integration must not register support.' );
st_wp_starter_test_same( array(), $test_hooks['after_setup_theme'] ?? array(), 'Disabled integration must not schedule setup.' );

$test_enabled = true;
require $test_bridge;
st_wp_starter_test_same(
	array( 'st_wp_starter_register_jetpack_hooks' => 'st_wp_starter_register_jetpack_hooks' ),
	$test_hooks['st_wp_core_jetpack_loaded'] ?? array(),
	'Theme hook must listen to the core Jetpack signal.'
);
st_wp_starter_test_same(
	array( 'st_wp_starter_jetpack_setup' => 'st_wp_starter_jetpack_setup' ),
	$test_hooks['after_setup_theme'] ?? array(),
	'Enabled integration must schedule theme setup.'
);
st_wp_starter_test_same( array(), $test_support, 'Theme support must wait for after_setup_theme.' );
do_action( 'after_setup_theme' );

$test_scroll = $test_support['infinite-scroll'] ?? array();
st_wp_starter_test_same( 'st_wp_starter_infinite_scroll_render', $test_scroll['render'] ?? null, 'Registered render callback must remain unchanged.' );
st_wp_starter_test_same( true, is_callable( $test_scroll['render'] ?? null ), 'Registered render callback must be callable.' );
st_wp_starter_test_same( true, $test_support['jetpack-responsive-videos'] ?? null, 'Responsive Videos support must remain enabled.' );
st_wp_starter_test_same( 'st-wp-starter-style', $test_support['jetpack-content-options']['post-details']['stylesheet'] ?? null, 'Content Options stylesheet must remain unchanged.' );

foreach ( array( 'index.php', 'archive.php', 'search.php' ) as $test_template ) {
	$test_dom   = st_wp_starter_test_template_dom( $test_template );
	$test_mains = $test_dom->getElementsByTagName( 'main' );
	st_wp_starter_test_same( 1, $test_mains->length, $test_template . ' must have one main loop container.' );
	st_wp_starter_test_same( $test_mains->item( 0 )?->getAttribute( 'id' ), $test_scroll['container'] ?? null, $test_template . ' loop ID must match the registered Infinite Scroll container.' );
}

$test_header = new DOMXPath( st_wp_starter_test_template_dom( 'header.php' ) );
$test_skip   = $test_header->query( '//a[contains(concat(" ", normalize-space(@class), " "), " skip-link ")]' );
st_wp_starter_test_same( 1, $test_skip->length, 'Header must have one skip-link.' );
st_wp_starter_test_same( $test_skip->item( 0 )?->getAttribute( 'href' ), '#' . ( $test_scroll['container'] ?? '' ), 'Skip-link and Infinite Scroll must target the same container.' );
st_wp_starter_test_same( 'page', $test_scroll['footer'] ?? null, 'Infinite Scroll footer target must remain unchanged.' );
st_wp_starter_test_same( 1, $test_header->query( '//*[@id="page"]' )->length, 'Registered footer target must exist in header markup.' );

foreach (
	array(
		'normal' => array( false, array( 'post', 'book' ), array( 'post', 'book' ) ),
		'search' => array( true, array( 'post', 'page' ), array( 'search', 'search' ) ),
		'empty'  => array( false, array(), array() ),
	) as $test_name => $test_case
) {
	list( $test_search, $test_posts, $test_expected_names ) = $test_case;
	$test_position = 0;
	$test_parts    = array();
	$test_expected = array();
	foreach ( $test_expected_names as $test_part_name ) {
		$test_expected[] = array( 'template-parts/content', $test_part_name );
	}

	if ( is_callable( $test_scroll['render'] ?? null ) ) {
		call_user_func( $test_scroll['render'] );
	}
	st_wp_starter_test_same( count( $test_posts ), $test_position, $test_name . ' batch must advance every post.' );
	st_wp_starter_test_same( $test_expected, $test_parts, $test_name . ' batch must request the correct template parts in order.' );
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Standalone CLI summary writes to STDOUT without booting WordPress.
fwrite( STDOUT, sprintf( '%d checks, %d failures.%s', $test_checks, $test_failures, PHP_EOL ) );
exit( 0 === $test_failures ? 0 : 1 );
