<?php

/**
 * Core functionality for WooCommerce Product Generator
 */

// Don't allow direct access
if (!defined('ABSPATH')) {
    exit;
}

class WC_Product_Generator_Core
{
    private $batch_size = 20;
    private $categories = ['Electronics', 'Clothing', 'Home Decor', 'Toys', 'Books', 'Sports', 'Food', 'Beauty', 'Jewelry', 'Office'];
    private $tags = ['Featured', 'Sale', 'New', 'Popular', 'Best Seller', 'Limited', 'Organic', 'Handmade', 'Imported', 'Local'];
    private $category_ids = [];
    private $tag_ids = [];

    public function __construct()
    {
        // Make sure WooCommerce is active
        if (!function_exists('WC')) {
            return;
        }

        // Increase memory limit for large operations
        ini_set('memory_limit', '512M');
    }

    /**
     * Set batch size for generation
     */
    public function set_batch_size($size)
    {
        $this->batch_size = max(1, intval($size));
    }

    /**
     * Clear all existing products from the store
     */
    public function clear_existing_products()
    {
        // Get all products using the WooCommerce API
        $products = wc_get_products([
            'limit' => -1,
            'status' => ['publish', 'draft', 'pending', 'private', 'future', 'trash']
        ]);

        if (empty($products)) {
            return true;
        }

        // Track progress for large stores
        $total = count($products);
        $deleted = 0;

        // Delete products one by one using the WooCommerce API
        foreach ($products as $product) {
            $product->delete(true); // true = force delete
            $deleted++;

            // Free up memory for large datasets
            if ($deleted % 100 === 0) {
                WC_Cache_Helper::get_transient_version('product', true);
                wp_cache_flush();
            }
        }

        // Clear any transients/cache
        wc_delete_product_transients();

        return true;
    }

    /**
     * Create a single product
     */
    public function create_single_product($index)
    {
        // Make sure categories and tags are initialized
        if (empty($this->category_ids)) {
            $this->category_ids = $this->create_product_categories();
        }

        if (empty($this->tag_ids)) {
            $this->tag_ids = $this->create_product_tags();
        }

        // Product data
        $name = $this->generate_product_name($index);
        $description = $this->generate_lorem_ipsum(5, 10);
        $short_description = $this->generate_lorem_ipsum(1, 2);
        $price = $this->generate_price();
        $sale_price = (rand(0, 10) > 7) ? $price * 0.8 : '';

        // Generate a unique SKU with timestamp and random string to avoid duplicates
        $random_string = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 3);
        $timestamp = time();
        $sku = 'PROD-' . $random_string . '-' . $timestamp . '-' . str_pad($index, 4, '0', STR_PAD_LEFT);

