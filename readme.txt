=== WooCommerce Product Generator ===
Contributors: yourname
Tags: woocommerce, products, dummy data, generator, testing
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate dummy WooCommerce products with realistic data for testing and development environments.

== Description ==

WooCommerce Product Generator helps developers and store owners quickly populate their WooCommerce store with realistic dummy products for testing and development purposes.

Whether you need a few products for a demo, or thousands for performance testing, this plugin makes it easy to generate a variety of dummy products with categories, tags, attributes, images, and variations.

### Features

* Generate any number of dummy products with realistic data
* Create products via an easy-to-use admin interface
* Use WP-CLI commands for large-scale generation
* Batch processing to prevent timeouts
* Create products with images, categories, and tags
* Clear all products with a single click
* Progress tracking for large operations

### WP-CLI Support

For large datasets or automated workflows, use the included WP-CLI commands:

```
# Generate 500 products
wp wc generate products --count=500

# Clear all existing products
wp wc clear products

# Generate 1000 products with specific settings
wp wc generate products --count=1000 --clear=true --batch=20
```

### Use Cases

* Quickly populate a development or staging environment
* Create realistic test data for theme development
* Benchmark site performance with varying numbers of products
* Set up demo stores with diverse product types
* Testing front-end components like WooNuxt

== Installation ==

1. Upload the `wc-product-generator` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Tools → WC Product Generator to start generating products

== Frequently Asked Questions ==

= How many products can I generate? =

Through the admin interface, it's recommended to generate up to 1,000 products at a time to avoid browser timeouts. For larger datasets, use the WP-CLI commands which can generate an unlimited number of products.

= Will this work on a production site? =

This plugin is primarily intended for development and testing environments. While it will work on production sites, we recommend using it on a staging site first.

= How are the product images generated? =

The plugin uses placeholder image services to generate unique product images.

= Can I customize the product data? =

You can modify the core class to adjust the types of products, categories, and other data generated.

== Screenshots ==

1. The admin interface for generating products
2. Progress tracking during generation
3. WP-CLI commands in action

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release