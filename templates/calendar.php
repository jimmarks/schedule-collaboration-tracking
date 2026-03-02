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
    <div class="ftt-calendar-header">
        <h2><?php esc_html_e('Family Calendar', 'schedule-collaboration-tracking'); ?></h2>
        
        <?php
        // Show member selector for parents
        $current_user_id = get_current_user_id();
        $children = FTT_Roles::get_children($current_user_id);
        
        if (!empty($children)) : ?>
            <div class="ftt-member-selector">
                <label for="ftt-calendar-member"><?php esc_html_e('View Calendar For:', 'schedule-collaboration-tracking'); ?></label>
                <select id="ftt-calendar-member" class="ftt-input">
                    <option value=""><?php esc_html_e('All Children', 'schedule-collaboration-tracking'); ?></option>
                    <?php foreach ($children as $child_id) :
                        $child = get_user_by('id', $child_id);
                        if ($child) : ?>
                            <option value="<?php echo esc_attr($child_id); ?>">
                                <?php echo esc_html($child->display_name); ?>
                            </option>
                        <?php endif;
                    endforeach; ?>
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
        
        <?php if (current_user_can('edit_posts')) : ?>
            <?php
            $event_form_url = FTT_Pages::get_page_url('event_form');
            if ($event_form_url) :
            ?>
            <p>
                <a href="<?php echo esc_url($event_form_url); ?>" class="button button-primary">
                    <?php esc_html_e('Add New Event', 'schedule-collaboration-tracking'); ?>
                </a>
            </p>
            <?php endif; ?>
        <?php endif; ?>
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
        
        <div class="ftt-qr-code-section">
            <button type="button" class="ftt-qr-toggle button">
                <span class="dashicons dashicons-smartphone"></span>
                <?php esc_html_e('Subscribe on Mobile Device', 'schedule-collaboration-tracking'); ?>
            </button>
            
            <div class="ftt-qr-code-content" style="display: none;">
                <div class="ftt-qr-code-inner">
                    <h4><?php esc_html_e('Scan QR Code', 'schedule-collaboration-tracking'); ?></h4>
                    <p><?php esc_html_e('Use your phone\'s camera to scan this QR code and add the calendar to your device:', 'schedule-collaboration-tracking'); ?></p>
                    
                    <div class="ftt-qr-code-image">
                        <img src="<?php echo esc_url($qr_code_url); ?>" alt="<?php esc_attr_e('Calendar Subscription QR Code', 'schedule-collaboration-tracking'); ?>" width="200" height="200" />
                    </div>
                    
                    <div class="ftt-qr-instructions">
                        <p><strong><?php esc_html_e('iOS (iPhone/iPad):', 'schedule-collaboration-tracking'); ?></strong></p>
                        <ol style="margin: 5px 0 10px 20px; font-size: 14px;">
                            <li><?php esc_html_e('Make sure your phone is unlocked', 'schedule-collaboration-tracking'); ?></li>
                            <li><?php esc_html_e('Scan QR code with Camera app', 'schedule-collaboration-tracking'); ?></li>
                            <li><?php esc_html_e('Tap the notification banner', 'schedule-collaboration-tracking'); ?></li>
                            <li><?php esc_html_e('Tap "Subscribe" to add the calendar', 'schedule-collaboration-tracking'); ?></li>
                        </ol>
                        <p><strong><?php esc_html_e('Android:', 'schedule-collaboration-tracking'); ?></strong></p>
                        <ol style="margin: 5px 0 10px 20px; font-size: 14px;">
                            <li><?php esc_html_e('Open Google Chrome or Camera app', 'schedule-collaboration-tracking'); ?></li>
                            <li><?php esc_html_e('Scan the QR code', 'schedule-collaboration-tracking'); ?></li>
                            <li><?php esc_html_e('Tap the webcal:// link that appears', 'schedule-collaboration-tracking'); ?></li>
                            <li><?php esc_html_e('Choose "Google Calendar" to subscribe', 'schedule-collaboration-tracking'); ?></li>
                        </ol>
                        <p style="font-size: 13px; color: #666;"><em><?php esc_html_e('Note: If the QR code doesn\'t work, copy the Webcal URL below and paste it into your device\'s calendar settings.', 'schedule-collaboration-tracking'); ?></em></p>
                    </div>
                    
                    <p class="ftt-qr-alternative">
                        <strong><?php esc_html_e('Webcal URL:', 'schedule-collaboration-tracking'); ?></strong><br>
                        <small><?php esc_html_e('(For manual subscription - use in Settings → Calendar → Add Subscribed Calendar)', 'schedule-collaboration-tracking'); ?></small><br>
                        <input type="text" readonly value="<?php echo esc_attr($webcal_url); ?>" class="ftt-webcal-url" onclick="this.select();" />
                        <button type="button" class="button ftt-copy-url" data-url="<?php echo esc_attr($webcal_url); ?>">
                            <?php esc_html_e('Copy', 'schedule-collaboration-tracking'); ?>
                        </button>
                    </p>
                    <p class="ftt-qr-alternative" style="padding-top: 10px; border-top: 0;">
                        <strong><?php esc_html_e('HTTPS URL:', 'schedule-collaboration-tracking'); ?></strong><br>
                        <small><?php esc_html_e('(Direct link - opens in browser)', 'schedule-collaboration-tracking'); ?></small><br>
                        <input type="text" readonly value="<?php echo esc_attr($ical_url); ?>" class="ftt-webcal-url" onclick="this.select();" />
                        <button type="button" class="button ftt-copy-webcal" data-url="<?php echo esc_attr($ical_url); ?>">
                            <?php esc_html_e('Copy', 'schedule-collaboration-tracking'); ?>
                        </button>
                    </p>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.ftt-qr-toggle').on('click', function() {
                var $content = $('.ftt-qr-code-content');
                $content.slideToggle(300);
                $(this).toggleClass('active');
            });
            
            $('.ftt-copy-url, .ftt-copy-webcal').on('click', function() {
                var url = $(this).data('url');
                var $input = $(this).prev('.ftt-webcal-url');
                $input.select();
                
                try {
                    document.execCommand('copy');
                    var $btn = $(this);
                    var originalText = $btn.text();
                    $btn.text('<?php esc_html_e('Copied!', 'schedule-collaboration-tracking'); ?>');
                    setTimeout(function() {
                        $btn.text(originalText);
                    }, 2000);
                } catch (err) {
                    alert('<?php esc_html_e('Please manually copy the URL', 'schedule-collaboration-tracking'); ?>');
                }
            });
        });
        </script>
    <?php endif; ?>
    
    <?php
    // Child color filter (for parents with multiple children)
    if (!empty($children) && count($children) > 1 && class_exists('FTT_Child_Colors')) :
        $children_with_colors = FTT_Child_Colors::get_children_with_colors($current_user_id);
        ?>
        <div class="ftt-child-filter">
            <h3><?php esc_html_e('Show Children', 'schedule-collaboration-tracking'); ?></h3>
            <div class="ftt-filter-list">
                <?php foreach ($children_with_colors as $child) : ?>
                    <label class="ftt-filter-item">
                        <input 
                            type="checkbox" 
                            class="ftt-child-toggle" 
                            data-child-id="<?php echo esc_attr($child['id']); ?>"
                            checked 
                        />
                        <span 
                            class="ftt-color-indicator" 
                            style="background-color: <?php echo esc_attr($child['color']['hex']); ?>;"
                        ></span>
                        <span class="ftt-child-name"><?php echo esc_html($child['name']); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
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

