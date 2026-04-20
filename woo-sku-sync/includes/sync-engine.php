<?php
/**
 * Sync Engine — processes a batch of CSV rows and updates WooCommerce products.
 *
 * @package WooSkuSync
 */

defined( 'ABSPATH' ) || exit;

/* ───────────────────────────────────────────────
   Entry point called by the scheduler
─────────────────────────────────────────────── */

/**
 * Kick off a fresh sync: fetch CSV, split into batches, queue them.
 * Called from the admin "Sync Now" button and the auto-sync recurring job.
 */
function sku_sync_start_sync(): void {

	// --- Lock check ----------------------------------------------------------
	if ( sku_sync_is_locked() ) {
		sku_sync_log( '', __( 'Sync blocked — another sync is already running or queued.', 'woo-sku-sync' ), 'warning' );
		return;
	}

	// --- URL check -----------------------------------------------------------
	$csv_url = get_option( 'sku_sync_csv_url', '' );
	if ( empty( $csv_url ) ) {
		sku_sync_log( '', __( 'Sync aborted — no CSV URL configured.', 'woo-sku-sync' ), 'error' );
		return;
	}

	// --- Hash check (skip if CSV unchanged) ----------------------------------
	$new_hash = sku_sync_get_csv_hash( $csv_url );
	if ( is_wp_error( $new_hash ) ) {
		sku_sync_log( '', $new_hash->get_error_message(), 'error' );
		return;
	}

	$last_hash = get_option( 'sku_sync_last_hash', '' );
	if ( $new_hash === $last_hash ) {
		sku_sync_log( '', __( 'Sync skipped — CSV content unchanged since last sync.', 'woo-sku-sync' ), 'info' );
		update_option( 'sku_sync_last_run', current_time( 'mysql' ) );
		return;
	}

	// --- Fetch & validate CSV ------------------------------------------------
	$rows = sku_sync_fetch_and_parse_csv( $csv_url );
	if ( is_wp_error( $rows ) ) {
		sku_sync_log( '', $rows->get_error_message(), 'error' );
		return;
	}

	// --- Acquire lock & reset stats ------------------------------------------
	update_option( 'sku_sync_status', 'running' );
	update_option( 'sku_sync_last_hash', $new_hash );
	update_option( 'sku_sync_stats', [
		'total'     => count( $rows ),
		'updated'   => 0,
		'skipped'   => 0,
		'errors'    => 0,
		'started'   => microtime( true ),
	] );

	sku_sync_log( '', sprintf(
		/* translators: %d: total rows */
		__( 'Sync started — %d rows to process.', 'woo-sku-sync' ),
		count( $rows )
	), 'info' );

	// --- Split into batches and queue ----------------------------------------
	$batches = array_chunk( $rows, SKU_SYNC_BATCH_SIZE );

	foreach ( $batches as $batch_index => $batch ) {
		// Stagger batches: 0 s, 2 s, 4 s …
		$delay = $batch_index * 2;

		as_schedule_single_action(
			time() + $delay,
			'sku_sync_process_batch',
			[ 'batch' => $batch, 'batch_index' => $batch_index, 'total_batches' => count( $batches ) ],
			'sku-sync'
		);
	}
}

/* ───────────────────────────────────────────────
   Batch processor (called by Action Scheduler)
─────────────────────────────────────────────── */

/**
 * Process a single batch of rows.
 *
 * @param array[] $batch         Rows for this batch.
 * @param int     $batch_index   0-based batch index.
 * @param int     $total_batches Total number of batches in this sync.
 */
function sku_sync_process_batch( array $batch, int $batch_index, int $total_batches ): void {

	// Respect emergency stop.
	$status = get_option( 'sku_sync_status', 'idle' );
	if ( 'stopped' === $status ) {
		return;
	}

	// Performance boosts.
	wp_suspend_cache_invalidation( true );
	wp_defer_term_counting( true );

	$stats   = (array) get_option( 'sku_sync_stats', [] );
	$updated = 0;
	$skipped = 0;
	$errors  = 0;

	foreach ( $batch as $row ) {
		$result = sku_sync_process_row( $row );
		switch ( $result ) {
			case 'updated':
				$updated++;
				break;
			case 'skipped':
				$skipped++;
				break;
			default:
				$errors++;
				break;
		}
	}

	// Restore caches.
	wp_suspend_cache_invalidation( false );
	wp_defer_term_counting( false );
	wc_recount_all_terms();

	// Update cumulative stats atomically.
	$stats['updated'] = ( $stats['updated'] ?? 0 ) + $updated;
	$stats['skipped'] = ( $stats['skipped'] ?? 0 ) + $skipped;
	$stats['errors']  = ( $stats['errors']  ?? 0 ) + $errors;
	update_option( 'sku_sync_stats', $stats );

	// Is this the last batch?
	$last_batch_index = $total_batches - 1;
	if ( $batch_index >= $last_batch_index ) {
		sku_sync_finalise_sync( $stats );
	}
}

