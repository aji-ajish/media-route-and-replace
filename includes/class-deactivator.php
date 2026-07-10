<?php
/**
 * Plugin deactivation handler.
 *
 * @package Media_Route_And_Replace
 */

namespace Media_Route_And_Replace;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deactivator
 */
class Deactivator {

	/**
	 * Run deactivation tasks.
	 *
	 * We flush rewrite rules so the parse_request intercept is cleaned up.
	 * We do NOT drop the database table — that only happens on uninstall.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules( true );
	}
}
