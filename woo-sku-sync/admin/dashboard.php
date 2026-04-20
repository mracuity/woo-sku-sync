<?php
/**
 * Admin Dashboard — WooCommerce → SKU Sync
 *
 * @package WooSkuSync
 */

defined( 'ABSPATH' ) || exit;

/* ───────────────────────────────────────────────
   Render callback (registered in woo-sku-sync.php)
─────────────────────────────────────────────── */

function sku_sync_render_dashboard(): void {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'woo-sku-sync' ) );
	}

	// Handle POST actions before output.
	if ( isset( $_POST['sku_sync_action_nonce'] ) ) {
		sku_sync_handle_dashboard_action();
	}

	// Gather data.
	$status    = get_option( 'sku_sync_status', 'idle' );
	$last_run  = get_option( 'sku_sync_last_run', '' );
	$stats     = (array) get_option( 'sku_sync_stats', [] );
	$auto_sync = get_option( 'sku_sync_auto_sync', '0' );
	$next_sync = sku_sync_next_auto_sync_time();

	// Logs pagination.
	$current_page  = isset( $_GET['log_page'] ) ? max( 1, (int) $_GET['log_page'] ) : 1;
	$log_type      = isset( $_GET['log_type'] ) ? sanitize_text_field( wp_unslash( $_GET['log_type'] ) ) : '';
	$per_page      = 30;
	$logs_data     = sku_sync_get_logs( $current_page, $per_page, $log_type );

	// Status badge mapping.
	$badge_class = [
		'idle'    => 'notice-info',
		'running' => 'notice-warning',
		'stopped' => 'notice-error',
	];
	$badge = $badge_class[ $status ] ?? 'notice-info';
	?>
	<div class="wrap sku-sync-dashboard">
		<h1><?php esc_html_e( 'SKU Price Sync — Dashboard', 'woo-sku-sync' ); ?></h1>

		<?php settings_errors( 'sku_sync_dashboard' ); ?>

		<!-- ── Status card ──────────────────────────────────────── -->
		<div class="notice <?php echo esc_attr( $badge ); ?> inline" style="padding:12px 16px;margin-bottom:20px;">
			<strong><?php esc_html_e( 'Sync Status:', 'woo-sku-sync' ); ?></strong>
			<?php echo esc_html( ucfirst( $status ) ); ?>
		</div>

		<!-- ── Stats grid ───────────────────────────────────────── -->
		<div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px;">
			<?php
			$stat_cards = [
				__( 'Last Sync',        'woo-sku-sync' ) => $last_run ? esc_html( get_date_from_gmt( $last_run, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) : '—',
				__( 'Next Auto Sync',   'woo-sku-sync' ) => $next_sync ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_sync ) ) : ( '1' === $auto_sync ? esc_html__( 'Scheduling…', 'woo-sku-sync' ) : esc_html__( 'Disabled', 'woo-sku-sync' ) ),
				__( 'Total Rows',       'woo-sku-sync' ) => esc_html( $stats['total']   ?? '0' ),
				__( 'Updated',          'woo-sku-sync' ) => esc_html( $stats['updated'] ?? '0' ),
				__( 'Skipped',          'woo-sku-sync' ) => esc_html( $stats['skipped'] ?? '0' ),
				__( 'Errors',           'woo-sku-sync' ) => esc_html( $stats['errors']  ?? '0' ),
				__( 'Duration (s)',      'woo-sku-sync' ) => esc_html( isset( $stats['duration'] ) ? number_format_i18n( $stats['duration'], 2 ) : '—' ),
			];
			foreach ( $stat_cards as $label => $value ) :
				?>
				<div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:14px 18px;min-width:130px;text-align:center;box-shadow:0 1px 2px rgba(0,0,0,.07);">
					<div style="font-size:22px;font-weight:700;color:#23282d;"><?php echo wp_kses_post( $value ); ?></div>
					<div style="color:#646970;font-size:12px;margin-top:4px;"><?php echo esc_html( $label ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- ── Action buttons ───────────────────────────────────── -->
		<form method="post" action="" style="margin-bottom:24px;display:flex;gap:10px;flex-wrap:wrap;">
			<?php wp_nonce_field( 'sku_sync_dashboard_action', 'sku_sync_action_nonce' ); ?>

			<button
				type="submit"
				name="sku_sync_do"
				value="sync_now"
				class="button button-primary"
				<?php disabled( 'running', $status ); ?>
			>
				<?php esc_html_e( '▶ Sync Now', 'woo-sku-sync' ); ?>
			</button>

			<button
				type="submit"
				name="sku_sync_do"
				value="emergency_stop"
				class="button"
				style="color:#c00;border-color:#c00;"
				onclick="return confirm('<?php echo esc_js( __( 'Stop all running and scheduled sync jobs?', 'woo-sku-sync' ) ); ?>')"
			>
				<?php esc_html_e( '⏹ Emergency Stop', 'woo-sku-sync' ); ?>
			</button>

			<button
				type="submit"
				name="sku_sync_do"
				value="clear_logs"
				class="button"
				onclick="return confirm('<?php echo esc_js( __( 'Delete all log entries?', 'woo-sku-sync' ) ); ?>')"
			>
				<?php esc_html_e( '🗑 Clear Logs', 'woo-sku-sync' ); ?>
			</button>

			<a href="<?php echo esc_url( admin_url( 'admin.php?page=sku-sync-settings' ) ); ?>" class="button">
				<?php esc_html_e( '⚙ Settings', 'woo-sku-sync' ); ?>
			</a>
		</form>

		<!-- ── Log table ────────────────────────────────────────── -->
		<h2><?php esc_html_e( 'Sync Log', 'woo-sku-sync' ); ?></h2>

		<!-- Filter tabs -->
		<?php
		$filter_url = admin_url( 'admin.php?page=sku-sync' );
		$log_types  = [ '' => __( 'All', 'woo-sku-sync' ), 'success' => __( 'Success', 'woo-sku-sync' ), 'warning' => __( 'Warning', 'woo-sku-sync' ), 'error' => __( 'Error', 'woo-sku-sync' ), 'info' => __( 'Info', 'woo-sku-sync' ) ];
		echo '<ul class="subsubsub" style="margin-bottom:10px;">';
		foreach ( $log_types as $type_key => $type_label ) {
			$active = ( $log_type === $type_key ) ? 'style="font-weight:700;"' : '';
			$url    = $type_key ? add_query_arg( 'log_type', $type_key, $filter_url ) : $filter_url;
			echo '<li>' . wp_kses_post( "<a href='" . esc_url( $url ) . "' {$active}>" . esc_html( $type_label ) . '</a> | </li>' );
		}
		echo '</ul>';
		?>

		<?php if ( empty( $logs_data['rows'] ) ) : ?>
			<p><?php esc_html_e( 'No log entries found.', 'woo-sku-sync' ); ?></p>
		<?php else : ?>
			<table class="widefat fixed striped" style="margin-bottom:16px;">
				<thead>
					<tr>
						<th style="width:50px;"><?php esc_html_e( 'ID', 'woo-sku-sync' ); ?></th>
						<th style="width:120px;"><?php esc_html_e( 'SKU', 'woo-sku-sync' ); ?></th>
						<th><?php esc_html_e( 'Message', 'woo-sku-sync' ); ?></th>
						<th style="width:90px;"><?php esc_html_e( 'Type', 'woo-sku-sync' ); ?></th>
						<th style="width:160px;"><?php esc_html_e( 'Timestamp', 'woo-sku-sync' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs_data['rows'] as $log_row ) :
						$type_colour = [
							'success' => '#46b450',
							'warning' => '#ffb900',
							'error'   => '#dc3232',
							'info'    => '#00a0d2',
						];
						$colour = $type_colour[ $log_row['type'] ] ?? '#00a0d2';
						?>
						<tr>
							<td><?php echo esc_html( $log_row['id'] ); ?></td>
							<td><?php echo esc_html( $log_row['sku'] ?: '—' ); ?></td>
							<td><?php echo esc_html( $log_row['message'] ); ?></td>
							<td>
								<span style="background:<?php echo esc_attr( $colour ); ?>;color:#fff;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;">
									<?php echo esc_html( ucfirst( $log_row['type'] ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $log_row['timestamp'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			// Pagination.
			$total_pages = (int) ceil( $logs_data['total'] / $per_page );
			if ( $total_pages > 1 ) {
				$page_url = add_query_arg( 'log_type', $log_type, $filter_url );
				echo '<div class="tablenav">';
				echo '<div class="tablenav-pages">';
				echo paginate_links( [
					'base'      => add_query_arg( 'log_page', '%#%', $page_url ),
					'format'    => '',
					'current'   => $current_page,
					'total'     => $total_pages,
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
				] );
				echo '</div></div>';
			}
			?>
		<?php endif; ?>
	</div>

	<style>
	.sku-sync-dashboard .widefat td,
	.sku-sync-dashboard .widefat th { vertical-align: middle; }
	</style>
	<?php
}

/* ───────────────────────────────────────────────
   Dashboard action handler
─────────────────────────────────────────────── */

function sku_sync_handle_dashboard_action(): void {
	if ( ! check_admin_referer( 'sku_sync_dashboard_action', 'sku_sync_action_nonce' ) ) {
		add_settings_error( 'sku_sync_dashboard', 'nonce', __( 'Security check failed.', 'woo-sku-sync' ), 'error' );
		return;
	}

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$action = isset( $_POST['sku_sync_do'] ) ? sanitize_key( $_POST['sku_sync_do'] ) : '';

	switch ( $action ) {

		case 'sync_now':
			if ( sku_sync_is_locked() ) {
				add_settings_error( 'sku_sync_dashboard', 'locked', __( 'Sync is already running. Please wait or use Emergency Stop.', 'woo-sku-sync' ), 'warning' );
			} else {
				// Dispatch as a background action so the page returns quickly.
				as_enqueue_async_action( 'sku_sync_start_sync_trigger', [], 'sku-sync' );
				add_action( 'sku_sync_start_sync_trigger', 'sku_sync_start_sync' );
				// Immediately run inline for small installs (optional — AS will also run it async).
				sku_sync_start_sync();
				add_settings_error( 'sku_sync_dashboard', 'sync_queued', __( 'Sync queued. Refresh in a moment to see progress.', 'woo-sku-sync' ), 'updated' );
			}
			break;

		case 'emergency_stop':
			sku_sync_cancel_all_scheduled_actions();
			add_settings_error( 'sku_sync_dashboard', 'stopped', __( 'Emergency stop executed. All pending sync jobs cancelled.', 'woo-sku-sync' ), 'updated' );
			break;

		case 'clear_logs':
			sku_sync_clear_logs();
			add_settings_error( 'sku_sync_dashboard', 'logs_cleared', __( 'All log entries deleted.', 'woo-sku-sync' ), 'updated' );
			break;
	}
}
