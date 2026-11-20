<?php
/**
 * Tests for the remaining Disable_Blog_Admin surface: modify_post_type_arguments(),
 * modify_taxonomies_arguments(), admin_notices(), update_posts_page_notice(),
 * posts_page_notice(), page_post_states(), available_permalink_structure_tags(),
 * filter_block_type_metadata(), site_status_tests() and get_test_rest_availability().
 *
 * Disable_Blog_Admin::__construct() is deliberately not covered again here -- both of its
 * branches (an injected $functions instance actually being used, and a real one being
 * constructed when omitted) are already proven by ConstructorInjectionTest.php.
 *
 * @package DisableBlog
 */

// Not required by tests/php/bootstrap.php on purpose (see ConstructorInjectionTest.php's note;
// LoaderTest's autoloader test needs Disable_Blog_Functions to remain undefined until its own
// isolated process). require_once is safe regardless of which test file happens to load first.
require_once __DIR__ . '/../../includes/class-disable-blog-functions.php';

// page_post_states() tests below use WP_Post, already required globally by
// tests/php/bootstrap.php's require of Support/fixtures/class-wp-post-stub.php.

/**
 * @covers Disable_Blog_Admin::modify_post_type_arguments
 * @covers Disable_Blog_Admin::modify_taxonomies_arguments
 * @covers Disable_Blog_Admin::admin_notices
 * @covers Disable_Blog_Admin::update_posts_page_notice
 * @covers Disable_Blog_Admin::posts_page_notice
 * @covers Disable_Blog_Admin::page_post_states
 * @covers Disable_Blog_Admin::available_permalink_structure_tags
 * @covers Disable_Blog_Admin::filter_block_type_metadata
 * @covers Disable_Blog_Admin::site_status_tests
 * @covers Disable_Blog_Admin::get_test_rest_availability
 */
class AdminMiscTest extends TestCase {

	/**
	 * The global $wp_post_types as it stood before the test, so it can be restored in
	 * tear_down().
	 *
	 * @var mixed
	 */
	private $original_wp_post_types;

	/**
	 * The global $wp_taxonomies as it stood before the test, so it can be restored in
	 * tear_down().
	 *
	 * @var mixed
	 */
	private $original_wp_taxonomies;

	/**
	 * $_COOKIE as it stood before the test, so it can be restored in tear_down().
	 *
	 * @var array
	 */
	private $original_cookie;

	/**
	 * $_SERVER as it stood before the test, so it can be restored in tear_down().
	 *
	 * @var array
	 */
	private $original_server;

	protected function set_up() {
		parent::set_up();

		global $wp_post_types, $wp_taxonomies;
		$this->original_wp_post_types = $wp_post_types ?? null;
		$this->original_wp_taxonomies = $wp_taxonomies ?? null;
		$this->original_cookie        = $_COOKIE; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
		$this->original_server        = $_SERVER;
	}

	protected function tear_down() {
		global $wp_post_types, $wp_taxonomies;
		$wp_post_types = $this->original_wp_post_types;
		$wp_taxonomies = $this->original_wp_taxonomies;
		$_COOKIE        = $this->original_cookie; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
		$_SERVER        = $this->original_server;

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
		WP_Mock::userFunction( 'get_post_types' )->with( array(), 'names' )->andReturn( array() );
		WP_Mock::userFunction( 'wp_cache_get' )->andReturn( false );
		WP_Mock::userFunction( 'wp_cache_set' )->andReturn( null );
		WP_Mock::userFunction( 'maybe_serialize' )->with( array() )->andReturn( 'a:0:{}' );
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
			->reply( $return_value );
	}

	/**
	 * Stubs has_front_page()'s two get_option() calls (and the absint() of the second).
	 *
	 * @param string $show_on_front The show_on_front option value.
	 * @param int    $page_on_front The page_on_front option value.
	 * @return void
	 */
	private function stub_has_front_page( $show_on_front, $page_on_front ) {
		WP_Mock::userFunction( 'get_option' )->with( 'show_on_front' )->andReturn( $show_on_front );
		if ( 'page' === $show_on_front ) {
			WP_Mock::userFunction( 'get_option' )->with( 'page_on_front' )->andReturn( $page_on_front );
			WP_Mock::userFunction( 'absint' )->with( $page_on_front )->andReturn( $page_on_front );
		}
	}

