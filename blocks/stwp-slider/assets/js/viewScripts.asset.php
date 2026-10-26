<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- WordPress requires the existing script's exact basename for its asset sidecar.
/**
 * Declare the frontend initializer's library dependency and source version.
 *
 * @package ST_WP_Starter
 */

declare(strict_types=1);

return array(
	'dependencies' => array( 'plugins.splidejs.splide' ),
	'version'      => filemtime( __DIR__ . '/viewScripts.js' ),
);
