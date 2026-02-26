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
class SRT_Roles {
    
    /**
     * Initialize
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('show_user_profile', array(__CLASS__, 'show_user_profile_fields'));
        add_action('edit_user_profile', array(__CLASS__, 'show_user_profile_fields'));
        add_action('personal_options_update', array(__CLASS__, 'save_user_profile_fields'));
        add_action('edit_user_profile_update', array(__CLASS__, 'save_user_profile_fields'));
    }
    
    /**
     * Make user a child/student in the system
     */
    public static function make_member($user_id) {
        update_user_meta($user_id, 'srt_is_member', true);
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
        delete_user_meta($user_id, 'srt_is_member');
        
        // Don't remove capabilities - user might be admin or have other reasons for them
    }
    
    /**
     * Check if user is a member
     */
    public static function is_member($user_id) {
        return (bool) get_user_meta($user_id, 'srt_is_member', true);
    }
    
    /**
     * Add parent relationship
     */
    public static function add_parent_child($parent_id, $child_id) {
        $children = self::get_children($parent_id);
        $is_new_child = !in_array($child_id, $children);
        
        if ($is_new_child) {
            $children[] = $child_id;
            update_user_meta($parent_id, 'srt_parent_of', $children);
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
        update_user_meta($parent_id, 'srt_parent_of', $children);
        
        // Remove reverse relationship
        $parents = self::get_parents($child_id);
        $parents = array_diff($parents, array($parent_id));
        update_user_meta($child_id, 'srt_parents', $parents);
    }
    
    /**
     * Get children for a parent user
     */
    public static function get_children($parent_id) {
        $children = get_user_meta($parent_id, 'srt_parent_of', true);
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
     */
    public static function is_parent($user_id) {
        $children = self::get_children($user_id);
        return !empty($children);
    }
    
    /**
     * Get all members
     */
    public static function get_all_members() {
        $args = array(
            'meta_key'   => 'srt_is_member',
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
            'meta_key'     => 'srt_parent_of',
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
            'edit.php?post_type=srt_event',
            __('Manage Users', 'schedule-collaboration-tracking'),
            __('Manage Users', 'schedule-collaboration-tracking'),
            'manage_options',
            'srt-manage-users',
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
        if (isset($_POST['srt_action']) && check_admin_referer('srt_manage_users')) {
            self::handle_admin_actions();
        }
        
        include SRT_PLUGIN_DIR . 'templates/admin-manage-users.php';
    }
    
    /**
     * Handle admin actions
     */
    private static function handle_admin_actions() {
        $action = sanitize_text_field($_POST['srt_action']);
        
        switch ($action) {
            case 'make_member':
                $user_id = intval($_POST['user_id']);
                self::make_member($user_id);
                add_settings_error('srt_messages', 'success', __('User marked as member.', 'schedule-collaboration-tracking'), 'updated');
                break;
                
            case 'remove_member':
                $user_id = intval($_POST['user_id']);
                self::remove_member($user_id);
                add_settings_error('srt_messages', 'success', __('Member status removed.', 'schedule-collaboration-tracking'), 'updated');
                break;
                
            case 'add_parent':
                $parent_id = intval($_POST['parent_id']);
                $child_id = intval($_POST['child_id']);
                self::add_parent_child($parent_id, $child_id);
                add_settings_error('srt_messages', 'success', __('Parent relationship added.', 'schedule-collaboration-tracking'), 'updated');
                break;
                
            case 'remove_parent':
                $parent_id = intval($_POST['parent_id']);
                $child_id = intval($_POST['child_id']);
                self::remove_parent_child($parent_id, $child_id);
                add_settings_error('srt_messages', 'success', __('Parent relationship removed.', 'schedule-collaboration-tracking'), 'updated');
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
                <th><label for="srt_is_member"><?php esc_html_e('Member', 'schedule-collaboration-tracking'); ?></label></th>
                <td>
                    <input type="checkbox" name="srt_is_member" id="srt_is_member" value="1" <?php checked(self::is_member($user->ID)); ?>>
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
        
        // Member status
        if (isset($_POST['srt_is_member'])) {
            self::make_member($user_id);
        } else {
            self::remove_member($user_id);
        }
        
        // Parent relationships
        $new_children = isset($_POST['srt_parent_of']) ? array_map('intval', $_POST['srt_parent_of']) : array();
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
}

// Initialize
SRT_Roles::init();
