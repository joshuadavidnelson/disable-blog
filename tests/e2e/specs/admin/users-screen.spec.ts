/**
 * The Users list table (`users.php`), default plugin state.
 *
 * COVERAGE:
 *  - `Disable_Blog_Admin::manage_users_columns()`, hooked on
 *    `manage_users_columns`, unsets core's `posts` column and adds one keyed
 *    `page` (plus any post type from `author_archive_post_types()`, none by
 *    default), labelled with the `page` post type's own
 *    `labels->name` -- not a plugin-owned string, mirrored in
 *    `dwpb-test-api.php`'s `users_pages_column_label`.
 *  - `Disable_Blog_Admin::manage_users_custom_column()`, hooked on
 *    `manage_users_custom_column`, renders that column's cell as a link to
 *    `edit.php?post_type=page&author=<user_id>`, mirroring core's own
 *    Posts-column link.
 *  - `Disable_Blog_Admin::user_row_actions()`, hooked on `user_row_actions`,
 *    unsets the `view` row action only when
 *    `Disable_Blog_Functions::disable_author_archives()`
 *    (`dwpb_disable_author_archives`) is true.
 *
 * GATING CONDITION: `dwpb_disable_author_archives` defaults to `false`, so
 * `user_row_actions()` never removes `view` in this environment's default
 * state (test 3).
 *
 * AUTHENTICATED BY DEFAULT: no `storageState` override -- the project default
 * (administrator) has `list_users`, required for this screen.
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
import { pluginStrings } from '../../config/strings';

/**
 * A `users.php` list-table row, by user id.
 *
 * Not `rowLocator()` from `config/admin.ts` -- that helper is `#post-<id>`,
 * which is what every post-type list table (edit.php, edit-comments.php, ...)
 * renders regardless of post type, but `WP_Users_List_Table::single_row()`
 * renders `<tr id='user-<id>'>` instead. Defined locally here instead of
 * extending the config export -- flagged for the coordinator; `config/admin.ts`
 * is not edited per the task constraints.
 *
 * @param page   Page under test.
 * @param userId User id.
 */
function userRowLocator( page: Page, userId: number ) {
	return page.locator( `#user-${ userId }` );
}

test.describe( 'admin: users screen (default state)', () => {
	test( 'the Posts column is replaced by a Pages column', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const strings = await pluginStrings( requestUtils );

		await admin.visitAdminPage( 'users.php' );

		await expect( page.locator( '#posts' ) ).toHaveCount( 0 );

		// The new column's header id is the post type slug itself ('page') --
		// see WP_List_Table::print_column_headers()'s `id="$column_key"`.
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

		// Row actions are visually hidden until hover (same house rule as
		// admin-bar.spec.ts's dropdown children) -- assert DOM presence via
		// toHaveCount(), not toBeVisible().
		const viewAction = userRowLocator( page, me.id ).locator( '.row-actions .view a' );

		await expect( viewAction ).toHaveCount( 1 );
	} );
} );
