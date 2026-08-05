/**
 * Probe for `Disable_Blog_Integrations::filter_woocommerce_comment_count()`,
 * via `dwpb-test/v1/wp-count-comments-type`.
 *
 * WordPress core's `wp_count_comments()` always returns an object; the
 * filter casts it back to an array on a pre-2.6.3 WooCommerce, and nothing
 * else in this stubbed environment consumes that array, so the cast has no
 * other visible effect. This route reports the PHP type directly.
 *
 * @see tests/e2e/fixtures/dwpb-test-integrations.php
 */

/**
 * External dependencies
 */
import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { TEST_API } from './roles';

/**
 * Fetch `gettype( wp_count_comments( 0 ) )` as reported by the plugin's own
 * comment-count filter chain.
 *
 * @param requestUtils Admin request utils.
 */
export async function wpCountCommentsType( requestUtils: RequestUtils ): Promise< string > {
	const response = await requestUtils.rest< { type: string } >( {
		path: `/${ TEST_API }/wp-count-comments-type`,
	} );

	return response.type;
}
