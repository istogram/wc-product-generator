<?php

/**
 * Uninstall WooCommerce Product Generator
 *
 * @package WC_Product_Generator
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('wc_product_generator_last_processed');
delete_option('wc_product_generator_settings');

// We don't delete any products by default when uninstalling,
// as this could be destructive to a store's data
