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
$is_parent = FTT_Roles::is_parent($current_user->ID);
$children = FTT_Roles::get_children($current_user->ID);
$parents = FTT_Roles::get_parents($current_user->ID);
?>

<div class="ftt-family-management-container">
    <div class="ftt-page-header">
        <h1><?php esc_html_e('Manage Family', 'schedule-collaboration-tracking'); ?></h1>
        <a href="<?php echo esc_url(home_url('/ftt-dashboard/')); ?>" class="button button-secondary">
            ← <?php esc_html_e('Back to Dashboard', 'schedule-collaboration-tracking'); ?>
        </a>
    </div>

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
                <?php foreach ($children as $child_id):
                    $child = get_userdata($child_id);
                    if (!$child) continue;
                    
                    $child_age = get_user_meta($child_id, 'child_age', true);
                    $child_grade = get_user_meta($child_id, 'child_grade', true);
                    $child_school = get_user_meta($child_id, 'child_school', true);
                    $child_color = get_user_meta($child_id, 'child_color', true) ?: '#2196F3';
                ?>
                    <div class="ftt-child-card" data-child-id="<?php echo esc_attr($child_id); ?>">
                        <div class="ftt-child-avatar" style="background-color: <?php echo esc_attr($child_color); ?>">
                            <?php echo esc_html(strtoupper(substr($child->first_name, 0, 1))); ?>
                        </div>
                        <div class="ftt-child-info">
                            <h3><?php echo esc_html($child->display_name); ?></h3>
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
                <?php foreach ($parents as $parent_id):
                    if ($parent_id == $current_user->ID) continue; // Skip self
                    
                    $parent = get_userdata($parent_id);
                    if (!$parent) continue;
                    
                    $relationship = get_user_meta($parent_id, 'relationship_to_' . $current_user->ID, true);
                ?>
                    <div class="ftt-adult-card" data-adult-id="<?php echo esc_attr($parent_id); ?>">
                        <div class="ftt-adult-avatar">
                            <?php echo esc_html(strtoupper(substr($parent->first_name, 0, 1))); ?>
                        </div>
                        <div class="ftt-adult-info">
                            <h3><?php echo esc_html($parent->display_name); ?></h3>
                            <p class="ftt-adult-email"><?php echo esc_html($parent->user_email); ?></p>
                            <?php if ($relationship): ?>
                                <p class="ftt-adult-meta"><?php echo esc_html($relationship); ?></p>
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
                        <button type="button" class="ftt-category-expand" data-category="<?php echo esc_attr($cat_key); ?>">▼</button>
                    </label>
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
.ftt-family-management-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

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
}

.ftt-category-icon {
    font-size: 24px;
}

.ftt-category-label {
    flex: 1;
    font-weight: 500;
}

.ftt-category-count {
    font-size: 12px;
    color: #999;
}

.ftt-category-expand {
    background: none;
    border: none;
    color: #666;
    cursor: pointer;
    padding: 5px;
    font-size: 12px;
    transition: transform 0.2s;
    margin-left: auto;
}

.ftt-category-expand.expanded {
    transform: rotate(180deg);
}

.ftt-category-checkbox-wrapper {
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
}

.ftt-category-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px;
    border: none;
    margin: 0;
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
</style>
