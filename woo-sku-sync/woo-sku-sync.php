<?php
/**
 * Plugin Name: SKU Price Sync System
 * Plugin URI:  https://github.com/mracuity/woo-sku-sync
 * Description: Syncs WooCommerce product prices from a Google Sheets CSV URL using background processing.
 * Version:     1.0.2
 * Author:      Mr. Acuity
 * License:     GPL-2.0+
 * Text Domain: woo-sku-sync
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'SKU_SYNC_VERSION',     '1.0.0' );
define( 'SKU_SYNC_PLUGIN_FILE', __FILE__ );
define( 'SKU_SYNC_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'SKU_SYNC_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'SKU_SYNC_LOG_TABLE',   'sku_sync_logs' );
define( 'SKU_SYNC_BATCH_SIZE',  50 );

/**
 * Check WooCommerce is active before bootstrapping.
 */
function sku_sync_check_dependencies(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'SKU Price Sync System requires WooCommerce to be active.', 'woo-sku-sync' )
				. '</p></div>';
		} );
		return;
	}
	sku_sync_bootstrap();
}
add_action( 'plugins_loaded', 'sku_sync_check_dependencies' );

/**
 * Bootstrap — load all includes and wire up hooks.
 */
function sku_sync_bootstrap(): void {
	require_once SKU_SYNC_PLUGIN_DIR . 'includes/logger.php';
	require_once SKU_SYNC_PLUGIN_DIR . 'includes/csv-parser.php';
	require_once SKU_SYNC_PLUGIN_DIR . 'includes/sync-engine.php';
	require_once SKU_SYNC_PLUGIN_DIR . 'includes/scheduler.php';
	require_once SKU_SYNC_PLUGIN_DIR . 'admin/settings-page.php';
	require_once SKU_SYNC_PLUGIN_DIR . 'admin/dashboard.php';

	// Admin menu.
	add_action( 'admin_menu', 'sku_sync_admin_menu' );

	// Action Scheduler hooks (registered in scheduler.php).
	sku_sync_register_scheduled_actions();
}

/**
 * Register WooCommerce → SKU Sync admin menu.
 */
function sku_sync_admin_menu(): void {
	add_submenu_page(
		'woocommerce',
		__( 'SKU Sync', 'woo-sku-sync' ),
		__( 'SKU Sync', 'woo-sku-sync' ),
		'manage_woocommerce',
		'sku-sync',
		'sku_sync_render_dashboard'
	);
	add_submenu_page(
		'woocommerce',
		__( 'SKU Sync Settings', 'woo-sku-sync' ),
		__( 'SKU Sync Settings', 'woo-sku-sync' ),
		'manage_woocommerce',
		'sku-sync-settings',
		'sku_sync_render_settings'
	);
}

/* ───────────────────────────────────────────────
   Activation / Deactivation
─────────────────────────────────────────────── */

register_activation_hook( __FILE__, 'sku_sync_activate' );
function sku_sync_activate(): void {
	require_once SKU_SYNC_PLUGIN_DIR . 'includes/logger.php';
	sku_sync_create_log_table();
	// Seed default options.
	add_option( 'sku_sync_csv_url',     '' );
	add_option( 'sku_sync_auto_sync',   '0' );
	add_option( 'sku_sync_status',      'idle' );
	add_option( 'sku_sync_last_run',    '' );
	add_option( 'sku_sync_last_hash',   '' );
	add_option( 'sku_sync_stats',       [] );
}

register_deactivation_hook( __FILE__, 'sku_sync_deactivate' );
function sku_sync_deactivate(): void {
	require_once SKU_SYNC_PLUGIN_DIR . 'includes/scheduler.php';
	sku_sync_cancel_all_scheduled_actions();
	update_option( 'sku_sync_status', 'idle' );
}
