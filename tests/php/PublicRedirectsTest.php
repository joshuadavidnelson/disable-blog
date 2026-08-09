<?php
/**
 * Tests for Disable_Blog_Public::redirect_public_pages(), its private archive-detection
 * helpers, and the pre_get_posts query-modification methods.
 *
 * @package DisableBlog
 */

// Not required by tests/php/bootstrap.php on purpose (see ConstructorInjectionTest.php's note;
// LoaderTest's autoloader test needs Disable_Blog_Functions to remain undefined until its own
// isolated process). require_once is safe regardless of which test file happens to load first.
require_once __DIR__ . '/../../includes/class-disable-blog-functions.php';
require_once __DIR__ . '/Support/fixtures/class-public-functions-double.php';

/**
 * @covers Disable_Blog_Public::redirect_public_pages
 * @covers Disable_Blog_Public::is_category_archive_request
 * @covers Disable_Blog_Public::is_tag_archive_request
 * @covers Disable_Blog_Public::modify_query
 * @covers Disable_Blog_Public::set_post_types_in_query
 */
class PublicRedirectsTest extends TestCase {

	/**
	 * The global $post as it stood before the test, so it can be restored in tear_down().
	 *
	 * @var mixed
	 */
	private $original_post;

	protected function set_up() {
		parent::set_up();

		global $post;
		$this->original_post = $post ?? null;
		$post                = null;
	}

	protected function tear_down() {
		global $post;
		$post = $this->original_post;

		parent::tear_down();
	}

