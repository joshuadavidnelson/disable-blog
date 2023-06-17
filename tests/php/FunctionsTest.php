<?php
/**
 * Class FunctionsTest
 *
 * @package Disable_Blog
 */

/**
 * Sample test case.
 */
class FunctionsTest extends TestCase {

	/**
	 * The post types to mock.
	 *
	 * @var array
	 */
	protected $post_types = array(
		'post',
		'page',
		'attachment',
		'revision',
		'nav_menu_item',
		'wp_template',
		'wp_template_part',
	);

	/**
	 * The taxonomies to mock.
	 *
	 * @var array
	 */
	protected $taxonomies = array(
		'category',
		'post_tag',
		'nav_menu',
		'post_format',
	);

	/**
	 * Set up the test.
	 */
	public function setUp(): void {
		parent::setUp();

		\WP_Mock::userFunction(
			'get_post_types', array(
				'return' => $this->post_types,
			)
		);
		\WP_Mock::userFunction(
			'post_type_supports', array(
				'return' => true,
			)
		);

		\WP_Mock::userFunction(
			'get_object_taxonomies', array(
				'return' => $this->taxonomies,
			)
		);

	}

	/**
	 * Test the dwpb_post_types_with_feature() function.
	 */
	public function test_post_types_with_feature() {

		// Mock the post types expected to be returned.
		$post_types = array_diff( $this->post_types, array( 'post' ) );

		// Run the function.
		$post_types_with_feature = dwpb_post_types_with_feature( 'comments' );

		// Check that the post types are returned as expected.
		$this->assertEqualsCanonicalizing( $post_types_with_feature, $post_types );
	}

	/**
	 * Test the dwpb_post_types_with_tax() function.
	 */
	public function test_post_types_with_tax() {

		// Mock the post types expected to be returned.
		$post_types = array_diff( $this->post_types, array( 'post' ) );

		// Run the function.
		$post_types_with_feature = dwpb_post_types_with_tax( 'category' );

		// Check that the post types are returned as expected.
		$this->assertEqualsCanonicalizing( $post_types_with_feature, $post_types );
	}
}
