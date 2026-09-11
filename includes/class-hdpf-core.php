<?php

namespace htrxuan\hdpf;

if (!defined('ABSPATH')) {
    exit;
}

final class HDPF_Core
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
        $this->includes();
        $this->init_hooks();
    }

    private function __clone()
    {
    }

    private function includes()
    {
        require_once HDPF_PLUGIN_DIR . 'includes/class-hdpf-query.php';
        require_once HDPF_PLUGIN_DIR . 'includes/class-hdpf-render.php';
        require_once HDPF_PLUGIN_DIR . 'includes/class-hdpf-admin.php';
    }

    private function init_hooks()
    {
        add_action('admin_notices', array($this, 'render_missing_woocommerce_notice'));

        if (!class_exists('WooCommerce')) {
            return;
        }

        HDPF_Query::get_instance();
        HDPF_Render::get_instance();
        HDPF_Admin::get_instance();
    }

    public function render_missing_woocommerce_notice()
    {
        $screen = get_current_screen();
        if (!$screen || 'plugins' !== $screen->id) {
            return;
        }

        if (!get_transient('hdpf_wc_missing_notice')) {
            return;
        }
        delete_transient('hdpf_wc_missing_notice');
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php esc_html_e('HDWebmobile Product Filters requires WooCommerce to be installed and active. The plugin has been deactivated.', 'hdwebmobile-product-filters'); ?>
            </p>
        </div>
        <?php
    }
}
