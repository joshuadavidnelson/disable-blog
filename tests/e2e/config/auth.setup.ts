/**
 * External dependencies
 */
import { test as setup, expect } from '@playwright/test';
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { ADMIN_STORAGE_STATE, ROLE_USERS, storageStatePath } from './roles';
import type { Role } from './roles';

interface UserRecord {
	username?: string;
}

/**
 * Whether a REST failure means "that login is already taken".
 *
 * `RequestUtils.rest()` throws the parsed error body, so the WordPress error
 * code is on the thrown value.
 *
 * @param error Value thrown by a REST call.
 */
function isExistingUserError( error: unknown ): boolean {
	return (
		typeof error === 'object' &&
		error !== null &&
		( error as { code?: string } ).code === 'existing_user_login'
	);
}

/**
 * Create the non-admin role users and persist a signed-in browser state for each.
 *
 * Runs as a Playwright setup project every browser project depends on, so a
 * spec can switch identity via
 * `test.use( { storageState: storageStatePath( 'editor' ) } )` without its own
 * login. Idempotent: existing users are reused, but storage state is always
 * refreshed so cookies are never stale.
 */
setup( 'create role users and store their authenticated state', async ( {
	baseURL,
} ) => {
	const admin = await RequestUtils.setup( {
		baseURL,
		storageStatePath: ADMIN_STORAGE_STATE,
	} );
	await admin.setupRest();

	// `search` keeps our three logins on the first page no matter how many
	// users a spec has left behind.
	const existingUsers: UserRecord[] = await admin.rest( {
		path: '/wp/v2/users',
		params: { per_page: 100, context: 'edit', search: 'dwpb_' },
	} );
	const existingLogins = new Set(
		existingUsers.map( ( user ) => user.username )
	);

	for ( const [ role, user ] of Object.entries( ROLE_USERS ) ) {
		if ( ! existingLogins.has( user.username ) ) {
			try {
				await admin.createUser( {
					username: user.username,
					email: user.email,
					password: user.password,
					roles: user.roles,
				} );
			} catch ( error ) {
				// The user exists but the listing missed it — carry on and
				// refresh its storage state below. Anything else is real.
				if ( ! isExistingUserError( error ) ) {
					throw error;
				}
			}
		}

		const roleUtils = await RequestUtils.setup( {
			baseURL,
			user: { username: user.username, password: user.password },
			storageStatePath: storageStatePath( role as Role ),
		} );

		const storageState = await roleUtils.setupRest();

		// A usable state has session cookies; anything else means the login
		// silently failed and every spec using this role would be misleading.
		expect( storageState.cookies.length ).toBeGreaterThan( 0 );

		await roleUtils.request.dispose();
	}

	await admin.request.dispose();
} );
