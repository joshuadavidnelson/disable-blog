<?php
/**
 * Tests for Disable_Blog_Public's feed surface: disable_feed(), its private
 * is_post_feed_request() helper, feed_links_show_posts_feed(),
 * feed_links_show_comments_feed() and header_feeds().
 *
 * @package DisableBlog
 */

// Not required by tests/php/bootstrap.php on purpose (see ConstructorInjectionTest.php's note;
// LoaderTest's autoloader test needs Disable_Blog_Functions to remain undefined until its own
// isolated process). require_once is safe regardless of which test file happens to load first.
require_once __DIR__ . '/../../includes/class-disable-blog-functions.php';
require_once __DIR__ . '/Support/fixtures/class-public-functions-double.php';

/**
 * @covers Disable_Blog_Public::disable_feed
 * @covers Disable_Blog_Public::is_post_feed_request
 * @covers Disable_Blog_Public::feed_links_show_posts_feed
 * @covers Disable_Blog_Public::feed_links_show_comments_feed
 * @covers Disable_Blog_Public::header_feeds
 */
class PublicFeedsTest extends TestCase {

	/**
	 * The global $post as it stood before the test, so it can be restored in tear_down().
	 *
	 * @var mixed
	 */
	private $original_post;

	/**
	 * The global $wp as it stood before the test, so it can be restored in tear_down().
	 *
	 * @var mixed
	 */
	private $original_wp;

	protected function set_up() {
		parent::set_up();

		global $post, $wp;
		$this->original_post = $post ?? null;
		$this->original_wp   = $wp ?? null;
		$post                = null;
	}

	protected function tear_down() {
		global $post, $wp;
		$post = $this->original_post;
		$wp   = $this->original_wp;

		parent::tear_down();
	}

	/**
	 * Invokes the private is_post_feed_request() method via Reflection.
	 *
	 * @param Disable_Blog_Public $public Instance to invoke on.
	 * @return bool
	 */
	private function invoke_is_post_feed_request( Disable_Blog_Public $public ) {
		$reflection = new ReflectionMethod( $public, 'is_post_feed_request' );
		$reflection->setAccessible( true );

		return $reflection->invoke( $public );
	}

	/**
	 * Forces dwpb_post_types_with_feature( $feature ) to return $return_value, via the
	 * dwpb_post_types_supporting_{$feature} filter that always applies to its computed result.
	 *
	 * @param string     $feature      The feature slug (e.g. 'comments').
	 * @param array|bool $return_value The value dwpb_post_types_with_feature() should return.
	 * @return void
	 */
	private function stub_post_types_with_feature_result( $feature, $return_value ) {
		WP_Mock::userFunction( 'esc_attr' )->with( $feature )->andReturn( $feature );
		WP_Mock::userFunction( 'wp_cache_get' )->andReturn( false );
		WP_Mock::userFunction( 'get_post_types' )->andReturn( array() );
		WP_Mock::userFunction( 'wp_cache_set' )->andReturn( null );
		WP_Mock::onFilter( "dwpb_post_types_supporting_{$feature}" )->with( false, array() )->reply( $return_value );
	}

	/**
	 * The `$allowed_html` array wp_kses() is called with in the wp_die() message branch,
	 * exactly as built in the source.
	 *
	 * @return array
	 */
	private function allowed_feed_html() {
		return array(
			'a' => array(
				'href' => array(),
				'name' => array(),
				'id'   => array(),
			),
		);
	}

	/**
	 * disable_feed()
	 */

	/**
	 * A comment feed must bail before ever touching $this->functions when another post type
	 * still supports comments.
	 */
	public function test_disable_feed_returns_early_for_comment_feed_when_other_post_types_support_comments() {
		$this->stub_post_types_with_feature_result( 'comments', array( 'book' ) );

		$functions = new Disable_Blog_Public_Functions_Double();
		$public    = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->disable_feed( true );

		$this->assertSame( array(), $functions->redirect_calls );
	}

	/**
	 * A post feed ($is_comment_feed = false) must never call dwpb_post_types_with_feature() at
	 * all -- if it did, the un-mocked WordPress functions inside it would fatal here.
	 */
	public function test_disable_feed_post_feed_never_checks_post_types_with_feature() {
		$functions                     = new Disable_Blog_Public_Functions_Double();
		$functions->disable_feeds_return = false;
		$public                         = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->disable_feed( false );

		$this->assertSame( array(), $functions->redirect_calls );
	}

	public function test_disable_feed_does_nothing_when_functions_disable_feeds_returns_false() {
		$this->stub_post_types_with_feature_result( 'comments', false );

		$functions                     = new Disable_Blog_Public_Functions_Double();
		$functions->disable_feeds_return = false;
		$public                         = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->disable_feed( true );

		$this->assertSame( array(), $functions->redirect_calls );
	}

