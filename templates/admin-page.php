<?php

/**
 * Main admin page template.
 *
 * @package WP_Media_Manager
 */
if (! defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap wpmm-wrap">

	<div class="wpmm-header">
		<div class="wpmm-header__left">
			<span class="dashicons dashicons-portfolio wpmm-header__icon"></span>
			<h1 class="wpmm-header__title"><?php esc_html_e('Media Manager', 'wp-media-manager'); ?></h1>
		</div>
		<div class="wpmm-header__right">
			<button type="button" class="wpmm-btn wpmm-btn--secondary" id="wpmm-replace-media-btn">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e('Replace Media', 'wp-media-manager'); ?>
			</button>
			<button type="button" class="wpmm-btn wpmm-btn--primary" id="wpmm-add-new">
				<span class="dashicons dashicons-plus-alt2"></span>
				<?php esc_html_e('Add New Media', 'wp-media-manager'); ?>
			</button>
		</div>
	</div>

	<div id="wpmm-toast-container" aria-live="polite" aria-atomic="true"></div>

	<div class="wpmm-toolbar">
		<div class="wpmm-search">
			<span class="dashicons dashicons-search wpmm-search__icon"></span>
			<input type="search" id="wpmm-search" class="wpmm-search__input"
				placeholder="<?php esc_attr_e('Search by name, path, or type…', 'wp-media-manager'); ?>"
				autocomplete="off" />
		</div>
		<div class="wpmm-toolbar__meta">
			<span id="wpmm-count-label" class="wpmm-count-label"></span>
		</div>
	</div>

	<div id="wpmm-table-wrap" class="wpmm-table-wrap">
		<div id="wpmm-loading" class="wpmm-loading">
			<span class="wpmm-spinner"></span>
			<span><?php esc_html_e('Loading entries…', 'wp-media-manager'); ?></span>
		</div>

		<div id="wpmm-empty" class="wpmm-empty" hidden>
			<span class="dashicons dashicons-portfolio wpmm-empty__icon"></span>
			<h3 class="wpmm-empty__title"><?php esc_html_e('No entries yet', 'wp-media-manager'); ?></h3>
			<p class="wpmm-empty__desc"><?php esc_html_e('Click "Add New" to add your first media entry.', 'wp-media-manager'); ?></p>
			<button type="button" class="wpmm-btn wpmm-btn--primary" id="wpmm-add-new-empty">
				<span class="dashicons dashicons-plus-alt2"></span>
				<?php esc_html_e('Add New Entry', 'wp-media-manager'); ?>
			</button>
		</div>

		<div id="wpmm-grid" class="wpmm-grid" hidden></div>
		<div id="wpmm-pagination" class="wpmm-pagination" hidden></div>
	</div>

	<div id="wpmm-replace-modal" class="wpmm-modal" role="dialog" aria-modal="true" aria-labelledby="wpmm-replace-modal-title" style="z-index: 999999;" hidden>
		<div class="wpmm-modal__backdrop" style="z-index: 999998;"></div>
		<div class="wpmm-modal__dialog" style="max-width: 750px; z-index: 999999; background: #fff; border-radius: 8px; overflow: hidden; position: relative;">

			<div class="wpmm-modal__header">
				<h2 class="wpmm-modal__title" id="wpmm-replace-modal-title"><?php esc_html_e('Select New Replacement Media', 'wp-media-manager'); ?></h2>
				<button type="button" class="wpmm-modal__close" id="wpmm-replace-modal-close">&times;</button>
			</div>

			<div class="wpmm-modal__body" style="padding: 20px; max-height: 80vh; overflow-y: auto;">
				<input type="hidden" id="wpmm-replace-attachment-id" value="" />

				<div class="wpmm-form-row" style="display: flex; gap: 20px; align-items: center; background: #f3f4f6; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
					<div style="flex: 1; text-align: center;">
						<strong style="display:block; margin-bottom: 8px; color:#4b5563; font-size:12px;"><?php esc_html_e('Current Media', 'wp-media-manager'); ?></strong>
						<div id="wpmm-replace-current-preview" style="width:100px; height:100px; margin:0 auto; background:#e5e7eb; border-radius:6px; display:flex; align-items:center; justify-content:center; overflow:hidden;"></div>
					</div>
					<div style="font-size: 24px; color: #9ca3af; font-weight: bold;">➔</div>
					<div style="flex: 1; text-align: center;">
						<strong style="display:block; margin-bottom: 8px; color:#4b5563; font-size:12px;"><?php esc_html_e('New Media', 'wp-media-manager'); ?></strong>
						<div id="wpmm-replace-new-preview" style="width:100px; height:100px; margin:0 auto; background:#fff; border:2px dashed #9ca3af; border-radius:6px; display:flex; align-items:center; justify-content:center; overflow:hidden; cursor:pointer;">
							<span class="dashicons dashicons-plus" style="font-size:28px; color:#9ca3af;"></span>
						</div>
						<input type="file" id="wpmm-replace-file-input" style="display: none;" />
					</div>
				</div>

				<div style="display: flex; gap: 20px;">
					<div style="flex: 1; background: #f9fafb; padding: 15px; border-radius: 6px; border: 1px solid #e5e7eb;">
						<h3 style="margin-top:0; font-size:14px; border-bottom:1px solid #e5e7eb; padding-bottom:8px; color:#111827;"><?php esc_html_e('Replacement Options', 'wp-media-manager'); ?></h3>

						<label style="display:block; margin-bottom:10px; font-weight:600; color:#111827; cursor:pointer;">
							<input type="radio" name="wpmm_replace_method" value="just_replace" checked style="margin-right:5px;" />
							<?php esc_html_e('Just replace the file', 'wp-media-manager'); ?>
						</label>
						<p style="font-size:11px; color:#6b7280; margin-left:20px; margin-top:-5px; line-height:1.4;">
							<?php esc_html_e('The file name will remain the same regardless of what file you upload, and it will be saved in the exact same URL path.', 'wp-media-manager'); ?>
						</p>

						<label style="display:block; margin-top:20px; margin-bottom:10px; font-weight:600; color:#111827; cursor:pointer;">
							<input type="radio" name="wpmm_replace_method" value="replace_and_rename" style="margin-right:5px;" />
							<?php esc_html_e('Replace the file, use the new file name, and update all links', 'wp-media-manager'); ?>
						</label>
						<p style="font-size:11px; color:#6b7280; margin-left:20px; margin-top:-5px; line-height:1.4;">
							<?php esc_html_e('The original name of the new file will be used, and all custom manager links mapped to this media will be updated automatically.', 'wp-media-manager'); ?>
						</p>
					</div>

					<div style="flex: 1; background: #f9fafb; padding: 15px; border-radius: 6px; border: 1px solid #e5e7eb;">
						<h3 style="margin-top:0; font-size:14px; border-bottom:1px solid #e5e7eb; padding-bottom:8px; color:#111827;"><?php esc_html_e('Options', 'wp-media-manager'); ?></h3>
						<p style="font-size:11px; font-weight:600; color:#4b5563; margin-bottom:10px;"><?php esc_html_e('When replacing the media, do you want to:', 'wp-media-manager'); ?></p>

						<label style="display:block; margin-bottom:10px; font-weight:normal; color:#111827; cursor:pointer;">
							<input type="radio" name="wpmm_date_method" value="current_date" style="margin-right:5px;" />
							<?php esc_html_e('Replace the date with the current date', 'wp-media-manager'); ?>
							<span style="font-size:10px; color:#9ca3af;" id="wpmm-current-date-text"></span>
						</label>

						<label style="display:block; margin-bottom:10px; font-weight:normal; color:#111827; cursor:pointer;">
							<input type="radio" name="wpmm_date_method" value="keep_date" checked style="margin-right:5px;" />
							<?php esc_html_e('Keep the date', 'wp-media-manager'); ?>
							<span style="font-size:10px; color:#16a34a; font-weight:bold;" id="wpmm-old-date-text"></span>
						</label>
					</div>
				</div>
			</div>

			<div class="wpmm-modal__footer" style="padding:15px 20px; background:#f9fafb; display:flex; justify-content:flex-end; gap:10px; border-top:1px solid #e5e7eb;">
				<button type="button" class="wpmm-btn wpmm-btn--ghost" id="wpmm-replace-modal-cancel"><?php esc_html_e('Cancel', 'wp-media-manager'); ?></button>
				<button type="button" class="wpmm-btn wpmm-btn--primary" id="wpmm-replace-modal-submit" disabled>
					<span class="wpmm-spinner" style="display:none; margin-right:5px; vertical-align:middle;"></span>
					<span class="wpmm-btn__label"><?php esc_html_e('Upload', 'wp-media-manager'); ?></span>
				</button>
			</div>
		</div>
	</div>

</div>

<?php
require_once WPMM_PLUGIN_DIR . 'templates/modal.php';
require_once WPMM_PLUGIN_DIR . 'templates/preview-modal.php';
require_once WPMM_PLUGIN_DIR . 'templates/duplicate-modal.php';
?>