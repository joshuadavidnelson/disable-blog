const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );

const {
	extractJson,
	diffSurface,
	renderReport,
	renderFailure,
} = require( './surface' );

test( 'extractJson ignores wp-env noise around the markers', () => {
	const out =
		'ℹ Starting...\n---DWPB-SURFACE-BEGIN---\n{"a":1}\n---DWPB-SURFACE-END---\n✔ Ran in 2s\n';
	assert.deepEqual( extractJson( out ), { a: 1 } );
} );

test( 'extractJson throws when a marker is missing', () => {
	assert.throws( () => extractJson( '{"a":1}' ) );
	assert.throws( () => extractJson( '---DWPB-SURFACE-BEGIN---\n{"a":1}\n' ) );
	assert.throws( () => extractJson( '{"a":1}\n---DWPB-SURFACE-END---\n' ) );
} );

test( 'diffSurface reports an added list entry', () => {
	const c = diffSurface(
		{ post_type_props: [ 'public' ] },
		{ post_type_props: [ 'public', 'show_in_abilities' ] }
	);
	assert.deepEqual( c, [
		{ section: 'post_type_props', kind: 'added', key: 'show_in_abilities' },
	] );
} );

test( 'diffSurface reports a removed list entry', () => {
	const c = diffSurface(
		{ widgets: [ 'WP_Widget_Links', 'WP_Widget_Pages' ] },
		{ widgets: [ 'WP_Widget_Pages' ] }
	);
	assert.deepEqual( c, [
		{ section: 'widgets', kind: 'removed', key: 'WP_Widget_Links' },
	] );
} );

test( 'diffSurface reports a changed scalar and a changed hash', () => {
	const hashKey = 'WP_Site_Health::get_test_rest_availability';
	const c = diffSurface(
		{
			post_type_post: { public: true, rest_base: 'posts' },
			core_copies: { [ hashKey ]: 'aaa' },
		},
		{
			post_type_post: { public: false, rest_base: 'posts' },
			core_copies: { [ hashKey ]: 'bbb' },
		}
	);
	assert.deepEqual( c, [
		{
			section: 'core_copies',
			kind: 'changed',
			key: hashKey,
			before: 'aaa',
			after: 'bbb',
		},
		{
			section: 'post_type_post',
			kind: 'changed',
			key: 'public',
			before: true,
			after: false,
		},
	] );
} );

test( 'diffSurface walks nested lists', () => {
	const c = diffSurface(
		{
			site_health_tests: {
				direct: [ 'php_version' ],
				async: [ 'dotorg_communication' ],
			},
			block_post_defaults: { 'core/query': [ 'query.postType=post' ] },
		},
		{
			site_health_tests: {
				direct: [ 'php_version', 'post_check' ],
				async: [ 'dotorg_communication' ],
			},
			block_post_defaults: {
				'core/query': [ 'query.postType=post', 'query.taxonomy=category' ],
			},
		}
	);
	assert.deepEqual( c, [
		{
			section: 'block_post_defaults',
			kind: 'added',
			key: 'core/query: query.taxonomy=category',
		},
		{ section: 'site_health_tests', kind: 'added', key: 'direct: post_check' },
	] );
} );

test( 'diffSurface ignores wp_version and returns [] for equal input', () => {
	const surface = {
		wp_version: '7.1',
		post_type_props: [ 'public' ],
		admin_menu: { top: [ 'edit.php' ], sub: [ 'edit.php > post-new.php' ] },
	};
	assert.deepEqual(
		diffSurface( surface, { ...surface, wp_version: '7.2' } ),
		[]
	);
} );

test( 'diffSurface treats a section missing from current as all removed', () => {
	const c = diffSurface(
		{
			abilities: [ 'core/get-site-info', 'core/get-user-info' ],
			admin_menu: { top: [ 'edit.php' ], sub: [] },
		},
		{}
	);
	assert.deepEqual( c, [
		{ section: 'abilities', kind: 'removed', key: 'core/get-site-info' },
		{ section: 'abilities', kind: 'removed', key: 'core/get-user-info' },
		{ section: 'admin_menu', kind: 'removed', key: 'top: edit.php' },
	] );
} );

