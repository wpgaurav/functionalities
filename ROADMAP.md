# Dynamic Functionalities Roadmap

Version **1.6.0** is a security and correctness release built on the v1.5.0 WordPress 7 work. The
release is tagged only after automated checks and the real-WordPress smoke gate pass.

## v1.6.0: security, storage, and the HTML API

- Give every Abilities API operation its own permission callback and a strict input schema.
- Move redirect, 404, and task data into a private directory, migrate existing files, and add a Site
  Health probe that confirms over HTTP that it is unreachable.
- Filter header and footer snippets at save time only, so logged-out visitors receive them intact.
- Cache a remote JSON exception preset and invalidate it when a post or page is edited.
- Replace DOMDocument with the WordPress HTML API in Link Management, Block Cleanup, and Schema, and
  delete the JS-framework skip guard it required.
- Buffer redirect hits and 404 aggregates instead of rewriting JSON per request; run redirects at
  `parse_request`.
- Cache the Content Integrity list column, and keep the SVG icon library out of the autoload set.
- Harden the service worker: no admin, login, REST, cross-origin, or private responses; capped runtime
  cache; per-URL precache.
- Add username throttling, an IP allowlist, and an unlock action to Login Security.
- Bundle Prism.js instead of loading it from a CDN.
- Generate a real translation template and stop shipping build sources in the zip.

## v1.6.0 release gate

- No ability can be reached without the capability its operation implies.
- Plugin data files return a non-200 to an anonymous request on Apache, nginx, and IIS.
- A snippet containing `&&` renders byte-identical for an anonymous visitor and an administrator.
- A page using Vue or Alpine passes through all three content filters unchanged.
- Existing option names, hooks, admin URLs, shortcodes, and stored JSON remain compatible, and data
  written by earlier versions is migrated on first load.

## v1.5.0: WordPress 7 platform integration (completed)

- Upgrade the SVG Icon block to Block API v3.
- Add a WordPress Core Icon source without giving up the existing custom icon
  library.
- Support two-way transforms with `core/icon`, block bindings, synced-pattern
  overrides, and an Icon Callout pattern.
- Expose permissioned, schema-validated operations through the Abilities API.
- Add DataViews and DataForm workspaces for redirects and tasks with classic UI
  fallbacks.
- Add Command Palette actions for common navigation and scans.
- Add explicitly opt-in finding explanations through the WordPress AI Client.
- Keep every WordPress 7-only integration feature-detected and free of frontend
  assets.

## v1.4.8: Completed reliability foundation

## Phase 1: Stabilize the foundation

- Fix SVG Icons so a fresh install leaves the module disabled until the user
  enables it, matching the explicit-activation policy introduced in v1.2.0.
- Correct README duplication, module coverage, and performance claims.
- Make Task Manager and Redirect Manager JSON updates atomic and concurrency-safe
  ([#41](https://github.com/wpgaurav/functionalities/issues/41)).
- Add pull-request CI, supported-PHP checks, distribution validation, coding
  standards, and a minimal test foundation
  ([#42](https://github.com/wpgaurav/functionalities/issues/42)).

## Phase 2: Improve architecture and portability

- Introduce a true lazy module registry so disabled feature classes are not
  loaded on frontend requests
  ([#43](https://github.com/wpgaurav/functionalities/issues/43)).
- Split the monolithic Admin class into module controllers and move remaining
  inline assets into versioned files
  ([#44](https://github.com/wpgaurav/functionalities/issues/44)).
- Add validated, versioned settings export/import plus a redacted diagnostics
  bundle ([#45](https://github.com/wpgaurav/functionalities/issues/45)).

## Phase 3: Add operational workflows

- Add CSV redirect migration and a bounded, privacy-conscious 404 monitor
  ([#46](https://github.com/wpgaurav/functionalities/issues/46)).
- Integrate Assumption Detection and Content Integrity with Site Health,
  scheduled scans, deduplicated notifications, and useful snapshot diffs
  ([#47](https://github.com/wpgaurav/functionalities/issues/47)).

## v1.4.8 release gate (completed)

- All seven roadmap issues are closed through reviewed changes.
- Automated checks pass across the supported PHP and WordPress range.
- Concurrent file updates cannot lose redirects, tasks, or hit counts.
- Existing settings, hooks, admin URLs, and stored data remain compatible.
- Settings can round-trip between clean sites without exposing sensitive data.
- Monitoring is opt-in, capped, rate-limited, and cheap on frontend requests.
- Redirect imports are validated, previewable, and all-or-nothing.
- The manual block-editor iframe smoke test in `CLAUDE.md` passes in a real
  WordPress install.

## Planning rules

- Fix security, data loss, and compatibility regressions before feature work.
- Every feature needs a disabled-by-default path with no frontend assets.
- Persisted-data changes need migration, rollback, concurrency, and uninstall
  coverage.
- New admin UI must reuse WordPress patterns and remain keyboard accessible.
- A release is not complete until the GitHub artifact, WordPress.org package,
  version metadata, and manual editor smoke test agree.
