# Colorful listing assets for 1.7.0

The listing icon uses the same four modular tiles and two platform layers as `assets/brand/functionalities-admin.svg`. Its four tiles are violet, teal, orange, and blue; the platform uses two blue tones. An inset pale tile with transparent rounded corners keeps it recognizable on both light and dark listing surfaces. The admin UI continues to use its color-adaptive monochrome mark.

The bento banner groups real product features beside the product name: Content Tools, Link Health, reusable fonts/styles, and Site Activity. The 19-module count comes from the current module registry. Colored surfaces are amber, mint, lavender, and pale blue, with a navy product tile. Heading typography uses Really Sans Large at 800; supporting copy uses Finlandica Text. Module glyphs use the bundled Tabler outline paths and existing MIT license.

## Files

| File in `.wordpress-org/` | Purpose | Pixels |
|---|---|---|
| `icon.svg` | Editable, scalable colorful listing icon | 256-unit square |
| `icon-128x128.png` | Standard listing fallback | 128 x 128 |
| `icon-256x256.png` | Retina listing fallback | 256 x 256 |
| `banner.svg` | Editable bento banner master | 1544 x 500 units |
| `banner-772x250.png` | Standard listing banner | 772 x 250 |
| `banner-1544x500.png` | Retina listing banner | 1544 x 500 |

The exact filenames, pixel dimensions, and SVG-plus-PNG fallback follow the [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/). The banner master has no embedded raster data, remote resources, or font files; its PNG exports preserve the installed brand typography independently of fonts on the viewer's device.

## Verification and reproduction

Both SVG masters passed the strict SVG validator. The banner was checked at its actual 772px display width with an 11px minimum text floor. PNG signatures, exact dimensions, and WordPress file-size limits passed; the icon was inspected at both 128px and 256px, and the banner at both delivery sizes. All four PNGs together are under 165KB. The original outline paths were retained, with the combined platform path split only to assign its two colors.

Render from the editable masters using `rsvg-convert`, with the brand fonts installed:

```sh
rsvg-convert -w 128 -h 128 .wordpress-org/icon.svg -o .wordpress-org/icon-128x128.png
rsvg-convert -w 256 -h 256 .wordpress-org/icon.svg -o .wordpress-org/icon-256x256.png
rsvg-convert -w 772 -h 250 .wordpress-org/banner.svg -o .wordpress-org/banner-772x250.png
rsvg-convert -w 1544 -h 500 .wordpress-org/banner.svg -o .wordpress-org/banner-1544x500.png
```

These are prepared release assets. Public WordPress.org deployment is handled by the release workflow separately from the tested Gatilab runtime candidate.
