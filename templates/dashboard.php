<?php
/**
 * Template: Dashboard (Main Hub)
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$is_logged_in = is_user_logged_in();
$is_member = $is_logged_in && FTT_Roles::is_member($current_user->ID);
$is_parent = $is_logged_in && FTT_Roles::is_parent($current_user->ID);
$is_admin = $is_logged_in && current_user_can('manage_options');

// Handle adult invitation acceptance
$invite_message = '';
if ($is_logged_in && isset($_GET['ftt_invite']) && isset($_GET['inviter'])) {
    $invite_code = sanitize_text_field($_GET['ftt_invite']);
    $inviter_id = absint($_GET['inviter']);
    
    // Get inviter's invitations
    $invitations = get_user_meta($inviter_id, 'ftt_adult_invitations', true);
    
    if (is_array($invitations) && isset($invitations[$invite_code])) {
        $invitation = $invitations[$invite_code];
        
        // Check if expired
        if ($invitation['expires'] > time()) {
            // Check if email matches (or skip email check if they're logged in)
            $link_user = true;
            
            if ($link_user) {
                // Link the two users as co-parents
                // Add each to the other's parent list
                $inviter_parents = get_user_meta($inviter_id, 'ftt_parents', true);
                if (!is_array($inviter_parents)) {
                    $inviter_parents = [];
                }
                if (!in_array($current_user->ID, $inviter_parents)) {
                    $inviter_parents[] = $current_user->ID;
                    update_user_meta($inviter_id, 'ftt_parents', $inviter_parents);
                }
                
                $current_parents = get_user_meta($current_user->ID, 'ftt_parents', true);
                if (!is_array($current_parents)) {
                    $current_parents = [];
                }
                if (!in_array($inviter_id, $current_parents)) {
                    $current_parents[] = $inviter_id;
                    update_user_meta($current_user->ID, 'ftt_parents', $current_parents);
                }
                
                // Store relationship
                update_user_meta($current_user->ID, 'relationship_to_' . $inviter_id, $invitation['relationship']);
                
                // Mark invitation as used
                unset($invitations[$invite_code]);
                update_user_meta($inviter_id, 'ftt_adult_invitations', $invitations);
                
                $inviter = get_userdata($inviter_id);
                $invite_message = sprintf(
                    '<div class="ftt-notice ftt-notice-success"><p>✅ You\'re now linked with %s! You can now view and manage their family calendar.</p></div>',
                    esc_html($inviter->display_name)
                );
            }
        } else {
            $invite_message = '<div class="ftt-notice ftt-notice-error"><p>This invitation has expired.</p></div>';
        }
    } else {
        $invite_message = '<div class="ftt-notice ftt-notice-error"><p>Invalid invitation code.</p></div>';
    }
}

// Get page URLs
$calendar_url = FTT_Pages::get_page_url('calendar');
$event_list_url = FTT_Pages::get_page_url('event_list');
$event_form_url = FTT_Pages::get_page_url('event_form');
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

<div class="ftt-container">
    <!-- Main Navigation -->
    <div class="ftt-main-nav">
        <div class="ftt-nav-logo">
            <h1>✈️ <?php esc_html_e('Family Dashboard', 'schedule-collaboration-tracking'); ?></h1>
        </div>
        <nav class="ftt-nav-menu">
            <?php if ($calendar_url): ?>
                <a href="<?php echo esc_url($calendar_url); ?>" class="ftt-nav-link">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php esc_html_e('Calendar', 'schedule-collaboration-tracking'); ?>
                </a>
            <?php endif; ?>
            
            <?php if ($event_list_url): ?>
                <a href="<?php echo esc_url($event_list_url); ?>" class="ftt-nav-link">
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
                <a href="<?php echo esc_url($event_form_url); ?>" class="ftt-nav-link">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e('Manage Events', 'schedule-collaboration-tracking'); ?>
                </a>
            <?php endif; ?>
            
            <?php if ($is_admin): ?>
                <a href="<?php echo admin_url('edit.php?post_type=ftt_event&page=ftt-manage-users'); ?>" class="ftt-nav-link">
                    <span class="dashicons dashicons-groups"></span>
                    <?php esc_html_e('Manage Users', 'schedule-collaboration-tracking'); ?>
                </a>
            <?php endif; ?>
            
            <?php if ($is_logged_in): ?>
                <a href="<?php echo wp_logout_url(home_url()); ?>" class="ftt-nav-link ftt-nav-logout">
                    <span class="dashicons dashicons-exit"></span>
                    <?php esc_html_e('Logout', 'schedule-collaboration-tracking'); ?>
                </a>
            <?php else: ?>
                <a href="<?php echo wp_login_url(get_permalink()); ?>" class="ftt-nav-link ftt-nav-login">
                    <span class="dashicons dashicons-admin-users"></span>
                    <?php esc_html_e('Login', 'schedule-collaboration-tracking'); ?>
                </a>
            <?php endif; ?>
        </nav>
    </div>

    <?php
    // Display invitation acceptance message
    if (!empty($invite_message)) {
        echo $invite_message;
    }
    ?>

    <?php if (!$is_logged_in): ?>
        <!-- Public View: Welcome + Register -->
        <div class="ftt-welcome-section">
            <div class="ftt-welcome-content">
                <h2><?php esc_html_e('Welcome to Family Travel Tracker', 'schedule-collaboration-tracking'); ?></h2>
                <p class="ftt-welcome-text">
                    <?php esc_html_e('Track your children\'s activities, manage travel schedules, and get flight price alerts - all in one place. Perfect for busy families, divorced co-parents, and anyone managing kids\' events.', 'schedule-collaboration-tracking'); ?>
                </p>
                <div class="ftt-welcome-actions">
                    <a href="<?php echo wp_login_url(get_permalink()); ?>" class="button button-primary button-large">
                        <?php esc_html_e('Login', 'schedule-collaboration-tracking'); ?>
                    </a>
                    <?php
                    // Check if registration page exists
                    $pages = get_pages(array('meta_key' => '_wp_page_template'));
                    foreach ($pages as $page) {
                        if (has_shortcode($page->post_content, 'ftt_register')) {
                            echo '<a href="' . esc_url(get_permalink($page->ID)) . '" class="button button-secondary button-large">' . esc_html__('Register', 'schedule-collaboration-tracking') . '</a>';
                            break;
                        }
                    }
                    ?>
                </div>
            </div>
            
            <?php if ($calendar_url): ?>
                <div class="ftt-public-calendar">
                    <h3><?php esc_html_e('Upcoming Events', 'schedule-collaboration-tracking'); ?></h3>
                    <p><?php esc_html_e('View all family events and activities on the calendar:', 'schedule-collaboration-tracking'); ?></p>
                    <a href="<?php echo esc_url($calendar_url); ?>" class="button"><?php esc_html_e('View Full Calendar', 'schedule-collaboration-tracking'); ?></a>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($is_parent && !$is_member): ?>
        <!-- Parent View: Children's Events & Travel -->
        <div class="ftt-parent-dashboard">
            <div class="ftt-user-header">
                <h2><?php printf(esc_html__('Welcome, %s', 'schedule-collaboration-tracking'), esc_html($current_user->display_name)); ?></h2>
                <p class="ftt-user-role"><?php esc_html_e('Parent Dashboard', 'schedule-collaboration-tracking'); ?></p>
            </div>

            <!-- Quick Action Cards -->
            <div class="ftt-quick-actions">
                <div class="ftt-action-card ftt-action-add-child">
                    <div class="ftt-action-icon">👦</div>
                    <h3><?php esc_html_e('Add Child', 'schedule-collaboration-tracking'); ?></h3>
                    <p><?php esc_html_e('Link or create a child profile', 'schedule-collaboration-tracking'); ?></p>
                    <a href="#" class="button button-primary" id="ftt-quick-add-child"><?php esc_html_e('Add Child', 'schedule-collaboration-tracking'); ?></a>
                </div>
                
                <div class="ftt-action-card ftt-action-invite-adult">
                    <div class="ftt-action-icon">👥</div>
                    <h3><?php esc_html_e('Invite Co-Parent', 'schedule-collaboration-tracking'); ?></h3>
                    <p><?php esc_html_e('Share calendar access with another guardian', 'schedule-collaboration-tracking'); ?></p>
                    <a href="#" class="button button-primary" id="ftt-quick-invite-adult"><?php esc_html_e('Invite Adult', 'schedule-collaboration-tracking'); ?></a>
                </div>
                
                <div class="ftt-action-card ftt-action-add-event">
                    <div class="ftt-action-icon">📅</div>
                    <h3><?php esc_html_e('Quick Add Event', 'schedule-collaboration-tracking'); ?></h3>
                    <p><?php esc_html_e('Create a new event for your family', 'schedule-collaboration-tracking'); ?></p>
                    <a href="<?php echo esc_url(home_url('/ftt-calendar/')); ?>" class="button button-primary"><?php esc_html_e('Add Event', 'schedule-collaboration-tracking'); ?></a>
                </div>
                
                <div class="ftt-action-card ftt-action-manage-family">
                    <div class="ftt-action-icon">⚙️</div>
                    <h3><?php esc_html_e('Manage Family', 'schedule-collaboration-tracking'); ?></h3>
                    <p><?php esc_html_e('Update family members and preferences', 'schedule-collaboration-tracking'); ?></p>
                    <a href="<?php echo esc_url(home_url('/manage-family/')); ?>" class="button button-primary"><?php esc_html_e('Manage', 'schedule-collaboration-tracking'); ?></a>
                </div>
            </div>

            <?php
            $children = FTT_Roles::get_children($current_user->ID);
            if (!empty($children)):
            ?>
                <div class="ftt-family-section">
                    <h3><?php esc_html_e('Your Children', 'schedule-collaboration-tracking'); ?></h3>
                    <div class="ftt-children-list">
                        <?php foreach ($children as $child_id):
                            $child = get_userdata($child_id);
                            if (!$child) continue;
                            $section = get_user_meta($child_id, 'srt_section', true);
                            $instrument = get_user_meta($child_id, 'srt_instrument', true);
                        ?>
                            <div class="ftt-child-card">
                                <h4><?php echo esc_html($child->display_name); ?></h4>
                                <?php if ($section): ?>
                                    <p class="ftt-child-info">
                                        <strong><?php esc_html_e('Section:', 'schedule-collaboration-tracking'); ?></strong> 
                                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $section))); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($instrument): ?>
                                    <p class="ftt-child-info">
                                        <strong><?php esc_html_e('Instrument:', 'schedule-collaboration-tracking'); ?></strong> 
                                        <?php echo esc_html($instrument); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="ftt-notice ftt-notice-info">
                    <p><?php esc_html_e('No children linked to your account yet.', 'schedule-collaboration-tracking'); ?></p>
                    <?php if ($is_admin): ?>
                        <p><a href="<?php echo admin_url('edit.php?post_type=ftt_event&page=ftt-manage-users'); ?>"><?php esc_html_e('Manage Relationships', 'schedule-collaboration-tracking'); ?></a></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Parent's Travel Dashboard (children's travel) -->
            <div id="ftt-dashboard" class="ftt-dashboard" data-user-role="parent">
                <div class="ftt-dashboard-header">
                    <h3><?php esc_html_e('Family Travel Overview', 'schedule-collaboration-tracking'); ?></h3>
                    <?php 
                    $subscribe_page = get_pages(array('meta_key' => '_wp_page_template', 'number' => 1));
                    foreach ($subscribe_page as $page) {
                        if (has_shortcode($page->post_content, 'ftt_calendar_subscribe')) {
                            echo '<a href="' . esc_url(get_permalink($page->ID)) . '" class="button button-secondary"><span class="dashicons dashicons-calendar"></span> ' . esc_html__('Subscribe to Calendar', 'schedule-collaboration-tracking') . '</a>';
                            break;
                        }
                    }
                    ?>
                </div>
                
                <div class="ftt-dashboard-section">
                    <h4><?php esc_html_e('Flights Needed (Upcoming)', 'schedule-collaboration-tracking'); ?></h4>
                    <div id="ftt-flights-needed" class="ftt-dashboard-content">
                        <div class="ftt-loading"></div>
                    </div>
                </div>
                
                <div class="ftt-dashboard-section">
                    <h4><?php esc_html_e('💰 Linked Flights (Price Comparison)', 'schedule-collaboration-tracking'); ?></h4>
                    <div id="ftt-linked-flights" class="ftt-dashboard-content">
                        <div class="ftt-loading"></div>
                    </div>
                </div>
                
                <div class="ftt-dashboard-section">
                    <h4><?php esc_html_e('Upcoming Travel (Next 30 Days)', 'schedule-collaboration-tracking'); ?></h4>
                    <div id="ftt-upcoming-travel" class="ftt-dashboard-content">
                        <div class="ftt-loading"></div>
                    </div>
                </div>
                
                <!-- Add Child by Code Section (Parent View) -->
                <div class="ftt-dashboard-section">
                    <h4><span class="dashicons dashicons-groups"></span> <?php esc_html_e('Add Child by Code', 'schedule-collaboration-tracking'); ?></h4>
                    <div class="ftt-dashboard-content">
                        <div class="ftt-code-entry-section">
                            <p><?php esc_html_e('Enter the code your child shared with you to link their account.', 'schedule-collaboration-tracking'); ?></p>
                            <form id="ftt-parent-code-form" class="ftt-code-form">
                                <div class="ftt-form-inline">
                                    <input type="text" 
                                           id="ftt-parent-code-input" 
                                           name="code" 
                                           placeholder="<?php esc_attr_e('M-ABC123 or INV-XXXXXXXXXX', 'schedule-collaboration-tracking'); ?>" 
                                           class="ftt-code-input"
                                           required>
                                    <button type="submit" class="button button-primary">
                                        <span class="dashicons dashicons-plus"></span> <?php esc_html_e('Link Child', 'schedule-collaboration-tracking'); ?>
                                    </button>
                                </div>
                                <div id="ftt-code-message" class="ftt-message"></div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- My Price Alerts Section -->
                <div class="ftt-dashboard-section">
                    <h4><span class="dashicons dashicons-bell"></span> <?php esc_html_e('My Price Alerts', 'schedule-collaboration-tracking'); ?></h4>
                    <div id="ftt-user-alerts" class="ftt-dashboard-content ftt-alerts-container">
                        <div class="ftt-loading"></div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($is_member): ?>
        <!-- Member View: My Events & Travel -->
        <div class="ftt-member-dashboard">
            <div class="ftt-user-header">
                <h2><?php printf(esc_html__('Welcome, %s', 'schedule-collaboration-tracking'), esc_html($current_user->display_name)); ?></h2>
                <p class="ftt-user-role">
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
                        esc_html_e('Student', 'schedule-collaboration-tracking');
                    }
                    ?>
                </p>
            </div>

            <!-- Member's Travel Dashboard -->
            <div id="ftt-dashboard" class="ftt-dashboard" data-user-role="member">
                <div class="ftt-dashboard-header">
                    <h3><?php esc_html_e('My Travel Overview', 'schedule-collaboration-tracking'); ?></h3>
                    <?php 
                    $subscribe_page = get_pages(array('meta_key' => '_wp_page_template', 'number' => 1));
                    foreach ($subscribe_page as $page) {
                        if (has_shortcode($page->post_content, 'ftt_calendar_subscribe')) {
                            echo '<a href="' . esc_url(get_permalink($page->ID)) . '" class="button button-secondary"><span class="dashicons dashicons-calendar"></span> ' . esc_html__('Subscribe to Calendar', 'schedule-collaboration-tracking') . '</a>';
                            break;
                        }
                    }
                    ?>
                </div>
                
                <div class="ftt-dashboard-section">
                    <h4>✈️ <?php esc_html_e('My Upcoming Flights', 'schedule-collaboration-tracking'); ?></h4>
                    <p class="ftt-section-description"><?php esc_html_e('Click any flight to search prices, track deals, and set price alerts', 'schedule-collaboration-tracking'); ?></p>
                    <div id="ftt-flights-needed" class="ftt-dashboard-content">
                        <div class="ftt-loading"></div>
                    </div>
                </div>
                
                <div class="ftt-dashboard-section">
                    <h4>🔔 <?php esc_html_e('My Price Alerts', 'schedule-collaboration-tracking'); ?></h4>
                    <p class="ftt-section-description"><?php esc_html_e('Active flight price tracking and notifications', 'schedule-collaboration-tracking'); ?></p>
                    <div id="ftt-user-alerts" class="ftt-dashboard-content ftt-alerts-container">
                        <div class="ftt-loading"></div>
                    </div>
                </div>
                
                <div class="ftt-dashboard-section">
                    <h4>📅 <?php esc_html_e('Upcoming Travel (Next 30 Days)', 'schedule-collaboration-tracking'); ?></h4>
                    <div id="ftt-upcoming-travel" class="ftt-dashboard-content">
                        <div class="ftt-loading"></div>
                    </div>
                </div>
                
                <!-- Parent Invitations Section (Member View) -->
                <div class="ftt-dashboard-section">
                    <h3><span class="dashicons dashicons-groups"></span> <?php esc_html_e('Parent Access', 'schedule-collaboration-tracking'); ?></h3>
                    <div class="ftt-dashboard-content">
                        <div class="ftt-invitation-section">
                            <div class="ftt-member-code-card">
                                <h4><?php esc_html_e('Your Permanent Code', 'schedule-collaboration-tracking'); ?></h4>
                                <div class="ftt-code-display">
                                    <code id="ftt-member-code" class="ftt-permanent-code">---</code>
                                    <button type="button" class="button button-small ftt-copy-code" data-code-target="ftt-member-code">
                                        <span class="dashicons dashicons-clipboard"></span> <?php esc_html_e('Copy', 'schedule-collaboration-tracking'); ?>
                                    </button>
                                </div>
                                <p class="description"><?php esc_html_e('Share this code with your parents/guardians so they can link to your account and see your schedule.', 'schedule-collaboration-tracking'); ?></p>
                            </div>
                            
                            <div class="ftt-invitation-actions">
                                <button type="button" id="ftt-generate-invite" class="button button-secondary">
                                    <span class="dashicons dashicons-email"></span> <?php esc_html_e('Generate One-Time Invite Code', 'schedule-collaboration-tracking'); ?>
                                </button>
                            </div>
                            
                            <div id="ftt-invitations-list" class="ftt-invitations-container">
                                <div class="ftt-loading"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Admin/Other View: Full Dashboard -->
        <div id="ftt-dashboard" class="ftt-dashboard" data-user-role="admin">
            <div class="ftt-dashboard-header">
                <h2><?php esc_html_e('Travel Dashboard', 'schedule-collaboration-tracking'); ?></h2>
                <?php 
                $subscribe_page = get_pages(array('meta_key' => '_wp_page_template', 'number' => 1));
                foreach ($subscribe_page as $page) {
                    if (has_shortcode($page->post_content, 'ftt_calendar_subscribe')) {
                        echo '<a href="' . esc_url(get_permalink($page->ID)) . '" class="button button-secondary"><span class="dashicons dashicons-calendar"></span> ' . esc_html__('Subscribe to Calendar', 'schedule-collaboration-tracking') . '</a>';
                        break;
                    }
                }
                ?>
            </div>
            
            <div class="ftt-dashboard-section">
                <h3><?php esc_html_e('Flights Needed (Upcoming)', 'schedule-collaboration-tracking'); ?></h3>
                <div id="ftt-flights-needed" class="ftt-dashboard-content">
                    <div class="ftt-loading"></div>
                </div>
            </div>
            
            <div class="ftt-dashboard-section">
                <h3><?php esc_html_e('Upcoming Travel (Next 30 Days)', 'schedule-collaboration-tracking'); ?></h3>
                <div id="ftt-upcoming-travel" class="ftt-dashboard-content">
                    <div class="ftt-loading"></div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
/* Main Navigation */
.ftt-main-nav {
    background: linear-gradient(135deg, #6A3E8E 0%, #5B347A 100%);
    color: white;
    padding: 20px 30px;
    margin: -20px -30px 30px -30px;
    border-radius: 8px 8px 0 0;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.ftt-nav-logo h1 {
    margin: 0 0 15px 0;
    font-size: 28px;
    color: white;
}
.ftt-nav-menu {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.ftt-nav-link {
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
.ftt-nav-link:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
}
.ftt-nav-link .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}
.ftt-nav-logout {
    margin-left: auto;
    background: rgba(240,90,90,0.3);
}
.ftt-nav-logout:hover {
    background: rgba(240,90,90,0.5);
}
.ftt-nav-login {
    margin-left: auto;
    background: rgba(34,197,94,0.3);
}
.ftt-nav-login:hover {
    background: rgba(34,197,94,0.5);
}

/* Welcome Section (Public View) */
.ftt-welcome-section {
    text-align: center;
    padding: 60px 20px;
}
.ftt-welcome-content {
    max-width: 600px;
    margin: 0 auto 40px;
}
.ftt-welcome-content h2 {
    font-size: 32px;
    margin-bottom: 15px;
    color: #6A3E8E;
}
.ftt-welcome-text {
    font-size: 18px;
    color: #666;
    margin-bottom: 30px;
}
.ftt-welcome-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}
.ftt-public-calendar {
    background: #f3f4f6;
    padding: 30px;
    border-radius: 8px;
    max-width: 500px;
    margin: 0 auto;
}

/* Notice Messages */
.ftt-notice {
    padding: 15px 20px;
    border-radius: 8px;
    margin: 20px 30px;
    border-left: 4px solid;
}
.ftt-notice p {
    margin: 0;
    font-size: 16px;
}
.ftt-notice-success {
    background: #d4edda;
    border-color: #28a745;
    color: #155724;
}
.ftt-notice-error {
    background: #f8d7da;
    border-color: #dc3545;
    color: #721c24;
}
.ftt-notice-info {
    background: #d1ecf1;
    border-color: #17a2b8;
    color: #0c5460;
}

/* User Header */
.ftt-user-header {
    background: linear-gradient(135deg, #F8F5FB 0%, #E9E3F2 100%);
    padding: 25px;
    border-radius: 8px;
    margin-bottom: 30px;
}
.ftt-user-header h2 {
    margin: 0 0 5px 0;
    color: #6A3E8E;
}
.ftt-user-role {
    margin: 0;
    color: #666;
    font-size: 16px;
}

/* Quick Action Cards */
.ftt-quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.ftt-action-card {
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    padding: 25px;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}
.ftt-action-card:hover {
    border-color: #6A3E8E;
    box-shadow: 0 6px 20px rgba(106, 62, 142, 0.15);
    transform: translateY(-3px);
}
.ftt-action-icon {
    font-size: 48px;
    margin-bottom: 15px;
    animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}
.ftt-action-card h3 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 18px;
}
.ftt-action-card p {
    margin: 0 0 20px 0;
    color: #666;
    font-size: 14px;
    min-height: 40px;
}
.ftt-action-card .button {
    width: 100%;
    justify-content: center;
    background: linear-gradient(135deg, #6A3E8E 0%, #5B347A 100%);
    border-color: #6A3E8E;
    box-shadow: 0 2px 4px rgba(106, 62, 142, 0.2);
}
.ftt-action-card .button:hover {
    background: linear-gradient(135deg, #5B347A 0%, #4D2E68 100%);
    box-shadow: 0 4px 8px rgba(106, 62, 142, 0.3);
}

/* Family Section (Parent View) */
.ftt-family-section {
    margin-bottom: 30px;
}
.ftt-children-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 15px;
}
.ftt-child-card {
    background: white;
    border: 2px solid #E9E3F2;
    border-radius: 8px;
    padding: 20px;
    transition: all 0.3s;
}
.ftt-child-card:hover {
    border-color: #6A3E8E;
    box-shadow: 0 4px 6px rgba(106,62,142,0.1);
}
.ftt-child-card h4 {
    margin: 0 0 10px 0;
    color: #6A3E8E;
    font-size: 18px;
}
.ftt-child-info {
    margin: 5px 0;
    font-size: 14px;
    color: #666;
}

/* Notice Boxes */
.ftt-notice {
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.ftt-notice-info {
    background: #E9E3F2;
    border-left: 4px solid #6A3E8E;
    color: #5B347A;
}
.ftt-notice p {
    margin: 5px 0;
}

/* Dashboard Sections */
.ftt-dashboard {
    margin-top: 20px;
}
.ftt-dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}
.ftt-dashboard-header h2,
.ftt-dashboard-header h3 {
    color: #6A3E8E;
    border-bottom: 2px solid #E9E3F2;
    padding-bottom: 10px;
    margin: 0;
    flex: 1;
}
.ftt-dashboard-header .button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}
.ftt-dashboard-header .button .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}
.ftt-dashboard h3 {
    color: #6A3E8E;
    border-bottom: 2px solid #E9E3F2;
    padding-bottom: 10px;
    margin-bottom: 20px;
}
.ftt-dashboard-section {
    margin-bottom: 30px;
}
.ftt-dashboard-section h4 {
    color: #374151;
    margin-bottom: 15px;
}
.ftt-section-description {
    font-size: 13px;
    color: #6b7280;
    margin: -10px 0 15px 0;
    font-style: italic;
}

