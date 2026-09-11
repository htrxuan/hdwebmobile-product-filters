# HDWebmobile Product Filters

Filter the WooCommerce shop by attribute, price, category, stock and rating. Every filter is applied through WordPress's own query API — no filter value is ever placed into a SQL string.

- **WordPress.org:** https://wordpress.org/plugins/hdwebmobile-product-filters/
- **Requires:** WordPress 6.9+, WooCommerce, PHP 7.4+
- **License:** GPLv2 or later

## Description

Adds a filter panel to the shop and product-category pages. Shoppers narrow the grid by price range, category, product attributes, stock status, on-sale, and minimum rating. With JavaScript the grid updates without a full reload; without it the same filters work as ordinary links.

## Why this plugin exists

Shop-filter plugins read input straight from the URL on a public page, which makes them a frequent home for SQL injection. "Product Filter for WooCommerce by WBW" (< 3.1.3) shipped CVE-2025-8416 / CVE-2026-3830 — an unauthenticated filter parameter concatenated into a raw SQL statement.

This plugin closes that class by construction:

* **No SQL is built.** Every filter becomes standard `WP_Query` args (`tax_query`, `meta_query`, `post__in`) applied via the `woocommerce_product_query` hook. The plugin never calls `$wpdb`.
* **Every value is validated first** — categories/attributes matched against registered taxonomies and terms, prices cast with `floatval()`, stock/rating checked against a fixed allow-list.
* **Read-only** — the filters only read the query string to build a query; there is no write path.

## Features

* Filter by price, category, attributes, stock status, on sale, minimum rating
* Choose which filters to show (WooCommerce → HDWebmobile → Product Filters)
* No-reload grid update where JavaScript is available; plain link-based fallback otherwise
* Automatic placement above the shop grid, or the `[hdpf_filters]` shortcode
* Respects the current sort order, search term and pagination
* Classic and block-based shop templates

## Limitations

* Filters the main shop / category / tag / product archive query only
* No per-option result counts in this version
* Attribute filters cover global (`pa_*`) attributes, not per-product custom attributes

## Installation

1. Upload to `/wp-content/plugins/hdwebmobile-product-filters`, or install through the WordPress plugins screen.
2. Activate. WooCommerce must already be installed and active.
3. Go to **WooCommerce > HDWebmobile > Product Filters** to choose which filters to show.

## License

GPLv2 or later — https://www.gnu.org/licenses/gpl-2.0.html
