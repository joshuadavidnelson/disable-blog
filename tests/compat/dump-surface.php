<?php
/**
 * Print the parts of WordPress core Disable Blog depends on as JSON.
 *
 * Run with `wp --require=isolate-fixtures.php eval-file <this file>
 * --skip-plugins` so the surface is untouched core: plugins off, and the
 * must-use test fixtures switched off. The JSON sits between two marker lines so the caller can
 * find it among whatever wp-env prints around it. Every list is sorted and
 * every object is key-sorted, so two dumps of the same core are identical.
 *
 * @package Disable_Blog
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard widgets core adds only under conditions that vary between runs,
 * keyed by ID with the reason. Taken from the conditional
 * `wp_add_dashboard_widget()` calls in `wp-admin/includes/dashboard.php`.
 */
const DWPB_COMPAT_VOLATILE_WIDGETS = array(
	'dashboard_browser_nag' => 'Depends on the request user agent and a WordPress.org API call.',
	'dashboard_php_nag'     => 'Depends on the PHP version and a WordPress.org API call.',
);

/**
 * Admin bar nodes core adds only under conditions that vary between runs.
 */
const DWPB_COMPAT_VOLATILE_NODES = array(
	'updates' => 'Appears only while cached update data reports pending updates.',
);

/**
 * Block attribute default values that mean "this block assumes posts".
 */
const DWPB_COMPAT_POST_DEFAULTS = array( 'post', 'category', 'post_tag' );

/**
 * Fail every outbound HTTP request, so no section depends on the network.
 *
 * @return WP_Error
 */
function dwpb_compat_block_http() {
	return new WP_Error( 'dwpb_compat_offline', 'Outbound HTTP is blocked while the core surface is dumped.' );
}

/**
 * Stop the dump with a message on STDERR and a non-zero exit.
 *
 * @param string $message Why the dump cannot continue.
 * @return never
 */
function dwpb_compat_fail( $message ) {
	// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite -- STDERR, not a file.
	fwrite( STDERR, 'Surface dump failed: ' . $message . PHP_EOL );
	exit( 1 );
}

/**
 * Sort a list of strings, dropping duplicates and keys.
 *
 * @param array<mixed> $values The list.
 * @return string[]
 */
function dwpb_compat_sorted( array $values ) {
	$values = array_values( array_unique( array_map( 'strval', $values ) ) );
	sort( $values, SORT_STRING );
	return $values;
}

/**
 * Public, non-static property names declared on a class.
 *
 * @param string $class_name The class.
 * @return string[]
 */
function dwpb_compat_class_props( $class_name ) {
	$names = array();
	foreach ( ( new ReflectionClass( $class_name ) )->getProperties( ReflectionProperty::IS_PUBLIC ) as $property ) {
		if ( ! $property->isStatic() ) {
			$names[] = $property->getName();
		}
	}
	return dwpb_compat_sorted( $names );
}

/**
 * Property names on `WP_Post_Type`.
 *
 * @return string[]
 */
function dwpb_compat_post_type_props() {
	return dwpb_compat_class_props( 'WP_Post_Type' );
}

/**
 * Property names on `WP_Taxonomy`.
 *
 * @return string[]
 */
function dwpb_compat_taxonomy_props() {
	return dwpb_compat_class_props( 'WP_Taxonomy' );
}

/**
 * The scalar and null values core registers for the `post` post type.
 *
 * @return array<string, scalar|null>
 */
function dwpb_compat_post_type_post() {
	$values = array();
	foreach ( get_object_vars( get_post_type_object( 'post' ) ) as $name => $value ) {
		if ( null === $value || is_scalar( $value ) ) {
			$values[ $name ] = $value;
		}
	}
	ksort( $values, SORT_STRING );
	return $values;
}

/**
 * Every registered REST route, minus the test fixture namespace.
 *
 * @return string[]
 */
function dwpb_compat_rest_routes() {
	$routes = array();
	foreach ( array_keys( rest_get_server()->get_routes() ) as $route ) {
		if ( 0 !== strpos( $route, '/dwpb-test/' ) ) {
			$routes[] = $route;
		}
	}
	return dwpb_compat_sorted( $routes );
}

/**
 * Registered ability names, or none where the Abilities API does not exist.
 *
 * @return string[]
 */
function dwpb_compat_abilities() {
	if ( ! function_exists( 'wp_get_abilities' ) ) {
		return array();
	}

	$names = array();
	foreach ( wp_get_abilities() as $key => $ability ) {
		$names[] = is_object( $ability ) && method_exists( $ability, 'get_name' ) ? $ability->get_name() : $key;
	}
	return dwpb_compat_sorted( $names );
}

/**
 * XML-RPC method names.
 *
 * @return string[]
 */
