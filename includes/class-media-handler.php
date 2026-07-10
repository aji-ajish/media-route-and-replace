<?php
/**
 * Media / attachment utilities.
 *
 * @package Media_Route_And_Replace
 */

namespace Media_Route_And_Replace;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Media_Handler
 *
 * Resolves attachment data from IDs or URLs, and
 * builds the media data array used by the rest of the plugin.
 */
class Media_Handler {

	/**
	 * Resolve an attachment from its URL.
	 *
	 * Tries attachment_url_to_postid first; falls back to a DB
	 * query checking both guid and _wp_attached_file meta.
	 *
	 * @param string $url The media URL.
	 * @return int|null Attachment post ID or null if not found.
	 */
	public function get_attachment_id_from_url( string $url ): ?int {
		// Fast path: core function.
		$id = attachment_url_to_postid( $url );
		if ( $id ) {
			return $id;
		}

		// Slow path: meta query on _wp_attached_file.
		global $wpdb;
		$upload_dir = wp_upload_dir();
		$path       = str_replace( trailingslashit( $upload_dir['baseurl'] ), '', $url );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key = '_wp_attached_file'
                 AND meta_value = %s
                 LIMIT 1",
                $path
            )
        );

		return $id ?: null;
	}

	/**
	 * Build a standardised data array for an attachment.
	 *
	 * @param int $attachment_id WP attachment post ID.
	 * @return array<string,mixed>|null Data array or null if invalid.
	 */
	public function get_attachment_data( int $attachment_id ): ?array {
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			return null;
		}

		$mime     = get_post_mime_type( $attachment_id );
		$url      = wp_get_attachment_url( $attachment_id );
		$filename = wp_basename( get_attached_file( $attachment_id ) ?? $url );
		$category = Helper::get_file_category( $mime );

		// Thumbnail for images.
		$thumbnail = null;
		if ( 'image' === $category ) {
			$thumb = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
			if ( $thumb ) {
				$thumbnail = $thumb[0];
			}
		}

		return [
			'attachment_id' => $attachment_id,
			'original_url'  => $url,
			'filename'      => $filename,
			'mime_type'     => $mime,
			'file_type'     => $category,
			'thumbnail'     => $thumbnail,
			'title'         => get_the_title( $attachment_id ),
		];
	}

	/**
	 * Validate that a given URL belongs to the WordPress uploads directory.
	 *
	 * @param string $url URL to check.
	 * @return bool True if it is a local uploads URL.
	 */
	public function is_local_media_url( string $url ): bool {
		$upload_dir = wp_upload_dir();
		return str_starts_with( $url, $upload_dir['baseurl'] );
	}
}
