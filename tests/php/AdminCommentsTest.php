<?php
/**
 * Tests for Disable_Blog_Admin's comment-related surface: get_comment_counts(),
 * filter_wp_count_comments(), filter_admin_table_comment_count(), comment_filter(),
 * filter_comment_status(), filter_existing_comments(), filter_taxonomy_count() and
 * get_term_post_count_by_type().
 *
 * @package DisableBlog
 */

// Not required by tests/php/bootstrap.php on purpose (see ConstructorInjectionTest.php's note;
// LoaderTest's autoloader test needs Disable_Blog_Functions to remain undefined until its own
// isolated process). require_once is safe regardless of which test file happens to load first.
require_once __DIR__ . '/../../includes/class-disable-blog-functions.php';
require_once __DIR__ . '/Support/fixtures/class-admin-wpdb-double.php';

/**
 * Records the SQL string get_comment_counts() passes to get_results(), so tests can assert
 * on it directly. Defined here (rather than in Support/fixtures/) since only this file needs
 * the capture behavior.
 */
class Disable_Blog_Admin_Wpdb_Query_Capturing_Double extends Disable_Blog_Admin_Wpdb_Double {

	/**
	 * The query string passed to the most recent get_results() call.
	 *
	 * @var string|null
	 */
	public $captured_query;

	/**
	 * @param string      $query  The SQL query.
	 * @param string|null $output The output type.
	 * @return array
	 */
	public function get_results( $query, $output = null ) {
		$this->captured_query = $query;
		return parent::get_results( $query, $output );
	}
}

/**
 * @covers Disable_Blog_Admin::get_comment_counts
 * @covers Disable_Blog_Admin::filter_wp_count_comments
 * @covers Disable_Blog_Admin::filter_admin_table_comment_count
 * @covers Disable_Blog_Admin::comment_filter
 * @covers Disable_Blog_Admin::filter_comment_status
 * @covers Disable_Blog_Admin::filter_existing_comments
 * @covers Disable_Blog_Admin::filter_taxonomy_count
 * @covers Disable_Blog_Admin::get_term_post_count_by_type
 */
class AdminCommentsTest extends TestCase {

	/**
	 * The global $wpdb as it stood before the test, so it can be restored in tear_down().
	 *
	 * @var mixed
	 */
	private $original_wpdb;

	/**
	 * The global $pagenow as it stood before the test, so it can be restored in tear_down().
	 *
	 * @var mixed
	 */
	private $original_pagenow;

	/**
	 * The global $current_screen as it stood before the test, so it can be restored in tear_down().
	 *
	 * @var mixed
	 */
	private $original_current_screen;

	protected function set_up() {
		parent::set_up();

		global $wpdb, $pagenow, $current_screen;
		$this->original_wpdb           = $wpdb ?? null;
		$this->original_pagenow        = $pagenow ?? null;
		$this->original_current_screen = $current_screen ?? null;
		$pagenow                       = null;
	}

	protected function tear_down() {
		global $wpdb, $pagenow, $current_screen;
		$wpdb           = $this->original_wpdb;
		$pagenow        = $this->original_pagenow;
		$current_screen = $this->original_current_screen;

		parent::tear_down();
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
			->reply( $return_value );
	}

	/**
	 * Builds a $wpdb double whose get_results() answers $rows and whose ->comments /
	 * ->posts table-name properties are set, exactly as get_comment_counts()'s SQL string
	 * interpolation reads them.
	 *
	 * A plain declared class (rather than Mockery::mock()) so phpstan can see the
	 * ->comments/->posts properties and the get_results() method for itself.
	 *
	 * @param array $rows The rows get_results() should return.
	 * @return void
	 */
	private function stub_wpdb_get_results( array $rows ) {
		global $wpdb;
		$wpdb = new Disable_Blog_Admin_Wpdb_Double( $rows );

		WP_Mock::userFunction( 'esc_sql' )->andReturnUsing(
			function ( $value ) {
				return $value;
			}
		);
	}

	/**
	 * get_comment_counts()
	 */

