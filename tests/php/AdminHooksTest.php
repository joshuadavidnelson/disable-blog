<?php
/**
 * Tests for the remaining Disable_Blog_Admin hook-callback surface: remove_menu_pages(),
 * remove_dashboard_widgets(), remove_widgets(), filter_widget_removal(),
 * remove_admin_bar_links(), remove_writing_options(), admin_body_class(), has_front_page(),
 * disable_press_this(), enqueue_styles(), enqueue_scripts(), customizer_styles(),
 * customizer_scripts() and plugin_links().
 *
 * @package DisableBlog
 */

// Not required by tests/php/bootstrap.php on purpose (see ConstructorInjectionTest.php's note;
// LoaderTest's autoloader test needs Disable_Blog_Functions to remain undefined until its own
// isolated process). require_once is safe regardless of which test file happens to load first.
require_once __DIR__ . '/../../includes/class-disable-blog-functions.php';

// DWPB_URL is only ever defined by disable-blog.php, which this suite never loads (see
// phpstan.neon.dist's "Constant DWPB_URL not found" ignore, added for the same reason).
// enqueue_scripts() and customizer_scripts() both read it directly, so it must exist before
// either runs; guarded so re-running this file within the same process is safe.
if ( ! defined( 'DWPB_URL' ) ) {
	define( 'DWPB_URL', 'https://example.test/wp-content/plugins/disable-blog/' );
}

/**
 * @covers Disable_Blog_Admin::remove_menu_pages
 * @covers Disable_Blog_Admin::remove_dashboard_widgets
 * @covers Disable_Blog_Admin::remove_widgets
 * @covers Disable_Blog_Admin::filter_widget_removal
 * @covers Disable_Blog_Admin::remove_admin_bar_links
 * @covers Disable_Blog_Admin::remove_writing_options
 * @covers Disable_Blog_Admin::admin_body_class
 * @covers Disable_Blog_Admin::has_front_page
 * @covers Disable_Blog_Admin::disable_press_this
 * @covers Disable_Blog_Admin::enqueue_styles
 * @covers Disable_Blog_Admin::enqueue_scripts
 * @covers Disable_Blog_Admin::customizer_styles
 * @covers Disable_Blog_Admin::customizer_scripts
 * @covers Disable_Blog_Admin::plugin_links
 */
class AdminHooksTest extends TestCase {

	/**
	 * The global $wp_admin_bar as it stood before the test, so it can be restored in tear_down().
	 *
	 * @var mixed
	 */
	private $original_wp_admin_bar;

	/**
	 * The global $pagenow as it stood before the test, so it can be restored in tear_down().
	 *
	 * @var mixed
	 */
	private $original_pagenow;

	protected function set_up() {
		parent::set_up();

		global $wp_admin_bar, $pagenow;
		$this->original_wp_admin_bar = $wp_admin_bar ?? null;
		$this->original_pagenow      = $pagenow ?? null;
		$wp_admin_bar                = null;
		$pagenow                     = null;
	}

	protected function tear_down() {
		global $wp_admin_bar, $pagenow;
		$wp_admin_bar = $this->original_wp_admin_bar;
		$pagenow      = $this->original_pagenow;

		parent::tear_down();
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
		WP_Mock::userFunction( 'get_post_types' )
			->with(
				Mockery::on(
					function ( $args ) {
						return array() === $args;
					}
				),
				'names'
			)
			->andReturn( array() );
		WP_Mock::userFunction( 'wp_cache_get' )->andReturn( false );
		WP_Mock::userFunction( 'wp_cache_set' )->andReturn( null );
		WP_Mock::userFunction( 'maybe_serialize' )
			->with(
				Mockery::on(
					function ( $args ) {
						return array() === $args;
					}
				)
			)
			->andReturn( 'a:0:{}' );
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
			->reply(
				new WP_Mock\InvokedFilterValue(
					function ( $null, $tax, $post_types, $args, $output ) use ( $return_value ) {
						$this->assertSame( array(), $post_types, 'dwpb_taxonomy_support must receive the exact $post_types array it documents.' );
						$this->assertSame( array(), $args, 'dwpb_taxonomy_support must receive the exact $args array it documents.' );

						return $return_value;
					}
				)
			);
	}

