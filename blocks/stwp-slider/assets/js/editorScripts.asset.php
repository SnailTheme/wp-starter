<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- WordPress requires the existing script's exact basename for its asset sidecar.
/**
 * Load Splide before the editor preview initializer, including iframe previews.
 *
 * @package ST_WP_Starter
 */

declare(strict_types=1);

return array(
	'dependencies' => array( 'acf-input', 'plugins.splidejs.splide' ),
	'version'      => filemtime( __DIR__ . '/editorScripts.js' ),
);
