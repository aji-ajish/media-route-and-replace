<?php

/**
 * Database management — schema, CRUD, duplicate detection, URL lookup.
 *
 * @package Linko_Media_Path_Mapper_And_Swapper
 */

namespace Linko_Media_Path_Mapper_And_Swapper;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Database
 */
class Database
{

	/** @var string Full prefixed table name. */
	private string $table;

	public function __construct()
	{
		global $wpdb;
		$this->table = $wpdb->prefix . WPMM_TABLE_NAME;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Schema
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Create or upgrade the plugin table using dbDelta.
	 *
	 * @return bool True if table exists after the call.
	 */
	public static function create_tables(): bool
	{
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$media_table     = $wpdb->prefix . WPMM_TABLE_NAME;
		$redirect_table  = $wpdb->prefix . WPMM_REDIRECT_TABLE_NAME;

		// phpcs:disable
		$sql_media = "CREATE TABLE {$media_table} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		attachment_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
		original_url TEXT NOT NULL,
		original_url_hash VARCHAR(32) NOT NULL DEFAULT '',
		custom_name VARCHAR(255) NOT NULL DEFAULT '',
		custom_path VARCHAR(500) NOT NULL DEFAULT '',
		include_extension TINYINT(1) NOT NULL DEFAULT 1,
		file_type VARCHAR(100) NOT NULL DEFAULT '',
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id),
		KEY idx_attachment_id (attachment_id),
		UNIQUE KEY unique_path_name (custom_path(100),custom_name(100),include_extension,original_url_hash)
		) {$charset_collate};";

