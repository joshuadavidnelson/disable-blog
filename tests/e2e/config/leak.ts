/**
 * Probe discovery, the marker search and the count comparison behind the
 * post leak crawl (`specs/compat/post-leak.spec.ts`).
 *
 * A probe is one request a visitor or an administrator can make. A hit is a
 * probe whose response gives the seeded post away: its body carries the
 * marker, or, for the probes that cannot echo a marker, its final URL or a
 * user ID matches. Hits are checked against `tests/compat/leak-allowlist.json`.
 */

/**
 * External dependencies
 */
import { randomBytes } from 'node:crypto';
import type { APIRequestContext, APIResponse } from '@playwright/test';
import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import type { SeededPost } from './seed';
import { ADMIN_PASSWORD, ADMIN_USERNAME, methodCallXml } from './xmlrpc';
import type { XmlRpcValue } from './xmlrpc';
import { wpCliOk } from './wp-cli';

export const CUSTOM_COMMENT_TYPE = 'dwpb_custom';

export type Actor = 'anon' | 'admin';

export interface Probe {
	surface: 'rest' | 'front' | 'xmlrpc' | 'ability' | 'count';
	actor: Actor;
	method: 'GET' | 'POST';
	url: string;
	body?: unknown;
	/**
	 * A hit when any redirect along the way lands on this URL. The body is not
	 * searched: below 7.2 the random redirect probes are plain archives, which
	 * other probes already cover.
	 */
	matchUrl?: string;
	/** A hit when the JSON body lists a user with this ID. */
	matchUserId?: number;
	/** A hit when the body contains this text, as well as the marker. */
	matchText?: string;
}

export interface Hit extends Probe {
	status: number;
	/** The text around the first marker in the body, for the failure report. */
	excerpt?: string;
}

export interface AllowEntry {
	surface: Probe[ 'surface' ];
	actor: Actor;
	urlPattern: string;
	reason: string;
	minWp?: string;
}

export interface ProbeContext {
	anon: APIRequestContext;
	admin: RequestUtils;
}

const ACTORS: Actor[] = [ 'anon', 'admin' ];

const REQUEST_TIMEOUT = 60_000;

/**
 * A marker no other content on the site carries: the prefix plus 12
 * lowercase hex characters, with nothing `sanitize_title()` would change.
 *
 * @param prefix Lowercase letters.
 */
export function makeMarker( prefix: string ): string {
	return prefix + randomBytes( 6 ).toString( 'hex' );
}

/**
 * The search term for a marker. Search screens print the term back, so the
 * term drops the marker's last two characters and only a matched item can
 * put the whole marker in the response.
 *
 * @param marker Marker to search for.
 */
export function searchTerm( marker: string ): string {
	return marker.slice( 0, -2 );
}

/**
 * The tests site's WordPress version, e.g. `7.2-RC2`.
 */
export async function wpVersion(): Promise< string > {
	return wpCliOk( [ 'core', 'version' ] );
}

/**
 * Whether a WordPress version is at least a minimum. Only the numeric
 * `x.y.z` prefix is compared, so `7.2-RC2` counts as 7.2.
 *
 * @param version Version to test.
 * @param minimum Version to compare against.
 */
export function versionAtLeast( version: string, minimum: string ): boolean {
	const parse = ( value: string ): number[] =>
		( value.match( /^\d+(\.\d+)*/ )?.[ 0 ] ?? '0' ).split( '.' ).map( Number );

	const actual = parse( version );
	const wanted = parse( minimum );

	for ( let i = 0; i < Math.max( actual.length, wanted.length ); i++ ) {
		const difference = ( actual[ i ] ?? 0 ) - ( wanted[ i ] ?? 0 );

		if ( 0 !== difference ) {
			return difference > 0;
		}
	}

	return true;
}

/**
 * Send a request as the administrator, with the REST nonce cookie auth needs.
 *
 * @param admin  Admin request utils.
 * @param url    Path or absolute URL.
 * @param method HTTP method.
 * @param data   Request body.
 */