	/**
	 * Forces dwpb_post_types_with_feature( $feature ) to return $return_value.
	 *
	 * wp_cache_get() returning ANY array (even an empty one) is a cache HIT as far as
	 * dwpb_post_types_with_feature() is concerned -- it skips get_post_types()/
	 * post_type_supports() entirely and passes the cached value straight to the
	 * dwpb_post_types_supporting_{$feature} filter, whose reply IS the function's return
	 * value regardless of what was "cached".
	 *
	 * @param string     $feature      The feature slug (e.g. 'comments').
	 * @param array|bool $return_value The value dwpb_post_types_with_feature() should return.
	 * @return void
	 */
	private function stub_feature_cache_hit( $feature, $return_value ) {
		WP_Mock::userFunction( 'esc_attr' )->with( $feature )->andReturn( $feature );
		WP_Mock::userFunction( 'wp_cache_get' )
			->with( "post-types-supporting-{$feature}", 'post-types-by-feature' )
			->andReturn( array() );
		WP_Mock::onFilter( "dwpb_post_types_supporting_{$feature}" )
			->with( array(), array() )
			->reply(
				new WP_Mock\InvokedFilterValue(
					function ( $post_types_with_feature, $args ) use ( $return_value ) {
						// The cached empty array is normalized to false before it reaches
						// this filter -- safe_offset() string-casts both to '', so WP_Mock's
						// ->with( array(), array() ) above (needed to route here at all)
						// cannot by itself tell the two apart. assertSame() can.
						$this->assertFalse( $post_types_with_feature );
						$this->assertSame( array(), $args );
						return $return_value;
					}
				)
			);
	}

	/**
	 * Forces remove_writing_options()'s dwpb_remove_options_writing filter to $return_value.
	 *
	 * @param bool $return_value The value the filter should reply with.
	 * @return void
	 */
	private function stub_remove_writing_options( $return_value ) {
		WP_Mock::onFilter( 'dwpb_remove_options_writing' )->with( false )->reply(
			new WP_Mock\InvokedFilterValue(
				function ( $default ) use ( $return_value ) {
					// safe_offset( false ) === safe_offset( '' ), so the ->with( false )
					// above routes here for either value; assertSame() confirms the real
					// argument really is the boolean default, not a loosely-equal string.
					$this->assertFalse( $default );
					return $return_value;
				}
			)
		);
	}

	/**
	 * Forces remove_menu_pages()'s dwpb_menu_pages_to_remove filter, asserting the real
	 * argument is the full array WP_Mock routed on and not merely a value that flattens to
	 * the same safe_offset() string (a one-element array of a string collides with that bare
	 * string under safe_offset()).
	 *
	 * @param array $expected_pages The exact pages array $remove_pages must equal.
	 * @param array $return_value   The value the filter should reply with.
	 * @return void
	 */
	private function stub_menu_pages_to_remove( array $expected_pages, array $return_value ) {
		WP_Mock::onFilter( 'dwpb_menu_pages_to_remove' )->with( $expected_pages )->reply(
			new WP_Mock\InvokedFilterValue(
				function ( $pages ) use ( $expected_pages, $return_value ) {
					$this->assertSame( $expected_pages, $pages );
					return $return_value;
				}
			)
		);
	}

	/**
	 * Stubs a single-argument filter, asserting via InvokedFilterValue that the real
	 * argument WP_Mock routed on strictly (===) matches $expected_arg, rather than merely
	 * matching loosely (==) as safe_offset()'s string-cast routing key would otherwise
	 * allow -- e.g. bool true and int 1 both safe_offset() to the same key.
	 *
	 * @param string $hook         The filter hook name.
	 * @param mixed  $expected_arg The exact value apply_filters() must be called with.
	 * @param mixed  $return_value The value the filter should reply with.
	 * @return void
	 */
	private function stub_strict_filter( $hook, $expected_arg, $return_value ) {
		WP_Mock::onFilter( $hook )->with( $expected_arg )->reply(
			new WP_Mock\InvokedFilterValue(
				function ( $actual_arg ) use ( $expected_arg, $return_value ) {
					$this->assertSame( $expected_arg, $actual_arg );
					return $return_value;
				}
			)
		);
	}

