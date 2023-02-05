<?php
/**
 * Register all actions and filters for the plugin
 *
 * @link       https://github.com/joshuadavidnelson/disable-blog
 * @since      0.4.0
 * @package    Disable_Blog
 * @subpackage Disable_Blog\Includes
 * @author     Joshua Nelson <josh@joshuadnelson.com>
 */

/**
 * Register all actions and filters for the plugin.
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API. Call the
 * run function to execute the list of actions and filters.
 *
 * @since 0.4.0
 */
class Disable_Blog_Loader {

	/**
	 * The array of actions registered with WordPress.
	 *
	 * @since  0.4.0
	 * @access protected
	 * @var    array $actions The actions registered with WordPress to fire when the plugin loads.
	 */
	protected $actions;

	/**
	 * The array of filters registered with WordPress.
	 *
	 * @since  0.4.0
	 * @access protected
	 * @var    array $filters The filters registered with WordPress to fire when the plugin loads.
	 */
	protected $filters;

	/**
	 * Initialize the collections used to maintain the actions and filters.
	 *
	 * @since 0.4.0
	 */
	public function __construct() {

		$this->actions = array();
		$this->filters = array();

	}

	/**
	 * Simple Autoloader.
	 *
	 * @since 0.5.1
	 * @param string $class The Class to autoload.
	 */
	public function autoloader( $class ) {

		$class   = 'class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';
		$path    = plugin_dir_path( dirname( __FILE__ ) );
		$sources = array( 'includes' );

		foreach ( $sources as $source ) {
			if ( file_exists( $path . $source . '/' . $class ) ) {
				include $path . $source . '/' . $class;
			}
		}
	}

	/**
	 * Add a new action to the collection to be registered with WordPress.
	 *
	 * @since 0.4.0
	 * @param string $hook          The name of the WordPress action that is being registered.
	 * @param object $component     A reference to the instance of the object on which the action is defined.
	 * @param string $callback      The name of the function definition on the $component.
	 * @param int    $priority      Optional. he priority at which the function should be fired. Default is 10.
	 * @param int    $accepted_args Optional. The number of arguments that should be passed to the $callback. Default is 1.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a new filter to the collection to be registered with WordPress.
	 *
	 * @since 0.4.0
	 * @param string $hook          The name of the WordPress filter that is being registered.
	 * @param object $component     A reference to the instance of the object on which the filter is defined.
	 * @param string $callback      The name of the function definition on the $component.
	 * @param int    $priority      Optional. he priority at which the function should be fired. Default is 10.
	 * @param int    $accepted_args Optional. The number of arguments that should be passed to the $callback. Default is 1.
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * A utility function that is used to register the actions and hooks into a single
	 * collection.
	 *
	 * @since  0.4.0
	 * @access private
	 * @param  array  $hooks         The collection of hooks that is being registered (that is, actions or filters).
	 * @param  string $hook          The name of the WordPress filter that is being registered.
	 * @param  object $component     A reference to the instance of the object on which the filter is defined.
	 * @param  string $callback      The name of the function definition on the $component.
	 * @param  int    $priority      The priority at which the function should be fired.
	 * @param  int    $accepted_args The number of arguments that should be passed to the $callback.
	 * @return array                 The collection of actions and filters registered with WordPress.
	 */
	private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {

		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		return $hooks;

	}

	/**
	 * Remove a filter from the collection registered with WordPress.
	 *
	 * @since 0.5.1
	 * @param string $tag              The filter hook to which the function to be removed is hooked.
	 * @param string $class_name       Class name registering the filter callback.
	 * @param string $method_to_remove Method name for the filter's callback.
	 * @param int    $priority         The priority of the method (default 10).
	 *
	 * @return bool $removed Whether the function is removed.
	 */
	public function remove_filter( $tag, $class_name = '', $method_to_remove = '', $priority = 10 ) {

		global $wp_filter;
		$removed = false;

		foreach ( $wp_filter[ $tag ]->callbacks as $filter_priority => $filters ) {

			if ( $filter_priority === $priority ) {
				foreach ( $filters as $filter ) {
					if ( $filter['function'][1] === $method_to_remove
						&& is_object( $filter['function'][0] ) // only WP 4.7 and above. This plugin is requiring at least WP 4.9.
						&& $filter['function'][0] instanceof $class_name ) {
						$removed = $wp_filter[ $tag ]->remove_filter( $tag, array( $filter['function'][0], $method_to_remove ), $priority );
					}
				}
			}
		}

		return $removed;

	}

	/**
	 * Remove an action from the collection registered with WordPress.
	 *
	 * @since 0.5.1
	 * @param string $tag              The filter hook to which the function to be removed is hooked.
	 * @param string $class_name       Class name registering the filter callback.
	 * @param string $method_to_remove Method name for the filter's callback.
	 * @param int    $priority         The priority of the method (default 10).
	 *
	 * @return bool $removed Whether the function is removed.
	 */
	public function remove_action( $tag, $class_name = '', $method_to_remove = '', $priority = 10 ) {
		return $this->remove_filter( $tag, $class_name, $method_to_remove, $priority );
	}

	/**
	 * Register the filters and actions with WordPress.
	 *
	 * @since 0.4.0
	 */
	public function run() {

		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

	}

}
