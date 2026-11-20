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
}
