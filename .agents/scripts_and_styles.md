# Scripts And Styles

## Source And Build Paths

Edit source files:

- `/assets/scss/`
- `/assets/scripts/`
- `/blocks/<block-name>/assets/scss/`

Do not hand-edit generated files:

- `/assets/css/`
- `/assets/js/`
- `/blocks/<block-name>/assets/css/`

Use Vite to compile assets:

```bash
npm run dev
npm run build
```

## CSS Class Conventions

Use BEM for project CSS classes:

- Block: `.slider-section`
- Nested block: `.slider-section-splide`
- Element: `.slider-section-splide__title`
- Modifier: `.slider-section-splide--vertical`

SCSS should stay highly nested around the owning block class. Prefer local BEM
nesting with `&__element` and `&--modifier` so styles remain readable and easy
to move with a block.

Avoid broad global selectors unless you are intentionally targeting a
third-party library class, such as a Splide class that must be adjusted for a
block setting.

## Main Theme Assets

Project-owned theme assets are enqueued from `/inc/scripts.php`.

Default source/output pairs:

- `/assets/scss/main.scss` -> `/assets/css/main.min.css`
- `/assets/scripts/main.js` -> `/assets/js/main.min.js`
- `/assets/scss/editor.scss` -> `/assets/css/editor.min.css`
- `/assets/scripts/admin.js` -> `/assets/js/admin.min.js`
- `/assets/scss/woocommerce.scss` -> `/assets/css/woocommerce.min.css`

Core-owned asset sources live under:

- `/assets/scss/core/`
- `/assets/scripts/core/`

Avoid changing core-owned assets for project-specific styling.

## Auto-Registered And Auto-Enqueued Assets

The core asset loader watches compiled asset directories:

- `/assets/css/styles-register/` auto-registers styles
- `/assets/css/styles-enqueue/` auto-enqueues styles
- `/assets/js/scripts-register/` auto-registers scripts
- `/assets/js/scripts-enqueue/` auto-enqueues scripts

Handles are generated from the path after `styles-register`,
`styles-enqueue`, `scripts-register`, or `scripts-enqueue`.

Examples:

- `/assets/css/styles-register/page-default.min.css` -> `page-default`
- `/assets/css/styles-register/plugins/splidejs/core.min.css` -> `plugins.splidejs.core`
- `/assets/js/scripts-register/plugins/splidejs/core.min.js` -> `plugins.splidejs.core`

Shared assets under `/plugins/` are registered for the block editor and by the
front-end asset registry. Custom blocks can reference those handles from
`block.json` `viewStyle`, `editorStyle`, `viewScript`, or `editorScript`
without manually enqueueing the shared library.

## When To Use `/inc/scripts.php`

Use `/inc/scripts.php` when auto-loading is not enough.

Good uses:

- conditional loading by template, route, post type, or WooCommerce state
- third-party assets that need custom dependencies
- inline styles or scripts that need a registered handle
- replacing or extending theme-owned enqueue callbacks

Do not edit `/core/scripts.php` for project behavior.

Use `admin_enqueue_scripts` only for wp-admin UI behavior/assets. Use
`enqueue_block_assets` with an `is_admin()` guard for post/page editor canvas
styles so Block API v3 iframe loading stays compatible with WordPress.
