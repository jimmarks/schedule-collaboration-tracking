<?php
/**
 * User Registration
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SRT Registration Class
 */
class FTT_Registration {
    
    /**
     * Initialize
     */
    public static function init() {
        add_shortcode('ftt_register', array(__CLASS__, 'registration_shortcode'));
        add_action('init', array(__CLASS__, 'handle_registration'), 5); // Earlier priority
        add_filter('authenticate', array(__CLASS__, 'verify_login_hcaptcha'), 30, 3);
        add_filter('login_redirect', array(__CLASS__, 'intercept_login_redirect'), 1, 3); // Highest priority
    }
    
    /**
     * Intercept login redirects for newly registered users
     */
    public static function intercept_login_redirect($redirect_to, $request, $user) {
        if (isset($user->ID)) {
            $stored_redirect = get_transient('ftt_post_registration_redirect_' . $user->ID);
            if ($stored_redirect) {
                error_log('FTT DEBUG: intercept_login_redirect - using stored redirect: ' . $stored_redirect);
                delete_transient('ftt_post_registration_redirect_' . $user->ID);
                return $stored_redirect;
            }
        }
        return $redirect_to;
    }
    
    /**
     * Registration form shortcode
     */
    public static function registration_shortcode($atts) {
        // Don't show to logged-in users
        if (is_user_logged_in()) {
            return '<p>' . __('You are already registered and logged in.', 'schedule-collaboration-tracking') . ' <a href="' . wp_logout_url(home_url()) . '">' . __('Logout', 'schedule-collaboration-tracking') . '</a></p>';
        }
        
        // Ensure jQuery is loaded
        wp_enqueue_script('jquery');
        
        ob_start();
        include FTT_PLUGIN_DIR . 'templates/registration-form.php';
        return ob_get_clean();
    }
    
    /**
     * Handle registration form submission
     */
    public static function handle_registration() {
        if (!isset($_POST['ftt_register_submit']) || !isset($_POST['ftt_register_nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['ftt_register_nonce'], 'ftt_register')) {
            return;
        }
        
        $errors = array();
        
        // Verify hCaptcha if enabled
        $settings = get_option('ftt_settings', array());
        $enable_hcaptcha = $settings['enable_hcaptcha'] ?? false;
        error_log('FTT DEBUG: hCaptcha enabled: ' . ($enable_hcaptcha ? 'yes' : 'no'));
        
        if ($enable_hcaptcha) {
            $hcaptcha_response = isset($_POST['h-captcha-response']) ? $_POST['h-captcha-response'] : '';
            
            if (empty($hcaptcha_response)) {
                $errors[] = __('Please complete the captcha verification.', 'schedule-collaboration-tracking');
            } else {
                $verification = self::verify_hcaptcha($hcaptcha_response);
                if (!$verification['success']) {
                    $errors[] = __('Captcha verification failed. Please try again.', 'schedule-collaboration-tracking');
                }
            }
        }
        
        // Return early if captcha failed
        if (!empty($errors)) {
            error_log('FTT DEBUG: hCaptcha validation failed: ' . implode(', ', $errors));
            set_transient('ftt_registration_errors', $errors, 60);
            $redirect_url = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : home_url('/ftt-register/');
            wp_safe_redirect($redirect_url);
            exit;
        }
        
        error_log('FTT DEBUG: hCaptcha passed, continuing with registration');
        
        $user_type = sanitize_text_field($_POST['user_type']);
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $email = sanitize_email($_POST['email']);
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];
        $planned_children = isset($_POST['planned_children']) ? intval($_POST['planned_children']) : 1;
        
        // Validation
        if (empty($first_name) || empty($last_name)) {
            $errors[] = __('Please enter your full name.', 'schedule-collaboration-tracking');
        }
        
        if (empty($email) || !is_email($email)) {
            $errors[] = __('Please enter a valid email address.', 'schedule-collaboration-tracking');
        }
        
        if (email_exists($email)) {
            $errors[] = __('This email is already registered.', 'schedule-collaboration-tracking');
        }
        
        if (empty($password) || strlen($password) < 8) {
            $errors[] = __('Password must be at least 8 characters.', 'schedule-collaboration-tracking');
        }
        
        if ($password !== $password_confirm) {
            $errors[] = __('Passwords do not match.', 'schedule-collaboration-tracking');
        }
        
        if (!empty($errors)) {
            error_log('FTT DEBUG: Validation failed: ' . implode(', ', $errors));
            set_transient('ftt_registration_errors', $errors, 45);
            return;
        }
        
        error_log('FTT DEBUG: Validation passed. Creating user with email: ' . $email);
        
        // Create user - use email as username
        $username = $email;
        
        $user_id = wp_create_user($username, $password, $email);
        
        error_log('FTT DEBUG: User created with ID: ' . $user_id);
        
        if (is_wp_error($user_id)) {
            set_transient('ftt_registration_errors', array($user_id->get_error_message()), 45);
            return;
        }
        
        // Update user info
        wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $first_name . ' ' . $last_name,
        ));
        
