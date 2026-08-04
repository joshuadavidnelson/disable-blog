<?php
/**
 * E2E mu-plugin fixture: REST routes for seeding/inspecting content in the
 * Playwright suite.
 *
 * Namespace `dwpb-test/v1`; every route requires `manage_options`. The plugin
 * sets `show_in_rest => false` on the 'post' post type, so `POST /wp/v2/posts`
 * 404s and posts can't be seeded through core REST — these routes call
 * `wp_insert_post()` etc. directly instead.
 *
 * @package Disable_Blog\TestFixtures
 */

defined( 'ABSPATH' ) || exit;

// No function_exists() guard here: PHP hoists these top-level function
// declarations, so the guard would always be true and skip the
// add_action( 'rest_api_init', ... ) below, silently unregistering every route.

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
 * Only 'category' has a WordPress-recognised default term (Uncategorized).
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
 * Idempotently seed the Home/Blog pages and the reading settings that point
 * at them. Safe to call again mid-run to self-heal a reading-settings change
 * made by another spec.
 *
 * @return array<string, mixed>|WP_Error
 */
function dwpb_test_api_setup() {

	// Home page: create or fix status/title.
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

	// Blog page: create or fix status/title.
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

	// Not normalized on purpose: home_url() has no trailing slash while
	// get_permalink() does, and specs assert against both verbatim.
	return array(
		'home_id'             => (int) $home_id,
		'blog_id'             => (int) $blog_id,
		'front_page_url'      => get_permalink( $home_id ),
		'home_url'            => home_url(),
		'permalink_structure' => get_option( 'permalink_structure' ),
	);
}

/**
 * Set reading settings (show_on_front, page_on_front, page_for_posts)
 * directly via update_option(), returning previous + current values.
 *
 * WordPress 5.9's core `/wp/v2/settings` route doesn't expose these keys, so
 * a spec can't set them through core REST. Only args present in the request
 * are applied; `previous` lets a spec restore what it changed.
 *
 * @param WP_REST_Request $request Full request object.
 * @return array<string, array<string, mixed>>
 */
function dwpb_test_api_set_reading_settings( WP_REST_Request $request ) {

	$previous = array(
		'show_on_front'  => get_option( 'show_on_front' ),
		'page_on_front'  => (int) get_option( 'page_on_front' ),
		'page_for_posts' => (int) get_option( 'page_for_posts' ),
	);

	if ( null !== $request->get_param( 'show_on_front' ) ) {
		update_option( 'show_on_front', (string) $request->get_param( 'show_on_front' ) );
	}

	if ( null !== $request->get_param( 'page_on_front' ) ) {
		update_option( 'page_on_front', (int) $request->get_param( 'page_on_front' ) );
	}

	if ( null !== $request->get_param( 'page_for_posts' ) ) {
		update_option( 'page_for_posts', (int) $request->get_param( 'page_for_posts' ) );
	}

	$current = array(
		'show_on_front'  => get_option( 'show_on_front' ),
		'page_on_front'  => (int) get_option( 'page_on_front' ),
		'page_for_posts' => (int) get_option( 'page_for_posts' ),
	);

	return array(
		'previous' => $previous,
		'current'  => $current,
	);
}

/**
 * Delete all spec-seeded content: every 'post', every 'page' except
 * Home/Blog, every comment, and every non-default category/post_tag term.
 *
 * Uses get_posts()/get_comments()/get_terms() directly rather than the REST
 * controllers, since the 'post' type's REST route is disabled by the plugin.
 *
 * @return array<string, int>|WP_Error
 */
function dwpb_test_api_reset_content() {

	$home_id = (int) get_option( 'page_on_front' );
	$blog_id = (int) get_option( 'page_for_posts' );

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

	// Pages, excluding Home/Blog.
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

	$deleted_comments = 0;
	$comment_ids      = get_comments( array( 'fields' => 'ids' ) );

	foreach ( $comment_ids as $comment_id ) {
		if ( wp_delete_comment( $comment_id, true ) ) {
			++$deleted_comments;
		}
	}

	// Category/post_tag terms, excluding the default "Uncategorized" category.
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
 * Cheaper alternative to /reset-content for specs that only seeded posts.
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
 * Backs the TypeScript suite's per-spec afterEach teardown. A missing/
 * already-deleted id is not an error — it reports `deleted => false` with
 * HTTP 200, so a spec's own cleanup can't fail this route's teardown.
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
 * Only `users_pages_column_label` is derived at runtime; the other five are
 * hand-copied literals that must be kept in sync manually, since the plugin
 * echoes/wp_die()s that copy inline rather than exposing an accessor.
 *
 * @return array<string, string>
 */
function dwpb_test_api_strings() {

	// Mirrors Disable_Blog_Admin::manage_users_columns(), which sets the
	// column header to the 'page' post type's own label object.
	$page_type_object         = get_post_type_object( 'page' );
	$users_pages_column_label = isset( $page_type_object->labels->name ) ? $page_type_object->labels->name : '';

	return array(
		// Disable_Blog_Admin::admin_notices(), the "no static front page" branch.
		'no_front_page_notice'      => __( 'Disable Blog is not fully active until a static page is selected for the site\'s homepage.', 'disable-blog' ),
		// Disable_Blog_Admin::admin_notices(), the "front page === posts page" branch.
		'front_equals_posts_notice' => __( 'Disable Blog requires a homepage that is different from the post page. The "posts page" will be redirected to the homepage.', 'disable-blog' ),
		// Disable_Blog_Admin::posts_page_notice().
		'posts_page_edit_notice'    => __( 'You are currently editing the page that shows your latest posts, which is redirected to the homepage because the blog is disabled.', 'disable-blog' ),
		// Disable_Blog_Admin::disable_press_this(). Not passed through __() in
		// the plugin, so reproduced verbatim rather than translated.
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
 * @return void
 */
function dwpb_test_api_register_routes() {

	// Idempotently seed Home/Blog pages + reading settings.
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

	// Force-delete a single post/page by id; idempotent, see function docblock.
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

	// Create a post via wp_insert_post(); core REST's /wp/v2/posts is disabled by the plugin.
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

	// Set reading settings directly; core REST's /wp/v2/settings doesn't expose these keys on WP 5.9.
	register_rest_route(
		'dwpb-test/v1',
		'/reading-settings',
		array(
			'methods'             => 'POST',
			'permission_callback' => 'dwpb_test_api_can_manage',
			'args'                => array(
				'show_on_front'  => array(
					'type' => 'string',
				),
				'page_on_front'  => array(
					'type' => 'integer',
				),
				'page_for_posts' => array(
					'type' => 'integer',
				),
			),
			'callback'            => static function ( WP_REST_Request $request ) {
				return rest_ensure_response( dwpb_test_api_set_reading_settings( $request ) );
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
