<?php
/**
 * Admin Settings Page — WooCommerce → SKU Sync Settings
 *
 * @package WooSkuSync
 */

defined( 'ABSPATH' ) || exit;

/* ───────────────────────────────────────────────
   Render callback (registered in woo-sku-sync.php)
─────────────────────────────────────────────── */

function sku_sync_render_settings(): void {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'woo-sku-sync' ) );
	}

	// Handle form submission.
	if ( isset( $_POST['sku_sync_settings_nonce'] ) ) {
		sku_sync_handle_settings_save();
	}

	$csv_url   = get_option( 'sku_sync_csv_url', '' );
	$auto_sync = get_option( 'sku_sync_auto_sync', '0' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'SKU Price Sync — Settings', 'woo-sku-sync' ); ?></h1>

		<?php settings_errors( 'sku_sync_settings' ); ?>

		<form method="post" action="">
			<?php wp_nonce_field( 'sku_sync_save_settings', 'sku_sync_settings_nonce' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="sku_sync_csv_url">
							<?php esc_html_e( 'Google Sheets CSV URL', 'woo-sku-sync' ); ?>
						</label>
					</th>
					<td>
						<input
							type="url"
							id="sku_sync_csv_url"
							name="sku_sync_csv_url"
							value="<?php echo esc_attr( $csv_url ); ?>"
							class="regular-text"
							placeholder="https://docs.google.com/spreadsheets/d/…/export?format=csv"
						/>
						<p class="description">
							<?php esc_html_e( 'Paste the CSV export URL from your Google Sheet. Must end with format=csv.', 'woo-sku-sync' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Auto Sync (every 3 days)', 'woo-sku-sync' ); ?>
					</th>
					<td>
						<label>
							<input
								type="checkbox"
								name="sku_sync_auto_sync"
								value="1"
								<?php checked( '1', $auto_sync ); ?>
							/>
							<?php esc_html_e( 'Enable automatic sync every 3 days', 'woo-sku-sync' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save Settings', 'woo-sku-sync' ) ); ?>
		</form>

		<hr />
		<h2><?php esc_html_e( 'Google Sheets Setup Guide', 'woo-sku-sync' ); ?></h2>
		<ol>
			<li><?php esc_html_e( 'Create a Google Sheet with columns: sku, regular_price, sale_price, stock', 'woo-sku-sync' ); ?></li>
			<li><?php esc_html_e( 'Go to File → Share → Anyone with the link (Viewer).', 'woo-sku-sync' ); ?></li>
			<li>
				<?php esc_html_e( 'Use the CSV export URL:', 'woo-sku-sync' ); ?>
				<code>https://docs.google.com/spreadsheets/d/SPREADSHEET_ID/export?format=csv</code>
			</li>
			<li><?php esc_html_e( 'Ensure SKUs are unique, prices are numeric, and column names are lowercase.', 'woo-sku-sync' ); ?></li>
		</ol>
	</div>
	<?php
}

/* ───────────────────────────────────────────────
   Form handler
─────────────────────────────────────────────── */

function sku_sync_handle_settings_save(): void {
	if ( ! check_admin_referer( 'sku_sync_save_settings', 'sku_sync_settings_nonce' ) ) {
		add_settings_error( 'sku_sync_settings', 'nonce_failed', __( 'Security check failed. Please try again.', 'woo-sku-sync' ), 'error' );
		return;
	}

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	// --- CSV URL ---
	$raw_url = isset( $_POST['sku_sync_csv_url'] ) ? sanitize_url( wp_unslash( $_POST['sku_sync_csv_url'] ) ) : '';

	if ( ! empty( $raw_url ) ) {
		if ( ! filter_var( $raw_url, FILTER_VALIDATE_URL ) ) {
			add_settings_error( 'sku_sync_settings', 'invalid_url', __( 'The CSV URL is not a valid URL.', 'woo-sku-sync' ), 'error' );
			return;
		}

		// Quick HTTP check.
		$test = wp_remote_head( $raw_url, [ 'timeout' => 10 ] );
		if ( is_wp_error( $test ) ) {
			add_settings_error(
				'sku_sync_settings',
				'url_unreachable',
				sprintf(
					/* translators: %s: error message */
					__( 'Could not reach the CSV URL: %s', 'woo-sku-sync' ),
					$test->get_error_message()
				),
				'error'
			);
			return;
		}

		$code = wp_remote_retrieve_response_code( $test );
		if ( (int) $code >= 400 ) {
			add_settings_error(
				'sku_sync_settings',
				'url_bad_status',
				sprintf(
					/* translators: %d: HTTP status */
					__( 'CSV URL returned HTTP %d. Please check the URL and Sheet permissions.', 'woo-sku-sync' ),
					$code
				),
				'error'
			);
			return;
		}
	}

	update_option( 'sku_sync_csv_url', $raw_url );

	// --- Auto sync toggle ---
	$auto_sync = isset( $_POST['sku_sync_auto_sync'] ) && '1' === $_POST['sku_sync_auto_sync'] ? '1' : '0';
	$previous  = get_option( 'sku_sync_auto_sync', '0' );
	update_option( 'sku_sync_auto_sync', $auto_sync );

	// Immediately reconcile the scheduled action.
	if ( $auto_sync !== $previous ) {
		sku_sync_maybe_schedule_auto_sync();
	}

	add_settings_error( 'sku_sync_settings', 'saved', __( 'Settings saved.', 'woo-sku-sync' ), 'updated' );
}
