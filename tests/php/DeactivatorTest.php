<?php
/**
 * Tests for includes/class-disable-blog-deactivator.php.
 *
 * @package DisableBlog
 */

/**
 * @covers Disable_Blog_Deactivator
 */
class DeactivatorTest extends PluginLifecycleTestCase {

	protected function target_class(): string {
		return Disable_Blog_Deactivator::class;
	}

	protected function lifecycle_method(): string {
		return 'deactivate';
	}

	protected function single_nonce_action_prefix(): string {
		return 'deactivate-plugin_';
	}

	protected function single_action_value(): string {
		return 'deactivate';
	}

	protected function bulk_action_value(): string {
		return 'deactivate-selected';
	}

	/**
	 * Directly sets Disable_Blog_Deactivator::$request via Reflection, bypassing
	 * get_request(), to simulate static state left over from an earlier, successful call.
	 *
	 * @param array $value The value to assign.
	 * @return void
	 */
	private function set_static_request( array $value ) {
		$class    = new ReflectionClass( Disable_Blog_Deactivator::class );
		$property = $class->getProperty( 'request' );
		$property->setAccessible( true );
		$property->setValue( null, $value );
	}

	/**
	 * deactivate()'s outer guard is `false === self::get_request() || ...`. get_request() only
	 * ever returns `false` or an array, never boolean `true`, so `true === self::get_request()`
	 * can never be satisfied by any input -- it would make the first OR clause permanently
	 * dead, silently changing the guard from "get_request() failed" (true whenever it returns
	 * false) to always-false, falling through to whatever validate_request()/check_caps()
	 * decide instead.
	 *
	 * This primes static::$request with data that would satisfy validate_request() and stubs
	 * check_caps() to succeed, then drives $_REQUEST down a path where get_request() itself
	 * fails (bad nonce) without touching static::$request. Under the real guard, get_request()
	 * failing alone is enough to enter the check_admin_referer() branch: the guard is an OR,
	 * so stale primed state that would satisfy validate_request() and check_caps() must not
	 * suppress the nonce check.
	 */
	public function test_deactivate_enters_referer_check_when_get_request_fails_even_if_stale_state_would_validate() {
		$this->set_static_request(
			array(
				'plugin' => 'disable-blog',
				'action' => 'deactivate',
			)
		);

		$_REQUEST = array(
			'_wpnonce' => 'bad-nonce',
			'action'   => 'deactivate',
			'plugin'   => 'disable-blog',
		);

		WP_Mock::userFunction( 'wp_verify_nonce' )
			->once()
			->with( 'bad-nonce', 'deactivate-plugin_disable-blog' )
			->andReturn( false );

		// Present so check_caps() resolves on the path where the guard wrongly defers to it.
		WP_Mock::userFunction( 'current_user_can' )
			->with( 'activate_plugins' )
			->andReturn( true );

		WP_Mock::userFunction( 'check_admin_referer' )
			->once()
			->with( 'deactivate-plugin_disable-blog' )
			->andThrow( new Exception( 'halted' ) );

		WP_Mock::userFunction( 'wp_cache_delete' )->never();
		WP_Mock::userFunction( 'delete_transient' )->never();
		WP_Mock::userFunction( 'flush_rewrite_rules' )->never();

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'halted' );

		Disable_Blog_Deactivator::deactivate();
	}

	/**
	 * validate_request()'s bulk branch calls
	 * `in_array( $plugin, self::$request['plugins'], true )`. If the strict flag is relaxed to
	 * false, a non-empty string like 'disable-blog' loosely equals boolean true, so a
	 * $_REQUEST['checked'] entry of `true` would (wrongly) validate as if 'disable-blog' were
	 * among the checked plugins.
	 */
	public function test_deactivate_runs_referer_check_when_bulk_selection_only_loosely_matches_plugin() {
		$_REQUEST = array(
			'_wpnonce' => 'nonce-value',
			'action'   => 'deactivate-selected',
			'checked'  => array( true ),
		);

		WP_Mock::userFunction( 'wp_verify_nonce' )
			->once()
			->with( 'nonce-value', 'bulk-plugins' )
			->andReturn( 1 );

		// Present so check_caps() resolves if the bulk selection wrongly validates.
		WP_Mock::userFunction( 'current_user_can' )
			->with( 'activate_plugins' )
			->andReturn( true );

		WP_Mock::userFunction( 'check_admin_referer' )
			->once()
			->with( 'bulk-plugins' )
			->andThrow( new Exception( 'halted' ) );

		WP_Mock::userFunction( 'wp_cache_delete' )->never();
		WP_Mock::userFunction( 'delete_transient' )->never();
		WP_Mock::userFunction( 'flush_rewrite_rules' )->never();

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'halted' );

		Disable_Blog_Deactivator::deactivate();
	}
}
