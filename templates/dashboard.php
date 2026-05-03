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
$is_parent = $is_logged_in && FTT_Family_Groups::is_parent($current_user->ID);
$is_admin = $is_logged_in && current_user_can('manage_options');

// Handle invitation codes - redirect to registration if not logged in
if (isset($_GET['ftt_invite'])) {
    if (!$is_logged_in) {
        // Redirect to registration page with invite code
        $register_url = FTT_Pages::get_page_url('register');
        if (!$register_url) {
            $register_url = home_url('/ftt-register/');
        }
        $redirect_url = add_query_arg('ftt_invite', sanitize_text_field($_GET['ftt_invite']), $register_url);
        wp_redirect($redirect_url);
        exit;
    }
}

// Handle adult invitation acceptance for logged-in users
$invite_message = '';
if ($is_logged_in && isset($_GET['ftt_invite'])) {
    $invite_code = sanitize_text_field($_GET['ftt_invite']);
    
    // Search all users for this invite code
    $users = get_users(array('meta_key' => 'ftt_adult_invitations'));
    $invitation_found = false;
    
    foreach ($users as $inviter_user) {
        $invitations = get_user_meta($inviter_user->ID, 'ftt_adult_invitations', true);
        
        if (is_array($invitations) && isset($invitations[$invite_code])) {
            $invitation = $invitations[$invite_code];
            $inviter_id = $inviter_user->ID;
            $invitation_found = true;
        
$invitation = $invitations[$invite_code];
            $inviter_id = $inviter_user->ID;
            $invitation_found = true;
            
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
            break;
        }
    }
    
    if (!$invitation_found) {
        $invite_message = '<div class="ftt-notice ftt-notice-error"><p>Invalid or expired invitation link.</p></div>';
    }
}

// Get user's groups (v2.1)
$user_groups = [];
$selected_group_id = null;
$show_group_selector = false;

