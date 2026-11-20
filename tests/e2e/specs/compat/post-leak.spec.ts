/**
 * Post leak crawl. Seeds a published post whose title, content, slug, terms
 * and comments carry a unique marker, then requests every surface a visitor
 * or an administrator can reach and fails on any body that contains it.
 *
 * REST routes come from the live index, so a route core adds is crawled the
 * day it ships. A page with its own marker and the same comments is the
 * control: the crawl has to find it, or a quiet crawl proves nothing.
 *
 * Accepted leaks live in `tests/compat/leak-allowlist.json`. An entry that
 * no longer matches a hit fails the last test.
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
import { deletePosts, flushRewrites, seedPost, uniqueTitle } from '../../config/seed';
import { ROLE_USERS } from '../../config/roles';
import type { SeededPost } from '../../config/seed';
import { wpCliOk } from '../../config/wp-cli';
import allowlist from '../../../compat/leak-allowlist.json';
import {
	CUSTOM_COMMENT_TYPE,
	adminFetch,
	applyAllowlist,
	countHits,
	discoverRestProbes,
	discoverXmlrpcReaders,
	frontProbes,
	listAbilities,
	makeMarker,
	measureCounts,
	runProbes,
	searchTerm,
	targetedProbes,
	versionAtLeast,
	wpVersion,
	xmlrpcProbes,
} from '../../config/leak';
import type { AllowEntry, Hit, Probe } from '../../config/leak';

const allow = allowlist as AllowEntry[];

/**
 * One line per leak: where, who, and what the response showed.
 *
 * @param hit Leaked hit.
 */
const describeHit = ( hit: Hit ): string =>
	`${ hit.surface } ${ hit.actor } ${ hit.method } ${ hit.url } (${ hit.status })${ hit.excerpt ? ` … ${ hit.excerpt }` : '' }`;

interface SeededTarget {
	post: SeededPost;
	marker: string;
	authorId: number;
	date: string;
	commentIds: number[];
	pendingId: number;
	noteId?: number;
}

// Dates no other content uses, so a calendar or date archive link to the
// post's month can only come from the post.
const POST_DATE = '2001-02-03 04:05:06';
const PAGE_DATE = '2002-03-04 05:06:07';

test.describe.configure( { mode: 'serial' } );

