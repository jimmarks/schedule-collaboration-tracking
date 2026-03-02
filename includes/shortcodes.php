<?php
/**
 * Shortcodes
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FTT_Shortcodes {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        add_shortcode('ftt_calendar', array(__CLASS__, 'render_calendar'));
        add_shortcode('ftt_event_form', array(__CLASS__, 'render_event_form'));
        add_shortcode('ftt_dashboard', array(__CLASS__, 'render_dashboard'));
        add_shortcode('ftt_event_list', array(__CLASS__, 'render_event_list'));
        add_shortcode('ftt_calendar_subscribe', array(__CLASS__, 'render_calendar_subscribe'));
        add_shortcode('ftt_login', array(__CLASS__, 'render_login'));
        add_shortcode('ftt_homepage', array(__CLASS__, 'render_homepage'));
        add_shortcode('ftt_family_management', array(__CLASS__, 'render_family_management'));
        
        // Billing shortcodes
        add_shortcode('ftt_pricing_page', array(__CLASS__, 'render_pricing'));
        add_shortcode('ftt_manage_subscription', array(__CLASS__, 'render_manage_subscription'));
        add_shortcode('ftt_checkout_success', array(__CLASS__, 'render_checkout_success'));
        add_shortcode('ftt_checkout_cancel', array(__CLASS__, 'render_checkout_cancel'));
        
        // Add login redirect filters
        add_filter('login_redirect', array(__CLASS__, 'custom_login_redirect'), 10, 3);
        add_filter('authenticate', array(__CLASS__, 'custom_authenticate_redirect'), 30, 3);
        add_action('wp_login_failed', array(__CLASS__, 'login_failed_redirect'));
    }
    
    /**
     * Render calendar shortcode
     */
    public static function render_calendar($atts) {
        // Check permissions
        $settings = get_option('ftt_settings', array());
        $require_login = $settings['require_login'] ?? false;
        
        if ($require_login && !is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to view the calendar.', 'schedule-collaboration-tracking') . '</p>';
        }
        
        ob_start();
        include FTT_PLUGIN_DIR . 'templates/calendar.php';
        return ob_get_clean();
    }
    
    /**
     * Render event form shortcode
     */
    public static function render_event_form($atts) {
        // Check permissions - only editors and admins
        if (!current_user_can('edit_posts')) {
            return '<p>' . esc_html__('You do not have permission to add or edit events.', 'schedule-collaboration-tracking') . '</p>';
        }
        
        ob_start();
        include FTT_PLUGIN_DIR . 'templates/event-form.php';
        return ob_get_clean();
    }
    
    /**
     * Render dashboard shortcode
     */
    public static function render_dashboard($atts) {
        // Check permissions
        $settings = get_option('ftt_settings', array());
        $require_login = $settings['require_login'] ?? false;
        
        if ($require_login && !is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to view the dashboard.', 'schedule-collaboration-tracking') . '</p>';
        }
        
        ob_start();
        include FTT_PLUGIN_DIR . 'templates/dashboard.php';
        return ob_get_clean();
    }
    
    /**
     * Render homepage shortcode
     */
    public static function render_homepage($atts) {
        ob_start();
        include FTT_PLUGIN_DIR . 'templates/homepage.php';
        return ob_get_clean();
    }
    
    /**
     * Render event list shortcode
     */
    public static function render_event_list($atts) {
        // Check permissions
        $settings = get_option('ftt_settings', array());
        $require_login = $settings['require_login'] ?? false;
        
        if ($require_login && !is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to view events.', 'schedule-collaboration-tracking') . '</p>';
        }
        
        // Parse attributes
        $atts = shortcode_atts(array(
            'limit' => 10,
            'type'  => '',
        ), $atts);
        
        // Query upcoming events
        $args = array(
            'post_type'      => 'ftt_event',
            'posts_per_page' => intval($atts['limit']),
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => 'start_datetime',
                    'value'   => current_time('mysql'),
                    'compare' => '>=',
                    'type'    => 'DATETIME',
                ),
            ),
            'orderby'        => 'meta_value',
            'meta_key'       => 'start_datetime',
            'order'          => 'ASC',
        );
        
        // Filter by type if specified
        if (!empty($atts['type'])) {
            $args['meta_query'][] = array(
                'key'     => 'event_type',
                'value'   => sanitize_text_field($atts['type']),
                'compare' => '=',
            );
        }
        
        // Filter by member - show only events for logged-in user unless admin
        if (is_user_logged_in()) {
            $current_user_id = get_current_user_id();
            $is_admin = current_user_can('manage_options');
            
            // Non-admins only see their own events
            if (!$is_admin) {
                // Check if user is a parent
                $child_ids = get_user_meta($current_user_id, 'ftt_children', true);
                
                if (!empty($child_ids) && is_array($child_ids)) {
                    // Parent - show their children's events
                    $member_ids = $child_ids;
                    $member_ids[] = $current_user_id; // Include parent's own events
                    
                    $args['meta_query'][] = array(
                        'key'     => 'member_id',
                        'value'   => $member_ids,
                        'compare' => 'IN',
                    );
                } else {
                    // Member - show only their events
                    $args['meta_query'][] = array(
                        'key'     => 'member_id',
                        'value'   => $current_user_id,
                        'compare' => '=',
                    );
                }
            }
            // Admins see all events (no filter applied)
        }
        
        $query = new WP_Query($args);
        
        ob_start();
        include FTT_PLUGIN_DIR . 'templates/event-list.php';
        wp_reset_postdata();
        return ob_get_clean();
    }
    
    /**
     * Render calendar subscribe shortcode
     */
    public static function render_calendar_subscribe($atts) {
        $atts = shortcode_atts(array(
            'token' => '', // Optional pre-filled token
        ), $atts);
        
        $settings = get_option('ftt_settings', array());
        $enabled = $settings['enable_ical_feed'] ?? false;
        
        if (!$enabled) {
            return '<p>' . esc_html__('Calendar subscription is currently disabled.', 'schedule-collaboration-tracking') . '</p>';
        }
        
        $requires_auth = $settings['ical_require_auth'] ?? false;
        $feed_url = rest_url('ftt/v1/calendar.ics');
        
        if ($requires_auth && !empty($atts['token'])) {
            $feed_url .= '?token=' . urlencode($atts['token']);
        }
        
        ob_start();
        include FTT_PLUGIN_DIR . 'templates/calendar-subscribe.php';
        return ob_get_clean();
    }
    
    /**
     * Render login form shortcode
     */
    public static function render_login($atts) {
        ob_start();
        include FTT_PLUGIN_DIR . 'templates/login-form.php';
        return ob_get_clean();
    }
    
    /**
     * Custom login redirect - send users to dashboard instead of wp-admin
     */
    public static function custom_login_redirect($redirect_to, $request, $user) {
        // Check if this is a post-registration redirect
        if (isset($user->ID)) {
            // Check if access has been denied by admin
            $access_denied = get_user_meta($user->ID, 'ftt_access_denied', true);
            if ($access_denied) {
                // Invalidate calendar access
                if (class_exists('FTT_Billing_Manager')) {
                    FTT_Billing_Manager::invalidate_calendar_access($user->ID);
                }
                return add_query_arg('reason', 'admin_denied', home_url('/pricing/'));
            }
            
            $stored_redirect = get_transient('ftt_post_registration_redirect_' . $user->ID);
            if ($stored_redirect) {
                error_log('FTT DEBUG: custom_login_redirect found stored redirect: ' . $stored_redirect);
                delete_transient('ftt_post_registration_redirect_' . $user->ID);
                return $stored_redirect;
            }
        }
        
        // Check if user has a valid role
        if (isset($user->roles) && is_array($user->roles)) {
            // If user is an admin or editor, let them go to wp-admin if they want
            if (in_array('administrator', $user->roles) || in_array('editor', $user->roles)) {
                // But if they came from our login page, send them to dashboard
                if (strpos($redirect_to, 'sc-dashboard') !== false) {
                    return home_url('/ftt-dashboard/');
                }
                return $redirect_to;
            }
            
            // For all other users (ftt_member, ftt_parent), always go to dashboard
            return home_url('/ftt-dashboard/');
        }
        
        return $redirect_to;
    }
    
    /**
     * Handle failed login redirects
     */
    public static function custom_authenticate_redirect($user, $username, $password) {
        // Check if this is from wp-login.php and has errors
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false) {
            if (is_wp_error($user)) {
                // Get the redirect_to parameter if set
                $redirect_to = isset($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : '';
                
                // If redirecting to our dashboard or from our login page, send them to our login page with error
                if (strpos($redirect_to, 'ftt-dashboard') !== false || strpos($redirect_to, 'ftt-') !== false) {
                    $login_url = home_url('/ftt-login/');
                    
                    if ($user->get_error_code() === 'invalid_username' || $user->get_error_code() === 'incorrect_password') {
                        $login_url = add_query_arg('login', 'failed', $login_url);
                    } elseif ($user->get_error_code() === 'empty_username' || $user->get_error_code() === 'empty_password') {
                        $login_url = add_query_arg('login', 'empty', $login_url);
                    } else {
                        $login_url = add_query_arg('login', 'failed', $login_url);
                    }
                    
                    // Store redirect_to for after successful login
                    if (!empty($redirect_to)) {
                        wp_cache_set('login_redirect_' . $username, $redirect_to, '', 300);
                    }
                    
                    wp_redirect($login_url);
                    exit;
                }
            }
        }
        
        return $user;
    }
    
    /**
     * Redirect failed logins to custom login page
     */
    public static function login_failed_redirect($username) {
        // Get the referrer to check if it came from our custom login page
        $referrer = wp_get_referer();
        
        // Only redirect if coming from custom login or going to FTT pages
        if ($referrer && (strpos($referrer, 'ftt-login') !== false || strpos($referrer, 'ftt-') !== false)) {
            $login_url = home_url('/ftt-login/');
            $login_url = add_query_arg('login', 'failed', $login_url);
            
            // Preserve redirect_to if it exists
            if (isset($_REQUEST['redirect_to'])) {
                $login_url = add_query_arg('redirect_to', urlencode($_REQUEST['redirect_to']), $login_url);
            }
            
            wp_redirect($login_url);
            exit;
        }
    }
    
    /**
     * Render pricing page shortcode
     */
    public static function render_pricing($atts) {
        if (!file_exists(FTT_PLUGIN_DIR . 'templates/billing/pricing.php')) {
            return '<p>Billing template not found.</p>';
        }
        
        // Enqueue jQuery
        wp_enqueue_script('jquery');
        
        // Add pricing page JavaScript inline to avoid WordPress content filters mangling it
        $pricing_js = "
        console.log('FTT PRICING: Script loaded');
        jQuery(document).ready(function($) {
            console.log('FTT PRICING: jQuery ready');
            
            // Toggle between monthly and yearly
            function updatePricingDisplay() {
                const interval = $('input[name=\"billing_interval\"]:checked').val();
                console.log('FTT PRICING: Updating display for interval:', interval);
                $('.ftt-pricing-card').hide();
                $(`.ftt-pricing-card[data-interval=\"\${interval}\"]`).show();
            }
            
            // Initialize display on page load
            updatePricingDisplay();
            
            // Handle radio button changes
            $('input[name=\"billing_interval\"]').on('change', function() {
                console.log('FTT PRICING: Billing interval changed');
                updatePricingDisplay();
            });
            
            // Quantity control buttons
            $('.ftt-qty-btn').on('click', function() {
                const \$btn = $(this);
                const targetId = \$btn.data('target');
                const \$input = $('#' + targetId);
                const \$total = \$input.closest('.ftt-addon-selector').find('.ftt-addon-total');
                let current = parseInt(\$input.val()) || 0;
                const max = parseInt(\$input.attr('max'));
                const min = parseInt(\$input.attr('min'));
                
                if (\$btn.hasClass('ftt-qty-plus') && current < max) {
                    current++;
                } else if (\$btn.hasClass('ftt-qty-minus') && current > min) {
                    current--;
                }
                
                \$input.val(current);
                console.log('FTT PRICING: Addon quantity changed to:', current);
                
                // Update total display
                const interval = targetId.includes('month') ? 'month' : 'year';
                const pricePerChild = interval === 'month' ? 5 : 50;
                const totalAddon = current * pricePerChild;
                \$total.text('+$' + totalAddon + '/' + interval);
                
                // Update button states
                \$input.closest('.ftt-quantity-control').find('.ftt-qty-minus').prop('disabled', current <= min);
                \$input.closest('.ftt-quantity-control').find('.ftt-qty-plus').prop('disabled', current >= max);
            });
            
            // Initialize button states
            $('.ftt-addon-qty').each(function() {
                const \$input = $(this);
                const current = parseInt(\$input.val()) || 0;
                const min = parseInt(\$input.attr('min'));
                \$input.closest('.ftt-quantity-control').find('.ftt-qty-minus').prop('disabled', current <= min);
            });
            
            console.log('FTT PRICING: Attaching click handler to checkout buttons');
            
            // Handle checkout button
            $('.ftt-cta-button[data-interval]').on('click', function(e) {
                e.preventDefault();
                const interval = $(this).data('interval');
                const \$button = $(this);
                
                // Get addon quantity for the current interval
                const addonQty = parseInt($('#addon-qty-' + interval).val()) || 0;
                
                console.log('FTT PRICING: Checkout button clicked - interval:', interval, 'addon_qty:', addonQty);
                
                \$button.prop('disabled', true).text('" . esc_js(__('Creating checkout...', 'schedule-collaboration-tracking')) . "');
                
                console.log('FTT PRICING: Making AJAX call to:', '" . esc_url(rest_url('ftt/v1/create-checkout')) . "');
                
                // Call REST API to create checkout session
                $.ajax({
                    url: '" . esc_url(rest_url('ftt/v1/create-checkout')) . "',
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': '" . wp_create_nonce('wp_rest') . "'
                    },
                    data: JSON.stringify({
                        interval: interval,
                        addon_quantity: addonQty
                    }),
                    contentType: 'application/json',
                    success: function(response) {
                        console.log('FTT PRICING: AJAX success, response:', response);
                        if (response.url) {
                            console.log('FTT PRICING: Redirecting to Stripe checkout:', response.url);
                            window.location.href = response.url;
                        } else {
                            console.error('FTT PRICING: No URL in response');
                            alert('" . esc_js(__('Error creating checkout session', 'schedule-collaboration-tracking')) . "');
                            \$button.prop('disabled', false).text('" . esc_js(__('Start Free Trial', 'schedule-collaboration-tracking')) . "');
                        }
                    },
                    error: function(xhr) {
                        console.error('FTT PRICING: AJAX error:', xhr);
                        let errorMsg = '" . esc_js(__('Error creating checkout session', 'schedule-collaboration-tracking')) . "';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg += ': ' + xhr.responseJSON.message;
                            console.error('FTT PRICING: Error message:', xhr.responseJSON.message);
                        }
                        alert(errorMsg);
                        \$button.prop('disabled', false).text('" . esc_js(__('Start Free Trial', 'schedule-collaboration-tracking')) . "');
                    }
                });
            });
            
            console.log('FTT PRICING: Script initialization complete');
        });
        ";
        
        wp_add_inline_script('jquery', $pricing_js);
        
        ob_start();
        include FTT_PLUGIN_DIR . 'templates/billing/pricing.php';
        return ob_get_clean();
    }
    
    /**
     * Render manage subscription shortcode
     */
    public static function render_manage_subscription($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to manage your subscription.', 'schedule-collaboration-tracking') . '</p>';
        }
        
        if (!file_exists(FTT_PLUGIN_DIR . 'templates/billing/manage-subscription.php')) {
            return '<p>Billing template not found.</p>';
        }
        
        // Enqueue jQuery
        wp_enqueue_script('jquery');
        
        // Add manage subscription JavaScript inline to avoid WordPress content filters
        $manage_js = "
        jQuery(document).ready(function($) {
            // Add child addon
            $('#ftt-add-child-addon').on('click', function() {
                if (!confirm('" . esc_js(__('Add another child slot? You will be charged a prorated amount today.', 'schedule-collaboration-tracking')) . "')) {
                    return;
                }
                
                const \$button = $(this);
                \$button.prop('disabled', true).text('" . esc_js(__('Processing...', 'schedule-collaboration-tracking')) . "');
                
                $.ajax({
                    url: '" . esc_url(rest_url('ftt/v1/add-child-addon')) . "',
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': '" . wp_create_nonce('wp_rest') . "'
                    },
                    success: function() {
                        location.reload();
                    },
                    error: function() {
                        alert('" . esc_js(__('Error adding child addon', 'schedule-collaboration-tracking')) . "');
                        \$button.prop('disabled', false).text('" . esc_js(__('Add Child Slot', 'schedule-collaboration-tracking')) . "');
                    }
                });
            });
            
            // Billing portal
            $('#ftt-billing-portal').on('click', function() {
                const \$button = $(this);
                \$button.prop('disabled', true).text('" . esc_js(__('Loading...', 'schedule-collaboration-tracking')) . "');
                
                window.location.href = '" . esc_url(rest_url('ftt/v1/billing-portal')) . "?_wpnonce=" . wp_create_nonce('wp_rest') . "';
            });
            
            // Cancel subscription
            $('#ftt-cancel-subscription').on('click', function() {
                if (!confirm('" . esc_js(__('Are you sure you want to cancel? You will keep access until the end of your billing period.', 'schedule-collaboration-tracking')) . "')) {
                    return;
                }
                
                const \$button = $(this);
                \$button.prop('disabled', true).text('" . esc_js(__('Canceling...', 'schedule-collaboration-tracking')) . "');
                
                $.ajax({
                    url: '" . esc_url(rest_url('ftt/v1/cancel-subscription')) . "',
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': '" . wp_create_nonce('wp_rest') . "'
                    },
                    success: function() {
                        location.reload();
                    },
                    error: function() {
                        alert('" . esc_js(__('Error canceling subscription', 'schedule-collaboration-tracking')) . "');
                        \$button.prop('disabled', false).text('" . esc_js(__('Cancel Subscription', 'schedule-collaboration-tracking')) . "');
                    }
                });
            });
            
            // Reactivate subscription
            $('#ftt-reactivate-subscription').on('click', function() {
                const \$button = $(this);
                \$button.prop('disabled', true).text('" . esc_js(__('Reactivating...', 'schedule-collaboration-tracking')) . "');
                
                $.ajax({
                    url: '" . esc_url(rest_url('ftt/v1/reactivate-subscription')) . "',
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': '" . wp_create_nonce('wp_rest') . "'
                    },
                    success: function() {
                        location.reload();
                    },
                    error: function() {
                        alert('" . esc_js(__('Error reactivating subscription', 'schedule-collaboration-tracking')) . "');
                        \$button.prop('disabled', false).text('" . esc_js(__('Reactivate Subscription', 'schedule-collaboration-tracking')) . "');
                    }
                });
            });
        });
        ";
        
        wp_add_inline_script('jquery', $manage_js);
        
        ob_start();
        include FTT_PLUGIN_DIR . 'templates/billing/manage-subscription.php';
        return ob_get_clean();
    }
    
    /**
     * Render checkout success shortcode
     */
    public static function render_checkout_success($atts) {
        if (!file_exists(FTT_PLUGIN_DIR . 'templates/billing/checkout-success.php')) {
            return '<p>Success template not found.</p>';
        }
        
        ob_start();
        include FTT_PLUGIN_DIR . 'templates/billing/checkout-success.php';
        return ob_get_clean();
    }
    
    /**
     * Render checkout cancel shortcode
     */
    public static function render_checkout_cancel($atts) {
        if (!file_exists(FTT_PLUGIN_DIR . 'templates/billing/checkout-cancel.php')) {
            return '<p>Cancel template not found.</p>';
        }
        
        ob_start();
        include FTT_PLUGIN_DIR . 'templates/billing/checkout-cancel.php';
        return ob_get_clean();
    }
    
    /**
     * Render family management page
     * 
     * @return string
     */
    public static function render_family_management() {
        error_log('FTT: Rendering family management page');
        
        if (!is_user_logged_in()) {
            return self::render_login_form();
        }
        
        // Enqueue dashicons for front-end
        wp_enqueue_style('dashicons');
        
        // Enqueue family management JavaScript with jQuery dependency
        wp_enqueue_script(
            'ftt-family-management',
            FTT_PLUGIN_URL . 'assets/js/family-management.js',
            array('jquery'),
            FTT_VERSION,
            true // Load in footer
        );
        
        // Pass REST API nonce to JavaScript
        wp_localize_script('ftt-family-management', 'fttFamilyMgmt', array(
            'nonce' => wp_create_nonce('wp_rest')
        ));
        
        ob_start();
        include FTT_PLUGIN_DIR . 'templates/family-management.php';
        return ob_get_clean();
    }
}

// Initialize
FTT_Shortcodes::init();
