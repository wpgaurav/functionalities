/**
 * Content Regression Detection - Editor Integration
 *
 * Provides pre-publish panel and sidebar components for content integrity warnings.
 *
 * @package Functionalities
 */

( function( wp ) {
	'use strict';

	const { registerPlugin } = wp.plugins;
	const { PluginPrePublishPanel, PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
	const { PanelBody, Button, Spinner, Notice, ToggleControl, Icon } = wp.components;
	const { useState, useEffect, useCallback } = wp.element;
	const { useSelect, useDispatch } = wp.data;
	const apiFetch = wp.apiFetch;

	// Get localized data.
	const data = window.functionalitiesRegressionData || {};
	const { postId, restBase, i18n } = data;

	/**
	 * Format timestamp to human-readable date.
	 *
	 * @param {number} timestamp Unix timestamp.
	 * @return {string} Formatted date string.
	 */
	function formatTimestamp( timestamp ) {
		if ( ! timestamp ) {
			return '';
		}
		const date = new Date( timestamp * 1000 );
		return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
	}

	/**
	 * Get icon for warning type.
	 *
	 * @param {string} type Warning type.
	 * @return {string} Dashicon name.
	 */
	function getWarningIcon( type ) {
		switch ( type ) {
			case 'link_drop':
				return 'admin-links';
			case 'word_count_drop':
				return 'editor-contract';
			case 'heading_missing_h1':
			case 'heading_multiple_h1':
			case 'heading_skipped_level':
				return 'heading';
			case 'missing_alt_text':
				return 'format-image';
			default:
				return 'warning';
		}
	}

	/**
	 * Get severity color.
	 *
	 * @param {string} severity Severity level.
	 * @return {string} CSS color value.
	 */
	function getSeverityColor( severity ) {
		switch ( severity ) {
			case 'warning':
				return '#dba617';
			case 'notice':
				return '#72aee6';
			default:
				return '#646970';
		}
	}

	/**
	 * Warning item component.
	 *
	 * @param {Object} props Component props.
	 * @return {JSX.Element} Warning item element.
	 */
	function WarningItem( { warning } ) {
		const iconName = getWarningIcon( warning.type );
		const color = getSeverityColor( warning.severity );

		return wp.element.createElement(
			'div',
			{
				className: 'functionalities-regression-warning',
				style: {
					display: 'flex',
					alignItems: 'flex-start',
					gap: '10px',
					padding: '12px',
					background: '#fff8e5',
					borderLeft: '4px solid ' + color,
					marginBottom: '8px',
					borderRadius: '2px',
				}
			},
			wp.element.createElement( Icon, {
				icon: iconName,
				style: { color: color, flexShrink: 0, marginTop: '2px' }
			} ),
			wp.element.createElement(
				'div',
				{ style: { flex: 1 } },
				wp.element.createElement(
					'p',
					{ style: { margin: 0, fontSize: '13px', lineHeight: '1.5' } },
					warning.message
				),
				warning.before !== undefined && warning.after !== undefined && wp.element.createElement(
					'p',
					{
						style: {
							margin: '6px 0 0',
							fontSize: '12px',
							color: '#646970'
						}
					},
					warning.before + ' → ' + warning.after
				),
				warning.baseline_timestamp && wp.element.createElement(
					'p',
					{
						style: {
							margin: '4px 0 0',
							fontSize: '11px',
							color: '#8c8f94'
						}
					},
					i18n.lastSnapshot + ' ' + formatTimestamp( warning.baseline_timestamp )
				)
			)
		);
	}

	/**
	 * No issues component.
	 *
	 * @return {JSX.Element} No issues element.
	 */
	function NoIssues() {
		return wp.element.createElement(
			'div',
			{
				style: {
					display: 'flex',
					alignItems: 'center',
					gap: '10px',
					padding: '12px',
					background: '#edfaef',
					borderLeft: '4px solid #00a32a',
					borderRadius: '2px',
				}
			},
			wp.element.createElement( Icon, {
				icon: 'yes-alt',
				style: { color: '#00a32a' }
			} ),
			wp.element.createElement(
				'span',
				{ style: { fontSize: '13px' } },
				i18n.noIssues
			)
		);
	}

	/**
	 * No baseline component.
	 *
	 * @return {JSX.Element} No baseline element.
	 */
	function NoBaseline() {
		return wp.element.createElement(
			'div',
			{
				style: {
					display: 'flex',
					alignItems: 'flex-start',
					gap: '10px',
					padding: '12px',
					background: '#f0f0f1',
					borderLeft: '4px solid #646970',
					borderRadius: '2px',
				}
			},
			wp.element.createElement( Icon, {
				icon: 'info',
				style: { color: '#646970', flexShrink: 0 }
			} ),
			wp.element.createElement(
				'span',
				{ style: { fontSize: '13px', color: '#50575e' } },
				i18n.noBaseline
			)
		);
	}

	/**
	 * Stats display component.
	 *
	 * @param {Object} props Component props.
	 * @return {JSX.Element} Stats element.
	 */
	function StatsDisplay( { current, baseline } ) {
		if ( ! current || ! baseline ) {
			return null;
		}

		const items = [
			{
				label: 'Internal Links',
				current: current.internal_link_count || 0,
				baseline: baseline.internal_link_count || 0,
			},
			{
				label: 'Word Count',
				current: current.word_count || 0,
				baseline: baseline.word_count || 0,
			},
			{
				label: 'H1 Tags',
				current: current.h1_count || 0,
				baseline: baseline.h1_count || 0,
			}
		];

		return wp.element.createElement(
			'div',
			{
				style: {
					marginTop: '12px',
					padding: '12px',
					background: '#f6f7f7',
					borderRadius: '4px',
					fontSize: '12px',
				}
			},
			wp.element.createElement(
				'div',
				{ style: { fontWeight: '500', marginBottom: '8px', color: '#50575e' } },
				'Content Statistics'
			),
			items.map( function( item, idx ) {
				const diff = item.current - item.baseline;
				const diffColor = diff < 0 ? '#d63638' : ( diff > 0 ? '#00a32a' : '#646970' );
				const diffText = diff > 0 ? '+' + diff : diff.toString();

				return wp.element.createElement(
					'div',
					{
						key: idx,
						style: {
							display: 'flex',
							justifyContent: 'space-between',
							padding: '4px 0',
							borderBottom: idx < items.length - 1 ? '1px solid #e0e0e0' : 'none',
						}
					},
					wp.element.createElement( 'span', { style: { color: '#646970' } }, item.label ),
					wp.element.createElement(
						'span',
						null,
						wp.element.createElement( 'span', { style: { marginRight: '8px' } }, item.current ),
						diff !== 0 && wp.element.createElement(
							'span',
							{ style: { color: diffColor, fontSize: '11px' } },
							'(' + diffText + ')'
						)
					)
				);
			} )
		);
	}

	/**
	 * Main regression panel content component.
	 *
	 * @param {Object} props Component props.
	 * @return {JSX.Element} Panel content element.
	 */
	function RegressionPanelContent( { showActions = true } ) {
		const [ status, setStatus ] = useState( null );
		const [ loading, setLoading ] = useState( true );
		const [ error, setError ] = useState( null );
		const [ ignoreWarnings, setIgnoreWarnings ] = useState( false );

		// Get current post content for real-time analysis.
		const { isSaving, isDirty } = useSelect( ( select ) => {
			const editor = select( 'core/editor' );
			return {
				isSaving: editor.isSavingPost(),
				isDirty: editor.isEditedPostDirty(),
			};
		}, [] );

		/**
		 * Fetch regression status from API.
		 */
		const fetchStatus = useCallback( async () => {
			if ( ! postId ) {
				return;
			}

			setLoading( true );
			setError( null );

			try {
				const response = await apiFetch( {
					path: restBase + postId,
					method: 'GET',
				} );
				setStatus( response );
			} catch ( err ) {
				setError( err.message || 'Failed to fetch status' );
			} finally {
				setLoading( false );
			}
		}, [ postId ] );

		// Fetch status on mount and when post saves.
		useEffect( () => {
			fetchStatus();
		}, [ fetchStatus ] );

		// Refetch when post finishes saving.
		useEffect( () => {
			if ( ! isSaving && status ) {
				// Small delay to ensure save is complete.
				const timeout = setTimeout( fetchStatus, 500 );
				return () => clearTimeout( timeout );
			}
		}, [ isSaving ] );

		/**
		 * Mark change as intentional.
		 */
		const handleMarkIntentional = async () => {
			try {
				await apiFetch( {
					path: restBase + postId + '/mark-intentional',
					method: 'POST',
				} );
				fetchStatus();
			} catch ( err ) {
				setError( err.message );
			}
		};

		/**
		 * Reset baseline.
		 */
		const handleResetBaseline = async () => {
			try {
				await apiFetch( {
					path: restBase + postId + '/reset-baseline',
					method: 'POST',
				} );
				fetchStatus();
			} catch ( err ) {
				setError( err.message );
			}
		};

		/**
		 * Update per-post settings.
		 *
		 * @param {Object} settings Settings to update.
		 */
		const updateSettings = async ( settings ) => {
			try {
				await apiFetch( {
					path: restBase + postId + '/settings',
					method: 'POST',
					data: settings,
				} );
				fetchStatus();
			} catch ( err ) {
				setError( err.message );
			}
		};

		// Loading state.
		if ( loading ) {
			return wp.element.createElement(
				'div',
				{ style: { textAlign: 'center', padding: '20px' } },
				wp.element.createElement( Spinner ),
				wp.element.createElement(
					'p',
					{ style: { marginTop: '10px', color: '#646970' } },
					i18n.loading
				)
			);
		}

		// Error state.
		if ( error ) {
			return wp.element.createElement(
				Notice,
				{ status: 'error', isDismissible: false },
				error
			);
		}

		// No status yet.
		if ( ! status ) {
			return wp.element.createElement( NoBaseline );
		}

		const { warnings, has_baseline: hasBaseline, post_settings: postSettings, current, baseline } = status;
		const hasWarnings = warnings && warnings.length > 0;

		return wp.element.createElement(
			'div',
			{ className: 'functionalities-regression-panel' },

			// Warnings or success message.
			! hasBaseline
				? wp.element.createElement( NoBaseline )
				: hasWarnings && ! ignoreWarnings
					? wp.element.createElement(
						'div',
						{ className: 'functionalities-regression-warnings' },
						warnings.map( ( warning, index ) =>
							wp.element.createElement( WarningItem, {
								key: index,
								warning: warning
							} )
						)
					)
					: wp.element.createElement( NoIssues ),

			// Stats display.
			hasBaseline && showActions && wp.element.createElement( StatsDisplay, {
				current: current,
				baseline: baseline
			} ),

			// Actions (only shown if there are warnings and showActions is true).
			showActions && hasWarnings && ! ignoreWarnings && wp.element.createElement(
				'div',
				{
					className: 'functionalities-regression-actions',
					style: { marginTop: '16px' }
				},
				wp.element.createElement(
					Button,
					{
						variant: 'secondary',
						onClick: () => setIgnoreWarnings( true ),
						style: { marginRight: '8px', marginBottom: '8px' }
					},
					i18n.ignoreThisUpdate
				),
				wp.element.createElement(
					Button,
					{
						variant: 'secondary',
						onClick: handleMarkIntentional,
						style: { marginBottom: '8px' }
					},
					i18n.markIntentional
				)
			),

			// Per-post settings.
			showActions && wp.element.createElement(
				'div',
				{
					className: 'functionalities-regression-settings',
					style: {
						marginTop: '20px',
						paddingTop: '16px',
						borderTop: '1px solid #e0e0e0'
					}
				},
				wp.element.createElement( ToggleControl, {
					label: i18n.disableDetection,
					checked: postSettings && postSettings.detection_disabled,
					onChange: ( value ) => updateSettings( { detection_disabled: value } )
				} ),
				wp.element.createElement( ToggleControl, {
					label: i18n.markAsShortForm,
					checked: postSettings && postSettings.is_short_form,
					onChange: ( value ) => updateSettings( { is_short_form: value } )
				} ),
				wp.element.createElement(
					Button,
					{
						variant: 'link',
						isDestructive: true,
						onClick: handleResetBaseline,
						style: { marginTop: '8px' }
					},
					i18n.resetBaseline
				)
			)
		);
	}

	/**
	 * Pre-publish panel component.
	 *
	 * @return {JSX.Element} Pre-publish panel element.
	 */
	function ContentRegressionPrePublishPanel() {
		const [ status, setStatus ] = useState( null );

		useEffect( () => {
			if ( ! postId ) {
				return;
			}

			apiFetch( {
				path: restBase + postId,
				method: 'GET',
			} ).then( setStatus ).catch( () => {} );
		}, [] );

		const hasWarnings = status && status.warnings && status.warnings.length > 0;

		return wp.element.createElement(
			PluginPrePublishPanel,
			{
				title: i18n.panelTitle,
				icon: hasWarnings ? 'warning' : 'shield',
				initialOpen: hasWarnings,
			},
			wp.element.createElement( RegressionPanelContent, { showActions: false } )
		);
	}

	/**
	 * Sidebar component.
	 *
	 * @return {JSX.Element} Sidebar element.
	 */
	function ContentRegressionSidebar() {
		return wp.element.createElement(
			wp.element.Fragment,
			null,
			wp.element.createElement(
				PluginSidebarMoreMenuItem,
				{
					target: 'functionalities-content-regression-sidebar',
					icon: 'shield',
				},
				i18n.panelTitle
			),
			wp.element.createElement(
				PluginSidebar,
				{
					name: 'functionalities-content-regression-sidebar',
					icon: 'shield',
					title: i18n.panelTitle,
				},
				wp.element.createElement(
					PanelBody,
					{ title: i18n.panelTitle, initialOpen: true },
					wp.element.createElement( RegressionPanelContent, { showActions: true } )
				)
			)
		);
	}

	/**
	 * Main plugin component combining both pre-publish panel and sidebar.
	 *
	 * @return {JSX.Element} Combined plugin element.
	 */
	function ContentRegressionPlugin() {
		return wp.element.createElement(
			wp.element.Fragment,
			null,
			wp.element.createElement( ContentRegressionPrePublishPanel ),
			wp.element.createElement( ContentRegressionSidebar )
		);
	}

	// Only register if we have a valid post ID.
	if ( postId ) {
		registerPlugin( 'functionalities-content-regression', {
			render: ContentRegressionPlugin,
			icon: 'shield',
		} );
	}

} )( window.wp );
