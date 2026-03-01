<?php
/**
 * Template: Event Form
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('ftt_settings', array());
$default_timezone = $settings['default_timezone'] ?? wp_timezone_string();
$current_user = wp_get_current_user();
$default_airport = get_user_meta($current_user->ID, 'ftt_home_airport', true);
?>

<div class="ftt-container">
    <div class="ftt-form-header">
        <?php
        $calendar_url = FTT_Pages::get_page_url('calendar');
        if ($calendar_url) :
        ?>
        <p class="ftt-back-link">
            <a href="<?php echo esc_url($calendar_url); ?>">← <?php esc_html_e('Back to Calendar', 'schedule-collaboration-tracking'); ?></a>
        </p>
        <?php endif; ?>
        <h2><?php esc_html_e('Add/Edit Event', 'schedule-collaboration-tracking'); ?></h2>
    </div>
    
    <form id="ftt-event-form">
        <!-- Basic Information -->
        <div class="ftt-form-section">
            <h3><?php esc_html_e('Basic Information', 'schedule-collaboration-tracking'); ?></h3>
            
            <?php
            // Show member selector for parents or admins
            $current_user_id = get_current_user_id();
            $children = FTT_Roles::get_children($current_user_id);
            $is_member = FTT_Roles::is_member($current_user_id);
            
            if (!empty($children) || current_user_can('manage_options')) : ?>
                <div class="ftt-form-field">
                    <label for="member_id"><?php esc_html_e('Event For', 'schedule-collaboration-tracking'); ?> *</label>
                    <select id="member_id" name="member_id" required>
                        <option value=""><?php esc_html_e('Select Child...', 'schedule-collaboration-tracking'); ?></option>
                        <?php
                        // Parents see their children
                        if (!empty($children)) {
                            foreach ($children as $child_id) {
                                $child = get_user_by('id', $child_id);
                                if ($child) {
                                    echo '<option value="' . esc_attr($child_id) . '">' . esc_html($child->display_name) . '</option>';
                                }
                            }
                        }
                        // Admins see all members
                        if (current_user_can('manage_options')) {
                            $all_members = FTT_Roles::get_all_members();
                            foreach ($all_members as $member) {
                                echo '<option value="' . esc_attr($member->ID) . '">' . esc_html($member->display_name) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <small class="description"><?php esc_html_e('Select which child this event belongs to', 'schedule-collaboration-tracking'); ?></small>
                </div>
            <?php elseif ($is_member) : ?>
                <!-- Hidden field for members creating their own events -->
                <input type="hidden" id="member_id" name="member_id" value="<?php echo esc_attr($current_user_id); ?>">
            <?php endif; ?>
            
            <div class="ftt-form-field">
                <label for="event_title"><?php esc_html_e('Event Title', 'schedule-collaboration-tracking'); ?> *</label>
                <input type="text" id="event_title" name="title" required>
            </div>
            
            <div class="ftt-form-field">
                <label>
                    <input type="checkbox" id="all_day" name="all_day" value="1">
                    <?php esc_html_e('All Day Event', 'schedule-collaboration-tracking'); ?>
                </label>
                <small class="description"><?php esc_html_e('Check this for events that span the entire day without specific times', 'schedule-collaboration-tracking'); ?></small>
            </div>
            
            <div class="ftt-form-row">
                <div class="ftt-form-field">
                    <label for="start_datetime"><?php esc_html_e('Start Date/Time', 'schedule-collaboration-tracking'); ?> *</label>
                    <input type="datetime-local" id="start_datetime" name="start_datetime" required>
                </div>
                
                <div class="ftt-form-field">
                    <label for="end_datetime"><?php esc_html_e('End Date/Time', 'schedule-collaboration-tracking'); ?> *</label>
                    <input type="datetime-local" id="end_datetime" name="end_datetime" required>
                </div>
            </div>
            
            <div class="ftt-form-row">
                <div class="ftt-form-field">
                    <label for="timezone"><?php esc_html_e('Timezone', 'schedule-collaboration-tracking'); ?></label>
                    <select id="timezone" name="timezone">
                        <?php
                        $timezones = timezone_identifiers_list();
                        foreach ($timezones as $tz) :
                            ?>
                            <option value="<?php echo esc_attr($tz); ?>" <?php selected($default_timezone, $tz); ?>>
                                <?php echo esc_html($tz); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="ftt-form-field">
                    <label for="event_type"><?php esc_html_e('Event Type', 'schedule-collaboration-tracking'); ?></label>
                    <select id="event_type" name="event_type">
                        <?php
                        $event_types = FTT_CPT::get_event_types();
                        foreach ($event_types as $key => $label) :
                            ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="ftt-form-field">
                <label for="location_name"><?php esc_html_e('Location Name', 'schedule-collaboration-tracking'); ?></label>
                <input type="text" id="location_name" name="location_name">
            </div>
            
            <div class="ftt-form-field">
                <label for="location_address"><?php esc_html_e('Location Address', 'schedule-collaboration-tracking'); ?></label>
                <textarea id="location_address" name="location_address" rows="3"></textarea>
            </div>
            
            <div class="ftt-form-field">
                <label for="notes"><?php esc_html_e('Notes', 'schedule-collaboration-tracking'); ?></label>
                <textarea id="notes" name="notes" rows="4"></textarea>
            </div>
        </div>
        
        <!-- Time Blocks -->
        <div class="ftt-form-section">
            <h3><?php esc_html_e('Time Blocks', 'schedule-collaboration-tracking'); ?></h3>
            <p><?php esc_html_e('Add multiple time blocks for different activities within the same day (e.g., practice, meals, performances).', 'schedule-collaboration-tracking'); ?></p>
            
            <div id="ftt-time-blocks-container"></div>
            
            <button type="button" id="ftt-add-time-block" class="ftt-add-button">
                <?php esc_html_e('+ Add Time Block', 'schedule-collaboration-tracking'); ?>
            </button>
        </div>
        
        <!-- Travel Information -->
        <div class="ftt-form-section">
            <h3><?php esc_html_e('Travel Information', 'schedule-collaboration-tracking'); ?></h3>
            
            <div class="ftt-form-field">
                <label>
                    <input type="checkbox" id="travel_needed" name="travel_needed">
                    <?php esc_html_e('Travel Needed', 'schedule-collaboration-tracking'); ?>
                </label>
            </div>
            
            <div class="ftt-travel-section">
                <div class="ftt-form-row">
                    <div class="ftt-form-field">
                        <label for="travel_mode"><?php esc_html_e('Primary Travel Mode', 'schedule-collaboration-tracking'); ?></label>
                        <select id="travel_mode" name="travel_mode">
                            <?php
                            $travel_modes = FTT_CPT::get_travel_modes();
                            foreach ($travel_modes as $key => $label) :
                                ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="ftt-form-field">
                        <label>
                            <input type="checkbox" id="flight_needed" name="flight_needed">
                            <?php esc_html_e('Flight Needed', 'schedule-collaboration-tracking'); ?>
                        </label>
                    </div>
                </div>
                
                <div class="ftt-flight-section">
                    <h4><?php esc_html_e('Travel Legs', 'schedule-collaboration-tracking'); ?></h4>
                    <p><?php esc_html_e('Add multiple legs for complex itineraries (e.g., Home → Camp, Camp → Show, Show → Home).', 'schedule-collaboration-tracking'); ?></p>
                    
                    <div id="ftt-travel-legs-container"></div>
                    
                    <!-- Flight Link Suggestions -->
                    <div id="ftt-flight-suggestions" class="ftt-flight-suggestions" style="display: none;">
                        <h5><?php esc_html_e('💡 Link Flights for Better Pricing', 'schedule-collaboration-tracking'); ?></h5>
                        <div id="ftt-suggestions-list"></div>
                    </div>
                    
                    <button type="button" id="ftt-add-travel-leg" class="ftt-add-button">
                        <?php esc_html_e('+ Add Travel Leg', 'schedule-collaboration-tracking'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="ftt-form-actions">
            <button type="submit" class="ftt-button-primary">
                <?php esc_html_e('Save Event', 'schedule-collaboration-tracking'); ?>
            </button>
            
            <button type="button" id="ftt-delete-event" class="ftt-button-danger" style="display: none;">
                <?php esc_html_e('Delete Event', 'schedule-collaboration-tracking'); ?>
            </button>
        </div>
    </form>
</div>
