<?php
/**
 * Minimal stand-in for the real Disable Comments plugin's main class.
 *
 * Disable_Blog_Integrations::is_disable_comments_active() checks class_exists('Disable_Comments')
 * as an active-plugin fallback. Loaded only by IntegrationsTest's isolated process test for that
 * branch -- a class declaration can't live inline in a test method (PHP disallows nesting a class
 * declaration inside another class's method body) and, once declared, can never be undeclared, so
 * it must stay out of the main test process entirely.
 *
 * @package DisableBlog
 */

class Disable_Comments {}
