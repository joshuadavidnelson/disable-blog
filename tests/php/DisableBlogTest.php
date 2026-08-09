<?php
/**
 * Tests for includes/class-disable-blog.php.
 *
 * @package DisableBlog
 */

// None of these four are required by tests/php/bootstrap.php on purpose (see LoaderTest's
// autoloader test, which needs Disable_Blog_Functions specifically to remain undefined
// until its own isolated process); load them here instead. require_once is safe regardless
// of which test file happens to load first.
require_once __DIR__ . '/../../includes/class-disable-blog-functions.php';
require_once __DIR__ . '/../../includes/class-disable-blog-admin.php';
require_once __DIR__ . '/../../includes/class-disable-blog-public.php';
require_once __DIR__ . '/../../includes/class-disable-blog.php';

/**
 * Records the order boot()'s six setup calls run in, without performing any of their real
 * work. upgrade_check() is protected static, so the recorder has to be a static property:
 * a static method has no $this to push onto an instance array.
 */
class Disable_Blog_Boot_Recorder extends Disable_Blog {

	/**
	 * @var string[]
	 */
	public static $calls = array();

	protected static function upgrade_check() {
		self::$calls[] = 'upgrade_check';
	}

	protected function load_dependencies() {
		self::$calls[] = 'load_dependencies';
	}

	protected function set_locale() {
		self::$calls[] = 'set_locale';
	}

	public function plugin_integrations() {
		self::$calls[] = 'plugin_integrations';
	}

	protected function define_admin_hooks() {
		self::$calls[] = 'define_admin_hooks';
	}

	protected function define_public_hooks() {
		self::$calls[] = 'define_public_hooks';
	}
}

/**
 * @covers Disable_Blog
 */
class DisableBlogTest extends TestCase {

	/**
	 * Builds a Disable_Blog instance without running its constructor.
	 *
	 * The real constructor is not exercised here: it calls load_dependencies(), which uses
	 * Disable_Blog_Loader::autoloader() to `include` (not `include_once`) the source files
	 * for Disable_Blog_I18n and Disable_Blog_Integrations -- both of which
	 * tests/php/bootstrap.php already `require`s unconditionally for every other test file in
	 * this suite. Calling the constructor for real would therefore fatal on class
	 * redeclaration in this process, every time, with no isolation trick available (a fresh
	 * @runInSeparateProcess still runs the same bootstrap.php first). See
	 * test_upgrade_check_*, plugin_integrations() and the define_*_hooks() tests below for
	 * what's covered instead by driving the class's methods directly via Reflection.
	 *
	 * @param string               $plugin_name The plugin name.
	 * @param string               $version     The plugin version.
	 * @param Disable_Blog_Loader|null $loader  The loader to inject. Defaults to a new instance.
	 * @return Disable_Blog
	 */
	private function make_disable_blog( $plugin_name = 'disable-blog', $version = '0.5.6', $loader = null ) {
		$reflection = new ReflectionClass( Disable_Blog::class );
		$instance   = $reflection->newInstanceWithoutConstructor();

		$this->set_property( $instance, 'plugin_name', $plugin_name );
		$this->set_property( $instance, 'version', $version );
		$this->set_property( $instance, 'loader', $loader ? $loader : new Disable_Blog_Loader() );

		return $instance;
	}

	/**
	 * @param object $object Target object.
	 * @param string $name   Property name.
	 * @param mixed  $value  Value to assign.
	 * @return void
	 */
	private function set_property( $object, $name, $value ) {
		$property = new ReflectionProperty( Disable_Blog::class, $name );
		$property->setAccessible( true );
		$property->setValue( $object, $value );
	}

	/**
	 * @param object $object Target object.
	 * @param string $name   Property name.
	 * @return mixed
	 */
	private function get_property( $object, $name ) {
		$property = new ReflectionProperty( Disable_Blog::class, $name );
		$property->setAccessible( true );

		return $property->getValue( $object );
	}

