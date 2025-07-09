<?php
/**
 * Common utility functions for Primates Shoppers
 * This file consolidates shared functionality across platforms
 */

/**
 * Parse price string with intelligent comma handling
 * Handles both thousands separators (1,049.99) and European decimals (1,99)
 * 
 * @param string $price_str Raw price string (e.g., "$1,049.99")
 * @return float Parsed price value
 */
function ps_parse_price($price_str) {
    if (empty($price_str)) {
        return 0.0;
    }
    
    // Extract and convert price with intelligent comma handling
    $price_clean = preg_replace('/[^0-9.,]/', '', $price_str);
    
    // Handle commas intelligently to distinguish thousands separators from decimal separators
    if (preg_match('/^\d{1,3}(,\d{3})+(\.\d{2})?$/', $price_clean)) {
        // Thousands separator format (e.g., "1,049.99" or "12,345")
        $price_clean = str_replace(',', '', $price_clean);
    } elseif (preg_match('/^\d+,\d{2}$/', $price_clean)) {
        // European decimal format (e.g., "1,99")
        $price_clean = str_replace(',', '.', $price_clean);
    }
    // For all other cases, remove commas (safest approach)
    else {
        $price_clean = str_replace(',', '', $price_clean);
    }
    
    return (float) $price_clean;
}

/**
 * Extract numeric value from price string (for unit prices)
 * 
 * @param string $price_str Price string
 * @return float Numeric value
 */
function ps_extract_numeric_price($price_str) {
    return (float) preg_replace('/[^0-9.]/', '', $price_str);
}

/**
 * Validate if a unit string represents a real unit of measure
 * 
 * @param string $unit Unit string to validate
 * @return bool True if valid unit
 */
function ps_is_valid_unit($unit) {
    if (empty($unit)) {
        return false;
    }
    
    return preg_match('/(?:ml|g|gram|grams|oz|ounce|ounces|lb|pound|pounds|kg|kilogram|kilograms|unit|count|piece|pieces|pack|packs|each|item|items|fl\s*oz)\b/i', $unit);
}

/**
 * Perform unit price reasonableness check
 * 
 * @param float $unit_price_numeric Unit price value
 * @param float $total_price Total product price
 * @param string $context Context for logging (e.g., "amazon", "ebay")
 * @return bool True if reasonable, false if suspicious
 */
function ps_validate_unit_price($unit_price_numeric, $total_price, $context = '') {
    if ($unit_price_numeric <= 0 || $total_price <= 0) {
        return false;
    }
    
    $ratio = $unit_price_numeric / $total_price;
    
    // If unit price is more than 50x the product price, it's likely wrong
    if ($ratio > 50) {
        // ps_log_error("Unit price reasonableness check failed ({$context}): ratio " . number_format($ratio, 2) . " too high");
        return false;
    }
    
    // If unit price is less than 1/1000th of product price, also suspicious
    if ($ratio < 0.001) {
        // ps_log_error("Unit price reasonableness check failed ({$context}): ratio " . number_format($ratio, 6) . " too low");
        return false;
    }
    
    return true;
}

/**
 * Extract unit price from Amazon-style container text
 * Common logic for parsing unit prices from parenthetical expressions
 * 
 * @param DOMXPath $xpath XPath object
 * @param DOMElement $element Product element
 * @param float $total_price Total product price for validation
 * @param string $context Context for logging
 * @return array ['unit_price' => string, 'unit' => string, 'unit_price_numeric' => float]
 */
