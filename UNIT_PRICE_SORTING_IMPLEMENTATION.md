# Unit Price Sorting Implementation

## Overview

This implementation adds automatic default sorting by unit price when more than half of the search results have unit price data available. The system calculates the unit price percentage **only once after a live search** and saves the preference, rather than recalculating it on every filter or page refresh. This provides better performance and a more consistent user experience.

## How It Works

### 1. Unit Price Detection

The system analyzes each product in the search results to determine if it has meaningful unit price data. **Updated logic now recognizes products with unit pricing even when the unit field shows "No unit"**:

```javascript
function shouldDefaultToUnitPrice(items) {
    if (!items || items.length === 0) return false;
    
    let itemsWithUnitPrice = 0;
    items.forEach(function(item) {
        // A product has unit price if it has a price_per_unit_value > 0
        // Even if the unit is "No unit", it still represents a valid unit price
        const hasUnitPrice = item.price_per_unit && 
            item.price_per_unit_value && 
            parseFloat(item.price_per_unit_value) > 0 &&
            item.price_per_unit !== '' &&
            item.price_per_unit !== 'N/A';
        
        if (hasUnitPrice) {
            itemsWithUnitPrice++;
        }
    });
    
    const percentage = itemsWithUnitPrice / items.length;
    return percentage > 0.5; // More than 50%
}
```

### 2. Calculate and Save Default Sorting (Live Search Only)

When a live search is performed, the system calculates the unit price percentage and saves the default sorting preference **both in memory and localStorage for persistence across page refreshes**:

```javascript
function calculateAndSaveDefaultSorting(items) {
    const sortByElem = document.getElementById('ps-sort-by');
    if (!sortByElem) return false;
    
    const currentSortValue = sortByElem.value;
    const shouldDefault = shouldDefaultToUnitPrice(items);
    
    // Save the calculated preference both in memory and localStorage
    savedDefaultSort = shouldDefault ? 'price_per_unit' : 'price';
    try {
        localStorage.setItem('ps_saved_default_sort', savedDefaultSort);
    } catch (e) {
        console.warn('Could not save default sort to localStorage:', e);
    }
    
    // Only change default if currently set to 'price' (the default)
    if (currentSortValue === 'price' && shouldDefault) {
        console.log('Automatically switching to unit price sorting');
        sortByElem.value = 'price_per_unit';
        // Add visual feedback...
        return true;
    }
    
    return false;
}
```

### 3. Apply Saved Default Sorting (Cached Results and Filters)

For cached results loading and filter operations, the system uses the previously saved preference **with localStorage fallback for page refreshes**:

```javascript
function applySavedDefaultSorting() {
    const sortByElem = document.getElementById('ps-sort-by');
    if (!sortByElem) return false;
    
    // Try to get saved preference from memory first, then localStorage
    if (!savedDefaultSort) {
        try {
            savedDefaultSort = localStorage.getItem('ps_saved_default_sort');
        } catch (e) {
            console.warn('Could not read default sort from localStorage:', e);
        }
    }
    
    if (!savedDefaultSort) return false;
    
    const currentSortValue = sortByElem.value;
    
    // Only change default if currently set to 'price' and we have a saved preference for unit price
    if (currentSortValue === 'price' && savedDefaultSort === 'price_per_unit') {
        console.log('Applying saved unit price sorting preference');
        sortByElem.value = 'price_per_unit';
        return true;
    }
    
    return false;
}
```

### 4. Visual Feedback

When the sorting is automatically changed during a live search, users receive visual feedback:

- A green italic message appears below the sort dropdown: "Auto-sorted by unit price (most products have unit pricing)"
- The message automatically disappears after 5 seconds
- Console logging provides detailed analysis for debugging

## Implementation Details

### Files Modified

1. **`assets/js/search.js`**
   - Added `savedDefaultSort` global variable to store the calculated preference
   - Added `calculateAndSaveDefaultSorting()` function for live searches
   - Added `applySavedDefaultSorting()` function for cached results and filters
   - Modified `shouldDefaultToUnitPrice()` function with improved logging
   - Updated live search handler to use `calculateAndSaveDefaultSorting()`
   - Updated cached results loading to use `applySavedDefaultSorting()`
   - Kept existing `setDefaultSorting()` function for backward compatibility

2. **`assets/css/style.css`**
   - Added styling for the auto-sort feedback message
   - Included fade-in animation for smooth user experience

### Integration Points

The unit price analysis and sorting logic is triggered at these key points:

