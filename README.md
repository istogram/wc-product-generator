# WooCommerce Product Generator

A powerful WordPress plugin to generate dummy WooCommerce products for testing and development purposes.

## Description

WooCommerce Product Generator helps developers and store owners quickly populate their WooCommerce store with realistic dummy products for testing and development purposes.

Whether you need a few products for a demo, or thousands for performance testing, this plugin makes it easy to generate a variety of dummy products with categories, tags, attributes, images, and variations.

Perfect for testing themes, plugins, or performance benchmarking your WooCommerce setup.

## Features

- Generate any number of dummy products with realistic data
- Create products via an easy-to-use admin interface
- Use WP-CLI commands for large-scale generation
- Batch processing to prevent timeouts
- Create products with images, categories, and tags
- Clear all products with a single click
- Progress tracking for large operations

## Installation

1. Upload the `wc-product-generator` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Tools → WC Product Generator to start generating products

## Usage

### Admin Interface

1. Go to Tools → WC Product Generator
2. Set the number of products you want to generate
3. Set the batch size (smaller batches help avoid timeouts)
4. Click "Generate Products"

### WP-CLI Commands

For large-scale generation or automation, use the included WP-CLI commands:

```bash
# Generate 500 products
wp wc generate products --count=500

# Generate products and clear existing ones first
wp wc generate products --count=100 --clear=true

# Generate products with custom batch size
wp wc generate products --count=1000 --batch=20

# Clear all products
wp wc clear products

# Get store statistics
wp wc get stats
```

## Screenshots

![Admin Interface](screenshot-1.png)
![Generation Process](screenshot-2.png)
![WP-CLI Commands](screenshot-3.png)

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.2 or higher

## FAQ

### How many products can I generate?

Through the admin interface, it's recommended to generate up to 1,000 products at a time to avoid browser timeouts. For larger datasets, use the WP-CLI commands which can generate an unlimited number of products.

### Will this work on a production site?

This plugin is primarily intended for development and testing environments. While it will work on production sites, we recommend using it on a staging site first.

### How are the product images generated?

The plugin uses placeholder image services (like picsum.photos) to generate unique product images.

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## Credits

Created by [istogram](https://www.istogram.com)

## Support

If you find a bug or have a feature request, please [open an issue](https://github.com/istogram/wc-product-generator/issues) on GitHub.