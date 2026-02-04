<?php
/**
 * Template: Calendar View
 *
 * @package Summer_Regiment_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="srt-container">
    <div class="srt-calendar-header">
        <h2><?php esc_html_e('Schedule Schedule', 'schedule-collaboration-tracking'); ?></h2>
        
        <?php
        // Show member selector for parents
        $current_user_id = get_current_user_id();
        $children = SRT_Roles::get_children($current_user_id);
        
        if (!empty($children)) : ?>
            <div class="srt-member-selector">
                <label for="srt-calendar-member"><?php esc_html_e('View Calendar For:', 'schedule-collaboration-tracking'); ?></label>
                <select id="srt-calendar-member" class="srt-input">
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
        
        <?php if (current_user_can('edit_posts')) : ?>
            <?php
            $event_form_url = SRT_Pages::get_page_url('event_form');
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
        $user_token = get_user_meta($current_user->ID, 'srt_calendar_token', true);
        
        // Generate token if doesn't exist
        if (empty($user_token)) {
            $user_token = wp_generate_password(32, false);
            update_user_meta($current_user->ID, 'srt_calendar_token', $user_token);
        }
        
        $ical_url = add_query_arg(
            array(
                'srt_calendar' => '1',
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
        
        <div class="srt-qr-code-section">
            <button type="button" class="srt-qr-toggle button">
                <span class="dashicons dashicons-smartphone"></span>
                <?php esc_html_e('Subscribe on Mobile Device', 'schedule-collaboration-tracking'); ?>
            </button>
            
            <div class="srt-qr-code-content" style="display: none;">
                <div class="srt-qr-code-inner">
                    <h4><?php esc_html_e('Scan QR Code', 'schedule-collaboration-tracking'); ?></h4>
                    <p><?php esc_html_e('Use your phone\'s camera to scan this QR code and add the calendar to your device:', 'schedule-collaboration-tracking'); ?></p>
                    
                    <div class="srt-qr-code-image">
                        <img src="<?php echo esc_url($qr_code_url); ?>" alt="<?php esc_attr_e('Calendar Subscription QR Code', 'schedule-collaboration-tracking'); ?>" width="200" height="200" />
                    </div>
                    
                    <div class="srt-qr-instructions">
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
                    
                    <p class="srt-qr-alternative">
                        <strong><?php esc_html_e('Webcal URL:', 'schedule-collaboration-tracking'); ?></strong><br>
                        <small><?php esc_html_e('(For manual subscription - use in Settings → Calendar → Add Subscribed Calendar)', 'schedule-collaboration-tracking'); ?></small><br>
                        <input type="text" readonly value="<?php echo esc_attr($webcal_url); ?>" class="srt-webcal-url" onclick="this.select();" />
                        <button type="button" class="button srt-copy-url" data-url="<?php echo esc_attr($webcal_url); ?>">
                            <?php esc_html_e('Copy', 'schedule-collaboration-tracking'); ?>
                        </button>
                    </p>
                    <p class="srt-qr-alternative" style="padding-top: 10px; border-top: 0;">
                        <strong><?php esc_html_e('HTTPS URL:', 'schedule-collaboration-tracking'); ?></strong><br>
                        <small><?php esc_html_e('(Direct link - opens in browser)', 'schedule-collaboration-tracking'); ?></small><br>
                        <input type="text" readonly value="<?php echo esc_attr($ical_url); ?>" class="srt-webcal-url" onclick="this.select();" />
                        <button type="button" class="button srt-copy-webcal" data-url="<?php echo esc_attr($ical_url); ?>">
                            <?php esc_html_e('Copy', 'schedule-collaboration-tracking'); ?>
                        </button>
                    </p>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.srt-qr-toggle').on('click', function() {
                var $content = $('.srt-qr-code-content');
                $content.slideToggle(300);
                $(this).toggleClass('active');
            });
            
            $('.srt-copy-url, .srt-copy-webcal').on('click', function() {
                var url = $(this).data('url');
                var $input = $(this).prev('.srt-webcal-url');
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
    
    <div id="srt-calendar"></div>
    
    <div class="srt-calendar-legend">
        <h3><?php esc_html_e('Event Types', 'schedule-collaboration-tracking'); ?></h3>
        <div class="srt-legend-grid">
            <?php
            $event_types = SRT_CPT::get_event_types();
            foreach ($event_types as $key => $label) :
                ?>
                <div class="srt-legend-item">
                    <span class="srt-legend-color srt-event-type-<?php echo esc_attr($key); ?>"></span>
                    <span class="srt-legend-label"><?php echo esc_html($label); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.srt-qr-code-section {
    margin: 20px 0;
    padding: 0;
}

.srt-qr-toggle {
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

.srt-qr-toggle:hover {
    background: #005177;
}

.srt-qr-toggle.active {
    background: #005177;
}

.srt-qr-toggle .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}

.srt-qr-code-content {
    margin-top: 15px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 0;
    overflow: hidden;
}

.srt-qr-code-inner {
    padding: 20px;
}

.srt-qr-code-inner h4 {
    margin: 0 0 10px 0;
    font-size: 18px;
    color: #23282d;
}

.srt-qr-code-image {
    text-align: center;
    margin: 20px 0;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 4px;
}

.srt-qr-code-image img {
    display: inline-block;
    border: 3px solid #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-radius: 4px;
}

.srt-qr-instructions {
    background: #f0f6fc;
    padding: 15px;
    border-left: 4px solid #0073aa;
    margin: 20px 0;
    border-radius: 4px;
}

.srt-qr-instructions p {
    margin: 8px 0;
    font-size: 14px;
}

.srt-qr-alternative {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.srt-webcal-url {
    width: 100%;
    max-width: 500px;
    padding: 8px;
    font-family: monospace;
    font-size: 12px;
    border: 1px solid #ddd;
    border-radius: 3px;
    margin: 10px 0;
}

.srt-copy-url {
    margin-left: 10px;
}

.srt-calendar-legend {
    margin-top: 30px;
    padding: 20px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.srt-legend-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
    margin-top: 15px;
}

.srt-legend-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.srt-legend-color {
    width: 20px;
    height: 20px;
    border-radius: 3px;
}

.srt-legend-label {
    font-size: 14px;
}
</style>