	public function test_get_comment_counts_returns_zeroed_counts_when_no_post_types_support_comments() {
		$this->stub_feature_cache_hit( 'comments', false );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$expected = array(
			'moderated'           => 0,
			'approved'            => 0,
			'awaiting_moderation' => 0,
			'spam'                => 0,
			'trash'               => 0,
			'post-trashed'        => 0,
			'total_comments'      => 0,
			'all'                 => 0,
		);
		$this->assertSame( $expected, $admin->get_comment_counts() );
	}

	/**
	 * `! is_array( $supported_post_types )` is the second half of the early-return `||`
	 * check; a truthy, non-array reply (never returned by the real helper, but a valid
	 * filter reply) is the only way to prove that half is evaluated and honored.
	 */
	public function test_get_comment_counts_returns_zeroed_counts_when_supported_post_types_not_an_array() {
		$this->stub_feature_cache_hit( 'comments', true );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$expected = array(
			'moderated'           => 0,
			'approved'            => 0,
			'awaiting_moderation' => 0,
			'spam'                => 0,
			'trash'               => 0,
			'post-trashed'        => 0,
			'total_comments'      => 0,
			'all'                 => 0,
		);
		$this->assertSame( $expected, $admin->get_comment_counts() );
	}

	public function test_get_comment_counts_trash_row_only() {
		$this->stub_feature_cache_hit( 'comments', array( 'page' ) );
		$this->stub_wpdb_get_results(
			array(
				array(
					'comment_approved' => 'trash',
					'total'            => '2',
				),
			)
		);

		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$counts = $admin->get_comment_counts();

		$this->assertSame( 2, $counts['trash'] );
		$this->assertSame( 0, $counts['total_comments'] );
		$this->assertSame( 0, $counts['all'] );
	}

	public function test_get_comment_counts_post_trashed_row_only() {
		$this->stub_feature_cache_hit( 'comments', array( 'page' ) );
		$this->stub_wpdb_get_results(
			array(
				array(
					'comment_approved' => 'post-trashed',
					'total'            => '1',
				),
			)
		);

		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$counts = $admin->get_comment_counts();

		$this->assertSame( 1, $counts['post-trashed'] );
		$this->assertSame( 0, $counts['total_comments'] );
		$this->assertSame( 0, $counts['all'] );
	}

	public function test_get_comment_counts_spam_row_only() {
		$this->stub_feature_cache_hit( 'comments', array( 'page' ) );
		$this->stub_wpdb_get_results(
			array(
				array(
					'comment_approved' => 'spam',
					'total'            => '4',
				),
			)
		);

		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$counts = $admin->get_comment_counts();

		$this->assertSame( 4, $counts['spam'] );
		$this->assertSame( 4, $counts['total_comments'] );
		$this->assertSame( 0, $counts['all'] );
	}

	public function test_get_comment_counts_approved_row_only() {
		$this->stub_feature_cache_hit( 'comments', array( 'page' ) );
		$this->stub_wpdb_get_results(
			array(
				array(
					'comment_approved' => '1',
					'total'            => '10',
				),
			)
		);

		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$counts = $admin->get_comment_counts();

		$this->assertSame( 10, $counts['approved'] );
		$this->assertSame( 10, $counts['total_comments'] );
		$this->assertSame( 10, $counts['all'] );
	}

	public function test_get_comment_counts_awaiting_moderation_row_only() {
		$this->stub_feature_cache_hit( 'comments', array( 'page' ) );
		$this->stub_wpdb_get_results(
			array(
				array(
					'comment_approved' => '0',
					'total'            => '3',
				),
			)
		);

		$admin  = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$counts = $admin->get_comment_counts();

		$this->assertSame( 3, $counts['awaiting_moderation'] );
		$this->assertSame( 3, $counts['moderated'] );
		$this->assertSame( 3, $counts['total_comments'] );
		$this->assertSame( 3, $counts['all'] );
	}

