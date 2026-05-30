<?php
/**
 * ST WP Starter core functions
 *
 * @package ST_WP_Core
 */

/**
 * /inc/ directory override core functions
 */
if ( file_exists( get_template_directory() . '/inc/core.php' ) ) {
	require get_template_directory() . '/inc/core.php';
}

if ( ! function_exists( 'st_wp_core_get_theme_version' ) ) {
	/**
	 * Return the active theme version for cache busting.
	 *
	 * @return string
	 */
	function st_wp_core_get_theme_version(): string {
		$theme   = wp_get_theme();
		$version = $theme->get( 'Version' );

		return $version ? $version : ST_WP_CORE_VERSION;
	}
}

if ( ! function_exists( 'st_wp_core_plugin_dependencies' ) ) {
	/**
	 * Theme plugin dependencies notice
	 *  List of required plugins and links to download them
	 *
	 * @return void
	 */
	function st_wp_core_plugin_dependencies(): void {
		// List of required plugins and their download links.
		$required_plugins = array(
			'advanced-custom-fields-pro/acf.php' => array(
				'name' => 'ACF Pro',
				'url'  => 'https://www.advancedcustomfields.com/pro/',
			),
			'acf-svg-icon/acf-svg-icon.php'      => array(
				'name' => 'ACF SVG Icon Field',
				'url'  => 'https://github.com/shoot56/acf-svg-icon',
			),
		);

		$inactive_plugins = array();

		foreach ( $required_plugins as $plugin_path => $plugin_info ) {
			if ( ! is_plugin_active( $plugin_path ) ) {
				$inactive_plugins[] = sprintf(
					'<strong><a href="%s" target="_blank">%s</a></strong>',
					esc_url( $plugin_info['url'] ),
					esc_html( $plugin_info['name'] )
				);
			}
		}

		if ( ! empty( $inactive_plugins ) ) {
			$plugin_list = implode( ', ', $inactive_plugins );

			echo '<div class="notice notice-error"><p>';

			printf(
			/* translators: %s is a list of plugin names with links */
				esc_html__( 'The following plugins are required by this theme: %s. Please install and activate them.', 'st-wp-starter' ),
				wp_kses_post( $plugin_list )
			);

			echo '</p></div>';
		}
	}
}
if ( apply_filters( 'st_wp_core_enable_plugin_dependency_notice', true ) ) {
	add_action( 'admin_notices', 'st_wp_core_plugin_dependencies' );
}


if ( ! function_exists( 'st_wp_core_plugins_suggestions' ) ) {
	/**
	 * Theme plugin suggestions notice
	 *  List of suggested plugins and links to download them
	 *
	 * @return void
	 */
	function st_wp_core_plugins_suggestions(): void {
		if ( get_user_meta( get_current_user_id(), 'st_wp_core_ignore_plugin_notice', true ) ) {
			return; // User has dismissed the notice.
		}

		// List of suggested plugins and their download links.
		$required_plugins = array(
			'index-wp-mysql-for-speed/index-wp-mysql-for-speed.php' => array(
				'name' => 'Index WP MySQL For Speed',
				'url'  => 'https://wordpress.org/plugins/index-wp-mysql-for-speed/',
			),
			'redis-cache/redis-cache.php' => array(
				'name' => 'Redis Object Cache',
				'url'  => 'https://wordpress.org/plugins/redis-cache/',
			),
			'regenerate-thumbnails/regenerate-thumbnails.php' => array(
				'name' => 'Regenerate Thumbnails',
				'url'  => 'https://wordpress.org/plugins/regenerate-thumbnails/',
			),
			'svg-support/svg-support.php' => array(
				'name' => 'SVG Support',
				'url'  => 'https://wordpress.org/plugins/svg-support/',
			),
			'webp-converter-for-media/webp-converter-for-media.php' => array(
				'name' => 'Converter for Media',
				'url'  => 'https://wordpress.org/plugins/webp-converter-for-media/',
			),
		);

		$inactive_plugins = array();

		foreach ( $required_plugins as $plugin_path => $plugin_info ) {
			if ( ! is_plugin_active( $plugin_path ) ) {
				$inactive_plugins[] = sprintf(
					'<strong><a href="%s" target="_blank">%s</a></strong>',
					esc_url( $plugin_info['url'] ),
					esc_html( $plugin_info['name'] )
				);
			}
		}

		if ( ! empty( $inactive_plugins ) ) {
			echo '<div class="notice notice-warning is-dismissible st-plugin-notice"><p>';
			echo esc_html__( 'The following plugins are advisable to use with this theme:', 'st-wp-starter' );
			echo '</p><ul>';

			foreach ( $inactive_plugins as $plugin_link ) {
				echo '<li>' . wp_kses_post( $plugin_link ) . '</li>';
			}

			echo '</ul>';
			echo '<p>' . esc_html__( 'Please install and activate them.', 'st-wp-starter' ) . '</p>';
			echo '<p><a href="#" class="st-dismiss-plugin-notice">' . esc_html__( 'Don\'t remind me', 'st-wp-starter' ) . '</a></p>';
			echo '</div>';
		}
	}
}
if ( apply_filters( 'st_wp_core_enable_plugin_suggestions_notice', true ) ) {
	add_action( 'admin_notices', 'st_wp_core_plugins_suggestions' );
}


