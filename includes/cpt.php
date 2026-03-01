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
        
        // Family-friendly event types (ages 3-25)
        return array(
            // Education
            'school_event'     => __('School Event', 'schedule-collaboration-tracking'),
            'parent_teacher'   => __('Parent-Teacher Conference', 'schedule-collaboration-tracking'),
            'exam'             => __('Exam/Test', 'schedule-collaboration-tracking'),
            'field_trip'       => __('Field Trip', 'schedule-collaboration-tracking'),
            'graduation'       => __('Graduation', 'schedule-collaboration-tracking'),
            
            // Sports & Activities
            'sports_practice'  => __('Sports Practice', 'schedule-collaboration-tracking'),
            'sports_game'      => __('Sports Game', 'schedule-collaboration-tracking'),
            'tournament'       => __('Tournament', 'schedule-collaboration-tracking'),
            'music_lesson'     => __('Music Lesson', 'schedule-collaboration-tracking'),
            'music_performance' => __('Music Performance', 'schedule-collaboration-tracking'),
            'dance_class'      => __('Dance Class', 'schedule-collaboration-tracking'),
            'dance_recital'    => __('Dance Recital', 'schedule-collaboration-tracking'),
            'art_class'        => __('Art Class', 'schedule-collaboration-tracking'),
            'theater_rehearsal' => __('Theater Rehearsal', 'schedule-collaboration-tracking'),
            'theater_performance' => __('Theater Performance', 'schedule-collaboration-tracking'),
            'club_meeting'     => __('Club Meeting', 'schedule-collaboration-tracking'),
            
            // Health & Wellness
            'doctor_appointment' => __('Doctor Appointment', 'schedule-collaboration-tracking'),
            'dentist'          => __('Dentist', 'schedule-collaboration-tracking'),
            'orthodontist'     => __('Orthodontist', 'schedule-collaboration-tracking'),
            'therapist'        => __('Therapist/Counselor', 'schedule-collaboration-tracking'),
            'medication_reminder' => __('Medication Reminder', 'schedule-collaboration-tracking'),
            'vaccination'      => __('Vaccination', 'schedule-collaboration-tracking'),
            
            // Social & Personal
            'birthday_party'   => __('Birthday Party', 'schedule-collaboration-tracking'),
            'playdate'         => __('Playdate', 'schedule-collaboration-tracking'),
            'family_gathering' => __('Family Gathering', 'schedule-collaboration-tracking'),
            'sleepover'        => __('Sleepover', 'schedule-collaboration-tracking'),
            
            // Transportation
            'pickup'           => __('Pickup', 'schedule-collaboration-tracking'),
            'dropoff'          => __('Drop-off', 'schedule-collaboration-tracking'),
            'carpool'          => __('Carpool', 'schedule-collaboration-tracking'),
            
            // Administrative
            'registration_deadline' => __('Registration Deadline', 'schedule-collaboration-tracking'),
            'payment_due'      => __('Payment Due', 'schedule-collaboration-tracking'),
            'forms_due'        => __('Forms Due', 'schedule-collaboration-tracking'),
            'college_visit'    => __('College Visit', 'schedule-collaboration-tracking'),
            'college_application' => __('College Application', 'schedule-collaboration-tracking'),
            
            // Travel (from original system)
            'travel_day'       => __('Travel Day', 'schedule-collaboration-tracking'),
            'flight_only'      => __('Flight Only', 'schedule-collaboration-tracking'),
            
            // Other
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
        
        // Default colors by category
        $colors = array(
            // Education (Blue shades)
            'school_event'     => '#2196F3',
            'parent_teacher'   => '#1976D2',
            'exam'             => '#1565C0',
            'field_trip'       => '#42A5F5',
            'graduation'       => '#0D47A1',
            
            // Sports (Green shades)
            'sports_practice'  => '#4CAF50',
            'sports_game'      => '#2E7D32',
            'tournament'       => '#1B5E20',
            
            // Music/Arts (Purple shades)
            'music_lesson'     => '#9C27B0',
            'music_performance' => '#7B1FA2',
            'dance_class'      => '#BA68C8',
            'dance_recital'    => '#8E24AA',
            'art_class'        => '#AB47BC',
            'theater_rehearsal' => '#6A1B9A',
            'theater_performance' => '#4A148C',
            'club_meeting'     => '#CE93D8',
            
            // Health (Red shades)
            'doctor_appointment' => '#F44336',
            'dentist'          => '#E53935',
            'orthodontist'     => '#D32F2F',
            'therapist'        => '#EF5350',
            'medication_reminder' => '#FF5252',
            'vaccination'      => '#C62828',
            
            // Social (Pink shades)
            'birthday_party'   => '#E91E63',
            'playdate'         => '#F06292',
            'family_gathering' => '#C2185B',
            'sleepover'        => '#EC407A',
            
            // Transportation (Orange shades)
            'pickup'           => '#FF9800',
            'dropoff'          => '#F57C00',
            'carpool'          => '#E65100',
            
            // Administrative (Brown shades)
            'registration_deadline' => '#795548',
            'payment_due'      => '#6D4C41',
            'forms_due'        => '#5D4037',
            'college_visit'    => '#8D6E63',
            'college_application' => '#4E342E',
            
            // Travel (Cyan shades)
            'travel_day'       => '#00BCD4',
            'flight_only'      => '#0097A7',
            
            // Other
            'other'            => '#9E9E9E',
        );
        
        return $colors[$type_key] ?? '#2196F3';
    }
    
    /**
     * Get event type categories (for filtering)
     */
    public static function get_event_categories() {
        return array(
            'education' => array(
                'label' => __('Education', 'schedule-collaboration-tracking'),
                'icon' => '📚',
                'types' => array('school_event', 'parent_teacher', 'exam', 'field_trip', 'graduation'),
            ),
            'sports' => array(
                'label' => __('Sports', 'schedule-collaboration-tracking'),
                'icon' => '⚽',
                'types' => array('sports_practice', 'sports_game', 'tournament'),
            ),
            'arts' => array(
                'label' => __('Music & Arts', 'schedule-collaboration-tracking'),
                'icon' => '🎨',
                'types' => array('music_lesson', 'music_performance', 'dance_class', 'dance_recital', 'art_class', 'theater_rehearsal', 'theater_performance', 'club_meeting'),
            ),
            'health' => array(
                'label' => __('Health', 'schedule-collaboration-tracking'),
                'icon' => '🏥',
                'types' => array('doctor_appointment', 'dentist', 'orthodontist', 'therapist', 'medication_reminder', 'vaccination'),
            ),
            'social' => array(
                'label' => __('Social', 'schedule-collaboration-tracking'),
                'icon' => '🎉',
                'types' => array('birthday_party', 'playdate', 'family_gathering', 'sleepover'),
            ),
            'transportation' => array(
                'label' => __('Transportation', 'schedule-collaboration-tracking'),
                'icon' => '🚗',
                'types' => array('pickup', 'dropoff', 'carpool'),
            ),
            'administrative' => array(
                'label' => __('Administrative', 'schedule-collaboration-tracking'),
                'icon' => '📝',
                'types' => array('registration_deadline', 'payment_due', 'forms_due', 'college_visit', 'college_application'),
            ),
            'travel' => array(
                'label' => __('Travel', 'schedule-collaboration-tracking'),
                'icon' => '✈️',
                'types' => array('travel_day', 'flight_only'),
            ),
        );
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
