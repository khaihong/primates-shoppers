/**
 * Primates Shoppers JavaScript
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        const $form = $('#ps-search-form');
        const $results = $('#ps-results');
        const $resultsCount = $('#ps-results-count');
        const $loading = $('#ps-loading');
        const $sortBy = $('#ps-sort-by');
        const productTemplate = $('#ps-product-template').html();
        
        // Store current search results for re-sorting
        let currentSearchResults = [];
        
        // Unit detection patterns for title extraction
        const unitPatterns = [
            { regex: /\b(\d+)\s*ml\b/i, unit: 'ml', multiplier: 100 },
            { regex: /\b(\d+)\s*milliliter(s)?\b/i, unit: 'ml', multiplier: 100 },
            { regex: /\b(\d+)\s*millilitre(s)?\b/i, unit: 'ml', multiplier: 100 },
            { regex: /\b(\d+)\s*g\b/i, unit: 'g', multiplier: 100 },
            { regex: /\b(\d+)\s*gram(s)?\b/i, unit: 'g', multiplier: 100 },
            { regex: /\b(\d+)\s*oz\b/i, unit: 'oz', multiplier: 100 },
            { regex: /\b(\d+)\s*ounce(s)?\b/i, unit: 'oz', multiplier: 100 },
            { regex: /\b(\d+)\s*fl\s*oz\b/i, unit: 'fl oz', multiplier: 100 },
            { regex: /\b(\d+)\s*pound(s)?\b/i, unit: 'lb', multiplier: 1 },
            { regex: /\b(\d+)\s*lb(s)?\b/i, unit: 'lb', multiplier: 1 }
        ];
        
        /**
         * Extract size from product title
         * @param {string} title - Product title
         * @returns {object|null} - Size object or null if not found
         */
        function extractSizeFromTitle(title) {
            for (const pattern of unitPatterns) {
                const match = title.match(pattern.regex);
                if (match) {
                    return {
                        value: parseFloat(match[1]),
                        unit: pattern.unit,
                        multiplier: pattern.multiplier
                    };
                }
            }
            return null;
        }
        
        /**
         * Process product items, normalize price per unit
         * @param {Array} items - Array of product items
         * @returns {Array} - Processed items
         */
        function processProductItems(items) {
            return items.map(function(item) {
                const processedItem = {...item}; // Create a copy to avoid mutating the original
                
                // Skip unit price handling if the backend already cleared the unit data
                if (processedItem.unit === '' && processedItem.price_per_unit === '' && processedItem.price_per_unit_value === 0) {
                    return processedItem;
                }
                
                // Check if we need to calculate unit price from title
                let hasPricePerUnit = processedItem.unit && processedItem.unit !== 'N/A' && processedItem.unit !== 'unit';
                let pricePerUnitValue = parseFloat(processedItem.price_per_unit) || 0;
                
                // If no price per unit or it's a placeholder, try to extract from title
                if (!hasPricePerUnit && processedItem.title && processedItem.price_value > 0) {
                    const sizeInfo = extractSizeFromTitle(processedItem.title);
                    if (sizeInfo) {
                        // Calculate price per unit based on extracted size
                        // First, get the price per single unit (e.g., per 1ml)
                        const pricePerSingleUnit = processedItem.price_value / sizeInfo.value;
                        
                        // Then calculate the price per 100 units
                        const normalizedPrice = pricePerSingleUnit * sizeInfo.multiplier;
                        
                        // Format for display with proper decimal places
                        processedItem.price_per_unit = normalizedPrice.toFixed(2);
                        processedItem.price_per_unit_value = normalizedPrice; // Update the value used for sorting
                        processedItem.unit = sizeInfo.unit === 'ml' ? '100 ml' : 
                                    sizeInfo.unit === 'g' ? '100 grams' : 
                                    sizeInfo.unit === 'oz' ? '100 oz' : 
                                    sizeInfo.unit;
                        
                        pricePerUnitValue = normalizedPrice;
                        hasPricePerUnit = true;
                        console.log(`Calculated price per unit from title: $${processedItem.price_value} for ${sizeInfo.value}${sizeInfo.unit} = $${normalizedPrice.toFixed(2)}/${processedItem.unit}`);
                    }
                }

                // Normalize price per unit to 100 units
                if (hasPricePerUnit) {
                    const unitLower = processedItem.unit.toLowerCase();
                    // Check for known units that should be normalized to 100
                    if (unitLower === 'gram' || unitLower === 'grams' || unitLower === 'g') {
                        // Normalize to 100 grams (multiply by 100)
                        if (pricePerUnitValue > 0) {
                            processedItem.price_per_unit = (pricePerUnitValue * 100).toFixed(2);
                            processedItem.price_per_unit_value = pricePerUnitValue * 100; // Update the value used for sorting
                            processedItem.unit = '100 grams';
                        }
                    } else if (unitLower === 'ml' || unitLower === 'milliliter' || unitLower === 'milliliters' || unitLower === 'millilitre' || unitLower === 'millilitres') {
                        // Normalize milliliters to 100ml
                        if (pricePerUnitValue > 0) {
                            processedItem.price_per_unit = (pricePerUnitValue * 100).toFixed(2);
                            processedItem.price_per_unit_value = pricePerUnitValue * 100; // Update the value used for sorting
                            processedItem.unit = '100 ml';
                        }
                    } else if (unitLower === 'oz' || unitLower === 'ounce' || unitLower === 'ounces' || unitLower === 'fl oz') {
                        // Normalize ounces to 100oz
                        if (pricePerUnitValue > 0) {
                            processedItem.price_per_unit = (pricePerUnitValue * 100).toFixed(2);
                            processedItem.price_per_unit_value = pricePerUnitValue * 100; // Update the value used for sorting
                            processedItem.unit = '100 oz';
                        }
                    }
                }

                return processedItem;
            });
        }

        /**
         * Sort products by selected criteria
         * @param {Array} items - Array of product items
         * @param {string} sortBy - Sort criteria ('price' or 'price_per_unit')
         * @returns {Array} - Sorted items
         */
        function sortProducts(items, sortBy) {
            const sortedItems = [...items]; // Create a copy to avoid mutating the original
            
            if (sortBy === 'price') {
                sortedItems.sort(function(a, b) {
                    const priceA = parseFloat(a.price_value) || 0;
                    const priceB = parseFloat(b.price_value) || 0;
                    return priceA - priceB;
                });
            } else if (sortBy === 'price_per_unit') {
                // First filter out products without unit prices
                const itemsWithUnitPrice = sortedItems.filter(function(item) {
                    return item.price_per_unit && item.unit && parseFloat(item.price_per_unit_value) > 0;
                });
                
                // If we have products with unit prices, return those sorted
                if (itemsWithUnitPrice.length > 0) {
                    return itemsWithUnitPrice.sort(function(a, b) {
                        const priceA = parseFloat(a.price_per_unit_value) || 0;
                        const priceB = parseFloat(b.price_per_unit_value) || 0;
                        return priceA - priceB;
                    });
                } else {
                    // If no products have unit prices, fall back to regular price sorting
                    return sortProducts(sortedItems, 'price');
                }
            }
            
            return sortedItems;
        }
        
        /**
         * Render product items to the results container
         * @param {Array} items - Array of processed product items
         */
        function renderProducts(items) {
            $results.empty();
            
            if (items.length === 0) {
                $results.html('<div class="ps-no-results">No products found. Try different search terms.</div>');
                return;
            }
            
            items.forEach(function(item) {
                // Process if_rating and if_rating_count conditional tags
                let productHtml = productTemplate;
                
                // Handle ratings conditional section
                if (item.rating_number && item.rating) {
                    productHtml = productHtml.replace(/{{#if_rating}}([\s\S]*?){{\/if_rating}}/g, function(match, content) {
                        return content;
                    });
                } else {
                    productHtml = productHtml.replace(/{{#if_rating}}[\s\S]*?{{\/if_rating}}/g, '');
                }
                
                // Handle rating count conditional
                if (item.rating_count) {
                    productHtml = productHtml.replace(/{{#if_rating_count}}([\s\S]*?){{\/if_rating_count}}/g, function(match, content) {
                        return content;
                    });
                } else {
                    productHtml = productHtml.replace(/{{#if_rating_count}}[\s\S]*?{{\/if_rating_count}}/g, '');
                }
                
                // Handle delivery time conditional
                if (item.delivery_time) {
                    productHtml = productHtml.replace(/{{#if_delivery}}([\s\S]*?){{\/if_delivery}}/g, function(match, content) {
                        return content;
                    });
                } else {
                    productHtml = productHtml.replace(/{{#if_delivery}}[\s\S]*?{{\/if_delivery}}/g, '');
                }
                
                // Handle brand conditional
                if (item.brand && item.brand.trim() !== '') {
                    productHtml = productHtml.replace(/{{#if_brand}}([\s\S]*?){{\/if_brand}}/g, function(match, content) {
                        return content;
                    });
                } else {
                    productHtml = productHtml.replace(/{{#if_brand}}[\s\S]*?{{\/if_brand}}/g, '');
                }
                
                // Handle price per unit conditional
                if (item.price_per_unit && item.unit) {
                    productHtml = productHtml.replace(/{{#if_price_per_unit}}([\s\S]*?){{\/if_price_per_unit}}/g, function(match, content) {
                        return content;
                    });
                } else {
                    productHtml = productHtml.replace(/{{#if_price_per_unit}}[\s\S]*?{{\/if_price_per_unit}}/g, '');
                }
                
                // Replace standard placeholders
                productHtml = productHtml
                    .replace(/{{title}}/g, item.title || '')
                    .replace(/{{link}}/g, item.link || '')
                    .replace(/{{image}}/g, item.image || '')
                    .replace(/{{price}}/g, item.price || '')
                    .replace(/{{price_per_unit}}/g, item.price_per_unit || '')
                    .replace(/{{unit}}/g, item.unit ? '/' + item.unit : '')
                    .replace(/{{brand}}/g, item.brand || 'Brand');
                
                // Replace rating placeholders only if they exist
                if (item.rating_number) {
                    productHtml = productHtml.replace(/{{rating_number}}/g, item.rating_number);
                }
                if (item.rating) {
                    productHtml = productHtml.replace(/{{rating}}/g, item.rating);
                }
                if (item.rating_count) {
                    productHtml = productHtml.replace(/{{rating_count}}/g, item.rating_count);
                }
                if (item.rating_link) {
                    productHtml = productHtml.replace(/{{rating_link}}/g, item.rating_link);
                }
                
                // Replace delivery time if it exists
                if (item.delivery_time) {
                    productHtml = productHtml.replace(/{{delivery_time}}/g, item.delivery_time);
                }
                
                $results.append(productHtml);
            });
        }
        
        // Handle sort change (re-sort current results)
        $sortBy.on('change', function() {
            if (currentSearchResults.length > 0) {
                const sortCriteria = $(this).val();
                const sortedResults = sortProducts(currentSearchResults, sortCriteria);
                renderProducts(sortedResults);
            }
        });
        
        // Handle form submission
        $form.on('submit', function(e) {
            e.preventDefault();
            
            // Show loading indicator
            $loading.show();
            $results.empty();
            $resultsCount.hide();
            
            // Get form data
            const formData = {
                action: 'ps_search',
                nonce: psData.nonce,
                query: $('#ps-search-query').val(),
                exclude: $('#ps-exclude-keywords').val(),
                sort_by: $sortBy.val()
            };
            
            // Send AJAX request
            $.post(psData.ajaxurl, formData, function(response) {
                $loading.hide();
                
                if (!response.success) {
                    let errorMessage = response.message || 'An error occurred while searching.';
                    
                    // Check for specific error types
                    if (response.error_type === 'amazon_blocking') {
                        errorMessage = '<strong>Amazon is currently blocking our search requests.</strong><br>' +
                            'This can happen when Amazon detects automated searches. ' +
                            'Please try again later or use fewer searches.';
                    } else if (response.error_type === 'parsing_error') {
                        errorMessage = '<strong>Unable to parse Amazon search results.</strong><br>' +
                            'Amazon may have changed their website structure. ' +
                            'Please contact the site administrator.';
                    } else if (response.error_type === 'connection_error') {
                        errorMessage = '<strong>Unable to connect to Amazon.</strong><br>' +
                            'There may be a network issue or Amazon might be unavailable. ' +
                            'Please try again later.';
                    }

                    // Add processed file information to the error message
                    if (response.processed_file) {
                        errorMessage += '<br><small>Processed File: ' + response.processed_file + '</small>';
                    }
                    
                    $results.html('<div class="ps-error">' + errorMessage + '</div>');
                    return;
                }
                
                // Display processed file for successful responses
                if (response.processed_file) {
                    $results.append('<p class="ps-processed-file">Processed File: ' + response.processed_file + '</p>');
                }
                
                // Display results count
                if (response.count !== undefined) {
                    $resultsCount.html('<p>Found <strong>' + response.count + '</strong> products matching your search.</p>').show();
                }
                
                if (response.items.length === 0) {
                    $results.html('<div class="ps-no-results">No products found. Try different search terms.</div>');
                    return;
                }
                
                // Process and store search results
                currentSearchResults = processProductItems(response.items);
                
                // Check if more than half of the products have unit prices
                const productsWithUnitPrice = currentSearchResults.filter(function(item) {
                    return item.price_per_unit && item.unit && parseFloat(item.price_per_unit_value) > 0;
                });
                
                const userSelectedSort = $sortBy.val();
                let sortCriteria = userSelectedSort;
                
                // If more than half of products have unit prices and user hasn't explicitly changed the sort,
                // default to sorting by price per unit
                if (productsWithUnitPrice.length > currentSearchResults.length / 2) {
                    // Only update the dropdown if user hasn't changed it from the default
                    if ($sortBy.prop('selectedIndex') === 0) {
                        $sortBy.val('price_per_unit');
                        sortCriteria = 'price_per_unit';
                    }
                }
                
                // Sort products according to selected option
                const sortedResults = sortProducts(currentSearchResults, sortCriteria);
                
                // Display results
                renderProducts(sortedResults);
            }).fail(function() {
                $loading.hide();
                $results.html('<div class="ps-error">Error connecting to server. Please try again.</div>');
            });
        });
    });
})(jQuery);