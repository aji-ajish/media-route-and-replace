<?php
/**
 * Plugin deactivation handler.
 *
 * @package Linko_Media_Path_Mapper_And_Swapper
 */

namespace Linko_Media_Path_Mapper_And_Swapper;

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
