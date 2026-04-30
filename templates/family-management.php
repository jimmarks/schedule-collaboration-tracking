<?php
/**
 * Template: Family Management
 * 
 * Manage children, co-parents, and family settings
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Require login
if (!is_user_logged_in()) {
    echo '<p>' . esc_html__('Please log in to manage your family.', 'schedule-collaboration-tracking') . '</p>';
    return;
}

$current_user = wp_get_current_user();

// Check if managing a specific group (v2.1)
// Accept an opaque token (preferred) or fall back to a raw integer for legacy links.
$group_id = null;
$group    = null;
$is_group_admin = false;

if ( isset( $_GET['group'] ) && class_exists( 'FTT_Family_Groups' ) ) {
    $raw = sanitize_text_field( wp_unslash( $_GET['group'] ) );

    // Try token resolution first (non-numeric string is always a token).
    if ( ! ctype_digit( $raw ) ) {
        $group_id = FTT_Family_Groups::resolve_group_token( $raw );
    } else {
        // Numeric: legacy link — resolve via token to validate it's a real group.
        $numeric_id = (int) $raw;
        // Silently accept the legacy numeric ID so old bookmarks still work,
        // but we won't expose the raw integer in any new links we generate.
        $group_id = $numeric_id > 0 ? $numeric_id : null;
    }
}

if ($group_id && class_exists('FTT_Family_Groups')) {
    $group = FTT_Family_Groups::get_group($group_id);
    
    // Verify user has access to this group
    if (!$group || !FTT_Family_Groups::can_manage_group($group_id, $current_user->ID)) {
        echo '<p>' . esc_html__('You do not have permission to manage this group.', 'schedule-collaboration-tracking') . '</p>';
        return;
    }
    
    $is_group_admin = true;
    $group_members = FTT_Family_Groups::get_group_members($group_id);
    $children = array_filter($group_members, function($m) { return $m->role === 'child'; });
    $parents = array_filter($group_members, function($m) { return $m->role === 'parent'; });
} else {
    // Legacy mode - use old relationship system
    $is_parent = FTT_Roles::is_parent($current_user->ID);
    $children_ids = FTT_Roles::get_children($current_user->ID);
    $children = array_map(function($id) {
        $user = get_userdata($id);
        return (object)['user_id' => $id, 'role' => 'child', 'display_name' => $user ? $user->display_name : ''];
    }, $children_ids);
    
    $parents_ids = FTT_Roles::get_parents($current_user->ID);
    $parents = array_map(function($id) {
        $user = get_userdata($id);
        return (object)['user_id' => $id, 'role' => 'parent', 'display_name' => $user ? $user->display_name : ''];
    }, $parents_ids);
}
?>

<div class="ftt-container">
    <?php
    $ftt_page_title  = $group
        ? sprintf(__('Manage %s', 'schedule-collaboration-tracking'), $group->name)
        : __('Manage Family', 'schedule-collaboration-tracking');
    $ftt_active_slug = 'groups';
    include FTT_PLUGIN_DIR . 'templates/partials/nav.php';
    ?>

    <!-- Breadcrumb: Back to Groups (contextual — 2 levels deep) -->
    <p class="ftt-breadcrumb">
        <?php if ($group): ?>
            <a href="<?php echo esc_url(FTT_Pages::get_page_url('groups') ?: home_url('/ftt-groups/')); ?>">
                ← <?php esc_html_e('Back to Family Groups', 'schedule-collaboration-tracking'); ?>
            </a>
        <?php else: ?>
            <a href="<?php echo esc_url(FTT_Pages::get_page_url('dashboard') ?: home_url('/ftt-dashboard/')); ?>">
                ← <?php esc_html_e('Back to Dashboard', 'schedule-collaboration-tracking'); ?>
            </a>
        <?php endif; ?>
    </p>

    <?php if ($group): ?>
    <!-- Group Info Bar -->
    <div class="ftt-group-info-bar">
        <div class="ftt-group-color-bar" style="background-color: <?php echo esc_attr($group->color); ?>"></div>
        <div class="ftt-group-info-content">
            <div class="ftt-group-info-text">
                <?php if ($group->description): ?>
                    <p class="ftt-group-description-text"><?php echo esc_html($group->description); ?></p>
                <?php endif; ?>
                <p class="ftt-group-meta">
                    <span><?php echo esc_html($group->member_count - $group->child_count); ?> <?php echo ($group->member_count - $group->child_count) == 1 ? 'parent' : 'parents'; ?></span>
                    <span class="ftt-separator">•</span>
                    <span><?php echo esc_html($group->child_count); ?> <?php echo $group->child_count == 1 ? 'child' : 'children'; ?></span>
                </p>
            </div>
            <a href="<?php echo esc_url(home_url('/ftt-calendar/?group=' . FTT_Family_Groups::get_group_token($group->id))); ?>" class="button button-primary">
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php esc_html_e('View Calendar', 'schedule-collaboration-tracking'); ?>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Children Section -->
    <div class="ftt-management-section ftt-children-section">
        <div class="ftt-section-header">
            <h2>👦 <?php esc_html_e('Children', 'schedule-collaboration-tracking'); ?></h2>
            <button type="button" class="button button-primary" id="ftt-add-child-btn">
                <span class="dashicons dashicons-plus"></span> <?php esc_html_e('Add Child', 'schedule-collaboration-tracking'); ?>
            </button>
        </div>

        <div id="ftt-children-list" class="ftt-children-grid">
            <?php if (!empty($children)): ?>
                <?php foreach ($children as $child):
                    $child_id = is_object($child) ? $child->user_id : $child;
                    $child_user = get_userdata($child_id);
                    if (!$child_user) continue;
                    
                    $child_age = get_user_meta($child_id, 'child_age', true);
                    $child_grade = get_user_meta($child_id, 'child_grade', true);
                    $child_school = get_user_meta($child_id, 'child_school', true);
                    
                    // Use FTT_Child_Colors for consistent color handling
                    $child_color = '#2196F3'; // default
                    $color_data = null;
                    if (class_exists('FTT_Child_Colors')) {
                        $color_data = FTT_Child_Colors::get_child_color($child_id);
                        if ($color_data && isset($color_data['hex'])) {
                            $child_color = $color_data['hex'];
                        }
                    }
                    if (!$color_data) {
                        // Fallback to direct meta read for backwards compatibility
                        $child_color = get_user_meta($child_id, 'child_color', true) ?: '#2196F3';
                    }
                ?>
                    <div class="ftt-child-card" data-child-id="<?php echo esc_attr($child_id); ?>">
                        <div class="ftt-child-avatar" style="background-color: <?php echo esc_attr($child_color); ?>">
                            <?php echo esc_html(strtoupper(substr($child_user->first_name, 0, 1))); ?>
                        </div>
                        <div class="ftt-child-info">
                            <h3><?php echo esc_html($child_user->display_name); ?></h3>
                            <?php if ($child_age): ?>
                                <p class="ftt-child-meta"><?php printf(esc_html__('Age: %s', 'schedule-collaboration-tracking'), esc_html($child_age)); ?></p>
                            <?php endif; ?>
                            <?php if ($child_grade): ?>
                                <p class="ftt-child-meta"><?php printf(esc_html__('Grade: %s', 'schedule-collaboration-tracking'), esc_html($child_grade)); ?></p>
                            <?php endif; ?>
                            <?php if ($child_school): ?>
                                <p class="ftt-child-meta"><?php echo esc_html($child_school); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="ftt-child-actions">
                            <button type="button" class="button button-small ftt-edit-child" data-child-id="<?php echo esc_attr($child_id); ?>">
                                <span class="dashicons dashicons-edit"></span> <?php esc_html_e('Edit', 'schedule-collaboration-tracking'); ?>
                            </button>
                            <button type="button" class="button button-small button-link-delete ftt-remove-child" data-child-id="<?php echo esc_attr($child_id); ?>">
                                <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Remove', 'schedule-collaboration-tracking'); ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="ftt-empty-state">
                    <p><?php esc_html_e('No children added yet. Click "Add Child" to get started.', 'schedule-collaboration-tracking'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Co-Parents/Adults Section -->
    <div class="ftt-management-section ftt-adults-section">
        <div class="ftt-section-header">
            <h2>👥 <?php esc_html_e('Co-Parents & Guardians', 'schedule-collaboration-tracking'); ?></h2>
            <button type="button" class="button button-primary" id="ftt-invite-adult-btn">
                <span class="dashicons dashicons-email"></span> <?php esc_html_e('Invite Adult', 'schedule-collaboration-tracking'); ?>
            </button>
        </div>

        <div id="ftt-adults-list" class="ftt-adults-grid">
            <?php if (!empty($parents)): ?>
                <?php foreach ($parents as $parent):
                    $parent_id = is_object($parent) ? $parent->user_id : $parent;
                    if ($parent_id == $current_user->ID) continue; // Skip self
                    
                    $parent_user = get_userdata($parent_id);
                    if (!$parent_user) continue;
                    
                    $relationship = is_object($parent) && isset($parent->relationship) ? $parent->relationship : get_user_meta($parent_id, 'relationship_to_' . $current_user->ID, true);
                    $can_manage = is_object($parent) && isset($parent->can_manage_group) ? $parent->can_manage_group : false;
                ?>
                    <div class="ftt-adult-card" data-adult-id="<?php echo esc_attr($parent_id); ?>">
                        <div class="ftt-adult-avatar">
                            <?php echo esc_html(strtoupper(substr($parent_user->first_name, 0, 1))); ?>
                        </div>
                        <div class="ftt-adult-info">
                            <h3><?php echo esc_html($parent_user->display_name); ?></h3>
                            <p class="ftt-adult-email"><?php echo esc_html($parent_user->user_email); ?></p>
                            <?php if ($relationship): ?>
                                <p class="ftt-adult-meta"><?php echo esc_html(ucfirst($relationship)); ?></p>
                            <?php endif; ?>
                            <?php if ($can_manage): ?>
                                <span class="ftt-badge-admin">Admin</span>
                            <?php endif; ?>
                        </div>
                        <div class="ftt-adult-actions">
                            <button type="button" class="button button-small button-link-delete ftt-remove-adult" data-adult-id="<?php echo esc_attr($parent_id); ?>">
                                <span class="dashicons dashicons-dismiss"></span> <?php esc_html_e('Remove Access', 'schedule-collaboration-tracking'); ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="ftt-empty-state">
                    <p><?php esc_html_e('No linked adults yet. Invite co-parents or guardians to share calendar access.', 'schedule-collaboration-tracking'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pending Invitations -->
        <?php
        $invitations = get_user_meta($current_user->ID, 'ftt_adult_invitations', true);
        if (is_array($invitations) && !empty($invitations)):
            // Filter out expired or accepted invitations
            $pending = array_filter($invitations, function($inv) {
                return isset($inv['status']) && $inv['status'] === 'pending' && $inv['expires'] > time();
            });
            
            if (!empty($pending)):
        ?>
        <div class="ftt-pending-invitations">
            <div class="ftt-subsection-header ftt-toggle-pending-invitations">
                <h3 class="ftt-subsection-title">
                    ⏳ <?php esc_html_e('Pending Invitations', 'schedule-collaboration-tracking'); ?>
                    <span class="ftt-count-badge"><?php echo count($pending); ?></span>
                    <span class="dashicons dashicons-arrow-down-alt2 ftt-toggle-icon"></span>
                </h3>
            </div>
            <div class="ftt-invitations-list">
                <?php 
                foreach ($pending as $code => $invite): 
                    $days_since = floor((time() - $invite['created']) / DAY_IN_SECONDS);
                    $days_until_expire = floor(($invite['expires'] - time()) / DAY_IN_SECONDS);
                    $expire_date = date_i18n(get_option('date_format'), $invite['expires']);
                    
                    // Format relationship with proper capitalization
                    $relationship = ucwords(str_replace('_', '-', $invite['relationship']));
                ?>
                <div class="ftt-invitation-card" data-invite-code="<?php echo esc_attr($code); ?>">
                    <div class="ftt-invitation-info">
                        <p class="ftt-invitation-main">
                            <strong><?php echo esc_html($relationship); ?></strong> - <?php echo esc_html($invite['email']); ?>
                        </p>
                        <p class="ftt-invitation-meta">
                            <span class="ftt-invitation-sent">
                                <?php printf(esc_html__('Sent %d day(s) ago', 'schedule-collaboration-tracking'), $days_since); ?>
                            </span>
                            <span class="ftt-invitation-separator">•</span>
                            <span class="ftt-invitation-expires <?php echo $days_until_expire <= 1 ? 'ftt-expiring-soon' : ''; ?>">
                                <?php 
                                if ($days_until_expire == 0) {
                                    esc_html_e('Expires today', 'schedule-collaboration-tracking');
                                } else {
                                    printf(esc_html__('Expires in %d day(s)', 'schedule-collaboration-tracking'), $days_until_expire);
                                }
                                ?>
                            </span>
                        </p>
                    </div>
                    <div class="ftt-invitation-actions">
                        <button type="button" class="button button-small ftt-resend-invite" data-invite-code="<?php echo esc_attr($code); ?>" title="<?php esc_attr_e('Resend invitation email', 'schedule-collaboration-tracking'); ?>">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Resend', 'schedule-collaboration-tracking'); ?>
                        </button>
                        <button type="button" class="button button-small button-link-delete ftt-cancel-invite" data-invite-code="<?php echo esc_attr($code); ?>" title="<?php esc_attr_e('Cancel invitation', 'schedule-collaboration-tracking'); ?>">
                            <span class="dashicons dashicons-no"></span>
                            <?php esc_html_e('Cancel', 'schedule-collaboration-tracking'); ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php 
            endif;
        endif; 
        ?>
    </div>

    <!-- Event Preferences Section -->
    <div class="ftt-management-section ftt-event-preferences-section">
        <div class="ftt-section-header">
            <h2>🎯 <?php esc_html_e('Event Preferences', 'schedule-collaboration-tracking'); ?></h2>
            <p class="ftt-section-description"><?php esc_html_e('Choose which types of events you want to see on your calendar', 'schedule-collaboration-tracking'); ?></p>
        </div>

        <form id="ftt-event-preferences-form" class="ftt-event-categories-form">
            <?php
            $user_preferences = get_user_meta($current_user->ID, 'ftt_visible_event_categories', true);
            if (!is_array($user_preferences)) {
                $user_preferences = array(); // Show all by default
            }
            
            $categories = FTT_CPT::get_event_categories();
            $event_types = FTT_CPT::get_event_types();
            
            foreach ($categories as $cat_key => $category):
                $is_checked = empty($user_preferences) || in_array($cat_key, $user_preferences);
            ?>
                <div class="ftt-category-checkbox-wrapper">
                    <label class="ftt-category-checkbox">
                        <input type="checkbox" 
                               name="visible_categories[]" 
                               value="<?php echo esc_attr($cat_key); ?>"
                               <?php checked($is_checked); ?>>
                        <span class="ftt-category-icon"><?php echo $category['icon']; ?></span>
                        <span class="ftt-category-label"><?php echo esc_html($category['label']); ?></span>
                        <span class="ftt-category-count"><?php echo count($category['types']); ?> types</span>
                    </label>
                    <button type="button" class="ftt-category-expand" data-category="<?php echo esc_attr($cat_key); ?>">▼</button>
                    <div class="ftt-category-types-list" id="ftt-types-<?php echo esc_attr($cat_key); ?>" style="display: none;">
                        <ul>
                            <?php foreach ($category['types'] as $type_key): 
                                if (isset($event_types[$type_key])): ?>
                                    <li><strong><?php echo esc_html($event_types[$type_key]); ?></strong> <code><?php echo esc_html($type_key); ?></code></li>
                                <?php endif; 
                            endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="ftt-form-actions">
                <button type="submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-yes"></span> <?php esc_html_e('Save Preferences', 'schedule-collaboration-tracking'); ?>
                </button>
                <span id="ftt-preferences-message" class="ftt-message"></span>
            </div>
        </form>
    </div>

    <!-- My Settings Section -->
    <div class="ftt-management-section ftt-settings-section">
        <div class="ftt-section-header">
            <h2>⚙️ <?php esc_html_e('My Settings', 'schedule-collaboration-tracking'); ?></h2>
            <p class="ftt-section-description"><?php esc_html_e('Personal preferences for travel and scheduling', 'schedule-collaboration-tracking'); ?></p>
        </div>

        <form id="ftt-user-preferences-form" class="ftt-user-preferences-form">
            <div class="ftt-settings-grid">
                <div class="ftt-setting-card">
                    <div class="ftt-setting-icon">
                        <span class="dashicons dashicons-airplane"></span>
                    </div>
                    <div class="ftt-setting-content">
                        <label for="home_airport" class="ftt-setting-label"><?php esc_html_e('Home Airport', 'schedule-collaboration-tracking'); ?></label>
                        <input type="text" 
                               id="home_airport" 
                               name="home_airport" 
                               value="<?php echo esc_attr(get_user_meta($current_user->ID, 'ftt_home_airport', true)); ?>"
                               placeholder="e.g., BDL" 
                               maxlength="3"
                               style="text-transform: uppercase; width: 100%; max-width: 150px;"
                               class="ftt-input-large">
                        <small class="ftt-help-text">
                            <?php esc_html_e('Your nearest airport (3-letter code)', 'schedule-collaboration-tracking'); ?> 
                            | <a href="https://airportcodes.io/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Look up airport codes', 'schedule-collaboration-tracking'); ?></a>
                        </small>
                    </div>
                </div>
                
                <div class="ftt-setting-card">
                    <div class="ftt-setting-icon">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="ftt-setting-content">
                        <label for="timezone" class="ftt-setting-label"><?php esc_html_e('Time Zone', 'schedule-collaboration-tracking'); ?></label>
                        <select id="timezone" name="timezone" class="ftt-select-large" style="width: 100%; max-width: 250px;">
                            <?php 
                            $user_timezone = get_user_meta($current_user->ID, 'ftt_timezone', true) ?: wp_timezone_string();
                            ?>
                            <option value="">Select timezone...</option>
                            <option value="America/New_York" <?php selected($user_timezone, 'America/New_York'); ?>>Eastern Time</option>
                            <option value="America/Chicago" <?php selected($user_timezone, 'America/Chicago'); ?>>Central Time</option>
                            <option value="America/Denver" <?php selected($user_timezone, 'America/Denver'); ?>>Mountain Time</option>
                            <option value="America/Los_Angeles" <?php selected($user_timezone, 'America/Los_Angeles'); ?>>Pacific Time</option>
                            <option value="America/Anchorage" <?php selected($user_timezone, 'America/Anchorage'); ?>>Alaska Time</option>
                            <option value="Pacific/Honolulu" <?php selected($user_timezone, 'Pacific/Honolulu'); ?>>Hawaii Time</option>
                        </select>
                        <small class="ftt-help-text"><?php esc_html_e('For accurate event times', 'schedule-collaboration-tracking'); ?></small>
                    </div>
                </div>
            </div>
            
            <div class="ftt-form-actions">
                <button type="submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-yes"></span> <?php esc_html_e('Save Settings', 'schedule-collaboration-tracking'); ?>
                </button>
                <span id="ftt-settings-message" class="ftt-message"></span>
            </div>
        </form>
    </div>

    <!-- Subscription Management Section -->
    <?php if (class_exists('FTT_Billing_Manager')): 
        $billing = FTT_Billing_Manager::get_billing_summary($current_user->ID);
        if ($billing && !empty($billing['status'])):
    ?>
    <div class="ftt-management-section ftt-subscription-section">
        <div class="ftt-section-header">
            <h2>💳 <?php esc_html_e('Subscription', 'schedule-collaboration-tracking'); ?></h2>
            <p class="ftt-section-description"><?php esc_html_e('Manage your subscription and billing', 'schedule-collaboration-tracking'); ?></p>
        </div>

        <div class="ftt-subscription-summary">
            <div class="ftt-subscription-status <?php echo esc_attr('status-' . $billing['status']); ?>">
                <div class="ftt-status-badge">
                    <?php echo esc_html($billing['status_label']); ?>
                </div>
                
                <?php if ($billing['in_trial']): ?>
                    <p class="ftt-trial-info">
                        <?php printf(esc_html__('Trial ends in %d days', 'schedule-collaboration-tracking'), $billing['days_until_charge']); ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="ftt-subscription-details">
                <div class="ftt-detail-row">
                    <span class="ftt-detail-label"><?php esc_html_e('Plan:', 'schedule-collaboration-tracking'); ?></span>
                    <span class="ftt-detail-value">
                        <?php echo esc_html($billing['total_price'] . '/' . $billing['period']); ?>
                    </span>
                </div>
                
                <div class="ftt-detail-row">
                    <span class="ftt-detail-label"><?php esc_html_e('Children:', 'schedule-collaboration-tracking'); ?></span>
                    <span class="ftt-detail-value">
                        <?php printf(esc_html__('%d of %d', 'schedule-collaboration-tracking'), $billing['children_count'], $billing['allowed_children']); ?>
                    </span>
                </div>
                
                <?php if (!$billing['in_trial']): ?>
                <div class="ftt-detail-row">
                    <span class="ftt-detail-label"><?php esc_html_e('Next Billing:', 'schedule-collaboration-tracking'); ?></span>
                    <span class="ftt-detail-value">
                        <?php echo esc_html($billing['next_billing_date']); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <div class="ftt-subscription-actions">
                <a href="<?php echo esc_url(home_url('/manage-subscription/')); ?>" class="button button-primary">
                    <?php esc_html_e('Manage Subscription', 'schedule-collaboration-tracking'); ?>
                </a>
            </div>
        </div>
    </div>
    <?php endif; endif; ?>
</div>

<!-- Add/Edit Child Modal -->
<div id="ftt-child-modal" class="ftt-modal" style="display:none;">
    <div class="ftt-modal-content">
        <div class="ftt-modal-header">
            <h2 id="ftt-child-modal-title"><?php esc_html_e('Add Child', 'schedule-collaboration-tracking'); ?></h2>
            <button type="button" class="ftt-modal-close-x">&times;</button>
        </div>
        <form id="ftt-child-form">
            <input type="hidden" name="child_id" id="ftt-child-id">
            
            <div class="ftt-form-group">
                <label for="child-first-name"><?php esc_html_e('First Name', 'schedule-collaboration-tracking'); ?> *</label>
                <input type="text" id="child-first-name" name="first_name" required>
            </div>
            
            <div class="ftt-form-group">
                <label for="child-last-name"><?php esc_html_e('Last Name', 'schedule-collaboration-tracking'); ?> *</label>
                <input type="text" id="child-last-name" name="last_name" required>
            </div>
            
            <div class="ftt-form-group">
                <label for="child-email"><?php esc_html_e('Email (optional)', 'schedule-collaboration-tracking'); ?></label>
                <input type="email" id="child-email" name="email">
                <small><?php esc_html_e('Enter if child has their own account', 'schedule-collaboration-tracking'); ?></small>
            </div>
            
            <div class="ftt-form-row">
                <div class="ftt-form-group">
                    <label for="child-age"><?php esc_html_e('Age', 'schedule-collaboration-tracking'); ?></label>
                    <input type="number" id="child-age" name="age" min="3" max="25">
                </div>
                
                <div class="ftt-form-group">
                    <label for="child-grade"><?php esc_html_e('Grade', 'schedule-collaboration-tracking'); ?></label>
                    <input type="text" id="child-grade" name="grade" placeholder="e.g., 5th, 10th">
                </div>
            </div>
            
            <div class="ftt-form-group">
                <label for="child-school"><?php esc_html_e('School', 'schedule-collaboration-tracking'); ?></label>
                <input type="text" id="child-school" name="school">
            </div>
            
            <div class="ftt-form-group">
                <label for="child-color"><?php esc_html_e('Color (for calendar)', 'schedule-collaboration-tracking'); ?></label>
                <input type="color" id="child-color" name="color" value="#2196F3">
            </div>
            
            <div class="ftt-modal-actions">
                <button type="button" class="button ftt-modal-close"><?php esc_html_e('Cancel', 'schedule-collaboration-tracking'); ?></button>
                <button type="submit" class="button button-primary"><?php esc_html_e('Save Child', 'schedule-collaboration-tracking'); ?></button>
            </div>
            
            <div id="ftt-child-form-message" class="ftt-message"></div>
        </form>
    </div>
</div>

<!-- Invite Adult Modal -->
<div id="ftt-invite-adult-modal" class="ftt-modal" style="display:none;">
    <div class="ftt-modal-content">
        <div class="ftt-modal-header">
            <h2><?php esc_html_e('Invite Co-Parent or Guardian', 'schedule-collaboration-tracking'); ?></h2>
            <button type="button" class="ftt-modal-close-x">&times;</button>
        </div>
        <form id="ftt-invite-adult-form">
            <div class="ftt-form-group">
                <label for="adult-email"><?php esc_html_e('Email Address', 'schedule-collaboration-tracking'); ?> *</label>
                <input type="email" id="adult-email" name="email" required>
                <small><?php esc_html_e('They will receive an invitation to link their account', 'schedule-collaboration-tracking'); ?></small>
            </div>
            
            <div class="ftt-form-group">
                <label for="adult-relationship"><?php esc_html_e('Relationship', 'schedule-collaboration-tracking'); ?></label>
                <select id="adult-relationship" name="relationship">
                    <option value=""><?php esc_html_e('Select...', 'schedule-collaboration-tracking'); ?></option>
                    <option value="co-parent"><?php esc_html_e('Co-Parent', 'schedule-collaboration-tracking'); ?></option>
                    <option value="guardian"><?php esc_html_e('Guardian', 'schedule-collaboration-tracking'); ?></option>
                    <option value="grandparent"><?php esc_html_e('Grandparent', 'schedule-collaboration-tracking'); ?></option>
                    <option value="other"><?php esc_html_e('Other', 'schedule-collaboration-tracking'); ?></option>
                </select>
            </div>
            
            <div class="ftt-modal-actions">
                <button type="button" class="button ftt-modal-close"><?php esc_html_e('Cancel', 'schedule-collaboration-tracking'); ?></button>
                <button type="submit" class="button button-primary"><?php esc_html_e('Send Invitation', 'schedule-collaboration-tracking'); ?></button>
            </div>
            
            <div id="ftt-invite-adult-message" class="ftt-message"></div>
        </form>
    </div>
</div>

<style>
.ftt-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.ftt-page-header h1 {
    margin: 0;
}

.ftt-management-section {
    background: white;
    border-radius: 8px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.ftt-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.ftt-section-header h2 {
    margin: 0;
}

.ftt-section-description {
    color: #666;
    margin: 10px 0 0 0;
}

.ftt-children-grid,
.ftt-adults-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.ftt-child-card,
.ftt-adult-card {
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: flex-start;
    gap: 15px;
    transition: all 0.2s;
}

.ftt-child-card:hover,
.ftt-adult-card:hover {
    border-color: #2196F3;
    box-shadow: 0 2px 8px rgba(33, 150, 243, 0.2);
}

.ftt-child-avatar,
.ftt-adult-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 20px;
    flex-shrink: 0;
}

.ftt-adult-avatar {
    background-color: #6A3E8E;
}

.ftt-child-info,
.ftt-adult-info {
    flex: 1;
}

.ftt-child-info h3,
.ftt-adult-info h3 {
    margin: 0 0 5px 0;
    font-size: 18px;
}

.ftt-child-meta,
.ftt-adult-meta,
.ftt-adult-email {
    margin: 2px 0;
    color: #666;
    font-size: 14px;
}

.ftt-child-actions,
.ftt-adult-actions {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.ftt-empty-state {
    text-align: center;
    padding: 40px;
    color: #999;
}

.ftt-event-categories-form {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
}

.ftt-category-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.ftt-category-checkbox:hover {
    border-color: #2196F3;
    background: #f5f5f5;
}

.ftt-category-checkbox input[type="checkbox"] {
    margin: 0;
    cursor: pointer;
}

.ftt-category-icon {
    font-size: 24px;
}

.ftt-category-label {
    flex: 1;
    font-weight: 500;
    cursor: pointer;
}

.ftt-category-count {
    font-size: 12px;
    color: #999;
}

.ftt-category-expand.expanded {
    transform: rotate(180deg);
}

.ftt-category-checkbox-wrapper {
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    position: relative;
}

.ftt-category-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px;
    padding-right: 50px; /* Make room for expand button */
    border: none;
    margin: 0;
}

