<?php
/**
 * Logger — creates and manages the wp_sku_sync_logs table.
 *
 * @package WooSkuSync
 */

defined( 'ABSPATH' ) || exit;

/* ───────────────────────────────────────────────
   Table creation
─────────────────────────────────────────────── */

function sku_sync_create_log_table(): void {
	global $wpdb;

	$table_name      = $wpdb->prefix . SKU_SYNC_LOG_TABLE;
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
		id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		sku         VARCHAR(100)        NOT NULL DEFAULT '',
		message     TEXT                NOT NULL,
		type        VARCHAR(20)         NOT NULL DEFAULT 'info',
		timestamp   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY type      (type),
		KEY timestamp (timestamp),
		KEY sku       (sku(50))
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

/* ───────────────────────────────────────────────
   Write a log entry
─────────────────────────────────────────────── */

/**
 * Insert a log row.
 *
 * @param string $sku     Product SKU (empty string for system messages).
 * @param string $message Human-readable message.
 * @param string $type    success | warning | error | info
 */
function sku_sync_log( string $sku, string $message, string $type = 'info' ): void {
	global $wpdb;

	$allowed_types = [ 'success', 'warning', 'error', 'info' ];
	if ( ! in_array( $type, $allowed_types, true ) ) {
		$type = 'info';
	}

	$wpdb->insert(
		$wpdb->prefix . SKU_SYNC_LOG_TABLE,
		[
			'sku'       => sanitize_text_field( $sku ),
			'message'   => sanitize_textarea_field( $message ),
			'type'      => $type,
			'timestamp' => current_time( 'mysql' ),
		],
		[ '%s', '%s', '%s', '%s' ]
	);
}

/* ───────────────────────────────────────────────
   Retrieve logs (paginated)
─────────────────────────────────────────────── */

/**
 * Fetch log rows with optional filters.
 *
 * @param int    $page     1-based page number.
 * @param int    $per_page Rows per page.
 * @param string $type     Filter by type ('' = all).
 * @return array{ rows: array, total: int }
 */
function sku_sync_get_logs( int $page = 1, int $per_page = 50, string $type = '' ): array {
	global $wpdb;

	$table  = $wpdb->prefix . SKU_SYNC_LOG_TABLE;
	$offset = ( max( 1, $page ) - 1 ) * $per_page;

	$where  = '';
	$params = [];

	if ( $type ) {
		$where    = 'WHERE type = %s';
		$params[] = $type;
	}

	// Total count.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where}", ...$params ) );

	// Rows.
	$params[] = $per_page;
	$params[] = $offset;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d", ...$params ),
		ARRAY_A
	);

	return [
		'rows'  => $rows ?: [],
		'total' => $total,
	];
}

/* ───────────────────────────────────────────────
   Clear all logs
─────────────────────────────────────────────── */

function sku_sync_clear_logs(): void {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . SKU_SYNC_LOG_TABLE );
}
