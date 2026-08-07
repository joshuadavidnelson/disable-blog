<?php
/**
 * Tests for Disable_Blog_Admin::redirect_admin_pages(), its is_admin_page() gate, and each
 * individual redirect_admin_* delegate method.
 *
 * @package DisableBlog
 */

// Not required by tests/php/bootstrap.php on purpose (see ConstructorInjectionTest.php's note;
// LoaderTest's autoloader test needs Disable_Blog_Functions to remain undefined until its own
// isolated process). require_once is safe regardless of which test file happens to load first.
require_once __DIR__ . '/../../includes/class-disable-blog-functions.php';
require_once __DIR__ . '/Support/fixtures/class-admin-functions-double.php';

/**
 * @covers Disable_Blog_Admin::redirect_admin_pages
 * @covers Disable_Blog_Admin::is_admin_page
 * @covers Disable_Blog_Admin::redirect_admin_post
 * @covers Disable_Blog_Admin::redirect_admin_edit
 * @covers Disable_Blog_Admin::redirect_admin_post_new
 * @covers Disable_Blog_Admin::redirect_admin_term
 * @covers Disable_Blog_Admin::redirect_admin_edit_tags
 * @covers Disable_Blog_Admin::redirect_admin_edit_comments
 * @covers Disable_Blog_Admin::redirect_admin_options_discussion
 * @covers Disable_Blog_Admin::redirect_admin_options_writing
 * @covers Disable_Blog_Admin::redirect_admin_tools
 */
class AdminRedirectsTest extends TestCase {

	/**
	 * The global $pagenow as it stood before the test, so it can be restored in tear_down().
	 *
	 * @var mixed
	 */
	private $original_pagenow;

	/**
	 * The superglobal $_GET as it stood before the test, so it can be restored in tear_down().
	 *
	 * @var array
	 */
	private $original_get;

	protected function set_up() {
		parent::set_up();

		global $pagenow;
		$this->original_pagenow = $pagenow ?? null;
		// A null $pagenow is indistinguishable from an unset one to isset(), which is all
		// redirect_admin_pages()'s first guard and is_admin_page() ever check it with.
		$pagenow = null;

		$this->original_get = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$_GET                = array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	protected function tear_down() {
		global $pagenow;
		$pagenow = $this->original_pagenow;

		$_GET = $this->original_get; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		parent::tear_down();
	}

	/**
	 * Stubs get_current_screen() (null, so the multisite guard's is_callable() check is
	 * false), is_multisite() (false, so the guard's first condition is false) and
	 * admin_url( 'index.php' ) (the unconditional $dashboard_url computed right after both
	 * guards) -- everything redirect_admin_pages() needs before it reaches the dispatch loop.
	 *
	 * @param string $dashboard_url The url admin_url( 'index.php' ) should return.
	 * @return void
	 */
	private function stub_dashboard_guards_pass( $dashboard_url ) {
		WP_Mock::userFunction( 'get_current_screen' )->andReturn( null );
		WP_Mock::userFunction( 'is_multisite' )->andReturn( false );
		WP_Mock::userFunction( 'admin_url' )->with( 'index.php' )->andReturn( $dashboard_url );
	}

	/**
	 * Stubs the two filters redirect_admin_pages() applies, in order, after the dispatch loop
	 * computes $redirect_url: dwpb_admin_redirect_url (passthrough) and dwpb_redirect_admin
	 * (unfiltered true, so the redirect isn't skipped) -- so $redirect_url reaches
	 * $functions->redirect() unmodified by either.
	 *
	 * @param string $redirect_url The url expected to reach redirect() unmodified.
	 * @return void
	 */
	private function stub_final_filters_passthrough( $redirect_url ) {
		WP_Mock::onFilter( 'dwpb_admin_redirect_url' )->with( $redirect_url )->reply( $redirect_url );
		WP_Mock::onFilter( 'dwpb_redirect_admin' )->with( true, $redirect_url )->reply( true );
	}

	/**
	 * Stubs the WordPress functions dwpb_post_types_with_tax() calls on every invocation,
	 * regardless of taxonomy or cache state: get_post_types(), wp_cache_get()/wp_cache_set()
	 * and maybe_serialize(), all called with the ( array(), 'names' ) arguments this file's
	 * tests always use.
	 *
	 * @return void
	 */
	private function stub_tax_lookup_plumbing() {
		WP_Mock::userFunction( 'get_post_types' )->with( array(), 'names' )->andReturn( array() );
		WP_Mock::userFunction( 'wp_cache_get' )->andReturn( false );
		WP_Mock::userFunction( 'wp_cache_set' )->andReturn( null );
		WP_Mock::userFunction( 'maybe_serialize' )->with( array() )->andReturn( 'a:0:{}' );
	}

	/**
	 * Forces dwpb_post_types_with_tax( $taxonomy ) to return $return_value, via the
	 * dwpb_taxonomy_support short-circuit filter -- requires stub_tax_lookup_plumbing() to
	 * have been called first in the same test.
	 *
	 * @param string     $taxonomy     The taxonomy slug ('post_tag' or 'category').
	 * @param array|bool $return_value The value dwpb_post_types_with_tax() should return.
	 * @return void
	 */
	private function stub_post_types_with_tax_result( $taxonomy, $return_value ) {
		WP_Mock::userFunction( 'esc_attr' )->with( $taxonomy )->andReturn( $taxonomy );
		WP_Mock::onFilter( 'dwpb_taxonomy_support' )
			->with( null, $taxonomy, array(), array(), 'names' )
			->reply( $return_value );
	}

	/**
	 * Forces dwpb_post_types_with_feature( $feature ) to return $return_value.
	 *
	 * wp_cache_get() returning ANY array (even an empty one) is a cache HIT as far as
	 * dwpb_post_types_with_feature() is concerned -- it skips get_post_types()/
	 * post_type_supports() entirely and passes the cached value straight to the
	 * dwpb_post_types_supporting_{$feature} filter, whose reply IS the function's return
	 * value regardless of what was "cached".
	 *
	 * @param string     $feature      The feature slug (e.g. 'comments').
	 * @param array|bool $return_value The value dwpb_post_types_with_feature() should return.
	 * @return void
	 */
	private function stub_feature_cache_hit( $feature, $return_value ) {
		WP_Mock::userFunction( 'esc_attr' )->with( $feature )->andReturn( $feature );
		WP_Mock::userFunction( 'wp_cache_get' )
			->with( "post-types-supporting-{$feature}", 'post-types-by-feature' )
			->andReturn( array() );
		WP_Mock::onFilter( "dwpb_post_types_supporting_{$feature}" )
			->with( array(), array() )
			->reply( $return_value );
	}

	/**
	 * redirect_admin_pages()
	 */

	/**
	 * The `! isset( $pagenow )` guard is the very first thing the method checks, so nothing
	 * else may be stubbed here -- an unexpected call to an un-mocked WP function fatals.
	 */
	public function test_redirect_admin_pages_returns_early_when_pagenow_not_set() {
		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array(), $functions->redirect_calls );
	}