	/**
	 * modify_post_type_arguments()
	 */

	public function test_modify_post_type_arguments_disables_expected_properties_when_all_set() {
		global $wp_post_types;

		$post_type = new stdClass();
		foreach ( array( 'has_archive', 'public', 'publicly_queryable', 'rewrite', 'query_var', 'show_ui', 'show_in_admin_bar', 'show_in_nav_menus', 'show_in_menu', 'show_in_rest' ) as $arg ) {
			$post_type->$arg = true;
		}
		$post_type->label = 'Posts'; // A property NOT in the removal list, to prove it's untouched.

		$wp_post_types = array( 'post' => $post_type );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->modify_post_type_arguments();

		foreach ( array( 'has_archive', 'public', 'publicly_queryable', 'rewrite', 'query_var', 'show_ui', 'show_in_admin_bar', 'show_in_nav_menus', 'show_in_menu', 'show_in_rest' ) as $arg ) {
			$this->assertFalse( $post_type->$arg, $arg );
		}
		$this->assertTrue( $post_type->exclude_from_search );
		$this->assertSame( array(), $post_type->supports );
		$this->assertSame( 'Posts', $post_type->label );
	}

	public function test_modify_post_type_arguments_only_disables_properties_that_are_set() {
		global $wp_post_types;

		$post_type              = new stdClass();
		$post_type->has_archive = true;
		// 'rewrite' and every other removable property are deliberately left unset.

		$wp_post_types = array( 'post' => $post_type );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->modify_post_type_arguments();

		$this->assertFalse( $post_type->has_archive );
		$this->assertFalse( isset( $post_type->rewrite ) );
		$this->assertTrue( $post_type->exclude_from_search );
		$this->assertSame( array(), $post_type->supports );
	}

	public function test_modify_post_type_arguments_does_nothing_when_post_type_not_set() {
		global $wp_post_types;

		$page_type     = new stdClass();
		$wp_post_types = array( 'page' => $page_type );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->modify_post_type_arguments();

		$this->assertSame( array( 'page' ), array_keys( $wp_post_types ) );
		$this->assertFalse( property_exists( $page_type, 'exclude_from_search' ) );
	}

	/**
	 * modify_taxonomies_arguments()
	 */

	public function test_modify_taxonomies_arguments_removes_post_and_disables_public_args_when_only_post_type() {
		global $wp_taxonomies;

		$category                = new stdClass();
		$category->object_type   = array( 'post' );
		foreach ( array( 'has_archive', 'public', 'publicly_queryable', 'query_var', 'show_ui', 'show_tagcloud', 'show_in_admin_bar', 'show_in_quick_edit', 'show_in_nav_menus', 'show_admin_column', 'show_in_menu', 'show_in_rest' ) as $arg ) {
			$category->$arg = true;
		}
		$wp_taxonomies = array( 'category' => $category );

		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', false );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->modify_taxonomies_arguments();

		$this->assertNotContains( 'post', $category->object_type );
		foreach ( array( 'has_archive', 'public', 'publicly_queryable', 'query_var', 'show_ui', 'show_tagcloud', 'show_in_admin_bar', 'show_in_quick_edit', 'show_in_nav_menus', 'show_admin_column', 'show_in_menu', 'show_in_rest' ) as $arg ) {
			$this->assertFalse( $category->$arg, $arg );
		}
	}

	public function test_modify_taxonomies_arguments_keeps_public_args_when_other_post_type_uses_taxonomy() {
		global $wp_taxonomies;

		$category               = new stdClass();
		$category->object_type  = array( 'post' );
		$category->public       = true;
		$wp_taxonomies           = array( 'category' => $category );

		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', array( 'book' ) );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->modify_taxonomies_arguments();

		// 'post' is still stripped from object_type unconditionally...
		$this->assertNotContains( 'post', $category->object_type );
		// ...but the public arguments are left alone, since another post type uses it.
		$this->assertTrue( $category->public );
	}

