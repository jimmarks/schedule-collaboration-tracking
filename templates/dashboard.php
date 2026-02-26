<?php
/**
 * Template: Dashboard (Main Hub)
 *
 * @package Summer_Regiment_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$is_logged_in = is_user_logged_in();
$is_member = $is_logged_in && SRT_Roles::is_member($current_user->ID);
$is_parent = $is_logged_in && SRT_Roles::is_parent($current_user->ID);
$is_admin = $is_logged_in && current_user_can('manage_options');

// Get page URLs
$calendar_url = SRT_Pages::get_page_url('calendar');
$event_list_url = SRT_Pages::get_page_url('event_list');
$event_form_url = SRT_Pages::get_page_url('event_form');
?>

<script>
console.log('=== Dashboard User Roles ===');
console.log('User ID:', <?php echo $is_logged_in ? $current_user->ID : 0; ?>);
console.log('Is Logged In:', <?php echo $is_logged_in ? 'true' : 'false'; ?>);
console.log('Is Member:', <?php echo $is_member ? 'true' : 'false'; ?>);
console.log('Is Parent:', <?php echo $is_parent ? 'true' : 'false'; ?>);
console.log('Is Admin:', <?php echo $is_admin ? 'true' : 'false'; ?>);
console.log('Showing view:', <?php 
    if (!$is_logged_in) echo '"Not logged in"';
    elseif ($is_parent && !$is_member) echo '"Parent only"';
    elseif ($is_member) echo '"Member"';
    else echo '"Other"';
?>);
console.log('============================');
</script>

<div class="srt-container">
    <!-- Main Navigation -->
    <div class="srt-main-nav">
        <div class="srt-nav-logo">
            <h1>🎺 <?php esc_html_e('Schedule Dashboard', 'schedule-collaboration-tracking'); ?></h1>
        </div>
        <nav class="srt-nav-menu">
            <?php if ($calendar_url): ?>
                <a href="<?php echo esc_url($calendar_url); ?>" class="srt-nav-link">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php esc_html_e('Calendar', 'schedule-collaboration-tracking'); ?>
                </a>
            <?php endif; ?>
            
            <?php if ($event_list_url): ?>
                <a href="<?php echo esc_url($event_list_url); ?>" class="srt-nav-link">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php 
                    // Change label based on user role
                    if ($is_admin) {
                        esc_html_e('All Events', 'schedule-collaboration-tracking');
                    } else {
                        esc_html_e('My Events', 'schedule-collaboration-tracking');
                    }
                    ?>
                </a>
            <?php endif; ?>
            
            <?php if ($is_admin && $event_form_url): ?>
                <a href="<?php echo esc_url($event_form_url); ?>" class="srt-nav-link">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e('Manage Events', 'schedule-collaboration-tracking'); ?>
                </a>
            <?php endif; ?>
            
            <?php if ($is_admin): ?>
                <a href="<?php echo admin_url('edit.php?post_type=srt_event&page=srt-manage-users'); ?>" class="srt-nav-link">
                    <span class="dashicons dashicons-groups"></span>
                    <?php esc_html_e('Manage Users', 'schedule-collaboration-tracking'); ?>
                </a>
            <?php endif; ?>
            
            <?php if ($is_logged_in): ?>
                <a href="<?php echo wp_logout_url(home_url()); ?>" class="srt-nav-link srt-nav-logout">
                    <span class="dashicons dashicons-exit"></span>
                    <?php esc_html_e('Logout', 'schedule-collaboration-tracking'); ?>
                </a>
            <?php else: ?>
                <a href="<?php echo wp_login_url(get_permalink()); ?>" class="srt-nav-link srt-nav-login">
                    <span class="dashicons dashicons-admin-users"></span>
                    <?php esc_html_e('Login', 'schedule-collaboration-tracking'); ?>
                </a>
            <?php endif; ?>
        </nav>
    </div>

    <?php if (!$is_logged_in): ?>
        <!-- Public View: Welcome + Register -->
        <div class="srt-welcome-section">
            <div class="srt-welcome-content">
                <h2><?php esc_html_e('Welcome to Schedule Tracking', 'schedule-collaboration-tracking'); ?></h2>
                <p class="srt-welcome-text">
                    <?php esc_html_e('Track events, manage travel, and get flight price alerts for schedule members and families.', 'schedule-collaboration-tracking'); ?>
                </p>
                <div class="srt-welcome-actions">
                    <a href="<?php echo wp_login_url(get_permalink()); ?>" class="button button-primary button-large">
                        <?php esc_html_e('Login', 'schedule-collaboration-tracking'); ?>
                    </a>
                    <?php
                    // Check if registration page exists
                    $pages = get_pages(array('meta_key' => '_wp_page_template'));
                    foreach ($pages as $page) {
                        if (has_shortcode($page->post_content, 'srt_register')) {
                            echo '<a href="' . esc_url(get_permalink($page->ID)) . '" class="button button-secondary button-large">' . esc_html__('Register', 'schedule-collaboration-tracking') . '</a>';
                            break;
                        }
                    }
                    ?>
                </div>
            </div>
            
            <?php if ($calendar_url): ?>
                <div class="srt-public-calendar">
                    <h3><?php esc_html_e('Upcoming Events', 'schedule-collaboration-tracking'); ?></h3>
                    <p><?php esc_html_e('View all schedule events on the calendar:', 'schedule-collaboration-tracking'); ?></p>
                    <a href="<?php echo esc_url($calendar_url); ?>" class="button"><?php esc_html_e('View Full Calendar', 'schedule-collaboration-tracking'); ?></a>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($is_parent && !$is_member): ?>
        <!-- Parent View: Children's Events & Travel -->
        <div class="srt-parent-dashboard">
            <div class="srt-user-header">
                <h2><?php printf(esc_html__('Welcome, %s', 'schedule-collaboration-tracking'), esc_html($current_user->display_name)); ?></h2>
                <p class="srt-user-role"><?php esc_html_e('Parent Dashboard', 'schedule-collaboration-tracking'); ?></p>
            </div>

            <?php
            $children = SRT_Roles::get_children($current_user->ID);
            if (!empty($children)):
            ?>
                <div class="srt-family-section">
                    <h3><?php esc_html_e('Your Members', 'schedule-collaboration-tracking'); ?></h3>
                    <div class="srt-children-list">
                        <?php foreach ($children as $child_id):
                            $child = get_userdata($child_id);
                            if (!$child) continue;
                            $section = get_user_meta($child_id, 'srt_section', true);
                            $instrument = get_user_meta($child_id, 'srt_instrument', true);
                        ?>
                            <div class="srt-child-card">
                                <h4><?php echo esc_html($child->display_name); ?></h4>
                                <?php if ($section): ?>
                                    <p class="srt-child-info">
                                        <strong><?php esc_html_e('Section:', 'schedule-collaboration-tracking'); ?></strong> 
                                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $section))); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($instrument): ?>
                                    <p class="srt-child-info">
                                        <strong><?php esc_html_e('Instrument:', 'schedule-collaboration-tracking'); ?></strong> 
                                        <?php echo esc_html($instrument); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="srt-notice srt-notice-info">
                    <p><?php esc_html_e('No children linked to your account yet.', 'schedule-collaboration-tracking'); ?></p>
                    <?php if ($is_admin): ?>
                        <p><a href="<?php echo admin_url('edit.php?post_type=srt_event&page=srt-manage-users'); ?>"><?php esc_html_e('Manage Relationships', 'schedule-collaboration-tracking'); ?></a></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Parent's Travel Dashboard (children's travel) -->
            <div id="srt-dashboard" class="srt-dashboard" data-user-role="parent">
                <div class="srt-dashboard-header">
                    <h3><?php esc_html_e('Family Travel Overview', 'schedule-collaboration-tracking'); ?></h3>
                    <?php 
                    $subscribe_page = get_pages(array('meta_key' => '_wp_page_template', 'number' => 1));
                    foreach ($subscribe_page as $page) {
                        if (has_shortcode($page->post_content, 'srt_calendar_subscribe')) {
                            echo '<a href="' . esc_url(get_permalink($page->ID)) . '" class="button button-secondary"><span class="dashicons dashicons-calendar"></span> ' . esc_html__('Subscribe to Calendar', 'schedule-collaboration-tracking') . '</a>';
                            break;
                        }
                    }
                    ?>
                </div>
                
                <div class="srt-dashboard-section">
                    <h4><?php esc_html_e('Flights Needed (Upcoming)', 'schedule-collaboration-tracking'); ?></h4>
                    <div id="srt-flights-needed" class="srt-dashboard-content">
                        <div class="srt-loading"></div>
                    </div>
                </div>
                
                <div class="srt-dashboard-section">
                    <h4><?php esc_html_e('💰 Linked Flights (Price Comparison)', 'schedule-collaboration-tracking'); ?></h4>
                    <div id="srt-linked-flights" class="srt-dashboard-content">
                        <div class="srt-loading"></div>
                    </div>
                </div>
                
                <div class="srt-dashboard-section">
                    <h4><?php esc_html_e('Upcoming Travel (Next 30 Days)', 'schedule-collaboration-tracking'); ?></h4>
                    <div id="srt-upcoming-travel" class="srt-dashboard-content">
                        <div class="srt-loading"></div>
                    </div>
                </div>
                
                <!-- Add Child by Code Section (Parent View) -->
                <div class="srt-dashboard-section">
                    <h4><span class="dashicons dashicons-groups"></span> <?php esc_html_e('Add Child by Code', 'schedule-collaboration-tracking'); ?></h4>
                    <div class="srt-dashboard-content">
                        <div class="srt-code-entry-section">
                            <p><?php esc_html_e('Enter the code your child shared with you to link their account.', 'schedule-collaboration-tracking'); ?></p>
                            <form id="srt-parent-code-form" class="srt-code-form">
                                <div class="srt-form-inline">
                                    <input type="text" 
                                           id="srt-parent-code-input" 
                                           name="code" 
                                           placeholder="<?php esc_attr_e('M-ABC123 or INV-XXXXXXXXXX', 'schedule-collaboration-tracking'); ?>" 
                                           class="srt-code-input"
                                           required>
                                    <button type="submit" class="button button-primary">
                                        <span class="dashicons dashicons-plus"></span> <?php esc_html_e('Link Child', 'schedule-collaboration-tracking'); ?>
                                    </button>
                                </div>
                                <div id="srt-code-message" class="srt-message"></div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- My Settings Section -->
                <div class="srt-dashboard-section">
                    <h4><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('My Settings', 'schedule-collaboration-tracking'); ?></h4>
                    <div class="srt-dashboard-content">
                        <div class="srt-settings-grid">
                            <form id="srt-user-preferences-form" class="srt-preferences-form" style="display: contents;">
                                <div class="srt-setting-card">
                                    <div class="srt-setting-icon">
                                        <span class="dashicons dashicons-airplane"></span>
                                    </div>
                                    <div class="srt-setting-content">
                                        <label for="home_airport" class="srt-setting-label"><?php esc_html_e('Home Airport', 'schedule-collaboration-tracking'); ?></label>
                                        <input type="text" 
                                               id="home_airport" 
                                               name="home_airport" 
                                               placeholder="e.g., BDL" 
                                               maxlength="3"
                                               style="text-transform: uppercase; width: 100%; max-width: 150px;"
                                               class="srt-input-large">
                                        <small class="srt-help-text"><?php esc_html_e('Your nearest airport (3-letter code)', 'schedule-collaboration-tracking'); ?></small>
                                    </div>
                                </div>
                                
                                <div class="srt-setting-card">
                                    <div class="srt-setting-icon">
                                        <span class="dashicons dashicons-clock"></span>
                                    </div>
                                    <div class="srt-setting-content">
                                        <label for="timezone" class="srt-setting-label"><?php esc_html_e('Time Zone', 'schedule-collaboration-tracking'); ?></label>
                                        <select id="timezone" name="timezone" class="srt-select-large" style="width: 100%; max-width: 250px;">
                                            <option value="">Select timezone...</option>
                                            <option value="America/New_York">Eastern Time</option>
                                            <option value="America/Chicago">Central Time</option>
                                            <option value="America/Denver">Mountain Time</option>
                                            <option value="America/Los_Angeles">Pacific Time</option>
                                            <option value="America/Anchorage">Alaska Time</option>
                                            <option value="Pacific/Honolulu">Hawaii Time</option>
                                        </select>
                                        <small class="srt-help-text"><?php esc_html_e('For accurate event times', 'schedule-collaboration-tracking'); ?></small>
                                    </div>
                                </div>
                                
                                <div class="srt-setting-card srt-setting-action">
                                    <button type="submit" class="button button-primary button-large">
                                        <span class="dashicons dashicons-yes"></span> <?php esc_html_e('Save Preferences', 'schedule-collaboration-tracking'); ?>
                                    </button>
                                    <span id="srt-preferences-message" class="srt-message"></span>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- My Price Alerts Section -->
                <div class="srt-dashboard-section">
                    <h4><span class="dashicons dashicons-bell"></span> <?php esc_html_e('My Price Alerts', 'schedule-collaboration-tracking'); ?></h4>
                    <div id="srt-user-alerts" class="srt-dashboard-content srt-alerts-container">
                        <div class="srt-loading"></div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($is_member): ?>
        <!-- Member View: My Events & Travel -->
        <div class="srt-member-dashboard">
            <div class="srt-user-header">
                <h2><?php printf(esc_html__('Welcome, %s', 'schedule-collaboration-tracking'), esc_html($current_user->display_name)); ?></h2>
                <p class="srt-user-role">
                    <?php 
                    $section = get_user_meta($current_user->ID, 'srt_section', true);
                    $instrument = get_user_meta($current_user->ID, 'srt_instrument', true);
                    
                    if ($section && $instrument) {
                        printf(esc_html__('%s - %s', 'schedule-collaboration-tracking'), 
                            esc_html(ucfirst(str_replace('_', ' ', $section))), 
                            esc_html($instrument)
                        );
                    } elseif ($section) {
                        echo esc_html(ucfirst(str_replace('_', ' ', $section)));
                    } else {
                        esc_html_e('Member', 'schedule-collaboration-tracking');
                    }
                    ?>
                </p>
            </div>

            <!-- Member's Travel Dashboard -->
            <div id="srt-dashboard" class="srt-dashboard" data-user-role="member">
                <div class="srt-dashboard-header">
                    <h3><?php esc_html_e('My Travel Overview', 'schedule-collaboration-tracking'); ?></h3>
                    <?php 
                    $subscribe_page = get_pages(array('meta_key' => '_wp_page_template', 'number' => 1));
                    foreach ($subscribe_page as $page) {
                        if (has_shortcode($page->post_content, 'srt_calendar_subscribe')) {
                            echo '<a href="' . esc_url(get_permalink($page->ID)) . '" class="button button-secondary"><span class="dashicons dashicons-calendar"></span> ' . esc_html__('Subscribe to Calendar', 'schedule-collaboration-tracking') . '</a>';
                            break;
                        }
                    }
                    ?>
                </div>
                
                <div class="srt-dashboard-section">
                    <h4>✈️ <?php esc_html_e('My Upcoming Flights', 'schedule-collaboration-tracking'); ?></h4>
                    <p class="srt-section-description"><?php esc_html_e('Click any flight to search prices, track deals, and set price alerts', 'schedule-collaboration-tracking'); ?></p>
                    <div id="srt-flights-needed" class="srt-dashboard-content">
                        <div class="srt-loading"></div>
                    </div>
                </div>
                
                <div class="srt-dashboard-section">
                    <h4>🔔 <?php esc_html_e('My Price Alerts', 'schedule-collaboration-tracking'); ?></h4>
                    <p class="srt-section-description"><?php esc_html_e('Active flight price tracking and notifications', 'schedule-collaboration-tracking'); ?></p>
                    <div id="srt-user-alerts" class="srt-dashboard-content srt-alerts-container">
                        <div class="srt-loading"></div>
                    </div>
                </div>
                
                <div class="srt-dashboard-section">
                    <h4>📅 <?php esc_html_e('Upcoming Travel (Next 30 Days)', 'schedule-collaboration-tracking'); ?></h4>
                    <div id="srt-upcoming-travel" class="srt-dashboard-content">
                        <div class="srt-loading"></div>
                    </div>
                </div>
                
                <!-- My Settings Section -->
                <div class="srt-dashboard-section">
                    <h4><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('My Settings', 'schedule-collaboration-tracking'); ?></h4>
                    <div class="srt-dashboard-content">
                        <div class="srt-settings-grid">
                            <form id="srt-user-preferences-form" class="srt-preferences-form" style="display: contents;">
                                <div class="srt-setting-card">
                                    <div class="srt-setting-icon">
                                        <span class="dashicons dashicons-airplane"></span>
                                    </div>
                                    <div class="srt-setting-content">
                                        <label for="home_airport" class="srt-setting-label"><?php esc_html_e('Home Airport', 'schedule-collaboration-tracking'); ?></label>
                                        <input type="text" 
                                               id="home_airport" 
                                               name="home_airport" 
                                               placeholder="e.g., BDL" 
                                               maxlength="3"
                                               style="text-transform: uppercase; width: 100%; max-width: 150px;"
                                               class="srt-input-large">
                                        <small class="srt-help-text"><?php esc_html_e('Your nearest airport (3-letter code)', 'schedule-collaboration-tracking'); ?></small>
                                    </div>
                                </div>
                                
                                <div class="srt-setting-card">
                                    <div class="srt-setting-icon">
                                        <span class="dashicons dashicons-clock"></span>
                                    </div>
                                    <div class="srt-setting-content">
                                        <label for="timezone" class="srt-setting-label"><?php esc_html_e('Time Zone', 'schedule-collaboration-tracking'); ?></label>
                                        <select id="timezone" name="timezone" class="srt-select-large" style="width: 100%; max-width: 250px;">
                                            <option value="">Select timezone...</option>
                                            <option value="America/New_York">Eastern Time</option>
                                            <option value="America/Chicago">Central Time</option>
                                            <option value="America/Denver">Mountain Time</option>
                                            <option value="America/Los_Angeles">Pacific Time</option>
                                            <option value="America/Anchorage">Alaska Time</option>
                                            <option value="Pacific/Honolulu">Hawaii Time</option>
                                        </select>
                                        <small class="srt-help-text"><?php esc_html_e('For accurate event times', 'schedule-collaboration-tracking'); ?></small>
                                    </div>
                                </div>
                                
                                <div class="srt-setting-card srt-setting-action">
                                    <button type="submit" class="button button-primary button-large">
                                        <span class="dashicons dashicons-yes"></span> <?php esc_html_e('Save Preferences', 'schedule-collaboration-tracking'); ?>
                                    </button>
                                    <span id="srt-preferences-message" class="srt-message"></span>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Parent Invitations Section (Member View) -->
                <div class="srt-dashboard-section">
                    <h3><span class="dashicons dashicons-groups"></span> <?php esc_html_e('Parent Access', 'schedule-collaboration-tracking'); ?></h3>
                    <div class="srt-dashboard-content">
                        <div class="srt-invitation-section">
                            <div class="srt-member-code-card">
                                <h4><?php esc_html_e('Your Permanent Code', 'schedule-collaboration-tracking'); ?></h4>
                                <div class="srt-code-display">
                                    <code id="srt-member-code" class="srt-permanent-code">---</code>
                                    <button type="button" class="button button-small srt-copy-code" data-code-target="srt-member-code">
                                        <span class="dashicons dashicons-clipboard"></span> <?php esc_html_e('Copy', 'schedule-collaboration-tracking'); ?>
                                    </button>
                                </div>
                                <p class="description"><?php esc_html_e('Share this code with your parents so they can link to your account.', 'schedule-collaboration-tracking'); ?></p>
                            </div>
                            
                            <div class="srt-invitation-actions">
                                <button type="button" id="srt-generate-invite" class="button button-secondary">
                                    <span class="dashicons dashicons-email"></span> <?php esc_html_e('Generate One-Time Invite Code', 'schedule-collaboration-tracking'); ?>
                                </button>
                            </div>
                            
                            <div id="srt-invitations-list" class="srt-invitations-container">
                                <div class="srt-loading"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Admin/Other View: Full Dashboard -->
        <div id="srt-dashboard" class="srt-dashboard" data-user-role="admin">
            <div class="srt-dashboard-header">
                <h2><?php esc_html_e('Travel Dashboard', 'schedule-collaboration-tracking'); ?></h2>
                <?php 
                $subscribe_page = get_pages(array('meta_key' => '_wp_page_template', 'number' => 1));
                foreach ($subscribe_page as $page) {
                    if (has_shortcode($page->post_content, 'srt_calendar_subscribe')) {
                        echo '<a href="' . esc_url(get_permalink($page->ID)) . '" class="button button-secondary"><span class="dashicons dashicons-calendar"></span> ' . esc_html__('Subscribe to Calendar', 'schedule-collaboration-tracking') . '</a>';
                        break;
                    }
                }
                ?>
            </div>
            
            <div class="srt-dashboard-section">
                <h3><?php esc_html_e('Flights Needed (Upcoming)', 'schedule-collaboration-tracking'); ?></h3>
                <div id="srt-flights-needed" class="srt-dashboard-content">
                    <div class="srt-loading"></div>
                </div>
            </div>
            
            <div class="srt-dashboard-section">
                <h3><?php esc_html_e('Upcoming Travel (Next 30 Days)', 'schedule-collaboration-tracking'); ?></h3>
                <div id="srt-upcoming-travel" class="srt-dashboard-content">
                    <div class="srt-loading"></div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
/* Main Navigation */
.srt-main-nav {
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
    color: white;
    padding: 20px 30px;
    margin: -20px -30px 30px -30px;
    border-radius: 8px 8px 0 0;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.srt-nav-logo h1 {
    margin: 0 0 15px 0;
    font-size: 28px;
    color: white;
}
.srt-nav-menu {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.srt-nav-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 16px;
    background: rgba(255,255,255,0.15);
    color: white !important;
    text-decoration: none;
    border-radius: 6px;
    transition: all 0.3s;
    font-size: 14px;
    font-weight: 500;
}
.srt-nav-link:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
}
.srt-nav-link .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}
.srt-nav-logout {
    margin-left: auto;
    background: rgba(220,38,38,0.3);
}
.srt-nav-logout:hover {
    background: rgba(220,38,38,0.5);
}
.srt-nav-login {
    margin-left: auto;
    background: rgba(34,197,94,0.3);
}
.srt-nav-login:hover {
    background: rgba(34,197,94,0.5);
}

