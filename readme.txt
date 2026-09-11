=== HDWebmobile Product Filters ===
Contributors: htrxuan
Donate link: https://paypal.me/htrxuan/20
Tags: woocommerce, product filter, shop filter, layered nav, ajax filter
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Filter the shop by attribute, price, category, stock and rating. Every filter is applied through WordPress's own query API.

== Description ==

HDWebmobile Product Filters adds a filter panel to your shop and product-category pages. Shoppers narrow the product grid by price range, product category, product attributes (colour, size and so on), stock status, on-sale, and minimum rating. With JavaScript enabled the grid updates without a full page reload; with it disabled the same filters still work as ordinary links.

= Why this plugin exists =
Shop-filter plugins take input straight from the URL on a public, unauthenticated page, which makes them a common home for SQL injection. "Product Filter for WooCommerce by WBW" (versions before 3.1.3) shipped CVE-2025-8416 and CVE-2026-3830: a filter parameter from an unauthenticated visitor was concatenated into a raw SQL statement with no escaping or preparation, turning the storefront filter into a database-read primitive.

This plugin closes that entire class of bug by construction:

* **No SQL is ever built.** Every filter is turned into standard `WP_Query` arguments (`tax_query`, `meta_query`, `post__in`) and handed to WooCommerce's own product query through the `woocommerce_product_query` hook. WordPress prepares and escapes all of it. The plugin never calls `$wpdb` and never assembles a query fragment.
* **Every value is validated before use.** Category and attribute filters are matched against real, registered taxonomies and terms; prices are cast with `floatval()` and clamped; stock status and rating are checked against a fixed allow-list. An unrecognised or malformed value is dropped, not passed along.
* **Read-only.** The filters only ever read the query string to build a query. Nothing about them changes site state, so there is no write path to protect.

= Key Features =
* Filter by price range, category, product attributes, stock status, on sale, and minimum rating
* Choose which filters to show, from **WooCommerce > HDWebmobile > Product Filters**
* Grid updates without a page reload when JavaScript is available; degrades to plain link-based filtering when it is not
* Automatic placement above the shop grid, or the `[hdpf_filters]` shortcode anywhere
* Respects the current sort order, search term and pagination
* Works on the classic shop template and block-based shop/archive templates

= Limitations (please read before installing) =
* Filters the main shop / product-category / product-tag / product archive query only; it does not filter arbitrary custom product grids elsewhere on the page
* No filter result counts per option in this version
* Attribute filters cover global product attributes (the `pa_*` taxonomies), not per-product custom attributes

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/hdwebmobile-product-filters` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress. WooCommerce must already be installed and active.
3. Go to **WooCommerce > HDWebmobile > Product Filters** to choose which filters to show.

== How to Use ==

= 1. Pick your filters =
On the Product Filters tab, tick the filters you want (price, category, attributes, stock, on sale, rating) and choose whether the panel is placed automatically above the shop grid.

= 2. Customers filter the shop =
The panel appears on the shop and product-category pages. Selecting options narrows the grid; the "Clear" link removes all filters. The current URL always reflects the active filters, so a filtered view can be bookmarked or shared.

== Screenshots ==

1. The filter panel on the shop page.
2. The Product Filters settings tab under WooCommerce > HDWebmobile.

== Changelog ==

= 1.0.0 =
* Initial release: price / category / attribute / stock / on-sale / rating filters applied entirely through WP_Query, with a no-reload progressive enhancement.