	public function test_modify_taxonomies_arguments_skips_when_taxonomy_not_set() {
		global $wp_taxonomies;

		$post_format         = new stdClass();
		$post_format->public = true;
		$wp_taxonomies        = array( 'post_format' => $post_format );

		// Neither dwpb_post_types_with_tax() nor anything else may be reached: nothing
		// is stubbed.
		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->modify_taxonomies_arguments();

		$this->assertSame( array( 'post_format' ), array_keys( $wp_taxonomies ) );
		$this->assertTrue( $post_format->public );
	}

	public function test_modify_taxonomies_arguments_leaves_object_type_unchanged_when_post_not_present() {
		global $wp_taxonomies;

		$category               = new stdClass();
		$category->object_type  = array( 'book' ); // 'post' is not present -- array_search() returns false.
		$wp_taxonomies           = array( 'category' => $category );

		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', false );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->modify_taxonomies_arguments();

		$this->assertSame( array( 'book' ), $category->object_type );
	}

	public function test_modify_taxonomies_arguments_skips_object_type_removal_when_not_an_array() {
		global $wp_taxonomies;

		$category              = new stdClass();
		$category->object_type = 'post'; // Not an array -- is_array() gate skips the removal block entirely.
		$wp_taxonomies          = array( 'category' => $category );

		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', false );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->modify_taxonomies_arguments();

		$this->assertSame( 'post', $category->object_type );
	}

	/**
	 * admin_notices()
	 */

