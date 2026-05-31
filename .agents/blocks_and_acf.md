<!-- st-toolkit-agent-doc-version: 1.0.0 -->
# Blocks And ACF

## Block Location

Blocks live in:

```text
/blocks/<block-namespace>-<block-name>/
```

Each block should keep its PHP, block metadata, and block assets inside its own
directory.

A block using namespace `<namespace>` and slug `slider` lives in:

```text
/blocks/<namespace>-slider/
```

## Block Metadata

New blocks should use WordPress Block API v3 and ACF Blocks v3:

```json
{
  "apiVersion": 3,
  "acf": {
    "blockVersion": 3,
    "mode": "preview",
    "renderTemplate": "block-render.php"
  }
}
```

The current block namespace is defined by:

```php
ST_WP_CORE_THEME_PATTERNS['block_namespace']
```

Generated themes may use different block namespaces. Do not hardcode a
namespace in reusable block logic when the value can be read from theme patterns
or from `$block['name']`.

Block category comes from:

```php
ST_WP_CORE_THEME_PATTERNS['block_category']
```

Do not enqueue block editor styles or scripts from `admin_enqueue_scripts`.
Block API v3 renders the post/page editor canvas inside an iframe, so block
assets must be declared from `block.json` or a block-aware hook.

Use `block.json` for front-end and editor assets:

```json
{
  "viewStyle": [ "plugins.splidejs.core", "file:./assets/css/view-style.min.css" ],
  "editorStyle": [ "plugins.splidejs.core", "file:./assets/css/editor-style.min.css" ],
  "viewScript": [ "plugins.splidejs.splide", "file:./assets/js/viewScripts.js" ],
  "editorScript": [ "plugins.splidejs.splide", "file:./assets/js/editorScripts.js" ]
}
```

Shared handles such as `plugins.splidejs.core` and `plugins.splidejs.splide`
come from the core asset registry. Block-specific files stay inside the block
directory.

## Markup And BEM

Block markup should use BEM classes derived from the block slug. Keep the root
class stable, then add elements and modifiers around it:

- Root: `.slider-section`
- Nested block: `.slider-section-splide`
- Element: `.slider-section-splide__title`
- Modifier: `.slider-section--vertical`

Block SCSS should follow the same shape with highly nested BEM selectors.
Put block-level setting modifiers on the root block, then target nested
elements through that root state:

```scss
.slider-section {

  &--vertical {

    .slider-section-splide {

      &__track {
        padding: 0;
      }
    }
  }
}
```

Use stable core helpers when useful, especially:

```php
st_wp_core_get_block_wrapper_attributes()
```

## Render Files

Render files should derive the block namespace and base name from theme
patterns and `block.json` / `$block`. That keeps generated themes and
toolkit-installed block copies reusable when the namespace or destination slug
changes:

```php
$block_namespace = '';
if ( defined( 'ST_WP_CORE_THEME_PATTERNS' ) && is_array( ST_WP_CORE_THEME_PATTERNS ) ) {
	$block_namespace = (string) ( ST_WP_CORE_THEME_PATTERNS['block_namespace'] ?? '' );
}

$block_name = str_replace( "{$block_namespace}/", '', $block['name'] );
```

Build ACF field roots from `$block_namespace` and `$block_name`:

```php
$fields_group   = "{$block_namespace}-{$block_name}-fields";
$settings_group = "{$block_namespace}-{$block_name}-settings";

$fields   = get_field( $fields_group );
$settings = get_field( $settings_group );
$fields   = is_array( $fields ) ? $fields : array();
$settings = is_array( $settings ) ? $settings : array();
```

Settings that affect JavaScript behavior should be printed into the rendered
markup, usually as data attributes. Let JavaScript read the rendered options
instead of duplicating PHP settings logic in the script.

## ACF Field Structure

New ACF blocks should use two main field groups. Replace `<namespace>` with
`ST_WP_CORE_THEME_PATTERNS['block_namespace']`:

- `<namespace>-<block-slug>-fields`
- `<namespace>-<block-slug>-settings`

Use `Fields` for content shown on the front end.

Use `Settings` for block-level options such as style, container width,
animation, and behavior toggles.

Simple field names:

```text
<namespace>-<block-slug>-fields__<field_name>
<namespace>-<block-slug>-settings__<setting_name>
```

Repeater names:

```text
<namespace>-<block-slug>-fields-<plural_repeater_name>
<namespace>-<block-slug>-fields-<singular_repeater_name>__<field_name>
```

Avoid loose fields outside the two main groups.

When blocks are installed through the toolkit, package source ACF JSON can use
readable symbolic keys such as `group_<namespace>_<block_slug>` and
`field_<namespace>_<block_slug>_fields_group`. The toolkit rewrites those to
deterministic ACF-style keys for the installed destination block and names the
local JSON file after the generated group key. This avoids key conflicts while
keeping repeated installs of the same destination block stable.

## Block Styles

Block SCSS source files live in:

```text
/blocks/<block-name>/assets/scss/
```

Current style file pattern:

- `_styles.scss` contains shared block styles and BEM selectors.
- `view-style.scss` imports front-end styles.
- `editor-style.scss` imports editor styles.

For Block API v3, editor styles must be iframe-safe. Scope editor-only rules
under `.editor-styles-wrapper` when they are meant only for the editor canvas:

```scss
.editor-styles-wrapper {
  @import "styles";
}
```

Vite compiles block SCSS to:

```text
/blocks/<block-name>/assets/css/view-style.min.css
/blocks/<block-name>/assets/css/editor-style.min.css
```

Run `npm run dev` while actively editing styles. Run `npm run build` before
committing. Do not hand-edit generated block CSS.

## Block Scripts

Block scripts live in:

```text
/blocks/<block-name>/assets/js/
```

The current V1 build does not compile block JavaScript. `block.json` loads
`editorScripts.js` and `viewScripts.js` directly, so keep those files
browser-ready and run `node --check` after editing them.

For Block API v3, editor scripts must handle iframe rendering:

- Do not assume `.block-editor` contains the rendered block preview.
- Do not rely only on `wp.domReady()`.
- Observe the current document and same-origin iframe documents.
- Guard missing dependencies such as `Splide`.
- Skip instances that are already initialized.
- Skip empty markup with no slides/items.
- Catch initialization errors so one bad block does not break the editor.

Front-end scripts can usually initialize on `DOMContentLoaded`, but should use
the same guards for missing dependencies, empty markup, and duplicate
initialization.

When a script needs options, prefer reading them from rendered markup:

```html
<div class="splide slider-section-splide" data-splide="{...}">
```

That keeps PHP, ACF settings, editor previews, and front-end behavior in sync.
