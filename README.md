st-wp-starter
===

Hi. I'm a starter theme called `st-wp-starter`. I'm a theme meant for hacking so don't use me as a Parent Theme. Instead, try turning me into the next, most awesome, WordPress theme out there. That's what I'm here for.

My ultra-minimal CSS might make me look like theme tartare but that means less stuff to get in your way when you're designing your awesome theme. Here are some of the other more interesting things you'll find here:

* A modern workflow with a pre-made command-line interface to turn your project into a more pleasant experience.
* A just right amount of lean, well-commented, modern, HTML5 templates.
* Custom template tags in `inc/template-tags.php` that keep your templates clean and neat and prevent code duplication.
* Some small tweaks in `inc/template-functions.php` that can improve your theming experience.
* A script at `js/navigation.js` that makes your menu a toggled dropdown on small screens (like your phone), ready for CSS artistry. It's enqueued automatically.
* 2 sample layouts in `sass/layouts/` made using CSS Grid for a sidebar on either side of your content. Just uncomment the layout of your choice in `/assets/scss/main.scss`.
Note: `.no-sidebar` styles are automatically loaded.
* Full support for `WooCommerce plugin` integration with hooks in `inc/woocommerce.php`, styling override woocommerce.css with product gallery features (zoom, swipe, lightbox) enabled.
* Licensed under GPLv2 or later. :) Use it to make something cool.

Scripts / Styles autoloader:

* directory `/assets/css/styles-register/` stylesheets are auto-registered
* directory `/assets/css/styles-enqueue/` stylesheets are auto-enqueued
* directory `/assets/js/scripts-register/` scripts are auto-registered
* directory `/assets/js/scripts-enqueue/` scripts are auto-enqueued
* auto-registered styles / scripts use a directory dot-notation for what comes after `/styles-register/`
  or `/scripts-register/` in combination with the file name to generate the handle. Examples:
  * source: `/assets/css/styles-register/page-default.min.css`
  * handle: `page-default`
  * source: `/assets/css/styles-register/plugins/splidejs/core.min.css`
  * handle: `plugins.splidejs.core`
  * source: `/assets/js/scripts-register/plugins/splidejs/core.min.js`
  * handle: `plugins.splidejs.core`

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

Clone or download this repository, change its name to something else (like, say, `megatherium-is-awesome`), and then you'll need to do a six-step find and replace on the name in all the templates.

1. Search for `'st-wp-starter'` (inside single quotations) to capture the text domain and replace with: `'megatherium-is-awesome'`.
2. Search for `st_wp_starter_` to capture all the functions names and replace with: `megatherium_is_awesome_`.
3. Search for `Text Domain: st-wp-starter` in `style.css` and replace with: `Text Domain: megatherium-is-awesome`.
4. Search for <code>&nbsp;ST_WP_Starter</code> (with a space before it) to capture DocBlocks and replace with: <code>&nbsp;Megatherium_is_Awesome</code>.
5. Search for `st-wp-starter-` to capture prefixed handles and replace with: `megatherium-is-awesome-`.
6. Search for `ST_WP_STARTER_` (in uppercase) to capture constants and replace with: `MEGATHERIUM_IS_AWESOME_`.

Then, update the stylesheet header in `style.css`, the links in `footer.php` with your own information and rename `st-wp-starter.pot` from `languages` folder to use the theme's slug. Next, update or delete this readme.

### Setup

To start using all the tools that come with `st-wp-starter` you need to install the necessary Node.js and Composer dependencies:

```sh
$ composer install
$ npm install
```

### Available CLI commands

`st-wp-starter` comes packed with CLI commands tailored for WordPress theme development:

- `composer lint:wpcs` : checks all PHP files against [PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/).
- `composer lint:wpcs:fix` : will fix most standards from `wpcs`.
- `composer lint:php` : checks all PHP files for syntax errors.
- `composer make-pot` : generates a .pot file in the `languages/` directory.
- `npm run dev` : `ViteJS` runs compilers, and watches for changes
  - generated css files are minified
  - generated js files are minified and mangled
  - generates .map files
  - `/assets/scss/` will compile with the same directory structure to `/assets/css/`
  - `/assets/scripts/` will compile with the same directory structure to `/assets/js/`
  - `/blocks/<block_name>/assets/scss/` will compile to `/blocks/<block_name>/assets/css/`
- `npm run build` : same as dev | deletes .map files

Now you're ready to go! The next step is easy to say, but harder to do: make an awesome WordPress theme. :)

Good luck!
