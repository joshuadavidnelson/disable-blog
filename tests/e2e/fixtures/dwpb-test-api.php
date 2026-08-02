<?php
/**
 * E2E mu-plugin fixture: seeding + inspection endpoints for the Playwright suite.
 *
 * NAMESPACE: `dwpb-test/v1`. Every route declares
 * `permission_callback => current_user_can( 'manage_options' )`, so the surface is
 * unreachable to anonymous or low-privileged requests.
 *
 * WHY THIS FILE EXISTS: Disable Blog strips the 'post' post type down to almost
 * nothing. `Disable_Blog_Admin::modify_post_type_arguments()`, hooked on `init` at
 * priority 25, sets `show_in_rest`, `public`, `show_ui`, etc. to `false` on the
 * 'post' post type. That means `POST /wp/v2/posts` returns `rest_no_route` for the
 * entire life of the test environment, so the standard REST-based seeding helper
 * (`RequestUtils.createPost()`) simply cannot create a post — there is no core
 * route left to call. Pages are untouched by the plugin and seed fine through core
 * REST; posts, comments, and category/tag terms attached to posts do not, so this
 * fixture provides an authenticated back door that calls the same underlying
 * WordPress APIs (`wp_insert_post()`, `wp_insert_comment()`, `wp_insert_term()`,
 * `get_posts()`, ...) directly, bypassing the plugin's REST restrictions the same
 * way WP-CLI or a direct database actor would.
 *
 * This mu-plugin is mapped into `wp-content/mu-plugins` by `.wp-env.json`, so it
 * loads on every request in the test environment. It registers nothing on the
 * plugin's own hooks and performs no work until an authenticated administrator
 * calls one of these routes, so it is safe to leave active for the whole suite.
 *
 * Routes:
 *   POST   dwpb-test/v1/setup                 -> idempotently seed Home/Blog pages + reading settings
 *   POST   dwpb-test/v1/reset-content         -> wipe posts/comments/terms seeded by specs
 *   POST   dwpb-test/v1/delete-all-posts      -> wipe 'post' type content only
 *   DELETE dwpb-test/v1/post/<id>             -> force-delete a single post/page by id (idempotent)
 *   POST   dwpb-test/v1/create-post           -> wp_insert_post() (the route core REST won't allow)
 *   POST   dwpb-test/v1/create-comment        -> wp_insert_comment()
 *   POST   dwpb-test/v1/create-term           -> wp_insert_term(), optionally assigned to a post
 *   GET    dwpb-test/v1/post-state/<id>       -> status, comment/ping state, permalink for a post
 *   POST   dwpb-test/v1/flush-rewrites        -> flush_rewrite_rules()
 *   GET    dwpb-test/v1/strings               -> user-facing strings asserted by specs (see note on that function)
 *
 * @package Disable_Blog\TestFixtures
 */

defined( 'ABSPATH' ) || exit;

// NOTE: do not add a `function_exists()` early-return guard at the top of this
// file. PHP hoists unconditional top-level function declarations at compile
// time, so every `dwpb_test_*` function below is already declared before the
// first line of this file executes -- such a guard is therefore always true and
// returns before `add_action( 'rest_api_init', ... )` at the bottom ever runs,
// leaving the functions defined but the routes silently unregistered (every
// request 404s with `rest_no_route`). WordPress includes each mu-plugin exactly
// once via `include_once`, so no guard is needed.

/**
 * Shared permission callback: administrators only.
 *
 * @return bool
 */
function dwpb_test_api_can_manage() {
	return current_user_can( 'manage_options' );
}

/**
 * The term_id of the taxonomy's "default" term that must survive a reset.
 *
 * Only 'category' has a WordPress-recognised default term (Uncategorized);
 * 'post_tag' has no equivalent, so this only ever protects a category term.
 *
 * @return int
 */
function dwpb_test_api_default_category_id() {
	return (int) get_option( 'default_category' );
}

/**
 * Describe a post's status-relevant state for the /post-state route.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>|WP_Error
 */
