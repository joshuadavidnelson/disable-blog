/**
 * Which comments the plugin returns, for the cases the post leak crawl does
 * not reach. Every comment type on a post is hidden: from lists, by ID, by
 * parent, over REST for every method and over XML-RPC. Comments on a page
 * stay reachable, and so do comments with no parent post. Only discussion
 * comments follow `dwpb_post_types_supporting_comments`.
 *
 * The admin comment screens treat a comment on a post as a missing one.
 * Privacy exports and erasures, and the replies and reactions core trashes
 * or deletes with a note, still reach comments on posts.
 *
 * Disable Comments, or `dwpb_filter_comment_queries` returning `false`,
 * switches the rule off.
 */

/**
 * External dependencies
 */
import { request as playwrightRequest } from '@playwright/test';
import type { APIRequestContext } from '@playwright/test';

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { createContentTracker } from '../../config/content-tracker';
import { FIXTURE_TOGGLES, resetFixtures, setFixtures } from '../../config/fixtures';
import { resetFilterOverrides, setFilterOverrides } from '../../config/filter-overrides';
import { CUSTOM_COMMENT_TYPE, adminFetch, makeMarker, versionAtLeast, wpVersion } from '../../config/leak';
import type { SeededPost } from '../../config/seed';
import { ADMIN_PASSWORD, ADMIN_USERNAME, callXmlRpc, expectXmlRpcFault } from '../../config/xmlrpc';
import type { XmlRpcValue } from '../../config/xmlrpc';
import { wpCliOk } from '../../config/wp-cli';

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

interface CommentQuery {
	post?: number;
	type?: string;
	include?: string;
	status?: string;
}

const XMLRPC_COMMENT_METHODS = [ 'wp.getComment', 'wp.editComment', 'wp.deleteComment' ];

test.describe.configure( { mode: 'serial' } );

