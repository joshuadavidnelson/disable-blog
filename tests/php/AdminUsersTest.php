<?php
/**
 * Tests for Disable_Blog_Admin's user-table surface: manage_users_columns(),
 * manage_users_custom_column(), its private user_column_post_types() helper, and
 * user_row_actions().
 *
 * @package DisableBlog
 */

// Not required by tests/php/bootstrap.php on purpose (see ConstructorInjectionTest.php's note;
// LoaderTest's autoloader test needs Disable_Blog_Functions to remain undefined until its own
// isolated process). require_once is safe regardless of which test file happens to load first.
require_once __DIR__ . '/../../includes/class-disable-blog-functions.php';

/**
 * @covers Disable_Blog_Admin::manage_users_columns
 * @covers Disable_Blog_Admin::manage_users_custom_column
 * @covers Disable_Blog_Admin::user_column_post_types
 * @covers Disable_Blog_Admin::user_row_actions
 */
class AdminUsersTest extends TestCase {

	/**
	 * The global $current_screen as it stood before the test, so it can be restored in
	 * tear_down().
	 *
	 * @var mixed
	 */
	private $original_current_screen;

	protected function set_up() {
		parent::set_up();

		global $current_screen;
		$this->original_current_screen = $current_screen ?? null;
	}

	protected function tear_down() {
		global $current_screen;
		$current_screen = $this->original_current_screen;

		parent::tear_down();
	}

	/**
	 * Invokes the private user_column_post_types() method via Reflection.
	 *
	 * @param Disable_Blog_Admin $admin Instance to invoke on.
	 * @return array
	 */
	private function invoke_user_column_post_types( Disable_Blog_Admin $admin ) {
		$reflection = new ReflectionMethod( $admin, 'user_column_post_types' );
		$reflection->setAccessible( true );

		return $reflection->invoke( $admin );
	}

	/**
	 * Stubs a single-argument filter, asserting via InvokedFilterValue that the real
	 * argument WP_Mock routed on strictly (===) matches $expected_arg, rather than merely
	 * matching loosely (==) as safe_offset()'s string-cast routing key would otherwise
	 * allow -- e.g. bool false, an empty array and '' all safe_offset() to the same key, as
	 * do a one-element array of a string and that bare string.
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
	 * A Mockery matcher asserting the argument is exactly array( $value ), pinning $value
	 * by identity (===) rather than Mockery's default loose (==) comparison. This is
	 * distinct from onFilter()'s safe_offset() collision that stub_strict_filter() guards
	 * against: apply_filters_deprecated() is a plain userFunction(), so its ->with()
	 * expectation IS Mockery-backed and matches with ==, under which array( true ) ==
	 * array( 1 ) and array( false ) == array( '' ) both hold -- a plain
	 * ->with( array( true ) ) therefore still matches a caller that passed array( 1 ) (or
	 * any other loosely-equal value) instead of the literal boolean it documents.
	 *
	 * @param bool $value The exact boolean the single-element array must contain.
	 * @return Mockery\Matcher\Closure
	 */
	private function strict_bool_array( $value ) {
		return Mockery::on(
			function ( $actual ) use ( $value ) {
				$this->assertSame( array( $value ), $actual );
				return true;
			}
		);
	}

	/**
	 * Forces user_column_post_types() to resolve to array( 'page' ): an empty
	 * dwpb_author_archive_post_types reply (real Disable_Blog_Functions::author_archive_post_types()
	 * default), merged with 'page', passed through dwpb_admin_user_post_types unchanged.
	 *
	 * @return void
	 */
	private function stub_default_user_column_post_types() {
		$this->stub_strict_filter( 'dwpb_author_archive_post_types', array(), array() );
		$this->stub_strict_filter( 'dwpb_admin_user_post_types', array( 'page' ), array( 'page' ) );
	}

	/**
	 * manage_users_columns()
	 */

	public function test_manage_users_columns_removes_posts_column_and_adds_page_column_by_default() {
		$this->stub_strict_filter( 'dwpb_disable_user_post_column', true, true );
		WP_Mock::userFunction( 'apply_filters_deprecated' )
			->once()
			->with( 'dpwb_disable_user_post_column', $this->strict_bool_array( true ), '0.5.6', 'dwpb_disable_user_post_column' )
			->andReturn( true );

		global $current_screen;
		$current_screen     = new stdClass();
		$current_screen->id = 'users';
		WP_Mock::userFunction( 'get_current_screen' )->once()->andReturn( $current_screen );

		$this->stub_default_user_column_post_types();

		$this->stub_strict_filter( 'dwpb_create_user_page_column', true, true );
		WP_Mock::userFunction( 'apply_filters_deprecated' )
			->once()
			->with( 'dpwb_create_user_page_column', $this->strict_bool_array( true ), '0.5.6', 'dwpb_create_user_page_column' )
			->andReturn( true );

		$page_type          = new stdClass();
		$page_type->labels  = new stdClass();
		$page_type->labels->name = 'Pages';
		WP_Mock::userFunction( 'get_post_type_object' )->once()->with( 'page' )->andReturn( $page_type );

		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$result = $admin->manage_users_columns(
			array(
				'cb'    => '<input type="checkbox" />',
				'posts' => 'Posts',
			)
		);

		$this->assertArrayNotHasKey( 'posts', $result );
		$this->assertSame( 'Pages', $result['page'] );
	}

