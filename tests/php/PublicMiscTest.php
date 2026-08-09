<?php
/**
 * Tests for the remaining Disable_Blog_Public surface: xmlrpc_methods(), its private
 * get_disabled_xmlrpc_methods() helper, filter_wp_headers(), remove_pingback_header_fallback(),
 * wp_sitemaps_post_types(), wp_sitemaps_taxonomies(), wp_author_sitemaps() and
 * disable_removed_sitemaps().
 *
 * @package DisableBlog
 */

// Not required by tests/php/bootstrap.php on purpose (see ConstructorInjectionTest.php's note;
// LoaderTest's autoloader test needs Disable_Blog_Functions to remain undefined until its own
// isolated process). require_once is safe regardless of which test file happens to load first.
require_once __DIR__ . '/../../includes/class-disable-blog-functions.php';
require_once __DIR__ . '/Support/fixtures/class-public-functions-double.php';

/**
 * @covers Disable_Blog_Public::xmlrpc_methods
 * @covers Disable_Blog_Public::get_disabled_xmlrpc_methods
 * @covers Disable_Blog_Public::filter_wp_headers
 * @covers Disable_Blog_Public::remove_pingback_header_fallback
 * @covers Disable_Blog_Public::wp_sitemaps_post_types
 * @covers Disable_Blog_Public::wp_sitemaps_taxonomies
 * @covers Disable_Blog_Public::wp_author_sitemaps
 * @covers Disable_Blog_Public::disable_removed_sitemaps
 */
class PublicMiscTest extends TestCase {

	/**
	 * The global $wp_query as it stood before the test, so it can be restored in tear_down().
	 *
	 * @var mixed
	 */
	private $original_wp_query;

	protected function set_up() {
		parent::set_up();

		global $wp_query;
		$this->original_wp_query = $wp_query ?? null;
	}

	protected function tear_down() {
		global $wp_query;
		$wp_query = $this->original_wp_query;

		parent::tear_down();
	}

	/**
	 * Invokes the private get_disabled_xmlrpc_methods() method via Reflection.
	 *
	 * @param Disable_Blog_Public $public Instance to invoke on.
	 * @return array|bool
	 */
	private function invoke_get_disabled_xmlrpc_methods( Disable_Blog_Public $public ) {
		$reflection = new ReflectionMethod( $public, 'get_disabled_xmlrpc_methods' );
		$reflection->setAccessible( true );

		return $reflection->invoke( $public );
	}

	/**
	 * The base, taxonomy-independent list of xmlrpc methods get_disabled_xmlrpc_methods()
	 * always starts from.
	 *
	 * @return string[]
	 */
	private function base_xmlrpc_methods() {
		return array(
			'wp.getUsersBlogs',
			'wp.newPost',
			'wp.editPost',
			'wp.deletePost',
			'wp.getPost',
			'wp.getPosts',
			'blogger.getPost',
			'blogger.getRecentPosts',
			'blogger.newPost',
			'blogger.editPost',
			'blogger.deletePost',
			'metaWeblog.newPost',
			'metaWeblog.editPost',
			'metaWeblog.getPost',
			'metaWeblog.getRecentPosts',
			'metaWeblog.deletePost',
			'mt.getRecentPostTitles',
			'mt.getTrackbackPings',
			'mt.publishPost',
			'pingback.ping',
			'pingback.extensions.getPingbacks',
			'demo.sayHello',
			'demo.addTwoNumbers',
		);
	}

	/**
	 * The category-taxonomy-specific xmlrpc methods, added only when no other post type uses
	 * the 'category' taxonomy.
	 *
	 * @return string[]
	 */
	private function category_xmlrpc_methods() {
		return array(
			'wp.newCategory',
			'wp.deleteCategory',
			'mt.getCategoryList',
			'wp.suggestCategories',
			'mt.getPostCategories',
			'mt.setPostCategories',
			'metaWeblog.getCategories',
		);
	}

	/**
	 * The full method list get_disabled_xmlrpc_methods() builds and passes to the
	 * dwpb_disabled_xmlrpc_methods filter when neither taxonomy is used elsewhere -- the
	 * category and post_tag stubs every test in this section that needs this uses.
	 *
	 * @return string[]
	 */
	private function all_xmlrpc_methods() {
		return array_merge( $this->base_xmlrpc_methods(), $this->category_xmlrpc_methods(), array( 'wp.getTags' ) );
	}