function dwpb_compat_xmlrpc_methods() {
	require_once ABSPATH . 'wp-includes/class-IXR.php';
	require_once ABSPATH . 'wp-includes/class-wp-xmlrpc-server.php';
	$server = new wp_xmlrpc_server();
	return dwpb_compat_sorted( array_keys( $server->methods ) );
}

/**
 * Registered widget classes.
 *
 * @return string[]
 */
function dwpb_compat_widgets() {
	global $wp_widget_factory;
	return dwpb_compat_sorted( array_keys( $wp_widget_factory->widgets ) );
}

/**
 * Site Health test keys, direct and async.
 *
 * @return array<string, string[]>
 */
function dwpb_compat_site_health_tests() {
	require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
	$tests = WP_Site_Health::get_tests();
	return array(
		'async'  => dwpb_compat_sorted( array_keys( $tests['async'] ) ),
		'direct' => dwpb_compat_sorted( array_keys( $tests['direct'] ) ),
	);
}

/**
 * Collect `path=value` for every default, at any depth, that names a post
 * type or taxonomy the plugin disables.
 *
 * @param mixed    $value The default, or a part of it.
 * @param string   $path  The dotted path to `$value`.
 * @param string[] $found The matches so far.
 * @return string[]
 */
function dwpb_compat_find_post_defaults( $value, $path, array $found ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $key => $child ) {
			$found = dwpb_compat_find_post_defaults( $child, $path . '.' . $key, $found );
		}
	} elseif ( is_string( $value ) && in_array( $value, DWPB_COMPAT_POST_DEFAULTS, true ) ) {
		$found[] = $path . '=' . $value;
	}
	return $found;
}

/**
 * Block attributes whose default is `post`, `category`, or `post_tag`.
 *
 * @return array<string, string[]>
 */
function dwpb_compat_block_post_defaults() {
	$blocks = array();
	foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $block ) {
		$found = array();
		foreach ( (array) $block->attributes as $attribute => $schema ) {
			if ( is_array( $schema ) && array_key_exists( 'default', $schema ) ) {
				$found = dwpb_compat_find_post_defaults( $schema['default'], (string) $attribute, $found );
			}
		}
		if ( $found ) {
			$blocks[ $name ] = dwpb_compat_sorted( $found );
		}
	}
	ksort( $blocks, SORT_STRING );
	return $blocks;
}

/**
 * Hash a function's source, ignoring indentation and trailing whitespace.
 *
 * @param ReflectionFunctionAbstract $reflection The function or method.
 * @return string
 */
function dwpb_compat_source_hash( ReflectionFunctionAbstract $reflection ) {
	$file = $reflection->getFileName();
	if ( ! $file ) {
		dwpb_compat_fail( 'no source file for ' . $reflection->getName() );
	}

	// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- a local core file.
	$lines  = explode( "\n", (string) file_get_contents( $file ) );
	$source = array_slice( $lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 );
	return hash( 'sha256', implode( "\n", array_map( 'trim', $source ) ) );
}

/**
 * Source hashes of the core functions the plugin copies or wraps.
 *
 * @return array<string, string>
 */
function dwpb_compat_core_copies() {
	require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
	$copies = array(
		'WP_Site_Health::get_test_rest_availability' => dwpb_compat_source_hash( new ReflectionMethod( 'WP_Site_Health', 'get_test_rest_availability' ) ),
		'get_comment_count'                          => dwpb_compat_source_hash( new ReflectionFunction( 'get_comment_count' ) ),
	);
	ksort( $copies, SORT_STRING );
	return $copies;
}

/**
 * Load the admin as the first administrator on the dashboard screen.
 *
 * @return void
 */
function dwpb_compat_load_admin() {
	$admins = get_users(
		array(
			'role'    => 'administrator',
			'orderby' => 'ID',
			'order'   => 'ASC',
			'number'  => 1,
			'fields'  => 'ID',
		)
	);
	if ( ! $admins ) {
		dwpb_compat_fail( 'no administrator to load the admin as.' );
	}

	wp_set_current_user( (int) $admins[0] );
	$_SERVER['REQUEST_METHOD'] = 'GET';
	unset( $GLOBALS['plugin_page'] );

	require_once ABSPATH . 'wp-admin/includes/admin.php';
	set_current_screen( 'dashboard' );
}

/**
 * Top-level and submenu page slugs, as `wp-admin/menu.php` builds them.
 *
 * @return array<string, string[]>
 */
function dwpb_compat_admin_menu() {
	// `menu.php` runs at global scope in core; these are the globals it assigns and reads.
	global $menu, $submenu, $_wp_real_parent_file, $_wp_submenu_nopriv, $_registered_pages, $_parent_pages, $admin_page_hooks, $_wp_menu_nopriv;

	require ABSPATH . 'wp-admin/menu.php';

	$top = array();
	foreach ( (array) $menu as $item ) {
		if ( false === strpos( (string) $item[4], 'wp-menu-separator' ) ) {
			$top[] = $item[2];
		}
	}

	$sub = array();
	foreach ( (array) $submenu as $parent => $items ) {
		foreach ( $items as $item ) {
			$sub[] = $parent . ' > ' . $item[2];
		}
	}

	if ( ! $top || ! $sub ) {
		dwpb_compat_fail( 'the admin menu came back empty.' );
	}

	return array(
		'sub' => dwpb_compat_sorted( $sub ),
		'top' => dwpb_compat_sorted( $top ),
	);
}

