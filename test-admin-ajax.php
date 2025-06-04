<?php
/**
 * Simple admin page to test AJAX handlers
 */

// Add admin menu for testing
add_action( 'admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        'Test AJAX',
        'Test AJAX',
        'manage_woocommerce',
        'test-ajax-handlers',
        'render_test_ajax_page'
    );
});

function render_test_ajax_page() {
    ?>
    <div class="wrap">
        <h1>Test AJAX Handlers</h1>
        <button id="test-simple" class="button">Test Simple AJAX</button>
        <button id="test-get-addons" class="button">Test Get Addons</button>
        <button id="test-get-addon-options" class="button">Test Get Addon Options</button>
        <div id="output" style="margin-top: 20px; padding: 10px; border: 1px solid #ccc; background: #f9f9f9;"></div>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#test-simple').click(function() {
            $('#output').html('Testing simple AJAX...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'test_simple_ajax'
                },
                success: function(response) {
                    $('#output').html('<strong>Success:</strong><br><pre>' + JSON.stringify(response, null, 2) + '</pre>');
                },
                error: function(xhr, status, error) {
                    $('#output').html('<strong>Error:</strong> ' + xhr.status + ' - ' + xhr.statusText + '<br><strong>Response:</strong><br><pre>' + xhr.responseText + '</pre>');
                }
            });
        });
        
        $('#test-get-addons').click(function() {
            $('#output').html('Testing get addons AJAX...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wc_pao_get_addons',
                    context: 'all'
                },
                success: function(response) {
                    $('#output').html('<strong>Success:</strong><br><pre>' + JSON.stringify(response, null, 2) + '</pre>');
                },
                error: function(xhr, status, error) {
                    $('#output').html('<strong>Error:</strong> ' + xhr.status + ' - ' + xhr.statusText + '<br><strong>Response:</strong><br><pre>' + xhr.responseText + '</pre>');
                }
            });
        });
        
        $('#test-get-addon-options').click(function() {
            $('#output').html('Testing get addon options AJAX...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wc_pao_get_addon_options',
                    addon_id: 'test_addon_1'
                },
                success: function(response) {
                    $('#output').html('<strong>Success:</strong><br><pre>' + JSON.stringify(response, null, 2) + '</pre>');
                },
                error: function(xhr, status, error) {
                    $('#output').html('<strong>Error:</strong> ' + xhr.status + ' - ' + xhr.statusText + '<br><strong>Response:</strong><br><pre>' + xhr.responseText + '</pre>');
                }
            });
        });
    });
    </script>
    <?php
}

// Include this test file in the main plugin
require_once __FILE__;
?>