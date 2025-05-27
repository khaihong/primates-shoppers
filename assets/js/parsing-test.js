(function($) {
    'use strict';

    $(document).ready(function() {
        // Handle source type change
        $('#ps-source-type').on('change', function() {
            const sourceType = $(this).val();
            $('.ps-source-option').hide();
            $(`#ps-${sourceType}-source`).show();
        });
        
        // Run test button handler
        $('#ps-run-test').on('click', function() {
            console.log("Parsing test button clicked");
            runParsingTest();
        });
    });
    
    /**
     * Run the parsing test via AJAX
     */
    function runParsingTest() {
        console.log("runParsingTest function executing");
        const sourceType = $('#ps-source-type').val();
        const country = $('#ps-country').val();
        
        let data = {
            action: 'ps_test_parsing',
            nonce: psParsing.nonce,
            source_type: sourceType,
            country: country
        };
        
        // Add source-specific data
        if (sourceType === 'file') {
            const filePath = $('#ps-file-path').val();
            if (!filePath) {
                alert('Please select a file to test.');
                return;
            }
            data.file_path = filePath;
        } else if (sourceType === 'url') {
            const url = $('#ps-url').val();
            if (!url) {
                alert('Please enter a URL to test.');
                return;
            }
            data.url = url;
        } else if (sourceType === 'text') {
            const htmlContent = $('#ps-html-content').val();
            if (!htmlContent) {
                alert('Please enter HTML content to test.');
                return;
            }
            data.html_content = htmlContent;
        }
        
        // Show loading state
        $('#ps-run-test').prop('disabled', true).text('Testing...');
        $('#ps-parsing-results').hide();
        
        console.log("Sending AJAX request with data:", data);
        
        $.post(psParsing.ajaxurl, data, function(response) {
            console.log("Raw AJAX response:", response);
            console.log("Response success:", response.success);
            console.log("Response data:", response.data);
            console.log("Debug extraction data:", response.data?.debug_extraction);
            console.log("Sample products:", response.data?.xpath_results?.sample_products);
            
            $('#ps-run-test').prop('disabled', false).text('Run Parsing Test');
            
            if (response.success) {
                displayResults(response.data);
            } else {
                console.error("Error response:", response.data);
                alert('Error: ' + (response.data?.message || 'Unknown error occurred'));
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error("AJAX request failed:", {
                status: jqXHR.status,
                statusText: jqXHR.statusText,
                responseText: jqXHR.responseText,
                textStatus: textStatus,
                errorThrown: errorThrown
            });
            $('#ps-run-test').prop('disabled', false).text('Run Parsing Test');
            alert('AJAX Error: ' + textStatus + ' - ' + errorThrown);
        });
    }
    
    /**
     * Display the test results
     */
    function displayResults(data) {
        // Debug output
        console.log('Parsing test response data:', data);
        console.log('Sample products:', data.xpath_results.sample_products);
        
        // Display Amazon blocking status
        const blockingStatus = data.amazon_blocking ? 
            '<span style="color: red;">YES - Amazon is blocking requests</span>' : 
            '<span style="color: green;">No - Amazon is not blocking</span>';
        $('#ps-blocking-result').html(blockingStatus);
        
        // Display sample product titles if available
        if (data.xpath_results.sample_products && data.xpath_results.sample_products.length > 0) {
            $('#ps-sample-products').show();
            
            // Create a detailed table for product data
            let productTableHtml = `
                <table class="ps-product-table">
                    <thead>
                        <tr>
                            <th>Field</th>
                            ${data.xpath_results.sample_products.map((_, index) => 
                                `<th>Product ${index + 1}</th>`).join('')}
                        </tr>
                    </thead>
                    <tbody>`;
            
            // Check if we have full product objects or just titles
            const isFullProductData = typeof data.xpath_results.sample_products[0] === 'object' && 
                                     Object.keys(data.xpath_results.sample_products[0]).length > 1;
            
            if (isFullProductData) {
                // Get all possible keys from all products
                const allKeys = new Set();
                data.xpath_results.sample_products.forEach(product => {
                    Object.keys(product).forEach(key => allKeys.add(key));
                });
                
                // Add rows for each field - prioritize title, brand, delivery time and their extraction methods
                const priorityFields = ['title', 'title_extraction_method', 'brand', 'brand_extraction_method', 'delivery_time', 'delivery_extraction_method'];
                const otherFields = Array.from(allKeys).filter(key => !priorityFields.includes(key)).sort();
                const orderedFields = [...priorityFields, ...otherFields];
                
                orderedFields.forEach(key => {
                    // Highlight important rows
                    const isHighlighted = key === 'title' || key.includes('_extraction_method');
                    const rowStyle = isHighlighted ? 'background-color: #f8f9fa; font-weight: bold;' : '';
                    
                    productTableHtml += `
                        <tr style="${rowStyle}">
                            <td><strong>${key}</strong></td>
                            ${data.xpath_results.sample_products.map(product => {
                                let value = product[key] || '';
                                
                                // Format specific fields
                                if (key === 'image' && value) {
                                    value = `<img src="${value}" style="max-height: 50px;" alt="Product image">`;
                                } else if (key === 'link' && value) {
                                    value = `<a href="${value}" target="_blank">View</a>`;
                                } else if (key === 'price' && value) {
                                    value = `<span style="color: #e63946;">${value}</span>`;
                                } else if (key === 'title_extraction_method' && value) {
                                    value = `<span style="color: #2a9d8f; font-weight: bold;">${value}</span>`;
                                } else if (key === 'brand_extraction_method' && value) {
                                    value = `<span style="color: #f77f00; font-weight: bold;">${value}</span>`;
                                } else if (key === 'delivery_extraction_method' && value) {
                                    value = `<span style="color: #6f42c1; font-weight: bold;">${value}</span>`;
                                } else if (key === 'brand' && value) {
                                    value = `<span style="color: #d63384; font-weight: bold;">${value}</span>`;
                                } else if (key === 'delivery_time' && value) {
                                    // Highlight FREE in delivery time
                                    if (value.toLowerCase().includes('free')) {
                                        value = value.replace(/free/gi, '<span style="color: #198754; font-weight: bold;">FREE</span>');
                                    }
                                    value = `<span style="color: #0d6efd;">${value}</span>`;
                                }
                                
                                return `<td>${value}</td>`;
                            }).join('')}
                        </tr>`;
                });
            } else {
                // Simple format - just titles
                productTableHtml += `
                    <tr>
                        <td><strong>title</strong></td>
                        ${data.xpath_results.sample_products.map(product => {
                            const title = typeof product === 'object' ? (product.title || '') : product;
                            return `<td>${title}</td>`;
                        }).join('')}
                    </tr>`;
            }
            
            productTableHtml += `</tbody></table>`;
            
            // Display debug extraction info if available
            if (data.debug_extraction && Object.keys(data.debug_extraction).length > 0) {
                productTableHtml += `
                    <h4>Debug Extraction Information</h4>
                    <p>The debug information below shows all the values found during extraction for each field.</p>
                `;
                
                // Create debug table for each product
                Object.keys(data.debug_extraction).forEach(productIndex => {
                    const debugData = data.debug_extraction[productIndex];
                    
                    productTableHtml += `
                        <div class="ps-debug-product">
                            <h5>Product ${parseInt(productIndex) + 1} Debug Info</h5>
                            <table class="ps-debug-table">
                                <thead>
                                    <tr>
                                        <th>Selector/Method</th>
                                        <th>Value Found</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    // Title-related entries first
                    const titleKeys = Object.keys(debugData).filter(key => 
                        key.includes('title') || key.includes('h2') || key.includes('text')
                    );
                    
                    titleKeys.forEach(key => {
                        productTableHtml += `
                            <tr class="ps-debug-title-row">
                                <td><strong>${key}</strong></td>
                                <td>${debugData[key] || ''}</td>
                            </tr>
                        `;
                    });
                    
                    // Other data
                    Object.keys(debugData).filter(key => !titleKeys.includes(key)).forEach(key => {
                        productTableHtml += `
                            <tr>
                                <td><strong>${key}</strong></td>
                                <td>${debugData[key] || ''}</td>
                            </tr>
                        `;
                    });
                    
                    productTableHtml += `
                                </tbody>
                            </table>
                        </div>
                    `;
                });
            }
            
            $('#ps-sample-product-list').html(productTableHtml);
            
            // Add CSS for the tables
            if (!$('#ps-product-table-styles').length) {
                $('head').append(`
                    <style id="ps-product-table-styles">
                        .ps-product-table, .ps-debug-table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-top: 10px;
                            font-size: 14px;
                        }
                        .ps-product-table th, .ps-product-table td,
                        .ps-debug-table th, .ps-debug-table td {
                            border: 1px solid #ddd;
                            padding: 8px;
                            text-align: left;
                        }
                        .ps-product-table th, .ps-debug-table th {
                            background-color: #f2f2f2;
                            font-weight: bold;
                        }
                        .ps-product-table tr:nth-child(even),
                        .ps-debug-table tr:nth-child(even) {
                            background-color: #f9f9f9;
                        }
                        .ps-debug-title-row {
                            background-color: #e6f3ff !important;
                        }
                        .ps-debug-product {
                            margin-top: 20px;
                            padding: 10px;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                        }
                    </style>
                `);
            }
        } else {
            $('#ps-sample-products').hide();
        }
        
        // Display XPath selector results
        let xpathSelectorsHtml = '';
        
        for (const [selector, count] of Object.entries(data.xpath_results.selector_counts)) {
            const isSelected = (selector === data.xpath_results.selected_selector);
            const style = isSelected ? 'color: green; font-weight: bold;' : '';
            xpathSelectorsHtml += `<div style="${style}">
                ${selector}: <span class="count">${count}</span>
                ${isSelected ? ' (Selected)' : ''}
            </div>`;
        }
        
        $('#ps-xpath-selectors').html(xpathSelectorsHtml);
        
        // Display alternative XPath selector results
        let altXpathSelectorsHtml = '';
        
        for (const [selector, count] of Object.entries(data.xpath_results.alternative_selector_counts)) {
            altXpathSelectorsHtml += `<div>
                ${selector}: <span class="count">${count}</span>
            </div>`;
        }
        
        $('#ps-alt-xpath-selectors').html(altXpathSelectorsHtml);
        
        // Display XML errors
        const xmlErrorCount = data.xpath_results.xml_errors;
        let xmlErrorsHtml = `${xmlErrorCount} errors`;
        
        if (xmlErrorCount > 0 && data.xpath_results.xml_error_samples.length > 0) {
            xmlErrorsHtml += ' (Samples: ';
            xmlErrorsHtml += data.xpath_results.xml_error_samples.join(', ');
            xmlErrorsHtml += ')';
        }
        
        $('#ps-xml-errors').html(xmlErrorsHtml);
        
        // Display HTML sample
        $('#ps-html-preview').text(data.html_sample);
        
        // Show results container
        $('#ps-parsing-results').show();
    }
    
})(jQuery); 