# v1.7.0 release-fix verification

The release review findings are covered by regression tests and a Gatilab verification pass. The checks below cover the v1.7.0 release contents.

## Corrected behavior

- Link edits defer File-block unlinks, bound URLs, and serialized block settings to the post editor. Valid block-comment whitespace cannot bypass the guard; safety checks inspect beyond the scan limit.
- Source fingerprints and edit revisions prevent a partial scan or overlapping worker from restoring an obsolete link offset.
- Published-post edits dispatch WordPress's status transition and edit/save hooks, including feed-cache invalidation.
- Recheck, Ignore, and reconciliation read current post/metadata caches under the shared lock. Native controls require the displayed scan generation; AJAX uses its live generation rather than a stale hidden field.
- Filtered CSV exports traverse each source once. The 100,000-link synthetic benchmark fell from 162,000 queries to 81, retaining every row. This benchmark uses production report code with a zero-latency simulated database.
- Site Activity uses final per-item installation results, distinguishes manual AJAX skins from automatic skins, and waits for automatic rollback outcomes.
- The JSON preset field has a translated accessible label.
- Components enqueue a real editor stylesheet handle with inline rules through `enqueue_block_assets`, so the canvas does not depend on generated uploads CSS or the settings-style channel.

## Automated checks

- `composer check` with the real WordPress 6.3 HTML API: 180 tests, 6,978 assertions; syntax, coding standards, and PHP compatibility checks passed.
- The 51 utility tests also passed with WordPress 6.9 core; the five asset tests passed on both core versions.
- `npm test`: 19 tests passed.
- Original defects were reproduced before changes, including nine failures in an isolated copy of `f85d308` with the new tests. Cache races, the native/AJAX generation interaction, and canvas asset delivery each received explicit failing-then-passing checks.
- Translation freshness, package required-file checks, and whitespace checks passed. The runtime package contains 151 files; development fixtures remain excluded.

## Gatilab runtime checks

Tested on WordPress 7.1.2 and PHP 8.5.10. Original content and settings were snapshotted before installing the candidate. Only disposable fixtures were edited.

A six-link fixture was scanned for four links, stopped, unlinked, replaced through the browser's reviewed edit flow, and resumed. Every remaining URL was checked before completion. A deliberately cached feed timestamp was invalidated. Parallel real WordPress requests retained both recheck results and both Ignore choices. Browser filters and downloaded CSV agreed on the two ignored fixture rows. Final upgrade callbacks were exercised with real WordPress automatic and manual AJAX skin classes, without updating unrelated installed plugins.

Editor checks were completed in Brave Origin. The configured variable font appeared in the picker and canvas with a normalized `100 1000` face. SVG Icon transformed to Core Icon and back with its SVG and 48px size preserved. Component styles were verified with generated CSS present and with generation deliberately unavailable through a temporary QA-only path filter; the badge retained its background and padding in both cases.

The temporary path filter and both fixtures were removed. Original module options, link report metadata, and scan state matched the deployment snapshot after cleanup. Concurrent, unrelated content edits were preserved; cleanup did not restore a database over other changes. Existing activity history was retained, and update-outcome test events used isolated private storage.

Deployment backups and detailed verification receipts are kept outside the repository. Before repeating these checks, snapshot the target site's settings, source content, report metadata, and scan state; scope edits to disposable fixtures; then remove only test-owned records and compare the preserved data.
