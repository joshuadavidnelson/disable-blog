<?php
/**
 * Shared base test case wiring WP_Mock into PHPUnit's lifecycle.
 *
 * @package DisableBlog
 */

/**
 * Base test case for the plugin's WP_Mock-based unit suite.
 */
class TestCase extends \Yoast\PHPUnitPolyfills\TestCases\TestCase {

	/**
	 * Boots WP_Mock and registers the common translation passthroughs before each test.
	 *
	 * @return void
	 */
	protected function set_up() {
		parent::set_up();

		WP_Mock::setUp();

		// Translation functions used throughout the plugin: pass the first argument through.
		WP_Mock::userFunction(
			'__',
			array(
				'return_arg' => 0,
			)
		);
		WP_Mock::userFunction(
			'_e',
			array(
				'return_arg' => 0,
			)
		);
		WP_Mock::userFunction(
			'esc_html__',
			array(
				'return_arg' => 0,
			)
		);
		WP_Mock::userFunction(
			'esc_html_e',
			array(
				'return_arg' => 0,
			)
		);
		WP_Mock::userFunction(
			'esc_attr__',
			array(
				'return_arg' => 0,
			)
		);
	}

	/**
	 * Tears down WP_Mock after each test.
	 *
	 * @return void
	 */
	protected function tear_down() {
		WP_Mock::tearDown();

		parent::tear_down();
	}

	/**
	 * Registers $filter so it asserts the exact value it receives is $expected before
	 * replying with $reply.
	 *
	 * WP_Mock::onFilter()->with() keys its matches on WP_Mock\Hook::safe_offset(), which
	 * casts scalars via (string) -- so safe_offset( true ) === safe_offset( 1 ) === '1',
	 * safe_offset( false ) === safe_offset( '' ) === safe_offset( array() ) === '', and
	 * safe_offset( array( 'x' ) ) === safe_offset( 'x' ). A plain ->with( true ) therefore
	 * still matches when the source passes a loosely-equal value instead of the literal
	 * the filter documents. Mockery::on() cannot fix it either: onFilter() is not
	 * Mockery-backed, so a matcher object is just another value safe_offset() stringifies.
	 * WP_Mock\InvokedFilterValue's callback receives the real invoked argument via
	 * func_get_args() regardless of which (possibly collided) key matched, so the value
	 * can be asserted strictly.
	 *
	 * @param string $filter   The filter hook name.
	 * @param mixed  $expected The exact value the filter must receive.
	 * @param mixed  $reply    The value the filter should return.
	 * @return void
	 */
	protected function stub_filter_strict( $filter, $expected, $reply ) {
		WP_Mock::onFilter( $filter )->with( $expected )->reply(
			new WP_Mock\InvokedFilterValue(
				function ( $value ) use ( $filter, $expected, $reply ) {
					$this->assertSame( $expected, $value, "{$filter} must receive the exact value it documents." );

					return $reply;
				}
			)
		);
	}
}
