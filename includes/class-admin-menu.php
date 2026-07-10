<?php

/**
 * Admin menu registration and asset enqueueing.
 *
 * @package Media_Route_And_Replace
 */

namespace Media_Route_And_Replace;

if (! defined('ABSPATH')) {
	exit;
}

class Admin_Menu
{

	private Database $db;
	private string   $hook_suffix = '';

	public function __construct(Database $db)
	{
		$this->db = $db;
	}

	public function register(): void
	{
		add_action('admin_menu',            [$this, 'add_menu_page']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
	}

	public function add_menu_page(): void
	{
		// Media Manager
		$this->hook_suffix = add_menu_page(
			__('Media Relink & Routes', 'media-relink-and-routes'),
			__('Media Relink & Routes', 'media-relink-and-routes'),
			'manage_options',
			'media-relink-and-routes', 
			[$this, 'render_page'],
			'dashicons-randomize', 
			75
		);

		// Redirect Rules
		add_submenu_page(
			'media-relink-and-routes',
			__('Redirect Rules', 'media-relink-and-routes'),
			__('Redirect Rules', 'media-relink-and-routes'),
			'manage_options',
			'wpmm-redirect-rules',
			[$this, 'render_redirect_rules_page']
		);
	}

	public function enqueue_assets(string $hook_suffix): void
	{
		// Fix: Safely modified to load assets only if the page name contains 
		// 'wpmm-redirect-rules' or 'media-relink-and-routes'.
		if (! str_contains($hook_suffix, 'media-relink-and-routes') && ! str_contains($hook_suffix, 'wpmm-redirect-rules')) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'wpmm-admin',
			WPMM_PLUGIN_URL . 'assets/css/admin.css',
			[],
			WPMM_VERSION
		);

		wp_enqueue_script(
			'wpmm-admin',
			WPMM_PLUGIN_URL . 'assets/js/admin.js',
			['jquery', 'wp-util'],
			WPMM_VERSION,
			true
		);

		wp_localize_script('wpmm-admin', 'wpmmData', [
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'nonce'   => wp_create_nonce('wpmm_nonce'),
			'siteUrl' => home_url('/'),
			'cacheBuster'  => '',
			'i18n'    => [
				'confirmDelete'     => __('Are you sure you want to delete this entry? This cannot be undone.', 'media-relink-and-routes'),
				'saving'            => __('Saving…', 'media-relink-and-routes'),
				'deleting'          => __('Deleting…', 'media-relink-and-routes'),
				'checking'          => __('Checking…', 'media-relink-and-routes'),
				'selectMedia'       => __('Select Media', 'media-relink-and-routes'),
				'useSelected'       => __('Use this file', 'media-relink-and-routes'),
				'noEntries'         => __('No media entries found. Click "Add New" to get started.', 'media-relink-and-routes'),
				'invalidUrl'        => __('Please enter a valid WordPress media URL.', 'media-relink-and-routes'),
				'urlNotMedia'       => __("The URL does not belong to this site's media library.", 'media-relink-and-routes'),
				'duplicateTitle'    => __('File Already Exists', 'media-relink-and-routes'),
				'btnReplace'        => __('Yes, Replace It', 'media-relink-and-routes'),
				'btnCancel'         => __('No, Cancel', 'media-relink-and-routes'),
				'copyUrl'           => __('Copy URL', 'media-relink-and-routes'),
				'copied'            => __('Copied!', 'media-relink-and-routes'),
			],
		]);
	}

	public function render_page(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'media-relink-and-routes'));
		}
		require_once WPMM_PLUGIN_DIR . 'templates/admin-page.php';
	}

	/**
	 * redirect rules for submenu page
	 */
	public function render_redirect_rules_page(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'media-relink-and-routes'));
		}
		require_once WPMM_PLUGIN_DIR . 'templates/redirect-rules-page.php';
	}
}
