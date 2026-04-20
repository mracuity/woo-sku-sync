<?php
/**
 * Scheduler — registers Action Scheduler hooks and manages auto-sync.
 *
 * @package WooSkuSync
 */

defined( 'ABSPATH' ) || exit;

// Auto sync interval: 3 days in seconds.
define( 'SKU_SYNC_AUTO_INTERVAL', 3 * DAY_IN_SECONDS );

/* ───────────────────────────────────────────────
   Register AS callbacks
─────────────────────────────────────────────── */

function sku_sync_register_scheduled_actions(): void {
	// Single-batch processing (one action per batch).
	add_action( 'sku_sync_process_batch', 'sku_sync_as_process_batch_handler', 10, 3 );

	// Recurring auto sync trigger.
	add_action( 'sku_sync_auto_sync_trigger', 'sku_sync_start_sync' );

	// Boot auto-sync scheduling on init.
	add_action( 'init', 'sku_sync_maybe_schedule_auto_sync' );
}

/**
 * Action Scheduler callback — unwrap args and delegate.
 *
 * AS passes the args array as positional parameters when the hook has multiple args.
 */
function sku_sync_as_process_batch_handler( array $batch, int $batch_index, int $total_batches ): void {
	sku_sync_process_batch( $batch, $batch_index, $total_batches );
}

/* ───────────────────────────────────────────────
   Auto-sync management
─────────────────────────────────────────────── */

/**
 * Called on `init` — ensures the recurring auto-sync action exists iff enabled.
 */
function sku_sync_maybe_schedule_auto_sync(): void {
	if ( ! function_exists( 'as_next_scheduled_action' ) ) {
		return;
	}

	$enabled = get_option( 'sku_sync_auto_sync', '0' );

	$is_scheduled = (bool) as_next_scheduled_action( 'sku_sync_auto_sync_trigger', [], 'sku-sync' );

	if ( '1' === $enabled && ! $is_scheduled ) {
		as_schedule_recurring_action(
			time() + SKU_SYNC_AUTO_INTERVAL,
			SKU_SYNC_AUTO_INTERVAL,
			'sku_sync_auto_sync_trigger',
			[],
			'sku-sync'
		);
	} elseif ( '0' === $enabled && $is_scheduled ) {
		as_unschedule_all_actions( 'sku_sync_auto_sync_trigger', [], 'sku-sync' );
	}
}

/**
 * Return the next scheduled auto-sync timestamp, or null if not scheduled.
 */
function sku_sync_next_auto_sync_time(): ?int {
	if ( ! function_exists( 'as_next_scheduled_action' ) ) {
		return null;
	}
	$next = as_next_scheduled_action( 'sku_sync_auto_sync_trigger', [], 'sku-sync' );
	return $next ?: null;
}

/* ───────────────────────────────────────────────
   Emergency stop / cancel all
─────────────────────────────────────────────── */

/**
 * Cancel ALL pending batch and auto-sync actions and set status to stopped.
 */
function sku_sync_cancel_all_scheduled_actions(): void {
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( 'sku_sync_process_batch',     [], 'sku-sync' );
		as_unschedule_all_actions( 'sku_sync_auto_sync_trigger', [], 'sku-sync' );
	}
	update_option( 'sku_sync_status', 'stopped' );
	sku_sync_log( '', __( 'Emergency stop triggered — all pending sync actions cancelled.', 'woo-sku-sync' ), 'warning' );
}
