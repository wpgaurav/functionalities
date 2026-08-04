/**
 * SVG Icons block editor integration.
 *
 * @package Functionalities
 * @since 0.11.0
 */

(function (wp) {
	'use strict';

	if (!wp || !wp.element || !wp.blocks || !wp.blockEditor || !wp.components) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useCallback = wp.element.useCallback;
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var BlockControls = wp.blockEditor.BlockControls;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var AlignmentToolbar = wp.blockEditor.AlignmentToolbar;
	var RichTextToolbarButton = wp.blockEditor.RichTextToolbarButton;
	var Popover = wp.components.Popover;
	var PanelBody = wp.components.PanelBody;
	var RangeControl = wp.components.RangeControl;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var TextControl = wp.components.TextControl;
	var ToolbarGroup = wp.components.ToolbarGroup;
	var ToolbarButton = wp.components.ToolbarButton;
	var SearchControl = wp.components.SearchControl;
	var Button = wp.components.Button;
	var Spinner = wp.components.Spinner;
	var Notice = wp.components.Notice;
	var __ = wp.i18n.__;
	var apiFetch = wp.apiFetch;
	var config = window.functionalitiesSvgIcons || {};
	var i18n = config.i18n || {};
	var restPath = config.restPath || '/functionalities/v1/svg-icons';
	var coreRestPath = config.coreRestPath || '/wp/v2/icons';
	var requestCache = {};
	var perPage = 48;

	var toolbarIcon = el('svg', {
		xmlns: 'http://www.w3.org/2000/svg',
		viewBox: '0 0 24 24',
		width: 24,
		height: 24,
		'aria-hidden': true,
		focusable: false
	}, el('path', {
		fill: 'currentColor',
		d: 'M17 3H7c-1.1 0-2 .9-2 2v16l7-3 7 3V5c0-1.1-.9-2-2-2zm0 15-5-2.18L7 18V5h10v13z'
	}));

	function getRecentSlugs() {
		try {
			var value = JSON.parse(window.localStorage.getItem('functionalitiesSvgRecent') || '[]');
			return Array.isArray(value) ? value : [];
		} catch (error) {
			return [];
		}
	}

	function rememberIcon(slug) {
		try {
			var recent = getRecentSlugs().filter(function (item) {
				return item !== slug;
			});
			recent.unshift(slug);
			window.localStorage.setItem('functionalitiesSvgRecent', JSON.stringify(recent.slice(0, 8)));
		} catch (error) {
			// Storage may be disabled. Icon selection must still work.
		}
	}

	function requestIcons(search, page, include, source) {
		source = source === 'core' ? 'core' : 'custom';
		var key = [source, search || '', page || 1, include || ''].join('|');
		if (requestCache[key]) {
			return Promise.resolve(requestCache[key]);
		}

		if (source === 'core') {
			var coreQuery = search ? '?search=' + encodeURIComponent(search) : '';
			return apiFetch({ path: coreRestPath + coreQuery }).then(function (icons) {
				var normalized = (Array.isArray(icons) ? icons : []).map(function (icon) {
					return {
						slug: icon.name,
						name: icon.label || icon.name,
						svg: icon.content,
						source: 'core'
					};
				});
				if (include) {
					normalized = normalized.filter(function (icon) {
						return icon.slug === include;
					});
				}
				var start = ((page || 1) - 1) * perPage;
				requestCache[key] = normalized.slice(start, start + perPage);
				return requestCache[key];
			});
		}

		var query = '?per_page=' + perPage + '&page=' + (page || 1);
		if (search) {
			query += '&search=' + encodeURIComponent(search);
		}
		if (include) {
			query += '&include=' + encodeURIComponent(include);
		}

		return apiFetch({ path: restPath + query }).then(function (icons) {
			requestCache[key] = (Array.isArray(icons) ? icons : []).map(function (icon) {
				icon.source = 'custom';
				return icon;
			});
			return requestCache[key];
		});
	}

	function renderSvgIcon(svgString) {
		return el('span', {
			className: 'func-icon-preview',
			'aria-hidden': true,
			dangerouslySetInnerHTML: { __html: svgString }
		});
	}

	function IconPicker(props) {
		var searchState = useState('');
		var search = searchState[0];
		var setSearch = searchState[1];
		var iconsState = useState([]);
		var icons = iconsState[0];
		var setIcons = iconsState[1];
		var pageState = useState(1);
		var page = pageState[0];
		var setPage = pageState[1];
		var moreState = useState(false);
		var hasMore = moreState[0];
		var setHasMore = moreState[1];
		var loadingState = useState(false);
		var loading = loadingState[0];
		var setLoading = loadingState[1];
		var errorState = useState(false);
		var hasError = errorState[0];
		var setHasError = errorState[1];

		useEffect(function () {
			var active = true;
			var timer = window.setTimeout(function () {
				setLoading(true);
				setHasError(false);
				requestIcons(search, 1, '', props.source).then(function (results) {
					if (!active) {
						return;
					}
					var recent = getRecentSlugs();
					results.sort(function (first, second) {
						var firstIndex = recent.indexOf(first.slug);
						var secondIndex = recent.indexOf(second.slug);
						firstIndex = firstIndex === -1 ? 999 : firstIndex;
						secondIndex = secondIndex === -1 ? 999 : secondIndex;
						return firstIndex - secondIndex;
					});
					setIcons(results);
					setPage(1);
					setHasMore(results.length === perPage);
					setLoading(false);
				}).catch(function () {
					if (active) {
						setIcons([]);
						setHasError(true);
						setLoading(false);
					}
				});
			}, 200);

			return function () {
				active = false;
				window.clearTimeout(timer);
			};
		}, [search, props.source]);

		var loadMore = useCallback(function () {
			var nextPage = page + 1;
			setLoading(true);
			requestIcons(search, nextPage, '', props.source).then(function (results) {
				setIcons(icons.concat(results));
				setPage(nextPage);
				setHasMore(results.length === perPage);
				setLoading(false);
			}).catch(function () {
				setHasError(true);
				setLoading(false);
			});
		}, [icons, page, search, props.source]);

		var chooseIcon = function (icon) {
			rememberIcon(icon.slug);
			props.onSelect(icon);
			props.onClose();
		};

		var content;
		if (hasError) {
			content = el(Notice, {
				status: 'error',
				isDismissible: false
			}, i18n.loadError || __('Icons could not be loaded. Try again.', 'functionalities'));
		} else if (!loading && icons.length === 0) {
			content = el('p', { className: 'func-icon-empty' },
				search
					? (i18n.noMatchingIcons || __('No matching icons found.', 'functionalities'))
					: (i18n.noIcons || __('No icons found. Add icons in Functionalities > SVG Icons.', 'functionalities'))
			);
		} else {
			content = el(Fragment, {},
				el('div', {
					className: 'func-icon-grid',
					role: 'list',
					'aria-label': i18n.selectIcon || __('Select icon', 'functionalities')
				}, icons.map(function (icon) {
					var selected = props.selectedSlug === icon.slug;
					return el('button', {
						key: icon.slug,
						type: 'button',
						role: 'listitem',
						className: 'func-icon-btn' + (selected ? ' is-selected' : ''),
						onClick: function () { chooseIcon(icon); },
						title: icon.name || icon.slug,
						'aria-label': icon.name || icon.slug,
						'aria-pressed': selected
					}, renderSvgIcon(icon.svg));
				})),
				loading && el('div', {
					className: 'func-icon-loading',
					'aria-label': i18n.loadingIcons || __('Loading icons…', 'functionalities')
				}, el(Spinner)),
				hasMore && !loading && el(Button, {
					variant: 'secondary',
					onClick: loadMore,
					className: 'func-icon-load-more'
				}, i18n.loadMore || __('Load more', 'functionalities'))
			);
		}

		return el(Popover, {
			position: 'bottom center',
			onClose: props.onClose,
			className: 'func-svg-icon-popover',
			focusOnMount: 'firstElement'
		}, el('div', { className: 'func-svg-icon-picker' },
			el(SearchControl, {
				label: i18n.searchIcons || __('Search icons', 'functionalities'),
				value: search,
				onChange: setSearch,
				className: 'func-icon-search'
			}),
			content
		));
	}

	function InlineIconPicker(props) {
		var openState = useState(false);
		var isOpen = openState[0];
		var setIsOpen = openState[1];

		var insertIcon = useCallback(function (icon) {
			var shortcode = '[func_icon name="' + icon.slug + '"]';
			var iconValue = wp.richText.create({ text: shortcode });
			props.onChange(wp.richText.insert(props.value, iconValue));
		}, [props.value, props.onChange]);

		return el(Fragment, {},
			el(RichTextToolbarButton, {
				icon: toolbarIcon,
				title: i18n.insertIcon || __('Insert icon shortcode', 'functionalities'),
				onClick: function () { setIsOpen(!isOpen); },
				isActive: isOpen
			}),
			isOpen && el(IconPicker, {
				onSelect: insertIcon,
				onClose: function () { setIsOpen(false); },
				selectedSlug: ''
			})
		);
	}

	function SvgIconEdit(props) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var openState = useState(false);
		var isOpen = openState[0];
		var setIsOpen = openState[1];
		var selectedState = useState(null);
		var selectedIcon = selectedState[0];
		var setSelectedIcon = selectedState[1];
		var missingState = useState(false);
		var isMissing = missingState[0];
		var setIsMissing = missingState[1];
		var unit = ['px', 'em', 'rem'].indexOf(attributes.sizeUnit) !== -1 ? attributes.sizeUnit : 'px';
		var size = typeof attributes.size === 'number' ? attributes.size : 48;
		var mode = attributes.colorMode === 'original' ? 'original' : 'monochrome';

		useEffect(function () {
			var active = true;
			var source = attributes.iconSource === 'core' ? 'core' : 'custom';
			var selectedSlug = source === 'core' ? attributes.coreIcon : attributes.iconSlug;
			if (!selectedSlug) {
				setSelectedIcon(null);
				setIsMissing(false);
				return function () { active = false; };
			}
			requestIcons('', 1, selectedSlug, source).then(function (results) {
				if (active) {
					setSelectedIcon(results[0] || null);
					setIsMissing(results.length === 0);
				}
			}).catch(function () {
				if (active) {
					setSelectedIcon(null);
					setIsMissing(true);
				}
			});
			return function () { active = false; };
		}, [attributes.iconSource, attributes.iconSlug, attributes.coreIcon]);

		var selectIcon = function (icon) {
			if (attributes.iconSource === 'core') {
				setAttributes({ coreIcon: icon.slug });
			} else {
				setAttributes({ iconSlug: icon.slug });
			}
			setSelectedIcon(icon);
			setIsMissing(false);
		};
		var maxSize = unit === 'px' ? 512 : 32;
		var minSize = unit === 'px' ? 8 : 0.5;
		var step = unit === 'px' ? 1 : 0.1;
		var nativeColor = attributes.style && attributes.style.color ? attributes.style.color.text : '';
		var previewStyle = {
			'--func-icon-size': size + unit,
			textAlign: ['left', 'center', 'right'].indexOf(attributes.align) !== -1 ? attributes.align : undefined,
			color: nativeColor || attributes.color || undefined
		};
		var blockProps = useBlockProps({
			className: 'func-svg-icon-block-wrapper is-color-' + mode,
			style: previewStyle
		});

		return el(Fragment, {},
			el(BlockControls, {},
				el(AlignmentToolbar, {
					value: attributes.align === 'none' ? undefined : attributes.align,
					onChange: function (align) { setAttributes({ align: align || 'none' }); }
				}),
				el(ToolbarGroup, {},
					el(ToolbarButton, {
						icon: toolbarIcon,
						title: selectedIcon
							? (i18n.changeIcon || __('Change icon', 'functionalities'))
							: (i18n.selectIcon || __('Select icon', 'functionalities')),
						onClick: function () { setIsOpen(true); },
						isPressed: isOpen
					})
				)
			),
			el(InspectorControls, {},
				el(PanelBody, {
					title: i18n.iconSettings || __('Icon settings', 'functionalities'),
					initialOpen: true
				},
					el(SelectControl, {
						label: i18n.iconSource || __('Icon source', 'functionalities'),
						value: attributes.iconSource === 'core' ? 'core' : 'custom',
						options: [
							{ label: i18n.customLibrary || __('Custom library', 'functionalities'), value: 'custom' },
							{ label: i18n.coreLibrary || __('WordPress Core', 'functionalities'), value: 'core', disabled: !config.hasCoreIcons }
						],
						onChange: function (value) {
							setAttributes({ iconSource: value });
							setSelectedIcon(null);
							setIsMissing(false);
						}
					}),
					el(RangeControl, {
						label: i18n.iconSize || __('Icon size', 'functionalities'),
						value: size,
						onChange: function (value) { setAttributes({ size: value }); },
						min: minSize,
						max: maxSize,
						step: step
					}),
					el(SelectControl, {
						label: i18n.sizeUnit || __('Size unit', 'functionalities'),
						value: unit,
						options: [
							{ label: 'px', value: 'px' },
							{ label: 'em', value: 'em' },
							{ label: 'rem', value: 'rem' }
						],
						onChange: function (value) {
							var nextSize = value === 'px' ? Math.max(8, Math.min(512, size)) : Math.max(0.5, Math.min(32, size));
							setAttributes({ sizeUnit: value, size: nextSize });
						}
					}),
					el(SelectControl, {
						label: i18n.colorMode || __('Color mode', 'functionalities'),
						value: mode,
						options: [
							{ label: i18n.monochrome || __('Monochrome (inherit text color)', 'functionalities'), value: 'monochrome' },
							{ label: i18n.originalColors || __('Original SVG colors', 'functionalities'), value: 'original' }
						],
						onChange: function (value) { setAttributes({ colorMode: value }); }
					}),
					el(ToggleControl, {
						label: i18n.decorative || __('Decorative icon', 'functionalities'),
						help: i18n.decorativeHelp || __('Decorative icons are hidden from assistive technology.', 'functionalities'),
						checked: attributes.decorative !== false,
						onChange: function (value) { setAttributes({ decorative: value }); }
					}),
					attributes.decorative === false && el(TextControl, {
						label: i18n.accessibility || __('Accessibility label', 'functionalities'),
						value: attributes.label || '',
						onChange: function (value) { setAttributes({ label: value }); }
					})
				)
			),
			el('div', blockProps,
				isMissing && el(Notice, {
					status: 'warning',
					isDismissible: false
				}, i18n.missingIcon || __('The selected icon is no longer in the library. Choose a replacement.', 'functionalities')),
				selectedIcon ? el('span', {
					className: 'func-svg-icon-block-render',
					'aria-label': attributes.decorative === false ? (attributes.label || selectedIcon.name) : undefined,
					'aria-hidden': attributes.decorative === false ? undefined : true,
					role: attributes.decorative === false ? 'img' : undefined,
					dangerouslySetInnerHTML: { __html: selectedIcon.svg }
				}) : el(Button, {
					variant: 'primary',
					onClick: function () { setIsOpen(true); },
					className: 'func-svg-icon-block-placeholder'
				}, i18n.selectIcon || __('Select icon', 'functionalities')),
				isOpen && el(IconPicker, {
					onSelect: selectIcon,
					onClose: function () { setIsOpen(false); },
					selectedSlug: attributes.iconSource === 'core' ? (attributes.coreIcon || '') : (attributes.iconSlug || ''),
					source: attributes.iconSource === 'core' ? 'core' : 'custom'
				})
			)
		);
	}

	wp.domReady(function () {
		if (wp.richText && RichTextToolbarButton && wp.richText.registerFormatType) {
			wp.richText.registerFormatType('functionalities/svg-icon', {
				title: i18n.insertIcon || __('Insert icon shortcode', 'functionalities'),
				tagName: 'i',
				className: 'func-icon',
				attributes: {
					dataIcon: 'data-icon'
				},
				edit: InlineIconPicker,
				object: true
			});
		}

		var metadata = config.blockMetadata || {};
		var blockName = metadata.name || 'functionalities/svg-icon-block';
		var settings = Object.assign({}, metadata, {
			icon: toolbarIcon,
			edit: SvgIconEdit,
			save: function () { return null; },
			transforms: {
				from: [
					{
						type: 'block',
						blocks: ['core/icon'],
						transform: function (attributes) {
							var width = attributes.style && attributes.style.dimensions ? attributes.style.dimensions.width : '';
							var parsedSize = typeof width === 'string' ? parseFloat(width) : 48;
							var parsedUnit = typeof width === 'string' && /(?:em|rem)$/.test(width) ? width.replace(/^[0-9.]+/, '') : 'px';
							return wp.blocks.createBlock(blockName, {
								iconSource: 'core',
								coreIcon: attributes.icon || '',
								size: isNaN(parsedSize) ? 48 : parsedSize,
								sizeUnit: parsedUnit,
								align: attributes.align || 'none',
								decorative: !attributes.ariaLabel,
								label: attributes.ariaLabel || '',
								style: attributes.style || {},
								textColor: attributes.textColor,
								backgroundColor: attributes.backgroundColor,
								className: attributes.className,
								anchor: attributes.anchor
							});
						}
					}
				],
				to: [
					{
						type: 'block',
						blocks: ['core/icon'],
						isMatch: function (attributes) {
							return attributes.iconSource === 'core' && !!attributes.coreIcon;
						},
						transform: function (attributes) {
							var style = Object.assign({}, attributes.style || {});
							style.dimensions = Object.assign({}, style.dimensions || {}, {
								width: (attributes.size || 48) + (attributes.sizeUnit || 'px')
							});
							return wp.blocks.createBlock('core/icon', {
								icon: attributes.coreIcon,
								align: attributes.align === 'none' ? undefined : attributes.align,
								ariaLabel: attributes.decorative === false ? attributes.label : undefined,
								style: style,
								textColor: attributes.textColor,
								backgroundColor: attributes.backgroundColor,
								className: attributes.className,
								anchor: attributes.anchor
							});
						}
					}
				]
			}
		});
		delete settings.name;
		delete settings.$schema;
		registerBlockType(blockName, settings);
	});
})(window.wp);