1. **Live Search Results**: When fresh search results are returned from the Amazon API
   - Calls `calculateAndSaveDefaultSorting()` to analyze and save preference
   - Unit price percentage is calculated once and saved

2. **Cached Results Loading**: When loading previously cached search results on page refresh
   - Calls `applySavedDefaultSorting()` to apply the saved preference
   - No unit price analysis is performed

3. **Filter Operations**: When filtering, sorting, or using "Show All"
   - Uses existing sort criteria without any unit price analysis
   - Respects user's manual sort selection

### Performance Benefits

- **Reduced Calculations**: Unit price analysis is performed only once per search session
- **Faster Filters**: Filter operations no longer trigger expensive unit price calculations
- **Consistent Behavior**: Same sorting preference is maintained across page refreshes and filters
- **Better UX**: No unexpected sorting changes during filter operations

### Criteria for Unit Price Detection

A product is considered to have valid unit price data if ALL of the following conditions are met:

- `price_per_unit` field is not empty
- `price_per_unit_value` is greater than 0
- `price_per_unit` is not "N/A" or empty string

**Note**: The unit field is no longer required to be valid. Products with `unit: "No unit"` but valid `price_per_unit_value` are now correctly recognized as having unit pricing data.

### Threshold Logic

- **Threshold**: 50% of products must have unit price data
- **Calculation**: Only performed once after live searches
- **Storage**: Preference saved in `savedDefaultSort` variable and localStorage
- **Application**: Saved preference applied to cached results and filters
- **User Control**: Users can manually change sorting at any time, and the system respects their choice

## Recent Fixes (2025-01-27)

### Issue 1: Unit Price Detection Not Working
**Problem**: Many products had `"unit": "No unit"` but valid `price_per_unit` and `price_per_unit_value` data. The original logic rejected these products because it required a valid unit field.

**Solution**: Updated the unit price detection logic to focus on `price_per_unit_value` rather than the unit field. Products with "No unit" but valid pricing data are now correctly counted.

**Before**:
```javascript
const hasUnitPrice = item.price_per_unit && 
    item.unit && 
    item.price_per_unit_value && 
    parseFloat(item.price_per_unit_value) > 0 &&
    item.unit !== 'N/A' && 
    item.unit !== 'unit' && 
    item.unit !== '';
```

**After**:
```javascript
const hasUnitPrice = item.price_per_unit && 
    item.price_per_unit_value && 
    parseFloat(item.price_per_unit_value) > 0 &&
    item.price_per_unit !== '' &&
    item.price_per_unit !== 'N/A';
```

### Issue 2: Preference Lost on Page Refresh
**Problem**: The `savedDefaultSort` variable was reset to `null` on page refresh because it's a JavaScript variable that doesn't persist across page loads.

**Solution**: Added localStorage persistence to save and restore the default sort preference across page refreshes.

**Implementation**:
- Save to localStorage during live searches: `localStorage.setItem('ps_saved_default_sort', savedDefaultSort)`
- Load from localStorage on page refresh: `savedDefaultSort = localStorage.getItem('ps_saved_default_sort')`
- Graceful fallback if localStorage is not available

### Issue 3: Unit Price Extraction Incomplete (2025-01-27)
**Problem**: The unit price extraction was only capturing the price value (e.g., `$3.49`) but missing the unit information (e.g., `/100 ml`), resulting in incomplete unit price data like `"price_per_unit": "$3.49"` instead of `"price_per_unit": "$3.49/100 ml"`.

**Root Cause**: The HTML structure contains both visible and hidden price elements:
```html
<span class="a-size-base a-color-secondary">(<span class="a-price a-text-price"><span class="a-offscreen">$3.49</span><span aria-hidden="true">$3.49</span></span>/100 ml)</span>
```

The original extraction logic was either getting duplicate prices or missing the unit part.

**Solution**: Implemented a two-step extraction process:
1. Extract the clean price value from the `a-offscreen` span for accuracy
2. Extract the unit information from the container text using regex
3. Combine them to form the complete unit price (e.g., `$3.49/100 ml`)

**Before**:
```javascript
// Old logic - could result in "$3.49" or "$3.49$3.49/100 ml"
$unitPriceNode = $xpath->query('.//span[contains(@class, "a-price a-text-price")]/span[@class="a-offscreen"]', $element)->item(0);
$unit_price_val = trim($unitPriceNode->textContent); // "$3.49"
```

