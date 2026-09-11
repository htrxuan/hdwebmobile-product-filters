<?php

namespace htrxuan\hdpf;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reads the active shop filters from the query string and applies them to WooCommerce's own
 * product query.
 *
 * CVE-2025-8416 / CVE-2026-3830 in "Product Filter for WooCommerce by WBW" (< 3.1.3): a
 * filter parameter from a public, unauthenticated request was concatenated into a raw SQL
 * statement without escaping or preparation, giving an unauthenticated visitor a SQL
 * injection point on the storefront.
 *
 * This class closes that entire class of bug by construction: it NEVER builds a SQL string
 * and NEVER touches $wpdb. Every filter is turned into standard WP_Query arguments
 * (`tax_query`, `meta_query`, `post__in`) via WooCommerce's `woocommerce_product_query`
 * hook, and WP_Query / WC_Query prepare and escape all of it. On top of that, each value is
 * validated before use -- taxonomy filters are matched against real registered
 * taxonomies/terms, prices are cast with `floatval()`, stock/rating are checked against a
 * fixed allow-list -- so an unrecognised or malformed value is simply dropped, never passed
 * through.
 */
final class HDPF_Query
{

    private static $instance = null;

    /** Fixed allow-list; anything else in `hdpf_stock` is ignored. */
    const STOCK_STATUSES = array('instock', 'onbackorder', 'outofstock');

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('woocommerce_product_query', array($this, 'apply'), 20, 1);
    }

    /**
     * The active filters, already validated. Public so the renderer can show current state
     * and counts without re-parsing the request.
     *
     * @return array{
     *   cats: string[], attributes: array<string,string[]>, min_price: ?float,
     *   max_price: ?float, stock: string[], on_sale: bool, min_rating: int
     * }
     */
    public static function get_active_filters()
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- these are read-only
        // navigational filters on a public archive (exactly like WooCommerce's own price and
        // attribute filter widgets, which also read $_GET with no nonce). Nothing here changes
        // state; every value is validated below and only ever becomes a WP_Query argument.
        $raw = wp_unslash($_GET);
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $cats = array();
        if (!empty($raw['hdpf_cat'])) {
            foreach (self::split_list($raw['hdpf_cat']) as $slug) {
                $slug = sanitize_title($slug);
                if ('' !== $slug && term_exists($slug, 'product_cat')) {
                    $cats[] = $slug;
                }
            }
        }

        $attributes = array();
        foreach (self::attribute_taxonomies() as $taxonomy) {
            $key = 'hdpf_' . $taxonomy; // e.g. hdpf_pa_color
            if (empty($raw[$key])) {
                continue;
            }
            $terms = array();
            foreach (self::split_list($raw[$key]) as $slug) {
                $slug = sanitize_title($slug);
                if ('' !== $slug && term_exists($slug, $taxonomy)) {
                    $terms[] = $slug;
                }
            }
            if (!empty($terms)) {
                $attributes[$taxonomy] = array_values(array_unique($terms));
            }
        }

        $min_price = isset($raw['hdpf_min_price']) && '' !== $raw['hdpf_min_price']
            ? max(0.0, (float) $raw['hdpf_min_price'])
            : null;
        $max_price = isset($raw['hdpf_max_price']) && '' !== $raw['hdpf_max_price']
            ? max(0.0, (float) $raw['hdpf_max_price'])
            : null;
        if (null !== $min_price && null !== $max_price && $min_price > $max_price) {
            list($min_price, $max_price) = array($max_price, $min_price);
        }

        $stock = array();
        if (!empty($raw['hdpf_stock'])) {
            foreach (self::split_list($raw['hdpf_stock']) as $status) {
                $status = sanitize_key($status);
                if (in_array($status, self::STOCK_STATUSES, true)) {
                    $stock[] = $status;
                }
            }
        }

        $on_sale    = !empty($raw['hdpf_on_sale']) && '1' === (string) $raw['hdpf_on_sale'];
        $min_rating = isset($raw['hdpf_rating']) ? (int) $raw['hdpf_rating'] : 0;
        $min_rating = ($min_rating >= 1 && $min_rating <= 5) ? $min_rating : 0;

        return array(
            'cats'       => array_values(array_unique($cats)),
            'attributes' => $attributes,
            'min_price'  => $min_price,
            'max_price'  => $max_price,
            'stock'      => array_values(array_unique($stock)),
            'on_sale'    => $on_sale,
            'min_rating' => $min_rating,
        );
    }

    public static function has_active_filters()
    {
        $f = self::get_active_filters();
        return !empty($f['cats']) || !empty($f['attributes']) || null !== $f['min_price']
            || null !== $f['max_price'] || !empty($f['stock']) || $f['on_sale'] || $f['min_rating'] > 0;
    }

    /**
     * Turn the validated filters into WP_Query args on the shop query. No SQL is built here;
     * every branch only calls $q->set() with arrays that WP_Query itself compiles safely.
     *
     * @param \WP_Query $q
     */
    public function apply($q)
    {
        $f = self::get_active_filters();

        $existing_tax = $q->get('tax_query');
        $tax_query    = is_array($existing_tax) ? $existing_tax : array();
        if (!empty($f['cats'])) {
            $tax_query[] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $f['cats'],
                'operator' => 'IN',
            );
        }
        foreach ($f['attributes'] as $taxonomy => $terms) {
            $tax_query[] = array(
                'taxonomy' => $taxonomy,
                'field'    => 'slug',
                'terms'    => $terms,
                'operator' => 'IN',
            );
        }
        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }
        if (!empty($tax_query)) {
            $q->set('tax_query', $tax_query);
        }

        $existing_meta = $q->get('meta_query');
        $meta_query    = is_array($existing_meta) ? $existing_meta : array();
        if (null !== $f['min_price'] || null !== $f['max_price']) {
            $min = null !== $f['min_price'] ? $f['min_price'] : 0;
            $max = null !== $f['max_price'] ? $f['max_price'] : PHP_INT_MAX;
            $meta_query[] = array(
                'key'     => '_price',
                'value'   => array($min, $max),
                'compare' => 'BETWEEN',
                'type'    => 'NUMERIC',
            );
        }
        if (!empty($f['stock'])) {
            $meta_query[] = array(
                'key'     => '_stock_status',
                'value'   => $f['stock'],
                'compare' => 'IN',
            );
        }
        if ($f['min_rating'] > 0) {
            $meta_query[] = array(
                'key'     => '_wc_average_rating',
                'value'   => $f['min_rating'],
                'compare' => '>=',
                'type'    => 'DECIMAL',
            );
        }
        if (!empty($meta_query)) {
            $q->set('meta_query', $meta_query);
        }

        if ($f['on_sale']) {
            $on_sale_ids   = wc_get_product_ids_on_sale();
            $existing_in   = $q->get('post__in');
            $existing_in   = is_array($existing_in) ? array_filter($existing_in) : array();
            // Intersect with any existing post__in so we never widen another plugin's restriction.
            // array(0) forces an empty result set rather than "no restriction" when nothing is on sale.
            $q->set('post__in', !empty($existing_in)
                ? (array_values(array_intersect($existing_in, $on_sale_ids)) ?: array(0))
                : ($on_sale_ids ?: array(0)));
        }
    }

    /**
     * All registered global product-attribute taxonomies (pa_*). Used both to know which
     * `hdpf_pa_*` query vars are legitimate and to render the attribute filters.
     *
     * @return string[]
     */
    public static function attribute_taxonomies()
    {
        $out = array();
        foreach (wc_get_attribute_taxonomies() as $attr) {
            $tax = wc_attribute_taxonomy_name($attr->attribute_name);
            if (taxonomy_exists($tax)) {
                $out[] = $tax;
            }
        }
        return $out;
    }

    private static function split_list($value)
    {
        if (is_array($value)) {
            return $value;
        }
        return preg_split('/[,\s]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: array();
    }
}