export async function adminFetch(
	admin: RequestUtils,
	url: string,
	method: 'GET' | 'POST' = 'GET',
	data?: unknown
): Promise< APIResponse > {
	if ( ! admin.storageState?.nonce ) {
		await admin.setupRest();
	}

	return admin.request.fetch( url, {
		method,
		data,
		headers: { 'X-WP-Nonce': admin.storageState?.nonce ?? '' },
		timeout: REQUEST_TIMEOUT,
	} );
}

/**
 * Names of the registered abilities, or none where the Abilities API is
 * absent.
 *
 * @param admin Admin request utils.
 */
export async function listAbilities( admin: RequestUtils ): Promise< string[] > {
	const response = await adminFetch( admin, '/wp-json/wp-abilities/v1/abilities?per_page=100' );

	if ( ! response.ok() ) {
		return [];
	}

	const abilities = ( await response.json() ) as { name: string }[];

	return abilities.map( ( ability ) => ability.name );
}

interface RestIndexEndpoint {
	methods: string[];
	args?: Record< string, { required?: boolean } >;
}

/**
 * One probe per actor for every REST route that needs no ID and no
 * required argument, with a search for the marker where the route takes one.
 *
 * @param request Any request context; the index is the same for every user.
 * @param marker  Marker to search for.
 */
export async function discoverRestProbes( request: APIRequestContext, marker: string ): Promise< Probe[] > {
	const response = await request.get( '/wp-json/', { timeout: REQUEST_TIMEOUT } );
	const index = ( await response.json() ) as { routes: Record< string, { endpoints: RestIndexEndpoint[] } > };
	const probes: Probe[] = [];

	for ( const [ route, { endpoints } ] of Object.entries( index.routes ) ) {
		if ( route.includes( '(?P<' ) || route.startsWith( '/dwpb-test/' ) ) {
			continue;
		}

		const gets = endpoints.filter( ( endpoint ) => endpoint.methods.includes( 'GET' ) );

		if ( 0 === gets.length ) {
			continue;
		}

		if ( gets.some( ( endpoint ) => Object.values( endpoint.args ?? {} ).some( ( arg ) => arg.required ) ) ) {
			continue;
		}

		const searchable = gets.some( ( endpoint ) => undefined !== endpoint.args?.search );
		const query = searchable ? `?search=${ searchTerm( marker ) }&per_page=100` : '';

		for ( const actor of ACTORS ) {
			probes.push( { surface: 'rest', actor, method: 'GET', url: `/wp-json${ route }${ query }` } );
		}
	}

	return probes;
}

/**
 * The day archive path for a `post_date`, e.g. `/2001/02/03/`, which is
 * what the calendar links a day with posts to.
 *
 * @param date `post_date` as `Y-m-d H:i:s`.
 */
export function dayArchivePath( date: string ): string {
	const [ year, month, day ] = date.slice( 0, 10 ).split( '-' );

	return `/${ year }/${ month }/${ day }/`;
}

/**
 * The month archive path for a `post_date`, e.g. `/2001/02/`. The calendar
 * shows the current month, with a link back to the latest earlier month that
 * has posts, so a calendar names a lone old post's month.
 *
 * @param date `post_date` as `Y-m-d H:i:s`.
 */
export function monthArchivePath( date: string ): string {
	return `/${ date.slice( 0, 4 ) }/${ date.slice( 5, 7 ) }/`;
}

/**
 * The calendar block renderer. A calendar shows no titles, so a link to the
 * month of the target's date is the hit.
 *
 * @param date `post_date` as `Y-m-d H:i:s`.
 */
function calendarProbe( date?: string ): Probe {
	return {
		surface: 'rest',
		actor: 'admin',
		method: 'GET',
		url: '/wp-json/wp/v2/block-renderer/core/calendar?context=edit',
		...( undefined !== date && { matchText: monthArchivePath( date ) } ),
	};
}

