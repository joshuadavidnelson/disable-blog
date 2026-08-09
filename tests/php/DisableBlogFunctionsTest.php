<?php
/**
 * Tests for includes/class-disable-blog-functions.php.
 *
 * @package DisableBlog
 */

// Not required by tests/php/bootstrap.php on purpose (see LoaderTest's autoloader test,
// which needs this class to remain undefined until its own isolated process); load it
// here instead. require_once is safe regardless of which test file happens to load first.
require_once __DIR__ . '/../../includes/class-disable-blog-functions.php';

/**
 * @covers Disable_Blog_Functions
 */
class DisableBlogFunctionsTest extends TestCase {

	/**
	 * $_SERVER as it stood before the test, so it can be restored in tear_down().
	 *
	 * @var array
	 */
	private $original_server;

	/**
	 * The global $wp as it stood before the test, so it can be restored in tear_down().
	 *
	 * @var mixed
	 */
	private $original_wp;

	protected function set_up() {
		parent::set_up();

		$this->original_server = $_SERVER;

		global $wp;
		$this->original_wp = $wp ?? null;
	}

	protected function tear_down() {
		$_SERVER = $this->original_server;

		global $wp;
		$wp = $this->original_wp;

		parent::tear_down();
	}

	/**
	 * Invokes a private instance method via Reflection.
	 *
	 * @param Disable_Blog_Functions $functions Instance to invoke on.
	 * @param string                 $method    Method name.
	 * @param array                  $args      Positional arguments.
	 * @return mixed
	 */
	private function invoke_private( Disable_Blog_Functions $functions, $method, array $args = array() ) {
		$reflection = new ReflectionMethod( $functions, $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( $functions, $args );
	}

	/**
	 * Registers a generic absint() stub that mirrors WordPress core's real behavior
	 * (abs(intval())), used by every test that reaches get_redirect_status_code().
	 *
	 * @return void
	 */
	private function stub_real_absint() {
		WP_Mock::userFunction( 'absint' )->andReturnUsing(
			static function ( $value ) {
				return abs( intval( $value ) );
			}
		);
	}

	/**
	 * redirect()
	 */

	/**
	 * In the admin, the current url is derived from admin_url( add_query_arg( array(),
	 * $wp->request ) ), and the wp_safe_redirect_fallback filter must never be added on
	 * this branch. wp_safe_redirect() is stubbed to throw so the exit; right after it can
	 * be observed without terminating the test process.
	 */
	public function test_redirect_in_admin_derives_current_url_from_admin_url_and_redirects() {
		global $wp;
		$wp = (object) array( 'request' => 'wp-admin/edit.php' );

		$functions = new Disable_Blog_Functions();

		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( true );
		WP_Mock::userFunction( 'add_query_arg' )
			->once()
			->with( array(), 'wp-admin/edit.php' )
			->andReturn( 'wp-admin/edit.php' );
		WP_Mock::userFunction( 'admin_url' )
			->once()
			->with( 'wp-admin/edit.php' )
			->andReturn( 'https://example.test/wp-admin/edit.php' );

		WP_Mock::expectFilterNotAdded(
			'wp_safe_redirect_fallback',
			array( $functions, 'wp_safe_redirect_fallback' ),
			9,
			1
		);

		$redirect_url = 'https://example.test/wp-admin/options-general.php';

		WP_Mock::userFunction( 'esc_url_raw' )->with( $redirect_url )->andReturn( $redirect_url );
		WP_Mock::onFilter( 'dwpb_pass_query_string_on_redirect' )->with( false )->reply( false );
		WP_Mock::onFilter( 'dwpb_redirect_status_code' )
			->with( 301, 'https://example.test/wp-admin/edit.php', $redirect_url )
			->reply( 301 );
		$this->stub_real_absint();

		WP_Mock::userFunction( 'wp_safe_redirect' )
			->once()
			->with( $redirect_url, 301 )
			->andThrow( new Exception( 'halted' ) );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'halted' );

		$functions->redirect( $redirect_url );
	}

