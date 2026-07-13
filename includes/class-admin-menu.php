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
			__('Linko Media Path Mapper & Swapper', 'linko-media-path-mapper-and-swapper'),
			__('Linko Media Path Mapper & Swapper', 'linko-media-path-mapper-and-swapper'),
			'manage_options',
			'linko-media-path-mapper-and-swapper', 
			[$this, 'render_page'],
			'dashicons-randomize', 
			99
		);

		// Redirect Rules
		add_submenu_page(
			'linko-media-path-mapper-and-swapper',
			__('Redirect Rules', 'linko-media-path-mapper-and-swapper'),
			__('Redirect Rules', 'linko-media-path-mapper-and-swapper'),
			'manage_options',
			'wpmm-redirect-rules',
			[$this, 'render_redirect_rules_page']
		);
	}

	public function enqueue_assets(string $hook_suffix): void
	{
		// Fix: Safely modified to load assets only if the page name contains 
		// 'wpmm-redirect-rules' or 'linko-media-path-mapper-and-swapper'.
		if (! str_contains($hook_suffix, 'linko-media-path-mapper-and-swapper') && ! str_contains($hook_suffix, 'wpmm-redirect-rules')) {
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
				'confirmDelete'     => __('Are you sure you want to delete this entry? This cannot be undone.', 'linko-media-path-mapper-and-swapper'),
				'saving'            => __('Saving…', 'linko-media-path-mapper-and-swapper'),
				'deleting'          => __('Deleting…', 'linko-media-path-mapper-and-swapper'),
				'checking'          => __('Checking…', 'linko-media-path-mapper-and-swapper'),
				'selectMedia'       => __('Select Media', 'linko-media-path-mapper-and-swapper'),
				'useSelected'       => __('Use this file', 'linko-media-path-mapper-and-swapper'),
				'noEntries'         => __('No media entries found. Click "Add New" to get started.', 'linko-media-path-mapper-and-swapper'),
				'invalidUrl'        => __('Please enter a valid WordPress media URL.', 'linko-media-path-mapper-and-swapper'),
				'urlNotMedia'       => __("The URL does not belong to this site's media library.", 'linko-media-path-mapper-and-swapper'),
				'duplicateTitle'    => __('File Already Exists', 'linko-media-path-mapper-and-swapper'),
				'btnReplace'        => __('Yes, Replace It', 'linko-media-path-mapper-and-swapper'),
				'btnCancel'         => __('No, Cancel', 'linko-media-path-mapper-and-swapper'),
				'copyUrl'           => __('Copy URL', 'linko-media-path-mapper-and-swapper'),
				'copied'            => __('Copied!', 'linko-media-path-mapper-and-swapper'),
			],
		]);
	}

	public function render_page(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'linko-media-path-mapper-and-swapper'));
		}
		require_once WPMM_PLUGIN_DIR . 'templates/admin-page.php';
	}

	/**
	 * redirect rules for submenu page
	 */
	public function render_redirect_rules_page(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'linko-media-path-mapper-and-swapper'));
		}
		require_once WPMM_PLUGIN_DIR . 'templates/redirect-rules-page.php';
	}
}