type QueryValue = string | number | boolean | QueryValue[] | { [ key: string ]: QueryValue };

/**
 * Encode a value as a PHP-style bracketed query string. REST reads a GET
 * object argument only in this form, not as a JSON string.
 *
 * @param name  Parameter name.
 * @param value Parameter value.
 */
function bracketQuery( name: string, value: QueryValue ): string {
	if ( Array.isArray( value ) ) {
		return value.map( ( item ) => bracketQuery( `${ name }[]`, item ) ).join( '&' );
	}

	if ( 'object' === typeof value ) {
		return Object.entries( value )
			.map( ( [ key, item ] ) => bracketQuery( `${ name }[${ key }]`, item ) )
			.join( '&' );
	}

	const key = encodeURIComponent( name ).replace( /%5B/g, '[' ).replace( /%5D/g, ']' );

	return `${ key }=${ encodeURIComponent( String( value ) ) }`;
}

/**
 * Probes for the routes that need an ID or input: the target by ID, oEmbed,
 * search, the post-listing block renderers, every shape of comment list, and
 * the content abilities.
 *
 * @param target            Seeded target.
 * @param target.post       The post or page.
 * @param target.commentIds Every comment seeded on it.
 * @param target.noteId     The note, where one was seeded.
 * @param target.authorId   The target's author.
 * @param target.date       The target's `post_date`, which no other content shares.
 * @param marker            Marker the target carries.
 * @param abilities         Registered ability names.
 */
export function targetedProbes(
	target: { post: SeededPost; commentIds: number[]; noteId?: number; authorId?: number; date?: string },
	marker: string,
	abilities: string[]
): Probe[] {
	const { post, commentIds, noteId, authorId, date } = target;
	const both = ( url: string ): Probe[] =>
		ACTORS.map( ( actor ) => ( { surface: 'rest', actor, method: 'GET', url } ) );
	const admin = ( url: string, surface: Probe[ 'surface' ] = 'rest' ): Probe => ( {
		surface,
		actor: 'admin',
		method: 'GET',
		url,
	} );
	const comments = '/wp-json/wp/v2/comments';
	const base = 'page' === post.postType ? 'pages' : 'posts';

	const probes: Probe[] = [
		...both( `/wp-json/wp/v2/${ base }/${ post.id }` ),
		{
			surface: 'rest',
			actor: 'anon',
			method: 'GET',
			url: `/wp-json/oembed/1.0/embed?url=${ encodeURIComponent( post.permalink ) }`,
		},
		...both( `/wp-json/wp/v2/search?search=${ searchTerm( marker ) }&per_page=100` ),
		admin( '/wp-json/wp/v2/block-renderer/core/latest-posts?context=edit' ),
		calendarProbe( date ),
		...commentIds.flatMap( ( id ) => both( `${ comments }/${ id }` ) ),
		...both( `${ comments }?include=${ commentIds.join( ',' ) }&per_page=100` ),
		...both( `${ comments }?post=${ post.id }&per_page=100` ),
		...both( `${ comments }?type=all&per_page=100` ),
		...both( `${ comments }?type=${ CUSTOM_COMMENT_TYPE }&per_page=100` ),
		...both( `${ comments }?type=${ CUSTOM_COMMENT_TYPE }&post=${ post.id }&per_page=100` ),
	];

	if ( undefined !== noteId ) {
		probes.push(
			...both( `${ comments }?type=note&per_page=100` ),
			...both( `${ comments }?type=note&post=${ post.id }&per_page=100` ),
			...both( `${ comments }?parent=${ noteId }&type=reaction&per_page=100` )
		);
	}

	const run = ( ability: string, input: QueryValue ): string =>
		`/wp-json/wp-abilities/v1/abilities/${ ability }/run?${ bracketQuery( 'input', input ) }`;

	if ( abilities.includes( 'core/content-query' ) ) {
		const slug = new URL( post.permalink ).pathname.split( '/' ).filter( Boolean ).pop() ?? '';

		probes.push(
			admin( run( 'core/content-query', { where: { type: post.postType, ids: [ post.id ] } } ), 'ability' ),
			admin( run( 'core/content-query', { where: { type: post.postType, slugs: [ slug ] } } ), 'ability' )
		);
	}

	// `has_published_posts` only accepts publicly viewable post types by
	// name, so the post asks for every such type and its author must have
	// published nothing else.
	if ( abilities.includes( 'core/users-query' ) && undefined !== authorId ) {
		const types = 'post' === post.postType ? true : [ post.postType ];

		probes.push( {
			...admin( run( 'core/users-query', { where: { has_published_posts: types } } ), 'ability' ),
			matchUserId: authorId,
		} );
	}

	return probes;
}

