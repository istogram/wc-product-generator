<?php
/**
 * WooCommerce Product Generator Admin Class
 *
 * @package WC_Product_Generator
 */

// Don't allow direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class for the WooCommerce Product Generator
 */
class WC_Product_Generator_Admin
{
    /**
     * Core instance
     *
     * @var WC_Product_Generator_Core
     */
    private $core;

    /**
     * Plugin settings
     *
     * @var array
     */
    private $settings;

    /**
     * Initialize the admin class
     */
    public function __construct()
    {
        $this->core = new WC_Product_Generator_Core();
        $this->settings = get_option('wc_product_generator_settings', array(
            'batch_size' => 10,
            'default_count' => 100,
            'image_source' => 'picsum',
        ));

        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Register AJAX handlers
        add_action('wp_ajax_generate_wc_products_batch', array($this, 'ajax_generate_batch'));
        add_action('wp_ajax_clear_wc_products', array($this, 'ajax_clear_products'));
        add_action('wp_ajax_save_generator_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_reset_progress_counter', array($this, 'ajax_reset_progress_counter'));
        add_action('wp_ajax_get_last_processed', array($this, 'ajax_get_last_processed'));

        // Show admin notices
        add_action('admin_notices', array($this, 'show_admin_notices'));
    }

    /**
     * Add admin menu item
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'tools.php',
            __('WooCommerce Product Generator', 'wc-product-generator'),
            __('WC Product Generator', 'wc-product-generator'),
            'manage_options',
            'wc-product-generator',
            array($this, 'admin_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook)
    {
        if ('tools_page_wc-product-generator' !== $hook) {
            return;
        }

        // Enqueue admin styles
        wp_enqueue_style(
            'wc-product-generator-admin',
            WC_PRODUCT_GENERATOR_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WC_PRODUCT_GENERATOR_VERSION
        );

        // Enqueue admin scripts
        wp_enqueue_script(
            'wc-product-generator-admin',
            WC_PRODUCT_GENERATOR_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WC_PRODUCT_GENERATOR_VERSION,
            true
        );

        // Localize script with data and nonces
        wp_localize_script(
            'wc-product-generator-admin',
            'wcProductGenerator',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc_product_generator_nonce'),
                'clearNonce' => wp_create_nonce('wc_product_generator_clear_nonce'),
                'settingsNonce' => wp_create_nonce('wc_product_generator_settings_nonce'),
                'settings' => $this->settings,
                'i18n' => array(
                    'confirmClear' => __('Are you sure you want to delete ALL products? This cannot be undone!', 'wc-product-generator'),
                    'generating' => __('Generating products...', 'wc-product-generator'),
                    'generated' => __('Generated products', 'wc-product-generator'),
                    'of' => __('of', 'wc-product-generator'),
                    'complete' => __('Generation complete', 'wc-product-generator'),
                    'stopped' => __('Generation stopped', 'wc-product-generator'),
                ),
            )
        );
    }

    /**
     * Show admin notices
     */
    public function show_admin_notices()
    {
        // Show welcome message on activation
        if (get_transient('wc_product_generator_activated')) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Thank you for installing WooCommerce Product Generator! Go to Tools → WC Product Generator to start generating products.', 'wc-product-generator'); ?></p>
            </div>
            <?php
            delete_transient('wc_product_generator_activated');
        }
    }

    /**
     * Render admin page
     */
    public function admin_page()
    {
        // Get WooCommerce product count
        $product_count = $this->get_product_count();

        // Get last processed value
        $last_processed = get_option('wc_product_generator_last_processed', 0);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('WooCommerce Product Generator', 'wc-product-generator'); ?></h1>
            
            <div id="wc-product-generator-notices"></div>
            
            <?php if ($product_count > 0) : ?>
            <div class="wc-product-generator-card">
                <h2><?php esc_html_e('Current Store Statistics', 'wc-product-generator'); ?></h2>
                
                <div class="wc-stats-grid">
                    <div class="wc-stat-card">
                        <div class="wc-stat-number"><?php echo esc_html($product_count); ?></div>
                        <div class="wc-stat-label"><?php esc_html_e('Products', 'wc-product-generator'); ?></div>
                    </div>
                    
                    <div class="wc-stat-card">
                        <div class="wc-stat-number"><?php echo esc_html(count(get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false)))); ?></div>
                        <div class="wc-stat-label"><?php esc_html_e('Categories', 'wc-product-generator'); ?></div>
                    </div>
                    
                    <div class="wc-stat-card">
                        <div class="wc-stat-number"><?php echo esc_html(count(get_terms(array('taxonomy' => 'product_tag', 'hide_empty' => false)))); ?></div>
                        <div class="wc-stat-label"><?php esc_html_e('Tags', 'wc-product-generator'); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="wc-product-generator-card">
                <h2><?php esc_html_e('Generate Products', 'wc-product-generator'); ?></h2>
                <p><?php esc_html_e('Use this tool to generate WooCommerce products with dummy data. For large numbers of products, consider using WP-CLI.', 'wc-product-generator'); ?></p>
                
                <?php if ($last_processed > 0) : ?>
                <div class="notice notice-warning">
                    <p><?php printf(esc_html__('Previous generation was interrupted. %d products were generated. You can continue from where you left off or reset the progress counter.', 'wc-product-generator'), $last_processed); ?></p>
                </div>
                <?php endif; ?>
                
                <form id="wc-product-generator-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="product_count"><?php esc_html_e('Number of Products', 'wc-product-generator'); ?></label></th>
                            <td>
                                <input type="number" name="product_count" id="product_count" class="regular-text" value="<?php echo esc_attr($this->settings['default_count']); ?>" min="1" max="1000">
                                <p class="description"><?php esc_html_e('For more than 1000 products, please use WP-CLI.', 'wc-product-generator'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="batch_size"><?php esc_html_e('Batch Size', 'wc-product-generator'); ?></label></th>
                            <td>
                                <input type="number" name="batch_size" id="batch_size" class="regular-text" value="<?php echo esc_attr($this->settings['batch_size']); ?>" min="1" max="50">
                                <p class="description"><?php esc_html_e('Products per batch. Lower values prevent timeouts but take longer.', 'wc-product-generator'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php esc_html_e('Starting Point', 'wc-product-generator'); ?></label></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="radio" name="start_point" value="new" checked>
                                        <?php esc_html_e('Start fresh from the beginning', 'wc-product-generator'); ?>
                                    </label>
                                    <br>
                                    <?php if ($last_processed > 0) : ?>
                                    <label>
                                        <input type="radio" name="start_point" value="continue">
                                        <?php printf(esc_html__('Continue from product #%d', 'wc-product-generator'), $last_processed + 1); ?>
                                    </label>
                                    <?php endif; ?>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                    
                    <div id="wc-product-generator-progress" style="display: none;">
                        <h3><?php esc_html_e('Progress:', 'wc-product-generator'); ?> <span id="progress-status">0%</span></h3>
                        <div class="progress-bar" style="width:100%; height:20px; margin-bottom: 20px;">
                            <div class="progress-bar-fill" style="height:20px; width:0%"></div>
                        </div>
                        <p id="progress-text"><?php esc_html_e('Preparing...', 'wc-product-generator'); ?></p>
                    </div>
                    
                    <p class="submit">
                        <button type="button" id="generate-products" class="button button-primary"><?php esc_html_e('Generate Products', 'wc-product-generator'); ?></button>
                        <button type="button" id="stop-generation" class="button" style="display:none;"><?php esc_html_e('Stop Generation', 'wc-product-generator'); ?></button>
                        <?php if ($last_processed > 0) : ?>
                        <button type="button" id="reset-progress" class="button"><?php esc_html_e('Reset Progress Counter', 'wc-product-generator'); ?></button>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
            
            <div class="wc-product-generator-card">
                <h2><?php esc_html_e('Clear Existing Products', 'wc-product-generator'); ?></h2>
                <p><?php esc_html_e('Warning: This will permanently delete all products in your store.', 'wc-product-generator'); ?></p>
                
                <p class="submit">
                    <button type="button" id="clear-products" class="button button-secondary"><?php esc_html_e('Delete All Products', 'wc-product-generator'); ?></button>
                </p>
            </div>
            
            <div class="wc-product-generator-card">
                <h2><?php esc_html_e('WP-CLI Commands', 'wc-product-generator'); ?></h2>
                <p><?php esc_html_e('For large numbers of products or to avoid timeouts, use WP-CLI:', 'wc-product-generator'); ?></p>
                
                <div class="wc-cli-commands">
                    <code># Generate 500 products, clearing existing ones first<br>
                        $ wp wc generate products --count=500 --clear=true<br><br>
                        # Generate 100 products without clearing existing ones<br>
                        $ wp wc generate products --count=100<br><br>
                        # Clear all products<br>
                        $ wp wc clear products
                    </code>
                </div>
            </div>
            
            <div class="wc-product-generator-footer">
                <p>
                    <?php printf(
                        esc_html__('WooCommerce Product Generator v%s | Made with %s by %s', 'wc-product-generator'),
                        WC_PRODUCT_GENERATOR_VERSION,
                        '❤️',
                        '<a href="https://www.istogram.com" target="_blank">istogram</a>'
                    ); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for generating a batch of products
     */
    public function ajax_generate_batch()
    {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wc_product_generator_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        // Get parameters
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 10;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 1;
        $total = isset($_POST['total']) ? intval($_POST['total']) : 100;

        // Set batch size
        $this->core->set_batch_size($batch_size);

        // Generate batch
        $end = min($start + $batch_size - 1, $total);
        $products = array();
        $errors = array();

        for ($i = $start; $i <= $end; $i++) {
            try {
                $product_id = $this->core->create_single_product($i);
                $products[] = $product_id;

                // Save progress
                update_option('wc_product_generator_last_processed', $i);
            } catch (Exception $e) {
                $errors[] = "Error at product #$i: " . $e->getMessage();
            }
        }

        // Calculate progress
        $progress = min(100, round(($end / $total) * 100));
        $is_completed = $end >= $total;

        // Reset progress counter if completed
        if ($is_completed) {
            update_option('wc_product_generator_last_processed', 0);
        }

        // Send response
        wp_send_json_success(array(
            'start' => $start,
            'end' => $end,
            'next' => $end + 1,
            'total' => $total,
            'progress' => $progress,
            'is_completed' => $is_completed,
            'products' => $products,
            'errors' => $errors,
        ));
    }

    /**
     * AJAX handler for clearing products
     */
    public function ajax_clear_products()
    {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wc_product_generator_clear_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        // Clear products
        $result = $this->core->clear_existing_products();

        // Reset progress counter
        update_option('wc_product_generator_last_processed', 0);

        // Send response
        wp_send_json_success('All products have been deleted');
    }

    /**
     * AJAX handler for saving settings
     */
    public function ajax_save_settings()
    {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wc_product_generator_settings_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        // Get settings
        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();

        // Sanitize settings
        $sanitized_settings = array(
            'batch_size' => isset($settings['batch_size']) ? intval($settings['batch_size']) : 10,
            'default_count' => isset($settings['default_count']) ? intval($settings['default_count']) : 100,
            'image_source' => isset($settings['image_source']) ? sanitize_text_field($settings['image_source']) : 'picsum',
        );

        // Save settings
        update_option('wc_product_generator_settings', $sanitized_settings);

        // Send response
        wp_send_json_success('Settings saved successfully');
    }

    /**
     * Get total number of WooCommerce products
     */
    private function get_product_count()
    {
        $count_posts = wp_count_posts('product');
        return $count_posts->publish;
    }

    /**
     * AJAX handler for resetting the progress counter
     */
    public function ajax_reset_progress_counter()
    {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wc_product_generator_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        // Reset the progress counter
        update_option('wc_product_generator_last_processed', 0);

        // Send response
        wp_send_json_success('Progress counter has been reset');
    }

    /**
     * AJAX handler for getting the last processed count
     */
    public function ajax_get_last_processed()
    {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wc_product_generator_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        // Get the last processed count
        $last_processed = get_option('wc_product_generator_last_processed', 0);

        // Send response
        wp_send_json_success($last_processed);
    }
}