function dwpb_test_api_describe_post_state( $post_id ) {
	$post = get_post( $post_id );

	if ( ! $post ) {
		return new WP_Error( 'dwpb_test_no_post', "No post {$post_id}.", array( 'status' => 404 ) );
	}

	return array(
		'id'            => $post->ID,
		'post_status'   => $post->post_status,
		'post_type'     => $post->post_type,
		'comments_open' => comments_open( $post ),
		'pings_open'    => pings_open( $post ),
		'permalink'     => get_permalink( $post ),
		'comment_count' => (int) $post->comment_count,
	);
}

/**
 * Idempotently seed the Home/Blog pages and the reading settings that point at
 * them. Called once by global setup and safely callable again mid-run by any
 * spec that needs to self-heal a reading-settings change made by another spec.
 *
 * @return array<string, mixed>|WP_Error
 */
function dwpb_test_api_setup() {

	// Ensure the 'home' page exists, is published, and is titled 'Home'.
	$home_page = get_page_by_path( 'home', OBJECT, 'page' );

	if ( ! $home_page ) {
		$home_id = wp_insert_post(
			array(
				'post_title'  => 'Home',
				'post_name'   => 'home',
				'post_type'   => 'page',
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $home_id ) ) {
			return $home_id;
		}
	} else {
		$home_id = $home_page->ID;

		if ( 'publish' !== $home_page->post_status || 'Home' !== $home_page->post_title ) {
			$updated = wp_update_post(
				array(
					'ID'          => $home_id,
					'post_title'  => 'Home',
					'post_status' => 'publish',
				),
				true
			);

			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}
	}

	// Ensure the 'blog' page exists, is published, and is titled 'Blog'.
	$blog_page = get_page_by_path( 'blog', OBJECT, 'page' );

	if ( ! $blog_page ) {
		$blog_id = wp_insert_post(
			array(
				'post_title'  => 'Blog',
				'post_name'   => 'blog',
				'post_type'   => 'page',
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $blog_id ) ) {
			return $blog_id;
		}
	} else {
		$blog_id = $blog_page->ID;

		if ( 'publish' !== $blog_page->post_status || 'Blog' !== $blog_page->post_title ) {
			$updated = wp_update_post(
				array(
					'ID'          => $blog_id,
					'post_title'  => 'Blog',
					'post_status' => 'publish',
				),
				true
			);

			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}
	}

	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $home_id );
	update_option( 'page_for_posts', $blog_id );
	update_option( 'permalink_structure', '/%postname%/' );
	flush_rewrite_rules( false );

	// Deliberately NOT normalized: home_url() has no trailing slash while
	// get_permalink() does, and the test suite asserts against both
	// redirect targets verbatim, so both are returned as-is.
	return array(
		'home_id'             => (int) $home_id,
		'blog_id'             => (int) $blog_id,
		'front_page_url'      => get_permalink( $home_id ),
		'home_url'            => home_url(),
		'permalink_structure' => get_option( 'permalink_structure' ),
	);
}

/**
 * Delete all spec-seeded content: every 'post' in any status, every 'page'
 * except the Home/Blog pages set up by /setup, every comment, and every
 * non-default category/post_tag term.
 *
 * Uses get_posts()/get_comments()/get_terms() directly rather than the REST
 * controllers, because the 'post' post type's REST route is disabled by the
 * plugin under test and would not see this content at all.
 *
 * @return array<string, int>|WP_Error
 */