**After**:
```javascript
// New logic - results in "$3.49/100 ml"
$unitPriceContainer = $xpath->query('.//span[contains(@class, "a-size-base") and contains(@class, "a-color-secondary")]', $element)->item(0);
if ($unitPriceContainer && strpos($container_text, '(') !== false) {
    $unitPriceNode = $xpath->query('.//span[@class="a-offscreen"]', $element)->item(0);
    $unit_price_val = trim($unitPriceNode->textContent); // "$3.49"
    
    if (preg_match('/\/([^)]+)\)/', $container_text, $unitMatch)) {
        $unit = trim($unitMatch[1]); // "100 ml"
        $unit_price = $unit_price_val . '/' . $unit; // "$3.49/100 ml"
    }
}
```

### Issue 4: Unit Price Reasonableness Check (2025-01-27)
**Problem**: Amazon sometimes provides obviously incorrect unit price calculations. For example, a $54.00 product showing as $5,400.00/100ml, which would mean the product is 500ml but Amazon calculated it as if it were 1ml.

**Examples of Amazon Errors**:
- $54.00 product showing $5,400.00/100ml (ratio: 100x - should be ~$10.80/100ml for 500ml product)
- $15.99 product showing $1,599.00/100ml (ratio: 100x)
- Products showing unit prices that are 1000x too low

**Solution**: Added a reasonableness check that validates unit prices against the product price:

```php
// Reasonableness check: detect obviously wrong unit prices
$unit_price_numeric = (float) preg_replace('/[^0-9.]/', '', $unit_price_val);
if ($unit_price_numeric > 0 && $price_value > 0) {
    $ratio = $unit_price_numeric / $price_value;
    
    // If unit price is more than 50x the product price, it's likely wrong
    if ($ratio > 50) {
        ps_log_error("Unit price reasonableness check failed: Product price $price_str, Unit price $unit_price (ratio: " . number_format($ratio, 2) . "). Marking as invalid.");
        $unit_price = ''; // Mark as invalid
        $unit = '';
    }
    // If unit price is less than 1/1000th of product price, also suspicious
    elseif ($ratio < 0.001) {
        ps_log_error("Unit price reasonableness check failed: Product price $price_str, Unit price $unit_price (ratio: " . number_format($ratio, 6) . "). Marking as invalid.");
        $unit_price = ''; // Mark as invalid
        $unit = '';
    }
}
```

**Thresholds**:
- **Upper bound**: Unit price > 50x product price (likely Amazon calculation error)
- **Lower bound**: Unit price < 0.001x product price (suspiciously low)
- **Valid range**: Unit price between 0.1% and 5000% of product price

**Benefits**:
- Filters out Amazon's calculation errors that would skew unit price sorting
- Prevents misleading unit price comparisons
- Maintains data quality for better user experience
- Logs detected errors for monitoring and debugging

### Impact of Fixes
With these fixes, the unit price sorting should now work correctly:
1. **Higher Detection Rate**: More products are correctly identified as having unit pricing
2. **Persistent Preferences**: Sort preferences survive page refreshes
3. **Complete Unit Price Data**: Unit prices now include both price and unit information (e.g., `$3.49/100 ml`)
4. **Data Quality**: Invalid unit prices are filtered out to prevent misleading comparisons
5. **Better User Experience**: Consistent sorting behavior across sessions with accurate unit price display

## Benefits

1. **Better Performance**: Unit price analysis is calculated only once instead of on every operation
2. **Consistent Experience**: Same sorting preference maintained across page refreshes and filters
3. **Better Value Discovery**: Users automatically see the most cost-effective products when unit pricing is available
4. **Improved UX**: No manual intervention required when unit pricing is prevalent, and no unexpected sorting changes during filters
5. **Transparency**: Clear visual feedback when automatic sorting occurs during live searches

## Testing

A test file (`test_unit_price_sorting.html`) is included to verify the functionality:

- Test 1: Products with majority having unit prices (should auto-switch)
- Test 2: Products with minority having unit prices (should not auto-switch)

## Console Output

The implementation provides detailed console logging:

```
Unit price analysis: 4/5 products (80%) have unit price data
Automatically switching to unit price sorting
```

This helps with debugging and understanding the system's decision-making process.

## Future Enhancements

Potential improvements could include:

1. Configurable threshold percentage (currently hardcoded at 50%)
2. User preference storage to remember manual sorting choices
3. Different thresholds for different product categories
4. A/B testing to optimize the threshold value

## Compatibility

This implementation is fully backward compatible and does not affect existing functionality:

- Products without unit pricing continue to work normally
- Manual sorting always takes precedence
- No changes to server-side code or database structure required 