	/**
	 * Invokes a private instance method via Reflection.
	 *
	 * @param Disable_Blog_Public $public Instance to invoke on.
	 * @param string              $method Method name.
	 * @param array               $args   Positional arguments.
	 * @return mixed
	 */
	private function invoke_private( Disable_Blog_Public $public, $method, array $args = array() ) {
		$reflection = new ReflectionMethod( $public, $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( $public, $args );
	}

	/**
	 * redirect_public_pages()
	 */

	/**
	 * Stubs get_query_var( 'sitemap'/'sitemap-stylesheet' ), is_admin() and
	 * get_option( 'page_on_front' )/get_permalink() so the function's four early-return
	 * guards all pass and execution reaches the $public_redirects map.
	 *
	 * @param int    $page_on_front_id The page_on_front option value.
	 * @param string $homepage_url     The url get_permalink() should return for it.
	 * @return void
	 */
	private function stub_guards_pass( $page_on_front_id = 5, $homepage_url = 'https://example.test/' ) {
		WP_Mock::userFunction( 'get_query_var' )->with( 'sitemap', false )->andReturn( false );
		WP_Mock::userFunction( 'get_query_var' )->with( 'sitemap-stylesheet', false )->andReturn( false );
		WP_Mock::userFunction( 'is_admin' )->andReturn( false );
		WP_Mock::userFunction( 'get_option' )->with( 'page_on_front' )->andReturn( $page_on_front_id );
		WP_Mock::userFunction( 'get_permalink' )->with( $page_on_front_id )->andReturn( $homepage_url );
	}

	/**
	 * Stubs every WordPress function the six $public_redirects array entries call while being
	 * built -- all six are evaluated unconditionally as part of constructing the array literal,
	 * regardless of which one (if any) the foreach later breaks on. Defaults make every entry
	 * false; pass $overrides to make specific ones true.
	 *
	 * @param array $overrides Values to override in the default (all-false) condition set.
	 * @return void
	 */
	private function stub_redirect_conditions( array $overrides = array() ) {
		$c = array_merge(
			array(
				'is_singular_post'        => false,
				'is_tag'                  => false,
				'is_category'             => false,
				'is_feed'                 => false,
				'queried_object'          => null,
				'query_var_tag'           => '',
				'query_var_tag_id'        => '',
				'query_var_category_name' => '',
				'query_var_cat'           => '',
				'is_home'                 => false,
				'is_date'                 => false,
				'is_author'               => false,
			),
			$overrides
		);

		WP_Mock::userFunction( 'is_singular' )->with( 'post' )->andReturn( $c['is_singular_post'] );
		WP_Mock::userFunction( 'is_tag' )->andReturn( $c['is_tag'] );
		WP_Mock::userFunction( 'is_category' )->andReturn( $c['is_category'] );
		WP_Mock::userFunction( 'is_feed' )->andReturn( $c['is_feed'] );
		WP_Mock::userFunction( 'get_queried_object' )->andReturn( $c['queried_object'] );
		WP_Mock::userFunction( 'get_query_var' )->with( 'tag' )->andReturn( $c['query_var_tag'] );
		WP_Mock::userFunction( 'get_query_var' )->with( 'tag_id' )->andReturn( $c['query_var_tag_id'] );
		WP_Mock::userFunction( 'get_query_var' )->with( 'category_name' )->andReturn( $c['query_var_category_name'] );
		WP_Mock::userFunction( 'get_query_var' )->with( 'cat' )->andReturn( $c['query_var_cat'] );
		WP_Mock::userFunction( 'is_home' )->andReturn( $c['is_home'] );
		WP_Mock::userFunction( 'is_date' )->andReturn( $c['is_date'] );
		WP_Mock::userFunction( 'is_author' )->andReturn( $c['is_author'] );
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
	 * Stubs the two filters redirect_public_pages() applies, in order, after the
	 * $public_redirects loop computes $redirect_url: dwpb_redirect_front_end (unfiltered
	 * true, so the redirect isn't skipped) and dwpb_front_end_redirect_url (passthrough),
	 * so $redirect_url reaches $functions->redirect() unmodified by either.
	 *
	 * @param string $redirect_url The url expected to reach redirect() unmodified.
	 * @return void
	 */
	private function stub_front_end_redirect_passthrough( $redirect_url ) {
		WP_Mock::onFilter( 'dwpb_redirect_front_end' )->with( true )->reply( true );
		WP_Mock::onFilter( 'dwpb_front_end_redirect_url' )->with( $redirect_url )->reply( $redirect_url );
	}

	/**
	 * is_admin() must short-circuit before any other guard, so nothing else can even be
	 * stubbed for this test -- an unexpected call to an un-mocked WP function fatals.
	 */
	public function test_redirect_public_pages_returns_early_when_is_admin() {
		WP_Mock::userFunction( 'get_query_var' )->with( 'sitemap', false )->andReturn( false );
		WP_Mock::userFunction( 'get_query_var' )->with( 'sitemap-stylesheet', false )->andReturn( false );
		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( true );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->redirect_public_pages();

		$this->assertSame( array(), $functions->redirect_calls );
	}

	public function test_redirect_public_pages_returns_early_when_no_page_on_front() {
		WP_Mock::userFunction( 'get_query_var' )->with( 'sitemap', false )->andReturn( false );
		WP_Mock::userFunction( 'get_query_var' )->with( 'sitemap-stylesheet', false )->andReturn( false );
		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( false );
		WP_Mock::userFunction( 'get_option' )->with( 'page_on_front' )->andReturn( 0 );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->redirect_public_pages();

		$this->assertSame( array(), $functions->redirect_calls );
	}

	/**
	 * The guard is a single `||` chain evaluated left to right, so is_admin() and
	 * get_option( 'page_on_front' ) both run before the sitemap check is ever reached;
	 * both must resolve false/truthy here so the early return is actually caused by the
	 * sitemap query var, not by one of the earlier conditions.
	 */
	public function test_redirect_public_pages_returns_early_when_sitemap_query_var_present() {
		WP_Mock::userFunction( 'get_query_var' )->with( 'sitemap', false )->andReturn( 'posts' );
		WP_Mock::userFunction( 'get_query_var' )->with( 'sitemap-stylesheet', false )->andReturn( false );
		WP_Mock::userFunction( 'is_admin' )->andReturn( false );
		WP_Mock::userFunction( 'get_option' )->with( 'page_on_front' )->andReturn( 5 );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->redirect_public_pages();

		$this->assertSame( array(), $functions->redirect_calls );
	}

	/**
	 * Same guard-ordering note as the sitemap test above: is_admin() and
	 * get_option( 'page_on_front' ) both run first, so both must resolve false/truthy
	 * for the early return to be caused by the sitemap-stylesheet check specifically.
	 */
	public function test_redirect_public_pages_returns_early_when_sitemap_stylesheet_query_var_present() {
		WP_Mock::userFunction( 'get_query_var' )->with( 'sitemap', false )->andReturn( false );
		WP_Mock::userFunction( 'get_query_var' )->with( 'sitemap-stylesheet', false )->andReturn( 'xsl' );
		WP_Mock::userFunction( 'is_admin' )->andReturn( false );
		WP_Mock::userFunction( 'get_option' )->with( 'page_on_front' )->andReturn( 5 );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->redirect_public_pages();

		$this->assertSame( array(), $functions->redirect_calls );
	}

	/**
	 * 'post' row: singular post, default (unfiltered) redirect url.
	 */
	public function test_redirect_public_pages_redirects_singular_post_with_default_url() {
		global $post;
		$post = new WP_Post();

		$this->stub_guards_pass();
		$this->stub_redirect_conditions( array( 'is_singular_post' => true ) );

		WP_Mock::onFilter( 'dwpb_redirect_post' )->with( 'https://example.test/' )->reply( 'https://example.test/' );
		$this->stub_front_end_redirect_passthrough( 'https://example.test/' );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->redirect_public_pages();

		$this->assertSame( array( 'https://example.test/' ), $functions->redirect_calls );
	}

	/**
	 * The dwpb_redirect_post filter must be able to override the url that reaches redirect().
	 */
	public function test_redirect_public_pages_dwpb_redirect_post_filter_overrides_url() {
		global $post;
		$post = new WP_Post();

		$this->stub_guards_pass();
		$this->stub_redirect_conditions( array( 'is_singular_post' => true ) );

		WP_Mock::onFilter( 'dwpb_redirect_post' )->with( 'https://example.test/' )->reply( 'https://example.test/custom-post/' );
		$this->stub_front_end_redirect_passthrough( 'https://example.test/custom-post/' );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->redirect_public_pages();

		$this->assertSame( array( 'https://example.test/custom-post/' ), $functions->redirect_calls );
	}

	/**
	 * The 'post' row requires BOTH is_singular( 'post' ) AND a real $post global -- proven
	 * independently of is_singular() by leaving $post null (its set_up() default) while
	 * is_singular( 'post' ) is stubbed true.
	 */
	public function test_redirect_public_pages_no_post_redirect_when_post_global_is_not_a_wp_post_instance() {
		global $post;
		$post = null;

		$this->stub_guards_pass();
		$this->stub_redirect_conditions( array( 'is_singular_post' => true ) );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->redirect_public_pages();

		$this->assertSame( array(), $functions->redirect_calls );
	}

	/**
	 * 'post_tag_archive' row: a tag archive with no other post type using post_tag.
	 */
	public function test_redirect_public_pages_redirects_tag_archive_when_no_other_post_types_use_post_tag() {
		$this->stub_guards_pass();
		$this->stub_redirect_conditions( array( 'is_tag' => true ) );
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'post_tag', false );

		WP_Mock::onFilter( 'dwpb_redirect_post_tag_archive' )->with( 'https://example.test/' )->reply( 'https://example.test/' );
		$this->stub_front_end_redirect_passthrough( 'https://example.test/' );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->redirect_public_pages();

		$this->assertSame( array( 'https://example.test/' ), $functions->redirect_calls );
	}

	public function test_redirect_public_pages_dwpb_redirect_post_tag_archive_filter_overrides_url() {
		$this->stub_guards_pass();
		$this->stub_redirect_conditions( array( 'is_tag' => true ) );
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'post_tag', false );

		WP_Mock::onFilter( 'dwpb_redirect_post_tag_archive' )->with( 'https://example.test/' )->reply( 'https://example.test/custom-tag/' );
		$this->stub_front_end_redirect_passthrough( 'https://example.test/custom-tag/' );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->redirect_public_pages();

		$this->assertSame( array( 'https://example.test/custom-tag/' ), $functions->redirect_calls );
	}

	/**
	 * When another post type also uses post_tag, the tag archive must NOT redirect.
	 */
	public function test_redirect_public_pages_no_tag_redirect_when_other_post_types_use_post_tag() {
		$this->stub_guards_pass();
		$this->stub_redirect_conditions( array( 'is_tag' => true ) );
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'post_tag', array( 'book' ) );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->redirect_public_pages();

		$this->assertSame( array(), $functions->redirect_calls );
	}

