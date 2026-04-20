# SKU Price Sync System

Sync WooCommerce product prices and stock from a Google Sheets CSV URL using background processing.

---

## Overview

SKU Price Sync System is a WooCommerce plugin that updates product pricing and inventory by matching SKUs from a centralized Google Sheets CSV.

It is designed for multi-site usage and updates only existing products. No new products are created.

---

## Features

* Automated sync from Google Sheets CSV
* Matches by product SKU and variation SKU
* Updates regular price, sale price, and stock
* Background processing to prevent timeouts
* Safe execution with no duplicate sync runs
* Multi-site compatible

---

## CSV Structure

Your Google Sheet must be published as CSV and follow this format:

| sku       | regular_price | sale_price | stock |
| --------- | ------------- | ---------- | ----- |
| ABC123    | 19.99         | 17.99      | 10    |
| VAR-RED-M | 24.50         |            | 5     |

Requirements:

* sku must match an existing WooCommerce product or variation SKU
* regular_price and sale_price must be valid numbers
* leave sale_price empty if not applicable
* stock must be a valid integer
* first row must contain headers

---

## Raw CSV Example

```
sku,regular_price,sale_price,stock
ABC123,19.99,17.99,10
VAR-RED-M,24.50,,5
```

---

## Google Sheets Setup

1. Create a Google Sheet
2. Go to File → Share → Publish to web
3. Select CSV format
4. Copy the URL
5. Add it in plugin settings

---

## Installation

1. Clone or download the repository
   https://github.com/mracuity/woo-sku-sync

2. Upload to:
   /wp-content/plugins/

3. Activate from WordPress Admin → Plugins

---

## Configuration

* Add your Google Sheets CSV URL
* Run sync manually or via scheduler (if enabled)

---

## Sync Behavior

* Matches products using sku

* Supports simple and variable products

* Updates:

  * regular_price
  * sale_price (if provided)
  * stock quantity

* Ignores:

  * missing SKUs
  * invalid rows

---

## Requirements

* WordPress 5.8+
* PHP 7.4+
* WooCommerce 6.0+

---

## Version

1.0.2

---

## Author

Mr. Acuity
https://github.com/mracuity

---

## License

GPL-2.0+

---

## Notes

* Ensure SKU consistency
* Empty sale_price will remove sale pricing
* Test on staging before production
* Background processing improves performance on large catalogs

---

## Future Plans

* Change detection (update only if needed)
* Scheduled auto sync
* Admin logs and monitoring
* CSV validation

---

## Plugin Metadata

Plugin Name: SKU Price Sync System
Plugin URI: https://github.com/mracuity/woo-sku-sync
Description: Syncs WooCommerce product prices from a Google Sheets CSV URL using background processing.
Version: 1.0.2
Author: Mr. Acuity
License: GPL-2.0+
Text Domain: woo-sku-sync
Requires at least: 5.8
Requires PHP: 7.4
WC requires at least: 6.0
