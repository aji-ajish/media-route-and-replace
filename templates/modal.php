<?php
/**
 * Add / Edit modal template.
 *
 * @package Media_Route_And_Replace
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- Add/Edit Modal -->
<div id="wpmm-modal" class="wpmm-modal" role="dialog" aria-modal="true" aria-labelledby="wpmm-modal-title" hidden>
	<div class="wpmm-modal__backdrop"></div>
	<div class="wpmm-modal__dialog">

		<div class="wpmm-modal__header">
			<h2 class="wpmm-modal__title" id="wpmm-modal-title">
				<?php esc_html_e( 'Add Media Entry', 'media-route-and-replace' ); ?>
			</h2>
			<button type="button" class="wpmm-modal__close" aria-label="<?php esc_attr_e( 'Close', 'media-route-and-replace' ); ?>">
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
			</button>
		</div>

		<div class="wpmm-modal__body">

			<!-- Hidden edit ID -->
			<input type="hidden" id="wpmm-entry-id" value="" />

			<!-- ── SECTION 1: Media Selection ── -->
			<div class="wpmm-form-section">
				<h3 class="wpmm-form-section__heading">
					<span class="dashicons dashicons-admin-media" aria-hidden="true"></span>
					<?php esc_html_e( 'Media File', 'media-route-and-replace' ); ?>
				</h3>

				<div class="wpmm-media-selector">

					<!-- Preview area -->
					<div id="wpmm-media-preview" class="wpmm-media-preview wpmm-media-preview--empty">
						<span class="dashicons dashicons-format-image wpmm-media-preview__placeholder-icon" aria-hidden="true"></span>
						<span class="wpmm-media-preview__placeholder-text">
							<?php esc_html_e( 'No file selected', 'media-route-and-replace' ); ?>
						</span>
					</div>

					<div class="wpmm-media-selector__actions">
						<button type="button" class="wpmm-btn wpmm-btn--secondary" id="wpmm-open-media-library">
							<span class="dashicons dashicons-admin-media" aria-hidden="true"></span>
							<?php esc_html_e( 'Choose from Media Library', 'media-route-and-replace' ); ?>
						</button>

						<span class="wpmm-or-divider"><?php esc_html_e( 'or paste URL', 'media-route-and-replace' ); ?></span>

						<div class="wpmm-url-input-wrap">
							<input
								type="url"
								id="wpmm-url-input"
								class="wpmm-input"
								placeholder="<?php esc_attr_e( 'https://yoursite.com/wp-content/uploads/…', 'media-route-and-replace' ); ?>"
								autocomplete="off"
							/>
							<button type="button" class="wpmm-btn wpmm-btn--secondary wpmm-btn--icon" id="wpmm-resolve-url" title="<?php esc_attr_e( 'Detect attachment', 'media-route-and-replace' ); ?>">
								<span class="dashicons dashicons-search" aria-hidden="true"></span>
							</button>
						</div>

						<div id="wpmm-url-error" class="wpmm-field-error" hidden></div>
					</div>

					<!-- Hidden fields populated programmatically -->
					<input type="hidden" id="wpmm-attachment-id" name="attachment_id" value="" />
					<input type="hidden" id="wpmm-original-url" name="original_url" value="" />
					<input type="hidden" id="wpmm-file-type" name="file_type" value="" />
				</div>

				<!-- Selected file info strip -->
				<div id="wpmm-selected-info" class="wpmm-selected-info" hidden>
					<span class="dashicons dashicons-yes-alt wpmm-selected-info__icon" aria-hidden="true"></span>
					<span id="wpmm-selected-filename" class="wpmm-selected-info__name"></span>
					<button type="button" class="wpmm-selected-info__clear" id="wpmm-clear-media" title="<?php esc_attr_e( 'Clear selection', 'media-route-and-replace' ); ?>">
						<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
					</button>
				</div>
			</div>

			<!-- ── SECTION 2: Custom Settings ── -->
			<div class="wpmm-form-section">
				<h3 class="wpmm-form-section__heading">
					<span class="dashicons dashicons-edit" aria-hidden="true"></span>
					<?php esc_html_e( 'Custom Settings', 'media-route-and-replace' ); ?>
				</h3>

				<div class="wpmm-form-row">
					<label class="wpmm-label" for="wpmm-custom-name">
						<?php esc_html_e( 'Custom Name', 'media-route-and-replace' ); ?>
						<span class="wpmm-label__hint"><?php esc_html_e( '(without extension)', 'media-route-and-replace' ); ?></span>
					</label>
					<input
						type="text"
						id="wpmm-custom-name"
						class="wpmm-input"
						placeholder="<?php esc_attr_e( 'e.g. my-annual-report', 'media-route-and-replace' ); ?>"
						autocomplete="off"
					/>
				</div>

				<div class="wpmm-form-row">
					<label class="wpmm-label" for="wpmm-custom-path">
						<?php esc_html_e( 'Custom Path', 'media-route-and-replace' ); ?>
						<span class="wpmm-label__hint"><?php esc_html_e( '(relative, no leading slash)', 'media-route-and-replace' ); ?></span>
					</label>
					<input
						type="text"
						id="wpmm-custom-path"
						class="wpmm-input"
						placeholder="<?php esc_attr_e( 'e.g. reports/2025', 'media-route-and-replace' ); ?>"
						autocomplete="off"
					/>
				</div>

				<div class="wpmm-form-row wpmm-form-row--toggle">
					<label class="wpmm-toggle" for="wpmm-include-ext">
						<input type="checkbox" id="wpmm-include-ext" class="wpmm-toggle__input" checked />
						<span class="wpmm-toggle__track" aria-hidden="true"></span>
						<span class="wpmm-toggle__label"><?php esc_html_e( 'Include file extension', 'media-route-and-replace' ); ?></span>
					</label>
				</div>

				<!-- Live output preview -->
				<div class="wpmm-output-preview">
					<span class="wpmm-output-preview__label"><?php esc_html_e( 'Preview:', 'media-route-and-replace' ); ?></span>
					<code id="wpmm-output-preview-value" class="wpmm-output-preview__value">—</code>
				</div>
			</div>

		</div><!-- .wpmm-modal__body -->

		<div class="wpmm-modal__footer">
			<button type="button" class="wpmm-btn wpmm-btn--ghost" id="wpmm-modal-cancel">
				<?php esc_html_e( 'Cancel', 'media-route-and-replace' ); ?>
			</button>
			<button type="button" class="wpmm-btn wpmm-btn--primary" id="wpmm-modal-save">
				<span class="wpmm-btn__spinner wpmm-spinner" hidden></span>
				<span class="wpmm-btn__label"><?php esc_html_e( 'Save Entry', 'media-route-and-replace' ); ?></span>
			</button>
		</div>

	</div><!-- .wpmm-modal__dialog -->
</div><!-- #wpmm-modal -->