	/**
	 * 'category_archive' row: a category archive with no other post type using category.
	 */
	public function test_redirect_public_pages_redirects_category_archive_when_no_other_post_types_use_category() {
		$this->stub_guards_pass();
		$this->stub_redirect_conditions( array( 'is_category' => true ) );
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', false );

		WP_Mock::onFilter( 'dwpb_redirect_category_archive' )->with( 'https://example.test/' )->reply( 'https://example.test/' );
		$this->stub_front_end_redirect_passthrough( 'https://example.test/' );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->redirect_public_pages();

		$this->assertSame( array( 'https://example.test/' ), $functions->redirect_calls );
	}

	public function test_redirect_public_pages_dwpb_redirect_category_archive_filter_overrides_url() {
		$this->stub_guards_pass();
		$this->stub_redirect_conditions( array( 'is_category' => true ) );
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', false );

		WP_Mock::onFilter( 'dwpb_redirect_category_archive' )->with( 'https://example.test/' )->reply( 'https://example.test/custom-category/' );
		$this->stub_front_end_redirect_passthrough( 'https://example.test/custom-category/' );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->redirect_public_pages();

		$this->assertSame( array( 'https://example.test/custom-category/' ), $functions->redirect_calls );
	}

	/**
	 * When another post type also uses category, the category archive must NOT redirect.
	 */
	public function test_redirect_public_pages_no_category_redirect_when_other_post_types_use_category() {
		$this->stub_guards_pass();
		$this->stub_redirect_conditions( array( 'is_category' => true ) );
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', array( 'page' ) );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->redirect_public_pages();

		$this->assertSame( array(), $functions->redirect_calls );
	}

