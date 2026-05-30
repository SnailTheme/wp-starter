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
vendor/bin/st-toolkit core:check --theme=.
vendor/bin/st-toolkit core:update --theme=. --dry-run
vendor/bin/st-toolkit core:update --theme=. --yes
vendor/bin/st-toolkit core:rollback --theme=. --yes
vendor/bin/st-toolkit blocks:list
vendor/bin/st-toolkit blocks:install slider hero-slider --theme=. --dry-run
vendor/bin/st-toolkit blocks:install slider hero-slider --theme=. --yes
vendor/bin/st-toolkit doctor --theme=.
```

Toolkit core updates are for package-owned files only.

Expected update targets:

- `/core/**`
- `/assets/scss/core/**`
- `/assets/scripts/core/**`
- `/assets/css/core/**`
- `/assets/js/core/**`

Do not use toolkit core updates as a way to change project-specific code in
`/inc/`, templates, generic assets, or local blocks.