if ($is_logged_in) {
    $user_groups = FTT_Family_Groups::get_user_groups($current_user->ID);
    $show_group_selector = !empty($user_groups) && count($user_groups) > 1;
    
    // Determine selected group
    if (isset($_GET['group']) && !empty($_GET['group'])) {
        $raw_group = sanitize_text_field(wp_unslash($_GET['group']));
        if (!ctype_digit($raw_group)) {
            $selected_group_id = FTT_Family_Groups::resolve_group_token($raw_group);
        } else {
            $selected_group_id = (int) $raw_group;
        }
        // Verify user has access to this group
        $has_access = false;
        foreach ($user_groups as $group) {
            if ($group->id == $selected_group_id) {
                $has_access = true;
                break;
            }
        }
        if (!$has_access) {
            $selected_group_id = null;
        }
    }
    
    // Default to primary group if no selection
    if (!$selected_group_id && !empty($user_groups)) {
        $primary_group = get_user_meta($current_user->ID, 'ftt_primary_group', true);
        if ($primary_group) {
            foreach ($user_groups as $group) {
                if ($group->id == $primary_group) {
                    $selected_group_id = $primary_group;
                    break;
                }
            }
        }
        // If still not set, use first group
        if (!$selected_group_id) {
            $selected_group_id = $user_groups[0]->id;
        }
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
    
    <?php if ($show_group_selector): ?>
        <!-- Group Selector (v2.1) -->
        <div class="ftt-group-selector-bar">
            <div class="ftt-group-selector-label">
                <span class="dashicons dashicons-groups"></span>
                <span>Viewing Group:</span>
            </div>
            <select id="ftt-group-selector" class="ftt-group-selector-dropdown">
                <option value="">All Groups</option>
                <?php foreach ($user_groups as $group): ?>
                    <option value="<?php echo esc_attr($group->id); ?>" 
                            <?php selected($group->id, $selected_group_id); ?>>
                        <?php 
                        echo esc_html($group->name);
                        if ($group->planned_children > 0) {
                            $remaining = max(0, $group->planned_children - $group->child_count);
                            echo ' (' . esc_html($group->child_count) . ' used, ' . esc_html($remaining) . ' remaining)';
                        } else {
                            echo ' (' . esc_html($group->child_count) . ' ' . ($group->child_count == 1 ? 'child' : 'children') . ')';
                        }
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <a href="/ftt-groups/" class="button ftt-manage-groups-btn">
                <span class="dashicons dashicons-admin-settings"></span>
                Manage Groups
            </a>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#ftt-group-selector').on('change', function() {
                var groupId = $(this).val();
                var url = new URL(window.location.href);
                if (groupId) {
                    url.searchParams.set('group', groupId);
                } else {
                    url.searchParams.delete('group');
                }
                window.location.href = url.toString();
            });
        });
        </script>
    <?php endif; ?>
    
    <?php if ($selected_group_id && !empty($user_groups)): ?>
        <!-- Selected Group Summary (v2.1) -->
        <?php
        $selected_group = null;
        foreach ($user_groups as $group) {
            if ($group->id == $selected_group_id) {
                $selected_group = $group;
                break;
            }
        }
        
        if ($selected_group):
            // Get group billing summary
            $billing_info = null;
            if (class_exists('FTT_Billing_Manager')) {
                $billing_info = FTT_Billing_Manager::get_group_billing_summary($selected_group_id);
            }
        ?>
            <div class="ftt-group-summary-card">
                <div class="ftt-group-summary-header" style="border-left: 4px solid <?php echo esc_attr($selected_group->color ?: '#6A3E8E'); ?>;">
                    <h3><?php echo esc_html($selected_group->name); ?></h3>
                    <?php if ($selected_group->description): ?>
                        <p class="ftt-group-description"><?php echo esc_html($selected_group->description); ?></p>
                    <?php endif; ?>
                </div>
                <div class="ftt-group-summary-stats">
                    <div class="ftt-stat-box">
                        <span class="dashicons dashicons-groups"></span>
                        <div>
                            <strong><?php echo esc_html($selected_group->member_count); ?></strong>
                            <span><?php echo $selected_group->member_count == 1 ? 'Member' : 'Members'; ?></span>
                        </div>
                    </div>
                    <div class="ftt-stat-box">
                        <span class="dashicons dashicons-admin-users"></span>
                        <div>
                            <?php 
                            if ($selected_group->planned_children > 0) {
                                $remaining = max(0, $selected_group->planned_children - $selected_group->child_count);
                                ?>
                                <strong><?php echo esc_html($selected_group->child_count . ' / ' . $remaining); ?></strong>
                                <span>used / remaining</span>
                                <?php
                            } else {
                                ?>
                                <strong><?php echo esc_html($selected_group->child_count); ?></strong>
                                <span><?php echo $selected_group->child_count == 1 ? 'Child' : 'Children'; ?></span>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                    <?php if ($billing_info && $billing_info['has_billing']): ?>
                        <div class="ftt-stat-box <?php echo $billing_info['in_trial'] ? 'ftt-stat-trial' : ''; ?>">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <div>
                                <strong><?php echo esc_html($billing_info['status_label']); ?></strong>
                                <?php if ($billing_info['in_trial']): ?>
                                    <span><?php echo esc_html($billing_info['days_until_charge']); ?> days left</span>
                                <?php else: ?>
                                    <span>Active</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="ftt-stat-box ftt-stat-inactive">
                            <span class="dashicons dashicons-warning"></span>
                            <div>
                                <strong>No Billing</strong>
                                <span>Setup Required</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- Main Navigation -->
    <?php
    $ftt_page_title  = __('Family Dashboard', 'schedule-collaboration-tracking');
    $ftt_active_slug = 'dashboard';
    include FTT_PLUGIN_DIR . 'templates/partials/nav.php';
    ?>

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
                    <?php
                    $login_url = FTT_Pages::get_page_url('login');
                    if (!$login_url || strpos($login_url, 'ftt-login') === false) {
                        $login_url = home_url('/ftt-login/');
                    }
                    ?>
                    <a href="<?php echo esc_url($login_url); ?>" class="button button-primary button-large">
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
                <?php $event_form_url = FTT_Pages::get_page_url('event_form') ?: home_url('/ftt-manage-events/'); ?>
                <a href="<?php echo esc_url($event_form_url); ?>" class="ftt-action-card ftt-action-add-event">
                    <div class="ftt-action-icon">📅</div>
                    <h3><?php esc_html_e('Add Event', 'schedule-collaboration-tracking'); ?></h3>
                    <p><?php esc_html_e('Create a new event for your family', 'schedule-collaboration-tracking'); ?></p>
                </a>

                <?php $groups_url = FTT_Pages::get_page_url('groups') ?: home_url('/ftt-groups/'); ?>
                <a href="<?php echo esc_url($groups_url); ?>" class="ftt-action-card ftt-action-manage-groups">
                    <div class="ftt-action-icon">📦</div>
                    <h3><?php esc_html_e('Family Groups', 'schedule-collaboration-tracking'); ?></h3>
                    <p><?php esc_html_e('Manage family groups and billing', 'schedule-collaboration-tracking'); ?></p>
                </a>

                <a href="#" class="ftt-action-card ftt-action-add-child" id="ftt-quick-add-child">
                    <div class="ftt-action-icon">👦</div>
                    <h3><?php esc_html_e('Add Child', 'schedule-collaboration-tracking'); ?></h3>
                    <p><?php esc_html_e('Link or create a child profile', 'schedule-collaboration-tracking'); ?></p>
                </a>

                <a href="#" class="ftt-action-card ftt-action-invite-adult" id="ftt-quick-invite-adult">
                    <div class="ftt-action-icon">👥</div>
                    <h3><?php esc_html_e('Invite Co-Parent', 'schedule-collaboration-tracking'); ?></h3>
                    <p><?php esc_html_e('Share calendar access with another guardian', 'schedule-collaboration-tracking'); ?></p>
                </a>
            </div>

            <?php
            $children = FTT_Family_Groups::get_user_children($current_user->ID);
            if (!empty($children)):
            ?>
                <div class="ftt-family-section">
                    <h3><?php esc_html_e('Your Children', 'schedule-collaboration-tracking'); ?></h3>
                    <div class="ftt-children-list">
                        <?php foreach ($children as $child_id):
                            $child = get_userdata($child_id);
                            if (!$child) continue;
                            $section = get_user_meta($child_id, 'ftt_section', true);
                            $instrument = get_user_meta($child_id, 'ftt_instrument', true);
                            $child_color = FTT_Child_Colors::get_child_color($child_id);
                        ?>
                            <div class="ftt-child-card">
                                <h4>
                                    <?php if ($child_color): ?>
                                        <span class="ftt-child-color-bubble" style="background-color: <?php echo esc_attr($child_color['hex']); ?>"></span>
                                    <?php endif; ?>
                                    <?php echo esc_html($child->display_name); ?>
                                </h4>
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
                        <p><a href="<?php echo esc_url(home_url('/ftt-groups/')); ?>"><?php esc_html_e('Manage Family Groups', 'schedule-collaboration-tracking'); ?></a></p>
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
                    $section = get_user_meta($current_user->ID, 'ftt_section', true);
                    $instrument = get_user_meta($current_user->ID, 'ftt_instrument', true);
                    
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
/* Group Selector Bar (v2.1) */
.ftt-group-selector-bar {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 15px 20px;
    margin: -10px 0 20px 0;
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}
.ftt-group-selector-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #495057;
    font-size: 14px;
}
.ftt-group-selector-label .dashicons {
    color: #6A3E8E;
    font-size: 20px;
    width: 20px;
    height: 20px;
}
.ftt-group-selector-dropdown {
    flex: 1;
    min-width: 200px;
    max-width: 400px;
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 14px;
    background: white;
    cursor: pointer;
    transition: all 0.2s;
}
.ftt-group-selector-dropdown:hover {
    border-color: #6A3E8E;
}
.ftt-group-selector-dropdown:focus {
    outline: none;
    border-color: #6A3E8E;
    box-shadow: 0 0 0 3px rgba(106, 62, 142, 0.1);
}
.ftt-manage-groups-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: #6A3E8E;
    color: white !important;
    border: none;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
}
.ftt-manage-groups-btn:hover {
    background: #5B347A;
    transform: translateY(-1px);
}
.ftt-manage-groups-btn .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