	/**
	 * 'blog_page' row.
	 */
	public function test_redirect_public_pages_redirects_blog_home_with_default_url() {
		$this->stub_guards_pass();
		$this->stub_redirect_conditions( array( 'is_home' => true ) );

		WP_Mock::onFilter( 'dwpb_redirect_blog_page' )->with( 'https://example.test/' )->reply( 'https://example.test/' );
		$this->stub_front_end_redirect_passthrough( 'https://example.test/' );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->redirect_public_pages();

		$this->assertSame( array( 'https://example.test/' ), $functions->redirect_calls );
	}

	public function test_redirect_public_pages_dwpb_redirect_blog_page_filter_overrides_url() {
		$this->stub_guards_pass();
		$this->stub_redirect_conditions( array( 'is_home' => true ) );

		WP_Mock::onFilter( 'dwpb_redirect_blog_page' )->with( 'https://example.test/' )->reply( 'https://example.test/custom-blog/' );
		$this->stub_front_end_redirect_passthrough( 'https://example.test/custom-blog/' );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->redirect_public_pages();

		$this->assertSame( array( 'https://example.test/custom-blog/' ), $functions->redirect_calls );
	}

	/**
	 * 'date_archive' row.
	 */
	public function test_redirect_public_pages_redirects_date_archive_with_default_url() {
		$this->stub_guards_pass();
		$this->stub_redirect_conditions( array( 'is_date' => true ) );

		WP_Mock::onFilter( 'dwpb_redirect_date_archive' )->with( 'https://example.test/' )->reply( 'https://example.test/' );
		$this->stub_front_end_redirect_passthrough( 'https://example.test/' );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->redirect_public_pages();

		$this->assertSame( array( 'https://example.test/' ), $functions->redirect_calls );
	}

	public function test_redirect_public_pages_dwpb_redirect_date_archive_filter_overrides_url() {
		$this->stub_guards_pass();
		$this->stub_redirect_conditions( array( 'is_date' => true ) );

		WP_Mock::onFilter( 'dwpb_redirect_date_archive' )->with( 'https://example.test/' )->reply( 'https://example.test/custom-date/' );
		$this->stub_front_end_redirect_passthrough( 'https://example.test/custom-date/' );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->redirect_public_pages();

		$this->assertSame( array( 'https://example.test/custom-date/' ), $functions->redirect_calls );
	}

	/**
	 * 'author_archive' row: gated by $this->functions->disable_author_archives(), not a plain
	 * WordPress conditional.
	 */
	public function test_redirect_public_pages_redirects_author_archive_when_disable_author_archives_true() {
		$this->stub_guards_pass();
		$this->stub_redirect_conditions( array( 'is_author' => true ) );

		WP_Mock::onFilter( 'dwpb_redirect_author_archive' )->with( 'https://example.test/' )->reply( 'https://example.test/' );
		$this->stub_front_end_redirect_passthrough( 'https://example.test/' );

		$functions                                   = new Disable_Blog_Public_Functions_Double();
		$functions->disable_author_archives_return = true;
		$public                                       = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->redirect_public_pages();

		$this->assertSame( array( 'https://example.test/' ), $functions->redirect_calls );
	}

	public function test_redirect_public_pages_dwpb_redirect_author_archive_filter_overrides_url() {
		$this->stub_guards_pass();
		$this->stub_redirect_conditions( array( 'is_author' => true ) );

		WP_Mock::onFilter( 'dwpb_redirect_author_archive' )->with( 'https://example.test/' )->reply( 'https://example.test/custom-author/' );
		$this->stub_front_end_redirect_passthrough( 'https://example.test/custom-author/' );

		$functions                                   = new Disable_Blog_Public_Functions_Double();
		$functions->disable_author_archives_return = true;
		$public                                       = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->redirect_public_pages();

		$this->assertSame( array( 'https://example.test/custom-author/' ), $functions->redirect_calls );
	}

	public function test_redirect_public_pages_no_author_redirect_when_disable_author_archives_false() {
		$this->stub_guards_pass();
		$this->stub_redirect_conditions( array( 'is_author' => true ) );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->redirect_public_pages();

		$this->assertSame( array(), $functions->redirect_calls );
	}

