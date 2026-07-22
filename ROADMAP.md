# Dynamic Functionalities Roadmap

This roadmap favors reliability and measurable performance before adding more
surface area. GitHub issues are the source of truth for scope and acceptance
criteria; this file explains the intended release sequence.

## v1.4.8: Stability and quality gates

- Fix SVG Icons so a fresh install leaves the module disabled until the user
  enables it, matching the explicit-activation policy introduced in v1.2.0.
- Correct README duplication, module coverage, and performance claims.
- Make Task Manager and Redirect Manager JSON updates atomic and concurrency-safe
  ([#41](https://github.com/wpgaurav/functionalities/issues/41)).
- Add pull-request CI, supported-PHP checks, distribution validation, coding
  standards, and a minimal test foundation
  ([#42](https://github.com/wpgaurav/functionalities/issues/42)).

Release gate: automated checks pass across the supported PHP range, concurrent
file updates cannot lose data, and the manual block-editor iframe smoke test in
`CLAUDE.md` passes in a real WordPress install.

## v1.5.0: Architecture and portability

- Introduce a true lazy module registry so disabled feature classes are not
  loaded on frontend requests
  ([#43](https://github.com/wpgaurav/functionalities/issues/43)).
- Split the monolithic Admin class into module controllers and move remaining
  inline assets into versioned files
  ([#44](https://github.com/wpgaurav/functionalities/issues/44)).
- Add validated, versioned settings export/import plus a redacted diagnostics
  bundle ([#45](https://github.com/wpgaurav/functionalities/issues/45)).

Release gate: existing settings and admin URLs remain backward compatible, a
configuration round-trips between clean sites, and benchmarks show the lazy
registry reduces included files without increasing option queries.

## v1.6.0: Operational workflows

- Add CSV redirect migration and a bounded, privacy-conscious 404 monitor
  ([#46](https://github.com/wpgaurav/functionalities/issues/46)).
- Integrate Assumption Detection and Content Integrity with Site Health,
  scheduled scans, deduplicated notifications, and useful snapshot diffs
  ([#47](https://github.com/wpgaurav/functionalities/issues/47)).

Release gate: monitoring is opt-in, capped, and cheap on frontend requests;
notifications are rate-limited; imports are validated and all-or-nothing.

## Planning rules

- Fix security, data loss, and compatibility regressions before feature work.
- Every feature needs a disabled-by-default path with no frontend assets.
- Persisted-data changes need migration, rollback, concurrency, and uninstall
  coverage.
- New admin UI must reuse WordPress patterns and remain keyboard accessible.
- A release is not complete until the GitHub artifact, WordPress.org package,
  version metadata, and manual editor smoke test agree.
