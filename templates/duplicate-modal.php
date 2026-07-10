<?php

/**
 * Duplicate confirmation modal.
 *
 * Shown when JS detects a (custom_path + custom_name) collision before save.
 *
 * @package Media_Route_And_Replace
 */
if (! defined('ABSPATH')) {
	exit;
}
?>
<div id="wpmm-dup-modal" class="wpmm-modal wpmm-modal--sm" role="alertdialog"
	aria-modal="true" aria-labelledby="wpmm-dup-title" aria-describedby="wpmm-dup-desc" hidden>
	<div class="wpmm-modal__backdrop"></div>
	<div class="wpmm-modal__dialog wpmm-modal__dialog--sm">

		<div class="wpmm-modal__header wpmm-modal__header--warning">
			<span class="dashicons dashicons-warning wpmm-modal__header-icon" aria-hidden="true"></span>
			<h2 class="wpmm-modal__title" id="wpmm-dup-title">
				<?php esc_html_e('File Already Exists', 'media-relink-and-routes'); ?>
			</h2>
		</div>

		<div class="wpmm-modal__body wpmm-modal__body--centered">
			<p id="wpmm-dup-desc" class="wpmm-dup-message"></p>
			<input type="hidden" id="wpmm-dup-target-id" value="" />
		</div>

		<div class="wpmm-modal__footer">
			<button type="button" class="wpmm-btn wpmm-btn--ghost" id="wpmm-dup-cancel">
				<?php esc_html_e('No, Cancel', 'media-relink-and-routes'); ?>
			</button>
			<button type="button" class="wpmm-btn wpmm-btn--danger" id="wpmm-dup-replace">
				<span class="dashicons dashicons-update" aria-hidden="true"></span>
				<?php esc_html_e('Yes, Replace It', 'media-relink-and-routes'); ?>
			</button>
		</div>

	</div>
</div>