	/**
	 * Break semantics: when two rows are simultaneously true, only the FIRST one declared in
	 * $public_redirects (here, 'blog_page' before 'date_archive') is used -- proven by giving
	 * both rows distinct filtered urls and asserting only the earlier one's reaches redirect().
	 */
	public function test_redirect_public_pages_only_first_matching_row_wins() {
		$this->stub_guards_pass();
		$this->stub_redirect_conditions(
			array(
				'is_home' => true,
				'is_date' => true,
			)
		);

		WP_Mock::onFilter( 'dwpb_redirect_blog_page' )->with( 'https://example.test/' )->reply( 'https://example.test/blog-page-wins/' );
		// Deliberately not stubbing dwpb_redirect_date_archive: it must never fire, so if it did
		// its default (unfiltered) passthrough would leak the raw homepage url through instead.
		$this->stub_front_end_redirect_passthrough( 'https://example.test/blog-page-wins/' );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->redirect_public_pages();

		$this->assertSame( array( 'https://example.test/blog-page-wins/' ), $functions->redirect_calls );
	}

	/**
	 * dwpb_redirect_front_end toggle: false disables the redirect entirely, even though a row
	 * matched and a redirect url was computed.
	 */
	public function test_redirect_public_pages_dwpb_redirect_front_end_false_skips_redirect_entirely() {
		$this->stub_guards_pass();
		$this->stub_redirect_conditions( array( 'is_home' => true ) );

		WP_Mock::onFilter( 'dwpb_redirect_blog_page' )->with( 'https://example.test/' )->reply( 'https://example.test/' );
		WP_Mock::onFilter( 'dwpb_redirect_front_end' )->with( true )->reply( false );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->redirect_public_pages();

		$this->assertSame( array(), $functions->redirect_calls );
	}

	/**
	 * dwpb_front_end_redirect_url is a global override applied after the per-page filter and
	 * after the front-end toggle, so it must win over the per-page filtered value.
	 */
	public function test_redirect_public_pages_dwpb_front_end_redirect_url_overrides_final_url() {
		$this->stub_guards_pass();
		$this->stub_redirect_conditions( array( 'is_home' => true ) );

		WP_Mock::onFilter( 'dwpb_redirect_blog_page' )->with( 'https://example.test/' )->reply( 'https://example.test/' );
		WP_Mock::onFilter( 'dwpb_redirect_front_end' )->with( true )->reply( true );
		WP_Mock::onFilter( 'dwpb_front_end_redirect_url' )->with( 'https://example.test/' )->reply( 'https://example.test/global-override/' );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->redirect_public_pages();

		$this->assertSame( array( 'https://example.test/global-override/' ), $functions->redirect_calls );
	}

	/**
	 * is_category_archive_request() (private)
	 */

	public function test_is_category_archive_request_true_when_is_category() {
		WP_Mock::userFunction( 'is_category' )->once()->andReturn( true );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertTrue( $this->invoke_private( $public, 'is_category_archive_request' ) );
	}

	public function test_is_category_archive_request_false_on_feed_request() {
		WP_Mock::userFunction( 'is_category' )->once()->andReturn( false );
		WP_Mock::userFunction( 'is_feed' )->once()->andReturn( true );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertFalse( $this->invoke_private( $public, 'is_category_archive_request' ) );
	}

	public function test_is_category_archive_request_false_when_queried_object_already_resolved() {
		WP_Mock::userFunction( 'is_category' )->once()->andReturn( false );
		WP_Mock::userFunction( 'is_feed' )->once()->andReturn( false );
		WP_Mock::userFunction( 'get_queried_object' )->once()->andReturn( new stdClass() );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertFalse( $this->invoke_private( $public, 'is_category_archive_request' ) );
	}

	public function test_is_category_archive_request_true_from_raw_category_name_query_var() {
		WP_Mock::userFunction( 'is_category' )->once()->andReturn( false );
		WP_Mock::userFunction( 'is_feed' )->once()->andReturn( false );
		WP_Mock::userFunction( 'get_queried_object' )->once()->andReturn( null );
		WP_Mock::userFunction( 'get_query_var' )->with( 'category_name' )->once()->andReturn( 'news' );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertTrue( $this->invoke_private( $public, 'is_category_archive_request' ) );
	}

	public function test_is_category_archive_request_true_from_raw_cat_query_var() {
		WP_Mock::userFunction( 'is_category' )->once()->andReturn( false );
		WP_Mock::userFunction( 'is_feed' )->once()->andReturn( false );
		WP_Mock::userFunction( 'get_queried_object' )->once()->andReturn( null );
		WP_Mock::userFunction( 'get_query_var' )->with( 'category_name' )->once()->andReturn( '' );
		WP_Mock::userFunction( 'get_query_var' )->with( 'cat' )->once()->andReturn( '4' );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertTrue( $this->invoke_private( $public, 'is_category_archive_request' ) );
	}

