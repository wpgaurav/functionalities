# Module navigation, SVG mark, and settings sidebar

The backend mark is an original monoline simplification of the official four-module platform graphic. `assets/brand/functionalities-admin.svg` contains editable vector paths, with no embedded raster image. CSS masks inherit WordPress menu colors, including hover/current states. The dashboard uses the same geometry in its admin accent color. The [colorful listing icon and bento banner](branding-assets.md) reuse this silhouette for WordPress.org.

Every module header includes a Back to modules button and a semantic breadcrumb with the current module. Task Manager also retains project ancestry. Individual settings pages show their module glyph without repeating the product mark. The navigation arrow comes from `~/Icons/svg/outline/arrow-left.svg`; the bundled Tabler subset now contains 54 icons with its MIT license.

The shared PHP renderer collects documentation separately from the complete settings form. Informational guidance is placed in a right sidebar at 1180px and above; it stacks after settings on narrower screens. Native form ownership, hidden fields, nonce fields, and submission targets are preserved. Active warnings, scan status, recent lockout actions, and result controls remain in the main area. Tables scroll within their own container when necessary. Container queries stack dense form rows when the main column becomes narrow.

## Verification on Gatilab

- WordPress 7.1.2 / PHP 8.5.10, with the 1.7.0 candidate active.
- All 19 module pages: current breadcrumb item and back button present; no product mark in individual headers; no nested forms, orphan named controls, sidebar controls, or page overflow.
- Link Health's direct submenu alias also includes its current breadcrumb. Back to modules was clicked and returned to all 19 dashboard cards.
- Schema, Link Health, Site Activity, Components, Fonts, SVG Icons, and Header & Footer were checked at 1280px, 1024px, and 390px. The guide appeared on the right at 1280px and below content at 1024px/390px. Visible value controls were at least 40px on desktop and 44px on phones; the phone back button was 44px high.
- Schema's 11 value/checkbox controls matched their pre-deployment values and names.
- The SVG was rendered at 22px and 64px on light/dark surfaces and passed strict validation. Actual WordPress Light and Midnight menu previews confirmed inherited icon colors and visible contrast. Profile changes were discarded; the original Default scheme was restored.
- Three layout regression tests verify guidance outside the complete form, warnings remaining in the form, no empty sidebar, standalone rendering, and buffer/state restoration after exceptions. The layout is server-rendered and does not require JavaScript.
- PHP lint, WordPress coding standards/PHP 7.4 compatibility, and 152 PHP tests / 810 assertions passed. All eight JavaScript tests passed. Translation freshness and distribution checks passed.
- All 19 module option arrays and homepage content/metadata matched the snapshot, for 21 unchanged-state checks. Installed runtime hashes matched the verified ZIP's 146 files.

Recovery files are retained on Gatilab in `/home/gatilab/functionalities-sidebar-20261002`. Screenshots and JSON receipts are saved with the local UI verification artifacts. The public release and WordPress.org asset deployment remain separate from this candidate.
