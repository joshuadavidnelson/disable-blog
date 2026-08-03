/**
 * The wp-admin Dashboard (`index.php`) console behaviour when
 * `dwpb.commentsSupported` is `false`, a state otherwise unreachable in this
 * environment — see `tests/e2e/fixtures/dwpb-test-comments-unsupported.php`'s
 * docblock for why 'page'/'attachment' supporting comments by default keeps
 * this branch dead code from this suite's point of view.
 *
 * COVERAGE: `assets/js/disable-blog-admin.js`'s Dashboard (`'index'`) case
 * does `document.querySelector( '.welcome-icon.welcome-comments' ).parentNode`
 * (:14) whenever `dwpb.commentsSupported` is false. WordPress 6.1+ no longer
 * renders those classes on the Dashboard welcome panel, so the selector
 * returns `null` and `.parentNode` throws a `TypeError` — an uncaught
 * exception inside the `DOMContentLoaded` handler, which silently kills
 * every later `case` in that same handler for the rest of the page load
 * (DEFECT D6).
 *
 * DEFECT D6: both tests below assert the CORRECTED behaviour and are
 * expected to FAIL until D6 is fixed:
 *  - no uncaught `TypeError` on `index.php` with the toggle on;
 *  - a positive control (`options-writing.php`) proving the script itself
 *    still runs to completion in this same toggle state, so the first
 *    assertion is proven to be about this one broken `case`, not a broken
 *    script file/build/enqueue that would fail everything indiscriminately.
 *
 * PAGEERROR, NOT CONSOLE: an uncaught exception is delivered to Playwright as
 * a `pageerror` event, not a `console` event — the DevTools protocol keeps
 * `Runtime.exceptionThrown` (uncaught errors) and `Runtime.consoleAPICalled`
 * (`console.*()` calls) as two distinct events, and Playwright maps only the
 * latter onto `page.on( 'console', ... )`. That means
 * `@wordpress/e2e-test-utils-playwright`'s own built-in listener
 * (`observeConsoleLogging` in its `test` fixture, which only listens on
 * `'console'`) does not, by itself, reliably surface this specific defect —
 * and neither would an assertion in this file that bound to a `'console'`
 * collector. The D6 test below binds its assertion to the `'pageerror'`
 * collector specifically; a `'console'` collector is also kept, folded into
 * the failure message as a belt-and-braces signal, but it is never what the
 * `expect()` call itself checks. The failure message includes the captured
 * error text, so a red run names the actual `TypeError` instead of a bare
 * count mismatch.
 *
 * AUTHENTICATED BY DEFAULT: no `storageState` override — both screens here
 * require an authenticated admin, which the project default already is.
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
 * it. Mirrors `formTableRow()` in `settings-screens.spec.ts` -- kept local
 * rather than shared, since this is the only options-writing.php assertion
 * in this file and does not warrant promoting to `config/admin.ts` on its
 * own.
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

	test( 'DEFECT D6: the dashboard throws no TypeError when comments are unsupported', async ( {
		page,
		admin,
	} ) => {
		// DEFECT D6: assets/js/disable-blog-admin.js:14 —
		// document.querySelector( '.welcome-icon.welcome-comments' ) returns
		// null on modern WordPress, so .parentNode throws a TypeError that
		// currently kills the rest of the DOMContentLoaded handler.
		// PAGEERROR, NOT CONSOLE: an uncaught exception is delivered to
		// Playwright via the 'pageerror' event, not 'console' -- the DevTools
		// protocol keeps Runtime.exceptionThrown (uncaught errors) and
		// Runtime.consoleAPICalled (console.*() calls) as two distinct
		// events, and Playwright's 'console' event only ever surfaces the
		// latter. So `pageErrors` is what this assertion binds to; the
		// console collector is kept only as a belt-and-braces signal folded
		// into the failure message, and is deliberately NOT part of the
		// assertion itself -- otherwise this test would go back to passing
		// vacuously exactly as it did before.
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

	test( 'DEFECT D6 control: the writing screen still hides the post format row in this state', async ( {
		page,
		admin,
	} ) => {
		// Proves disable-blog-admin.js as a whole still runs to completion
		// with the toggle on -- the 'options-writing' case never touches the
		// Dashboard's broken selector, so this passing is what proves the D6
		// failure above is specific to the 'index' case, not a broken script
		// file/enqueue that would fail indiscriminately.
		await admin.visitAdminPage( 'options-writing.php' );

		await expect( formTableRow( page, 'default_post_format' ) ).toBeHidden();
		await expect(
			page.getByRole( 'heading', { name: 'Writing Settings' } )
		).toBeVisible();
	} );
} );