.ftt-category-expand {
    position: absolute;
    top: 15px;
    right: 15px;
    background: none;
    border: none;
    color: #666;
    cursor: pointer;
    padding: 8px;
    font-size: 14px;
    line-height: 1;
    transition: transform 0.2s;
    z-index: 10;
    user-select: none;
}

.ftt-category-expand:hover {
    color: #2271b1;
}

.ftt-category-expand:focus {
    outline: none;
}

.ftt-category-types-list {
    background: #f9f9f9;
    border-top: 1px solid #e0e0e0;
    padding: 0 15px 15px 15px;
}

.ftt-category-types-list ul {
    margin: 10px 0 0 0;
    padding-left: 35px;
    list-style: disc;
}

.ftt-category-types-list li {
    margin: 8px 0;
    font-size: 13px;
    color: #666;
}

.ftt-category-types-list strong {
    color: #333;
}

.ftt-category-types-list code {
    background: #e0e0e0;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    color: #666;
    margin-left: 5px;
}

.ftt-form-actions {
    grid-column: 1 / -1;
    display: flex;
    align-items: center;
    gap: 15px;
    margin-top: 20px;
}

/* Modal Styles */
.ftt-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}

.ftt-modal-content {
    background: white;
    border-radius: 8px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.ftt-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
}

