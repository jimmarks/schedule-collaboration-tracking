<?php
/**
 * Template: Event List
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="srt-container">
    <div class="srt-event-list">
        <h2><?php esc_html_e('Upcoming Events', 'schedule-collaboration-tracking'); ?></h2>
        
        <?php if ($query->have_posts()) : ?>
            <?php while ($query->have_posts()) : $query->the_post(); ?>
                <?php
                $event_id = get_the_ID();
                $start_datetime = get_post_meta($event_id, 'start_datetime', true);
                $end_datetime = get_post_meta($event_id, 'end_datetime', true);
                $event_type = get_post_meta($event_id, 'event_type', true);
                $location_name = get_post_meta($event_id, 'location_name', true);
                $travel_needed = get_post_meta($event_id, 'travel_needed', true);
                $flight_needed = get_post_meta($event_id, 'flight_needed', true);
                $member_id = get_post_meta($event_id, 'member_id', true);
                
                $event_types = SRT_CPT::get_event_types();
                $event_type_label = $event_types[$event_type] ?? $event_type;
                
                $start_date = $start_datetime ? date_create($start_datetime) : null;
                $end_date = $end_datetime ? date_create($end_datetime) : null;
                
                // Get member name if available
                $member_name = '';
                if ($member_id) {
                    $member_user = get_userdata($member_id);
                    if ($member_user) {
                        $member_name = $member_user->display_name;
                    }
                }
                ?>
                
                <div class="srt-event-item" data-event-id="<?php echo esc_attr($event_id); ?>">
                    <?php if ($member_name) : ?>
                        <div class="srt-member-name"><?php echo esc_html($member_name); ?></div>
                    <?php endif; ?>
                    <h3><?php the_title(); ?></h3>
                    
                    <div class="srt-event-meta">
                        <?php if ($start_date) : ?>
                            <span>
                                <strong><?php esc_html_e('Date:', 'schedule-collaboration-tracking'); ?></strong>
                                <?php echo esc_html($start_date->format('F j, Y g:i A')); ?>
                                <?php if ($end_date) : ?>
                                    - <?php echo esc_html($end_date->format('g:i A')); ?>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                        
                        <span>
                            <strong><?php esc_html_e('Type:', 'schedule-collaboration-tracking'); ?></strong>
                            <?php echo esc_html($event_type_label); ?>
                        </span>
                        
                        <?php if ($location_name) : ?>
                            <span>
                                <strong><?php esc_html_e('Location:', 'schedule-collaboration-tracking'); ?></strong>
                                <?php echo esc_html($location_name); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="srt-event-badges">
                        <?php if ($travel_needed) : ?>
                            <span class="srt-event-badge srt-event-badge-travel">
                                <?php esc_html_e('Travel', 'schedule-collaboration-tracking'); ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($flight_needed) : ?>
                            <span class="srt-event-badge srt-event-badge-flight">
                                <?php esc_html_e('Flight', 'schedule-collaboration-tracking'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (current_user_can('edit_posts')) : ?>
                        <p>
                            <a href="<?php echo esc_url(get_permalink() . '?event_id=' . $event_id); ?>" class="button">
                                <?php esc_html_e('Edit', 'schedule-collaboration-tracking'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else : ?>
            <p class="srt-no-events"><?php esc_html_e('No upcoming events found.', 'schedule-collaboration-tracking'); ?></p>
        <?php endif; ?>
    </div>
</div>
