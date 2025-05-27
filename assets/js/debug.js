/**
 * Primates Shoppers Debug Script
 * 
 * This script adds debugging tools to diagnose AJAX issues.
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Add a debug button to the page
        const $container = $('.ps-search-container');
        if ($container.length > 0) {
            const $debugButton = $('<button id="ps-debug-button" style="position: fixed; bottom: 10px; right: 10px; z-index: 9999; background: #ff9900; color: #fff; border: none; border-radius: 4px; padding: 5px 10px;">Debug</button>');
            $('body').append($debugButton);

            $debugButton.on('click', function() {
                // Run diagnostic tests
                runDiagnostics();
            });
        }

        function runDiagnostics() {
            // Create diagnostic output container
            const $diagOutput = $('<div id="ps-diagnostics" style="position: fixed; top: 20px; left: 20px; right: 20px; bottom: 20px; background: rgba(0,0,0,0.9); color: #fff; z-index: 10000; padding: 20px; overflow: auto; font-family: monospace;"></div>');
            const $closeBtn = $('<button style="position: absolute; top: 5px; right: 5px; background: #ff5555; border: none; color: white; padding: 5px 10px;">Close</button>');
            
            $diagOutput.append($closeBtn);
            $diagOutput.append('<h3>Primates Shoppers Diagnostics</h3>');
            
            // Add psData info
            $diagOutput.append('<h4>psData Object:</h4>');
            $diagOutput.append('<pre>' + JSON.stringify(psData, null, 2) + '</pre>');
            
            // Test API connectivity
            $diagOutput.append('<h4>Testing AJAX Connection:</h4>');
            
            // Test 1: Simple AJAX ping with simple_ajax_nonce
            $diagOutput.append('<div id="test1">Testing simple AJAX ping: <span class="result">Running...</span></div>');
            $.ajax({
                url: psData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ps_simple_ajax_test',
                    nonce: psData.simple_ajax_nonce,
                    test_payload: 'Diagnostic test from debug.js'
                },
                success: function(response) {
                    $('#test1 .result').html('<span style="color: #00ff00;">Success ✓</span>');
                    $diagOutput.append('<div style="font-size: 12px; color: #aaffaa; margin-left: 20px;">Response: ' + JSON.stringify(response) + '</div>');
                },
                error: function(jqXHR) {
                    $('#test1 .result').html('<span style="color: #ff5555;">Failed ✗</span>');
                    $diagOutput.append('<div style="font-size: 12px; color: #ffaaaa; margin-left: 20px;">Error: ' + jqXHR.status + ' - ' + jqXHR.responseText + '</div>');
                }
            });
            
            // Test 2: Filter request
            $diagOutput.append('<div id="test2">Testing filter request: <span class="result">Running...</span></div>');
            $.ajax({
                url: psData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ps_filter',
                    nonce: psData.filter_nonce,
                    last_search_query: localStorage.getItem('ps_last_search_query') || 'test',
                    country_code: localStorage.getItem('ps_last_search_country') || 'us',
                    exclude_keywords: '',
                    sort_by: 'price'
                },
                success: function(response) {
                    $('#test2 .result').html('<span style="color: #00ff00;">Success ✓</span>');
                    $diagOutput.append('<div style="font-size: 12px; color: #aaffaa; margin-left: 20px;">Response contains success: ' + (response.success ? 'Yes' : 'No') + '</div>');
                },
                error: function(jqXHR) {
                    $('#test2 .result').html('<span style="color: #ff5555;">Failed ✗</span>');
                    $diagOutput.append('<div style="font-size: 12px; color: #ffaaaa; margin-left: 20px;">Error: ' + jqXHR.status + ' - ' + jqXHR.responseText + '</div>');
                }
            });
            
            // Test 3: Check base cache
            $diagOutput.append('<div id="test3">Testing ps_check_base_cache: <span class="result">Running...</span></div>');
            $.ajax({
                url: psData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ps_check_base_cache',
                    nonce: psData.check_cache_nonce || psData.filter_nonce, // Try with check_cache_nonce, fall back to filter_nonce
                    query: localStorage.getItem('ps_last_search_query') || 'test',
                    country_code: localStorage.getItem('ps_last_search_country') || 'us'
                },
                success: function(response) {
                    $('#test3 .result').html('<span style="color: #00ff00;">Success ✓</span>');
                    $diagOutput.append('<div style="font-size: 12px; color: #aaffaa; margin-left: 20px;">Response: ' + JSON.stringify(response) + '</div>');
                },
                error: function(jqXHR) {
                    $('#test3 .result').html('<span style="color: #ff5555;">Failed ✗</span>');
                    $diagOutput.append('<div style="font-size: 12px; color: #ffaaaa; margin-left: 20px;">Error: ' + jqXHR.status + ' - ' + jqXHR.responseText + '</div>');
                }
            });
            
            // Add browser info
            $diagOutput.append('<h4>Browser Information:</h4>');
            $diagOutput.append('<div>User Agent: ' + navigator.userAgent + '</div>');
            $diagOutput.append('<div>localStorage available: ' + (window.localStorage ? 'Yes' : 'No') + '</div>');
            $diagOutput.append('<div>jQuery version: ' + $.fn.jquery + '</div>');
            
            // Add close functionality
            $closeBtn.on('click', function() {
                $diagOutput.remove();
            });
            
            // Add to document
            $('body').append($diagOutput);
        }
    });
})(jQuery); 