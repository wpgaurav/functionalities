/**
 * SVG Icons Block Editor Integration
 *
 * Adds an icon picker to the block toolbar for RichText blocks.
 *
 * @package Functionalities
 * @since 0.11.0
 */

(function (wp) {
	'use strict';

	// Debug logging helper
	var DEBUG = true;
	var log = function (message, data) {
		if (DEBUG && console && console.log) {
			if (data !== undefined) {
				console.log('[Functionalities SVG Icons] ' + message, data);
			} else {
				console.log('[Functionalities SVG Icons] ' + message);
			}
		}
	};

	log('Script loaded');

	// Exit if wp is not available
	if (typeof wp === 'undefined') {
		log('ERROR: wp object is undefined - WordPress not loaded properly');
		return;
	}

	if (typeof wp.richText === 'undefined') {
		log('ERROR: wp.richText is undefined - rich text module not available');
		return;
	}

	log('WordPress dependencies available', {
		'wp.element': typeof wp.element,
		'wp.richText': typeof wp.richText,
		'wp.blockEditor': typeof wp.blockEditor,
		'wp.components': typeof wp.components,
		'wp.i18n': typeof wp.i18n,
		'wp.domReady': typeof wp.domReady
	});

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useCallback = wp.element.useCallback;
	var registerFormatType = wp.richText.registerFormatType;
	var insert = wp.richText.insert;
	var create = wp.richText.create;
	var BlockControls = wp.blockEditor.BlockControls;
	var RichTextToolbarButton = wp.blockEditor.RichTextToolbarButton;
	var Popover = wp.components.Popover;
	var ToolbarGroup = wp.components.ToolbarGroup;
	var ToolbarButton = wp.components.ToolbarButton;
	var SearchControl = wp.components.SearchControl;
	var Button = wp.components.Button;
	var __ = wp.i18n.__;

	// Verify critical components
	if (!RichTextToolbarButton) {
		log('ERROR: RichTextToolbarButton is not available from wp.blockEditor');
		return;
	}

	log('All critical components loaded successfully');

	// Get icons data
	var iconsData = window.functionalitiesSvgIcons || {};
	var allIcons = iconsData.icons || [];
	var i18n = iconsData.i18n || {};

	log('Icons data loaded', {
		iconCount: allIcons.length,
		hasI18n: Object.keys(i18n).length > 0,
		rawData: iconsData
	});

	// SVG icon for the toolbar button (flag/bookmark style)
	var toolbarIcon = el('svg', {
		xmlns: 'http://www.w3.org/2000/svg',
		viewBox: '0 0 24 24',
		width: 24,
		height: 24
	}, el('path', {
		fill: 'currentColor',
		d: 'M17 3H7c-1.1 0-2 .9-2 2v16l7-3 7 3V5c0-1.1-.9-2-2-2zm0 15l-5-2.18L7 18V5h10v13z'
	}));

	/**
	 * Render an SVG string safely as React element
	 */
	var renderSvgIcon = function (svgString) {
		return el('span', {
			className: 'func-icon-preview',
			dangerouslySetInnerHTML: { __html: svgString }
		});
	};

	/**
	 * Icon Picker Edit Component
	 */
	var IconPickerEdit = function (props) {
		var value = props.value;
		var onChange = props.onChange;

		var stateOpen = useState(false);
		var isOpen = stateOpen[0];
		var setIsOpen = stateOpen[1];

		var stateSearch = useState('');
		var searchTerm = stateSearch[0];
		var setSearchTerm = stateSearch[1];

		// Filter icons
		var filteredIcons = allIcons;
		if (searchTerm) {
			var term = searchTerm.toLowerCase();
			filteredIcons = allIcons.filter(function (icon) {
				return (icon.name && icon.name.toLowerCase().indexOf(term) !== -1) ||
					(icon.slug && icon.slug.toLowerCase().indexOf(term) !== -1);
			});
		}

		// Handle icon insertion - insert actual SVG code
		var onInsertIcon = useCallback(function (icon) {
			log('Inserting icon', icon.slug);
			// Clean and prepare SVG for insertion
			var svgCode = icon.svg
				.replace(/<!--[\s\S]*?-->/g, '') // Remove HTML/XML comments
				.replace(/<svg/, '<svg class="func-icon" style="display:inline-block;width:1em;height:1em;vertical-align:-0.125em;fill:currentColor"')
				.replace(/\s*(width|height)="[^"]*"/g, ''); // Remove width/height attributes
			// Add inline styles to wrapper for editor iframe compatibility
			var iconHTML = '<span class="func-icon-wrapper" style="display:inline-flex;align-items:center;line-height:0">' + svgCode + '</span>';
			onChange(insert(value, create({ html: iconHTML })));
			setIsOpen(false);
			setSearchTerm('');
		}, [value, onChange]);

		// Handle toggle
		var onToggle = useCallback(function () {
			log('Toggling icon picker', { wasOpen: isOpen, willBeOpen: !isOpen });
			setIsOpen(!isOpen);
			if (isOpen) {
				setSearchTerm('');
			}
		}, [isOpen]);

		// Handle close
		var onClose = useCallback(function () {
			log('Closing icon picker');
			setIsOpen(false);
			setSearchTerm('');
		}, []);

		// Build icon buttons
		var iconButtons = [];
		if (filteredIcons.length === 0) {
			var noIconsText = allIcons.length === 0
				? (i18n.noIcons || __('No icons available. Add icons in Functionalities > SVG Icons.', 'functionalities'))
				: __('No matching icons found.', 'functionalities');
			iconButtons.push(
				el('p', {
					key: 'empty',
					className: 'func-icon-empty'
				}, noIconsText)
			);
		} else {
			filteredIcons.forEach(function (icon) {
				iconButtons.push(
					el('button', {
						key: icon.slug,
						type: 'button',
						className: 'func-icon-btn',
						onClick: function () { onInsertIcon(icon); },
						title: icon.name
					}, renderSvgIcon(icon.svg))
				);
			});
		}

		// Build popover
		var popover = null;
		if (isOpen) {
			popover = el(Popover, {
				position: 'bottom center',
				onClose: onClose,
				className: 'func-svg-icon-popover',
				focusOnMount: 'firstElement'
			},
				el('div', { className: 'func-svg-icon-picker' },
					el('div', { className: 'func-icon-search-wrapper' },
						el('input', {
							type: 'search',
							value: searchTerm,
							onChange: function (e) { setSearchTerm(e.target.value); },
							placeholder: i18n.searchIcons || __('Search icons...', 'functionalities'),
							className: 'func-icon-search-input'
						})
					),
					el('div', { className: 'func-icon-grid' }, iconButtons)
				)
			);
		}

		// Use RichTextToolbarButton for main toolbar placement
		return el(Fragment, {},
			el(RichTextToolbarButton, {
				icon: toolbarIcon,
				title: i18n.insertIcon || __('Insert Icon', 'functionalities'),
				onClick: onToggle,
				isActive: isOpen
			}),
			popover
		);
	};

	// Register format type on DOM ready
	wp.domReady(function () {
		log('DOM ready - registering format type');

		try {
			registerFormatType('functionalities/svg-icon', {
				title: i18n.insertIcon || __('Insert Icon', 'functionalities'),
				tagName: 'span',
				className: 'func-icon',
				edit: IconPickerEdit
			});
			log('Format type registered successfully: functionalities/svg-icon');
		} catch (error) {
			log('ERROR registering format type', error);
		}
	});

})(window.wp);
