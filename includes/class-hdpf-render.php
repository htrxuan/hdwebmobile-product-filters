<?php

namespace htrxuan\hdpf;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders the filter form and the small progressive-enhancement script that submits it
 * without a full page reload. The form is a plain <form method="get">: with JavaScript off it
 * still works as an ordinary link-based filter, and with it on the script swaps just the
 * product grid. Either way the request is the same GET request that HDPF_Query validates.
 */
final class HDPF_Render
{

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
        add_shortcode('hdpf_filters', array($this, 'shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'assets'));

        if (HDPF_Admin::get_option('auto_display')) {
            add_action('woocommerce_before_shop_loop', array($this, 'auto_render'), 25);
        }
    }

    public function assets()
    {
        if (!function_exists('is_shop') || !(is_shop() || is_product_category() || is_product_tag() || is_post_type_archive('product'))) {
            return;
        }
        wp_enqueue_style('hdpf-filters', HDPF_PLUGIN_URL . 'assets/css/hdpf-filters.css', array(), HDPF_VERSION);
        wp_enqueue_script('hdpf-filters', HDPF_PLUGIN_URL . 'assets/js/hdpf-filters.js', array(), HDPF_VERSION, true);
    }

    public function shortcode()
    {
        return $this->get_html();
    }

    public function auto_render()
    {
        echo $this->get_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_html() escapes every dynamic value at the point of output.
    }

    private function get_html()
    {
        $enabled = HDPF_Admin::get_option('filters');
        if (empty($enabled)) {
            return '';
        }

        $active = HDPF_Query::get_active_filters();
        // Preserve non-filter query vars (sort order, search, pagination base) as hidden inputs.
        // Anything whose key starts with "hdpf_" is a filter this form re-submits from its own
        // controls, so it is never carried through as a hidden input (that would also echo the
        // raw request value back into the page).
        $preserve = array();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only archive navigation; values are escaped at output and never used in a query.
        foreach (wp_unslash($_GET) as $key => $value) {
            if (is_string($key) && 0 !== strpos($key, 'hdpf_') && 'paged' !== $key) {
                $preserve[$key] = $value;
            }
        }

        ob_start();
        ?>
        <form class="hdpf-filters" method="get" action="<?php echo esc_url($this->base_url()); ?>">
            <?php foreach ($this->flatten($preserve) as $name => $value) : ?>
                <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" />
            <?php endforeach; ?>

            <?php if (in_array('price', $enabled, true)) : ?>
                <div class="hdpf-group hdpf-group--price">
                    <span class="hdpf-group__title"><?php esc_html_e('Price', 'hdwebmobile-product-filters'); ?></span>
                    <label><?php esc_html_e('Min', 'hdwebmobile-product-filters'); ?>
                        <input type="number" name="hdpf_min_price" min="0" step="any" value="<?php echo null !== $active['min_price'] ? esc_attr($active['min_price']) : ''; ?>" />
                    </label>
                    <label><?php esc_html_e('Max', 'hdwebmobile-product-filters'); ?>
                        <input type="number" name="hdpf_max_price" min="0" step="any" value="<?php echo null !== $active['max_price'] ? esc_attr($active['max_price']) : ''; ?>" />
                    </label>
                </div>
            <?php endif; ?>

            <?php if (in_array('category', $enabled, true)) : ?>
                <?php $cats = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => true)); ?>
                <?php if (!is_wp_error($cats) && $cats) : ?>
                    <div class="hdpf-group hdpf-group--category">
                        <span class="hdpf-group__title"><?php esc_html_e('Category', 'hdwebmobile-product-filters'); ?></span>
                        <?php foreach ($cats as $cat) : ?>
                            <label>
                                <input type="checkbox" name="hdpf_cat[]" value="<?php echo esc_attr($cat->slug); ?>" <?php checked(in_array($cat->slug, $active['cats'], true)); ?> />
                                <?php echo esc_html($cat->name); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (in_array('attributes', $enabled, true)) : ?>
                <?php foreach (HDPF_Query::attribute_taxonomies() as $taxonomy) : ?>
                    <?php $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => true)); ?>
                    <?php if (is_wp_error($terms) || !$terms) { continue; } ?>
                    <?php $tax_obj = get_taxonomy($taxonomy); ?>
                    <?php $selected = isset($active['attributes'][$taxonomy]) ? $active['attributes'][$taxonomy] : array(); ?>
                    <div class="hdpf-group hdpf-group--attribute">
                        <span class="hdpf-group__title"><?php echo esc_html($tax_obj ? $tax_obj->labels->singular_name : $taxonomy); ?></span>
                        <?php foreach ($terms as $term) : ?>
                            <label>
                                <input type="checkbox" name="hdpf_<?php echo esc_attr($taxonomy); ?>[]" value="<?php echo esc_attr($term->slug); ?>" <?php checked(in_array($term->slug, $selected, true)); ?> />
                                <?php echo esc_html($term->name); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (in_array('stock', $enabled, true)) : ?>
                <div class="hdpf-group hdpf-group--stock">
                    <span class="hdpf-group__title"><?php esc_html_e('Availability', 'hdwebmobile-product-filters'); ?></span>
                    <?php
                    $stock_labels = array(
                        'instock'      => __('In stock', 'hdwebmobile-product-filters'),
                        'onbackorder'  => __('On backorder', 'hdwebmobile-product-filters'),
                        'outofstock'   => __('Out of stock', 'hdwebmobile-product-filters'),
                    );
                    foreach ($stock_labels as $value => $label) :
                        ?>
                        <label>
                            <input type="checkbox" name="hdpf_stock[]" value="<?php echo esc_attr($value); ?>" <?php checked(in_array($value, $active['stock'], true)); ?> />
                            <?php echo esc_html($label); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (in_array('on_sale', $enabled, true)) : ?>
                <div class="hdpf-group hdpf-group--on-sale">
                    <label>
                        <input type="checkbox" name="hdpf_on_sale" value="1" <?php checked($active['on_sale']); ?> />
                        <?php esc_html_e('On sale only', 'hdwebmobile-product-filters'); ?>
                    </label>
                </div>
            <?php endif; ?>

            <?php if (in_array('rating', $enabled, true)) : ?>
                <div class="hdpf-group hdpf-group--rating">
                    <span class="hdpf-group__title"><?php esc_html_e('Minimum rating', 'hdwebmobile-product-filters'); ?></span>
                    <select name="hdpf_rating">
                        <option value=""><?php esc_html_e('Any', 'hdwebmobile-product-filters'); ?></option>
                        <?php for ($i = 4; $i >= 1; $i--) : ?>
                            <option value="<?php echo (int) $i; ?>" <?php selected($active['min_rating'], $i); ?>>
                                <?php
                                /* translators: %d: number of stars */
                                printf(esc_html__('%d stars & up', 'hdwebmobile-product-filters'), (int) $i);
                                ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="hdpf-actions">
                <button type="submit" class="button hdpf-apply"><?php esc_html_e('Apply filters', 'hdwebmobile-product-filters'); ?></button>
                <?php if (HDPF_Query::has_active_filters()) : ?>
                    <a class="hdpf-clear" href="<?php echo esc_url($this->base_url()); ?>"><?php esc_html_e('Clear', 'hdwebmobile-product-filters'); ?></a>
                <?php endif; ?>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    private function base_url()
    {
        if (function_exists('wc_get_page_permalink') && is_shop()) {
            return wc_get_page_permalink('shop');
        }
        $obj = get_queried_object();
        if ($obj instanceof \WP_Term) {
            $link = get_term_link($obj);
            return is_wp_error($link) ? home_url('/') : $link;
        }
        return home_url(add_query_arg(array(), $GLOBALS['wp']->request ? '/' . $GLOBALS['wp']->request . '/' : '/'));
    }

    /** Flatten a (possibly nested) query-var array into name => value pairs for hidden inputs. */
    private function flatten($arr, $prefix = '')
    {
        $out = array();
        foreach ((array) $arr as $key => $value) {
            $name = '' === $prefix ? (string) $key : $prefix . '[' . $key . ']';
            if (is_array($value)) {
                $out += $this->flatten($value, $name);
            } else {
                $out[$name] = (string) $value;
            }
        }
        return $out;
    }
}