	/**
	 * Proves the deprecated dpwb_disable_user_post_column alias actually takes effect:
	 * overriding the primary filter's reply back to false keeps the posts column.
	 */
	public function test_manage_users_columns_deprecated_disable_alias_can_keep_posts_column() {
		$this->stub_strict_filter( 'dwpb_disable_user_post_column', true, true );
		WP_Mock::userFunction( 'apply_filters_deprecated' )
			->once()
			->with( 'dpwb_disable_user_post_column', $this->strict_bool_array( true ), '0.5.6', 'dwpb_disable_user_post_column' )
			->andReturn( false );

		global $current_screen;
		$current_screen     = new stdClass();
		$current_screen->id = 'users';
		WP_Mock::userFunction( 'get_current_screen' )->once()->andReturn( $current_screen );

		$this->stub_default_user_column_post_types();

		$this->stub_strict_filter( 'dwpb_create_user_page_column', true, false );
		WP_Mock::userFunction( 'apply_filters_deprecated' )
			->once()
			->with( 'dpwb_create_user_page_column', $this->strict_bool_array( false ), '0.5.6', 'dwpb_create_user_page_column' )
			->andReturn( false );

		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$result = $admin->manage_users_columns( array( 'posts' => 'Posts' ) );

		$this->assertArrayHasKey( 'posts', $result );
		$this->assertArrayNotHasKey( 'page', $result );
	}

	/**
	 * Proves the deprecated dpwb_create_user_{$post_type}_column alias actually takes
	 * effect: the primary filter says yes, but the deprecated alias overrides to no.
	 */
	public function test_manage_users_columns_deprecated_create_alias_can_disable_page_column() {
		$this->stub_strict_filter( 'dwpb_disable_user_post_column', true, true );
		WP_Mock::userFunction( 'apply_filters_deprecated' )
			->once()
			->with( 'dpwb_disable_user_post_column', $this->strict_bool_array( true ), '0.5.6', 'dwpb_disable_user_post_column' )
			->andReturn( true );

		global $current_screen;
		$current_screen     = new stdClass();
		$current_screen->id = 'users';
		WP_Mock::userFunction( 'get_current_screen' )->once()->andReturn( $current_screen );

		$this->stub_default_user_column_post_types();

		$this->stub_strict_filter( 'dwpb_create_user_page_column', true, true );
		WP_Mock::userFunction( 'apply_filters_deprecated' )
			->once()
			->with( 'dpwb_create_user_page_column', $this->strict_bool_array( true ), '0.5.6', 'dwpb_create_user_page_column' )
			->andReturn( false );

		// get_post_type_object() must never be reached: nothing further is stubbed.
		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$result = $admin->manage_users_columns( array( 'posts' => 'Posts' ) );

		$this->assertArrayNotHasKey( 'page', $result );
	}

	public function test_manage_users_columns_skips_column_on_site_users_network_screen() {
		$this->stub_strict_filter( 'dwpb_disable_user_post_column', true, true );
		WP_Mock::userFunction( 'apply_filters_deprecated' )
			->once()
			->with( 'dpwb_disable_user_post_column', $this->strict_bool_array( true ), '0.5.6', 'dwpb_disable_user_post_column' )
			->andReturn( true );

		global $current_screen;
		$current_screen     = new stdClass();
		$current_screen->id = 'site-users-network';
		WP_Mock::userFunction( 'get_current_screen' )->once()->andReturn( $current_screen );

		$this->stub_default_user_column_post_types();

		$this->stub_strict_filter( 'dwpb_create_user_page_column', true, true );
		WP_Mock::userFunction( 'apply_filters_deprecated' )
			->once()
			->with( 'dpwb_create_user_page_column', $this->strict_bool_array( true ), '0.5.6', 'dwpb_create_user_page_column' )
			->andReturn( true );

		// get_post_type_object() must never be reached: nothing further is stubbed.
		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$result = $admin->manage_users_columns( array() );

		$this->assertArrayNotHasKey( 'page', $result );
	}