function ps_extract_amazon_unit_price($xpath, $element, $total_price, $context = 'amazon') {
    $unit_price = '';
    $unit = '';
    $unit_price_numeric = 0;
    
    // Look for the unit price container that contains parentheses
    $unitPriceContainers = $xpath->query('.//span[contains(@class, "a-size-base") and contains(@class, "a-color-secondary")]', $element);
    $unitPriceContainer = null;
    
    // Find the container that actually contains parentheses with unit price
    foreach ($unitPriceContainers as $container) {
        $container_text = trim($container->textContent);
        if (strpos($container_text, '(') !== false && strpos($container_text, ')') !== false && strpos($container_text, '/') !== false) {
            $unitPriceContainer = $container;
            break;
        }
    }
    
    if ($unitPriceContainer) {
        $container_text = trim($unitPriceContainer->textContent);
        
        // Check if it contains parentheses (indicating unit price)
        if (strpos($container_text, '(') !== false && strpos($container_text, ')') !== false) {
            // Extract the price from the offscreen span for accuracy
            $unitPriceNode = $xpath->query('.//span[contains(@class, "a-price a-text-price")]/span[@class="a-offscreen"]', $element)->item(0);
            if ($unitPriceNode) {
                $unit_price_val = trim($unitPriceNode->textContent);
                
                // Extract the unit from the container text (e.g., "/100 ml" from "($3.49$3.49/100 ml)")
                if (preg_match('/\/([^)]+)\)/', $container_text, $unitMatch)) {
                    $unit = trim($unitMatch[1]);
                    $unit_price = $unit_price_val . '/' . $unit;
                    
                    // Reasonableness check
                    $unit_price_numeric = ps_extract_numeric_price($unit_price_val);
                    if (!ps_validate_unit_price($unit_price_numeric, $total_price, $context)) {
                        $unit_price = '';
                        $unit = '';
                        $unit_price_numeric = 0;
                    }
                } else {
                    $unit_price = $unit_price_val;
                    $unit_price_numeric = ps_extract_numeric_price($unit_price_val);
                }
            } else {
                // Fallback: try to extract from the container text directly
                if (preg_match('/\(([^)]+)\)/', $container_text, $match)) {
                    $extracted_content = trim($match[1]);
                    // Try to clean up duplicate price values (e.g., "$3.49$3.49/100 ml" -> "$3.49/100 ml")
                    if (preg_match('/(\$[\d.]+).*?\/(.+)$/', $extracted_content, $cleanMatch)) {
                        $unit_price = $cleanMatch[1] . '/' . $cleanMatch[2];
                        $unit = $cleanMatch[2];
                        
                        // Apply the same reasonableness check
                        $unit_price_numeric = ps_extract_numeric_price($cleanMatch[1]);
                        if (!ps_validate_unit_price($unit_price_numeric, $total_price, $context)) {
                            $unit_price = '';
                            $unit = '';
                            $unit_price_numeric = 0;
                        }
                    } else {
                        $unit_price = $extracted_content;
                        $unit_price_numeric = ps_extract_numeric_price($unit_price);
                    }
                }
            }
        }
    }
    
    // Fallback to the old method if the new method doesn't work
    if (empty($unit_price)) {
        $unitPriceNode = $xpath->query('.//span[contains(@class, "a-price a-text-price")]/span[@class="a-offscreen"]', $element)->item(0);
        $unitTextNode = $xpath->query('.//span[contains(@class, "a-size-base a-color-secondary")]', $element)->item(0);
        if ($unitPriceNode && $unitTextNode) {
            $unit_price_val = trim($unitPriceNode->textContent);
            // Try to extract unit (e.g., /100 ml) from the text node
            if (preg_match('/\/([\d\w\s.]+)/', $unitTextNode->textContent, $unitMatch)) {
                $unit = trim($unitMatch[1]);
                $unit_price = $unit_price_val . '/' . $unit;
            } else {
                $unit_price = $unit_price_val;
            }
            $unit_price_numeric = ps_extract_numeric_price($unit_price_val);
        }
    }
    
    // Clear unit price data if no actual unit of measure exists
    if (!empty($unit_price) && !ps_is_valid_unit($unit)) {
        $unit_price = '';
        $unit = '';
        $unit_price_numeric = 0;
    }
    
    return [
        'unit_price' => $unit_price,
        'unit' => $unit,
        'unit_price_numeric' => $unit_price_numeric
    ];
}

/**
 * Detect the user's country from server variables or headers
 * @return string 'us' or 'ca'
 */
if (!function_exists('ps_detect_user_country')) {
    function ps_detect_user_country() {
        $country = 'us'; // Default to US
        $ip_address = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : $_SERVER['REMOTE_ADDR'];
        if (isset($_SERVER['HTTP_CF_IPCOUNTRY']) && !empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            $detected_country = strtolower($_SERVER['HTTP_CF_IPCOUNTRY']);
            if ($detected_country === 'ca') {
                $country = 'ca';
            }
        } else if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach ($langs as $lang) {
                if (strpos(strtolower($lang), 'en-ca') !== false || strpos(strtolower($lang), 'fr-ca') !== false) {
                    $country = 'ca';
                    break;
                }
            }
        }
        return $country;
    }
}