	/**
	 * Stubs the WordPress functions dwpb_post_types_with_tax() calls on every invocation,
	 * regardless of taxonomy or cache state.
	 *
	 * @return void
	 */
	private function stub_tax_lookup_plumbing() {
		WP_Mock::userFunction( 'get_post_types' )
			->with(
				Mockery::on(
					function ( $args ) {
						return array() === $args;
					}
				),
				'names'
			)
			->andReturn( array() );
		WP_Mock::userFunction( 'wp_cache_get' )->andReturn( false );
		WP_Mock::userFunction( 'wp_cache_set' )->andReturn( null );
		WP_Mock::userFunction( 'maybe_serialize' )
			->with(
				Mockery::on(
					function ( $args ) {
						return array() === $args;
					}
				)
			)
			->andReturn( 'a:0:{}' );
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
			->reply(
				new WP_Mock\InvokedFilterValue(
					function ( $null, $tax, $post_types, $args, $output ) use ( $return_value ) {
						$this->assertSame( array(), $post_types, 'dwpb_taxonomy_support must receive the exact $post_types array it documents.' );
						$this->assertSame( array(), $args, 'dwpb_taxonomy_support must receive the exact $args array it documents.' );

						return $return_value;
					}
				)
			);
	}

	/**
	 * Asserts disable_removed_sitemaps() had no 404 side effect: sets up global $wp_query so a
	 * set_404() call would fail the test, and rejects any status_header()/nocache_headers() call.
	 *
	 * @return void
	 */
	private function assert_no_404_side_effects() {
		global $wp_query;
		$wp_query = Mockery::mock( 'Disable_Blog_Public_Query_Double' );
		$wp_query->shouldNotReceive( 'set_404' );

		WP_Mock::userFunction( 'status_header' )->never();
		WP_Mock::userFunction( 'nocache_headers' )->never();
	}

	/**
	 * get_disabled_xmlrpc_methods() (private)
	 */

	public function test_get_disabled_xmlrpc_methods_includes_taxonomy_methods_when_neither_taxonomy_used_elsewhere() {
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', false );
		$this->stub_post_types_with_tax_result( 'post_tag', false );

		$expected = array_merge( $this->base_xmlrpc_methods(), $this->category_xmlrpc_methods(), array( 'wp.getTags' ) );
		WP_Mock::onFilter( 'dwpb_disabled_xmlrpc_methods' )->with( $expected )->reply( $expected );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertSame( $expected, $this->invoke_get_disabled_xmlrpc_methods( $public ) );
	}

	public function test_get_disabled_xmlrpc_methods_omits_category_methods_when_other_post_types_use_category() {
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', array( 'book' ) );
		$this->stub_post_types_with_tax_result( 'post_tag', false );

		$expected = array_merge( $this->base_xmlrpc_methods(), array( 'wp.getTags' ) );
		WP_Mock::onFilter( 'dwpb_disabled_xmlrpc_methods' )->with( $expected )->reply( $expected );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertSame( $expected, $this->invoke_get_disabled_xmlrpc_methods( $public ) );
	}

	public function test_get_disabled_xmlrpc_methods_omits_get_tags_when_other_post_types_use_post_tag() {
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', false );
		$this->stub_post_types_with_tax_result( 'post_tag', array( 'book' ) );

		$expected = array_merge( $this->base_xmlrpc_methods(), $this->category_xmlrpc_methods() );
		WP_Mock::onFilter( 'dwpb_disabled_xmlrpc_methods' )->with( $expected )->reply( $expected );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertSame( $expected, $this->invoke_get_disabled_xmlrpc_methods( $public ) );
	}

	public function test_get_disabled_xmlrpc_methods_omits_all_taxonomy_methods_when_both_taxonomies_used_elsewhere() {
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', array( 'book' ) );
		$this->stub_post_types_with_tax_result( 'post_tag', array( 'book' ) );

		WP_Mock::onFilter( 'dwpb_disabled_xmlrpc_methods' )->with( $this->base_xmlrpc_methods() )->reply( $this->base_xmlrpc_methods() );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertSame( $this->base_xmlrpc_methods(), $this->invoke_get_disabled_xmlrpc_methods( $public ) );
	}

