/**
 * The Latest Posts and Calendar blocks only ever list posts, so the plugin
 * unregisters both and keeps them out of the editor's inserter. A page that
 * already contains one renders without it, and the block renderer answers
 * 404. `dwpb_unregister_blocks` keeps a block.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { createContentTracker } from '../../config/content-tracker';
import { resetFilterOverrides, setFilterOverrides } from '../../config/filter-overrides';
import { adminFetch, makeMarker } from '../../config/leak';
import type { SeededPost } from '../../config/seed';

const BLOCKS = [
	{ name: 'core/latest-posts', markup: '<!-- wp:latest-posts /-->', wrapper: 'wp-block-latest-posts' },
	{ name: 'core/calendar', markup: '<!-- wp:calendar /-->', wrapper: 'wp-block-calendar' },
] as const;

/**
 * The editor globals the inserter check reads, in the page.
 */
interface EditorWindow {
	wp?: {
		data?: { select( store: 'core/block-editor' ): { getInserterItems(): { name: string }[] } };
	};
}

test.describe.configure( { mode: 'serial' } );

test.describe( 'admin: blocks that list posts', () => {
	const tracker = createContentTracker();
	const pages: Record< string, SeededPost > = {};

	let marker: string;

	test.beforeAll( async ( { requestUtils } ) => {
		marker = makeMarker( 'dwpbblock' );

		// A published post, so both blocks have something to show when kept.
		await tracker.seedPost( requestUtils, { title: marker } );

		for ( const block of BLOCKS ) {
			pages[ block.name ] = await tracker.seedPage( requestUtils, {
				title: `${ marker } ${ block.name }`,
				content: block.markup,
			} );
		}
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await tracker.cleanup( requestUtils );
	} );

	test( 'neither block is registered, and the block renderer answers 404', async ( { requestUtils } ) => {
		for ( const block of BLOCKS ) {
			const type = await adminFetch( requestUtils, `/wp-json/wp/v2/block-types/${ block.name }` );

			expect( type.status(), block.name ).toBe( 404 );

			const render = await adminFetch( requestUtils, `/wp-json/wp/v2/block-renderer/${ block.name }?context=edit` );

			expect( render.status(), block.name ).toBe( 404 );
			expect( ( ( await render.json() ) as { code?: string } ).code, block.name ).toBe( 'block_invalid' );
		}

		// Control: a block the plugin leaves alone is still registered.
		expect( ( await adminFetch( requestUtils, '/wp-json/wp/v2/block-types/core/paragraph' ) ).status() ).toBe( 200 );
	} );

	test( 'a page that contains either block renders without it', async ( { request, requestUtils } ) => {
		const rendered = async (): Promise< Record< string, boolean > > => {
			const found: Record< string, boolean > = {};

			for ( const block of BLOCKS ) {
				const body = await ( await request.get( pages[ block.name ].permalink ) ).text();

				found[ block.name ] = body.includes( block.wrapper );
			}

			return found;
		};

		expect( await rendered() ).toEqual( { 'core/latest-posts': false, 'core/calendar': false } );

		// Control: with the blocks kept, the same pages render them.
		await setFilterOverrides( requestUtils, { dwpb_unregister_blocks: false } );

		expect( await rendered() ).toEqual( { 'core/latest-posts': true, 'core/calendar': true } );
	} );

	test( 'the editor does not offer either block', async ( { admin, page, requestUtils } ) => {
		const insertable = async (): Promise< Record< string, boolean > > => {
			await admin.visitAdminPage( 'post-new.php', 'post_type=page' );

			// The inserter lists blocks once the editor has registered them.
			const handle = await page.waitForFunction( () => {
				const items = ( globalThis as EditorWindow ).wp?.data?.select( 'core/block-editor' ).getInserterItems() ?? [];
				const names = items.map( ( item ) => item.name );

				return names.includes( 'core/paragraph' ) ? names : false;
			} );
			const offered = ( await handle.jsonValue() ) as string[];

			return Object.fromEntries(
				[ 'core/paragraph', ...BLOCKS.map( ( block ) => block.name ) ].map( ( name ) => [ name, offered.includes( name ) ] )
			);
		};

		expect( await insertable() ).toEqual( {
			'core/paragraph': true,
			'core/latest-posts': false,
			'core/calendar': false,
		} );

		// Control: with the blocks kept, the editor offers them.
		await setFilterOverrides( requestUtils, { dwpb_unregister_blocks: false } );

		expect( await insertable() ).toEqual( {
			'core/paragraph': true,
			'core/latest-posts': true,
			'core/calendar': true,
		} );
	} );
} );
