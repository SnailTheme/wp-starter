<!-- st-toolkit-agent-doc-version: 1.0.0 -->
# Template Tags And Helpers

## Template Tags

Put functions that generate or echo theme markup in `/inc/template-tags.php`.

Good examples:

- post meta output
- thumbnail wrappers
- author/date/category markup
- reusable HTML fragments used by templates

Template tags are project-owned. They may be changed to match the generated
theme's markup and design.

## Template Functions

Put non-markup helper functions and WordPress filters in
`/inc/template-functions.php`.

Good examples:

- body class filters
- excerpt filters
- query or archive behavior
- small helpers that return data instead of echoing markup

## Core Helpers

Stable shared helpers live in `/core/` and use the `st_wp_core_*` prefix.

Examples:

- `st_wp_core_generate_img()`
- `st_wp_core_generate_sprite_svg()`
- `st_wp_core_get_block_wrapper_attributes()`

Do not rename stable `st_wp_core_*` helpers for project-specific branding.
They are shared API used across generated themes and toolkit-installed blocks.
