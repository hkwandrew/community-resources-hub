<!-- markdownlint-disable MD013 -->

# Development

This guide is for contributors working inside the plugin source tree.

## Source-of-Truth Rules

| Path | Treat as |
| --- | --- |
| `includes/` | PHP runtime source |
| `blocks/` | Source-owned block/view entry points and SCSS |
| `src/` | Shared JavaScript runtime source |
| `assets/` | Static assets and curated upload helpers |
| `tests/` | Direct-run smoke/regression scripts |
| `build/` | Generated output consumed by WordPress at runtime |

Do not hand-edit `build/`. The runtime consumes those files, but they are outputs of the source tree, not owners of behavior.

## Build Chain

### JavaScript

[`webpack.config.js`](../webpack.config.js) defines the built entrypoints:

| Build output | Source entry |
| --- | --- |
| `build/calendar/runtime.js` | `src/calendar/index.js` |
| `build/opportunity-hub/view.js` | `blocks/opportunity-hub/view.js` |
| `build/opportunity-hub/view-module.js` | `blocks/opportunity-hub/view.js` |
| `build/member-directory/view.js` | `blocks/member-directory/view.js` |
| `build/video-slider/view.js` | `blocks/video-slider/view.js` |

### CSS

SCSS sources compile into:

| Build output | Source SCSS |
| --- | --- |
| `build/opportunity-hub/style.css` | `blocks/opportunity-hub/style.scss` |
| `build/member-directory/style.css` | `blocks/member-directory/style.scss` |
| `build/video-slider/style.css` | `blocks/video-slider/style.scss` |
| `build/newsletter-archives/style.css` | `blocks/newsletter-archives/style.scss` |

### Runtime registration

After build output exists, [`includes/assets/class-registry.php`](../includes/assets/class-registry.php) is the PHP owner that registers those files with WordPress and exposes the shared handles used by renderers.

## Scripts

The repo's npm scripts live in [`package.json`](../package.json).

| Command | Purpose |
| --- | --- |
| `yarn build` | Run SCSS build and JS build |
| `yarn start` | Watch SCSS and JS in parallel |
| `yarn lint:js` | Lint JS sources via `@wordpress/scripts` |
| `yarn lint:css` | Lint SCSS sources |
| `yarn styles:build` | Build only SCSS outputs |
| `yarn styles:watch` | Watch only SCSS outputs |
| `yarn build:js` | Build only JS outputs |
| `yarn start:js` | Watch only JS outputs |

## Test Strategy

The repository currently ships direct-run smoke/regression scripts instead of a Composer/PHPUnit or Jest-only harness.

### PHP tests

PHP tests are executable scripts under `tests/*.php`. They provide isolated coverage for plugin contracts such as:

| Area | Files |
| --- | --- |
| Admin menu and settings | `admin-menu-contract-test.php`, `config-health-checks-test.php`, `provisioner-test.php` |
| Content model | `acf-post-fields-test.php`, `opportunity-editor-test.php`, `opportunity-type-taxonomy-test.php`, `member-data-restore-test.php` |
| Workflow persistence | `opportunity-repository-test.php`, `opportunity-reconciliation-test.php` |
| Frontend renderers | `member-directory-renderer-test.php`, `opportunity-hub-renderer-test.php`, `video-slider-renderer-test.php`, `newsletter-archives-renderer-test.php` |
| Asset registration | `calendar-runtime-assets-test.php`, `submit-modal-source-contract-test.php` |

### Node tests

Node-based JSDOM/runtime tests live under `tests/*.mjs`.

| Area | Files |
| --- | --- |
| Modal URL state | `modal-url-state-test.mjs`, `modal-url-state-integration-test.mjs` |
| Opportunity filters and modal hydration | `opportunity-hub-date-filter-test.mjs`, `opportunity-hub-dropdown-disclosure-test.mjs`, `opportunity-hub-filter-order-test.mjs`, `opportunity-modal-hydration-test.mjs` |
| Calendar runtime | `calendar-runtime-observer-test.mjs`, `calendar-runtime-toolbar-filter-test.mjs` |

## Recommended Command Patterns

Run one targeted PHP test:

```bash
php tests/opportunity-hub-renderer-test.php
```

Run one targeted Node test:

```bash
node tests/modal-url-state-test.mjs
```

Run every PHP contract script:

```bash
for file in tests/*.php; do
  php "$file"
done
```

Run every Node contract script:

```bash
for file in tests/*.mjs; do
  node "$file"
done
```

## Safe Edit Workflow

1. Identify the real source owner first.
   - PHP behavior: usually `includes/`
   - renderer markup: `includes/frontend/`
   - JS runtime: `blocks/*/view.js` or `src/`
   - SCSS: `blocks/*/style.scss` or `src/styles/`
2. Make the smallest source-owned change.
3. Rebuild only when the edited source feeds `build/`.
4. Run the narrowest relevant smoke/regression scripts.
5. Verify the runtime chain when behavior depends on built assets:
   - source file
   - build output
   - asset registry
   - renderer/runtime enqueue

## Common Ownership Mistakes

| Mistake | Correct owner |
| --- | --- |
| Editing `build/*` to patch a runtime issue | Edit `blocks/*`, `src/*`, or PHP source and rebuild |
| Treating the theme as the owner of BCI content behavior | The plugin owns BCI content/workflow/rendering; the theme only supplies surrounding visual conventions |
| Changing ACF field names ad hoc | Update the canonical schema in `includes/config/class-settings-schema.php` or `includes/content-model/class-schema.php` |
| Adding a new runtime handle directly in a renderer | Register it centrally in `includes/assets/class-registry.php` |
| Hard-coding opportunity type labels in frontend code | Resolve them from taxonomy/config owners |

## Notes on Ignored Paths

The plugin's `.gitignore` currently ignores:

- `node_modules`
- `build`
- `.DS_Store`
- `.codex`

That means documentation or source changes will show up normally, but regenerated build output will not.