	public function test_disable_feed_does_nothing_when_not_a_post_feed_request() {
		global $wp;
		// A singular query var makes is_post_feed_request() false.
		$wp = (object) array( 'query_vars' => array( 'p' => 5 ) );

		$functions                     = new Disable_Blog_Public_Functions_Double();
		$functions->disable_feeds_return = true;
		$public                         = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->disable_feed( false );

		$this->assertSame( array(), $functions->redirect_calls );
	}

	/**
	 * Default behaviour: redirect() is called with the unfiltered home_url(), and neither the
	 * dwpb_feed_message toggle nor wp_die() is reached.
	 */
	public function test_disable_feed_redirects_to_home_url_by_default() {
		global $wp;
		// Non-empty, no singular query var, no post_type: is_post_feed_request() defaults true.
		$wp = (object) array( 'query_vars' => array( 'feed' => 'feed' ) );

		WP_Mock::userFunction( 'home_url' )->once()->andReturn( 'https://example.test/' );
		WP_Mock::onFilter( 'dwpb_redirect_feeds' )->with( 'https://example.test/', null, false )->reply( 'https://example.test/' );
		WP_Mock::onFilter( 'dwpb_feed_message' )->with( false, null, false )->reply( false );

		$functions                     = new Disable_Blog_Public_Functions_Double();
		$functions->disable_feeds_return = true;
		$public                         = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->disable_feed( false );

		$this->assertSame( array( 'https://example.test/' ), $functions->redirect_calls );
	}

	/**
	 * The dwpb_redirect_feeds filter must be able to override the url that reaches redirect().
	 */
	public function test_disable_feed_dwpb_redirect_feeds_filter_overrides_url() {
		$this->stub_post_types_with_feature_result( 'comments', false );

		global $post, $wp;
		$post = new WP_Post();
		$wp   = (object) array( 'query_vars' => array( 'feed' => 'feed' ) );

		WP_Mock::userFunction( 'home_url' )->once()->andReturn( 'https://example.test/' );
		WP_Mock::onFilter( 'dwpb_redirect_feeds' )
			->with( 'https://example.test/', $post, true )
			->reply( 'https://example.test/custom-feed-redirect/' );
		WP_Mock::onFilter( 'dwpb_feed_message' )->with( false, $post, true )->reply( false );

		$functions                     = new Disable_Blog_Public_Functions_Double();
		$functions->disable_feeds_return = true;
		$public                         = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$public->disable_feed( true );

		$this->assertSame( array( 'https://example.test/custom-feed-redirect/' ), $functions->redirect_calls );
	}

	/**
	 * dwpb_feed_message toggled true: wp_die() is reached (via the globally-throwing wp_die()
	 * polyfill) instead of a redirect, carrying the default constructed message.
	 */
	public function test_disable_feed_dwpb_feed_message_true_reaches_wp_die_with_default_message() {
		global $wp;
		$wp = (object) array( 'query_vars' => array( 'feed' => 'feed' ) );

		WP_Mock::userFunction( 'home_url' )->once()->andReturn( 'https://example.test/' );
		WP_Mock::onFilter( 'dwpb_redirect_feeds' )->with( 'https://example.test/', null, false )->reply( 'https://example.test/' );
		WP_Mock::onFilter( 'dwpb_feed_message' )->with( false, null, false )->reply( true );
		WP_Mock::userFunction( 'esc_url_raw' )->with( 'https://example.test/' )->andReturn( 'https://example.test/' );

		$expected_message = 'No feed available, please visit our homepage:: <a href="https://example.test/">https://example.test/</a>';

		WP_Mock::onFilter( 'dwpb_feed_die_message' )->with( $expected_message )->reply( $expected_message );

		WP_Mock::userFunction( 'wp_kses' )
			->once()
			->with( $expected_message, $this->allowed_feed_html() )
			->andReturn( $expected_message );

		$functions                     = new Disable_Blog_Public_Functions_Double();
		$functions->disable_feeds_return = true;
		$public                         = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( $expected_message );

		$public->disable_feed( false );
	}

	/**
	 * The dwpb_feed_die_message filter must be able to override the message wp_die() receives.
	 */
	public function test_disable_feed_dwpb_feed_die_message_filter_overrides_message() {
		global $wp;
		$wp = (object) array( 'query_vars' => array( 'feed' => 'feed' ) );

		WP_Mock::userFunction( 'home_url' )->once()->andReturn( 'https://example.test/' );
		WP_Mock::onFilter( 'dwpb_redirect_feeds' )->with( 'https://example.test/', null, false )->reply( 'https://example.test/' );
		WP_Mock::onFilter( 'dwpb_feed_message' )->with( false, null, false )->reply( true );
		WP_Mock::userFunction( 'esc_url_raw' )->with( 'https://example.test/' )->andReturn( 'https://example.test/' );

		$default_message  = 'No feed available, please visit our homepage:: <a href="https://example.test/">https://example.test/</a>';
		$expected_message = 'Custom feed removal notice.';

		WP_Mock::onFilter( 'dwpb_feed_die_message' )->with( $default_message )->reply( $expected_message );

		WP_Mock::userFunction( 'wp_kses' )
			->once()
			->with( $expected_message, $this->allowed_feed_html() )
			->andReturn( $expected_message );

		$functions                     = new Disable_Blog_Public_Functions_Double();
		$functions->disable_feeds_return = true;
		$public                         = new Disable_Blog_Public( 'disable-blog', '0.5.6', $functions );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( $expected_message );

		$public->disable_feed( false );
	}