if ( ! function_exists( 'st_wp_core_dismiss_plugin_notice' ) ) {
	/**
	 * Handle AJAX request to dismiss notice.
	 */
	function st_wp_core_dismiss_plugin_notice(): void {
		check_ajax_referer( 'st_wp_core_ignore_plugin_notice', 'nonce' );

		update_user_meta( get_current_user_id(), 'st_wp_core_ignore_plugin_notice', 1 );

		wp_send_json_success();
	}
}
if ( apply_filters( 'st_wp_core_enable_plugin_suggestions_notice', true ) ) {
	add_action( 'wp_ajax_st_wp_core_dismiss_plugin_notice', 'st_wp_core_dismiss_plugin_notice' );
}


if ( ! function_exists( 'st_wp_core_generate_asset_handle' ) ) {
	/**
	 * Function responsible for generating a clean file name
	 *  - used by st_wp_core_register_and_enqueue_assets()
	 *  - used by st_wp_core_register_block_editor_libraries()
	 *
	 * @param string $relative_path The relative file path to be cleaned.
	 * @return string The sanitized file name.
	 */
	function st_wp_core_generate_asset_handle( string $relative_path ): string {
		$handle = str_replace( array( '/', '\\' ), '.', $relative_path ); // Convert path separators to dots.

		$handle = preg_replace( '/\.min/', '', $handle ); // Remove ".min" from filename.

		$handle = preg_replace( '/\.(css|js)$/', '', $handle ); // Remove ".css" or ".js" extension.

		return trim( $handle, '.' ); // Remove any leading/trailing dots.
	}
}


