# CLI And Toolkit

## Composer Commands

```bash
composer install
composer lint:php
composer lint:wpcs
composer lint:wpcs:fix
composer make-pot
```

`composer make-pot` generates the POT file in `/languages/` and normalizes
project-owned headers through `/core/tools/normalize-pot.php`.

## Node Commands

```bash
npm install
npm run dev
npm run build
```

Use `npm run dev` during active development to watch source assets for file
changes and keep sourcemaps available.

Use `npm run build` before committing. It creates production assets and removes
sourcemaps.

## Toolkit

The toolkit is installed as a Composer development dependency.

Public repository:

https://github.com/snailtheme/wp-starter-toolkit

Common commands:

```bash
composer toolkit:doctor
composer toolkit:core-check
composer toolkit:core-update:dry-run
composer toolkit:core-update -- --yes
composer toolkit:core-rollback -- --yes
composer toolkit:blocks-list
composer toolkit:blocks-install:dry-run slider hero-slider
composer toolkit:blocks-install slider hero-slider -- --yes
```

These Composer scripts are project wrappers around the toolkit binary installed
in `/vendor/bin/`. Prefer them in documentation and day-to-day usage so
developers do not need to know the vendor binary path.

For less-common toolkit commands, use the generic passthrough script:

```bash
composer st-toolkit core:check --theme=.
composer st-toolkit -- blocks:install slider hero-slider --theme=. --dry-run
```

Use `--` before toolkit arguments when an option name may also be a Composer
option, such as `--dry-run`.

Toolkit core updates are for package-owned files only.

Expected update targets:

- `/core/**`
- `/assets/scss/core/**`
- `/assets/scripts/core/**`
- `/assets/css/core/**`
- `/assets/js/core/**`

Do not use toolkit core updates as a way to change project-specific code in
`/inc/`, templates, generic assets, or local blocks.
