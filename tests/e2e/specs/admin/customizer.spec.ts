/**
 * The Customizer (`customize.php`), default plugin state.
 *
 * Both hooks fire directly inside `wp-admin/customize.php`'s own document,
 * not the site-preview iframe, so `page.content()` sees their output without
 * opening the (JS-driven, normally-collapsed) Homepage Settings panel.
 *
 * `has_front_page()` is true for the whole suite by default (global-setup's
 * `setupSite()`), so the default-state assertion below is "present"; the
 * negative control clears that reading setting the same way
 * `admin/settings-screens.spec.ts` does.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { setReadingSettings } from '../../config/seed';
import type { ReadingSettings } from '../../config/seed';
import { pluginStrings } from '../../config/strings';

/**
 * A selector `customizer_styles()` hides only when `has_front_page()` is
 * true. Genesis-theme specific, so it can't collide with anything the
 * pinned theme or WordPress core itself renders on this screen.
 */
const HIDDEN_CONTROL_MARKER = 'customize-control-genesis_trackbacks_posts';

/** The customizer-only script `customizer_scripts()` enqueues. */
const CUSTOMIZER_SCRIPT_SRC = 'assets/js/disable-blog-customizer.js';

test.describe( 'admin: customizer styles (default state)', () => {
	test( 'the homepage-settings controls are hidden when a static front page is set', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'customize.php' );

		expect( await page.content() ).toContain( HIDDEN_CONTROL_MARKER );
	} );

	test.describe( 'no static front page set (mutates site-wide reading settings)', () => {
		let previous: ReadingSettings | undefined;

		test.afterEach( async ( { requestUtils } ) => {
			if ( previous ) {
				await setReadingSettings( requestUtils, previous );
				previous = undefined;
			}
		} );

		test( 'the style block disappears once the front page setting is cleared', async ( {
			admin,
			page,
			requestUtils,
		} ) => {
			( { previous } = await setReadingSettings( requestUtils, {
				showOnFront: 'posts',
			} ) );

			await admin.visitAdminPage( 'customize.php' );

			// Not vacuous: the test above proves this exact marker is present
			// when has_front_page() is true.
			expect( await page.content() ).not.toContain( HIDDEN_CONTROL_MARKER );
		} );
	} );
} );

test.describe( 'admin: customizer scripts (default state)', () => {
	test( 'the customizer script and its localized homepage-settings text are enqueued', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		await admin.visitAdminPage( 'customize.php' );

		const content = await page.content();
		const strings = await pluginStrings( requestUtils );

		expect( content ).toContain( CUSTOMIZER_SCRIPT_SRC );
		expect( content ).toContain( 'dwpbCustomizer' );
		expect( content ).toContain( strings.homepage_settings_text );
	} );

	test( 'control: neither is enqueued on an unrelated admin screen', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'options-general.php' );

		const content = await page.content();

		expect( content ).not.toContain( CUSTOMIZER_SCRIPT_SRC );
		expect( content ).not.toContain( 'dwpbCustomizer' );
	} );
} );
