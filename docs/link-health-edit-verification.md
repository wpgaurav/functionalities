# Link Health filtering and source edits

The report now filters by effective result status, source type, and URL/source-title text before applying 50-row pagination. Applied filters survive live refreshes, page navigation, and CSV export. Filtered reads use bounded source batches and read only Link Health metadata. The unfiltered count-index path remains available.

Each report row offers Replace URL and Unlink for users who can edit its source post. A preview identifies that post and counts matching anchors. The apply token is bound to the current actor, operation, URLs, and source fingerprint, and expires after ten minutes. Apply rechecks permissions and source state; a binary compare-and-swap prevents overwriting a save that arrives after validation. WordPress revisions, cache invalidation, and post-save notifications are preserved. New destinations appear unchecked; unchanged URLs retain their earlier results.

The HTML API targets anchor tokens without reserializing surrounding block comments, scripts, or markup. Replacement preserves original fragments unless explicitly replaced. Unlink retains child text/media and preserves native button structure. Ambiguous target markup and URLs duplicated in serialized block settings are rejected with a post-editor fallback. The small bookmark adapter handles WordPress 6.3's inclusive end offset and newer core's length representation, with coverage against both implementations. URL inputs retain percent encoding instead of passing through text sanitization that would remove encoded bytes.

## Verification

- PHP syntax, WordPress coding standards/PHP 7.4 compatibility, and 167 PHP tests / 892 assertions passed. The 39 utility-module tests also passed against newer local core.
- All 18 JavaScript tests passed. New coverage includes filter preservation, report refresh suspension while editing, invalidating changed/cancelled previews, literal source titles, late responses, and applying only a reviewed token.
- PHP cases cover filtering before pagination, source types, ignored status, literal zero searches, percent encoding, fragments, quoted attributes, raw scripts, inline media, native buttons, unclosed links, actor permissions, stale previews, single-use application, failed writes, and a concurrent save arriving after validation.
- Gatilab's real report returned 200 broken links. Page two contained 50 broken rows and preserved the filter; CSV export contained exactly those 200 broken results. Combining Broken with Pages returned one matching result. A fixture title search completed in approximately 0.37 seconds against the retained report set.
- On the temporary public fixture, Replace updated three matching anchors and retained their distinct fragments. Unlink then preserved strong text, the image, button structure, block types, and the unrelated anchor. Readback found three WordPress revisions and a reconciled report containing only the remaining URL. The anonymous frontend output matched these changes.
- A further fixture replacement preserved `link%20editor` through the browser request and stored content.
- At 390px, the dialog measured 342px, with no page overflow. Switching an invalid replacement input to Unlink disabled the hidden destination field and successfully produced a preview.
- After removing the verified fixtures and their revisions, all 19 module settings, all 1,203 existing post/page content/type/status hashes, and all existing link-report metadata matched the snapshot. The original completed scan retained 1,161 posts and 3,500 link checks. All 150 deployed runtime files matched the package.

## Visual-check limitation

Gutenberg's iframe canvas remained empty in the test browser on both the edited fixture and an untouched control containing the original markup. No successful canvas-validation claim is made. Stored block structure, exact surrounding markup, revisions, and public frontend output were verified independently. The existing editor-iframe visual release gate remains applicable.

Recovery and private receipts are under `/home/gatilab/functionalities-link-editor-20261002`. Only the temporary fixture and control were edited or deleted during live content testing; cleanup validates their expected content before removal.