/**
 * Front-end probes for both actors: search, feeds, the sitemap and every
 * sitemap it lists, the permalink, the random redirect from the home page
 * and from the author archive, and the term, author and date archives.
 *
 * @param post             The seeded post or page.
 * @param marker           Marker it carries.
 * @param archives         Archive URLs that lead to it.
 * @param archives.termLinks Term archive URLs.
 * @param archives.authorId  Author archive ID.
 * @param archives.date      The post's `post_date`, which no other content shares.
 * @param archives.latestPostsPage Permalink of a page with a Latest Posts block.
 * @param archives.calendarPage    Permalink of a page with a Calendar block.
 */
export function frontProbes(
	post: SeededPost,
	marker: string,
	archives: { termLinks: string[]; authorId: number; date: string; latestPostsPage?: string; calendarPage?: string }
): Probe[] {
	const path = ( url: string ): string => {
		const parsed = new URL( url );

		return parsed.pathname + parsed.search;
	};
	const permalink = path( post.permalink ).replace( /\/?$/, '/' );
	const day = dayArchivePath( archives.date );
	const urls = [
		`/?s=${ searchTerm( marker ) }`,
		'/feed/',
		'/feed/atom/',
		'/comments/feed/',
		'/comments/feed/atom/',
		'/wp-sitemap.xml',
		permalink,
		`${ permalink }feed/`,
		`${ permalink }embed/`,
		`/?p=${ post.id }`,
		`/?p=${ post.id }&feed=comments-rss2`,
		`/?embed=true&p=${ post.id }`,
		...archives.termLinks.map( path ),
		`/?author=${ archives.authorId }`,
		day,
		`/${ archives.date.slice( 0, 4 ) }/`,
	];
	const probes: Probe[] = [];

	for ( const actor of ACTORS ) {
		probes.push( ...urls.map( ( url ): Probe => ( { surface: 'front', actor, method: 'GET', url } ) ) );

		if ( undefined !== archives.latestPostsPage ) {
			probes.push( { surface: 'front', actor, method: 'GET', url: path( archives.latestPostsPage ) } );
		}

		if ( undefined !== archives.calendarPage ) {
			probes.push( {
				surface: 'front',
				actor,
				method: 'GET',
				url: path( archives.calendarPage ),
				matchText: monthArchivePath( archives.date ),
			} );
		}

		probes.push(
			...[ '/?random=1', '/random/', `/?random=1&author=${ archives.authorId }` ].map(
				( url ): Probe => ( { surface: 'front', actor, method: 'GET', url, matchUrl: post.permalink } )
			)
		);
	}

	return probes;
}

/**
 * Arguments for each XML-RPC method that reads posts or pages, built from
 * the target's ID and the credentials.
 */