	/**
	 * Returning false from dwpb_disabled_xmlrpc_methods disables the functionality entirely.
	 */
	public function test_get_disabled_xmlrpc_methods_filter_returning_false_disables_functionality() {
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', false );
		$this->stub_post_types_with_tax_result( 'post_tag', false );

		WP_Mock::onFilter( 'dwpb_disabled_xmlrpc_methods' )->with( $this->all_xmlrpc_methods() )->reply( false );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertFalse( $this->invoke_get_disabled_xmlrpc_methods( $public ) );
	}

	/**
	 * array_filter() preserves keys, so a non-string entry dropped from the middle leaves a gap
	 * instead of the array being reindexed.
	 */
	public function test_get_disabled_xmlrpc_methods_filters_out_non_string_entries() {
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', false );
		$this->stub_post_types_with_tax_result( 'post_tag', false );

		WP_Mock::onFilter( 'dwpb_disabled_xmlrpc_methods' )->with( $this->all_xmlrpc_methods() )->reply( array( 'wp.newPost', 123, 'wp.editPost' ) );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertSame(
			array( 0 => 'wp.newPost', 2 => 'wp.editPost' ),
			$this->invoke_get_disabled_xmlrpc_methods( $public )
		);
	}

	/**
	 * xmlrpc_methods()
	 */

	public function test_xmlrpc_methods_removes_configured_methods_and_keeps_others() {
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', false );
		$this->stub_post_types_with_tax_result( 'post_tag', false );

		WP_Mock::onFilter( 'dwpb_disabled_xmlrpc_methods' )->with( $this->all_xmlrpc_methods() )->reply( array( 'wp.newPost', 'wp.getTags' ) );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$methods = array(
			'wp.newPost'  => 'callback_a',
			'wp.getTags'  => 'callback_b',
			'wp.getUsers' => 'callback_c',
		);

		$this->assertSame(
			array( 'wp.getUsers' => 'callback_c' ),
			$public->xmlrpc_methods( $methods )
		);
	}

	public function test_xmlrpc_methods_returns_methods_unchanged_when_none_to_remove_are_present() {
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', false );
		$this->stub_post_types_with_tax_result( 'post_tag', false );

		WP_Mock::onFilter( 'dwpb_disabled_xmlrpc_methods' )->with( $this->all_xmlrpc_methods() )->reply( array( 'wp.newPost' ) );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$methods = array( 'wp.getUsers' => 'callback_c' );

		$this->assertSame( $methods, $public->xmlrpc_methods( $methods ) );
	}

	/**
	 * filter_wp_headers()
	 */

	public function test_filter_wp_headers_removes_pingback_header_by_default() {
		$this->stub_filter_strict( 'dwpb_remove_pingback_header', true, true );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$headers = array(
			'X-Pingback' => 'https://example.test/xmlrpc.php',
			'Other'      => 'value',
		);

		$this->assertSame( array( 'Other' => 'value' ), $public->filter_wp_headers( $headers ) );
	}

	public function test_filter_wp_headers_dwpb_remove_pingback_header_filter_false_keeps_header() {
		$this->stub_filter_strict( 'dwpb_remove_pingback_header', true, false );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$headers = array( 'X-Pingback' => 'https://example.test/xmlrpc.php' );

		$this->assertSame( $headers, $public->filter_wp_headers( $headers ) );
	}

	public function test_filter_wp_headers_noop_when_pingback_header_absent() {
		$this->stub_filter_strict( 'dwpb_remove_pingback_header', true, true );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$headers = array( 'Other' => 'value' );

		$this->assertSame( $headers, $public->filter_wp_headers( $headers ) );
	}

	/**
	 * remove_pingback_header_fallback()
	 */

	/**
	 * The guard is a return: the filter disabling removal must stop the fallback before it
	 * reaches do_remove_pingback_header() at all. Asserted via that seam rather than the
	 * headers_sent()/header_remove() body it guards, since those are genuine internal PHP
	 * functions WP_Mock/Patchwork refuses to override ("Cannot override internal PHP
	 * functions!").
	 */
	public function test_remove_pingback_header_fallback_returns_early_when_filter_disables_it() {
		$this->stub_filter_strict( 'dwpb_remove_pingback_header', true, false );

		$public = new class( 'disable-blog', '0.5.6' ) extends Disable_Blog_Public {
			/**
			 * @var int
			 */
			public $do_remove_pingback_header_calls = 0;

			protected function do_remove_pingback_header() {
				++$this->do_remove_pingback_header_calls;
			}
		};

		$this->assertNull( $public->remove_pingback_header_fallback() );
		$this->assertSame( 0, $public->do_remove_pingback_header_calls );
	}