	/**
	 * Invokes a private instance method via Reflection.
	 *
	 * @param object $object Target object.
	 * @param string $method Method name.
	 * @param array  $args   Positional arguments.
	 * @return mixed
	 */
	private function invoke_private( $object, $method, array $args = array() ) {
		$reflection = new ReflectionMethod( $object, $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( $object, $args );
	}

	/**
	 * Invokes Disable_Blog's private static upgrade_check() via Reflection.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Positional arguments.
	 * @return mixed
	 */
	private function invoke_private_static( $method, array $args = array() ) {
		$reflection = new ReflectionMethod( Disable_Blog::class, $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( null, $args );
	}

	/**
	 * Reads a protected property (actions/filters) off a Disable_Blog_Loader via Reflection.
	 *
	 * @param Disable_Blog_Loader $loader The loader instance.
	 * @param string               $name  'actions' or 'filters'.
	 * @return array
	 */
	private function get_loader_hooks( Disable_Blog_Loader $loader, $name ) {
		$property = new ReflectionProperty( Disable_Blog_Loader::class, $name );
		$property->setAccessible( true );

		return $property->getValue( $loader );
	}

	/**
	 * Reduces a loader's raw hook entries to deterministic [hook, component class,
	 * callback, priority, accepted_args] tuples, so they can be compared with assertSame()
	 * without relying on object identity.
	 *
	 * @param array $hooks Raw entries as stored by Disable_Blog_Loader::add().
	 * @return array
	 */
	private function reduce_hooks( array $hooks ) {
		return array_map(
			static function ( $hook ) {
				return array(
					$hook['hook'],
					get_class( $hook['component'] ),
					$hook['callback'],
					$hook['priority'],
					$hook['accepted_args'],
				);
			},
			$hooks
		);
	}

	/**
	 * __construct() / boot()
	 */

	/**
	 * Uses two clearly distinct values so a swap between the two assignments in
	 * __construct() shows up as one getter returning the other's value.
	 */
	public function test_construct_assigns_plugin_name_and_version_to_the_correct_properties() {
		$disable_blog = new Disable_Blog( 'disable-blog', '0.5.6', false );

		$this->assertSame( 'disable-blog', $disable_blog->get_plugin_name() );
		$this->assertSame( '0.5.6', $disable_blog->get_version() );
	}

	/**
	 * boot() fires 'dwpb_init' before running any of its six setup calls -- third parties
	 * hooking dwpb_init depend on it running ahead of load_dependencies() and the rest.
	 * Asserting the full sequence (not just that all seven happened) is what catches a
	 * reordering, not only a deletion.
	 */
	public function test_boot_runs_dwpb_init_then_the_six_setup_methods_in_order() {
		Disable_Blog_Boot_Recorder::$calls = array();

		// do_action() is defined by WP_Mock itself (not stubbable via userFunction()) and
		// dispatches through the event manager; with( null ) matches the no-extra-args call
		// boot() makes.
		WP_Mock::onAction( 'dwpb_init' )->with( null )->perform(
			static function () {
				Disable_Blog_Boot_Recorder::$calls[] = 'dwpb_init';
			}
		);

		$disable_blog = new Disable_Blog_Boot_Recorder( 'disable-blog', '0.5.6', false );
		$disable_blog->boot();

		$this->assertSame(
			array(
				'dwpb_init',
				'upgrade_check',
				'load_dependencies',
				'set_locale',
				'plugin_integrations',
				'define_admin_hooks',
				'define_public_hooks',
			),
			Disable_Blog_Boot_Recorder::$calls
		);
	}

	/**
	 * load_dependencies()
	 */

	/**
	 * Runs load_dependencies() for real, with a spy loader injected first so the method's
	 * null-check leaves it in place instead of constructing a Disable_Blog_Loader. Wrong
	 * directory or file names make the require_once calls fatal (they resolve to real,
	 * already-loaded files under correct paths, so nothing observable would otherwise
	 * distinguish a corrupted path from a correct one). The spy also records the exact
	 * classes handed to the loader's autoloader, in order.
	 */
	public function test_load_dependencies_resolves_real_files_and_registers_expected_classes_in_order() {
		$repo_root        = dirname( __DIR__, 2 );
		$expected_dir_arg = $repo_root . '/includes';

		WP_Mock::userFunction( 'plugin_dir_path' )
			->once()
			->with( $expected_dir_arg )
			->andReturn( $repo_root . '/' );

		$spy_loader = new class() extends Disable_Blog_Loader {
			/**
			 * @var string[]
			 */
			public $autoloaded = array();

			public function autoloader( $requested_class ) {
				$this->autoloaded[] = $requested_class;
			}
		};

		$disable_blog = $this->make_disable_blog( 'disable-blog', '0.5.6', $spy_loader );

		$this->invoke_private( $disable_blog, 'load_dependencies' );

		$this->assertSame(
			array(
				'Disable_Blog_I18n',
				'Disable_Blog_Functions',
				'Disable_Blog_Admin',
				'Disable_Blog_Public',
				'Disable_Blog_Integrations',
			),
			$spy_loader->autoloaded
		);
	}

	/**
	 * define_admin_hooks() / define_public_hooks()
	 */

	/**
	 * Drives both hook-registration methods against a real, unmocked Disable_Blog_Loader
	 * and asserts the exact wiring they buffer into it -- including the two raw add_filter()
	 * calls in define_admin_hooks() that bypass the loader entirely. The comments-supported
	 * branch is forced on (via a cache-hit stub for dwpb_post_types_with_feature('comments'))
	 * so all 49 hooks between the two methods are exercised; the conditional block is
	 * covered separately below with it forced off.
	 */
	public function test_define_admin_and_public_hooks_register_expected_wiring_when_comments_supported() {
		WP_Mock::userFunction( 'esc_attr' )->with( 'comments' )->andReturn( 'comments' );
		WP_Mock::userFunction( 'wp_cache_get' )
			->once()
			->with( 'post-types-supporting-comments', 'post-types-by-feature' )
			->andReturn( array( 'page' ) );
		WP_Mock::onFilter( 'dwpb_post_types_supporting_comments' )
			->with( array( 'page' ), array() )
			->reply( array( 'page' ) );

		WP_Mock::expectFilterAdded( 'enable_update_services_configuration', '__return_false' );
		WP_Mock::expectFilterAdded( 'enable_post_by_email_configuration', '__return_false' );

		$disable_blog = $this->make_disable_blog();

		$this->invoke_private( $disable_blog, 'define_admin_hooks' );
		$this->invoke_private( $disable_blog, 'define_public_hooks' );

		WP_Mock::assertHooksAdded();

		$loader  = $this->get_property( $disable_blog, 'loader' );
		$actions = $this->reduce_hooks( $this->get_loader_hooks( $loader, 'actions' ) );
		$filters = $this->reduce_hooks( $this->get_loader_hooks( $loader, 'filters' ) );

		$admin  = Disable_Blog_Admin::class;
		$public = Disable_Blog_Public::class;

		$expected_actions = array(
			// define_admin_hooks().
			array( 'admin_enqueue_scripts', $admin, 'enqueue_styles', 10, 1 ),
			array( 'admin_enqueue_scripts', $admin, 'enqueue_scripts', 100, 1 ),
			array( 'admin_menu', $admin, 'remove_menu_pages', 10, 1 ),
			array( 'init', $admin, 'modify_post_type_arguments', 25, 1 ),
			array( 'init', $admin, 'modify_taxonomies_arguments', 25, 1 ),
			array( 'current_screen', $admin, 'redirect_admin_pages', 10, 1 ),
			array( 'pings_open', $admin, 'filter_comment_status', 20, 2 ),
			array( 'wp_before_admin_bar_render', $admin, 'remove_admin_bar_links', 10, 1 ),
			array( 'admin_init', $admin, 'remove_dashboard_widgets', 10, 1 ),
			array( 'admin_notices', $admin, 'admin_notices', 10, 1 ),
			array( 'load-press-this.php', $admin, 'disable_press_this', 10, 1 ),
			array( 'widgets_init', $admin, 'remove_widgets', 10, 1 ),
			array( 'manage_users_columns', $admin, 'manage_users_columns', 10, 1 ),
			array( 'customize_controls_print_styles', $admin, 'customizer_styles', 999, 1 ),
			array( 'customize_controls_enqueue_scripts', $admin, 'customizer_scripts', 999, 1 ),
			array( 'post_edit_form_tag', $admin, 'update_posts_page_notice', 10, 1 ),
			// comments-supported conditional block.
			array( 'comments_open', $admin, 'filter_comment_status', 20, 2 ),
			array( 'pre_get_comments', $admin, 'comment_filter', 10, 1 ),
			// define_public_hooks().
			array( 'template_redirect', $public, 'redirect_public_pages', 10, 1 ),
			array( 'pre_get_posts', $public, 'modify_query', 9, 1 ),
			array( 'do_feed', $public, 'disable_feed', 1, 2 ),
			array( 'do_feed_rdf', $public, 'disable_feed', 1, 2 ),
			array( 'do_feed_rss', $public, 'disable_feed', 1, 2 ),
			array( 'do_feed_rss2', $public, 'disable_feed', 1, 2 ),
			array( 'do_feed_atom', $public, 'disable_feed', 1, 2 ),
			array( 'wp_loaded', $public, 'header_feeds', 1, 1 ),
			array( 'wp', $public, 'remove_pingback_header_fallback', 10, 1 ),
			array( 'template_redirect', $public, 'disable_removed_sitemaps', 9, 1 ),
		);

		$expected_filters = array(
			// define_admin_hooks().
			array( 'plugin_row_meta', $admin, 'plugin_links', 10, 2 ),
			array( 'admin_body_class', $admin, 'admin_body_class', 10, 1 ),
			array( 'dwpb_unregister_widgets', $admin, 'filter_widget_removal', 10, 2 ),
			array( 'display_post_states', $admin, 'page_post_states', 10, 2 ),
			array( 'site_status_tests', $admin, 'site_status_tests', 10, 1 ),
			array( 'manage_users_custom_column', $admin, 'manage_users_custom_column', 10, 3 ),
			array( 'user_row_actions', $admin, 'user_row_actions', 10, 2 ),
			array( 'post_tag_row_actions', $admin, 'filter_taxonomy_count', 10, 2 ),
			array( 'category_row_actions', $admin, 'filter_taxonomy_count', 10, 2 ),
			array( 'available_permalink_structure_tags', $admin, 'available_permalink_structure_tags', 10, 1 ),
			array( 'block_type_metadata', $admin, 'filter_block_type_metadata', 10, 1 ),
			// comments-supported conditional block.
			array( 'views_edit-comments', $admin, 'filter_admin_table_comment_count', 20, 1 ),
			array( 'wp_count_comments', $admin, 'filter_wp_count_comments', 10, 2 ),
			array( 'comments_array', $admin, 'filter_existing_comments', 20, 2 ),
			// define_public_hooks().
			array( 'wp_headers', $public, 'filter_wp_headers', 10, 1 ),
			array( 'feed_links_show_posts_feed', $public, 'feed_links_show_posts_feed', 10, 1 ),
			array( 'feed_links_show_comments_feed', $public, 'feed_links_show_comments_feed', 10, 1 ),
			array( 'xmlrpc_methods', $public, 'xmlrpc_methods', 10, 1 ),
			array( 'wp_sitemaps_post_types', $public, 'wp_sitemaps_post_types', 10, 1 ),
			array( 'wp_sitemaps_taxonomies', $public, 'wp_sitemaps_taxonomies', 10, 1 ),
			array( 'wp_sitemaps_add_provider', $public, 'wp_author_sitemaps', 100, 2 ),
		);

		$this->assertSame( $expected_actions, $actions );
		$this->assertSame( $expected_filters, $filters );
		$this->assertCount( 49, array_merge( $actions, $filters ) );
	}

	/**
	 * With no post type supporting comments, the five comment-specific hooks (two actions,
	 * three filters) inside define_admin_hooks()'s conditional block must be absent, while
	 * everything else registers as usual.
	 */
	public function test_define_admin_hooks_skips_comment_related_hooks_when_no_post_type_supports_comments() {
		WP_Mock::userFunction( 'esc_attr' )->with( 'comments' )->andReturn( 'comments' );
		WP_Mock::userFunction( 'wp_cache_get' )->once()->andReturn( false );
		WP_Mock::userFunction( 'get_post_types' )->once()->with( array(), 'names' )->andReturn( array( 'post' ) );
		WP_Mock::userFunction( 'post_type_supports' )->with( 'post', 'comments' )->andReturn( true );
		WP_Mock::userFunction( 'wp_cache_set' )->once();
		WP_Mock::onFilter( 'dwpb_post_types_supporting_comments' )->with( false, array() )->reply( false );

		WP_Mock::expectFilterAdded( 'enable_update_services_configuration', '__return_false' );
		WP_Mock::expectFilterAdded( 'enable_post_by_email_configuration', '__return_false' );

		$disable_blog = $this->make_disable_blog();

		$this->invoke_private( $disable_blog, 'define_admin_hooks' );

		$loader  = $this->get_property( $disable_blog, 'loader' );
		$actions = $this->reduce_hooks( $this->get_loader_hooks( $loader, 'actions' ) );
		$filters = $this->reduce_hooks( $this->get_loader_hooks( $loader, 'filters' ) );

		$this->assertCount( 16, $actions );
		$this->assertCount( 11, $filters );

		foreach ( array( 'comments_open', 'pre_get_comments' ) as $hook ) {
			$this->assertNotContains( $hook, array_column( $actions, 0 ) );
		}
		foreach ( array( 'views_edit-comments', 'wp_count_comments', 'comments_array' ) as $hook ) {
			$this->assertNotContains( $hook, array_column( $filters, 0 ) );
		}
	}

	/**
	 * plugin_integrations()
	 */

	public function test_plugin_integrations_disables_comments_filter_when_disable_comments_plugin_active() {
		WP_Mock::userFunction( 'is_plugin_active' )->with( 'disable-comments/disable-comments.php' )->andReturn( true );
		WP_Mock::userFunction( 'is_plugin_active' )->with( 'woocommerce/woocommerce.php' )->andReturn( false );

		WP_Mock::expectFilterAdded( 'dwpb_post_types_supporting_comments', '__return_false' );

		$disable_blog = $this->make_disable_blog();

		$disable_blog->plugin_integrations();

		WP_Mock::assertHooksAdded();

		// The WooCommerce branch never runs, so nothing reaches the loader.
		$loader = $this->get_property( $disable_blog, 'loader' );
		$this->assertSame( array(), $this->get_loader_hooks( $loader, 'filters' ) );
	}

	public function test_plugin_integrations_registers_woocommerce_comment_count_filter_when_active_and_comments_supported() {
		WP_Mock::userFunction( 'is_plugin_active' )->with( 'disable-comments/disable-comments.php' )->andReturn( false );
		WP_Mock::userFunction( 'is_plugin_active' )->with( 'woocommerce/woocommerce.php' )->andReturn( true );

		WP_Mock::userFunction( 'esc_attr' )->with( 'comments' )->andReturn( 'comments' );
		WP_Mock::userFunction( 'wp_cache_get' )
			->once()
			->with( 'post-types-supporting-comments', 'post-types-by-feature' )
			->andReturn( array( 'page' ) );
		WP_Mock::onFilter( 'dwpb_post_types_supporting_comments' )
			->with( array( 'page' ), array() )
			->reply( array( 'page' ) );

		$disable_blog = $this->make_disable_blog();

		$disable_blog->plugin_integrations();

		$loader  = $this->get_property( $disable_blog, 'loader' );
		$filters = $this->reduce_hooks( $this->get_loader_hooks( $loader, 'filters' ) );

		$this->assertSame(
			array(
				array( 'wp_count_comments', Disable_Blog_Integrations::class, 'filter_woocommerce_comment_count', 15, 2 ),
			),
			$filters
		);
	}

	public function test_plugin_integrations_does_nothing_when_neither_integration_active() {
		WP_Mock::userFunction( 'is_plugin_active' )->with( 'disable-comments/disable-comments.php' )->andReturn( false );
		WP_Mock::userFunction( 'is_plugin_active' )->with( 'woocommerce/woocommerce.php' )->andReturn( false );

		$disable_blog = $this->make_disable_blog();

		$disable_blog->plugin_integrations();

		$loader = $this->get_property( $disable_blog, 'loader' );
		$this->assertSame( array(), $this->get_loader_hooks( $loader, 'filters' ) );
	}

	/**
	 * upgrade_check() (private static)
	 */

	/**
	 * DWPB_VERSION is never referenced on this path: the function returns before that line
	 * is reached, so this is the one upgrade_check() test that needs no isolation.
	 */
	public function test_upgrade_check_bails_when_not_admin() {
		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( false );
		WP_Mock::userFunction( 'get_option' )->never();
		WP_Mock::userFunction( 'update_option' )->never();

		$this->assertNull( $this->invoke_private_static( 'upgrade_check' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_upgrade_check_sets_version_option_on_fresh_install() {
		define( 'DWPB_VERSION', '0.5.6' );

		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( true );
		WP_Mock::userFunction( 'get_option' )->once()->with( 'dwpb_version', false )->andReturn( false );
		WP_Mock::userFunction( 'update_option' )->once()->with( 'dwpb_version', '0.5.6', false );

		$this->assertNull( $this->invoke_private_static( 'upgrade_check' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_upgrade_check_does_nothing_when_version_matches() {
		define( 'DWPB_VERSION', '0.5.6' );

		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( true );
		WP_Mock::userFunction( 'get_option' )->once()->with( 'dwpb_version', false )->andReturn( '0.5.6' );
		WP_Mock::userFunction( 'update_option' )->never();

		$this->assertNull( $this->invoke_private_static( 'upgrade_check' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_upgrade_check_records_previous_version_and_updates_when_upgrading() {
		define( 'DWPB_VERSION', '0.5.6' );

		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( true );
		WP_Mock::userFunction( 'get_option' )->once()->with( 'dwpb_version', false )->andReturn( '0.5.4' );

		WP_Mock::userFunction( 'update_option' )->once()->with( 'dwpb_previous_version', '0.5.4', false );
		WP_Mock::userFunction( 'update_option' )->once()->with( 'dwpb_version', '0.5.6', false );

		$this->assertNull( $this->invoke_private_static( 'upgrade_check' ) );
	}

	/**
	 * get_plugin_name() / get_loader() / get_version()
	 */

	public function test_getters_return_constructor_assigned_properties() {
		$loader       = new Disable_Blog_Loader();
		$disable_blog = $this->make_disable_blog( 'disable-blog', '9.9.9', $loader );

		$this->assertSame( 'disable-blog', $disable_blog->get_plugin_name() );
		$this->assertSame( '9.9.9', $disable_blog->get_version() );
		$this->assertSame( $loader, $disable_blog->get_loader() );
	}

	/**
	 * set_locale()
	 */

	public function test_set_locale_registers_i18n_load_plugin_textdomain_on_plugins_loaded() {
		$disable_blog = $this->make_disable_blog();

		$this->invoke_private( $disable_blog, 'set_locale' );

		$loader  = $this->get_property( $disable_blog, 'loader' );
		$actions = $this->reduce_hooks( $this->get_loader_hooks( $loader, 'actions' ) );

		$this->assertSame(
			array(
				array( 'plugins_loaded', Disable_Blog_I18n::class, 'load_plugin_textdomain', 10, 1 ),
			),
			$actions
		);
	}

	/**
	 * run()
	 */

	public function test_run_delegates_to_the_loader() {
		$disable_blog = $this->make_disable_blog();
		$loader       = $this->get_property( $disable_blog, 'loader' );

		// A hook buffered directly on the real loader, so run() can be proven to flush it
		// through to WordPress for real, not just call some mock's run() method. A real
		// object with a real method, because the tuple passed to
		// WP_Mock::expectActionAdded() below must be a genuine callable per its docblock
		// type; WP_Mock never actually invokes it.
		$component = new class() {
			public function noop() {}
		};
		$loader->add_action( 'init', $component, 'noop' );

		WP_Mock::expectActionAdded( 'init', array( $component, 'noop' ), 10, 1 );

		$disable_blog->run();

		WP_Mock::assertHooksAdded();
	}
}