	/**
	 * Outside the admin, the current url is derived from home_url() plus the raw
	 * REQUEST_URI (not $wp->request, which is path-only -- see the @since 0.5.6 note on
	 * the source), and the wp_safe_redirect_fallback filter must be added.
	 */
	public function test_redirect_not_admin_derives_current_url_from_home_url_and_request_uri_and_adds_fallback_filter() {
		$_SERVER['REQUEST_URI'] = '/current-page/?foo=bar';

		$functions = new Disable_Blog_Functions();

		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( false );
		WP_Mock::userFunction( 'wp_unslash' )->with( '/current-page/?foo=bar' )->andReturn( '/current-page/?foo=bar' );
		WP_Mock::userFunction( 'esc_url_raw' )->with( '/current-page/?foo=bar' )->andReturn( '/current-page/?foo=bar' );
		WP_Mock::userFunction( 'home_url' )
			->once()
			->with( '/current-page/?foo=bar' )
			->andReturn( 'https://example.test/current-page/?foo=bar' );

		WP_Mock::expectFilterAdded(
			'wp_safe_redirect_fallback',
			array( $functions, 'wp_safe_redirect_fallback' ),
			9,
			1
		);

		$redirect_url = 'https://example.test/other-page/';

		WP_Mock::userFunction( 'esc_url_raw' )->with( $redirect_url )->andReturn( $redirect_url );
		WP_Mock::onFilter( 'dwpb_pass_query_string_on_redirect' )->with( false )->reply( false );
		WP_Mock::onFilter( 'dwpb_redirect_status_code' )
			->with( 301, 'https://example.test/current-page/?foo=bar', $redirect_url )
			->reply( 301 );
		$this->stub_real_absint();

		WP_Mock::userFunction( 'wp_safe_redirect' )
			->once()
			->with( $redirect_url, 301 )
			->andThrow( new Exception( 'halted' ) );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'halted' );