test( 'renderReport returns the green summary for no changes', () => {
	const report = renderReport( [], '7.1', '7.1' );
	assert.equal( report.trim().split( '\n' ).length, 1 );
	assert.match( report, /no tracked core surface changed/i );
	assert.match( report, /7\.1/ );
} );

test( 'extractJson finds the END marker after BEGIN, not before it', () => {
	const out =
		'---DWPB-SURFACE-END---\n---DWPB-SURFACE-BEGIN---\n{"a":1}\n---DWPB-SURFACE-END---\n';
	assert.deepEqual( extractJson( out ), { a: 1 } );
} );

test( 'diffSurface treats a section present only in current as all added', () => {
	const c = diffSurface(
		{},
		{
			abilities: [ 'core/get-site-info' ],
			admin_menu: { top: [ 'edit.php' ] },
		}
	);
	assert.deepEqual( c, [
		{ section: 'abilities', kind: 'added', key: 'core/get-site-info' },
		{ section: 'admin_menu', kind: 'added', key: 'top: edit.php' },
	] );
} );

test( 'diffSurface carries the value of an added or removed scalar', () => {
	const c = diffSurface(
		{ post_type_post: { public: true, rewrite: false } },
		{ post_type_post: { public: true, randomable: true } }
	);
	assert.deepEqual( c, [
		{ section: 'post_type_post', kind: 'added', key: 'randomable', after: true },
		{ section: 'post_type_post', kind: 'removed', key: 'rewrite', before: false },
	] );
} );

test( 'diffSurface joins nested object keys as outer.inner', () => {
	const c = diffSurface(
		{ section: { outer: { inner: 'a', list: [ 'x' ] } } },
		{ section: { outer: { inner: 'b', list: [ 'x', 'y' ] } } }
	);
	assert.deepEqual( c, [
		{
			section: 'section',
			kind: 'changed',
			key: 'outer.inner',
			before: 'a',
			after: 'b',
		},
		{ section: 'section', kind: 'added', key: 'outer.list: y' },
	] );
} );

test( 'diffSurface sorts by section, then key, whatever the input order', () => {
	const c = diffSurface(
		{ widgets: [ 'B', 'D' ], abilities: [ 'z' ] },
		{ widgets: [ 'C', 'A', 'D' ], abilities: [ 'y' ] }
	);
	assert.deepEqual(
		c.map( ( change ) => `${ change.section } ${ change.kind } ${ change.key }` ),
		[
			'abilities added y',
			'abilities removed z',
			'widgets added A',
			'widgets removed B',
			'widgets added C',
		]
	);
} );

test( 'renderReport groups changes by section with one line per change', () => {
	const report = renderReport(
		[
			{ section: 'post_type_post', kind: 'added', key: 'randomable', after: true },
			{ section: 'post_type_post', kind: 'changed', key: 'public', before: true, after: false },
			{ section: 'widgets', kind: 'removed', key: 'WP_Widget_Links' },
			{ section: 'widgets', kind: 'removed', key: 'rewrite_old', before: 'x' },
		],
		'7.1',
		'7.2'
	);
	assert.equal(
		report,
		[
			'## Core surface changes: WordPress 7.2 against the 7.1 baseline',
			'',
			'4 changes to the parts of core Disable Blog depends on.',
			'',
			'### `post_type_post`',
			'',
			'- added: `randomable` = `true`',
			'- changed: `public` from `true` to `false`',
			'',
			'### `widgets`',
			'',
			'- removed: `WP_Widget_Links`',
			'- removed: `rewrite_old` (was `"x"`)',
			'',
		].join( '\n' )
	);
} );

test( 'renderFailure names the error so no stale report survives', () => {
	const report = renderFailure( new Error( 'Surface dump failed: the admin menu came back empty.' ) );
	assert.match( report, /^## Core surface check failed/ );
	assert.match( report, /Surface dump failed: the admin menu came back empty\./ );
} );