	/**
	 * On multisite, a network-admin screen must bail before the dispatch loop even starts.
	 */
	public function test_redirect_admin_pages_returns_early_on_multisite_network_admin_screen() {
		global $pagenow;
		$pagenow = 'post.php'; // Passes guard 1; must never be reached beyond guard 2.

		$screen = Mockery::mock();
		$screen->shouldReceive( 'in_admin' )->once()->with( 'network' )->andReturn( true );

		WP_Mock::userFunction( 'get_current_screen' )->once()->andReturn( $screen );
		WP_Mock::userFunction( 'is_multisite' )->once()->andReturn( true );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array(), $functions->redirect_calls );
	}

	/**
	 * is_multisite() alone must not be enough to bail -- $screen->in_admin( 'network' )
	 * returning false must let execution continue into the dispatch loop.
	 */
	public function test_redirect_admin_pages_continues_past_guard_when_screen_not_on_network_admin() {
		global $pagenow;
		$pagenow = 'unrelated-page.php'; // Matches no admin_redirects slug.

		$screen = Mockery::mock();
		$screen->shouldReceive( 'in_admin' )->once()->with( 'network' )->andReturn( false );

		WP_Mock::userFunction( 'get_current_screen' )->once()->andReturn( $screen );
		WP_Mock::userFunction( 'is_multisite' )->once()->andReturn( true );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );

		$dashboard_url = 'https://example.test/wp-admin/index.php';
		WP_Mock::userFunction( 'admin_url' )->with( 'index.php' )->andReturn( $dashboard_url );
		WP_Mock::onFilter( 'dwpb_admin_redirect_url' )->with( false )->reply( false );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array(), $functions->redirect_calls );
	}

	/**
	 * When $pagenow doesn't match any of the nine admin_redirects slugs, the loop never
	 * finds a match, $redirect_url stays false, and no redirect happens.
	 */
	public function test_redirect_admin_pages_does_nothing_when_pagenow_matches_no_slug() {
		global $pagenow;
		$pagenow = 'unrelated-page.php';

		$dashboard_url = 'https://example.test/wp-admin/index.php';
		$this->stub_dashboard_guards_pass( $dashboard_url );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		WP_Mock::onFilter( 'dwpb_admin_redirect_url' )->with( false )->reply( false );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array(), $functions->redirect_calls );
	}

	/**
	 * Break semantics: is_admin_page() is forced to report a match for every slug the loop
	 * checks, so the FIRST entry in $admin_redirects ('post') is reached before any later
	 * entry. dwpb_redirect_admin_edit (the second row's filter) is deliberately left
	 * unstubbed: if `break` were removed, the loop would reach 'edit' next and call
	 * apply_filters() on that hook, which strict mode turns into a hard failure.
	 */
	public function test_redirect_admin_pages_break_stops_loop_at_first_matching_slug() {
		global $pagenow;
		// The value doesn't matter to is_admin_page() here (it's mocked to always match),
		// but it must be a real string to pass the `! isset( $pagenow )` guard.
		$pagenow = 'post.php';

		$dashboard_url = 'https://example.test/wp-admin/index.php';
		$this->stub_dashboard_guards_pass( $dashboard_url );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );

		$_GET['post'] = 5;
		WP_Mock::userFunction( 'get_post_type' )->with( 5 )->andReturn( 'post' );

		$post_wins_url = 'https://example.test/wp-admin/post-wins/';
		WP_Mock::onFilter( 'dwpb_redirect_admin_post' )->with( $dashboard_url )->reply( $post_wins_url );
		$this->stub_final_filters_passthrough( $post_wins_url );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = $this->getMockBuilder( Disable_Blog_Admin::class )
			->setConstructorArgs( array( 'disable-blog', '0.5.6', $functions ) )
			->onlyMethods( array( 'is_admin_page' ) )
			->getMock();
		$admin->method( 'is_admin_page' )->willReturn( true );

		$admin->redirect_admin_pages();

		$this->assertSame( array( $post_wins_url ), $functions->redirect_calls );
	}

	/**
	 * dwpb_admin_redirect_url is a global override applied after the per-page filter and
	 * after the loop, so it must win over the per-page filtered value.
	 */
	public function test_redirect_admin_pages_dwpb_admin_redirect_url_filter_overrides_final_url() {
		global $pagenow;
		$pagenow = 'tools.php';

		$dashboard_url = 'https://example.test/wp-admin/index.php';
		$this->stub_dashboard_guards_pass( $dashboard_url );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );

		WP_Mock::onFilter( 'dwpb_redirect_admin_tools' )->with( $dashboard_url )->reply( $dashboard_url );
		WP_Mock::userFunction( 'apply_filters_deprecated' )
			->once()
			->with( 'dwpb_redirect_admin_options_tools', array( $dashboard_url ), '0.5.6', 'dwpb_redirect_admin_tools' )
			->andReturn( $dashboard_url );

		$global_override_url = 'https://example.test/wp-admin/global-override/';
		WP_Mock::onFilter( 'dwpb_admin_redirect_url' )->with( $dashboard_url )->reply( $global_override_url );
		WP_Mock::onFilter( 'dwpb_redirect_admin' )->with( true, $global_override_url )->reply( true );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array( $global_override_url ), $functions->redirect_calls );
	}

	/**
	 * dwpb_redirect_admin toggle: false disables the redirect entirely, even though a slug
	 * matched and a redirect url was computed.
	 */
	public function test_redirect_admin_pages_dwpb_redirect_admin_false_skips_redirect_entirely() {
		global $pagenow;
		$pagenow = 'tools.php';

		$dashboard_url = 'https://example.test/wp-admin/index.php';
		$this->stub_dashboard_guards_pass( $dashboard_url );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );

		WP_Mock::onFilter( 'dwpb_redirect_admin_tools' )->with( $dashboard_url )->reply( $dashboard_url );
		WP_Mock::userFunction( 'apply_filters_deprecated' )
			->once()
			->with( 'dwpb_redirect_admin_options_tools', array( $dashboard_url ), '0.5.6', 'dwpb_redirect_admin_tools' )
			->andReturn( $dashboard_url );

		WP_Mock::onFilter( 'dwpb_admin_redirect_url' )->with( $dashboard_url )->reply( $dashboard_url );
		WP_Mock::onFilter( 'dwpb_redirect_admin' )->with( true, $dashboard_url )->reply( false );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array(), $functions->redirect_calls );
	}

	/**
	 * 'post' row: redirect_admin_post() returns bool true -> dashboard redirect.
	 */
	public function test_redirect_admin_pages_redirects_post_screen_with_default_url() {
		global $pagenow;
		$pagenow      = 'post.php';
		$_GET['post'] = 5;

		$dashboard_url = 'https://example.test/wp-admin/index.php';
		$this->stub_dashboard_guards_pass( $dashboard_url );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		WP_Mock::userFunction( 'get_post_type' )->with( 5 )->andReturn( 'post' );

		WP_Mock::onFilter( 'dwpb_redirect_admin_post' )->with( $dashboard_url )->reply( $dashboard_url );
		$this->stub_final_filters_passthrough( $dashboard_url );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array( $dashboard_url ), $functions->redirect_calls );
	}

	public function test_redirect_admin_pages_dwpb_redirect_admin_post_filter_overrides_url() {
		global $pagenow;
		$pagenow      = 'post.php';
		$_GET['post'] = 5;

		$dashboard_url = 'https://example.test/wp-admin/index.php';
		$this->stub_dashboard_guards_pass( $dashboard_url );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		WP_Mock::userFunction( 'get_post_type' )->with( 5 )->andReturn( 'post' );

		$custom_url = 'https://example.test/wp-admin/custom-post/';
		WP_Mock::onFilter( 'dwpb_redirect_admin_post' )->with( $dashboard_url )->reply( $custom_url );
		$this->stub_final_filters_passthrough( $custom_url );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array( $custom_url ), $functions->redirect_calls );
	}

	/**
	 * 'edit' row: redirect_admin_edit() returns a url string (never bool true).
	 */
	public function test_redirect_admin_pages_redirects_edit_screen_with_default_url() {
		global $pagenow;
		$pagenow = 'edit.php';
		// The post_type query var is intentionally left absent.

		$dashboard_url = 'https://example.test/wp-admin/index.php';
		$edit_url      = 'https://example.test/wp-admin/edit.php?post_type=page';
		$this->stub_dashboard_guards_pass( $dashboard_url );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		WP_Mock::userFunction( 'admin_url' )->with( 'edit.php?post_type=page' )->andReturn( $edit_url );
		WP_Mock::userFunction( 'esc_url_raw' )->with( $edit_url )->andReturn( $edit_url );

		WP_Mock::onFilter( 'dwpb_redirect_admin_edit' )->with( $edit_url )->reply( $edit_url );
		$this->stub_final_filters_passthrough( $edit_url );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array( $edit_url ), $functions->redirect_calls );
	}

	public function test_redirect_admin_pages_dwpb_redirect_admin_edit_filter_overrides_url() {
		global $pagenow;
		$pagenow = 'edit.php';

		$dashboard_url = 'https://example.test/wp-admin/index.php';
		$edit_url      = 'https://example.test/wp-admin/edit.php?post_type=page';
		$this->stub_dashboard_guards_pass( $dashboard_url );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		WP_Mock::userFunction( 'admin_url' )->with( 'edit.php?post_type=page' )->andReturn( $edit_url );
		WP_Mock::userFunction( 'esc_url_raw' )->with( $edit_url )->andReturn( $edit_url );

		$custom_url = 'https://example.test/wp-admin/custom-edit/';
		WP_Mock::onFilter( 'dwpb_redirect_admin_edit' )->with( $edit_url )->reply( $custom_url );
		$this->stub_final_filters_passthrough( $custom_url );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array( $custom_url ), $functions->redirect_calls );
	}

	/**
	 * 'post-new' row: redirect_admin_post_new() returns a url string (never bool true).
	 */
	public function test_redirect_admin_pages_redirects_post_new_screen_with_default_url() {
		global $pagenow;
		$pagenow = 'post-new.php';

		$dashboard_url = 'https://example.test/wp-admin/index.php';
		$post_new_url  = 'https://example.test/wp-admin/post-new.php?post_type=page';
		$this->stub_dashboard_guards_pass( $dashboard_url );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		WP_Mock::userFunction( 'admin_url' )->with( 'post-new.php?post_type=page' )->andReturn( $post_new_url );
		WP_Mock::userFunction( 'esc_url_raw' )->with( $post_new_url )->andReturn( $post_new_url );

		WP_Mock::onFilter( 'dwpb_redirect_admin_post_new' )->with( $post_new_url )->reply( $post_new_url );
		$this->stub_final_filters_passthrough( $post_new_url );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array( $post_new_url ), $functions->redirect_calls );
	}

	public function test_redirect_admin_pages_dwpb_redirect_admin_post_new_filter_overrides_url() {
		global $pagenow;
		$pagenow = 'post-new.php';

		$dashboard_url = 'https://example.test/wp-admin/index.php';
		$post_new_url  = 'https://example.test/wp-admin/post-new.php?post_type=page';
		$this->stub_dashboard_guards_pass( $dashboard_url );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		WP_Mock::userFunction( 'admin_url' )->with( 'post-new.php?post_type=page' )->andReturn( $post_new_url );
		WP_Mock::userFunction( 'esc_url_raw' )->with( $post_new_url )->andReturn( $post_new_url );

		$custom_url = 'https://example.test/wp-admin/custom-post-new/';
		WP_Mock::onFilter( 'dwpb_redirect_admin_post_new' )->with( $post_new_url )->reply( $custom_url );
		$this->stub_final_filters_passthrough( $custom_url );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array( $custom_url ), $functions->redirect_calls );
	}

	/**
	 * 'edit-tags' row: redirect_admin_edit_tags() returns bool true -> dashboard redirect.
	 */
	public function test_redirect_admin_pages_redirects_edit_tags_screen_with_default_url() {
		global $pagenow;
		$pagenow          = 'edit-tags.php';
		$_GET['taxonomy'] = 'post_tag';

		$dashboard_url = 'https://example.test/wp-admin/index.php';
		$this->stub_dashboard_guards_pass( $dashboard_url );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'post_tag', false );

		WP_Mock::onFilter( 'dwpb_redirect_admin_edit_tags' )->with( $dashboard_url )->reply( $dashboard_url );
		$this->stub_final_filters_passthrough( $dashboard_url );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array( $dashboard_url ), $functions->redirect_calls );
	}

	public function test_redirect_admin_pages_dwpb_redirect_admin_edit_tags_filter_overrides_url() {
		global $pagenow;
		$pagenow          = 'edit-tags.php';
		$_GET['taxonomy'] = 'post_tag';

		$dashboard_url = 'https://example.test/wp-admin/index.php';
		$this->stub_dashboard_guards_pass( $dashboard_url );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'post_tag', false );

		$custom_url = 'https://example.test/wp-admin/custom-edit-tags/';
		WP_Mock::onFilter( 'dwpb_redirect_admin_edit_tags' )->with( $dashboard_url )->reply( $custom_url );
		$this->stub_final_filters_passthrough( $custom_url );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array( $custom_url ), $functions->redirect_calls );
	}

	/**
	 * 'term' row: redirect_admin_term() returns bool true -> dashboard redirect.
	 */
	public function test_redirect_admin_pages_redirects_term_screen_with_default_url() {
		global $pagenow;
		$pagenow          = 'term.php';
		$_GET['taxonomy'] = 'category';

		$dashboard_url = 'https://example.test/wp-admin/index.php';
		$this->stub_dashboard_guards_pass( $dashboard_url );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', false );

		WP_Mock::onFilter( 'dwpb_redirect_admin_term' )->with( $dashboard_url )->reply( $dashboard_url );
		$this->stub_final_filters_passthrough( $dashboard_url );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array( $dashboard_url ), $functions->redirect_calls );
	}

	public function test_redirect_admin_pages_dwpb_redirect_admin_term_filter_overrides_url() {
		global $pagenow;
		$pagenow          = 'term.php';
		$_GET['taxonomy'] = 'category';

		$dashboard_url = 'https://example.test/wp-admin/index.php';
		$this->stub_dashboard_guards_pass( $dashboard_url );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', false );

		$custom_url = 'https://example.test/wp-admin/custom-term/';
		WP_Mock::onFilter( 'dwpb_redirect_admin_term' )->with( $dashboard_url )->reply( $custom_url );
		$this->stub_final_filters_passthrough( $custom_url );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array( $custom_url ), $functions->redirect_calls );
	}

	/**
	 * 'edit-comments' row: redirect_admin_edit_comments() returns bool true -> dashboard redirect.
	 */
	public function test_redirect_admin_pages_redirects_edit_comments_screen_with_default_url() {
		global $pagenow;
		$pagenow = 'edit-comments.php';

		$dashboard_url = 'https://example.test/wp-admin/index.php';
		$this->stub_dashboard_guards_pass( $dashboard_url );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		$this->stub_feature_cache_hit( 'comments', false );

		WP_Mock::onFilter( 'dwpb_redirect_admin_edit_comments' )->with( $dashboard_url )->reply( $dashboard_url );
		$this->stub_final_filters_passthrough( $dashboard_url );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array( $dashboard_url ), $functions->redirect_calls );
	}

	public function test_redirect_admin_pages_dwpb_redirect_admin_edit_comments_filter_overrides_url() {
		global $pagenow;
		$pagenow = 'edit-comments.php';

		$dashboard_url = 'https://example.test/wp-admin/index.php';
		$this->stub_dashboard_guards_pass( $dashboard_url );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		$this->stub_feature_cache_hit( 'comments', false );

		$custom_url = 'https://example.test/wp-admin/custom-edit-comments/';
		WP_Mock::onFilter( 'dwpb_redirect_admin_edit_comments' )->with( $dashboard_url )->reply( $custom_url );
		$this->stub_final_filters_passthrough( $custom_url );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array( $custom_url ), $functions->redirect_calls );
	}

	/**
	 * 'options-discussion' row: redirect_admin_options_discussion() wraps
	 * redirect_admin_edit_comments(), but is dispatched under its own slug and filter.
	 */
	public function test_redirect_admin_pages_redirects_options_discussion_screen_with_default_url() {
		global $pagenow;
		$pagenow = 'options-discussion.php';

		$dashboard_url = 'https://example.test/wp-admin/index.php';
		$this->stub_dashboard_guards_pass( $dashboard_url );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		$this->stub_feature_cache_hit( 'comments', false );

		WP_Mock::onFilter( 'dwpb_redirect_admin_options_discussion' )->with( $dashboard_url )->reply( $dashboard_url );
		$this->stub_final_filters_passthrough( $dashboard_url );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array( $dashboard_url ), $functions->redirect_calls );
	}

	public function test_redirect_admin_pages_dwpb_redirect_admin_options_discussion_filter_overrides_url() {
		global $pagenow;
		$pagenow = 'options-discussion.php';

		$dashboard_url = 'https://example.test/wp-admin/index.php';
		$this->stub_dashboard_guards_pass( $dashboard_url );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		$this->stub_feature_cache_hit( 'comments', false );

		$custom_url = 'https://example.test/wp-admin/custom-options-discussion/';
		WP_Mock::onFilter( 'dwpb_redirect_admin_options_discussion' )->with( $dashboard_url )->reply( $custom_url );
		$this->stub_final_filters_passthrough( $custom_url );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array( $custom_url ), $functions->redirect_calls );
	}

	/**
	 * 'options-writing' row: redirect_admin_options_writing() returns a url string (never
	 * bool true).
	 */
	public function test_redirect_admin_pages_redirects_options_writing_screen_with_default_url() {
		global $pagenow;
		$pagenow = 'options-writing.php';

		$dashboard_url    = 'https://example.test/wp-admin/index.php';
		$options_general_url = 'https://example.test/wp-admin/options-general.php';
		$this->stub_dashboard_guards_pass( $dashboard_url );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		WP_Mock::onFilter( 'dwpb_remove_options_writing' )->with( false )->reply( true );
		WP_Mock::userFunction( 'admin_url' )->with( 'options-general.php' )->andReturn( $options_general_url );
		WP_Mock::userFunction( 'esc_url_raw' )->with( $options_general_url )->andReturn( $options_general_url );

		WP_Mock::onFilter( 'dwpb_redirect_admin_options_writing' )->with( $options_general_url )->reply( $options_general_url );
		$this->stub_final_filters_passthrough( $options_general_url );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array( $options_general_url ), $functions->redirect_calls );
	}

	public function test_redirect_admin_pages_dwpb_redirect_admin_options_writing_filter_overrides_url() {
		global $pagenow;
		$pagenow = 'options-writing.php';

		$dashboard_url        = 'https://example.test/wp-admin/index.php';
		$options_general_url  = 'https://example.test/wp-admin/options-general.php';
		$this->stub_dashboard_guards_pass( $dashboard_url );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		WP_Mock::onFilter( 'dwpb_remove_options_writing' )->with( false )->reply( true );
		WP_Mock::userFunction( 'admin_url' )->with( 'options-general.php' )->andReturn( $options_general_url );
		WP_Mock::userFunction( 'esc_url_raw' )->with( $options_general_url )->andReturn( $options_general_url );

		$custom_url = 'https://example.test/wp-admin/custom-options-writing/';
		WP_Mock::onFilter( 'dwpb_redirect_admin_options_writing' )->with( $options_general_url )->reply( $custom_url );
		$this->stub_final_filters_passthrough( $custom_url );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array( $custom_url ), $functions->redirect_calls );
	}

	/**
	 * 'tools' row: redirect_admin_tools() returns bool true -> dashboard redirect, and the
	 * 'tools' page is the only one that also fires the back-compat
	 * dwpb_redirect_admin_options_tools deprecated filter afterwards.
	 */
	public function test_redirect_admin_pages_redirects_tools_screen_with_default_url() {
		global $pagenow;
		$pagenow = 'tools.php';
		// The page query var is intentionally left absent.

		$dashboard_url = 'https://example.test/wp-admin/index.php';
		$this->stub_dashboard_guards_pass( $dashboard_url );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );

		WP_Mock::onFilter( 'dwpb_redirect_admin_tools' )->with( $dashboard_url )->reply( $dashboard_url );
		WP_Mock::userFunction( 'apply_filters_deprecated' )
			->once()
			->with( 'dwpb_redirect_admin_options_tools', array( $dashboard_url ), '0.5.6', 'dwpb_redirect_admin_tools' )
			->andReturn( $dashboard_url );
		$this->stub_final_filters_passthrough( $dashboard_url );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array( $dashboard_url ), $functions->redirect_calls );
	}

	public function test_redirect_admin_pages_dwpb_redirect_admin_tools_filter_overrides_url() {
		global $pagenow;
		$pagenow = 'tools.php';

		$dashboard_url = 'https://example.test/wp-admin/index.php';
		$this->stub_dashboard_guards_pass( $dashboard_url );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );

		$primary_custom_url = 'https://example.test/wp-admin/custom-tools/';
		WP_Mock::onFilter( 'dwpb_redirect_admin_tools' )->with( $dashboard_url )->reply( $primary_custom_url );
		WP_Mock::userFunction( 'apply_filters_deprecated' )
			->once()
			->with( 'dwpb_redirect_admin_options_tools', array( $primary_custom_url ), '0.5.6', 'dwpb_redirect_admin_tools' )
			->andReturn( $primary_custom_url );
		$this->stub_final_filters_passthrough( $primary_custom_url );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array( $primary_custom_url ), $functions->redirect_calls );
	}

	/**
	 * The deprecated dwpb_redirect_admin_options_tools filter runs AFTER the primary
	 * dwpb_redirect_admin_tools filter and can independently override its result.
	 */
	public function test_redirect_admin_pages_dwpb_redirect_admin_options_tools_deprecated_filter_overrides_url() {
		global $pagenow;
		$pagenow = 'tools.php';

		$dashboard_url = 'https://example.test/wp-admin/index.php';
		$this->stub_dashboard_guards_pass( $dashboard_url );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );

		WP_Mock::onFilter( 'dwpb_redirect_admin_tools' )->with( $dashboard_url )->reply( $dashboard_url );

		$deprecated_custom_url = 'https://example.test/wp-admin/deprecated-tools/';
		WP_Mock::userFunction( 'apply_filters_deprecated' )
			->once()
			->with( 'dwpb_redirect_admin_options_tools', array( $dashboard_url ), '0.5.6', 'dwpb_redirect_admin_tools' )
			->andReturn( $deprecated_custom_url );
		$this->stub_final_filters_passthrough( $deprecated_custom_url );

		$functions = new Disable_Blog_Admin_Functions_Double();
		$admin     = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $functions );

		$admin->redirect_admin_pages();

		$this->assertSame( array( $deprecated_custom_url ), $functions->redirect_calls );
	}

	/**
	 * is_admin_page()
	 */

	public function test_is_admin_page_false_when_not_is_admin() {
		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( false );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertFalse( $admin->is_admin_page( 'post' ) );
	}

	public function test_is_admin_page_false_when_pagenow_not_set() {
		// set_up()'s default leaves $pagenow null, which reads as unset to isset().
		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( true );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertFalse( $admin->is_admin_page( 'post' ) );
	}

	public function test_is_admin_page_false_when_pagenow_not_a_string() {
		global $pagenow;
		$pagenow = array( 'post.php' );

		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( true );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertFalse( $admin->is_admin_page( 'post' ) );
	}

	public function test_is_admin_page_false_when_pagenow_does_not_match() {
		global $pagenow;
		$pagenow = 'edit.php';

		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( true );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertFalse( $admin->is_admin_page( 'post' ) );
	}

	public function test_is_admin_page_true_when_pagenow_matches() {
		global $pagenow;
		$pagenow = 'post.php';

		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( true );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertTrue( $admin->is_admin_page( 'post' ) );
	}

	/**
	 * redirect_admin_post()
	 */

	public function test_redirect_admin_post_true_when_get_post_id_is_a_post() {
		$_GET['post'] = 5;
		WP_Mock::userFunction( 'get_post_type' )->once()->with( 5 )->andReturn( 'post' );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertTrue( $admin->redirect_admin_post() );
	}

	public function test_redirect_admin_post_false_when_get_post_id_is_not_a_post() {
		$_GET['post'] = 5;
		WP_Mock::userFunction( 'get_post_type' )->once()->with( 5 )->andReturn( 'page' );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertFalse( $admin->redirect_admin_post() );
	}

	public function test_redirect_admin_post_false_when_get_post_not_set() {
		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertFalse( $admin->redirect_admin_post() );
	}

	/**
	 * redirect_admin_edit()
	 */

	public function test_redirect_admin_edit_redirects_when_post_type_not_set() {
		$url = 'https://example.test/wp-admin/edit.php?post_type=page';
		WP_Mock::userFunction( 'admin_url' )->once()->with( 'edit.php?post_type=page' )->andReturn( $url );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertSame( $url, $admin->redirect_admin_edit() );
	}

	public function test_redirect_admin_edit_redirects_when_post_type_is_post() {
		$_GET['post_type'] = 'post';
		$url                = 'https://example.test/wp-admin/edit.php?post_type=page';
		WP_Mock::userFunction( 'admin_url' )->once()->with( 'edit.php?post_type=page' )->andReturn( $url );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertSame( $url, $admin->redirect_admin_edit() );
	}

	public function test_redirect_admin_edit_false_when_post_type_is_other() {
		$_GET['post_type'] = 'page';

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertFalse( $admin->redirect_admin_edit() );
	}

	/**
	 * redirect_admin_post_new()
	 */

	public function test_redirect_admin_post_new_redirects_when_post_type_not_set() {
		$url = 'https://example.test/wp-admin/post-new.php?post_type=page';
		WP_Mock::userFunction( 'admin_url' )->once()->with( 'post-new.php?post_type=page' )->andReturn( $url );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertSame( $url, $admin->redirect_admin_post_new() );
	}

	public function test_redirect_admin_post_new_redirects_when_post_type_is_post() {
		$_GET['post_type'] = 'post';
		$url                = 'https://example.test/wp-admin/post-new.php?post_type=page';
		WP_Mock::userFunction( 'admin_url' )->once()->with( 'post-new.php?post_type=page' )->andReturn( $url );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertSame( $url, $admin->redirect_admin_post_new() );
	}

	public function test_redirect_admin_post_new_false_when_post_type_is_other() {
		$_GET['post_type'] = 'page';

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertFalse( $admin->redirect_admin_post_new() );
	}

	/**
	 * redirect_admin_term()
	 */

	public function test_redirect_admin_term_true_when_taxonomy_not_used_elsewhere() {
		$_GET['taxonomy'] = 'category';
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', false );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertTrue( $admin->redirect_admin_term() );
	}

	public function test_redirect_admin_term_false_when_taxonomy_used_elsewhere() {
		$_GET['taxonomy'] = 'category';
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', array( 'book' ) );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertFalse( $admin->redirect_admin_term() );
	}

	public function test_redirect_admin_term_false_when_taxonomy_not_set() {
		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertFalse( $admin->redirect_admin_term() );
	}

	/**
	 * redirect_admin_edit_tags()
	 */

	public function test_redirect_admin_edit_tags_true_when_taxonomy_not_used_elsewhere() {
		$_GET['taxonomy'] = 'post_tag';
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'post_tag', false );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertTrue( $admin->redirect_admin_edit_tags() );
	}

	public function test_redirect_admin_edit_tags_false_when_taxonomy_used_elsewhere() {
		$_GET['taxonomy'] = 'post_tag';
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'post_tag', array( 'book' ) );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertFalse( $admin->redirect_admin_edit_tags() );
	}

	public function test_redirect_admin_edit_tags_false_when_taxonomy_not_set() {
		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertFalse( $admin->redirect_admin_edit_tags() );
	}

	/**
	 * redirect_admin_edit_comments()
	 */

	public function test_redirect_admin_edit_comments_true_when_no_other_post_type_supports_comments() {
		$this->stub_feature_cache_hit( 'comments', false );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertTrue( $admin->redirect_admin_edit_comments() );
	}

	public function test_redirect_admin_edit_comments_false_when_another_post_type_supports_comments() {
		$this->stub_feature_cache_hit( 'comments', array( 'book' ) );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertFalse( $admin->redirect_admin_edit_comments() );
	}

	/**
	 * redirect_admin_options_discussion() -- a thin wrapper around redirect_admin_edit_comments().
	 */

	public function test_redirect_admin_options_discussion_true_when_no_other_post_type_supports_comments() {
		$this->stub_feature_cache_hit( 'comments', false );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertTrue( $admin->redirect_admin_options_discussion() );
	}

	public function test_redirect_admin_options_discussion_false_when_another_post_type_supports_comments() {
		$this->stub_feature_cache_hit( 'comments', array( 'book' ) );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertFalse( $admin->redirect_admin_options_discussion() );
	}

	/**
	 * redirect_admin_options_writing()
	 */

	public function test_redirect_admin_options_writing_redirects_when_writing_options_removed() {
		$url = 'https://example.test/wp-admin/options-general.php';
		WP_Mock::onFilter( 'dwpb_remove_options_writing' )->with( false )->reply( true );
		WP_Mock::userFunction( 'admin_url' )->once()->with( 'options-general.php' )->andReturn( $url );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertSame( $url, $admin->redirect_admin_options_writing() );
	}

	public function test_redirect_admin_options_writing_false_when_writing_options_kept() {
		WP_Mock::onFilter( 'dwpb_remove_options_writing' )->with( false )->reply( false );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertFalse( $admin->redirect_admin_options_writing() );
	}

	/**
	 * redirect_admin_tools()
	 */

	public function test_redirect_admin_tools_true_when_page_not_set() {
		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertTrue( $admin->redirect_admin_tools() );
	}

	public function test_redirect_admin_tools_false_when_page_set() {
		$_GET['page'] = 'some-plugin-page';

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertFalse( $admin->redirect_admin_tools() );
	}
}
