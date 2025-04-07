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
    private $debug = false;

    public function __construct()
    {
        // Make sure WooCommerce is active
        if (!function_exists('WC')) {
            return;
        }

        // Increase memory limit for large operations
        ini_set('memory_limit', '512M');

        // Set debug mode
        $this->debug = defined('WC_PRODUCT_GENERATOR_DEBUG') && WC_PRODUCT_GENERATOR_DEBUG;

        // Create assets directory if it doesn't exist
        $this->maybe_create_assets_directory();
    }

    /**
     * Create assets directory if it doesn't exist
     */
    private function maybe_create_assets_directory()
    {
        $images_dir = WC_PRODUCT_GENERATOR_PLUGIN_DIR . 'assets/images';
        if (!file_exists($images_dir)) {
            wp_mkdir_p($images_dir);

            // Try to create a placeholder image if GD is available
            if (function_exists('imagecreatetruecolor')) {
                $this->create_placeholder_image($images_dir . '/placeholder.jpg');
            }
        }
    }

    /**
     * Create a basic placeholder image
     */
    private function create_placeholder_image($path)
    {
        if (!function_exists('imagecreatetruecolor')) {
            return false;
        }

        $image = imagecreatetruecolor(800, 800);
        $bg = imagecolorallocate($image, 240, 240, 240);
        $text = imagecolorallocate($image, 100, 100, 100);

        imagefill($image, 0, 0, $bg);
        imagestring($image, 5, 300, 400, 'Product Image', $text);

        imagejpeg($image, $path);
        imagedestroy($image);

        return file_exists($path);
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
            $this->log_message('Error creating product #' . $index . ': ' . $e->getMessage(), true);
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
        // Try online services first, only fall back to placeholder if they fail
        $image_services = [
            // Picsum Photos - Direct URL that doesn't redirect
            function () {
                $id = rand(1, 1000);
                return "https://picsum.photos/id/{$id}/800/800";
            },
            // Unsplash Source API
            function () {
                return "https://source.unsplash.com/800x800/?product";
            },
            // Placeholder service as last resort
            function () {
                $width = 800;
                $height = 800;
                $bg = dechex(rand(150, 220));
                $fg = dechex(rand(80, 120));
                return "https://dummyimage.com/{$width}x{$height}/{$bg}/{$fg}.jpg";
            }
        ];

        // Try each image service in sequence until one works
        foreach ($image_services as $service) {
            $image_url = $service();

            // Log attempt
            $this->log_message('Trying to fetch featured image from: ' . $image_url);

            // Use WordPress HTTP API directly for better handling
            $response = wp_remote_get($image_url, [
                'timeout' => 15,
                'sslverify' => false,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36'
            ]);

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $upload_dir = wp_upload_dir();
                $filename = 'product-' . $product_id . '-' . uniqid() . '.jpg';
                $file_path = $upload_dir['path'] . '/' . $filename;

                // Save the image content to a file
                $result = file_put_contents($file_path, wp_remote_retrieve_body($response));

                if ($result) {
                    // Create attachment
                    $attachment = [
                        'post_mime_type' => 'image/jpeg',
                        'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
                        'post_content' => '',
                        'post_status' => 'inherit'
                    ];

                    $attach_id = wp_insert_attachment($attachment, $file_path, $product_id);

                    if (!is_wp_error($attach_id)) {
                        // Generate attachment metadata
                        require_once(ABSPATH . 'wp-admin/includes/image.php');
                        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
                        wp_update_attachment_metadata($attach_id, $attach_data);

                        // Set as featured image
                        set_post_thumbnail($product_id, $attach_id);

                        $this->log_message('Successfully set featured image for product #' . $product_id . ' using URL: ' . $image_url);

                        return $attach_id;
                    }
                }
            }

            $this->log_message('Failed to set image from: ' . $image_url);
        }

        // As a last resort, use local placeholder
        $local_placeholder = WC_PRODUCT_GENERATOR_PLUGIN_DIR . 'assets/images/placeholder.jpg';

        if (file_exists($local_placeholder)) {
            $this->log_message('Using local placeholder image for product #' . $product_id);
            return $this->attach_local_image($local_placeholder, $product_id, true);
        }

        // If all else fails, create a dynamic placeholder
        return $this->create_and_attach_dynamic_placeholder($product_id, true);
    }

    /**
     * Set product gallery images
     */
    private function set_product_gallery($product_id, $count)
    {
        $gallery_image_ids = [];

        // Try online sources first for each gallery image
        for ($i = 0; $i < $count; $i++) {
            // Different image sources for variety
            $image_services = [
                // Picsum with specific ID and random effects
                function () use ($i) {
                    $id = rand(1, 1000) + $i;
                    $effects = ['', '?grayscale', '?blur=2'];
                    $effect = $effects[array_rand($effects)];
                    return "https://picsum.photos/id/{$id}/800/800{$effect}";
                },
                // Unsplash with different categories
                function () use ($i) {
                    $categories = ['product', 'item', 'technology', 'design', 'retail'];
                    $category = $categories[$i % count($categories)];
                    return "https://source.unsplash.com/800x800/?{$category}";
                },
                // Dummy image with different colors
                function () use ($i) {
                    $colors = [
                        'cccccc/999999', 'eeeeee/666666', 'dddddd/777777',
                        'ffffff/333333', 'f5f5f5/555555'
                    ];
                    $color = $colors[$i % count($colors)];
                    return "https://dummyimage.com/800x800/{$color}.jpg&text=Gallery+Image+{$i}";
                }
            ];

            // Try each service for this gallery image
            foreach ($image_services as $service) {
                $image_url = $service();

                // Log attempt
                $this->log_message('Trying to fetch gallery image from: ' . $image_url);

                // Use WordPress HTTP API directly
                $response = wp_remote_get($image_url, [
                    'timeout' => 15,
                    'sslverify' => false,
                    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36'
                ]);

                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                    $upload_dir = wp_upload_dir();
                    $filename = 'gallery-' . $product_id . '-' . $i . '-' . uniqid() . '.jpg';
                    $file_path = $upload_dir['path'] . '/' . $filename;

                    // Save the image content to a file
                    $result = file_put_contents($file_path, wp_remote_retrieve_body($response));

                    if ($result) {
                        // Create attachment
                        $attachment = [
                            'post_mime_type' => 'image/jpeg',
                            'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
                            'post_content' => '',
                            'post_status' => 'inherit'
                        ];

                        $attach_id = wp_insert_attachment($attachment, $file_path, $product_id);

                        if (!is_wp_error($attach_id)) {
                            // Generate attachment metadata
                            require_once(ABSPATH . 'wp-admin/includes/image.php');
                            $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
                            wp_update_attachment_metadata($attach_id, $attach_data);

                            $gallery_image_ids[] = $attach_id;
                            $this->log_message('Successfully added gallery image #' . $i . ' for product #' . $product_id);

                            // Break out of the service loop as we got a successful image
                            break;
                        }
                    }
                }

                $this->log_message('Failed to get gallery image from: ' . $image_url);
            }

            // If all services failed for this gallery image, use local placeholder
            if (empty($gallery_image_ids[$i])) {
                $local_placeholder = WC_PRODUCT_GENERATOR_PLUGIN_DIR . 'assets/images/placeholder.jpg';

                if (file_exists($local_placeholder)) {
                    $attach_id = $this->attach_local_image(
                        $local_placeholder,
                        $product_id,
                        false,
                        "Gallery Image " . ($i + 1)
                    );

                    if ($attach_id) {
                        $gallery_image_ids[] = $attach_id;
                    }
                } else {
                    // As last resort, create dynamic image
                    $attach_id = $this->create_and_attach_dynamic_placeholder(
                        $product_id,
                        false,
                        "Gallery Image " . ($i + 1)
                    );

                    if ($attach_id) {
                        $gallery_image_ids[] = $attach_id;
                    }
                }
            }
        }

        // Update product gallery
        if (!empty($gallery_image_ids)) {
            update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_image_ids));
        }

        return $gallery_image_ids;
    }

    /**
     * Attach an image from URL to a product
     */
    private function attach_image_from_url($image_url, $post_id, $is_featured = false)
    {
        $this->log_message('Attempting to attach image: ' . $image_url);

        // Ensure WordPress media functions are available
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Download URL to temporary file
        $tmp = download_url($image_url);

        // If error occurred, try a fallback image
        if (is_wp_error($tmp)) {
            $this->log_message('Error downloading image: ' . $tmp->get_error_message(), true);

            // Try a different placeholder service as fallback
            $fallback_url = 'https://via.placeholder.com/800x800.jpg?text=Product+Image';
            $this->log_message('Trying fallback image: ' . $fallback_url);
            $tmp = download_url($fallback_url);

            if (is_wp_error($tmp)) {
                $this->log_message('Error downloading fallback image: ' . $tmp->get_error_message(), true);
                return false;
            }
        }

        $file_array = array(
            'name' => basename($image_url),
            'tmp_name' => $tmp
        );

        // Upload the image and get the attachment ID
        $attachment_id = media_handle_sideload($file_array, $post_id);

        // Clean up the temporary file
        if (file_exists($tmp)) {
            @unlink($tmp);
        }

        // If error occurred during upload
        if (is_wp_error($attachment_id)) {
            $this->log_message('Error uploading image: ' . $attachment_id->get_error_message(), true);
            return false;
        }

        $this->log_message('Successfully created attachment with ID: ' . $attachment_id);

        // Set as featured image if requested
        if ($is_featured) {
            set_post_thumbnail($post_id, $attachment_id);
        }

        return $attachment_id;
    }

    /**
     * Helper function for logging
     */
    private function log_message($message, $is_error = false)
    {
        if ($this->debug || $is_error) {
            error_log('WC Product Generator: ' . $message);
        }
    }

    /**
     * Attach a local image to a product
     */
    private function attach_local_image($image_path, $post_id, $is_featured = false, $title = '')
    {
        $this->log_message('Attaching local image: ' . $image_path);

        // Ensure WordPress media functions are available
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Create a unique filename
        $filename = !empty($title) ? sanitize_title($title) . '.jpg' : 'product-image-' . uniqid() . '.jpg';

        // Create a temporary file
        $tmp_dir = get_temp_dir();
        $tmp_file = $tmp_dir . 'wc_product_gen_' . uniqid() . '.jpg';

        if (!copy($image_path, $tmp_file)) {
            $this->log_message('Failed to copy local image to temp location', true);
            return false;
        }

        $file_array = array(
            'name' => $filename,
            'tmp_name' => $tmp_file
        );

        // Add the image to the media library
        $attachment_id = media_handle_sideload($file_array, $post_id);

        // Clean up
        if (file_exists($tmp_file)) {
            @unlink($tmp_file);
        }

        if (is_wp_error($attachment_id)) {
            $this->log_message('Error adding local image: ' . $attachment_id->get_error_message(), true);
            return false;
        }

        // Set as featured image if requested
        if ($is_featured) {
            set_post_thumbnail($post_id, $attachment_id);
        }

        return $attachment_id;
    }

    /**
     * Create and attach a dynamically generated placeholder image
     */
    private function create_and_attach_dynamic_placeholder($post_id, $is_featured = false, $text = 'Product Image')
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->log_message('GD library not available for dynamic image creation', true);
            return false;
        }

        $this->log_message('Creating dynamic placeholder image for product #' . $post_id);

        // Create directory if it doesn't exist
        $tmp_dir = get_temp_dir();
        $tmp_file = $tmp_dir . 'wc_product_generator_' . uniqid() . '.jpg';

        // Create a blank image
        $image = imagecreatetruecolor(800, 800);
        $bg_color = imagecolorallocate($image, 240, 240, 240);
        $text_color = imagecolorallocate($image, 100, 100, 100);
        imagefill($image, 0, 0, $bg_color);

        // Add text
        $text = !empty($text) ? $text : 'Product Image';
        imagestring($image, 5, 300, 400, $text, $text_color);

        // Save image
        imagejpeg($image, $tmp_file);
        imagedestroy($image);

        if (!file_exists($tmp_file)) {
            $this->log_message('Failed to create dynamic image', true);
            return false;
        }

        // Add to media library
        $file_array = array(
            'name' => 'product-image-' . uniqid() . '.jpg',
            'tmp_name' => $tmp_file
        );

        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $attachment_id = media_handle_sideload($file_array, $post_id);

        // Clean up
        if (file_exists($tmp_file)) {
            @unlink($tmp_file);
        }

        if (is_wp_error($attachment_id)) {
            $this->log_message('Error adding dynamic image: ' . $attachment_id->get_error_message(), true);
            return false;
        }

        // Set as featured image if requested
        if ($is_featured) {
            set_post_thumbnail($post_id, $attachment_id);
        }

        return $attachment_id;
    }
}
