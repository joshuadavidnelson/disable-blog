<?php
/**
 * Tests for includes/class-disable-blog-loader.php.
 *
 * @package DisableBlog
 */

/**
 * A minimal stand-in for the real WP_Hook object that Disable_Blog_Loader::remove_filter()
 * reads via the global $wp_filter. Only exposes what the source actually touches:
 * a $callbacks array keyed by priority, and a remove_filter() method.
 */
class LoaderTestFakeWpHook {

	/**
	 * Callbacks keyed by priority, each shaped like WP_Hook::callbacks.
	 *
	 * @var array
	 */
	public $callbacks = array();

	/**
	 * Arguments captured from every remove_filter() call made against this fake.
	 *
	 * @var array
	 */
	public $remove_filter_calls = array();

	/**
	 * The value remove_filter() should report back.
	 *
	 * @var bool
	 */
	private $remove_filter_return;

	/**
	 * @param bool $remove_filter_return The value remove_filter() should report back.
	 */
	public function __construct( $remove_filter_return = true ) {
		$this->remove_filter_return = $remove_filter_return;
	}

	/**
	 * Records the call and reports back the configured result, like WP_Hook::remove_filter().
	 *
	 * @param string $tag                The hook name.
	 * @param array  $function_to_remove The [object, method] callback being removed.
	 * @param int    $priority           The priority it was registered at.
	 * @return bool
	 */
	public function remove_filter( $tag, $function_to_remove, $priority ) {
		$this->remove_filter_calls[] = array( $tag, $function_to_remove, $priority );

		return $this->remove_filter_return;
	}
}

/**
 * A throwaway component class used only so remove_filter()'s instanceof check has
 * something concrete to compare against.
 */
class LoaderTestFakeComponent {

	/**
	 * A stand-in callback method; never actually invoked by the tests.
	 */
	public function my_method() {}
}

/**
 * @covers Disable_Blog_Loader
 */
class LoaderTest extends TestCase {

	/**
	 * The loader instance under test.
	 *
	 * @var Disable_Blog_Loader
	 */
	private $loader;

	/**
	 * $wp_filter as it stood before the test, so it can be restored in tear_down().
	 *
	 * @var mixed
	 */
	private $original_wp_filter;

	protected function set_up() {
		parent::set_up();

		$this->loader = new Disable_Blog_Loader();

		global $wp_filter;
		$this->original_wp_filter = $wp_filter ?? null;
	}

	protected function tear_down() {
		global $wp_filter;
		$wp_filter = $this->original_wp_filter;

		parent::tear_down();
	}

	/**
	 * Reads a protected property off the loader via Reflection.
	 *
	 * @param string $name Property name.
	 * @return mixed
	 */
	private function get_loader_property( $name ) {
		$property = new ReflectionProperty( Disable_Blog_Loader::class, $name );
		$property->setAccessible( true );

		return $property->getValue( $this->loader );
	}

	/**
	 * The directory the real class-disable-blog-loader.php file lives in, computed the
	 * same way __DIR__ resolves inside that file, so the plugin_dir_path() stub can be
	 * asserted against the exact argument the source passes.
	 *
	 * @return string
	 */
	private function includes_dir() {
		return dirname( __DIR__, 2 ) . '/includes';
	}

	/**
	 * autoloader()
	 */

	/**
	 * A requested class whose mapped filename exists in includes/ must be included, making
	 * the class available. Disable_Blog_Functions is deliberately not required by the test
	 * bootstrap, so it starts undefined in a fresh process -- but DisableBlogFunctionsTest.php
	 * and DisableBlogTest.php both require it directly (outside bootstrap.php) to exercise it,
	 * and PHPUnit's suite discovery loads every *Test.php file up front regardless of run
	 * order. Isolated so this test's "not yet defined" assumption holds regardless: the
	 * child process only re-runs bootstrap.php plus this file, neither of which loads it (a
	 * second, non-include_once autoloader() include of an already-declared class would fatal
	 * on redeclaration).
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_autoloader_includes_file_for_a_class_it_should_load() {
		$includes_dir = $this->includes_dir();

		WP_Mock::userFunction( 'plugin_dir_path' )
			->once()
			->with( $includes_dir )
			->andReturn( dirname( $includes_dir ) . '/' );

		$this->assertFalse( class_exists( 'Disable_Blog_Functions', false ) );

		$this->loader->autoloader( 'Disable_Blog_Functions' );

		$this->assertTrue( class_exists( 'Disable_Blog_Functions', false ) );
	}

	/**
	 * A class name that maps to no file under includes/ must be silently ignored: no error,
	 * and the class never becomes defined.
	 */
	public function test_autoloader_ignores_a_class_with_no_matching_file() {
		$includes_dir = $this->includes_dir();

		WP_Mock::userFunction( 'plugin_dir_path' )
			->once()
			->with( $includes_dir )
			->andReturn( dirname( $includes_dir ) . '/' );

		$this->loader->autoloader( 'Totally_Unrelated_Non_Plugin_Class' );

		$this->assertFalse( class_exists( 'Totally_Unrelated_Non_Plugin_Class', false ) );
	}

	/**
	 * add_action() / add_filter()
	 */

	public function test_add_action_buffers_the_tuple_with_default_priority_and_args() {
		$component = new stdClass();

		$this->loader->add_action( 'init', $component, 'my_callback' );

		$this->assertSame(
			array(
				array(
					'hook'          => 'init',
					'component'     => $component,
					'callback'      => 'my_callback',
					'priority'      => 10,
					'accepted_args' => 1,
				),
			),
			$this->get_loader_property( 'actions' )
		);
	}

