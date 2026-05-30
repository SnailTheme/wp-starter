# Theme Agent Guide

This file is the entrypoint for maintainers and coding agents working inside
this theme.

## Main Rule

Keep project-specific work out of `/core/` when future toolkit updates should
remain possible.

- Use `/core/` for shared, updateable functionality.
- Use `/inc/` for theme-owned behavior, overrides, and integrations.
- Use templates, assets, and blocks for project-specific output.
- Keep stable `st_wp_core_*` helpers intact; they are shared core API.

## Focused Guides

- [Core and overrides](.agents/core_and_overrides.md)
- [Scripts and styles](.agents/scripts_and_styles.md)
- [Template tags and helpers](.agents/template_tags_and_helpers.md)
- [Blocks and ACF](.agents/blocks_and_acf.md)
- [CLI and toolkit](.agents/cli_and_toolkit.md)

## Before Editing

- Read the focused guide for the area you are changing.
- Prefer `/inc/` over `/core/` for project behavior.
- Avoid changing generated assets directly; update source assets instead.
- Keep documentation and examples generator-safe.
