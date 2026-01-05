/**
 * SVG Icons Block Editor Integration
 *
 * Registers a RichText format for inserting SVG icons inline in the block editor.
 * Based on the md-icons-gutenberg plugin pattern.
 *
 * @package Functionalities
 * @since 0.11.0
 */

(function(wp) {
	'use strict';

	var registerFormatType = wp.richText.registerFormatType;
	var toggleFormat = wp.richText.toggleFormat;
	var insert = wp.richText.insert;
	var create = wp.richText.create;
	var Fragment = wp.element.Fragment;
	var createElement = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var Popover = wp.components.Popover;
	var TextControl = wp.components.TextControl;
	var IconButton = wp.components.Button;
	var Tooltip = wp.components.Tooltip;
	var RichTextToolbarButton = wp.blockEditor.RichTextToolbarButton;
	var __ = wp.i18n.__;

	// Get icons data from localized script.
	var iconsData = window.functionalitiesSvgIcons || { icons: [], i18n: {} };
	var allIcons = iconsData.icons || [];
	var i18n = iconsData.i18n || {};

	/**
	 * IconMap Component - Handles the icon picker UI
	 */
	function IconMap(props) {
		var isActive = props.isActive;
		var value = props.value;
		var onChange = props.onChange;

		var _useState = useState(false);
		var isOpen = _useState[0];
		var setIsOpen = _useState[1];

		var _useState2 = useState('');
		var keyword = _useState2[0];
		var setKeyword = _useState2[1];

		var _useState3 = useState(allIcons);
		var icons = _useState3[0];
		var setIcons = _useState3[1];

		// Filter icons when keyword changes
		useEffect(function() {
			if (!keyword) {
				setIcons(allIcons);
				return;
			}
			var term = keyword.toLowerCase();
			var filtered = allIcons.filter(function(icon) {
				return icon.name.toLowerCase().indexOf(term) !== -1 ||
					   icon.slug.toLowerCase().indexOf(term) !== -1;
			});
			setIcons(filtered);
		}, [keyword]);

		function toggleOpen() {
			setIsOpen(!isOpen);
			if (isOpen) {
				setKeyword('');
			}
		}

		function insertIcon(icon) {
			// Create the icon HTML - use span with data attribute
			var iconHtml = '<span class="func-icon" data-icon="' + icon.slug + '" aria-hidden="true"></span>';

			// Insert the icon
			onChange(insert(value, create({ html: iconHtml })));

			// Close popover
			setIsOpen(false);
			setKeyword('');
		}

		// Build the popover content
		var popoverContent = null;
		if (isOpen) {
			var iconButtons = [];

			if (icons.length === 0) {
				iconButtons.push(
					createElement('p', {
						key: 'no-icons',
						style: {
							textAlign: 'center',
							color: '#666',
							padding: '20px',
							margin: 0
						}
					}, allIcons.length === 0
						? (i18n.noIcons || __('No icons found. Add icons in Functionalities > SVG Icons.', 'functionalities'))
						: __('No matching icons found.', 'functionalities')
					)
				);
			} else {
				icons.forEach(function(icon) {
					iconButtons.push(
						createElement(Tooltip, { key: icon.slug, text: icon.name },
							createElement('button', {
								type: 'button',
								className: 'func-icon-btn',
								onClick: function() { insertIcon(icon); },
								'aria-label': icon.name,
								dangerouslySetInnerHTML: { __html: icon.svg }
							})
						)
					);
				});
			}

			popoverContent = createElement(Popover, {
				position: 'bottom center',
				onClose: function() {
					setIsOpen(false);
					setKeyword('');
				},
				className: 'func-svg-icon-popover',
				focusOnMount: 'firstElement'
			},
				createElement('div', { className: 'func-svg-icon-picker' },
					createElement(TextControl, {
						type: 'search',
						value: keyword,
						onChange: setKeyword,
						placeholder: i18n.searchIcons || __('Search icons...', 'functionalities'),
						className: 'func-icon-search'
					}),
					createElement('div', { className: 'func-icon-grid' }, iconButtons)
				)
			);
		}

		return createElement(Fragment, null,
			createElement(RichTextToolbarButton, {
				icon: createElement('svg', {
					xmlns: 'http://www.w3.org/2000/svg',
					viewBox: '0 0 24 24',
					width: 24,
					height: 24
				},
					createElement('path', {
						fill: 'currentColor',
						d: 'M14.5 2.5c4.95 0 9 4.05 9 9s-4.05 9-9 9c-1.73 0-3.35-.5-4.72-1.35L3.5 21.5l2.35-6.28C4.85 13.85 4.5 12.23 4.5 10.5c0-4.95 4.05-9 9-9zm0 2c-3.87 0-7 3.13-7 7 0 1.47.46 2.83 1.23 3.96l.26.38-.93 2.48 2.48-.93.38.26c1.13.77 2.49 1.23 3.96 1.23 3.87 0 7-3.13 7-7s-3.13-7-7-7z'
					})
				),
				title: i18n.insertIcon || __('Insert Icon', 'functionalities'),
				onClick: toggleOpen,
				isActive: isOpen
			}),
			popoverContent
		);
	}

	// Register the format type when DOM is ready
	wp.domReady(function() {
		registerFormatType('functionalities/svg-icon', {
			title: i18n.insertIcon || __('Insert Icon', 'functionalities'),
			tagName: 'span',
			className: 'func-icon',
			edit: IconMap
		});
	});

})(window.wp);