/* Responsive */
@media (max-width: 768px) {
    .ftt-main-nav {
        margin: -15px -15px 20px -15px;
        padding: 15px;
    }
    .ftt-nav-logo h1 {
        font-size: 22px;
    }
    .ftt-nav-menu {
        justify-content: center;
    }
    .ftt-nav-link {
        font-size: 13px;
        padding: 8px 12px;
    }
    .ftt-welcome-content h2 {
        font-size: 24px;
    }
    .ftt-children-list {
        grid-template-columns: 1fr;
    }
    .ftt-quick-actions {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    console.log('FTT DASHBOARD: Quick action handlers ready');
    
    // Quick Add Child button
    $('#ftt-quick-add-child').on('click', function(e) {
        e.preventDefault();
        console.log('Quick add child clicked');
        
        // Redirect to family management page, which has the full add child modal
        window.location.href = '<?php echo esc_js(home_url('/manage-family/')); ?>';
    });
    
    // Quick Invite Adult button  
    $('#ftt-quick-invite-adult').on('click', function(e) {
        e.preventDefault();
        console.log('Quick invite adult clicked');
        
        // Redirect to family management page
        window.location.href = '<?php echo esc_js(home_url('/manage-family/')); ?>';
    });
    
    console.log('FTT DASHBOARD: Event handlers attached');
});
</script>
