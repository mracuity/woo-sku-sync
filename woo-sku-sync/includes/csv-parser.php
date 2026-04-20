<?php
/**
 * CSV Parser — fetches, validates, and normalises the Google Sheets CSV.
 *
 * @package WooSkuSync
 */

defined( 'ABSPATH' ) || exit;

/* ───────────────────────────────────────────────
   Public API
─────────────────────────────────────────────── */

/**
 * Fetch and validate the CSV.  Returns parsed rows or WP_Error.
 *
 * @param  string $url Google Sheets CSV export URL.
 * @return array[]|WP_Error   Array of associative row arrays on success.
 */
function sku_sync_fetch_and_parse_csv( string $url ) {
	// 1. Fetch.
	$raw = sku_sync_fetch_csv( $url );
	if ( is_wp_error( $raw ) ) {
		return $raw;
	}

	// 2. Parse lines → rows.
	$rows = sku_sync_parse_csv_string( $raw );
	if ( is_wp_error( $rows ) ) {
		return $rows;
	}

	// 3. Validate.
	$validated = sku_sync_validate_rows( $rows );
	if ( is_wp_error( $validated ) ) {
		return $validated;
	}

	return $validated;
}

/**
 * Return MD5 hash of remote CSV content (for change detection).
 *
 * @param  string $url CSV URL.
 * @return string|WP_Error  Hash string or WP_Error on failure.
 */
function sku_sync_get_csv_hash( string $url ) {
	$raw = sku_sync_fetch_csv( $url );
	if ( is_wp_error( $raw ) ) {
		return $raw;
	}
	return md5( $raw );
}

/* ───────────────────────────────────────────────
   Internals
─────────────────────────────────────────────── */

/**
 * HTTP-fetch the CSV, return raw string or WP_Error.
 */
function sku_sync_fetch_csv( string $url ) {
	$response = wp_remote_get(
		$url,
		[
			'timeout'    => 30,
			'user-agent' => 'WooSkuSync/' . SKU_SYNC_VERSION . '; ' . get_bloginfo( 'url' ),
		]
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error(
			'fetch_failed',
			sprintf(
				/* translators: %s: error message */
				__( 'Failed to fetch CSV: %s', 'woo-sku-sync' ),
				$response->get_error_message()
			)
		);
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== (int) $code ) {
		return new WP_Error(
			'bad_response',
			sprintf(
				/* translators: %d: HTTP status code */
				__( 'CSV URL returned HTTP %d.', 'woo-sku-sync' ),
				$code
			)
		);
	}

	$body = wp_remote_retrieve_body( $response );
	if ( empty( trim( $body ) ) ) {
		return new WP_Error( 'empty_csv', __( 'CSV response body is empty.', 'woo-sku-sync' ) );
	}

	return $body;
}

/**
 * Convert raw CSV string into an array of associative arrays.
 *
 * @param  string $raw Raw CSV text.
 * @return array[]|WP_Error
 */
function sku_sync_parse_csv_string( string $raw ) {
	// Normalise line endings.
	$raw   = str_replace( "\r\n", "\n", $raw );
	$raw   = str_replace( "\r", "\n", $raw );
	$lines = explode( "\n", trim( $raw ) );

	if ( count( $lines ) < 2 ) {
		return new WP_Error( 'too_few_rows', __( 'CSV must have a header row and at least one data row.', 'woo-sku-sync' ) );
	}

	// Parse header (first line).
	$header = sku_sync_parse_csv_line( array_shift( $lines ) );
	$header = array_map( 'strtolower', array_map( 'trim', $header ) );

	$rows = [];
	foreach ( $lines as $line_number => $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue; // Skip empty lines.
		}

		$cols = sku_sync_parse_csv_line( $line );

		// Pad / trim to header length.
		$cols = array_slice( array_pad( $cols, count( $header ), '' ), 0, count( $header ) );

		$row = array_combine( $header, $cols );
		if ( false === $row ) {
			continue;
		}

		$rows[] = $row;
	}

	return $rows;
}

