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
            'publicly_queryable' => false, // Disable public URLs - use custom views instead
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
    /**
     * Get event types (returns key => label pairs)
     */
    public static function get_event_types() {
        $settings = get_option('ftt_settings', array());
        
        // Get event types from settings or defaults
        $event_types = !empty($settings['event_types']) ? 
            $settings['event_types'] : 
            self::get_default_event_types();
        
        // Return as key => label pairs for backward compatibility
        $types = array();
        foreach ($event_types as $key => $type) {
            $types[$key] = $type['label'];
        }
        return $types;
    }
    
    /**
     * Get event type color
     */
    /**
     * Get event type color
     */
    public static function get_event_type_color($type_key) {
        $settings = get_option('ftt_settings', array());
        
        // Get from settings first
        if (!empty($settings['event_types'][$type_key]['color'])) {
            return $settings['event_types'][$type_key]['color'];
        }
        
        // Fall back to defaults
        $defaults = self::get_default_event_types();
        if (isset($defaults[$type_key]['color'])) {
            return $defaults[$type_key]['color'];
        }
        
        // Ultimate fallback
        return '#2196F3';
    }
    
    /**
     * Get event type categories (for filtering)
     */
    /**
     * Get event categories with types dynamically assigned
     */
    public static function get_event_categories() {
        $settings = get_option('ftt_settings', array());
        
        // Get categories from settings or defaults
        $categories = !empty($settings['event_categories']) ? 
            $settings['event_categories'] : 
            self::get_default_event_categories();
        
        // Get all event types
        $event_types = !empty($settings['event_types']) ? 
            $settings['event_types'] : 
            self::get_default_event_types();
        
        // Build types array for each category
        foreach ($categories as $cat_key => &$category) {
            $category['types'] = array();
            foreach ($event_types as $type_key => $type) {
                if (isset($type['category']) && $type['category'] === $cat_key) {
                    $category['types'][] = $type_key;
                }
            }
        }
        
        return $categories;
    }
    
    /**
     * Get default event categories (for backward compatibility)
     */
    private static function get_default_event_categories() {
        return array(
            'education' => array(
                'label' => __('Education', 'schedule-collaboration-tracking'),
                'icon' => '📚',
            ),
            'sports' => array(
                'label' => __('Sports', 'schedule-collaboration-tracking'),
                'icon' => '⚽',
            ),
            'arts' => array(
                'label' => __('Music & Arts', 'schedule-collaboration-tracking'),
                'icon' => '🎨',
            ),
            'health' => array(
                'label' => __('Health', 'schedule-collaboration-tracking'),
                'icon' => '🏥',
            ),
            'social' => array(
                'label' => __('Social', 'schedule-collaboration-tracking'),
                'icon' => '🎉',
            ),
            'transportation' => array(
                'label' => __('Transportation', 'schedule-collaboration-tracking'),
                'icon' => '🚗',
            ),
            'administrative' => array(
                'label' => __('Administrative', 'schedule-collaboration-tracking'),
                'icon' => '📝',
            ),
            'travel' => array(
                'label' => __('Travel', 'schedule-collaboration-tracking'),
                'icon' => '✈️',
            ),
        );
    }
    
    /**
     * Get default event types with category assignments (for backward compatibility)
     */
    public static function get_default_event_types() {
        return array(
            // Education
            'school_event' => array(
                'label' => __('School Event', 'schedule-collaboration-tracking'),
                'color' => '#2196F3',
                'category' => 'education',
            ),
            'parent_teacher' => array(
                'label' => __('Parent-Teacher Conference', 'schedule-collaboration-tracking'),
                'color' => '#1976D2',
                'category' => 'education',
            ),
            'exam' => array(
                'label' => __('Exam/Test', 'schedule-collaboration-tracking'),
                'color' => '#1565C0',
                'category' => 'education',
            ),
            'field_trip' => array(
                'label' => __('Field Trip', 'schedule-collaboration-tracking'),
                'color' => '#42A5F5',
                'category' => 'education',
            ),
            'graduation' => array(
                'label' => __('Graduation', 'schedule-collaboration-tracking'),
                'color' => '#64B5F6',
                'category' => 'education',
            ),
            'tutoring' => array(
                'label' => __('Tutoring', 'schedule-collaboration-tracking'),
                'color' => '#1E88E5',
                'category' => 'education',
            ),
            'study_group' => array(
                'label' => __('Study Group', 'schedule-collaboration-tracking'),
                'color' => '#0D47A1',
                'category' => 'education',
            ),
            'science_fair' => array(
                'label' => __('Science Fair', 'schedule-collaboration-tracking'),
                'color' => '#2979FF',
                'category' => 'education',
            ),
            'school_play' => array(
                'label' => __('School Play', 'schedule-collaboration-tracking'),
                'color' => '#448AFF',
                'category' => 'education',
            ),
            'open_house' => array(
                'label' => __('Open House', 'schedule-collaboration-tracking'),
                'color' => '#82B1FF',
                'category' => 'education',
            ),
            
            // Sports
            'sports_practice' => array(
                'label' => __('Sports Practice', 'schedule-collaboration-tracking'),
                'color' => '#4CAF50',
                'category' => 'sports',
            ),
            'sports_game' => array(
                'label' => __('Sports Game', 'schedule-collaboration-tracking'),
                'color' => '#388E3C',
                'category' => 'sports',
            ),
            'tournament' => array(
                'label' => __('Tournament', 'schedule-collaboration-tracking'),
                'color' => '#2E7D32',
                'category' => 'sports',
            ),
            'team_meeting' => array(
                'label' => __('Team Meeting', 'schedule-collaboration-tracking'),
                'color' => '#66BB6A',
                'category' => 'sports',
            ),
            'sports_physical' => array(
                'label' => __('Sports Physical', 'schedule-collaboration-tracking'),
                'color' => '#1B5E20',
                'category' => 'sports',
            ),
            'equipment_fitting' => array(
                'label' => __('Equipment Fitting', 'schedule-collaboration-tracking'),
                'color' => '#43A047',
                'category' => 'sports',
            ),
            'awards_ceremony' => array(
                'label' => __('Awards Ceremony', 'schedule-collaboration-tracking'),
                'color' => '#81C784',
                'category' => 'sports',
            ),
            
            // Arts & Music
            'music_lesson' => array(
                'label' => __('Music Lesson', 'schedule-collaboration-tracking'),
                'color' => '#9C27B0',
                'category' => 'arts',
            ),
            'music_performance' => array(
                'label' => __('Music Performance', 'schedule-collaboration-tracking'),
                'color' => '#7B1FA2',
                'category' => 'arts',
            ),
            'dance_class' => array(
                'label' => __('Dance Class', 'schedule-collaboration-tracking'),
                'color' => '#AB47BC',
                'category' => 'arts',
            ),
            'dance_recital' => array(
                'label' => __('Dance Recital', 'schedule-collaboration-tracking'),
                'color' => '#8E24AA',
                'category' => 'arts',
            ),
            'art_class' => array(
                'label' => __('Art Class', 'schedule-collaboration-tracking'),
                'color' => '#BA68C8',
                'category' => 'arts',
            ),
            'theater_rehearsal' => array(
                'label' => __('Theater Rehearsal', 'schedule-collaboration-tracking'),
                'color' => '#CE93D8',
                'category' => 'arts',
            ),
            'theater_performance' => array(
                'label' => __('Theater Performance', 'schedule-collaboration-tracking'),
                'color' => '#6A1B9A',
                'category' => 'arts',
            ),
            'club_meeting' => array(
                'label' => __('Club Meeting', 'schedule-collaboration-tracking'),
                'color' => '#E1BEE7',
                'category' => 'arts',
            ),
            'art_show' => array(
                'label' => __('Art Show', 'schedule-collaboration-tracking'),
                'color' => '#D81B60',
                'category' => 'arts',
            ),
            'photography_class' => array(
                'label' => __('Photography Class', 'schedule-collaboration-tracking'),
                'color' => '#AD1457',
                'category' => 'arts',
            ),
            'pottery_class' => array(
                'label' => __('Pottery Class', 'schedule-collaboration-tracking'),
                'color' => '#F48FB1',
                'category' => 'arts',
            ),
            'voice_lesson' => array(
                'label' => __('Voice Lesson', 'schedule-collaboration-tracking'),
                'color' => '#880E4F',
                'category' => 'arts',
            ),
            'instrument_rental' => array(
                'label' => __('Instrument Rental', 'schedule-collaboration-tracking'),
                'color' => '#F8BBD0',
                'category' => 'arts',
            ),
            
            // Health
            'doctor_appointment' => array(
                'label' => __('Doctor Appointment', 'schedule-collaboration-tracking'),
                'color' => '#F44336',
                'category' => 'health',
            ),
            'dentist' => array(
                'label' => __('Dentist', 'schedule-collaboration-tracking'),
                'color' => '#E53935',
                'category' => 'health',
            ),
            'orthodontist' => array(
                'label' => __('Orthodontist', 'schedule-collaboration-tracking'),
                'color' => '#D32F2F',
                'category' => 'health',
            ),
            'therapist' => array(
                'label' => __('Therapist/Counselor', 'schedule-collaboration-tracking'),
                'color' => '#EF5350',
                'category' => 'health',
            ),
            'medication_reminder' => array(
                'label' => __('Medication Reminder', 'schedule-collaboration-tracking'),
                'color' => '#FF5252',
                'category' => 'health',
            ),
            'vaccination' => array(
                'label' => __('Vaccination', 'schedule-collaboration-tracking'),
                'color' => '#C62828',
                'category' => 'health',
            ),
            
            // Social
            'birthday_party' => array(
                'label' => __('Birthday Party', 'schedule-collaboration-tracking'),
                'color' => '#FF9800',
                'category' => 'social',
            ),
            'playdate' => array(
                'label' => __('Playdate', 'schedule-collaboration-tracking'),
                'color' => '#FB8C00',
                'category' => 'social',
            ),
            'family_gathering' => array(
                'label' => __('Family Gathering', 'schedule-collaboration-tracking'),
                'color' => '#F57C00',
                'category' => 'social',
            ),
            'sleepover' => array(
                'label' => __('Sleepover', 'schedule-collaboration-tracking'),
                'color' => '#FFB74D',
                'category' => 'social',
            ),
            'school_dance' => array(
                'label' => __('School Dance', 'schedule-collaboration-tracking'),
                'color' => '#EF6C00',
                'category' => 'social',
            ),
            'prom' => array(
                'label' => __('Prom', 'schedule-collaboration-tracking'),
                'color' => '#E65100',
                'category' => 'social',
            ),
            'homecoming' => array(
                'label' => __('Homecoming', 'schedule-collaboration-tracking'),
                'color' => '#FF6F00',
                'category' => 'social',
            ),
            'holiday_party' => array(
                'label' => __('Holiday Party', 'schedule-collaboration-tracking'),
                'color' => '#FF8A65',
                'category' => 'social',
            ),
            'summer_bbq' => array(
                'label' => __('Summer BBQ', 'schedule-collaboration-tracking'),
                'color' => '#FFAB91',
                'category' => 'social',
            ),
            
            // Transportation
            'pickup' => array(
                'label' => __('Pickup', 'schedule-collaboration-tracking'),
                'color' => '#607D8B',
                'category' => 'transportation',
            ),
            'dropoff' => array(
                'label' => __('Drop-off', 'schedule-collaboration-tracking'),
                'color' => '#546E7A',
                'category' => 'transportation',
            ),
            'carpool' => array(
                'label' => __('Carpool', 'schedule-collaboration-tracking'),
                'color' => '#455A64',
                'category' => 'transportation',
            ),
            'bus_schedule' => array(
                'label' => __('Bus Schedule', 'schedule-collaboration-tracking'),
                'color' => '#78909C',
                'category' => 'transportation',
            ),
            'rideshare' => array(
                'label' => __('Rideshare', 'schedule-collaboration-tracking'),
                'color' => '#37474F',
                'category' => 'transportation',
            ),
            'train_subway' => array(
                'label' => __('Train/Subway', 'schedule-collaboration-tracking'),
                'color' => '#90A4AE',
                'category' => 'transportation',
            ),
            
            // Administrative
            'registration_deadline' => array(
                'label' => __('Registration Deadline', 'schedule-collaboration-tracking'),
                'color' => '#795548',
                'category' => 'administrative',
            ),
            'payment_due' => array(
                'label' => __('Payment Due', 'schedule-collaboration-tracking'),
                'color' => '#6D4C41',
                'category' => 'administrative',
            ),
            'forms_due' => array(
                'label' => __('Forms Due', 'schedule-collaboration-tracking'),
                'color' => '#5D4037',
                'category' => 'administrative',
            ),
            'college_visit' => array(
                'label' => __('College Visit', 'schedule-collaboration-tracking'),
                'color' => '#8D6E63',
                'category' => 'administrative',
            ),
            'college_application' => array(
                'label' => __('College Application', 'schedule-collaboration-tracking'),
                'color' => '#A1887F',
                'category' => 'administrative',
            ),
            'insurance_deadline' => array(
                'label' => __('Insurance Deadline', 'schedule-collaboration-tracking'),
                'color' => '#4E342E',
                'category' => 'administrative',
            ),
            'scholarship_application' => array(
                'label' => __('Scholarship Application', 'schedule-collaboration-tracking'),
                'color' => '#BCAAA4',
                'category' => 'administrative',
            ),
            'financial_aid_forms' => array(
                'label' => __('Financial Aid Forms', 'schedule-collaboration-tracking'),
                'color' => '#D7CCC8',
                'category' => 'administrative',
            ),
            
            // Travel
            'travel_day' => array(
                'label' => __('Travel Day', 'schedule-collaboration-tracking'),
                'color' => '#00BCD4',
                'category' => 'travel',
            ),
            'flight_only' => array(
                'label' => __('Flight Only', 'schedule-collaboration-tracking'),
                'color' => '#0097A7',
                'category' => 'travel',
            ),
            
            // Other
            'other' => array(
                'label' => __('Other', 'schedule-collaboration-tracking'),
                'color' => '#9E9E9E',
                'category' => '',
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
            $event_view_url = FTT_Pages::get_page_url('event_view');
            
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
            
            // Add View Details link (ID-based, read-only)
            if ($event_view_url) {
                $view_url = add_query_arg('event_id', $post->ID, $event_view_url);
                $actions['view_details'] = sprintf(
                    '<a href="%s">%s</a>',
                    esc_url($view_url),
                    __('View Details', 'schedule-collaboration-tracking')
                );
            }
            
            // Remove the default "View" action since we've disabled public URLs
            // and replaced it with "View Details" that uses event IDs
            unset($actions['view']);
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
        
        $members = FTT_Family_Groups::get_all_children();
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
        
        // Check if FTT_Family_Groups class exists
        if (!class_exists('FTT_Family_Groups')) {
            return;
        }
        
        $members = FTT_Family_Groups::get_all_children();
        
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
