<?php

/**
 * Plugin Name: HDWebmobile Product Filters
 * Plugin URI: https://hdwebmobile.com/plugins/hdwebmobile-product-filters/
 * Description: Filter the shop by attribute, price, category, stock and rating. Every filter is applied through WordPress's own query API -- no filter value is ever put into a SQL string.
 * Version: 1.0.0
 * Author: htrxuan - Han Tran
 * Author URI: https://hdwebmobile.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hdwebmobile-product-filters
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Requires at least: 6.9
 */

namespace htrxuan\hdpf;

if (!defined('ABSPATH')) {
    exit;
}

define('HDPF_VERSION', '1.0.0');
define('HDPF_PLUGIN_FILE', __FILE__);
define('HDPF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HDPF_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once HDPF_PLUGIN_DIR . 'includes/class-hdpf-activator.php';

register_activation_hook(__FILE__, array(HDPF_Activator::class, 'activate'));

add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', HDPF_PLUGIN_FILE, true);
    }
});

add_action('plugins_loaded', function () {
    require_once HDPF_PLUGIN_DIR . 'includes/class-hdpf-core.php';
    HDPF_Core::get_instance();
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $donate_link = '<a href="https://paypal.me/htrxuan/20" target="_blank" rel="noopener noreferrer">' . esc_html__('Donate', 'hdwebmobile-product-filters') . '</a>';
    array_unshift($links, $donate_link);
    return $links;
});
