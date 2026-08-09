<?php
/**
 * Tests for includes/functions.php.
 *
 * @package DisableBlog
 */

/**
 * @covers ::dwpb_post_types_with_feature
 * @covers ::dwpb_post_types_with_tax
 */
class HelperFunctionsTest extends TestCase {

	/**
	 * Stubs esc_attr() and maybe_serialize() as identity-like passthroughs matching the
	 * exact $taxonomy/$args dwpb_post_types_with_tax() builds its cache key from, so
	 * tax_cache_key() below computes the same string the source will.
	 *
	 * @param string $taxonomy The taxonomy slug.
	 * @param array  $args     The $args passed to dwpb_post_types_with_tax().
	 * @return void
	 */
	private function stub_tax_cache_key_helpers( $taxonomy, $args = array() ) {
		WP_Mock::userFunction( 'esc_attr' )->with( $taxonomy )->andReturn( $taxonomy );
		WP_Mock::userFunction( 'maybe_serialize' )->with( $args )->andReturn( serialize( $args ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
	}

	/**
	 * A Mockery argument matcher for an empty array, specifically -- Mockery's default
	 * `with()` comparison is loose `==`, under which `array() == false` is true, so a bare
	 * `array()` matcher would silently accept `false` too. Used wherever a call is expected
	 * to receive the literal `$args = array()` default, to catch that default being
	 * corrupted to `false` (or any other falsy non-array value).
	 *
	 * @return Mockery\Matcher\Closure
	 */
	private function strict_empty_array() {
		return Mockery::on(
			static function ( $value ) {
				return array() === $value;
			}
		);
	}

	/**
	 * Computes the same cache key dwpb_post_types_with_tax() builds, given
	 * stub_tax_cache_key_helpers() has stubbed esc_attr()/maybe_serialize() as passthroughs.
	 *
	 * @param string $taxonomy The taxonomy slug.
	 * @param array  $args     The $args passed to dwpb_post_types_with_tax().
	 * @param string $output   The $output passed to dwpb_post_types_with_tax().
	 * @return string
	 */
	private function tax_cache_key( $taxonomy, $args = array(), $output = 'names' ) {
		return 'post-types-with-tax-' . md5( $taxonomy ) . '-' . md5( $output ) . '-' . md5( serialize( $args ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
	}

	/**
	 * Registers the dwpb_post_types_supporting_{$feature} filter (2 arguments:
	 * $post_types_with_feature, $args) so both positions are asserted strictly.
	 * safe_offset() flattens both for ->with() routing -- false, '', array(), and null all
	 * collide there -- so a plain ->with() match cannot tell a corrupted false/array()/''
	 * apart from the documented value. The InvokedFilterValue responder receives the real
	 * invoked arguments via func_get_args() regardless of which key matched, so both are
	 * asserted with assertSame().
	 *
	 * @param string $filter                   The filter hook name.
	 * @param mixed  $post_types_with_feature   The exact position-1 value the filter must receive.
	 * @param mixed  $args                      The exact position-2 value the filter must receive.
	 * @param mixed  $reply                     The value the filter should return.
	 * @return void
	 */
	private function stub_post_types_supporting_filter( $filter, $post_types_with_feature, $args, $reply ) {
		WP_Mock::onFilter( $filter )
			->with( $post_types_with_feature, $args )
			->reply(
				new WP_Mock\InvokedFilterValue(
					function ( $received_post_types_with_feature, $received_args ) use ( $filter, $post_types_with_feature, $args, $reply ) {
						$this->assertSame( $post_types_with_feature, $received_post_types_with_feature, "{$filter} must receive the exact post types value it documents." );
						$this->assertSame( $args, $received_args, "{$filter} must receive the exact \$args value it documents." );

						return $reply;
					}
				)
			);
	}

	/**
	 * Registers the dwpb_taxonomy_support filter (5 arguments: null, $taxonomy,
	 * $post_types, $args, $output) so position-4 ($args) is asserted strictly.
	 * safe_offset() flattens $args for ->with() routing -- the array() default collides
	 * with false and '' there -- so a plain ->with() match cannot tell a corrupted $args
	 * apart from the documented array(). The InvokedFilterValue responder receives the
	 * real invoked $args via func_get_args() regardless of which key matched, so it is
	 * asserted with assertSame().
	 *
	 * @param string|object $taxonomy   The $taxonomy argument.
	 * @param array         $post_types The $post_types argument.
	 * @param array         $args       The exact $args value the filter must receive.
	 * @param string        $output     The $output argument.
	 * @param mixed         $reply      The value the filter should return.
	 * @return void
	 */
	private function stub_taxonomy_support_filter( $taxonomy, $post_types, $args, $output, $reply ) {
		WP_Mock::onFilter( 'dwpb_taxonomy_support' )
			->with( null, $taxonomy, $post_types, $args, $output )
			->reply(
				new WP_Mock\InvokedFilterValue(
					function ( $received_null, $received_taxonomy, $received_post_types, $received_args, $received_output ) use ( $args, $reply ) {
						$this->assertSame( $args, $received_args, 'dwpb_taxonomy_support must receive the exact $args value it documents.' );

						return $reply;
					}
				)
			);
	}

	/**
	 * A falsy feature must short-circuit before any WordPress function is touched.
	 *
	 * @return void
	 */
	public function test_post_types_with_feature_falsy_feature_returns_false() {
		$this->assertFalse( dwpb_post_types_with_feature( '' ) );
		$this->assertFalse( dwpb_post_types_with_feature( 0 ) );
		$this->assertFalse( dwpb_post_types_with_feature( false ) );
		$this->assertFalse( dwpb_post_types_with_feature( null ) );
	}

	/**
	 * A non-string feature must also short-circuit before any WordPress function is touched.
	 *
	 * @return void
	 */
	public function test_post_types_with_feature_non_string_feature_returns_false() {
		$this->assertFalse( dwpb_post_types_with_feature( array( 'comments' ) ) );
		$this->assertFalse( dwpb_post_types_with_feature( 42 ) );
	}

	/**
	 * Cache miss: the function must query get_post_types(), filter by post_type_supports(),
	 * exclude 'post', and populate the cache with the result.
	 *
	 * @return void
	 */
	public function test_post_types_with_feature_cache_miss_queries_and_populates_cache() {
		$feature = 'comments';
		$args    = array( 'public' => true );

		WP_Mock::userFunction( 'esc_attr' )
			->once()
			->with( $feature )
			->andReturn( $feature );

		WP_Mock::userFunction( 'wp_cache_get' )
			->once()
			->with( 'post-types-supporting-comments', 'post-types-by-feature' )
			->andReturn( false );

		WP_Mock::userFunction( 'get_post_types' )
			->once()
			->with( $args, 'names' )
			->andReturn( array( 'post', 'page', 'attachment' ) );

		// 'post' supports the feature too, but must be excluded regardless.
		WP_Mock::userFunction( 'post_type_supports' )->with( 'post', $feature )->andReturn( true );
		WP_Mock::userFunction( 'post_type_supports' )->with( 'page', $feature )->andReturn( true );
		WP_Mock::userFunction( 'post_type_supports' )->with( 'attachment', $feature )->andReturn( false );

		WP_Mock::userFunction( 'wp_cache_set' )
			->once()
			->with( 'post-types-supporting-comments', array( 'page' ), 'post-types-by-feature' );

		// safe_offset( array( 'page' ) ) === safe_offset( 'page' ), so a plain ->with()
		// match would also route (and silently pass) if the source ever collapsed the
		// one-element array to the bare string. The InvokedFilterValue responder receives
		// the real invoked argument regardless of which key matched, so assertSame() below
		// catches that.
		WP_Mock::onFilter( 'dwpb_post_types_supporting_comments' )
			->with( array( 'page' ), $args )
			->reply(
				new WP_Mock\InvokedFilterValue(
					function ( $post_types_with_feature ) {
						$this->assertSame( array( 'page' ), $post_types_with_feature );

						return array( 'page' );
					}
				)
			);

		$result = dwpb_post_types_with_feature( $feature, $args );

		$this->assertSame( array( 'page' ), $result );
	}

	/**
	 * Cache hit: a populated cached value must be returned as-is, and get_post_types()/
	 * post_type_supports()/wp_cache_set() must never run.
	 *
	 * @return void
	 */
	public function test_post_types_with_feature_cache_hit_returns_cached_value_without_querying() {
		$feature = 'comments';
		$cached  = array( 'page', 'book' );

		WP_Mock::userFunction( 'esc_attr' )
			->once()
			->with( $feature )
			->andReturn( $feature );

		WP_Mock::userFunction( 'wp_cache_get' )
			->once()
			->with( 'post-types-supporting-comments', 'post-types-by-feature' )
			->andReturn( $cached );

		WP_Mock::userFunction( 'get_post_types' )->never();
		WP_Mock::userFunction( 'post_type_supports' )->never();
		WP_Mock::userFunction( 'wp_cache_set' )->never();

		$this->stub_post_types_supporting_filter( 'dwpb_post_types_supporting_comments', $cached, array(), $cached );

		$result = dwpb_post_types_with_feature( $feature );

		$this->assertSame( $cached, $result );
	}

	/**
	 * When nothing supports the feature (other than the excluded 'post' type), the
	 * empty result array must normalise to false, both in what gets cached and returned.
	 *
	 * @return void
	 */
	public function test_post_types_with_feature_no_matches_normalises_to_false() {
		$feature = 'comments';

		WP_Mock::userFunction( 'esc_attr' )->with( $feature )->andReturn( $feature );

		WP_Mock::userFunction( 'wp_cache_get' )
			->once()
			->with( 'post-types-supporting-comments', 'post-types-by-feature' )
			->andReturn( false );

		WP_Mock::userFunction( 'get_post_types' )
			->once()
			->with( $this->strict_empty_array(), 'names' )
			->andReturn( array( 'post' ) );

		WP_Mock::userFunction( 'post_type_supports' )->with( 'post', $feature )->andReturn( true );

		// The empty result array is cached as-is; only the value returned to the caller
		// (and passed to the filter below) is normalized to false. array() == false, so
		// a plain ->with( array() ) would also accept a regression that cached false
		// directly -- strict_empty_array() forces ===.
		WP_Mock::userFunction( 'wp_cache_set' )
			->once()
			->with( 'post-types-supporting-comments', $this->strict_empty_array(), 'post-types-by-feature' );

		$this->stub_post_types_supporting_filter( 'dwpb_post_types_supporting_comments', false, array(), false );

		$result = dwpb_post_types_with_feature( $feature );

		$this->assertFalse( $result );
	}

	/**
	 * The dwpb_post_types_supporting_{$feature} filter can override the computed result.
	 *
	 * @return void
	 */
	public function test_post_types_with_feature_filter_overrides_result() {
		$feature  = 'comments';
		$args     = array();
		$override = array( 'custom_cpt' );

		WP_Mock::userFunction( 'esc_attr' )->with( $feature )->andReturn( $feature );
		WP_Mock::userFunction( 'wp_cache_get' )->once()->andReturn( false );
		WP_Mock::userFunction( 'get_post_types' )->once()->with( $this->strict_empty_array(), 'names' )->andReturn( array( 'page' ) );
		WP_Mock::userFunction( 'post_type_supports' )->with( 'page', $feature )->andReturn( true );
		WP_Mock::userFunction( 'wp_cache_set' )->once();

		// safe_offset( array( 'page' ) ) === safe_offset( 'page' ), so a plain ->with()
		// match would also route (and silently pass) if the source ever collapsed the
		// one-element array to the bare string. The InvokedFilterValue responder receives
		// the real invoked argument regardless of which key matched, so assertSame() below
		// catches that.
		WP_Mock::onFilter( "dwpb_post_types_supporting_{$feature}" )
			->with( array( 'page' ), $args )
			->reply(
				new WP_Mock\InvokedFilterValue(
					function ( $post_types_with_feature ) use ( $override ) {
						$this->assertSame( array( 'page' ), $post_types_with_feature );

						return $override;
					}
				)
			);

		$result = dwpb_post_types_with_feature( $feature, $args );

		$this->assertSame( $override, $result );
	}

	/**
	 * A falsy taxonomy must short-circuit before any WordPress function is touched.
	 *
	 * @return void
	 */
	public function test_post_types_with_tax_falsy_taxonomy_returns_false() {
		$this->assertFalse( dwpb_post_types_with_tax( '' ) );
		$this->assertFalse( dwpb_post_types_with_tax( 0 ) );
		$this->assertFalse( dwpb_post_types_with_tax( null ) );
		$this->assertFalse( dwpb_post_types_with_tax( false ) );
	}

	/**
	 * A taxonomy that is neither an object nor a string must also short-circuit.
	 *
	 * @return void
	 */
	public function test_post_types_with_tax_non_string_non_object_taxonomy_returns_false() {
		$this->assertFalse( dwpb_post_types_with_tax( array( 'category' ) ) );
		$this->assertFalse( dwpb_post_types_with_tax( 42 ) );
	}

	/**
	 * A taxonomy object must be resolved through its ->name property.
	 *
	 * @return void
	 */
	public function test_post_types_with_tax_taxonomy_object_uses_name_property() {
		$taxonomy = (object) array( 'name' => 'category' );

		$this->stub_tax_cache_key_helpers( 'category' );
		WP_Mock::userFunction( 'wp_cache_get' )
			->once()
			->with( $this->tax_cache_key( 'category' ), 'post-types-by-tax' )
			->andReturn( false );
		WP_Mock::userFunction( 'wp_cache_set' )
			->once()
			->with( $this->tax_cache_key( 'category' ), array( 'page' ), 'post-types-by-tax' );

		WP_Mock::userFunction( 'get_post_types' )
			->once()
			->with( $this->strict_empty_array(), 'names' )
			->andReturn( array( 'post', 'page' ) );

		// 'post' is skipped before get_object_taxonomies() would ever be called for it.
		WP_Mock::userFunction( 'get_object_taxonomies' )
			->once()
			->with( 'page', 'names' )
			->andReturn( array( 'category', 'post_tag' ) );

		$this->stub_taxonomy_support_filter( 'category', array( 'post', 'page' ), array(), 'names', null );

		$result = dwpb_post_types_with_tax( $taxonomy );

		$this->assertSame( array( 'page' ), $result );
	}

	/**
	 * Post types with the taxonomy are returned; 'post' is always excluded even
	 * when it supports the taxonomy.
	 *
	 * @return void
	 */
	public function test_post_types_with_tax_matching_post_types_returned_excluding_post() {
		$this->stub_tax_cache_key_helpers( 'category' );
		WP_Mock::userFunction( 'wp_cache_get' )->once()->andReturn( false );
		WP_Mock::userFunction( 'wp_cache_set' )
			->once()
			->with( $this->tax_cache_key( 'category' ), array( 'page' ), 'post-types-by-tax' );

		WP_Mock::userFunction( 'get_post_types' )
			->once()
			->with( $this->strict_empty_array(), 'names' )
			->andReturn( array( 'post', 'page', 'book' ) );

		WP_Mock::userFunction( 'get_object_taxonomies' )->with( 'page', 'names' )->andReturn( array( 'category' ) );
		WP_Mock::userFunction( 'get_object_taxonomies' )->with( 'book', 'names' )->andReturn( array( 'genre' ) );

		$this->stub_taxonomy_support_filter( 'category', array( 'post', 'page', 'book' ), array(), 'names', null );

		$result = dwpb_post_types_with_tax( 'category' );

		$this->assertSame( array( 'page' ), $result );
	}

	/**
	 * When no post type (other than 'post') carries the taxonomy, the result is false --
	 * both what gets cached and what gets returned.
	 *
	 * @return void
	 */
	public function test_post_types_with_tax_no_matches_returns_false() {
		$this->stub_tax_cache_key_helpers( 'category' );
		WP_Mock::userFunction( 'wp_cache_get' )->once()->andReturn( false );
		// The empty result array is cached as-is; only the value returned to the caller
		// is normalized to false. array() == false, so a plain ->with( array() ) would
		// also accept a regression that cached false directly -- strict_empty_array()
		// forces ===.
		WP_Mock::userFunction( 'wp_cache_set' )
			->once()
			->with( $this->tax_cache_key( 'category' ), $this->strict_empty_array(), 'post-types-by-tax' );

		WP_Mock::userFunction( 'get_post_types' )
			->once()
			->with( $this->strict_empty_array(), 'names' )
			->andReturn( array( 'page' ) );

		WP_Mock::userFunction( 'get_object_taxonomies' )
			->once()
			->with( 'page', 'names' )
			->andReturn( array( 'post_tag' ) );

		$this->stub_taxonomy_support_filter( 'category', array( 'page' ), array(), 'names', null );

		$result = dwpb_post_types_with_tax( 'category' );

		$this->assertFalse( $result );
	}

	/**
	 * The dwpb_taxonomy_support filter short-circuits and overrides the result whenever
	 * it returns a non-null value, and is passed the full unfiltered $post_types list.
	 *
	 * @return void
	 */
	public function test_post_types_with_tax_filter_overrides_result_when_non_null() {
		$args     = array();
		$output   = 'names';
		$override = array( 'custom_cpt' );

		$this->stub_tax_cache_key_helpers( 'category', $args );
		WP_Mock::userFunction( 'wp_cache_get' )->once()->andReturn( false );
		WP_Mock::userFunction( 'wp_cache_set' )->once();

		WP_Mock::userFunction( 'get_post_types' )
			->once()
			->with( $this->strict_empty_array(), $output )
			->andReturn( array( 'page' ) );

		WP_Mock::userFunction( 'get_object_taxonomies' )
			->once()
			->with( 'page', 'names' )
			->andReturn( array() );

		$this->stub_taxonomy_support_filter( 'category', array( 'page' ), $args, $output, $override );

		$result = dwpb_post_types_with_tax( 'category', $args, $output );

		$this->assertSame( $override, $result );
	}

	/**
	 * Cache miss: the function must query get_post_types(), narrow the result down to post
	 * types carrying the taxonomy (excluding 'post'), and populate the cache with that
	 * narrowed result -- mirrors test_post_types_with_feature_cache_miss_queries_and_populates_cache().
	 *
	 * @return void
	 */
	public function test_post_types_with_tax_cache_miss_queries_and_populates_cache() {
		$taxonomy = 'category';
		$args     = array( 'public' => true );
		$output   = 'names';

		$this->stub_tax_cache_key_helpers( $taxonomy, $args );

		WP_Mock::userFunction( 'wp_cache_get' )
			->once()
			->with( $this->tax_cache_key( $taxonomy, $args, $output ), 'post-types-by-tax' )
			->andReturn( false );

		WP_Mock::userFunction( 'get_post_types' )
			->once()
			->with( $args, $output )
			->andReturn( array( 'post', 'page', 'book' ) );

		// 'post' is skipped before get_object_taxonomies() would ever be called for it.
		WP_Mock::userFunction( 'get_object_taxonomies' )->with( 'page', 'names' )->andReturn( array( 'category' ) );
		WP_Mock::userFunction( 'get_object_taxonomies' )->with( 'book', 'names' )->andReturn( array( 'genre' ) );

		WP_Mock::userFunction( 'wp_cache_set' )
			->once()
			->with( $this->tax_cache_key( $taxonomy, $args, $output ), array( 'page' ), 'post-types-by-tax' );

		$this->stub_taxonomy_support_filter( $taxonomy, array( 'post', 'page', 'book' ), $args, $output, null );

		$result = dwpb_post_types_with_tax( $taxonomy, $args, $output );

		$this->assertSame( array( 'page' ), $result );
	}

	/**
	 * Cache hit: the cached value is returned as-is and get_object_taxonomies()/
	 * wp_cache_set() must never run. Unlike dwpb_post_types_with_feature(), get_post_types()
	 * still runs on a hit -- its result is one of the documented arguments the
	 * dwpb_taxonomy_support filter receives on every call, cached or not, so it can't be
	 * skipped just because the taxonomy-matching work was cached.
	 *
	 * @return void
	 */
	public function test_post_types_with_tax_cache_hit_returns_cached_value_without_querying_taxonomies() {
		$taxonomy = 'category';
		$cached   = array( 'page', 'book' );

		$this->stub_tax_cache_key_helpers( $taxonomy );

		WP_Mock::userFunction( 'wp_cache_get' )
			->once()
			->with( $this->tax_cache_key( $taxonomy ), 'post-types-by-tax' )
			->andReturn( $cached );

		WP_Mock::userFunction( 'get_post_types' )->once()->with( $this->strict_empty_array(), 'names' )->andReturn( array( 'page' ) );
		WP_Mock::userFunction( 'get_object_taxonomies' )->never();
		WP_Mock::userFunction( 'wp_cache_set' )->never();

		$this->stub_taxonomy_support_filter( $taxonomy, array( 'page' ), array(), 'names', null );

		$result = dwpb_post_types_with_tax( $taxonomy );

		$this->assertSame( $cached, $result );
	}

	/**
	 * The dwpb_taxonomy_support filter must run on a cache HIT too, not only on a miss --
	 * it documents $post_types (get_post_types()'s unfiltered result) as one of its
	 * arguments "on every call" regardless of whether the taxonomy-matching work itself
	 * was cached. Asserting on the filtered return value (rather than a call-count
	 * expectation) means this fails if the filter is ever skipped on the hit path: with
	 * no filter reply applied, dwpb_post_types_with_tax() would return the raw cached
	 * value instead of $override.
	 *
	 * @return void
	 */
	public function test_post_types_with_tax_cache_hit_still_runs_dwpb_taxonomy_support_filter() {
		$taxonomy = 'category';
		$cached   = array( 'page', 'book' );
		$override = array( 'custom_cpt' );

		$this->stub_tax_cache_key_helpers( $taxonomy );

		WP_Mock::userFunction( 'wp_cache_get' )
			->once()
			->with( $this->tax_cache_key( $taxonomy ), 'post-types-by-tax' )
			->andReturn( $cached );

		WP_Mock::userFunction( 'get_post_types' )->once()->with( $this->strict_empty_array(), 'names' )->andReturn( array( 'page' ) );
		WP_Mock::userFunction( 'get_object_taxonomies' )->never();
		WP_Mock::userFunction( 'wp_cache_set' )->never();

		$this->stub_taxonomy_support_filter( $taxonomy, array( 'page' ), array(), 'names', $override );

		$result = dwpb_post_types_with_tax( $taxonomy );

		$this->assertSame( $override, $result );
	}

	/**
	 * Regression: naively concatenating $taxonomy and $output with a single '-' delimiter
	 * (no per-component hashing) lets two calls whose values straddle the delimiter
	 * identically collide onto the same cache key -- e.g. ('cat', 'single-tag') and
	 * ('cat-single', 'tag') would both naively produce 'post-types-with-tax-cat-single-tag-...'.
	 * Hashing $taxonomy and $output independently before concatenating rules this out,
	 * since each hash is a fixed-length, unambiguous token.
	 *
	 * @return void
	 */
	public function test_post_types_with_tax_cache_key_does_not_collide_across_taxonomy_output_boundary() {
		WP_Mock::userFunction( 'esc_attr' )->with( 'cat' )->andReturn( 'cat' );
		WP_Mock::userFunction( 'esc_attr' )->with( 'cat-single' )->andReturn( 'cat-single' );
		WP_Mock::userFunction( 'maybe_serialize' )->with( $this->strict_empty_array() )->andReturn( serialize( array() ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize

		WP_Mock::userFunction( 'get_post_types' )->with( $this->strict_empty_array(), 'single-tag' )->andReturn( array() );
		WP_Mock::userFunction( 'get_post_types' )->with( $this->strict_empty_array(), 'tag' )->andReturn( array() );

		$seen_keys = array();
		WP_Mock::userFunction( 'wp_cache_get' )
			->twice()
			->andReturnUsing(
				function ( $key ) use ( &$seen_keys ) {
					$seen_keys[] = $key;

					return false;
				}
			);
		WP_Mock::userFunction( 'wp_cache_set' )->twice();

		$this->stub_taxonomy_support_filter( 'cat', array(), array(), 'single-tag', null );
		$this->stub_taxonomy_support_filter( 'cat-single', array(), array(), 'tag', null );

		dwpb_post_types_with_tax( 'cat', array(), 'single-tag' );
		dwpb_post_types_with_tax( 'cat-single', array(), 'tag' );

		$this->assertCount( 2, array_unique( $seen_keys ) );
	}

	/**
	 * The cache key must incorporate $output: two calls that differ only in $output must
	 * not collide and read each other's cached result.
	 *
	 * @return void
	 */
	public function test_post_types_with_tax_cache_key_distinguishes_calls_that_differ_only_by_output() {
		$this->stub_tax_cache_key_helpers( 'category' );

		WP_Mock::userFunction( 'get_post_types' )->with( $this->strict_empty_array(), 'names' )->andReturn( array() );
		WP_Mock::userFunction( 'get_post_types' )->with( $this->strict_empty_array(), 'objects' )->andReturn( array() );

		$seen_keys = array();
		WP_Mock::userFunction( 'wp_cache_get' )
			->twice()
			->andReturnUsing(
				function ( $key ) use ( &$seen_keys ) {
					$seen_keys[] = $key;

					return false;
				}
			);
		WP_Mock::userFunction( 'wp_cache_set' )->twice();

		$this->stub_taxonomy_support_filter( 'category', array(), array(), 'names', null );
		$this->stub_taxonomy_support_filter( 'category', array(), array(), 'objects', null );

		dwpb_post_types_with_tax( 'category', array(), 'names' );
		dwpb_post_types_with_tax( 'category', array(), 'objects' );

		$this->assertCount( 2, array_unique( $seen_keys ) );
	}

	/**
	 * The no-match case must return strictly `false`, not the empty array that gets
	 * cached, on both a cache miss (the negative result is freshly computed) and the
	 * following cache hit (that same empty array is read back from the cache) -- the
	 * false-normalisation has to run on both paths for the documented array|bool
	 * contract to hold. assertSame() is required here: `array() == false`, so
	 * assertEquals() would not catch a regression that skips the normalisation.
	 *
	 * This also asserts what the filter actually RECEIVES as its first argument.
	 * onFilter()->with() alone cannot tell false from array() apart -- safe_offset()
	 * casts both to the same '' key -- so a WP_Mock\InvokedFilterValue responder is used
	 * instead: it still gets invoked with the real arguments regardless of which key
	 * matched, so the captured value reveals whatever the source actually passed in.
	 *
	 * @return void
	 */
	public function test_post_types_with_feature_no_matches_returns_false_not_array_on_cold_and_warm_cache() {
		$feature             = 'comments';
		$args                = array();
		$seen_filter_values  = array();

		WP_Mock::userFunction( 'esc_attr' )->with( $feature )->andReturn( $feature );

		// False (a real cache miss) on the first call, then the empty array the first
		// call's wp_cache_set() stored (a real cache hit) on the second.
		WP_Mock::userFunction( 'wp_cache_get' )
			->twice()
			->with( 'post-types-supporting-comments', 'post-types-by-feature' )
			->andReturn( false, array() );

		WP_Mock::userFunction( 'get_post_types' )
			->once()
			->with( $this->strict_empty_array(), 'names' )
			->andReturn( array( 'post' ) );

		WP_Mock::userFunction( 'post_type_supports' )->with( 'post', $feature )->andReturn( true );

		WP_Mock::userFunction( 'wp_cache_set' )
			->once()
			->with( 'post-types-supporting-comments', $this->strict_empty_array(), 'post-types-by-feature' );

		WP_Mock::onFilter( "dwpb_post_types_supporting_{$feature}" )
			->with( false, $args )
			->reply(
				new WP_Mock\InvokedFilterValue(
					function ( $value ) use ( &$seen_filter_values ) {
						$seen_filter_values[] = $value;

						return $value;
					}
				)
			);

		$cold_result = dwpb_post_types_with_feature( $feature, $args );
		$warm_result = dwpb_post_types_with_feature( $feature, $args );

		$this->assertFalse( $cold_result );
		$this->assertFalse( $warm_result );
		$this->assertSame( array( false, false ), $seen_filter_values );
	}

	/**
	 * Mirrors test_post_types_with_feature_no_matches_returns_false_not_array_on_cold_and_warm_cache():
	 * the no-match case must return strictly `false`, not the empty array that gets
	 * cached, on both a cache miss and the cache hit that reads that empty array back.
	 *
	 * @return void
	 */
	public function test_post_types_with_tax_no_matches_returns_false_not_array_on_cold_and_warm_cache() {
		$taxonomy  = 'category';
		$cache_key = $this->tax_cache_key( $taxonomy );

		$this->stub_tax_cache_key_helpers( $taxonomy );

		// False (a real cache miss) on the first call, then the empty array the first
		// call's wp_cache_set() stored (a real cache hit) on the second.
		WP_Mock::userFunction( 'wp_cache_get' )
			->twice()
			->with( $cache_key, 'post-types-by-tax' )
			->andReturn( false, array() );

		// Computed unconditionally on every call regardless of cache state (see the
		// comment above get_post_types() in the source).
		WP_Mock::userFunction( 'get_post_types' )
			->twice()
			->with( $this->strict_empty_array(), 'names' )
			->andReturn( array( 'page' ) );

		WP_Mock::userFunction( 'get_object_taxonomies' )
			->once()
			->with( 'page', 'names' )
			->andReturn( array( 'post_tag' ) );

		WP_Mock::userFunction( 'wp_cache_set' )
			->once()
			->with( $cache_key, $this->strict_empty_array(), 'post-types-by-tax' );

		$this->stub_taxonomy_support_filter( $taxonomy, array( 'page' ), array(), 'names', null );

		$cold_result = dwpb_post_types_with_tax( $taxonomy );
		$warm_result = dwpb_post_types_with_tax( $taxonomy );

		$this->assertFalse( $cold_result );
		$this->assertFalse( $warm_result );
	}

	/**
	 * `in_array( $taxonomy, $taxonomies, true )` must compare strictly: a post type whose
	 * taxonomies list contains a value that is only loosely equal to the requested
	 * taxonomy -- here `true`, which loosely equals any non-empty string including
	 * 'category', but is never identical to it -- must not be treated as carrying that
	 * taxonomy.
	 *
	 * @return void
	 */
	public function test_post_types_with_tax_strict_comparison_excludes_loosely_equal_value() {
		$taxonomy = 'category';

		$this->stub_tax_cache_key_helpers( $taxonomy );

		WP_Mock::userFunction( 'wp_cache_get' )->once()->andReturn( false );

		// The array's exact contents depend on whether the comparison below is strict,
		// which is the very thing under test -- only the type is pinned down here.
		WP_Mock::userFunction( 'wp_cache_set' )
			->once()
			->with( $this->tax_cache_key( $taxonomy ), Mockery::type( 'array' ), 'post-types-by-tax' );

		WP_Mock::userFunction( 'get_post_types' )
			->once()
			->with( $this->strict_empty_array(), 'names' )
			->andReturn( array( 'page' ) );

		WP_Mock::userFunction( 'get_object_taxonomies' )
			->once()
			->with( 'page', 'names' )
			->andReturn( array( true ) );

		$this->stub_taxonomy_support_filter( $taxonomy, array( 'page' ), array(), 'names', null );

		$result = dwpb_post_types_with_tax( $taxonomy );

		$this->assertFalse( $result );
	}
}
