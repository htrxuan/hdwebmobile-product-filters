<?php

namespace htrxuan\hdpf;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The hub tab: which filters to offer and whether to auto-place the form above the shop loop.
 * Settings are written only through the WordPress Settings API (`options.php`), which performs
 * its own capability check (`manage_options`) and nonce verification -- there is no custom
 * write path, and nothing here ever touches the storefront query.
 */
class HDPF_Admin
{
    const OPTION_KEY = 'hdpf_settings';

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        require_once HDPF_PLUGIN_DIR . 'includes/class-hdpf-hub.php';
        add_filter('hdwebmobile_hub_tabs', array($this, 'register_hub_tabs'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public static function defaults()
    {
        return array(
            'auto_display' => 1,
            'filters'      => array('price', 'category', 'attributes', 'stock', 'on_sale', 'rating'),
        );
    }

    public static function get_options()
    {
        $opts = get_option(self::OPTION_KEY, array());
        if (!is_array($opts)) {
            $opts = array();
        }
        return wp_parse_args($opts, self::defaults());
    }

    public static function get_option($key)
    {
        $opts = self::get_options();
        return isset($opts[$key]) ? $opts[$key] : null;
    }

    public function register_settings()
    {
        register_setting('hdpf_group', self::OPTION_KEY, array(
            'type'              => 'array',
            'sanitize_callback' => array($this, 'sanitize'),
            'default'           => self::defaults(),
        ));
    }

    public function sanitize($input)
    {
        $all = array('price', 'category', 'attributes', 'stock', 'on_sale', 'rating');
        $out = array(
            'auto_display' => !empty($input['auto_display']) ? 1 : 0,
            'filters'      => array(),
        );
        if (!empty($input['filters']) && is_array($input['filters'])) {
            $out['filters'] = array_values(array_intersect($all, array_map('sanitize_key', $input['filters'])));
        }
        return $out;
    }

    public function register_hub_tabs($tabs)
    {
        $tabs['product-filters'] = array(
            'label'  => __('Product Filters', 'hdwebmobile-product-filters'),
            'order'  => 46,
            'render' => array($this, 'render_page'),
        );
        return $tabs;
    }

    public function render_page()
    {
        $opts = self::get_options();
        $all  = array(
            'price'      => __('Price range', 'hdwebmobile-product-filters'),
            'category'   => __('Product category', 'hdwebmobile-product-filters'),
            'attributes' => __('Product attributes (colour, size, &hellip;)', 'hdwebmobile-product-filters'),
            'stock'      => __('Stock status', 'hdwebmobile-product-filters'),
            'on_sale'    => __('On sale', 'hdwebmobile-product-filters'),
            'rating'     => __('Minimum rating', 'hdwebmobile-product-filters'),
        );
        ?>
        <p><?php esc_html_e('Adds a filter panel to your shop and product-category pages. Filters apply through WordPress\'s own query API -- a filter value is never placed into a database query.', 'hdwebmobile-product-filters'); ?></p>
        <p><code>[hdpf_filters]</code> <?php esc_html_e('-- place the filter panel anywhere with this shortcode.', 'hdwebmobile-product-filters'); ?></p>

        <form method="post" action="options.php">
            <?php settings_fields('hdpf_group'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Automatic placement', 'hdwebmobile-product-filters'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[auto_display]" value="1" <?php checked(!empty($opts['auto_display'])); ?> />
                            <?php esc_html_e('Show the filter panel automatically above the shop product grid', 'hdwebmobile-product-filters'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Filters to show', 'hdwebmobile-product-filters'); ?></th>
                    <td>
                        <?php foreach ($all as $key => $label) : ?>
                            <label style="display:block;margin:.25em 0;">
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[filters][]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, (array) $opts['filters'], true)); ?> />
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Save Filter Settings', 'hdwebmobile-product-filters')); ?>
        </form>
        <?php
    }
}
