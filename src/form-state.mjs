/** Preserve edits made while a creation request is in flight. */
export function resetSubmittedFields( current, submitted, cleared ) {
	if ( ! Object.keys( submitted ).every( ( key ) => current[ key ] === submitted[ key ] ) ) {
		return current;
	}
	return { ...current, ...cleared };
}