/* Welcome Section (Public View) */
.srt-welcome-section {
    text-align: center;
    padding: 60px 20px;
}
.srt-welcome-content {
    max-width: 600px;
    margin: 0 auto 40px;
}
.srt-welcome-content h2 {
    font-size: 32px;
    margin-bottom: 15px;
    color: #1e3a8a;
}
.srt-welcome-text {
    font-size: 18px;
    color: #666;
    margin-bottom: 30px;
}
.srt-welcome-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}
.srt-public-calendar {
    background: #f3f4f6;
    padding: 30px;
    border-radius: 8px;
    max-width: 500px;
    margin: 0 auto;
}

/* User Header */
.srt-user-header {
    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
    padding: 25px;
    border-radius: 8px;
    margin-bottom: 30px;
}
.srt-user-header h2 {
    margin: 0 0 5px 0;
    color: #1e3a8a;
}
.srt-user-role {
    margin: 0;
    color: #666;
    font-size: 16px;
}

/* Family Section (Parent View) */
.srt-family-section {
    margin-bottom: 30px;
}
.srt-children-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 15px;
}
.srt-child-card {
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    transition: all 0.3s;
}
.srt-child-card:hover {
    border-color: #3b82f6;
    box-shadow: 0 4px 6px rgba(59,130,246,0.1);
}
.srt-child-card h4 {
    margin: 0 0 10px 0;
    color: #1e3a8a;
    font-size: 18px;
}
.srt-child-info {
    margin: 5px 0;
    font-size: 14px;
    color: #666;
}

