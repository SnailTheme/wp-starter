<?php
/**
 * Toolkit component loader.
 *
 * Reusable components are selected per project, so their integrations live in
 * `/inc/components/`. The discovery mechanism itself is shared core behavior:
 * keeping it here lets the toolkit add or improve component loading through a
 * normal core update without placing infrastructure in the project-owned
 * `/inc/` layer.
 *
 * Disable this module through the `st_wp_core_files` filter, change the source
 * directory with `st_wp_core_components_directory`, or filter the final ordered
 * file list with `st_wp_core_component_files`.
 *
 * @package ST_WP_Core
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'st_wp_core_load_components' ) ) {
	/**
	 * Load project-selected component integrations in deterministic order.
	 *
	 * Component files may register hooks immediately because this module loads
	 * after the core setup, script, and template helper modules. An empty or
	 * missing component directory is a valid state for a clean starter theme.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	function st_wp_core_load_components(): void {
		/**
		 * Filters the directory containing project component integrations.
		 *
		 * Return an empty string to disable component discovery without filtering
		 * the complete core module registry.
		 *
		 * @param string $directory Absolute component directory path.
		 *
		 * @since 1.1.0
		 */
		$directory = apply_filters(
			'st_wp_core_components_directory',
			get_template_directory() . '/inc/components'
		);

		if ( ! is_string( $directory ) || '' === trim( $directory ) || ! is_dir( $directory ) ) {
			return;
		}

		$component_files = array();
		$directory_files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator(
				$directory,
				FilesystemIterator::SKIP_DOTS
			)
		);

		foreach ( $directory_files as $directory_file ) {
			if ( $directory_file->isFile() && 'php' === strtolower( $directory_file->getExtension() ) ) {
				$component_files[] = $directory_file->getPathname();
			}
		}

		/**
		 * Filters component PHP files before core loads them.
		 *
		 * Paths must resolve to PHP files inside the selected component directory.
		 * This containment check prevents an accidental filter value from loading
		 * arbitrary files elsewhere on the filesystem.
		 *
		 * @param string[] $component_files Absolute component file paths.
		 * @param string   $directory       Absolute component directory path.
		 *
		 * @since 1.1.0
		 */
		$filtered_files = apply_filters( 'st_wp_core_component_files', $component_files, $directory );

		if ( is_array( $filtered_files ) ) {
			$component_files = $filtered_files;
		}

		sort( $component_files, SORT_STRING );

		$component_root = realpath( $directory );

		if ( false === $component_root ) {
			return;
		}

		foreach ( $component_files as $component_file ) {
			if ( ! is_string( $component_file ) || 'php' !== strtolower( (string) pathinfo( $component_file, PATHINFO_EXTENSION ) ) ) {
				continue;
			}

			$resolved_file = realpath( $component_file );

			if (
				false === $resolved_file
				|| ! is_file( $resolved_file )
				|| strpos( $resolved_file, $component_root . DIRECTORY_SEPARATOR ) !== 0
			) {
				continue;
			}

			require_once $resolved_file;
		}
	}
}

st_wp_core_load_components();
