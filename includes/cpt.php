<?php
/**
 * Custom Post Type Registration
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FTT_CPT {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_type'));
        add_action('admin_init', array(__CLASS__, 'redirect_to_custom_form'));
        add_filter('post_row_actions', array(__CLASS__, 'modify_row_actions'), 10, 2);
        add_action('quick_edit_custom_box', array(__CLASS__, 'bulk_edit_custom_box'), 10, 2);
        add_action('bulk_edit_custom_box', array(__CLASS__, 'bulk_edit_custom_box'), 10, 2);
        add_action('save_post', array(__CLASS__, 'save_bulk_edit_data'));
        add_action('admin_footer', array(__CLASS__, 'bulk_edit_javascript'));
        add_action('admin_head-edit.php', array(__CLASS__, 'test_hook'));
    }
    
    /**
     * Test if this even runs
     */
    public static function test_hook() {
        global $current_screen;
        error_log('test_hook called on screen: ' . ($current_screen ? $current_screen->id : 'unknown'));
        if ($current_screen && $current_screen->id === 'edit-ftt_event') {
            error_log('We are on edit-ftt_event screen');
        }
    }
    
    /**
     * Register custom post type
     */
    public static function register_post_type() {
        $labels = array(
            'name'                  => _x('Events', 'Post type general name', 'schedule-collaboration-tracking'),
            'singular_name'         => _x('Event', 'Post type singular name', 'schedule-collaboration-tracking'),
            'menu_name'             => _x('Schedule Events', 'Admin Menu text', 'schedule-collaboration-tracking'),
            'name_admin_bar'        => _x('Event', 'Add New on Toolbar', 'schedule-collaboration-tracking'),
            'add_new'               => __('Add New', 'schedule-collaboration-tracking'),
            'add_new_item'          => __('Add New Event', 'schedule-collaboration-tracking'),
            'new_item'              => __('New Event', 'schedule-collaboration-tracking'),
            'edit_item'             => __('Edit Event', 'schedule-collaboration-tracking'),
            'view_item'             => __('View Event', 'schedule-collaboration-tracking'),
            'all_items'             => __('All Events', 'schedule-collaboration-tracking'),
            'search_items'          => __('Search Events', 'schedule-collaboration-tracking'),
            'parent_item_colon'     => __('Parent Events:', 'schedule-collaboration-tracking'),
            'not_found'             => __('No events found.', 'schedule-collaboration-tracking'),
            'not_found_in_trash'    => __('No events found in Trash.', 'schedule-collaboration-tracking'),
            'featured_image'        => _x('Event Image', 'Overrides the "Featured Image" phrase', 'schedule-collaboration-tracking'),
            'set_featured_image'    => _x('Set event image', 'Overrides the "Set featured image" phrase', 'schedule-collaboration-tracking'),
            'remove_featured_image' => _x('Remove event image', 'Overrides the "Remove featured image" phrase', 'schedule-collaboration-tracking'),
            'use_featured_image'    => _x('Use as event image', 'Overrides the "Use as featured image" phrase', 'schedule-collaboration-tracking'),
            'archives'              => _x('Event archives', 'The post type archive label', 'schedule-collaboration-tracking'),
            'insert_into_item'      => _x('Insert into event', 'Overrides the "Insert into post"/"Insert into page" phrase', 'schedule-collaboration-tracking'),
            'uploaded_to_this_item' => _x('Uploaded to this event', 'Overrides the "Uploaded to this post"/"Uploaded to this page" phrase', 'schedule-collaboration-tracking'),
            'filter_items_list'     => _x('Filter events list', 'Screen reader text for the filter links', 'schedule-collaboration-tracking'),
            'items_list_navigation' => _x('Events list navigation', 'Screen reader text for the pagination', 'schedule-collaboration-tracking'),
            'items_list'            => _x('Events list', 'Screen reader text for the items list', 'schedule-collaboration-tracking'),
        );
        
        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'regiment-event'),
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 20,
            'menu_icon'          => 'dashicons-calendar-alt',
            'supports'           => array('title', 'editor', 'thumbnail'),
            'show_in_rest'       => true,
        );
        
        register_post_type('ftt_event', $args);
    }
    
    /**
     * Get event types
     */
    public static function get_event_types() {
        $settings = get_option('ftt_settings', array());
        
        // Use custom event types if defined
        if (!empty($settings['event_types'])) {
            $types = array();
            foreach ($settings['event_types'] as $key => $type) {
                $types[$key] = $type['label'];
            }
            return $types;
        }
        
        // Otherwise return defaults
        return array(
            'move_in'          => __('Move In', 'schedule-collaboration-tracking'),
            'move_out'         => __('Move Out', 'schedule-collaboration-tracking'),
            'camp_weekend'     => __('Camp Weekend', 'schedule-collaboration-tracking'),
            'rehearsal_block'  => __('Rehearsal Block', 'schedule-collaboration-tracking'),
            'travel_day'       => __('Travel Day', 'schedule-collaboration-tracking'),
            'flight_only'      => __('Flight Only', 'schedule-collaboration-tracking'),
            'performance_day'  => __('Performance Day', 'schedule-collaboration-tracking'),
            'housing_checkin'  => __('Housing Check-In', 'schedule-collaboration-tracking'),
            'medical'          => __('Medical', 'schedule-collaboration-tracking'),
            'uniform_fitting'  => __('Uniform Fitting', 'schedule-collaboration-tracking'),
            'admin_deadline'   => __('Admin Deadline', 'schedule-collaboration-tracking'),
            'other'            => __('Other', 'schedule-collaboration-tracking'),
        );
    }
    
    /**
     * Get event type color
     */
    public static function get_event_type_color($type_key) {
        $settings = get_option('ftt_settings', array());
        
        if (!empty($settings['event_types'][$type_key]['color'])) {
            return $settings['event_types'][$type_key]['color'];
        }
        
        // Default colors
        $colors = array(
            'move_in'          => '#4CAF50',
            'move_out'         => '#F44336',
            'camp_weekend'     => '#2196F3',
            'rehearsal_block'  => '#9C27B0',
            'travel_day'       => '#FF9800',
            'flight_only'      => '#03A9F4',
            'performance_day'  => '#E91E63',
            'housing_checkin'  => '#00BCD4',
            'medical'          => '#FF5722',
            'uniform_fitting'  => '#607D8B',
            'admin_deadline'   => '#795548',
            'other'            => '#9E9E9E',
        );
        
        return $colors[$type_key] ?? '#2196F3';
    }
    
    /**
     * Get block types
     */
    public static function get_block_types() {
        return array(
            'practice'     => __('Practice', 'schedule-collaboration-tracking'),
            'travel'       => __('Travel', 'schedule-collaboration-tracking'),
            'admin'        => __('Admin', 'schedule-collaboration-tracking'),
            'meal'         => __('Meal', 'schedule-collaboration-tracking'),
            'medical'      => __('Medical', 'schedule-collaboration-tracking'),
            'performance'  => __('Performance', 'schedule-collaboration-tracking'),
            'other'        => __('Other', 'schedule-collaboration-tracking'),
        );
    }
    
    /**
     * Get travel modes
     */
    public static function get_travel_modes() {
        return array(
            'fly'     => __('Fly', 'schedule-collaboration-tracking'),
            'drive'   => __('Drive', 'schedule-collaboration-tracking'),
            'bus'     => __('Bus', 'schedule-collaboration-tracking'),
            'shuttle' => __('Shuttle', 'schedule-collaboration-tracking'),
            'other'   => __('Other', 'schedule-collaboration-tracking'),
        );
    }
    
    /**
     * Get baggage types
     */
    public static function get_baggage_types() {
        return array(
            'carry_on'            => __('Carry-On', 'schedule-collaboration-tracking'),
            'checked'             => __('Checked', 'schedule-collaboration-tracking'),
            'oversized_instrument' => __('Oversized Instrument', 'schedule-collaboration-tracking'),
        );
    }
    
    /**
     * Redirect post.php and post-new.php to custom event form
     */
    public static function redirect_to_custom_form() {
        global $pagenow;
        
        // Check if we're on post.php or post-new.php for ftt_event
        if (($pagenow === 'post.php' || $pagenow === 'post-new.php') && 
            isset($_GET['post_type']) && $_GET['post_type'] === 'ftt_event') {
            
            // Get the event form page URL
            $event_form_url = FTT_Pages::get_page_url('event_form');
            
            if ($event_form_url) {
                // Add event ID if editing
                if ($pagenow === 'post.php' && isset($_GET['post'])) {
                    $event_form_url = add_query_arg('event_id', intval($_GET['post']), $event_form_url);
                }
                
                wp_redirect($event_form_url);
                exit;
            }
        }
        
        // Also handle when editing existing event (post.php without post_type param)
        if ($pagenow === 'post.php' && isset($_GET['post']) && !isset($_GET['post_type'])) {
            $post = get_post(intval($_GET['post']));
            if ($post && $post->post_type === 'ftt_event') {
                $event_form_url = FTT_Pages::get_page_url('event_form');
                if ($event_form_url) {
                    $event_form_url = add_query_arg('event_id', $post->ID, $event_form_url);
                    wp_redirect($event_form_url);
                    exit;
                }
            }
        }
    }
    
    /**
     * Modify row actions to point to custom form
     */
    public static function modify_row_actions($actions, $post) {
        if ($post->post_type === 'ftt_event') {
            $event_form_url = FTT_Pages::get_page_url('event_form');
            
            if ($event_form_url) {
                // Replace Edit link
                if (isset($actions['edit'])) {
                    $edit_url = add_query_arg('event_id', $post->ID, $event_form_url);
                    $actions['edit'] = sprintf(
                        '<a href="%s">%s</a>',
                        esc_url($edit_url),
                        __('Edit', 'schedule-collaboration-tracking')
                    );
                }
            }
        }
        
        return $actions;
    }
    
    /**
     * Register custom bulk actions
     */
    
    /**
     * Add custom field to bulk edit panel
     */
    public static function bulk_edit_custom_box($column_name, $post_type) {
        static $rendered = false;
        
        error_log('bulk_edit_custom_box called - column: ' . $column_name . ', post_type: ' . $post_type);
        
        // Only render for ftt_event post type
        if ($post_type !== 'ftt_event') {
            error_log('Skipping - not ftt_event');
            return;
        }
        
        // Only render once (WordPress calls this for each column)
        if ($rendered) {
            error_log('Already rendered, skipping');
            return;
        }
        
        // Check if FTT_Roles class exists
        if (!class_exists('FTT_Roles')) {
            error_log('FTT_Roles class does not exist');
            return;
        }
        
        $members = FTT_Roles::get_all_members();
        error_log('Found ' . count($members) . ' members');
        
        if (empty($members)) {
            error_log('No members found, returning');
            return;
        }
        
        $rendered = true;
        error_log('Rendering bulk edit dropdown');
        ?>
        <fieldset class="inline-edit-col-right">
            <div class="inline-edit-col">
                <label>
                    <span class="title"><?php _e('Assign to Member', 'schedule-collaboration-tracking'); ?></span>
                    <span class="input-text-wrap">
                        <select name="ftt_bulk_assign_member" id="ftt_bulk_assign_member">
                            <option value="-1"><?php _e('— No Change —', 'schedule-collaboration-tracking'); ?></option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo esc_attr($member->ID); ?>">
                                    <?php echo esc_html($member->display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </span>
                </label>
            </div>
        </fieldset>
        <?php
    }
    
    /**
     * Save bulk edit data
     */
    public static function save_bulk_edit_data($post_id) {
        // Debug logging
        error_log('SRT save_bulk_edit_data called for post ID: ' . $post_id);
        error_log('POST data: ' . print_r($_POST, true));
        error_log('REQUEST data: ' . print_r($_REQUEST, true));
        
        // Check if this is a bulk edit
        if (!isset($_REQUEST['ftt_bulk_assign_member'])) {
            error_log('ftt_bulk_assign_member not set in REQUEST');
            return;
        }
        
        error_log('ftt_bulk_assign_member found: ' . $_REQUEST['ftt_bulk_assign_member']);
        
        // Verify post type
        if (get_post_type($post_id) !== 'ftt_event') {
            error_log('Post type is not ftt_event: ' . get_post_type($post_id));
            return;
        }
        
        $member_id = intval($_REQUEST['ftt_bulk_assign_member']);
        
        // -1 means "No Change"
        if ($member_id === -1) {
            error_log('Member ID is -1, skipping');
            return;
        }
        
        // Verify member exists and is a member
        if (!class_exists('FTT_Roles') || !FTT_Roles::is_member($member_id)) {
            error_log('FTT_Roles class missing or user is not a member');
            return;
        }
        
        error_log('Updating post ' . $post_id . ' author to ' . $member_id);
        
        // Update post author
        wp_update_post(array(
            'ID' => $post_id,
            'post_author' => $member_id,
        ));
        
        error_log('Post author updated successfully');
    }
    
    /**
     * Add JavaScript to handle bulk edit
     */
    public static function bulk_edit_javascript() {
        global $current_screen;
        
        // Only load on the edit screen for our post type
        if (!$current_screen || $current_screen->id !== 'edit-ftt_event') {
            return;
        }
        
        // Check if FTT_Roles class exists
        if (!class_exists('FTT_Roles')) {
            return;
        }
        
        $members = FTT_Roles::get_all_members();
        
        if (empty($members)) {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Debug: Log when script loads
            console.log('SRT Bulk Edit JavaScript Loaded');
            
            // Build the member dropdown HTML
            var memberDropdown = '<fieldset class="inline-edit-col-right"><div class="inline-edit-col">';
            memberDropdown += '<label class="inline-edit-group">';
            memberDropdown += '<span class="title">Assign to Member</span>';
            memberDropdown += '<select name="ftt_bulk_assign_member" id="ftt_bulk_assign_member">';
            memberDropdown += '<option value="-1">— No Change —</option>';
            <?php foreach ($members as $member): ?>
            memberDropdown += '<option value="<?php echo esc_js($member->ID); ?>"><?php echo esc_js($member->display_name); ?></option>';
            <?php endforeach; ?>
            memberDropdown += '</select>';
            memberDropdown += '</label>';
            memberDropdown += '</div></fieldset>';
            
            // Wait for bulk edit panel to be created, then inject our dropdown
            var checkBulkEdit = setInterval(function() {
                var bulkEditRow = $('#bulk-edit');
                if (bulkEditRow.length && !$('#ftt_bulk_assign_member').length) {
                    console.log('Bulk edit panel found, injecting dropdown');
                    bulkEditRow.find('fieldset.inline-edit-col-left').last().after(memberDropdown);
                    console.log('Dropdown exists:', $('#ftt_bulk_assign_member').length);
                    clearInterval(checkBulkEdit);
                }
            }, 100);
            
            // Stop checking after 5 seconds
            setTimeout(function() {
                clearInterval(checkBulkEdit);
            }, 5000);
            
            // When bulk edit is submitted (the Update button in the bulk edit panel)
            $(document).on('click', '#bulk_edit', function(e) {
                console.log('Bulk edit clicked');
                
                // Get the selected member
                var member_id = $('#ftt_bulk_assign_member').val();
                console.log('Selected member ID:', member_id);
                
                // If no member selected or -1 (No Change), don't add the field
                if (!member_id || member_id === '-1') {
                    console.log('No member selected, skipping');
                    return;
                }
                
                // Add the member ID to each hidden field in the bulk edit row
                var bulkRow = $('#bulk-edit');
                
                // Remove any existing ftt_bulk_assign_member fields to avoid duplicates
                bulkRow.find('input[name="ftt_bulk_assign_member"]').remove();
                
                // Add our hidden input
                bulkRow.append('<input type="hidden" name="ftt_bulk_assign_member" value="' + member_id + '" />');
                console.log('Added hidden input with member ID:', member_id);
            });
        });
        </script>
        <?php
    }
}

// Initialize
FTT_CPT::init();
