<?php
/**
 * Template: Event View (Read-Only)
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

if (!$event_id) {
    wp_die(__('Invalid event ID.', 'schedule-collaboration-tracking'));
}

$event = get_post($event_id);

if (!$event || $event->post_type !== 'ftt_event') {
    wp_die(__('Event not found.', 'schedule-collaboration-tracking'));
}

// Check permissions
if (!current_user_can('read_post', $event_id) && !current_user_can('edit_posts')) {
    wp_die(__('You do not have permission to view this event.', 'schedule-collaboration-tracking'));
}

// Get event metadata
$start_datetime = get_post_meta($event_id, 'start_datetime', true);
$end_datetime = get_post_meta($event_id, 'end_datetime', true);
$timezone = get_post_meta($event_id, 'timezone', true);
$all_day = get_post_meta($event_id, 'all_day', true);
$event_type = get_post_meta($event_id, 'event_type', true);
$location_name = get_post_meta($event_id, 'location_name', true);
$location_address = get_post_meta($event_id, 'location_address', true);
$notes = get_post_meta($event_id, 'notes', true);
$time_blocks = get_post_meta($event_id, 'time_blocks', true);
$travel_needed = get_post_meta($event_id, 'travel_needed', true);
$travel_mode = get_post_meta($event_id, 'travel_mode', true);
$flight_needed = get_post_meta($event_id, 'flight_needed', true);
$travel_legs = get_post_meta($event_id, 'travel_legs', true);
$member_id = get_post_meta($event_id, 'member_id', true);
$group_id = get_post_meta($event_id, 'group_id', true);

// Get event type label
$event_types = FTT_CPT::get_event_types();
$event_type_label = $event_types[$event_type] ?? $event_type;

// Get member name
$member_name = '';
if ($member_id) {
    $member_user = get_userdata($member_id);
    if ($member_user) {
        $member_name = $member_user->display_name;
    }
}

// Get group name
$group_name = '';
if ($group_id) {
    $group = FTT_Family_Groups::get_group($group_id);
    if ($group) {
        $group_name = $group->name;
    }
}

// Parse time blocks
$time_blocks_data = array();
if ($time_blocks) {
    $time_blocks_data = json_decode($time_blocks, true);
    if (!is_array($time_blocks_data)) {
        $time_blocks_data = array();
    }
}

// Parse travel legs
$travel_legs_data = array();
if ($travel_legs) {
    $travel_legs_data = json_decode($travel_legs, true);
    if (!is_array($travel_legs_data)) {
        $travel_legs_data = array();
    }
}

?>

<div class="ftt-container">
    <?php
    $ftt_page_title  = __('Event Details', 'schedule-collaboration-tracking');
    $ftt_active_slug = 'event_view';
    include FTT_PLUGIN_DIR . 'templates/partials/nav.php';
    ?>
    
    <div class="ftt-event-view">
        <div class="ftt-view-header">
            <?php
            $calendar_url = FTT_Pages::get_page_url('calendar');
            if ($calendar_url) :
            ?>
            <p class="ftt-breadcrumb">
                <a href="<?php echo esc_url($calendar_url); ?>">← <?php esc_html_e('Back to Calendar', 'schedule-collaboration-tracking'); ?></a>
            </p>
            <?php endif; ?>
            
            <div class="ftt-view-title-row">
                <h2><?php echo esc_html($event->post_title); ?></h2>
                <?php if (current_user_can('edit_post', $event_id)) : ?>
                    <div class="ftt-view-actions">
                        <?php
                        $event_form_url = FTT_Pages::get_page_url('event_form');
                        if ($event_form_url) {
                            $edit_url = add_query_arg('event_id', $event_id, $event_form_url);
                        ?>
                            <a href="<?php echo esc_url($edit_url); ?>" class="ftt-button-primary">
                                <?php esc_html_e('Edit Event', 'schedule-collaboration-tracking'); ?>
                            </a>
                        <?php } ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Basic Information -->
        <div class="ftt-view-section">
            <h3><?php esc_html_e('Basic Information', 'schedule-collaboration-tracking'); ?></h3>
            
            <div class="ftt-view-grid">
                <?php if ($member_name) : ?>
                <div class="ftt-view-field">
                    <span class="ftt-view-label"><?php esc_html_e('Event For:', 'schedule-collaboration-tracking'); ?></span>
                    <span class="ftt-view-value"><?php echo esc_html($member_name); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($group_name) : ?>
                <div class="ftt-view-field">
                    <span class="ftt-view-label"><?php esc_html_e('Family Group:', 'schedule-collaboration-tracking'); ?></span>
                    <span class="ftt-view-value"><?php echo esc_html($group_name); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($start_datetime) : ?>
                <div class="ftt-view-field">
                    <span class="ftt-view-label"><?php esc_html_e('Start:', 'schedule-collaboration-tracking'); ?></span>
                    <span class="ftt-view-value">
                        <?php
                        $start_date = date_create($start_datetime);
                        if ($start_date) {
                            if ($all_day) {
                                echo esc_html($start_date->format('F j, Y'));
                            } else {
                                echo esc_html($start_date->format('F j, Y g:i A'));
                            }
                        }
                        ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if ($end_datetime) : ?>
                <div class="ftt-view-field">
                    <span class="ftt-view-label"><?php esc_html_e('End:', 'schedule-collaboration-tracking'); ?></span>
                    <span class="ftt-view-value">
                        <?php
                        $end_date = date_create($end_datetime);
                        if ($end_date) {
                            if ($all_day) {
                                echo esc_html($end_date->format('F j, Y'));
                            } else {
                                echo esc_html($end_date->format('F j, Y g:i A'));
                            }
                        }
                        ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if ($all_day) : ?>
                <div class="ftt-view-field">
                    <span class="ftt-view-label"><?php esc_html_e('All Day:', 'schedule-collaboration-tracking'); ?></span>
                    <span class="ftt-view-value"><?php esc_html_e('Yes', 'schedule-collaboration-tracking'); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($timezone) : ?>
                <div class="ftt-view-field">
                    <span class="ftt-view-label"><?php esc_html_e('Timezone:', 'schedule-collaboration-tracking'); ?></span>
                    <span class="ftt-view-value"><?php echo esc_html($timezone); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($event_type) : ?>
                <div class="ftt-view-field">
                    <span class="ftt-view-label"><?php esc_html_e('Event Type:', 'schedule-collaboration-tracking'); ?></span>
                    <span class="ftt-view-value"><?php echo esc_html($event_type_label); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($location_name) : ?>
                <div class="ftt-view-field">
                    <span class="ftt-view-label"><?php esc_html_e('Location:', 'schedule-collaboration-tracking'); ?></span>
                    <span class="ftt-view-value"><?php echo esc_html($location_name); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($location_address) : ?>
                <div class="ftt-view-field ftt-view-field-full">
                    <span class="ftt-view-label"><?php esc_html_e('Address:', 'schedule-collaboration-tracking'); ?></span>
                    <span class="ftt-view-value"><?php echo nl2br(esc_html($location_address)); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($notes) : ?>
        <!-- Notes -->
        <div class="ftt-view-section">
            <h3><?php esc_html_e('Notes', 'schedule-collaboration-tracking'); ?></h3>
            <div class="ftt-view-content">
                <?php echo wp_kses_post($notes); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($time_blocks_data)) : ?>
        <!-- Time Blocks -->
        <div class="ftt-view-section">
            <h3><?php esc_html_e('Time Blocks', 'schedule-collaboration-tracking'); ?></h3>
            <div class="ftt-view-list">
                <?php foreach ($time_blocks_data as $block) : ?>
                <div class="ftt-view-list-item">
                    <div class="ftt-view-field">
                        <span class="ftt-view-label"><?php esc_html_e('Type:', 'schedule-collaboration-tracking'); ?></span>
                        <span class="ftt-view-value"><?php echo esc_html($block['type'] ?? ''); ?></span>
                    </div>
                    <div class="ftt-view-field">
                        <span class="ftt-view-label"><?php esc_html_e('Time:', 'schedule-collaboration-tracking'); ?></span>
                        <span class="ftt-view-value">
                            <?php
                            if (!empty($block['start_time']) && !empty($block['end_time'])) {
                                echo esc_html($block['start_time'] . ' - ' . $block['end_time']);
                            }
                            ?>
                        </span>
                    </div>
                    <?php if (!empty($block['description'])) : ?>
                    <div class="ftt-view-field">
                        <span class="ftt-view-label"><?php esc_html_e('Description:', 'schedule-collaboration-tracking'); ?></span>
                        <span class="ftt-view-value"><?php echo esc_html($block['description']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($travel_legs_data)) : ?>
        <!-- Travel Information -->
        <div class="ftt-view-section">
            <h3><?php esc_html_e('Travel Information', 'schedule-collaboration-tracking'); ?></h3>
            
            <?php if ($travel_needed) : ?>
            <div class="ftt-view-badges">
                <span class="ftt-badge ftt-badge-travel"><?php esc_html_e('Travel Needed', 'schedule-collaboration-tracking'); ?></span>
                <?php if ($flight_needed) : ?>
                <span class="ftt-badge ftt-badge-flight"><?php esc_html_e('Flight Needed', 'schedule-collaboration-tracking'); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="ftt-view-list">
                <?php foreach ($travel_legs_data as $index => $leg) : ?>
                <div class="ftt-view-list-item">
                    <h4><?php printf(esc_html__('Leg %d', 'schedule-collaboration-tracking'), $index + 1); ?></h4>
                    
                    <div class="ftt-view-grid">
                        <?php if (!empty($leg['mode'])) : ?>
                        <div class="ftt-view-field">
                            <span class="ftt-view-label"><?php esc_html_e('Mode:', 'schedule-collaboration-tracking'); ?></span>
                            <span class="ftt-view-value"><?php echo esc_html(ucfirst($leg['mode'])); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($leg['origin_airport'])) : ?>
                        <div class="ftt-view-field">
                            <span class="ftt-view-label"><?php esc_html_e('From:', 'schedule-collaboration-tracking'); ?></span>
                            <span class="ftt-view-value"><?php echo esc_html($leg['origin_airport']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($leg['destination_airport'])) : ?>
                        <div class="ftt-view-field">
                            <span class="ftt-view-label"><?php esc_html_e('To:', 'schedule-collaboration-tracking'); ?></span>
                            <span class="ftt-view-value"><?php echo esc_html($leg['destination_airport']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($leg['departure_datetime'])) : ?>
                        <div class="ftt-view-field">
                            <span class="ftt-view-label"><?php esc_html_e('Departure:', 'schedule-collaboration-tracking'); ?></span>
                            <span class="ftt-view-value">
                                <?php
                                $dep_date = date_create($leg['departure_datetime']);
                                if ($dep_date) {
                                    echo esc_html($dep_date->format('M j, Y g:i A'));
                                }
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($leg['arrival_datetime'])) : ?>
                        <div class="ftt-view-field">
                            <span class="ftt-view-label"><?php esc_html_e('Arrival:', 'schedule-collaboration-tracking'); ?></span>
                            <span class="ftt-view-value">
                                <?php
                                $arr_date = date_create($leg['arrival_datetime']);
                                if ($arr_date) {
                                    echo esc_html($arr_date->format('M j, Y g:i A'));
                                }
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($leg['flight_number'])) : ?>
                        <div class="ftt-view-field">
                            <span class="ftt-view-label"><?php esc_html_e('Flight #:', 'schedule-collaboration-tracking'); ?></span>
                            <span class="ftt-view-value"><?php echo esc_html($leg['flight_number']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($leg['airline'])) : ?>
                        <div class="ftt-view-field">
                            <span class="ftt-view-label"><?php esc_html_e('Airline:', 'schedule-collaboration-tracking'); ?></span>
                            <span class="ftt-view-value"><?php echo esc_html($leg['airline']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($leg['confirmation_number'])) : ?>
                        <div class="ftt-view-field">
                            <span class="ftt-view-label"><?php esc_html_e('Confirmation:', 'schedule-collaboration-tracking'); ?></span>
                            <span class="ftt-view-value"><?php echo esc_html($leg['confirmation_number']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Metadata -->
        <div class="ftt-view-section ftt-view-meta">
            <div class="ftt-view-field">
                <span class="ftt-view-label"><?php esc_html_e('Event ID:', 'schedule-collaboration-tracking'); ?></span>
                <span class="ftt-view-value"><?php echo esc_html($event_id); ?></span>
            </div>
            <div class="ftt-view-field">
                <span class="ftt-view-label"><?php esc_html_e('Created:', 'schedule-collaboration-tracking'); ?></span>
                <span class="ftt-view-value"><?php echo esc_html(get_the_date('F j, Y g:i A', $event)); ?></span>
            </div>
            <div class="ftt-view-field">
                <span class="ftt-view-label"><?php esc_html_e('Last Modified:', 'schedule-collaboration-tracking'); ?></span>
                <span class="ftt-view-value"><?php echo esc_html(get_the_modified_date('F j, Y g:i A', $event)); ?></span>
            </div>
        </div>
    </div>
</div>

<style>
.ftt-event-view {
    max-width: 1000px;
    margin: 0 auto;
}

.ftt-view-header {
    margin-bottom: 2rem;
}

.ftt-view-title-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.ftt-view-title-row h2 {
    margin: 0;
    flex: 1;
}

.ftt-view-actions {
    display: flex;
    gap: 0.5rem;
}

.ftt-view-section {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.ftt-view-section h3 {
    margin-top: 0;
    margin-bottom: 1rem;
    font-size: 1.25rem;
    color: #333;
    border-bottom: 2px solid #f0f0f0;
    padding-bottom: 0.5rem;
}

.ftt-view-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.ftt-view-field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.ftt-view-field-full {
    grid-column: 1 / -1;
}

.ftt-view-label {
    font-weight: 600;
    color: #666;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.ftt-view-value {
    color: #333;
    font-size: 1rem;
}

.ftt-view-content {
    color: #333;
    line-height: 1.6;
}

.ftt-view-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.ftt-view-list-item {
    background: #f9f9f9;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 1rem;
}

.ftt-view-list-item h4 {
    margin: 0 0 0.75rem 0;
    font-size: 1rem;
    color: #555;
}

.ftt-view-badges {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.ftt-badge {
    display: inline-block;
    padding: 0.375rem 0.75rem;
    border-radius: 4px;
    font-size: 0.875rem;
    font-weight: 600;
}

.ftt-badge-travel {
    background: #e3f2fd;
    color: #1976d2;
}

.ftt-badge-flight {
    background: #fff3e0;
    color: #f57c00;
}

.ftt-view-meta {
    background: #f5f5f5;
    border-color: #d0d0d0;
}

.ftt-view-meta .ftt-view-field {
    flex-direction: row;
    gap: 0.5rem;
    align-items: center;
}

.ftt-view-meta .ftt-view-label {
    font-size: 0.75rem;
}

.ftt-view-meta .ftt-view-value {
    font-size: 0.875rem;
    color: #666;
}

@media (max-width: 768px) {
    .ftt-view-grid {
        grid-template-columns: 1fr;
    }
    
    .ftt-view-title-row {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
