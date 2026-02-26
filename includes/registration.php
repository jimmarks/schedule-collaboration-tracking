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
class SRT_Registration {
    
    /**
     * Initialize
     */
    public static function init() {
        add_shortcode('srt_register', array(__CLASS__, 'registration_shortcode'));
        add_action('init', array(__CLASS__, 'handle_registration'));
    }
    
    /**
     * Registration form shortcode
     */
    public static function registration_shortcode($atts) {
        // Don't show to logged-in users
        if (is_user_logged_in()) {
            return '<p>' . __('You are already registered and logged in.', 'schedule-collaboration-tracking') . ' <a href="' . wp_logout_url(home_url()) . '">' . __('Logout', 'schedule-collaboration-tracking') . '</a></p>';
        }
        
        ob_start();
        include SRT_PLUGIN_DIR . 'templates/registration-form.php';
        return ob_get_clean();
    }
    
    /**
     * Handle registration form submission
     */
    public static function handle_registration() {
        if (!isset($_POST['srt_register_submit']) || !isset($_POST['srt_register_nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['srt_register_nonce'], 'srt_register')) {
            return;
        }
        
        $user_type = sanitize_text_field($_POST['user_type']);
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];
        
        $errors = array();
        
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
            set_transient('srt_registration_errors', $errors, 45);
            return;
        }
        
        // Create user
        $username = sanitize_user(strtolower($first_name . '.' . $last_name));
        
        // Make username unique if needed
        $username_base = $username;
        $counter = 1;
        while (username_exists($username)) {
            $username = $username_base . $counter;
            $counter++;
        }
        
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            set_transient('srt_registration_errors', array($user_id->get_error_message()), 45);
            return;
        }
        
        // Update user info
        wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $first_name . ' ' . $last_name,
        ));
        
        // Store phone
        if (!empty($phone)) {
            update_user_meta($user_id, 'phone', $phone);
        }
        
        // Handle user type
        if ($user_type === 'member') {
            // Mark as member
            SRT_Roles::make_member($user_id);
            
            // Store member-specific info
            if (!empty($_POST['member_instrument'])) {
                update_user_meta($user_id, 'srt_instrument', sanitize_text_field($_POST['member_instrument']));
            }
            if (!empty($_POST['member_section'])) {
                update_user_meta($user_id, 'srt_section', sanitize_text_field($_POST['member_section']));
            }
            
            // Check if any parents are waiting to link to this email
            SRT_Invitations::process_pending_parent_links($user_id);
            
        } elseif ($user_type === 'parent') {
            // Check if parent provided an invite code
            $invite_code = sanitize_text_field($_POST['invite_code'] ?? '');
            
            if (!empty($invite_code)) {
                // Try to link via invite code
                if (strpos($invite_code, 'M-') === 0) {
                    // Member code
                    $member_id = SRT_Invitations::get_member_by_code($invite_code);
                    if ($member_id) {
                        SRT_Roles::add_parent_child($user_id, $member_id);
                    }
                } elseif (strpos($invite_code, 'INV-') === 0) {
                    // Invitation code
                    $invitation = SRT_Invitations::get_invitation_by_code($invite_code);
                    if ($invitation && $invitation['status'] === 'pending') {
                        SRT_Roles::add_parent_child($user_id, $invitation['member_id']);
                        SRT_Invitations::update_invitation_status($invite_code, 'accepted', $user_id);
                    }
                }
            }
            
            // Fallback: If parent provided member email, link them
            $member_email = sanitize_email($_POST['member_email'] ?? '');
            if (!empty($member_email)) {
                $member = get_user_by('email', $member_email);
                if ($member) {
                    SRT_Roles::add_parent_child($user_id, $member->ID);
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
        
        // Log user in
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        
        // Redirect
        $redirect = !empty($_POST['redirect_to']) ? $_POST['redirect_to'] : home_url();
        wp_safe_redirect($redirect);
        exit;
    }
}

// Initialize
SRT_Registration::init();
