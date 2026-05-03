<?php
/**
 * Template: Calendar View
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ftt-container">
    <?php
    $ftt_page_title  = __('Calendar', 'schedule-collaboration-tracking');
    $ftt_active_slug = 'calendar';
    include FTT_PLUGIN_DIR . 'templates/partials/nav.php';
    ?>

    <div class="ftt-calendar-header">
        <?php
        // Get user's groups (v2.1) - only for group selector, children loaded via REST API
        $user_groups = array();
        $selected_group_id = null;
        if (class_exists('FTT_Family_Groups')) {
            $current_user_id = get_current_user_id();
            $user_groups = FTT_Family_Groups::get_user_groups($current_user_id);
            if (isset($_GET['group']) && !empty($_GET['group'])) {
                $raw_group = sanitize_text_field(wp_unslash($_GET['group']));
                if (!ctype_digit($raw_group)) {
                    $selected_group_id = FTT_Family_Groups::resolve_group_token($raw_group);
                } else {
                    $selected_group_id = (int) $raw_group;
                }
            }
        }
        
        // Show group selector if user has multiple groups (v2.1)
        if (!empty($user_groups) && count($user_groups) > 1) : ?>
            <div class="ftt-group-selector-inline">
                <label for="ftt-calendar-group"><?php esc_html_e('Group:', 'schedule-collaboration-tracking'); ?></label>
                <select id="ftt-calendar-group" class="ftt-input">
                    <option value=""><?php esc_html_e('All Groups', 'schedule-collaboration-tracking'); ?></option>
                    <?php foreach ($user_groups as $group) : ?>
                        <option value="<?php echo esc_attr($group->id); ?>" <?php selected($group->id, $selected_group_id); ?>>
                            <?php echo esc_html($group->name); ?> (<?php echo esc_html($group->child_count); ?> <?php echo $group->child_count == 1 ? 'child' : 'children'; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        

        
        <!-- Event Category Filters -->
        <div class="ftt-event-filters">
            <button type="button" id="ftt-filter-toggle" class="button">
                <span class="dashicons dashicons-filter"></span>
                <?php esc_html_e('Filter Events', 'schedule-collaboration-tracking'); ?>
            </button>
            
            <div id="ftt-filter-panel" class="ftt-filter-panel" style="display: none;">
                <h4><?php esc_html_e('Show Event Types:', 'schedule-collaboration-tracking'); ?></h4>
                <div class="ftt-filter-categories">
                    <?php
                    // Get user's preferences
                    $user_preferences = get_user_meta($current_user_id, 'ftt_visible_event_categories', true);
                    if (!is_array($user_preferences)) {
                        $user_preferences = array(); // Show all by default
                    }
                    
                    // Get event categories
                    $categories = FTT_CPT::get_event_categories();
                    foreach ($categories as $cat_key => $category):
                        $is_checked = empty($user_preferences) || in_array($cat_key, $user_preferences);
                    ?>
                        <label class="ftt-filter-category">
                            <input type="checkbox" 
                                   name="event_category[]" 
                                   value="<?php echo esc_attr($cat_key); ?>"
                                   class="ftt-category-filter"
                                   <?php checked($is_checked); ?>>
                            <span class="ftt-category-icon"><?php echo $category['icon']; ?></span>
                            <span class="ftt-category-label"><?php echo esc_html($category['label']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                
                <div class="ftt-filter-actions">
                    <button type="button" id="ftt-filter-apply" class="button button-primary">
                        <?php esc_html_e('Apply Filters', 'schedule-collaboration-tracking'); ?>
                    </button>
                    <button type="button" id="ftt-filter-reset" class="button">
                        <?php esc_html_e('Reset', 'schedule-collaboration-tracking'); ?>
                    </button>
                </div>
            </div>
        </div>
        
    </div>
    
    <?php
    // Show calendar subscription QR code if user is logged in
    if (is_user_logged_in()) :
        $current_user = wp_get_current_user();
        $user_token = get_user_meta($current_user->ID, 'ftt_calendar_token', true);
        
        // Generate token if doesn't exist
        if (empty($user_token)) {
            $user_token = wp_generate_password(32, false);
            update_user_meta($current_user->ID, 'ftt_calendar_token', $user_token);
        }
        
        $ical_url = add_query_arg(
            array(
                'ftt_calendar' => '1',
                'token' => $user_token,
                'user_id' => $current_user->ID
            ),
            home_url('/')
        );
        
        // Generate webcal URL for QR code - iOS recognizes this as calendar subscription
        $webcal_url = preg_replace('/^https?:\/\//', 'webcal://', $ical_url);
        
        // Generate QR code URL using webcal:// protocol
        $qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query(array(
            'size' => '200x200',
            'data' => $webcal_url
        ));
        ?>
        
        <div class="ftt-cal-subscribe-bar">
            <button type="button" id="ftt-open-subscribe-modal" class="button">
                <span class="dashicons dashicons-smartphone"></span>
                <?php esc_html_e('Add this calendar to your device', 'schedule-collaboration-tracking'); ?>
            </button>
        </div>

        <!-- Subscribe modal -->
        <div id="ftt-subscribe-modal" class="ftt-modal" style="display:none;">
            <div class="ftt-modal-content ftt-subscribe-modal-content">
                <div class="ftt-modal-header">
                    <h2><?php esc_html_e('Add to Your Calendar App', 'schedule-collaboration-tracking'); ?></h2>
                    <button type="button" class="ftt-modal-close-x" id="ftt-close-subscribe-modal" aria-label="<?php esc_attr_e('Close', 'schedule-collaboration-tracking'); ?>">&#x2715;</button>
                </div>
                <div class="ftt-modal-body">
                    <?php echo do_shortcode('[ftt_calendar_subscribe]'); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Child filter - populated by JavaScript via REST API -->
    <div class="ftt-child-filter" id="ftt-child-filter" style="display:none;">
        <div class="ftt-child-filter-header">
            <h3><?php esc_html_e('Show Children', 'schedule-collaboration-tracking'); ?></h3>
            <button type="button" id="ftt-select-all-children" class="button button-small">
                <?php esc_html_e('Select All', 'schedule-collaboration-tracking'); ?>
            </button>
        </div>
        <div class="ftt-filter-list" id="ftt-child-filter-list">
            <!-- Children loaded via REST API -->
            <div class="ftt-loading"><?php esc_html_e('Loading children...', 'schedule-collaboration-tracking'); ?></div>
        </div>
    </div>
    
    <div id="ftt-calendar"></div>
    
    <div class="ftt-calendar-legend">
        <h3><?php esc_html_e('Event Types', 'schedule-collaboration-tracking'); ?></h3>
        <div class="ftt-legend-grid">
            <?php
            $event_types = FTT_CPT::get_event_types();
            foreach ($event_types as $key => $label) :
                ?>
                <div class="ftt-legend-item">
                    <span class="ftt-legend-color ftt-event-type-<?php echo esc_attr($key); ?>"></span>
                    <span class="ftt-legend-label"><?php echo esc_html($label); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    console.log('FTT CALENDAR: Filter handlers loaded');
    
    // Select All Children button
    $('#ftt-select-all-children').on('click', function() {
        const allChecked = $('.ftt-child-toggle:checked').length === $('.ftt-child-toggle').length;
        $('.ftt-child-toggle').prop('checked', !allChecked);
        
        // Update button text
        $(this).text(allChecked ? 'Select All' : 'Deselect All');
        
        // Trigger calendar refresh if calendar is loaded
        if (typeof window.fttCalendar !== 'undefined' && window.fttCalendar.refetchEvents) {
            window.fttCalendar.refetchEvents();
        }
    });
    
    // Toggle filter panel
    $('#ftt-filter-toggle').on('click', function() {
        $(this).toggleClass('active');
        $('#ftt-filter-panel').slideToggle(300);
    });
    
    // Apply filters
    $('#ftt-filter-apply').on('click', function() {
        console.log('Applying event category filters');
        
        var visibleCategories = [];
        $('.ftt-category-filter:checked').each(function() {
            visibleCategories.push($(this).val());
        });
        
        console.log('Visible categories:', visibleCategories);
        
        // Save preferences via REST API
        $.ajax({
            url: '<?php echo esc_url(rest_url('ftt/v1/save-event-preferences')); ?>',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            contentType: 'application/json',
            data: JSON.stringify({ visible_categories: visibleCategories }),
            success: function(response) {
                console.log('Filters saved, reloading calendar');
                // Reload calendar to apply filters
                if (typeof window.fttCalendar !== 'undefined' && window.fttCalendar.refetchEvents) {
                    window.fttCalendar.refetchEvents();
                } else {
                    location.reload();
                }
            },
            error: function(xhr) {
                console.error('Error saving filters:', xhr.responseJSON);
                alert('Failed to save filters. Please try again.');
            }
        });
    });
    
    // Reset filters
    $('#ftt-filter-reset').on('click', function() {
        console.log('Resetting filters');
        $('.ftt-category-filter').prop('checked', true);
        $('#ftt-filter-apply').click();
    });

    // ── Mobile: collapsible Event Types legend ────────────────────────
    if ($(window).width() <= 768) {
        var STORE_KEY = 'ftt_mob_collapse';
        var state;
        try { state = JSON.parse(localStorage.getItem(STORE_KEY) || '{}'); } catch(e) { state = {}; }
        function saveLegendState() {
            try { localStorage.setItem(STORE_KEY, JSON.stringify(state)); } catch(e) {}
        }

        var $h3   = $('.ftt-calendar-legend h3');
        var $grid = $('.ftt-legend-grid');
        var collapsed = ('legend' in state) ? state['legend'] : true; // default: collapsed

        $h3.addClass('ftt-mob-toggle-hdr');
        var $chev = $('<span class="ftt-mob-chevron" aria-hidden="true"></span>').appendTo($h3);
        state['legend'] = collapsed;
        if (collapsed) { $grid.hide(); $chev.text('›'); } else { $chev.text('˅'); }

        $h3.on('click', function() {
            state['legend'] = !state['legend'];
            $grid.slideToggle(180);
            $chev.text(state['legend'] ? '›' : '˅');
            saveLegendState();
        });
    }

    console.log('FTT CALENDAR: Filter handlers attached');
});
</script>

