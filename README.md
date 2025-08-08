# Functionalities (WordPress Plugin)

A modular site-specific plugin to organize common features with simple toggles.

## Features

- Safe plugin bootstrap with constants
- Example shortcode: `[functionalities_hello name="World"]`
- Asset enqueues for CSS/JS
- Link Management: add rel="nofollow" to external links with exceptions list
- Block Cleanup: strip common wp-block classes from frontend output
- Editor Link Suggestions: limit suggestions to selected post types
- Miscellaneous: toggle common WordPress optimizations and head cleanups
- Header & Footer: inject GA4 via Measurement ID and custom code safely
- Schema Settings: add itemscope/itemtype to html, header/footer, and article
- Components: define reusable CSS components (class + CSS) and auto-enqueue

## Installation

1. Copy this folder `functionalities/` into your WordPress `wp-content/plugins/` directory.
2. In wp-admin, go to Plugins and activate "Functionalities".
3. Optional: Add the shortcode to a post or page: `[functionalities_hello name="Alice"]`.

### Settings

- Link Management: enable nofollow for external links and manage exceptions by full URL, domain, or partial match (one per line).
- Block Cleanup: strip `.wp-block-heading`, `.wp-block-list`, and `.wp-block-image` classes from content.
- Editor Link Suggestions: restrict results to chosen post types in search dialogs.
- Miscellaneous: toggle emojis, embeds, REST/RSD/WLW links, shortlink, generator meta, XML-RPC, feeds, dashicons for guests, heartbeat, admin bar, block widgets, separate block assets, etc.
- Header & Footer: enable GA4 (enter Measurement ID, e.g., G-XXXXXXX), and/or add custom header/footer snippets.
- Schema Settings: choose site `itemtype` (default WebPage), optionally mark header/footer (WPHeader/WPFooter), and article (Article/BlogPosting/NewsArticle). Optionally set itemprops for headline, dates, and author.
- Components: enable and manage a list of components. Each has a CSS selector (e.g., `.c-card`) and CSS rules. Default set includes cards, buttons, accordions, marquee, etc. All component CSS is auto-enqueued on the frontend.

## Development

- Edit PHP in `includes/` and `functionalities.php`.
- CSS/JS live in `assets/`.

### Notes

- Some PHP/WP functions may appear undefined in static analysis outside WordPress. They work at runtime within WordPress.

## Localization

- Text domain: `functionalities`
- Language files path: `languages/`

## License

GPL-2.0-or-later
