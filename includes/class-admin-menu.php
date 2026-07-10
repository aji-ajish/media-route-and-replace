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
			__('Media Route & Replace', 'media-route-and-replace'),
			__('Media Route & Replace', 'media-route-and-replace'),
			'manage_options',
			'media-route-and-replace', 
			[$this, 'render_page'],
			'dashicons-randomize', 
			25
		);

		// Redirect Rules
		add_submenu_page(
			'media-route-and-replace',
			__('Redirect Rules', 'media-route-and-replace'),
			__('Redirect Rules', 'media-route-and-replace'),
			'manage_options',
			'wpmm-redirect-rules',
			[$this, 'render_redirect_rules_page']
		);
	}

	public function enqueue_assets(string $hook_suffix): void
	{
		// Fix: Safely modified to load assets only if the page name contains 
		// 'wpmm-redirect-rules' or 'media-route-and-replace'.
		if (! str_contains($hook_suffix, 'media-route-and-replace') && ! str_contains($hook_suffix, 'wpmm-redirect-rules')) {
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
				'confirmDelete'     => __('Are you sure you want to delete this entry? This cannot be undone.', 'media-route-and-replace'),
				'saving'            => __('Saving…', 'media-route-and-replace'),
				'deleting'          => __('Deleting…', 'media-route-and-replace'),
				'checking'          => __('Checking…', 'media-route-and-replace'),
				'selectMedia'       => __('Select Media', 'media-route-and-replace'),
				'useSelected'       => __('Use this file', 'media-route-and-replace'),
				'noEntries'         => __('No media entries found. Click "Add New" to get started.', 'media-route-and-replace'),
				'invalidUrl'        => __('Please enter a valid WordPress media URL.', 'media-route-and-replace'),
				'urlNotMedia'       => __("The URL does not belong to this site's media library.", 'media-route-and-replace'),
				'duplicateTitle'    => __('File Already Exists', 'media-route-and-replace'),
				'btnReplace'        => __('Yes, Replace It', 'media-route-and-replace'),
				'btnCancel'         => __('No, Cancel', 'media-route-and-replace'),
				'copyUrl'           => __('Copy URL', 'media-route-and-replace'),
				'copied'            => __('Copied!', 'media-route-and-replace'),
			],
		]);
	}

	public function render_page(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'media-route-and-replace'));
		}
		require_once WPMM_PLUGIN_DIR . 'templates/admin-page.php';
	}

	/**
	 * redirect rules for submenu page
	 */
	public function render_redirect_rules_page(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'media-route-and-replace'));
		}
		require_once WPMM_PLUGIN_DIR . 'templates/redirect-rules-page.php';
	}
}
