/**
 * External dependencies
 */
import path from 'node:path';

/**
 * Directory holding the authenticated browser storage state for each role.
 *
 * Written by `auth.setup.ts` (non-admin roles) and `global-setup.ts` (admin),
 * consumed by `playwright.config.ts` and by specs via
 * `test.use( { storageState: storageStatePath( 'editor' ) } )`.
 *
 * Git-ignored: the files hold live session cookies.
 */
export const AUTH_DIR = path.join( __dirname, '..', '..', '..', 'playwright', '.auth' );

/**
 * Roles the harness keeps a signed-in browser state for.
 *
 * `administrator` already exists in a stock wp-env install; the rest are
 * created by `auth.setup.ts`.
 */
export const ROLES = [ 'administrator', 'editor', 'author', 'subscriber' ] as const;

export type Role = ( typeof ROLES )[ number ];

/**
 * Non-admin users created by `auth.setup.ts`, keyed by role.
 *
 * Passwords are fixed so the state can be regenerated deterministically; this
 * is a throwaway local environment. The `dwpb_` username prefix is relied on
 * by `auth.setup.ts`, which searches `/wp/v2/users` on it to keep these three
 * logins on the first page no matter how many users a spec has left behind.
 */
export const ROLE_USERS: Record<
	Exclude< Role, 'administrator' >,
	{ username: string; password: string; email: string; roles: string[] }
> = {
	editor: {
		username: 'dwpb_editor',
		password: 'dwpb-editor-password',
		email: 'dwpb_editor@example.com',
		roles: [ 'editor' ],
	},
	author: {
		username: 'dwpb_author',
		password: 'dwpb-author-password',
		email: 'dwpb_author@example.com',
		roles: [ 'author' ],
	},
	subscriber: {
		username: 'dwpb_subscriber',
		password: 'dwpb-subscriber-password',
		email: 'dwpb_subscriber@example.com',
		roles: [ 'subscriber' ],
	},
};

/**
 * Absolute path to the stored authentication state for a role.
 *
 * @param role Role to resolve the storage state for.
 */
export function storageStatePath( role: Role ): string {
	return path.join( AUTH_DIR, `${ role }.json` );
}

/**
 * Storage state used by every project unless a spec opts into another role.
 */
export const ADMIN_STORAGE_STATE = storageStatePath( 'administrator' );

/**
 * Slug of the plugin under test, as `RequestUtils.activatePlugin()` keys it
 * (kebab-cased plugin name from `/wp/v2/plugins`).
 */
export const PLUGIN_SLUG = 'disable-blog';

/**
 * `plugin` REST field format (`<slug>/<main-file>.php`) some endpoints/CLI
 * calls expect instead of the bare slug.
 */
export const PLUGIN_FILE = 'disable-blog/disable-blog.php';

/**
 * Namespace of the mu-plugin test API that backs seeding/inspection routes
 * REST cannot reach directly (the plugin under test disables `/wp/v2/posts`).
 *
 * @see tests/e2e/fixtures/dwpb-test-api.php
 */
export const TEST_API = 'dwpb-test/v1';
