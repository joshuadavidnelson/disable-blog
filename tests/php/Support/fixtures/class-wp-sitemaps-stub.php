<?php
/**
 * Minimal stand-in for WordPress core's WP_Sitemaps / WP_Sitemaps_Registry classes.
 *
 * Disable_Blog_Public::disable_removed_sitemaps() checks `$server instanceof WP_Sitemaps` and
 * `$server->registry instanceof WP_Sitemaps_Registry` before reading the registered providers.
 * Loaded only from PublicMiscTest's isolated-process tests for that method, alongside a
 * test-declared wp_sitemaps_get_server() -- both a class declaration and a global function
 * declaration are permanent for the rest of the process, so neither can live in the main
 * (shared) test process.
 *
 * @package DisableBlog
 */

/**
 * Stand-in for WP_Sitemaps_Registry.
 */
class WP_Sitemaps_Registry {

	/**
	 * @var array
	 */
	private $providers;

	/**
	 * @param array $providers Registered provider objects, keyed by name.
	 */
	public function __construct( array $providers = array() ) {
		$this->providers = $providers;
	}

	/**
	 * @return array
	 */
	public function get_providers() {
		return $this->providers;
	}
}

/**
 * Stand-in for WP_Sitemaps.
 */
class WP_Sitemaps {

	/**
	 * @var WP_Sitemaps_Registry
	 */
	public $registry;

	/**
	 * @param WP_Sitemaps_Registry $registry The sitemaps registry.
	 */
	public function __construct( WP_Sitemaps_Registry $registry ) {
		$this->registry = $registry;
	}
}