/* Notice Boxes */
.srt-notice {
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.srt-notice-info {
    background: #dbeafe;
    border-left: 4px solid #3b82f6;
    color: #1e40af;
}
.srt-notice p {
    margin: 5px 0;
}

/* Dashboard Sections */
.srt-dashboard {
    margin-top: 20px;
}
.srt-dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}
.srt-dashboard-header h2,
.srt-dashboard-header h3 {
    color: #1e3a8a;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 10px;
    margin: 0;
    flex: 1;
}
.srt-dashboard-header .button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}
.srt-dashboard-header .button .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}
.srt-dashboard h3 {
    color: #1e3a8a;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 10px;
    margin-bottom: 20px;
}
.srt-dashboard-section {
    margin-bottom: 30px;
}
.srt-dashboard-section h4 {
    color: #374151;
    margin-bottom: 15px;
}
.srt-section-description {
    font-size: 13px;
    color: #6b7280;
    margin: -10px 0 15px 0;
    font-style: italic;
}

/* Responsive */
@media (max-width: 768px) {
    .srt-main-nav {
        margin: -15px -15px 20px -15px;
        padding: 15px;
    }
    .srt-nav-logo h1 {
        font-size: 22px;
    }
    .srt-nav-menu {
        justify-content: center;
    }
    .srt-nav-link {
        font-size: 13px;
        padding: 8px 12px;
    }
    .srt-welcome-content h2 {
        font-size: 24px;
    }
    .srt-children-list {
        grid-template-columns: 1fr;
    }
}
</style>
