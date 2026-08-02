/**
 * Locators and URL builders for WordPress-core admin screens.
 *
 * Disable Blog has no test-ids of its own; every selector below is either a
 * stable WP-core CSS id/class name, or a role/text locator where the visible
 * copy itself is the thing under test. Centralizing them here means a core
 * markup change — a WordPress version bump, say — touches exactly one file
 * instead of every spec that happens to click the Posts menu item.
 */

/**
 * External dependencies
 */
import type { Locator, Page } from '@playwright/test';

/* -------------------------------------------------------------------------
 * URL builders
 * ---------------------------------------------------------------------- */

/**
 * Admin URL for an arbitrary wp-admin path.
 *
 * @param path Path relative to `/wp-admin/`, e.g. `options-reading.php`.
 */
export function adminUrl( path: string ): string {
	return `/wp-admin/${ path.replace( /^\//, '' ) }`;
}

/**
 * The list table screen for a post type.
 *
 * @param postType Post type slug. Defaults to `post`.
 */
export function editPhp( postType = 'post' ): string {
	return adminUrl( `edit.php?post_type=${ postType }` );
}

/**
 * The "Add New" screen for a post type.
 *
 * @param postType Post type slug. Defaults to `post`.
 */
export function postNewPhp( postType = 'post' ): string {
	return adminUrl( `post-new.php?post_type=${ postType }` );
}

/**
 * The classic edit screen for a single post/page.
 *
 * @param id Post id.
 */
export function postPhp( id: number ): string {
	return adminUrl( `post.php?post=${ id }&action=edit` );
}

/**
 * The term list table for a taxonomy.
 *
 * @param taxonomy Taxonomy slug.
 */
export function editTagsPhp( taxonomy: string ): string {
	return adminUrl( `edit-tags.php?taxonomy=${ taxonomy }` );
}

/**
 * The edit screen for a single term.
 *
 * @param taxonomy Taxonomy slug.
 * @param termId   Term id.
 */
export function termPhp( taxonomy: string, termId: number ): string {
	return adminUrl( `term.php?taxonomy=${ taxonomy }&tag_ID=${ termId }` );
}

/* -------------------------------------------------------------------------
 * Admin menu (#adminmenu)
 * ---------------------------------------------------------------------- */

export const MENU_POSTS = '#menu-posts';
export const MENU_PAGES = '#menu-pages';
export const MENU_COMMENTS = '#menu-comments';
export const MENU_TOOLS = '#menu-tools';
export const MENU_SETTINGS = '#menu-settings';

/**
 * A menu link (top-level or submenu) by the wp-admin page it points at.
 *
 * Matches on `href$=` (suffix) rather than an exact string, so `href` can be
 * given as e.g. `edit.php?post_type=page` without also needing to know
 * whether core rendered a `/wp-admin/`-relative or absolute URL.
 *
 * @param page Page under test.
 * @param href wp-admin page/query string the link's `href` ends with, e.g. `edit.php?post_type=page`.
 */
export function menuLink( page: Page, href: string ): Locator {
	return page.locator( `#adminmenu a[href$="${ href }"]` );
}

/* -------------------------------------------------------------------------
 * Admin bar (#wpadminbar)
 * ---------------------------------------------------------------------- */

export const ADMIN_BAR_NEW_POST = '#wp-admin-bar-new-post';
export const ADMIN_BAR_NEW_PAGE = '#wp-admin-bar-new-page';
export const ADMIN_BAR_COMMENTS = '#wp-admin-bar-comments';
export const ADMIN_BAR_COMMENTS_COUNT = '#wp-admin-bar-comments .count';

/* -------------------------------------------------------------------------
 * Dashboard (index.php)
 * ---------------------------------------------------------------------- */

export const DASHBOARD_QUICK_PRESS = '#dashboard_quick_press';
export const DASHBOARD_ACTIVITY = '#dashboard_activity';
export const DASHBOARD_PRIMARY = '#dashboard_primary';
export const DASHBOARD_SITE_HEALTH = '#dashboard_site_health';
export const DASHBOARD_RIGHT_NOW = '#dashboard_right_now';

/**
 * The post counter inside the "At a Glance" (`#dashboard_right_now`) widget.
 *
 * @param page Page under test.
 */
export function rightNowPostCount( page: Page ): Locator {
	return page.locator( `${ DASHBOARD_RIGHT_NOW } .post-count` );
}

/**
 * The page counter inside the "At a Glance" widget.
 *
 * @param page Page under test.
 */
export function rightNowPageCount( page: Page ): Locator {
	return page.locator( `${ DASHBOARD_RIGHT_NOW } .page-count` );
}

/**
 * The comment counter inside the "At a Glance" widget.
 *
 * @param page Page under test.
 */
export function rightNowCommentCount( page: Page ): Locator {
	return page.locator( `${ DASHBOARD_RIGHT_NOW } .comment-count` );
}

/* -------------------------------------------------------------------------
 * Notices
 * ---------------------------------------------------------------------- */

/**
 * Every admin notice on the screen.
 *
 * More than one can be present at once; prefer {@link noticeWith} over
 * asserting on this locator directly.
 *
 * @param page Page under test.
 */
export function noticeLocator( page: Page ): Locator {
	return page.locator( '.notice' );
}

/**
 * The admin notice containing the given text.
 *
 * @param page Page under test.
 * @param text Substring or pattern the notice must contain.
 */
export function noticeWith( page: Page, text: string | RegExp ): Locator {
	return noticeLocator( page ).filter( { hasText: text } );
}

/* -------------------------------------------------------------------------
 * List tables (edit.php, edit-comments.php, edit-tags.php, ...)
 * ---------------------------------------------------------------------- */

/**
 * A single row of a list table.
 *
 * @param page Page under test.
 * @param id   Row id (post/page id — core renders list-table rows as `#post-<id>` regardless of post type).
 */
export function rowLocator( page: Page, id: number ): Locator {
	return page.locator( `#post-${ id }` );
}

/**
 * A row action link inside a row.
 *
 * @param page   Page under test.
 * @param id     Row id.
 * @param action Row action key (`edit`, `trash`, `view`, ...).
 */
export function rowActionLocator(
	page: Page,
	id: number,
	action: string
): Locator {
	return rowLocator( page, id ).locator( `.row-actions .${ action } a` );
}
