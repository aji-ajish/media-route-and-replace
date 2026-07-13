<?php
/**
 * Plugin activation handler.
 *
 * IMPORTANT: This file is require_once'd from the MAIN PLUGIN FILE using the
 * WPMM_PLUGIN_DIR constant before the autoloader is registered. It must:
 *   - Use only PHP built-ins and the manually-required Database class.
 *   - Never use the autoloader.
 *   - Never call wpmm() — the singleton doesn't exist at activation time.
 *
 * @package Linko_Media_Path_Mapper_And_Swapper
 */

namespace Linko_Media_Path_Mapper_And_Swapper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Database is manually required by the main plugin file — no autoloader needed.

/**
 * Class Activator
 */
class Activator {

	/**
	 * Run all activation tasks.
	 *
	 * Called via register_activation_hook(). Safe to run multiple times
	 * (all operations are idempotent).
	 *
	 * @return void
	 */
	public static function activate(): void {
		// ── 1. Create / upgrade the DB table ─────────────────────────────────
		// Database class is available because the main plugin file required it
		// before registering the activation hook.
		Database::create_tables();

		// ── 2. Verify the table was actually created ──────────────────────────
		if ( ! self::table_exists() ) {
			// Log the failure so developers can diagnose it.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[WP Media Manager] CRITICAL: DB table creation failed during activation. Check DB user permissions and error logs.' );

			// Store a transient so we can show an admin notice on next page load.
			set_transient( 'wpmm_activation_error', 'table_missing', HOUR_IN_SECONDS );
		} else {
			delete_transient( 'wpmm_activation_error' );
		}

		// ── 3. Store installed version ────────────────────────────────────────
		// Used by Plugin::maybe_create_tables() to detect when an upgrade
		// needs to run dbDelta again.
		update_option( 'wpmm_version', WPMM_VERSION );

		// ── 4. Flush rewrite rules ────────────────────────────────────────────
		// Removes any stale catch-all rule from previous versions.
		// hard=true forces .htaccess rewrite (required on Apache).
		flush_rewrite_rules( true );
	}

	/**
	 * Check whether the plugin table exists in the database.
	 *
	 * @return bool
	 */
	public static function table_exists(): bool {
    global $wpdb;
    $table = $wpdb->prefix . WPMM_TABLE_NAME;
    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    return (bool) $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
}
}
