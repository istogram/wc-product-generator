<?php
/**
 * Plugin Name: WooCommerce Product Generator
 * Plugin URI: https://github.com/istogram/wc-product-generator
 * Description: Generate dummy WooCommerce products with realistic data for testing and development environments.
 * Version: 1.0.0
 * Author: istogram
 * Author URI: https://www.istogram.com
 * Text Domain: wc-product-generator
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0.0
 * WC tested up to: 8.0.0
 * License: MIT
 * License URI: https://mit-license.org/
 */

// Don't allow direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WC_PRODUCT_GENERATOR_VERSION', '1.0.0');
define('WC_PRODUCT_GENERATOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_PRODUCT_GENERATOR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Declare compatibility with High-Performance Order Storage (HPOS)
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

/**
 * Check if WooCommerce is active
 */
function wc_product_generator_check_woocommerce()
{
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'wc_product_generator_woocommerce_missing_notice');
        return false;
    }
    return true;
}

/**
 * Admin notice for missing WooCommerce
 */
function wc_product_generator_woocommerce_missing_notice()
{
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e('WooCommerce Product Generator requires WooCommerce to be installed and activated.', 'wc-product-generator'); ?></p>
    </div>
    <?php
}

/**
 * Initialize the plugin
 */
function wc_product_generator_init()
{
    // Check if WooCommerce is active
    if (!wc_product_generator_check_woocommerce()) {
        return;
    }

    // Load text domain for translations
    load_plugin_textdomain('wc-product-generator', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Include core functionality
    require_once WC_PRODUCT_GENERATOR_PLUGIN_DIR . 'includes/class-wc-product-generator-core.php';

    // Initialize admin interface if not in WP-CLI mode
    if (!defined('WP_CLI')) {
        // Only load admin interface in admin area
        if (is_admin()) {
            require_once WC_PRODUCT_GENERATOR_PLUGIN_DIR . 'includes/class-wc-product-generator-admin.php';
            new WC_Product_Generator_Admin();
        }
    }

    // Initialize WP-CLI commands
    if (defined('WP_CLI') && WP_CLI) {
        require_once WC_PRODUCT_GENERATOR_PLUGIN_DIR . 'includes/class-wc-product-generator-cli.php';
        WP_CLI::add_command('wc generate', 'WC_Product_Generator_CLI_Command');
    }
}
add_action('plugins_loaded', 'wc_product_generator_init');

/**
 * Plugin activation hook
 */
function wc_product_generator_activate()
{
    // Initialize default settings if needed
    if (!get_option('wc_product_generator_settings')) {
        $default_settings = array(
            'batch_size' => 10,
            'default_count' => 100,
            'image_source' => 'picsum',
        );
        update_option('wc_product_generator_settings', $default_settings);
    }

    // Set a transient to trigger the welcome message
    set_transient('wc_product_generator_activated', true, 60);
}
register_activation_hook(__FILE__, 'wc_product_generator_activate');

/**
 * Plugin deactivation hook
 */
function wc_product_generator_deactivate()
{
    // Nothing to do for now
}
register_deactivation_hook(__FILE__, 'wc_product_generator_deactivate');

/**
 * Add settings link on plugin page
 */
function wc_product_generator_settings_link($links)
{
    $settings_link = '<a href="' . admin_url('tools.php?page=wc-product-generator') . '">' . esc_html__('Generator', 'wc-product-generator') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wc_product_generator_settings_link');
