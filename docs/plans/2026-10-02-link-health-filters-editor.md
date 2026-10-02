# Link Health filters and source-link editing

Add status, source-type, and URL/source-title search filters. Apply filters before the existing 50-row pagination, preserve them across live refreshes and navigation, and allow CSV export of the same selection. Keep the existing fast count-index path for unfiltered reports; filtered reads load report metadata in bounded chunks rather than all post metadata.

Each row gains Replace URL and Unlink actions scoped to its source post. The editor previews the occurrence count and source title, then requires Apply. Preview tokens belong to the current administrator, expire, and bind the exact operation, source URL, replacement, and current content fingerprint. Applying revalidates edit_post permission, public-source membership, current content, and token ownership.

Use the WordPress HTML API for exact anchor targeting. Replacement changes href attributes only, retaining fragments unless the new URL supplies one. Unlink removes matching anchor wrappers while keeping their inner content; native button anchors retain their structural element with navigation attributes removed. Use HTML API bookmark offsets only through a small compatibility adapter tested against the minimum and current core implementations. Refuse ambiguous/unclosed target anchors.

Save through a compare-and-swap source update, preserve revisions when enabled, clear post caches, fire normal post-save notifications, and reconcile report rows after the edit. Existing unchanged URLs retain their results; newly introduced URLs appear unchecked. Report refreshes pause while the edit dialog is open so input and review state are not replaced. Editing a preview invalidates it; late responses cannot re-enable Apply for different inputs.

Verify filtering across sources, pagination, ignored statuses, URL entities/fragments, block comments, inline markup, scripts, native buttons, stale previews, actor permissions, one-use application, source-write failure, and changes during save. On Gatilab, modify only dedicated temporary fixtures and preserve existing user content/settings.