	public function test_add_filter_buffers_the_tuple_with_explicit_priority_and_args() {
		$component = new stdClass();

		$this->loader->add_filter( 'the_content', $component, 'my_filter', 20, 3 );

		$this->assertSame(
			array(
				array(
					'hook'          => 'the_content',
					'component'     => $component,
					'callback'      => 'my_filter',
					'priority'      => 20,
					'accepted_args' => 3,
				),
			),
			$this->get_loader_property( 'filters' )
		);
	}

	public function test_add_action_and_add_filter_append_independently_without_disturbing_each_other() {
		$component = new stdClass();

		$this->loader->add_filter( 'the_content', $component, 'filter_one' );
		$this->loader->add_action( 'init', $component, 'action_one' );
		$this->loader->add_filter( 'the_title', $component, 'filter_two', 5, 2 );

		$this->assertCount( 2, $this->get_loader_property( 'filters' ) );
		$this->assertCount( 1, $this->get_loader_property( 'actions' ) );
	}

	/**
	 * run()
	 */

	public function test_run_flushes_buffered_hooks_through_wordpress() {
		$filter_component = new stdClass();
		$action_component = new stdClass();

		$this->loader->add_filter( 'the_content', $filter_component, 'filter_cb', 20, 2 );
		$this->loader->add_action( 'init', $action_component, 'action_cb' );

		WP_Mock::expectFilterAdded( 'the_content', array( $filter_component, 'filter_cb' ), 20, 2 );
		WP_Mock::expectActionAdded( 'init', array( $action_component, 'action_cb' ), 10, 1 );

		$this->loader->run();

		WP_Mock::assertHooksAdded();
	}

	/**
	 * remove_filter() / remove_action()
	 */

	public function test_remove_filter_removes_matching_callback_at_matching_priority() {
		$component = new LoaderTestFakeComponent();
		$hook      = new LoaderTestFakeWpHook( true );
		$hook->callbacks[10] = array(
			array( 'function' => array( $component, 'my_method' ) ),
		);

		global $wp_filter;
		$wp_filter = array( 'some_tag' => $hook );

		$result = $this->loader->remove_filter( 'some_tag', LoaderTestFakeComponent::class, 'my_method', 10 );

		$this->assertTrue( $result );
		$this->assertSame(
			array( array( 'some_tag', array( $component, 'my_method' ), 10 ) ),
			$hook->remove_filter_calls
		);
	}

	public function test_remove_filter_defaults_to_priority_ten() {
		$component = new LoaderTestFakeComponent();
		$hook      = new LoaderTestFakeWpHook( true );
		$hook->callbacks[10] = array(
			array( 'function' => array( $component, 'my_method' ) ),
		);

		global $wp_filter;
		$wp_filter = array( 'some_tag' => $hook );

		// Priority omitted: must still match the default-priority-10 callback.
		$result = $this->loader->remove_filter( 'some_tag', LoaderTestFakeComponent::class, 'my_method' );

		$this->assertTrue( $result );
		$this->assertCount( 1, $hook->remove_filter_calls );
	}

	public function test_remove_filter_returns_false_when_priority_does_not_match() {
		$component = new LoaderTestFakeComponent();
		$hook      = new LoaderTestFakeWpHook( true );
		$hook->callbacks[20] = array(
			array( 'function' => array( $component, 'my_method' ) ),
		);

		global $wp_filter;
		$wp_filter = array( 'some_tag' => $hook );

		$result = $this->loader->remove_filter( 'some_tag', LoaderTestFakeComponent::class, 'my_method', 10 );

		$this->assertFalse( $result );
		$this->assertSame( array(), $hook->remove_filter_calls );
	}

	public function test_remove_filter_returns_false_when_method_name_does_not_match() {
		$component = new LoaderTestFakeComponent();
		$hook      = new LoaderTestFakeWpHook( true );
		$hook->callbacks[10] = array(
			array( 'function' => array( $component, 'a_different_method' ) ),
		);

		global $wp_filter;
		$wp_filter = array( 'some_tag' => $hook );

		$result = $this->loader->remove_filter( 'some_tag', LoaderTestFakeComponent::class, 'my_method', 10 );

		$this->assertFalse( $result );
		$this->assertSame( array(), $hook->remove_filter_calls );
	}

	public function test_remove_filter_returns_false_when_component_is_not_instance_of_class_name() {
		$component = new LoaderTestFakeComponent();
		$hook      = new LoaderTestFakeWpHook( true );
		$hook->callbacks[10] = array(
			array( 'function' => array( $component, 'my_method' ) ),
		);

		global $wp_filter;
		$wp_filter = array( 'some_tag' => $hook );

		// stdClass is unrelated to LoaderTestFakeComponent, so the instanceof check fails.
		$result = $this->loader->remove_filter( 'some_tag', stdClass::class, 'my_method', 10 );

		$this->assertFalse( $result );
		$this->assertSame( array(), $hook->remove_filter_calls );
	}

	public function test_remove_action_delegates_to_remove_filter_with_the_same_arguments() {
		$component = new LoaderTestFakeComponent();
		$hook      = new LoaderTestFakeWpHook( true );
		$hook->callbacks[10] = array(
			array( 'function' => array( $component, 'my_method' ) ),
		);

		global $wp_filter;
		$wp_filter = array( 'some_tag' => $hook );

		$result = $this->loader->remove_action( 'some_tag', LoaderTestFakeComponent::class, 'my_method', 10 );

		$this->assertTrue( $result );
		$this->assertSame(
			array( array( 'some_tag', array( $component, 'my_method' ), 10 ) ),
			$hook->remove_filter_calls
		);
	}
}
