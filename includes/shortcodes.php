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
        add_shortcode('ftt_event_view', array(__CLASS__, 'render_event_view'));
        add_shortcode('ftt_dashboard', array(__CLASS__, 'render_dashboard'));
        add_shortcode('ftt_event_list', array(__CLASS__, 'render_event_list'));
        add_shortcode('ftt_calendar_subscribe', array(__CLASS__, 'render_calendar_subscribe'));
        add_shortcode('ftt_login', array(__CLASS__, 'render_login'));
        add_shortcode('ftt_homepage', array(__CLASS__, 'render_homepage'));
        add_shortcode('ftt_family_management', array(__CLASS__, 'render_family_management'));
        add_shortcode('ftt_groups', array(__CLASS__, 'render_groups'));
        
        // Billing shortcodes
        add_shortcode('ftt_pricing_page', array(__CLASS__, 'render_pricing'));
        add_shortcode('ftt_manage_subscription', array(__CLASS__, 'render_manage_subscription'));
        add_shortcode('ftt_checkout_success', array(__CLASS__, 'render_checkout_success'));
        add_shortcode('ftt_checkout_cancel', array(__CLASS__, 'render_checkout_cancel'));

        // Onboarding & trial-expired shortcodes
        add_shortcode('ftt_onboarding',    array(__CLASS__, 'render_onboarding'));
        add_shortcode('ftt_trial_expired', array(__CLASS__, 'render_trial_expired'));

        // Policy URL shortcodes — return the URL of the assigned policy page
        add_shortcode('ftt_privacy_url', array(__CLASS__, 'render_privacy_url'));
        add_shortcode('ftt_terms_url',   array(__CLASS__, 'render_terms_url'));
        add_shortcode('ftt_cookie_url',  array(__CLASS__, 'render_cookie_url'));
        add_shortcode('ftt_sms_url',     array(__CLASS__, 'render_sms_url'));

        // Utility shortcodes
        add_shortcode('ftt_current_year',   array(__CLASS__, 'render_current_year'));
        add_shortcode('ftt_site_title',     array(__CLASS__, 'render_site_title'));
        add_shortcode('ftt_copyright',      array(__CLASS__, 'render_copyright'));
        add_shortcode('ftt_cookie_policy',   array(__CLASS__, 'render_cookie_policy'));
        add_shortcode('ftt_manage_cookies',  array(__CLASS__, 'render_manage_cookies_button'));

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
        // Require an active FTT account. We accept:
        //   - site admins (can always manage anything)
        //   - parents (registered via FTT registration, have user_type=parent or linked children)
        //   - members/children (have ftt_is_member meta, set by make_member())
        // We do NOT use current_user_can('edit_posts') because parent accounts are not
        // granted that WordPress capability — only make_member() (child/member) accounts
        // receive it. We also cannot use is_user_logged_in() alone because that would
        // allow any random WordPress account to create events.
        $uid = get_current_user_id();
        $is_ftt_user = $uid && (
            current_user_can('manage_options') ||
            FTT_Roles::is_parent($uid) ||
            FTT_Roles::is_member($uid)
        );
        if (!$is_ftt_user) {
            if (!is_user_logged_in()) {
                return '<p>' . esc_html__('Please log in to add or edit events.', 'schedule-collaboration-tracking') . '</p>';
            }
            return '<p>' . esc_html__('You do not have permission to add or edit events.', 'schedule-collaboration-tracking') . '</p>';
        }
        
        ob_start();
        include FTT_PLUGIN_DIR . 'templates/event-form.php';
        return ob_get_clean();
    }
    
    /**
     * Render event view shortcode (read-only)
     */
    public static function render_event_view($atts) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to view event details.', 'schedule-collaboration-tracking') . '</p>';
        }
        
        ob_start();
        include FTT_PLUGIN_DIR . 'templates/event-view.php';
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

        // Build the user's personal token-authenticated feed URL when logged in
        $https_url = '';
        $webcal_url = '';
        $google_url = '';

        if ( is_user_logged_in() ) {
            $current_uid = get_current_user_id();
            $token = get_user_meta( $current_uid, 'ftt_calendar_token', true );
            if ( empty($token) ) {
                $token = wp_generate_password( 32, false );
                update_user_meta( $current_uid, 'ftt_calendar_token', $token );
            }
            $https_url = add_query_arg(
                array( 'ftt_calendar' => '1', 'token' => $token, 'user_id' => $current_uid ),
                home_url('/')
            );
            $webcal_url = preg_replace( '/^https?:\/\//', 'webcal://', $https_url );
            $google_url = 'https://calendar.google.com/calendar/r?cid=' . rawurlencode( $webcal_url );
        } else {
            // Fall back to the public REST feed for non-logged-in views
            $https_url = rest_url('ftt/v1/calendar.ics');
            $requires_auth = $settings['ical_require_auth'] ?? false;
            if ( $requires_auth && !empty($atts['token']) ) {
                $https_url .= '?token=' . urlencode($atts['token']);
            }
            $webcal_url = preg_replace( '/^https?:\/\//', 'webcal://', $https_url );
            $google_url = 'https://calendar.google.com/calendar/r?cid=' . rawurlencode( $webcal_url );
        }

        // Mobile detection (server-side hint; JS refines on front-end)
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $is_mobile = (bool) preg_match( '/iPhone|iPad|iPod|Android|Mobile|BlackBerry|IEMobile/i', $ua );

        // QR code image URL for desktop visitors
        $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query( array(
            'size' => '200x200',
            'data' => $webcal_url,
        ) );
        
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
                // Log them out and redirect to login with message
                wp_logout();
                return add_query_arg('access_denied', '1', home_url('/ftt-login/'));
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
        // Only handle errors
        if (!is_wp_error($user)) {
            return $user;
        }
        
        // Check if this login attempt came from our custom login page
        $from_custom_login = isset($_POST['ftt_custom_login']) || isset($_REQUEST['ftt_custom_login']);
        
        // Also check referrer and redirect_to as fallback
        if (!$from_custom_login) {
            $referrer = wp_get_referer();
            $redirect_to = isset($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : '';
            
            if (($referrer && strpos($referrer, 'ftt-login') !== false) ||
                ($redirect_to && (strpos($redirect_to, 'ftt-dashboard') !== false || strpos($redirect_to, 'ftt-') !== false))) {
                $from_custom_login = true;
            }
        }
        
        // If from custom login, redirect back to our login page with error
        if ($from_custom_login) {
            $login_url = home_url('/ftt-login/');
            
            if ($user->get_error_code() === 'invalid_username' || $user->get_error_code() === 'incorrect_password') {
                $login_url = add_query_arg('login', 'failed', $login_url);
            } elseif ($user->get_error_code() === 'empty_username' || $user->get_error_code() === 'empty_password') {
                $login_url = add_query_arg('login', 'empty', $login_url);
            } else {
                $login_url = add_query_arg('login', 'failed', $login_url);
            }
            
            // Preserve redirect_to
            if (!empty($_REQUEST['redirect_to'])) {
                $login_url = add_query_arg('redirect_to', urlencode($_REQUEST['redirect_to']), $login_url);
            }
            
            wp_safe_redirect($login_url);
            exit;
        }
        
        return $user;
    }
    
    /**
     * Redirect failed logins to custom login page
     */
    public static function login_failed_redirect($username) {
        // Check if this login attempt came from our custom login page
        $from_custom_login = isset($_POST['ftt_custom_login']) || isset($_REQUEST['ftt_custom_login']);
        
        // Also check referrer as fallback
        $referrer = wp_get_referer();
        if (!$from_custom_login && $referrer) {
            $from_custom_login = (strpos($referrer, 'ftt-login') !== false || strpos($referrer, 'ftt-') !== false);
        }
        
        // Only redirect if coming from custom login
        if ($from_custom_login) {
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
        // These redirects MUST be before ob_start() — calling exit inside a buffer produces a blank page.
        if (!is_user_logged_in()) {
            wp_safe_redirect(add_query_arg('redirect_to', urlencode(home_url('/manage-subscription/')), home_url('/ftt-login/')));
            exit;
        }

        if (!file_exists(FTT_PLUGIN_DIR . 'templates/billing/manage-subscription.php')) {
            return '<p>Billing template not found.</p>';
        }

        $user_id          = get_current_user_id();
        $primary_group_id = get_user_meta($user_id, 'ftt_primary_group', true);

        $billing = ($primary_group_id && class_exists('FTT_Billing_Manager'))
            ? FTT_Billing_Manager::get_group_billing_summary((int) $primary_group_id)
            : null;

        if (!$billing || empty($billing['status'])) {
            wp_safe_redirect(home_url('/pricing/'));
            exit;
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
     * Render onboarding wizard (post-registration: calendar setup + billing offer)
     */
    public static function render_onboarding() {
        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/ftt-login/'));
            exit;
        }
        ob_start();
        include FTT_PLUGIN_DIR . 'templates/onboarding.php';
        return ob_get_clean();
    }

    /**
     * Render trial-expired page (card-free trial has ended, prompt for billing)
     */
    public static function render_trial_expired() {
        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/ftt-login/'));
            exit;
        }
        ob_start();
        include FTT_PLUGIN_DIR . 'templates/trial-expired.php';
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
        
        // Note: All AJAX handlers are in the inline script within templates/family-management.php.
        // They use fttData.nonce which is provided by the globally-enqueued ftt-main script.
        
        ob_start();
        include FTT_PLUGIN_DIR . 'templates/family-management.php';
        return ob_get_clean();
    }
    
    /**
     * Render groups management page
     * 
     * @return string
     */
    public static function render_groups() {
        if (!is_user_logged_in()) {
            return self::render_login_form();
        }
        
        ob_start();
        include FTT_PLUGIN_DIR . 'templates/groups.php';
        return ob_get_clean();
    }
    /**
     * Policy URL shortcodes — delegate to FTT_Email_Templates for URL lookup.
     */
    public static function render_privacy_url( $atts = [] ) {
        return class_exists('FTT_Email_Templates') ? esc_url( FTT_Email_Templates::get_policy_url('privacy') ) : '';
    }
    public static function render_terms_url( $atts = [] ) {
        return class_exists('FTT_Email_Templates') ? esc_url( FTT_Email_Templates::get_policy_url('terms') ) : '';
    }
    public static function render_cookie_url( $atts = [] ) {
        return class_exists('FTT_Email_Templates') ? esc_url( FTT_Email_Templates::get_policy_url('cookie') ) : '';
    }
    public static function render_sms_url( $atts = [] ) {
        return class_exists('FTT_Email_Templates') ? esc_url( FTT_Email_Templates::get_policy_url('sms') ) : '';
    }

    /**
     * Utility shortcodes.
     *
     * [ftt_current_year]  — outputs the 4-digit current year, e.g. 2026
     * [ftt_site_title]    — outputs the site name from WordPress settings
     * [ftt_copyright]     — outputs "© YEAR Site Title" as a convenience
     */
    public static function render_current_year( $atts = [] ) {
        return date( 'Y' );
    }

    public static function render_site_title( $atts = [] ) {
        return esc_html( get_bloginfo( 'name' ) );
    }

    public static function render_copyright( $atts = [] ) {
        $atts = shortcode_atts( [ 'year' => get_bloginfo( 'wpyear' ) ], $atts, 'ftt_copyright' );
        $year = ! empty( $atts['year'] ) ? esc_html( $atts['year'] ) : date( 'Y' );
        return '&copy; ' . $year . ' ' . esc_html( get_bloginfo( 'name' ) );
    }

    /**
     * [ftt_cookie_policy]
     *
     * Full GDPR-compliant cookie policy:
     * - Cookie inventory table with legal basis, duration, third-party links
     * - International data transfers disclosure
     * - How to withdraw consent (includes inline manage-cookies button)
     * - GDPR data-subject rights
     * - Data controller contact
     */
    public static function render_cookie_policy( $atts = [] ) {
        $site        = esc_html( get_bloginfo( 'name' ) );
        $admin_email = get_option( 'admin_email' );

        // ----------------------------------------------------------------
        // Cookie inventory
        // Each row: name, set_by, set_by_url, purpose, duration, type
        // Each group: category (GDPR label), color, legal_basis, rows[]
        // ----------------------------------------------------------------
        $groups = [
            [
                'category'    => '1. Strictly Necessary',
                'color'       => '#2e7d32',
                'legal_basis' => '<strong>Legal basis:</strong> Art.&nbsp;6(1)(b) GDPR – performance of a contract; Art.&nbsp;6(1)(f) – legitimate interests (security). <strong>Consent is not required</strong> for these cookies — they are essential for the site to function and cannot be declined.',
                'rows'        => [
                    [
                        'name'       => 'wordpress_logged_in_*',
                        'set_by'     => 'WordPress',
                        'set_by_url' => 'https://wordpress.org/about/privacy/',
                        'purpose'    => 'Authenticates your login session. Without it the site cannot recognise that you are signed in.',
                        'duration'   => 'Session / 14 days ("Remember Me")',
                        'type'       => 'First-party',
                    ],
                    [
                        'name'       => 'wordpress_sec_*',
                        'set_by'     => 'WordPress',
                        'set_by_url' => 'https://wordpress.org/about/privacy/',
                        'purpose'    => 'Security token for the admin area and login form; prevents cross-site request forgery (CSRF).',
                        'duration'   => 'Session',
                        'type'       => 'First-party',
                    ],
                ],
            ],
            [
                'category'    => '2. Functional',
                'color'       => '#1565c0',
                'legal_basis' => '<strong>Legal basis:</strong> Art.&nbsp;6(1)(a) GDPR – consent. These cookies remember choices you make. You may decline them; doing so will not prevent you from using core site features.',
                'rows'        => [
                    [
                        'name'       => 'ftt_cookie_consent',
                        'set_by'     => $site,
                        'set_by_url' => '',
                        'purpose'    => 'Records whether you have accepted or declined cookies so the consent banner is not shown on every page view.',
                        'duration'   => '365 days',
                        'type'       => 'First-party',
                    ],
                ],
            ],
            [
                'category'    => '3. Analytics &amp; Performance',
                'color'       => '#e65100',
                'legal_basis' => '<strong>Legal basis:</strong> Art.&nbsp;6(1)(a) GDPR – consent. Analytics cookies are placed <strong>only after you click "Accept"</strong>. If you decline, Google Analytics does not collect any data about your visit. Data from accepted sessions is processed by Google LLC on servers in the United States (covered by EU–US Data Privacy Framework adequacy decision).',
                'rows'        => [
                    [
                        'name'       => '_ga, _ga_*',
                        'set_by'     => 'Google Analytics 4',
                        'set_by_url' => 'https://policies.google.com/privacy',
                        'purpose'    => 'Assigns a random pseudonymous ID per browser to count unique visitors and measure page-level usage. No personally identifiable information is stored in this cookie.',
                        'duration'   => '2 years',
                        'type'       => 'Third-party (google.com)',
                    ],
                    [
                        'name'       => '_gid',
                        'set_by'     => 'Google Analytics 4',
                        'set_by_url' => 'https://policies.google.com/privacy',
                        'purpose'    => 'Distinguishes users for session-level analytics. Complements _ga; expires after 24 hours.',
                        'duration'   => '24 hours',
                        'type'       => 'Third-party (google.com)',
                    ],
                ],
            ],
            [
                'category'    => '4. Payments &amp; Fraud Prevention',
                'color'       => '#6A3E8E',
                'legal_basis' => '<strong>Legal basis:</strong> Art.&nbsp;6(1)(b) GDPR – performance of a contract (processing your payment); Art.&nbsp;6(1)(f) – legitimate interests (fraud prevention). Stripe cookies are placed <strong>only on checkout and subscription management pages</strong> and are necessary to complete a transaction securely. Stripe Inc. processes data in the United States under Standard Contractual Clauses.',
                'rows'        => [
                    [
                        'name'       => '__stripe_mid',
                        'set_by'     => 'Stripe, Inc.',
                        'set_by_url' => 'https://stripe.com/privacy',
                        'purpose'    => 'Machine identifier used by Stripe to detect and prevent fraudulent payment attempts across sessions.',
                        'duration'   => '1 year',
                        'type'       => 'Third-party (stripe.com)',
                    ],
                    [
                        'name'       => '__stripe_sid',
                        'set_by'     => 'Stripe, Inc.',
                        'set_by_url' => 'https://stripe.com/privacy',
                        'purpose'    => 'Session identifier used by Stripe during the checkout flow.',
                        'duration'   => 'Session',
                        'type'       => 'Third-party (stripe.com)',
                    ],
                    [
                        'name'       => 'ftt_ads_conversion_fired',
                        'set_by'     => $site,
                        'set_by_url' => '',
                        'purpose'    => 'Set for 5 minutes after a successful subscription checkout to prevent a Google Ads conversion event firing twice if the confirmation page is refreshed.',
                        'duration'   => '5 minutes',
                        'type'       => 'First-party',
                    ],
                ],
            ],
        ];

        $th = 'text-align:left;padding:10px 12px;border-bottom:2px solid #ddd;';
        $td = 'padding:9px 12px;border-bottom:1px solid #eee;';

        ob_start();
        ?>
        <div class="ftt-cookie-policy" style="max-width:900px;">

            <!-- ── Introduction ── -->
            <h2>What are cookies?</h2>
            <p>Cookies are small text files placed on your device when you visit a website. They allow the site to remember information about your visit — such as whether you are logged in — and help us understand how visitors use our service.</p>
            <p>This policy explains <strong>every</strong> cookie <?php echo $site; ?> places on your device, why we use it, and how long it stays. We do not use cookies for advertising profiling or to build cross-site tracking profiles.</p>

            <!-- ── Cookie table ── -->
            <h2 style="margin-top:1.8em;">Cookies we use</h2>
            <?php foreach ( $groups as $group ) : ?>
            <h3 style="display:flex;align-items:center;gap:8px;margin-top:1.8em;">
                <span style="display:inline-block;width:12px;height:12px;border-radius:50%;flex-shrink:0;background:<?php echo esc_attr( $group['color'] ); ?>;"></span>
                <?php echo $group['category']; // safe — hardcoded above ?>
            </h3>
            <p style="font-size:13px;line-height:1.6;color:#555;margin-bottom:10px;"><?php echo $group['legal_basis']; // safe — hardcoded above ?></p>
            <div style="overflow-x:auto;margin-bottom:1.5em;">
            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <thead>
                    <tr style="background:#f3f0f8;">
                        <th style="<?php echo $th; ?>white-space:nowrap;">Cookie name</th>
                        <th style="<?php echo $th; ?>white-space:nowrap;">Set by</th>
                        <th style="<?php echo $th; ?>">Purpose</th>
                        <th style="<?php echo $th; ?>white-space:nowrap;">Duration</th>
                        <th style="<?php echo $th; ?>white-space:nowrap;">Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $group['rows'] as $i => $row ) : ?>
                    <tr style="<?php echo $i % 2 === 1 ? 'background:#faf9fc;' : ''; ?>">
                        <td style="<?php echo $td; ?>font-family:monospace;font-size:13px;white-space:nowrap;vertical-align:top;">
                            <?php echo esc_html( $row['name'] ); ?>
                        </td>
                        <td style="<?php echo $td; ?>white-space:nowrap;vertical-align:top;">
                            <?php if ( ! empty( $row['set_by_url'] ) ) : ?>
                                <a href="<?php echo esc_url( $row['set_by_url'] ); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo esc_html( $row['set_by'] ); ?></a>
                            <?php else : ?>
                                <?php echo esc_html( $row['set_by'] ); ?>
                            <?php endif; ?>
                        </td>
                        <td style="<?php echo $td; ?>line-height:1.5;vertical-align:top;"><?php echo esc_html( $row['purpose'] ); ?></td>
                        <td style="<?php echo $td; ?>white-space:nowrap;vertical-align:top;"><?php echo esc_html( $row['duration'] ); ?></td>
                        <td style="<?php echo $td; ?>white-space:nowrap;vertical-align:top;"><?php echo esc_html( $row['type'] ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endforeach; ?>

            <!-- ── International transfers ── -->
            <h2 style="margin-top:1.8em;">International data transfers</h2>
            <p>Some third-party services listed above operate servers in the <strong>United States</strong>:</p>
            <ul>
                <li><strong>Google Analytics 4</strong> — Google LLC, covered by the <a href="https://www.dataprivacyframework.gov/" target="_blank" rel="noopener noreferrer">EU–US Data Privacy Framework</a> adequacy decision.</li>
                <li><strong>Stripe</strong> — Stripe, Inc., transfers are covered by Standard Contractual Clauses (SCCs) approved by the European Commission. See <a href="https://stripe.com/privacy" target="_blank" rel="noopener noreferrer">Stripe's Privacy Policy</a>.</li>
            </ul>
            <p>We engage with these processors under Data Processing Agreements that require them to apply appropriate safeguards to your data.</p>

            <!-- ── Manage / withdraw ── -->
            <h2 style="margin-top:1.8em;">How to manage or withdraw your consent</h2>
            <p>You can change or withdraw your cookie consent at any time using the button below. This will reopen the consent banner so you can change your choice:</p>
            <p><?php echo self::render_manage_cookies_button(); ?></p>
            <p>You can also control cookies through your browser settings:</p>
            <ul>
                <li><a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener noreferrer">Google Chrome</a></li>
                <li><a href="https://support.mozilla.org/en-US/kb/enable-and-disable-cookies-website-preferences" target="_blank" rel="noopener noreferrer">Mozilla Firefox</a></li>
                <li><a href="https://support.apple.com/guide/safari/manage-cookies-sfri11471/mac" target="_blank" rel="noopener noreferrer">Apple Safari</a></li>
                <li><a href="https://support.microsoft.com/en-us/windows/manage-cookies-in-microsoft-edge-168dab11-0753-043d-7c16-ede5947fc64d" target="_blank" rel="noopener noreferrer">Microsoft Edge</a></li>
            </ul>
            <p>Note that disabling cookies through your browser may affect the functionality of this site, including your ability to stay logged in.</p>

            <!-- ── Your rights ── -->
            <h2 style="margin-top:1.8em;">Your rights under GDPR</h2>
            <p>If you are located in the European Economic Area (EEA), United Kingdom, or Switzerland, you have the following rights under applicable data protection law:</p>
            <ul style="line-height:2;">
                <li><strong>Right of access</strong> — request a copy of the personal data we hold about you.</li>
                <li><strong>Right to rectification</strong> — ask us to correct inaccurate data.</li>
                <li><strong>Right to erasure</strong> — ask us to delete your personal data ("right to be forgotten").</li>
                <li><strong>Right to data portability</strong> — receive your data in a structured, machine-readable format.</li>
                <li><strong>Right to object</strong> — object to processing based on legitimate interests.</li>
                <li><strong>Right to withdraw consent</strong> — where processing is based on consent, you can withdraw it at any time (see above). Withdrawal does not affect the lawfulness of processing before withdrawal.</li>
                <li><strong>Right to lodge a complaint</strong> — you have the right to complain to your local supervisory authority. In the EU, find yours at <a href="https://edpb.europa.eu/about-edpb/about-edpb/members_en" target="_blank" rel="noopener noreferrer">edpb.europa.eu</a>.</li>
            </ul>
            <p>To exercise any of these rights, contact us at <a href="mailto:<?php echo esc_attr( $admin_email ); ?>"><?php echo esc_html( $admin_email ); ?></a>. We will respond within 30 days.</p>

            <!-- ── Data controller ── -->
            <h2 style="margin-top:1.8em;">Data controller</h2>
            <p>
                <strong><?php echo $site; ?></strong> is the data controller for data collected through this website.<br>
                Contact: <a href="mailto:<?php echo esc_attr( $admin_email ); ?>"><?php echo esc_html( $admin_email ); ?></a>
            </p>

            <!-- ── Footer ── -->
            <p style="margin-top:2em;padding-top:1em;border-top:1px solid #eee;font-size:13px;color:#888;">
                Last updated: <?php echo esc_html( date( 'F j, Y' ) ); ?>.
            </p>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [ftt_manage_cookies]
     *
     * Renders a "Manage cookie preferences" button that clears the consent
     * cookie and re-opens the consent banner so users can change their choice.
     * GDPR requires that consent withdrawal is as easy as giving it.
     */
    public static function render_manage_cookies_button( $atts = [] ) {
        $atts  = shortcode_atts( [ 'label' => 'Manage cookie preferences' ], $atts, 'ftt_manage_cookies' );
        $label = esc_html( $atts['label'] );
        return '<button type="button" onclick="fttManageCookies()" '
            . 'style="background:#6A3E8E;color:#fff;border:none;padding:10px 20px;border-radius:6px;'
            . 'font-size:14px;font-weight:600;cursor:pointer;">'
            . '🍪 ' . $label
            . '</button>';
    }
}

// Initialize
FTT_Shortcodes::init();
