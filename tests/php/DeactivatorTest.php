<?php
/**
 * Tests for includes/class-disable-blog-deactivator.php.
 *
 * @package DisableBlog
 */

/**
 * @covers Disable_Blog_Deactivator
 */
class DeactivatorTest extends PluginLifecycleTestCase {

	protected function target_class(): string {
		return Disable_Blog_Deactivator::class;
	}

	protected function lifecycle_method(): string {
		return 'deactivate';
	}

	protected function single_nonce_action_prefix(): string {
		return 'deactivate-plugin_';
	}

	protected function single_action_value(): string {
		return 'deactivate';
	}

	protected function bulk_action_value(): string {
		return 'deactivate-selected';
	}
}