.ftt-modal-header h2 {
    margin: 0;
}

.ftt-modal-close-x {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: #999;
    padding: 0;
    width: 30px;
    height: 30px;
    line-height: 1;
}

.ftt-modal-close-x:hover {
    color: #333;
}

.ftt-modal-actions .button {
    min-width: 100px;
    white-space: nowrap;
    padding: 6px 12px;
    font-size: 13px;
    line-height: 1.5;
}

.ftt-modal-actions .button-primary {
    min-width: 120px;
}

.ftt-modal-content form {
    padding: 20px;
}

.ftt-form-group {
    margin-bottom: 20px;
}

.ftt-form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.ftt-form-group input,
.ftt-form-group select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.ftt-form-group small {
    display: block;
    margin-top: 5px;
    color: #666;
    font-size: 12px;
}

.ftt-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.ftt-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.ftt-message {
    display: block;
    padding: 10px;
    border-radius: 4px;
    margin-top: 10px;
}

.ftt-message.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.ftt-message.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Group Info Bar */
.ftt-group-info-bar {
    background: white;
    border-radius: 8px;
    padding: 0;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.ftt-group-color-bar {
    height: 6px;
    width: 100%;
}

.ftt-group-info-content {
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.ftt-group-info-text {
    flex: 1;
}

.ftt-group-description-text {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 15px;
}

.ftt-group-meta {
    margin: 0;
    color: #666;
    font-size: 14px;
}

.ftt-separator {
    margin: 0 8px;
}

.ftt-badge-admin {
    display: inline-block;
    background: #2271b1;
    color: white;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    margin-left: 10px;
}

/* Back Button Styling */
.ftt-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 8px 16px;
    background: white;
    border: 2px solid #2271b1;
    border-radius: 4px;
    color: #2271b1;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
}

.ftt-back-btn:hover {
    background: #2271b1;
    color: white;
    text-decoration: none;
}

.ftt-back-btn .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

/* ==================== Mobile Responsiveness ==================== */

@media (max-width: 768px) {
    .ftt-management-container {
        padding: 10px;
    }
    
    .ftt-page-header {
        padding: 15px;
    }
    
    .ftt-page-header h1 {
        font-size: 24px;
    }
    
    .ftt-back-btn {
        width: 100%;
        justify-content: center;
        margin-top: 10px;
    }
    
    .ftt-group-info-bar {
        flex-direction: column;
        padding: 15px;
    }
    
    .ftt-group-info-content {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }
    
    .ftt-group-info-content .button {
        width: 100%;
        justify-content: center;
    }
    
    .ftt-section-header {
        padding: 15px;
    }
    
    .ftt-section-header h2 {
        font-size: 20px;
    }
    
    .ftt-child-list,
    .ftt-coparent-list {
        grid-template-columns: 1fr !important;
    }
    
    .ftt-child-card,
    .ftt-coparent-card {
        padding: 15px;
    }
    
    .ftt-child-card h3,
    .ftt-coparent-card h3 {
        font-size: 16px;
    }
    
    .ftt-card-actions {
        flex-direction: column;
        width: 100%;
    }
    
    .ftt-card-actions .button {
        width: 100%;
        white-space: nowrap;
    }
    
    .ftt-add-child-btn,
    .ftt-add-coparent-btn {
        width: 100%;
        justify-content: center;
        padding: 12px;
    }
    
    .ftt-category-checkbox-wrapper {
        padding: 12px;
    }
    
    .ftt-category-checkbox {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .ftt-form-field {
        margin-bottom: 15px;
    }
    
    .ftt-form-field input,
    .ftt-form-field select,
    .ftt-form-field textarea {
        font-size: 16px !important; /* Prevent zoom on iOS */
    }
    
    .ftt-form-actions {
        flex-direction: column;
    }
    
    .ftt-form-actions .button {
        width: 100%;
        padding: 12px;
    }
    
    .ftt-modal-content {
        width: 95%;
        margin: 10% auto;
        padding: 20px;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .ftt-modal-header h3 {
        font-size: 18px;
    }
    
    .ftt-invitation-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .ftt-invitation-actions {
        width: 100%;
        justify-content: stretch;
    }
    
    .ftt-invitation-actions .button {
        flex: 1;
    }
}

@media (max-width: 480px) {
    .ftt-management-container {
        padding: 5px;
    }
    
    .ftt-page-header {
        padding: 10px;
    }
    
    .ftt-page-header h1 {
        font-size: 20px;
    }
    
    .ftt-section-header h2 {
        font-size: 18px;
    }
    
    .ftt-child-card,
    .ftt-coparent-card {
        padding: 12px;
    }
    
    .ftt-form-field input,
    .ftt-form-field select,
    .ftt-form-field textarea {
        padding: 10px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    const groupId    = <?php echo $group_id ? (int) $group_id : 'null'; ?>;
    const groupToken = <?php echo ( $group && ! empty( $group->group_token ) ) ? wp_json_encode( $group->group_token ) : 'null'; ?>;
    const isGroupMode = groupId !== null;
    
    // Add Child Button
    $('#ftt-add-child-btn').on('click', function() {
        $('#ftt-child-form')[0].reset();
        $('#ftt-child-id').val('');
        $('#ftt-child-modal-title').text('<?php esc_html_e('Add Child', 'schedule-collaboration-tracking'); ?>');
        $('#ftt-child-modal').fadeIn(200);
    });
    
    // Edit Child Button
    $(document).on('click', '.ftt-edit-child', function() {
        const childId = $(this).data('child-id');
        const card = $(this).closest('.ftt-child-card');
        
        $('#ftt-child-id').val(childId);
        $('#child-first-name').val(card.find('h3').text().split(' ')[0]);
        $('#child-last-name').val(card.find('h3').text().split(' ').slice(1).join(' '));
        
        const metaText = card.find('.ftt-child-meta').text();
        
        const age = metaText.match(/Age: (\d+)/);
        if (age) $('#child-age').val(age[1]);
        
        const grade = metaText.match(/Grade: ([^•]+)/);
        if (grade) $('#child-grade').val(grade[1].trim());
        
        const school = card.find('.ftt-child-meta:contains("School")').text();
        if (school) {
            $('#child-school').val(school.replace(/^.*:\s*/, '').trim());
        }
        
        // Get color from avatar background
        const avatarBg = card.find('.ftt-child-avatar').css('background-color');
        if (avatarBg) {
            // Convert rgb to hex
            const rgb = avatarBg.match(/\d+/g);
            if (rgb && rgb.length >= 3) {
                const hex = '#' + rgb.slice(0,3).map(x => {
                    const hex = parseInt(x).toString(16);
                    return hex.length === 1 ? '0' + hex : hex;
                }).join('');
                $('#child-color').val(hex);
            }
        }
        
        $('#ftt-child-modal-title').text('<?php esc_html_e('Edit Child', 'schedule-collaboration-tracking'); ?>');
        $('#ftt-child-modal').fadeIn(200);
    });
    
    // Save Child Form
    $('#ftt-child-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serializeArray();
        const data = {};
        formData.forEach(field => data[field.name] = field.value);
        
        const childId = $('#ftt-child-id').val();
        const endpoint = childId ? `/wp-json/ftt/v1/children/${childId}` : '/wp-json/ftt/v1/children';
        const method = childId ? 'PUT' : 'POST';
        
        // Add group_id if in group mode
        if (isGroupMode) {
            data.group_id = groupId;
        }
        
        $.ajax({
            url: endpoint,
            method: method,
            data: JSON.stringify(data),
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
            }
        }).done(function(response) {
            $('#ftt-child-form-message').addClass('success').text(response.message || 'Child saved successfully!');
            setTimeout(function() {
                $('#ftt-child-modal').fadeOut(200);
                location.reload();
            }, 1000);
        }).fail(function(xhr) {
            const message = xhr.responseJSON?.message || 'Error saving child';
            $('#ftt-child-form-message').addClass('error').text(message);
        });
    });
    
    // Remove Child
    $(document).on('click', '.ftt-remove-child', function() {
        if (!confirm('<?php esc_html_e('Remove this child?', 'schedule-collaboration-tracking'); ?>')) {
            return;
        }
        
        const childId = $(this).data('child-id');
        const endpoint = isGroupMode 
            ? `/wp-json/ftt/v1/groups/${groupId}/members/${childId}`
            : `/wp-json/ftt/v1/children/${childId}`;
        
        $.ajax({
            url: endpoint,
            method: 'DELETE',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
            }
        }).done(function(response) {
            $(`.ftt-child-card[data-child-id="${childId}"]`).fadeOut(300, function() {
                $(this).remove();
            });
        }).fail(function(xhr) {
            alert(xhr.responseJSON?.message || 'Error removing child');
        });
    });
    
    // Invite Adult Button
    $('#ftt-invite-adult-btn').on('click', function() {
        $('#ftt-invite-adult-form')[0].reset();
        $('#ftt-invite-adult-modal').fadeIn(200);
    });
    
    // Invite Adult Form
    $('#ftt-invite-adult-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serializeArray();
        const data = {};
        formData.forEach(field => data[field.name] = field.value);
        
        // Add group_id if in group mode
        if (isGroupMode) {
            data.group_id = groupId;
            data.role = 'parent';
        }
        
        const endpoint = isGroupMode
            ? `/wp-json/ftt/v1/groups/${groupId}/invitations`
            : '/wp-json/ftt/v1/invite-adult';
        
        $.ajax({
            url: endpoint,
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
            }
        }).done(function(response) {
            $('#ftt-invite-adult-message').addClass('success').text(response.message || 'Invitation sent!');
            setTimeout(function() {
                $('#ftt-invite-adult-modal').fadeOut(200);
                location.reload();
            }, 1500);
        }).fail(function(xhr) {
            const message = xhr.responseJSON?.message || 'Error sending invitation';
            $('#ftt-invite-adult-message').addClass('error').text(message);
        });
    });
    
    // Remove Adult
    $(document).on('click', '.ftt-remove-adult', function() {
        if (!confirm('<?php esc_html_e('Remove this co-parent/guardian?', 'schedule-collaboration-tracking'); ?>')) {
            return;
        }
        
        const adultId = $(this).data('adult-id');
        
        if (isGroupMode) {
            $.ajax({
                url: `/wp-json/ftt/v1/groups/${groupId}/members/${adultId}`,
                method: 'DELETE',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
                }
            }).done(function(response) {
                $(`.ftt-adult-card[data-adult-id="${adultId}"]`).fadeOut(300, function() {
                    $(this).remove();
                });
            }).fail(function(xhr) {
                alert(xhr.responseJSON?.message || 'Error removing adult');
            });
        } else {
            $.ajax({
                url: '/wp-json/ftt/v1/remove-adult',
                method: 'POST',
                data: JSON.stringify({ adult_id: adultId }),
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
                }
            }).done(function(response) {
                $(`.ftt-adult-card[data-adult-id="${adultId}"]`).fadeOut(300, function() {
                    $(this).remove();
                });
            }).fail(function(xhr) {
                alert(xhr.responseJSON?.message || 'Error removing adult');
            });
        }
    });
    
    // Modal Close Handlers
    $('.ftt-modal-close, .ftt-modal-close-x').on('click', function() {
        $(this).closest('.ftt-modal').fadeOut(200);
    });
    
    $('.ftt-modal').on('click', function(e) {
        if ($(e.target).hasClass('ftt-modal')) {
            $(this).fadeOut(200);
        }
    });
    
    // Event Preferences Form
    $('#ftt-event-preferences-form').on('submit', function(e) {
        e.preventDefault();
        
        const categories = [];
        $(this).find('input[name="visible_categories[]"]:checked').each(function() {
            categories.push($(this).val());
        });
        
        $.ajax({
            url: '/wp-json/ftt/v1/user-preferences',
            method: 'POST',
            data: JSON.stringify({ visible_categories: categories }),
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
            }
        }).done(function(response) {
            $('#ftt-preferences-message').addClass('success').text('Preferences saved!');
            setTimeout(function() {
                $('#ftt-preferences-message').removeClass('success').text('');
            }, 3000);
        }).fail(function(xhr) {
            $('#ftt-preferences-message').addClass('error').text('Error saving preferences');
        });
    });
    
    // Category Expand/Collapse
    $(document).on('click', '.ftt-category-expand', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const category = $(this).data('category');
        const typesList = $('#ftt-types-' + category);
        
        $(this).toggleClass('expanded');
        typesList.slideToggle(200);
    });
    
    // User Settings Form
    $('#ftt-user-preferences-form').on('submit', function(e) {
        e.preventDefault();
        
        const data = {
            home_airport: $('#home_airport').val().toUpperCase(),
            timezone: $('#timezone').val()
        };
        
        $.ajax({
            url: '/wp-json/ftt/v1/user-preferences',
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
            }
        }).done(function(response) {
            $('#ftt-settings-message').addClass('success').text('Settings saved!');
            setTimeout(function() {
                $('#ftt-settings-message').removeClass('success').text('');
            }, 3000);
        }).fail(function(xhr) {
            $('#ftt-settings-message').addClass('error').text('Error saving settings');
        });
    });
    
    // Toggle Pending Invitations
    $('.ftt-toggle-pending-invitations').on('click', function() {
        $(this).find('.ftt-toggle-icon').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
        $(this).next('.ftt-invitations-list').slideToggle(200);
    });
    
    // Resend Invitation
    $(document).on('click', '.ftt-resend-invite', function() {
        const code = $(this).data('invite-code');
        
        $.ajax({
            url: '/wp-json/ftt/v1/resend-invitation',
            method: 'POST',
            data: JSON.stringify({ invite_code: code }),
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
            }
        }).done(function(response) {
            alert('Invitation resent successfully!');
        }).fail(function(xhr) {
            alert(xhr.responseJSON?.message || 'Error resending invitation');
        });
    });
    
    // Cancel Invitation
    $(document).on('click', '.ftt-cancel-invite', function() {
        if (!confirm('Cancel this invitation?')) {
            return;
        }
        
        const code = $(this).data('invite-code');
        
        $.ajax({
            url: '/wp-json/ftt/v1/cancel-invitation',
            method: 'POST',
            data: JSON.stringify({ invite_code: code }),
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
            }
        }).done(function(response) {
            $(`.ftt-invitation-card[data-invite-code="${code}"]`).fadeOut(300, function() {
                $(this).remove();
            });
        }).fail(function(xhr) {
            alert(xhr.responseJSON?.message || 'Error canceling invitation');
        });
    });
});
</script>