const XMLRPC_READERS: Record< string, ( id: number, auth: { user: string; pass: string } ) => XmlRpcValue[] > = {
	'wp.getPost': ( id, { user, pass } ) => [ 0, user, pass, id ],
	'wp.getPosts': ( id, { user, pass } ) => [ 0, user, pass, { number: 100 } ],
	'wp.getPage': ( id, { user, pass } ) => [ 0, id, user, pass ],
	'wp.getPages': ( id, { user, pass } ) => [ 0, user, pass, 100 ],
	'blogger.getPost': ( id, { user, pass } ) => [ '', id, user, pass ],
	'blogger.getRecentPosts': ( id, { user, pass } ) => [ '', 0, user, pass, 100 ],
	'metaWeblog.getPost': ( id, { user, pass } ) => [ id, user, pass ],
	'metaWeblog.getRecentPosts': ( id, { user, pass } ) => [ 0, user, pass, 100 ],
	'mt.getRecentPostTitles': ( id, { user, pass } ) => [ 0, user, pass, 100 ],
};

/**
 * Every XML-RPC method core registers that reads posts or pages, whether or
 * not the plugin removes it. Read from core's own method map, so a reader
 * core adds fails here until it has arguments in {@link XMLRPC_READERS}.
 */
export async function discoverXmlrpcReaders(): Promise< string[] > {
	const output = await wpCliOk( [
		'eval',
		"require_once ABSPATH . WPINC . '/class-IXR.php'; require_once ABSPATH . WPINC . '/class-wp-xmlrpc-server.php'; " +
			"remove_all_filters( 'xmlrpc_methods' ); $server = new wp_xmlrpc_server(); echo wp_json_encode( array_keys( $server->methods ) );",
	] );
	const json = output.match( /\[.*\]/s )?.[ 0 ];

	if ( ! json ) {
		throw new Error( `No XML-RPC method list:\n${ output }` );
	}

	const readers = ( JSON.parse( json ) as string[] ).filter( ( name ) =>
		/\.get(Post|Posts|RecentPosts|RecentPostTitles|Page|Pages)$/.test( name )
	);
	const unknown = readers.filter( ( name ) => ! ( name in XMLRPC_READERS ) );

	if ( unknown.length > 0 ) {
		throw new Error( `XML-RPC readers with no known arguments: ${ unknown.join( ', ' ) }` );
	}

	return readers;
}

/**
 * XML-RPC probes, as the administrator: every post and page reader, the
 * comment list, and each seeded comment by ID.
 *
 * @param target            Seeded target.
 * @param target.postId     The post or page.
 * @param target.commentIds Every comment seeded on it.
 * @param readers           Reader method names, from {@link discoverXmlrpcReaders}.
 */
export function xmlrpcProbes( target: { postId: number; commentIds: number[] }, readers: string[] ): Probe[] {
	const auth = { user: ADMIN_USERNAME, pass: ADMIN_PASSWORD };
	const credentials: XmlRpcValue[] = [ 0, auth.user, auth.pass ];
	const call = ( url: string, params: XmlRpcValue[] ): Probe => ( {
		surface: 'xmlrpc',
		actor: 'admin',
		method: 'POST',
		url,
		body: params,
	} );

	return [
		...readers.map( ( name ) => call( `${ name }/${ target.postId }`, XMLRPC_READERS[ name ]( target.postId, auth ) ) ),
		call( 'wp.getComments', [ ...credentials, { number: 100 } ] ),
		...target.commentIds.map( ( id ) => call( `wp.getComment/${ id }`, [ ...credentials, id ] ) ),
	];
}

/**
 * Send one probe.
 *
 * @param probe Probe to send.
 * @param ctx   Request contexts for each actor.
 */
async function send( probe: Probe, ctx: ProbeContext ): Promise< APIResponse > {
	const maxRedirects = undefined === probe.matchUrl ? 20 : 0;

	if ( 'xmlrpc' === probe.surface ) {
		const methodName = probe.url.split( '/' )[ 0 ];

		return ctx.anon.post( '/xmlrpc.php', {
			headers: { 'Content-Type': 'text/xml' },
			data: methodCallXml( methodName, probe.body as XmlRpcValue[] ),
			timeout: REQUEST_TIMEOUT,
		} );
	}

	if ( 'anon' === probe.actor ) {
		return ctx.anon.fetch( probe.url, {
			method: probe.method,
			data: probe.body,
			maxRedirects,
			timeout: REQUEST_TIMEOUT,
		} );
	}

	if ( 'front' === probe.surface ) {
		return ctx.admin.request.fetch( probe.url, {
			method: probe.method,
			data: probe.body,
			maxRedirects,
			timeout: REQUEST_TIMEOUT,
		} );
	}

	return adminFetch( ctx.admin, probe.url, probe.method, probe.body );
}