	/**
	 * headers_sent() and header_remove() are both genuine internal PHP functions, so neither can
	 * be stubbed via WP_Mock (see the note above) -- there is no way, short of a source change
	 * adding an injectable seam, to simulate the "not yet sent" branch that would call
	 * header_remove(). By the time any PHPUnit test runs, PHPUnit's own CLI banner output has
	 * already made the real headers_sent() report true for the rest of the process, so this
	 * exercises the real "already sent" branch for real rather than simulating it.
	 */
	public function test_remove_pingback_header_fallback_skips_header_remove_once_headers_have_actually_been_sent() {
		$this->assertTrue( headers_sent(), "Expected PHPUnit's own CLI output to have already marked headers as sent." );

		$this->stub_filter_strict( 'dwpb_remove_pingback_header', true, true );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertNull( $public->remove_pingback_header_fallback() );
	}

	/**
	 * The counterpart to the early-return test above: when the filter allows removal, the
	 * fallback must actually reach do_remove_pingback_header(), not just return null (a void
	 * method returns null either way, so assertNull() alone can't distinguish "reached" from
	 * "guarded out").
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_remove_pingback_header_fallback_calls_header_remove_when_headers_not_yet_sent() {
		$this->stub_filter_strict( 'dwpb_remove_pingback_header', true, true );

		$public = new class( 'disable-blog', '0.5.6' ) extends Disable_Blog_Public {
			/**
			 * @var int
			 */
			public $do_remove_pingback_header_calls = 0;

