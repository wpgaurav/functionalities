/**
 * SVG Icons Block Editor Integration
 *
 * @package Functionalities
 * @since 0.11.0
 */

( function( wp ) {
	'use strict';

	// Exit if wp is not available
	if ( typeof wp === 'undefined' || typeof wp.richText === 'undefined' ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var registerFormatType = wp.richText.registerFormatType;
	var insert = wp.richText.insert;
	var create = wp.richText.create;
	var RichTextToolbarButton = wp.blockEditor.RichTextToolbarButton;
	var Popover = wp.components.Popover;
	var TextControl = wp.components.TextControl;
	var Button = wp.components.Button;
	var __ = wp.i18n.__;

	// Get icons data
	var iconsData = window.functionalitiesSvgIcons || {};
	var allIcons = iconsData.icons || [];
	var i18n = iconsData.i18n || {};

	// SVG icon for the toolbar button
	var toolbarIcon = el( 'svg', {
		xmlns: 'http://www.w3.org/2000/svg',
		viewBox: '0 0 24 24',
		width: 24,
		height: 24
	}, el( 'path', {
		fill: 'currentColor',
		d: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z'
	} ) );

	/**
	 * Icon Picker Edit Component
	 */
	var IconPickerEdit = function( props ) {
		var value = props.value;
		var onChange = props.onChange;

		var stateOpen = useState( false );
		var isOpen = stateOpen[0];
		var setIsOpen = stateOpen[1];

		var stateSearch = useState( '' );
		var searchTerm = stateSearch[0];
		var setSearchTerm = stateSearch[1];

		// Filter icons
		var filteredIcons = allIcons;
		if ( searchTerm ) {
			var term = searchTerm.toLowerCase();
			filteredIcons = allIcons.filter( function( icon ) {
				return ( icon.name && icon.name.toLowerCase().indexOf( term ) !== -1 ) ||
					   ( icon.slug && icon.slug.toLowerCase().indexOf( term ) !== -1 );
			} );
		}

		// Handle icon insertion
		var onInsertIcon = function( icon ) {
			var iconHTML = '<span class="func-icon" data-icon="' + icon.slug + '"></span>';
			onChange( insert( value, create( { html: iconHTML } ) ) );
			setIsOpen( false );
			setSearchTerm( '' );
		};

		// Handle toggle
		var onToggle = function() {
			setIsOpen( ! isOpen );
			if ( isOpen ) {
				setSearchTerm( '' );
			}
		};

		// Handle close
		var onClose = function() {
			setIsOpen( false );
			setSearchTerm( '' );
		};

		// Build icon buttons
		var iconButtons = [];
		if ( filteredIcons.length === 0 ) {
			var noIconsText = allIcons.length === 0
				? ( i18n.noIcons || __( 'No icons available. Add icons in Functionalities > SVG Icons.', 'functionalities' ) )
				: __( 'No matching icons found.', 'functionalities' );
			iconButtons.push(
				el( 'p', {
					key: 'empty',
					style: { textAlign: 'center', color: '#666', padding: '20px', margin: 0 }
				}, noIconsText )
			);
		} else {
			filteredIcons.forEach( function( icon ) {
				iconButtons.push(
					el( Button, {
						key: icon.slug,
						className: 'func-icon-btn',
						onClick: function() { onInsertIcon( icon ); },
						title: icon.name,
						dangerouslySetInnerHTML: { __html: icon.svg }
					} )
				);
			} );
		}

		// Build popover
		var popover = null;
		if ( isOpen ) {
			popover = el( Popover, {
				position: 'bottom center',
				onClose: onClose,
				className: 'func-svg-icon-popover',
				focusOnMount: 'firstElement'
			},
				el( 'div', { className: 'func-svg-icon-picker' },
					el( TextControl, {
						type: 'search',
						value: searchTerm,
						onChange: setSearchTerm,
						placeholder: i18n.searchIcons || __( 'Search icons...', 'functionalities' ),
						className: 'func-icon-search'
					} ),
					el( 'div', { className: 'func-icon-grid' }, iconButtons )
				)
			);
		}

		return el( Fragment, {},
			el( RichTextToolbarButton, {
				icon: toolbarIcon,
				title: i18n.insertIcon || __( 'Insert Icon', 'functionalities' ),
				onClick: onToggle,
				isActive: isOpen
			} ),
			popover
		);
	};

	// Register format type on DOM ready
	wp.domReady( function() {
		registerFormatType( 'functionalities/svg-icon', {
			title: i18n.insertIcon || __( 'Insert Icon', 'functionalities' ),
			tagName: 'span',
			className: 'func-icon',
			edit: IconPickerEdit
		} );
	} );

} )( window.wp );