	/**
	 * An unrecognized comment_approved value must hit the switch's `default: break;` arm
	 * and leave every counter untouched.
	 */
	public function test_get_comment_counts_unrecognized_status_row_hits_default_arm() {
		$this->stub_feature_cache_hit( 'comments', array( 'page' ) );
		$this->stub_wpdb_get_results(
			array(
				array(
					'comment_approved' => 'unmapped-status',
					'total'            => '99',
				),
			)
		);

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$expected = array(
			'moderated'           => 0,
			'approved'            => 0,
			'awaiting_moderation' => 0,
			'spam'                => 0,
			'trash'               => 0,
			'post-trashed'        => 0,
			'total_comments'      => 0,
			'all'                 => 0,
		);
		$this->assertSame( $expected, $admin->get_comment_counts() );
	}

	/**
	 * All six switch arms in a single response, proving the running totals accumulate
	 * correctly across rows rather than only in isolation.
	 */
	public function test_get_comment_counts_aggregates_every_status_across_multiple_rows() {
		$this->stub_feature_cache_hit( 'comments', array( 'page' ) );
		$this->stub_wpdb_get_results(
			array(
				array(
					'comment_approved' => 'trash',
					'total'            => '2',
				),
				array(
					'comment_approved' => 'post-trashed',
					'total'            => '1',
				),
				array(
					'comment_approved' => 'spam',
					'total'            => '4',
				),
				array(
					'comment_approved' => '1',
					'total'            => '10',
				),
				array(
					'comment_approved' => '0',
					'total'            => '3',
				),
				array(
					'comment_approved' => 'unmapped-status',
					'total'            => '99',
				),
			)
		);

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$expected = array(
			'moderated'           => 3,
			'approved'            => 10,
			'awaiting_moderation' => 3,
			'spam'                => 4,
			'trash'               => 2,
			'post-trashed'        => 1,
			'total_comments'      => 17,
			'all'                 => 13,
		);
		$this->assertSame( $expected, $admin->get_comment_counts() );
	}

	/**
	 * The post types are joined with `implode( "','", ... )` so each becomes its own quoted
	 * SQL string literal inside the `IN (...)` list. Asserts the exact resulting clause,
	 * since a plain `implode( ',', ... )` would still produce a query that "looks" similar
	 * but silently collapses every post type into a single unquoted/mis-quoted literal.
	 */
	public function test_get_comment_counts_quotes_each_post_type_separately_in_the_in_clause() {
		$this->stub_feature_cache_hit( 'comments', array( 'book', 'recipe', 'event' ) );

		global $wpdb;
		$wpdb = new Disable_Blog_Admin_Wpdb_Query_Capturing_Double( array() );
		WP_Mock::userFunction( 'esc_sql' )->andReturnUsing(
			function ( $value ) {
				return $value;
			}
		);

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->get_comment_counts();

		$this->assertStringContainsString( "post_type in ('book','recipe','event')", $wpdb->captured_query );
	}

	/**
	 * filter_wp_count_comments()
	 */

	public function test_filter_wp_count_comments_returns_cached_value_when_post_id_zero_and_cache_hit() {
		$cached = (object) array( 'all' => 5 );
		WP_Mock::userFunction( 'wp_cache_get' )->once()->with( 'comments-0', 'counts' )->andReturn( $cached );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		// Deliberately nothing else stubbed: get_comment_counts()'s dependencies (e.g. the
		// dwpb_post_types_supporting_comments filter) would throw under strict mode if
		// reached, proving the cache hit short-circuits before get_comment_counts() runs.
		$this->assertSame( $cached, $admin->filter_wp_count_comments( new stdClass(), 0 ) );
	}

