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
class SRT_Invitations {
    
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
        register_rest_route('srt/v1', '/invite/generate', array(
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
                
                if (!class_exists('SRT_Roles')) {
                    error_log('Invite generate: SRT_Roles class not found');
                    return false;
                }
                
                $is_member = SRT_Roles::is_member($user_id);
                
                // If not marked as member, auto-promote if they have the right capability
                if (!$is_member && current_user_can('edit_posts')) {
                    error_log('Invite generate: User ' . $user_id . ' has edit_posts cap but not member flag, auto-promoting');
                    SRT_Roles::make_member($user_id);
                    $is_member = true;
                }
                
                error_log('Invite generate permission check - User ID: ' . $user_id . ', Is member: ' . ($is_member ? 'yes' : 'no'));
                
                return $is_member;
            }
        ));
        
        // Get member's invitations
        register_rest_route('srt/v1', '/invitations', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_invitations'),
            'permission_callback' => 'is_user_logged_in'
        ));
        
        // Revoke/delete invitation
        register_rest_route('srt/v1', '/invite/(?P<code>[a-zA-Z0-9\-]+)/revoke', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'revoke_invitation'),
            'permission_callback' => 'is_user_logged_in'
        ));
        
        // Parent accepts invitation by code
        register_rest_route('srt/v1', '/invite/accept', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'accept_invitation_by_code'),
            'permission_callback' => 'is_user_logged_in'
        ));
        
        // Validate invite code (for registration page)
        register_rest_route('srt/v1', '/invite/(?P<code>[a-zA-Z0-9\-]+)/validate', array(
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
        $code = get_user_meta($member_id, 'srt_member_code', true);
        
        if (empty($code)) {
            // Generate new permanent code
            $code = 'M-' . strtoupper(substr(md5($member_id . time()), 0, 6));
            update_user_meta($member_id, 'srt_member_code', $code);
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
            'meta_key' => 'srt_member_code',
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
        $invitations = get_user_meta($member_id, 'srt_invitations', true);
        if (!is_array($invitations)) {
            $invitations = array();
        }
        $invitations[$code] = $invitation;
        update_user_meta($member_id, 'srt_invitations', $invitations);
        
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
            'meta_key' => 'srt_invitations',
            'meta_compare' => 'EXISTS'
        ));
        
        foreach ($users as $user) {
            $invitations = get_user_meta($user->ID, 'srt_invitations', true);
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
            'meta_key' => 'srt_invitations',
            'meta_compare' => 'EXISTS'
        ));
        
        foreach ($users as $user) {
            $invitations = get_user_meta($user->ID, 'srt_invitations', true);
            if (isset($invitations[$code])) {
                $invitations[$code]['status'] = $status;
                
                if ($parent_id) {
                    $invitations[$code]['used_by'] = $parent_id;
                    $invitations[$code]['used_at'] = current_time('mysql');
                }
                
                update_user_meta($user->ID, 'srt_invitations', $invitations);
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
        $invitations = get_user_meta($member_id, 'srt_invitations', true);
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
        update_user_meta($member_id, 'srt_invitations', $invitations);
        
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
            'meta_key' => 'srt_pending_child_email',
            'meta_value' => $member_email
        ));
        
        foreach ($users as $parent) {
            // Link them
            SRT_Roles::add_parent_child($parent->ID, $member_id);
            
            // Clear pending status
            delete_user_meta($parent->ID, 'srt_pending_child_email');
            
            // Send notification
            $subject = sprintf('[%s] Parent Account Linked', get_bloginfo('name'));
            $message = sprintf(
                "Your parent account has been automatically linked to %s.\n\nYou can now view their events and receive price alerts.",
                get_userdata($member_id)->display_name
            );
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
        $is_member = SRT_Roles::is_member($user_id);
        
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
        $invitations = get_user_meta($current_user_id, 'srt_invitations', true);
        
        if (isset($invitations[$code]) && $invitations[$code]['member_id'] == $current_user_id) {
            // User is revoking their own invitation
            $invitations[$code]['status'] = 'revoked';
            update_user_meta($current_user_id, 'srt_invitations', $invitations);
            
            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Invitation revoked'
            ));
        }
        
        // If not found, check if current user is a parent of the member who created it
        if (SRT_Roles::is_parent($current_user_id)) {
            $children = SRT_Roles::get_children($current_user_id);
            
            foreach ($children as $child) {
                $child_invitations = get_user_meta($child->ID, 'srt_invitations', true);
                
                if (isset($child_invitations[$code]) && $child_invitations[$code]['member_id'] == $child->ID) {
                    // Parent is revoking their child's invitation
                    $child_invitations[$code]['status'] = 'revoked';
                    update_user_meta($child->ID, 'srt_invitations', $child_invitations);
                    
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
            
            // Check if already linked
            $children = SRT_Roles::get_children($parent_id);
            if (in_array($member_id, $children)) {
                return new WP_Error('already_linked', 'You are already linked to this member', array('status' => 400));
            }
            
            // Link them
            SRT_Roles::add_parent_child($parent_id, $member_id);
            
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
            
            // Check if already linked
            $children = SRT_Roles::get_children($parent_id);
            if (in_array($member_id, $children)) {
                return new WP_Error('already_linked', 'You are already linked to this member', array('status' => 400));
            }
            
            // Link them
            SRT_Roles::add_parent_child($parent_id, $member_id);
            
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
        
        // Check member codes
        if (strpos($code, 'M-') === 0) {
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
        
        return rest_ensure_response(array(
            'valid' => false
        ));
    }
}

// Initialize
SRT_Invitations::init();