	public function test_manage_users_columns_skips_column_when_post_type_labels_missing() {
		$this->stub_strict_filter( 'dwpb_disable_user_post_column', true, true );
		WP_Mock::userFunction( 'apply_filters_deprecated' )
			->once()
			->with( 'dpwb_disable_user_post_column', $this->strict_bool_array( true ), '0.5.6', 'dwpb_disable_user_post_column' )
			->andReturn( true );

		global $current_screen;
		$current_screen     = new stdClass();
		$current_screen->id = 'users';
		WP_Mock::userFunction( 'get_current_screen' )->once()->andReturn( $current_screen );

		$this->stub_default_user_column_post_types();

		$this->stub_strict_filter( 'dwpb_create_user_page_column', true, true );
		WP_Mock::userFunction( 'apply_filters_deprecated' )
			->once()
			->with( 'dpwb_create_user_page_column', $this->strict_bool_array( true ), '0.5.6', 'dwpb_create_user_page_column' )
			->andReturn( true );

		// No 'labels' property at all -- isset( $post_type_obj->labels->name ) is false.
		WP_Mock::userFunction( 'get_post_type_object' )->once()->with( 'page' )->andReturn( new stdClass() );

		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$result = $admin->manage_users_columns( array() );

		$this->assertArrayNotHasKey( 'page', $result );
	}

	public function test_manage_users_columns_adds_columns_for_additional_author_archive_post_types() {
		$this->stub_strict_filter( 'dwpb_disable_user_post_column', true, true );
		WP_Mock::userFunction( 'apply_filters_deprecated' )
			->once()
			->with( 'dpwb_disable_user_post_column', $this->strict_bool_array( true ), '0.5.6', 'dwpb_disable_user_post_column' )
			->andReturn( true );

		global $current_screen;
		$current_screen     = new stdClass();
		$current_screen->id = 'users';
		WP_Mock::userFunction( 'get_current_screen' )->once()->andReturn( $current_screen );

		$this->stub_strict_filter( 'dwpb_author_archive_post_types', array(), array( 'book' ) );
		WP_Mock::onFilter( 'dwpb_admin_user_post_types' )
			->with( array( 'page', 'book' ) )
			->reply( array( 'page', 'book' ) );

		foreach ( array( 'page', 'book' ) as $post_type ) {
			$this->stub_strict_filter( "dwpb_create_user_{$post_type}_column", true, true );
			WP_Mock::userFunction( 'apply_filters_deprecated' )
				->once()
				->with( "dpwb_create_user_{$post_type}_column", $this->strict_bool_array( true ), '0.5.6', "dwpb_create_user_{$post_type}_column" )
				->andReturn( true );

			$type_obj                 = new stdClass();
			$type_obj->labels         = new stdClass();
			$type_obj->labels->name   = ucfirst( $post_type ) . 's';
			WP_Mock::userFunction( 'get_post_type_object' )->once()->with( $post_type )->andReturn( $type_obj );
		}

		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$result = $admin->manage_users_columns( array() );

		$this->assertSame( 'Pages', $result['page'] );
		$this->assertSame( 'Books', $result['book'] );
	}

	/**
	 * manage_users_custom_column()
	 */

	public function test_manage_users_custom_column_builds_output_for_matching_post_type() {
		$this->stub_default_user_column_post_types();

		$page_type                     = new stdClass();
		$page_type->labels             = new stdClass();
		$page_type->labels->name       = 'Pages';
		$page_type->labels->singular_name = 'Page';
		WP_Mock::userFunction( 'get_post_type_object' )->once()->with( 'page' )->andReturn( $page_type );

		WP_Mock::userFunction( 'count_many_users_posts' )->once()->with(
			Mockery::on(
				function ( $user_ids ) {
					$this->assertSame( array( 42 ), $user_ids );
					return true;
				}
			),
			'page'
		)->andReturn( array( 42 => '3' ) );
		WP_Mock::userFunction( 'absint' )->once()->with( '3' )->andReturn( 3 );
		WP_Mock::userFunction( 'number_format_i18n' )->once()->with( 3 )->andReturn( '3' );

		// _n() is one of WP_Mock's hard-coded predefined functions (see
		// vendor/10up/wp_mock/php/WP_Mock/API/function-mocks.php): it runs its own real
		// singular/plural selection logic unconditionally and is never routed through
		// WP_Mock::userFunction(), so it is deliberately left unstubbed here.
		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$output = $admin->manage_users_custom_column( 'original', 'page', 42 );

		$this->assertStringContainsString( 'edit.php?post_type=page&author=42', $output );
		$this->assertStringContainsString( '3 Pages by this author', $output );
	}