		$functions->redirect( $redirect_url );
	}

	/**
	 * When REQUEST_URI is absent from $_SERVER, the isset() ternary must fall back to an
	 * empty string (not skip the fallback, and not some other placeholder), so home_url()
	 * receives '' and wp_unslash()/esc_url_raw() are never reached for it.
	 */
	public function test_redirect_falls_back_to_empty_string_when_request_uri_is_absent() {
		unset( $_SERVER['REQUEST_URI'] );

		$functions = new Disable_Blog_Functions();

		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( false );
		WP_Mock::userFunction( 'wp_unslash' )->never();
		WP_Mock::userFunction( 'home_url' )
			->once()
			->with( '' )
			->andReturn( 'https://example.test/' );

		WP_Mock::expectFilterAdded(
			'wp_safe_redirect_fallback',
			array( $functions, 'wp_safe_redirect_fallback' ),
			9,
			1
		);

		WP_Mock::userFunction( 'wp_safe_redirect' )->never();

		// The redirect url matches the derived current url, so the loop guard returns
		// before esc_url_raw() is ever called on it -- home_url()'s '' argument alone is
		// what's under test here.
		$this->assertNull( $functions->redirect( 'https://example.test/' ) );
	}

	/**
	 * The loop guard: when the redirect url matches the current url, redirect() must
	 * return without calling wp_safe_redirect() at all. The fallback filter is added
	 * unconditionally before this guard runs (it's the first statement in the non-admin
	 * branch), so it's still expected here.
	 */
	public function test_redirect_returns_without_redirecting_when_redirect_url_equals_current_url() {
		$_SERVER['REQUEST_URI'] = '/same-page/';

		$functions = new Disable_Blog_Functions();

		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( false );
		WP_Mock::userFunction( 'wp_unslash' )->with( '/same-page/' )->andReturn( '/same-page/' );
		WP_Mock::userFunction( 'esc_url_raw' )->with( '/same-page/' )->andReturn( '/same-page/' );
		WP_Mock::userFunction( 'home_url' )
			->once()
			->with( '/same-page/' )
			->andReturn( 'https://example.test/same-page/' );

		WP_Mock::expectFilterAdded(
			'wp_safe_redirect_fallback',
			array( $functions, 'wp_safe_redirect_fallback' ),
			9,
			1
		);

		WP_Mock::userFunction( 'wp_safe_redirect' )->never();

		$this->assertNull( $functions->redirect( 'https://example.test/same-page/' ) );

		WP_Mock::assertHooksAdded();
	}

	/**
	 * An invalid (here: empty) redirect url fails esc_url_raw()'s truthiness check in the
	 * loop guard's second clause, so redirect() must return without calling
	 * wp_safe_redirect().
	 */
	public function test_redirect_returns_without_redirecting_when_redirect_url_is_invalid() {
		$_SERVER['REQUEST_URI'] = '/current-page/';

		$functions = new Disable_Blog_Functions();

		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( false );
		WP_Mock::userFunction( 'wp_unslash' )->with( '/current-page/' )->andReturn( '/current-page/' );
		WP_Mock::userFunction( 'esc_url_raw' )->with( '/current-page/' )->andReturn( '/current-page/' );
		WP_Mock::userFunction( 'home_url' )
			->once()
			->with( '/current-page/' )
			->andReturn( 'https://example.test/current-page/' );

		WP_Mock::expectFilterAdded(
			'wp_safe_redirect_fallback',
			array( $functions, 'wp_safe_redirect_fallback' ),
			9,
			1
		);

		WP_Mock::userFunction( 'esc_url_raw' )->with( '' )->andReturn( '' );

		WP_Mock::userFunction( 'wp_safe_redirect' )->never();

		$this->assertNull( $functions->redirect( '' ) );
	}

	/**
	 * When the dwpb_pass_query_string_on_redirect filter returns true, parse_query_string()
	 * must run and its result (allowed query vars appended via add_query_arg()) must be
	 * what actually reaches wp_safe_redirect() -- not the original url.
	 */
	public function test_redirect_appends_allowed_query_vars_when_pass_query_string_filter_is_true() {
		$_SERVER['REQUEST_URI']  = '/current-page/';
		$_SERVER['QUERY_STRING'] = 'foo=bar';

		$functions = new Disable_Blog_Functions();

		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( false );
		WP_Mock::userFunction( 'wp_unslash' )->with( '/current-page/' )->andReturn( '/current-page/' );
		WP_Mock::userFunction( 'esc_url_raw' )->with( '/current-page/' )->andReturn( '/current-page/' );
		WP_Mock::userFunction( 'home_url' )
			->once()
			->with( '/current-page/' )
			->andReturn( 'https://example.test/current-page/' );

		WP_Mock::expectFilterAdded(
			'wp_safe_redirect_fallback',
			array( $functions, 'wp_safe_redirect_fallback' ),
			9,
			1
		);

		$redirect_url         = 'https://example.test/target/';
		$redirect_url_with_qs = 'https://example.test/target/?foo=bar';

		WP_Mock::userFunction( 'esc_url_raw' )->with( $redirect_url )->andReturn( $redirect_url );
		WP_Mock::userFunction( 'esc_url_raw' )->with( $redirect_url_with_qs )->andReturn( $redirect_url_with_qs );

		WP_Mock::onFilter( 'dwpb_pass_query_string_on_redirect' )->with( false )->reply( true );
		WP_Mock::onFilter( 'dwpb_allowed_query_vars' )->with( array() )->reply( array( 'foo' ) );
		WP_Mock::userFunction( 'sanitize_key' )->with( 'foo' )->andReturn( 'foo' );

		WP_Mock::userFunction( 'add_query_arg' )
			->once()
			->with( array( 'foo' => 'bar' ), $redirect_url )
			->andReturn( $redirect_url_with_qs );

		WP_Mock::onFilter( 'dwpb_redirect_status_code' )
			->with( 301, 'https://example.test/current-page/', $redirect_url_with_qs )
			->reply( 301 );
		$this->stub_real_absint();

		WP_Mock::userFunction( 'wp_safe_redirect' )
			->once()
			->with( $redirect_url_with_qs, 301 )
			->andThrow( new Exception( 'halted' ) );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'halted' );

		$functions->redirect( $redirect_url );
	}

	/**
	 * dwpb_pass_query_string_on_redirect defaults to false: parse_query_string() (and
	 * therefore add_query_arg()) must never run, even when a real QUERY_STRING is present
	 * in the environment.
	 */
	public function test_redirect_does_not_append_query_string_when_pass_query_string_filter_is_false_by_default() {
		$_SERVER['REQUEST_URI']  = '/current-page/';
		$_SERVER['QUERY_STRING'] = 'foo=bar';

		$functions = new Disable_Blog_Functions();

		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( false );
		WP_Mock::userFunction( 'wp_unslash' )->with( '/current-page/' )->andReturn( '/current-page/' );
		WP_Mock::userFunction( 'esc_url_raw' )->with( '/current-page/' )->andReturn( '/current-page/' );
		WP_Mock::userFunction( 'home_url' )
			->once()
			->with( '/current-page/' )
			->andReturn( 'https://example.test/current-page/' );

		WP_Mock::expectFilterAdded(
			'wp_safe_redirect_fallback',
			array( $functions, 'wp_safe_redirect_fallback' ),
			9,
			1
		);

		$redirect_url = 'https://example.test/target/';

		WP_Mock::userFunction( 'esc_url_raw' )->with( $redirect_url )->andReturn( $redirect_url );
		WP_Mock::onFilter( 'dwpb_pass_query_string_on_redirect' )->with( false )->reply( false );
		WP_Mock::userFunction( 'add_query_arg' )->never();

		WP_Mock::onFilter( 'dwpb_redirect_status_code' )
			->with( 301, 'https://example.test/current-page/', $redirect_url )
			->reply( 301 );
		$this->stub_real_absint();

		WP_Mock::userFunction( 'wp_safe_redirect' )
			->once()
			->with( $redirect_url, 301 )
			->andThrow( new Exception( 'halted' ) );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'halted' );

		$functions->redirect( $redirect_url );
	}

	/**
	 * The status code get_redirect_status_code() computes must be exactly what reaches
	 * wp_safe_redirect(), not just the 301 default.
	 */
	public function test_redirect_passes_filtered_status_code_through_to_wp_safe_redirect() {
		$_SERVER['REQUEST_URI'] = '/current-page/';

		$functions = new Disable_Blog_Functions();

		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( false );
		WP_Mock::userFunction( 'wp_unslash' )->with( '/current-page/' )->andReturn( '/current-page/' );
		WP_Mock::userFunction( 'esc_url_raw' )->with( '/current-page/' )->andReturn( '/current-page/' );
		WP_Mock::userFunction( 'home_url' )
			->once()
			->with( '/current-page/' )
			->andReturn( 'https://example.test/current-page/' );

		WP_Mock::expectFilterAdded(
			'wp_safe_redirect_fallback',
			array( $functions, 'wp_safe_redirect_fallback' ),
			9,
			1
		);

		$redirect_url = 'https://example.test/target/';

		WP_Mock::userFunction( 'esc_url_raw' )->with( $redirect_url )->andReturn( $redirect_url );
		WP_Mock::onFilter( 'dwpb_pass_query_string_on_redirect' )->with( false )->reply( false );

		WP_Mock::onFilter( 'dwpb_redirect_status_code' )
			->with( 301, 'https://example.test/current-page/', $redirect_url )
			->reply( 302 );

		$this->stub_real_absint();

		WP_Mock::userFunction( 'wp_safe_redirect' )
			->once()
			->with( $redirect_url, 302 )
			->andThrow( new Exception( 'halted' ) );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'halted' );

		$functions->redirect( $redirect_url );
	}

	/**
	 * wp_safe_redirect() must receive the esc_url_raw()-escaped form of the redirect url,
	 * not the raw value -- even though the raw value already passed the earlier loop-guard
	 * truthiness check and is what's passed into get_redirect_status_code().
	 */
	public function test_redirect_passes_escaped_url_to_wp_safe_redirect() {
		$_SERVER['REQUEST_URI'] = '/current-page/';

		$functions = new Disable_Blog_Functions();

		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( false );
		WP_Mock::userFunction( 'wp_unslash' )->with( '/current-page/' )->andReturn( '/current-page/' );
		WP_Mock::userFunction( 'esc_url_raw' )->with( '/current-page/' )->andReturn( '/current-page/' );
		WP_Mock::userFunction( 'home_url' )
			->once()
			->with( '/current-page/' )
			->andReturn( 'https://example.test/current-page/' );

		WP_Mock::expectFilterAdded(
			'wp_safe_redirect_fallback',
			array( $functions, 'wp_safe_redirect_fallback' ),
			9,
			1
		);

		$raw_redirect_url     = 'https://example.test/target/?x=1&y=2';
		$escaped_redirect_url = 'https://example.test/target/?x=1&#038;y=2';

		WP_Mock::userFunction( 'esc_url_raw' )->with( $raw_redirect_url )->andReturn( $escaped_redirect_url );
		WP_Mock::onFilter( 'dwpb_pass_query_string_on_redirect' )->with( false )->reply( false );
		WP_Mock::onFilter( 'dwpb_redirect_status_code' )
			->with( 301, 'https://example.test/current-page/', $raw_redirect_url )
			->reply( 301 );
		$this->stub_real_absint();

		WP_Mock::userFunction( 'wp_safe_redirect' )
			->once()
			->with( $escaped_redirect_url, 301 )
			->andThrow( new Exception( 'halted' ) );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'halted' );

		$functions->redirect( $raw_redirect_url );
	}

	/**
	 * parse_query_string() (private)
	 */

	public function test_parse_query_string_returns_url_unchanged_when_query_string_not_set() {
		unset( $_SERVER['QUERY_STRING'] );

		$functions = new Disable_Blog_Functions();

		WP_Mock::userFunction( 'add_query_arg' )->never();

		$this->assertSame(
			'https://example.test/target/',
			$this->invoke_private( $functions, 'parse_query_string', array( 'https://example.test/target/' ) )
		);
	}

	public function test_parse_query_string_returns_url_unchanged_when_query_string_empty() {
		$_SERVER['QUERY_STRING'] = '';

		$functions = new Disable_Blog_Functions();

		WP_Mock::userFunction( 'add_query_arg' )->never();

		$this->assertSame(
			'https://example.test/target/',
			$this->invoke_private( $functions, 'parse_query_string', array( 'https://example.test/target/' ) )
		);
	}

	/**
	 * dwpb_allowed_query_vars replies with its own default (an empty array) here, so the
	 * outer `! empty( $allowed_query_vars )` guard must skip everything below it.
	 */
	public function test_parse_query_string_returns_url_unchanged_when_no_allowed_query_vars() {
		$_SERVER['QUERY_STRING'] = 'foo=bar';

		$functions = new Disable_Blog_Functions();

		WP_Mock::onFilter( 'dwpb_allowed_query_vars' )->with( array() )->reply( array() );
		WP_Mock::userFunction( 'add_query_arg' )->never();

		$this->assertSame(
			'https://example.test/target/',
			$this->invoke_private( $functions, 'parse_query_string', array( 'https://example.test/target/' ) )
		);
	}

	/**
	 * Only allowed query vars present in the real, parsed QUERY_STRING are appended;
	 * an allowed-but-empty value ('empty=') is dropped by the inner array_filter().
	 */
	public function test_parse_query_string_filters_to_allowed_vars_and_appends_to_url() {
		$_SERVER['QUERY_STRING'] = 'foo=bar&baz=qux&empty=';

		$functions = new Disable_Blog_Functions();

		WP_Mock::onFilter( 'dwpb_allowed_query_vars' )->with( array() )->reply( array( 'foo', 'empty' ) );
		WP_Mock::userFunction( 'sanitize_key' )->with( 'foo' )->andReturn( 'foo' );
		WP_Mock::userFunction( 'sanitize_key' )->with( 'empty' )->andReturn( 'empty' );

		WP_Mock::userFunction( 'add_query_arg' )
			->once()
			->with( array( 'foo' => 'bar' ), 'https://example.test/target/' )
			->andReturn( 'https://example.test/target/?foo=bar' );

		$result = $this->invoke_private( $functions, 'parse_query_string', array( 'https://example.test/target/' ) );

		$this->assertSame( 'https://example.test/target/?foo=bar', $result );
	}

	/**
	 * Allowed query vars are configured, but none of them appear in the actual query
	 * string: the inner `! empty( $query_vars )` guard must skip add_query_arg() too.
	 */
	public function test_parse_query_string_returns_url_unchanged_when_none_of_the_query_string_vars_are_allowed() {
		$_SERVER['QUERY_STRING'] = 'foo=bar';

		$functions = new Disable_Blog_Functions();

		WP_Mock::onFilter( 'dwpb_allowed_query_vars' )->with( array() )->reply( array( 'zzz' ) );
		WP_Mock::userFunction( 'sanitize_key' )->with( 'zzz' )->andReturn( 'zzz' );

		WP_Mock::userFunction( 'add_query_arg' )->never();

		$result = $this->invoke_private( $functions, 'parse_query_string', array( 'https://example.test/target/' ) );

		$this->assertSame( 'https://example.test/target/', $result );
	}

	/**
	 * get_allowed_query_vars() (private)
	 */

	public function test_get_allowed_query_vars_returns_empty_array_by_default() {
		$functions = new Disable_Blog_Functions();

		WP_Mock::onFilter( 'dwpb_allowed_query_vars' )->with( array() )->reply( array() );
		WP_Mock::userFunction( 'sanitize_key' )->never();

		$this->assertSame( array(), $this->invoke_private( $functions, 'get_allowed_query_vars' ) );
	}

	/**
	 * array_filter() preserves keys, so the dropped empty entry at index 1 leaves a gap
	 * in the result instead of the array being reindexed.
	 */
	public function test_get_allowed_query_vars_sanitizes_keys_and_drops_empty_values() {
		$functions = new Disable_Blog_Functions();

		WP_Mock::onFilter( 'dwpb_allowed_query_vars' )->with( array() )->reply( array( 'Foo', '', 'Bar_Baz' ) );
		WP_Mock::userFunction( 'sanitize_key' )->with( 'Foo' )->andReturn( 'foo' );
		WP_Mock::userFunction( 'sanitize_key' )->with( '' )->andReturn( '' );
		WP_Mock::userFunction( 'sanitize_key' )->with( 'Bar_Baz' )->andReturn( 'bar_baz' );

		$result = $this->invoke_private( $functions, 'get_allowed_query_vars' );

		$this->assertSame( array( 0 => 'foo', 2 => 'bar_baz' ), $result );
	}

	/**
	 * get_redirect_status_code() (private)
	 */

	/**
	 * Invokes get_redirect_status_code() with the dwpb_redirect_status_code filter
	 * replying $filtered_value, and asserts the result is $expected.
	 *
	 * @param mixed $filtered_value The value the filter should reply with.
	 * @param int   $expected       The expected return value.
	 * @return void
	 */
	private function assert_redirect_status_code( $filtered_value, $expected ) {
		$functions    = new Disable_Blog_Functions();
		$current_url  = 'https://example.test/current/';
		$redirect_url = 'https://example.test/redirect/';

		WP_Mock::onFilter( 'dwpb_redirect_status_code' )
			->with( 301, $current_url, $redirect_url )
			->reply( $filtered_value );

		$this->stub_real_absint();

		$result = $this->invoke_private( $functions, 'get_redirect_status_code', array( $current_url, $redirect_url ) );

		$this->assertSame( $expected, $result );
	}

	public function test_get_redirect_status_code_clamps_299_to_301() {
		$this->assert_redirect_status_code( 299, 301 );
	}

	public function test_get_redirect_status_code_allows_300_boundary() {
		$this->assert_redirect_status_code( 300, 300 );
	}

	public function test_get_redirect_status_code_allows_399_boundary() {
		$this->assert_redirect_status_code( 399, 399 );
	}

	public function test_get_redirect_status_code_clamps_400_to_301() {
		$this->assert_redirect_status_code( 400, 301 );
	}

	public function test_get_redirect_status_code_clamps_zero_to_301() {
		$this->assert_redirect_status_code( 0, 301 );
	}

	public function test_get_redirect_status_code_clamps_non_numeric_value_to_301() {
		$this->assert_redirect_status_code( 'not-a-number', 301 );
	}

	/**
	 * A valid in-range value must come back through absint() as an int, not pass through
	 * as whatever type the filter returned -- a numeric string here would satisfy the
	 * range checks unmodified, so only the absint() cast on the return value tells them
	 * apart. assertSame() (strict) makes the type mismatch fail.
	 */
	public function test_get_redirect_status_code_returns_absint_not_raw_filtered_value() {
		$this->assert_redirect_status_code( '350', 350 );
	}

	/**
	 * wp_safe_redirect_fallback()
	 */

	public function test_wp_safe_redirect_fallback_returns_original_url_when_in_admin() {
		$functions = new Disable_Blog_Functions();

		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( true );
		WP_Mock::userFunction( 'home_url' )->never();

		$this->assertSame(
			'https://example.test/wp-login.php',
			$functions->wp_safe_redirect_fallback( 'https://example.test/wp-login.php' )
		);
	}

	public function test_wp_safe_redirect_fallback_returns_home_url_when_not_admin() {
		$functions = new Disable_Blog_Functions();

		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( false );
		WP_Mock::userFunction( 'home_url' )->once()->andReturn( 'https://example.test/' );

		$this->assertSame(
			'https://example.test/',
			$functions->wp_safe_redirect_fallback( 'https://example.test/wp-login.php' )
		);
	}

	/**
	 * author_archive_post_types()
	 */

	public function test_author_archive_post_types_returns_filtered_post_types_when_non_empty() {
		$functions = new Disable_Blog_Functions();

		WP_Mock::onFilter( 'dwpb_author_archive_post_types' )->with( array() )->reply( array( 'book' ) );

		$this->assertSame( array( 'book' ), $functions->author_archive_post_types() );
	}

	public function test_author_archive_post_types_returns_false_when_empty_by_default() {
		$functions = new Disable_Blog_Functions();

		WP_Mock::onFilter( 'dwpb_author_archive_post_types' )->with( array() )->reply( array() );

		$this->assertFalse( $functions->author_archive_post_types() );
	}

	/**
	 * disable_author_archives()
	 */

	public function test_disable_author_archives_defaults_to_false() {
		$functions = new Disable_Blog_Functions();

		WP_Mock::onFilter( 'dwpb_disable_author_archives' )->with( false )->reply( false );

		$this->assertFalse( $functions->disable_author_archives() );
	}

	public function test_disable_author_archives_true_when_filtered() {
		$functions = new Disable_Blog_Functions();

		WP_Mock::onFilter( 'dwpb_disable_author_archives' )->with( false )->reply( true );

		$this->assertTrue( $functions->disable_author_archives() );
	}

	/**
	 * disable_feeds()
	 */

	/**
	 * $is_comment_feed defaults to false when the argument is omitted.
	 */
	public function test_disable_feeds_defaults_is_comment_feed_to_false_when_omitted() {
		$functions = new Disable_Blog_Functions();
		$post      = (object) array( 'ID' => 1 );

		WP_Mock::onFilter( 'dwpb_disable_feed' )->with( true, $post, false )->reply( false );

		$this->assertFalse( $functions->disable_feeds( $post ) );
	}

	public function test_disable_feeds_defaults_to_true_and_passes_post_and_comment_feed_flag_to_filter() {
		$functions = new Disable_Blog_Functions();
		$post      = (object) array( 'ID' => 1 );

		WP_Mock::onFilter( 'dwpb_disable_feed' )->with( true, $post, true )->reply( true );

		$this->assertTrue( $functions->disable_feeds( $post, true ) );
	}

	public function test_disable_feeds_false_when_filtered() {
		$functions = new Disable_Blog_Functions();
		$post      = (object) array( 'ID' => 1 );

		WP_Mock::onFilter( 'dwpb_disable_feed' )->with( true, $post, false )->reply( false );

		$this->assertFalse( $functions->disable_feeds( $post, false ) );
	}
}
