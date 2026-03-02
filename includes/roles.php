<?php
/**
 * User Roles Management
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SRT User Management Class
 * 
 * Manages children and parent relationships using user meta
 * instead of custom roles to allow flexibility (admin can also be parent, etc.)
 */
class FTT_Roles {
    
    /**
     * Initialize
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('show_user_profile', array(__CLASS__, 'show_user_profile_fields'));
        add_action('edit_user_profile', array(__CLASS__, 'show_user_profile_fields'));
        add_action('personal_options_update', array(__CLASS__, 'save_user_profile_fields'));
        add_action('edit_user_profile_update', array(__CLASS__, 'save_user_profile_fields'));
        
        // Add custom columns to admin users list
        add_filter('manage_users_columns', array(__CLASS__, 'add_user_columns'));
        add_filter('manage_users_custom_column', array(__CLASS__, 'populate_user_columns'), 10, 3);
    }
    
    /**
     * Make user a child/student in the system
     */
    public static function make_member($user_id) {
        update_user_meta($user_id, 'ftt_is_member', true);
        update_user_meta($user_id, 'srt_member_since', current_time('mysql'));
        
        // Grant event editing capabilities
        $user = get_user_by('id', $user_id);
        if ($user) {
            $user->add_cap('read');
            $user->add_cap('edit_posts');
            $user->add_cap('edit_published_posts');
            $user->add_cap('publish_posts');
            $user->add_cap('delete_posts');
            $user->add_cap('delete_published_posts');
            $user->add_cap('upload_files');
        }
    }
    
    /**
     * Remove member status
     */
    public static function remove_member($user_id) {
        delete_user_meta($user_id, 'ftt_is_member');
        
        // Don't remove capabilities - user might be admin or have other reasons for them
    }
    
    /**
     * Check if user is a member
     */
    public static function is_member($user_id) {
        return (bool) get_user_meta($user_id, 'ftt_is_member', true);
    }
    
    /**
     * Add parent relationship
     */
    public static function add_parent_child($parent_id, $child_id) {
        $children = self::get_children($parent_id);
        $is_new_child = !in_array($child_id, $children);
        
        if ($is_new_child) {
            $children[] = $child_id;
            update_user_meta($parent_id, 'ftt_parent_of', $children);
        }
        
        // Also store reverse relationship
        $parents = self::get_parents($child_id);
        if (!in_array($parent_id, $parents)) {
            $parents[] = $parent_id;
            update_user_meta($child_id, 'srt_parents', $parents);
        }
        
        // Auto-assign color to child when first added to a parent
        if ($is_new_child && class_exists('FTT_Child_Colors')) {
            FTT_Child_Colors::assign_color($child_id, $parent_id);
        }
    }
    
    /**
     * Remove parent relationship
     */
    public static function remove_parent_child($parent_id, $child_id) {
        $children = self::get_children($parent_id);
        $children = array_diff($children, array($child_id));
        update_user_meta($parent_id, 'ftt_parent_of', $children);
        
        // Remove reverse relationship
        $parents = self::get_parents($child_id);
        $parents = array_diff($parents, array($parent_id));
        update_user_meta($child_id, 'srt_parents', $parents);
    }
    
    /**
     * Get children for a parent user
     */
    public static function get_children($parent_id) {
        $children = get_user_meta($parent_id, 'ftt_parent_of', true);
        return is_array($children) ? $children : array();
    }
    
    /**
     * Get parents for a user
     */
    public static function get_parents($user_id) {
        $parents = get_user_meta($user_id, 'srt_parents', true);
        return is_array($parents) ? $parents : array();
    }
    
    /**
     * Check if user is a parent
     * Returns true if user registered as parent OR has children linked
     */
    public static function is_parent($user_id) {
        // Check if registered as parent
        $user_type = get_user_meta($user_id, 'user_type', true);
        if ($user_type === 'parent') {
            return true;
        }
        
        // Also check if they have children linked (legacy check)
        $children = self::get_children($user_id);
        return !empty($children);
    }
    
