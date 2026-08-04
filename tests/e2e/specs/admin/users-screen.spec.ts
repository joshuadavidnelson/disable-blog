/**
 * The Users list table (`users.php`), default plugin state.
 *
 * COVERAGE:
 *  - `manage_users_columns()` unsets core's `posts` column and adds a `page`
 *    column labelled with the `page` post type's own `labels->name`.
 *  - `manage_users_custom_column()` renders that column's cell as a link to
 *    `edit.php?post_type=page&author=<user_id>`, mirroring core's Posts link.
 *  - `user_row_actions()` unsets the `view` row action only when
 *    `dwpb_disable_author_archives` is true — false by default, so `view`
 *    stays (test 3).
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { userRowLocator } from '../../config/admin';
import { pluginStrings } from '../../config/strings';

test.describe( 'admin: users screen (default state)', () => {
	test( 'the Posts column is replaced by a Pages column', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const strings = await pluginStrings( requestUtils );

		await admin.visitAdminPage( 'users.php' );

		await expect( page.locator( '#posts' ) ).toHaveCount( 0 );

		// The new column's header id is the post type slug itself ('page').
		await expect( page.locator( 'th#page' ) ).toHaveText( strings.users_pages_column_label );
	} );

	test( 'the pages count links to the author-filtered pages list', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const me = await requestUtils.rest< { id: number } >( { path: '/wp/v2/users/me' } );

		await admin.visitAdminPage( 'users.php' );

		const link = userRowLocator( page, me.id ).locator( '.column-page a' );

		await expect( link ).toHaveAttribute(
			'href',
			new RegExp( `edit\\.php\\?post_type=page&author=${ me.id }` )
		);
	} );

	test( 'the View row action is present by default', async ( { admin, page, requestUtils } ) => {
		const me = await requestUtils.rest< { id: number } >( { path: '/wp/v2/users/me' } );

		await admin.visitAdminPage( 'users.php' );

		// Row actions are hidden until hover, so assert presence, not visibility.
		const viewAction = userRowLocator( page, me.id ).locator( '.row-actions .view a' );

		await expect( viewAction ).toHaveCount( 1 );
	} );
} );
