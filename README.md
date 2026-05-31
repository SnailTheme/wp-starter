st-wp-starter
===

Hi. I'm a starter theme called `st-wp-starter`. I'm here to be turned into a
project theme, with a shared `/core/` layer for reusable functionality and an
`/inc/` layer for project-specific behavior and overrides.

If you plan to use the toolkit to update the theme in the future, avoid
customizing `/core/` directly. Keep your theme-specific logic in `/inc/`,
templates, assets, or blocks instead.

My ultra-minimal CSS might make me look like theme tartare but that means less
stuff to get in your way when you're designing your awesome theme. Here are
some of the other more interesting things you'll find here:

* A modern workflow with a pre-made command-line interface to turn your project into a more pleasant experience.
* A just right amount of lean, well-commented, modern, HTML5 templates.
* Custom template tags in `inc/template-tags.php` that keep your templates clean and neat and prevent code duplication.
* Some small tweaks in `inc/template-functions.php` that can improve your theming experience.
* A split between `/core/` for shared, updateable logic and `/inc/` for project overrides and theme-owned behavior.
* Theme scripts live in `/assets/scripts/` and compile to `/assets/js/`, so your front-end behavior stays organized alongside the rest of the asset pipeline.
* 2 sample layouts in `/assets/scss/layouts/` made using CSS Grid for a sidebar on either side of your content. Just uncomment the layout of your choice in `/assets/scss/main.scss`.
Note: `.no-sidebar` styles are automatically loaded.
* Full support for `WooCommerce plugin` integration with hooks in `inc/woocommerce.php`, with matching theme styles in `/assets/scss/woocommerce.scss` and compiled output in `/assets/css/woocommerce.min.css`.
* Licensed under GPLv2 or later. :) Use it to make something cool.

Installation
---------------

### Plugin requirements
- [Advanced Custom Fields PRO](https://www.advancedcustomfields.com/pro/)
- [ACF SVG Icon Field](https://github.com/shoot56/acf-svg-icon)

### CLI Requirements

`st-wp-starter` requires the following dependencies:

- [Node.js](https://nodejs.org/)
- [Composer](https://getcomposer.org/)

### Quick Start

To start using all the tools that come with `st-wp-starter`, install the
necessary Node.js and Composer dependencies, then initialize the local
development notes:

```sh
$ composer install
$ npm install
$ composer st-toolkit init
```

`composer st-toolkit init` installs `AGENTS.md` and `.agents/` with local
theme development notes. Those files are ignored by Git by default so they can
guide development without being committed or deployed.

Now you're ready to go! The next step is easy to say, but harder to do: make an awesome WordPress theme. :)

Good luck!