    /**
     * Get all members
     */
    public static function get_all_members() {
        $args = array(
            'meta_key'   => 'ftt_is_member',
            'meta_value' => '1',
            'orderby'    => 'display_name',
            'order'      => 'ASC',
        );
        return get_users($args);
    }
    
    /**
     * Get all parents
     */
    public static function get_all_parents() {
        $args = array(
            'meta_key'     => 'ftt_parent_of',
            'meta_compare' => 'EXISTS',
            'orderby'      => 'display_name',
            'order'        => 'ASC',
        );
        return get_users($args);
    }
    
    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=ftt_event',
            __('Manage Users', 'schedule-collaboration-tracking'),
            __('Manage Users', 'schedule-collaboration-tracking'),
            'manage_options',
            'ftt-manage-users',
            array(__CLASS__, 'render_admin_page')
        );
    }
    
    /**
     * Render admin page
     */
    public static function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle form submissions
        if (isset($_POST['ftt_action']) && check_admin_referer('srt_manage_users')) {
            self::handle_admin_actions();
        }
        
        include FTT_PLUGIN_DIR . 'templates/admin-manage-users.php';
    }
    
    /**
     * Handle admin actions
     */
    private static function handle_admin_actions() {
        $action = sanitize_text_field($_POST['ftt_action']);
        
        switch ($action) {
            case 'make_member':
                $user_id = intval($_POST['user_id']);
                self::make_member($user_id);
                add_settings_error('ftt_messages', 'success', __('User marked as member.', 'schedule-collaboration-tracking'), 'updated');
                break;
                
            case 'remove_member':
                $user_id = intval($_POST['user_id']);
                self::remove_member($user_id);
                add_settings_error('ftt_messages', 'success', __('Member status removed.', 'schedule-collaboration-tracking'), 'updated');
                break;
                
            case 'add_parent':
                $parent_id = intval($_POST['parent_id']);
                $child_id = intval($_POST['child_id']);
                self::add_parent_child($parent_id, $child_id);
                add_settings_error('ftt_messages', 'success', __('Parent relationship added.', 'schedule-collaboration-tracking'), 'updated');
                break;
                
            case 'remove_parent':
                $parent_id = intval($_POST['parent_id']);
                $child_id = intval($_POST['child_id']);
                self::remove_parent_child($parent_id, $child_id);
                add_settings_error('ftt_messages', 'success', __('Parent relationship removed.', 'schedule-collaboration-tracking'), 'updated');
                break;
        }
    }
    
    /**
     * Show fields on user profile
     */
    public static function show_user_profile_fields($user) {
        if (!current_user_can('edit_users')) {
            return;
        }
        ?>
        <h2><?php esc_html_e('Child/Student Information', 'schedule-collaboration-tracking'); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="ftt_is_member"><?php esc_html_e('Member', 'schedule-collaboration-tracking'); ?></label></th>
                <td>
                    <input type="checkbox" name="ftt_is_member" id="ftt_is_member" value="1" <?php checked(self::is_member($user->ID)); ?>>
                    <p class="description"><?php esc_html_e('Check if this user is an active member.', 'schedule-collaboration-tracking'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label><?php esc_html_e('Parent/Guardian Of', 'schedule-collaboration-tracking'); ?></label></th>
                <td>
                    <?php
                    $children = self::get_children($user->ID);
                    $all_users = get_users(array('orderby' => 'display_name'));
                    ?>
                    <select name="srt_parent_of[]" multiple style="height: 150px; width: 100%;">
                        <?php foreach ($all_users as $u) : ?>
                            <?php if ($u->ID !== $user->ID) : ?>
                                <option value="<?php echo esc_attr($u->ID); ?>" <?php selected(in_array($u->ID, $children)); ?>>
                                    <?php echo esc_html($u->display_name . ' (' . $u->user_email . ')'); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Hold Ctrl (Cmd on Mac) to select multiple. This user will receive price alerts for selected members.', 'schedule-collaboration-tracking'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th><label for="ftt_home_airport"><?php esc_html_e('Home Airport', 'schedule-collaboration-tracking'); ?></label></th>
                <td>
                    <input type="text" name="ftt_home_airport" id="ftt_home_airport" value="<?php echo esc_attr(get_user_meta($user->ID, 'ftt_home_airport', true)); ?>" class="regular-text" maxlength="3" placeholder="ORD" style="text-transform: uppercase;">
                    <p class="description"><?php esc_html_e('Your primary airport (IATA code, e.g., ORD, JFK, LAX). Used as default for event forms.', 'schedule-collaboration-tracking'); ?></p>
                </td>
            </tr>
        </table>
        
        <h2><?php esc_html_e('Subscription Access', 'schedule-collaboration-tracking'); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="ftt_access_denied"><?php esc_html_e('Deny Access', 'schedule-collaboration-tracking'); ?></label></th>
                <td>
                    <input type="checkbox" name="ftt_access_denied" id="ftt_access_denied" value="1" <?php checked(get_user_meta($user->ID, 'ftt_access_denied', true)); ?>>
                    <p class="description"><?php esc_html_e('Check to manually deny site access regardless of subscription status. User will be redirected to pricing page.', 'schedule-collaboration-tracking'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save user profile fields
     */
    public static function save_user_profile_fields($user_id) {
        if (!current_user_can('edit_users')) {
            return;
        }
        
        // Home airport
        if (isset($_POST['ftt_home_airport'])) {
            $airport = strtoupper(sanitize_text_field($_POST['ftt_home_airport']));
            update_user_meta($user_id, 'ftt_home_airport', $airport);
        }
        
        // Access denial override
        if (isset($_POST['ftt_access_denied'])) {
            update_user_meta($user_id, 'ftt_access_denied', true);
            
            // Invalidate calendar token when access is denied
            if (class_exists('FTT_Billing_Manager')) {
                FTT_Billing_Manager::invalidate_calendar_access($user_id);
            }
        } else {
            delete_user_meta($user_id, 'ftt_access_denied');
        }
        
        // Member status
        if (isset($_POST['ftt_is_member'])) {
            self::make_member($user_id);
        } else {
            self::remove_member($user_id);
        }
        
        // Parent relationships
        $new_children = isset($_POST['ftt_parent_of']) ? array_map('intval', $_POST['ftt_parent_of']) : array();
        $old_children = self::get_children($user_id);
        
        // Add new relationships
        foreach ($new_children as $child_id) {
            if (!in_array($child_id, $old_children)) {
                self::add_parent_child($user_id, $child_id);
            }
        }
        
        // Remove old relationships
        foreach ($old_children as $child_id) {
            if (!in_array($child_id, $new_children)) {
                self::remove_parent_child($user_id, $child_id);
            }
        }
    }
    
    /**
     * Get all user IDs that a parent should see events for (children + self if member)
     *
     * @param int $user_id User ID
     * @return array Array of user IDs
     */
    public static function get_viewable_user_ids($user_id) {
        $user_ids = array();
        
        // If they're a member, include themselves
        if (self::is_member($user_id)) {
            $user_ids[] = $user_id;
        }
        
        // If they're a parent, include all children
        if (self::is_parent($user_id)) {
            $children = self::get_children($user_id);
            $user_ids = array_merge($user_ids, $children);
        }
        
        return array_unique($user_ids);
    }
    
    /**
     * Get all user IDs who should be notified about a member's events
     * (the member + all their parents)
     *
     * @param int $member_id Member user ID
     * @return array Array of user IDs
     */
    public static function get_notification_recipients($member_id) {
        $recipients = array($member_id);
        
        // Add all parents
        $parents = self::get_parents($member_id);
        $recipients = array_merge($recipients, $parents);
        
        return array_unique($recipients);
    }
    
    /**
     * Get user's home airport
     *
     * @param int $user_id User ID (defaults to current user)
     * @return string Airport code (e.g., 'ORD') or empty string
     */
    public static function get_user_airport($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return get_user_meta($user_id, 'ftt_home_airport', true);
    }
    
    /**
     * Set user's home airport
     *
     * @param int $user_id User ID
     * @param string $airport_code IATA airport code
     * @return bool Success
     */
    public static function set_user_airport($user_id, $airport_code) {
        $airport_code = strtoupper(sanitize_text_field($airport_code));
        
        // Basic validation - 3 letters
        if (strlen($airport_code) !== 3 || !ctype_alpha($airport_code)) {
            return false;
        }
        
        return update_user_meta($user_id, 'ftt_home_airport', $airport_code);
    }
    
    /**
     * Add custom columns to users list
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public static function add_user_columns($columns) {
        $columns['linked_adults'] = __('Linked Adults', 'schedule-collaboration-tracking');
        $columns['linked_children'] = __('Linked Children', 'schedule-collaboration-tracking');
        $columns['billing_status'] = __('Billing Status', 'schedule-collaboration-tracking');
        $columns['next_billing'] = __('Next Billing', 'schedule-collaboration-tracking');
        return $columns;
    }
    
    /**
     * Populate custom user columns
     *
     * @param string $output Custom column output
     * @param string $column_name Column identifier
     * @param int $user_id User ID
     * @return string Column content
     */
    public static function populate_user_columns($output, $column_name, $user_id) {
        switch ($column_name) {
            case 'linked_adults':
                $parents = self::get_parents($user_id);
                if (!empty($parents)) {
                    $names = array();
                    foreach ($parents as $parent_id) {
                        $parent = get_userdata($parent_id);
                        if ($parent) {
                            $names[] = esc_html($parent->display_name);
                        }
                    }
                    $output = implode(', ', $names);
                } else {
                    $output = '—';
                }
                break;
                
            case 'linked_children':
                $children = self::get_children($user_id);
                if (!empty($children)) {
                    $names = array();
                    foreach ($children as $child_id) {
                        $child = get_userdata($child_id);
                        if ($child) {
                            $names[] = esc_html($child->display_name);
                        }
                    }
                    $output = implode(', ', $names);
                } else {
                    $output = '—';
                }
                break;
                
            case 'billing_status':
                if (class_exists('FTT_Billing_Manager')) {
                    $billing = FTT_Billing_Manager::get_billing_summary($user_id);
                    if ($billing && !empty($billing['status'])) {
                        $status_class = 'ftt-status-' . esc_attr($billing['status']);
                        $status_label = esc_html($billing['status_label']);
                        $output = '<span class="' . $status_class . '">' . $status_label . '</span>';
                        
                        if ($billing['in_trial']) {
                            $output .= '<br><small>' . sprintf(__('Trial (%d days left)', 'schedule-collaboration-tracking'), $billing['days_until_charge']) . '</small>';
                        }
                    } else {
                        $output = '—';
                    }
                } else {
                    $output = '—';
                }
                break;
                
            case 'next_billing':
                if (class_exists('FTT_Billing_Manager')) {
                    $billing = FTT_Billing_Manager::get_billing_summary($user_id);
                    if ($billing && !empty($billing['next_billing_date'])) {
                        if ($billing['in_trial']) {
                            $output = '<span style="color: #2196F3;">' . esc_html($billing['next_billing_date']) . '</span>';
                            $output .= '<br><small>' . __('(First charge)', 'schedule-collaboration-tracking') . '</small>';
                        } else {
                            $output = esc_html($billing['next_billing_date']);
                        }
                        
                        // Show amount
                        if (!empty($billing['total_price'])) {
                            $output .= '<br><small>' . esc_html($billing['total_price']) . '</small>';
                        }
                    } else {
                        $output = '—';
                    }
                } else {
                    $output = '—';
                }
                break;
        }
        
        return $output;
    }
}


// Initialize
FTT_Roles::init();
