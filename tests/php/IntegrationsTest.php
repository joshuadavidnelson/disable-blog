<?php
/**
 * Tests for includes/class-disable-blog-integrations.php.
 *
 * @package DisableBlog
 */

/**
 * @covers Disable_Blog_Integrations
 */
class IntegrationsTest extends TestCase {

	/**
	 * Invokes the private woocommerce_version_check() method via Reflection.
	 *
	 * @param Disable_Blog_Integrations $integrations Instance to invoke on.
	 * @param array                     $args         Positional arguments.
	 * @return mixed
	 */
	private function invoke_woocommerce_version_check( Disable_Blog_Integrations $integrations, array $args ) {
		$method = new ReflectionMethod( $integrations, 'woocommerce_version_check' );
		$method->setAccessible( true );

		return $method->invokeArgs( $integrations, $args );
	}

	/**
	 * is_plugin_active()
	 */

	public function test_is_plugin_active_returns_true_when_wp_reports_active() {
		WP_Mock::userFunction( 'is_plugin_active' )
			->once()
			->with( 'woocommerce/woocommerce.php' )
			->andReturn( true );

		$integrations = new Disable_Blog_Integrations();

		$this->assertTrue( $integrations->is_plugin_active( 'woocommerce/woocommerce.php' ) );
	}

	public function test_is_plugin_active_returns_false_when_wp_reports_inactive() {
		WP_Mock::userFunction( 'is_plugin_active' )
			->once()
			->with( 'woocommerce/woocommerce.php' )
			->andReturn( false );

		$integrations = new Disable_Blog_Integrations();

		$this->assertFalse( $integrations->is_plugin_active( 'woocommerce/woocommerce.php' ) );
	}

	/**
	 * Isolated: is_plugin_active() only reaches the include_once() when the WP function
	 * is_plugin_active is not yet defined. Once any test defines it (via WP_Mock::userFunction,
	 * which eval()s a global function the first time it's mocked), PHP can never undefine it
	 * for the rest of the process. A fresh process guarantees the function is genuinely absent.
	 * bootstrap.php points ABSPATH at tests/php/Support/fixtures/, which holds a fixture
	 * wp-admin/includes/plugin.php that defines a real is_plugin_active() to be included
	 * and called.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_is_plugin_active_includes_core_file_when_function_not_yet_defined() {
		$integrations = new Disable_Blog_Integrations();

		$this->assertTrue( $integrations->is_plugin_active( 'fixture-plugin/fixture-plugin.php' ) );
		$this->assertFalse( $integrations->is_plugin_active( 'something-else/something-else.php' ) );
	}

	/**
	 * is_disable_comments_active()
	 */

	public function test_is_disable_comments_active_true_when_plugin_reports_active() {
		WP_Mock::userFunction( 'is_plugin_active' )
			->once()
			->with( 'disable-comments/disable-comments.php' )
			->andReturn( true );

		$integrations = new Disable_Blog_Integrations();

		$this->assertTrue( $integrations->is_disable_comments_active() );
	}

	public function test_is_disable_comments_active_false_when_plugin_inactive_and_class_missing() {
		WP_Mock::userFunction( 'is_plugin_active' )
			->once()
			->with( 'disable-comments/disable-comments.php' )
			->andReturn( false );

		$integrations = new Disable_Blog_Integrations();

		$this->assertFalse( $integrations->is_disable_comments_active() );
	}

	/**
	 * Isolated: declaring class Disable_Comments cannot be undone within the process, and
	 * would otherwise make class_exists('Disable_Comments') permanently true for every
	 * later test (including the "class missing" case above). The class lives in a fixture
	 * file rather than inline because PHP disallows nesting a class declaration inside
	 * another class's method body ("Class declarations may not be nested").
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_is_disable_comments_active_true_when_class_exists_even_if_plugin_inactive() {
		require __DIR__ . '/Support/fixtures/class-disable-comments-stub.php';

		WP_Mock::userFunction( 'is_plugin_active' )
			->once()
			->with( 'disable-comments/disable-comments.php' )
			->andReturn( false );

		$integrations = new Disable_Blog_Integrations();

		$this->assertTrue( $integrations->is_disable_comments_active() );
	}

	/**
	 * is_woocommerce_active()
	 */

	public function test_is_woocommerce_active_true_when_plugin_reports_active() {
		WP_Mock::userFunction( 'is_plugin_active' )
			->once()
			->with( 'woocommerce/woocommerce.php' )
			->andReturn( true );

		$integrations = new Disable_Blog_Integrations();

		$this->assertTrue( $integrations->is_woocommerce_active() );
	}

	public function test_is_woocommerce_active_false_when_plugin_inactive_and_function_missing() {
		WP_Mock::userFunction( 'is_plugin_active' )
			->once()
			->with( 'woocommerce/woocommerce.php' )
			->andReturn( false );

		$integrations = new Disable_Blog_Integrations();

		$this->assertFalse( $integrations->is_woocommerce_active() );
	}

