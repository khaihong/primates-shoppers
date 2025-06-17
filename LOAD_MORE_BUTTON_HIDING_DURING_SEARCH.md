# Load More Button Hiding During New Search

## Overview
This feature hides the load more buttons when a new search is initiated and only shows them again after the search results are displayed. This provides better visual feedback and prevents users from accidentally clicking load more during an active search operation.

## Problem Addressed
Previously, load more buttons remained visible during new searches, which could:
- Confuse users about whether they were clicking a valid button
- Allow accidental clicks during search operations
- Create inconsistent UI states during search transitions
- Make it unclear when load more functionality was available

## Implementation Details

### 1. Button Hiding Triggers

#### Early Validation Errors
```javascript
if (!query) {
    resultsContainer.innerHTML = '<div class="ps-error">Please enter search keywords.</div>';
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
```

#### Configuration Errors
```javascript
if (!window.psData || !window.psData.ajaxurl || !window.psData.nonce) {
    console.error('psData is not properly initialized', window.psData);
    resultsContainer.innerHTML = '<div class="ps-error">Configuration error. Please refresh the page or contact support.</div>';
    $resultsCount.hide();
    // Hide load more buttons on configuration error
    toggleLoadMoreButton(false);
    return;
}
```

#### Search Initiation
```javascript
// Hide load more buttons during search
console.log('New Search: Hiding load more buttons during search');
logToServer('New Search: Hiding load more buttons', { 
    query: query, 
    platforms: platforms 
});
toggleLoadMoreButton(false);

// Show loading message
resultsContainer.innerHTML = '<div class="ps-loading">Searching...</div>';
```

### 2. Button Restoration After Results

#### Successful Search with Pagination
```javascript
if (hasLoadMoreCapability) {
    console.log('New Search: Showing load more buttons - pagination available');
    logToServer('New Search: Showing load more buttons', { 
        hasLoadMoreCapability: true,
        platforms: platforms,
        hasAmazonPagination: hasAmazonPagination,
        hasEbay: hasEbay
    });
    toggleLoadMoreButton(true);
} else {
    console.log('New Search: Load more buttons remain hidden - no pagination capability');
    logToServer('New Search: Load more buttons remain hidden', { 
        hasLoadMoreCapability: false,
        platforms: platforms,
        hasAmazonPagination: hasAmazonPagination,
        hasEbay: hasEbay
    });
    toggleLoadMoreButton(false);
}
```

#### Search Errors and No Results
```javascript
// Hide load more button when there's an error
toggleLoadMoreButton(false);

// Hide load more button when no results
toggleLoadMoreButton(false);

// Hide load more buttons on AJAX error
toggleLoadMoreButton(false);
```

## User Experience Flow

### Before Implementation
1. User clicks "New Search"
2. Load more buttons remain visible during search
3. User might accidentally click load more during search
4. Confusing UI state during search operation
5. Results appear → Button state determined

### After Implementation
1. User clicks "New Search"
2. **Load more buttons immediately hidden**
3. Search loading state displayed
4. AJAX request executed
5. Results processed
6. **Buttons shown only if pagination available**

## Error Handling Coverage

### Validation Errors
- **Missing search query**: Buttons hidden
- **No platforms selected**: Buttons hidden
- **Configuration errors**: Buttons hidden

### Search Process Errors
- **AJAX errors**: Buttons hidden
- **Server errors**: Buttons hidden (via error response handler)
- **No results found**: Buttons hidden

### Success Cases
- **Results with pagination**: Buttons shown
- **Results without pagination**: Buttons remain hidden

## Logging Implementation

### Search Initiation Logging
```javascript
logToServer('New Search: Hiding load more buttons', { 
    query: query, 
    platforms: platforms 
});
```

### Button Restoration Logging
```javascript
// When showing buttons
logToServer('New Search: Showing load more buttons', { 
    hasLoadMoreCapability: true,
    platforms: platforms,
    hasAmazonPagination: hasAmazonPagination,
    hasEbay: hasEbay
});

// When keeping buttons hidden
logToServer('New Search: Load more buttons remain hidden', { 
    hasLoadMoreCapability: false,
    platforms: platforms,
    hasAmazonPagination: hasAmazonPagination,
    hasEbay: hasEbay
});
```

## Integration Points

### Form Submission Handler
- Validates inputs and hides buttons on errors
- Hides buttons before starting AJAX request
- Logs button state changes

### AJAX Success Handler
- Analyzes pagination capability
- Shows buttons only when appropriate
- Logs decision reasoning

### AJAX Error Handler
- Ensures buttons are hidden on all error types
- Maintains consistent error state

### Search Response Processing
- Handles both success and error responses
- Applies button visibility based on search outcome
- Integrates with existing cooldown system

## Benefits

### User Experience
- **Clear Visual Feedback**: Users know when search is active
- **Prevents Confusion**: No ambiguous button states
- **Consistent Behavior**: Predictable button visibility patterns
- **Error Prevention**: Can't accidentally click load more during search

### Technical
- **State Management**: Clean button state transitions
- **Error Handling**: Comprehensive error state coverage
- **Logging**: Full visibility into button state changes
- **Integration**: Works with existing cooldown and pagination systems

## Testing Scenarios

### Basic Functionality
1. **Valid Search**: Buttons hidden → Results displayed → Buttons shown/hidden based on pagination
2. **Invalid Query**: Buttons hidden on validation error
3. **No Platforms**: Buttons hidden on validation error
4. **Configuration Error**: Buttons hidden on system error

### Error Conditions
1. **AJAX Error**: Buttons hidden on network/server errors
2. **No Results**: Buttons remain hidden when no products found
3. **Server Error**: Buttons hidden on 500/403 errors

### Edge Cases
1. **Rapid Clicks**: Cooldown system prevents multiple rapid searches
2. **Page Navigation**: Button state properly reset on page load
3. **Platform Changes**: Buttons respond to platform selection changes

## Related Files
- `assets/js/search.js` - Main implementation
- `LOAD_MORE_ENHANCED_SUMMARY.md` - Overall load more functionality
- `LOAD_MORE_SPINNER_FIX.md` - Spinner state management
- `PLATFORM_SELECTION_CACHING.md` - Platform selection persistence 