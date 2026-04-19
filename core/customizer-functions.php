<?php
/**
 * ST WP Starter Theme Customizer Functions
 *
 * Customizer feature implementations live in /core/customizer/*.php and load
 * from /core/customizer-runtime.php. This file remains as a compatibility
 * bridge for project-owned runtime overrides in /inc/customizer-functions.php.
 *
 * @package ST_WP_Core
 */

/**
 * /inc/ directory override core functions.
 *
 * This bridge is intentionally tiny. It keeps older mental models working while
 * the real runtime loader lives in customizer-runtime.php. Put project-owned
 * Customizer runtime overrides in /inc/customizer-functions.php.
 */
if ( file_exists( get_template_directory() . '/inc/customizer-functions.php' ) ) {
	require_once get_template_directory() . '/inc/customizer-functions.php';
}