	/**
	 * Stubs remove_widgets()'s dwpb_unregister_widgets filter for a single widget class,
	 * asserting the real first argument is strictly the boolean default (bool true and int 1
	 * both safe_offset() to the same routing key).
	 *
	 * @param string $widget       The widget class name.
	 * @param bool   $return_value The value the filter should reply with.
	 * @return void
	 */
	private function stub_unregister_widget_filter( $widget, $return_value ) {
		WP_Mock::onFilter( 'dwpb_unregister_widgets' )->with( true, $widget )->reply(
			new WP_Mock\InvokedFilterValue(
				function ( $default, $actual_widget ) use ( $widget, $return_value ) {
					$this->assertTrue( $default );
					$this->assertSame( $widget, $actual_widget );
					return $return_value;
				}
			)
		);
	}

	/**
	 * remove_menu_pages()
	 */

	/**
	 * With comments supported elsewhere and the writing page kept, only edit.php and its
	 * tools.php submenu entry are removed.
	 */
	public function test_remove_menu_pages_removes_only_edit_and_tools_by_default() {
		$this->stub_feature_cache_hit( 'comments', array( 'page' ) );
		$this->stub_remove_writing_options( false );

		$this->stub_menu_pages_to_remove( array( 'edit.php' ), array( 'edit.php' ) );
		WP_Mock::userFunction( 'remove_menu_page' )->once()->with( 'edit.php' );

		$expected_subpages = array(
			'tools.php'           => array( 'tools.php' ),
			'options-general.php' => array(),
		);
		WP_Mock::onFilter( 'dwpb_menu_subpages_to_remove' )->with( $expected_subpages )->reply( $expected_subpages );
		WP_Mock::userFunction( 'remove_submenu_page' )->once()->with( 'tools.php', 'tools.php' );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->remove_menu_pages();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * When no post type (other than 'post') supports comments, edit-comments.php and the
	 * Settings > Discussion submenu entry are removed too.
	 */
	public function test_remove_menu_pages_also_removes_comments_pages_when_unsupported() {
		$this->stub_feature_cache_hit( 'comments', false );
		$this->stub_remove_writing_options( false );

		$expected_pages = array( 'edit.php', 'edit-comments.php' );
		WP_Mock::onFilter( 'dwpb_menu_pages_to_remove' )->with( $expected_pages )->reply( $expected_pages );
		WP_Mock::userFunction( 'remove_menu_page' )->once()->with( 'edit.php' );
		WP_Mock::userFunction( 'remove_menu_page' )->once()->with( 'edit-comments.php' );

		$expected_subpages = array(
			'tools.php'           => array( 'tools.php' ),
			'options-general.php' => array( 'options-discussion.php' ),
		);
		WP_Mock::onFilter( 'dwpb_menu_subpages_to_remove' )->with( $expected_subpages )->reply( $expected_subpages );
		WP_Mock::userFunction( 'remove_submenu_page' )->once()->with( 'tools.php', 'tools.php' );
		WP_Mock::userFunction( 'remove_submenu_page' )->once()->with( 'options-general.php', 'options-discussion.php' );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->remove_menu_pages();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * When remove_writing_options() is true, the writing submenu entry is removed too.
	 */
	public function test_remove_menu_pages_removes_writing_page_when_remove_writing_options_true() {
		$this->stub_feature_cache_hit( 'comments', array( 'page' ) );
		$this->stub_remove_writing_options( true );

		$this->stub_menu_pages_to_remove( array( 'edit.php' ), array( 'edit.php' ) );
		WP_Mock::userFunction( 'remove_menu_page' )->once()->with( 'edit.php' );

		$expected_subpages = array(
			'tools.php'           => array( 'tools.php' ),
			'options-general.php' => array( 'options-writing.php' ),
		);
		WP_Mock::onFilter( 'dwpb_menu_subpages_to_remove' )->with( $expected_subpages )->reply( $expected_subpages );
		WP_Mock::userFunction( 'remove_submenu_page' )->once()->with( 'tools.php', 'tools.php' );
		WP_Mock::userFunction( 'remove_submenu_page' )->once()->with( 'options-general.php', 'options-writing.php' );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->remove_menu_pages();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * dwpb_menu_pages_to_remove must be able to add an extra top-level page to remove.
	 */
	public function test_remove_menu_pages_dwpb_menu_pages_to_remove_filter_can_add_a_page() {
		$this->stub_feature_cache_hit( 'comments', array( 'page' ) );
		$this->stub_remove_writing_options( false );

		$this->stub_menu_pages_to_remove( array( 'edit.php' ), array( 'edit.php', 'extra-page.php' ) );
		WP_Mock::userFunction( 'remove_menu_page' )->once()->with( 'edit.php' );
		WP_Mock::userFunction( 'remove_menu_page' )->once()->with( 'extra-page.php' );

		$expected_subpages = array(
			'tools.php'           => array( 'tools.php' ),
			'options-general.php' => array(),
		);
		WP_Mock::onFilter( 'dwpb_menu_subpages_to_remove' )->with( $expected_subpages )->reply( $expected_subpages );
		WP_Mock::userFunction( 'remove_submenu_page' )->once()->with( 'tools.php', 'tools.php' );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->remove_menu_pages();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * dwpb_menu_subpages_to_remove must be able to replace the subpages map entirely,
	 * including the backwards-compatible single-string (rather than array) subpage form.
	 */
	public function test_remove_menu_pages_dwpb_menu_subpages_to_remove_filter_supports_string_value() {
		$this->stub_feature_cache_hit( 'comments', array( 'page' ) );
		$this->stub_remove_writing_options( false );

		$this->stub_menu_pages_to_remove( array( 'edit.php' ), array( 'edit.php' ) );
		WP_Mock::userFunction( 'remove_menu_page' )->once()->with( 'edit.php' );

		$default_subpages = array(
			'tools.php'           => array( 'tools.php' ),
			'options-general.php' => array(),
		);
		$overridden_subpages = array( 'custom-page.php' => 'custom-subpage.php' );
		WP_Mock::onFilter( 'dwpb_menu_subpages_to_remove' )->with( $default_subpages )->reply( $overridden_subpages );
		WP_Mock::userFunction( 'remove_submenu_page' )->once()->with( 'custom-page.php', 'custom-subpage.php' );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->remove_menu_pages();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * remove_dashboard_widgets()
	 */

	public function test_remove_dashboard_widgets_removes_both_widgets_by_default() {
		$this->stub_strict_filter( 'dwpb_disable_dashboard_quick_press', true, true );
		$this->stub_strict_filter( 'dwpb_disable_dashboard_activity', true, true );
		WP_Mock::userFunction( 'remove_meta_box' )->once()->with( 'dashboard_quick_press', 'dashboard', 'side' );
		WP_Mock::userFunction( 'remove_meta_box' )->once()->with( 'dashboard_activity', 'dashboard', 'normal' );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->remove_dashboard_widgets();

		$this->addToAssertionCount( 1 );
	}

	public function test_remove_dashboard_widgets_keeps_quick_press_when_its_filter_returns_false() {
		$this->stub_strict_filter( 'dwpb_disable_dashboard_quick_press', true, false );
		$this->stub_strict_filter( 'dwpb_disable_dashboard_activity', true, true );
		WP_Mock::userFunction( 'remove_meta_box' )->never()->with( 'dashboard_quick_press', 'dashboard', 'side' );
		WP_Mock::userFunction( 'remove_meta_box' )->once()->with( 'dashboard_activity', 'dashboard', 'normal' );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->remove_dashboard_widgets();

		$this->addToAssertionCount( 1 );
	}

	public function test_remove_dashboard_widgets_keeps_activity_when_its_filter_returns_false() {
		$this->stub_strict_filter( 'dwpb_disable_dashboard_quick_press', true, true );
		$this->stub_strict_filter( 'dwpb_disable_dashboard_activity', true, false );
		WP_Mock::userFunction( 'remove_meta_box' )->once()->with( 'dashboard_quick_press', 'dashboard', 'side' );
		WP_Mock::userFunction( 'remove_meta_box' )->never()->with( 'dashboard_activity', 'dashboard', 'normal' );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->remove_dashboard_widgets();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * remove_widgets()
	 */

	/**
	 * The full list of widget classes remove_widgets() considers unregistering, in the
	 * order the method itself declares them.
	 *
	 * @return string[]
	 */
	private function widget_class_names() {
		return array(
			'WP_Widget_Recent_Comments',
			'WP_Widget_Tag_Cloud',
			'WP_Widget_Categories',
			'WP_Widget_Archives',
			'WP_Widget_Calendar',
			'WP_Widget_Links',
			'WP_Widget_Recent_Posts',
			'WP_Widget_RSS',
		);
	}

	public function test_remove_widgets_unregisters_every_supported_widget_by_default() {
		foreach ( $this->widget_class_names() as $widget ) {
			$this->stub_unregister_widget_filter( $widget, true );
			WP_Mock::userFunction( 'unregister_widget' )->once()->with( $widget );
		}

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->remove_widgets();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * dwpb_unregister_widgets must be able to keep a single widget registered without
	 * affecting the others.
	 */
	public function test_remove_widgets_dwpb_unregister_widgets_filter_can_keep_one_widget() {
		foreach ( $this->widget_class_names() as $widget ) {
			$unregister = 'WP_Widget_Categories' !== $widget;
			$this->stub_unregister_widget_filter( $widget, $unregister );
			if ( $unregister ) {
				WP_Mock::userFunction( 'unregister_widget' )->once()->with( $widget );
			} else {
				WP_Mock::userFunction( 'unregister_widget' )->never()->with( $widget );
			}
		}

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->remove_widgets();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * filter_widget_removal()
	 */

	public function test_filter_widget_removal_hides_categories_widget_when_category_used_elsewhere() {
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', array( 'book' ) );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertFalse( $admin->filter_widget_removal( true, 'WP_Widget_Categories' ) );
	}

	public function test_filter_widget_removal_keeps_categories_widget_when_category_not_used_elsewhere() {
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', false );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertTrue( $admin->filter_widget_removal( true, 'WP_Widget_Categories' ) );
	}

	public function test_filter_widget_removal_hides_recent_comments_widget_when_another_post_type_supports_comments() {
		$this->stub_feature_cache_hit( 'comments', array( 'book' ) );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertFalse( $admin->filter_widget_removal( true, 'WP_Widget_Recent_Comments' ) );
	}

	public function test_filter_widget_removal_keeps_recent_comments_widget_when_no_other_post_type_supports_comments() {
		$this->stub_feature_cache_hit( 'comments', false );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertTrue( $admin->filter_widget_removal( true, 'WP_Widget_Recent_Comments' ) );
	}

	public function test_filter_widget_removal_hides_tag_cloud_widget_when_post_tag_used_elsewhere() {
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'post_tag', array( 'book' ) );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertFalse( $admin->filter_widget_removal( true, 'WP_Widget_Tag_Cloud' ) );
	}

	public function test_filter_widget_removal_keeps_tag_cloud_widget_when_post_tag_not_used_elsewhere() {
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'post_tag', false );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertTrue( $admin->filter_widget_removal( true, 'WP_Widget_Tag_Cloud' ) );
	}

	/**
	 * A widget name matching none of the three special cases passes $show through unchanged,
	 * with no dependency on dwpb_post_types_with_tax()/dwpb_post_types_with_feature() at all.
	 */
	public function test_filter_widget_removal_passes_through_unrelated_widget_unchanged() {
		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertTrue( $admin->filter_widget_removal( true, 'WP_Widget_Links' ) );
		$this->assertFalse( $admin->filter_widget_removal( false, 'WP_Widget_Links' ) );
	}

	/**
	 * remove_admin_bar_links()
	 */

	public function test_remove_admin_bar_links_removes_comments_menu_when_unsupported_elsewhere() {
		global $wp_admin_bar;
		$wp_admin_bar = Mockery::mock();
		$wp_admin_bar->shouldReceive( 'remove_menu' )->once()->with( 'comments' );
		$wp_admin_bar->shouldReceive( 'remove_node' )->once()->with( 'new-post' );

		$this->stub_feature_cache_hit( 'comments', false );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->remove_admin_bar_links();

		$this->addToAssertionCount( 1 );
	}

	public function test_remove_admin_bar_links_keeps_comments_menu_when_supported_elsewhere() {
		global $wp_admin_bar;
		$wp_admin_bar = Mockery::mock();
		$wp_admin_bar->shouldNotReceive( 'remove_menu' );
		$wp_admin_bar->shouldReceive( 'remove_node' )->once()->with( 'new-post' );

		$this->stub_feature_cache_hit( 'comments', array( 'book' ) );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->remove_admin_bar_links();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * remove_writing_options()
	 */

	public function test_remove_writing_options_false_by_default() {
		$this->stub_remove_writing_options( false );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertFalse( $admin->remove_writing_options() );
	}

	public function test_remove_writing_options_dwpb_remove_options_writing_filter_can_enable_it() {
		$this->stub_remove_writing_options( true );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertTrue( $admin->remove_writing_options() );
	}

	/**
	 * admin_body_class()
	 */

	public function test_admin_body_class_appends_class_when_front_page_set() {
		WP_Mock::userFunction( 'get_option' )->with( 'show_on_front' )->andReturn( 'page' );
		WP_Mock::userFunction( 'get_option' )->with( 'page_on_front' )->andReturn( 5 );
		WP_Mock::userFunction( 'absint' )->with( 5 )->andReturn( 5 );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertSame( 'some-classes disabled-blog', $admin->admin_body_class( 'some-classes' ) );
	}

	public function test_admin_body_class_unchanged_when_no_front_page_set() {
		WP_Mock::userFunction( 'get_option' )->with( 'show_on_front' )->andReturn( 'posts' );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertSame( 'some-classes', $admin->admin_body_class( 'some-classes' ) );
	}

	/**
	 * has_front_page()
	 */

	public function test_has_front_page_true_when_static_front_page_set() {
		WP_Mock::userFunction( 'get_option' )->with( 'show_on_front' )->andReturn( 'page' );
		WP_Mock::userFunction( 'get_option' )->with( 'page_on_front' )->andReturn( 5 );
		WP_Mock::userFunction( 'absint' )->with( 5 )->andReturn( 5 );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertTrue( $admin->has_front_page() );
	}

	/**
	 * show_on_front !== 'page' short-circuits the && before get_option( 'page_on_front' ) is
	 * ever called -- leaving it unstubbed here is what proves that.
	 */
	public function test_has_front_page_false_when_show_on_front_is_not_page() {
		WP_Mock::userFunction( 'get_option' )->with( 'show_on_front' )->andReturn( 'posts' );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertFalse( $admin->has_front_page() );
	}

	public function test_has_front_page_false_when_page_on_front_is_zero() {
		WP_Mock::userFunction( 'get_option' )->with( 'show_on_front' )->andReturn( 'page' );
		WP_Mock::userFunction( 'get_option' )->with( 'page_on_front' )->andReturn( 0 );
		WP_Mock::userFunction( 'absint' )->with( 0 )->andReturn( 0 );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertFalse( $admin->has_front_page() );
	}

	/**
	 * disable_press_this()
	 */

	public function test_disable_press_this_terminates_via_wp_die() {
		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( '"Press This" functionality has been disabled.' );

		$admin->disable_press_this();
	}

	/**
	 * enqueue_styles()
	 */

	public function test_enqueue_styles_registers_expected_handle_and_version() {
		WP_Mock::userFunction( 'plugin_dir_url' )
			->once()
			->andReturn( 'https://example.test/wp-content/plugins/disable-blog/includes/' );
		WP_Mock::userFunction( 'wp_enqueue_style' )
			->once()
			->with(
				'disable-blog',
				'https://example.test/wp-content/plugins/disable-blog/includes/../assets/css/disable-blog-admin.css',
				array(),
				'0.5.6',
				'all'
			);

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->enqueue_styles();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * enqueue_scripts()
	 */

	public function test_enqueue_scripts_localizes_expected_vars() {
		global $pagenow;
		$pagenow = 'edit.php';

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->once()
			->with( 'disable-blog', DWPB_URL . 'assets/js/disable-blog-admin.js', array(), '0.5.6', true );

		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', array( 'book' ) );
		$this->stub_post_types_with_tax_result( 'post_tag', false );
		$this->stub_feature_cache_hit( 'comments', false );

		WP_Mock::userFunction( 'wp_localize_script' )
			->once()
			->with(
				'disable-blog',
				'dwpb',
				Mockery::on(
					function ( $js_vars ) {
						// WP_Mock's ->with() matches plain arrays with loose (==) comparison,
						// under which a non-empty array or truthy string is indistinguishable
						// from (bool) true. assertSame() enforces the real per-key types
						// (string 'page', strict bool for the three *Supported flags) that
						// wp_localize_script()/json_encode() actually depend on.
						$this->assertSame(
							array(
								'page'                => 'edit',
								'categoriesSupported' => true,
								'tagsSupported'       => false,
								'commentsSupported'   => false,
							),
							$js_vars
						);
						return true;
					}
				)
			);

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->enqueue_scripts();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * customizer_styles()
	 */

	public function test_customizer_styles_outputs_style_block_when_front_page_set() {
		WP_Mock::userFunction( 'get_option' )->with( 'show_on_front' )->andReturn( 'page' );
		WP_Mock::userFunction( 'get_option' )->with( 'page_on_front' )->andReturn( 5 );
		WP_Mock::userFunction( 'absint' )->with( 5 )->andReturn( 5 );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		ob_start();
		$admin->customizer_styles();
		$output = ob_get_clean();

		$this->assertStringContainsString( '#customize-control-show_on_front', $output );
	}

	public function test_customizer_styles_outputs_nothing_when_no_front_page_set() {
		WP_Mock::userFunction( 'get_option' )->with( 'show_on_front' )->andReturn( 'posts' );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		ob_start();
		$admin->customizer_styles();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * customizer_scripts()
	 */

	public function test_customizer_scripts_enqueues_and_localizes_expected_vars() {
		WP_Mock::userFunction( 'wp_enqueue_script' )
			->once()
			->with(
				'disable-blog-customizer-scripts',
				DWPB_URL . 'assets/js/disable-blog-customizer.js',
				array( 'jquery', 'customize-controls' ),
				'0.5.6',
				true
			);

		WP_Mock::userFunction( 'wp_localize_script' )
			->once()
			->with(
				'disable-blog-customizer-scripts',
				'dwpbCustomizer',
				array(
					'homepageSettingsText' => "You can choose what's displayed on the homepage of your site. To set a static homepage, create or select the page below.",
				)
			);

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->customizer_scripts();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * plugin_links()
	 */

	public function test_plugin_links_returns_unchanged_links_when_user_cannot_install_plugins() {
		WP_Mock::userFunction( 'current_user_can' )->once()->with( 'install_plugins' )->andReturn( false );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$links = array( 'deactivate' => '<a>Deactivate</a>' );
		$this->assertSame( $links, $admin->plugin_links( $links, 'disable-blog/disable-blog.php' ) );
	}

	public function test_plugin_links_adds_meta_links_for_this_plugins_file() {
		WP_Mock::userFunction( 'current_user_can' )->once()->with( 'install_plugins' )->andReturn( true );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$links  = array( 'deactivate' => '<a>Deactivate</a>' );
		$result = $admin->plugin_links( $links, 'disable-blog/disable-blog.php' );

		$this->assertArrayHasKey( 'deactivate', $result );
		$this->assertArrayHasKey( 'support', $result );
		$this->assertArrayHasKey( 'review', $result );
		$this->assertArrayHasKey( 'donate', $result );
		$this->assertArrayHasKey( 'github', $result );
		$this->assertCount( 5, $result );
	}

	public function test_plugin_links_returns_unchanged_links_for_a_different_plugin_file() {
		WP_Mock::userFunction( 'current_user_can' )->once()->with( 'install_plugins' )->andReturn( true );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$links = array( 'deactivate' => '<a>Deactivate</a>' );
		$this->assertSame( $links, $admin->plugin_links( $links, 'some-other-plugin/some-other-plugin.php' ) );
	}
}