function dwpb_test_api_reset_content() {

	$home_id = (int) get_option( 'page_on_front' );
	$blog_id = (int) get_option( 'page_for_posts' );

	// Delete every 'post' in any status.
	$deleted_posts = 0;
	$post_ids      = get_posts(
		array(
			'post_type'   => 'post',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	);

	foreach ( $post_ids as $post_id ) {
		if ( wp_delete_post( $post_id, true ) ) {
			++$deleted_posts;
		}
	}

	// Delete every 'page' in any status, except the Home/Blog pages.
	$deleted_pages = 0;
	$page_ids      = get_posts(
		array(
			'post_type'    => 'page',
			'post_status'  => 'any',
			'numberposts'  => -1,
			'fields'       => 'ids',
			'post__not_in' => array_values( array_filter( array( $home_id, $blog_id ) ) ),
		)
	);

	foreach ( $page_ids as $page_id ) {
		if ( wp_delete_post( $page_id, true ) ) {
			++$deleted_pages;
		}
	}

	// Delete every comment, regardless of status.
	$deleted_comments = 0;
	$comment_ids      = get_comments( array( 'fields' => 'ids' ) );

	foreach ( $comment_ids as $comment_id ) {
		if ( wp_delete_comment( $comment_id, true ) ) {
			++$deleted_comments;
		}
	}

	// Delete every category/post_tag term except the default "Uncategorized" category.
	$deleted_terms    = 0;
	$default_category = dwpb_test_api_default_category_id();
	$reset_taxonomies = array( 'category', 'post_tag' );

	foreach ( $reset_taxonomies as $taxonomy ) {
		$term_ids = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);

		if ( is_wp_error( $term_ids ) ) {
			return $term_ids;
		}

		foreach ( $term_ids as $term_id ) {
			if ( 'category' === $taxonomy && $default_category === (int) $term_id ) {
				continue;
			}

			$result = wp_delete_term( (int) $term_id, $taxonomy );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( true === $result ) {
				++$deleted_terms;
			}
		}
	}

	return array(
		'deleted_posts'    => $deleted_posts,
		'deleted_pages'    => $deleted_pages,
		'deleted_terms'    => $deleted_terms,
		'deleted_comments' => $deleted_comments,
	);
}

/**
 * Delete every 'post' in any status, leaving pages/comments/terms alone.
 *
 * A narrower sibling of /reset-content for specs that only seeded posts and
 * want a cheaper cleanup than the full reset.
 *
 * @return array<string, int>
 */