	public function test_is_category_archive_request_false_when_no_category_query_vars_present() {
		WP_Mock::userFunction( 'is_category' )->once()->andReturn( false );
		WP_Mock::userFunction( 'is_feed' )->once()->andReturn( false );
		WP_Mock::userFunction( 'get_queried_object' )->once()->andReturn( null );
		WP_Mock::userFunction( 'get_query_var' )->with( 'category_name' )->once()->andReturn( '' );
		WP_Mock::userFunction( 'get_query_var' )->with( 'cat' )->once()->andReturn( '' );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertFalse( $this->invoke_private( $public, 'is_category_archive_request' ) );
	}

	/**
	 * is_tag_archive_request() (private)
	 */

	public function test_is_tag_archive_request_true_when_is_tag() {
		WP_Mock::userFunction( 'is_tag' )->once()->andReturn( true );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertTrue( $this->invoke_private( $public, 'is_tag_archive_request' ) );
	}

	public function test_is_tag_archive_request_false_on_feed_request() {
		WP_Mock::userFunction( 'is_tag' )->once()->andReturn( false );
		WP_Mock::userFunction( 'is_feed' )->once()->andReturn( true );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertFalse( $this->invoke_private( $public, 'is_tag_archive_request' ) );
	}

	public function test_is_tag_archive_request_false_when_queried_object_already_resolved() {
		WP_Mock::userFunction( 'is_tag' )->once()->andReturn( false );
		WP_Mock::userFunction( 'is_feed' )->once()->andReturn( false );
		WP_Mock::userFunction( 'get_queried_object' )->once()->andReturn( new stdClass() );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertFalse( $this->invoke_private( $public, 'is_tag_archive_request' ) );
	}

	public function test_is_tag_archive_request_true_from_raw_tag_query_var() {
		WP_Mock::userFunction( 'is_tag' )->once()->andReturn( false );
		WP_Mock::userFunction( 'is_feed' )->once()->andReturn( false );
		WP_Mock::userFunction( 'get_queried_object' )->once()->andReturn( null );
		WP_Mock::userFunction( 'get_query_var' )->with( 'tag' )->once()->andReturn( 'news' );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertTrue( $this->invoke_private( $public, 'is_tag_archive_request' ) );
	}

	public function test_is_tag_archive_request_true_from_raw_tag_id_query_var() {
		WP_Mock::userFunction( 'is_tag' )->once()->andReturn( false );
		WP_Mock::userFunction( 'is_feed' )->once()->andReturn( false );
		WP_Mock::userFunction( 'get_queried_object' )->once()->andReturn( null );
		WP_Mock::userFunction( 'get_query_var' )->with( 'tag' )->once()->andReturn( '' );
		WP_Mock::userFunction( 'get_query_var' )->with( 'tag_id' )->once()->andReturn( '7' );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertTrue( $this->invoke_private( $public, 'is_tag_archive_request' ) );
	}

	public function test_is_tag_archive_request_false_when_no_tag_query_vars_present() {
		WP_Mock::userFunction( 'is_tag' )->once()->andReturn( false );
		WP_Mock::userFunction( 'is_feed' )->once()->andReturn( false );
		WP_Mock::userFunction( 'get_queried_object' )->once()->andReturn( null );
		WP_Mock::userFunction( 'get_query_var' )->with( 'tag' )->once()->andReturn( '' );
		WP_Mock::userFunction( 'get_query_var' )->with( 'tag_id' )->once()->andReturn( '' );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertFalse( $this->invoke_private( $public, 'is_tag_archive_request' ) );
	}

	/**
	 * modify_query()
	 */

	public function test_modify_query_returns_early_when_is_admin() {
		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( true );

		$query  = Mockery::mock( 'Disable_Blog_Public_Query_Double' );
		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertNull( $public->modify_query( $query ) );
	}

	public function test_modify_query_returns_early_when_not_main_query() {
		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( false );

		$query = Mockery::mock( 'Disable_Blog_Public_Query_Double' );
		$query->shouldReceive( 'is_main_query' )->once()->andReturn( false );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertNull( $public->modify_query( $query ) );
	}

	public function test_modify_query_sets_tag_post_types_when_tag_archive_and_other_post_types_use_post_tag() {
		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( false );
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'post_tag', array( 'book' ) );
		$this->stub_post_types_with_tax_result( 'category', false );

		$query = Mockery::mock( 'Disable_Blog_Public_Query_Double' );
		$query->shouldReceive( 'is_main_query' )->once()->andReturn( true );
		$query->shouldReceive( 'is_tag' )->once()->andReturn( true );
		$query->shouldReceive( 'set' )->once()->with( 'post_type', array( 'book' ) );