        try {
            // Create the product
            $product = new WC_Product_Simple();
            $product->set_name($name);
            $product->set_status('publish');
            $product->set_description($description);
            $product->set_short_description($short_description);
            $product->set_sku($sku);
            $product->set_regular_price($price);

            if (!empty($sale_price)) {
                $product->set_sale_price($sale_price);
            }

            // Set product category - assign 1-3 random categories
            $cat_count = rand(1, 3);
            $selected_cats = array_rand($this->category_ids, min($cat_count, count($this->category_ids)));
            if (!is_array($selected_cats)) {
                $selected_cats = [$selected_cats];
            }
            $selected_cat_ids = array_map(function ($key) {
                return $this->category_ids[$key];
            }, $selected_cats);
            $product->set_category_ids($selected_cat_ids);

            // Set product tags - assign 0-5 random tags
            if (rand(0, 10) > 3) {
                $tag_count = rand(1, 5);
                $selected_tags = array_rand($this->tag_ids, min($tag_count, count($this->tag_ids)));
                if (!is_array($selected_tags)) {
                    $selected_tags = [$selected_tags];
                }
                $selected_tag_ids = array_map(function ($key) {
                    return $this->tag_ids[$key];
                }, $selected_tags);
                $product->set_tag_ids($selected_tag_ids);
            }

            // Set stock status
            $stock_status = (rand(0, 10) > 2) ? 'instock' : 'outofstock';
            $product->set_stock_status($stock_status);

            if ($stock_status === 'instock') {
                $product->set_manage_stock(true);
                $product->set_stock_quantity(rand(5, 100));
            }

            // Set product attributes
            $weight = number_format(rand(1, 100) / 10, 1);
            $product->set_weight($weight);

            // Set dimensions randomly
            $product->set_length(rand(1, 30));
            $product->set_width(rand(1, 20));
            $product->set_height(rand(1, 15));

            // Save the product
            $product_id = $product->save();

            // Set a featured image
            $this->set_featured_image($product_id);

            // Add product gallery (0-4 additional images)
            if (rand(0, 10) > 3) {
                $gallery_count = rand(1, 4);
                $this->set_product_gallery($product_id, $gallery_count);
            }

            return $product_id;

        } catch (Exception $e) {
            // Log the error and rethrow
            error_log('Error creating product #' . $index . ': ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create product categories
     */
    private function create_product_categories()
    {
        $category_ids = [];

        foreach ($this->categories as $category) {
            $term = term_exists($category, 'product_cat');

            if (!$term) {
                $term = wp_insert_term($category, 'product_cat');
            }

            if (!is_wp_error($term)) {
                $category_ids[] = $term['term_id'];
            }
        }

        return $category_ids;
    }

    /**
     * Create product tags
     */
    private function create_product_tags()
    {
        $tag_ids = [];

        foreach ($this->tags as $tag) {
            $term = term_exists($tag, 'product_tag');

            if (!$term) {
                $term = wp_insert_term($tag, 'product_tag');
            }

            if (!is_wp_error($term)) {
                $tag_ids[] = $term['term_id'];
            }
        }

        return $tag_ids;
    }

    /**
     * Generate a product name
     */
    private function generate_product_name($index)
    {
        $adjectives = ['Premium', 'Deluxe', 'Elegant', 'Classic', 'Modern', 'Ultimate', 'Pro', 'Elite', 'Essential', 'Advanced'];
        $nouns = ['Widget', 'Gadget', 'Device', 'Tool', 'Solution', 'System', 'Kit', 'Package', 'Collection', 'Set'];

        $adj = $adjectives[rand(0, count($adjectives) - 1)];
        $noun = $nouns[rand(0, count($nouns) - 1)];
        $model = chr(rand(65, 90)) . chr(rand(65, 90)) . '-' . rand(100, 999);

        return $adj . ' ' . $noun . ' ' . $model;
    }

    /**
     * Generate lorem ipsum text
     */
    private function generate_lorem_ipsum($min_paragraphs, $max_paragraphs)
    {
        $paragraphs = [
            'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nullam at justo vel elit laoreet suscipit. Donec malesuada dui ut nisl finibus, non rutrum sapien faucibus.',
            'Phasellus euismod turpis eu nisi sollicitudin, ut dapibus est molestie. Aliquam erat volutpat. Cras tempus velit id libero lobortis, vitae efficitur velit tincidunt.',
            'Nulla facilisi. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Vivamus condimentum felis non ligula commodo, ut pulvinar magna bibendum.',
            'Fusce feugiat ligula vel magna vehicula, quis efficitur nisl tempus. Sed dictum mauris nec arcu vehicula, non tincidunt risus euismod. Integer ac placerat tellus.',
            'Curabitur tincidunt, purus at aliquam condimentum, augue lectus varius nisi, vel aliquam arcu ipsum nec lacus. Vestibulum fringilla nibh vel dui auctor, in fermentum lacus pretium.',
            'Donec sollicitudin, magna at dignissim posuere, nisi magna ullamcorper quam, ut luctus lorem arcu vitae sapien. Praesent mattis erat at risus luctus sollicitudin.',
            'Mauris tempor commodo neque, ut fermentum ex convallis quis. Cras volutpat justo in suscipit luctus. Nullam laoreet neque non magna tincidunt, at accumsan odio sollicitudin.',
            'Etiam consequat tristique neque in fermentum. Proin interdum tempus enim, a mollis dolor interdum ut. Nulla facilisi. Phasellus eget enim vel purus dapibus commodo vitae non velit.',
            'Duis non ligula vitae dolor vestibulum elementum. Integer id justo vel tellus lacinia faucibus. Suspendisse potenti. Cras mollis volutpat nibh, non rutrum elit.',
            'Vivamus accumsan libero felis, id vehicula risus suscipit vel. Ut aliquam sem ut magna sodales, non ultrices mauris blandit. Sed non nunc eget est sollicitudin dictum.'
        ];

        shuffle($paragraphs);
        $paragraph_count = rand($min_paragraphs, $max_paragraphs);
        $selected_paragraphs = array_slice($paragraphs, 0, $paragraph_count);

        return implode("\n\n", $selected_paragraphs);
    }

    /**
     * Generate a random price
     */
    private function generate_price()
    {
        $price_patterns = [
            function () { return rand(5, 20) - 0.01; },          // $4.99 - $19.99
            function () { return rand(20, 50) - 0.01; },         // $19.99 - $49.99
            function () { return rand(50, 100) - 0.01; },        // $49.99 - $99.99
            function () { return rand(100, 500) - 0.01; },       // $99.99 - $499.99
            function () { return rand(500, 1000) - 0.01; }       // $499.99 - $999.99
        ];

        $price_function = $price_patterns[rand(0, count($price_patterns) - 1)];
        return number_format($price_function(), 2, '.', '');
    }

    /**
     * Set a featured image for a product
     */
    private function set_featured_image($product_id)
    {
        // Use placeholder service for demonstration
        $width = rand(600, 800);
        $height = rand(600, 800);
        $image_url = "https://picsum.photos/{$width}/{$height}";

        // Try to get a placeholder image and upload it as product featured image
        $this->attach_image_from_url($image_url, $product_id, true);
    }

    /**
     * Set product gallery images
     */
    private function set_product_gallery($product_id, $count)
    {
        $gallery_image_ids = [];

        for ($i = 0; $i < $count; $i++) {
            $width = rand(600, 800);
            $height = rand(600, 800);
            $image_url = "https://picsum.photos/{$width}/{$height}";

            $attachment_id = $this->attach_image_from_url($image_url, $product_id, false);
            if ($attachment_id) {
                $gallery_image_ids[] = $attachment_id;
            }
        }

        if (!empty($gallery_image_ids)) {
            update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_image_ids));
        }
    }

