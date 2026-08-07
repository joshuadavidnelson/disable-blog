<?php
/**
 * Shared base test case for Disable_Blog_Activator and Disable_Blog_Deactivator.
 *
 * The two classes are structurally identical (get_request(), validate_request(), check_caps(),
 * and a public static lifecycle method that reads $_REQUEST and holds static state), differing
 * only in the verb ('activate' vs 'deactivate') baked into their nonce actions and $_REQUEST
 * values. Subclasses supply that verb-specific vocabulary via the abstract methods below; every
 * test method here is verb-agnostic.
 *
 * @package DisableBlog
 */

/**
 * Base test case for Disable_Blog_Activator / Disable_Blog_Deactivator.
 */
abstract class PluginLifecycleTestCase extends TestCase {

	/**
	 * $_REQUEST as it stood before the test, so it can be restored in tear_down().
	 *
	 * @var array
	 */
	private $original_request;

	/**
	 * Fully-qualified name of the class under test.
	 *
	 * @return string
	 */
	abstract protected function target_class(): string;

	/**
	 * Name of the public static lifecycle method under test ('activate' or 'deactivate').
	 *
	 * @return string
	 */
	abstract protected function lifecycle_method(): string;

	/**
	 * Nonce action prefix used for the single-plugin path (e.g. 'activate-plugin_').
	 *
	 * @return string
	 */
	abstract protected function single_nonce_action_prefix(): string;

	/**
	 * $_REQUEST['action'] value expected on the single-plugin path (e.g. 'activate').
	 *
	 * @return string
	 */
	abstract protected function single_action_value(): string;

	/**
	 * $_REQUEST['action'] value expected on the bulk path (e.g. 'activate-selected').
	 *
	 * @return string
	 */
	abstract protected function bulk_action_value(): string;

	protected function set_up() {
		parent::set_up();

		$this->original_request = $_REQUEST;

		$this->reset_target_static_state();

		// Every get_request() path runs $_REQUEST values through these before use; treat
		// them as passthroughs so tests can assert on the raw values they set.
		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
	}

	protected function tear_down() {
		$_REQUEST = $this->original_request;

		parent::tear_down();
	}

	/**
	 * Resets the target class's static::$request and static::$plugin to their initial
	 * values. Both are private static properties that persist across tests in the same
	 * process, so without this, an earlier test's leftover state would leak into the next.
	 *
	 * @return void
	 */
	private function reset_target_static_state() {
		$class = new ReflectionClass( $this->target_class() );

		$request_property = $class->getProperty( 'request' );
		$request_property->setAccessible( true );
		$request_property->setValue( null, array() );

		$plugin_property = $class->getProperty( 'plugin' );
		$plugin_property->setAccessible( true );
		$plugin_property->setValue( null, 'disable-blog' );
	}

	/**
	 * Directly sets the target class's static::$request, bypassing get_request(), so
	 * validate_request() can be tested in isolation.
	 *
	 * @param array $value The value to assign.
	 * @return void
	 */
	private function set_target_static_request( array $value ) {
		$class    = new ReflectionClass( $this->target_class() );
		$property = $class->getProperty( 'request' );
		$property->setAccessible( true );
		$property->setValue( null, $value );
	}

