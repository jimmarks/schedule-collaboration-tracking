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
    <?php
    $ftt_page_title  = __('Manage Events', 'schedule-collaboration-tracking');
    $ftt_active_slug = 'event_form';
    include FTT_PLUGIN_DIR . 'templates/partials/nav.php';
    ?>
    <div class="ftt-form-header">
        <?php
        $calendar_url = FTT_Pages::get_page_url('calendar');
        if ($calendar_url) :
        ?>
        <p class="ftt-breadcrumb">
            <a href="<?php echo esc_url($calendar_url); ?>">← <?php esc_html_e('Back to Calendar', 'schedule-collaboration-tracking'); ?></a>
        </p>
        <?php endif; ?>
        <h2><?php esc_html_e('Add/Edit Event', 'schedule-collaboration-tracking'); ?></h2>
    </div>

    <?php
    $ftt_settings   = get_option('ftt_settings', []);
    $has_openai_key = ! empty( $ftt_settings['openai_api_key'] );
    if ( $has_openai_key ) :
    ?>
    <!-- ── AI Event Assistant (conversational chat) ── -->
    <div class="ftt-ai-assistant" id="ftt-ai-assistant">
        <div class="ftt-ai-header">
            <span class="ftt-ai-icon">✨</span>
            <span class="ftt-ai-label"><?php esc_html_e( 'AI Event Assistant', 'schedule-collaboration-tracking' ); ?></span>
            <button type="button" class="ftt-ai-toggle" id="ftt-ai-toggle" aria-expanded="false">
                <?php esc_html_e( 'Chat', 'schedule-collaboration-tracking' ); ?> ▾
            </button>
        </div>
        <div class="ftt-ai-body" id="ftt-ai-body" style="display:none;">
            <div class="ftt-ai-chat" id="ftt-ai-chat" role="log" aria-live="polite">
                <div class="ftt-ai-bubble ftt-ai-bubble-ai">
                    <?php esc_html_e( 'Tell me about the trip — who\'s going, where to, and when?', 'schedule-collaboration-tracking' ); ?>
                </div>
            </div>
            <div class="ftt-ai-chat-footer">
                <textarea id="ftt-ai-prompt"
                          class="ftt-ai-chat-input"
                          rows="2"
                          placeholder="<?php esc_attr_e( 'Type your reply…', 'schedule-collaboration-tracking' ); ?>"></textarea>
                <div class="ftt-ai-chat-meta">
                    <button type="button" class="ftt-btn ftt-btn-primary ftt-ai-send-btn" id="ftt-ai-parse-btn">
                        <?php esc_html_e( 'Send', 'schedule-collaboration-tracking' ); ?>
                    </button>
                    <span class="ftt-ai-spinner" id="ftt-ai-spinner" style="display:none;">
                        ✨ <?php esc_html_e( 'Thinking…', 'schedule-collaboration-tracking' ); ?>
                    </span>
                    <button type="button" class="ftt-btn-link ftt-ai-restart" id="ftt-ai-restart" style="display:none;">
                        <?php esc_html_e( 'Start over', 'schedule-collaboration-tracking' ); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Form is collapsed while the AI chat is active, expanded after fill -->
    <div id="ftt-form-wrap" class="ftt-form-wrap">
        <button type="button" id="ftt-form-expand-bar" class="ftt-form-expand-bar" style="display:none;" aria-expanded="false">
            <span class="ftt-form-expand-label"><?php esc_html_e( 'Event Details', 'schedule-collaboration-tracking' ); ?></span>
            <span class="ftt-form-expand-hint"><?php esc_html_e( 'Chat to fill — or click to edit manually', 'schedule-collaboration-tracking' ); ?></span>
            <span class="ftt-form-expand-arrow">▾</span>
        </button>
        <div id="ftt-form-fields">
    <form id="ftt-event-form">
        <!-- Basic Information -->
        <div class="ftt-form-section">
            <h3><?php esc_html_e('Basic Information', 'schedule-collaboration-tracking'); ?></h3>
            
            <?php
            // Show member selector for parents only
            $current_user_id = get_current_user_id();
            $children = FTT_Family_Groups::get_user_children($current_user_id);
            $is_member = FTT_Roles::is_member($current_user_id);
            
            if (!empty($children)) :
                // Parent - show their children only
                $selectable_children = array();
                foreach ($children as $child_id) {
                    $child = get_user_by('id', $child_id);
                    if ($child) {
                        $selectable_children[] = array('id' => $child_id, 'name' => $child->display_name);
                    }
                }
            ?>
                <div class="ftt-form-field">
                    <label><?php esc_html_e('Event For', 'schedule-collaboration-tracking'); ?> *</label>
                    <div id="ftt-member-checkboxes" class="ftt-member-checkboxes">
                        <?php if (count($selectable_children) > 1) : ?>
                        <label class="ftt-member-check ftt-member-check--family">
                            <input type="checkbox" id="ftt-family-event" value="family">
                            <span><?php esc_html_e('Family Event (all children)', 'schedule-collaboration-tracking'); ?></span>
                        </label>
                        <hr class="ftt-member-divider">
                        <?php endif; ?>
                        <?php foreach ($selectable_children as $sc) : ?>
                        <label class="ftt-member-check">
                            <input type="checkbox" class="ftt-child-checkbox" value="<?php echo esc_attr($sc['id']); ?>">
                            <span><?php echo esc_html($sc['name']); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <small class="description"><?php esc_html_e('Select one or more children, or choose Family Event to attach to all.', 'schedule-collaboration-tracking'); ?></small>
                </div>
            <?php elseif ($is_member) : ?>
                <!-- Member creating their own event — no child selector needed -->
                <input type="hidden" id="member_id" name="member_id" value="<?php echo esc_attr($current_user_id); ?>">
            <?php endif; ?>
            
            <?php
            // Group selector (v2.1 - Family Groups)
            $user_groups = FTT_Family_Groups::get_user_groups($current_user_id);
            $primary_group = get_user_meta($current_user_id, 'ftt_primary_group', true);
            
            if (!empty($user_groups)) : ?>
                <div class="ftt-form-field">
                    <label for="group_id"><?php esc_html_e('Family Group', 'schedule-collaboration-tracking'); ?> *</label>
                    <select id="group_id" name="group_id" required>
                        <?php foreach ($user_groups as $group) : ?>
                            <option value="<?php echo esc_attr($group->id); ?>" 
                                    <?php selected($group->id, $primary_group); ?>>
                                <?php echo esc_html($group->name); ?>
                                <?php if ($group->id == $primary_group) : ?>
                                    (Primary)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="description"><?php esc_html_e('Select which family group this event belongs to', 'schedule-collaboration-tracking'); ?></small>
                </div>
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
                    <label for="event_type_search"><?php esc_html_e('Event Type', 'schedule-collaboration-tracking'); ?></label>
                    <div class="ftt-combobox" id="ftt-event-type-combobox">
                        <input type="text" id="event_type_search" class="ftt-combobox-input" autocomplete="off"
                               placeholder="<?php esc_attr_e('Type to search or enter custom type…', 'schedule-collaboration-tracking'); ?>">
                        <input type="hidden" id="event_type" name="event_type">
                        <ul class="ftt-combobox-list" id="event_type_list" role="listbox"></ul>
                    </div>
                </div>
                <?php
                $event_types = FTT_CPT::get_event_types();
                ?>
                <script>window.fttEventTypes = <?php echo wp_json_encode($event_types); ?>;</script>
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
        <!-- travel_needed, travel_mode, and flight_needed are derived automatically
             from the travel legs on submit — no manual fields needed here. -->
        <div class="ftt-form-section">
            <h3><?php esc_html_e('Travel Information', 'schedule-collaboration-tracking'); ?></h3>
            <p class="ftt-form-hint"><?php esc_html_e('Add legs for any travel involved in this event (flights, drives, etc.). Leave empty if no travel is needed.', 'schedule-collaboration-tracking'); ?></p>

            <div id="ftt-travel-legs-container"></div>

            <!-- Flight Link Suggestions -->
            <div id="ftt-flight-suggestions" class="ftt-flight-suggestions" style="display: none;">
                <h5><?php esc_html_e('✓ Round-Trip Detected', 'schedule-collaboration-tracking'); ?></h5>
                <div id="ftt-suggestions-list"></div>
            </div>

            <button type="button" id="ftt-add-travel-leg" class="ftt-add-button">
                <?php esc_html_e('+ Add Travel Leg', 'schedule-collaboration-tracking'); ?>
            </button>
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
        </div><!-- /#ftt-form-fields -->
    </div><!-- /#ftt-form-wrap -->
</div><!-- /.ftt-container -->