/**
 * Parse a single CSV line respecting quoted fields.
 *
 * @param  string $line CSV line.
 * @return string[]
 */
function sku_sync_parse_csv_line( string $line ): array {
	// str_getcsv handles quoted fields & escaped quotes correctly.
	return str_getcsv( $line, ',', '"', '\\' );
}

/**
 * Validate rows: required columns, no duplicate SKUs, numeric prices.
 *
 * @param  array[] $rows Parsed rows.
 * @return array[]|WP_Error  Cleaned rows or error.
 */
function sku_sync_validate_rows( array $rows ) {
	if ( empty( $rows ) ) {
		return new WP_Error( 'no_data_rows', __( 'CSV contains no data rows after the header.', 'woo-sku-sync' ) );
	}

	$first_row  = reset( $rows );
	$columns    = array_keys( $first_row );
	$required   = [ 'sku', 'regular_price' ];

	foreach ( $required as $col ) {
		if ( ! in_array( $col, $columns, true ) ) {
			return new WP_Error(
				'missing_column',
				sprintf(
					/* translators: %s: column name */
					__( 'Required CSV column "%s" is missing.', 'woo-sku-sync' ),
					$col
				)
			);
		}
	}

	$seen_skus = [];
	$clean     = [];

	foreach ( $rows as $index => $row ) {
		$sku = trim( $row['sku'] ?? '' );

		// Skip rows with empty SKU.
		if ( '' === $sku ) {
			continue;
		}

		// Duplicate SKU check.
		if ( isset( $seen_skus[ $sku ] ) ) {
			return new WP_Error(
				'duplicate_sku',
				sprintf(
					/* translators: %s: SKU value */
					__( 'Duplicate SKU "%s" found in CSV. Each SKU must appear only once.', 'woo-sku-sync' ),
					$sku
				)
			);
		}
		$seen_skus[ $sku ] = true;

		// Validate regular_price.
		$regular_price = trim( $row['regular_price'] ?? '' );
		if ( '' === $regular_price || ! is_numeric( $regular_price ) || (float) $regular_price < 0 ) {
			return new WP_Error(
				'invalid_price',
				sprintf(
					/* translators: 1: SKU, 2: price value */
					__( 'Invalid regular_price "%2$s" for SKU "%1$s". Must be a non-negative number.', 'woo-sku-sync' ),
					$sku,
					$regular_price
				)
			);
		}

		// Validate sale_price if present.
		$sale_price = trim( $row['sale_price'] ?? '' );
		if ( '' !== $sale_price && ( ! is_numeric( $sale_price ) || (float) $sale_price < 0 ) ) {
			return new WP_Error(
				'invalid_sale_price',
				sprintf(
					/* translators: 1: SKU, 2: price value */
					__( 'Invalid sale_price "%2$s" for SKU "%1$s". Must be a non-negative number or empty.', 'woo-sku-sync' ),
					$sku,
					$sale_price
				)
			);
		}

		// Validate stock if present.
		$stock = trim( $row['stock'] ?? '' );
		if ( '' !== $stock && ( ! is_numeric( $stock ) || (int) $stock < 0 ) ) {
			return new WP_Error(
				'invalid_stock',
				sprintf(
					/* translators: 1: SKU, 2: stock value */
					__( 'Invalid stock "%2$s" for SKU "%1$s". Must be a non-negative integer or empty.', 'woo-sku-sync' ),
					$sku,
					$stock
				)
			);
		}

		$clean[] = [
			'sku'           => $sku,
			'regular_price' => wc_format_decimal( $regular_price ),
			'sale_price'    => '' !== $sale_price ? wc_format_decimal( $sale_price ) : '',
			'stock'         => '' !== $stock ? (int) $stock : null,
		];
	}

	if ( empty( $clean ) ) {
		return new WP_Error( 'all_rows_skipped', __( 'No valid data rows remain after validation.', 'woo-sku-sync' ) );
	}

	return $clean;
}
