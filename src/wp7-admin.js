/**
 * WordPress 7 admin workspace.
 *
 * @package Functionalities
 */

import {
	DataForm,
	DataViews,
	filterSortAndPaginate,
} from '@wordpress/dataviews';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import { dispatch } from '@wordpress/data';
import { createRoot, useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import './wp7-admin.css';

const config = window.functionalitiesWp7 || {};

function notify( status, message ) {
	try {
		dispatch( 'core/notices' ).createNotice( status, message, {
			type: 'snackbar',
		} );
	} catch ( error ) {
		// Notices are helpful but must not block an action.
	}
}

function runAbility( name, input, readonly = false ) {
	const path = `${ config.abilitiesPath }functionalities/${ name }/run`;
	if ( readonly ) {
		const query = input
			? `?input=${ encodeURIComponent( JSON.stringify( input ) ) }`
			: '';
		return apiFetch( { path: path + query, method: 'GET' } );
	}
	return apiFetch( {
		path,
		method: 'POST',
		data: { input: input || null },
	} );
}

function getRedirectFields() {
	return [
		{
			id: 'from',
			type: 'text',
			label: __( 'Source', 'functionalities' ),
			enableHiding: false,
		},
		{
			id: 'to',
			type: 'text',
			label: __( 'Destination', 'functionalities' ),
			enableHiding: false,
		},
		{
			id: 'type',
			type: 'integer',
			label: __( 'Code', 'functionalities' ),
			elements: [ 301, 302, 307, 308 ].map( ( value ) => ( {
				value,
				label: String( value ),
			} ) ),
			filterBy: { operators: [ 'isAny' ] },
		},
		{
			id: 'enabled',
			type: 'boolean',
			label: __( 'Enabled', 'functionalities' ),
		},
		{
			id: 'hits',
			type: 'integer',
			label: __( 'Hits', 'functionalities' ),
		},
		{
			id: 'created',
			type: 'datetime',
			label: __( 'Created', 'functionalities' ),
		},
	];
}

function getTaskFields() {
	return [
		{
			id: 'text',
			type: 'text',
			label: __( 'Task', 'functionalities' ),
			enableHiding: false,
		},
		{
			id: 'project_name',
			type: 'text',
			label: __( 'Project', 'functionalities' ),
			filterBy: { operators: [ 'isAny' ] },
		},
		{
			id: 'completed',
			type: 'boolean',
			label: __( 'Completed', 'functionalities' ),
		},
		{
			id: 'priority',
			type: 'integer',
			label: __( 'Priority', 'functionalities' ),
		},
		{
			id: 'tags',
			type: 'text',
			label: __( 'Tags', 'functionalities' ),
			getValue: ( { item } ) =>
				Array.isArray( item.tags ) ? item.tags.join( ', ' ) : '',
		},
		{
			id: 'created',
			type: 'datetime',
			label: __( 'Created', 'functionalities' ),
		},
	];
}

function getNotFoundFields() {
	return [
		{
			id: 'path',
			type: 'text',
			label: __( 'Missing path', 'functionalities' ),
			enableHiding: false,
		},
		{
			id: 'count',
			type: 'integer',
			label: __( 'Requests', 'functionalities' ),
		},
		{
			id: 'last_seen',
			type: 'datetime',
			label: __( 'Last seen', 'functionalities' ),
			getValue: ( { item } ) =>
				new Date( Number( item.last_seen ) * 1000 ).toISOString(),
		},
		{
			id: 'referrer_origin',
			type: 'text',
			label: __( 'Referrer', 'functionalities' ),
		},
	];
}

function ManagedDataView( { data, fields, initialView } ) {
	const [ view, setView ] = useState( initialView );
	const result = useMemo(
		() => filterSortAndPaginate( data, view, fields ),
		[ data, fields, view ]
	);

	return (
		<DataViews
			data={ result.data }
			fields={ fields }
			view={ view }
			onChangeView={ setView }
			paginationInfo={ result.paginationInfo }
			getItemId={ ( item ) =>
				String( item.id || item.path || item.text )
			}
			defaultLayouts={ {
				table: {},
				list: {},
			} }
		/>
	);
}

function RedirectForm( { onCreated } ) {
	const [ data, setData ] = useState( {
		from_url: '',
		to_url: '',
		type: 301,
	} );
	const [ busy, setBusy ] = useState( false );
	const fields = useMemo(
		() => [
			{
				id: 'from_url',
				type: 'text',
				label: __( 'Source path', 'functionalities' ),
				description: __( 'Example: /old-page', 'functionalities' ),
			},
			{
				id: 'to_url',
				type: 'text',
				label: __( 'Destination URL', 'functionalities' ),
			},
			{
				id: 'type',
				type: 'integer',
				label: __( 'Redirect code', 'functionalities' ),
				elements: [ 301, 302, 307, 308 ].map( ( value ) => ( {
					value,
					label: String( value ),
				} ) ),
			},
		],
		[]
	);

	const submit = () => {
		setBusy( true );
		runAbility( 'create-redirect', data )
			.then( () => {
				setData( { from_url: '', to_url: '', type: 301 } );
				notify(
					'success',
					__( 'Redirect created.', 'functionalities' )
				);
				onCreated();
			} )
			.catch( ( error ) =>
				notify( 'error', error.message || String( error ) )
			)
			.finally( () => setBusy( false ) );
	};

	return (
		<div className="functionalities-wp7-workspace__form">
			<h3>{ __( 'Add redirect', 'functionalities' ) }</h3>
			<DataForm
				data={ data }
				fields={ fields }
				form={ {
					layout: { type: 'regular' },
					fields: [ 'from_url', 'to_url', 'type' ],
				} }
				onChange={ ( updates ) =>
					setData( ( current ) => ( { ...current, ...updates } ) )
				}
			/>
			<div className="functionalities-wp7-workspace__actions">
				<Button
					variant="primary"
					onClick={ submit }
					isBusy={ busy }
					disabled={
						busy || ! data.from_url.trim() || ! data.to_url.trim()
					}
				>
					{ __( 'Create redirect', 'functionalities' ) }
				</Button>
			</div>
		</div>
	);
}

function TaskForm( { projects, onCreated } ) {
	const [ data, setData ] = useState( {
		project: projects[ 0 ]?.slug || '',
		text: '',
		notes: '',
	} );
	const [ busy, setBusy ] = useState( false );
	const fields = useMemo(
		() => [
			{
				id: 'project',
				type: 'text',
				label: __( 'Project', 'functionalities' ),
				elements: projects.map( ( project ) => ( {
					value: project.slug,
					label: project.name,
				} ) ),
			},
			{
				id: 'text',
				type: 'text',
				label: __( 'Task', 'functionalities' ),
			},
			{
				id: 'notes',
				type: 'text',
				Edit: { control: 'textarea', rows: 4 },
				label: __( 'Notes', 'functionalities' ),
			},
		],
		[ projects ]
	);

	const submit = () => {
		setBusy( true );
		runAbility( 'create-task', data )
			.then( () => {
				setData( ( current ) => ( {
					project: current.project,
					text: '',
					notes: '',
				} ) );
				notify( 'success', __( 'Task created.', 'functionalities' ) );
				onCreated();
			} )
			.catch( ( error ) =>
				notify( 'error', error.message || String( error ) )
			)
			.finally( () => setBusy( false ) );
	};

	return (
		<div className="functionalities-wp7-workspace__form">
			<h3>{ __( 'Add task', 'functionalities' ) }</h3>
			{ projects.length ? (
				<>
					<DataForm
						data={ data }
						fields={ fields }
						form={ {
							layout: { type: 'regular' },
							fields: [ 'project', 'text', 'notes' ],
						} }
						onChange={ ( updates ) =>
							setData( ( current ) => ( {
								...current,
								...updates,
							} ) )
						}
					/>
					<div className="functionalities-wp7-workspace__actions">
						<Button
							variant="primary"
							onClick={ submit }
							isBusy={ busy }
							disabled={
								busy ||
								! data.project ||
								! data.text.trim()
							}
						>
							{ __( 'Create task', 'functionalities' ) }
						</Button>
					</div>
				</>
			) : (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'Create a project in the classic interface below before adding tasks here.',
						'functionalities'
					) }
				</Notice>
			) }
		</div>
	);
}

