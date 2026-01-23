<?php
/**
 * Shortcodes for Summer Regiment Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all shortcodes
 */
function srt_register_shortcodes() {
    add_shortcode('srt_calendar', 'srt_calendar_shortcode');
    add_shortcode('srt_event_form', 'srt_event_form_shortcode');
    add_shortcode('srt_dashboard', 'srt_dashboard_shortcode');
}

/**
 * Calendar shortcode
 * Usage: [srt_calendar]
 */
function srt_calendar_shortcode($atts) {
    $atts = shortcode_atts(array(
        'month' => date('n'),
        'year' => date('Y')
    ), $atts);
    
    $month = intval($atts['month']);
    $year = intval($atts['year']);
    
    // Get events for the month
    $events = srt_get_events_for_month($year, $month);
    
    // Organize events by day
    $events_by_day = array();
    foreach ($events as $event) {
        $day = date('j', strtotime($event->event_date));
        if (!isset($events_by_day[$day])) {
            $events_by_day[$day] = array();
        }
        $events_by_day[$day][] = $event;
    }
    
    // Generate calendar HTML
    ob_start();
    ?>
    <div class="srt-calendar-wrapper">
        <div class="srt-calendar-header">
            <button class="srt-prev-month" data-month="<?php echo $month; ?>" data-year="<?php echo $year; ?>">&laquo; Previous</button>
            <h2><?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></h2>
            <button class="srt-next-month" data-month="<?php echo $month; ?>" data-year="<?php echo $year; ?>">Next &raquo;</button>
        </div>
        
        <div class="srt-calendar">
            <div class="srt-calendar-days">
                <div class="srt-day-header">Sun</div>
                <div class="srt-day-header">Mon</div>
                <div class="srt-day-header">Tue</div>
                <div class="srt-day-header">Wed</div>
                <div class="srt-day-header">Thu</div>
                <div class="srt-day-header">Fri</div>
                <div class="srt-day-header">Sat</div>
            </div>
            
            <div class="srt-calendar-grid">
                <?php
                $first_day = mktime(0, 0, 0, $month, 1, $year);
                $days_in_month = date('t', $first_day);
                $day_of_week = date('w', $first_day);
                
                // Empty cells before month starts
                for ($i = 0; $i < $day_of_week; $i++) {
                    echo '<div class="srt-calendar-cell srt-empty"></div>';
                }
                
                // Days of the month
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $has_events = isset($events_by_day[$day]);
                    $class = $has_events ? 'srt-has-events' : '';
                    
                    echo '<div class="srt-calendar-cell ' . $class . '">';
                    echo '<div class="srt-day-number">' . $day . '</div>';
                    
                    if ($has_events) {
                        echo '<div class="srt-event-indicators">';
                        foreach ($events_by_day[$day] as $event) {
                            echo '<div class="srt-event-indicator" data-event-id="' . $event->id . '" title="' . esc_attr($event->title) . '">';
                            echo esc_html($event->title);
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                    
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Event form shortcode
 * Usage: [srt_event_form] or [srt_event_form id="123"]
 */
function srt_event_form_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => 0
    ), $atts);
    
    $event_id = intval($atts['id']);
    $event = null;
    $time_blocks = array();
    $flights = array();
    
    if ($event_id > 0) {
        $event = srt_get_event($event_id);
        $time_blocks = srt_get_time_blocks($event_id);
        $flights = srt_get_flights($event_id);
    }
    
    ob_start();
    ?>
    <div class="srt-event-form-wrapper">
        <form id="srt-event-form" class="srt-event-form">
            <?php wp_nonce_field('srt_save_event', 'srt_event_nonce'); ?>
            <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
            
            <h3><?php echo $event ? 'Edit Event' : 'Add New Event'; ?></h3>
            
            <div class="srt-form-group">
                <label for="event_title">Event Title *</label>
                <input type="text" id="event_title" name="title" required 
                       value="<?php echo $event ? esc_attr($event->title) : ''; ?>">
            </div>
            
            <div class="srt-form-group">
                <label for="event_date">Event Date *</label>
                <input type="date" id="event_date" name="event_date" required 
                       value="<?php echo $event ? esc_attr($event->event_date) : ''; ?>">
            </div>
            
            <div class="srt-form-group">
                <label for="event_location">Location</label>
                <input type="text" id="event_location" name="location" 
                       value="<?php echo $event ? esc_attr($event->location) : ''; ?>">
            </div>
            
            <div class="srt-form-group">
                <label for="event_description">Description</label>
                <textarea id="event_description" name="description" rows="4"><?php 
                    echo $event ? esc_textarea($event->description) : ''; 
                ?></textarea>
            </div>
            
            <!-- Time Blocks Section -->
            <div class="srt-form-section">
                <h4>Time Blocks</h4>
                <div id="srt-time-blocks">
                    <?php if ($time_blocks): ?>
                        <?php foreach ($time_blocks as $index => $block): ?>
                            <div class="srt-time-block" data-block-id="<?php echo $block->id; ?>">
                                <select name="time_blocks[<?php echo $index; ?>][block_type]" required>
                                    <option value="">Select Type</option>
                                    <option value="practice" <?php selected($block->block_type, 'practice'); ?>>Practice</option>
                                    <option value="travel" <?php selected($block->block_type, 'travel'); ?>>Travel</option>
                                    <option value="admin" <?php selected($block->block_type, 'admin'); ?>>Admin</option>
                                    <option value="performance" <?php selected($block->block_type, 'performance'); ?>>Performance</option>
                                    <option value="meal" <?php selected($block->block_type, 'meal'); ?>>Meal</option>
                                    <option value="other" <?php selected($block->block_type, 'other'); ?>>Other</option>
                                </select>
                                <input type="time" name="time_blocks[<?php echo $index; ?>][start_time]" 
                                       value="<?php echo esc_attr($block->start_time); ?>" required>
                                <input type="time" name="time_blocks[<?php echo $index; ?>][end_time]" 
                                       value="<?php echo esc_attr($block->end_time); ?>" required>
                                <input type="text" name="time_blocks[<?php echo $index; ?>][notes]" 
                                       placeholder="Notes" value="<?php echo esc_attr($block->notes); ?>">
                                <button type="button" class="srt-remove-block">Remove</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" id="srt-add-time-block" class="srt-button">Add Time Block</button>
            </div>
            
            <!-- Flights Section -->
            <div class="srt-form-section">
                <h4>Flight Information (Optional)</h4>
                <div id="srt-flights">
                    <?php if ($flights): ?>
                        <?php foreach ($flights as $index => $flight): ?>
                            <div class="srt-flight" data-flight-id="<?php echo $flight->id; ?>">
                                <h5>Leg <?php echo $flight->leg_number; ?></h5>
                                <input type="hidden" name="flights[<?php echo $index; ?>][leg_number]" value="<?php echo $flight->leg_number; ?>">
                                <input type="text" name="flights[<?php echo $index; ?>][departure_airport]" 
                                       placeholder="Departure Airport (e.g., ORD)" 
                                       value="<?php echo esc_attr($flight->departure_airport); ?>" required>
                                <input type="text" name="flights[<?php echo $index; ?>][arrival_airport]" 
                                       placeholder="Arrival Airport (e.g., LAX)" 
                                       value="<?php echo esc_attr($flight->arrival_airport); ?>" required>
                                <input type="datetime-local" name="flights[<?php echo $index; ?>][departure_time]" 
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($flight->departure_time)); ?>" required>
                                <input type="datetime-local" name="flights[<?php echo $index; ?>][arrival_time]" 
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($flight->arrival_time)); ?>" required>
                                <label>
                                    <input type="checkbox" name="flights[<?php echo $index; ?>][is_booked]" 
                                           value="1" <?php checked($flight->is_booked, 1); ?>>
                                    Booked
                                </label>
                                <input type="text" name="flights[<?php echo $index; ?>][booking_reference]" 
                                       placeholder="Booking Reference" 
                                       value="<?php echo esc_attr($flight->booking_reference); ?>">
                                <button type="button" class="srt-remove-flight">Remove</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" id="srt-add-flight" class="srt-button">Add Flight Leg</button>
            </div>
            
            <div class="srt-form-actions">
                <button type="submit" class="srt-button srt-button-primary">Save Event</button>
                <?php if ($event_id > 0): ?>
                    <button type="button" id="srt-delete-event" class="srt-button srt-button-danger" 
                            data-event-id="<?php echo $event_id; ?>">Delete Event</button>
                <?php endif; ?>
            </div>
        </form>
        
        <div id="srt-form-message"></div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Dashboard shortcode
 * Usage: [srt_dashboard]
 */
