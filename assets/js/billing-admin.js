/**
 * Billing Settings Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Tab switching
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            
            const target = $(this).attr('href');
            
            // Update tabs
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            // Update content
            $('.tab-content').removeClass('active');
            $(target).addClass('active');
        });

        // Test Stripe connection
        $('#ftt-test-stripe-btn').on('click', function() {
            const $btn = $(this);
            const $spinner = $btn.next('.spinner');
            const $result = $('#ftt-connection-result');
            
            $btn.prop('disabled', true);
            $spinner.addClass('is-active');
            $result.empty();
            
            $.ajax({
                url: fttBilling.ajax_url,
                type: 'POST',
                data: {
                    action: 'ftt_test_stripe_connection',
                    nonce: fttBilling.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html(
                            '<div class="ftt-connection-success">' +
                            '<strong>✓ Connected!</strong><br>' +
                            'Account: ' + response.data.account_name + '<br>' +
                            'ID: <code>' + response.data.account_id + '</code>' +
                            '</div>'
                        );
                    } else {
                        $result.html(
                            '<div class="ftt-connection-error">' +
                            '<strong>✗ Connection Failed</strong><br>' +
                            response.data.message +
                            '</div>'
                        );
                    }
                },
                error: function() {
                    $result.html(
                        '<div class="ftt-connection-error">' +
                        '<strong>✗ Request Failed</strong><br>' +
                        'Unable to contact server' +
                        '</div>'
                    );
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        });

        // Auto-fetch Stripe prices
        $('#ftt-fetch-prices-btn').on('click', function() {
            const $btn = $(this);
            const $result = $('#ftt-prices-result');
            
            $btn.prop('disabled', true);
            $result.html('<div class="notice notice-info inline"><p>Fetching prices from Stripe...</p></div>');
            
            $.ajax({
                url: fttBilling.ajax_url,
                type: 'POST',
                data: {
                    action: 'ftt_fetch_stripe_prices',
                    nonce: fttBilling.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const prices = response.data.prices;
                        let foundHtml = '<div class="ftt-prices-found"><strong>✓ Found prices:</strong><ul>';
                        
                        if (prices.price_base_monthly) {
                            $('#ftt_price_base_monthly').val(prices.price_base_monthly);
                            foundHtml += '<li>Base Monthly: <code>' + prices.price_base_monthly + '</code></li>';
                        }
                        if (prices.price_base_yearly) {
                            $('#ftt_price_base_yearly').val(prices.price_base_yearly);
                            foundHtml += '<li>Base Yearly: <code>' + prices.price_base_yearly + '</code></li>';
                        }
                        if (prices.price_addon_monthly) {
                            $('#ftt_price_addon_monthly').val(prices.price_addon_monthly);
                            foundHtml += '<li>Add-on Monthly: <code>' + prices.price_addon_monthly + '</code></li>';
                        }
                        if (prices.price_addon_yearly) {
                            $('#ftt_price_addon_yearly').val(prices.price_addon_yearly);
                            foundHtml += '<li>Add-on Yearly: <code>' + prices.price_addon_yearly + '</code></li>';
                        }
                        
                        foundHtml += '</ul><p><strong>Remember to save your settings!</strong></p></div>';
                        $result.html(foundHtml);
                    } else {
                        $result.html(
                            '<div class="notice notice-error inline"><p>' +
                            response.data.message +
                            '</p></div>'
                        );
                    }
                },
                error: function() {
                    $result.html(
                        '<div class="notice notice-error inline"><p>Request failed</p></div>'
                    );
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        });

        // Show/hide password fields
        $('.form-table input[type="password"]').each(function() {
            const $input = $(this);
            const $wrapper = $('<div class="ftt-password-wrapper" style="display:inline-flex;gap:5px;align-items:center;"></div>');
            
            $input.wrap($wrapper);
            
            const $toggle = $('<button type="button" class="button button-small">Show</button>');
            $input.after($toggle);
            
            $toggle.on('click', function() {
                if ($input.attr('type') === 'password') {
                    $input.attr('type', 'text');
                    $toggle.text('Hide');
                } else {
                    $input.attr('type', 'password');
                    $toggle.text('Show');
                }
            });
        });

        // Mode change warning
        $('#ftt_mode').on('change', function() {
            const mode = $(this).val();
            
            if (mode === 'live') {
                if (!confirm('⚠️ You are switching to LIVE MODE. Real payments will be processed!\n\nAre you sure you want to continue?')) {
                    $(this).val('test');
                }
            }
        });
    });

})(jQuery);
