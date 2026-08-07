<?php
/**
 * Tests for includes/class-disable-blog-activator.php.
 *
 * @package DisableBlog
 */

/**
 * @covers Disable_Blog_Activator
 */
class ActivatorTest extends PluginLifecycleTestCase {

	protected function target_class(): string {
		return Disable_Blog_Activator::class;
	}

	protected function lifecycle_method(): string {
		return 'activate';
	}

	protected function single_nonce_action_prefix(): string {
		return 'activate-plugin_';
	}

	protected function single_action_value(): string {
		return 'activate';
	}

	protected function bulk_action_value(): string {
		return 'activate-selected';
	}
}
