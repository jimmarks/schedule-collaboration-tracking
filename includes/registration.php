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
        add_filter('authenticate', array(__CLASS__, 'verify_login_recaptcha'), 30, 3);
        add_filter('login_redirect', array(__CLASS__, 'intercept_login_redirect'), 1, 3); // Highest priority
        add_filter('logout_redirect', array(__CLASS__, 'redirect_after_logout'), 10, 3);
    }
    
    /**
     * Intercept login redirects for newly registered users
     */
    /**
     * Send users to the FTT login page after logout instead of wp-login.php.
     */
    public static function redirect_after_logout($redirect_to, $requested_redirect_to, $user) {
        $login_url = home_url('/ftt-login/');
        return add_query_arg('logged_out', '1', $login_url);
    }

    public static function intercept_login_redirect($redirect_to, $request, $user) {
        if (isset($user->ID)) {
            $stored_redirect = get_transient('ftt_post_registration_redirect_' . $user->ID);
            if ($stored_redirect) {
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('FTT DEBUG: intercept_login_redirect - using stored redirect: ' . $stored_redirect);
                }
                delete_transient('ftt_post_registration_redirect_' . $user->ID);
                return $stored_redirect;
            }

            // Apply user's login redirect preference when no specific page was targeted.
            // Only applies when redirect_to equals the default dashboard URL.
            $default_redirect = home_url('/ftt-dashboard/');
            if (untrailingslashit($redirect_to) === untrailingslashit($default_redirect)) {
                $pref = get_user_meta($user->ID, 'ftt_login_redirect_preference', true);
                if ($pref && $pref !== 'dashboard') {
                    switch ($pref) {
                        case 'calendar':
                            return home_url('/ftt-calendar/');
                        case 'account':
                            return home_url('/manage-subscription/');
                    }
                }
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
        
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('FTT DEBUG: Registration handler called - ftt_register_submit present');
        }
        
        if (!wp_verify_nonce($_POST['ftt_register_nonce'], 'ftt_register')) {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT DEBUG: Nonce verification FAILED');
            }
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('FTT DEBUG: Nonce verified successfully');
        }
        
        $errors = array();
        
        // Verify reCAPTCHA v3 if enabled
        $settings = get_option('ftt_settings', array());
        $enable_recaptcha = $settings['enable_recaptcha'] ?? false;
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('FTT DEBUG: reCAPTCHA enabled: ' . ($enable_recaptcha ? 'yes' : 'no'));
        }
        
        if ($enable_recaptcha) {
            $recaptcha_response = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';
            
            if (empty($recaptcha_response)) {
                $errors[] = __('Please complete the captcha verification.', 'schedule-collaboration-tracking');
            } else {
                $verification = self::verify_recaptcha($recaptcha_response);
                if (!$verification['success']) {
                    $errors[] = __('Captcha verification failed. Please try again.', 'schedule-collaboration-tracking');
                }
            }
        }
        
        // Return early if captcha failed
        if (!empty($errors)) {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT DEBUG: reCAPTCHA validation failed: ' . implode(', ', $errors));
            }
            set_transient('ftt_registration_errors', $errors, 60);
            $redirect_url = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : home_url('/ftt-register/');
            wp_safe_redirect($redirect_url);
            exit;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('FTT DEBUG: reCAPTCHA passed, continuing with registration');
        }
        
        $user_type = sanitize_text_field($_POST['user_type']);
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $email = sanitize_email($_POST['email']);
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        
        // Decrypt password (encrypted client-side)
        $password_encrypted = isset($_POST['password']) ? $_POST['password'] : '';
        $password_confirm_encrypted = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';
        
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('FTT DEBUG: Attempting password decryption...');
            error_log('FTT DEBUG: Encrypted password length: ' . strlen($password_encrypted));
            error_log('FTT DEBUG: Encrypted confirm length: ' . strlen($password_confirm_encrypted));
        }
        
        $password = FTT_Password_Encryption::decrypt_password($password_encrypted);
        $password_confirm = FTT_Password_Encryption::decrypt_password($password_confirm_encrypted);
        
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            if ($password === false) {
                error_log('FTT DEBUG: Password decryption FAILED');
            } else {
                error_log('FTT DEBUG: Password decrypted successfully (length: ' . strlen($password) . ')');
            }
            if ($password_confirm === false) {
                error_log('FTT DEBUG: Password confirm decryption FAILED');
            } else {
                error_log('FTT DEBUG: Password confirm decrypted successfully (length: ' . strlen($password_confirm) . ')');
            }
        }
        
        if ($password === false || $password_confirm === false) {
            $errors[] = __('Password decryption failed. Please try again.', 'schedule-collaboration-tracking');
        }
        
        $planned_children = isset($_POST['planned_children']) ? intval($_POST['planned_children']) : 1;
        $group_name = isset($_POST['group_name']) ? sanitize_text_field($_POST['group_name']) : $first_name . "'s Family";
        $billing_interval = isset($_POST['billing_interval']) && $_POST['billing_interval'] === 'year' ? 'year' : 'month';
        
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('FTT DEBUG: Form data - Name: ' . $first_name . ' ' . $last_name . ', Email: ' . $email);
            error_log('FTT DEBUG: Group: ' . $group_name . ', Children: ' . $planned_children . ', Interval: ' . $billing_interval);
        }
        
        // Validation
        if (empty($first_name) || empty($last_name)) {
            $errors[] = __('Please enter your full name.', 'schedule-collaboration-tracking');
        }
        
        if (empty($email) || !is_email($email)) {
            $errors[] = __('Please enter a valid email address.', 'schedule-collaboration-tracking');
        }

        if (empty($phone)) {
            $errors[] = __('Phone number is required.', 'schedule-collaboration-tracking');
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
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT DEBUG: Validation failed: ' . implode(', ', $errors));
            }
            set_transient('ftt_registration_errors', $errors, 45);
            wp_safe_redirect(home_url('/ftt-register/'));
            exit;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('FTT DEBUG: Validation passed. Creating user with email: ' . $email);
        }
        
        // Create user - use email as username
        $username = $email;
        
        $user_id = wp_create_user($username, $password, $email);
        
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            if (is_wp_error($user_id)) {
                error_log('FTT DEBUG: User creation FAILED: ' . $user_id->get_error_message());
            } else {
                error_log('FTT DEBUG: User created with ID: ' . $user_id);
            }
        }
        
        if (is_wp_error($user_id)) {
            set_transient('ftt_registration_errors', array($user_id->get_error_message()), 45);
            wp_safe_redirect(home_url('/ftt-register/'));
            exit;
        }
        
        // Log user in IMMEDIATELY after creation (before group operations that require logged-in user)
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('FTT DEBUG: Logging in user ID: ' . $user_id . ' (before group creation)');
        }
        wp_clear_auth_cookie();
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        
        // Update user info
        wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $first_name . ' ' . $last_name,
        ));
        
        // Disable admin toolbar on frontend for this user
        update_user_meta($user_id, 'show_admin_bar_front', 'false');
        
        // Store user type (parent or member)
        update_user_meta($user_id, 'user_type', $user_type);
        
        // Check for adult invitation code BEFORE auto-creating a group
        // This prevents creating a duplicate group when the user is being invited to an existing one
        $adult_invite_code = sanitize_text_field($_POST['ftt_invite_code'] ?? '');
        
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('FTT DEBUG: POST ftt_invite_code value: ' . var_export($_POST['ftt_invite_code'] ?? 'NOT SET', true));
            error_log('FTT DEBUG: Sanitized adult_invite_code: ' . var_export($adult_invite_code, true));
            error_log('FTT DEBUG: Is empty check result: ' . (empty($adult_invite_code) ? 'EMPTY' : 'NOT EMPTY'));
        }
        
        $has_adult_invitation = false;
        $invitation_group_id = 0;
        $inviter_user_id = 0;
        $invitation_relationship = 'co-parent';
        
        if (!empty($adult_invite_code)) {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT DEBUG: Checking for invitation code: ' . $adult_invite_code);
            }
            
            // Search for the invitation
            $users = get_users(array('meta_key' => 'ftt_adult_invitations'));
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT DEBUG: Found ' . count($users) . ' users with adult invitations');
            }
            
            foreach ($users as $inviter_user) {
                $invitations = get_user_meta($inviter_user->ID, 'ftt_adult_invitations', true);
                
                if (is_array($invitations) && isset($invitations[$adult_invite_code])) {
                    $invitation = $invitations[$adult_invite_code];
                    
                    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                        error_log('FTT DEBUG: Found invitation - expires: ' . $invitation['expires'] . ', now: ' . time() . ', status: ' . ($invitation['status'] ?? 'not set'));
                    }
                    
                    // Check if not expired and pending
                    $status = isset($invitation['status']) ? $invitation['status'] : 'pending';
                    if (isset($invitation['expires']) && $invitation['expires'] > time() && $status === 'pending') {
                        $has_adult_invitation = true;
                        $invitation_group_id = isset($invitation['group_id']) ? intval($invitation['group_id']) : 0;
                        $inviter_user_id = $inviter_user->ID;
                        $invitation_relationship = isset($invitation['relationship']) ? $invitation['relationship'] : 'co-parent';
                        $invitation_can_manage = isset($invitation['can_manage_group']) ? (bool) $invitation['can_manage_group'] : true;
                        
                        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                            error_log('FTT DEBUG: VALID INVITATION FOUND - group_id: ' . $invitation_group_id . ', inviter: ' . $inviter_user_id . ', relationship: ' . $invitation_relationship . ', can_manage: ' . ($invitation_can_manage ? 'yes' : 'no'));
                        }
                        
                        // IMMEDIATELY add user to the invited group
                        if ($invitation_group_id > 0 && class_exists('FTT_Family_Groups')) {
                            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                                error_log('FTT DEBUG: Adding user ' . $user_id . ' to invited group ' . $invitation_group_id);
                            }
                            
                            $add_result = FTT_Family_Groups::add_member($invitation_group_id, $user_id, 'parent', [
                                'relationship' => $invitation_relationship,
                                'can_manage_group' => $invitation_can_manage,
                                'added_by' => $inviter_user_id
                            ]);
                            
                            if (is_wp_error($add_result)) {
                                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                                    error_log('FTT DEBUG: FAILED to add user to group: ' . $add_result->get_error_message());
                                }
                            } else {
                                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                                    error_log('FTT DEBUG: SUCCESS - User added to group ' . $invitation_group_id);
                                }
                                
                                // Set as primary group
                                update_user_meta($user_id, 'ftt_primary_group', $invitation_group_id);
                                
                                // LEGACY SUPPORT: Also create user meta relationships for backward compatibility
                                if (class_exists('FTT_Roles')) {
                                    // Get all members of the group
                                    $group_members = FTT_Family_Groups::get_group_members($invitation_group_id);
                                    
                                    // Link to all parents in the group (co-parents)
                                    $parent_ids = [];
                                    $child_ids = [];
                                    foreach ($group_members as $member) {
                                        if ($member->user_id == $user_id) continue; // Skip self
                                        
                                        if ($member->role === 'parent') {
                                            $parent_ids[] = $member->user_id;
                                        } elseif ($member->role === 'child') {
                                            $child_ids[] = $member->user_id;
                                        }
                                    }
                                    
                                    // Update ftt_parents for new user (link to co-parents)
                                    if (!empty($parent_ids)) {
                                        update_user_meta($user_id, 'ftt_parents', $parent_ids);
                                        
                                        // Add this new parent to each existing parent's ftt_parents
                                        foreach ($parent_ids as $parent_id) {
                                            $existing_parents = get_user_meta($parent_id, 'ftt_parents', true);
                                            if (!is_array($existing_parents)) {
                                                $existing_parents = [];
                                            }
                                            if (!in_array($user_id, $existing_parents)) {
                                                $existing_parents[] = $user_id;
                                                update_user_meta($parent_id, 'ftt_parents', $existing_parents);
                                            }
                                        }
                                    }
                                    
                                    // Link to all children in the group
                                    if (!empty($child_ids)) {
                                        update_user_meta($user_id, 'ftt_parent_of', $child_ids);
                                        
                                        // Add this parent to each child's ftt_parents
                                        foreach ($child_ids as $child_id) {
                                            $child_parents = get_user_meta($child_id, 'ftt_parents', true);
                                            if (!is_array($child_parents)) {
                                                $child_parents = [];
                                            }
                                            if (!in_array($user_id, $child_parents)) {
                                                $child_parents[] = $user_id;
                                                update_user_meta($child_id, 'ftt_parents', $child_parents);
                                            }
                                            
                                            // Store relationship type
                                            update_user_meta($child_id, 'relationship_to_' . $user_id, $invitation_relationship);
                                        }
                                    }
                                    
                                    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                                        error_log('FTT DEBUG: Legacy relationships created - Linked to ' . count($parent_ids) . ' parents and ' . count($child_ids) . ' children');
                                    }
                                }
                                
                                // Mark invitation as accepted
                                $invitation['status'] = 'accepted';
                                $invitation['accepted_by'] = $user_id;
                                $invitation['accepted_at'] = time();
                                $invitations[$adult_invite_code] = $invitation;
                                update_user_meta($inviter_user_id, 'ftt_adult_invitations', $invitations);
                                
                                // Skip normal registration flow and redirect to groups page
                                $redirect_url = home_url('/ftt-groups/');
                                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                                    error_log('FTT DEBUG: Invitation accepted - redirecting to groups page: ' . $redirect_url);
                                }
                                wp_redirect($redirect_url, 302);
                                die();
                            }
                        } else {
                            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                                error_log('FTT DEBUG: Cannot add to group - group_id: ' . $invitation_group_id . ', FTT_Family_Groups exists: ' . (class_exists('FTT_Family_Groups') ? 'yes' : 'no'));
                            }
                        }
                    } else {
                        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                            error_log('FTT DEBUG: Invitation invalid - expired or wrong status');
                        }
                    }
                    break;
                }
            }
        }

        // Also check ftt_group_invitations DB table (group invitations sent from the Groups page)
        if (!$has_adult_invitation && !empty($adult_invite_code) && class_exists('FTT_Family_Groups')) {
            global $wpdb;
            $inv_table = $wpdb->prefix . 'ftt_group_invitations';
            $db_invite = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$inv_table} WHERE invite_code = %s AND status = 'pending' AND expires_at > %s",
                $adult_invite_code,
                current_time('mysql')
            ));

            if ($db_invite) {
                $has_adult_invitation   = true;
                $invitation_group_id    = intval($db_invite->group_id);
                $inviter_user_id        = intval($db_invite->invited_by);
                $invitation_relationship= $db_invite->relationship ?: 'co-parent';
                $invitation_can_manage  = true;

                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('FTT DEBUG: Found DB group invitation - group_id: ' . $invitation_group_id . ', inviter: ' . $inviter_user_id);
                }

                $add_result = FTT_Family_Groups::add_member($invitation_group_id, $user_id, 'parent', [
                    'relationship'     => $invitation_relationship,
                    'can_manage_group' => $invitation_can_manage,
                    'added_by'         => $inviter_user_id,
                ]);

                if (!is_wp_error($add_result)) {
                    update_user_meta($user_id, 'ftt_primary_group', $invitation_group_id);

                    // Mark DB invitation as accepted
                    $wpdb->update(
                        $inv_table,
                        [
                            'status'      => 'accepted',
                            'accepted_by' => $user_id,
                            'accepted_at' => current_time('mysql'),
                        ],
                        ['invite_code' => $adult_invite_code]
                    );

                    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                        error_log('FTT DEBUG: DB group invitation accepted - redirecting to groups page');
                    }
                    wp_redirect(home_url('/ftt-groups/'), 302);
                    die();
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                        error_log('FTT DEBUG: FAILED to add user to group from DB invite: ' . $add_result->get_error_message());
                    }
                }
            }
        }

        // Auto-create family group for new users (v2.2)
        // SKIP if user has a valid adult invitation - they'll be added to the inviting group instead
        $group_id = null;
        if (class_exists('FTT_Family_Groups') && $user_type === 'parent' && !$has_adult_invitation) {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT DEBUG: Creating family group: ' . $group_name);
            }
            
            $group_id = FTT_Family_Groups::create_group([
                'name' => $group_name,
                'description' => '',
                'billing_owner' => $user_id,
                'color' => '#2196F3', // Blue
                'is_primary' => 1
            ]);
            
            if (is_wp_error($group_id)) {
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('FTT DEBUG: Group creation FAILED: ' . $group_id->get_error_message());
                }
                $errors[] = 'Failed to create family group: ' . $group_id->get_error_message();
                set_transient('ftt_registration_errors', $errors, 45);
                wp_safe_redirect(home_url('/ftt-register/'));
                exit;
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT DEBUG: Group created with ID: ' . $group_id . ', adding member...');
            }
            
            // Add creator as parent with full permissions
            $member_result = FTT_Family_Groups::add_member($group_id, $user_id, 'parent', [
                'relationship' => 'self',
                'can_manage_group' => true
            ]);
            
            if (is_wp_error($member_result)) {
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('FTT DEBUG: Add member FAILED: ' . $member_result->get_error_message());
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('FTT DEBUG: Member added successfully');
                }
            }
            
            // Store the auto-created group ID and planned children count
            update_user_meta($user_id, 'ftt_primary_group', $group_id);
            update_user_meta($user_id, 'ftt_planned_children', $planned_children);
            
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT DEBUG: Auto-created family group ' . $group_id . ' for user ' . $user_id . ' (planned: ' . $planned_children . ' children)');
            }
        }
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('FTT DEBUG: Stored user_type: ' . $user_type);
        }
        
        // Store phone
        if (!empty($phone)) {
            update_user_meta($user_id, 'phone', $phone);
        }
        
        // Note: planned_children already stored above in group creation section
        
        // Handle user type
        if ($user_type === 'member') {
            // Mark as member
            FTT_Roles::make_member($user_id);
            
            // Store member-specific info
            if (!empty($_POST['member_instrument'])) {
                update_user_meta($user_id, 'ftt_instrument', sanitize_text_field($_POST['member_instrument']));
            }
            if (!empty($_POST['member_section'])) {
                update_user_meta($user_id, 'ftt_section', sanitize_text_field($_POST['member_section']));
            }
            
            // Check if any parents are waiting to link to this email
            FTT_Invitations::process_pending_parent_links($user_id);
            
        } elseif ($user_type === 'parent') {
            // Note: Adult invitation handling moved earlier (after user creation) to prevent duplicate group creation
            // Check if parent provided an invite code for member/child linking
            $invite_code = sanitize_text_field($_POST['invite_code'] ?? '');
            
            if (!empty($invite_code)) {
                // Try to link via member/child invite code
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
                    update_user_meta($user_id, 'ftt_pending_child_email', $member_email);
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
        
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('FTT DEBUG: Registration complete. Determining redirect...');
        }
        
        // Determine redirect destination
        // v2.3: No-friction onboarding — start card-free trial, redirect to onboarding wizard
        $redirect_url = home_url('/ftt-dashboard/'); // Default fallback

        // Note: Users who accepted adult invitations exit earlier with redirect to /ftt-groups/
        // This section only handles new parent registrations

        // v2.3: Write trial status directly (no Stripe required at registration)
        if (class_exists('FTT_Family_Groups') && !empty($group_id) && $user_type === 'parent') {
            $ftt_stripe_settings = get_option('ftt_stripe_settings', []);
            $ftt_trial_days      = max(1, intval($ftt_stripe_settings['trial_days'] ?? 14));

            FTT_Family_Groups::update_group_billing($group_id, [
                'subscription_status' => 'trialing',
                'trial_ends_at'       => date('Y-m-d H:i:s', strtotime("+{$ftt_trial_days} days")),
            ]);

            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT DEBUG: Card-free trial started for group ' . $group_id . ', ending in ' . $ftt_trial_days . ' days. Redirecting to onboarding.');
            }

            $redirect_url = home_url('/ftt-onboarding/');
        }
        
        // Store redirect URL in transient before redirecting
        set_transient('ftt_post_registration_redirect_' . $user_id, $redirect_url, 60);
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('FTT DEBUG: Stored redirect URL in transient for user: ' . $user_id);
        }
        
        // IMMEDIATELY redirect - don't let WordPress process anything else
        // (User is already logged in from earlier in the flow)
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('FTT DEBUG: Performing immediate redirect to: ' . $redirect_url);
        }
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
    public static function verify_login_recaptcha($user, $username, $password) {
        // Skip if already an error or if username/password are empty
        if (is_wp_error($user) || empty($username) || empty($password)) {
            return $user;
        }
        
        // Skip reCAPTCHA check for wp-login.php (admin login)
        // Only apply to custom FTT login forms
        $is_wp_login = (strpos($_SERVER['SCRIPT_NAME'], 'wp-login.php') !== false);
        if ($is_wp_login) {
            return $user;
        }
        
        // Check if reCAPTCHA is enabled
        $settings = get_option('ftt_settings', array());
        $enable_recaptcha = $settings['enable_recaptcha'] ?? false;
        
        if (!$enable_recaptcha) {
            return $user;
        }
        
        // Verify reCAPTCHA v3 response token
        $recaptcha_response = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';
        
        if (empty($recaptcha_response)) {
            return new WP_Error(
                'recaptcha_error',
                __('<strong>ERROR</strong>: Please complete the captcha verification.', 'schedule-collaboration-tracking')
            );
        }
        
        $verification = self::verify_recaptcha($recaptcha_response);
        
        if (!$verification['success']) {
            return new WP_Error(
                'recaptcha_error',
                __('<strong>ERROR</strong>: Captcha verification failed. Please try again.', 'schedule-collaboration-tracking')
            );
        }
        
        return $user;
    }
    
    /**
     * Verify Google reCAPTCHA v3 response token
     * 
     * @param string $response reCAPTCHA response token from g-recaptcha-response POST field
     * @return array Array with 'success' boolean and optional 'error' message
     */
    private static function verify_recaptcha($response) {
        $settings = get_option('ftt_settings', array());
        $secret_key = $settings['recaptcha_secret_key'] ?? '';
        
        if (empty($secret_key)) {
            return array(
                'success' => false,
                'error' => 'reCAPTCHA secret key not configured'
            );
        }
        
        $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
        $options = array(
            'body' => array(
                'secret'   => $secret_key,
                'response' => $response,
                'remoteip' => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
            ),
            'method'  => 'POST',
            'timeout' => 15,
        );
        
        $api_response = wp_remote_post($verify_url, $options);
        
        if (is_wp_error($api_response)) {
            return array(
                'success' => false,
                'error' => $api_response->get_error_message()
            );
        }
        
        $result = json_decode(wp_remote_retrieve_body($api_response), true);
        
        if (!isset($result['success'])) {
            return array(
                'success' => false,
                'error' => 'Invalid response from reCAPTCHA server'
            );
        }
        
        // reCAPTCHA v3 returns a score (0.0–1.0). Threshold of 0.5 is Google's
        // recommended default — bots score near 0, humans near 1.
        $min_score = 0.5;
        $passed = $result['success'] && ( (float) ( $result['score'] ?? 0 ) >= $min_score );
        
        return array(
            'success' => $passed,
            'score'   => $result['score'] ?? null,
            'error'   => ! $passed && isset($result['error-codes']) ? implode(', ', $result['error-codes']) : ''
        );
    }
}

// Initialize
FTT_Registration::init();
