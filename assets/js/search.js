/**
 * Primates Shoppers JavaScript
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        if (window.psData && window.psData.ajaxurl && window.psData.nonce) {
            
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
        const $deliveryDropdownHeader = $('#ps-delivery-dropdown-header');
        const $deliveryDropdownContent = $('#ps-delivery-dropdown-content');
        const $deliveryDatesContainer = $('#ps-delivery-dates-container');
        const $deliveryAllCheckbox = $('#ps-delivery-all-checkbox');
        const $deliveryNoneCheckbox = $('#ps-delivery-none-checkbox');
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
        
        // Note: Platform restoration will be handled after loading cached results
        // based on what platforms are actually available in the data
        
        // Hide delivery filter by default until results are loaded
        $deliveryDropdownHeader.closest('tr').hide();
        
        // Global click handler to close delivery dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.ps-delivery-dropdown').length) {
                closeDeliveryDropdown();
            }
        });
        
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
        
        // Initialize delivery filter update flag (set to true for initial load)
        window.deliveryFilterNeedsUpdate = true;
        
        // Unit detection patterns for title extraction
        const unitPatterns = [
            // Milliliters (ml) - various formats including parentheses
            { regex: /\(\s*(\d+(?:\.\d+)?)\s*ml\s*\)/i, unit: 'ml', multiplier: 100 },
            { regex: /\b(\d+(?:\.\d+)?)\s*ml\b/i, unit: 'ml', multiplier: 100 },
            { regex: /\(\s*(\d+(?:\.\d+)?)\s*milliliter(s)?\s*\)/i, unit: 'ml', multiplier: 100 },
            { regex: /\b(\d+(?:\.\d+)?)\s*milliliter(s)?\b/i, unit: 'ml', multiplier: 100 },
            { regex: /\(\s*(\d+(?:\.\d+)?)\s*millilitre(s)?\s*\)/i, unit: 'ml', multiplier: 100 },
            { regex: /\b(\d+(?:\.\d+)?)\s*millilitre(s)?\b/i, unit: 'ml', multiplier: 100 },
            
            // Grams (g) - various formats including parentheses
            { regex: /\(\s*(\d+(?:\.\d+)?)\s*g\s*\)/i, unit: 'g', multiplier: 100 },
            { regex: /\b(\d+(?:\.\d+)?)\s*g\b/i, unit: 'g', multiplier: 100 },
            { regex: /\(\s*(\d+(?:\.\d+)?)\s*gram(s)?\s*\)/i, unit: 'g', multiplier: 100 },
            { regex: /\b(\d+(?:\.\d+)?)\s*gram(s)?\b/i, unit: 'g', multiplier: 100 },
            
            // Ounces (oz) - various formats including parentheses
            { regex: /\(\s*(\d+(?:\.\d+)?)\s*oz\s*\)/i, unit: 'oz', multiplier: 100 },
            { regex: /\b(\d+(?:\.\d+)?)\s*oz\b/i, unit: 'oz', multiplier: 100 },
            { regex: /\(\s*(\d+(?:\.\d+)?)\s*ounce(s)?\s*\)/i, unit: 'oz', multiplier: 100 },
            { regex: /\b(\d+(?:\.\d+)?)\s*ounce(s)?\b/i, unit: 'oz', multiplier: 100 },
            { regex: /\(\s*(\d+(?:\.\d+)?)\s*fl\s*oz\s*\)/i, unit: 'fl oz', multiplier: 100 },
            { regex: /\b(\d+(?:\.\d+)?)\s*fl\s*oz\b/i, unit: 'fl oz', multiplier: 100 },
            
            // Pounds (lb) - don't normalize to 100 for these larger units
            { regex: /\(\s*(\d+(?:\.\d+)?)\s*pound(s)?\s*\)/i, unit: 'lb', multiplier: 1 },
            { regex: /\b(\d+(?:\.\d+)?)\s*pound(s)?\b/i, unit: 'lb', multiplier: 1 },
            { regex: /\(\s*(\d+(?:\.\d+)?)\s*lb(s)?\s*\)/i, unit: 'lb', multiplier: 1 },
            { regex: /\b(\d+(?:\.\d+)?)\s*lb(s)?\b/i, unit: 'lb', multiplier: 1 },
            
            // Kilograms (kg) - don't normalize to 100 for these larger units
            { regex: /\(\s*(\d+(?:\.\d+)?)\s*kg\s*\)/i, unit: 'kg', multiplier: 1 },
            { regex: /\b(\d+(?:\.\d+)?)\s*kg\b/i, unit: 'kg', multiplier: 1 },
            { regex: /\(\s*(\d+(?:\.\d+)?)\s*kilogram(s)?\s*\)/i, unit: 'kg', multiplier: 1 },
            { regex: /\b(\d+(?:\.\d+)?)\s*kilogram(s)?\b/i, unit: 'kg', multiplier: 1 },
            
            // Liters (l) - don't normalize to 100 for these larger units
            { regex: /\(\s*(\d+(?:\.\d+)?)\s*l\s*\)/i, unit: 'l', multiplier: 1 },
            { regex: /\b(\d+(?:\.\d+)?)\s*l\b/i, unit: 'l', multiplier: 1 },
            { regex: /\(\s*(\d+(?:\.\d+)?)\s*liter(s)?\s*\)/i, unit: 'l', multiplier: 1 },
            { regex: /\b(\d+(?:\.\d+)?)\s*liter(s)?\b/i, unit: 'l', multiplier: 1 },
            { regex: /\(\s*(\d+(?:\.\d+)?)\s*litre(s)?\s*\)/i, unit: 'l', multiplier: 1 },
            { regex: /\b(\d+(?:\.\d+)?)\s*litre(s)?\b/i, unit: 'l', multiplier: 1 }
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

                                logToServer('Country Detection: Set country to CA based on IP detection');
                            }
                            // For US and all other countries, keep the default (US)
                        }
                    }
                },
                error: function(error) {
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

                        logToServer('Country Detection: Set country to CA based on browser language');
                    }
                }
            } catch (e) {
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
            // Simple test for debugging La Roche-Posay issue
            if (title.includes('La Roche-Posay')) {
                logToServer('UNIT_PRICE_DEBUG: Testing La Roche-Posay title', {
                    title: title,
                    testPattern: '/\\b(\\d+(?:\\.\\d+)?)\\s*ml\\b/i',
                    testMatch: title.match(/\b(\d+(?:\.\d+)?)\s*ml\b/i)
                });
            }
            
            for (let i = 0; i < unitPatterns.length; i++) {
                const pattern = unitPatterns[i];
                const match = title.match(pattern.regex);
                
                if (match) {
                    const result = {
                        value: parseFloat(match[1]),
                        unit: pattern.unit,
                        multiplier: pattern.multiplier
                    };
                    
                    // Only log successful extractions for eBay products
                    if (title.includes('La Roche-Posay') || title.includes('150')) {
                        logToServer('UNIT_PRICE_DEBUG: Size extracted', {
                            title: title,
                            extractedSize: result,
                            matchedPattern: pattern.regex.toString()
                        });
                    }
                    
                    return result;
                }
            }
            
            // Only log failures for specific problematic products
            if (title.includes('La Roche-Posay') || title.includes('150')) {
                logToServer('UNIT_PRICE_DEBUG: No size found in title', {
                    title: title
                });
            }
            
            return null;
        }
        
        /**
         * Process product items, normalize price per unit
         * @param {Array} items - Array of product items
         * @returns {Array} - Processed items
         */
        function processProductItems(items) {
            console.log('🔥 PROCESS DEBUG: processProductItems called', {
                totalItems: items.length,
                ebayItems: items.filter(i => i.platform === 'ebay').length
            });
            
            // First, check if most Amazon products have unit prices
            const amazonProducts = items.filter(item => item.platform === 'amazon');
            const shouldCalculateUnitPrices = amazonProducts.length === 0 || shouldDefaultToUnitPrice(amazonProducts);
            
            console.log('🔥 PROCESS DEBUG: shouldCalculateUnitPrices', shouldCalculateUnitPrices);
            
            logToServer('UNIT_PRICE_DEBUG: processProductItems', {
                totalItems: items.length,
                ebayItems: items.filter(i => i.platform === 'ebay').length,
                shouldCalculateUnitPrices: shouldCalculateUnitPrices
            });
            
            return items.map(function(item) {
                const processedItem = {...item}; // Create a copy to avoid mutating the original
                
                // Log initial state for specific eBay products only
                if (processedItem.platform === 'ebay' && (processedItem.title.includes('La Roche-Posay') || processedItem.title.includes('150'))) {
                    logToServer('UNIT_PRICE_DEBUG: eBay Product Initial State', {
                        title: processedItem.title,
                        price: processedItem.price,
                        price_value: processedItem.price_value,
                        unit: processedItem.unit,
                        price_per_unit: processedItem.price_per_unit,
                        price_per_unit_value: processedItem.price_per_unit_value
                    });
                }
                
                // Skip unit price handling if the backend already cleared the unit data
                if (processedItem.unit === '' && processedItem.price_per_unit === '' && processedItem.price_per_unit_value === 0) {
                    if (processedItem.platform === 'ebay') {
                        logToServer('Unit Price Processing: eBay Product - Backend cleared unit data, but continuing', {
                            title: processedItem.title
                        });
                    }
                    // Don't return early for eBay - we want to try calculating from title
                    if (processedItem.platform !== 'ebay') {
                        return processedItem;
                    }
                }
                
                // Check if we need to calculate unit price from title
                let hasPricePerUnit = processedItem.unit && processedItem.unit !== 'N/A' && processedItem.unit !== 'unit';
                let pricePerUnitValue = parseFloat(processedItem.price_per_unit) || 0;
                
                // If no price per unit or it's a placeholder, try to extract from title
                // For non-Amazon platforms, only calculate if most Amazon products have unit prices
                if (!hasPricePerUnit && processedItem.title && processedItem.price_value > 0) {
                    if (processedItem.platform === 'ebay') {
                        logToServer('Unit Price Processing: eBay Product - Checking title extraction', {
                            title: processedItem.title,
                            hasPricePerUnit: hasPricePerUnit,
                            shouldCalculateUnitPrices: shouldCalculateUnitPrices,
                            willAttemptExtraction: processedItem.platform === 'amazon' || shouldCalculateUnitPrices
                        });
                    }
                    
                    // For Amazon products, always try to calculate (existing behavior)
                    // For other platforms, only calculate if most Amazon products have unit prices
                    if (processedItem.platform === 'amazon' || shouldCalculateUnitPrices) {
                        const sizeInfo = extractSizeFromTitle(processedItem.title);
                        
                        if (sizeInfo) {
                            // Calculate price per unit based on extracted size
                            // First, get the price per single unit (e.g., per 1ml)
                            const pricePerSingleUnit = processedItem.price_value / sizeInfo.value;
                            
                            // Then calculate the price per 100 units
                            const normalizedPrice = pricePerSingleUnit * sizeInfo.multiplier;
                            
                            // Format for display with proper decimal places and currency
                            let currencySymbol = processedItem.price.match(/^(C \$|[^\d\s]+)/)?.[0] || '$';
                            // Clean up Canadian currency symbol from "C $" to just "$"
                            if (currencySymbol === 'C $') {
                                currencySymbol = '$';
                            }
                            const unitDisplay = sizeInfo.unit === 'ml' ? '100 ml' : 
                                                sizeInfo.unit === 'g' ? '100 grams' : 
                                                sizeInfo.unit === 'oz' ? '100 oz' : 
                                                sizeInfo.unit === 'fl oz' ? '100 fl oz' : 
                                                sizeInfo.unit === 'lb' ? 'lb' : 
                                                sizeInfo.unit === 'kg' ? 'kg' : 
                                                sizeInfo.unit === 'l' ? 'liter' : 
                                                sizeInfo.unit;
                            
                            processedItem.price_per_unit = `${currencySymbol}${normalizedPrice.toFixed(2)}/${unitDisplay}`;
                            processedItem.price_per_unit_value = normalizedPrice; // Update the value used for sorting
                            processedItem.unit = unitDisplay;
                            
                            // Only log for specific products to debug
                            if (processedItem.title.includes('La Roche-Posay') || processedItem.title.includes('150')) {
                                logToServer('UNIT_PRICE_DEBUG: Unit Price Calculated', {
                                title: processedItem.title,
                                extractedSize: sizeInfo.value + sizeInfo.unit,
                                unitPrice: processedItem.price_per_unit,
                                calculation: `${processedItem.price_value} ÷ ${sizeInfo.value} × ${sizeInfo.multiplier} = ${normalizedPrice.toFixed(2)}`
                            });
                            }
                            
                            pricePerUnitValue = normalizedPrice;
                            hasPricePerUnit = true;
                        } else {
                            logToServer('UNIT_PRICE_DEBUG: No size info found for product', {
                                platform: processedItem.platform,
                                title: processedItem.title
                            });
                        }
                    } else {
                        logToServer('UNIT_PRICE_DEBUG: Skipping unit price calculation', {
                            platform: processedItem.platform,
                            title: processedItem.title,
                            isAmazon: processedItem.platform === 'amazon',
                            shouldCalculateUnitPrices: shouldCalculateUnitPrices
                        });
                    }
                }

                // Normalize price per unit to 100 units
                // Only normalize for Amazon products or when most Amazon products have unit prices
                if (hasPricePerUnit && (processedItem.platform === 'amazon' || shouldCalculateUnitPrices)) {
                    if (processedItem.platform === 'ebay') {
                        logToServer('UNIT_PRICE_DEBUG: eBay Product - Entering normalization', {
                            title: processedItem.title,
                            hasPricePerUnit: hasPricePerUnit,
                            unit: processedItem.unit,
                            pricePerUnitValue: pricePerUnitValue
                        });
                    }
                    const unitLower = processedItem.unit.toLowerCase();
                    

                    
                    // Check if the price_per_unit_value seems unreasonable (likely the total price instead of per-unit)
                    // If price_per_unit_value is close to the total price, it's probably wrong
                    const totalPrice = parseFloat(processedItem.price_value) || 0;
                    const isUnreasonableUnitPrice = Math.abs(pricePerUnitValue - totalPrice) < (totalPrice * 0.1); // Within 10% of total price
                    
                    if (isUnreasonableUnitPrice && totalPrice > 0) {
                        // Try to extract size from title to recalculate
                        const sizeInfo = extractSizeFromTitle(processedItem.title);
                        if (sizeInfo) {
                            // Recalculate the correct unit price
                            const correctPricePerUnit = totalPrice / sizeInfo.value;
                            pricePerUnitValue = correctPricePerUnit;
                        }
                    }
                    
                    // Get currency symbol from the product price
                    let currencySymbol = processedItem.price.match(/^(C \$|[^\d\s]+)/)?.[0] || '$';
                    // Clean up Canadian currency symbol from "C $" to just "$"
                    if (currencySymbol === 'C $') {
                        currencySymbol = '$';
                    }
                    
                    // Check for known units that should be normalized to 100
                    if (unitLower === 'gram' || unitLower === 'grams' || unitLower === 'g') {
                        // If unit is already "100 grams", don't multiply again
                        if (!unitLower.includes('100')) {
                            const normalizedValue = pricePerUnitValue * 100;
                            processedItem.price_per_unit = `${currencySymbol}${normalizedValue.toFixed(2)}/100 grams`;
                            processedItem.price_per_unit_value = normalizedValue;
                        }
                        processedItem.unit = '100 grams';
                    } else if (unitLower === 'ml' || unitLower === 'milliliter' || unitLower === 'milliliters' || unitLower === 'millilitre' || unitLower === 'millilitres') {
                        // If unit is already "100 ml", don't multiply again
                        if (!unitLower.includes('100')) {
                            const normalizedValue = pricePerUnitValue * 100;
                            processedItem.price_per_unit = `${currencySymbol}${normalizedValue.toFixed(2)}/100 ml`;
                            processedItem.price_per_unit_value = normalizedValue;
                        }
                        processedItem.unit = '100 ml';
                    } else if (unitLower === 'oz' || unitLower === 'ounce' || unitLower === 'ounces') {
                        // If unit is already "100 oz", don't multiply again
                        if (!unitLower.includes('100')) {
                            const normalizedValue = pricePerUnitValue * 100;
                            processedItem.price_per_unit = `${currencySymbol}${normalizedValue.toFixed(2)}/100 oz`;
                            processedItem.price_per_unit_value = normalizedValue;
                        }
                        processedItem.unit = '100 oz';
                    } else if (unitLower === 'fl oz') {
                        // If unit is already "100 fl oz", don't multiply again
                        if (!unitLower.includes('100')) {
                            const normalizedValue = pricePerUnitValue * 100;
                            processedItem.price_per_unit = `${currencySymbol}${normalizedValue.toFixed(2)}/100 fl oz`;
                            processedItem.price_per_unit_value = normalizedValue;
                        }
                        processedItem.unit = '100 fl oz';
                    } else if (unitLower === 'lb' || unitLower === 'lbs' || unitLower === 'pound' || unitLower === 'pounds') {
                        // Don't normalize pounds - keep as per pound
                        processedItem.price_per_unit = `${currencySymbol}${pricePerUnitValue.toFixed(2)}/lb`;
                        processedItem.price_per_unit_value = pricePerUnitValue;
                        processedItem.unit = 'lb';
                    } else if (unitLower === 'kg' || unitLower === 'kilogram' || unitLower === 'kilograms') {
                        // Don't normalize kilograms - keep as per kg
                        processedItem.price_per_unit = `${currencySymbol}${pricePerUnitValue.toFixed(2)}/kg`;
                        processedItem.price_per_unit_value = pricePerUnitValue;
                        processedItem.unit = 'kg';
                    } else if (unitLower === 'l' || unitLower === 'liter' || unitLower === 'liters' || unitLower === 'litre' || unitLower === 'litres') {
                        // Don't normalize liters - keep as per liter
                        processedItem.price_per_unit = `${currencySymbol}${pricePerUnitValue.toFixed(2)}/liter`;
                        processedItem.price_per_unit_value = pricePerUnitValue;
                        processedItem.unit = 'liter';
                    }
                    

                }
                
                // Log final state for specific eBay products only
                if (processedItem.platform === 'ebay' && (processedItem.title.includes('La Roche-Posay') || processedItem.title.includes('150'))) {
                    logToServer('UNIT_PRICE_DEBUG: eBay Product Final State', {
                        title: processedItem.title,
                        price_per_unit: processedItem.price_per_unit,
                        unit: processedItem.unit,
                        price_per_unit_value: processedItem.price_per_unit_value
                    });
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
            console.log('🔥 SORT DEBUG: sortProducts called', {
                itemCount: items.length,
                sortBy: sortBy,
                ebayItems: items.filter(i => i.platform === 'ebay').length,
                isApplyingSavedSorting: window.isApplyingSavedSorting
            });
            
            // Process items for unit prices before sorting
            const processedItems = processProductItems(items);
            const sortedItems = [...processedItems]; // Create a copy to avoid mutating the original
            
            if (sortBy === 'price') {

                sortedItems.sort(function(a, b) {
                    const priceA = parseFloat(a.price_value) || 0;
                    const priceB = parseFloat(b.price_value) || 0;
                    return priceA - priceB;
                });

            } else if (sortBy === 'price_per_unit') {
                console.log('🔥 SORT DEBUG: price_per_unit sorting triggered');
                
                // DEBUG: Log the structure of the first few items to understand what fields are available
                const firstFewItems = sortedItems.slice(0, 3);
                console.log('🔥 DEBUG: First 3 items structure:', firstFewItems.map(item => ({
                    title: item.title ? item.title.substring(0, 50) + '...' : 'NO TITLE',
                    price: item.price,
                    price_value: item.price_value,
                    price_per_unit: item.price_per_unit,
                    price_per_unit_value: item.price_per_unit_value,
                    unit: item.unit,
                    platform: item.platform,
                    allKeys: Object.keys(item)
                })));
                
                // Separate products with and without unit prices
                const itemsWithUnitPrice = [];
                const itemsWithoutUnitPrice = [];
                
                sortedItems.forEach(function(item) {
                    // Use the same logic as shouldDefaultToUnitPrice - check for valid price_per_unit_value
                    // Even if unit is "No unit", it still represents a valid unit price
                    const hasValidUnitPrice = item.price_per_unit && 
                        item.price_per_unit_value && 
                        parseFloat(item.price_per_unit_value) > 0 &&
                        item.price_per_unit !== '' &&
                        item.price_per_unit !== 'N/A';
                    
                    // Only log for specific eBay products to debug
                    if (item.platform === 'ebay' && (item.title.includes('La Roche-Posay') || item.title.includes('150'))) {
                        logToServer('UNIT_PRICE_DEBUG: Categorizing eBay item for sorting', {
                            title: item.title,
                            price_per_unit: item.price_per_unit,
                            price_per_unit_value: item.price_per_unit_value,
                            unit: item.unit,
                            hasValidUnitPrice: hasValidUnitPrice,
                            category: hasValidUnitPrice ? 'WITH_UNIT_PRICE' : 'WITHOUT_UNIT_PRICE'
                        });
                    }
                    
                    if (hasValidUnitPrice) {
                        itemsWithUnitPrice.push(item);
                    } else {
                        itemsWithoutUnitPrice.push(item);
                    }
                });
                
                // Log actual unit price values for debugging
                const sampleUnitPrices = itemsWithUnitPrice.slice(0, 5).map(item => ({
                    title: item.title.substring(0, 50) + '...',
                    price_per_unit: item.price_per_unit,
                    price_per_unit_value: item.price_per_unit_value,
                    unit: item.unit,
                    platform: item.platform
                }));
                
                console.log('🔥 UNIT PRICE DEBUG: Sample unit prices before sorting', sampleUnitPrices);
                
                logToServer('UNIT_PRICE_DEBUG: Sorting result', {
                    itemsWithUnitPrice: itemsWithUnitPrice.length,
                    itemsWithoutUnitPrice: itemsWithoutUnitPrice.length,
                    ebayItemsWithUnitPrice: itemsWithUnitPrice.filter(i => i.platform === 'ebay').length,
                    sampleUnitPrices: sampleUnitPrices
                });
                
                // Sort products with unit prices by unit price
                itemsWithUnitPrice.sort(function(a, b) {
                    const priceA = parseFloat(a.price_per_unit_value) || 0;
                    const priceB = parseFloat(b.price_per_unit_value) || 0;
                    return priceA - priceB;
                });
                
                // Log sorted unit prices to verify sorting worked
                const sortedSampleUnitPrices = itemsWithUnitPrice.slice(0, 5).map(item => ({
                    title: item.title.substring(0, 50) + '...',
                    price_per_unit: item.price_per_unit,
                    price_per_unit_value: item.price_per_unit_value,
                    unit: item.unit,
                    platform: item.platform
                }));
                
                console.log('🔥 UNIT PRICE DEBUG: Sample unit prices AFTER sorting', sortedSampleUnitPrices);
                
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
            if (minRating !== null) {
                const beforeRatingFilter = filteredItems.length;
                filteredItems = filteredItems.filter(item => {
                    // Include all products when "All ratings" is selected (minRating = 0)
                    if (minRating === 0) {
                        return true;
                    }
                    
                    // Exclude products with no rating when minimum rating filter is applied
                    if (!item.rating_number || item.rating_number === '' || item.rating_number === 'N/A') {
                        return false; // Exclude products with no rating when filtering by rating
                    }
                    
                    let itemRating;
                    
                    // Both eBay and Amazon ratings are already in star format
                    // eBay backend already converts percentages to stars (95% → 4.5 stars)
                    itemRating = parseFloat(item.rating_number);
                    
                    const shouldInclude = itemRating >= minRating;
                    return shouldInclude;
                });
                
                // Rating filter applied successfully
            }
            
            return filteredItems;
        }
        
        /**
         * Extract platform-specific delivery dates from product items (fallback function)
         * @param {Array} items - Array of product items
         * @returns {Object} - Object with platform keys and arrays of delivery dates
         */
        function extractPlatformDeliveryDatesFromItems(items) {
            if (!items || !Array.isArray(items)) return {};
            
            const platformDates = {};
            const platformsWithUnspecified = {};
            
            items.forEach(item => {
                if (item.platform) {
                    const platform = item.platform;
                    const deliveryText = item.delivery_time;
                    
                    // Initialize platform array if not exists
                    if (!platformDates[platform]) {
                        platformDates[platform] = [];
                    }
                    
                    // Track platforms that have products without delivery info
                    if (!deliveryText || deliveryText.trim() === '' || deliveryText === 'N/A') {
                        platformsWithUnspecified[platform] = true;
                        return;
                    }
                    
                    // Extract specific delivery dates using regex patterns
                    const dateMatches = deliveryText.match(/\b(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun)\w*,?\s+[A-Z][a-z]{2,8}\s+\d{1,2}\b/g);
                    
                    if (dateMatches) {
                        dateMatches.forEach(date => {
                            // Clean up the date string
                            const cleanDate = date.replace(/^,?\s*/, '').replace(/,?\s*$/, '');
                            if (!platformDates[platform].includes(cleanDate)) {
                                platformDates[platform].push(cleanDate);
                            }
                        });
                    } else {
                        // If no specific date pattern found, use the first line of delivery text
                        const firstLine = deliveryText.trim().split('\n')[0];
                        if (firstLine && firstLine !== 'N/A' && !platformDates[platform].includes(firstLine)) {
                            platformDates[platform].push(firstLine);
                        }
                    }
                }
            });
            
            // Sort dates for each platform by actual date values
            Object.keys(platformDates).forEach(platform => {
                const dates = platformDates[platform];
                
                // Create array of date objects for sorting
                const dateObjects = dates.map(dateString => {
                    let parsedDate = null;
                    let dayNumber = null;
                    let monthNumber = null;
                    
                    // Extract day and month from date string (e.g., "Monday, Dec 2" -> day: 2, month: 12)
                    const dayMatch = dateString.match(/\b(\d{1,2})\b/);
                    if (dayMatch) {
                        dayNumber = parseInt(dayMatch[1]);
                        
                        // Extract month name and convert to number
                        const monthNames = {
                            'jan': 1, 'january': 1,
                            'feb': 2, 'february': 2,
                            'mar': 3, 'march': 3,
                            'apr': 4, 'april': 4,
                            'may': 5,
                            'jun': 6, 'june': 6,
                            'jul': 7, 'july': 7,
                            'aug': 8, 'august': 8,
                            'sep': 9, 'september': 9,
                            'oct': 10, 'october': 10,
                            'nov': 11, 'november': 11,
                            'dec': 12, 'december': 12
                        };
                        
                        for (const [monthName, monthNum] of Object.entries(monthNames)) {
                            if (dateString.toLowerCase().includes(monthName)) {
                                monthNumber = monthNum;
                                break;
                            }
                        }
                        
                        // Try to create a proper date object for this year
                        if (monthNumber !== null) {
                            const currentYear = new Date().getFullYear();
                            try {
                                parsedDate = new Date(currentYear, monthNumber - 1, dayNumber);
                                
                                // If the created date is in the past, assume it's for next year
                                const now = new Date();
                                if (parsedDate < now) {
                                    parsedDate = new Date(currentYear + 1, monthNumber - 1, dayNumber);
                                }
                            } catch (e) {
                                parsedDate = null;
                            }
                        }
                    }
                    
                    return {
                        original: dateString,
                        parsedDate: parsedDate,
                        dayNumber: dayNumber,
                        monthNumber: monthNumber
                    };
                });
                
                // Sort by parsed date first, then by month/day, then alphabetically
                dateObjects.sort((a, b) => {
                    // If both have parsed dates, sort by those
                    if (a.parsedDate && b.parsedDate) {
                        return a.parsedDate.getTime() - b.parsedDate.getTime();
                    }
                    
                    // If both have month and day numbers, sort by those
                    if (a.monthNumber !== null && b.monthNumber !== null && 
                        a.dayNumber !== null && b.dayNumber !== null) {
                        
                        const monthDiff = a.monthNumber - b.monthNumber;
                        if (monthDiff !== 0) {
                            return monthDiff;
                        }
                        return a.dayNumber - b.dayNumber;
                    }
                    
                    // If both have day numbers only, sort by those
                    if (a.dayNumber !== null && b.dayNumber !== null) {
                        return a.dayNumber - b.dayNumber;
                    }
                    
                    // If one has day number and other doesn't, prioritize the one with day number
                    if (a.dayNumber !== null && b.dayNumber === null) {
                        return -1;
                    }
                    if (a.dayNumber === null && b.dayNumber !== null) {
                        return 1;
                    }
                    
                    // Fallback to alphabetical sorting
                    return a.original.localeCompare(b.original);
                });
                
                // Extract the sorted original date strings
                platformDates[platform] = dateObjects.map(dateObj => dateObj.original);
            });
            
            // Add "unspecified" option to platforms that have products without delivery info
            Object.keys(platformsWithUnspecified).forEach(platform => {
                if (platformsWithUnspecified[platform]) {
                    if (!platformDates[platform]) {
                        platformDates[platform] = [];
                    }
                    platformDates[platform].push('unspecified');
                }
            });
            

            return platformDates;
        }

        /**
         * Extract distinct delivery dates from product items
         * @param {Array} items - Array of product items
         * @returns {Array} - Array of unique delivery date strings
         */
        function extractDeliveryDates(items) {
            if (!items || !Array.isArray(items)) return [];
            
            const deliveryDates = new Set();
            
            items.forEach(item => {
                if (item.delivery_time) {
                    // Extract delivery dates from delivery_time text
                    const deliveryText = item.delivery_time;
                    
                    // Look for date patterns like "Monday, Dec 2", "Tuesday, Dec 3", etc.
                    const dateMatches = deliveryText.match(/\b(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun)\w*,?\s+[A-Z][a-z]{2,8}\s+\d{1,2}\b/g);
                    
                    if (dateMatches) {
                        dateMatches.forEach(date => {
                            // Clean up the date string
                            const cleanDate = date.replace(/^,?\s*/, '').replace(/,?\s*$/, '');
                            deliveryDates.add(cleanDate);
                        });
                    } else {
                        // If no specific date pattern found, add the full delivery text as an option
                        const cleanDeliveryText = deliveryText.trim().split('\n')[0]; // Take first line only
                        if (cleanDeliveryText && cleanDeliveryText !== 'N/A') {
                            deliveryDates.add(cleanDeliveryText);
                        }
                    }
                }
            });
            
            const uniqueDates = Array.from(deliveryDates);
            
            // Sort dates by actual date values
            const dateObjects = uniqueDates.map(dateString => {
                let parsedDate = null;
                let dayNumber = null;
                let monthNumber = null;
                
                // Extract day and month from date string (e.g., "Monday, Dec 2" -> day: 2, month: 12)
                const dayMatch = dateString.match(/\b(\d{1,2})\b/);
                if (dayMatch) {
                    dayNumber = parseInt(dayMatch[1]);
                    
                    // Extract month name and convert to number
                    const monthNames = {
                        'jan': 1, 'january': 1,
                        'feb': 2, 'february': 2,
                        'mar': 3, 'march': 3,
                        'apr': 4, 'april': 4,
                        'may': 5,
                        'jun': 6, 'june': 6,
                        'jul': 7, 'july': 7,
                        'aug': 8, 'august': 8,
                        'sep': 9, 'september': 9,
                        'oct': 10, 'october': 10,
                        'nov': 11, 'november': 11,
                        'dec': 12, 'december': 12
                    };
                    
                    for (const [monthName, monthNum] of Object.entries(monthNames)) {
                        if (dateString.toLowerCase().includes(monthName)) {
                            monthNumber = monthNum;
                            break;
                        }
                    }
                    
                    // Try to create a proper date object for this year
                    if (monthNumber !== null) {
                        const currentYear = new Date().getFullYear();
                        try {
                            parsedDate = new Date(currentYear, monthNumber - 1, dayNumber);
                            
                            // If the created date is in the past, assume it's for next year
                            const now = new Date();
                            if (parsedDate < now) {
                                parsedDate = new Date(currentYear + 1, monthNumber - 1, dayNumber);
                            }
                        } catch (e) {
                            parsedDate = null;
                        }
                    }
                }
                
                return {
                    original: dateString,
                    parsedDate: parsedDate,
                    dayNumber: dayNumber,
                    monthNumber: monthNumber
                };
            });
            
            // Sort by parsed date first, then by month/day, then alphabetically
            dateObjects.sort((a, b) => {
                // If both have parsed dates, sort by those
                if (a.parsedDate && b.parsedDate) {
                    return a.parsedDate.getTime() - b.parsedDate.getTime();
                }
                
                // If both have month and day numbers, sort by those
                if (a.monthNumber !== null && b.monthNumber !== null && 
                    a.dayNumber !== null && b.dayNumber !== null) {
                    
                    const monthDiff = a.monthNumber - b.monthNumber;
                    if (monthDiff !== 0) {
                        return monthDiff;
                    }
                    return a.dayNumber - b.dayNumber;
                }
                
                // If both have day numbers only, sort by those
                if (a.dayNumber !== null && b.dayNumber !== null) {
                    return a.dayNumber - b.dayNumber;
                }
                
                // If one has day number and other doesn't, prioritize the one with day number
                if (a.dayNumber !== null && b.dayNumber === null) {
                    return -1;
                }
                if (a.dayNumber === null && b.dayNumber !== null) {
                    return 1;
                }
                
                // Fallback to alphabetical sorting
                return a.original.localeCompare(b.original);
            });
            
            // Extract the sorted original date strings
            return dateObjects.map(dateObj => dateObj.original);
        }
        
        /**
         * Populate the delivery filter dropdown with platform-specific cached delivery dates
         * @param {Array} items - Array of product items (used to determine relevant dates)
         */
        function populateDeliveryFilter(items) {
            // Prevent multiple rapid re-populations
            if (window.isPopulatingDeliveryFilter) {
                return;
            }
            
            window.isPopulatingDeliveryFilter = true;
            
            // IMMEDIATE state capture before any other operations
            const existingButtons = $deliveryDatesContainer.find('.ps-delivery-date-button');
            
            // Always extract delivery dates from current items to ensure freshness
            let platformDeliveryDates = {};
            if (items && items.length > 0) {
                platformDeliveryDates = extractPlatformDeliveryDatesFromItems(items);
            }
            
            // Get currently available platforms in the items
            const currentPlatforms = [...new Set(items.map(item => item.platform))].filter(Boolean);
            
            // Get delivery dates that exist in current results for verification
            const currentDeliveryDates = extractDeliveryDates(items);
            
            // Combine all dates from relevant platforms
            let allPlatformDates = [];
            currentPlatforms.forEach(platform => {
                if (platformDeliveryDates[platform] && Array.isArray(platformDeliveryDates[platform])) {
                    allPlatformDates = [...allPlatformDates, ...platformDeliveryDates[platform]];
                }
            });
            
            // Remove duplicates and sort by date values
            const uniqueDates = [...new Set(allPlatformDates)];
            
            // Sort the combined dates using the same logic as other functions
            const dateObjects = uniqueDates.map(dateString => {
                let parsedDate = null;
                let dayNumber = null;
                let monthNumber = null;
                
                // Extract day and month from date string (e.g., "Monday, Dec 2" -> day: 2, month: 12)
                const dayMatch = dateString.match(/\b(\d{1,2})\b/);
                if (dayMatch) {
                    dayNumber = parseInt(dayMatch[1]);
                    
                    // Extract month name and convert to number
                    const monthNames = {
                        'jan': 1, 'january': 1,
                        'feb': 2, 'february': 2,
                        'mar': 3, 'march': 3,
                        'apr': 4, 'april': 4,
                        'may': 5,
                        'jun': 6, 'june': 6,
                        'jul': 7, 'july': 7,
                        'aug': 8, 'august': 8,
                        'sep': 9, 'september': 9,
                        'oct': 10, 'october': 10,
                        'nov': 11, 'november': 11,
                        'dec': 12, 'december': 12
                    };
                    
                    for (const [monthName, monthNum] of Object.entries(monthNames)) {
                        if (dateString.toLowerCase().includes(monthName)) {
                            monthNumber = monthNum;
                            break;
                        }
                    }
                    
                    // Try to create a proper date object for this year
                    if (monthNumber !== null) {
                        const currentYear = new Date().getFullYear();
                        try {
                            parsedDate = new Date(currentYear, monthNumber - 1, dayNumber);
                            
                            // If the created date is in the past, assume it's for next year
                            const now = new Date();
                            if (parsedDate < now) {
                                parsedDate = new Date(currentYear + 1, monthNumber - 1, dayNumber);
                            }
                        } catch (e) {
                            parsedDate = null;
                        }
                    }
                }
                
                return {
                    original: dateString,
                    parsedDate: parsedDate,
                    dayNumber: dayNumber,
                    monthNumber: monthNumber
                };
            });
            
            // Sort by parsed date first, then by month/day, then alphabetically
            dateObjects.sort((a, b) => {
                // If both have parsed dates, sort by those
                if (a.parsedDate && b.parsedDate) {
                    return a.parsedDate.getTime() - b.parsedDate.getTime();
                }
                
                // If both have month and day numbers, sort by those
                if (a.monthNumber !== null && b.monthNumber !== null && 
                    a.dayNumber !== null && b.dayNumber !== null) {
                    
                    const monthDiff = a.monthNumber - b.monthNumber;
                    if (monthDiff !== 0) {
                        return monthDiff;
                    }
                    return a.dayNumber - b.dayNumber;
                }
                
                // If both have day numbers only, sort by those
                if (a.dayNumber !== null && b.dayNumber !== null) {
                    return a.dayNumber - b.dayNumber;
                }
                
                // If one has day number and other doesn't, prioritize the one with day number
                if (a.dayNumber !== null && b.dayNumber === null) {
                    return -1;
                }
                if (a.dayNumber === null && b.dayNumber !== null) {
                    return 1;
                }
                
                // Fallback to alphabetical sorting
                return a.original.localeCompare(b.original);
            });
            
            const sortedDates = dateObjects.map(dateObj => dateObj.original);
            
            // Clear existing date options
            $deliveryDatesContainer.empty();
            
            if (sortedDates.length === 0) {
                $deliveryDropdownHeader.closest('tr').hide();
                return;
            }
            
            // Show the delivery filter row
            $deliveryDropdownHeader.closest('tr').show();
            
            // Store all dates for reference
            window.allDeliveryDates = sortedDates;
            
            // Create button-style delivery date options - leave them unselected by default
            // The "All" button will be selected by default to show all products
            let allDatesHtml = '';
            sortedDates.forEach((date, index) => {
                const sanitizedId = 'delivery-date-' + date.replace(/[^a-zA-Z0-9]/g, '-');
                
                allDatesHtml += `
                    <button type="button" id="${sanitizedId}" class="ps-delivery-date-button" data-date="${date}" data-selected="false">
                        ${date}
                    </button>
                `;
            });
            
            // Update the dropdown content with all dates
            $deliveryDatesContainer.html(allDatesHtml);
            
            // Calculate and set uniform button width based on longest text (only if not already set)
            setTimeout(() => {
                const allButtons = $deliveryDatesContainer.find('.ps-delivery-date-button');
                const allButton = $('#ps-delivery-all-button');
                
                // Check if width has already been calculated and set
                if (allButton.data('width-calculated')) {
                    return;
                }
                
                // Include the All button in width calculation
                const allElementsToMeasure = allButtons.add(allButton);
                
                // Reset any existing min-width to get natural width
                allElementsToMeasure.css('min-width', '');
                
                let maxWidth = 0;
                
                // Measure all buttons to find the widest
                allElementsToMeasure.each(function() {
                    const buttonWidth = $(this).outerWidth();
                    if (buttonWidth > maxWidth) {
                        maxWidth = buttonWidth;
                    }
                });
                
                // Add some padding to the max width
                const uniformWidth = maxWidth + 20;
                
                // Apply uniform width to all buttons
                allElementsToMeasure.css('min-width', uniformWidth + 'px');
                
                // Mark that width has been calculated
                allButton.data('width-calculated', true);
            }, 50);
            
            // Preserve current selection state if delivery filter already exists
            const currentAllSelected = $('#ps-delivery-all-button').attr('data-selected');
            const currentSelectedDates = [];
            
            // Store current individual date selections BEFORE any HTML changes
            $deliveryDatesContainer.find('.ps-delivery-date-button[data-selected="true"]').each(function() {
                const dateValue = $(this).attr('data-date');
                currentSelectedDates.push(dateValue);
            });
            
            // Only set "All" to highlighted by default if this is the first time (no current state)
            if (currentAllSelected === undefined) {
                $('#ps-delivery-all-button').attr('data-selected', 'true').addClass('ps-selected');
            } else {
                // Preserve current "All" button state
                const $allBtn = $('#ps-delivery-all-button');
                $allBtn.attr('data-selected', currentAllSelected);
                if (currentAllSelected === 'true') {
                    $allBtn.addClass('ps-selected');
                } else {
                    $allBtn.removeClass('ps-selected');
                }
            }
            
            // Re-bind control events after recreating elements
            bindDeliveryControlEvents();
            
            // Check if CSS is properly loaded for date buttons
            setTimeout(function() {
                // CSS is loaded after DOM insertion
            }, 100);
            
            // Bind individual date button events after HTML is inserted
            $deliveryDatesContainer.find('.ps-delivery-date-button').off('click').on('click', function() {
                const $clickedButton = $(this);
                const clickedValue = $clickedButton.attr('data-date');
                const allButtons = $deliveryDatesContainer.find('.ps-delivery-date-button');
                const isSelected = $clickedButton.attr('data-selected') === 'true';
                
                if (!isSelected) {
                    // Handle "unspecified" separately - it doesn't participate in cumulative selection
                    if (clickedValue === 'unspecified') {
                        $clickedButton.attr('data-selected', 'true').addClass('ps-selected');
                        // Force style application
                        $clickedButton[0].offsetHeight; // Trigger reflow
                    } else {
                        // When selecting a date, also select all dates before it (cumulative selection)
                        let foundClicked = false;
                        allButtons.each(function() {
                            const $button = $(this);
                            const buttonValue = $button.attr('data-date');
                            
                            // Skip "unspecified" in cumulative selection
                            if (buttonValue === 'unspecified') {
                                return;
                            }
                            
                            if (buttonValue === clickedValue) {
                                foundClicked = true;
                                $button.attr('data-selected', 'true').addClass('ps-selected');
                                // Force style application
                                $button[0].offsetHeight; // Trigger reflow
                            } else if (!foundClicked) {
                                // This is a date before the clicked one
                                $button.attr('data-selected', 'true').addClass('ps-selected');
                                // Force style application
                                $button[0].offsetHeight; // Trigger reflow
                            }
                        });
                    }
                } else {
                    // Handle "unspecified" separately - it doesn't participate in cumulative selection
                    if (clickedValue === 'unspecified') {
                        $clickedButton.attr('data-selected', 'false').removeClass('ps-selected');
                    } else {
                        // When unselecting a date, also unselect all dates after it
                        let foundClicked = false;
                        allButtons.each(function() {
                            const $button = $(this);
                            const buttonValue = $button.attr('data-date');
                            
                            // Skip "unspecified" in cumulative selection
                            if (buttonValue === 'unspecified') {
                                return;
                            }
                            
                            if (buttonValue === clickedValue) {
                                foundClicked = true;
                                $button.attr('data-selected', 'false').removeClass('ps-selected');
                            } else if (foundClicked) {
                                // This is a date after the clicked one
                                $button.attr('data-selected', 'false').removeClass('ps-selected');
                            }
                        });
                    }
                }
                
                // Update "All" button based on individual button states
                const selectedButtons = allButtons.filter('[data-selected="true"]');
                
                const $allButton = $('#ps-delivery-all-button');
                
                if (selectedButtons.length === allButtons.length) {
                    // All selected - select "All" button
                    $allButton.attr('data-selected', 'true').addClass('ps-selected');
                } else {
                    // Some or none selected - unselect "All" button
                    $allButton.attr('data-selected', 'false').removeClass('ps-selected');
                }
                
                // Update date button highlighting status
                allButtons.each(function() {
                    const $btn = $(this);
                    // Ensure proper highlighting is applied
                    if ($btn.attr('data-selected') === 'true') {
                        $btn.addClass('ps-selected');
                    } else {
                        $btn.removeClass('ps-selected');
                    }
                });
                
                triggerDeliveryFilterChange();
            });
            
            // Restore individual date button selections after HTML recreation
            if (currentSelectedDates.length > 0) {
                console.log('🔄 RESTORING individual date selections:', currentSelectedDates);
                // Use setTimeout to ensure the DOM is fully updated before restoration
                setTimeout(function() {
                    currentSelectedDates.forEach(function(selectedDate) {
                        const $restoredButton = $deliveryDatesContainer.find('.ps-delivery-date-button[data-date="' + selectedDate + '"]');
                        if ($restoredButton.length > 0) {
                            $restoredButton.attr('data-selected', 'true').addClass('ps-selected');
                            
                            // Force a style recalculation to ensure CSS is applied
                            $restoredButton[0].offsetHeight; // Trigger reflow
                        }
                    });
                }, 50);
            }
            
            // Reset the flag to allow future populations after a delay
            setTimeout(function() {
                window.isPopulatingDeliveryFilter = false;
            }, 200);
        }
        
        /**
         * Get currently selected delivery dates from buttons
         * @returns {Array} - Array of selected delivery date strings
         */
        function getSelectedDeliveryDates() {
            const selectedDates = [];
            $deliveryDropdownContent.find('.ps-delivery-date-button[data-selected="true"]').each(function() {
                selectedDates.push($(this).attr('data-date'));
            });
            return selectedDates;
        }
        
        // updateDeliveryHeaderText function removed - using static "Delivery dates" text
        
        /**
         * Bind events for delivery control elements
         */
        function bindDeliveryControlEvents() {
            // Handle dropdown header click (open/close) - but not on checkbox clicks
            $deliveryDropdownHeader.off('click').on('click', function(e) {
                // Don't toggle dropdown if clicking on checkboxes or labels
                if ($(e.target).is('input[type="checkbox"]') || $(e.target).closest('label').length) {
                    return;
                }
                
                e.stopPropagation();
                const isActive = $(this).hasClass('active');
                
                if (isActive) {
                    closeDeliveryDropdown();
                } else {
                    openDeliveryDropdown();
                }
            });
            
            // Handle "All" button in header
            $('#ps-delivery-all-button').off('click').on('click', function(e) {
                e.stopPropagation();
                
                const isSelected = $(this).attr('data-selected') === 'true';
                
                if (!isSelected) {
                    // Select all date buttons
                    $(this).attr('data-selected', 'true').addClass('ps-selected');
                    $deliveryDatesContainer.find('.ps-delivery-date-button').attr('data-selected', 'true').addClass('ps-selected');
                } else {
                    // Unselect all date buttons
                    $(this).attr('data-selected', 'false').removeClass('ps-selected');
                    $deliveryDatesContainer.find('.ps-delivery-date-button').attr('data-selected', 'false').removeClass('ps-selected');
                }
                triggerDeliveryFilterChange();
            });

        }
        
        /**
         * Open the delivery dropdown and show all dates
         */
        function openDeliveryDropdown() {
            $deliveryDropdownHeader.addClass('active');
            $deliveryDropdownContent.addClass('show');
        }
        
        /**
         * Close the delivery dropdown
         */
        function closeDeliveryDropdown() {
            $deliveryDropdownHeader.removeClass('active');
            $deliveryDropdownContent.removeClass('show');
        }
        
        /**
         * Reset delivery filter to "All" selected (show all products)
         */
        function resetDeliveryFilterToAll() {
            $('#ps-delivery-all-button').attr('data-selected', 'true').addClass('ps-selected');
            // Unselect all individual date buttons
            $('#ps-delivery-dates-container .ps-delivery-date-button').attr('data-selected', 'false').removeClass('ps-selected');
        }

        /**
         * Trigger delivery filter change event
         */
        function triggerDeliveryFilterChange() {
            if (originalCachedResults.length > 0) {
                applyAllFilters();
            }
        }
        
        /**
         * Filter products by selected delivery dates
         * @param {Array} items - Array of product items
         * @param {Array} selectedDates - Array of selected delivery date strings
         * @returns {Array} - Filtered items
         */
        function filterProductsByDelivery(items, selectedDates) {
            if (!items || !Array.isArray(items)) return [];
            
            const isAllSelected = $('#ps-delivery-all-button').attr('data-selected') === 'true';
            
            if (isAllSelected || selectedDates.length === 0) {
                // "All" selected OR no specific dates selected: show all products (including those without delivery dates)
                return items;
            }
            
            // Check if "unspecified" is selected
            const isUnspecifiedSelected = selectedDates.includes('unspecified');
            
            // Specific dates selected: filter to only products with matching delivery dates
            return items.filter(item => {
                const deliveryText = item.delivery_time;
                
                // If "unspecified" is selected and product has no delivery info, include it
                if (isUnspecifiedSelected && (!deliveryText || deliveryText.trim() === '' || deliveryText === 'N/A')) {
                    return true;
                }
                
                // If product has no delivery info and "unspecified" is not selected, exclude it
                if (!deliveryText || deliveryText.trim() === '' || deliveryText === 'N/A') {
                    return false;
                }
                
                // Check if any of the selected dates match this product's delivery info
                return selectedDates.some(selectedDate => {
                    // Skip the "unspecified" option when matching actual dates
                    if (selectedDate === 'unspecified') {
                        return false;
                    }
                    
                    // For exact date matches
                    if (deliveryText.includes(selectedDate)) {
                        return true;
                    }
                    
                    // For general delivery text matches (when no specific date was extracted)
                    const firstLine = deliveryText.split('\n')[0];
                    return firstLine === selectedDate;
                });
            });
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
                
                // Don't hide delivery filter if we have originalCachedResults (meaning delivery filter caused the empty result)
                // Only hide if there are truly no products available at all
                if (!originalCachedResults || originalCachedResults.length === 0) {
                    $deliveryDropdownHeader.closest('tr').hide();
                } else {
                    // We have cached results, so populate the delivery filter based on those
                    // This ensures the delivery filter remains functional even when current filtered results are empty
                    populateDeliveryFilter(originalCachedResults);
                }
                return;
            }
            
            // Only populate delivery filter if it doesn't exist yet OR if items have significantly changed
            // Don't re-populate during normal filtering operations to preserve user selections
            const currentFilterExists = $deliveryDatesContainer.find('.ps-delivery-date-button').length > 0;
            const shouldRepopulate = !currentFilterExists || (window.deliveryFilterNeedsUpdate === true);
            
            if (shouldRepopulate) {
                populateDeliveryFilter(items);
                window.deliveryFilterNeedsUpdate = false;
            } else {
                // Skipping delivery filter re-population to preserve user selections
            }
            
            // Items are already processed in sortProducts function, so we can render them directly
            
            items.forEach(function(item) {
                let productHtml = productTemplate;
                
                // eBay debug logging removed
                
                // Handle platform conditional
                if (item.platform) {
                    productHtml = productHtml.replace(/{{#if platform}}([\s\S]*?){{\/if}}/g, '$1');
                } else {
                    productHtml = productHtml.replace(/{{#if platform}}[\s\S]*?{{\/if}}/g, '');
                }

                // Handle brand conditional
                if (item.brand) {
                    productHtml = productHtml.replace(/{{#if brand}}([\s\S]*?){{\/if}}/g, '$1');
                } else {
                    productHtml = productHtml.replace(/{{#if brand}}[\s\S]*?{{\/if}}/g, '');
                }

                // Handle the entire rating section as one atomic operation
                if (item.rating) {
                    if (item.is_ebay_seller_rating) {
                        // eBay debug removed
                        
                        // For eBay seller ratings, replace the entire rating conditional block with just the eBay rating
                        const ratingPattern = /{{#if rating}}\s*<div class="ps-product-rating-inline">\s*<a href="{{rating_link}}" target="_blank">\s*{{#if is_ebay_seller_rating}}\s*<span class="ps-stars">{{rating}}<\/span>\s*{{else}}[\s\S]*?{{\/if}}\s*<\/a>\s*<\/div>\s*{{\/if}}/g;
                        
                        productHtml = productHtml.replace(
                            ratingPattern,
                            '<div class="ps-product-rating-inline"><a href="{{rating_link}}" target="_blank"><span class="ps-stars ps-ebay-rating">{{rating}}</span></a></div>'
                        );
                        
                        // eBay debug removed
                    } else {
                        // For regular Amazon ratings, replace with the Amazon section content
                        const ratingPattern = /{{#if rating}}\s*<div class="ps-product-rating-inline">\s*<a href="{{rating_link}}" target="_blank">\s*{{#if is_ebay_seller_rating}}\s*<span class="ps-stars">{{rating}}<\/span>\s*{{else}}([\s\S]*?){{\/if}}\s*<\/a>\s*<\/div>\s*{{\/if}}/g;
                        
                        productHtml = productHtml.replace(
                            ratingPattern,
                            '<div class="ps-product-rating-inline"><a href="{{rating_link}}" target="_blank">$1</a></div>'
                        );
                    }
                } else {
                    // For items without ratings, remove the entire rating section
                    productHtml = productHtml.replace(
                        /{{#if rating}}[\s\S]*?{{\/if}}/g,
                        ''
                    );
                }
                
                // Handle rating_number conditional (inside rating conditional)
                if (item.rating && item.rating_number && !item.is_ebay_seller_rating) {
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
                // eBay debug removed
                
                productHtml = productHtml
                    .replace(/{{platform}}/g, item.platform || '')
                    .replace(/{{brand}}/g, item.brand || '')
                    .replace(/{{title}}/g, item.title || '')
                    .replace(/{{link}}/g, item.link || '')
                    .replace(/{{image}}/g, item.image || '')
                    .replace(/{{price}}/g, item.price || '')
                    .replace(/{{price_per_unit}}/g, item.price_per_unit || '')
                    .replace(/{{unit}}/g, item.unit || '')
                    .replace(/{{rating}}/g, item.rating || '');
                
                // eBay debug removed
                    
                if (item.rating_number) {
                    productHtml = productHtml.replace(/{{rating_number}}/g, item.rating_number);
                }
                if (item.rating_count) {
                    productHtml = productHtml.replace(/{{rating_count}}/g, item.rating_count);
                }
                if (item.rating_link) {
                    productHtml = productHtml.replace(/{{rating_link}}/g, item.rating_link);
                } else {
                    // Fallback: use product link or # for rating link
                    productHtml = productHtml.replace(/{{rating_link}}/g, item.link || '#');
                }
                
                // Replace delivery time if it exists
                if (item.delivery_time) {
                    productHtml = productHtml.replace(/{{delivery_time}}/g, item.delivery_time);
                }
                
                // Remove any remaining template syntax that might have been left behind
                productHtml = productHtml.replace(/{{\/if}}/g, '');
                productHtml = productHtml.replace(/{{else}}/g, '');
                productHtml = productHtml.replace(/{{#if[^}]*}}/g, '');
                
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
                        
                        /* Button-style delivery filter elements */
                        .ps-delivery-all-button,
                        .ps-delivery-date-button {
                            border: 1px solid #ccc;
                            background-color: #fff;
                            color: #333;
                            padding: 6px 12px;
                            margin: 2px;
                            border-radius: 4px;
                            cursor: pointer;
                            font-size: 14px;
                            min-width: 120px;
                            text-align: center;
                            transition: all 0.2s ease;
                            white-space: nowrap;
                        }
                        
                        .ps-delivery-all-button:hover,
                        .ps-delivery-date-button:hover {
                            border-color: #999;
                            background-color: #f8f8f8;
                        }
                        
                        .ps-delivery-all-button[data-selected="true"],
                        .ps-delivery-date-button[data-selected="true"] {
                            background-color: #4CAF50 !important;
                            border-color: #4CAF50 !important;
                            color: white !important;
                        }
                        
                        .ps-delivery-all-button[data-selected="true"]:hover,
                        .ps-delivery-date-button[data-selected="true"]:hover {
                            background-color: #45a049 !important;
                            border-color: #45a049 !important;
                        }
                        
                        /* Ultra-specific selectors to override any dark theme */
                        html body .ps-delivery-dropdown-content .ps-delivery-date-button[data-selected="true"] {
                            background-color: #4CAF50 !important;
                            border-color: #4CAF50 !important;
                            color: white !important;
                            background-image: none !important;
                        }
                        
                        html body .ps-delivery-header-controls .ps-delivery-all-button[data-selected="true"] {
                            background-color: #4CAF50 !important;
                            border-color: #4CAF50 !important;
                            color: white !important;
                            background-image: none !important;
                        }
                        
                        /* Class-based approach as backup */
                        .ps-delivery-date-button.ps-selected {
                            background-color: #4CAF50 !important;
                            border-color: #4CAF50 !important;
                            color: white !important;
                            background-image: none !important;
                        }
                        
                        .ps-delivery-all-button.ps-selected {
                            background-color: #4CAF50 !important;
                            border-color: #4CAF50 !important;
                            color: white !important;
                            background-image: none !important;
                        }
                        
                        /* Container styling */
                        .ps-delivery-dates-container {
                            display: flex;
                            flex-direction: column;
                            gap: 2px;
                            padding: 8px;
                        }
                        
                        .ps-delivery-header-controls {
                            display: flex;
                            align-items: center;
                        }
                    </style>
                `);
            }
        }
        
        // Handle sort change (re-sort current results)
        $sortBy.on('change', function() {
            console.log('🔥 SORT CHANGE: User changed sort dropdown', {
                newValue: $(this).val(),
                timestamp: new Date().toISOString()
            });
            
            // Skip processing if we're programmatically applying saved sorting
            if (window.isApplyingSavedSorting) {
                console.log('🔥 SORT CHANGE: Skipping due to saved sorting application');
                return;
            }
            
            // Clear saved default sorting preference when user manually changes sort
            // This allows the system to recalculate the preference on the next search
            clearSavedDefaultSorting();
            
            if (originalCachedResults.length > 0) {
                // Re-apply all filters when sort changes
                const excludeText = $('#ps-exclude-keywords').val();
                const includeText = $('#ps-search-query').val();
                const minRating = parseFloat($('#ps-min-rating').val()) || null;
                const sortCriteria = $(this).val();
                
                console.log('🔥 SORT CHANGE: Applying filters with sort criteria', {
                    sortCriteria: sortCriteria
                });
                
                // Get selected platforms
                const selectedPlatforms = [];
                $('input[name="platforms"]:checked').each(function() {
                    selectedPlatforms.push($(this).val());
                });
                
                // Get selected delivery dates
                const selectedDeliveryDates = $deliveryDropdownContent.val() || [];
                
                let filteredResults = [...originalCachedResults];
                
                // Apply platform filter first
                if (selectedPlatforms.length > 0) {
                    filteredResults = filteredResults.filter(function(item) {
                        return selectedPlatforms.includes(item.platform);
                    });
                }
                
                // Apply delivery date filter
                if (selectedDeliveryDates.length > 0) {
                    filteredResults = filterProductsByDelivery(filteredResults, selectedDeliveryDates);
                }
                
                // Apply other filters
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
            if (originalCachedResults.length > 0) {
                applyAllFilters();
            }
        });
        
        // Handle platform checkbox changes (auto-trigger filter)
        $('input[name="platforms"]').on('change', function() {
            // Save platform selections to cache
            savePlatformSelections();
            
            if (originalCachedResults.length > 0) {
                // Get selected platforms to filter the data
                const selectedPlatforms = [];
                $('input[name="platforms"]:checked').each(function() {
                    selectedPlatforms.push($(this).val());
                });
                
                // Filter cached results to only include selected platforms
                let platformFilteredResults = [...originalCachedResults];
                if (selectedPlatforms.length > 0) {
                    platformFilteredResults = platformFilteredResults.filter(function(item) {
                        return selectedPlatforms.includes(item.platform);
                    });
                }
                
                // Repopulate delivery filter based on filtered results
                // This ensures only delivery dates from selected platforms are shown
                window.deliveryFilterNeedsUpdate = true;
                populateDeliveryFilter(platformFilteredResults);
                
                // Apply all filters while preserving user's search terms
                applyAllFilters();
            }
        });
        
        // Delivery filter events are now handled by bindDeliveryControlEvents() function
        
        // Function to apply all filters (rating, text, platform, delivery)
        function applyAllFilters() {
            const excludeText = $('#ps-exclude-keywords').val();
            const includeText = $('#ps-search-query').val();
            const minRating = parseFloat($('#ps-min-rating').val()) || null;
            const sortCriteria = $sortBy.val();
            
            console.log('🔥 APPLY ALL: applyAllFilters called', {
                sortCriteria: sortCriteria,
                timestamp: new Date().toISOString()
            });
            
            // Check if search terms have changed - if so, reset delivery filter to "All"
            const currentSearchTerms = includeText + '|' + excludeText;
            if (window.lastSearchTerms && window.lastSearchTerms !== currentSearchTerms) {
    
                resetDeliveryFilterToAll();
            }
            window.lastSearchTerms = currentSearchTerms;
            
            // Get selected platforms
            const selectedPlatforms = [];
            $('input[name="platforms"]:checked').each(function() {
                selectedPlatforms.push($(this).val());
            });
            
            // Get selected delivery dates
            const selectedDeliveryDates = getSelectedDeliveryDates();
            
            // Apply filters based on current criteria
            
            let filteredResults = [...originalCachedResults];
            
            // Apply platform filter first
            if (selectedPlatforms.length > 0) {
                filteredResults = filteredResults.filter(function(item) {
                    return selectedPlatforms.includes(item.platform);
                });
            }
            
            // Apply delivery date filter
            filteredResults = filterProductsByDelivery(filteredResults, selectedDeliveryDates);
            
            // Apply other filters
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
        
        // Handle Filter Cached Results button click
        $filterButton.on('click', function(e) {
            e.preventDefault();
            
            // Reset the flag since filtering should restore normal message behavior
            isAfterLiveSearch = false;
            
            // Show that we're filtering cached results
            $loadingText.text('Filtering cached results...');
            $loading.show();
            
            // Always filter from the original cached results
            if (originalCachedResults.length > 0) {
                applyAllFilters();
                $loading.hide();
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
            
            // Ensure all available platforms are selected first
            if (originalCachedResults.length > 0) {
                autoDetectAndCheckPlatforms(originalCachedResults);
                
                // Show all results without any filtering - bypass the normal filter pipeline
                // that might pick up residual search terms
                setTimeout(function() {
                    // Clear all filter fields AFTER bypassing filters
                    $('#ps-search-query').val('');
                    $('#ps-exclude-keywords').val('');
                    $('#ps-min-rating').val('4.0');
                    
                    // Reset delivery filter to "All" to show all products
                    resetDeliveryFilterToAll();
                    
                    // Just apply sorting to all original cached results
                    const sortCriteria = $sortBy.val();
                    let allResults = [...originalCachedResults];
                    allResults = sortProducts(allResults, sortCriteria);
                    
                    // Update UI directly
                    currentSearchResults = allResults;
                    renderProducts(allResults);
                    
                    // Update results count
                    const totalCount = originalCachedResults.length;
                    if (!isAfterLiveSearch) {
                        $resultsCount.html('<p><strong>' + totalCount + '</strong> products.</p>').show();
                    }
                    
                    $loading.hide();
                }, 50);
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
                        
                        // Note: Platform-specific delivery dates will be extracted fresh from current items when needed
                        // No need to cache them since the delivery filter now always uses current data
                        
                        // CRITICAL: Auto-detect and check platform checkboxes BEFORE filtering
                        // This ensures that eBay and other platform checkboxes are properly restored
                        // before the platform filtering logic runs
                        autoDetectAndCheckPlatforms(products);
                        
                        // Reset pagination state
                        currentPage = 1;
                        
                        // Reset flag since this is loading cached results, not a live search
                        isAfterLiveSearch = false;
                        
                        // Apply saved default sorting preference (don't recalculate from cached data)
                        // Set flag to prevent sort change handler from executing during saved sorting
                        window.isApplyingSavedSorting = true;
                        const sortingChanged = applySavedDefaultSorting();
                        // Clear flag after a short delay to allow normal sort changes
                        setTimeout(() => {
                            window.isApplyingSavedSorting = false;
                        }, 50);
                        
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
                        
                        // Auto-apply current filter values including platform selections
                        const currentIncludeText = queryElem ? queryElem.value : '';
                        const currentExcludeText = excludeElem ? excludeElem.value : '';
                        const currentMinRating = parseFloat($('#ps-min-rating').val()) || null;
                        
                        // Get currently selected platforms (restored from cache)
                        const selectedPlatforms = [];
                        $('input[name="platforms"]:checked').each(function() {
                            selectedPlatforms.push($(this).val());
                        });
                        
                        let filteredProducts = [...products];
                        

                        
                        // Apply platform filter first
                        if (selectedPlatforms.length > 0) {
                            filteredProducts = filteredProducts.filter(function(item) {
                                return selectedPlatforms.includes(item.platform);
                            });
                        }
                        
                        // Populate delivery filter first to get available dates (fresh search results)
                        window.deliveryFilterNeedsUpdate = true;
                        populateDeliveryFilter(filteredProducts);
                        
                        // Ensure delivery filter starts with "All" selected (default behavior)
                        resetDeliveryFilterToAll();
                        
                        // Get selected delivery dates (all selected by default after population)
                        const selectedDeliveryDates = getSelectedDeliveryDates();
                        
                        // Apply delivery date filter
                        if (selectedDeliveryDates.length > 0) {
                            filteredProducts = filterProductsByDelivery(filteredProducts, selectedDeliveryDates);
                        }
                        
                        // Apply other filters
                        if (currentIncludeText || currentExcludeText || currentMinRating) {
                            filteredProducts = filterProducts(filteredProducts, currentExcludeText, currentIncludeText, currentMinRating);
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
                        
                        // Check if load more is available for any platform
                        const paginationUrls = response.data && response.data.pagination_urls ? response.data.pagination_urls : {};
                        const platforms = response.data && response.data.platforms ? response.data.platforms : ['amazon'];
                        
                        // Amazon requires pagination URLs, eBay can paginate with page numbers
                        // Currently only Amazon and eBay support load more functionality
                        const hasAmazonPagination = paginationUrls && typeof paginationUrls === 'object' && 
                                                   (paginationUrls.page_2 || paginationUrls.page_3);
                        const hasEbay = platforms.includes('ebay');
                        const loadMoreSupportedPlatforms = ['amazon', 'ebay'];
                        const hasLoadMoreCapablePlatforms = platforms.some(platform => loadMoreSupportedPlatforms.includes(platform));
                        const hasLoadMoreCapability = hasLoadMoreCapablePlatforms && (hasAmazonPagination || hasEbay);
                        
                        logToServer('Load Cached Results: Load more capability check', {
                            hasAmazonPagination: hasAmazonPagination,
                            hasEbay: hasEbay,
                            hasLoadMoreCapability: hasLoadMoreCapability,
                            platforms: platforms,
                            paginationUrls: paginationUrls,
                            paginationUrlsKeys: Object.keys(paginationUrls)
                        });
                        
                        // Show or hide load more button based on platform capabilities
                        if (hasLoadMoreCapability) {
                            toggleLoadMoreButton(true);
                        } else {
                            toggleLoadMoreButton(false);
                        }
                        
                        logToServer('Load Cached Results: Successfully processed and rendered products', {
                            originalCount: products.length,
                            filteredCount: filteredProducts.length,
                            finalCount: sortedProducts.length,
                            sortingChanged: sortingChanged,
                            currentSortBy: currentSortBy,
                            selectedPlatforms: selectedPlatforms,
                            platformFilterApplied: selectedPlatforms.length > 0
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
            const originalSearchText = searchButton ? searchButton.textContent : 'Search';
            const originalLoadText = $loadMoreButton.find('.ps-load-more-text').text() || 'Load More';
            
            // Disable all buttons and add cooldown classes
            if (searchButton) {
                searchButton.classList.add('ps-cooldown');
                searchButton.disabled = true;
            }
            $loadMoreButton.prop('disabled', true);
            $topLoadMoreButton.prop('disabled', true);
            
            // Ensure spinner is hidden and text is shown during cooldown

            $loadMoreButton.find('.ps-load-more-spinner').hide();
            $loadMoreButton.find('.ps-load-more-text').show();
            
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
                    $loadMoreButton.find('.ps-load-more-text').text(originalLoadText).show();
                    $loadMoreButton.find('.ps-load-more-spinner').hide();
                    
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
            
            // Get selected platforms
            const platformElems = document.querySelectorAll('input[name="platforms"]:checked');
            const platforms = Array.from(platformElems).map(elem => elem.value);

            if (!query) {
                resultsContainer.innerHTML = '<div class="ps-error">Please enter search keywords.</div>';
                // Keep results count hidden since this is after a live search attempt
                $resultsCount.hide();
                // Hide load more buttons on validation error
                toggleLoadMoreButton(false);
                return;
            }
            
            if (platforms.length === 0) {
                resultsContainer.innerHTML = '<div class="ps-error">Please select at least one platform to search.</div>';
                $resultsCount.hide();
                // Hide load more buttons on validation error
                toggleLoadMoreButton(false);
                return;
            }
            
            // Save platform selections to cache for next page load
            savePlatformSelections();
            
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
                // Hide load more buttons on configuration error
                toggleLoadMoreButton(false);
                return;
            }

            // Hide load more buttons during search
            logToServer('New Search: Hiding load more buttons', { 
                query: query, 
                platforms: platforms 
            });
            toggleLoadMoreButton(false);
            
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
                    platforms: platforms,
                    filter_cached: 'false'
                },
                success: function(response) {
    
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
                            errorHtml += '<br><br>Or continue search on <a href="' + response.data.amazon_search_url + '" target="_blank" rel="noopener">' + response.data.amazon_search_url + '</a>';
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
                        
                        // Check if load more is available for any platform
                        const paginationUrls = response.data && response.data.pagination_urls ? response.data.pagination_urls : {};
                        const platforms = response.data && response.data.platforms ? response.data.platforms : ['amazon'];
                        
                        // Amazon requires pagination URLs, eBay can paginate with page numbers
                        // Currently only Amazon and eBay support load more functionality
                        const hasAmazonPagination = paginationUrls && typeof paginationUrls === 'object' && 
                                                   (paginationUrls.page_2 || paginationUrls.page_3);
                        const hasEbay = platforms.includes('ebay');
                        const loadMoreSupportedPlatforms = ['amazon', 'ebay'];
                        const hasLoadMoreCapablePlatforms = platforms.some(platform => loadMoreSupportedPlatforms.includes(platform));
                        const hasLoadMoreCapability = hasLoadMoreCapablePlatforms && (hasAmazonPagination || hasEbay);
                        
                        logToServer('Live Search: Load more capability check', {
                            hasAmazonPagination: hasAmazonPagination,
                            hasEbay: hasEbay,
                            hasLoadMoreCapability: hasLoadMoreCapability,
                            platforms: platforms,
                            paginationUrls: paginationUrls,
                            paginationUrlsKeys: Object.keys(paginationUrls)
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
                        if (hasLoadMoreCapability) {
                            logToServer('New Search: Showing load more buttons', { 
                                hasLoadMoreCapability: true,
                                platforms: platforms,
                                hasAmazonPagination: hasAmazonPagination,
                                hasEbay: hasEbay
                            });
                            toggleLoadMoreButton(true);
                        } else {
                            logToServer('New Search: Load more buttons remain hidden', { 
                                hasLoadMoreCapability: false,
                                platforms: platforms,
                                hasAmazonPagination: hasAmazonPagination,
                                hasEbay: hasEbay
                            });
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
                    
                    // Hide load more buttons on AJAX error
                    toggleLoadMoreButton(false);
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
            logToServer('Debug: Load More State Check', {
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
            
            console.log('🔥 APPLY SAVED: applySavedDefaultSorting called', {
                currentSortValue: currentSortValue,
                savedDefaultSort: savedDefaultSort,
                willChangeSorting: currentSortValue === 'price' && savedDefaultSort === 'price_per_unit'
            });
            
            logToServer('Apply Saved Default Sorting', {
                currentSortValue: currentSortValue,
                savedDefaultSort: savedDefaultSort,
                willChangeSorting: currentSortValue === 'price' && savedDefaultSort === 'price_per_unit'
            });
            
            // Only change default if currently set to 'price' and we have a saved preference for unit price
            if (currentSortValue === 'price' && savedDefaultSort === 'price_per_unit') {
                console.log('🔥 APPLY SAVED: About to change dropdown value, flag status', {
                    isApplyingSavedSorting: window.isApplyingSavedSorting
                });

                sortByElem.value = 'price_per_unit';
                
                console.log('🔥 APPLY SAVED: Changed sort dropdown from price to price_per_unit');
                
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

        /**
         * Save the current platform selections to localStorage
         */
        function savePlatformSelections() {
            try {
                const selectedPlatforms = [];
                $('input[name="platforms"]:checked').each(function() {
                    selectedPlatforms.push($(this).val());
                });
                
                localStorage.setItem('ps_selected_platforms', JSON.stringify(selectedPlatforms));
                
                logToServer('Platform Selection: Saved to cache', {
                    selectedPlatforms: selectedPlatforms,
                    platformCount: selectedPlatforms.length
                });
                
                console.log('Platform selections saved to cache:', selectedPlatforms);
            } catch (e) {
                console.warn('Could not save platform selections to localStorage:', e);
                logToServer('Platform Selection: Failed to save to cache', {
                    error: e.message
                });
            }
        }

        /**
         * Restore platform selections from localStorage
         */
        function restorePlatformSelections() {
            try {
                const savedPlatforms = localStorage.getItem('ps_selected_platforms');
                if (savedPlatforms) {
                    const platformsArray = JSON.parse(savedPlatforms);
                    
                    if (Array.isArray(platformsArray) && platformsArray.length > 0) {
                        // First, uncheck all platforms
                        $('input[name="platforms"]').prop('checked', false);
                        
                        // Then check the saved platforms
                        platformsArray.forEach(function(platform) {
                            $('input[name="platforms"][value="' + platform + '"]').prop('checked', true);
                        });
                        
                        logToServer('Platform Selection: Restored from cache', {
                            restoredPlatforms: platformsArray,
                            platformCount: platformsArray.length
                        });
                        
                        console.log('Platform selections restored from cache:', platformsArray);
                        return true;
                    }
                }
                
                logToServer('Platform Selection: No cached selections found');
                console.log('No cached platform selections found');
                return false;
            } catch (e) {
                console.warn('Could not restore platform selections from localStorage:', e);
                logToServer('Platform Selection: Failed to restore from cache', {
                    error: e.message
                });
                return false;
            }
        }

        /**
         * Clear saved platform selections from localStorage
         */
        function clearPlatformSelections() {
            try {
                localStorage.removeItem('ps_selected_platforms');
                logToServer('Platform Selection: Cleared cached selections');
                console.log('Platform selections cleared from cache');
            } catch (e) {
                console.warn('Could not clear platform selections from localStorage:', e);
                logToServer('Platform Selection: Failed to clear cache', {
                    error: e.message
                });
            }
        }

        /**
         * Auto-detect and check platform checkboxes based on available results
         * @param {Array} products - Array of product items
         */
        function autoDetectAndCheckPlatforms(products) {
            if (!products || products.length === 0) {
                console.log('Platform Auto-Detection: No products to analyze');
                logToServer('Platform Auto-Detection: No products to analyze');
                return;
            }

            // Get all unique platforms from the products
            const availablePlatforms = [...new Set(products.map(item => item.platform))].filter(Boolean);
            

            logToServer('Platform Auto-Detection: Detected platforms', {
                availablePlatforms: availablePlatforms,
                totalProducts: products.length
            });

            // If we have cached platform selections, restore those first
            const restoredFromCache = restorePlatformSelections();
            
            if (!restoredFromCache) {
                // No cached selections, so check all available platforms

                $('input[name="platforms"]').prop('checked', false);
                availablePlatforms.forEach(function(platform) {
                    $('input[name="platforms"][value="' + platform + '"]').prop('checked', true);
                });
                

                logToServer('Platform Auto-Detection: Checked all available platforms (no cache)', {
                    checkedPlatforms: availablePlatforms
                });
            } else {
                // We restored from cache, but also check any new platforms that have results
                // but weren't in the cached selections
                const currentlySelected = [];
                $('input[name="platforms"]:checked').each(function() {
                    currentlySelected.push($(this).val());
                });
                
                const newPlatforms = availablePlatforms.filter(platform => !currentlySelected.includes(platform));
                

                
                if (newPlatforms.length > 0) {
                    newPlatforms.forEach(function(platform) {
                        $('input[name="platforms"][value="' + platform + '"]').prop('checked', true);
                    });
                    

                    logToServer('Platform Auto-Detection: Added new platforms to cached selections', {
                        cachedPlatforms: currentlySelected,
                        newPlatforms: newPlatforms,
                        finalPlatforms: [...currentlySelected, ...newPlatforms]
                    });
                    
                    // Save the updated selections
                    savePlatformSelections();
                }
            }
            
            // Final verification - log what's actually checked
            const finalSelected = [];
            $('input[name="platforms"]:checked').each(function() {
                finalSelected.push($(this).val());
            });

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
            
            // Get current search parameters first
            const queryElem = document.getElementById('ps-search-query');
            const excludeElem = document.getElementById('ps-exclude-keywords');
            const sortByElem = document.getElementById('ps-sort-by');
            const minRatingElem = document.getElementById('ps-min-rating');
            const countryElem = document.querySelector('input[name="country"]:checked');
            
            // Get currently selected platforms
            const selectedPlatforms = [];
            const platformCheckboxes = document.querySelectorAll('input[name="platforms"]:checked');
            platformCheckboxes.forEach(checkbox => {
                selectedPlatforms.push(checkbox.value);
            });
            
            const query = lastSearchQuery; // Use the actual search query that was performed, not current input value
            const exclude = excludeElem ? excludeElem.value.trim() : '';
            const sortBy = sortByElem ? sortByElem.value : 'price';
            const minRating = minRatingElem ? minRatingElem.value : '4.0';
            const country = countryElem ? countryElem.value : lastSearchCountry;
            
            // Increment page for next page
            const nextPage = currentPage + 1;
            
            // Show loading state (after variables are declared)

            logToServer('Load More: Spinner started', { currentPage, nextPage });
            $button.prop('disabled', true);
            $topButton.prop('disabled', true);
            $loadMoreText.hide();
            $loadMoreSpinner.show();
            
            // Immediately disable the search button when load more is clicked
            const searchButton = document.querySelector('.ps-search-button');
            if (searchButton) {
                searchButton.disabled = true;

            }
            

            
            // Check if we have the required data for load more
            if (!query || !lastSearchQuery) {
                console.error('Load More: Missing search query', { query, lastSearchQuery });
                logToServer('Load More: Missing search query', { query, lastSearchQuery });
                
                // Reset button state and hide buttons

                logToServer('Load More: Spinner stopped', { reason: 'missing_search_query' });
                $button.prop('disabled', false);
                $topButton.prop('disabled', false);
                $loadMoreText.show();
                $loadMoreSpinner.hide();
                
                // Re-enable search button on error
                const searchButton = document.querySelector('.ps-search-button');
                if (searchButton) {
                    searchButton.disabled = false;
                }
                
                toggleLoadMoreButton(false);
                return;
            }
            
            // Check if we have at least one platform selected
            if (selectedPlatforms.length === 0) {
                console.error('Load More: No platforms selected');
                logToServer('Load More: No platforms selected', { selectedPlatforms });
                
                // Reset button state and show error

                logToServer('Load More: Spinner stopped', { reason: 'no_platforms_selected' });
                $button.prop('disabled', false);
                $topButton.prop('disabled', false);
                $loadMoreText.show();
                $loadMoreSpinner.hide();
                
                // Re-enable search button on error
                const searchButton = document.querySelector('.ps-search-button');
                if (searchButton) {
                    searchButton.disabled = false;
                }
                
                // Show error message
                $container.after('<div class="ps-load-more-error" style="text-align: center; padding: 20px; color: #d63031;">Please select at least one platform to load more results.</div>');
                
                // Remove error message after 5 seconds
                setTimeout(function() {
                    $('.ps-load-more-error').fadeOut(function() {
                        $(this).remove();
                    });
                }, 5000);
                
                return;
            }
            
            logToServer('Load More: Requesting page ' + nextPage, {
                query: query,
                country: country,
                currentPage: currentPage,
                nextPage: nextPage,
                lastSearchQuery: lastSearchQuery,
                selectedPlatforms: selectedPlatforms,
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
                    platforms: selectedPlatforms, // Send selected platforms
                    page: nextPage
                },
                success: function(response) {

                    
                    if (response.success && response.items && response.items.length > 0) {
                        // Backend has already merged, filtered, and sorted the complete dataset
                        const completeItems = response.items;
                        const newItemsCount = response.new_items_count || 0;
                        

                        
                        // Update current page
                        currentPage = response.page_loaded || (currentPage + 1);
                        
                        // Update the original cached results with the complete merged dataset from server
                        // Note: The server has already merged new items with existing ones
                        originalCachedResults = completeItems;
                        
                        // Apply all current filters to the complete dataset
                        // This ensures any client-side filtering (platform selection, include/exclude text, etc.) is applied

                        
                        logToServer('Load More: Auto-applying filters after load more', {
                            totalItemsBeforeFilter: completeItems.length,
                            newItemsAdded: newItemsCount
                        });
                        
                        applyAllFilters();
                        
                        // Add animation class to highlight that new content was loaded
                        setTimeout(() => {
                            $('.ps-product').addClass('ps-refreshed-content');
                            // Remove the animation class after animation completes
                            setTimeout(() => {
                                $('.ps-product').removeClass('ps-refreshed-content');
                            }, 500);
                        }, 100);
                        
                        // Reset the flag so results count shows properly
                        isAfterLiveSearch = false;
                        
                        // Check if more pages are available
                        
                        if (!response.has_more_pages) {
                            // Hide load more buttons when no more pages

                            toggleLoadMoreButton(false);
                        }
                        
                        logToServer('Load More: Successfully loaded page ' + (response.page_loaded || currentPage), {
                            newItemsCount: newItemsCount,
                            totalItemsCount: completeItems.length,
                            displayItemsCount: currentSearchResults.length,
                            hasMorePages: response.has_more_pages || false
                        });
                        
                        // Reset loading state immediately before starting cooldown
                        console.log('Load More: Spinner stopped - successful load completed');
                        logToServer('Load More: Spinner stopped', { 
                            reason: 'successful_load', 
                            newItemsCount: newItemsCount,
                            totalItems: completeItems.length 
                        });
                        $button.prop('disabled', false);
                        $topButton.prop('disabled', false);
                        $loadMoreText.show();
                        $loadMoreSpinner.hide();
                        
                        // Start shared cooldown after successful load more
                        console.log('Load More: Starting cooldown timer');
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
                        console.log('Load More: Spinner stopped - no more results available');
                        logToServer('Load More: Spinner stopped', { 
                            reason: 'no_more_results',
                            success: response.success,
                            hasItems: !!(response.items),
                            itemsLength: response.items ? response.items.length : 0
                        });
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
                    console.log('Load More: Spinner stopped - AJAX error occurred');
                    logToServer('Load More: Spinner stopped', { 
                        reason: 'ajax_error',
                        status: status,
                        error: error,
                        page: nextPage
                    });
                    
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
                    
                    // Re-enable search button on AJAX error
                    const searchButton = document.querySelector('.ps-search-button');
                    if (searchButton) {
                        searchButton.disabled = false;
                    }
                    
                    // Show error message
                    $container.after('<div class="ps-load-more-error" style="text-align: center; padding: 20px; color: #d63031;">Failed to load more results. Please try again.</div>');
                    
                    // Hide buttons on error
                    toggleLoadMoreButton(false);
                },
                complete: function() {
                    // Reset button state (final cleanup)
                    console.log('Load More: AJAX complete - final spinner cleanup');
                    logToServer('Load More: AJAX complete', { reason: 'final_cleanup' });
                    $button.prop('disabled', false);
                    $topButton.prop('disabled', false);
                    $loadMoreText.show();
                    $loadMoreSpinner.hide();
                    
                    // Ensure search button is re-enabled as final fallback
                    const searchButton = document.querySelector('.ps-search-button');
                    if (searchButton && searchButton.disabled) {
                        searchButton.disabled = false;
                        console.log('Load More: Re-enabled search button in complete handler');
                    }
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