	/**
	 * Isolated: declaring function WC() cannot be undone within the process, and would
	 * otherwise make function_exists('WC') permanently true for every later test.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_is_woocommerce_active_true_when_wc_function_exists_even_if_plugin_inactive() {
		function WC() {}

		WP_Mock::userFunction( 'is_plugin_active' )
			->once()
			->with( 'woocommerce/woocommerce.php' )
			->andReturn( false );

		$integrations = new Disable_Blog_Integrations();

		$this->assertTrue( $integrations->is_woocommerce_active() );
	}

	/**
	 * filter_woocommerce_comment_count()
	 */

	public function test_filter_woocommerce_comment_count_skips_when_post_id_is_not_zero() {
		$comments = (object) array( 'foo' => 'bar' );

		$integrations = new Disable_Blog_Integrations();

		// The 0 === $post_id check must short-circuit before woocommerce_version_check()
		// (and therefore is_plugin_active()) is ever reached. Asserting ->never() catches
		// the call directly instead of relying on an unstubbed call happening to error out.
		WP_Mock::userFunction( 'is_plugin_active' )->never();

		$result = $integrations->filter_woocommerce_comment_count( $comments, 5 );

		$this->assertSame( $comments, $result );
	}

	public function test_filter_woocommerce_comment_count_unchanged_when_woocommerce_inactive() {
		$comments = (object) array( 'foo' => 'bar' );

		WP_Mock::userFunction( 'is_plugin_active' )
			->once()
			->with( 'woocommerce/woocommerce.php' )
			->andReturn( false );

		$integrations = new Disable_Blog_Integrations();

		$result = $integrations->filter_woocommerce_comment_count( $comments, 0 );

		$this->assertSame( $comments, $result );
	}

	/**
	 * Isolated: needs WC_VERSION defined below the 2.6.2 threshold, which would otherwise
	 * leak into every later woocommerce_version_check()-dependent test.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_filter_woocommerce_comment_count_casts_to_array_when_old_woocommerce_active() {
		define( 'WC_VERSION', '2.6.0' );

		WP_Mock::userFunction( 'is_plugin_active' )
			->once()
			->with( 'woocommerce/woocommerce.php' )
			->andReturn( true );

		$comments = (object) array( 'foo' => 'bar' );

		$integrations = new Disable_Blog_Integrations();

		$result = $integrations->filter_woocommerce_comment_count( $comments, 0 );

		$this->assertSame( array( 'foo' => 'bar' ), $result );
	}

	/**
	 * woocommerce_version_check() (private)
	 */

	public function test_woocommerce_version_check_false_when_woocommerce_inactive() {
		WP_Mock::userFunction( 'is_plugin_active' )
			->once()
			->with( 'woocommerce/woocommerce.php' )
			->andReturn( false );

		$integrations = new Disable_Blog_Integrations();

		$this->assertFalse( $this->invoke_woocommerce_version_check( $integrations, array( '2.6.2' ) ) );
	}

	/**
	 * Relies on WC_VERSION/WOOCOMMERCE_VERSION never being defined outside the isolated
	 * tests below, so this can safely assert the "neither constant defined" branch without
	 * isolation of its own.
	 */
	public function test_woocommerce_version_check_false_when_active_but_no_version_constant_defined() {
		WP_Mock::userFunction( 'is_plugin_active' )
			->once()
			->with( 'woocommerce/woocommerce.php' )
			->andReturn( true );

		$integrations = new Disable_Blog_Integrations();

		$this->assertFalse( $this->invoke_woocommerce_version_check( $integrations, array( '2.6.2' ) ) );
	}

	/**
	 * Isolated: defines WC_VERSION, which cannot be undefined afterward. Also exercises the
	 * optional $check comparison operator argument in the same process.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_woocommerce_version_check_uses_wc_version_constant_when_defined() {
		define( 'WC_VERSION', '2.6.0' );

		WP_Mock::userFunction( 'is_plugin_active' )
			->twice()
			->with( 'woocommerce/woocommerce.php' )
			->andReturn( true );

		$integrations = new Disable_Blog_Integrations();

		// Default $check is '<=': 2.6.0 <= 2.6.2 is true.
		$this->assertTrue( $this->invoke_woocommerce_version_check( $integrations, array( '2.6.2' ) ) );

		// Explicit $check '>': 2.6.0 > 2.6.2 is false.
		$this->assertFalse( $this->invoke_woocommerce_version_check( $integrations, array( '2.6.2', '>' ) ) );
	}

	/**
	 * Isolated: defines WOOCOMMERCE_VERSION (and deliberately not WC_VERSION) to exercise the
	 * elseif fallback branch; the constant cannot be undefined afterward.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_woocommerce_version_check_falls_back_to_woocommerce_version_constant() {
		define( 'WOOCOMMERCE_VERSION', '2.0.0' );

		WP_Mock::userFunction( 'is_plugin_active' )
			->once()
			->with( 'woocommerce/woocommerce.php' )
			->andReturn( true );

		$integrations = new Disable_Blog_Integrations();

		// 2.0.0 <= 2.6.2 is true -- chosen deliberately so this diverges from the fallthrough
		// `return false;` a skipped elseif would produce, unlike a WOOCOMMERCE_VERSION that
		// also compares false against the checked version.
		$this->assertTrue( $this->invoke_woocommerce_version_check( $integrations, array( '2.6.2' ) ) );
	}
}