			protected function do_remove_pingback_header() {
				++$this->do_remove_pingback_header_calls;
			}
		};

		$this->assertNull( $public->remove_pingback_header_fallback() );
		$this->assertSame( 1, $public->do_remove_pingback_header_calls );
	}

	/**
	 * wp_sitemaps_post_types()
	 */

	public function test_wp_sitemaps_post_types_removes_post_key() {
		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$result = $public->wp_sitemaps_post_types(
			array(
				'post' => 'PostType',
				'page' => 'PageType',
			)
		);

		$this->assertSame( array( 'page' => 'PageType' ), $result );
	}

	public function test_wp_sitemaps_post_types_unchanged_when_post_key_absent() {
		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$input = array( 'page' => 'PageType' );

		$this->assertSame( $input, $public->wp_sitemaps_post_types( $input ) );
	}

	/**
	 * wp_sitemaps_taxonomies()
	 */

	public function test_wp_sitemaps_taxonomies_removes_both_when_neither_used_by_other_post_types() {
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'post_tag', false );
		$this->stub_post_types_with_tax_result( 'category', false );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$result = $public->wp_sitemaps_taxonomies(
			array(
				'post_tag'    => 'a',
				'category'    => 'b',
				'post_format' => 'c',
			)
		);

		$this->assertSame( array( 'post_format' => 'c' ), $result );
	}

	public function test_wp_sitemaps_taxonomies_keeps_category_when_other_post_types_use_it() {
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'post_tag', false );
		$this->stub_post_types_with_tax_result( 'category', array( 'book' ) );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$result = $public->wp_sitemaps_taxonomies(
			array(
				'post_tag' => 'a',
				'category' => 'b',
			)
		);

		$this->assertSame( array( 'category' => 'b' ), $result );
	}

	/**
	 * The isset() guard must skip the lookup entirely for a taxonomy key not present in the
	 * input -- if dwpb_post_types_with_tax( 'category' ) were called anyway, the un-mocked
	 * esc_attr( 'category' ) call inside it would fatal here.
	 */
	public function test_wp_sitemaps_taxonomies_skips_lookup_for_taxonomy_key_not_present() {
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'post_tag', false );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$result = $public->wp_sitemaps_taxonomies( array( 'post_tag' => 'a' ) );

		$this->assertSame( array(), $result );
	}

	/**
	 * wp_author_sitemaps()
	 */

	public function test_wp_author_sitemaps_returns_provider_unchanged_when_name_is_not_users() {
		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );
		$provider  = new stdClass();

		$this->assertSame( $provider, $public->wp_author_sitemaps( $provider, 'posts' ) );
	}

	public function test_wp_author_sitemaps_returns_false_when_disable_author_archives_true() {
		$functions                                   = new Disable_Blog_Public_Functions_Double();
		$functions->disable_author_archives_return = true;
		$public                                       = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$this->stub_filter_strict( 'dwpb_disable_user_sitemap', true, true );

		$this->assertFalse( $public->wp_author_sitemaps( new stdClass(), 'users' ) );
	}

	public function test_wp_author_sitemaps_returns_false_when_no_post_types_support_author_archives() {
		$functions                                     = new Disable_Blog_Public_Functions_Double();
		$functions->disable_author_archives_return   = false;
		$functions->author_archive_post_types_return = false;
		$public                                         = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$this->stub_filter_strict( 'dwpb_disable_user_sitemap', true, true );

		$this->assertFalse( $public->wp_author_sitemaps( new stdClass(), 'users' ) );
	}

	public function test_wp_author_sitemaps_returns_provider_when_post_types_support_author_archives() {
		$functions                                     = new Disable_Blog_Public_Functions_Double();
		$functions->disable_author_archives_return   = false;
		$functions->author_archive_post_types_return = array( 'book' );
		$public                                         = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$this->stub_filter_strict( 'dwpb_disable_user_sitemap', false, false );

		$provider = new stdClass();

		$this->assertSame( $provider, $public->wp_author_sitemaps( $provider, 'users' ) );
	}

	/**
	 * The dwpb_disable_user_sitemap filter must be able to force the sitemap off even though
	 * the computed default would have kept it.
	 */
	public function test_wp_author_sitemaps_dwpb_disable_user_sitemap_filter_can_force_disable() {
		$functions                                     = new Disable_Blog_Public_Functions_Double();
		$functions->disable_author_archives_return   = false;
		$functions->author_archive_post_types_return = array( 'book' );
		$public                                         = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$this->stub_filter_strict( 'dwpb_disable_user_sitemap', false, true );

		$this->assertFalse( $public->wp_author_sitemaps( new stdClass(), 'users' ) );
	}

	/**
	 * The dwpb_disable_user_sitemap filter must be able to force the sitemap back on even
	 * though the computed default would have disabled it.
	 */
	public function test_wp_author_sitemaps_dwpb_disable_user_sitemap_filter_can_force_enable() {
		$functions                                   = new Disable_Blog_Public_Functions_Double();
		$functions->disable_author_archives_return = true;
		$public                                       = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$this->stub_filter_strict( 'dwpb_disable_user_sitemap', true, false );

		$provider = new stdClass();

		$this->assertSame( $provider, $public->wp_author_sitemaps( $provider, 'users' ) );
	}

	/**
	 * disable_removed_sitemaps()
	 */

	public function test_disable_removed_sitemaps_returns_early_when_sitemap_query_var_empty() {
		WP_Mock::userFunction( 'get_query_var' )->with( 'sitemap', '' )->andReturn( '' );
		$this->assert_no_404_side_effects();

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertNull( $public->disable_removed_sitemaps() );
	}

	/**
	 * Isolated, with a real registry in place (so a broken guard would fall through all the
	 * way to the provider check below): 'index' is never a registered provider name, so if the
	 * 'index' exclusion itself were dropped, isset( $providers['index'] ) would be false and
	 * this would 404 instead of returning early. Proves the 'index' check specifically, rather
	 * than incidentally passing via the sibling "wp_sitemaps_get_server undefined" guard.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_disable_removed_sitemaps_returns_early_when_sitemap_is_index() {
		require_once __DIR__ . '/Support/fixtures/class-wp-sitemaps-stub.php';

		function wp_sitemaps_get_server() {
			return new WP_Sitemaps( new WP_Sitemaps_Registry( array( 'posts' => new stdClass() ) ) );
		}

		WP_Mock::userFunction( 'get_query_var' )->with( 'sitemap', '' )->andReturn( 'index' );
		$this->assert_no_404_side_effects();

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertNull( $public->disable_removed_sitemaps() );
	}

	/**
	 * wp_sitemaps_get_server() is a WordPress core function this test environment never
	 * defines, so function_exists() naturally reports false here.
	 */
	public function test_disable_removed_sitemaps_returns_early_when_wp_sitemaps_get_server_undefined() {
		WP_Mock::userFunction( 'get_query_var' )->with( 'sitemap', '' )->andReturn( 'posts' );
		$this->stub_filter_strict( 'dwpb_disable_removed_sitemaps', true, true );
		$this->assert_no_404_side_effects();

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertNull( $public->disable_removed_sitemaps() );
	}

	/**
	 * Isolated: declares the global wp_sitemaps_get_server() function, which cannot be
	 * undeclared afterward and would otherwise make function_exists() report true for every
	 * later test, including the "undefined" case above.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_disable_removed_sitemaps_returns_early_when_server_is_not_a_wp_sitemaps_instance() {
		function wp_sitemaps_get_server() {
			return new stdClass();
		}

		WP_Mock::userFunction( 'get_query_var' )->with( 'sitemap', '' )->andReturn( 'users' );
		$this->stub_filter_strict( 'dwpb_disable_removed_sitemaps', true, true );
		$this->assert_no_404_side_effects();

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertNull( $public->disable_removed_sitemaps() );
	}

	/**
	 * Isolated: same permanence concern as above, plus requires the WP_Sitemaps/
	 * WP_Sitemaps_Registry fixture, which -- like the WC() precedent in IntegrationsTest --
	 * can't be declared inline in this method (PHP disallows nesting a class declaration
	 * inside another class's method body) and so lives in its own file.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_disable_removed_sitemaps_returns_early_when_provider_still_registered() {
		require_once __DIR__ . '/Support/fixtures/class-wp-sitemaps-stub.php';

		function wp_sitemaps_get_server() {
			return new WP_Sitemaps(
				new WP_Sitemaps_Registry(
					array(
						'posts' => new stdClass(),
						'users' => new stdClass(),
					)
				)
			);
		}

		WP_Mock::userFunction( 'get_query_var' )->with( 'sitemap', '' )->andReturn( 'users' );
		$this->stub_filter_strict( 'dwpb_disable_removed_sitemaps', true, true );
		$this->assert_no_404_side_effects();

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertNull( $public->disable_removed_sitemaps() );
	}

	/**
	 * Isolated: see the two notes above.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_disable_removed_sitemaps_404s_when_provider_was_removed() {
		require_once __DIR__ . '/Support/fixtures/class-wp-sitemaps-stub.php';

		function wp_sitemaps_get_server() {
			return new WP_Sitemaps( new WP_Sitemaps_Registry( array( 'posts' => new stdClass() ) ) );
		}

		WP_Mock::userFunction( 'get_query_var' )->with( 'sitemap', '' )->andReturn( 'users' );
		$this->stub_filter_strict( 'dwpb_disable_removed_sitemaps', true, true );

		global $wp_query;
		$wp_query = Mockery::mock( 'Disable_Blog_Public_Query_Double' );
		$wp_query->shouldReceive( 'set_404' )->once();

		WP_Mock::userFunction( 'status_header' )
			->once()
			->with(
				Mockery::on(
					function ( $code ) {
						return 404 === $code;
					}
				)
			);
		WP_Mock::userFunction( 'nocache_headers' )->once();

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertNull( $public->disable_removed_sitemaps() );
	}

	/**
	 * Isolated: see the two notes above. Proves the dwpb_disable_removed_sitemaps filter
	 * actually changes the outcome, using the exact same "removed provider" setup as the 404
	 * test above -- the only difference is the filter override.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_disable_removed_sitemaps_dwpb_disable_removed_sitemaps_filter_false_prevents_404() {
		require_once __DIR__ . '/Support/fixtures/class-wp-sitemaps-stub.php';

		function wp_sitemaps_get_server() {
			return new WP_Sitemaps( new WP_Sitemaps_Registry( array( 'posts' => new stdClass() ) ) );
		}

		WP_Mock::userFunction( 'get_query_var' )->with( 'sitemap', '' )->andReturn( 'users' );
		$this->stub_filter_strict( 'dwpb_disable_removed_sitemaps', true, false );
		$this->assert_no_404_side_effects();

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertNull( $public->disable_removed_sitemaps() );
	}
}
