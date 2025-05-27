/**
 * Primates Shoppers JavaScript
 */
console.log('search.js loaded');
(function($) {
    'use strict';
    
    // Debug the psData object - remove after debugging
    console.log('psData available at load:', window.psData);
    
    $(document).ready(function() {
        console.log('jQuery ready fired');
        // Debug the psData object again after document ready
        console.log('psData available after document ready:', window.psData);
        
        // Add debug logs for the condition
        console.log('psData.ajaxurl:', window.psData && window.psData.ajaxurl);
        console.log('psData.nonce:', window.psData && window.psData.nonce);
        console.log('Condition:', !!(window.psData && window.psData.ajaxurl && window.psData.nonce));
        
        if (window.psData && window.psData.ajaxurl && window.psData.nonce) {
            console.log('Calling loadCachedResults');
            loadCachedResults();
        } else {
            console.error('psData not available for loadCachedResults', window.psData);
            const resultsContainer = document.getElementById('ps-results');
            if (resultsContainer) {
                resultsContainer.innerHTML = '<div class="ps-error">Configuration error. Please refresh the page or contact support.</div>';
            }
        }
        
        const $form = $('#ps-search-form');
        const $results = $('#ps-results');
        const $resultsCount = $('#ps-results-count');
        const $loading = $('#ps-loading');
        const $loadingText = $('#ps-loading-text');
        const $sortBy = $('#ps-sort-by');
        const $filterButton = $('#ps-filter-button');
        const $showAllButton = $('#ps-show-all-button');
        const $filterCachedInput = $('#ps-filter-cached');
        const $cachedNotice = $('#ps-cached-notice');
        const $cachedTime = $('.ps-cached-time');
        const productTemplate = $('#ps-product-template').html();
        
        // Make filter button the default action when pressing enter
        $form.on('keypress', function(e) {
            if (e.which === 13 && $filterButton.length && $filterButton.is(':visible')) {
                e.preventDefault();
                $filterButton.click();
                return false;
            }
        });
        
        // Detect user's country and set the appropriate radio button
        detectUserCountry();
        
        // Store current search results for re-sorting
        let currentSearchResults = [];
        // Store the original cached results for always-on filtering
        let originalCachedResults = [];
        
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
         * Detect user's country and set the appropriate radio button
         */
        function detectUserCountry() {
            // First try using a free IP geolocation API
            $.ajax({
                url: 'https://ipapi.co/json/',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response && response.country_code) {
                        const countryCode = response.country_code.toLowerCase();
                        console.log('Detected country from IP:', countryCode);
                        
                        // Check if the detected country is supported in our selector
                        if (countryCode === 'ca') {
                            // Set Canada as selected
                            $('input[name="country"][value="ca"]').prop('checked', true);
                        }
                        // For US and all other countries, keep the default (US)
                    }
                },
                error: function(error) {
                    console.log('Error detecting country from IP:', error);
                    // Fall back to browser language detection
                    detectCountryFromBrowser();
                }
            });
        }
        
        /**
         * Fallback method to detect country from browser language
         */
        function detectCountryFromBrowser() {
            try {
                const language = (navigator.language || navigator.userLanguage || '').toLowerCase();
                console.log('Browser language:', language);
                
                // Check for Canadian English/French
                if (language === 'en-ca' || language === 'fr-ca') {
                    $('input[name="country"][value="ca"]').prop('checked', true);
                }
            } catch (e) {
                console.log('Error detecting country from browser language:', e);
                // Keep default (US) on error
            }
        }
        
        /**
         * Format relative time from timestamp
         * @param {string} timestamp - ISO timestamp
         * @returns {string} - Formatted relative time
         */
        function formatRelativeTime(timestamp) {
            if (!timestamp) return '';
            
            const date = new Date(timestamp);
            const now = new Date();
            const diffMs = now - date;
            const diffMinutes = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMinutes / 60);
            
            if (diffMinutes < 1) {
                return 'just now';
            } else if (diffMinutes < 60) {
                return `${diffMinutes} minute${diffMinutes > 1 ? 's' : ''} ago`;
            } else if (diffHours < 24) {
                return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
            } else {
                // Format as date if more than 24 hours
                return date.toLocaleString();
            }
        }
        
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
         * Client-side filtering of products based on criteria
         * @param {Array} items - Array of product items
         * @param {string} excludeText - Text to exclude from titles
         * @param {string} includeText - Text that must be included in titles (optional)
         * @param {float} minRating - Minimum rating filter (optional)
         * @returns {Array} - Filtered items
         */
        function filterProducts(items, excludeText, includeText = '', minRating = null) {
            if (!items || !Array.isArray(items)) {
                console.error('Invalid items array:', items);
                return [];
            }
            
            let filteredItems = [...items];
            
            // Filter by excluded keywords
            if (excludeText) {
                const excludeTerms = excludeText.toLowerCase().split(/\s+/).filter(term => term.length > 0);
                
                if (excludeTerms.length > 0) {
                    console.log('Filtering out products containing terms:', excludeTerms);
                    
                    filteredItems = filteredItems.filter(item => {
                        const titleLower = (item.title || '').toLowerCase();
                        
                        for (const term of excludeTerms) {
                            if (titleLower.includes(term)) {
                                return false; // Exclude this item
                            }
                        }
                        return true; // Keep this item
                    });
                }
            }
            
            // Filter by included keywords (if provided)
            if (includeText) {
                const includeTerms = includeText.toLowerCase().split(/\s+/).filter(term => term.length > 0);
                
                if (includeTerms.length > 0) {
                    console.log('Filtering for products containing terms:', includeTerms);
                    
                    filteredItems = filteredItems.filter(item => {
                        const titleLower = (item.title || '').toLowerCase();
                        
                        for (const term of includeTerms) {
                            // Check if the term contains a wildcard (*)
                            if (term.includes('*')) {
                                const prefix = term.replace(/\*/g, '');
                                if (prefix.length < 2) continue;
                                
                                // Check if any word in the title starts with the prefix
                                const titleWords = titleLower.split(/\s+/);
                                let found = false;
                                
                                for (const word of titleWords) {
                                    if (word.startsWith(prefix)) {
                                        found = true;
                                        break;
                                    }
                                }
                                
                                // If no word starts with this prefix, filter out the product
                                if (!found) return false;
                            } else {
                                // Regular exact term matching
                                if (!titleLower.includes(term)) {
                                    return false;
                                }
                            }
                        }
                        return true;
                    });
                }
            }
            
            // Filter by minimum rating
            if (minRating !== null && minRating > 0) {
                console.log('Filtering for products with minimum rating:', minRating);
                
                filteredItems = filteredItems.filter(item => {
                    // Include products with no rating or rating >= minimum
                    if (!item.rating_number) {
                        return true; // Include products with no rating
                    }
                    
                    const itemRating = parseFloat(item.rating_number);
                    return itemRating >= minRating;
                });
            }
            
            console.log(`Filtered from ${items.length} to ${filteredItems.length} products`);
            return filteredItems;
        }
        
        /**
         * Render product items to the results container
         * @param {Array} items - Array of processed product items
         */
        function renderProducts(items) {
            currentSearchResults = items; // <-- Always sync currentSearchResults
            $results.empty();
            // Remove debug box
            // Enable filter and show all buttons if products are present
            if (items.length > 0) {
                $('#ps-filter-btn').prop('disabled', false).removeClass('ps-disabled');
                $filterButton.show(); // Show the filter button when products are found
                $showAllButton.show(); // Show the show all button when products are found
            }
            
            if (items.length === 0) {
                $results.html('<div class="ps-no-results">No products found. Try different search terms.</div>');
                return;
            }
            
            items.forEach(function(item) {
                let productHtml = productTemplate;
                
                // Handle brand conditional
                if (item.brand) {
                    productHtml = productHtml.replace(/{{#if brand}}([\s\S]*?){{\/if}}/g, '$1');
                } else {
                    productHtml = productHtml.replace(/{{#if brand}}[\s\S]*?{{\/if}}/g, '');
                }

                // Handle ratings conditional with else clause
                if (item.rating) {
                    productHtml = productHtml.replace(/{{#if rating}}([\s\S]*?){{else}}[\s\S]*?{{\/if}}/g, '$1');
                } else {
                    productHtml = productHtml.replace(/{{#if rating}}[\s\S]*?{{else}}([\s\S]*?){{\/if}}/g, '$1');
                }
                
                // Handle rating_number conditional (inside rating conditional)
                if (item.rating && item.rating_number) {
                    productHtml = productHtml.replace(/{{#if rating_number}}([\s\S]*?){{\/if}}/g, '$1');
                } else {
                    productHtml = productHtml.replace(/{{#if rating_number}}[\s\S]*?{{\/if}}/g, '');
                    productHtml = productHtml.replace(/{{rating_number}}/g, '');
                }

                // Handle rating count conditional
                if (item.rating_count) {
                    productHtml = productHtml.replace(/{{#if rating_count}}([\s\S]*?){{\/if}}/g, '$1');
                } else {
                    productHtml = productHtml.replace(/{{#if rating_count}}[\s\S]*?{{\/if}}/g, '');
                }
                
                // Handle price per unit conditional - only show if unit price exists AND unit is not blank
                if (item.price_per_unit && item.unit && item.unit.trim() !== '') {
                    productHtml = productHtml.replace(/{{#if price_per_unit}}([\s\S]*?){{\/if}}/g, '$1');
                } else {
                    productHtml = productHtml.replace(/{{#if price_per_unit}}[\s\S]*?{{\/if}}/g, '');
                }
                
                // Handle delivery time conditional
                if (item.delivery_time) {
                    productHtml = productHtml.replace(/{{#if delivery_time}}([\s\S]*?){{\/if}}/g, '$1');
                } else {
                    productHtml = productHtml.replace(/{{#if delivery_time}}[\s\S]*?{{\/if}}/g, '');
                }
                
                // Replace standard placeholders
                productHtml = productHtml
                    .replace(/{{brand}}/g, item.brand || '')
                    .replace(/{{title}}/g, item.title || '')
                    .replace(/{{link}}/g, item.link || '')
                    .replace(/{{image}}/g, item.image || '')
                    .replace(/{{price}}/g, item.price || '')
                    .replace(/{{price_per_unit}}/g, item.price_per_unit || '')
                    .replace(/{{unit}}/g, item.unit || '');
                
                // Replace rating placeholders only if they exist
                if (item.rating) {
                    productHtml = productHtml.replace(/{{rating}}/g, item.rating);
                }
                if (item.rating_number) {
                    productHtml = productHtml.replace(/{{rating_number}}/g, item.rating_number);
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
                
                // Remove any stray {{/if}} left in the template
                productHtml = productHtml.replace(/{{\/if}}/g, '');
                
                $results.append(productHtml);
            });

            // Add styles for the new layout if not already added
            if (!$('style#ps-product-layout-styles').length) {
                $('head').append(`
                    <style id="ps-product-layout-styles">
                        .ps-product {
                            display: flex; /* Use flexbox for layout */
                            border: 1px solid #ddd;
                            border-radius: 5px;
                            overflow: hidden;
                            background: white;
                            transition: transform 0.2s, box-shadow 0.2s;
                            margin-bottom: 15px; /* Add some space between products */
                        }
                        .ps-product:hover {
                            transform: translateY(-3px);
                            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                        }
                        .ps-product-image-container {
                            flex: 0 0 25%; /* Image container takes 25% width */
                            padding: 10px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        }
                        .ps-product-image-tag {
                            max-width: 100%;
                            max-height: 150px; /* Control max image height */
                            object-fit: contain;
                        }
                        .ps-product-info {
                            flex: 1; /* Info takes remaining space */
                            padding: 15px;
                            display: flex;
                            flex-direction: column;
                        }
                        .ps-product-brand {
                            font-size: 0.85em;
                            color: #555;
                            margin-bottom: 5px;
                            order: 1;
                        }
                        .ps-product-title {
                            font-size: 0.85em; /* Reduced font size */
                            line-height: 1.3;
                            margin: 0 0 4px 0;
                            order: 2;
                            /* Removed fixed height and line clamping for more flexibility */
                        }
                        .ps-product-title a {
                            color: #333;
                            text-decoration: none;
                        }
                        .ps-product-title a:hover {
                            color: #4CAF50;
                        }
                        .ps-product-rating {
                            display: flex;
                            align-items: center;
                            margin-bottom: 3px;
                            font-size: 0.9em;
                            order: 3;
                        }
                        .ps-product-rating a {
                            text-decoration: none;
                            color: inherit;
                            display: flex;
                            align-items: center;
                        }
                        .ps-rating-number {
                            margin-right: 5px;
                            font-weight: bold;
                        }
                        .ps-stars {
                            color: #ffc107; /* Gold color for stars */
                            margin-right: 5px;
                        }
                        .ps-rating-count {
                            color: #666;
                            font-size: 0.9em; /* Slightly smaller */
                        }
                        .ps-rating-spacer {
                            height: 8px; /* Provides spacing equivalent to rating section */
                            margin-bottom: 3px;
                            order: 3;
                        }
                        .ps-product-pricing {
                            margin-bottom: 3px;
                            order: 4;
                        }
                        .ps-product-price {
                            font-weight: bold;
                            font-size: 1em; /* Reduced from 1.2em */
                            color: #e63946;
                            margin-bottom: 3px;
                        }
                        .ps-product-price-unit {
                            font-size: 0.8em;
                            color: #666;
                        }
                        .ps-delivery-time {
                            font-size: 0.8em;
                            color: #555;
                            text-align: left; /* Ensure left alignment */
                            margin-top: auto; /* Pushes delivery time to the bottom */
                            white-space: pre-line; /* Preserve line breaks */
                            order: 5;
                        }
                        /* Ensure the grid layout is removed or adjusted if it conflicts */
                        .ps-results-grid {
                           display: block; /* Override grid if it was set */
                        }
                    </style>
                `);
            }
        }
        
        // Handle sort change (re-sort current results)
        $sortBy.on('change', function() {
            if (originalCachedResults.length > 0) {
                // Re-apply all filters when sort changes
                const excludeText = $('#ps-exclude-keywords').val();
                const includeText = $('#ps-search-query').val();
                const minRating = parseFloat($('#ps-min-rating').val()) || null;
                const sortCriteria = $(this).val();
                
                let filteredResults = [...originalCachedResults];
                filteredResults = filterProducts(filteredResults, excludeText, includeText, minRating);
                filteredResults = sortProducts(filteredResults, sortCriteria);
                
                currentSearchResults = filteredResults;
                renderProducts(filteredResults);
                
                // Update results count
                const totalCount = originalCachedResults.length;
                $resultsCount.html('<p><strong>' + filteredResults.length + '</strong> products of <strong>' + totalCount + '</strong> match your criteria.</p>').show();
            }
        });
        
        // Handle minimum rating change (auto-trigger filter)
        $('#ps-min-rating').on('change', function() {
            if (originalCachedResults.length > 0) {
                // Re-apply all filters when minimum rating changes
                const excludeText = $('#ps-exclude-keywords').val();
                const includeText = $('#ps-search-query').val();
                const minRating = parseFloat($(this).val()) || null;
                const sortCriteria = $sortBy.val();
                
                let filteredResults = [...originalCachedResults];
                filteredResults = filterProducts(filteredResults, excludeText, includeText, minRating);
                filteredResults = sortProducts(filteredResults, sortCriteria);
                
                currentSearchResults = filteredResults;
                renderProducts(filteredResults);
                
                // Update results count
                const totalCount = originalCachedResults.length;
                $resultsCount.html('<p><strong>' + filteredResults.length + '</strong> products of <strong>' + totalCount + '</strong> match your criteria.</p>').show();
            }
        });
        
        // Handle Filter Cached Results button click
        $filterButton.on('click', function(e) {
            e.preventDefault();
            
            // Show that we're filtering cached results
            $loadingText.text('Filtering cached results...');
            $loading.show();
            
            // Get current filter values
            const excludeText = $('#ps-exclude-keywords').val();
            const includeText = $('#ps-search-query').val();
            const minRating = parseFloat($('#ps-min-rating').val()) || null;
            const sortCriteria = $sortBy.val();
            
            // Always filter from the original cached results
            if (originalCachedResults.length > 0) {
                let filteredResults = [...originalCachedResults];
                // Apply include, exclude, and rating filtering
                filteredResults = filterProducts(filteredResults, excludeText, includeText, minRating);
                // Sort the filtered results
                filteredResults = sortProducts(filteredResults, sortCriteria);
                // Update UI
                $loading.hide();
                const totalCount = originalCachedResults.length;
                $resultsCount.html('<p><strong>' + filteredResults.length + '</strong> products of <strong>' + totalCount + '</strong> match your criteria.</p>').show();
                renderProducts(filteredResults);
            } else {
                $loading.hide();
                $results.html('<div class="ps-no-results">No results to filter. Please perform a search first.</div>');
            }
        });
        
        // Handle Show All button click
        $showAllButton.on('click', function(e) {
            e.preventDefault();
            
            // Show that we're loading all cached results
            $loadingText.text('Loading all cached results...');
            $loading.show();
            
            // Clear all filter fields
            $('#ps-search-query').val('');
            $('#ps-exclude-keywords').val('');
            $('#ps-min-rating').val('4.0'); // Reset to default
            
            // Show all original cached results without any filters
            if (originalCachedResults.length > 0) {
                const sortCriteria = $sortBy.val();
                let allResults = [...originalCachedResults];
                // Only apply sorting, no filtering
                allResults = sortProducts(allResults, sortCriteria);
                // Update UI
                $loading.hide();
                const totalCount = originalCachedResults.length;
                $resultsCount.html('<p><strong>' + totalCount + '</strong> products.</p>').show();
                renderProducts(allResults);
            } else {
                $loading.hide();
                $results.html('<div class="ps-no-results">No cached results available. Please perform a search first.</div>');
            }
        });
        
        // Function to load cached results on page load
        function loadCachedResults() {
            const resultsContainer = document.getElementById('ps-results');
            if (!resultsContainer) return;
            // Show loading message
            resultsContainer.innerHTML = '<div class="ps-loading">Loading your last search results...</div>';
            // Get current filter values
            const sortByElem = document.getElementById('ps-sort-by');
            const countryElem = document.querySelector('input[name="country"]:checked');
            const sortBy = sortByElem ? sortByElem.value : '';
            const country = countryElem ? countryElem.value : 'us';
            // Make AJAX request to get cached results
            jQuery.ajax({
                url: psData.ajaxurl,
                type: 'POST',
                dataType: 'json',
                cache: false,
                data: {
                    action: 'ps_search',
                    nonce: psData.nonce,
                    query: '',
                    exclude: '',
                    sort_by: sortBy,
                    country: country,
                    filter_cached: 'true'
                },
                success: function(response) {
                    let products = [];
                    if (response.success) {
                        if (response.data && response.data.items) {
                            products = response.data.items;
                        } else if (response.items) {
                            products = response.items;
                        }
                    }
                    if (products.length > 0) {
                        // Store the original cached results for filtering
                        originalCachedResults = [...products];
                        
                        // Auto-apply search/exclude terms when loading cached results
                        const queryElem = document.getElementById('ps-search-query');
                        const excludeElem = document.getElementById('ps-exclude-keywords');
                        
                        // Populate the form fields with cached values
                        if (queryElem) {
                            if (response.data && response.data.query) {
                                queryElem.value = response.data.query;
                            } else if (response.query) {
                                queryElem.value = response.query;
                            }
                        }
                        
                        if (excludeElem) {
                            if (response.data && response.data.exclude) {
                                excludeElem.value = response.data.exclude;
                            } else if (response.exclude) {
                                excludeElem.value = response.exclude;
                            }
                        }
                        
                        // Auto-apply current filter values
                        const currentIncludeText = queryElem ? queryElem.value : '';
                        const currentExcludeText = excludeElem ? excludeElem.value : '';
                        const currentMinRating = parseFloat($('#ps-min-rating').val()) || null;
                        
                        let filteredProducts = [...products];
                        if (currentIncludeText || currentExcludeText || currentMinRating) {
                            filteredProducts = filterProducts(products, currentExcludeText, currentIncludeText, currentMinRating);
                        }
                        
                        const sortedProducts = sortProducts(filteredProducts, sortBy);
                        currentSearchResults = sortedProducts;
                        renderProducts(sortedProducts);
                        
                        // Update results count with new format
                        const totalCount = response.data && response.data.base_items_count ? response.data.base_items_count : products.length;
                        $resultsCount.html('<p><strong>' + filteredProducts.length + '</strong> products of <strong>' + totalCount + '</strong> match your criteria.</p>').show();
                        
                        if ($filterButton) $filterButton.prop('disabled', false);
                        if ($showAllButton) $showAllButton.show();
                    } else {
                        currentSearchResults = [];
                        originalCachedResults = [];
                        if ($filterButton) $filterButton.prop('disabled', true);
                        if ($showAllButton) $showAllButton.hide();
                        resultsContainer.innerHTML = '<div class="ps-no-results">No previous search results found.</div>';
                    }
                },
                error: function(xhr, status, error) {
                    if (xhr.status === 403) {
                        resultsContainer.innerHTML = '<div class="ps-error">Access forbidden. This could be due to a security check failure or session timeout. Please refresh the page and try again.</div>';
                    } else if (xhr.status === 500) {
                        resultsContainer.innerHTML = '<div class="ps-error">Server error. The search operation could not be completed. Please try again later or contact support.</div>';
                    } else {
                        resultsContainer.innerHTML = '<div class="ps-error">Error loading cached results. Please try searching again.</div>';
                    }
                }
            });
        }
                
        // Handle form submission
        document.getElementById('ps-search-form').addEventListener('submit', function(e) {
            e.preventDefault();
                    
            // Only proceed if the submit button was clicked
            const activeElement = document.activeElement;
            if (!activeElement || activeElement.type !== 'submit') {
                return;
            }

            const queryElem = document.getElementById('ps-search-query');
            const excludeKeywordsElem = document.getElementById('ps-exclude-keywords');
            const sortByElem = document.getElementById('ps-sort-by');
            const minRatingElem = document.getElementById('ps-min-rating');
            const resultsContainer = document.getElementById('ps-results');

            const query = queryElem ? queryElem.value.trim() : '';
            const excludeKeywords = excludeKeywordsElem ? excludeKeywordsElem.value.trim() : '';
            const sortBy = sortByElem ? sortByElem.value : '';
            const minRating = minRatingElem ? minRatingElem.value : '4.0';
            const countryElem = document.querySelector('input[name="country"]:checked');
            const country = countryElem ? countryElem.value : 'us';

            if (!query) {
                resultsContainer.innerHTML = '<div class="ps-error">Please enter search keywords.</div>';
                return;
                    }
                    
            // Verify psData is available
            if (!window.psData || !window.psData.ajaxurl || !window.psData.nonce) {
                console.error('psData is not properly initialized', window.psData);
                resultsContainer.innerHTML = '<div class="ps-error">Configuration error. Please refresh the page or contact support.</div>';
                return;
            }

            // Show loading message
            resultsContainer.innerHTML = '<div class="ps-loading">Searching...</div>';

            // Make AJAX request
            jQuery.ajax({
                url: psData.ajaxurl,
                type: 'POST',
                dataType: 'json',
                cache: false,
                data: {
                    action: 'ps_search',
                    nonce: psData.nonce,
                    query: query,
                    exclude: excludeKeywords,
                    sort_by: sortBy,
                    min_rating: minRating,
                    country: country,
                    filter_cached: 'false'
                },
                success: function(response) {
                    console.log('AJAX response (search):', response);
                    let products = [];
                    let baseItemsCount = 0;
                    
                    if (response.success) {
                        if (response.data && response.data.items) {
                            products = response.data.items;
                            baseItemsCount = response.data.base_items_count || products.length;
                        } else if (response.items) {
                            products = response.items;
                            baseItemsCount = response.base_items_count || products.length;
                        }
                    }
                    
                    if (products.length > 0) {
                        // Store the original results for filtering
                        originalCachedResults = [...products];
                        
                        // Auto-apply current filter values from the form
                        const currentIncludeText = query; // The search query is the include text
                        const currentExcludeText = excludeKeywords;
                        const currentMinRating = parseFloat(minRating) || null;
                        
                        let filteredProducts = [...products];
                        if (currentIncludeText || currentExcludeText || currentMinRating) {
                            filteredProducts = filterProducts(products, currentExcludeText, currentIncludeText, currentMinRating);
                        }
                        
                        const sortedProducts = sortProducts(filteredProducts, sortBy);
                        currentSearchResults = sortedProducts;
                        renderProducts(sortedProducts);
                        
                        // Update results count with new format
                        $resultsCount.html('<p><strong>' + filteredProducts.length + '</strong> products of <strong>' + baseItemsCount + '</strong> match your criteria.</p>').show();
                        
                        // Show filter and show all buttons when products are found
                        $('#ps-filter-button').show();
                        $('#ps-show-all-button').show();
                    } else {
                        resultsContainer.innerHTML = '<div class="ps-no-results">No products found.</div>';
                    }
                },
                error: function(xhr, status, error) {
                    console.log('AJAX error (search):', xhr.status, status, error);
                    console.log('Response text:', xhr.responseText);
                    
                    if (xhr.status === 403) {
                        resultsContainer.innerHTML = '<div class="ps-error">Access forbidden. This could be due to a security check failure or session timeout. Please refresh the page and try again.</div>';
                    } else if (xhr.status === 500) {
                        let errorMessage = 'Server error. The search operation could not be completed.';
                
                        // Try to extract more details from the response
                        try {
                            if (xhr.responseText) {
                                // First try to parse as JSON
                                try {
                                    const jsonResponse = JSON.parse(xhr.responseText);
                                    if (jsonResponse.message) {
                                        errorMessage += ' Error: ' + jsonResponse.message;
                                    }
                                } catch (e) {
                                    // Not JSON, try to extract message from HTML
                                    const match = xhr.responseText.match(/<b>Message<\/b>:\s*([^<]+)/);
                                    if (match && match[1]) {
                                        errorMessage += ' Error: ' + match[1].trim();
                    }
                }
                            }
                        } catch (e) {
                            console.error('Error parsing error response:', e);
                        }
                        
                        resultsContainer.innerHTML = '<div class="ps-error">' + errorMessage + '</div>';
                    } else {
                        resultsContainer.innerHTML = '<div class="ps-error">Error performing search. Please try again.</div>';
                    }
                }
            });
        });
    });
})(jQuery);