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

		WP_Mock::userFunction( 'get_post_types' )
			->once()
			->with( array(), 'names' )
			->andReturn( array( 'post', 'page' ) );

		// 'post' is skipped before get_object_taxonomies() would ever be called for it.
		WP_Mock::userFunction( 'get_object_taxonomies' )
			->once()
			->with( 'page', 'names' )
			->andReturn( array( 'category', 'post_tag' ) );

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
		WP_Mock::userFunction( 'get_post_types' )
			->once()
			->with( array(), 'names' )
			->andReturn( array( 'post', 'page', 'book' ) );

		WP_Mock::userFunction( 'get_object_taxonomies' )->with( 'page', 'names' )->andReturn( array( 'category' ) );
		WP_Mock::userFunction( 'get_object_taxonomies' )->with( 'book', 'names' )->andReturn( array( 'genre' ) );

		$result = dwpb_post_types_with_tax( 'category' );

		$this->assertSame( array( 'page' ), $result );
	}

	/**
	 * When no post type (other than 'post') carries the taxonomy, the result is false.
	 *
	 * @return void
	 */
	public function test_post_types_with_tax_no_matches_returns_false() {
		WP_Mock::userFunction( 'get_post_types' )
			->once()
			->with( array(), 'names' )
			->andReturn( array( 'page' ) );

		WP_Mock::userFunction( 'get_object_taxonomies' )
			->once()
			->with( 'page', 'names' )
			->andReturn( array( 'post_tag' ) );

		$result = dwpb_post_types_with_tax( 'category' );

		$this->assertFalse( $result );
	}

	/**
	 * The dwpb_taxonomy_support filter short-circuits and overrides the result whenever
	 * it returns a non-null value.
	 *
	 * @return void
	 */
	public function test_post_types_with_tax_filter_overrides_result_when_non_null() {
		$args     = array();
		$output   = 'names';
		$override = array( 'custom_cpt' );

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
}
