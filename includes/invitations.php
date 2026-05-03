<?php
/**
 * Parent-Child Invitation System
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SRT Invitations Class
 * 
 * Manages member-to-parent invitations with unique codes
 */
class FTT_Invitations {
    
    /**
     * Initialize
     */
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public static function register_routes() {
        // Generate new invite code
        register_rest_route('ftt/v1', '/invite/generate', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'generate_invite_code'),
            'permission_callback' => function() {
                $user_id = get_current_user_id();
                
                if (!is_user_logged_in()) {
                    error_log('Invite generate: User not logged in');
                    return false;
                }
                
                // Admins can always generate codes
                if (current_user_can('manage_options')) {
                    error_log('Invite generate: Admin access granted for user ' . $user_id);
                    return true;
                }
                
                if (!class_exists('FTT_Roles')) {
                    error_log('Invite generate: FTT_Roles class not found');
                    return false;
                }
                
                $is_member = FTT_Roles::is_member($user_id);
                
                // If not marked as member, auto-promote if they have the right capability
                if (!$is_member && current_user_can('edit_posts')) {
                    error_log('Invite generate: User ' . $user_id . ' has edit_posts cap but not member flag, auto-promoting');
                    FTT_Roles::make_member($user_id);
                    $is_member = true;
                }
                
                error_log('Invite generate permission check - User ID: ' . $user_id . ', Is member: ' . ($is_member ? 'yes' : 'no'));
                
                return $is_member;
            }
        ));
        
        // Get member's invitations
        register_rest_route('ftt/v1', '/invitations', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_invitations'),
            'permission_callback' => 'is_user_logged_in'
        ));
        
        // Revoke/delete invitation
        register_rest_route('ftt/v1', '/invite/(?P<code>[a-zA-Z0-9\-]+)/revoke', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'revoke_invitation'),
            'permission_callback' => 'is_user_logged_in'
        ));
        
        // Parent accepts invitation by code
        register_rest_route('ftt/v1', '/invite/accept', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'accept_invitation_by_code'),
            'permission_callback' => 'is_user_logged_in'
        ));
        
        // Validate invite code (for registration page)
        register_rest_route('ftt/v1', '/invite/(?P<code>[a-zA-Z0-9\-]+)/validate', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'validate_invite_code'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Generate a unique member code for sharing
     * This is a permanent code that parents can use to link
     *
     * @param int $member_id Member user ID
     * @return string Member code (e.g., M-ABC123)
     */
    public static function get_member_code($member_id) {
        $code = get_user_meta($member_id, 'ftt_member_code', true);
        
        if (empty($code)) {
            // Generate new permanent code
            $code = 'M-' . strtoupper(substr(md5($member_id . time()), 0, 6));
            update_user_meta($member_id, 'ftt_member_code', $code);
        }
        
        return $code;
    }
    
    /**
     * Find member by their permanent code
     *
     * @param string $code Member code
     * @return int|false Member ID or false
     */
    public static function get_member_by_code($code) {
        $users = get_users(array(
            'meta_key' => 'ftt_member_code',
            'meta_value' => $code,
            'number' => 1
        ));
        
        return !empty($users) ? $users[0]->ID : false;
    }
    
    /**
     * Generate a single-use invite code for a parent
     *
     * @param int $member_id Member user ID
     * @return array Invitation data
     */
    public static function create_invitation($member_id) {
        $code = 'INV-' . strtoupper(wp_generate_password(10, false));
        
        $invitation = array(
            'code' => $code,
            'member_id' => $member_id,
            'created_at' => current_time('mysql'),
            'status' => 'pending',
            'used_by' => null,
            'used_at' => null
        );
        
        // Store in member's meta
        $invitations = get_user_meta($member_id, 'ftt_invitations', true);
        if (!is_array($invitations)) {
            $invitations = array();
        }
        $invitations[$code] = $invitation;
        update_user_meta($member_id, 'ftt_invitations', $invitations);
        
        return $invitation;
    }
    
    /**
     * Get invitation by code
     *
     * @param string $code Invitation code
     * @return array|false Invitation data or false
     */
    public static function get_invitation_by_code($code) {
        // Search through all users' invitations
        $users = get_users(array(
            'meta_key' => 'ftt_invitations',
            'meta_compare' => 'EXISTS'
        ));
        
        foreach ($users as $user) {
            $invitations = get_user_meta($user->ID, 'ftt_invitations', true);
            if (isset($invitations[$code])) {
                return $invitations[$code];
            }
        }
        
        return false;
    }
    
    /**
     * Update invitation status
     *
     * @param string $code Invitation code
     * @param string $status New status (accepted/rejected/revoked)
     * @param int|null $parent_id Parent user ID (for accepted)
     */
    public static function update_invitation_status($code, $status, $parent_id = null) {
        // Find the invitation
        $users = get_users(array(
            'meta_key' => 'ftt_invitations',
            'meta_compare' => 'EXISTS'
        ));
        
        foreach ($users as $user) {
            $invitations = get_user_meta($user->ID, 'ftt_invitations', true);
            if (isset($invitations[$code])) {
                $invitations[$code]['status'] = $status;
                
                if ($parent_id) {
                    $invitations[$code]['used_by'] = $parent_id;
                    $invitations[$code]['used_at'] = current_time('mysql');
                }
                
                update_user_meta($user->ID, 'ftt_invitations', $invitations);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get all invitations for a member
     *
     * @param int $member_id Member user ID
     * @return array Invitations with parent details
     */
    public static function get_member_invitations($member_id) {
        $invitations = get_user_meta($member_id, 'ftt_invitations', true);
        if (!is_array($invitations)) {
            return array();
        }
        
        $thirty_days_ago = strtotime('-30 days');
        
        // Filter and process invitations
        foreach ($invitations as $code => $invite) {
            // Remove revoked invitations older than 30 days
            if ($invite['status'] === 'revoked') {
                $created_timestamp = strtotime($invite['created_at']);
                if ($created_timestamp < $thirty_days_ago) {
                    unset($invitations[$code]);
                    continue;
                }
            }
            
            // Add parent details for accepted invitations
            if (!empty($invite['used_by'])) {
                $parent = get_userdata($invite['used_by']);
                if ($parent) {
                    $invitations[$code]['parent_name'] = $parent->display_name;
                    $invitations[$code]['parent_email'] = $parent->user_email;
                }
            }
        }
        
        // Update user meta to remove expired revoked invitations
        update_user_meta($member_id, 'ftt_invitations', $invitations);
        
        return $invitations;
    }
    
    /**
     * Process invitation on registration
     * Check if a pending parent's email matches this new member
     *
     * @param int $member_id Newly registered member ID
     */
    public static function process_pending_parent_links($member_id) {
        $member_email = get_userdata($member_id)->user_email;
        
        // Find parents waiting to link to this email
        $users = get_users(array(
            'meta_key' => 'ftt_pending_child_email',
            'meta_value' => $member_email
        ));
        
        foreach ($users as $parent) {
            // Link them via groups (add to parent's primary group)
            $primary_group = FTT_Family_Groups::get_primary_group($parent->ID);
            if ($primary_group) {
                FTT_Family_Groups::add_member($primary_group->id, $member_id, 'child', [
                    'added_by' => $parent->ID,
                    'relationship' => 'child'
                ]);
            }
            
            // Clear pending status
            delete_user_meta($parent->ID, 'ftt_pending_child_email');
            
            // Send notification
            $subject = FTT_Email_Templates::render_subject('parent_linked', [
                'site_name' => get_bloginfo('name'),
            ]);
            $message = FTT_Email_Templates::render_body('parent_linked', [
                'site_name'  => get_bloginfo('name'),
                'child_name' => get_userdata($member_id)->display_name,
            ]);
            wp_mail($parent->user_email, $subject, $message);
        }
    }
    
    /**
     * REST: Generate new invite code
     */
    public static function generate_invite_code($request) {
        $member_id = get_current_user_id();
        $invitation = self::create_invitation($member_id);
        
        return rest_ensure_response(array(
            'success' => true,
            'invitation' => $invitation
        ));
    }
    
    /**
     * REST: Get invitations for current user
     */
    public static function get_invitations($request) {
        $user_id = get_current_user_id();
        $is_member = FTT_Roles::is_member($user_id);
        
        if ($is_member) {
            // Return member's sent invitations
            return rest_ensure_response(array(
                'success' => true,
                'invitations' => self::get_member_invitations($user_id),
                'member_code' => self::get_member_code($user_id)
            ));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'invitations' => array(),
            'member_code' => null
        ));
    }
    
    /**
     * REST: Revoke invitation
     */
    public static function revoke_invitation($request) {
        $code = $request['code'];
        $current_user_id = get_current_user_id();
        
        // First check if the invitation belongs to the current user
        $invitations = get_user_meta($current_user_id, 'ftt_invitations', true);
        
        if (isset($invitations[$code]) && $invitations[$code]['member_id'] == $current_user_id) {
            // User is revoking their own invitation
            $invitations[$code]['status'] = 'revoked';
            update_user_meta($current_user_id, 'ftt_invitations', $invitations);
            
            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Invitation revoked'
            ));
        }
        
        // If not found, check if current user is a parent of the member who created it
        if (FTT_Family_Groups::is_parent($current_user_id)) {
            $children = FTT_Family_Groups::get_user_children($current_user_id);
            
            foreach ($children as $child_id) {
                $child_invitations = get_user_meta($child_id, 'ftt_invitations', true);
                
                if (isset($child_invitations[$code]) && $child_invitations[$code]['member_id'] == $child_id) {
                    // Parent is revoking their child's invitation
                    $child_invitations[$code]['status'] = 'revoked';
                    update_user_meta($child_id, 'ftt_invitations', $child_invitations);
                    
                    return rest_ensure_response(array(
                        'success' => true,
                        'message' => 'Invitation revoked'
                    ));
                }
            }
        }
        
        return new WP_Error('invalid_code', 'Invalid invitation code or insufficient permissions', array('status' => 403));
    }
    
    /**
     * REST: Accept invitation by code (for already registered parents)
     */
    public static function accept_invitation_by_code($request) {
        $parent_id = get_current_user_id();
        $code = sanitize_text_field($request->get_param('code'));
        
        // Check if it's a member code (permanent) or invite code (single-use)
        if (strpos($code, 'M-') === 0) {
            // Permanent member code
            $member_id = self::get_member_by_code($code);
            
            if (!$member_id) {
                return new WP_Error('invalid_code', 'Invalid member code', array('status' => 404));
            }
            
            // Check if already linked via groups
            $children = FTT_Family_Groups::get_user_children($parent_id);
            if (in_array($member_id, $children)) {
                return new WP_Error('already_linked', 'You are already linked to this member', array('status' => 400));
            }
            
            // Link them via groups (add to parent's primary group)
            $primary_group = FTT_Family_Groups::get_primary_group($parent_id);
            if ($primary_group) {
                FTT_Family_Groups::add_member($primary_group->id, $member_id, 'child', [
                    'added_by' => $parent_id,
                    'relationship' => 'child'
                ]);
            } else {
                return new WP_Error('no_group', 'Parent has no group to add member to', array('status' => 500));
            }
            
            $member = get_userdata($member_id);
            
            return rest_ensure_response(array(
                'success' => true,
                'message' => sprintf('Successfully linked to %s', $member->display_name),
                'member' => array(
                    'id' => $member_id,
                    'name' => $member->display_name
                )
            ));
            
        } elseif (strpos($code, 'INV-') === 0) {
            // Single-use invitation code
            $invitation = self::get_invitation_by_code($code);
            
            if (!$invitation) {
                return new WP_Error('invalid_code', 'Invalid invitation code', array('status' => 404));
            }
            
            if ($invitation['status'] !== 'pending') {
                return new WP_Error('code_used', 'This invitation has already been used or revoked', array('status' => 400));
            }
            
            $member_id = $invitation['member_id'];
            
            // Check if already linked via groups
            $children = FTT_Family_Groups::get_user_children($parent_id);
            if (in_array($member_id, $children)) {
                return new WP_Error('already_linked', 'You are already linked to this member', array('status' => 400));
            }
            
            // Link them via groups (add to parent's primary group)
            $primary_group = FTT_Family_Groups::get_primary_group($parent_id);
            if ($primary_group) {
                FTT_Family_Groups::add_member($primary_group->id, $member_id, 'child', [
                    'added_by' => $parent_id,
                    'relationship' => 'child'
                ]);
            } else {
                return new WP_Error('no_group', 'Parent has no group to add member to', array('status' => 500));
            }
            
            // Update invitation status
            self::update_invitation_status($code, 'accepted', $parent_id);
            
            $member = get_userdata($member_id);
            
            return rest_ensure_response(array(
                'success' => true,
                'message' => sprintf('Successfully linked to %s', $member->display_name),
                'member' => array(
                    'id' => $member_id,
                    'name' => $member->display_name
                )
            ));
        }
        
        return new WP_Error('invalid_format', 'Invalid code format', array('status' => 400));
    }
    
    /**
     * REST: Validate invite code (public endpoint for registration)
     */
    public static function validate_invite_code($request) {
        $code = $request['code'];
        
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('FTT Invitation validation: Code received - ' . $code);
        }
        
        try {
            // Validate code format before processing
            if (!preg_match('/^(M-[A-Z0-9]{6,}|INV-[A-Z0-9]{8,}|[a-zA-Z0-9]{12})$/', $code)) {
                return rest_ensure_response(array(
                    'valid' => false,
                    'error' => 'invalid_format',
                    'message' => 'Invalid invitation code format'
                ));
            }
            
            // Check member codes
            if (strpos($code, 'M-') === 0) {
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('FTT Invitation validation: Checking member code');
                }
                $member_id = self::get_member_by_code($code);
                if ($member_id) {
                    $member = get_userdata($member_id);
                    return rest_ensure_response(array(
                        'valid' => true,
                        'type' => 'member_code',
                        'member' => array(
                            'id' => $member_id,
                            'name' => $member->display_name
                        )
                    ));
                }
            }
            
            // Check invitation codes
            if (strpos($code, 'INV-') === 0) {
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('FTT Invitation validation: Checking invitation code');
                }
                $invitation = self::get_invitation_by_code($code);
                if ($invitation && $invitation['status'] === 'pending') {
                    $member = get_userdata($invitation['member_id']);
                    return rest_ensure_response(array(
                        'valid' => true,
                        'type' => 'invitation',
                        'member' => array(
                            'id' => $invitation['member_id'],
                            'name' => $member->display_name
                        )
                    ));
                }
            }
            
            // Check adult invitation codes (random alphanumeric)
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT Invitation validation: Checking adult invitation code');
            }
            // Search all users for this invite code
            $users = get_users(array('meta_key' => 'ftt_adult_invitations'));
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT Invitation validation: Found ' . count($users) . ' users with adult invitations');
            }
            
            foreach ($users as $user) {
                $invitations = get_user_meta($user->ID, 'ftt_adult_invitations', true);
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('FTT Invitation validation: User ' . $user->ID . ' has ' . count($invitations) . ' invitations');
                }
                
                if (is_array($invitations) && isset($invitations[$code])) {
                    $invitation = $invitations[$code];
                    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                        error_log('FTT Invitation validation: Found matching invitation for user ' . $user->ID);
                    }
                    
                    // Check if expired
                    if (isset($invitation['expires']) && $invitation['expires'] < time()) {
                        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                            error_log('FTT Invitation validation: Invitation expired');
                        }
                        return rest_ensure_response(array(
                            'valid' => false,
                            'error' => 'expired',
                            'message' => 'This invitation has expired'
                        ));
                    }
                    
                    // Check if already used (default to pending if not set)
                    $status = isset($invitation['status']) ? $invitation['status'] : 'pending';
                    if ($status !== 'pending') {
                        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                            error_log('FTT Invitation validation: Invitation already used, status: ' . $status);
                        }
                        return rest_ensure_response(array(
                            'valid' => false,
                            'error' => 'used',
                            'message' => 'This invitation has already been used'
                        ));
                    }
                    
                    // Get inviter details
                    $inviter = get_userdata($user->ID);
                    
                    // Get billing status - check if invitation has group_id
                    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                        error_log('FTT Invitation validation: Getting billing info');
                    }
                    $group_id = isset($invitation['group_id']) ? $invitation['group_id'] : 0;
                    $billing_info = self::get_billing_info($user->ID, $group_id);
                    
                    // Get linked adults (co-parents)
                    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                        error_log('FTT Invitation validation: Getting linked adults');
                    }
                    $linked_adults = self::get_linked_adults($user->ID);
                    
                    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                        error_log('FTT Invitation validation: Returning valid adult invitation');
                    }
                    return rest_ensure_response(array(
                        'valid' => true,
                        'type' => 'adult_invitation',
                        'inviter' => array(
                            'name' => $inviter->display_name,
                            'email' => $inviter->user_email,
                        ),
                        'invitee_email' => isset($invitation['email']) ? $invitation['email'] : '',
                        'relationship' => isset($invitation['relationship']) ? $invitation['relationship'] : 'co-parent',
                        'expires' => date_i18n(get_option('date_format'), $invitation['expires']),
                        'billing' => $billing_info,
                        'linked_adults' => $linked_adults,
                    ));
                }
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT Invitation validation: No valid invitation found');
            }

            // Final fallback: check ftt_group_invitations DB table (group invitations)
            if (class_exists('FTT_Family_Groups')) {
                global $wpdb;
                $inv_table = $wpdb->prefix . 'ftt_group_invitations';
                $db_invite = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$inv_table} WHERE invite_code = %s AND status = 'pending' AND expires_at > %s",
                    $code,
                    current_time('mysql')
                ));

                if ($db_invite) {
                    $inviter = get_userdata($db_invite->invited_by);
                    $group   = FTT_Family_Groups::get_group((int) $db_invite->group_id);
                    return rest_ensure_response(array(
                        'valid'         => true,
                        'type'          => 'adult_invitation',
                        'inviter'       => array(
                            'name'  => $inviter ? $inviter->display_name : '',
                            'email' => $inviter ? $inviter->user_email : '',
                        ),
                        'invitee_email' => $db_invite->email,
                        'relationship'  => $db_invite->relationship ?: 'co-parent',
                        'expires'       => date_i18n(get_option('date_format'), strtotime($db_invite->expires_at)),
                        'group_name'    => $group ? $group->name : '',
                        'billing'       => array('has_billing' => true, 'invited_user' => true),
                        'linked_adults' => array(),
                    ));
                }
            }

            return rest_ensure_response(array(
                'valid' => false,
                'error' => 'not_found',
                'message' => 'Invalid invitation code'
            ));
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT Invitation validation: Exception - ' . $e->getMessage());
                error_log('FTT Invitation validation: Stack trace - ' . $e->getTraceAsString());
            }
            return new WP_Error('validation_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Get billing information for a user or group
     */
    private static function get_billing_info($user_id, $group_id = 0) {
        global $wpdb;
        
        try {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT get_billing_info: Starting for user ' . $user_id . ', group ' . $group_id);
            }
            
            // If group_id is provided, get billing info from the group
            if ($group_id > 0) {
                $table_name = $wpdb->prefix . 'ftt_groups';
                $group = $wpdb->get_row($wpdb->prepare(
                    "SELECT subscription_status, billing_interval, trial_ends_at FROM $table_name WHERE id = %d",
                    $group_id
                ));
                
                if ($group && !empty($group->subscription_status)) {
                    $status = $group->subscription_status;
                    $interval = $group->billing_interval ?: 'monthly';
                    
                    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                        error_log('FTT get_billing_info: Group Status=' . $status . ', Interval=' . $interval);
                    }
                    
                    $status_labels = array(
                        'active' => 'Active',
                        'trialing' => 'Free Trial',
                        'past_due' => 'Payment Past Due',
                        'canceled' => 'Canceled',
                        'unpaid' => 'Unpaid',
                    );
                    
                    $interval_labels = array(
                        'monthly' => 'Monthly',
                        'yearly' => 'Yearly',
                    );
                    
                    $status_text = isset($status_labels[$status]) ? $status_labels[$status] : ucfirst($status);
                    $interval_text = isset($interval_labels[$interval]) ? $interval_labels[$interval] : ucfirst($interval);
                    
                    return array(
                        'status' => $status,
                        'status_label' => $status_text,
                        'interval' => $interval,
                        'interval_label' => $interval_text,
                        'message' => $status_text . ' (' . $interval_text . ')'
                    );
                }
            }
            
            // Fallback to legacy user-based billing
            $status = get_user_meta($user_id, 'ftt_subscription_status', true);
            $interval = get_user_meta($user_id, 'ftt_subscription_interval', true);
            
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT get_billing_info: Status=' . ($status ?: 'EMPTY') . ', Interval=' . ($interval ?: 'EMPTY'));
            }
            
            // If no subscription data, assume trial or no billing required
            if (empty($status)) {
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('FTT get_billing_info: No subscription found, returning trial/free');
                }
                return array(
                    'status' => 'trialing',
                    'interval' => 'monthly',
                    'message' => 'Free Trial'
                );
            }
            
            $status_labels = array(
                'active' => 'Active',
                'trialing' => 'Free Trial',
                'past_due' => 'Payment Past Due',
                'canceled' => 'Canceled',
                'unpaid' => 'Unpaid',
            );
            
            $interval_labels = array(
                'month' => 'Monthly',
                'year' => 'Yearly',
            );
            
            $interval = $interval ?: 'month';
            
            $status_text = strtolower(isset($status_labels[$status]) ? $status_labels[$status] : 'active');
            $interval_text = strtolower(isset($interval_labels[$interval]) ? $interval_labels[$interval] : 'monthly');
            
            // Use correct article (a/an) based on first letter
            $article = (in_array($status_text[0], ['a', 'e', 'i', 'o', 'u'])) ? 'an' : 'a';
            
            $result = array(
                'status' => $status,
                'status_label' => isset($status_labels[$status]) ? $status_labels[$status] : 'Unknown',
                'interval' => $interval,
                'interval_label' => isset($interval_labels[$interval]) ? $interval_labels[$interval] : 'Monthly',
                'message' => sprintf(
                    'This group has %s %s subscription billed %s',
                    $article,
                    $status_text,
                    $interval_text
                )
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT get_billing_info: Returning - ' . print_r($result, true));
            }
            return $result;
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT get_billing_info: Exception - ' . $e->getMessage());
            }
            return array(
                'status' => 'unknown',
                'interval' => 'unknown',
                'message' => 'Join this family calendar group'
            );
        }
    }
    
    /**
     * Get linked adults (co-parents) for a user
     */
    private static function get_linked_adults($user_id) {
        try {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT get_linked_adults: Starting for user ' . $user_id);
            }
            
            $parents = get_user_meta($user_id, 'ftt_parents', true);
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT get_linked_adults: ftt_parents meta - ' . print_r($parents, true));
            }
            
            if (!is_array($parents) || empty($parents)) {
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('FTT get_linked_adults: No parents found, returning empty');
                }
                return array();
            }
            
            $linked = array();
            foreach ($parents as $parent_id) {
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('FTT get_linked_adults: Processing parent_id ' . $parent_id);
                }
                $parent = get_userdata($parent_id);
                if ($parent) {
                    $relationship = get_user_meta($user_id, 'relationship_to_' . $parent_id, true);
                    $linked[] = array(
                        'name' => $parent->display_name,
                        'relationship' => $relationship ?: 'Co-parent',
                    );
                    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                        error_log('FTT get_linked_adults: Added parent - ' . $parent->display_name);
                    }
                }
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT get_linked_adults: Returning ' . count($linked) . ' linked adults');
            }
            return $linked;
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT get_linked_adults: Exception - ' . $e->getMessage());
            }
            return array();
        }
    }
}

// Initialize
FTT_Invitations::init();
