<?php
/**
 * Early theme bootstrap hooks.
 *
 * This file loads before /core/bootstrap.php so projects can filter core module
 * loading through hooks such as `st_wp_core_files` and `st_wp_core_should_load_file`.
 * Keep this file lightweight: use it for early loader decisions, not normal
 * theme setup.
 *
 * Common uses:
 *
 * 1. Disable an entire core file.
 *
 *    add_filter(
 *        'st_wp_core_files',
 *        function ( array $files ): array {
 *            return array_values(
 *                array_diff(
 *                    $files,
 *                    array( 'woocommerce.php', 'jetpack.php' )
 *                )
 *            );
 *        }
 *    );
 *
 * 2. Conditionally skip a core file.
 *
 *    add_filter(
 *        'st_wp_core_should_load_file',
 *        function ( bool $should_load, string $file ): bool {
 *            if ( 'customizer.php' === $file && ! current_user_can( 'customize' ) ) {
 *                return false;
 *            }
 *
 *            return $should_load;
 *        },
 *        10,
 *        2
 *    );
 *
 *    Note: `customizer-runtime.php` owns saved Customizer runtime behavior.
 *    Disable `customizer.php` when you only want to remove the Customizer UI.
 *
 * 3. Disable a feature while keeping the core file loaded.
 *
 *    add_filter( 'st_wp_core_enable_customizer', '__return_false' );
 *    add_filter( 'st_wp_core_enable_plugin_suggestions_notice', '__return_false' );
 *    add_filter( 'st_wp_core_enable_customizer_disable_emojis', '__return_false' );
 *
 *    Available feature filters:
 *
 *    - st_wp_core_enable_plugin_dependency_notice
 *    - st_wp_core_enable_plugin_suggestions_notice
 *    - st_wp_core_enable_asset_registry
 *    - st_wp_core_enable_block_editor_libraries
 *    - st_wp_core_enable_admin_assets
 *    - st_wp_core_enable_customizer
 *    - st_wp_core_enable_woocommerce
 *    - st_wp_core_enable_jetpack
 *    - st_wp_core_enable_nav_menu_fields
 *    - st_wp_core_enable_acf_json
 *    - st_wp_core_enable_block_registration
 *
 *    Integration filters are intentionally broad. For example,
 *    `st_wp_core_enable_woocommerce` disables the reusable core WooCommerce hooks,
 *    the theme-owned hooks registered from /inc/woocommerce.php, and the
 *    WooCommerce theme stylesheet enqueued from /inc/scripts.php. Use the
 *    matching filter when you want the whole integration off; use
 *    `st_wp_core_files` only when you specifically want to prevent a core file
 *    from loading.
 *
 *    `st_wp_core_enable_customizer` disables the Customizer UI shell only. Use the
 *    more specific Customizer feature filters below to disable the feature file,
 *    control, and runtime behavior:
 *
 *    - st_wp_core_enable_customizer_disable_emojis
 *    - st_wp_core_enable_customizer_animatecss
 *    - st_wp_core_enable_customizer_disable_users_rest_api
 *    - st_wp_core_enable_customizer_disable_wp_version
 *    - st_wp_core_enable_customizer_thumbnail_sizes
 *    - st_wp_core_enable_customizer_html_header_code
 *
 * 4. Override functions from a core file.
 *
 *    Do not place normal overrides in this file. Add the replacement callback to
 *    the matching /inc/ file instead, for example:
 *
 *    - /core/scripts.php -> /inc/scripts.php
 *    - /core/customizer.php -> /inc/customizer.php
 *    - /core/customizer-runtime.php -> /inc/customizer-functions.php
 *    - /core/template-tags.php -> /inc/template-tags.php
 *
 *    Core loads matching /inc/ files before defining guarded fallback functions.
 *
 * 5. Keep debug helpers tied to WP_DEBUG.
 *
 *    `debug.php` is ignored by the core loader unless WP_DEBUG is true, even if a
 *    filter in this file adds it back to the file registry.
 *
 * @package ST_WP_Starter
 */