test.describe( 'compat: comment scope', () => {
	const tracker = createContentTracker();
	const orphanIds: number[] = [];

	let anon: APIRequestContext;
	let admin: RequestUtils;
	let notes: boolean;
	let post: SeededPost;
	let page: SeededPost;
	let postMarker: string;
	let pageMarker: string;
	let orphanMarker: string;
	let postCommentIds: number[];

	/**
	 * Seed a marked discussion comment, a custom-type comment and, where core
	 * supports them, a note on a post or page.
	 *
	 * @param requestUtils Admin request utils.
	 * @param parent       Post or page.
	 * @param marker       Marker each comment carries.
	 */
	async function seedComments( requestUtils: RequestUtils, parent: SeededPost, marker: string ): Promise< number[] > {
		const ids: number[] = [];

		for ( const type of [ 'comment', CUSTOM_COMMENT_TYPE, ...( notes ? [ 'note' ] : [] ) ] ) {
			const { id } = await tracker.seedComment( requestUtils, {
				postId: parent.id,
				type,
				content: `${ type } ${ marker }`,
			} );
			ids.push( id );
		}

		return ids;
	}

	/**
	 * Insert a marked comment whose `comment_post_ID` names no post, which
	 * the seeding route refuses.
	 *
	 * @param postId Parent post ID that does not exist.
	 * @param type   Comment type.
	 * @param marker Marker the comment carries.
	 */
	async function seedOrphan( postId: number, type: string, marker: string ): Promise< void > {
		const output = await wpCliOk( [
			'eval',
			`echo (int) wp_insert_comment( array( 'comment_post_ID' => ${ postId }, 'comment_type' => '${ type }', 'comment_content' => 'orphan ${ marker }', 'comment_approved' => 1 ) );`,
		] );
		const id = Number( output.match( /\d+\s*$/ )?.[ 0 ] );

		expect( id, output ).toBeGreaterThan( 0 );
		orphanIds.push( id );
	}

	/**
	 * Whether `/wp/v2/comments` with the given query returns a comment
	 * carrying the marker, as the administrator.
	 *
	 * @param query  Query arguments.
	 * @param marker Marker to look for.
	 */
	async function visible( query: CommentQuery, marker: string ): Promise< boolean > {
		const params = new URLSearchParams( { per_page: '100' } );

		for ( const [ key, value ] of Object.entries( query ) ) {
			if ( undefined !== value ) {
				params.set( key, String( value ) );
			}
		}

		const url = `/wp-json/wp/v2/comments?${ params.toString() }`;
		const response = await adminFetch( admin, url );

		expect( response.status(), url ).toBe( 200 );

		return ( await response.text() ).includes( marker );
	}

	/**
	 * Send a request to the single comment route as the administrator. Edits
	 * send no fields, and `DELETE` does not force, so a request that gets
	 * through changes nothing a later test reads.
	 *
	 * @param id     Comment ID.
	 * @param method HTTP method.
	 * @param route  Route prefix, for checking how the route is matched.
	 */
	async function singleComment(
		id: number,
		method: Method,
		route = '/wp/v2/comments'
	): Promise< { status: number; code?: string } > {
		const response = await adminFetch(
			admin,
			`/wp-json${ route }/${ id }`,
			method,
			'GET' === method || 'DELETE' === method ? undefined : {}
		);
		const body = ( await response.json() ) as { code?: string };

		return { status: response.status(), code: body.code };
	}

	/**
	 * Arguments for one of the XML-RPC single comment methods. The edit only
	 * sets the status the comment already has.
	 *
	 * @param method Method name.
	 * @param id     Comment ID.
	 */
	function xmlrpcArgs( method: string, id: number ): XmlRpcValue[] {
		const args: XmlRpcValue[] = [ 0, ADMIN_USERNAME, ADMIN_PASSWORD, id ];

		return 'wp.editComment' === method ? [ ...args, { status: 'approve' } ] : args;
	}

	/**
	 * Run PHP through `wp eval` and parse the JSON it echoes.
	 *
	 * @param lines PHP statements.
	 */
	async function evalJson( lines: string[] ): Promise< unknown > {
		const output = await wpCliOk( [ 'eval', lines.join( ' ' ) ] );
		const json = output.match( /[\[{].*[\]}]/s )?.[ 0 ];

		expect( json, output ).toBeDefined();

		return JSON.parse( json ?? 'null' );
	}

	test.beforeAll( async ( { requestUtils } ) => {
		admin = requestUtils;
		anon = await playwrightRequest.newContext( { baseURL: requestUtils.baseURL } );
		notes = versionAtLeast( await wpVersion(), '6.9' );

		postMarker = makeMarker( 'dwpbscopepost' );
		pageMarker = makeMarker( 'dwpbscopepage' );
		orphanMarker = makeMarker( 'dwpbscopeorphan' );

		post = await tracker.seedPost( requestUtils, { title: postMarker, commentStatus: 'open' } );
		page = await tracker.seedPage( requestUtils, { title: pageMarker, commentStatus: 'open' } );
		postCommentIds = await seedComments( requestUtils, post, postMarker );
		await seedComments( requestUtils, page, pageMarker );

		for ( const type of [ 'comment', CUSTOM_COMMENT_TYPE ] ) {
			await seedOrphan( 0, type, orphanMarker );
			await seedOrphan( 2_000_000_000, type, orphanMarker );
		}
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
		await resetFixtures( requestUtils, [ FIXTURE_TOGGLES.integrationsDisableComments ] );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		if ( orphanIds.length > 0 ) {
			await wpCliOk( [ 'comment', 'delete', ...orphanIds.map( String ), '--force' ] );
		}

		await tracker.cleanup( requestUtils );
		await anon?.dispose();
	} );

	test( 'with no post type supporting comments, non-discussion types on a page are still returned', async ( {
		requestUtils,
	} ) => {
		// Control: the page's discussion comment is returned while pages support comments.
		expect( await visible( { post: page.id }, pageMarker ) ).toBe( true );

		await setFilterOverrides( requestUtils, { dwpb_post_types_supporting_comments: false } );

		expect( await visible( { post: page.id, type: CUSTOM_COMMENT_TYPE }, pageMarker ) ).toBe( true );

		if ( notes ) {
			expect( await visible( { post: page.id, type: 'note' }, pageMarker ) ).toBe( true );
		}

		expect( await visible( { post: page.id }, pageMarker ) ).toBe( false );
	} );

	test( 'no comment type on a post is returned, by list, by ID, or by parent', async ( { request } ) => {
		// Control: the same queries find the page's comments.
		expect( await visible( { type: 'all' }, pageMarker ) ).toBe( true );

		// `status=any` with `type=all` leaves core's WHERE clause empty.
		expect( await visible( { type: 'all', status: 'any' }, pageMarker ) ).toBe( true );
		expect( await visible( { type: 'all', status: 'any' }, postMarker ) ).toBe( false );

		for ( const type of [ undefined, CUSTOM_COMMENT_TYPE, 'all', ...( notes ? [ 'note' ] : [] ) ] ) {
			expect( await visible( { type }, postMarker ), String( type ) ).toBe( false );
			expect( await visible( { type, include: postCommentIds.join( ',' ) }, postMarker ), String( type ) ).toBe(
				false
			);
		}

		for ( const id of postCommentIds ) {
			for ( const method of [ 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ] as const ) {
				const res = await singleComment( id, method );

				expect( res.status, `${ method } ${ id }` ).toBe( 404 );
				expect( res.code, `${ method } ${ id }` ).toBe( 'rest_comment_invalid_id' );
			}
		}

		// Core matches REST routes without regard to case.
		const upper = await singleComment( postCommentIds[ 0 ], 'GET', '/wp/v2/COMMENTS' );

		expect( upper ).toEqual( { status: 404, code: 'rest_comment_invalid_id' } );

		const multicall = await callXmlRpc( request, 'system.multicall', [
			XMLRPC_COMMENT_METHODS.map( ( methodName ) => ( {
				methodName,
				params: xmlrpcArgs( methodName, postCommentIds[ 0 ] ),
			} ) ),
		] );

		expect( multicall ).not.toContain( postMarker );
		expect(
			[ ...multicall.matchAll( /<name>faultCode<\/name>\s*<value>\s*<int>404<\/int>/g ) ],
			multicall
		).toHaveLength( XMLRPC_COMMENT_METHODS.length );

		for ( const method of XMLRPC_COMMENT_METHODS ) {
			const body = await callXmlRpc( request, method, xmlrpcArgs( method, postCommentIds[ 0 ] ) );

			expect( body, method ).not.toContain( postMarker );
			expectXmlRpcFault( body, 404 );
		}
	} );

	test( 'XML-RPC checks the credentials before saying a comment on a post does not exist', async ( {
		request,
	} ) => {
		const body = await callXmlRpc( request, 'wp.getComment', [ 0, ADMIN_USERNAME, 'wrong', postCommentIds[ 0 ] ] );

		expectXmlRpcFault( body, 403 );
	} );

	test( 'a comment with no parent post is still returned, whatever its type', async ( { requestUtils } ) => {
		const ids = async (): Promise< number[] > => {
			const response = await adminFetch(
				admin,
				`/wp-json/wp/v2/comments?type=all&include=${ orphanIds.join( ',' ) }&per_page=100`
			);

			return ( ( await response.json() ) as { id: number }[] ).map( ( comment ) => comment.id ).sort();
		};

		expect( await ids() ).toEqual( [ ...orphanIds ].sort() );

		await setFilterOverrides( requestUtils, { dwpb_post_types_supporting_comments: false } );

		expect( await ids() ).toEqual( [ ...orphanIds ].sort() );
	} );

	test( 'the comments feed lists comments on a page and none on a post', async () => {
		for ( const url of [ '/comments/feed/', '/comments/feed/atom/', '/?feed=comments-rss2' ] ) {
			const response = await anon.get( url );
			const body = await response.text();

			expect( response.status(), url ).toBe( 200 );
			expect( body, url ).toContain( pageMarker );
			expect( body, url ).not.toContain( postMarker );
		}
	} );

	test( 'with Disable Comments active, comment requests are untouched', async ( { request, requestUtils } ) => {
		await setFixtures( requestUtils, { [ FIXTURE_TOGGLES.integrationsDisableComments ]: true } );

		expect( await visible( { type: 'all' }, postMarker ) ).toBe( true );
		expect( ( await singleComment( postCommentIds[ 0 ], 'PUT' ) ).status ).toBe( 200 );
		expect( await callXmlRpc( request, 'wp.getComment', xmlrpcArgs( 'wp.getComment', postCommentIds[ 0 ] ) ) ).toContain(
			postMarker
		);
	} );

	test( 'cached comment queries and counts are cleared when the rule can change', async () => {
		const result = await evalJson( [
			"require_once DWPB_DIR . '/includes/class-disable-blog-activator.php';",
			"require_once DWPB_DIR . '/includes/class-disable-blog-deactivator.php';",
			'$probe = function ( $change ) {',
			"	wp_cache_set( 'comments-0', 'cached', 'counts' );",
			"	$before = wp_cache_get_last_changed( 'comment' );",
			'	usleep( 2000 );',
			'	$change();',
			"	return array( wp_cache_get_last_changed( 'comment' ) !== $before, false === wp_cache_get( 'comments-0', 'counts' ) );",
			'};',
			'echo wp_json_encode( array(',
			"	'disable comments on' => $probe( function () { do_action( 'activated_plugin', 'disable-comments/disable-comments.php', false ); } ),",
			"	'disable comments off' => $probe( function () { do_action( 'deactivated_plugin', 'disable-comments/disable-comments.php', false ); } ),",
			"	'another plugin' => $probe( function () { do_action( 'activated_plugin', 'hello-dolly/hello.php', false ); } ),",
			"	'activate' => $probe( array( 'Disable_Blog_Activator', 'activate' ) ),",
			"	'deactivate' => $probe( array( 'Disable_Blog_Deactivator', 'deactivate' ) ),",
			') );',
		] );

		expect( result ).toEqual( {
			'disable comments on': [ true, true ],
			'disable comments off': [ true, true ],
			'another plugin': [ false, false ],
			activate: [ true, true ],
			deactivate: [ true, true ],
		} );
	} );

	test( 'dwpb_filter_comment_queries false switches the rule off', async ( { requestUtils } ) => {
		await setFilterOverrides( requestUtils, { dwpb_filter_comment_queries: false } );

		expect( await visible( { type: 'all' }, postMarker ) ).toBe( true );
		expect( ( await singleComment( postCommentIds[ 0 ], 'PUT' ) ).status ).toBe( 200 );
	} );

	test( 'a post type registered after the supported list is cached still gets its discussion comments', async () => {
		const result = await evalJson( [
			"dwpb_post_types_with_feature( 'comments' );",
			"register_post_type( 'dwpb_late', array( 'public' => true, 'supports' => array( 'title', 'comments' ) ) );",
			"$parent = wp_insert_post( array( 'post_type' => 'dwpb_late', 'post_status' => 'publish', 'post_title' => 'late' ) );",
			"$id = (int) wp_insert_comment( array( 'comment_post_ID' => $parent, 'comment_content' => 'late', 'comment_approved' => 1 ) );",
			"$found = get_comments( array( 'post_id' => $parent, 'fields' => 'ids' ) );",
			'wp_delete_post( $parent, true );',
			'echo wp_json_encode( array_map( function ( $found ) use ( $id ) { return (int) $found === $id; }, $found ) );',
		] );

		expect( result ).toEqual( [ true ] );
	} );

	test( 'privacy export and erasure still include comments on posts', async () => {
		const email = `${ makeMarker( 'dwpbprivacy' ) }@example.com`;
		const result = await evalJson( [
			'$ids = array();',
			`foreach ( array( ${ post.id }, ${ page.id } ) as $parent ) {`,
			`	$ids[] = (int) wp_insert_comment( array( 'comment_post_ID' => $parent, 'comment_author' => 'Privacy', 'comment_author_email' => '${ email }', 'comment_content' => 'privacy', 'comment_approved' => 1 ) );`,
			'}',
			// Cache the filtered result under the arguments each core version's exporter uses.
			'$primed = 0;',
			"foreach ( array( 'orderby', 'order_by' ) as $key ) {",
			`	$primed = count( get_comments( array( 'author_email' => '${ email }', 'number' => 500, 'paged' => 1, $key => 'comment_ID', 'order' => 'ASC', 'update_comment_meta_cache' => false ) ) );`,
			'}',
			"$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );",
			"$erasers = apply_filters( 'wp_privacy_personal_data_erasers', array() );",
			`$export = call_user_func( $exporters['wordpress-comments']['callback'], '${ email }', 1 );`,
			`$erase = call_user_func( $erasers['wordpress-comments']['callback'], '${ email }', 1 );`,
			'global $wpdb;',
			`$left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_author_email = %s", '${ email }' ) );`,
			"echo wp_json_encode( array( 'primed' => $primed, 'exported' => count( $export['data'] ), 'anonymized' => count( $ids ) - $left ) );",
		] );

		expect( result ).toEqual( { primed: 1, exported: 2, anonymized: 2 } );
	} );

	test( 'trashing or deleting a note on a post takes its replies and reactions with it', async () => {
		test.skip( ! notes, 'Notes need WordPress 6.9.' );

		const reactions = versionAtLeast( await wpVersion(), '7.2' );
		const result = ( await evalJson( [
			'$add = function ( $type, $parent = 0 ) {',
			`	return (int) wp_insert_comment( array( 'comment_post_ID' => ${ post.id }, 'comment_type' => $type, 'comment_parent' => $parent, 'comment_content' => $type, 'comment_approved' => 1 ) );`,
			'};',
			'$status = function ( $id ) {',
			'	global $wpdb;',
			'	$value = $wpdb->get_var( $wpdb->prepare( "SELECT comment_approved FROM {$wpdb->comments} WHERE comment_ID = %d", $id ) );',
			"	return null === $value ? 'deleted' : $value;",
			'};',
			"$note = $add( 'note' );",
			"$reply = $add( 'note', $note );",
			"$reaction = $add( 'reaction', $note );",
			"$other = $add( 'note' );",
			"$add( 'note', $other );",
			'wp_trash_comment( $note );',
			// Only the children of a comment being trashed or deleted are exempt.
			"$others = count( get_comments( array( 'parent__in' => array( $note, $other ), 'type' => 'note', 'status' => 'all', 'fields' => 'ids' ) ) );",
			"$doomed = $add( 'note' );",
			"$doomed_reaction = $add( 'reaction', $doomed );",
			'wp_delete_comment( $doomed, true );',
			'echo wp_json_encode( array(',
			"	'reply' => $status( $reply ),",
			"	'reaction' => $status( $reaction ),",
			"	'deleted reaction' => $status( $doomed_reaction ),",
			"	'others' => $others,",
			') );',
		] ) ) as Record< string, string | number >;

		expect( result.reply ).toBe( 'trash' );
		expect( result.others ).toBe( 0 );

		if ( reactions ) {
			expect( result.reaction ).toBe( 'trash' );
			expect( result[ 'deleted reaction' ] ).toBe( 'deleted' );
		}
	} );

	test( 'the admin edit screen treats a comment on a post as an invalid ID', async () => {
		const editScreen = async ( id: number ) =>
			admin.request.get( `/wp-admin/comment.php?action=editcomment&c=${ id }`, { maxRedirects: 0 } );

		const pageComment = ( await adminFetch(
			admin,
			`/wp-json/wp/v2/comments?post=${ page.id }&per_page=1`
		).then( ( response ) => response.json() ) ) as { id: number }[];

		// Control: the same screen shows a comment on a page.
		const control = await editScreen( pageComment[ 0 ].id );

		expect( control.status() ).toBe( 200 );
		expect( await control.text() ).toContain( pageMarker );

		const blocked = await editScreen( postCommentIds[ 0 ] );
		const body = await blocked.text();

		expect( blocked.status() ).toBe( 200 );
		expect( body ).toContain( 'Invalid comment ID.' );
		expect( body ).not.toContain( postMarker );

		for ( const query of [ 'action=trash', 'action=cdc', 'action=mac', 'dt=spam' ] ) {
			const moderate = await admin.request.get( `/wp-admin/comment.php?${ query }&c=${ postCommentIds[ 0 ] }`, {
				maxRedirects: 0,
			} );

			expect( moderate.status(), query ).toBe( 302 );
			expect( moderate.headers().location, query ).toContain( 'edit-comments.php?error=1' );
		}
	} );

	test( 'XML-RPC fires xmlrpc_call where core does before saying a comment on a post does not exist', async () => {
		const result = await evalJson( [
			"require_once ABSPATH . WPINC . '/class-IXR.php';",
			"require_once ABSPATH . WPINC . '/class-wp-xmlrpc-server.php';",
			'$GLOBALS[\'wp_xmlrpc_server\'] = new wp_xmlrpc_server();',
			// serve_request() hands the methods to IXR_Server before a request is read.
			"$GLOBALS['wp_xmlrpc_server']->callbacks = $GLOBALS['wp_xmlrpc_server']->methods;",
			'$seen = array();',
			"add_action( 'xmlrpc_call', function ( $name ) use ( &$seen ) { $seen[] = $name; } );",
			'$call = function ( $method, $args ) use ( &$seen ) {',
			'	$seen = array();',
			"	$result = $GLOBALS['wp_xmlrpc_server']->call( $method, $args );",
			"	return array( $seen, $result instanceof IXR_Error ? $result->code : 'ok' );",
			'};',
			'echo wp_json_encode( array(',
			`	'get' => $call( 'wp.getComment', array( 0, '${ ADMIN_USERNAME }', '${ ADMIN_PASSWORD }', ${ postCommentIds[ 0 ] } ) ),`,
			`	'edit' => $call( 'wp.editComment', array( 0, '${ ADMIN_USERNAME }', '${ ADMIN_PASSWORD }', ${ postCommentIds[ 0 ] }, array( 'status' => 'approve' ) ) ),`,
			`	'delete' => $call( 'wp.deleteComment', array( 0, '${ ADMIN_USERNAME }', '${ ADMIN_PASSWORD }', ${ postCommentIds[ 0 ] } ) ),`,
			') );',
		] );

		expect( result ).toEqual( {
			get: [ [ 'wp.getComment' ], 404 ],
			edit: [ [], 404 ],
			delete: [ [], 404 ],
		} );
	} );

	test( 'an upgrade clears cached comment queries and counts', async () => {
		const result = await evalJson( [
			"$saved = array( 'dwpb_version' => get_option( 'dwpb_version' ), 'dwpb_previous_version' => get_option( 'dwpb_previous_version' ) );",
			"defined( 'WP_ADMIN' ) || define( 'WP_ADMIN', true );",
			"$check = new ReflectionMethod( 'Disable_Blog', 'upgrade_check' );",
			'$check->setAccessible( true );',
			'$probe = function () use ( $check ) {',
			"	wp_cache_set( 'comments-0', 'cached', 'counts' );",
			"	$before = wp_cache_get_last_changed( 'comment' );",
			'	usleep( 2000 );',
			'	$check->invoke( null );',
			"	return array( wp_cache_get_last_changed( 'comment' ) !== $before, false === wp_cache_get( 'comments-0', 'counts' ) );",
			'};',
			"update_option( 'dwpb_version', '0.5.5', false );",
			'$result = array( $probe(), $probe() );',
			'foreach ( $saved as $name => $value ) {',
			'	false === $value ? delete_option( $name ) : update_option( $name, $value, false );',
			'}',
			'echo wp_json_encode( $result );',
		] );

		// The second check finds the version current and leaves the cache alone.
		expect( result ).toEqual( [
			[ true, true ],
			[ false, false ],
		] );
	} );
} );