/**
 * Dashboard widget IDs as `context: id`, minus the volatile ones.
 *
 * @return string[]
 */
function dwpb_compat_dashboard_widgets() {
	global $wp_meta_boxes;

	require_once ABSPATH . 'wp-admin/includes/dashboard.php';
	wp_dashboard_setup();

	$widgets = array();
	foreach ( (array) ( $wp_meta_boxes['dashboard'] ?? array() ) as $context => $priorities ) {
		foreach ( (array) $priorities as $boxes ) {
			foreach ( (array) $boxes as $id => $box ) {
				if ( $box && ! array_key_exists( $id, DWPB_COMPAT_VOLATILE_WIDGETS ) ) {
					$widgets[] = $context . ': ' . $id;
				}
			}
		}
	}

	if ( ! $widgets ) {
		dwpb_compat_fail( 'no dashboard widgets were registered.' );
	}

	return dwpb_compat_sorted( $widgets );
}

/**
 * Admin bar node IDs, minus the volatile ones.
 *
 * @return string[]
 */
function dwpb_compat_admin_bar_nodes() {
	require_once ABSPATH . 'wp-includes/class-wp-admin-bar.php';

	$bar = new WP_Admin_Bar();
	$bar->initialize();
	$bar->add_menus();
	do_action_ref_array( 'admin_bar_menu', array( &$bar ) );

	$nodes = array_diff( array_keys( (array) $bar->get_nodes() ), array_keys( DWPB_COMPAT_VOLATILE_NODES ) );
	if ( ! $nodes ) {
		dwpb_compat_fail( 'the admin bar came back empty.' );
	}

	return dwpb_compat_sorted( $nodes );
}

/**
 * Stop unless the surface is core's alone: the fixture toggles are forced
 * off, and nothing outside core registered a post type or taxonomy.
 *
 * @return void
 */
function dwpb_compat_assert_core_only() {
	if ( ! defined( 'DWPB_COMPAT_FIXTURE_OPTIONS' ) ) {
		dwpb_compat_fail( 'run it with --require=tests/compat/isolate-fixtures.php, through `npm run compat:dump`.' );
	}

	$foreign = array_merge(
		array_map(
			function ( $name ) {
				return 'post type ' . $name;
			},
			get_post_types( array( '_builtin' => false ) )
		),
		array_map(
			function ( $name ) {
				return 'taxonomy ' . $name;
			},
			get_taxonomies( array( '_builtin' => false ) )
		)
	);
	if ( $foreign ) {
		dwpb_compat_fail( 'registered outside core: ' . implode( ', ', dwpb_compat_sorted( $foreign ) ) . '.' );
	}
}

/**
 * Build the whole surface. The admin sections run last, since loading the
 * admin changes global state the other sections read.
 *
 * @return array<string, mixed>
 */
function dwpb_compat_surface() {
	dwpb_compat_assert_core_only();
	add_filter( 'pre_http_request', 'dwpb_compat_block_http' );

	$surface = array(
		'wp_version'          => (string) get_bloginfo( 'version' ),
		'post_type_props'     => dwpb_compat_post_type_props(),
		'post_type_post'      => dwpb_compat_post_type_post(),
		'taxonomy_props'      => dwpb_compat_taxonomy_props(),
		'rest_routes'         => dwpb_compat_rest_routes(),
		'abilities'           => dwpb_compat_abilities(),
		'xmlrpc_methods'      => dwpb_compat_xmlrpc_methods(),
		'widgets'             => dwpb_compat_widgets(),
		'site_health_tests'   => dwpb_compat_site_health_tests(),
		'block_post_defaults' => dwpb_compat_block_post_defaults(),
		'core_copies'         => dwpb_compat_core_copies(),
	);

	dwpb_compat_load_admin();
	$surface['admin_menu']        = dwpb_compat_admin_menu();
	$surface['dashboard_widgets'] = dwpb_compat_dashboard_widgets();
	$surface['admin_bar_nodes']   = dwpb_compat_admin_bar_nodes();

	ksort( $surface, SORT_STRING );
	return $surface;
}

$dwpb_compat_json = wp_json_encode( dwpb_compat_surface(), JSON_UNESCAPED_SLASHES );
if ( false === $dwpb_compat_json ) {
	dwpb_compat_fail( 'the surface could not be encoded as JSON.' );
}

echo "\n---DWPB-SURFACE-BEGIN---\n" . $dwpb_compat_json . "\n---DWPB-SURFACE-END---\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output, not HTML.
