<?php
/**
 * Tests for the optional $functions constructor parameter on Disable_Blog_Admin and
 * Disable_Blog_Public.
 *
 * @package DisableBlog
 */

// Not required by tests/php/bootstrap.php on purpose (see LoaderTest's autoloader test,
// which needs Disable_Blog_Functions specifically to remain undefined until its own
// isolated process); load them here instead. require_once is safe regardless of which
// test file happens to load first.
require_once __DIR__ . '/../../includes/class-disable-blog-functions.php';
require_once __DIR__ . '/../../includes/class-disable-blog-admin.php';
require_once __DIR__ . '/../../includes/class-disable-blog-public.php';

/**
 * @covers Disable_Blog_Admin::__construct
 * @covers Disable_Blog_Public::__construct
 */
class ConstructorInjectionTest extends TestCase {

	/**
	 * Both classes' third constructor argument must be the instance actually used at
	 * runtime, not just stored and ignored in favor of a fresh internally-constructed
	 * Disable_Blog_Functions. Proven by injecting a double with a distinctive return
	 * value and driving a real method that calls through it.
	 */
	public function test_admin_constructor_uses_the_injected_functions_instance() {
		$double = new class() extends Disable_Blog_Functions {
			/**
			 * @return string[]
			 */
			public function author_archive_post_types() {
				return array( 'injected_cpt' );
			}
		};

		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6', $double );

		WP_Mock::onFilter( 'dwpb_admin_user_post_types' )
			->with( array( 'page', 'injected_cpt' ) )
			->reply( array( 'page', 'injected_cpt' ) );

		$reflection = new ReflectionMethod( $admin, 'user_column_post_types' );
		$reflection->setAccessible( true );

		$this->assertSame( array( 'page', 'injected_cpt' ), $reflection->invoke( $admin ) );
	}

	/**
	 * Omitting the third argument must fall back to a real Disable_Blog_Functions
	 * instance, exactly as at both real call sites in includes/class-disable-blog.php.
	 */
	public function test_admin_constructor_defaults_to_a_real_functions_instance_when_omitted() {
		$admin = new Disable_Blog_Admin( 'disable-blog', '0.5.6' );

		$property = new ReflectionProperty( $admin, 'functions' );
		$property->setAccessible( true );

		$this->assertInstanceOf( Disable_Blog_Functions::class, $property->getValue( $admin ) );
	}

	/**
	 * Same proof as the admin test above, for Disable_Blog_Public. The double's answers
	 * are chosen to diverge from a real, unfiltered Disable_Blog_Functions: a fresh
	 * instance's author_archive_post_types() defaults to empty, which alone is already
	 * enough to disable the sitemap, so a double that instead reports a non-empty list
	 * is the only way $provider (not false) can come back out.
	 */
	public function test_public_constructor_uses_the_injected_functions_instance() {
		$double = new class() extends Disable_Blog_Functions {
			public function disable_author_archives() {
				return false;
			}
			/**
			 * @return string[]
			 */
			public function author_archive_post_types() {
				return array( 'book' );
			}
		};

		$public   = new Disable_Blog_Public( 'disable-blog', '0.5.6', $double );
		$provider = new stdClass();

		WP_Mock::onFilter( 'dwpb_disable_user_sitemap' )->with( false )->reply( false );

		$this->assertSame( $provider, $public->wp_author_sitemaps( $provider, 'users' ) );
	}

	/**
	 * Omitting the third argument must fall back to a real Disable_Blog_Functions
	 * instance, exactly as at both real call sites in includes/class-disable-blog.php.
	 */
	public function test_public_constructor_defaults_to_a_real_functions_instance_when_omitted() {
		$public = new Disable_Blog_Public( 'disable-blog', '0.5.6' );

		$property = new ReflectionProperty( $public, 'functions' );
		$property->setAccessible( true );

		$this->assertInstanceOf( Disable_Blog_Functions::class, $property->getValue( $public ) );
	}
}
