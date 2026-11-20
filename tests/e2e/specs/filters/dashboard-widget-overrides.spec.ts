/**
 * `dwpb_disable_dashboard_quick_press`, via the generic filter-override
 * mechanism (`config/filter-overrides.ts` + `dwpb-test-filters.php`) — one of
 * the four filters `remove_dashboard_widgets()` builds as
 * `"dwpb_disable_{$metabox_id}"`, all defaulting `true` (see
 * admin/dashboard.spec.ts for the default-removed state).
 *
 * Forced `false`, `remove_meta_box( 'dashboard_quick_press', ... )` never
 * runs, so Quick Draft reappears on the Dashboard.
 *
 * `dwpb_disable_dashboard_activity` forced `false` keeps the Activity widget,
 * which still lists no post: not under Recently Published, Publishing Soon
 * or, on WordPress 7.2 and later, Published On This Day.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { DASHBOARD_QUICK_PRESS, DASHBOARD_ACTIVITY } from '../../config/admin';
import { setFilterOverrides, resetFilterOverrides } from '../../config/filter-overrides';
import { makeMarker } from '../../config/leak';
import { wpCliOk } from '../../config/wp-cli';

test.describe( 'filters: dashboard quick press override (dwpb_disable_dashboard_quick_press)', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await setFilterOverrides( requestUtils, {
			dwpb_disable_dashboard_quick_press: false,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFilterOverrides( requestUtils );
	} );

	test( 'the Quick Draft widget reappears', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'index.php' );

		await expect( page.locator( DASHBOARD_QUICK_PRESS ) ).toBeVisible();

		// Control: the sibling widget built by the same foreach loop, under
		// its own filter name, is still removed -- proves the override
		// targets only its own metabox id.
		await expect( page.locator( DASHBOARD_ACTIVITY ) ).toHaveCount( 0 );
	} );
} );

test.describe( 'filters: dashboard activity override (dwpb_disable_dashboard_activity)', () => {
	const marker = makeMarker( 'dwpbactivity' );
	let ids: number[] = [];

	test.beforeAll( async ( { requestUtils } ) => {
		await setFilterOverrides( requestUtils, {
			dwpb_disable_dashboard_activity: false,
		} );

		// One post for each section: published, scheduled, and published on this day in an earlier year.
		const output = await wpCliOk( [
			'eval',
			[
				'$now = current_datetime();',
				'$add = function ( $status, $date ) {',
				`	return (int) wp_insert_post( array( 'post_title' => $status . ' ${ marker }', 'post_status' => $status, 'post_date' => $date->format( 'Y-m-d H:i:s' ) ) );`,
				'};',
				"echo wp_json_encode( array( $add( 'publish', $now ), $add( 'future', $now->modify( '+1 year' ) ), $add( 'publish', $now->modify( '-3 years' ) ) ) );",
			].join( ' ' ),
		] );

		ids = JSON.parse( output.match( /\[.*\]/s )?.[ 0 ] ?? '[]' ) as number[];

		expect( ids.length, output ).toBe( 3 );
		expect( ids.every( ( id ) => id > 0 ), output ).toBe( true );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		if ( ids.length > 0 ) {
			await wpCliOk( [ 'post', 'delete', ...ids.map( String ), '--force' ] );
		}

		await resetFilterOverrides( requestUtils );
	} );

	test( 'the Activity widget lists no post', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'index.php' );

		const activity = page.locator( DASHBOARD_ACTIVITY );

		await expect( activity.locator( '#activity-widget' ) ).toBeAttached();
		await expect( activity ).not.toContainText( marker );
	} );
} );
