# Dynamic Functionalities Roadmap

All planned fixes and features below target **v1.4.8**. GitHub issues are the
source of truth for scope and acceptance criteria. Work can land in phases, but
the v1.4.8 release should not be tagged until every listed issue is complete.

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

## v1.4.8 release gate

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
