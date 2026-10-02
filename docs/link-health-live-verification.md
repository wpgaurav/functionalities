# Link Health live scanning and result pagination

Gatilab's scan was advancing on its external cron schedule while the settings page displayed a static snapshot. The site had `DISABLE_WP_CRON` enabled, 1,161 eligible posts/pages, and only four link checks per scheduled batch. The report also paginated by one source post, producing short or empty-looking result pages. A separate stop-control regression rejected Stop while a worker held its lease.

## Change

- The open, visible admin workspace advances one bounded batch at a time through a nonce- and capability-protected endpoint. It monitors existing workers instead of overlapping them. Hidden tabs pause browser-driven work; the background schedule remains available.
- A live state label, activity indicator, progress bar, completed-post count, checked-link count, and refreshed results distinguish waiting, checking, stopping, stopped, completed, disabled, and error states.
- Stop is queued until the current bounded batch finishes. Resume preserves the run and cursor. Generation checks prevent a stale browser from advancing or stopping a different scan. Late client responses cannot overwrite Stop, and failed Stop requests do not restart browser-driven work.
- Results paginate across sources in groups of 50. A small per-post count index avoids deserializing every report on each refresh; older candidate reports are backfilled once. Empty reports consume no result rows. The index follows the existing opt-in uninstall cleanup.
- The results table uses the full module width and scrolls within its container on narrow screens. Native forms, CSV export, and manual one-batch fallback remain available.
- `readme.txt` now describes 1.7.0, live scanning, pagination, scan limits, optional scheduling, and the actual storage locations. Unsupported blanket performance/compatibility claims were tightened.

## Verification

- The stop-during-worker and stale-browser regressions failed on the unfixed code, then passed with the fix.
- PHP syntax, coding standards/PHP 7.4 compatibility, and 158 PHP tests / 844 assertions passed. Tests cover worker cancellation, expired cancellation, saved-cursor resume, private progress fields, admin/nonce rejection, and complete 50/50/20 pagination across 120 fixture results with private sources excluded.
- All 13 JavaScript tests passed, including five live-scanner tests for bounded work, result refresh, late-response cancellation, failed Stop handling, other workers, tab visibility, and expired authorization.
- Gatilab: the visible page advanced from 88 to 176 checked links without a reload. Stop settled at 180 links and 30 completed posts; its background event was cleared. Resume preserved progress and advanced to 380 links and 88 posts during the next observation.
- The second result page contained 50 rows, covered results 51–100, and had no overlap with the first page. The URL uses a single result-page parameter.
- At 390px, the page had no horizontal overflow, the back button was 44px high, and the wide table scrolled inside its own container.
- An anonymous request to the live endpoint was rejected with HTTP 400 and no handler execution. Administrator/nonce negative paths are also covered in PHP tests.
- All 147 installed runtime files matched the verified ZIP. The 19 module option arrays and all 1,203 existing post/page content, type, and status hashes matched the pre-deployment snapshot. Scan reports and their count metadata advanced as intended.
- The submenu alias loaded the live-scanner asset and the same 50-result layout. A real workspace screenshot was added to the WordPress.org assets and readme.

Recovery files are retained in `/home/gatilab/functionalities-live-links-20261002`: the prior plugin and protected-data archives, settings/source-hash snapshot, candidate ZIP, and verification receipts. The original user-started scan and existing reports were retained throughout testing.