/**
 * Whether a JSON value holds an object whose `id` or `ID` is the given user.
 *
 * @param value  Parsed JSON.
 * @param userId User ID.
 */
function listsUser( value: unknown, userId: number ): boolean {
	if ( Array.isArray( value ) ) {
		return value.some( ( item ) => listsUser( item, userId ) );
	}

	if ( value && 'object' === typeof value ) {
		const record = value as Record< string, unknown >;

		if ( Number( record.id ?? record.ID ) === userId ) {
			return true;
		}

		return Object.values( record ).some( ( item ) => listsUser( item, userId ) );
	}

	return false;
}

/**
 * Whether a response gives the target away.
 *
 * @param probe  Probe that produced it.
 * @param marker Marker to look for.
 * @param hops   Every URL the request was redirected to.
 * @param body   Final response body.
 */
function isHit( probe: Probe, marker: string, hops: string[], body: string ): boolean {
	if ( undefined !== probe.matchUrl ) {
		const trim = ( url: string ): string => url.replace( /\/+$/, '' );
		const target = trim( probe.matchUrl );

		return hops.some( ( hop ) => trim( hop ) === target );
	}

	if ( body.includes( marker ) || ( undefined !== probe.matchText && body.includes( probe.matchText ) ) ) {
		return true;
	}

	if ( undefined !== probe.matchUserId ) {
		try {
			return listsUser( JSON.parse( body ), probe.matchUserId );
		} catch {
			return false;
		}
	}

	return false;
}

/**
 * Send every probe and return the hits. Any status counts. Redirects are
 * followed and the final body is searched. The sitemap index adds a probe
 * for each sitemap it lists.
 *
 * @param probes Probes to send. Duplicates are sent once.
 * @param marker Marker to look for.
 * @param ctx    Request contexts for each actor.
 */
