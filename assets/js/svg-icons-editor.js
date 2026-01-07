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
	var registerBlockType = wp.blocks.registerBlockType;
	var insert = wp.richText.insert;
	var create = wp.richText.create;
	var BlockControls = wp.blockEditor.BlockControls;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var AlignmentToolbar = wp.blockEditor.AlignmentToolbar;
	var RichTextToolbarButton = wp.blockEditor.RichTextToolbarButton;
	var Popover = wp.components.Popover;
	var PanelBody = wp.components.PanelBody;
	var RangeControl = wp.components.RangeControl;
	var ColorPalette = wp.components.ColorPalette;
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

		// Handle icon insertion - use placeholder that PHP will replace on frontend
		var onInsertIcon = useCallback(function (icon) {
			log('Inserting icon', icon.slug);

			// Insert placeholder i tag (PHP will replace with SVG on frontend)
			// Using <i> tag as it's standard for icons and Gutenberg handles it better than empty spans.
			// We add a bullet character inside to prevent Gutenberg from stripping the closing tag.
			// CSS hides the bullet in the editor, PHP strips it on frontend render.
			// contenteditable="false" prevents the cursor from getting stuck inside.
			var iconHTML = '<i data-icon="' + icon.slug + '" class="func-icon" contenteditable="false">•</i>';

			// We insert the icon and then a space separately. 
			// This helps prevent the space (and subsequent typing) from being merged into the atomic span.
			var iconValue = create({ html: iconHTML });
			var spaceValue = create({ text: ' ' });
			var combinedValue = wp.richText.concat ? wp.richText.concat(iconValue, spaceValue) : insert(iconValue, spaceValue, iconValue.text.length);

			onChange(insert(value, combinedValue));

			// Inject CSS to display icon in editor using pseudo-element
			injectIconStyle(icon);

			setIsOpen(false);
			setSearchTerm('');
		}, [value, onChange]);

		// Helper to inject CSS for an icon
		var injectIconStyle = function (icon) {
			var styleId = 'func-icon-style-' + icon.slug;
			if (document.getElementById(styleId)) return;

			// Clean SVG for CSS background
			var svgCode = icon.svg
				.replace(/<!--[\s\S]*?-->/g, '')
				.replace(/"/g, "'")
				.replace(/#/g, '%23')
				.replace(/\n/g, ' ')
				.trim();

			var style = document.createElement('style');
			style.id = styleId;
			style.textContent = '.func-icon[data-icon="' + icon.slug + '"]::before { ' +
				'content: ""; ' +
				'display: inline-block; ' +
				'width: 1em; ' +
				'height: 1em; ' +
				'vertical-align: -0.125em; ' +
				'background-image: url("data:image/svg+xml,' + encodeURIComponent(svgCode) + '"); ' +
				'background-size: contain; ' +
				'background-repeat: no-repeat; ' +
				'background-position: center; ' +
				'}';
			document.head.appendChild(style);
			log('Injected style for icon:', icon.slug);
		};

		// Inject styles for all icons on mount (for existing content)
		wp.element.useEffect(function () {
			allIcons.forEach(injectIconStyle);
		}, []);

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
				tagName: 'i',
				className: 'func-icon',
				attributes: {
					dataIcon: 'data-icon'
				},
				edit: IconPickerEdit,
				object: true
			});
			log('Format type registered successfully: functionalities/svg-icon');
		} catch (error) {
			log('ERROR registering format type', error);
		}

		// Register Block Type
		try {
			registerBlockType('functionalities/svg-icon-block', {
				title: i18n.blockTitle || __('SVG Icon', 'functionalities'),
				description: i18n.blockDesc || __('Insert an SVG icon from your library as a block.', 'functionalities'),
				icon: toolbarIcon,
				category: 'design',
				attributes: {
					iconSlug: { type: 'string' },
					size: { type: 'number', default: 48 },
					align: { type: 'string', default: 'none' },
					color: { type: 'string' }
				},
				edit: function (props) {
					var attributes = props.attributes;
					var setAttributes = props.setAttributes;
					var iconSlug = attributes.iconSlug;
					var size = attributes.size;
					var align = attributes.align;
					var color = attributes.color;

					var stateOpen = useState(false);
					var isOpen = stateOpen[0];
					var setIsOpen = stateOpen[1];

					var stateSearch = useState('');
					var searchTerm = stateSearch[0];
					var setSearchTerm = stateSearch[1];

					var selectedIcon = allIcons.find(function (i) { return i.slug === iconSlug; });

					var filteredIcons = allIcons;
					if (searchTerm) {
						var term = searchTerm.toLowerCase();
						filteredIcons = allIcons.filter(function (icon) {
							return (icon.name && icon.name.toLowerCase().indexOf(term) !== -1) ||
								(icon.slug && icon.slug.toLowerCase().indexOf(term) !== -1);
						});
					}

					var onSelectIcon = function (icon) {
						setAttributes({ iconSlug: icon.slug });
						setIsOpen(false);
					};

					var iconButtons = [];
					if (filteredIcons.length === 0) {
						iconButtons.push(el('p', { key: 'empty', className: 'func-icon-empty' }, i18n.noIcons || __('No matching icons found.', 'functionalities')));
					} else {
						filteredIcons.forEach(function (icon) {
							iconButtons.push(
								el('button', {
									key: icon.slug,
									type: 'button',
									className: 'func-icon-btn' + (iconSlug === icon.slug ? ' is-selected' : ''),
									onClick: function () { onSelectIcon(icon); },
									title: icon.name
								}, renderSvgIcon(icon.svg))
							);
						});
					}

					return el(Fragment, {},
						el(BlockControls, {},
							el(AlignmentToolbar, {
								value: align,
								onChange: function (newAlign) { setAttributes({ align: newAlign }); }
							}),
							el(ToolbarGroup, {},
								el(ToolbarButton, {
									icon: toolbarIcon,
									title: i18n.changeIcon || __('Change Icon', 'functionalities'),
									onClick: function () { setIsOpen(true); }
								})
							)
						),
						el(InspectorControls, {},
							el(PanelBody, { title: i18n.iconSettings || __('Icon Settings', 'functionalities') },
								el(RangeControl, {
									label: i18n.iconSize || __('Icon Size (px)', 'functionalities'),
									value: size,
									onChange: function (newSize) { setAttributes({ size: newSize }); },
									min: 10,
									max: 300
								}),
								el('p', {}, i18n.iconColor || __('Icon Color', 'functionalities')),
								el(ColorPalette, {
									value: color,
									onChange: function (newColor) { setAttributes({ color: newColor }); }
								})
							)
						),
						el('div', {
							className: 'func-svg-icon-block-wrapper align' + align,
							style: {
								textAlign: align === 'left' || align === 'right' || align === 'center' ? align : undefined
							}
						},
							selectedIcon ? el('div', {
								className: 'func-svg-icon-block-render',
								style: {
									width: size + 'px',
									height: size + 'px',
									color: color,
									display: 'inline-block'
								},
								dangerouslySetInnerHTML: { __html: selectedIcon.svg }
							}) : el(Button, {
								isPrimary: true,
								onClick: function () { setIsOpen(true); },
								className: 'func-svg-icon-block-placeholder'
							}, i18n.selectIcon || __('Select Icon', 'functionalities')),
							isOpen && el(Popover, {
								onClose: function () { setIsOpen(false); },
								className: 'func-svg-icon-popover'
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
							)
						)
					);
				},
				save: function () {
					return null;
				}
			});
			log('Block type registered successfully: functionalities/svg-icon-block');
		} catch (error) {
			log('ERROR registering block type', error);
		}
	});

})(window.wp);