	public function test_admin_notices_returns_early_on_unsupported_screen() {
		$current_screen       = new stdClass();
		$current_screen->base = 'dashboard';
		WP_Mock::userFunction( 'get_current_screen' )->once()->andReturn( $current_screen );

		// has_front_page() must never be reached: nothing further is stubbed.
		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		ob_start();
		$admin->admin_notices();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_admin_notices_returns_early_when_screen_base_not_set() {
		WP_Mock::userFunction( 'get_current_screen' )->once()->andReturn( new stdClass() );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		ob_start();
		$admin->admin_notices();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_admin_notices_prints_reading_settings_link_when_no_front_page_and_not_on_reading_screen() {
		$current_screen       = new stdClass();
		$current_screen->base = 'edit';
		WP_Mock::userFunction( 'get_current_screen' )->once()->andReturn( $current_screen );

		$this->stub_has_front_page( 'posts', 0 );

		$reading_url = 'https://example.test/wp-admin/options-reading.php';
		WP_Mock::userFunction( 'get_admin_url' )->once()->with( null, 'options-reading.php' )->andReturn( $reading_url );
		WP_Mock::userFunction( 'wp_kses_post' )->once()->andReturnUsing(
			function ( $value ) {
				return $value;
			}
		);

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		ob_start();
		$admin->admin_notices();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-error', $output );
		$this->assertStringContainsString( $reading_url, $output );
	}

	public function test_admin_notices_prints_selection_prompt_when_no_front_page_and_on_reading_screen() {
		$current_screen       = new stdClass();
		$current_screen->base = 'options-reading';
		WP_Mock::userFunction( 'get_current_screen' )->once()->andReturn( $current_screen );

		$this->stub_has_front_page( 'posts', 0 );

		WP_Mock::userFunction( 'wp_kses_post' )->once()->andReturnUsing(
			function ( $value ) {
				return $value;
			}
		);

		// get_admin_url() must never be reached: nothing further is stubbed.
		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		ob_start();
		$admin->admin_notices();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Select a page for your homepage below.', $output );
	}

	public function test_admin_notices_prints_conflict_warning_when_front_page_equals_posts_page_on_reading_screen() {
		$current_screen       = new stdClass();
		$current_screen->base = 'options-reading';
		WP_Mock::userFunction( 'get_current_screen' )->once()->andReturn( $current_screen );

		$this->stub_has_front_page( 'page', 5 );
		WP_Mock::userFunction( 'get_option' )->with( 'page_for_posts' )->andReturn( 5 );

		WP_Mock::userFunction( 'esc_attr' )->once()->andReturnUsing(
			function ( $value ) {
				return $value;
			}
		);

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		ob_start();
		$admin->admin_notices();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'requires a homepage that is different', $output );
	}

	public function test_admin_notices_prints_nothing_when_front_page_set_and_pages_differ() {
		$current_screen       = new stdClass();
		$current_screen->base = 'options-reading';
		WP_Mock::userFunction( 'get_current_screen' )->once()->andReturn( $current_screen );

		$this->stub_has_front_page( 'page', 5 );
		WP_Mock::userFunction( 'get_option' )->with( 'page_for_posts' )->andReturn( 9 );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		ob_start();
		$admin->admin_notices();
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * update_posts_page_notice()
	 */

	public function test_update_posts_page_notice_swaps_the_notice_when_editing_the_posts_page() {
		WP_Mock::userFunction( 'get_option' )->once()->with( 'page_for_posts' )->andReturn( 5 );
		WP_Mock::userFunction( 'get_the_ID' )->once()->andReturn( 5 );
		WP_Mock::userFunction( 'remove_action' )->once()->with( 'edit_form_after_title', '_wp_posts_page_notice' );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		WP_Mock::expectActionAdded( 'edit_form_after_title', array( $admin, 'posts_page_notice' ) );

		$admin->update_posts_page_notice();

		$this->addToAssertionCount( 1 );
	}

	public function test_update_posts_page_notice_no_op_when_not_editing_the_posts_page() {
		WP_Mock::userFunction( 'get_option' )->once()->with( 'page_for_posts' )->andReturn( 5 );
		WP_Mock::userFunction( 'get_the_ID' )->once()->andReturn( 9 );

		// remove_action()/add_action() must never be reached: nothing further is stubbed.
		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->update_posts_page_notice();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * posts_page_notice()
	 */

	public function test_posts_page_notice_echoes_expected_markup() {
		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		ob_start();
		$admin->posts_page_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice notice-warning inline', $output );
		$this->assertStringContainsString( 'redirected to the homepage', $output );
	}

	/**
	 * page_post_states()
	 */

	public function test_page_post_states_adds_redirected_state_when_front_page_set_and_ids_match() {
		$this->stub_has_front_page( 'page', 5 );
		WP_Mock::userFunction( 'get_option' )->with( 'page_for_posts' )->andReturn( 5 );
		WP_Mock::userFunction( 'absint' )->with( 5 )->andReturn( 5 );

		$post = new WP_Post( array( 'ID' => 5 ) );

		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$result = $admin->page_post_states( array( 'existing' => 'Existing' ), $post );

		$this->assertArrayHasKey( 'dwpb-redirected', $result );
		$this->assertSame( 'Existing', $result['existing'] );
	}

	public function test_page_post_states_unchanged_when_ids_do_not_match() {
		$this->stub_has_front_page( 'page', 5 );
		WP_Mock::userFunction( 'get_option' )->with( 'page_for_posts' )->andReturn( 5 );
		WP_Mock::userFunction( 'absint' )->with( 5 )->andReturn( 5 );

		$post = new WP_Post( array( 'ID' => 9 ) );

		$admin       = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$post_states = array( 'existing' => 'Existing' );

		$this->assertSame( $post_states, $admin->page_post_states( $post_states, $post ) );
	}

	public function test_page_post_states_unchanged_when_no_front_page_set() {
		$this->stub_has_front_page( 'posts', 0 );

		$post = new WP_Post( array( 'ID' => 5 ) );

		// get_option( 'page_for_posts' ) must never be reached: has_front_page() short-
		// circuits the && before it.
		$admin       = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$post_states = array( 'existing' => 'Existing' );

		$this->assertSame( $post_states, $admin->page_post_states( $post_states, $post ) );
	}

	/**
	 * available_permalink_structure_tags()
	 */

	public function test_available_permalink_structure_tags_removes_category_when_unsupported() {
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', false );
		WP_Mock::onFilter( 'dwpb_disable_author_archives' )->with( false )->reply( false );

		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$result = $admin->available_permalink_structure_tags(
			array(
				'category' => '%category%',
				'author'   => '%author%',
			)
		);

		$this->assertArrayNotHasKey( 'category', $result );
		$this->assertArrayHasKey( 'author', $result );
	}

	public function test_available_permalink_structure_tags_keeps_category_when_supported() {
		$this->stub_tax_lookup_plumbing();
		$this->stub_post_types_with_tax_result( 'category', array( 'book' ) );
		WP_Mock::onFilter( 'dwpb_disable_author_archives' )->with( false )->reply( false );

		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$result = $admin->available_permalink_structure_tags( array( 'category' => '%category%' ) );

		$this->assertArrayHasKey( 'category', $result );
	}

	public function test_available_permalink_structure_tags_removes_author_when_author_archives_disabled() {
		WP_Mock::onFilter( 'dwpb_disable_author_archives' )->with( false )->reply( true );

		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$result = $admin->available_permalink_structure_tags( array( 'author' => '%author%' ) );

		$this->assertArrayNotHasKey( 'author', $result );
	}

	public function test_available_permalink_structure_tags_keeps_author_when_author_archives_enabled() {
		WP_Mock::onFilter( 'dwpb_disable_author_archives' )->with( false )->reply( false );

		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$result = $admin->available_permalink_structure_tags( array( 'author' => '%author%' ) );

		$this->assertArrayHasKey( 'author', $result );
	}

	public function test_available_permalink_structure_tags_no_op_when_keys_absent() {
		// Neither dwpb_post_types_with_tax() nor dwpb_disable_author_archives may be
		// reached: nothing is stubbed.
		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$result = $admin->available_permalink_structure_tags( array( 'month' => '%monthnum%' ) );

		$this->assertSame( array( 'month' => '%monthnum%' ), $result );
	}

	/**
	 * filter_block_type_metadata()
	 */

	public function test_filter_block_type_metadata_leaves_unrelated_block_unchanged() {
		$admin    = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$metadata = array( 'name' => 'core/paragraph' );

		$this->assertSame( $metadata, $admin->filter_block_type_metadata( $metadata ) );
	}

	public function test_filter_block_type_metadata_changes_query_block_default_post_type_to_page() {
		$admin    = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$metadata = array(
			'name'       => 'core/query',
			'attributes' => array(
				'query' => array(
					'default' => array( 'postType' => 'post' ),
				),
			),
		);

		$result = $admin->filter_block_type_metadata( $metadata );

		$this->assertSame( 'page', $result['attributes']['query']['default']['postType'] );
	}

	public function test_filter_block_type_metadata_leaves_query_block_unchanged_when_default_missing() {
		$admin    = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$metadata = array(
			'name'       => 'core/query',
			'attributes' => array(),
		);

		$this->assertSame( $metadata, $admin->filter_block_type_metadata( $metadata ) );
	}

	public function test_filter_block_type_metadata_bails_when_name_missing() {
		$admin    = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$metadata = array( 'attributes' => array() );

		$this->assertSame( $metadata, $admin->filter_block_type_metadata( $metadata ) );
	}

	/**
	 * Documents behavior, not a distinct branch: the guard's `! is_string( $metadata['name'] )`
	 * half is unreachable-as-observable. Proven by mutation -- deleting that half of the
	 * guard and rerunning this test still passes, because the subsequent
	 * `'core/query' === $metadata['name']` strict comparison already evaluates to false for
	 * any non-string $metadata['name'] under PHP's strict-equality type rules, independent
	 * of the guard. This test asserts the function's actual (correct) output for a
	 * non-string name; it does not, and cannot, isolate the is_string() check itself.
	 */
	public function test_filter_block_type_metadata_bails_when_name_not_a_string() {
		$admin    = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$metadata = array( 'name' => array( 'core/query' ) );

		$this->assertSame( $metadata, $admin->filter_block_type_metadata( $metadata ) );
	}

	/**
	 * site_status_tests()
	 */

	public function test_site_status_tests_replaces_rest_availability_test_callback() {
		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$tests = array(
			'direct' => array(
				'rest_availability' => array( 'test' => 'rest_availability' ),
			),
		);

		$result = $admin->site_status_tests( $tests );

		$this->assertSame( array( $admin, 'get_test_rest_availability' ), $result['direct']['rest_availability']['test'] );
	}

	public function test_site_status_tests_leaves_tests_unchanged_when_rest_availability_absent() {
		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$tests = array( 'direct' => array( 'other_test' => array( 'test' => 'other_test' ) ) );

		$this->assertSame( $tests, $admin->site_status_tests( $tests ) );
	}

	/**
	 * get_test_rest_availability()
	 */

	/**
	 * Stubs everything get_test_rest_availability() unconditionally reaches before its
	 * wp_remote_get() branch check: wp_unslash( $_COOKIE ), wp_create_nonce(),
	 * the https_local_ssl_verify filter, rest_url() and add_query_arg().
	 *
	 * @return void
	 */
	private function stub_get_test_rest_availability_common() {
		WP_Mock::userFunction( 'wp_unslash' )->with( array() )->andReturn( array() );
		WP_Mock::userFunction( 'wp_create_nonce' )->once()->with( 'wp_rest' )->andReturn( 'a-nonce' );
		WP_Mock::onFilter( 'https_local_ssl_verify' )->with( false )->reply( false );
		WP_Mock::userFunction( 'rest_url' )->once()->with( 'wp/v2/types/page' )->andReturn( 'https://example.test/wp-json/wp/v2/types/page' );
		WP_Mock::userFunction( 'add_query_arg' )
			->once()
			->with( array( 'context' => 'edit' ), 'https://example.test/wp-json/wp/v2/types/page' )
			->andReturn( 'https://example.test/wp-json/wp/v2/types/page?context=edit' );
	}

	public function test_get_test_rest_availability_happy_path_when_capabilities_present() {
		$this->stub_get_test_rest_availability_common();

		$response = array( 'body' => '{"capabilities":{"read":true}}' );
		WP_Mock::userFunction( 'wp_remote_get' )->once()->andReturn( $response );
		WP_Mock::userFunction( 'is_wp_error' )->once()->with( $response )->andReturn( false );
		WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->once()->with( $response )->andReturn( 200 );
		WP_Mock::userFunction( 'wp_remote_retrieve_body' )->with( $response )->andReturn( $response['body'] );

		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$result = $admin->get_test_rest_availability();

		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( 'The REST API is available', $result['label'] );
	}

	public function test_get_test_rest_availability_wp_error_branch() {
		$this->stub_get_test_rest_availability_common();

		$error = Mockery::mock();
		$error->shouldReceive( 'get_error_message' )->once()->andReturn( 'Connection refused' );
		$error->shouldReceive( 'get_error_code' )->once()->andReturn( 'http_request_failed' );
		WP_Mock::userFunction( 'wp_remote_get' )->once()->andReturn( $error );
		WP_Mock::userFunction( 'is_wp_error' )->once()->with( $error )->andReturn( true );

		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$result = $admin->get_test_rest_availability();

		$this->assertSame( 'critical', $result['status'] );
		$this->assertSame( 'The REST API encountered an error', $result['label'] );
		$this->assertStringContainsString( 'Connection refused', $result['description'] );
		$this->assertStringContainsString( 'http_request_failed', $result['description'] );
	}

	public function test_get_test_rest_availability_non_200_response_branch() {
		$this->stub_get_test_rest_availability_common();

		$response = array( 'body' => 'Not Found' );
		WP_Mock::userFunction( 'wp_remote_get' )->once()->andReturn( $response );
		WP_Mock::userFunction( 'is_wp_error' )->once()->with( $response )->andReturn( false );
		// Called twice: once for the elseif condition, once again inside the error message.
		WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->twice()->with( $response )->andReturn( 404 );
		WP_Mock::userFunction( 'wp_remote_retrieve_body' )->with( $response )->andReturn( 'Not Found' );
		WP_Mock::userFunction( 'esc_html' )->with( 'Not Found' )->andReturn( 'Not Found' );

		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$result = $admin->get_test_rest_availability();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertSame( 'The REST API encountered an unexpected result', $result['label'] );
		$this->assertStringContainsString( '404', $result['description'] );
	}

	public function test_get_test_rest_availability_200_missing_capabilities_branch() {
		$this->stub_get_test_rest_availability_common();

		$response = array( 'body' => '{"other":"value"}' );
		WP_Mock::userFunction( 'wp_remote_get' )->once()->andReturn( $response );
		WP_Mock::userFunction( 'is_wp_error' )->once()->with( $response )->andReturn( false );
		WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->once()->with( $response )->andReturn( 200 );
		WP_Mock::userFunction( 'wp_remote_retrieve_body' )->with( $response )->andReturn( $response['body'] );

		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$result = $admin->get_test_rest_availability();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertSame( 'The REST API did not behave correctly', $result['label'] );
		$this->assertStringContainsString( 'context', $result['description'] );
	}

	/**
	 * Proves the Basic-auth header is only added when PHP_AUTH_USER/PHP_AUTH_PW are both
	 * present on $_SERVER -- every other test in this group leaves them unset.
	 */
	public function test_get_test_rest_availability_includes_basic_auth_header_when_present() {
		// phpcs:disable WordPressVIPMinimum.Variables.ServerVariables.BasicAuthentication -- exercising the exact same $_SERVER keys the method under test reads.
		$_SERVER['PHP_AUTH_USER'] = 'admin';
		$_SERVER['PHP_AUTH_PW']   = 'secret';
		// phpcs:enable WordPressVIPMinimum.Variables.ServerVariables.BasicAuthentication

		WP_Mock::userFunction( 'wp_unslash' )->with( array() )->andReturn( array() );
		WP_Mock::userFunction( 'wp_unslash' )->with( 'admin' )->andReturn( 'admin' );
		WP_Mock::userFunction( 'wp_unslash' )->with( 'secret' )->andReturn( 'secret' );
		WP_Mock::userFunction( 'wp_create_nonce' )->once()->with( 'wp_rest' )->andReturn( 'a-nonce' );
		WP_Mock::onFilter( 'https_local_ssl_verify' )->with( false )->reply( false );
		WP_Mock::userFunction( 'rest_url' )->once()->with( 'wp/v2/types/page' )->andReturn( 'https://example.test/wp-json/wp/v2/types/page' );
		WP_Mock::userFunction( 'add_query_arg' )
			->once()
			->with( array( 'context' => 'edit' ), 'https://example.test/wp-json/wp/v2/types/page' )
			->andReturn( 'https://example.test/wp-json/wp/v2/types/page?context=edit' );

		$response = array( 'body' => '{"capabilities":{"read":true}}' );
		WP_Mock::userFunction( 'wp_remote_get' )
			->once()
			->with(
				'https://example.test/wp-json/wp/v2/types/page?context=edit',
				Mockery::on(
					function ( $args ) {
						return isset( $args['headers']['Authorization'] )
							&& 'Basic ' . base64_encode( 'admin:secret' ) === $args['headers']['Authorization'];
					}
				)
			)
			->andReturn( $response );
		WP_Mock::userFunction( 'is_wp_error' )->once()->with( $response )->andReturn( false );
		WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->once()->with( $response )->andReturn( 200 );
		WP_Mock::userFunction( 'wp_remote_retrieve_body' )->with( $response )->andReturn( $response['body'] );

		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$result = $admin->get_test_rest_availability();

		$this->assertSame( 'good', $result['status'] );
	}
}
