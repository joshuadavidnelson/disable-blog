/**
 * External dependencies
 */
import { request } from '@playwright/test';
import type { FullConfig } from '@playwright/test';
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { resetFixtures } from './fixtures';
import { ADMIN_STORAGE_STATE } from './roles';
import { resetContent } from './seed';

/**
 * Reset content and fixture toggles once the run finishes, as a backstop for
 * `afterEach`/`afterAll` teardown a killed or timed-out worker never reached.
 *
 * @param config Resolved Playwright config.
 */
async function globalTeardown( config: FullConfig ): Promise< void > {
	const { baseURL } = config.projects[ 0 ].use;

	const requestContext = await request.newContext( { baseURL } );
	const requestUtils = new RequestUtils( requestContext, {
		baseURL,
		storageStatePath: ADMIN_STORAGE_STATE,
	} );

	await requestUtils.setupRest();

	await resetContent( requestUtils );
	await resetFixtures( requestUtils );

	await requestContext.dispose();
}

export default globalTeardown;