if ( ! function_exists( 'st_wp_core_register_and_enqueue_assets' ) ) {
	/**
	 * Function will enqueue or register theme styles & scripts
	 *
	 *  1. will look for files in
	 *      - `\assets\css\styles-enqueue\`
	 *      - `\assets\css\styles-register\`
	 *      - `\assets\js\scripts-enqueue\`
	 *      - `\assets\js\scripts-register\`
	 *
	 *  2. will add handle to files based on their directory.
	 *      the filename will be a dot-notation of the file's directory and the file name. eg:
	 *      - source: `\assets\css\styles-enqueue\main.min.css`
	 *      - handle: `main`
	 *      - source: `\assets\css\styles-enqueue\plugins\splidejs\core.min.css`
	 *      - handle: `plugins.splidejs.core`
	 *      - source: `\assets\js\scripts-register\pages\archive.min.js`
	 *      - handle: `pages.archive`
	 *
	 *  3. will add the file timestamp as a file version
	 *
	 * @return void
	 */
	function st_wp_core_register_and_enqueue_assets(): void {
		// Use get_stylesheet_directory() for child themes.
		$base_dir = get_template_directory();

		$assets = array(
			'css' => array(
				'register' => '/assets/css/styles-register/',
				'enqueue'  => '/assets/css/styles-enqueue/',
			),
			'js'  => array(
				'register' => '/assets/js/scripts-register/',
				'enqueue'  => '/assets/js/scripts-enqueue/',
			),
		);

		foreach ( $assets as $type => $dirs ) {
			foreach ( $dirs as $action => $dir ) {
				$path = $base_dir . $dir;
				if ( ! is_dir( $path ) ) {
					continue;
				}

				$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path ) );

				foreach ( $files as $file ) {
					if ( $file->isFile() && pathinfo( $file, PATHINFO_EXTENSION ) === $type ) {
						$relative_path = str_replace( $path, '', $file->getPathname() );
						$handle        = st_wp_core_generate_asset_handle( $relative_path );
						$asset_url     = get_template_directory_uri() . $dir . $relative_path;
						$file_version  = filemtime( $file->getPathname() );

						if ( 'css' === $type ) {
							if ( 'register' === $action ) {
								wp_register_style( $handle, $asset_url, array(), $file_version );
							} else {
								wp_enqueue_style( $handle, $asset_url, array(), $file_version );
							}
						} elseif ( 'js' === $type ) {
							if ( 'register' === $action ) {
								wp_register_script( $handle, $asset_url, array(), $file_version, true );
							} else {
								wp_enqueue_script( $handle, $asset_url, array(), $file_version, true );
							}
						}
					}
				}
			}
		}
	}
}
// Hook into WordPress after the base theme stylesheet/script enqueue.
// Auto-enqueued assets are intended as project additions, so they should layer
// after style.css and main.min.css from /inc/scripts.php.
if ( apply_filters( 'st_wp_core_enable_asset_registry', true ) ) {
	add_action( 'wp_enqueue_scripts', 'st_wp_core_register_and_enqueue_assets', 20 );
}


if ( ! function_exists( 'st_wp_core_register_block_editor_libraries' ) ) {
	/**
	 * Register shared third-party libraries for blocks.
	 *
	 * Makes vendor libraries (Splide, GSAP, etc.) available as registered
	 * handles that block.json can reference from `viewStyle`, `editorStyle`,
	 * `viewScript`, and `editorScript`.
	 *
	 * Registering these handles on block editor asset hooks keeps them available
	 * to the editor parent document and the Block API v3 iframe without touching
	 * unrelated wp-admin screens.
	 *
	 * IMPORTANT:
	 * - Only REGISTERS assets (doesn't enqueue them)
	 * - Only processes files in `/plugins/` subdirectory
	 * - Custom blocks should reference the registered handles from block.json
	 *
	 * WHY only /plugins/?
	 * - Page-specific styles (pages/archive.css) should not be available globally
	 * - Full site layouts would interfere with block editing
	 * - Only shared libraries should be available to all blocks
	 *
	 * Directory structure:
	 *  - `\assets\css\styles-register\plugins\splidejs\core.css`
	 *    → handle: `plugins.splidejs.core`
	 *  - `\assets\js\scripts-register\plugins\gsap\core.js`
	 *    → handle: `plugins.gsap.core`
	 *
	 * File versioning: Uses file timestamp for cache busting
	 *
	 * @return void
	 */
	function st_wp_core_register_block_editor_libraries(): void {
		static $registered = false;

		if ( ! is_admin() ) {
			return;
		}

		if ( $registered ) {
			return;
		}

		$registered = true;

		// Use get_stylesheet_directory() for child themes.
		$base_dir = get_template_directory();

		$assets = array(
			'css' => '/assets/css/styles-register/',
			'js'  => '/assets/js/scripts-register/',
		);

		foreach ( $assets as $type => $dir ) {
			$path = $base_dir . $dir;
			if ( ! is_dir( $path ) ) {
				continue;
			}

			$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path ) );

			foreach ( $files as $file ) {
				if ( $file->isFile() && pathinfo( $file, PATHINFO_EXTENSION ) === $type ) {
					$relative_path = str_replace( $path, '', $file->getPathname() );

					// Check if the file is in the "plugins" subdirectory.
					if ( ! str_contains( $relative_path, 'plugins/' ) ) {
						continue;
					}

					$handle       = st_wp_core_generate_asset_handle( $relative_path );
					$asset_url    = get_template_directory_uri() . $dir . $relative_path;
					$file_version = filemtime( $file->getPathname() );

					if ( 'css' === $type ) {
						wp_register_style( $handle, $asset_url, array(), $file_version );
					} elseif ( 'js' === $type ) {
						wp_register_script( $handle, $asset_url, array(), $file_version, true );
					}
				}
			}
		}
	}
}
// Register only for block editor asset loading, not the whole admin dashboard.
if ( apply_filters( 'st_wp_core_enable_block_editor_libraries', true ) ) {
	add_action( 'enqueue_block_assets', 'st_wp_core_register_block_editor_libraries', 1 );
	add_action( 'enqueue_block_editor_assets', 'st_wp_core_register_block_editor_libraries', 1 );
}