    /**
     * Attach an image from URL to a product
     */
    private function attach_image_from_url($image_url, $post_id, $is_featured = false)
    {
        // Check if WP_Http exists
        if (!class_exists('WP_Http')) {
            include_once(ABSPATH . WPINC . '/class-http.php');
        }

        $http = new WP_Http();
        $response = $http->request($image_url);

        if (is_wp_error($response) || 200 !== $response['response']['code']) {
            return false;
        }

        $upload = wp_upload_bits(basename($image_url), null, $response['body']);

        if (!empty($upload['error'])) {
            return false;
        }

        $file_path = $upload['file'];
        $file_name = basename($file_path);
        $file_type = wp_check_filetype($file_name, null);
        $attachment_title = sanitize_file_name(pathinfo($file_name, PATHINFO_FILENAME));

        $attachment = array(
            'post_mime_type' => $file_type['type'],
            'post_title' => $attachment_title,
            'post_content' => '',
            'post_status' => 'inherit',
            'guid' => $upload['url']
        );

        $attachment_id = wp_insert_attachment($attachment, $file_path, $post_id);

        if (is_wp_error($attachment_id)) {
            return false;
        }

        // Include image.php
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Generate attachment metadata
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        if ($is_featured) {
            set_post_thumbnail($post_id, $attachment_id);
        }

        return $attachment_id;
    }
}