	/**
	 * Invokes a private static method on the target class via Reflection.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Positional arguments.
	 * @return mixed
	 */
	private function invoke_private_static( $method, array $args = array() ) {
		$class      = new ReflectionClass( $this->target_class() );
		$reflection = $class->getMethod( $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( null, $args );
	}

	/**
	 * Invokes the target class's public static lifecycle method (activate()/deactivate()).
	 *
	 * @return mixed
	 */
	private function invoke_lifecycle_method() {
		$target = $this->target_class();
		$method = $this->lifecycle_method();

		return $target::$method();
	}

	/**
	 * get_request()
	 */

	/**
	 * The `! empty( $_REQUEST )` clause in get_request()'s outer guard can't be isolated by
	 * any input: whenever $_REQUEST is empty, the isset() checks that follow it in the same
	 * && chain already evaluate false on their own, so this test cannot distinguish that
	 * clause being present from it being absent. It still confirms the real, non-redundant
	 * behavior that a request with no keys at all returns false without a PHP notice on the
	 * missing array indexes.
	 */
	public function test_get_request_returns_false_when_request_has_no_keys_at_all() {
		$_REQUEST = array();

		$this->assertFalse( $this->invoke_private_static( 'get_request' ) );
	}

	public function test_get_request_returns_false_when_required_keys_missing() {
		// Has 'plugin' and '_wpnonce', but no 'action' -- the outer guard must fail closed.
		$_REQUEST = array(
			'_wpnonce' => 'nonce-value',
			'plugin'   => 'disable-blog',
		);

		$this->assertFalse( $this->invoke_private_static( 'get_request' ) );
	}

	public function test_get_request_returns_populated_array_when_single_plugin_nonce_valid() {
		$_REQUEST = array(
			'_wpnonce' => 'nonce-value',
			'action'   => $this->single_action_value(),
			'plugin'   => 'disable-blog',
		);

		WP_Mock::userFunction( 'wp_verify_nonce' )
			->once()
			->with( 'nonce-value', $this->single_nonce_action_prefix() . 'disable-blog' )
			->andReturn( 1 );

		$this->assertSame(
			array(
				'plugin' => 'disable-blog',
				'action' => $this->single_action_value(),
			),
			$this->invoke_private_static( 'get_request' )
		);
	}

	public function test_get_request_returns_false_when_single_plugin_nonce_invalid() {
		$_REQUEST = array(
			'_wpnonce' => 'bad-nonce',
			'action'   => $this->single_action_value(),
			'plugin'   => 'disable-blog',
		);

		WP_Mock::userFunction( 'wp_verify_nonce' )
			->once()
			->with( 'bad-nonce', $this->single_nonce_action_prefix() . 'disable-blog' )
			->andReturn( false );

		$this->assertFalse( $this->invoke_private_static( 'get_request' ) );
	}

	public function test_get_request_returns_populated_array_when_bulk_nonce_valid() {
		$_REQUEST = array(
			'_wpnonce' => 'nonce-value',
			'action'   => $this->bulk_action_value(),
			'checked'  => array( 'disable-blog', 'other-plugin' ),
		);

		WP_Mock::userFunction( 'wp_verify_nonce' )
			->once()
			->with( 'nonce-value', 'bulk-plugins' )
			->andReturn( 1 );

		$this->assertSame(
			array(
				'action'  => $this->bulk_action_value(),
				'plugins' => array( 'disable-blog', 'other-plugin' ),
			),
			$this->invoke_private_static( 'get_request' )
		);
	}

	public function test_get_request_returns_false_when_bulk_nonce_invalid() {
		$_REQUEST = array(
			'_wpnonce' => 'bad-nonce',
			'action'   => $this->bulk_action_value(),
			'checked'  => array( 'disable-blog' ),
		);

		WP_Mock::userFunction( 'wp_verify_nonce' )
			->once()
			->with( 'bad-nonce', 'bulk-plugins' )
			->andReturn( false );

		$this->assertFalse( $this->invoke_private_static( 'get_request' ) );
	}

	/**
	 * validate_request( $plugin )
	 */

	public function test_validate_request_true_for_matching_single_plugin() {
		$this->set_target_static_request(
			array(
				'plugin' => 'disable-blog',
				'action' => $this->single_action_value(),
			)
		);

		$this->assertTrue( $this->invoke_private_static( 'validate_request', array( 'disable-blog' ) ) );
	}

	public function test_validate_request_false_for_mismatched_plugin() {
		$this->set_target_static_request(
			array(
				'plugin' => 'some-other-plugin',
				'action' => $this->single_action_value(),
			)
		);

		$this->assertFalse( $this->invoke_private_static( 'validate_request', array( 'disable-blog' ) ) );
	}

	public function test_validate_request_false_for_wrong_action() {
		$this->set_target_static_request(
			array(
				'plugin' => 'disable-blog',
				'action' => 'some-unrelated-action',
			)
		);

		$this->assertFalse( $this->invoke_private_static( 'validate_request', array( 'disable-blog' ) ) );
	}

	public function test_validate_request_true_for_matching_bulk_selection() {
		$this->set_target_static_request(
			array(
				'plugins' => array( 'other-plugin', 'disable-blog' ),
				'action'  => $this->bulk_action_value(),
			)
		);

		$this->assertTrue( $this->invoke_private_static( 'validate_request', array( 'disable-blog' ) ) );
	}

	public function test_validate_request_false_when_plugin_not_in_bulk_selection() {
		$this->set_target_static_request(
			array(
				'plugins' => array( 'other-plugin' ),
				'action'  => $this->bulk_action_value(),
			)
		);

		$this->assertFalse( $this->invoke_private_static( 'validate_request', array( 'disable-blog' ) ) );
	}

	public function test_validate_request_false_when_neither_plugin_nor_plugins_set() {
		$this->set_target_static_request( array() );

		$this->assertFalse( $this->invoke_private_static( 'validate_request', array( 'disable-blog' ) ) );
	}

	/**
	 * check_caps()
	 */

	public function test_check_caps_true_when_user_can_activate_plugins() {
		WP_Mock::userFunction( 'current_user_can' )
			->once()
			->with( 'activate_plugins' )
			->andReturn( true );

		$this->assertTrue( $this->invoke_private_static( 'check_caps' ) );
	}

	public function test_check_caps_false_when_user_cannot_activate_plugins() {
		WP_Mock::userFunction( 'current_user_can' )
			->once()
			->with( 'activate_plugins' )
			->andReturn( false );

		$this->assertFalse( $this->invoke_private_static( 'check_caps' ) );
	}

	/**
	 * activate() / deactivate()
	 */

	/**
	 * Terminating paths guarded by check_admin_referer() end in exit;, which a test process
	 * can't survive. Stubbing check_admin_referer() to throw exercises the guard clause and
	 * proves the exception propagates out of the lifecycle method -- but the exception is
	 * thrown by check_admin_referer() itself, so control never reaches the exit; statement
	 * that follows it. Verifying that literal exit; is reached is out of reach without a
	 * source-side refactor (e.g. routing through a wrapper exit() could be intercepted), which
	 * is out of scope for a zero-source-change phase.
	 */
	public function test_public_static_method_calls_check_admin_referer_with_correct_action_and_propagates_exception_when_single_plugin_validation_fails() {
		$_REQUEST = array(
			'_wpnonce' => 'nonce-value',
			'action'   => $this->single_action_value(),
			'plugin'   => 'some-other-plugin',
		);

		WP_Mock::userFunction( 'wp_verify_nonce' )
			->once()
			->with( 'nonce-value', $this->single_nonce_action_prefix() . 'some-other-plugin' )
			->andReturn( 1 );

		// get_request() succeeds (nonce valid) and populates static::$request['plugin'] with
		// 'some-other-plugin', so validate_request() fails on the plugin-name comparison --
		// not on a missing array key -- keeping the interpolation below well-defined.
		WP_Mock::userFunction( 'check_admin_referer' )
			->once()
			->with( $this->single_nonce_action_prefix() . 'some-other-plugin' )
			->andThrow( new Exception( 'halted' ) );

		WP_Mock::userFunction( 'wp_cache_delete' )->never();
		WP_Mock::userFunction( 'delete_transient' )->never();
		WP_Mock::userFunction( 'flush_rewrite_rules' )->never();

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'halted' );

		$this->invoke_lifecycle_method();
	}