/**
 * Common product filtering logic
 * 
 * @param array $items Product items
 * @param string $includeText Text that must be in title
 * @param string $excludeText Text that must NOT be in title
 * @param float|null $minRating Minimum rating filter
 * @return array Filtered items
 */
function ps_filter_products($items, $includeText = '', $excludeText = '', $minRating = null) {
    $filtered_items = [];
    
    foreach ($items as $item) {
        $skip_item = false;
        
        // Include text filtering
        if (!empty($includeText)) {
            $include_terms = array_map('trim', explode(',', $includeText));
            $title_lower = strtolower($item['title'] ?? '');
            
            $has_include_term = false;
            foreach ($include_terms as $term) {
                if (!empty($term) && strpos($title_lower, strtolower($term)) !== false) {
                    $has_include_term = true;
                    break;
                }
            }
            
            if (!$has_include_term) {
                $skip_item = true;
            }
        }
        
        // Exclude text filtering
        if (!$skip_item && !empty($excludeText)) {
            $exclude_terms = array_map('trim', explode(',', $excludeText));
            $title_lower = strtolower($item['title'] ?? '');
            
            foreach ($exclude_terms as $term) {
                if (!empty($term) && strpos($title_lower, strtolower($term)) !== false) {
                    $skip_item = true;
                    break;
                }
            }
        }
        
        // Rating filtering (for platforms that support it)
        if (!$skip_item && $minRating !== null && isset($item['rating_numeric']) && is_numeric($item['rating_numeric'])) {
            if (floatval($item['rating_numeric']) < floatval($minRating)) {
                $skip_item = true;
            }
        }
        
        if (!$skip_item) {
            $filtered_items[] = $item;
        }
    }
    
    return $filtered_items;
}

/**
 * Common product sorting logic
 * 
 * @param array $items Product items
 * @param string $sortBy Sort criteria ('price', 'price_per_unit', 'rating', 'title')
 * @return array Sorted items
 */
function ps_sort_products($items, $sortBy = 'price') {
    $sorted_items = $items; // Create copy
    
    switch ($sortBy) {
        case 'price':
            usort($sorted_items, function($a, $b) {
                $priceA = floatval($a['price_value'] ?? $a['price_numeric'] ?? 0);
                $priceB = floatval($b['price_value'] ?? $b['price_numeric'] ?? 0);
                return $priceA <=> $priceB;
            });
            break;
            
        case 'price_per_unit':
            // Separate items with and without unit prices
            $with_unit = [];
            $without_unit = [];
            
            foreach ($sorted_items as $item) {
                $has_valid_unit = !empty($item['unit']) && 
                                 $item['unit'] !== 'N/A' && 
                                 $item['unit'] !== 'unit' &&
                                 ps_is_valid_unit($item['unit']) &&
                                 floatval($item['price_per_unit_value'] ?? 0) > 0;
                
                if ($has_valid_unit) {
                    $with_unit[] = $item;
                } else {
                    $without_unit[] = $item;
                }
            }
            
            // Sort each group
            usort($with_unit, function($a, $b) {
                $priceA = floatval($a['price_per_unit_value'] ?? 0);
                $priceB = floatval($b['price_per_unit_value'] ?? 0);
                return $priceA <=> $priceB;
            });
            
            usort($without_unit, function($a, $b) {
                $priceA = floatval($a['price_value'] ?? $a['price_numeric'] ?? 0);
                $priceB = floatval($b['price_value'] ?? $b['price_numeric'] ?? 0);
                return $priceA <=> $priceB;
            });
            
            $sorted_items = array_merge($with_unit, $without_unit);
            break;
            
        case 'rating':
            usort($sorted_items, function($a, $b) {
                $ratingA = floatval($a['rating_numeric'] ?? 0);
                $ratingB = floatval($b['rating_numeric'] ?? 0);
                return $ratingB <=> $ratingA; // Descending (highest first)
            });
            break;
            
        case 'title':
            usort($sorted_items, function($a, $b) {
                return strcasecmp($a['title'] ?? '', $b['title'] ?? '');
            });
            break;
    }
    
    return $sorted_items;
}