	/**
	 * A count of 1 selects _n()'s singular string ( using labels->singular_name ) rather
	 * than the plural ( using labels->name ), proving both labels are threaded through
	 * correctly rather than one being used for both.
	 */
	public function test_manage_users_custom_column_uses_singular_label_for_count_of_one() {
		$this->stub_default_user_column_post_types();

		$page_type                     = new stdClass();
		$page_type->labels             = new stdClass();
		$page_type->labels->name       = 'Pages';
		$page_type->labels->singular_name = 'Page';
		WP_Mock::userFunction( 'get_post_type_object' )->once()->with( 'page' )->andReturn( $page_type );

		WP_Mock::userFunction( 'count_many_users_posts' )->once()->with(
			Mockery::on(
				function ( $user_ids ) {
					$this->assertSame( array( 7 ), $user_ids );
					return true;
				}
			),
			'page'
		)->andReturn( array( 7 => '1' ) );
		WP_Mock::userFunction( 'absint' )->once()->with( '1' )->andReturn( 1 );
		WP_Mock::userFunction( 'number_format_i18n' )->once()->with( 1 )->andReturn( '1' );

		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$output = $admin->manage_users_custom_column( 'original', 'page', 7 );

		$this->assertStringContainsString( '1 Page by this author', $output );
		$this->assertStringNotContainsString( '1 Pages by this author', $output );
	}

	public function test_manage_users_custom_column_returns_output_unchanged_for_non_matching_column() {
		$this->stub_default_user_column_post_types();

		// get_post_type_object()/count_many_users_posts() must never be reached: nothing
		// further is stubbed.
		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertSame( 'original', $admin->manage_users_custom_column( 'original', 'unrelated-column', 7 ) );
	}

	public function test_manage_users_custom_column_skips_when_post_type_labels_missing() {
		$this->stub_strict_filter( 'dwpb_author_archive_post_types', array(), array( 'book' ) );
		WP_Mock::onFilter( 'dwpb_admin_user_post_types' )
			->with( array( 'page', 'book' ) )
			->reply( array( 'page', 'book' ) );

		// Missing singular_name -- isset()'s second argument fails, so continue is hit.
		$book_type           = new stdClass();
		$book_type->labels   = new stdClass();
		$book_type->labels->name = 'Books';
		WP_Mock::userFunction( 'get_post_type_object' )->once()->with( 'book' )->andReturn( $book_type );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertSame( 'original', $admin->manage_users_custom_column( 'original', 'book', 7 ) );
	}

	/**
	 * user_column_post_types()
	 */

	public function test_user_column_post_types_defaults_to_page_only() {
		$this->stub_default_user_column_post_types();

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertSame( array( 'page' ), $this->invoke_user_column_post_types( $admin ) );
	}

	public function test_user_column_post_types_merges_author_archive_post_types_with_page() {
		$this->stub_strict_filter( 'dwpb_author_archive_post_types', array(), array( 'book' ) );
		WP_Mock::onFilter( 'dwpb_admin_user_post_types' )
			->with( array( 'page', 'book' ) )
			->reply( array( 'page', 'book' ) );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertSame( array( 'page', 'book' ), $this->invoke_user_column_post_types( $admin ) );
	}

	public function test_user_column_post_types_filter_can_override_the_whole_list() {
		$this->stub_strict_filter( 'dwpb_author_archive_post_types', array(), array() );
		$this->stub_strict_filter( 'dwpb_admin_user_post_types', array( 'page' ), array( 'custom-cpt' ) );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertSame( array( 'custom-cpt' ), $this->invoke_user_column_post_types( $admin ) );
	}

	/**
	 * user_row_actions()
	 */

	public function test_user_row_actions_removes_view_link_when_author_archives_disabled() {
		$this->stub_strict_filter( 'dwpb_disable_author_archives', false, true );

		$admin   = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$actions = array( 'view' => '<a>View</a>' );

		$this->assertSame( array(), $admin->user_row_actions( $actions, new stdClass() ) );
	}

	public function test_user_row_actions_keeps_view_link_when_author_archives_enabled() {
		$this->stub_strict_filter( 'dwpb_disable_author_archives', false, false );

		$admin   = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$actions = array( 'view' => '<a>View</a>' );

		$this->assertSame( $actions, $admin->user_row_actions( $actions, new stdClass() ) );
	}

	public function test_user_row_actions_no_op_when_view_link_already_absent() {
		$this->stub_strict_filter( 'dwpb_disable_author_archives', false, true );

		$admin   = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$actions = array( 'edit' => '<a>Edit</a>' );

		$this->assertSame( $actions, $admin->user_row_actions( $actions, new stdClass() ) );
	}
}
