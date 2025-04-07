<?php

/**
 * WooCommerce Product Generator CLI Commands
 *
 * @package WC_Product_Generator
 */

// Don't allow direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP-CLI command for generating WooCommerce products
 */
class WC_Product_Generator_CLI_Command
{
    /**
     * Generates WooCommerce products
     *
     * ## OPTIONS
     *
     * [--count=<number>]
     * : Number of products to generate
     * ---
     * default: 100
     * ---
     *
     * [--clear]
     * : Clear existing products before generation
     * ---
     * default: false
     * ---
     *
     * [--categories=<number>]
     * : Number of categories to create
     * ---
     * default: 10
     * ---
     *
     * [--batch=<number>]
     * : Products per batch
     * ---
     * default: 20
     * ---
     *
     * ## EXAMPLES
     *
     *     # Generate 500 products, clearing existing ones first
     *     $ wp wc generate products --count=500 --clear=true
     *
     *     # Generate 100 products without clearing existing ones
     *     $ wp wc generate products --count=100
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function products($args, $assoc_args)
    {
        // Parse arguments
        $count = isset($assoc_args['count']) ? intval($assoc_args['count']) : 100;
        $clear = isset($assoc_args['clear']) && $assoc_args['clear'] === 'true';
        $categories = isset($assoc_args['categories']) ? intval($assoc_args['categories']) : 10;
        $batch_size = isset($assoc_args['batch']) ? intval($assoc_args['batch']) : 20;

        // Create generator instance
        $generator = new WC_Product_Generator_Core();
        $generator->set_batch_size($batch_size);

        // Clear existing products if requested
        if ($clear) {
            WP_CLI::log('Clearing existing products...');
            $generator->clear_existing_products();
            WP_CLI::success('All existing products have been deleted');
        }

        // Get current product count
        $count_posts = wp_count_posts('product');
        $current_count = $count_posts->publish;
        WP_CLI::log("Current product count: {$current_count}");

        // Generate products
        WP_CLI::log("Starting product generation. Creating {$count} products...");

        // Create progress bar
        $progress = \WP_CLI\Utils\make_progress_bar('Generating products', $count);

        $success_count = 0;
        $error_count = 0;

        for ($i = 1; $i <= $count; $i++) {
            try {
                // Create product
                $product_id = $generator->create_single_product($i);

                if ($product_id) {
                    $success_count++;
                }

                // Update progress bar
                $progress->tick();

                // Optional: Sleep between batches to prevent server overload
                if ($i % $batch_size === 0) {
                    WP_CLI::log("Completed batch ending with product #{$i}");

                    // Uncomment if you need a pause between batches
                    // usleep(500000); // 0.5 second pause
                }
            } catch (Exception $e) {
                $error_count++;
                WP_CLI::warning("Error at product #{$i}: " . $e->getMessage());
                // Continue with next product
            }
        }

        $progress->finish();

        // Get updated product count
        $count_posts = wp_count_posts('product');
        $updated_count = $count_posts->publish;
        $added_count = $updated_count - $current_count;

        WP_CLI::success("Generation complete!");
        WP_CLI::log("Successfully generated: {$success_count} products");

        if ($error_count > 0) {
            WP_CLI::warning("Errors encountered: {$error_count}");
        }

        WP_CLI::log("Products before: {$current_count}");
        WP_CLI::log("Products after: {$updated_count}");
        WP_CLI::log("Net products added: {$added_count}");
    }

    /**
     * Clears all WooCommerce products
     *
     * ## EXAMPLES
     *
     *     # Delete all products
     *     $ wp wc clear products
     */
    public function clear($args, $assoc_args)
    {
        // Confirm before clearing
        WP_CLI::confirm("Are you sure you want to delete ALL WooCommerce products? This cannot be undone.");

        // Get current product count
        $count_posts = wp_count_posts('product');
        $current_count = $count_posts->publish;

        if ($current_count == 0) {
            WP_CLI::warning('No products found to delete.');
            return;
        }

        WP_CLI::log("Clearing {$current_count} products...");

        $generator = new WC_Product_Generator_Core();
        $result = $generator->clear_existing_products();

        WP_CLI::success('All products have been deleted.');
    }

    /**
     * Gets product statistics
     *
     * ## EXAMPLES
     *
     *     # Get product stats
     *     $ wp wc get stats
     */
    public function stats($args, $assoc_args)
    {
        // Get product counts
        $count_posts = wp_count_posts('product');
        $published = $count_posts->publish;
        $draft = $count_posts->draft;
        $total = $published + $draft;

        // Get category count
        $category_count = count(get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false)));

        // Get tag count
        $tag_count = count(get_terms(array('taxonomy' => 'product_tag', 'hide_empty' => false)));

        // Output as table
        $items = array(
            array(
                'Metric' => 'Published Products',
                'Count' => $published,
            ),
            array(
                'Metric' => 'Draft Products',
                'Count' => $draft,
            ),
            array(
                'Metric' => 'Total Products',
                'Count' => $total,
            ),
            array(
                'Metric' => 'Product Categories',
                'Count' => $category_count,
            ),
            array(
                'Metric' => 'Product Tags',
                'Count' => $tag_count,
            ),
        );

        WP_CLI\Utils\format_items('table', $items, array('Metric', 'Count'));
    }
}