	/**
	 * See the docblock on the single-plugin variant above: this proves check_admin_referer()
	 * is called with the bulk nonce action and that its exception propagates, not that the
	 * exit; statement itself is reached.
	 */
	public function test_public_static_method_calls_check_admin_referer_with_bulk_action_and_propagates_exception_when_bulk_nonce_invalid() {
		$_REQUEST = array(
			'_wpnonce' => 'bad-nonce',
			'action'   => $this->bulk_action_value(),
			'checked'  => array( 'some-other-plugin' ),
		);

		WP_Mock::userFunction( 'wp_verify_nonce' )
			->once()
			->with( 'bad-nonce', 'bulk-plugins' )
			->andReturn( false );

		WP_Mock::userFunction( 'check_admin_referer' )
			->once()
			->with( 'bulk-plugins' )
			->andThrow( new Exception( 'halted' ) );

		WP_Mock::userFunction( 'wp_cache_delete' )->never();
		WP_Mock::userFunction( 'delete_transient' )->never();
		WP_Mock::userFunction( 'flush_rewrite_rules' )->never();

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'halted' );

		$this->invoke_lifecycle_method();
	}

	/**
	 * When $_REQUEST has neither 'plugin' nor 'checked', the guard's if/elseif has no
	 * matching branch and no else -- check_admin_referer() is never called and execution
	 * falls straight through to the cleanup calls. This is the source's actual behavior,
	 * quirky as it is.
	 */
	public function test_public_static_method_proceeds_without_referer_check_when_neither_plugin_nor_checked_present() {
		$_REQUEST = array();

		WP_Mock::userFunction( 'check_admin_referer' )->never();

		WP_Mock::userFunction( 'wp_cache_delete' )->once()->with( 'comments-0', 'counts' );
		WP_Mock::userFunction( 'delete_transient' )->once()->with( 'wc_count_comments' );

		$flush_called = false;
		WP_Mock::userFunction( 'flush_rewrite_rules' )
			->once()
			->andReturnUsing(
				function () use ( &$flush_called ) {
					$flush_called = true;
				}
			);

		$this->invoke_lifecycle_method();

		$this->assertTrue( $flush_called );
	}

	public function test_public_static_method_skips_referer_check_and_runs_cleanup_when_fully_validated() {
		$_REQUEST = array(
			'_wpnonce' => 'nonce-value',
			'action'   => $this->single_action_value(),
			'plugin'   => 'disable-blog',
		);

		WP_Mock::userFunction( 'wp_verify_nonce' )
			->once()
			->with( 'nonce-value', $this->single_nonce_action_prefix() . 'disable-blog' )
			->andReturn( 1 );

		WP_Mock::userFunction( 'current_user_can' )
			->once()
			->with( 'activate_plugins' )
			->andReturn( true );

		WP_Mock::userFunction( 'check_admin_referer' )->never();

		WP_Mock::userFunction( 'wp_cache_delete' )->once()->with( 'comments-0', 'counts' );
		WP_Mock::userFunction( 'delete_transient' )->once()->with( 'wc_count_comments' );

		$flush_called = false;
		WP_Mock::userFunction( 'flush_rewrite_rules' )
			->once()
			->andReturnUsing(
				function () use ( &$flush_called ) {
					$flush_called = true;
				}
			);

		$this->invoke_lifecycle_method();

		$this->assertTrue( $flush_called );
	}
}