	public function test_filter_wp_count_comments_computes_and_caches_on_cache_miss() {
		WP_Mock::userFunction( 'wp_cache_get' )->once()->with( 'comments-0', 'counts' )->andReturn( false );

		$computed = array(
			'moderated'           => 0,
			'approved'            => 2,
			'awaiting_moderation' => 3,
			'spam'                => 0,
			'trash'               => 0,
			'post-trashed'        => 0,
			'total_comments'      => 5,
			'all'                 => 5,
		);

		$admin = $this->getMockBuilder( Disable_Blog_Admin::class )
			->setConstructorArgs( array( 'disable-blog', '0.5.6' ) )
			->onlyMethods( array( 'get_comment_counts' ) )
			->getMock();
		$admin->method( 'get_comment_counts' )->willReturn( $computed );

		$expected                          = $computed;
		$expected['moderated']             = 3; // Overwritten from 'awaiting_moderation'.
		unset( $expected['awaiting_moderation'] );

		WP_Mock::userFunction( 'wp_cache_set' )->once()->with(
			'comments-0',
			Mockery::on(
				function ( $comments ) use ( $expected ) {
					$this->assertSame( $expected, (array) $comments );
					return true;
				}
			),
			'counts'
		);

		$result = $admin->filter_wp_count_comments( new stdClass(), 0 );

		$this->assertSame( $expected, (array) $result );
	}

	public function test_filter_wp_count_comments_returns_comments_unchanged_when_post_id_nonzero() {
		$admin    = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$comments = (object) array( 'all' => 9 );

		// Nothing stubbed: wp_cache_get()/get_comment_counts() must never be reached.
		$this->assertSame( $comments, $admin->filter_wp_count_comments( $comments, 42 ) );
	}

	/**
	 * filter_admin_table_comment_count()
	 */

	public function test_filter_admin_table_comment_count_rewrites_matching_views_on_edit_comments_screen() {
		global $current_screen;
		$current_screen     = new stdClass();
		$current_screen->id = 'edit-comments';

		$admin = $this->getMockBuilder( Disable_Blog_Admin::class )
			->setConstructorArgs( array( 'disable-blog', '0.5.6' ) )
			->onlyMethods( array( 'get_comment_counts' ) )
			->getMock();
		$admin->method( 'get_comment_counts' )->willReturn(
			array(
				'all'   => 3,
				'trash' => 1,
			)
		);

		$views = array(
			'all'      => 'All <span class="count">(5)</span>',
			'trash'    => 'Trash <span class="count">(2)</span>',
			'approved' => 'Approved <span class="count">(4)</span>', // No matching key in the updated counts.
		);

		$result = $admin->filter_admin_table_comment_count( $views );

		$this->assertSame( 'All <span class="count">(<span class="all-count">3</span>)</span>', $result['all'] );
		$this->assertSame( 'Trash <span class="count">(<span class="trash-count">1</span>)</span>', $result['trash'] );
		$this->assertSame( $views['approved'], $result['approved'] );
	}

	public function test_filter_admin_table_comment_count_leaves_views_unchanged_on_other_screens() {
		global $current_screen;
		$current_screen     = new stdClass();
		$current_screen->id = 'edit-post';

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$views = array( 'all' => 'All <span class="count">(5)</span>' );

		// get_comment_counts() must never be reached: nothing further is stubbed.
		$this->assertSame( $views, $admin->filter_admin_table_comment_count( $views ) );
	}

	/**
	 * comment_filter()
	 */

	public function test_comment_filter_returns_unchanged_when_pagenow_not_set() {
		$admin    = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$comments = new stdClass();

		$this->assertSame( $comments, $admin->comment_filter( $comments ) );
	}

	public function test_comment_filter_sets_post_type_query_var_on_edit_comments_with_supported_types() {
		global $pagenow;
		$pagenow = 'edit-comments.php';

		$this->stub_feature_cache_hit( 'comments', array( 'book' ) );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );

		$admin                = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$comments              = new stdClass();
		$comments->query_vars  = array();

		$result = $admin->comment_filter( $comments );