	/**
	 * is_post_feed_request() (private)
	 */

	public function test_is_post_feed_request_false_when_query_vars_empty() {
		global $wp;
		$wp = (object) array( 'query_vars' => array() );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertFalse( $this->invoke_is_post_feed_request( $public ) );
	}

	public function test_is_post_feed_request_false_when_query_vars_not_an_array() {
		global $wp;
		$wp = (object) array( 'query_vars' => 'not-an-array' );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertFalse( $this->invoke_is_post_feed_request( $public ) );
	}

	/**
	 * Any of the singular-object query vars present means this isn't the general 'post' feed.
	 */
	public function test_is_post_feed_request_false_when_any_singular_query_var_present() {
		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		global $wp;
		foreach ( array( 'p', 'name', 'pagename', 'page_id', 'attachment', 'attachment_id' ) as $var ) {
			$wp = (object) array( 'query_vars' => array( $var => 'some-value' ) );

			$this->assertFalse(
				$this->invoke_is_post_feed_request( $public ),
				"Expected false when the '{$var}' query var is present."
			);
		}
	}

	public function test_is_post_feed_request_true_when_post_type_query_var_absent() {
		global $wp;
		$wp = (object) array( 'query_vars' => array( 'paged' => 2 ) );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertTrue( $this->invoke_is_post_feed_request( $public ) );
	}

	public function test_is_post_feed_request_true_when_post_type_array_contains_post() {
		global $wp;
		$wp = (object) array( 'query_vars' => array( 'post_type' => array( 'post', 'page' ) ) );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertTrue( $this->invoke_is_post_feed_request( $public ) );
	}

	/**
	 * `true` is loosely == 'post' (a non-empty string is truthy) but never strictly === it, so
	 * this distinguishes the real strict in_array() check from a loose one without relying on
	 * PHP-version-sensitive numeric-string coercion.
	 */
	public function test_is_post_feed_request_false_when_post_type_array_contains_only_loosely_equal_value() {
		global $wp;
		$wp = (object) array( 'query_vars' => array( 'post_type' => array( true ) ) );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertFalse( $this->invoke_is_post_feed_request( $public ) );
	}

	public function test_is_post_feed_request_false_when_post_type_array_does_not_contain_post() {
		global $wp;
		$wp = (object) array( 'query_vars' => array( 'post_type' => array( 'page', 'book' ) ) );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertFalse( $this->invoke_is_post_feed_request( $public ) );
	}

	public function test_is_post_feed_request_true_when_post_type_string_is_post() {
		global $wp;
		$wp = (object) array( 'query_vars' => array( 'post_type' => 'post' ) );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertTrue( $this->invoke_is_post_feed_request( $public ) );
	}

	public function test_is_post_feed_request_false_when_post_type_string_is_not_post() {
		global $wp;
		$wp = (object) array( 'query_vars' => array( 'post_type' => 'page' ) );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertFalse( $this->invoke_is_post_feed_request( $public ) );
	}

	/**
	 * feed_links_show_posts_feed()
	 */

	public function test_feed_links_show_posts_feed_always_returns_false() {
		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertFalse( $public->feed_links_show_posts_feed( true ) );
		$this->assertFalse( $public->feed_links_show_posts_feed( false ) );
	}

	/**
	 * feed_links_show_comments_feed()
	 */

	public function test_feed_links_show_comments_feed_disables_link_when_no_other_post_types_support_comments() {
		$this->stub_post_types_with_feature_result( 'comments', false );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertFalse( $public->feed_links_show_comments_feed( true ) );
	}

	public function test_feed_links_show_comments_feed_leaves_link_unchanged_when_other_post_types_support_comments() {
		$this->stub_post_types_with_feature_result( 'comments', array( 'book' ) );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertTrue( $public->feed_links_show_comments_feed( true ) );
		$this->assertFalse( $public->feed_links_show_comments_feed( false ) );
	}

	/**
	 * header_feeds()
	 */

	public function test_header_feeds_removes_all_four_feed_actions_from_wp_head() {
		WP_Mock::userFunction( 'remove_action' )->once()->with( 'wp_head', 'feed_links', 2 );
		WP_Mock::userFunction( 'remove_action' )->once()->with( 'wp_head', 'feed_links_extra', 3 );
		WP_Mock::userFunction( 'remove_action' )->once()->with( 'wp_head', 'rsd_link', 10 );
		WP_Mock::userFunction( 'remove_action' )->once()->with( 'wp_head', 'wlwmanifest_link', 10 );

		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$this->assertNull( $public->header_feeds() );
	}
}
