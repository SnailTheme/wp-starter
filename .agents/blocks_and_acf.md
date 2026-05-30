# Blocks And ACF

## Block Location

Blocks live in:

```text
/blocks/<block-name>/
```

Each block should keep its PHP, block metadata, and block assets inside its own
directory.

## Naming

The current block namespace is `stwp`.

Block category comes from:

```php
ST_WP_CORE_THEME_PATTERNS['block_category']
```

Use stable core helpers when useful, especially:

```php
st_wp_core_get_block_wrapper_attributes()
```

## ACF Field Structure

New ACF blocks should use two main field groups:

- `stwp-<block-slug>-fields`
- `stwp-<block-slug>-settings`

Use `Fields` for content shown on the front end.

Use `Settings` for block-level options such as style, container width,
animation, and behavior toggles.

Simple field names:

```text
stwp-<block-slug>-fields__<field_name>
stwp-<block-slug>-settings__<setting_name>
```

Repeater names:

```text
stwp-<block-slug>-fields-<plural_repeater_name>
stwp-<block-slug>-fields-<singular_repeater_name>__<field_name>
```

Avoid loose fields outside the two main groups.