        // Disable admin toolbar on frontend for this user
        update_user_meta($user_id, 'show_admin_bar_front', 'false');
        
        // Store phone
        if (!empty($phone)) {
            update_user_meta($user_id, 'phone', $phone);
        }
        
        // Store planned children count
        if (!empty($planned_children)) {
            update_user_meta($user_id, 'planned_children', $planned_children);
            error_log('FTT DEBUG: Stored planned_children: ' . $planned_children);
        } else {
            error_log('FTT DEBUG: WARNING - No planned_children value!');
        }
        
        // Handle user type
        if ($user_type === 'member') {
            // Mark as member
            FTT_Roles::make_member($user_id);
            
            // Store member-specific info
            if (!empty($_POST['member_instrument'])) {
                update_user_meta($user_id, 'srt_instrument', sanitize_text_field($_POST['member_instrument']));
            }
            if (!empty($_POST['member_section'])) {
                update_user_meta($user_id, 'srt_section', sanitize_text_field($_POST['member_section']));
            }
            
            // Check if any parents are waiting to link to this email
            FTT_Invitations::process_pending_parent_links($user_id);
            
        } elseif ($user_type === 'parent') {
            // Check if parent provided an invite code
            $invite_code = sanitize_text_field($_POST['invite_code'] ?? '');
            
            if (!empty($invite_code)) {
                // Try to link via invite code
                if (strpos($invite_code, 'M-') === 0) {
                    // Member code
                    $member_id = FTT_Invitations::get_member_by_code($invite_code);
                    if ($member_id) {
                        FTT_Roles::add_parent_child($user_id, $member_id);
                    }
                } elseif (strpos($invite_code, 'INV-') === 0) {
                    // Invitation code
                    $invitation = FTT_Invitations::get_invitation_by_code($invite_code);
                    if ($invitation && $invitation['status'] === 'pending') {
                        FTT_Roles::add_parent_child($user_id, $invitation['member_id']);
                        FTT_Invitations::update_invitation_status($invite_code, 'accepted', $user_id);
                    }
                }
            }
            
            // Fallback: If parent provided member email, link them
            $member_email = sanitize_email($_POST['member_email'] ?? '');
            if (!empty($member_email)) {
                $member = get_user_by('email', $member_email);
                if ($member) {
                    FTT_Roles::add_parent_child($user_id, $member->ID);
                } else {
                    // Store for later linking
                    update_user_meta($user_id, 'srt_pending_child_email', $member_email);
                }
            }
        }
        
        // Set role based on settings (default to subscriber)
        $user = new WP_User($user_id);
        $user->set_role('subscriber');
        
        // Send notification email to admins
        $admin_email = get_option('admin_email');
        $subject = sprintf('[%s] New Registration: %s', get_bloginfo('name'), $first_name . ' ' . $last_name);
        $message = sprintf(
            "New user registered:\n\nName: %s %s\nEmail: %s\nType: %s\n\nManage user: %s",
            $first_name,
            $last_name,
            $email,
            $user_type,
            admin_url('user-edit.php?user_id=' . $user_id)
        );
        wp_mail($admin_email, $subject, $message);
        
        error_log('FTT DEBUG: Registration complete. Determining redirect...');
        
        // Determine redirect destination BEFORE logging user in
        $redirect_url = home_url('/pricing/'); // Default fallback
        error_log('FTT DEBUG: Default redirect URL: ' . $redirect_url);
        
