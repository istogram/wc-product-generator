/**
 * WooCommerce Product Generator Admin JavaScript
 */
(function($) {
    'use strict';
    
    // Generation state
    let isGenerating = false;
    let shouldStop = false;
    
    // Initialize
    $(document).ready(function() {
        // Generate products button
        $('#generate-products').on('click', function() {
            startGeneration();
        });
        
        // Stop generation button
        $('#stop-generation').on('click', function() {
            shouldStop = true;
            $(this).prop('disabled', true).text('Stopping...');
            showNotice('Stopping generation after current batch completes...', 'notice-warning');
        });
        
        // Clear products button
        $('#clear-products').on('click', function() {
            if (confirm('Are you sure you want to delete ALL products? This cannot be undone!')) {
                clearProducts();
            }
        });
        
        // Reset progress counter button
        $('#reset-progress').on('click', function() {
            resetProgressCounter();
        });
    });
    
    /**
     * Start product generation
     */
    function startGeneration() {
        if (isGenerating) {
            return;
        }
        
        // Get form values
        const totalProducts = parseInt($('#product_count').val(), 10);
        const batchSize = parseInt($('#batch_size').val(), 10);
        const startPoint = $('input[name="start_point"]:checked').val() || 'new';
        
        // Validate input
        if (isNaN(totalProducts) || totalProducts < 1) {
            showNotice('Please enter a valid number of products.', 'notice-error');
            return;
        }
        
        if (isNaN(batchSize) || batchSize < 1) {
            showNotice('Please enter a valid batch size.', 'notice-error');
            return;
        }
        
        // Initialize generation
        isGenerating = true;
        shouldStop = false;
        
        // Update UI
        $('#generate-products').hide();
        $('#stop-generation').show();
        $('#wc-product-generator-progress').show();
        $('#wc-product-generator-form input').prop('disabled', true);
        
        // Update progress
        updateProgress(0, 'Starting generation...');
        
        // Show notice
        showNotice('Product generation started. Please do not navigate away from this page.', 'notice-info');
        
        // Determine starting point (new or continue)
        let startIndex = 1;
        if (startPoint === 'continue') {
            // If continuing, get the last processed value from the server
            $.ajax({
                url: wcProductGenerator.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'get_last_processed',
                    nonce: wcProductGenerator.nonce
                },
                success: function(response) {
                    if (response.success) {
                        startIndex = parseInt(response.data) + 1;
                    }
                    // Start the generation process
                    generateBatch(startIndex, totalProducts, batchSize);
                },
                error: function() {
                    // On error, start from the beginning
                    generateBatch(1, totalProducts, batchSize);
                }
            });
        } else {
            // If starting fresh, reset the counter first
            $.ajax({
                url: wcProductGenerator.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'reset_progress_counter',
                    nonce: wcProductGenerator.nonce
                },
                success: function() {
                    // Start the generation process from the beginning
                    generateBatch(1, totalProducts, batchSize);
                },
                error: function() {
                    // On error, start anyway
                    generateBatch(1, totalProducts, batchSize);
                }
            });
        }
    }
    
    /**
     * Generate a batch of products
     */
    function generateBatch(start, total, batchSize) {
        if (shouldStop) {
            finishGeneration();
            return;
        }
        
        // Update status
        updateProgress(Math.min(100, Math.round(((start - 1) / total) * 100)), 
            'Generating products ' + start + ' to ' + Math.min(start + batchSize - 1, total) + ' of ' + total);
        
        // AJAX request for batch generation
        $.ajax({
            url: wcProductGenerator.ajaxUrl,
            type: 'POST',
            data: {
                action: 'generate_wc_products_batch',
                nonce: wcProductGenerator.nonce,
                start: start,
                total: total,
                batch_size: batchSize
            },
            success: function(response) {
                if (!response.success) {
                    showNotice('Error: ' + (response.data || 'Unknown error'), 'notice-error');
                    finishGeneration();
                    return;
                }
                
                const data = response.data;
                
                // Show any errors
                if (data.errors && data.errors.length > 0) {
                    data.errors.forEach(function(error) {
                        showNotice(error, 'notice-warning', false);
                    });
                }
                
                // Update progress
                updateProgress(data.progress, 
                    'Generated products ' + start + ' to ' + data.end + ' of ' + total);
                
                // Check if complete or continue to next batch
                if (data.is_completed || shouldStop) {
                    finishGeneration();
                } else {
                    // Short timeout to prevent browser freezing
                    setTimeout(function() {
                        generateBatch(data.next, total, batchSize);
                    }, 500);
                }
            },
            error: function(xhr, status, error) {
                showNotice('AJAX Error: ' + error, 'notice-error');
                finishGeneration();
            }
        });
    }
    
    /**
     * Finish product generation
     */
    function finishGeneration() {
        isGenerating = false;
        
        // Update UI
        $('#generate-products').show();
        $('#stop-generation').hide().prop('disabled', false).text('Stop Generation');
        $('#wc-product-generator-form input').prop('disabled', false);
        
        if (shouldStop) {
            showNotice('Product generation stopped by user.', 'notice-warning');
            updateProgress(0, 'Generation stopped');
        } else {
            showNotice('Product generation completed successfully!', 'notice-success');
            updateProgress(100, 'Generation complete');
        }
    }
    
    /**
     * Clear all products
     */
    function clearProducts() {
        // Disable button
        $('#clear-products').prop('disabled', true).text('Deleting...');
        
        // Show notice
        showNotice('Deleting all products...', 'notice-info');
        
        // AJAX request
        $.ajax({
            url: wcProductGenerator.ajaxUrl,
            type: 'POST',
            data: {
                action: 'clear_wc_products',
                nonce: wcProductGenerator.clearNonce
            },
            success: function(response) {
                $('#clear-products').prop('disabled', false).text('Delete All Products');
                
                if (response.success) {
                    showNotice('All products have been deleted successfully.', 'notice-success');
                } else {
                    showNotice('Error: ' + (response.data || 'Unknown error'), 'notice-error');
                }
            },
            error: function(xhr, status, error) {
                $('#clear-products').prop('disabled', false).text('Delete All Products');
                showNotice('AJAX Error: ' + error, 'notice-error');
            }
        });
    }
    
    /**
     * Reset progress counter
     */
    function resetProgressCounter() {
        // Disable button to prevent multiple clicks
        $('#reset-progress').prop('disabled', true).text('Resetting...');
        
        // Show notice
        showNotice('Resetting progress counter...', 'notice-info');
        
        // AJAX request
        $.ajax({
            url: wcProductGenerator.ajaxUrl,
            type: 'POST',
            data: {
                action: 'reset_progress_counter',
                nonce: wcProductGenerator.nonce
            },
            success: function(response) {
                $('#reset-progress').prop('disabled', false).text('Reset Progress Counter');
                
                if (response.success) {
                    showNotice('Progress counter has been reset successfully.', 'notice-success');
                    
                    // Refresh the page to update the UI
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice('Error: ' + (response.data || 'Unknown error'), 'notice-error');
                }
            },
            error: function(xhr, status, error) {
                $('#reset-progress').prop('disabled', false).text('Reset Progress Counter');
                showNotice('AJAX Error: ' + error, 'notice-error');
            }
        });
    }
    
    /**
     * Update progress bar and text
     */
    function updateProgress(percent, text) {
        $('.progress-bar-fill').css('width', percent + '%');
        $('#progress-status').text(percent + '%');
        $('#progress-text').text(text);
    }
    
    /**
     * Show notice message
     */
    function showNotice(message, type, clear = true) {
        const $notices = $('#wc-product-generator-notices');
        
        if (clear) {
            $notices.empty();
        }
        
        $notices.append(
            $('<div>').addClass('notice ' + type + ' is-dismissible').append(
                $('<p>').text(message)
            )
        );
        
        // Auto dismiss after 10 seconds for success notices
        if (type === 'notice-success') {
            setTimeout(function() {
                $notices.find('.notice-success').fadeOut(function() {
                    $(this).remove();
                });
            }, 10000);
        }
    }
})(jQuery);