/* ───────────────────────────────────────────────
   Per-row processing
─────────────────────────────────────────────── */

/**
 * Update a single product/variation by SKU.
 *
 * @param  array $row  Validated row with keys: sku, regular_price, sale_price, stock.
 * @return string  'updated' | 'skipped' | 'not_found' | 'error'
 */
function sku_sync_process_row( array $row ): string {
	$sku = $row['sku'];

	// Find product/variation by SKU.
	$product_id = wc_get_product_id_by_sku( $sku );
	if ( ! $product_id ) {
		sku_sync_log(
			$sku,
			sprintf(
				/* translators: %s: SKU */
				__( 'SKU "%s" not found on this site — skipped.', 'woo-sku-sync' ),
				$sku
			),
			'warning'
		);
		return 'not_found';
	}

	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		sku_sync_log( $sku, sprintf( __( 'Could not load product object for SKU "%s".', 'woo-sku-sync' ), $sku ), 'error' );
		return 'error';
	}

	$new_regular = $row['regular_price'];
	$new_sale    = $row['sale_price'];  // '' means "clear sale price"
	$new_stock   = $row['stock'];       // null means "don't touch"

	$current_regular = $product->get_regular_price();
	$current_sale    = $product->get_sale_price();
	$changed         = false;

	// Compare regular price.
	if ( wc_format_decimal( $current_regular ) !== wc_format_decimal( $new_regular ) ) {
		$product->set_regular_price( $new_regular );
		$changed = true;
	}

	// Compare sale price (treat '' as clear).
	if ( wc_format_decimal( $current_sale ) !== wc_format_decimal( $new_sale ) ) {
		$product->set_sale_price( $new_sale );
		$changed = true;
	}

	// Update stock if provided.
	if ( null !== $new_stock ) {
		$current_stock = $product->get_stock_quantity();
		if ( (int) $current_stock !== $new_stock ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( $new_stock );
			$changed = true;
		}
	}

	if ( ! $changed ) {
		sku_sync_log(
			$sku,
			sprintf( __( 'SKU "%s" — no price change, skipped.', 'woo-sku-sync' ), $sku ),
			'info'
		);
		return 'skipped';
	}

	// Save.
	try {
		$product->save();
		// Re-calculate price (handles sale_price being active).
		wc_update_product_lookup_tables_column( $product->get_id(), 'min_max_price' );

		sku_sync_log(
			$sku,
			sprintf(
				/* translators: 1: SKU, 2: new regular price, 3: new sale price */
				__( 'SKU "%1$s" updated — regular: %2$s, sale: %3$s.', 'woo-sku-sync' ),
				$sku,
				$new_regular,
				'' !== $new_sale ? $new_sale : __( 'cleared', 'woo-sku-sync' )
			),
			'success'
		);
		return 'updated';

	} catch ( \Exception $e ) {
		sku_sync_log(
			$sku,
			sprintf(
				/* translators: 1: SKU, 2: error message */
				__( 'Error saving SKU "%1$s": %2$s', 'woo-sku-sync' ),
				$sku,
				$e->getMessage()
			),
			'error'
		);
		return 'error';
	}
}

/* ───────────────────────────────────────────────
   Finalise sync
─────────────────────────────────────────────── */

function sku_sync_finalise_sync( array $stats ): void {
	$duration = isset( $stats['started'] ) ? round( microtime( true ) - $stats['started'], 2 ) : 0;

	$stats['duration'] = $duration;
	update_option( 'sku_sync_stats', $stats );
	update_option( 'sku_sync_status', 'idle' );
	update_option( 'sku_sync_last_run', current_time( 'mysql' ) );

	sku_sync_log(
		'',
		sprintf(
			/* translators: 1: updated, 2: skipped, 3: errors, 4: duration */
			__( 'Sync complete — Updated: %1$d | Skipped: %2$d | Errors: %3$d | Duration: %4$ss', 'woo-sku-sync' ),
			$stats['updated'] ?? 0,
			$stats['skipped'] ?? 0,
			$stats['errors']  ?? 0,
			$duration
		),
		'success'
	);
}

/* ───────────────────────────────────────────────
   Lock helpers
─────────────────────────────────────────────── */

/**
 * Return true if a sync is already running or has pending scheduled batches.
 */
function sku_sync_is_locked(): bool {
	$status = get_option( 'sku_sync_status', 'idle' );
	if ( 'running' === $status ) {
		return true;
	}

	// Also check Action Scheduler for any pending batch actions.
	if ( function_exists( 'as_get_scheduled_actions' ) ) {
		$pending = as_get_scheduled_actions( [
			'hook'   => 'sku_sync_process_batch',
			'status' => \ActionScheduler_Store::STATUS_PENDING,
			'group'  => 'sku-sync',
		], 'ids' );

		if ( ! empty( $pending ) ) {
			return true;
		}
	}

	return false;
}