        // Try to create Stripe checkout session directly with planned children count
        if (class_exists('FTT_Stripe_Integration') && !empty($planned_children)) {
            error_log('FTT DEBUG: FTT_Stripe_Integration class exists, planned_children: ' . $planned_children);
            
            // Base subscription includes 1 child, so additional children = planned - 1
            $addon_quantity = max(0, $planned_children - 1);
            error_log('FTT DEBUG: Calculated addon_quantity: ' . $addon_quantity);
            
            // Default to monthly interval
            $interval = 'month';
            error_log('FTT DEBUG: Calling create_checkout_session with user_id=' . $user_id . ', interval=' . $interval . ', addon_qty=' . $addon_quantity);
            
            $session = FTT_Stripe_Integration::create_checkout_session($user_id, $interval, $addon_quantity);
            
            if ($session && !empty($session['url'])) {
                $redirect_url = $session['url'];
                error_log('FTT DEBUG: Stripe session created! URL: ' . $redirect_url);
            } else {
                error_log('FTT DEBUG: Stripe session creation FAILED - session is: ' . print_r($session, true));
            }
        } else {
            error_log('FTT DEBUG: Skipping Stripe - class exists: ' . (class_exists('FTT_Stripe_Integration') ? 'yes' : 'no') . ', planned_children: ' . ($planned_children ?? 'null'));
        }
        
        // Store redirect URL in transient before logging in
        set_transient('ftt_post_registration_redirect_' . $user_id, $redirect_url, 60);
        error_log('FTT DEBUG: Stored redirect URL in transient for user: ' . $user_id);
        
        // Log user in WITHOUT triggering wp_login action (which causes wp-admin redirect)
        error_log('FTT DEBUG: Logging in user ID: ' . $user_id);
        $user = get_user_by('id', $user_id);
        wp_clear_auth_cookie();
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        
        // IMMEDIATELY redirect - don't let WordPress process anything else
        error_log('FTT DEBUG: Performing immediate redirect to: ' . $redirect_url);
        wp_redirect($redirect_url, 302);
        die(); // Use die() instead of exit() to stop ALL execution
    }
    
    /**
     * Verify hCaptcha on login
     * 
     * @param WP_User|WP_Error $user WP_User or WP_Error object if a previous callback failed authentication
     * @param string $username Username or email address
     * @param string $password User password
     * @return WP_User|WP_Error WP_User on success, WP_Error on failure
     */
    public static function verify_login_hcaptcha($user, $username, $password) {
        // Skip if already an error or if username/password are empty
        if (is_wp_error($user) || empty($username) || empty($password)) {
            return $user;
        }
        
        // Check if hCaptcha is enabled
        $settings = get_option('ftt_settings', array());
        $enable_hcaptcha = $settings['enable_hcaptcha'] ?? false;
        
        if (!$enable_hcaptcha) {
            return $user;
        }
        
        // Verify hCaptcha response
        $hcaptcha_response = isset($_POST['h-captcha-response']) ? $_POST['h-captcha-response'] : '';
        
        if (empty($hcaptcha_response)) {
            return new WP_Error(
                'hcaptcha_error',
                __('<strong>ERROR</strong>: Please complete the captcha verification.', 'schedule-collaboration-tracking')
            );
        }
        
        $verification = self::verify_hcaptcha($hcaptcha_response);
        
        if (!$verification['success']) {
            return new WP_Error(
                'hcaptcha_error',
                __('<strong>ERROR</strong>: Captcha verification failed. Please try again.', 'schedule-collaboration-tracking')
            );
        }
        
        return $user;
    }
    
    /**
     * Verify hCaptcha response
     * 
     * @param string $response hCaptcha response token
     * @return array Array with 'success' boolean and optional 'error' message
     */
    private static function verify_hcaptcha($response) {
        $settings = get_option('ftt_settings', array());
        $secret_key = $settings['hcaptcha_secret_key'] ?? '';
        
        if (empty($secret_key)) {
            return array(
                'success' => false,
                'error' => 'hCaptcha secret key not configured'
            );
        }
        
        $verify_url = 'https://hcaptcha.com/siteverify';
        $data = array(
            'secret' => $secret_key,
            'response' => $response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        );
        
        $options = array(
            'body' => $data,
            'method' => 'POST',
            'timeout' => 15
        );
        
        $response = wp_remote_post($verify_url, $options);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);
        
        if (!isset($result['success'])) {
            return array(
                'success' => false,
                'error' => 'Invalid response from hCaptcha server'
            );
        }
        
        return array(
            'success' => $result['success'],
            'error' => !$result['success'] && isset($result['error-codes']) ? implode(', ', $result['error-codes']) : ''
        );
    }
}

// Initialize
FTT_Registration::init();
