# Backend UI and branding polish for 1.7.0

The original branding pass bundled the main-site media item, [Dynamic Functionalities icon](https://r2.gauravtiwari.org/wp-content/uploads/2026/09/functionalities-icon-wporg-20260927.svg), matching the WordPress.org SVG master byte for byte. The subsequent [sidebar and navigation pass](ui-sidebar-verification.md) uses a simplified color-adaptive vector in the admin menu and dashboard. Individual module pages retain only their module glyph.

Backend module/action glyphs use a 53-icon subset from `~/Icons/svg/outline` (Tabler). The MIT license is bundled; paths and mappings are recorded in `admin-icon-sources.json`. Controls use WordPress's selected admin accent. Unsupported third-party Dashicons retain their native rendering.

Text/URL/number fields and selects use shared sizing, spacing, borders, focus states, and label alignment: 40px on desktop and 44px at phone width. Legacy component/font groups gain associated labels, including dynamically added groups. PWA repeaters have native visible labels and equal-width columns. Font upload rows reflow on phones. The activity search field is explicitly a search control. Fourteen older metadata/threshold fields gain clear accessible labels, and the legacy redirect selector includes the already-supported 308 status.

The sidebar and dashboard/alias routes load the same assets, including PWA controls. Backend-only styles remain scoped; product settings, option names, public output, and module enablement are unchanged. Edited backend styles/scripts use a file timestamp in their cache key for candidate testing.

## Verification

- PHP lint, WordPress coding standards/PHP compatibility, and all 149 PHP tests passed.
- All eight JavaScript tests and syntax checks passed.
- Translation regeneration/freshness and distribution packaging passed.
- All 53 local SVGs parsed, and the editable banner passed strict SVG validation.
- The banner and icon were rendered and visually inspected at their WordPress.org delivery sizes. Banners now describe 19 optional modules. Directory screenshots were refreshed from the real backend, with the surrounding account navigation cropped out.
- Gatilab (WordPress 7.1.2/PHP 8.5.10): all 19 screens were inspected at desktop width. No page overflow or unlabelled visible value controls remained after initialization.
- Seven dense screens plus the dashboard were checked at 390px. Controls reflowed without page overflow. Expanded fonts and dynamically added PWA rows had aligned, labelled controls and 44px phone targets.
- Existing font/component edit panels were opened for inspection; no settings form was saved. Dynamically added PWA rows were discarded by navigation.
- The 19 module option arrays and homepage content/metadata matched the pre-polish snapshot (21 checks).

The plugin and snapshot recovery files are retained on Gatilab in `/home/gatilab/functionalities-ui-20261002`. The public 1.7.0 release and WordPress.org asset deployment remain separate from this candidate.
