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

		WP_Mock::onFilter( 'dwpb_post_types_supporting_comments' )
			->with( array( 'page' ), $args )
			->reply( array( 'page' ) );

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

		WP_Mock::onFilter( 'dwpb_post_types_supporting_comments' )->with( $cached, array() )->reply( $cached );

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
			->with( array(), 'names' )
			->andReturn( array( 'post' ) );

		WP_Mock::userFunction( 'post_type_supports' )->with( 'post', $feature )->andReturn( true );

		WP_Mock::userFunction( 'wp_cache_set' )
			->once()
			->with( 'post-types-supporting-comments', false, 'post-types-by-feature' );

		WP_Mock::onFilter( 'dwpb_post_types_supporting_comments' )->with( false, array() )->reply( false );

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
		WP_Mock::userFunction( 'get_post_types' )->once()->with( $args, 'names' )->andReturn( array( 'page' ) );
		WP_Mock::userFunction( 'post_type_supports' )->with( 'page', $feature )->andReturn( true );
		WP_Mock::userFunction( 'wp_cache_set' )->once();

		WP_Mock::onFilter( "dwpb_post_types_supporting_{$feature}" )
			->with( array( 'page' ), $args )
			->reply( $override );

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
			->with( array(), 'names' )
			->andReturn( array( 'post', 'page' ) );

		// 'post' is skipped before get_object_taxonomies() would ever be called for it.
		WP_Mock::userFunction( 'get_object_taxonomies' )
			->once()
			->with( 'page', 'names' )
			->andReturn( array( 'category', 'post_tag' ) );

		WP_Mock::onFilter( 'dwpb_taxonomy_support' )
			->with( null, 'category', array( 'post', 'page' ), array(), 'names' )
			->reply( null );

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
			->with( array(), 'names' )
			->andReturn( array( 'post', 'page', 'book' ) );

		WP_Mock::userFunction( 'get_object_taxonomies' )->with( 'page', 'names' )->andReturn( array( 'category' ) );
		WP_Mock::userFunction( 'get_object_taxonomies' )->with( 'book', 'names' )->andReturn( array( 'genre' ) );

		WP_Mock::onFilter( 'dwpb_taxonomy_support' )
			->with( null, 'category', array( 'post', 'page', 'book' ), array(), 'names' )
			->reply( null );

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
		WP_Mock::userFunction( 'wp_cache_set' )
			->once()
			->with( $this->tax_cache_key( 'category' ), false, 'post-types-by-tax' );

		WP_Mock::userFunction( 'get_post_types' )
			->once()
			->with( array(), 'names' )
			->andReturn( array( 'page' ) );

		WP_Mock::userFunction( 'get_object_taxonomies' )
			->once()
			->with( 'page', 'names' )
			->andReturn( array( 'post_tag' ) );

		WP_Mock::onFilter( 'dwpb_taxonomy_support' )
			->with( null, 'category', array( 'page' ), array(), 'names' )
			->reply( null );

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
			->with( $args, $output )
			->andReturn( array( 'page' ) );

		WP_Mock::userFunction( 'get_object_taxonomies' )
			->once()
			->with( 'page', 'names' )
			->andReturn( array() );

		WP_Mock::onFilter( 'dwpb_taxonomy_support' )
			->with( null, 'category', array( 'page' ), $args, $output )
			->reply( $override );

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

		WP_Mock::onFilter( 'dwpb_taxonomy_support' )
			->with( null, $taxonomy, array( 'post', 'page', 'book' ), $args, $output )
			->reply( null );

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

		WP_Mock::userFunction( 'get_post_types' )->once()->with( array(), 'names' )->andReturn( array( 'page' ) );
		WP_Mock::userFunction( 'get_object_taxonomies' )->never();
		WP_Mock::userFunction( 'wp_cache_set' )->never();

		WP_Mock::onFilter( 'dwpb_taxonomy_support' )
			->with( null, $taxonomy, array( 'page' ), array(), 'names' )
			->reply( null );

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

		WP_Mock::userFunction( 'get_post_types' )->once()->with( array(), 'names' )->andReturn( array( 'page' ) );
		WP_Mock::userFunction( 'get_object_taxonomies' )->never();
		WP_Mock::userFunction( 'wp_cache_set' )->never();

		WP_Mock::onFilter( 'dwpb_taxonomy_support' )
			->with( null, $taxonomy, array( 'page' ), array(), 'names' )
			->reply( $override );

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
		WP_Mock::userFunction( 'maybe_serialize' )->with( array() )->andReturn( serialize( array() ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize

		WP_Mock::userFunction( 'get_post_types' )->with( array(), 'single-tag' )->andReturn( array() );
		WP_Mock::userFunction( 'get_post_types' )->with( array(), 'tag' )->andReturn( array() );

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

		WP_Mock::onFilter( 'dwpb_taxonomy_support' )->with( null, 'cat', array(), array(), 'single-tag' )->reply( null );
		WP_Mock::onFilter( 'dwpb_taxonomy_support' )->with( null, 'cat-single', array(), array(), 'tag' )->reply( null );

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

		WP_Mock::userFunction( 'get_post_types' )->with( array(), 'names' )->andReturn( array() );
		WP_Mock::userFunction( 'get_post_types' )->with( array(), 'objects' )->andReturn( array() );

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

		WP_Mock::onFilter( 'dwpb_taxonomy_support' )->with( null, 'category', array(), array(), 'names' )->reply( null );
		WP_Mock::onFilter( 'dwpb_taxonomy_support' )->with( null, 'category', array(), array(), 'objects' )->reply( null );

		dwpb_post_types_with_tax( 'category', array(), 'names' );
		dwpb_post_types_with_tax( 'category', array(), 'objects' );

		$this->assertCount( 2, array_unique( $seen_keys ) );
	}
}
