<?php

/**
 * Core plugin bootstrap — singleton.
 *
 * @package Media_Route_And_Replace
 */

namespace Media_Route_And_Replace;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Plugin
 */
class Plugin
{

	private static ?Plugin $instance = null;

	private Database        $database;
	private Media_Handler   $media_handler;
	private Ajax_Handler    $ajax_handler;
	private Admin_Menu      $admin_menu;
	private Rewrite_Handler $rewrite_handler;
	private Url_Replacer    $url_replacer;

	/**
	 * Get or create the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — wire all dependencies.
	 */
	private function __construct()
	{
		$this->database        = new Database();
		$this->media_handler   = new Media_Handler();
		$this->ajax_handler    = new Ajax_Handler($this->database, $this->media_handler);
		$this->admin_menu      = new Admin_Menu($this->database);
		$this->rewrite_handler = new Rewrite_Handler($this->database);
		$this->url_replacer    = new Url_Replacer($this->database);

		// Load translations.
		// add_action( 'init', [ $this, 'load_textdomain' ] );

		// ── DB safety net ─────────────────────────────────────────────────────
		// Runs on init priority 1 — before almost anything else.
		// Handles environments where the activation hook never fired
		// (manual SFTP deployment, WP CLI copy, etc.) and handles plugin
		// updates that change the schema.
		add_action('init', [$this, 'maybe_create_tables'], 1);

		// ── Admin notice for activation failure ───────────────────────────────
		add_action('admin_notices', [$this, 'maybe_show_db_error_notice']);
		// ── Settings Page Redirect Hack ──────────────────────────────────────────
		add_filter('plugin_action_links_' . WPMM_PLUGIN_BASENAME, [$this, 'inject_settings_link']);

		// ── Rewrite / URL serving ─────────────────────────────────────────────
		// Runs on both front and back end (needed for REST + admin-ajax paths).
		$this->rewrite_handler->register();

		// ── URL replacement ───────────────────────────────────────────────────
		// Front-end only; internal guard inside register().
		$this->url_replacer->register();

		// ── Admin-only ────────────────────────────────────────────────────────
		if (is_admin()) {
			$this->admin_menu->register();
			$this->ajax_handler->register();
		}
	}

	// /**
	//  * Load plugin translations.
	//  *
	//  * @return void
	//  */
	// public function load_textdomain(): void {
	// 	load_plugin_textdomain(
	// 		'media-relink-and-routes',
	// 		false,
	// 		dirname( WPMM_PLUGIN_BASENAME ) . '/languages'
	// 	);
	// }

	/**
	 * Create DB tables if missing or outdated.
	 *
	 * Covers:
	 *  - Fresh install where activation hook was skipped (SFTP/CLI deploy).
	 *  - Plugin update where schema changed.
	 *  - Table manually dropped by the site owner.
	 *
	 * Uses a transient to avoid running dbDelta on every request — only runs
	 * when the stored version doesn't match WPMM_VERSION.
	 *
	 * @return void
	 */
	public function maybe_create_tables(): void
	{
		$installed = get_option('wpmm_version', '');

		// Run if version is outdated OR table is missing.
		if (
			version_compare((string) $installed, WPMM_VERSION, '<')
			|| ! Activator::table_exists()
		) {
			Database::create_tables();
			update_option('wpmm_version', WPMM_VERSION);
			delete_transient('wpmm_activation_error');

			// Only flush on front-end init to avoid redirect loops in admin.
			if (! is_admin()) {
				flush_rewrite_rules(false);
			}
		}
	}

	/**
	 * Show an admin notice if DB table creation failed during activation.
	 *
	 * @return void
	 */
	public function maybe_show_db_error_notice(): void
	{
		if (! get_transient('wpmm_activation_error')) {
			return;
		}

		if (! current_user_can('manage_options')) {
			return;
		}

		echo '<div class="notice notice-error is-dismissible">'
			. '<p><strong>' . esc_html__('WP Media Manager', 'media-relink-and-routes') . '</strong> — '
			. esc_html__('The plugin database table could not be created. Please check that your database user has CREATE TABLE permissions, then deactivate and reactivate the plugin.', 'media-relink-and-routes')
			. '</p></div>';
	}

	    // ── Settings Redirect Hack ──────────────────────────────────────────────

	/**
	 * Intercepts the "Settings" link on the WordPress Installed Plugins page
	 * and redirects it to our custom admin menu page instead of a generic WP settings page.
	 */
	public function inject_settings_link(array $links): array
	{
		$settings_url = admin_url('admin.php?page=media-relink-and-routes');

		$links['settings'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url($settings_url),
			__('Settings', 'media-relink-and-routes')
		);

		return $links;
	}

	// ── Public accessors ──────────────────────────────────────────────────────

	public function get_database(): Database
	{
		return $this->database;
	}
	public function get_media_handler(): Media_Handler
	{
		return $this->media_handler;
	}
	public function get_url_replacer(): Url_Replacer
	{
		return $this->url_replacer;
	}

	private function __clone(): void {}

	public function __wakeup(): void
	{
		throw new \Exception('Cannot unserialize plugin singleton.');
	}
}
