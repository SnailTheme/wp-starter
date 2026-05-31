<!-- st-toolkit-agent-doc-version: 1.0.0 -->
# Core And Overrides

## Directory Roles

`/core/` contains shared functionality that can be updated by the toolkit.
Do not customize `/core/` directly when the project should keep receiving
future core updates.

`/inc/` contains theme-owned behavior. Put project customizations, overrides,
and integration hooks here.

## Early Loader Decisions

Use `/inc/bootstrap.php` only for early decisions that must run before
`/core/bootstrap.php` finishes loading.

Good uses:

- filter `st_wp_core_files`
- filter `st_wp_core_should_load_file`
- disable broad core features with `st_wp_core_enable_*` filters

Avoid normal theme setup in `/inc/bootstrap.php`. Use the matching `/inc/`
module instead.

## Matching Override Files

Core modules load matching `/inc/` files before defining guarded fallback
callbacks.

Common pairs:

- `/core/scripts.php` -> `/inc/scripts.php`
- `/core/template-tags.php` -> `/inc/template-tags.php`
- `/core/template-functions.php` -> `/inc/template-functions.php`
- `/core/woocommerce.php` -> `/inc/woocommerce.php`
- `/core/jetpack.php` -> `/inc/jetpack.php`

If a core callback is guarded with `function_exists()`, define the replacement
in the matching `/inc/` file.

## Feature Filters

Use feature filters when the core file should still load but a feature should
be disabled.

Examples:

```php
add_filter( 'st_wp_core_enable_customizer', '__return_false' );
add_filter( 'st_wp_core_enable_woocommerce', '__return_false' );
add_filter( 'st_wp_core_enable_plugin_suggestions_notice', '__return_false' );
```

Use `st_wp_core_files` or `st_wp_core_should_load_file` only when an entire core
module should not load.
