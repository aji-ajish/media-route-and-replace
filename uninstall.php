<?php
/**
 * Fired when the plugin is uninstalled (deleted from WP).
 *
 * @package WP_Media_Manager
 */

// Exit if called directly (not from WordPress uninstall API).
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Define constant so Database class can be loaded cleanly.
if ( ! defined( 'WPMM_TABLE_NAME' ) ) {
	define( 'WPMM_TABLE_NAME', 'wpmm_media_entries' );
}

// Load only what we need.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-database.php';

// Drop table and remove options.
WP_Media_Manager\Database::drop_tables();
delete_option( 'wpmm_version' );