		$sql_redirect = "CREATE TABLE {$redirect_table} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		source_path VARCHAR(500) NOT NULL DEFAULT '',
		target_url TEXT NOT NULL,
		redirect_type INT(3) NOT NULL DEFAULT 301,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		hits_count BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id),
		UNIQUE KEY unique_source_path (source_path(190))
		) {$charset_collate};";
		// phpcs:enable

		if (! function_exists('dbDelta')) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$result_media    = dbDelta($sql_media);
		$result_redirect = dbDelta($sql_redirect);

		if (! empty($result_media) || ! empty($result_redirect)) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log('[WP Media Manager] dbDelta triggered for media and redirect tables.');
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$media_exists    = (bool) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $media_table));
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$redirect_exists = (bool) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $redirect_table));

		$all_exist = $media_exists && $redirect_exists;

		if (! $all_exist) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log("[WP Media Manager] create_tables() FAILED. Last DB error: {$wpdb->last_error}");
		}

		return $all_exist;
	}

	/**
	 * Drop the plugin table — used by uninstall.php only.
	 *
	 * @return void
	 */
	public static function drop_tables(): void
	{
		global $wpdb;
		$table          = sanitize_key($wpdb->prefix . WPMM_TABLE_NAME);
		$redirect_table = sanitize_key($wpdb->prefix . WPMM_REDIRECT_TABLE_NAME);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query("DROP TABLE IF EXISTS {$table}");
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query("DROP TABLE IF EXISTS {$redirect_table}");
	}

	/**
	 * Ensure the table exists; create it if missing.
	 *
	 * @return bool
	 */
	public function ensure_table(): bool
	{
		static $verified = false;

		if ($verified) {
			return true;
		}

		global $wpdb;
		$table = $wpdb->prefix . WPMM_TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
			$verified = true;
			return true;
		}

		$created = self::create_tables();

		if ($created) {
			$verified = true;
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log('[WP Media Manager] ensure_table(): table still missing after create attempt.');
		}

		return $created;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// CRUD
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Insert a new entry.
	 *
	 * @param array<string,mixed> $data
	 * @return int|false Inserted ID, or false on failure.
	 */
	public function insert(array $data): int|false
	{
		global $wpdb;

		if (! $this->ensure_table()) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log('[WP Media Manager] insert() aborted — table unavailable.');
			return false;
		}

		$sanitized = $this->sanitize_entry($data);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result    = $wpdb->insert(
			$this->table,
			$sanitized,
			$this->get_format($sanitized)
		);

		if (false === $result) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log('[WP Media Manager] insert() DB error: ' . $wpdb->last_error);
			return false;
		}
		delete_transient('wpmm_url_lookup_map');
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing entry.
	 *
	 * @param int                 $id
	 * @param array<string,mixed> $data
	 * @return bool
	 */
	public function update(int $id, array $data): bool
	{
		global $wpdb;

		if (! $this->ensure_table()) {
			return false;
		}

		$sanitized               = $this->sanitize_entry($data);
		$sanitized['updated_at'] = current_time('mysql', true);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table,
			$sanitized,
			['id' => $id],
			$this->get_format($sanitized),
			['%d']
		);

		if (false === $result) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log('[WP Media Manager] update() DB error: ' . $wpdb->last_error);
		}
		delete_transient('wpmm_url_lookup_map');
		return $result !== false;
	}

	/**
	 * Overwrite all fields of an existing row (duplicate-replace flow).
	 *
	 * @param int                 $id
	 * @param array<string,mixed> $data
	 * @return bool
	 */
	public function replace_entry(int $id, array $data): bool
	{
		global $wpdb;

		if (! $this->ensure_table()) {
			return false;
		}

		$sanitized               = $this->sanitize_entry($data);
		$sanitized['updated_at'] = current_time('mysql', true);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table,
			$sanitized,
			['id' => $id],
			$this->get_format($sanitized),
			['%d']
		);
		delete_transient('wpmm_url_lookup_map');
		return $result !== false;
	}

	/**
	 * Delete an entry by ID.
	 *
	 * @param int $id
	 * @return bool
	 */
	public function delete(int $id): bool
	{
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$this->table,
			['id' => $id],
			['%d']
		);
		delete_transient('wpmm_url_lookup_map');
		return $result !== false;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Read / lookup
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Get a single row by ID.
	 *
	 * @param int $id
	 * @return object|null
	 */
	public function get(int $id): ?object
	{
		global $wpdb;
		$table = sanitize_key($this->table);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE id = %d LIMIT 1",
				$table,
				$id
			)
		);
	}

	/**
	 * Find a duplicate by (custom_path, custom_name), excluding one row.
	 *
	 * @param string   $custom_path
	 * @param string   $custom_name
	 * @param int|null $exclude_id
	 * @return object|null
	 */
	public function find_duplicate(
		string $custom_path,
		string $custom_name,
		?int   $exclude_id = null
	): ?object {
		global $wpdb;

		$table = sanitize_key($this->table);
		$path  = sanitize_text_field($custom_path);
		$name  = sanitize_text_field($custom_name);

		if ($exclude_id) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM %i
					 WHERE custom_path = %s AND custom_name = %s AND id != %d
					 LIMIT 1",
					$table,
					$path,
					$name,
					$exclude_id
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i
				 WHERE custom_path = %s AND custom_name = %s
				 LIMIT 1",
				$table,
				$path,
				$name
			)
		);
	}

	/**
	 * Return ALL entries with the same (custom_path, custom_name), optionally
	 * excluding one row (for edit operations).
	 *
	 * @param string   $custom_path
	 * @param string   $custom_name
	 * @param int|null $exclude_id
	 * @return array<object>
	 */
	public function find_all_by_path_name(
		string $custom_path,
		string $custom_name,
		?int   $exclude_id = null
	): array {
		global $wpdb;

		$table = sanitize_key($this->table);
		$path  = sanitize_text_field($custom_path);
		$name  = sanitize_text_field($custom_name);

		if ($exclude_id) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i
					 WHERE custom_path = %s AND custom_name = %s AND id != %d",
					$table,
					$path,
					$name,
					$exclude_id
				)
			) ?: [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i
				 WHERE custom_path = %s AND custom_name = %s",
				$table,
				$path,
				$name
			)
		) ?: [];
	}

	/**
	 * Resolve a URL request path to a DB entry.
	 *
	 * @param string $request_path
	 * @return object|null
	 */
	public function find_by_request_path(string $request_path): ?object
	{
		global $wpdb;

		$table        = sanitize_key($this->table);
		$request_path = ltrim($request_path, '/');
		$last_slash   = strrpos($request_path, '/');

		if ($last_slash !== false) {
			$dir  = substr($request_path, 0, $last_slash);
			$file = substr($request_path, $last_slash + 1);
		} else {
			$dir  = '';
			$file = $request_path;
		}

		$dot         = strrpos($file, '.');
		$file_no_ext = $dot !== false ? substr($file, 0, $dot) : $file;
		$request_ext = $dot !== false ? strtolower(substr($file, $dot + 1)) : '';

		if (! empty($request_ext)) {
			$like_pattern = '%' . $wpdb->esc_like('.' . $request_ext);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM %i
                 WHERE custom_path = %s 
                 AND custom_name = %s 
                 AND include_extension = 1
                 AND original_url LIKE %s
                 LIMIT 1",
					$table,
					$dir,
					$file_no_ext,
					$like_pattern
				)
			);

			if ($row) {
				return $row;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i
				 WHERE custom_path = %s AND custom_name = %s
				 LIMIT 1",
				$table,
				$dir,
				$file_no_ext
			)
		);

		if ($row) {
			return $row;
		}

		if ($file !== $file_no_ext) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM %i
					 WHERE custom_path = %s AND custom_name = %s
					 LIMIT 1",
					$table,
					$dir,
					$file
				)
			);
		}

		return null;
	}

	/**
	 * Look up an entry by its original WordPress upload URL.
	 *
	 * @param string $original_url
	 * @return object|null
	 */
	public function find_by_original_url(string $original_url): ?object
	{
		global $wpdb;

		$table = sanitize_key($this->table);
		$url   = rawurldecode(strtok($original_url, '?'));

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i
				 WHERE original_url = %s
				 LIMIT 1",
				$table,
				$url
			)
		);
	}

	/**
	 * Look up an entry by the path portion of an uploads URL.
	 *
	 * @param string               $request_path
	 * @param array<string,string> $upload_dir
	 * @return object|null
	 */
	public function find_by_original_url_path(string $request_path, array $upload_dir): ?object
	{
		global $wpdb;

		$table     = sanitize_key($this->table);
		$base_url  = trailingslashit($upload_dir['baseurl']);
		$protocols = ['http', 'https'];
		$file_rel  = ltrim($request_path, '/');
		$site_host = wp_parse_url($base_url, PHP_URL_HOST) ?? '';

		$candidates = [];
		foreach ($protocols as $proto) {
			$candidates[] = $proto . '://' . $site_host . '/' . $file_rel;
			$candidates[] = $proto . '://' . $site_host . '/' . ltrim($file_rel, '/');
		}

		$candidates = array_unique($candidates);

		foreach ($candidates as $candidate) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM %i
					 WHERE original_url = %s
					 LIMIT 1",
					$table,
					$candidate
				)
			);
			if ($row) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Find all entries that share an attachment_id.
	 *
	 * @param int $attachment_id
	 * @return array<object>
	 */
	public function find_by_attachment_id(int $attachment_id): array
	{
		global $wpdb;
		$table = sanitize_key($this->table);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE attachment_id = %d",
				$table,
				$attachment_id
			)
		) ?: [];
	}

	/**
	 * Paginated list with optional search.
	 *
	 * @param array<string,mixed> $args
	 * @return array<object>
	 */
	public function get_all(array $args = []): array
	{
		global $wpdb;

		$table = sanitize_key($this->table);

		$args = wp_parse_args($args, [
			'search'   => '',
			'per_page' => 20,
			'page'     => 1,
			'orderby'  => 'created_at',
			'order'    => 'DESC',
		]);

		$orderby = in_array($args['orderby'], ['id', 'custom_name', 'file_type', 'created_at'], true)
			? $args['orderby'] : 'created_at';
		$order   = 'ASC' === strtoupper((string) $args['order']) ? 'ASC' : 'DESC';

		$per_page = (int) $args['per_page'];
		$offset   = ((int) $args['page'] - 1) * $per_page;

		$clean_orderby = sanitize_key($orderby);
		$clean_order   = sanitize_key($order);

		if (-1 === $per_page) {
			if (! empty($args['search'])) {
				$like = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				return $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM %i WHERE 1=1 AND (custom_name LIKE %s OR custom_path LIKE %s OR file_type LIKE %s) ORDER BY %s %s",
						$table,
						$like,
						$like,
						$like,
						$clean_orderby,
						$clean_order
					)
				) ?: [];
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i WHERE 1=1 ORDER BY %s %s",
					$table,
					$clean_orderby,
					$clean_order
				)
			) ?: [];
		}

		if (! empty($args['search'])) {
			$like = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i WHERE 1=1 AND (custom_name LIKE %s OR custom_path LIKE %s OR file_type LIKE %s) ORDER BY %s %s LIMIT %d OFFSET %d",
					$table,
					$like,
					$like,
					$like,
					$clean_orderby,
					$clean_order,
					$per_page,
					$offset
				)
			) ?: [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE 1=1 ORDER BY %s %s LIMIT %d OFFSET %d",
				$table,
				$clean_orderby,
				$clean_order,
				$per_page,
				$offset
			)
		) ?: [];
	}

	/**
	 * Count entries with optional search.
	 *
	 * @param string $search
	 * @return int
	 */
	public function count(string $search = ''): int
	{
		global $wpdb;

		$table = sanitize_key($this->table);

		if (! empty($search)) {
			$like = '%' . $wpdb->esc_like(sanitize_text_field($search)) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i
					 WHERE custom_name LIKE %s OR custom_path LIKE %s OR file_type LIKE %s",
					$table,
					$like,
					$like,
					$like
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i",
				$table
			)
		);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Private helpers
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>
	 */
	private function sanitize_entry(array $data): array
	{
		$clean = [];

		if (array_key_exists('attachment_id', $data))     $clean['attachment_id']     = absint($data['attachment_id']);

		if (array_key_exists('original_url', $data)) {
			$url = esc_url_raw((string) $data['original_url']);
			$clean['original_url']      = $url;
			$clean['original_url_hash'] = md5(rawurldecode(strtok($url, '?')));
		}

		if (array_key_exists('custom_name', $data))       $clean['custom_name']       = sanitize_text_field((string) $data['custom_name']);
		if (array_key_exists('custom_path', $data))       $clean['custom_path']       = sanitize_text_field((string) $data['custom_path']);
		if (array_key_exists('include_extension', $data)) $clean['include_extension'] = (int) (bool) $data['include_extension'];
		if (array_key_exists('file_type', $data))         $clean['file_type']         = sanitize_text_field((string) $data['file_type']);

		return $clean;
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string>
	 */
	private function get_format(array $data): array
	{
		$map = [
			'attachment_id'     => '%d',
			'original_url'      => '%s',
			'original_url_hash' => '%s',
			'custom_name'       => '%s',
			'custom_path'       => '%s',
			'include_extension' => '%d',
			'file_type'         => '%s',
			'updated_at'        => '%s',
		];

		$formats = [];
		foreach ($data as $key => $v) {
			$formats[] = $map[$key] ?? '%s';
		}
		return $formats;
	}
}
