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
            
            // Log page initialization
            logToServer('Page Load: Document ready fired, initializing unit price sorting system', {
                hasAjaxUrl: !!(window.psData && window.psData.ajaxurl),
                hasNonce: !!(window.psData && window.psData.nonce),
                userAgent: navigator.userAgent.substring(0, 100)
            });
            
            loadCachedResults();
        } else {
            console.error('psData not available for loadCachedResults', window.psData);
            
            logToServer('Page Load: psData not available, cannot initialize unit price sorting', {
                psDataExists: !!window.psData,
                hasAjaxUrl: !!(window.psData && window.psData.ajaxurl),
                hasNonce: !!(window.psData && window.psData.nonce)
            });
            
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
        
        // Global variables
        let currentSearchResults = [];
        let originalCachedResults = [];
        let currentPage = 1;
        let lastSearchQuery = '';
        let lastSearchCountry = '';
        let searchCooldownActive = false;
        let searchCooldownTimer = null;
        let loadButtonCooldownActive = false;
        let loadButtonCooldownTimer = null;
        let savedDefaultSort = null;
        let isAfterLiveSearch = false;
        
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
         * Only sets country if no cached preference exists
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
                        
                        // Only set country if no cached preference has been applied
                        // Check if loadCachedResults has already set a country preference
                        const currentCountry = $('input[name="country"]:checked').val();
                        const hasExistingPreference = window.countrySetFromCache || false;
                        
                        logToServer('Country Detection: IP-based detection completed', {
                            detectedCountry: countryCode,
                            currentCountrySelection: currentCountry,
                            countrySetFromCache: hasExistingPreference,
                            willOverride: !hasExistingPreference && countryCode === 'ca'
                        });
                        
                        if (!hasExistingPreference) {
                            // Check if the detected country is supported in our selector
                            if (countryCode === 'ca') {
                                // Set Canada as selected
                                $('input[name="country"][value="ca"]').prop('checked', true);
                                console.log('Set country to CA based on IP detection');
                                logToServer('Country Detection: Set country to CA based on IP detection');
                            }
                            // For US and all other countries, keep the default (US)
                        } else {
                            console.log('Country already set from cached preference, not overriding');
                        }
                    }
                },
                error: function(error) {
                    console.log('Error detecting country from IP:', error);
                    logToServer('Country Detection: IP detection failed, falling back to browser language', {
                        error: error.statusText || 'Unknown error'
                    });
                    // Fall back to browser language detection
                    detectCountryFromBrowser();
                }
            });
        }
        
        /**
         * Fallback method to detect country from browser language
         * Only sets country if no cached preference exists
         */
        function detectCountryFromBrowser() {
            try {
                const language = (navigator.language || navigator.userLanguage || '').toLowerCase();
                console.log('Browser language:', language);
                
                // Only set country if no cached preference has been applied
                const hasExistingPreference = window.countrySetFromCache || false;
                const currentCountry = $('input[name="country"]:checked').val();
                
                logToServer('Country Detection: Browser language detection', {
                    browserLanguage: language,
                    currentCountrySelection: currentCountry,
                    countrySetFromCache: hasExistingPreference,
                    willOverride: !hasExistingPreference && (language === 'en-ca' || language === 'fr-ca')
                });
                
                if (!hasExistingPreference) {
                    // Check for Canadian English/French
                    if (language === 'en-ca' || language === 'fr-ca') {
                        $('input[name="country"][value="ca"]').prop('checked', true);
                        console.log('Set country to CA based on browser language');
                        logToServer('Country Detection: Set country to CA based on browser language');
                    }
                } else {
                    console.log('Country already set from cached preference, not overriding');
                }
            } catch (e) {
                console.log('Error detecting country from browser language:', e);
                logToServer('Country Detection: Browser language detection failed', {
                    error: e.message
                });
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
                    
                    // Debug: Log the original values to understand what we're working with
                    console.log(`Processing unit price for: ${processedItem.title.substring(0, 50)}...`);
                    console.log(`Original unit: "${processedItem.unit}", price_per_unit: "${processedItem.price_per_unit}", price_per_unit_value: ${pricePerUnitValue}`);
                    console.log(`Total price: $${processedItem.price_value}`);
                    
                    // Check if the price_per_unit_value seems unreasonable (likely the total price instead of per-unit)
                    // If price_per_unit_value is close to the total price, it's probably wrong
                    const totalPrice = parseFloat(processedItem.price_value) || 0;
                    const isUnreasonableUnitPrice = Math.abs(pricePerUnitValue - totalPrice) < (totalPrice * 0.1); // Within 10% of total price
                    
                    if (isUnreasonableUnitPrice && totalPrice > 0) {
                        console.log(`Unit price ${pricePerUnitValue} seems unreasonable (too close to total price ${totalPrice}). Attempting to recalculate.`);
                        
                        // Try to extract size from title to recalculate
                        const sizeInfo = extractSizeFromTitle(processedItem.title);
                        if (sizeInfo) {
                            // Recalculate the correct unit price
                            const correctPricePerUnit = totalPrice / sizeInfo.value;
                            pricePerUnitValue = correctPricePerUnit;
                            console.log(`Recalculated: $${totalPrice} ÷ ${sizeInfo.value}${sizeInfo.unit} = $${correctPricePerUnit.toFixed(4)} per ${sizeInfo.unit}`);
                        }
                    }
                    
                    // Check for known units that should be normalized to 100
                    if (unitLower === 'gram' || unitLower === 'grams' || unitLower === 'g') {
                        // If unit is already "100 grams", don't multiply again
                        if (!unitLower.includes('100')) {
                            processedItem.price_per_unit = (pricePerUnitValue * 100).toFixed(2);
                            processedItem.price_per_unit_value = pricePerUnitValue * 100;
                        }
                        processedItem.unit = '100 grams';
                    } else if (unitLower === 'ml' || unitLower === 'milliliter' || unitLower === 'milliliters' || unitLower === 'millilitre' || unitLower === 'millilitres') {
                        // If unit is already "100 ml", don't multiply again
                        if (!unitLower.includes('100')) {
                            processedItem.price_per_unit = (pricePerUnitValue * 100).toFixed(2);
                            processedItem.price_per_unit_value = pricePerUnitValue * 100;
                        }
                        processedItem.unit = '100 ml';
                    } else if (unitLower === 'oz' || unitLower === 'ounce' || unitLower === 'ounces' || unitLower === 'fl oz') {
                        // If unit is already "100 oz", don't multiply again
                        if (!unitLower.includes('100')) {
                            processedItem.price_per_unit = (pricePerUnitValue * 100).toFixed(2);
                            processedItem.price_per_unit_value = pricePerUnitValue * 100;
                        }
                        processedItem.unit = '100 oz';
                    }
                    
                    console.log(`Final unit: "${processedItem.unit}", price_per_unit: "${processedItem.price_per_unit}", price_per_unit_value: ${processedItem.price_per_unit_value}`);
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
                // Separate products with and without unit prices
                const itemsWithUnitPrice = [];
                const itemsWithoutUnitPrice = [];
                
                sortedItems.forEach(function(item) {
                    // Use the same logic as shouldDefaultToUnitPrice - check for valid price_per_unit_value
                    // Even if unit is "No unit", it still represents a valid unit price
                    if (item.price_per_unit && 
                        item.price_per_unit_value && 
                        parseFloat(item.price_per_unit_value) > 0 &&
                        item.price_per_unit !== '' &&
                        item.price_per_unit !== 'N/A') {
                        itemsWithUnitPrice.push(item);
                    } else {
                        itemsWithoutUnitPrice.push(item);
                    }
                });
                
                // Sort products with unit prices by unit price
                itemsWithUnitPrice.sort(function(a, b) {
                    const priceA = parseFloat(a.price_per_unit_value) || 0;
                    const priceB = parseFloat(b.price_per_unit_value) || 0;
                    return priceA - priceB;
                });
                
                // Debug logging for unit price sorting
                if (itemsWithUnitPrice.length > 0) {
                    console.log('Unit price sorting results:');
                    itemsWithUnitPrice.slice(0, 5).forEach(function(item, index) {
                        console.log(`${index + 1}. ${item.title.substring(0, 50)}... - $${item.price_per_unit_value}/unit (${item.unit})`);
                    });
                }
                
                // Sort products without unit prices by regular price
                itemsWithoutUnitPrice.sort(function(a, b) {
                    const priceA = parseFloat(a.price_value) || 0;
                    const priceB = parseFloat(b.price_value) || 0;
                    return priceA - priceB;
                });
                
                // Return unit price sorted items first, then regular price sorted items
                return [...itemsWithUnitPrice, ...itemsWithoutUnitPrice];
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
                filteredItems = filteredItems.filter(item => {
                    // Exclude products with no rating when minimum rating filter is applied
                    if (!item.rating_number || item.rating_number === '' || item.rating_number === 'N/A') {
                        return false; // Exclude products with no rating when filtering by rating
                    }
                    
                    const itemRating = parseFloat(item.rating_number);
                    
                    const shouldInclude = itemRating >= minRating;
                    if (!shouldInclude) {
                    }
                    return shouldInclude;
                });
            }
            
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
                
                // Note: Load more button visibility is now controlled by pagination URL availability
                // Don't automatically show it here
            } else {
                // Hide load more button when no results
                toggleLoadMoreButton(false);
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
                            display: flex;
                            align-items: baseline;
                            gap: 8px;
                        }
                        .ps-product-price {
                            font-weight: bold;
                            font-size: 1em; /* Reduced from 1.2em */
                            color: #e63946;
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
                        /* Animation for refreshed content after load more */
                        .ps-refreshed-content {
                            animation: ps-content-refresh 0.5s ease-in-out;
                        }
                        @keyframes ps-content-refresh {
                            0% { opacity: 0.7; transform: translateY(-5px); }
                            100% { opacity: 1; transform: translateY(0); }
                        }
                    </style>
                `);
            }
        }
        
        // Handle sort change (re-sort current results)
        $sortBy.on('change', function() {
            // Clear saved default sorting preference when user manually changes sort
            // This allows the system to recalculate the preference on the next search
            clearSavedDefaultSorting();
            
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
                if (!isAfterLiveSearch) {
                    $resultsCount.html('<p><strong>' + filteredResults.length + '</strong> products of <strong>' + totalCount + '</strong> match your criteria.</p>').show();
                }
            }
        });
        
        // Handle minimum rating change (auto-trigger filter)
        $('#ps-min-rating').on('change', function() {
            console.log('Rating dropdown changed to:', $(this).val());
            if (originalCachedResults.length > 0) {
                console.log('Processing rating change with', originalCachedResults.length, 'cached results');
                // Re-apply all filters when minimum rating changes
                const excludeText = $('#ps-exclude-keywords').val();
                const includeText = $('#ps-search-query').val();
                const minRating = parseFloat($(this).val()) || null;
                const sortCriteria = $sortBy.val();
                
                console.log('Filter criteria:', { excludeText, includeText, minRating, sortCriteria });
                
                let filteredResults = [...originalCachedResults];
                filteredResults = filterProducts(filteredResults, excludeText, includeText, minRating);
                filteredResults = sortProducts(filteredResults, sortCriteria);
                
                currentSearchResults = filteredResults;
                renderProducts(filteredResults);
                
                // Update results count
                const totalCount = originalCachedResults.length;
                if (!isAfterLiveSearch) {
                    $resultsCount.html('<p><strong>' + filteredResults.length + '</strong> products of <strong>' + totalCount + '</strong> match your criteria.</p>').show();
                }
            } else {
                console.log('No cached results available for rating filter');
            }
        });
        
        // Handle Filter Cached Results button click
        $filterButton.on('click', function(e) {
            e.preventDefault();
            
            // Reset the flag since filtering should restore normal message behavior
            isAfterLiveSearch = false;
            
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
                const sortByElem = document.getElementById('ps-sort-by');
                const currentSortBy = sortByElem ? sortByElem.value : 'price';
                const sortedProducts = sortProducts(filteredResults, currentSortBy);
                // Update UI
                $loading.hide();
                const totalCount = originalCachedResults.length;
                if (!isAfterLiveSearch) {
                    $resultsCount.html('<p><strong>' + sortedProducts.length + '</strong> products of <strong>' + totalCount + '</strong> match your criteria.</p>').show();
                }
                currentSearchResults = sortedProducts;
                renderProducts(sortedProducts);
            } else {
                $loading.hide();
                $results.html('<div class="ps-no-results">No results to filter. Please perform a search first.</div>');
            }
        });
        
        // Handle Show All button click
        $showAllButton.on('click', function(e) {
            e.preventDefault();
            
            // Reset the flag since showing all results should restore normal message behavior
            isAfterLiveSearch = false;
            
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
                if (!isAfterLiveSearch) {
                    $resultsCount.html('<p><strong>' + totalCount + '</strong> products.</p>').show();
                }
                currentSearchResults = allResults;
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
            
            // Log that we're loading cached results
            logToServer('Load Cached Results: Starting to load cached results on page refresh');
            
            // Show loading message
            resultsContainer.innerHTML = '<div class="ps-loading">Loading your last search results...</div>';
            // Get current filter values
            const sortByElem = document.getElementById('ps-sort-by');
            const countryElem = document.querySelector('input[name="country"]:checked');
            const sortBy = sortByElem ? sortByElem.value : 'price';
            const country = countryElem ? countryElem.value : 'us';
            
            logToServer('Load Cached Results: Request parameters', {
                sortBy: sortBy,
                country: country
            });
            
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
                    
                    logToServer('Load Cached Results: Received response', {
                        success: response.success,
                        productCount: products.length,
                        hasResponseData: !!(response.data),
                        hasItems: !!(response.items || (response.data && response.data.items))
                    });
                    
                    if (products.length > 0) {
                        // Store the original cached results for filtering
                        originalCachedResults = [...products];
                        
                        // Reset pagination state
                        currentPage = 1;
                        
                        // Reset flag since this is loading cached results, not a live search
                        isAfterLiveSearch = false;
                        
                        // Apply saved default sorting preference (don't recalculate from cached data)
                        const sortingChanged = applySavedDefaultSorting();
                        
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
                        
                        // Set search parameters for load more functionality AFTER populating form fields
                        const cachedCountryElem = document.querySelector('input[name="country"]:checked');
                        
                        // Set lastSearchQuery from response data, not from input field
                        if (response.data && response.data.query) {
                            lastSearchQuery = response.data.query;
                        } else if (response.query) {
                            lastSearchQuery = response.query;
                        }
                        
                        logToServer('Load Cached Results: Set lastSearchQuery for load more', {
                            lastSearchQuery: lastSearchQuery,
                            responseDataQuery: response.data ? response.data.query : null,
                            responseQuery: response.query || null
                        });
                        
                        if (cachedCountryElem) {
                            lastSearchCountry = cachedCountryElem.value;
                        }
                        
                        // Set the country radio button based on the cached search country
                        const cachedCountry = (response.data && response.data.country_code) || response.country_code;
                        if (cachedCountry) {
                            const countryRadio = document.querySelector(`input[name="country"][value="${cachedCountry}"]`);
                            if (countryRadio) {
                                countryRadio.checked = true;
                                // Set flag to prevent detectUserCountry from overriding this
                                window.countrySetFromCache = true;
                                logToServer('Load Cached Results: Set country radio button', {
                                    cachedCountry: cachedCountry,
                                    radioButtonFound: true
                                });
                            } else {
                                logToServer('Load Cached Results: Country radio button not found', {
                                    cachedCountry: cachedCountry,
                                    radioButtonFound: false
                                });
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
                        
                        // Get the current sort value (might have been changed by calculateAndSaveDefaultSorting)
                        const currentSortBy = sortByElem ? sortByElem.value : 'price';
                        const sortedProducts = sortProducts(filteredProducts, currentSortBy);
                        currentSearchResults = sortedProducts;
                        renderProducts(sortedProducts);
                        
                        // Update results count with new format for cached results
                        const totalCount = response.data && response.data.base_items_count ? response.data.base_items_count : products.length;
                        if (!isAfterLiveSearch) {
                            $resultsCount.html('<p><strong>' + sortedProducts.length + '</strong> products of <strong>' + totalCount + '</strong> match your criteria.</p>').show();
                        }
                        
                        if ($filterButton) $filterButton.prop('disabled', false);
                        if ($showAllButton) $showAllButton.show();
                        
                        // Check if pagination URLs are available for load more functionality
                        const paginationUrls = response.data && response.data.pagination_urls ? response.data.pagination_urls : {};
                        const hasPaginationUrls = paginationUrls && typeof paginationUrls === 'object' && 
                                                 (paginationUrls.page_2 || paginationUrls.page_3);
                        
                        logToServer('Load Cached Results: Pagination URLs check', {
                            hasPaginationUrls: hasPaginationUrls,
                            paginationUrls: paginationUrls,
                            paginationUrlsType: typeof paginationUrls,
                            paginationUrlsKeys: Object.keys(paginationUrls),
                            hasPage2: !!paginationUrls.page_2,
                            hasPage3: !!paginationUrls.page_3,
                            responsePaginationUrls: response.data ? response.data.pagination_urls : 'not found',
                            responseDataKeys: response.data ? Object.keys(response.data) : 'no response.data'
                        });
                        
                        // Show or hide load more button based on pagination availability
                        if (hasPaginationUrls) {
                            toggleLoadMoreButton(true);
                        } else {
                            toggleLoadMoreButton(false);
                        }
                        
                        logToServer('Load Cached Results: Successfully processed and rendered products', {
                            originalCount: products.length,
                            filteredCount: filteredProducts.length,
                            finalCount: sortedProducts.length,
                            sortingChanged: sortingChanged,
                            currentSortBy: currentSortBy
                        });
                    } else {
                        currentSearchResults = [];
                        originalCachedResults = [];
                        if ($filterButton) $filterButton.prop('disabled', true);
                        if ($showAllButton) $showAllButton.hide();
                        // For new visitors, don't show any message - just clear the container
                        resultsContainer.innerHTML = '';
                        
                        logToServer('Load Cached Results: No products found in cached results');
                    }
                },
                error: function(xhr, status, error) {
                    logToServer('Load Cached Results: AJAX error occurred', {
                        status: xhr.status,
                        statusText: status,
                        error: error
                    });
                    
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
                
        /**
         * Start the search cooldown timer
         * @param {HTMLElement} searchButton - The search button element
         */
        function startSearchCooldown(searchButton) {
            if (!searchButton) return;
            
            searchCooldownActive = true;
            let countdown = 5;
            const originalText = searchButton.textContent;
            
            // Clear any existing timer
            if (searchCooldownTimer) {
                clearInterval(searchCooldownTimer);
            }
            
            // Add cooldown class and disable button
            searchButton.classList.add('ps-cooldown');
            searchButton.disabled = true;
            
            // Also disable load buttons during search cooldown
            const $loadMoreButton = $('#ps-load-more-button');
            const $topLoadMoreButton = $('#ps-load-more-top-button');
            $loadMoreButton.prop('disabled', true);
            $topLoadMoreButton.prop('disabled', true);
            
            // Update button text with countdown
            searchButton.textContent = `Wait ${countdown}s`;
            
            // Start countdown timer
            searchCooldownTimer = setInterval(function() {
                countdown--;
                
                if (countdown > 0) {
                    searchButton.textContent = `Wait ${countdown}s`;
                } else {
                    // Reset search button state
                    searchButton.textContent = originalText;
                    searchButton.classList.remove('ps-cooldown');
                    searchButton.disabled = false;
                    searchCooldownActive = false;
                    
                    // Re-enable load buttons after search cooldown
                    $loadMoreButton.prop('disabled', false);
                    $topLoadMoreButton.prop('disabled', false);
                    
                    // Check if load buttons should be visible after cooldown
                    const $container = $('#ps-load-more-container');
                    if ($container.data('should-show') === true) {
                        toggleLoadMoreButton(true);
                    }
                    
                    // Clear timer
                    clearInterval(searchCooldownTimer);
                    searchCooldownTimer = null;
                }
            }, 1000);
        }

        /**
         * Start the load button cooldown timer that affects both load buttons and search
         */
        function startLoadButtonCooldown() {
            loadButtonCooldownActive = true;
            searchCooldownActive = true; // Also set search cooldown
            let countdown = 5;
            
            // Clear any existing timers
            if (loadButtonCooldownTimer) {
                clearInterval(loadButtonCooldownTimer);
            }
            if (searchCooldownTimer) {
                clearInterval(searchCooldownTimer);
            }
            
            // Get button elements
            const $loadMoreButton = $('#ps-load-more-button');
            const $topLoadMoreButton = $('#ps-load-more-top-button');
            const searchButton = document.querySelector('.ps-search-button');
            
            // Store original text
            const originalSearchText = searchButton ? searchButton.textContent : 'Search Amazon';
            const originalLoadText = $loadMoreButton.find('.ps-load-more-text').text() || 'Load More';
            
            // Disable all buttons and add cooldown classes
            if (searchButton) {
                searchButton.classList.add('ps-cooldown');
                searchButton.disabled = true;
            }
            $loadMoreButton.prop('disabled', true);
            $topLoadMoreButton.prop('disabled', true);
            
            // Update button texts with countdown
            function updateCountdown() {
                if (searchButton) {
                    searchButton.textContent = `Wait ${countdown}s`;
                }
                $loadMoreButton.find('.ps-load-more-text').text(`Wait ${countdown}s`);
                $topLoadMoreButton.text(`Wait ${countdown}s`);
            }
            
            updateCountdown();
            
            // Start countdown timer
            loadButtonCooldownTimer = setInterval(function() {
                countdown--;
                
                if (countdown > 0) {
                    updateCountdown();
                } else {
                    // Reset all button states
                    if (searchButton) {
                        searchButton.textContent = originalSearchText;
                        searchButton.classList.remove('ps-cooldown');
                        searchButton.disabled = false;
                    }
                    
                    $loadMoreButton.prop('disabled', false);
                    $loadMoreButton.find('.ps-load-more-text').text(originalLoadText);
                    
                    $topLoadMoreButton.prop('disabled', false);
                    $topLoadMoreButton.text(originalLoadText);
                    
                    // Reset cooldown flags
                    loadButtonCooldownActive = false;
                    searchCooldownActive = false;
                    
                    // Check if load buttons should be visible after cooldown
                    const $container = $('#ps-load-more-container');
                    if ($container.data('should-show') === true) {
                        toggleLoadMoreButton(true);
                    }
                    
                    // Clear timers
                    clearInterval(loadButtonCooldownTimer);
                    loadButtonCooldownTimer = null;
                    searchCooldownTimer = null;
                }
            }, 1000);
        }

        // Handle form submission
        document.getElementById('ps-search-form').addEventListener('submit', function(e) {
            e.preventDefault();
                    
            // Only proceed if the submit button was clicked
            const activeElement = document.activeElement;
            if (!activeElement || activeElement.type !== 'submit') {
                return;
            }

            // Check if cooldown is active
            if (searchCooldownActive || loadButtonCooldownActive) {
                return; // Prevent submission during cooldown
            }

            // Set flag to indicate we're performing a live search attempt
            // This should hide any existing result count messages regardless of search outcome
            isAfterLiveSearch = true;
            $resultsCount.hide();

            const queryElem = document.getElementById('ps-search-query');
            const excludeKeywordsElem = document.getElementById('ps-exclude-keywords');
            const sortByElem = document.getElementById('ps-sort-by');
            const minRatingElem = document.getElementById('ps-min-rating');
            const resultsContainer = document.getElementById('ps-results');
            const searchButton = document.querySelector('.ps-search-button');

            const query = queryElem ? queryElem.value.trim() : '';
            const excludeKeywords = excludeKeywordsElem ? excludeKeywordsElem.value.trim() : '';
            const sortBy = sortByElem ? sortByElem.value : '';
            const minRating = minRatingElem ? minRatingElem.value : '4.0';
            const countryElem = document.querySelector('input[name="country"]:checked');
            const country = countryElem ? countryElem.value : 'us';

            if (!query) {
                resultsContainer.innerHTML = '<div class="ps-error">Please enter search keywords.</div>';
                // Keep results count hidden since this is after a live search attempt
                $resultsCount.hide();
                return;
            }
            
            // Reset pagination state for new search
            currentPage = 1;
            lastSearchQuery = query;
            lastSearchCountry = country;
            
            // Verify psData is available
            if (!window.psData || !window.psData.ajaxurl || !window.psData.nonce) {
                console.error('psData is not properly initialized', window.psData);
                resultsContainer.innerHTML = '<div class="ps-error">Configuration error. Please refresh the page or contact support.</div>';
                // Keep results count hidden since this is after a live search attempt
                $resultsCount.hide();
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
                    let errorMessage = null;
                    
                    if (response.success) {
                        if (response.data && response.data.items) {
                            products = response.data.items;
                            baseItemsCount = response.data.base_items_count || products.length;
                        } else if (response.items) {
                            products = response.items;
                            baseItemsCount = response.base_items_count || products.length;
                        }
                    } else {
                        // Handle wp_send_json_error responses - message is in response.data.message
                        if (response.data && response.data.message) {
                            errorMessage = response.data.message;
                        } else if (response.message) {
                            errorMessage = response.message;
                        } else {
                            errorMessage = 'Search failed. Please try again.';
                        }
                    }
                    
                    // If there's an error message, display it instead of "No products found"
                    if (errorMessage) {
                        let errorHtml = '<div class="ps-error">' + errorMessage;
                        
                        // Add Amazon search link if available (for blocking errors)
                        if (response.data && response.data.amazon_search_url) {
                            console.log('DEBUG: Found amazon_search_url in error response:', response.data.amazon_search_url);
                            errorHtml += '<br><br>Or continue search on <a href="' + response.data.amazon_search_url + '" target="_blank" rel="noopener">' + response.data.amazon_search_url + '</a>';
                        } else {
                            console.log('DEBUG: No amazon_search_url found in error response. response.data:', response.data);
                            console.log('DEBUG: Full response structure:', response);
                            console.log('DEBUG: response.amazon_search_url:', response.amazon_search_url);
                            console.log('DEBUG: response.data && response.data.amazon_search_url:', response.data && response.data.amazon_search_url);
                            console.log('DEBUG: typeof response.data:', typeof response.data);
                            console.log('DEBUG: Object.keys(response):', Object.keys(response));
                            if (response.data) {
                                console.log('DEBUG: Object.keys(response.data):', Object.keys(response.data));
                            }
                        }
                        
                        errorHtml += '</div>';
                        resultsContainer.innerHTML = errorHtml;
                        
                        // Hide load more button when there's an error
                        toggleLoadMoreButton(false);
                        // Start cooldown timer
                        startSearchCooldown(searchButton);
                        // Keep results count hidden
                        $resultsCount.hide();
                        return;
                    }
                    
                    if (products.length > 0) {
                        // Store the original results for filtering
                        originalCachedResults = [...products];
                        
                        // Check if pagination URLs are available for load more functionality
                        const paginationUrls = response.data && response.data.pagination_urls ? response.data.pagination_urls : {};
                        const hasPaginationUrls = paginationUrls && typeof paginationUrls === 'object' && 
                                                 (paginationUrls.page_2 || paginationUrls.page_3);
                        
                        logToServer('Live Search: Pagination URLs check', {
                            hasPaginationUrls: hasPaginationUrls,
                            paginationUrls: paginationUrls,
                            paginationUrlsType: typeof paginationUrls,
                            paginationUrlsKeys: Object.keys(paginationUrls),
                            hasPage2: !!paginationUrls.page_2,
                            hasPage3: !!paginationUrls.page_3,
                            responsePaginationUrls: response.data ? response.data.pagination_urls : 'not found',
                            responseDataKeys: response.data ? Object.keys(response.data) : 'no response.data'
                        });
                        
                        // For live searches, always start with default "price" sorting and analyze fresh data
                        // Reset the sort dropdown to "price" first
                        if (sortByElem) {
                            sortByElem.value = 'price';
                        }
                        
                        // Check if we should default to unit price sorting based on current search results
                        logToServer('Live Search: Starting fresh analysis for unit price sorting');
                        const sortingChanged = calculateAndSaveDefaultSorting(products);
                        
                        // Auto-apply current filter values from the form
                        const currentIncludeText = query; // The search query is the include text
                        const currentExcludeText = excludeKeywords;
                        const currentMinRating = parseFloat(minRating) || null;
                        
                        let filteredProducts = [...products];
                        if (currentIncludeText || currentExcludeText || currentMinRating) {
                            filteredProducts = filterProducts(products, currentExcludeText, currentIncludeText, currentMinRating);
                        }
                        
                        // Get the current sort value (might have been changed by calculateAndSaveDefaultSorting)
                        const currentSortBy = sortByElem ? sortByElem.value : 'price';
                        const sortedProducts = sortProducts(filteredProducts, currentSortBy);
                        currentSearchResults = sortedProducts;
                        renderProducts(sortedProducts);
                        
                        // Reset the flag and show the results count for live searches
                        isAfterLiveSearch = false;
                        $resultsCount.html('<p><strong>' + sortedProducts.length + '</strong> products of <strong>' + baseItemsCount + '</strong> found.</p>').show();
                        
                        // Show filter and show all buttons when products are found
                        $('#ps-filter-button').show();
                        $('#ps-show-all-button').show();
                        
                        // Show or hide load more button based on pagination availability
                        if (hasPaginationUrls) {
                            toggleLoadMoreButton(true);
                        } else {
                            toggleLoadMoreButton(false);
                        }
                        
                        // Start cooldown timer after successful search
                        startSearchCooldown(searchButton);
                    } else {
                        resultsContainer.innerHTML = '<div class="ps-no-results">No products found.</div>';
                        // Hide load more button when no results
                        toggleLoadMoreButton(false);
                        // Start cooldown timer even when no products found
                        startSearchCooldown(searchButton);
                        // Keep results count hidden since this is after a live search attempt
                        $resultsCount.hide();
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
                    
                    // Start cooldown timer even on error to prevent spam
                    startSearchCooldown(searchButton);
                    // Keep results count hidden since this is after a live search attempt
                    $resultsCount.hide();
                }
            });
        });

        /**
         * Send debug data to server error log
         * @param {string} message - Debug message
         * @param {object} data - Additional data to log
         */
        function logToServer(message, data = {}) {
            if (window.psData && window.psData.ajaxurl && window.psData.nonce) {
                jQuery.ajax({
                    url: psData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ps_debug_log',
                        nonce: psData.nonce,
                        message: message,
                        data: JSON.stringify(data)
                    },
                    success: function(response) {
                        // Silent success
                    },
                    error: function(xhr, status, error) {
                        // Silent error - don't spam console
                    }
                });
            }
        }

        // Test the debug logging system immediately
        logToServer('Debug System Test: logToServer function is working', {
            timestamp: new Date().toISOString(),
            testMessage: 'This is a test to verify debug logging is functional'
        });

        // Debug function to check load more readiness
        function debugLoadMoreState() {
            console.log('Debug: Load More State Check', {
                currentSearchResults: currentSearchResults.length,
                originalCachedResults: originalCachedResults.length,
                currentPage: currentPage,
                lastSearchQuery: lastSearchQuery,
                lastSearchCountry: lastSearchCountry,
                searchCooldownActive: searchCooldownActive,
                loadButtonCooldownActive: loadButtonCooldownActive,
                loadButtonVisible: $('#ps-load-more-container').is(':visible'),
                topLoadButtonVisible: $('#ps-load-more-top-button').is(':visible'),
                shouldShow: $('#ps-load-more-container').data('should-show')
            });
        }
        
        // Make debug function available globally for testing
        window.debugLoadMoreState = debugLoadMoreState;

        /**
         * Check if more than 60% of the products have unit price data
         * @param {Array} items - Array of product items
         * @returns {boolean} - True if more than 60% have unit price data
         */
        function shouldDefaultToUnitPrice(items) {
            if (!items || items.length === 0) {
                logToServer('Unit Price Debug: No items provided', { itemCount: 0 });
                return false;
            }
            
            let itemsWithUnitPrice = 0;
            const debugItems = [];
            
            items.forEach(function(item, index) {
                // A product has unit price if it has a meaningful unit (not "No unit") 
                // AND a valid price_per_unit_value > 0
                const hasUnitPrice = item.price_per_unit && 
                    item.price_per_unit_value && 
                    parseFloat(item.price_per_unit_value) > 0 &&
                    item.price_per_unit !== '' &&
                    item.price_per_unit !== 'N/A' &&
                    item.price_per_unit !== 'No unit price' &&
                    item.unit &&
                    item.unit !== 'No unit' &&
                    item.unit !== '' &&
                    item.unit !== 'N/A';
                
                if (hasUnitPrice) {
                    itemsWithUnitPrice++;
                }
                
                // Log first 10 items for debugging
                if (index < 10) {
                    debugItems.push({
                        index: index,
                        title: item.title ? item.title.substring(0, 50) + '...' : 'No title',
                        price: item.price || 'No price',
                        price_value: item.price_value || 0,
                        price_per_unit: item.price_per_unit || 'No unit price',
                        unit: item.unit || 'No unit',
                        price_per_unit_value: item.price_per_unit_value || 0,
                        hasUnitPrice: hasUnitPrice
                    });
                }
            });
            
            const percentage = itemsWithUnitPrice / items.length;
            const shouldDefault = percentage > 0.6;
            
            // Log detailed analysis to server
            logToServer('Unit Price Analysis After Live Search', {
                totalItems: items.length,
                itemsWithUnitPrice: itemsWithUnitPrice,
                percentage: Math.round(percentage * 100),
                shouldDefaultToUnitPrice: shouldDefault,
                sampleItems: debugItems
            });
            
            console.log(`Unit price analysis: ${itemsWithUnitPrice}/${items.length} products (${Math.round(percentage * 100)}%) have unit price data`);
            
            return shouldDefault;
        }

        /**
         * Calculate and save the default sorting preference based on unit price availability
         * This should only be called after a live search, not on cached results or filters
         * @param {Array} items - Array of product items
         * @returns {boolean} - True if sorting was changed to unit price
         */
        function calculateAndSaveDefaultSorting(items) {
            const sortByElem = document.getElementById('ps-sort-by');
            if (!sortByElem) {
                logToServer('Unit Price Debug: Sort element not found');
                return false;
            }
            
            const currentSortValue = sortByElem.value;
            const shouldDefault = shouldDefaultToUnitPrice(items);
            
            // Save the calculated preference both in memory and localStorage
            savedDefaultSort = shouldDefault ? 'price_per_unit' : 'price';
            try {
                localStorage.setItem('ps_saved_default_sort', savedDefaultSort);
            } catch (e) {
                console.warn('Could not save default sort to localStorage:', e);
            }
            
            logToServer('Calculate and Save Default Sorting', {
                currentSortValue: currentSortValue,
                shouldDefaultToUnitPrice: shouldDefault,
                savedDefaultSort: savedDefaultSort,
                willChangeSorting: currentSortValue === 'price' && shouldDefault
            });
            
            // Only change default if currently set to 'price' (the default)
            if (currentSortValue === 'price' && shouldDefault) {
                console.log('Automatically switching to unit price sorting');
                sortByElem.value = 'price_per_unit';
                
                logToServer('Unit Price Sorting: Automatically switched to unit price sorting', {
                    previousValue: 'price',
                    newValue: 'price_per_unit',
                    itemCount: items.length
                });
                
                // Add visual feedback to let user know sorting was changed
                const sortContainer = sortByElem.parentElement;
                if (sortContainer) {
                    const feedback = document.createElement('div');
                    feedback.className = 'ps-auto-sort-feedback';
                    feedback.style.cssText = 'color: #28a745; font-size: 12px; margin-top: 2px; font-style: italic;';
                    feedback.textContent = 'Auto-sorted by unit price (most products have unit pricing)';
                    
                    // Remove any existing feedback
                    const existingFeedback = sortContainer.querySelector('.ps-auto-sort-feedback');
                    if (existingFeedback) {
                        existingFeedback.remove();
                    }
                    
                    sortContainer.appendChild(feedback);
                    
                    // Remove feedback after 5 seconds
                    setTimeout(function() {
                        if (feedback && feedback.parentElement) {
                            feedback.remove();
                        }
                    }, 5000);
                }
                
                return true; // Sorting was changed
            }
            
            return false; // Sorting was not changed
        }

        /**
         * Apply the saved default sorting preference
         * This is used for cached results and filters, without recalculating unit price analysis
         * @returns {boolean} - True if sorting was changed
         */
        function applySavedDefaultSorting() {
            const sortByElem = document.getElementById('ps-sort-by');
            if (!sortByElem) {
                logToServer('Apply Saved Default Sorting: Sort element not found');
                return false;
            }
            
            // Try to get saved preference from memory first, then localStorage
            if (!savedDefaultSort) {
                try {
                    savedDefaultSort = localStorage.getItem('ps_saved_default_sort');
                } catch (e) {
                    console.warn('Could not read default sort from localStorage:', e);
                }
            }
            
            if (!savedDefaultSort) {
                logToServer('Apply Saved Default Sorting: No saved preference found', {
                    hasSortElement: !!sortByElem,
                    savedDefaultSort: savedDefaultSort
                });
                return false;
            }
            
            const currentSortValue = sortByElem.value;
            
            logToServer('Apply Saved Default Sorting', {
                currentSortValue: currentSortValue,
                savedDefaultSort: savedDefaultSort,
                willChangeSorting: currentSortValue === 'price' && savedDefaultSort === 'price_per_unit'
            });
            
            // Only change default if currently set to 'price' and we have a saved preference for unit price
            if (currentSortValue === 'price' && savedDefaultSort === 'price_per_unit') {
                console.log('Applying saved unit price sorting preference');
                sortByElem.value = 'price_per_unit';
                
                logToServer('Unit Price Sorting: Applied saved unit price sorting preference', {
                    previousValue: 'price',
                    newValue: 'price_per_unit'
                });
                
                return true; // Sorting was changed
            }
            
            return false; // Sorting was not changed
        }

        /**
         * Clear the saved default sorting preference
         * This allows the system to recalculate the preference on the next search
         */
        function clearSavedDefaultSorting() {
            savedDefaultSort = null;
            try {
                localStorage.removeItem('ps_saved_default_sort');
                logToServer('Cleared saved default sorting preference');
            } catch (e) {
                console.warn('Could not clear default sort from localStorage:', e);
            }
        }

        /**
         * Set the default sorting based on unit price availability
         * @param {Array} items - Array of product items
         * @returns {boolean} - True if sorting was changed to unit price
         * @deprecated Use calculateAndSaveDefaultSorting for live searches or applySavedDefaultSorting for cached results
         */
        function setDefaultSorting(items) {
            // This function is kept for backward compatibility but should not be used
            // Use calculateAndSaveDefaultSorting for live searches or applySavedDefaultSorting for cached results
            return calculateAndSaveDefaultSorting(items);
        }

        // Handle Load More button click
        $('#ps-load-more-button').on('click', function(e) {
            e.preventDefault();
            
            // Check if any cooldown is active
            if (searchCooldownActive || loadButtonCooldownActive) {
                console.log('Load More: Blocked by cooldown', { searchCooldownActive, loadButtonCooldownActive });
                return; // Prevent action during cooldown
            }
            
            const $button = $(this);
            const $container = $('#ps-load-more-container');
            const $loadMoreText = $('.ps-load-more-text');
            const $loadMoreSpinner = $('.ps-load-more-spinner');
            const $topButton = $('#ps-load-more-top-button');
            
            // Show loading state
            $button.prop('disabled', true);
            $topButton.prop('disabled', true);
            $loadMoreText.hide();
            $loadMoreSpinner.show();
            
            // Get current search parameters
            const queryElem = document.getElementById('ps-search-query');
            const excludeElem = document.getElementById('ps-exclude-keywords');
            const sortByElem = document.getElementById('ps-sort-by');
            const minRatingElem = document.getElementById('ps-min-rating');
            const countryElem = document.querySelector('input[name="country"]:checked');
            
            const query = lastSearchQuery; // Use the actual search query that was performed, not current input value
            const exclude = excludeElem ? excludeElem.value.trim() : '';
            const sortBy = sortByElem ? sortByElem.value : 'price';
            const minRating = minRatingElem ? minRatingElem.value : '4.0';
            const country = countryElem ? countryElem.value : lastSearchCountry;
            
            // Increment page for next page
            const nextPage = currentPage + 1;
            
            // Debug logging for troubleshooting
            console.log('Load More: Button clicked with parameters:', {
                lastSearchQuery: lastSearchQuery,
                query: query,
                exclude: exclude,
                sortBy: sortBy,
                minRating: minRating,
                country: country,
                lastSearchCountry: lastSearchCountry,
                currentPage: currentPage,
                nextPage: nextPage,
                hasAjaxUrl: !!(window.psData && window.psData.ajaxurl),
                hasNonce: !!(window.psData && window.psData.nonce)
            });
            
            // Check if we have the required data for load more
            if (!query || !lastSearchQuery) {
                console.error('Load More: Missing search query', { query, lastSearchQuery });
                logToServer('Load More: Missing search query', { query, lastSearchQuery });
                
                // Reset button state and hide buttons
                $button.prop('disabled', false);
                $topButton.prop('disabled', false);
                $loadMoreText.show();
                $loadMoreSpinner.hide();
                toggleLoadMoreButton(false);
                return;
            }
            
            logToServer('Load More: Requesting page ' + nextPage, {
                query: query,
                country: country,
                currentPage: currentPage,
                nextPage: nextPage,
                lastSearchQuery: lastSearchQuery,
                queryFromInput: queryElem ? queryElem.value : null
            });
            
            // Make AJAX request for more results
            jQuery.ajax({
                url: psData.ajaxurl,
                type: 'POST',
                dataType: 'json',
                cache: false,
                data: {
                    action: 'ps_load_more',
                    nonce: psData.nonce,
                    query: query,
                    exclude: exclude,
                    sort_by: sortBy,
                    min_rating: minRating,
                    country: country,
                    page: nextPage
                },
                success: function(response) {
                    console.log('Load More: AJAX response received:', response);
                    
                    if (response.success && response.items && response.items.length > 0) {
                        // Backend has already merged, filtered, and sorted the complete dataset
                        const completeItems = response.items;
                        const newItemsCount = response.new_items_count || 0;
                        
                        console.log('Load More: Successfully received complete dataset with', completeItems.length, 'items (', newItemsCount, 'new items added)');
                        
                        // Update current page
                        currentPage = response.page_loaded || (currentPage + 1);
                        
                        // Replace current results with the complete sorted dataset from server
                        currentSearchResults = completeItems;
                        originalCachedResults = completeItems;  // Update cache reference too
                        
                        // Re-render all products with the complete dataset
                        renderProducts(currentSearchResults);
                        
                        // Add animation class to highlight that new content was loaded
                        setTimeout(() => {
                            $('.ps-product').addClass('ps-refreshed-content');
                            // Remove the animation class after animation completes
                            setTimeout(() => {
                                $('.ps-product').removeClass('ps-refreshed-content');
                            }, 500);
                        }, 100);
                        
                        // Update results count
                        const totalCount = response.base_items_count || completeItems.length;
                        const displayCount = completeItems.length;
                        // Always show the results count after load more
                        isAfterLiveSearch = false;
                        $resultsCount.html('<p><strong>' + displayCount + '</strong> products of <strong>' + totalCount + '</strong> match your criteria.</p>').show();
                        
                        // Check if more pages are available
                        console.log('Load More: Checking has_more_pages...', {
                            has_more_pages: response.has_more_pages,
                            page_loaded: response.page_loaded,
                            currentPage: currentPage,
                            responseHasMorePages: !!response.has_more_pages
                        });
                        
                        if (!response.has_more_pages) {
                            // Hide load more buttons when no more pages
                            console.log('Load More: No more pages available, hiding buttons');
                            toggleLoadMoreButton(false);
                        } else {
                            console.log('Load More: More pages available, keeping buttons visible');
                        }
                        
                        logToServer('Load More: Successfully loaded page ' + (response.page_loaded || currentPage), {
                            newItemsCount: newItemsCount,
                            totalItemsCount: totalCount,
                            displayItemsCount: displayCount,
                            hasMorePages: response.has_more_pages || false
                        });
                        
                        // Start shared cooldown after successful load more
                        startLoadButtonCooldown();
                    } else {
                        // Handle error response from backend
                        let errorMessage = 'No more results available.';
                        
                        // Check for specific error message from backend
                        if (response.message) {
                            errorMessage = response.message;
                        } else if (response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                        
                        // Create error HTML with potential Amazon link
                        let errorHtml = '<div class="ps-load-more-error" style="text-align: center; padding: 20px; color: #d63031;">' + errorMessage;
                        
                        // Add Amazon search link if available (for blocking errors)
                        if ((response.data && response.data.amazon_search_url) || response.amazon_search_url) {
                            const amazonUrl = (response.data && response.data.amazon_search_url) || response.amazon_search_url;
                            console.log('DEBUG: Found amazon_search_url in main search error response:', amazonUrl);
                            errorHtml += '<br><br>Or continue search on <a href="' + amazonUrl + '" target="_blank" rel="noopener">' + amazonUrl + '</a>';
                        } else {
                            console.log('DEBUG: No amazon_search_url found in main search error response. response.data:', response.data);
                            console.log('DEBUG: Full main search response structure:', response);
                            console.log('DEBUG: response.amazon_search_url:', response.amazon_search_url);
                            console.log('DEBUG: response.data && response.data.amazon_search_url:', response.data && response.data.amazon_search_url);
                            console.log('DEBUG: typeof response.data:', typeof response.data);
                            console.log('DEBUG: Object.keys(response):', Object.keys(response));
                            if (response.data) {
                                console.log('DEBUG: Object.keys(response.data):', Object.keys(response.data));
                            }
                        }
                        
                        errorHtml += '</div>';
                        
                        // Display error message
                        $container.after(errorHtml);
                        
                        // Hide load more buttons when no more results
                        console.log('Load More: No items received, hiding buttons', {
                            success: response.success,
                            hasItems: !!(response.items),
                            itemsLength: response.items ? response.items.length : 0,
                            response: response
                        });
                        toggleLoadMoreButton(false);
                        
                        logToServer('Load More: No more results available', {
                            response: response
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Load More: AJAX error occurred', {
                        status: status,
                        error: error,
                        page: nextPage,
                        xhr: xhr,
                        responseText: xhr.responseText
                    });
                    
                    logToServer('Load More: AJAX error', {
                        status: status,
                        error: error,
                        page: nextPage
                    });
                    
                    // Show error message
                    $container.after('<div class="ps-load-more-error" style="text-align: center; padding: 20px; color: #d63031;">Failed to load more results. Please try again.</div>');
                    
                    // Hide buttons on error
                    toggleLoadMoreButton(false);
                },
                complete: function() {
                    // Reset button state
                    $button.prop('disabled', false);
                    $topButton.prop('disabled', false);
                    $loadMoreText.show();
                    $loadMoreSpinner.hide();
                }
            });
        });

        /**
         * Show or hide the load more button based on search results
         * @param {boolean} show - Whether to show the button
         */
        function toggleLoadMoreButton(show) {
            const $container = $('#ps-load-more-container');
            const $topButton = $('#ps-load-more-top-button');
            
            // Store the intent to show buttons for after cooldown
            $container.data('should-show', show);
            
            if (show && !searchCooldownActive && !loadButtonCooldownActive) {
                $container.show();
                $topButton.show();
                // Remove any previous error/no-more messages
                $('.ps-load-more-error, .ps-no-more-results').remove();
            } else {
                $container.hide();
                $topButton.hide();
            }
        }

        // Handle Top Load More button click (same functionality as bottom button)
        $('#ps-load-more-top-button').on('click', function(e) {
            e.preventDefault();
            
            // Check if any cooldown is active
            if (searchCooldownActive || loadButtonCooldownActive) {
                return; // Prevent action during cooldown
            }
            
            // Trigger the same functionality as the bottom load more button
            $('#ps-load-more-button').trigger('click');
        });
    });
})(jQuery);