		WP_Mock::onFilter( 'dwpb_tag_post_types' )->with( array( 'book' ), $query )->reply( array( 'book' ) );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$this->assertNull( $public->modify_query( $query ) );
	}

	public function test_modify_query_tag_branch_dwpb_tag_post_types_filter_overrides_post_types() {
		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( false );
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'post_tag', array( 'book' ) );
		$this->stub_post_types_with_tax_result( 'category', false );

		$query = Mockery::mock( 'Disable_Blog_Public_Query_Double' );
		$query->shouldReceive( 'is_main_query' )->once()->andReturn( true );
		$query->shouldReceive( 'is_tag' )->once()->andReturn( true );
		$query->shouldReceive( 'set' )->once()->with( 'post_type', array( 'custom_cpt' ) );

		WP_Mock::onFilter( 'dwpb_tag_post_types' )->with( array( 'book' ), $query )->reply( array( 'custom_cpt' ) );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$this->assertNull( $public->modify_query( $query ) );
	}

	/**
	 * The tag branch requires BOTH is_tag() AND a non-empty $tag_post_types -- proven
	 * independently of is_tag() by holding it true while forcing $tag_post_types empty, so a
	 * relaxed `||` (which would enter the branch and wrongly strip 'post' from its own tag
	 * archive) is distinguishable from the real `&&`. Mirrors
	 * test_modify_query_author_branch_skipped_when_author_post_types_empty() below.
	 */
	public function test_modify_query_tag_branch_skipped_when_tag_post_types_empty() {
		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( false );
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'post_tag', false );
		$this->stub_post_types_with_tax_result( 'category', false );

		$query = Mockery::mock( 'Disable_Blog_Public_Query_Double' );
		$query->shouldReceive( 'is_main_query' )->once()->andReturn( true );
		$query->shouldReceive( 'is_tag' )->once()->andReturn( true );
		$query->shouldReceive( 'is_category' )->once()->andReturn( false );
		$query->shouldReceive( 'is_author' )->once()->andReturn( false );
		$query->shouldNotReceive( 'set' );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$this->assertNull( $public->modify_query( $query ) );
	}

	public function test_modify_query_sets_category_post_types_when_category_archive_and_other_post_types_use_category() {
		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( false );
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'post_tag', false );
		$this->stub_post_types_with_tax_result( 'category', array( 'page' ) );

		$query = Mockery::mock( 'Disable_Blog_Public_Query_Double' );
		$query->shouldReceive( 'is_main_query' )->once()->andReturn( true );
		$query->shouldReceive( 'is_tag' )->once()->andReturn( false );
		$query->shouldReceive( 'is_category' )->once()->andReturn( true );
		$query->shouldReceive( 'set' )->once()->with( 'post_type', array( 'page' ) );

		WP_Mock::onFilter( 'dwpb_category_post_types' )->with( array( 'page' ), $query )->reply( array( 'page' ) );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$this->assertNull( $public->modify_query( $query ) );
	}

	public function test_modify_query_category_branch_dwpb_category_post_types_filter_overrides_post_types() {
		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( false );
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'post_tag', false );
		$this->stub_post_types_with_tax_result( 'category', array( 'page' ) );

		$query = Mockery::mock( 'Disable_Blog_Public_Query_Double' );
		$query->shouldReceive( 'is_main_query' )->once()->andReturn( true );
		$query->shouldReceive( 'is_tag' )->once()->andReturn( false );
		$query->shouldReceive( 'is_category' )->once()->andReturn( true );
		$query->shouldReceive( 'set' )->once()->with( 'post_type', array( 'custom_cpt' ) );

		WP_Mock::onFilter( 'dwpb_category_post_types' )->with( array( 'page' ), $query )->reply( array( 'custom_cpt' ) );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$this->assertNull( $public->modify_query( $query ) );
	}

	/**
	 * The category branch requires BOTH is_category() AND a non-empty $category_post_types --
	 * proven independently of is_category() by holding it true while forcing
	 * $category_post_types empty, so a relaxed `||` (which would enter the branch and wrongly
	 * strip 'post' from its own category archive) is distinguishable from the real `&&`.
	 * Mirrors test_modify_query_author_branch_skipped_when_author_post_types_empty() below.
	 */
	public function test_modify_query_category_branch_skipped_when_category_post_types_empty() {
		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( false );
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'post_tag', false );
		$this->stub_post_types_with_tax_result( 'category', false );

		$query = Mockery::mock( 'Disable_Blog_Public_Query_Double' );
		$query->shouldReceive( 'is_main_query' )->once()->andReturn( true );
		$query->shouldReceive( 'is_tag' )->once()->andReturn( false );
		$query->shouldReceive( 'is_category' )->once()->andReturn( true );
		$query->shouldReceive( 'is_author' )->once()->andReturn( false );
		$query->shouldNotReceive( 'set' );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$this->assertNull( $public->modify_query( $query ) );
	}

	public function test_modify_query_sets_author_post_types_when_author_archive_and_functions_reports_post_types() {
		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( false );
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'post_tag', false );
		$this->stub_post_types_with_tax_result( 'category', false );

		$query = Mockery::mock( 'Disable_Blog_Public_Query_Double' );
		$query->shouldReceive( 'is_main_query' )->once()->andReturn( true );
		$query->shouldReceive( 'is_tag' )->once()->andReturn( false );
		$query->shouldReceive( 'is_category' )->once()->andReturn( false );
		$query->shouldReceive( 'is_author' )->once()->andReturn( true );
		$query->shouldReceive( 'set' )->once()->with( 'post_type', array( 'book' ) );

		$functions                                     = new Disable_Blog_Public_Functions_Double();
		$functions->author_archive_post_types_return = array( 'book' );
		$public                                         = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$this->assertNull( $public->modify_query( $query ) );
	}

	public function test_modify_query_does_nothing_when_no_condition_matches() {
		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( false );
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'post_tag', false );
		$this->stub_post_types_with_tax_result( 'category', false );

		$query = Mockery::mock( 'Disable_Blog_Public_Query_Double' );
		$query->shouldReceive( 'is_main_query' )->once()->andReturn( true );
		$query->shouldReceive( 'is_tag' )->once()->andReturn( false );
		$query->shouldReceive( 'is_category' )->once()->andReturn( false );
		$query->shouldReceive( 'is_author' )->once()->andReturn( false );
		$query->shouldNotReceive( 'set' );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$this->assertNull( $public->modify_query( $query ) );
	}

	public function test_modify_query_author_branch_skipped_when_author_post_types_empty() {
		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( false );
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'post_tag', false );
		$this->stub_post_types_with_tax_result( 'category', false );

		$query = Mockery::mock( 'Disable_Blog_Public_Query_Double' );
		$query->shouldReceive( 'is_main_query' )->once()->andReturn( true );
		$query->shouldReceive( 'is_tag' )->once()->andReturn( false );
		$query->shouldReceive( 'is_category' )->once()->andReturn( false );
		$query->shouldReceive( 'is_author' )->once()->andReturn( true );
		$query->shouldNotReceive( 'set' );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$this->assertNull( $public->modify_query( $query ) );
	}

	/**
	 * set_post_types_in_query()
	 */

	public function test_set_post_types_in_query_without_filter_sets_query_and_returns_true() {
		$query = Mockery::mock( 'Disable_Blog_Public_Query_Double' );
		$query->shouldReceive( 'set' )->once()->with( 'post_type', array( 'book' ) );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertTrue( $public->set_post_types_in_query( $query, array( 'book' ) ) );
	}

	public function test_set_post_types_in_query_with_filter_applies_and_can_override_post_types() {
		$query = Mockery::mock( 'Disable_Blog_Public_Query_Double' );
		$query->shouldReceive( 'set' )->once()->with( 'post_type', array( 'overridden' ) );

		WP_Mock::onFilter( 'my_filter' )->with( array( 'book' ), $query )->reply( array( 'overridden' ) );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertTrue( $public->set_post_types_in_query( $query, array( 'book' ), 'my_filter' ) );
	}

	public function test_set_post_types_in_query_returns_false_when_resulting_post_types_empty() {
		$query = Mockery::mock( 'Disable_Blog_Public_Query_Double' );
		$query->shouldNotReceive( 'set' );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertFalse( $public->set_post_types_in_query( $query, array() ) );
	}

	public function test_set_post_types_in_query_returns_false_when_query_object_lacks_set_method() {
		$query = new stdClass();

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertFalse( $public->set_post_types_in_query( $query, array( 'book' ) ) );
	}

	/**
	 * A filter is free to reply with a non-array value; the is_array() guard must stop that
	 * from ever reaching $query->set(), which requires an array for its 'post_type' value.
	 */
	public function test_set_post_types_in_query_returns_false_when_filter_replies_with_non_array() {
		$query = Mockery::mock( 'Disable_Blog_Public_Query_Double' );
		$query->shouldNotReceive( 'set' );

		WP_Mock::onFilter( 'my_filter' )->with( array( 'book' ), $query )->reply( 'oops' );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertFalse( $public->set_post_types_in_query( $query, array( 'book' ), 'my_filter' ) );
	}
}
