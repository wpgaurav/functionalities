# Liquid-glass listing assets for 1.7.0

The WordPress listing icon and bento banner use the approved four-color modular-platform identity with restrained, Apple-inspired Liquid Glass styling: thin translucent surfaces, shallow relief, fine edge highlights, soft ambient shadows, and gentle refraction. The four logo tiles retain softer violet, teal, amber, and blue identities above two blue platform layers. Transparent corners remain around the near-white icon tile.

The bento banner retains the product name, 19-module count, and real feature groups: Content Tools, Link Health, reusable fonts/styles, and Site Activity. A light pearl product panel and nearly clear feature panels sit over a soft amber, mint, lilac, and blue background wash. Dark text, calmer heading weight, and crisp monoline glyphs keep the content primary. All copy was visually checked after generation and again at the actual 772px listing width. The material direction follows [Apple's Liquid Glass reference](https://www.apple.com/newsroom/2025/06/apple-introduces-a-delightful-and-elegant-new-software-design/); these are static marketing images, not an implementation of Apple's rendering APIs.

The materials were rendered with Codex's built-in image-generation tool from the approved flat listing assets. The glass icon was then provided as the material and identity reference for the banner. Native selected renders and exact prompts are preserved in `docs/branding-sources/`; the earlier editable flat SVGs remain there as identity/layout references. The runtime admin mark remains the color-adaptive vector at `assets/brand/functionalities-admin.svg`.

## Listing exports

| File in `.wordpress-org/` | Purpose | Pixels |
|---|---|---|
| `icon-128x128.png` | Standard listing icon | 128 x 128 |
| `icon-256x256.png` | Retina listing icon | 256 x 256 |
| `banner-772x250.png` | Standard bento banner | 772 x 250 |
| `banner-1544x500.png` | Retina bento banner | 1544 x 500 |

The exact filenames and pixel dimensions follow the [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/). Listing SVGs were moved into the source archive so WordPress does not prefer the earlier flat SVG over the new glass PNGs. The configured 10up deployment action syncs its assets directory with deletion, so the superseded listing SVG is removed during the next public release.

## Verification and reproduction

- Inspected both native renders, both icon delivery sizes, and both banner sizes. The icon was also composited on white and dark backgrounds for alpha/edge inspection.
- PNG signatures, exact dimensions, transparent icon corners, and WordPress file-size limits passed. Icons are below 81KB and banners below 685KB.
- The native banner is 2203 x 714; the native icon is 1254 x 1254. Listing exports are downsampled, with no enlargement. The banner's native ratio differs from the exact listing ratio by less than 0.1%.
- The brand silhouette and all feature labels remain recognizable. Generation adds material detail; the archived flat SVGs remain the editable geometry and typography references rather than being presented as the source of the raster glass effects.
- No runtime PHP, JavaScript, CSS, settings, or frontend behavior changed in this asset pass.

Export from the selected native renders with ImageMagick:

```sh
magick docs/branding-sources/icon-liquid-glass.png -filter Lanczos -resize 128x128 .wordpress-org/icon-128x128.png
magick docs/branding-sources/icon-liquid-glass.png -filter Lanczos -resize 256x256 .wordpress-org/icon-256x256.png
magick docs/branding-sources/banner-liquid-glass.png -filter Lanczos -resize 772x250! .wordpress-org/banner-772x250.png
magick docs/branding-sources/banner-liquid-glass.png -filter Lanczos -resize 1544x500! .wordpress-org/banner-1544x500.png
```

These are prepared release assets. Public WordPress.org deployment remains separate from the tested Gatilab runtime candidate.