function dwpb_test_api_delete_all_posts() {

	$post_ids = get_posts(
		array(
			'post_type'   => 'post',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	);

	$deleted = 0;

	foreach ( $post_ids as $post_id ) {
		if ( wp_delete_post( $post_id, true ) ) {
			++$deleted;
		}
	}

	return array( 'deleted' => $deleted );
}

/**
 * Force-delete a single post (any post type) by id, via wp_delete_post().
 *
 * Idempotent and tolerant on purpose: this backs the per-spec `afterEach`
 * teardown helper on the TypeScript side, and a spec's own assertions may
 * already have deleted the id it is now tearing down. Treating a missing id
 * as an error would turn a passing test's teardown into a failure, so a
 * missing/already-deleted id is not a 404 or a WP_Error — it simply reports
 * `deleted => false` with HTTP 200.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function dwpb_test_api_delete_post( $post_id ) {

	if ( ! get_post( $post_id ) ) {
		return array(
			'deleted' => false,
			'id'      => $post_id,
		);
	}

	$result = wp_delete_post( $post_id, true );

	return array(
		'deleted' => (bool) $result,
		'id'      => $post_id,
	);
}

/**
 * Create a post via wp_insert_post(), the route core REST won't allow for
 * the 'post' post type under this plugin.
 *
 * @param WP_REST_Request $request Full request object.
 * @return array<string, mixed>|WP_Error
 */
function dwpb_test_api_create_post( WP_REST_Request $request ) {

	$post_args = array(
		'post_title'  => (string) $request->get_param( 'title' ),
		'post_status' => (string) $request->get_param( 'status' ),
		'post_type'   => (string) $request->get_param( 'post_type' ),
	);

	if ( null !== $request->get_param( 'content' ) ) {
		$post_args['post_content'] = (string) $request->get_param( 'content' );
	}

	if ( null !== $request->get_param( 'author' ) ) {
		$post_args['post_author'] = (int) $request->get_param( 'author' );
	} else {
		$post_args['post_author'] = get_current_user_id();
	}

	if ( null !== $request->get_param( 'comment_status' ) ) {
		$post_args['comment_status'] = (string) $request->get_param( 'comment_status' );
	}

	if ( null !== $request->get_param( 'ping_status' ) ) {
		$post_args['ping_status'] = (string) $request->get_param( 'ping_status' );
	}

	if ( null !== $request->get_param( 'post_date' ) ) {
		$post_args['post_date'] = (string) $request->get_param( 'post_date' );
	}

	$post_id = wp_insert_post( $post_args, true );

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	$categories = $request->get_param( 'categories' );

	if ( ! empty( $categories ) && is_array( $categories ) ) {
		$set_categories = wp_set_post_terms( $post_id, array_map( 'intval', $categories ), 'category' );

		if ( is_wp_error( $set_categories ) ) {
			return $set_categories;
		}
	}

	$tags = $request->get_param( 'tags' );

	if ( ! empty( $tags ) && is_array( $tags ) ) {
		$set_tags = wp_set_post_terms( $post_id, array_map( 'intval', $tags ), 'post_tag' );

		if ( is_wp_error( $set_tags ) ) {
			return $set_tags;
		}
	}

	$post = get_post( $post_id );

	return array(
		'id'          => (int) $post_id,
		'permalink'   => get_permalink( $post_id ),
		'post_type'   => $post->post_type,
		'post_status' => $post->post_status,
	);
}

/**
 * Create a comment via wp_insert_comment().
 *
 * @param WP_REST_Request $request Full request object.
 * @return array<string, mixed>|WP_Error
 */
function dwpb_test_api_create_comment( WP_REST_Request $request ) {

	$post_id = (int) $request->get_param( 'post_id' );

	if ( ! get_post( $post_id ) ) {
		return new WP_Error( 'dwpb_test_no_post', "No post {$post_id}.", array( 'status' => 404 ) );
	}

	$approved = $request->get_param( 'approved' );
	$approved = ( null === $approved ) ? true : (bool) $approved;

	$comment_id = wp_insert_comment(
		array(
			'comment_post_ID'      => $post_id,
			'comment_content'      => (string) $request->get_param( 'content' ),
			'comment_author'       => (string) $request->get_param( 'author_name' ),
			'comment_author_email' => (string) $request->get_param( 'author_email' ),
			'comment_approved'     => $approved ? 1 : 0,
			'comment_type'         => 'comment',
		)
	);

	if ( ! $comment_id ) {
		return new WP_Error(
			'dwpb_test_comment_failed',
			"wp_insert_comment() returned falsey for post {$post_id}.",
			array( 'status' => 500 )
		);
	}

	$comment = get_comment( $comment_id );

	return array(
		'id'       => (int) $comment_id,
		'approved' => ( '1' === $comment->comment_approved ),
	);
}

/**
 * Create a category/post_tag term via wp_insert_term(), optionally assigning
 * it straight onto a post.
 *
 * @param WP_REST_Request $request Full request object.
 * @return array<string, mixed>|WP_Error
 */
function dwpb_test_api_create_term( WP_REST_Request $request ) {

	$taxonomy = (string) $request->get_param( 'taxonomy' );
	$name     = (string) $request->get_param( 'name' );

	if ( ! taxonomy_exists( $taxonomy ) ) {
		return new WP_Error(
			'dwpb_test_invalid_taxonomy',
			"Taxonomy '{$taxonomy}' does not exist.",
			array( 'status' => 400 )
		);
	}

	$inserted = wp_insert_term( $name, $taxonomy );

	if ( is_wp_error( $inserted ) ) {
		return $inserted;
	}

	$term_id = (int) $inserted['term_id'];
	$post_id = $request->get_param( 'assign_to' );

	if ( ! empty( $post_id ) ) {
		$assigned = wp_set_post_terms( (int) $post_id, array( $term_id ), $taxonomy, true );

		if ( is_wp_error( $assigned ) ) {
			return $assigned;
		}
	}

	$term = get_term( $term_id, $taxonomy );

	if ( is_wp_error( $term ) ) {
		return $term;
	}

	$link = get_term_link( $term );

	if ( is_wp_error( $link ) ) {
		return $link;
	}

	return array(
		'term_id' => $term_id,
		'slug'    => $term->slug,
		'link'    => $link,
	);
}

/**
 * User-facing strings asserted by specs, kept in one place so no spec ever
 * hardcodes plugin copy inline.
 *
 * IMPORTANT — these are NOT all read dynamically from the plugin. Only
 * `users_pages_column_label` is genuinely derived at runtime (from the 'page'
 * post type's own label object, mirroring what the plugin does). The other
 * five are hand-copied literals that must be kept in sync by hand, because
 * the plugin echoes or `wp_die()`s that copy inline rather than exposing it
 * through a retrievable accessor. If a PR reworks that wording without also
 * updating this file, the affected spec fails on the stale text rather than
 * self-correcting. Exposing those labels from the plugin (so this route could
 * read them) would remove the duplication -- see the Phase 3 backlog.
 *
 * Unlike the mutating routes above, this route does not itself verify the
 * strings are non-empty before responding — following the precedent set by
 * the sibling Archived Post Status fixture (`aps-test-api.php`), that check
 * is the responsibility of the TypeScript test client, which asserts every
 * value it reads from this route is a non-empty string.
 *
 * @return array<string, string>
 */
function dwpb_test_api_strings() {

	// users_pages_column_label mirrors Disable_Blog_Admin::manage_users_columns(),
	// which sets the column header to the 'page' post type's own label object
	// rather than a plugin-owned string.
	$page_type_object         = get_post_type_object( 'page' );
	$users_pages_column_label = isset( $page_type_object->labels->name ) ? $page_type_object->labels->name : '';

	return array(
		// Disable_Blog_Admin::admin_notices(), the "no static front page" branch.
		'no_front_page_notice'      => __( 'Disable Blog is not fully active until a static page is selected for the site\'s homepage.', 'disable-blog' ),
		// Disable_Blog_Admin::admin_notices(), the "front page === posts page" branch.
		'front_equals_posts_notice' => __( 'Disable Blog requires a homepage that is different from the post page. The "posts page" will be redirected to the homepage.', 'disable-blog' ),
		// Disable_Blog_Admin::posts_page_notice().
		'posts_page_edit_notice'    => __( 'You are currently editing the page that shows your latest posts, which is redirected to the homepage because the blog is disabled.', 'disable-blog' ),
		// Disable_Blog_Admin::disable_press_this(). Not passed through __() in the
		// plugin itself, so it is reproduced here verbatim rather than translated.
		'press_this_disabled'       => '"Press This" functionality has been disabled.',
		// Disable_Blog_Admin::page_post_states().
		'page_post_state'           => __( 'Redirected to the homepage', 'disable-blog' ),
		// Disable_Blog_Admin::manage_users_columns().
		'users_pages_column_label'  => $users_pages_column_label,
	);
}

/**
 * Register every dwpb-test/v1 route.
 *
 * A named function (rather than an anonymous closure) so WordPress's own
 * `add_action()` de-duplication protects against double registration if
 * this file were ever required more than once in a request.
 *
 * @return void
 */
function dwpb_test_api_register_routes() {

	// Idempotent environment seed: Home/Blog pages + reading settings.
	register_rest_route(
		'dwpb-test/v1',
		'/setup',
		array(
			'methods'             => 'POST',
			'permission_callback' => 'dwpb_test_api_can_manage',
			'callback'            => static function () {
				$result = dwpb_test_api_setup();

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return rest_ensure_response( $result );
			},
		)
	);

	// Full spec-content wipe: posts, non-Home/Blog pages, comments, non-default terms.
	register_rest_route(
		'dwpb-test/v1',
		'/reset-content',
		array(
			'methods'             => 'POST',
			'permission_callback' => 'dwpb_test_api_can_manage',
			'callback'            => static function () {
				$result = dwpb_test_api_reset_content();

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return rest_ensure_response( $result );
			},
		)
	);

	// Narrower wipe: 'post' type content only.
	register_rest_route(
		'dwpb-test/v1',
		'/delete-all-posts',
		array(
			'methods'             => 'POST',
			'permission_callback' => 'dwpb_test_api_can_manage',
			'callback'            => static function () {
				return rest_ensure_response( dwpb_test_api_delete_all_posts() );
			},
		)
	);

	// Force-delete a single post/page by id, idempotently. Backs the
	// TypeScript suite's per-spec afterEach teardown; a missing id is not an
	// error, see dwpb_test_api_delete_post()'s docblock.
	register_rest_route(
		'dwpb-test/v1',
		'/post/(?P<id>\d+)',
		array(
			'methods'             => 'DELETE',
			'permission_callback' => 'dwpb_test_api_can_manage',
			'callback'            => static function ( WP_REST_Request $request ) {
				return rest_ensure_response( dwpb_test_api_delete_post( (int) $request['id'] ) );
			},
		)
	);

	// Create a 'post' (or other post type) via wp_insert_post() directly,
	// since core REST's /wp/v2/posts route is disabled by the plugin.
	register_rest_route(
		'dwpb-test/v1',
		'/create-post',
		array(
			'methods'             => 'POST',
			'permission_callback' => 'dwpb_test_api_can_manage',
			'args'                => array(
				'title'          => array(
					'required' => true,
					'type'     => 'string',
				),
				'status'         => array(
					'type'    => 'string',
					'default' => 'publish',
				),
				'content'        => array(
					'type' => 'string',
				),
				'author'         => array(
					'type' => 'integer',
				),
				'comment_status' => array(
					'type' => 'string',
				),
				'ping_status'    => array(
					'type' => 'string',
				),
				'post_type'      => array(
					'type'    => 'string',
					'default' => 'post',
				),
				'post_date'      => array(
					'type' => 'string',
				),
				'categories'     => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
				'tags'           => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
			),
			'callback'            => static function ( WP_REST_Request $request ) {
				$result = dwpb_test_api_create_post( $request );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return rest_ensure_response( $result );
			},
		)
	);

	// Create a comment via wp_insert_comment().
	register_rest_route(
		'dwpb-test/v1',
		'/create-comment',
		array(
			'methods'             => 'POST',
			'permission_callback' => 'dwpb_test_api_can_manage',
			'args'                => array(
				'post_id'      => array(
					'required' => true,
					'type'     => 'integer',
				),
				'content'      => array(
					'type'    => 'string',
					'default' => '',
				),
				'approved'     => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'author_name'  => array(
					'type'    => 'string',
					'default' => '',
				),
				'author_email' => array(
					'type'    => 'string',
					'default' => '',
				),
			),
			'callback'            => static function ( WP_REST_Request $request ) {
				$result = dwpb_test_api_create_comment( $request );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return rest_ensure_response( $result );
			},
		)
	);

	// Create a category/post_tag term, optionally assigned to a post.
	register_rest_route(
		'dwpb-test/v1',
		'/create-term',
		array(
			'methods'             => 'POST',
			'permission_callback' => 'dwpb_test_api_can_manage',
			'args'                => array(
				'taxonomy'  => array(
					'required' => true,
					'type'     => 'string',
				),
				'name'      => array(
					'required' => true,
					'type'     => 'string',
				),
				'assign_to' => array(
					'type' => 'integer',
				),
			),
			'callback'            => static function ( WP_REST_Request $request ) {
				$result = dwpb_test_api_create_term( $request );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return rest_ensure_response( $result );
			},
		)
	);

	// Read a post's status-relevant state.
	register_rest_route(
		'dwpb-test/v1',
		'/post-state/(?P<id>\d+)',
		array(
			'methods'             => 'GET',
			'permission_callback' => 'dwpb_test_api_can_manage',
			'callback'            => static function ( WP_REST_Request $request ) {
				$result = dwpb_test_api_describe_post_state( (int) $request['id'] );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return rest_ensure_response( $result );
			},
		)
	);

	// Flush rewrite rules, e.g. after a spec changes the permalink structure directly.
	register_rest_route(
		'dwpb-test/v1',
		'/flush-rewrites',
		array(
			'methods'             => 'POST',
			'permission_callback' => 'dwpb_test_api_can_manage',
			'callback'            => static function () {
				flush_rewrite_rules( false );

				return rest_ensure_response( array( 'flushed' => true ) );
			},
		)
	);

	// User-facing strings asserted by specs, read straight from the plugin source.
	register_rest_route(
		'dwpb-test/v1',
		'/strings',
		array(
			'methods'             => 'GET',
			'permission_callback' => 'dwpb_test_api_can_manage',
			'callback'            => static function () {
				return rest_ensure_response( dwpb_test_api_strings() );
			},
		)
	);
}
add_action( 'rest_api_init', 'dwpb_test_api_register_routes' );
