# st-wp-starter

A WordPress starter theme for building custom project themes, with an
updateable shared core, a Composer-powered development toolkit, selectable UI
foundations, composable blocks, reusable components, and local agent workflows.

Start with the essentials and install the pieces your project needs. This is
a foundation to develop directly, not a parent theme or a finished site design.

[Generate a white-label theme](https://wp-starter.snailtheme.com/) |
[Features and documentation](https://wp-starter.snailtheme.com/features/) |
[Toolkit source and reference](https://github.com/snailtheme/wp-starter-toolkit)

## Getting Started

Use the generator to download a theme with your own name, author details,
repository, and block namespace, or develop from this repository.

The project theme starts at `1.0.0` in `style.css`, the theme version constant
in `functions.php`, and the root npm package metadata. Maintain those together
as your project evolves. The separate `ST_WP_CORE_VERSION` in
`/core/bootstrap.php` tracks shared-core updates, not your project's version.

Requirements:

- WordPress 7.0+ and PHP 8.2+.
- [Advanced Custom Fields PRO 6.6+](https://www.advancedcustomfields.com/pro/)
  for the ACF integrations and Block API v3 workflow. This is a paid plugin,
  installed separately.
- [ACF SVG Icon Field](https://github.com/shoot56/acf-svg-icon).
- For local development: [Composer](https://getcomposer.org/),
  [Node.js](https://nodejs.org/) 22.x or 24.x, and npm. Both Node versions are
  covered by the project's build CI.

Install the theme and required plugins in WordPress, then run these commands
from the theme directory:

```bash
composer install
npm install
composer st-toolkit init
composer toolkit:ui-list
composer toolkit:ui-install bare
```

Choose `bare`, `blueprint`, or `tailwind` for that last command before starting
project styling. UI installation runs the production asset build. Use
`npm run dev` for watch builds while developing.

## Shared Core And Toolkit

The theme separates reusable functionality from project customizations:

- `/core/` and core asset directories contain shared, toolkit-updateable code.
- `/inc/` contains project behavior, integrations, and guarded core overrides.
- Templates, generic assets, and installed blocks stay available for project work.

Put reusable markup helpers in `/inc/template-tags.php` and non-markup helpers
or filters in `/inc/template-functions.php`.

The **PHP class autoloader** loads classes on demand using a lowercase,
hyphenated filename: `Project_Card` maps to
`/inc/classes/class-project-card.php`. It checks `/inc/classes/` before
`/core/classes/`, so a matching project class can override a shared class.
For example, the shared `ST_WP_Core_Walker_Nav_Menu` lives in
`class-st-wp-core-walker-nav-menu.php`. Class files are for class definitions,
not automatically executed setup scripts. Composer dependencies use Composer's
own autoloader.

Matching `/inc/*.php` override files load before the corresponding guarded
core implementations. Use `/inc/bootstrap.php` for early loader filters such
as `st_wp_core_files` and `st_wp_core_should_load_file`, not general project
initialization. Reusable component integrations under `/inc/components/`
are discovered recursively.

Keep project-specific changes out of shared core when you want to receive
future core updates. The toolkit can compare versions, preview file changes,
back up affected files, apply an update, and restore a selected core backup.
It updates package-owned paths, not the entire project theme.

The toolkit is a Composer development dependency. Its commands run from the
theme directory without adding a vendor binary to your shell path:

| Task | Command |
| --- | --- |
| Inspect setup and dependencies | `composer toolkit:doctor` |
| Compare installed and packaged core versions | `composer toolkit:core-check` |
| Preview a shared-core update | `composer toolkit:core-update:dry-run` |
| Apply an approved shared-core update | `composer toolkit:core-update` |
| Restore a core update backup | `composer toolkit:core-rollback` |
| List available blocks and bundles | `composer toolkit:blocks-list` |
| Inspect the active UI scaffold | `composer toolkit:ui-status` |
| List reusable components | `composer toolkit:components-list` |
| Refresh local agent guidance independently | `composer toolkit:docs-update` |

Review dry runs before changes and retain backups. Core updates can replace
local edits to package-owned files; they are not three-way merges. Updating
the toolkit dependency, copied core, and local documentation are separate
operations. See the [toolkit reference](https://github.com/snailtheme/wp-starter-toolkit)
for options, JSON output, explicit theme targets, and maintainer resource tools.

## UI Foundations

Choose the starting point that fits the project:

| Profile | Starting point |
| --- | --- |
| `bare` | Minimal resets, box sizing, responsive media, accessibility helpers, and WordPress alignments. |
| `blueprint` | A conventional Sass foundation for typography, forms, layouts, WordPress content, and WooCommerce. |
| `tailwind` | Tailwind CSS v4 through its official Vite plugin, with modular native CSS and separate frontend/editor entries. |

These are editable project scaffolds, not runtime themes to toggle. The first
selection is locked; replacing it later is a destructive migration requiring
explicit confirmation and backup. Reusable toolkit blocks and components are
designed to work across all three profiles.

## Asset Workflow

Vite compiles theme Sass and JavaScript plus block-owned Sass. Sass uses
`@use`/`@forward`; the Tailwind profile adds native CSS under `/assets/styles/`,
with separate token, base, component, utility, and editor partials. Classic Sass
can still compile alongside Tailwind, including third-party integrations.

- `npm run dev` watches sources and writes development source maps.
- `npm run build` cleans generated outputs and creates production assets.
- Frontend, admin UI, editor canvas, and block assets have distinct loading paths.
- Competing Sass/native CSS entries fail before output cleanup instead of
  silently overwriting one another.

The **asset autoloader** discovers compiled CSS and JavaScript recursively.
Choose whether a file should be available on demand or loaded automatically:

| Source directory | Compiled directory | Frontend behavior |
| --- | --- | --- |
| `assets/scss/styles-register/` | `assets/css/styles-register/` | Auto-register styles; enqueue only where needed. |
| `assets/scss/styles-enqueue/` | `assets/css/styles-enqueue/` | Auto-register and auto-enqueue styles. |
| `assets/scripts/scripts-register/` | `assets/js/scripts-register/` | Auto-register scripts; enqueue only where needed. |
| `assets/scripts/scripts-enqueue/` | `assets/js/scripts-enqueue/` | Auto-register and auto-enqueue scripts. |

Tailwind's `assets/styles/styles-register/` and `assets/styles/styles-enqueue/`
entries use the same CSS destinations and behavior. Underscore-prefixed Sass
and native CSS partials are imported, not standalone entries.

Handles use the path beneath the register/enqueue directory in dot notation,
without `.min` or the extension. For example,
`assets/css/styles-register/plugins/animatecss/animate.min.css` becomes
`plugins.animatecss.animate`. Files receive timestamp versions for cache busting.
Use `wp_enqueue_style()` or `wp_enqueue_script()` with a registered handle when
needed; registration alone does not download the asset to the browser.

Put conditional loading, third-party URLs, and explicit dependency declarations
in `/inc/scripts.php`. The frontend registry runs on `wp_enqueue_scripts` at
priority `20`; callbacks consuming its registered handles should run later.
Only shared `styles-register/plugins/` and `scripts-register/plugins/` libraries
are additionally registered for the block editor, where blocks request them
through `block.json`. Auto-enqueued frontend assets are not blanket admin assets.

Theme JavaScript is minified, not bundled. Block JavaScript is browser-ready
and loaded directly through `block.json`; block scripts declare their actual
dependencies, and ACF previews use the iframe-aware render lifecycle.

AnimateCSS is an optional theme feature, enabled through the Customizer or
project enqueue logic. Its local Sass modules let you select individual
animations rather than importing the whole library. Reusable blocks do not
require animation classes to display their content.

## Template And Developer Helpers

Shared `st_wp_core_*` APIs keep their names in generated themes. Project-owned
helpers use the generated theme prefix instead.

**Attachment images:** `st_wp_core_generate_img()` accepts an attachment ID or
an ACF Image array and returns `<figure><img></figure>` markup. It uses the
attachment's alt text, raster dimensions, and WordPress-provided responsive
sources when available, with a separate SVG attachment path. Missing images
return an empty string. Extra classes belong to the figure; fetch priority
defaults to `low` and does not imply lazy loading.

```php
echo wp_kses_post( st_wp_core_generate_img(
    get_post_thumbnail_id(), 'large', 'low', 'project-card__image'
) );
```

**SVG sprites:** `st_wp_core_generate_sprite_svg()` references a symbol in a
sprite under `/assets/images/icons/`, with configurable dimensions, cache
busting, and accessible labeling. It echoes sanitized SVG by default; pass
`should_return: true` to receive the markup instead. Icons are decorative
(`aria-hidden`) unless explicitly labeled:

```php
st_wp_core_generate_sprite_svg(
    'icon-social-facebook',
    aria_hidden: false,
    aria_label: 'Facebook'
);
```

The default sprite is `assets/images/icons/icons.svg`; custom sprites must
exist in that directory and contain the requested symbol.

Other useful APIs include:

| Helper | Purpose |
| --- | --- |
| `st_wp_core_get_block_wrapper_attributes()` | Preserve native block classes, IDs, and supported styles while adding block-owned attributes. |
| `st_wp_core_get_theme_version()` | Read the active theme's stylesheet version, with a shared-core fallback. |
| `st_wp_starter_asset_exists()`, `st_wp_starter_asset_has_content()` | Check optional compiled assets before enqueuing them. |
| `st_wp_starter_get_asset_version()` | Use a file timestamp, falling back to the theme version. |
| `st_wp_core_schema_add()`, `st_wp_core_schema_add_breadcrumbs()` | Register keyed JSON-LD nodes or an explicitly supplied breadcrumb trail before `get_header()`. |
| `st_wp_core_schema_add_faq()`, `st_wp_core_schema_register_block_provider()` | Contribute FAQ data and register early block-local schema providers. |

The asset helpers above live in `/inc/scripts.php`; their `st_wp_starter_`
prefix changes with the generated project. Existing post date, author,
entry-footer, and thumbnail helpers live in `/inc/template-tags.php`.

**Debugging:** `dd( $value )` dumps a value and stops execution. Composer's
Symfony VarDumper supplies it when available; the theme also provides a guarded
fallback loaded only with `WP_DEBUG`. Treat it as a development tool, remove
calls before deployment, and never dump credentials or private user data.

## Composable Blocks

The toolkit's Elementals bundle provides ten building blocks for editable
sections, using WordPress Block API v3 and ACF Blocks v3:

| Elements | Purpose |
| --- | --- |
| Wrapper, Container | Section background/spacing and content width/position. |
| Heading, Text | Native headings, paragraphs, lists, and quotations. |
| Columns, Column | Weighted columns, gaps, vertical alignment, and responsive stacking. |
| Grid, Grid Cell | Responsive column counts and bounded cell spans. |
| Content With Image | Native content and buttons alongside an ACF attachment image. |
| FAQ | A question/answer repeater with native, keyboard-accessible disclosures. |

Compose a section from a Wrapper, an optional Container, and meaningful content
elements. Heading levels remain native WordPress controls. FAQ opens its first
complete item by default and keeps one item open per instance without accordion
JavaScript; it is also available as an individual installation.

Preview and install the complete family:

```bash
composer toolkit:blocks-install:dry-run elementals
composer toolkit:blocks-install elementals
```

A separate Slider block offers horizontal and vertical variants. For example,
`composer toolkit:blocks-install slider hero-slider` installs it with a
project-specific destination name. Block names, directories, ACF fields, and
keys follow the configured namespace.

The clean starter ships without bundled blocks or Splide. The installer checks
requirements, can install missing npm dependencies with approval, adds shared
source assets and any required ACF JSON, and runs one final production build.
Native-only elements do not receive dummy field groups. Review conflicts and
the reported backup; a failed build leaves installed files for diagnosis.

## Mega Menu And Components

Reusable non-block features install separately from UI profiles and blocks.
The mega menu provides:

- A logo, primary navigation, and expandable search in the header.
- Whole-label desktop disclosure controls, compact dropdowns, and multi-column
  panels for grouped submenus.
- A mobile hamburger and recursive off-canvas drawers with contextual Back controls.
- Menu item images, icon/card display options, and column headings.
- Keyboard navigation, Escape/outside closing, focus handling, scroll locking,
  reduced-motion handling, and a no-JavaScript fallback.

After choosing the project UI, preview and install it:

```bash
composer toolkit:components-install mega-menu -- --dry-run
composer toolkit:components-install mega-menu
```

Component PHP and registered assets are isolated in their own `toolkit/`
subdirectories. The mega menu loads for an assigned menu location and does not
require manually rewriting `header.php`.

## Agent Guides And Skills

`composer st-toolkit init` installs project-local guidance for Codex and Claude
Code: `AGENTS.md`, focused `.agents/` guides, and eight task skills. Workflows
cover section composition, block development, assets, core overrides, schemas,
toolkit operations, updates/releases, and validation.

`CLAUDE.md` and thin Claude skill adapters point to the same maintained
instructions. Guides explain BEM/nested Sass, variant settings, asset handles,
Block API v3 previews, ownership boundaries, and validation commands. Start the
agent from the theme directory or below it; no global agent settings are changed.

Refresh guidance with `composer toolkit:docs-update`, independently of core
or block updates. Guides have individual content versions; removing a file's
version comment preserves it as project-owned customization.

Installed guidance is ignored by Git. Exclude it from manual deployments too:
ignore rules do not prevent a server from serving uploaded development files.

## WordPress Integrations

- **WordPress templates:** classic PHP templates for posts, pages, archives,
  search, and comments, with custom-logo, featured-image, and widget support.
- **WooCommerce (optional):** theme support, gallery features, content wrappers, and an
  optional mini-cart helper. Bare retains plugin styles; projects can provide
  their own compiled overrides.
- **Jetpack (optional):** Infinite Scroll, Responsive Videos, and Content Options
  integration when the plugin is active.
- **ACF:** local JSON, a theme options page, and namespace-aware registration
  from `/blocks/*/block.json`. Optional block-local `block-bootstrap.php` files
  register early providers/hooks without rendering content.
- **Menus:** image and unlinked-item controls through the shared menu walker,
  usable independently of the optional mega-menu component.
- **Customizer:** optional AnimateCSS, thumbnail-size controls, emoji/version
  output controls, user-list REST restrictions, and capability-aware custom head
  code. Thumbnail controls prevent future generation; they do not delete
  existing attachments or image files.
- **Structured data:** one head-only JSON-LD graph, supplied breadcrumb trails,
  and optional FAQ contributions derived from the block's visible answers.
  Breadcrumbs provide logic, not navigation markup. Schema ownership is explicit;
  the theme does not remove SEO-plugin output or promise FAQ rich results.
- **Translations and standards:** POT generation, PHP syntax checks, and
  WordPress Coding Standards through Composer commands.

## Development Checks

Before committing project changes:

```bash
npm run build
composer validate --strict
composer lint:php
composer lint:wpcs
```

Run `composer make-pot` after PHP changes, including changes to translation
source-line references. Include generated assets and the updated POT in the
project commit. Use `npm audit` and `composer audit --locked` for dependency
security checks.

For detailed conventions and examples, see the
[feature documentation](https://wp-starter.snailtheme.com/features/) and
the locally installed agent guides.

## License

GPLv2 or later. See [LICENSE](LICENSE).
