/**
 * SVG Icons Block Editor Integration
 *
 * Registers a RichText format for inserting SVG icons inline in the block editor.
 *
 * @package Functionalities
 * @since 0.11.0
 */

(function() {
	'use strict';

	const { registerFormatType, insert, create } = wp.richText;
	const { Fragment, useState, useEffect } = wp.element;
	const { Popover, TextControl, Button, Tooltip } = wp.components;
	const { RichTextToolbarButton } = wp.blockEditor;
	const { __ } = wp.i18n;

	// Get icons data from localized script.
	const iconsData = window.functionalitiesSvgIcons || { icons: [], i18n: {} };
	const icons = iconsData.icons || [];
	const i18n = iconsData.i18n || {};

	/**
	 * Icon Picker Component
	 */
	function IconPicker({ isActive, value, onChange }) {
		const [isOpen, setIsOpen] = useState(false);
		const [searchTerm, setSearchTerm] = useState('');
		const [filteredIcons, setFilteredIcons] = useState(icons);

		// Filter icons based on search term.
		useEffect(() => {
			if (!searchTerm) {
				setFilteredIcons(icons);
				return;
			}
			const term = searchTerm.toLowerCase();
			const filtered = icons.filter(icon =>
				icon.name.toLowerCase().includes(term) ||
				icon.slug.toLowerCase().includes(term)
			);
			setFilteredIcons(filtered);
		}, [searchTerm]);

		/**
		 * Insert an icon into the editor.
		 */
		function insertIcon(icon) {
			// Create HTML for the icon placeholder.
			// This will be replaced with actual SVG on frontend rendering.
			const iconHtml = '<span class="func-icon" data-icon="' + icon.slug + '"></span>';

			// Create a rich text object from the HTML.
			const iconRichText = create({ html: iconHtml });

			// Insert into the current position.
			onChange(insert(value, iconRichText));

			// Close the popover.
			setIsOpen(false);
			setSearchTerm('');
		}

		return (
			<Fragment>
				<RichTextToolbarButton
					icon={
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
							<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="currentColor"/>
						</svg>
					}
					title={i18n.insertIcon || __('Insert Icon', 'functionalities')}
					onClick={() => setIsOpen(!isOpen)}
					isActive={isOpen}
				/>
				{isOpen && (
					<Popover
						position="bottom center"
						onClose={() => {
							setIsOpen(false);
							setSearchTerm('');
						}}
						className="func-svg-icon-popover"
						focusOnMount="firstElement"
					>
						<div className="func-svg-icon-picker" style={{
							padding: '12px',
							minWidth: '280px',
							maxWidth: '320px'
						}}>
							<TextControl
								type="search"
								value={searchTerm}
								onChange={setSearchTerm}
								placeholder={i18n.searchIcons || __('Search icons...', 'functionalities')}
								style={{ marginBottom: '12px' }}
							/>

							{filteredIcons.length === 0 ? (
								<p style={{
									color: '#757575',
									fontSize: '13px',
									textAlign: 'center',
									margin: '20px 0'
								}}>
									{icons.length === 0
										? (i18n.noIcons || __('No icons found. Add icons in Functionalities > SVG Icons.', 'functionalities'))
										: __('No matching icons found.', 'functionalities')
									}
								</p>
							) : (
								<div
									className="func-svg-icon-grid"
									style={{
										display: 'grid',
										gridTemplateColumns: 'repeat(6, 1fr)',
										gap: '4px',
										maxHeight: '200px',
										overflowY: 'auto'
									}}
								>
									{filteredIcons.map(icon => (
										<Tooltip key={icon.slug} text={icon.name}>
											<button
												type="button"
												className="func-svg-icon-button"
												onClick={() => insertIcon(icon)}
												style={{
													display: 'flex',
													alignItems: 'center',
													justifyContent: 'center',
													width: '36px',
													height: '36px',
													padding: '6px',
													border: '1px solid #ddd',
													borderRadius: '4px',
													background: '#fff',
													cursor: 'pointer',
													transition: 'all 0.15s ease'
												}}
												onMouseOver={(e) => {
													e.currentTarget.style.borderColor = '#007cba';
													e.currentTarget.style.background = '#f0f6fc';
												}}
												onMouseOut={(e) => {
													e.currentTarget.style.borderColor = '#ddd';
													e.currentTarget.style.background = '#fff';
												}}
												dangerouslySetInnerHTML={{ __html: icon.svg }}
											/>
										</Tooltip>
									))}
								</div>
							)}
						</div>
					</Popover>
				)}
			</Fragment>
		);
	}

	// Register the format type when DOM is ready.
	wp.domReady(function() {
		registerFormatType('functionalities/svg-icon', {
			title: i18n.insertIcon || __('Insert Icon', 'functionalities'),
			tagName: 'span',
			className: 'func-icon',
			edit: IconPicker
		});
	});

})();