if ( ! function_exists( 'st_wp_core_classes_autoloader' ) ) {
	/**
	 * Autoloader for files in the classes directory.
	 * Checks /inc/classes/ first (for overrides), then /core/classes/.
	 * Only Classes should be added here. No executables.
	 *
	 * @param string $class_name File & Class Name.
	 * @return void
	 */
	function st_wp_core_classes_autoloader( string $class_name ): void {
		// Convert class name to lowercase with hyphens.
		// Example: ST_WP_Core_Walker_Nav_Menu -> class-st-wp-core-walker-nav-menu.php.
		$file_name = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

		// Check /inc/classes/ first (override location).
		$inc_file = get_template_directory() . '/inc/classes/' . $file_name;
		if ( file_exists( $inc_file ) ) {
			require_once $inc_file;
			return;
		}

		// Fall back to /core/classes/ (default location).
		$core_file = __DIR__ . '/classes/' . $file_name;
		if ( file_exists( $core_file ) ) {
			require_once $core_file;
		}
	}
}
// Register the autoloader.
spl_autoload_register( 'st_wp_core_classes_autoloader' );


if ( ! function_exists( 'st_wp_core_acf_save_json_path' ) ) {
	/**
	 * Save acf-json to the stylesheet directory.
	 *
	 * @return string
	 * @link https://support.advancedcustomfields.com/forums/topic/acf-json-fields-not-loading-from-parent-theme/
	 * @since 1.0.0
	 */
	function st_wp_core_acf_save_json_path(): string {
		return get_stylesheet_directory() . '/acf-json';
	}
}
if ( apply_filters( 'st_wp_core_enable_acf_json', true ) ) {
	add_filter( 'acf/settings/save_json', 'st_wp_core_acf_save_json_path' );
}


if ( ! function_exists( 'st_wp_core_acf_load_json_paths' ) ) {
	/**
	 * Load acf-json from parent theme and child theme, if available.
	 *
	 * @param array $paths Array of acf-json paths.
	 *
	 * @return array
	 * @link https://support.advancedcustomfields.com/forums/topic/acf-json-fields-not-loading-from-parent-theme/
	 * @since 1.0.0
	 */
	function st_wp_core_acf_load_json_paths( array $paths ): array {
		$paths = array( get_template_directory() . '/acf-json' );

		if ( is_child_theme() ) {
			$paths[] = get_stylesheet_directory() . '/acf-json';
		}

		return $paths;
	}
}
if ( apply_filters( 'st_wp_core_enable_acf_json', true ) ) {
	add_filter( 'acf/settings/load_json', 'st_wp_core_acf_load_json_paths' );
}


if ( function_exists( 'acf_register_block_type' )
	&& ! function_exists( 'st_wp_core_register_blocks' ) ) {
	/**
	 * Setup acf gutenberg blocks
	 *
	 * @return void
	 * @since 1.0.0
	 */
	function st_wp_core_register_blocks(): void {
		$blocks = glob( get_stylesheet_directory() . '/blocks/*' );
		foreach ( $blocks as $block ) {
			if ( is_dir( $block ) ) {
				register_block_type( $block );
			}
		}
	}
}
if ( function_exists( 'acf_register_block_type' )
	&& apply_filters( 'st_wp_core_enable_block_registration', true ) ) {
	add_action( 'acf/init', 'st_wp_core_register_blocks' );
}
