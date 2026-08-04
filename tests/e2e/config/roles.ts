import path from 'node:path';

/**
 * Directory holding each role's authenticated browser storage state.
 *
 * Written by `auth.setup.ts` (non-admin roles) and `global-setup.ts` (admin);
 * consumed by `playwright.config.ts` and by specs. Git-ignored — holds live
 * session cookies.
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
 * Passwords are fixed (throwaway local env). The `dwpb_` prefix is relied on
 * by `auth.setup.ts`'s `/wp/v2/users` search to find these users regardless
 * of how many others a spec has left behind.
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
 * Stylesheet slug of the theme the suite pins, as `RequestUtils.activateTheme()`
 * and `/wp/v2/themes` key it.
 *
 * Twenty Twenty-Two, chosen for its low WP requirement (5.9+) so it installs
 * across the plugin's whole supported version range; see `global-setup.ts`
 * for why the theme is pinned at all.
 */
export const THEME_SLUG = 'twentytwentytwo';

/**
 * Namespace of the mu-plugin test API that backs seeding/inspection routes
 * REST cannot reach directly (the plugin under test disables `/wp/v2/posts`).
 *
 * @see tests/e2e/fixtures/dwpb-test-api.php
 */
export const TEST_API = 'dwpb-test/v1';