<style>
.ftt-qr-code-section {
    margin: 20px 0;
    padding: 0;
}

.ftt-qr-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    font-size: 14px;
    cursor: pointer;
    background: #0073aa;
    color: #fff;
    border: none;
    border-radius: 4px;
    transition: background 0.2s;
}

.ftt-qr-toggle:hover {
    background: #005177;
}

.ftt-qr-toggle.active {
    background: #005177;
}

.ftt-qr-toggle .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}

.ftt-qr-code-content {
    margin-top: 15px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 0;
    overflow: hidden;
}

.ftt-qr-code-inner {
    padding: 20px;
}

.ftt-qr-code-inner h4 {
    margin: 0 0 10px 0;
    font-size: 18px;
    color: #23282d;
}

.ftt-qr-code-image {
    text-align: center;
    margin: 20px 0;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 4px;
}

.ftt-qr-code-image img {
    display: inline-block;
    border: 3px solid #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-radius: 4px;
}

.ftt-qr-instructions {
    background: #f0f6fc;
    padding: 15px;
    border-left: 4px solid #0073aa;
    margin: 20px 0;
    border-radius: 4px;
}

.ftt-qr-instructions p {
    margin: 8px 0;
    font-size: 14px;
}

.ftt-qr-alternative {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.ftt-webcal-url {
    width: 100%;
    max-width: 500px;
    padding: 8px;
    font-family: monospace;
    font-size: 12px;
    border: 1px solid #ddd;
    border-radius: 3px;
    margin: 10px 0;
}

.ftt-copy-url {
    margin-left: 10px;
}

.ftt-calendar-legend {
    margin-top: 30px;
    padding: 20px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.ftt-legend-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
    margin-top: 15px;
}

.ftt-legend-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.ftt-legend-color {
    width: 20px;
    height: 20px;
    border-radius: 3px;
}

.ftt-legend-label {
    font-size: 14px;
}

/* Event Category Filters */
.ftt-event-filters {
    margin: 20px 0;
    position: relative;
}

#ftt-filter-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    font-size: 14px;
    cursor: pointer;
    background: #6A3E8E;
    color: #fff;
    border: none;
    border-radius: 4px;
    transition: background 0.2s;
}

#ftt-filter-toggle:hover {
    background: #5B347A;
}

#ftt-filter-toggle.active {
    background: #5B347A;
}

#ftt-filter-toggle .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}

.ftt-filter-panel {
    margin-top: 15px;
    background: #fff;
    border: 2px solid #6A3E8E;
    border-radius: 8px;
    padding: 20px;
}

.ftt-filter-panel h4 {
    margin: 0 0 15px 0;
    color: #6A3E8E;
    font-size: 16px;
}

.ftt-filter-categories {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.ftt-filter-category {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    background: white;
}

.ftt-filter-category:hover {
    border-color: #6A3E8E;
    background: #f5f5f5;
}

.ftt-filter-category input[type="checkbox"] {
    margin: 0;
}

.ftt-filter-category input[type="checkbox"]:checked + .ftt-category-icon {
    transform: scale(1.2);
}

.ftt-filter-category .ftt-category-icon {
    font-size: 20px;
    transition: transform 0.2s;
}

.ftt-filter-category .ftt-category-label {
    flex: 1;
    font-weight: 500;
    font-size: 14px;
}

.ftt-filter-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding-top: 15px;
    border-top: 1px solid #e0e0e0;
}

.ftt-filter-actions .button {
    min-width: 100px;
}
</style>

<script>
jQuery(document).ready(function($) {
    console.log('FTT CALENDAR: Filter handlers loaded');
    
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
            url: '/wp-json/ftt/v1/save-event-preferences',
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
    
    console.log('FTT CALENDAR: Filter handlers attached');
});
</script>