function srt_dashboard_shortcode($atts) {
    $unbooked_flights = srt_get_flights_needing_booking();
    $total_flights = srt_get_all_flights();
    $unbooked_count = count($unbooked_flights);
    $total_count = count($total_flights);
    
    ob_start();
    ?>
    <div class="srt-dashboard">
        <h3>Flight Booking Dashboard</h3>
        
        <div class="srt-stats">
            <div class="srt-stat-card">
                <div class="srt-stat-number"><?php echo $total_count; ?></div>
                <div class="srt-stat-label">Total Flights</div>
            </div>
            <div class="srt-stat-card">
                <div class="srt-stat-number srt-booked"><?php echo $total_count - $unbooked_count; ?></div>
                <div class="srt-stat-label">Booked</div>
            </div>
            <div class="srt-stat-card">
                <div class="srt-stat-number srt-unbooked"><?php echo $unbooked_count; ?></div>
                <div class="srt-stat-label">Need Booking</div>
            </div>
        </div>
        
        <?php if ($unbooked_flights): ?>
            <div class="srt-flights-list">
                <h4>Flights Needing Booking</h4>
                <table class="srt-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Date</th>
                            <th>Leg</th>
                            <th>Route</th>
                            <th>Departure</th>
                            <th>Arrival</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unbooked_flights as $flight): ?>
                            <tr>
                                <td><?php echo esc_html($flight->event_title); ?></td>
                                <td><?php echo date('M j, Y', strtotime($flight->event_date)); ?></td>
                                <td><?php echo $flight->leg_number; ?></td>
                                <td><?php echo esc_html($flight->departure_airport . ' → ' . $flight->arrival_airport); ?></td>
                                <td><?php echo date('M j, g:i A', strtotime($flight->departure_time)); ?></td>
                                <td><?php echo date('M j, g:i A', strtotime($flight->arrival_time)); ?></td>
                                <td>
                                    <button class="srt-mark-booked" data-flight-id="<?php echo $flight->id; ?>">
                                        Mark as Booked
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="srt-no-flights">All flights are booked! 🎉</p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
