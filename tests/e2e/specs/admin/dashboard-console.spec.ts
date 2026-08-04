/**
 * The wp-admin Dashboard (`index.php`) console behaviour when
 * `dwpb.commentsSupported` is `false` — unreachable via the plugin's normal
 * default state, so exercised here via a test fixture toggle.
 *
 * Regression guard (fixed since v0.5.5): `disable-blog-admin.js`'s Dashboard
 * case used to call `.parentNode` on `document.querySelector(
 * '.welcome-icon.welcome-comments' )`, which is `null` on WordPress 6.1+ —
 * an uncaught `TypeError` inside the `DOMContentLoaded` handler that killed
 * every later `case` in that handler for the rest of the page load. An
 * uncaught exception surfaces to Playwright as a `pageerror` event, not
 * `console` — the assertion below binds to `pageerror` specifically; the
 * `console` collector is kept only to enrich the failure message.
 *
 * This overlaps with `filters/comments-unsupported.spec.ts`'s final test
 * (also asserts a clean dashboard in this state) but is kept separate: that
 * file covers the fixture branch as a whole, this one is scoped to the JS
 * regression itself.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * External dependencies
 */
import type { Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { setFixtures, resetFixtures, FIXTURE_TOGGLES } from '../../config/fixtures';

/**
 * Row-of-a-`.form-table` locator, matched by the `<label for="...">` inside
 * it. Mirrors `formTableRow()` in `settings-screens.spec.ts`, kept local here.
 *
 * @param page     Page under test.
 * @param labelFor The `for` attribute of a `<label>` inside the target row.
 */
function formTableRow( page: Page, labelFor: string ) {
	return page.locator( 'tr', { has: page.locator( `label[for="${ labelFor }"]` ) } );
}

test.describe( 'admin: dashboard console (commentsSupported === false)', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await setFixtures( requestUtils, {
			[ FIXTURE_TOGGLES.commentsUnsupported ]: true,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFixtures( requestUtils, [ FIXTURE_TOGGLES.commentsUnsupported ] );
	} );

	test( 'regression guard (D6): the dashboard throws no TypeError when comments are unsupported', async ( {
		page,
		admin,
	} ) => {
		const consoleErrors: string[] = [];
		const pageErrors: string[] = [];

		page.on( 'console', ( message ) => {
			if ( 'error' === message.type() ) {
				consoleErrors.push( message.text() );
			}
		} );
		page.on( 'pageerror', ( error ) => {
			pageErrors.push( error.message );
		} );

		await admin.visitAdminPage( 'index.php' );

		expect(
			pageErrors,
			'Uncaught page error(s) on the Dashboard with commentsSupported === false ' +
				'(expected: assets/js/disable-blog-admin.js:14 throws ' +
				"TypeError: Cannot read properties of null (reading 'parentNode') " +
				"because document.querySelector( '.welcome-icon.welcome-comments' ) " +
				'returns null on WP 6.1+):\n' +
				pageErrors.join( '\n' ) +
				( consoleErrors.length
					? `\n\nconsole error(s) also seen:\n${ consoleErrors.join( '\n' ) }`
					: '' )
		).toEqual( [] );
	} );

	test( 'regression guard (D6) control: the writing screen still hides the post format row in this state', async ( {
		page,
		admin,
	} ) => {
		// Proves the script as a whole still runs with the toggle on, so the
		// guard above is specific to the 'index' case, not a broken enqueue.
		await admin.visitAdminPage( 'options-writing.php' );

		await expect( formTableRow( page, 'default_post_format' ) ).toBeHidden();
		await expect(
			page.getByRole( 'heading', { name: 'Writing Settings' } )
		).toBeVisible();
	} );
} );