export async function runProbes( probes: Probe[], marker: string, ctx: ProbeContext ): Promise< Hit[] > {
	const key = ( probe: Probe ): string =>
		`${ probe.surface } ${ probe.actor } ${ probe.method } ${ probe.url } ${ JSON.stringify( probe.body ?? null ) }`;
	const seen = new Set< string >();
	const queue: Probe[] = [];
	const enqueue = ( probe: Probe ): void => {
		if ( ! seen.has( key( probe ) ) ) {
			seen.add( key( probe ) );
			queue.push( probe );
		}
	};
	const hits: Hit[] = [];

	probes.forEach( enqueue );

	for ( let i = 0; i < queue.length; i++ ) {
		const probe = queue[ i ];
		const hops: string[] = [];
		let response = await send( probe, ctx );

		// Probes that match on a redirect follow each hop themselves, since
		// the plugin redirects the post's permalink away again.
		while ( undefined !== probe.matchUrl && hops.length < 10 && response.headers().location ) {
			hops.push( new URL( response.headers().location, response.url() ).href );
			response = await send( { ...probe, url: hops[ hops.length - 1 ] }, ctx );
		}

		// JSON escapes slashes, so a rendered link like `\/2001\/02\/` is read
		// back as `/2001/02/` before matching.
		const body = ( await response.text() ).replace( /\\\//g, '/' );

		if ( 'front' === probe.surface && probe.url.endsWith( '/wp-sitemap.xml' ) ) {
			for ( const [ , loc ] of body.matchAll( /<loc>([^<]+)<\/loc>/g ) ) {
				const parsed = new URL( loc );

				enqueue( { ...probe, url: parsed.pathname + parsed.search } );
			}
		}

		if ( isHit( probe, marker, hops, body ) ) {
			const found = [ marker, probe.matchText ].find( ( text ) => undefined !== text && body.includes( text ) );
			const at = undefined === found ? -1 : body.indexOf( found );
			const excerpt =
				undefined === found
					? undefined
					: body.slice( Math.max( 0, at - 60 ), at + found.length + 20 ).replace( /\s+/g, ' ' );

			hits.push( { ...probe, status: response.status(), ...( excerpt && { excerpt } ) } );
		}
	}

	return hits;
}

/**
 * Comment totals: REST `X-WP-Total` for each actor, with the default type
 * and with `type=all`, and each field of `wp_count_comments()`. A REST total
 * the actor may not request is left out.
 *
 * @param ctx Request contexts for each actor.
 */
export async function measureCounts( ctx: ProbeContext ): Promise< Record< string, number > > {
	const counts: Record< string, number > = {};
	const lists = { default: '/wp-json/wp/v2/comments?per_page=1', all: '/wp-json/wp/v2/comments?type=all&per_page=1' };

	for ( const actor of ACTORS ) {
		for ( const [ name, url ] of Object.entries( lists ) ) {
			const response =
				'anon' === actor
					? await ctx.anon.get( url, { timeout: REQUEST_TIMEOUT } )
					: await adminFetch( ctx.admin, url );
			const total = response.headers()[ 'x-wp-total' ];

			if ( response.ok() && undefined !== total ) {
				counts[ `rest.${ actor }.${ name }` ] = Number( total );
			}
		}
	}

	const output = await wpCliOk( [ 'eval', 'echo wp_json_encode( wp_count_comments() );' ] );
	const json = output.match( /\{.*\}/s )?.[ 0 ];

	if ( ! json ) {
		throw new Error( `wp_count_comments() printed no JSON:\n${ output }` );
	}

	for ( const [ field, value ] of Object.entries( JSON.parse( json ) as Record< string, number > ) ) {
		counts[ `wp_count_comments.${ field }` ] = Number( value );
	}

	return counts;
}

/**
 * One `count` hit for each total that differs between two measurements,
 * with the total's name as its URL.
 *
 * @param before First measurement.
 * @param after  Second measurement.
 */
export function countHits( before: Record< string, number >, after: Record< string, number > ): Hit[] {
	const keys = [ ...new Set( [ ...Object.keys( before ), ...Object.keys( after ) ] ) ];

	return keys
		.filter( ( key ) => before[ key ] !== after[ key ] )
		.map( ( key ) => ( {
			surface: 'count',
			actor: key.startsWith( 'rest.anon.' ) ? 'anon' : 'admin',
			method: 'GET',
			url: key,
			status: 0,
		} ) );
}

/**
 * Split hits into leaks and allowed hits. An entry matches a hit with the
 * same surface and actor whose URL contains its pattern. Entries whose
 * `minWp` is above this version are ignored and never reported unused.
 *
 * @param hits  Hits to check.
 * @param allow Allowlist entries.
 * @param wp    WordPress version under test.
 */
export function applyAllowlist(
	hits: Hit[],
	allow: AllowEntry[],
	wp: string
): { leaks: Hit[]; unused: AllowEntry[] } {
	const applicable = allow.filter( ( entry ) => undefined === entry.minWp || versionAtLeast( wp, entry.minWp ) );
	const used = new Set< AllowEntry >();
	const leaks = hits.filter( ( hit ) => {
		const matches = applicable.filter(
			( entry ) => entry.surface === hit.surface && entry.actor === hit.actor && hit.url.includes( entry.urlPattern )
		);

		matches.forEach( ( entry ) => used.add( entry ) );

		return 0 === matches.length;
	} );

	return { leaks, unused: applicable.filter( ( entry ) => ! used.has( entry ) ) };
}
