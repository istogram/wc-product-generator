<?php

/**
 * Internationalization for WooCommerce Product Generator
 *
 * @package WC_Product_Generator
 */

// Don't allow direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load plugin textdomain.
 */
function wc_product_generator_load_textdomain()
{
    load_plugin_textdomain(
        'wc-product-generator',
        false,
        dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
    );
}
add_action('plugins_loaded', 'wc_product_generator_load_textdomain');
