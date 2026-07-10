<?php
/**
 * Preview modal template.
 *
 * @package Media_Route_And_Replace
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- Preview Modal -->
<div id="wpmm-preview-modal" class="wpmm-modal wpmm-modal--preview" role="dialog" aria-modal="true" aria-labelledby="wpmm-preview-title" hidden>
	<div class="wpmm-modal__backdrop"></div>
	<div class="wpmm-modal__dialog wpmm-modal__dialog--preview">

		<div class="wpmm-modal__header">
			<h2 class="wpmm-modal__title" id="wpmm-preview-title">
				<?php esc_html_e( 'Preview', 'media-route-and-replace' ); ?>
			</h2>
			<button type="button" class="wpmm-modal__close wpmm-preview-close" aria-label="<?php esc_attr_e( 'Close preview', 'media-route-and-replace' ); ?>">
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
			</button>
		</div>

		<div class="wpmm-modal__body wpmm-modal__body--preview" id="wpmm-preview-body">
			<!-- Content injected dynamically by JS -->
		</div>

	</div>
</div>