/* Group Summary Card (v2.1) */
.ftt-group-summary-card {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin: 0 0 25px 0;
    overflow: hidden;
}
.ftt-group-summary-header {
    padding: 20px 25px;
    background: linear-gradient(135deg, #F8F5FB 0%, #E9E3F2 100%);
}
.ftt-group-summary-header h3 {
    margin: 0 0 5px 0;
    color: #6A3E8E;
    font-size: 20px;
}
.ftt-group-description {
    margin: 0;
    color: #666;
    font-size: 14px;
}
.ftt-group-summary-stats {
    display: flex;
    gap: 0;
    padding: 0;
    flex-wrap: wrap;
}
.ftt-stat-box {
    flex: 1;
    min-width: 120px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-right: 1px solid #e9ecef;
}
.ftt-stat-box:last-child {
    border-right: none;
}
.ftt-stat-box .dashicons {
    font-size: 32px;
    width: 32px;
    height: 32px;
    color: #6A3E8E;
}
.ftt-stat-box div {
    display: flex;
    flex-direction: column;
}
.ftt-stat-box strong {
    font-size: 24px;
    color: #333;
    line-height: 1;
    margin-bottom: 4px;
}
.ftt-stat-box span {
    font-size: 13px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.ftt-stat-inactive .dashicons {
    color: #fbbf24;
}
.ftt-stat-inactive strong {
    color: #f59e0b;
}

/* Trial subscription styling */
.ftt-stat-trial .dashicons {
    color: #3b82f6;
}
.ftt-stat-trial strong {
    color: #2563eb;
}
.ftt-stat-trial span {
    color: #3b82f6;
    font-weight: 500;
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
    display: flex;
    align-items: center;
}
.ftt-child-color-bubble {
    display: inline-block;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    margin-right: 10px;
    border: 2px solid rgba(0, 0, 0, 0.1);
    flex-shrink: 0;
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
    .ftt-container {
        padding: 10px;
    }

    .ftt-welcome-section {
        padding: 20px 15px;
    }

    .ftt-welcome-content h2 {
        font-size: 24px;
    }

    .ftt-welcome-text {
        font-size: 14px;
    }

    .ftt-welcome-actions {
        flex-direction: column;
        gap: 12px;
    }

    .ftt-welcome-actions .button {
        width: 100%;
        padding: 12px;
    }

    .ftt-group-selector {
        margin: 15px 0;
    }

    .ftt-group-selector select {
        font-size: 14px;
        padding: 10px;
    }

    /* Group selector bar */
    .ftt-group-selector-bar {
        flex-wrap: wrap;
        gap: 8px;
        padding: 10px 12px;
        margin-bottom: 12px;
    }
    .ftt-group-selector-dropdown {
        min-width: 0;
        flex: 1 1 150px;
    }
    .ftt-manage-groups-btn {
        width: 100%;
        justify-content: center;
    }

    /* Group summary */
    .ftt-group-summary {
        padding: 15px;
    }

    .ftt-group-summary-card {
        overflow: visible;
        margin-bottom: 12px;
    }

    .ftt-group-summary-header {
        padding: 12px 15px;
        border-radius: 0;
    }

    /* Toggle header style */
    .ftt-mob-toggle-hdr {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .ftt-mob-toggle-hdr h3 {
        margin: 0;
        flex: 1;
    }
    .ftt-mob-chevron {
        font-size: 20px;
        line-height: 1;
        color: #6A3E8E;
        font-weight: 300;
        flex-shrink: 0;
    }

    .ftt-group-summary-header h3 {
        font-size: 18px;
    }

    .ftt-group-summary-stats {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        flex-wrap: unset;
    }

    .ftt-stat-box {
        padding: 12px 8px;
        border-right: 1px solid #e9ecef;
        border-top: none;
        min-width: unset;
    }

    .ftt-stat-box:nth-child(3) {
        border-right: none;
    }

    .ftt-stat-box .dashicons {
        font-size: 24px;
        width: 24px;
        height: 24px;
    }

    .ftt-stat-box strong {
        font-size: 18px;
    }

    .ftt-stat-box span {
        font-size: 11px;
    }

    .ftt-group-stats {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .ftt-stat-card {
        padding: 12px;
    }

    /* ── Parent dashboard: flex column so we can reorder ── */
    .ftt-parent-dashboard {
        display: flex;
        flex-direction: column;
    }
    .ftt-quick-actions  { order: 1; }  /* pinned to top on mobile */
    .ftt-user-header    { order: 2; }
    #ftt-dashboard      { order: 3; }  /* data next */
    .ftt-family-section { order: 4; }
    /* Synthetic collapsible header no longer needed for quick actions */
    .ftt-mob-synth-hdr  { order: 5; }

    /* Synthetic header inserted by JS before Quick Actions */
    .ftt-mob-synth-hdr {
        padding: 12px 15px;
        font-weight: 600;
        font-size: 14px;
        color: #6A3E8E;
        background: #f8f5fb;
        border: 1px solid #e0d5ed;
        border-radius: 6px 6px 0 0;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 16px;
    }
    .ftt-mob-synth-hdr + .ftt-quick-actions {
        border: 1px solid #e0d5ed;
        border-top: none;
        border-radius: 0 0 6px 6px;
        padding: 12px;
        margin-top: 0 !important;
    }

    /* Children list */
    .ftt-children-list {
        grid-template-columns: 1fr;
    }

    .ftt-child-card {
        padding: 15px;
    }

    /* Quick actions: single scrolling pill row at top */
    .ftt-quick-actions {
        display: flex;
        flex-direction: row;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        gap: 8px;
        padding: 8px 0 10px;
        margin-bottom: 12px;
        /* no synthetic collapsible header on mobile for this section */
    }
    .ftt-action-card {
        flex: 0 0 auto;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        padding: 10px 14px;
        border-radius: 10px;
        min-width: 70px;
        text-decoration: none;
        color: inherit;
        border: 2px solid #e0d5ed;
        background: #fff;
    }
    .ftt-action-card:hover, .ftt-action-card:focus {
        border-color: #6A3E8E;
        background: #f8f5fb;
        transform: none;
        box-shadow: none;
    }
    .ftt-action-icon {
        font-size: 24px;
        margin: 0;
        animation: none;
    }
    .ftt-action-card h3 {
        font-size: 11px;
        margin: 0;
        text-align: center;
        color: #5B347A;
        font-weight: 600;
        white-space: nowrap;
    }
    .ftt-action-card p {
        display: none;
    }

    /* Dashboard sections */
    .ftt-dashboard-section {
        padding: 12px;
        margin-bottom: 8px;
        border-radius: 6px;
    }

    /* Make the section headings look tappable on mobile */
    .ftt-dashboard-section .ftt-mob-toggle-hdr {
        padding: 2px 0 8px;
    }

    .ftt-dashboard-header {
        flex-direction: column;
        gap: 10px;
        margin-bottom: 12px;
    }

    .ftt-dashboard-header h3 {
        font-size: 17px;
    }

    .ftt-dashboard-header .button {
        width: 100%;
        justify-content: center;
    }

    .ftt-family-section h3 {
        font-size: 17px;
        cursor: pointer;
    }
}

@media (max-width: 480px) {
    .ftt-container {
        padding: 5px;
    }

    .ftt-nav-logo h1 {
        font-size: 18px;
    }

    .ftt-nav-link {
        font-size: 11px;
        padding: 5px 8px;
    }

    .ftt-welcome-content h2 {
        font-size: 20px;
    }

    .ftt-group-summary-header h3 {
        font-size: 18px;
    }

    .ftt-group-summary-stats {
        grid-template-columns: 1fr 1fr;
    }

    .ftt-stat-box {
        padding: 12px 8px;
        gap: 8px;
    }

    .ftt-stat-box:nth-child(2) {
        border-right: none;
    }

    .ftt-stat-box:nth-child(3) {
        grid-column: span 2;
        border-top: 1px solid #e9ecef;
        border-right: none;
        justify-content: center;
    }

    .ftt-stat-box .dashicons {
        font-size: 20px;
        width: 20px;
        height: 20px;
    }

    .ftt-stat-box strong {
        font-size: 16px;
    }

    .ftt-group-stats {
        grid-template-columns: 1fr;
    }

    .ftt-action-card {
        padding: 10px 12px;
    }
}
</style>

<script>
var fttSelectedGroupId = <?php echo $selected_group_id ? intval($selected_group_id) : 'null'; ?>;

jQuery(document).ready(function($) {
    // Quick Add Child button
    $('#ftt-quick-add-child').on('click', function(e) {
        e.preventDefault();
        window.location.href = '<?php echo esc_js(home_url('/manage-family/')); ?>';
    });

    // Quick Invite Adult button
    $('#ftt-quick-invite-adult').on('click', function(e) {
        e.preventDefault();
        window.location.href = '<?php echo esc_js(home_url('/manage-family/')); ?>';
    });

    // ── Mobile collapsible sections ─────────────────────────────────────
    function initMobileCollapsibles() {
        if ($(window).width() > 768) return;

        var STORE_KEY = 'ftt_mob_collapse';
        var state;
        try { state = JSON.parse(localStorage.getItem(STORE_KEY) || '{}'); } catch(e) { state = {}; }

        function save() {
            try { localStorage.setItem(STORE_KEY, JSON.stringify(state)); } catch(e) {}
        }

        // Make $header the toggle for $body, persisted under `id`.
        // defaultCollapsed = true → starts collapsed on first visit.
        function attach($header, $body, id, defaultCollapsed) {
            if (!$header.length || !$body.length) return;
            var collapsed = (id in state) ? state[id] : defaultCollapsed;

            $header.addClass('ftt-mob-toggle-hdr');
            var $chev = $('<span class="ftt-mob-chevron" aria-hidden="true"></span>').appendTo($header);

            state[id] = collapsed;
            if (collapsed) { $body.hide(); $chev.text('›'); } else { $chev.text('˅'); }

            $header.css('cursor', 'pointer').on('click', function(e) {
                // Don't hijack link/button clicks inside the header
                if ($(e.target).is('a, button, input, select')) return;
                state[id] = !state[id];
                $body.slideToggle(180);
                $chev.text(state[id] ? '›' : '˅');
                save();
            });
        }

        // 1. Group summary stats (tap the header card to expand)
        attach(
            $('.ftt-group-summary-header'),
            $('.ftt-group-summary-stats'),
            'group-stats', true
        );

        // 2. Children list — make the section h3 the toggle
        attach(
            $('.ftt-family-section h3').first(),
            $('.ftt-children-list'),
            'children', true
        );

        // 3. Dashboard sections — collapse secondary ones, keep primary open
        var primarySections = ['ftt-flights-needed', 'ftt-upcoming-travel'];
        $('.ftt-dashboard-section').each(function() {
            var $sec     = $(this);
            var $content = $sec.find('.ftt-dashboard-content').first();
            var $heading = $sec.find('h4, h3').first();
            var id       = $content.attr('id') || ('mob-sec-' + $sec.index());
            if (primarySections.indexOf(id) !== -1) return; // always open
            attach($heading, $content, id, true);
        });
    }

    initMobileCollapsibles();
});
</script>