		$this->assertSame( array( 'book' ), $result->query_vars['post_type'] );
	}

	public function test_comment_filter_leaves_comments_unchanged_when_no_supported_post_types() {
		global $pagenow;
		$pagenow = 'edit-comments.php';

		$this->stub_feature_cache_hit( 'comments', false );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );

		$admin                 = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$comments              = new stdClass();
		$comments->query_vars  = array();

		$result = $admin->comment_filter( $comments );

		$this->assertSame( array(), $result->query_vars );
	}

	public function test_comment_filter_leaves_comments_unchanged_when_not_on_edit_comments_page() {
		global $pagenow;
		$pagenow = 'edit.php';

		$this->stub_feature_cache_hit( 'comments', array( 'book' ) );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );

		$admin                = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$comments              = new stdClass();
		$comments->query_vars  = array();

		$result = $admin->comment_filter( $comments );

		$this->assertSame( array(), $result->query_vars );
	}

	/**
	 * filter_comment_status()
	 */

	public function test_filter_comment_status_returns_false_for_post_post_type() {
		WP_Mock::userFunction( 'get_post_type' )->once()->with( 5 )->andReturn( 'post' );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertFalse( $admin->filter_comment_status( true, 5 ) );
	}

	public function test_filter_comment_status_passes_open_through_for_other_post_types() {
		WP_Mock::userFunction( 'get_post_type' )->once()->with( 5 )->andReturn( 'page' );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertTrue( $admin->filter_comment_status( true, 5 ) );
		WP_Mock::userFunction( 'get_post_type' )->once()->with( 5 )->andReturn( 'page' );
		$this->assertFalse( $admin->filter_comment_status( false, 5 ) );
	}

	/**
	 * filter_existing_comments()
	 */

	public function test_filter_existing_comments_returns_empty_array_for_post_post_type() {
		WP_Mock::userFunction( 'get_post_type' )->once()->with( 5 )->andReturn( 'post' );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertSame( array(), $admin->filter_existing_comments( array( 'a-comment' ), 5 ) );
	}

	public function test_filter_existing_comments_passes_comments_through_for_other_post_types() {
		WP_Mock::userFunction( 'get_post_type' )->once()->with( 5 )->andReturn( 'page' );

		$admin    = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$comments = array( 'a-comment' );

		$this->assertSame( $comments, $admin->filter_existing_comments( $comments, 5 ) );
	}

	/**
	 * filter_taxonomy_count()
	 */

	public function test_filter_taxonomy_count_updates_count_for_category_tag() {
		global $current_screen;
		$current_screen            = new stdClass();
		$current_screen->post_type = 'book';
		WP_Mock::userFunction( 'get_current_screen' )->once()->andReturn( $current_screen );

		$admin = $this->getMockBuilder( Disable_Blog_Admin::class )
			->setConstructorArgs( array( 'disable-blog', '0.5.6' ) )
			->onlyMethods( array( 'get_term_post_count_by_type' ) )
			->getMock();
		$admin->expects( $this->once() )
			->method( 'get_term_post_count_by_type' )
			->with( 7, 'category', 'book' )
			->willReturn( 4 );

		$tag           = new stdClass();
		$tag->taxonomy = 'category';
		$tag->term_id  = 7;
		$tag->count    = 1;

		$actions = array( 'edit' => '<a>Edit</a>' );
		$result  = $admin->filter_taxonomy_count( $actions, $tag );

		$this->assertSame( $actions, $result );
		$this->assertSame( 4, $tag->count );
	}

	public function test_filter_taxonomy_count_updates_count_for_post_tag_tag() {
		global $current_screen;
		$current_screen            = new stdClass();
		$current_screen->post_type = 'page';
		WP_Mock::userFunction( 'get_current_screen' )->once()->andReturn( $current_screen );

		$admin = $this->getMockBuilder( Disable_Blog_Admin::class )
			->setConstructorArgs( array( 'disable-blog', '0.5.6' ) )
			->onlyMethods( array( 'get_term_post_count_by_type' ) )
			->getMock();
		$admin->expects( $this->once() )
			->method( 'get_term_post_count_by_type' )
			->with( 9, 'post_tag', 'page' )
			->willReturn( 0 );

		$tag           = new stdClass();
		$tag->taxonomy = 'post_tag';
		$tag->term_id  = 9;
		$tag->count    = 6;

		$admin->filter_taxonomy_count( array(), $tag );

		$this->assertSame( 0, $tag->count );
	}

	public function test_filter_taxonomy_count_leaves_unrelated_taxonomy_unchanged() {
		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$tag           = new stdClass();
		$tag->taxonomy = 'custom_tax';
		$tag->term_id  = 3;
		$tag->count    = 2;

		// get_current_screen() must never be reached: nothing further is stubbed.
		$admin->filter_taxonomy_count( array(), $tag );

		$this->assertSame( 2, $tag->count );
	}

	public function test_filter_taxonomy_count_leaves_tag_unchanged_when_required_properties_missing() {
		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$tag           = new stdClass();
		$tag->taxonomy = 'category';
		// The count and term id properties are deliberately left unset.

		$actions = array( 'edit' => '<a>Edit</a>' );
		$this->assertSame( $actions, $admin->filter_taxonomy_count( $actions, $tag ) );
	}

	/**
	 * get_term_post_count_by_type()
	 */

	/**
	 * Makes the next `new WP_Query( $args )` call return an instance whose ->posts is
	 * $posts, and records the constructor args into $captured_args by reference.
	 *
	 * Uses Mockery::mock( 'overload:WP_Query' ) rather than a same-named fixture class: a
	 * real `class WP_Query` declared in tests/php/ would be picked up by phpstan.neon.dist's
	 * directory scan and shadow the real WP_Query stub phpstan resolves from
	 * szepeviktor/phpstan-wordpress when analysing includes/class-disable-blog-admin.php's own
	 * WP_Query usage (see class-wp-post-stub.php's docblock for the same reasoning). An
	 * overload mock is instead defined purely at runtime, so phpstan's static scan never sees
	 * it -- but that also means the instance handed back for the ->posts assignment below is
	 * only known to phpstan as Mockery\MockInterface, which declares no ->posts property.
	 * There is no supported way to type that without the disallowed phpstan-mockery
	 * extension; see this file's scoped phpstan.neon.dist ignores for the resulting errors.
	 *
	 * @param array      $posts         The posts array the query should report.
	 * @param array|null $captured_args Set by reference to the args WP_Query was constructed with.
	 * @return void
	 */
	private function stub_wp_query_returns_posts( array $posts, &$captured_args = null ) {
		$container = Mockery::getContainer();
		$container->mock( 'overload:WP_Query' )
			->shouldReceive( '__construct' )
			->once()
			->andReturnUsing(
				function ( $args ) use ( $container, $posts, &$captured_args ) {
					$captured_args    = $args;
					$mocks            = $container->getMocks();
					$instance         = end( $mocks );
					$instance->posts  = $posts;
				}
			);
	}

	/**
	 * Declaring WP_Query (even as a mock) is permanent for the rest of the process, so
	 * every test using stub_wp_query_returns_posts() must run isolated.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_term_post_count_by_type_returns_post_count_when_posts_found() {
		$this->stub_wp_query_returns_posts( array( 101, 102, 103 ) );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertSame( 3, $admin->get_term_post_count_by_type( 5, 'category', 'page' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_term_post_count_by_type_returns_zero_when_no_posts_found() {
		$this->stub_wp_query_returns_posts( array() );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$this->assertSame( 0, $admin->get_term_post_count_by_type( 5, 'category', 'page' ) );
	}

	/**
	 * Proves the query args passed to `new WP_Query()` are wired correctly: the taxonomy,
	 * term id and post type all land in the expected places.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_term_post_count_by_type_builds_expected_query_args() {
		$captured_args = null;
		$this->stub_wp_query_returns_posts( array(), $captured_args );

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );
		$admin->get_term_post_count_by_type( 7, 'post_tag', 'page' );

		$this->assertSame( 'page', $captured_args['post_type'] );
		$this->assertSame( 'ids', $captured_args['fields'] );
		$this->assertSame( 'post_tag', $captured_args['tax_query'][0]['taxonomy'] );
		$this->assertSame( 'id', $captured_args['tax_query'][0]['field'] );
		$this->assertSame( 7, $captured_args['tax_query'][0]['terms'] );
	}
}