function AiExplanationForm() {
	const [ data, setData ] = useState( {
		context:
			config.module === 'content-regression'
				? 'content-integrity'
				: 'assumption',
		finding: '',
	} );
	const [ result, setResult ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const fields = useMemo(
		() => [
			{
				id: 'finding',
				type: 'text',
				Edit: { control: 'textarea', rows: 7 },
				label: __( 'Finding to explain', 'functionalities' ),
				description: __(
					'Paste only the warning you want to send to the configured AI provider.',
					'functionalities'
				),
			},
		],
		[]
	);

	const submit = () => {
		setBusy( true );
		setResult( '' );
		runAbility( 'explain-finding', data )
			.then( ( response ) => setResult( response.explanation || '' ) )
			.catch( ( error ) =>
				setResult( error.message || String( error ) )
			)
			.finally( () => setBusy( false ) );
	};

	return (
		<div className="functionalities-wp7-workspace__form">
			<h3>{ __( 'Explain a finding', 'functionalities' ) }</h3>
			<DataForm
				data={ data }
				fields={ fields }
				form={ {
					layout: { type: 'regular' },
					fields: [ 'finding' ],
				} }
				onChange={ ( updates ) =>
					setData( ( current ) => ( { ...current, ...updates } ) )
				}
			/>
			<div className="functionalities-wp7-workspace__actions">
				<Button
					variant="secondary"
					onClick={ submit }
					isBusy={ busy }
					disabled={ busy || ! data.finding.trim() }
				>
					{ __( 'Explain with AI', 'functionalities' ) }
				</Button>
			</div>
			{ result && (
				<p className="functionalities-wp7-workspace__message">
					{ result }
				</p>
			) }
		</div>
	);
}

function Workspace() {
	const [ payload, setPayload ] = useState( null );
	const [ error, setError ] = useState( '' );
	const load = useCallback( () => {
		setError( '' );
		return apiFetch( { path: config.adminDataPath } )
			.then( setPayload )
			.catch( ( requestError ) =>
				setError( requestError.message || String( requestError ) )
			);
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ config.i18n.loadError } { error }
			</Notice>
		);
	}
	if ( ! payload ) {
		return (
			<p>
				<Spinner /> { config.i18n.loading }
			</p>
		);
	}

	const isRedirects = config.module === 'redirect-manager';
	const isTasks =
		config.module === 'task-manager' ||
		config.page === 'functionalities-task-manager';
	const isAi =
		config.module === 'assumption-detection' ||
		config.module === 'content-regression';

	if ( isRedirects ) {
		const fields = getRedirectFields();
		const notFoundFields = getNotFoundFields();
		return (
			<div className="functionalities-wp7-workspace__grid">
				<RedirectForm onCreated={ load } />
				<div className="functionalities-wp7-workspace__view">
					<h3>{ __( 'Redirects', 'functionalities' ) }</h3>
					<ManagedDataView
						data={ payload.redirects }
						fields={ fields }
						initialView={ {
							type: 'table',
							page: 1,
							perPage: 20,
							search: '',
							filters: [],
							sort: { field: 'created', direction: 'desc' },
							titleField: 'from',
							fields: [
								'to',
								'type',
								'enabled',
								'hits',
								'created',
							],
							layout: { density: 'balanced' },
						} }
					/>
					<h3 className="functionalities-wp7-workspace__subheading">
						{ __( '404 activity', 'functionalities' ) }
					</h3>
					<ManagedDataView
						data={ payload.notFound || [] }
						fields={ notFoundFields }
						initialView={ {
							type: 'table',
							page: 1,
							perPage: 20,
							search: '',
							filters: [],
							sort: {
								field: 'last_seen',
								direction: 'desc',
							},
							titleField: 'path',
							fields: [
								'count',
								'last_seen',
								'referrer_origin',
							],
							layout: { density: 'balanced' },
						} }
					/>
				</div>
			</div>
		);
	}

	if ( isTasks ) {
		const fields = getTaskFields();
		return (
			<div className="functionalities-wp7-workspace__grid">
				<TaskForm projects={ payload.projects } onCreated={ load } />
				<div className="functionalities-wp7-workspace__view">
					<h3>{ __( 'Tasks', 'functionalities' ) }</h3>
					<ManagedDataView
						data={ payload.tasks }
						fields={ fields }
						initialView={ {
							type: 'table',
							page: 1,
							perPage: 20,
							search: '',
							filters: [],
							sort: { field: 'created', direction: 'desc' },
							titleField: 'text',
							fields: [
								'project_name',
								'completed',
								'priority',
								'tags',
								'created',
							],
							layout: { density: 'balanced' },
						} }
					/>
				</div>
			</div>
		);
	}

	if ( isAi ) {
		return <AiExplanationForm />;
	}
	return null;
}

function mountWorkspace() {
	if ( ! config.isFunctionalities ) {
		return;
	}
	const supportedModules = [
		'redirect-manager',
		'task-manager',
		'assumption-detection',
		'content-regression',
	];
	if (
		! supportedModules.includes( config.module ) &&
		config.page !== 'functionalities-task-manager'
	) {
		return;
	}
	const host = document.createElement( 'section' );
	host.className = 'functionalities-wp7-workspace';
	host.innerHTML = `<h2>${ config.i18n.modernTools }</h2><div data-functionalities-wp7-root></div>`;
	const target =
		document.querySelector( '.functionalities-module' ) ||
		document.querySelector( '.wrap' );
	if ( ! target ) {
		return;
	}
	const firstContent =
		target.querySelector( 'h1' )?.nextElementSibling || target.firstChild;
	target.insertBefore( host, firstContent );
	createRoot( host.querySelector( '[data-functionalities-wp7-root]' ) ).render(
		<Workspace />
	);
}

document.addEventListener( 'DOMContentLoaded', () => {
	mountWorkspace();
} );