test.describe( 'compat: post leak crawl', () => {
	const tracker = createContentTracker();
	const allHits: Hit[] = [];

	let anon: APIRequestContext;
	let wp: string;
	let notes: boolean;
	let reactions: boolean;
	let abilities: string[];
	let target: SeededTarget;
	let control: SeededTarget;
	let termLinks: string[];
	let latestPostsPage: SeededPost;
	let calendarPage: SeededPost;
	let xmlrpcReaders: string[];

	/**
	 * Seed an approved and a pending discussion comment and a custom-type
	 * comment on a target, plus a note and a reaction on that note where core
	 * supports them.
	 *
	 * @param requestUtils Admin request utils.
	 * @param post         Parent post or page.
	 * @param marker       Marker each comment carries.
	 */
	async function seedComments(
		requestUtils: RequestUtils,
		post: SeededPost,
		marker: string
	): Promise< { commentIds: number[]; pendingId: number; noteId?: number } > {
		const commentIds: number[] = [];

		for ( const type of [ 'comment', CUSTOM_COMMENT_TYPE ] ) {
			const { id } = await tracker.seedComment( requestUtils, {
				postId: post.id,
				type,
				content: `${ type } ${ marker }`,
			} );
			commentIds.push( id );
		}

		const { id: pendingId } = await tracker.seedComment( requestUtils, {
			postId: post.id,
			approved: false,
			content: `pending ${ marker }`,
		} );
		commentIds.push( pendingId );

		if ( ! notes ) {
			return { commentIds, pendingId };
		}

		const { id: noteId } = await tracker.seedComment( requestUtils, {
			postId: post.id,
			type: 'note',
			content: `note ${ marker }`,
		} );
		commentIds.push( noteId );

		if ( reactions ) {
			const { id } = await tracker.seedComment( requestUtils, {
				postId: post.id,
				type: 'reaction',
				parent: noteId,
				content: `reaction ${ marker }`,
			} );
			commentIds.push( id );
		}

		return { commentIds, pendingId, noteId };
	}

	test.beforeAll( async ( { requestUtils } ) => {
		anon = await playwrightRequest.newContext( { baseURL: requestUtils.baseURL } );
		wp = await wpVersion();
		notes = versionAtLeast( wp, '6.9' );
		reactions = versionAtLeast( wp, '7.2' );

		abilities = await listAbilities( requestUtils );
		xmlrpcReaders = await discoverXmlrpcReaders();

		const me = await requestUtils.rest< { id: number } >( { path: '/wp/v2/users/me' } );

		// The post's author publishes nothing else, so any user listing that
		// names them gives the post away.
		const users = await requestUtils.rest< { id: number; slug: string }[] >( {
			path: '/wp/v2/users',
			params: { search: ROLE_USERS.author.username },
		} );
		const postAuthor = users.find( ( user ) => user.slug === ROLE_USERS.author.username );

		if ( ! postAuthor ) {
			throw new Error( `No ${ ROLE_USERS.author.username } user; the auth project creates it.` );
		}

		const postMarker = makeMarker( 'dwpbleak' );
		const post = await tracker.seedPost( requestUtils, {
			title: postMarker,
			content: `<p>Body ${ postMarker }</p>`,
			author: postAuthor.id,
			postDate: POST_DATE,
			commentStatus: 'open',
		} );
		const category = await tracker.seedTerm( requestUtils, {
			taxonomy: 'category',
			name: `${ postMarker }cat`,
			assignTo: post.id,
		} );
		const tag = await tracker.seedTerm( requestUtils, {
			taxonomy: 'post_tag',
			name: `${ postMarker }tag`,
			assignTo: post.id,
		} );
		termLinks = [ category.link, tag.link ];
		target = {
			post,
			marker: postMarker,
			authorId: postAuthor.id,
			date: POST_DATE,
			...( await seedComments( requestUtils, post, postMarker ) ),
		};

		const pageMarker = makeMarker( 'dwpbctrl' );
		const page = await tracker.seedPage( requestUtils, {
			title: pageMarker,
			content: `<p>Body ${ pageMarker }</p>`,
			date: PAGE_DATE.replace( ' ', 'T' ),
			commentStatus: 'open',
		} );
		control = {
			post: page,
			marker: pageMarker,
			authorId: me.id,
			date: PAGE_DATE,
			...( await seedComments( requestUtils, page, pageMarker ) ),
		};

		latestPostsPage = await tracker.seedPage( requestUtils, {
			title: uniqueTitle( 'post leak latest posts' ),
			content: '<!-- wp:latest-posts /-->',
		} );
		calendarPage = await tracker.seedPage( requestUtils, {
			title: uniqueTitle( 'post leak calendar' ),
			content: '<!-- wp:calendar /-->',
		} );

		// `/random/` is a rewrite rule, so it reflects the current post type
		// flags only once the rules are rebuilt.
		await wpCliOk( [ 'rewrite', 'flush' ] );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await tracker.cleanup( requestUtils );
		await anon?.dispose();
	} );

	/**
	 * Every probe that searches for one target: the discovered REST routes,
	 * the targeted REST and ability probes, the front end and XML-RPC.
	 *
	 * @param seeded Target to probe for.
	 */
	async function probesFor( seeded: SeededTarget ): Promise< Record< 'rest' | 'front' | 'xmlrpc', Probe[] > > {
		return {
			rest: [
				...( await discoverRestProbes( anon, seeded.marker ) ),
				...targetedProbes(
					{
						post: seeded.post,
						commentIds: seeded.commentIds,
						noteId: seeded.noteId,
						authorId: seeded.authorId,
						...( seeded === target && { date: seeded.date } ),
					},
					seeded.marker,
					abilities
				),
			],
			front: frontProbes( seeded.post, seeded.marker, {
				termLinks: seeded === target ? termLinks : [],
				authorId: seeded.authorId,
				date: seeded.date,
				...( seeded === target && {
					latestPostsPage: latestPostsPage.permalink,
					calendarPage: calendarPage.permalink,
				} ),
			} ),
			xmlrpc: xmlrpcProbes( { postId: seeded.post.id, commentIds: seeded.commentIds }, xmlrpcReaders ),
		};
	}

	test( 'control: the crawl finds a marked page and each of its comments', async ( { requestUtils } ) => {
		test.setTimeout( 300_000 );

		const probes = await probesFor( control );
		const hits = await runProbes(
			[ ...probes.rest, ...probes.front, ...probes.xmlrpc ],
			control.marker,
			{ anon, admin: requestUtils }
		);
		const urls = hits.map( ( hit ) => `${ hit.surface } ${ hit.actor } ${ hit.url }` );
		const found = ( label: string, match: ( url: string ) => boolean ): void => {
			expect( urls.some( match ), `${ label } in:\n${ urls.join( '\n' ) }` ).toBe( true );
		};

		found( 'pages route', ( url ) => url.includes( '/wp/v2/pages' ) );

		for ( const actor of [ 'anon', 'admin' ] ) {
			found( `discovered comment search as ${ actor }`, ( url ) =>
				url === `rest ${ actor } /wp-json/wp/v2/comments?search=${ searchTerm( control.marker ) }&per_page=100`
			);
		}

		for ( const reader of xmlrpcReaders.filter( ( name ) => /^wp\.getPages?$/.test( name ) ) ) {
			found( `XML-RPC ${ reader }`, ( url ) => url === `xmlrpc admin ${ reader }/${ control.post.id }` );
		}

		found( 'front-end search', ( url ) => url.startsWith( 'front anon /?s=' ) );
		found( 'permalink', ( url ) => url === `front anon ${ new URL( control.post.permalink ).pathname }` );
		found( 'default comment list', ( url ) => url.includes( '/wp/v2/comments' ) && ! url.includes( 'type=' ) );
		found( 'custom comment type', ( url ) => url.includes( `type=${ CUSTOM_COMMENT_TYPE }` ) );
		found( 'XML-RPC comment list', ( url ) => url.endsWith( 'wp.getComments' ) );

		for ( const id of control.commentIds ) {
			found( `comment ${ id } by ID`, ( url ) => url === `rest admin /wp-json/wp/v2/comments/${ id }` );
			found( `comment ${ id } over XML-RPC`, ( url ) => url.endsWith( `wp.getComment/${ id }` ) );
		}

		if ( notes ) {
			found( 'note list', ( url ) => url.includes( 'type=note' ) );
		}

		if ( reactions ) {
			found( 'reaction list', ( url ) => url.includes( 'type=reaction' ) );
		}

		for ( const ability of [ 'core/content-query', 'core/users-query' ] ) {
			if ( abilities.includes( ability ) ) {
				found( ability, ( url ) => url.includes( ability ) );
			}
		}

		// The block renderer probes search for the post's marker, which a page
		// never matches, so the control asserts that the routes render.
		for ( const block of [ 'core/latest-posts', 'core/calendar' ] ) {
			const response = await adminFetch( requestUtils, `/wp-json/wp/v2/block-renderer/${ block }?context=edit` );

			expect( response.status(), block ).toBe( 200 );
		}
	} );

	test( 'no REST route or ability returns the marked post or its comments', async ( { requestUtils } ) => {
		test.setTimeout( 300_000 );

		const hits = await runProbes( ( await probesFor( target ) ).rest, target.marker, { anon, admin: requestUtils } );
		allHits.push( ...hits );

		const { leaks } = applyAllowlist( hits, allow, wp );

		expect( leaks.map( describeHit ) ).toEqual( [] );
	} );

	test( 'no front-end or XML-RPC surface returns the marked post', async ( { requestUtils } ) => {
		test.setTimeout( 300_000 );

		const probes = await probesFor( target );
		const hits = await runProbes( [ ...probes.front, ...probes.xmlrpc ], target.marker, { anon, admin: requestUtils } );
		allHits.push( ...hits );

		const { leaks } = applyAllowlist( hits, allow, wp );

		expect( leaks.map( describeHit ) ).toEqual( [] );
	} );

	test( 'control: a random redirect to a randomable item is detected', async ( { requestUtils } ) => {
		test.skip( ! versionAtLeast( wp, '7.2' ), 'Random content redirects arrive in WordPress 7.2.' );

		const users = await requestUtils.rest< { id: number; slug: string }[] >( {
			path: '/wp/v2/users',
			params: { search: ROLE_USERS.editor.username },
		} );
		const editor = users.find( ( user ) => user.slug === ROLE_USERS.editor.username );

		if ( ! editor ) {
			throw new Error( `No ${ ROLE_USERS.editor.username } user; the auth project creates it.` );
		}

		await setFixtures( requestUtils, { [ FIXTURE_TOGGLES.cptEnabled ]: true } );
		await flushRewrites( requestUtils );

		try {
			const item = await seedPost( requestUtils, {
				title: uniqueTitle( 'post leak random' ),
				postType: 'news',
				author: editor.id,
			} );

			try {
				// The author archive's random pick is limited to `post`, so the
				// control names the type: the same redirect, a type the plugin
				// leaves randomable.
				const hits = await runProbes(
					( [ 'anon', 'admin' ] as const ).map( ( actor ) => ( {
						surface: 'front',
						actor,
						method: 'GET',
						url: `/?random=1&author=${ editor.id }&post_type=news`,
						matchUrl: item.permalink,
					} ) ),
					makeMarker( 'unused' ),
					{ anon, admin: requestUtils }
				);

				expect( hits.map( ( hit ) => hit.actor ) ).toEqual( [ 'anon', 'admin' ] );
			} finally {
				await deletePosts( requestUtils, [ item.id ] );
			}
		} finally {
			await resetFixtures( requestUtils, [ FIXTURE_TOGGLES.cptEnabled ] );
			await flushRewrites( requestUtils );
		}
	} );

	test( 'comment totals do not count comments on posts', async ( { requestUtils } ) => {
		const ctx = { anon, admin: requestUtils };
		const postCommentIds = target.commentIds.map( String );

		// Reactions go first: trashing a note trashes its reactions, and
		// restoring the note leaves them in the trash.
		await wpCliOk( [ 'comment', 'trash', ...[ ...postCommentIds ].reverse() ] );
		const trashed = await measureCounts( ctx );
		await wpCliOk( [ 'comment', 'untrash', ...postCommentIds ] );
		const restored = await measureCounts( ctx );

		const hits = countHits( trashed, restored );
		allHits.push( ...hits );

		expect( applyAllowlist( hits, allow, wp ).leaks.map( ( hit ) => `${ hit.actor } ${ hit.url }` ) ).toEqual( [] );

		// Control: trashing one approved and one pending page comment moves
		// every total that counts approved, pending or trashed comments, so the
		// measurement can see a change on each key it compares.
		const pageCommentIds = [ control.commentIds[ 0 ], control.pendingId ].map( String );

		await wpCliOk( [ 'comment', 'trash', ...pageCommentIds ] );
		const pageTrashed = await measureCounts( ctx );
		await wpCliOk( [ 'comment', 'untrash', ...pageCommentIds ] );

		const moved = countHits( restored, pageTrashed ).map( ( hit ) => hit.url );
		const expected = Object.keys( restored ).filter(
			( key ) => key.startsWith( 'rest.' ) || /\.(approved|moderated|trash|total_comments|all)$/.test( key )
		);

		expect( expected.filter( ( key ) => key.startsWith( 'rest.' ) ).length ).toBeGreaterThan( 0 );
		expect( expected.filter( ( key ) => key.startsWith( 'wp_count_comments.' ) ) ).toHaveLength( 5 );
		expect( moved.sort() ).toEqual( expected.sort() );
	} );

	test( 'every allowlist entry that applies to this version still matches a hit', async () => {
		const { unused } = applyAllowlist( allHits, allow, wp );

		expect( unused.map( ( entry ) => `${ entry.surface } ${ entry.actor } ${ entry.urlPattern }` ) ).toEqual( [] );
	} );
} );
