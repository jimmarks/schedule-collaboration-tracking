<?php
/**
 * Template: Family Groups Management
 *
 * Displays user's groups and allows creating new groups.
 *
 * @package FamilyTravelTracker
 * @since 2.1.0
 */

if (!is_user_logged_in()) {
    $login_url = class_exists('FTT_Pages') ? (FTT_Pages::get_page_url('login') ?: home_url('/ftt-login/')) : home_url('/ftt-login/');
    echo '<p>Please <a href="' . esc_url($login_url) . '">log in</a> to manage your family groups.</p>';
    return;
}

$current_user_id = get_current_user_id();
$groups = FTT_Family_Groups::get_user_groups($current_user_id);
$primary_group = get_user_meta($current_user_id, 'ftt_primary_group', true);

// Check if this is a new user welcome
$show_welcome = isset($_GET['welcome']) && $_GET['welcome'] == '1';
$primary_group_id = get_user_meta($current_user_id, 'ftt_primary_group_id', true);

// Check for checkout status
$checkout_success = isset($_GET['checkout']) && $_GET['checkout'] === 'success';
$checkout_cancel = isset($_GET['checkout']) && $_GET['checkout'] === 'cancel';
$checkout_error = isset($_GET['checkout_error']) && $_GET['checkout_error'] === '1';
$group_created = isset($_GET['created']) && $_GET['created'] === '1';
$billing_error = isset($_GET['billing_error']) && $_GET['billing_error'] === '1';
?>

<div class="ftt-container">
    <?php
    $ftt_page_title  = __('Family Groups', 'schedule-collaboration-tracking');
    $ftt_active_slug = 'groups';
    include FTT_PLUGIN_DIR . 'templates/partials/nav.php';
    ?>

    <!-- Page heading -->
    <div class="ftt-groups-header">
        <h1><?php esc_html_e('Family Groups', 'schedule-collaboration-tracking'); ?></h1>
        <p><?php esc_html_e('Manage your family groups, members, and billing. Click "View Calendar" to see events and schedules.', 'schedule-collaboration-tracking'); ?></p>
    </div>

    <?php if ($checkout_success): ?>
    <!-- Checkout Success Message -->
    <div class="ftt-success-banner">
        <h2>🎉 Welcome to Family Travel Tracker!</h2>
        <p><strong>Your 14-day free trial has started!</strong> You can now add children to your family group and start tracking your adventures.</p>
        <p>No charges will be made during your trial period. You'll receive an email reminder before billing begins.</p>
    </div>
    <?php elseif ($checkout_cancel): ?>
    <!-- Checkout Cancelled Message -->
    <div class="ftt-warning-banner">
        <h3>⚠️ Setup Incomplete</h3>
        <p>You cancelled the checkout process. To access all features, please complete your billing setup by clicking "Start Trial" below.</p>
    </div>
    <?php elseif ($checkout_error): ?>
    <!-- Checkout Error Message -->
    <div class="ftt-error-banner">
        <h3>⚠️ Setup Error</h3>
        <p>There was a problem creating your billing session. Please contact support or try again later.</p>
    </div>
    <?php elseif ($group_created && $billing_error): ?>
    <!-- Group Created but Billing Failed -->
    <div class="ftt-warning-banner">
        <h3>⚠️ Group Created - Billing Setup Needed</h3>
        <p>Your group was created successfully, but billing setup couldn't be completed. Click "Start Trial" on your group below to complete the setup.</p>
    </div>
    <?php elseif ($group_created): ?>
    <!-- Group Created Successfully -->
    <div class="ftt-success-banner">
        <h2>✅ New Group Created!</h2>
        <p>Your family group has been created. Next steps:</p>
        <ol style="text-align: left; margin: 15px auto; max-width: 500px;">
            <li>Complete billing setup by clicking "Start Trial" below</li>
            <li>Add co-parents and children to your group</li>
            <li>Start planning your family adventures!</li>
        </ol>
    </div>
    <?php elseif ($show_welcome && $primary_group_id): ?>
    <!-- Welcome Message for New Users (no longer used - users go straight to billing) -->
    <div class="ftt-welcome-banner">
        <h2>🎉 Welcome to Family Travel Tracker!</h2>
        <p>We've created your family group automatically. Here's what to do next:</p>
        <ol>
            <li><strong>Add children to your group</strong> - Click "Manage" on your family group below</li>
            <li><strong>Start your 14-day free trial</strong> - No credit card required</li>
            <li><strong>Begin tracking your family's adventures</strong> - Start planning trips and events</li>
        </ol>
        <p><small>Your family group has been created and you're the billing owner. You can add children and other parents anytime.</small></p>
    </div>
    <?php endif; ?>
    
    <!-- Groups List -->
    <div class="ftt-groups-list">
        <?php if (empty($groups)): ?>
            <div class="ftt-no-groups">
                <p>You don't have any family groups yet.</p>
                <p>Create your first group to start organizing your family's activities.</p>
            </div>
        <?php else: ?>
            <?php foreach ($groups as $group): ?>
                <div class="ftt-group-card" data-group-id="<?php echo esc_attr($group->id); ?>">
                    <div class="ftt-group-header">
                        <div class="ftt-group-color" style="background-color: <?php echo esc_attr($group->color); ?>"></div>
                        <div class="ftt-group-info">
                            <h2>
                                <?php echo esc_html($group->name); ?>
                                <?php if ($group->id == $primary_group): ?>
                                    <span class="ftt-badge-primary">Primary</span>
                                <?php endif; ?>
                            </h2>
                            <?php if ($group->description): ?>
                                <p class="ftt-group-description"><?php echo esc_html($group->description); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="ftt-group-stats">
                        <div class="ftt-stat">
                            <span class="ftt-stat-label">Parents</span>
                            <span class="ftt-stat-value"><?php echo esc_html($group->member_count - $group->child_count); ?></span>
                        </div>
                        <div class="ftt-stat">
                            <span class="ftt-stat-label">Children</span>
                            <span class="ftt-stat-value">
                                <?php 
                                if ($group->planned_children > 0) {
                                    echo esc_html($group->child_count . ' / ' . $group->planned_children);
                                } else {
                                    echo esc_html($group->child_count);
                                }
                                ?>
                            </span>
                        </div>
                        <div class="ftt-stat">
                            <span class="ftt-stat-label">Billing</span>
                            <span class="ftt-stat-value status-<?php echo esc_attr($group->subscription_status ?: 'trial'); ?>">
                                <?php 
                                // If billing owner, check user meta for trial status
                                $display_status = $group->subscription_status;
                                $trial_ends = $group->trial_ends_at;
                                
                                // If no subscription_status in group, check billing owner's user meta
                                if (empty($display_status) && !empty($group->billing_owner)) {
                                    $owner_status = get_user_meta($group->billing_owner, 'ftt_subscription_status', true);
                                    $owner_trial_ends = get_user_meta($group->billing_owner, 'ftt_trial_end', true);
                                    if (!empty($owner_status)) {
                                        $display_status = $owner_status;
                                        if (!empty($owner_trial_ends)) {
                                            $trial_ends = date('Y-m-d H:i:s', $owner_trial_ends);
                                        }
                                    }
                                }
                                
                                if ($display_status) {
                                    echo ucfirst($display_status);
                                    
                                    // Show days remaining for trialing or canceled status
                                    if (($display_status === 'trialing' || $display_status === 'canceled') && !empty($trial_ends)) {
                                        $end_date = strtotime($trial_ends);
                                        $now = time();
                                        $days_remaining = ceil(($end_date - $now) / (60 * 60 * 24));
                                        
                                        if ($days_remaining > 0) {
                                            echo '<br><small style="font-size: 11px; font-weight: normal;">' . esc_html($days_remaining) . ' days remaining</small>';
                                        } elseif ($days_remaining === 0) {
                                            echo '<br><small style="font-size: 11px; font-weight: normal;">Ends today</small>';
                                        }
                                    }
                                } else {
                                    echo 'Trial';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="ftt-group-actions">
                        <a href="<?php echo esc_url(home_url('/ftt-calendar/?group=' . FTT_Family_Groups::get_group_token($group->id))); ?>" class="button button-primary ftt-calendar-btn">
                            <span class="dashicons dashicons-calendar-alt" style="vertical-align: middle; margin-top: 3px;"></span>
                            View Calendar
                        </a>
                        <button class="button ftt-view-group-btn" data-group-id="<?php echo esc_attr($group->id); ?>">
                            View Details
                        </button>
                        <?php if (FTT_Family_Groups::can_manage_group($group->id, $current_user_id)): ?>
                            <a href="<?php echo esc_url(home_url('/manage-family/?group=' . FTT_Family_Groups::get_group_token($group->id))); ?>" class="button ftt-manage-group-btn">
                                Manage
                            </a>
                        <?php endif; ?>
                        <?php if ($group->billing_owner == $current_user_id): ?>
                            <button class="button ftt-billing-btn" data-group-id="<?php echo esc_attr($group->id); ?>">
                                Billing
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Create New Group Section (Bottom of Page) -->
    <div class="ftt-create-group-section">
        <div class="ftt-divider"></div>
        <div class="ftt-create-group-cta">
            <h3>Need Another Family Group?</h3>
            <p>Create separate groups for different households, co-parenting arrangements, or extended family.</p>
            <button class="button button-primary" id="ftt-create-group-btn">
                + Create New Group
            </button>
        </div>
    </div>

</div>

<!-- Create Group Explainer Modal -->
<div id="ftt-create-group-explainer-modal" class="ftt-modal" style="display: none;">
    <div class="ftt-modal-content">
        <span class="ftt-modal-close">&times;</span>
        <h2>Create a New Family Group</h2>
        
        <div class="ftt-explainer-content">
            <p class="ftt-explainer-intro">Creating a new group is perfect for managing separate households or co-parenting arrangements.</p>
            
            <div class="ftt-explainer-section">
                <h3>📋 What Happens Next?</h3>
                <ol class="ftt-explainer-steps">
                    <li><strong>Name Your Group</strong> - Choose a family name or description</li>
                    <li><strong>You're Automatically Added</strong> - You'll become the group admin and billing owner</li>
                    <li><strong>Set Up Billing</strong> - Start a 14-day free trial (required to add children)</li>
                    <li><strong>Invite Members</strong> - Add co-parents and children to complete your group</li>
                </ol>
            </div>
            
            <div class="ftt-explainer-section">
                <h3>💡 Good to Know</h3>
                <ul class="ftt-explainer-list">
                    <li>Each group needs its own billing subscription</li>
                    <li>You can belong to multiple groups (e.g., Mom's house and Dad's house)</li>
                    <li>Only the billing owner can manage the subscription</li>
                    <li>Group admins can invite members and manage events</li>
                </ul>
            </div>
            
            <div class="ftt-explainer-actions">
                <button type="button" class="button" id="ftt-cancel-explainer">Cancel</button>
                <button type="button" class="button button-primary" id="ftt-continue-create-group">Continue to Create Group</button>
            </div>
        </div>
    </div>
</div>

<!-- Create Group Form Modal -->
<div id="ftt-create-group-modal" class="ftt-modal" style="display: none;">
    <div class="ftt-modal-content">
        <span class="ftt-modal-close">&times;</span>
        <h2>Create New Family Group</h2>
        
        <form id="ftt-create-group-form">
            <div class="ftt-form-group">
                <label for="group-name">Group Name *</label>
                <input type="text" id="group-name" name="name" required 
                       placeholder="e.g., Smith Family, Dad's Group">
                <small>This name will appear on calendars and events.</small>
            </div>
            
            <div class="ftt-form-group">
                <label for="group-description">Description (optional)</label>
                <textarea id="group-description" name="description" rows="3"
                          placeholder="Brief description of this family group"></textarea>
            </div>
            
            <div class="ftt-form-group">
                <label for="group-color">Group Color</label>
                <input type="color" id="group-color" name="color" value="#2196F3">
                <small>This color will be used in calendar views.</small>
            </div>
            
            <div class="ftt-form-actions">
                <button type="button" class="button" id="ftt-cancel-create-group">Cancel</button>
                <button type="submit" class="button button-primary">Create Group</button>
            </div>
            
            <div id="ftt-create-group-message" class="ftt-message" style="display: none;"></div>
        </form>
    </div>
</div>

<!-- Group Details Modal -->
<div id="ftt-group-details-modal" class="ftt-modal" style="display: none;">
    <div class="ftt-modal-content ftt-modal-large">
        <span class="ftt-modal-close">&times;</span>
        <div id="ftt-group-details-content">
            <!-- Populated by JavaScript -->
        </div>
    </div>
</div>

<!-- Manage Group Modal -->
<div id="ftt-manage-group-modal" class="ftt-modal" style="display: none;">
    <div class="ftt-modal-content ftt-modal-large">
        <span class="ftt-modal-close">&times;</span>
        <h2>Manage Family Group</h2>
        
        <div id="ftt-manage-group-content">
            
            <!-- Group Settings Section -->
            <div class="ftt-manage-section">
                <h3>Group Settings</h3>
                <form id="ftt-edit-group-form">
                    <input type="hidden" id="manage-group-id">
                    
                    <div class="ftt-form-group">
                        <label for="manage-group-name">Group Name *</label>
                        <input type="text" id="manage-group-name" name="name" required>
                    </div>
                    
                    <div class="ftt-form-group">
                        <label for="manage-group-description">Description</label>
                        <textarea id="manage-group-description" name="description" rows="2"></textarea>
                    </div>
                    
                    <div class="ftt-form-group">
                        <label for="manage-group-color">Group Color</label>
                        <input type="color" id="manage-group-color" name="color">
                    </div>
                    
                    <div class="ftt-form-group">
                        <label>
                            <input type="checkbox" id="manage-primary-group" name="primary">
                            Set as my primary group
                        </label>
                    </div>
                    
                    <button type="submit" class="button button-primary">Save Settings</button>
                </form>
            </div>
            
            <!-- Members Section -->
            <div class="ftt-manage-section">
                <div class="ftt-section-header">
                    <h3>Members</h3>
                    <button type="button" class="button" id="ftt-add-member-btn">+ Add Member</button>
                </div>
                
                <div id="ftt-add-member-form" style="display: none; margin-bottom: 20px; padding: 15px; background: #f5f5f5; border-radius: 4px;">
                    <h4>Add New Member</h4>
                    
                    <!-- Tab Navigation -->
                    <div class="ftt-tab-nav">
                        <button type="button" class="ftt-tab-btn active" data-tab="child">Add Child</button>
                        <button type="button" class="ftt-tab-btn" data-tab="parent">Invite Parent/Guardian</button>
                    </div>
                    
                    <!-- Add Child Tab -->
                    <div class="ftt-tab-content active" id="ftt-tab-child">
                        <div class="ftt-form-row">
                            <div class="ftt-form-group">
                                <label for="child-first-name">First Name *</label>
                                <input type="text" id="child-first-name" required>
                            </div>
                            <div class="ftt-form-group">
                                <label for="child-last-name">Last Name *</label>
                                <input type="text" id="child-last-name" required>
                            </div>
                        </div>
                        <div class="ftt-form-row">
                            <div class="ftt-form-group">
                                <label for="child-age">Age</label>
                                <input type="number" id="child-age" min="0" max="18">
                            </div>
                            <div class="ftt-form-group">
                                <label for="child-grade">Grade</label>
                                <input type="text" id="child-grade" placeholder="e.g., 5th Grade">
                            </div>
                        </div>
                        <div class="ftt-form-group">
                            <label for="child-school">School</label>
                            <input type="text" id="child-school">
                        </div>
                        <div class="ftt-form-group">
                            <label for="child-email">Email (optional)</label>
                            <input type="email" id="child-email" placeholder="optional@example.com">
                            <small>Only needed if child has their own account</small>
                        </div>
                        <div class="ftt-form-group">
                            <label for="child-color">Calendar Color</label>
                            <input type="color" id="child-color" value="#2196F3">
                        </div>
                    </div>
                    
                    <!-- Invite Parent Tab -->
                    <div class="ftt-tab-content" id="ftt-tab-parent">
                        <div class="ftt-form-group">
                            <label for="parent-email">Email Address *</label>
                            <input type="email" id="parent-email" required placeholder="parent@example.com">
                            <small>An invitation will be sent to this email</small>
                        </div>
                        <div class="ftt-form-group">
                            <label for="parent-relationship">Relationship *</label>
                            <select id="parent-relationship" required>
                                <option value="">Select relationship...</option>
                                <option value="mother">Mother</option>
                                <option value="father">Father</option>
                                <option value="co-parent" selected>Co-Parent</option>
                                <option value="step-parent">Step-Parent</option>
                                <option value="guardian">Guardian</option>
                                <option value="grandparent">Grandparent</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="ftt-form-group">
                            <label style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" id="parent-can-manage" checked>
                                <span>Allow this parent to manage the group</span>
                            </label>
                            <small>Admins can invite others, modify settings, and manage children</small>
                        </div>
                    </div>
                    
                    <div class="ftt-form-actions">
                        <button type="button" class="button" id="ftt-cancel-add-member">Cancel</button>
                        <button type="button" class="button button-primary" id="ftt-submit-add-member">Add Member</button>
                    </div>
                    <div id="ftt-add-member-message" class="ftt-message" style="display: none; margin-top: 10px;"></div>
                </div>
                
                <div id="ftt-members-list">
                    <!-- Populated by JavaScript -->
                </div>
            </div>
            
            <!-- Billing Section -->
            <div class="ftt-manage-section">
                <h3>Billing & Subscription</h3>
                <div id="ftt-billing-info">
                    <!-- Populated by JavaScript -->
                </div>
            </div>
            
            <div id="ftt-manage-group-message" class="ftt-message" style="display: none;"></div>
        </div>
    </div>
</div>

<style>
.ftt-welcome-banner {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.ftt-welcome-banner h2 {
    margin: 0 0 15px 0;
    font-size: 26px;
    color: white;
}

.ftt-welcome-banner p {
    margin: 10px 0;
    font-size: 16px;
    line-height: 1.6;
}

.ftt-welcome-banner ol {
    margin: 15px 0;
    padding-left: 25px;
}

.ftt-welcome-banner li {
    margin: 8px 0;
    font-size: 16px;
    line-height: 1.6;
}

.ftt-welcome-banner strong {
    color: #fff;
    font-weight: 600;
}

.ftt-welcome-banner small {
    opacity: 0.9;
    font-size: 14px;
}

/* Success Banner (checkout complete) */
.ftt-success-banner {
    background: linear-gradient(135deg, #34C759 0%, #2AAD4C 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.ftt-success-banner h2 {
    margin: 0 0 15px 0;
    font-size: 26px;
    color: white;
}

.ftt-success-banner p {
    margin: 10px 0;
    font-size: 16px;
    line-height: 1.6;
}

/* Warning Banner (checkout cancelled) */
.ftt-warning-banner {
    background: linear-gradient(135deg, #FF9500 0%, #FF8000 100%);
    color: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.ftt-warning-banner h3 {
    margin: 0 0 10px 0;
    font-size: 20px;
    color: white;
}

.ftt-warning-banner p {
    margin: 10px 0 0 0;
    font-size: 15px;
    line-height: 1.6;
}

/* Error Banner (checkout error) */
.ftt-error-banner {
    background: linear-gradient(135deg, #FF3B30 0%, #E6322B 100%);
    color: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.ftt-error-banner h3 {
    margin: 0 0 10px 0;
    font-size: 20px;
    color: white;
}

.ftt-error-banner p {
    margin: 10px 0 0 0;
    font-size: 15px;
    line-height: 1.6;
}

.ftt-groups-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    margin-bottom: 30px;
    gap: 15px;
    width: 100%;
    max-width: 100%;
}

.ftt-groups-nav {
    width: 100%;
    margin-bottom: 15px;
}

.ftt-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 16px;
    background: #ffffff;
    color: #2271b1;
    border: 2px solid #2271b1;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.ftt-back-btn:hover {
    background: #2271b1;
    color: #ffffff;
    text-decoration: none;
}

.ftt-back-btn .dashicons {
    margin-top: 2px;
}

.ftt-groups-nav .button {
    text-decoration: none;
}

.ftt-groups-header h1 {
    margin: 0;
    font-size: 28px;
    width: 100%;
}

.ftt-groups-header p {
    margin: 5px 0 0 0;
    color: #666;
    width: 100%;
}

.ftt-groups-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.ftt-group-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: box-shadow 0.2s;
}

.ftt-group-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.ftt-group-header {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.ftt-group-color {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    flex-shrink: 0;
}

.ftt-group-info h2 {
    margin: 0 0 5px 0;
    font-size: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.ftt-badge-primary {
    background: #2196F3;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: normal;
}

.ftt-group-description {
    margin: 5px 0 0 0;
    color: #666;
    font-size: 14px;
}

.ftt-group-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
    padding: 15px;
    background: #f5f5f5;
    border-radius: 6px;
}

.ftt-stat {
    text-align: center;
}

.ftt-stat-label {
    display: block;
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
    margin-bottom: 5px;
}

.ftt-stat-value {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: #333;
}

.ftt-stat-value.status-active {
    color: #4CAF50;
}

.ftt-stat-value.status-trialing {
    color: #2196F3;
}

.ftt-stat-value.status-past_due {
    color: #FF9800;
}

.ftt-stat-value.status-canceled {
    color: #F44336;
}

.ftt-group-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: center;
}

.ftt-group-actions .button {
    white-space: nowrap;
    font-size: 14px;
    padding: 8px 16px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.ftt-group-actions .ftt-calendar-btn {
    background: #2196F3;
    color: white;
    border-color: #2196F3;
}

.ftt-group-actions .ftt-calendar-btn:hover {
    background: #1976D2;
    border-color: #1976D2;
}

.ftt-no-groups {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.ftt-no-groups p {
    margin: 10px 0;
    font-size: 16px;
}

/* Modal Styles */
.ftt-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.ftt-modal.active {
    display: flex !important;
    align-items: center;
    justify-content: center;
}

.ftt-modal-content {
    background-color: white;
    padding: 30px;
    border-radius: 8px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
}

.ftt-modal-content.ftt-modal-large {
    max-width: 800px;
}

.ftt-modal-close {
    position: absolute;
    right: 15px;
    top: 15px;
    font-size: 28px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
}

.ftt-modal-close:hover {
    color: #000;
}

.ftt-form-group {
    margin-bottom: 20px;
}

.ftt-form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.ftt-form-group input[type="text"],
.ftt-form-group input[type="email"],
.ftt-form-group input[type="color"],
.ftt-form-group textarea,
.ftt-form-group select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.ftt-form-group input[type="color"] {
    width: 100px;
    height: 40px;
    cursor: pointer;
}

.ftt-form-group small {
    display: block;
    margin-top: 5px;
    color: #666;
    font-size: 12px;
}

.ftt-form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 25px;
}

.ftt-message {
    margin-top: 15px;
    padding: 10px;
    border-radius: 4px;
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

/* Manage Group Modal Styles */
.ftt-manage-section {
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 1px solid #ddd;
}

.ftt-manage-section:last-of-type {
    border-bottom: none;
}

.ftt-manage-section h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #333;
}

.ftt-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.ftt-section-header h3 {
    margin: 0;
}

.ftt-form-row {
    display: flex;
    gap: 15px;
}

.ftt-form-row .ftt-form-group {
    flex: 1;
}

.ftt-member-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    margin-bottom: 10px;
    background: #f9f9f9;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
}

.ftt-member-info {
    flex: 1;
}

.ftt-member-info strong {
    display: block;
    font-size: 14px;
    color: #333;
}

.ftt-member-info small {
    display: block;
    color: #666;
    font-size: 12px;
    margin-top: 3px;
}

.ftt-member-badges {
    display: flex;
    gap: 5px;
    margin-left: 10px;
}

.ftt-badge-admin,
.ftt-badge-primary,
.ftt-badge-owner {
    padding: 2px 8px;
    font-size: 11px;
    border-radius: 3px;
    font-weight: 600;
    text-transform: uppercase;
}

.ftt-badge-admin {
    background: #2196F3;
    color: white;
}

.ftt-badge-primary {
    background: #4CAF50;
    color: white;
}

.ftt-badge-owner {
    background: #FF9800;
    color: white;
}

.ftt-billing-card {
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
}

.ftt-billing-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #e0e0e0;
}

.ftt-billing-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.ftt-billing-label {
    font-weight: 600;
    color: #555;
}

.ftt-billing-value {
    color: #333;
}

.ftt-billing-actions {
    margin-top: 15px;
    display: flex;
    gap: 10px;
}

/* Tab Styles for Add Member Form */
.ftt-tab-nav {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    border-bottom: 2px solid #ddd;
}

.ftt-tab-btn {
    padding: 10px 20px;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: #666;
    transition: all 0.2s;
    margin-bottom: -2px;
}

.ftt-tab-btn:hover {
    color: #333;
}

.ftt-tab-btn.active {
    color: #2196F3;
    border-bottom-color: #2196F3;
}

.ftt-tab-content {
    display: none;
    animation: fadeIn 0.3s;
}

.ftt-tab-content.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Create Group Section at Bottom */
.ftt-create-group-section {
    margin-top: 40px;
}

.ftt-divider {
    height: 1px;
    background: linear-gradient(to right, transparent, #ddd 20%, #ddd 80%, transparent);
    margin: 40px 0 30px 0;
}

.ftt-create-group-cta {
    text-align: center;
    padding: 30px;
    background: #f9f9f9;
    border-radius: 8px;
    border: 2px dashed #ddd;
}

.ftt-create-group-cta h3 {
    margin: 0 0 10px 0;
    font-size: 20px;
    color: #333;
}

.ftt-create-group-cta p {
    margin: 0 0 20px 0;
    color: #666;
    font-size: 15px;
}

/* Explainer Modal Styles */
.ftt-explainer-content {
    padding: 10px 0;
}

.ftt-explainer-intro {
    font-size: 16px;
    line-height: 1.6;
    margin-bottom: 25px;
    color: #333;
}

.ftt-explainer-section {
    margin-bottom: 25px;
}

.ftt-explainer-section h3 {
    font-size: 18px;
    margin: 0 0 15px 0;
    color: #2271b1;
}

.ftt-explainer-steps {
    list-style: none;
    counter-reset: step-counter;
    padding-left: 0;
    margin: 0;
}

.ftt-explainer-steps li {
    counter-increment: step-counter;
    padding-left: 45px;
    margin-bottom: 15px;
    position: relative;
    line-height: 1.5;
}

.ftt-explainer-steps li:before {
    content: counter(step-counter);
    position: absolute;
    left: 0;
    top: 0;
    width: 30px;
    height: 30px;
    background: #2271b1;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
}

.ftt-explainer-list {
    list-style: none;
    padding-left: 0;
    margin: 0;
}

.ftt-explainer-list li {
    padding-left: 30px;
    margin-bottom: 12px;
    position: relative;
    line-height: 1.5;
}

.ftt-explainer-list li:before {
    content: "✓";
    position: absolute;
    left: 0;
    top: 0;
    color: #2271b1;
    font-weight: bold;
    font-size: 18px;
}

.ftt-explainer-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

/* Group Details Modal Styles */
.ftt-group-detail-section {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e0e0e0;
}

.ftt-group-detail-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.ftt-group-detail-section h3 {
    margin: 0 0 15px 0;
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.ftt-members-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.ftt-members-list li {
    padding: 8px 0;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.ftt-relationship {
    color: #666;
    font-size: 13px;
    font-style: italic;
}

.ftt-badge-admin {
    background: #2271b1;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.ftt-calendar-subscribe-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border: 2px dashed #dee2e6;
}

.ftt-subscribe-description {
    color: #666;
    font-size: 14px;
    margin: 10px 0 15px 0;
    line-height: 1.5;
}

.ftt-subscribe-actions {
    display: flex;
    gap: 10px;
}

.ftt-subscribe-actions .button {
    text-decoration: none;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .ftt-success-banner,
    .ftt-warning-banner,
    .ftt-error-banner,
    .ftt-welcome-banner {
        padding: 15px;
        margin: 10px 0;
    }
    
    .ftt-success-banner h2,
    .ftt-warning-banner h3,
    .ftt-error-banner h3,
    .ftt-welcome-banner h2 {
        font-size: 20px;
    }
    
    .ftt-success-banner ol,
    .ftt-welcome-banner ol {
        padding-left: 20px;
        max-width: 100% !important;
    }
    
    .ftt-groups-header {
        flex-direction: column;
        align-items: center;
        padding: 15px 10px;
    }
    
    .ftt-groups-header h1 {
        font-size: 24px;
        text-align: center;
    }
    
    .ftt-groups-header p {
        text-align: center;
        font-size: 14px;
    }
    
    .ftt-groups-list {
        grid-template-columns: 1fr;
    }
    
    .ftt-group-card {
        padding: 15px;
    }
    
    .ftt-group-header h2 {
        font-size: 18px;
    }
    
    .ftt-back-btn {
        width: 100%;
        justify-content: center;
        padding: 12px 16px;
    }
    
    .ftt-group-stats {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    
    .ftt-group-actions {
        flex-direction: column;
    }
    
    .ftt-group-actions .button {
        width: 100%;
        white-space: nowrap;
        font-size: 14px;
        padding: 10px;
    }
    
    .ftt-form-row {
        flex-direction: column;
    }
    
    .ftt-member-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .ftt-member-badges {
        margin-left: 0;
    }
    
    .ftt-modal-content {
        width: 95% !important;
        margin: 5% auto !important;
        padding: 15px !important;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .ftt-modal-header h2 {
        font-size: 18px;
    }
    
    .ftt-form-field input,
    .ftt-form-field select,
    .ftt-form-field textarea {
        font-size: 16px !important; /* Prevent iOS zoom */
    }
    
    #ftt-create-group-btn {
        width: 100%;
        padding: 12px;
    }
}

@media (max-width: 480px) {
    .ftt-groups-header h1 {
        font-size: 20px;
    }
    
    .ftt-group-card {
        padding: 12px;
    }
    
    .ftt-group-actions .button {
        font-size: 13px;
        padding: 8px;
    }
}
</style>

<script>
const fttUserPrimaryGroup = <?php echo json_encode(intval($primary_group)); ?>;

jQuery(document).ready(function($) {
    
    // Create Group - Show Explainer Modal First
    $('#ftt-create-group-btn').on('click', function() {
        $('#ftt-create-group-explainer-modal').addClass('active');
    });
    
    // Cancel Explainer
    $('#ftt-cancel-explainer, #ftt-create-group-explainer-modal .ftt-modal-close').on('click', function() {
        $('#ftt-create-group-explainer-modal').removeClass('active');
    });
    
    // Continue from Explainer to Form
    $('#ftt-continue-create-group').on('click', function() {
        $('#ftt-create-group-explainer-modal').removeClass('active');
        setTimeout(function() {
            $('#ftt-create-group-modal').addClass('active');
        }, 300);
    });
    
    // Cancel Create Group Form
    $('#ftt-cancel-create-group, #ftt-create-group-modal .ftt-modal-close').on('click', function() {
        $('#ftt-create-group-modal').removeClass('active');
        $('#ftt-create-group-form')[0].reset();
        $('#ftt-create-group-message').hide();
    });
    
    // Create Group Form Submit
    $('#ftt-create-group-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            name: $('#group-name').val(),
            description: $('#group-description').val(),
            color: $('#group-color').val()
        };
        
        // Disable submit button
        const $submitBtn = $('#ftt-create-group-form button[type="submit"]');
        $submitBtn.prop('disabled', true).text('Creating...');
        
        $.ajax({
            url: fttData.restUrl + 'groups',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            headers: {
                'X-WP-Nonce': fttData.nonce
            },
            success: function(response) {
                if (response.success && response.group_id) {
                    if (response.needs_billing) {
                        $('#ftt-create-group-message')
                            .removeClass('error')
                            .addClass('success')
                            .text('Group created! Redirecting to billing setup...')
                            .show();
                        // Redirect to billing setup flow
                        setTimeout(function() {
                            createCheckoutForGroup(response.group_id);
                        }, 1000);
                    } else {
                        // Admin / billing-exempt: no checkout needed, reload page
                        $('#ftt-create-group-message')
                            .removeClass('error')
                            .addClass('success')
                            .text('Group created successfully!')
                            .show();
                        setTimeout(function() {
                            location.reload();
                        }, 800);
                    }
                } else {
                    throw new Error(response.message || 'Failed to create group');
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || 'Failed to create group';
                $('#ftt-create-group-message')
                    .removeClass('success')
                    .addClass('error')
                    .text(message)
                    .show();
                $submitBtn.prop('disabled', false).text('Create Group');
            }
        });
    });
    
    // Create Stripe Checkout Session for New Group
    function createCheckoutForGroup(groupId) {
        $.ajax({
            url: fttData.restUrl + 'groups/' + groupId + '/checkout',
            method: 'POST',
            data: JSON.stringify({
                interval: 'month'  // Default to monthly
            }),
            contentType: 'application/json',
            headers: {
                'X-WP-Nonce': fttData.nonce
            },
            success: function(response) {
                if (response.success && response.url) {
                    // Redirect to Stripe checkout
                    window.location.href = response.url;
                } else {
                    // Fallback: redirect to groups page
                    window.location.href = '/ftt-groups/?created=1';
                }
            },
            error: function(xhr) {
                console.error('Failed to create checkout session:', xhr);
                // Fallback: redirect to groups page with error
                window.location.href = '/ftt-groups/?created=1&billing_error=1';
            }
        });
    }
    
    // View Group Details
    $('.ftt-view-group-btn').on('click', function() {
        const groupId = $(this).data('group-id');
        loadGroupDetails(groupId);
    });
    
    // Manage Group - now handled by direct links to /manage-family/?group=X
    // Legacy modal code removed in favor of dedicated page navigation
    
    // Billing
    $('.ftt-billing-btn').on('click', function() {
        window.location.href = '/manage-subscription/';
    });
    
    // Load group details
    function loadGroupDetails(groupId) {
        console.log('Loading group details for ID:', groupId);
        $.ajax({
            url: fttData.restUrl + 'groups/' + groupId,
            method: 'GET',
            headers: {
                'X-WP-Nonce': fttData.nonce
            },
            success: function(response) {
                console.log('Group details response:', response);
                if (response.success && response.group) {
                    const group = response.group;
                    renderGroupDetails(group);
                    $('#ftt-group-details-modal').addClass('active');
                    console.log('Modal shown');
                } else {
                    console.error('Invalid response format:', response);
                    alert('Failed to load group details - invalid response');
                }
            },
            error: function(xhr) {
                console.error('Failed to load group details:', xhr);
                alert('Failed to load group details: ' + (xhr.responseJSON?.message || xhr.statusText));
            }
        });
    }
    
    // Render group details
    function renderGroupDetails(group) {
        console.log('Rendering group details:', group);
        
        if (!group.members || !Array.isArray(group.members)) {
            console.error('Group has no members array:', group);
            $('#ftt-group-details-content').html('<p>Error: No member data available</p>');
            return;
        }
        
        const parents = group.members.filter(m => m.role === 'parent');
        const children = group.members.filter(m => m.role === 'child');
        
        let html = `
            <h2>${escapeHtml(group.name)}</h2>
            ${group.description ? `<p>${escapeHtml(group.description)}</p>` : ''}
            
            <div class="ftt-group-detail-section">
                <h3>Parents/Guardians (${parents.length})</h3>
                <ul class="ftt-members-list">
                    ${parents.map(m => `
                        <li>
                            <strong>${escapeHtml(m.display_name)}</strong>
                            ${m.relationship ? `<span class="ftt-relationship">${escapeHtml(m.relationship)}</span>` : ''}
                            ${m.can_manage_group ? '<span class="ftt-badge-admin">Admin</span>' : ''}
                        </li>
                    `).join('')}
                </ul>
            </div>
            
            <div class="ftt-group-detail-section">
                <h3>Children (${children.length})</h3>
                <ul class="ftt-members-list">
                    ${children.map(m => `
                        <li><strong>${escapeHtml(m.display_name)}</strong></li>
                    `).join('')}
                </ul>
            </div>
            
            <div class="ftt-group-detail-section ftt-calendar-subscribe-section">
                <h3>Calendar Subscription</h3>
                <p class="ftt-subscribe-description">Subscribe to this group's calendar on your mobile device or desktop calendar app (Apple Calendar, Google Calendar, Outlook, etc.)</p>
                <div class="ftt-subscribe-actions">
                    <a href="/ftt-calendar-subscribe/?group=${group.group_token}" class="button button-primary" target="_blank">
                        <span class="dashicons dashicons-calendar" style="vertical-align: middle; margin-top: 3px;"></span>
                        Subscribe to Calendar
                    </a>
                </div>
            </div>
        `;
        
        $('#ftt-group-details-content').html(html);
    }
    
    // Close group details modal
    $('#ftt-group-details-modal .ftt-modal-close').on('click', function() {
        $('#ftt-group-details-modal').removeClass('active');
    });
    
    // Close modal on outside click
    $('.ftt-modal').on('click', function(e) {
        if (e.target === this) {
            const $modal = $(this);
            $modal.removeClass('active');
            
            // If closing the manage modal, refresh the group card
            if ($modal.attr('id') === 'ftt-manage-group-modal' && currentManageGroupId) {
                refreshGroupCard(currentManageGroupId);
                $('#ftt-add-member-form').hide();
                currentManageGroupId = null;
                currentManageGroupData = null;
            }
        }
    });
    
    // ==================== MANAGE GROUP MODAL ====================
    
    let currentManageGroupId = null;
    let currentManageGroupData = null;
    
    // Helper function to escape HTML in JavaScript
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Refresh a group card with updated data
    function refreshGroupCard(groupId) {
        $.ajax({
            url: fttData.restUrl + 'groups/' + groupId,
            method: 'GET',
            headers: {
                'X-WP-Nonce': fttData.nonce
            },
            success: function(response) {
                if (response.success && response.group) {
                    const group = response.group;
                    const $card = $('.ftt-group-card[data-group-id="' + groupId + '"]');
                    
                    if ($card.length) {
                        // Update card with fresh data
                        renderGroupCard($card, group);
                    }
                }
            },
            error: function(xhr) {
                console.error('Failed to refresh group card:', xhr);
            }
        });
    }
    
    // Render/update a group card element
    function renderGroupCard($card, group) {
        const isPrimary = (group.id == fttUserPrimaryGroup);
        const currentUserId = <?php echo get_current_user_id(); ?>;
        const canManage = group.members?.some(m => m.user_id == currentUserId && m.can_manage_group);
        const isBillingOwner = (group.billing_owner == currentUserId);
        
        // Calculate parent count (total members minus children)
        const parentCount = (group.member_count || 0) - (group.child_count || 0);
        
        // Build child count display
        let childCountDisplay = group.child_count || 0;
        if (group.planned_children > 0) {
            childCountDisplay = childCountDisplay + ' / ' + group.planned_children;
        }
        
        // Build billing status display
        let billingDisplay = group.subscription_status ? group.subscription_status.charAt(0).toUpperCase() + group.subscription_status.slice(1) : 'Trial';
        let billingStatusClass = group.subscription_status || 'trial';
        let billingExtra = '';
        
        // Add days remaining for trialing or canceled status
        if ((group.subscription_status === 'trialing' || group.subscription_status === 'canceled') && group.trial_ends_at) {
            const endDate = new Date(group.trial_ends_at);
            const now = new Date();
            const daysRemaining = Math.ceil((endDate - now) / (1000 * 60 * 60 * 24));
            
            if (daysRemaining > 0) {
                billingExtra = '<br><small style="font-size: 11px; font-weight: normal;">' + daysRemaining + ' days remaining</small>';
            } else if (daysRemaining === 0) {
                billingExtra = '<br><small style="font-size: 11px; font-weight: normal;">Ends today</small>';
            }
        }
        
        // Build card HTML
        const cardHtml = `
            <div class="ftt-group-header">
                <div class="ftt-group-color" style="background-color: ${escapeHtml(group.color || '#2196F3')}"></div>
                <div class="ftt-group-info">
                    <h2>
                        ${escapeHtml(group.name)}
                        ${isPrimary ? '<span class="ftt-badge-primary">Primary</span>' : ''}
                    </h2>
                    ${group.description ? '<p class="ftt-group-description">' + escapeHtml(group.description) + '</p>' : ''}
                </div>
            </div>
            
            <div class="ftt-group-stats">
                <div class="ftt-stat">
                    <span class="ftt-stat-label">Parents</span>
                    <span class="ftt-stat-value">${parentCount}</span>
                </div>
                <div class="ftt-stat">
                    <span class="ftt-stat-label">Children</span>
                    <span class="ftt-stat-value">${childCountDisplay}</span>
                </div>
                <div class="ftt-stat">
                    <span class="ftt-stat-label">Billing</span>
                    <span class="ftt-stat-value status-${billingStatusClass}">${billingDisplay}${billingExtra}</span>
                </div>
            </div>
            
            <div class="ftt-group-actions">
                <a href="/ftt-calendar/?group=${group.group_token}" class="button button-primary ftt-calendar-btn">
                    <span class="dashicons dashicons-calendar-alt" style="vertical-align: middle; margin-top: 3px;"></span>
                    View Calendar
                </a>
                <button class="button ftt-view-group-btn" data-group-id="${group.id}">View Details</button>
                ${canManage ? '<a href="/manage-family/?group=' + group.group_token + '" class="button ftt-manage-group-btn">Manage</a>' : ''}
                ${isBillingOwner ? '<button class="button ftt-billing-btn" data-group-id="' + group.id + '">Billing</button>' : ''}
            </div>
        `;
        
        // Update the card
        $card.html(cardHtml);
        
        // Re-bind event handlers for the updated buttons
        $card.find('.ftt-view-group-btn').on('click', function() {
            const groupId = $(this).data('group-id');
            loadGroupDetails(groupId);
        });
        
        // Manage button now uses direct navigation, no click handler needed
        
        $card.find('.ftt-billing-btn').on('click', function() {
            window.location.href = '/manage-subscription/';
        });
    }
    
    // Load manage group modal
    function loadManageGroup(groupId) {
        console.log('Loading manage interface for group:', groupId);
        currentManageGroupId = groupId;
        
        $.ajax({
            url: fttData.restUrl + 'groups/' + groupId,
            method: 'GET',
            headers: {
                'X-WP-Nonce': fttData.nonce
            },
            success: function(response) {
                if (response.success && response.group) {
                    currentManageGroupData = response.group;
                    populateManageForm(response.group);
                    renderMembersList(response.group.members);
                    renderBillingInfo(response.group);
                    $('#ftt-manage-group-modal').addClass('active');
                } else {
                    alert('Failed to load group data');
                }
            },
            error: function(xhr) {
                console.error('Failed to load group:', xhr);
                alert('Failed to load group: ' + (xhr.responseJSON?.message || xhr.statusText));
            }
        });
    }
    
    // Populate manage form with group data
    function populateManageForm(group) {
        $('#manage-group-id').val(group.id);
        $('#manage-group-name').val(group.name);
        $('#manage-group-description').val(group.description || '');
        $('#manage-group-color').val(group.color || '#2196F3');
        
        // Check if this is the user's primary group
        const isPrimary = (group.id == fttUserPrimaryGroup);
        $('#manage-primary-group').prop('checked', isPrimary);
    }
    
    // Render members list
    function renderMembersList(members) {
        if (!members || !Array.isArray(members)) {
            $('#ftt-members-list').html('<p>No members found</p>');
            return;
        }
        
        const currentUserId = <?php echo get_current_user_id(); ?>;
        const parents = members.filter(m => m.role === 'parent');
        const children = members.filter(m => m.role === 'child');
        
        let html = '<h4>Parents/Guardians</h4>';
        parents.forEach(member => {
            html += renderMemberItem(member, currentUserId);
        });
        
        html += '<h4 style="margin-top: 25px;">Children</h4>';
        children.forEach(member => {
            html += renderMemberItem(member, currentUserId);
        });
        
        $('#ftt-members-list').html(html);
    }
    
    // Render single member item
    function renderMemberItem(member, currentUserId) {
        const isBillingOwner = currentManageGroupData && member.user_id == currentManageGroupData.billing_owner;
        const isSelf = member.user_id == currentUserId;
        
        let badges = '';
        if (isBillingOwner) badges += '<span class="ftt-badge-owner">Billing Owner</span>';
        // Only show Admin badge for parents (not children)
        if (member.can_manage_group && member.role === 'parent') badges += '<span class="ftt-badge-admin">Admin</span>';
        
        let actions = '';
        if (!isBillingOwner && !isSelf) {
            actions = `<button type="button" class="button button-small ftt-remove-member-btn" data-user-id="${member.user_id}">Remove</button>`;
        }
        
        return `
            <div class="ftt-member-item">
                <div class="ftt-member-info">
                    <strong>${escapeHtml(member.display_name)}</strong>
                    <small>${escapeHtml(member.role)}${member.relationship ? ' • ' + escapeHtml(member.relationship) : ''} • ${escapeHtml(member.user_email)}</small>
                </div>
                <div class="ftt-member-badges">
                    ${badges}
                    ${actions}
                </div>
            </div>
        `;
    }
    
    // Render billing information
    function renderBillingInfo(group) {
        const currentUserId = <?php echo get_current_user_id(); ?>;
        const isBillingOwner = (group.billing_owner == currentUserId);
        const status = group.subscription_status || 'none';
        const statusText = status === 'none' ? 'No Active Subscription' : status.charAt(0).toUpperCase() + status.slice(1);
        const billingOwner = group.members.find(m => m.user_id == group.billing_owner);
        
        // Get child counts - actual vs planned
        const actualChildCount = group.members.filter(m => m.role === 'child').length;
        const plannedChildren = group.planned_children || 0;
        const childCount = plannedChildren > 0 ? plannedChildren : actualChildCount;
        const remainingSlots = Math.max(0, plannedChildren - actualChildCount);
        
        // Format: "X used / Y remaining" or just the count if no planned
        let childCountDisplay;
        if (plannedChildren > 0) {
            childCountDisplay = `${actualChildCount} used / ${remainingSlots} remaining (${plannedChildren} total)`;
        } else {
            childCountDisplay = actualChildCount.toString();
        }
        
        // Calculate trial days remaining
        let trialInfo = '';
        if (status === 'trialing' && group.trial_ends_at) {
            const trialEnd = new Date(group.trial_ends_at);
            const now = new Date();
            const daysRemaining = Math.ceil((trialEnd - now) / (1000 * 60 * 60 * 24));
            if (daysRemaining > 0) {
                trialInfo = `
                    <div class="ftt-billing-row">
                        <span class="ftt-billing-label">Trial Ends In</span>
                        <span class="ftt-billing-value">${daysRemaining} days</span>
                    </div>
                `;
            }
        }
        
        // Calculate pricing based on planned/actual child count
        const basePrice = group.subscription_interval === 'year' ? '$99/year' : '$9.99/month';
        const addonPrice = group.subscription_interval === 'year' ? '$50/year' : '$5/month';
        const additionalChildren = Math.max(0, childCount - 1);
        const totalMonthly = group.subscription_interval === 'year' ? 
            (99 + (additionalChildren * 50)) : 
            (9.99 + (additionalChildren * 5));
        const intervalText = group.subscription_interval === 'year' ? '/year' : '/month';
        
        let html = `
            <div class="ftt-billing-card">
                <div class="ftt-billing-row">
                    <span class="ftt-billing-label">Subscription Status</span>
                    <span class="ftt-billing-value status-${status}">${statusText}</span>
                </div>
                ${trialInfo}
                <div class="ftt-billing-row">
                    <span class="ftt-billing-label">Children</span>
                    <span class="ftt-billing-value">${childCountDisplay}</span>
                </div>
                ${status !== 'none' ? `
                    <div class="ftt-billing-row">
                        <span class="ftt-billing-label">Pricing</span>
                        <span class="ftt-billing-value">${basePrice}${additionalChildren > 0 ? ` + ${additionalChildren} × ${addonPrice}` : ''} = $${totalMonthly.toFixed(2)}${intervalText}</span>
                    </div>
                ` : ''}
                <div class="ftt-billing-row">
                    <span class="ftt-billing-label">Billing Owner</span>
                    <span class="ftt-billing-value">${billingOwner ? billingOwner.display_name : 'Unknown'}</span>
                </div>
            </div>
            <div class="ftt-billing-actions">
                ${!isBillingOwner ? 
                    `<p class="ftt-billing-notice"><em>Only the billing owner (${billingOwner ? billingOwner.display_name : 'Unknown'}) can manage the subscription.</em></p>` :
                    (status === 'none' ? 
                        `<button type="button" class="button button-primary" id="start-trial-monthly-btn">Start 14-Day Free Trial (Monthly - $9.99/mo)</button>
                        <button type="button" class="button button-primary" id="start-trial-annual-btn">Start 14-Day Free Trial (Annual - $99/yr)</button>` :
                        `<button type="button" class="button" id="manage-billing-btn">Manage Subscription</button>`)
                }
            </div>
        `;
        
        $('#ftt-billing-info').html(html);
        
        // Wire up button handlers (only if user is billing owner)
        if (isBillingOwner) {
            if (status === 'none') {
                $('#start-trial-monthly-btn').on('click', function() {
                    startCheckout(group.id, 'month');
                });
                $('#start-trial-annual-btn').on('click', function() {
                    startCheckout(group.id, 'year');
                });
            } else {
                $('#manage-billing-btn').on('click', function() {
                    manageBilling(group.id);
                });
            }
        }
    }
    
    // Start checkout session
    function startCheckout(groupId, interval) {
        const btn = event.target;
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Creating checkout session...';
        
        $.ajax({
            url: fttData.restUrl + 'groups/' + groupId + '/checkout',
            method: 'POST',
            data: JSON.stringify({ interval: interval }),
            contentType: 'application/json',
            headers: {
                'X-WP-Nonce': fttData.nonce
            },
            success: function(response) {
                if (response.success && response.url) {
                    // Redirect to Stripe checkout
                    window.location.href = response.url;
                } else {
                    alert('Failed to create checkout session. Please try again.');
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || 'Failed to start checkout';
                alert(message);
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });
    }
    
    // Manage billing portal
    function manageBilling(groupId) {
        const btn = event.target;
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Opening billing portal...';
        
        $.ajax({
            url: fttData.restUrl + 'groups/' + groupId + '/portal',
            method: 'POST',
            contentType: 'application/json',
            headers: {
                'X-WP-Nonce': fttData.nonce
            },
            success: function(response) {
                if (response.success && response.url) {
                    // Redirect to Stripe portal
                    window.location.href = response.url;
                } else {
                    alert('Failed to open billing portal. Please try again.');
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || 'Failed to open billing portal';
                alert(message);
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });
    }
    
    // Close manage modal
    $('#ftt-manage-group-modal .ftt-modal-close').on('click', function() {
        $('#ftt-manage-group-modal').removeClass('active');
        $('#ftt-add-member-form').hide();
        
        // Refresh the group card with updated data
        if (currentManageGroupId) {
            refreshGroupCard(currentManageGroupId);
        }
        
        currentManageGroupId = null;
        currentManageGroupData = null;
    });
    
    // Edit group form submit
    $('#ftt-edit-group-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            name: $('#manage-group-name').val(),
            description: $('#manage-group-description').val(),
            color: $('#manage-group-color').val()
        };
        
        const isPrimaryChecked = $('#manage-primary-group').is(':checked');
        
        $.ajax({
            url: fttData.restUrl + 'groups/' + currentManageGroupId,
            method: 'PUT',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            headers: {
                'X-WP-Nonce': fttData.nonce
            },
            success: function(response) {
                // If primary checkbox is checked and this wasn't the primary group, update it
                if (isPrimaryChecked && currentManageGroupId != fttUserPrimaryGroup) {
                    $.ajax({
                        url: fttData.restUrl + 'user/primary-group',
                        method: 'POST',
                        data: JSON.stringify({ group_id: currentManageGroupId }),
                        contentType: 'application/json',
                        headers: {
                            'X-WP-Nonce': fttData.nonce
                        },
                        success: function() {
                            fttUserPrimaryGroup = currentManageGroupId;
                            showSuccessAndReload();
                        },
                        error: function() {
                            showSuccessAndReload(); // Still reload even if primary update fails
                        }
                    });
                } else {
                    showSuccessAndReload();
                }
                
                function showSuccessAndReload() {
                    $('#ftt-manage-group-message')
                        .removeClass('error')
                        .addClass('success')
                        .text('Group settings updated successfully!')
                        .show();
                    
                    // Update current data
                    if (response.group) {
                        currentManageGroupData = response.group;
                    }
                    
                    // Reload page after delay to show updated info
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                }
            },
            error: function(xhr) {
                $('#ftt-manage-group-message')
                    .removeClass('success')
                    .addClass('error')
                    .text('Failed to update group: ' + (xhr.responseJSON?.message || 'Unknown error'))
                    .show();
            }
        });
    });
    
    // Show/hide add member form
    $('#ftt-add-member-btn').on('click', function() {
        $('#ftt-add-member-form').slideDown();
        $('#ftt-add-member-message').hide();
    });
    
    $('#ftt-cancel-add-member').on('click', function() {
        $('#ftt-add-member-form').slideUp();
        $('#add-member-email').val('');
        $('#add-member-role').val('parent');
        $('#add-member-relationship').val('');
        $('#add-member-can-manage').prop('checked', false);
        $('#ftt-add-member-message').hide();
    });
    
    // Tab switching
    $('.ftt-tab-btn').on('click', function() {
        const tab = $(this).data('tab');
        
        // Update button states
        $('.ftt-tab-btn').removeClass('active');
        $(this).addClass('active');
        
        // Update content visibility
        $('.ftt-tab-content').removeClass('active');
        $('#ftt-tab-' + tab).addClass('active');
        
        // Clear message
        $('#ftt-add-member-message').hide();
    });
    
    // Add member submit - handles both child and parent based on active tab
    $('#ftt-submit-add-member').on('click', function() {
        const activeTab = $('.ftt-tab-btn.active').data('tab');
        
        if (activeTab === 'child') {
            addChildToGroup();
        } else if (activeTab === 'parent') {
            inviteParentToGroup();
        }
    });
    
    // Add child to group
    function addChildToGroup() {
        const firstName = $('#child-first-name').val().trim();
        const lastName = $('#child-last-name').val().trim();
        const age = $('#child-age').val();
        const grade = $('#child-grade').val().trim();
        const school = $('#child-school').val().trim();
        const email = $('#child-email').val().trim();
        const color = $('#child-color').val();
        
        if (!firstName || !lastName) {
            $('#ftt-add-member-message')
                .removeClass('success')
                .addClass('error')
                .text('Please enter first and last name')
                .show();
            return;
        }
        
        // Step 1: Create the child user account
        $.ajax({
            url: fttData.restUrl + 'add-child',
            method: 'POST',
            data: JSON.stringify({
                first_name: firstName,
                last_name: lastName,
                age: age ? parseInt(age) : 0,
                grade: grade,
                school: school,
                email: email,
                color: color
            }),
            contentType: 'application/json',
            headers: {
                'X-WP-Nonce': fttData.nonce
            },
            success: function(response) {
                if (response.success && response.child_id) {
                    // Step 2: Add the child to the group
                    addMemberToGroup(response.child_id, 'child', 'Child', false);
                } else {
                    $('#ftt-add-member-message')
                        .removeClass('success')
                        .addClass('error')
                        .text('Failed to create child account')
                        .show();
                }
            },
            error: function(xhr) {
                $('#ftt-add-member-message')
                    .removeClass('success')
                    .addClass('error')
                    .text('Failed to add child: ' + (xhr.responseJSON?.message || 'Unknown error'))
                    .show();
            }
        });
    }
    
    // Invite parent to group
    function inviteParentToGroup() {
        const email = $('#parent-email').val().trim();
        const relationship = $('#parent-relationship').val();
        const canManage = $('#parent-can-manage').is(':checked');
        
        if (!email) {
            $('#ftt-add-member-message')
                .removeClass('success')
                .addClass('error')
                .text('Please enter an email address')
                .show();
            return;
        }
        
        if (!relationship) {
            $('#ftt-add-member-message')
                .removeClass('success')
                .addClass('error')
                .text('Please select a relationship')
                .show();
            return;
        }
        
        // Send invitation
        $.ajax({
            url: fttData.restUrl + 'invite-adult',
            method: 'POST',
            data: JSON.stringify({
                email: email,
                relationship: relationship,
                can_manage_group: canManage,
                group_id: currentManageGroupId
            }),
            contentType: 'application/json',
            headers: {
                'X-WP-Nonce': fttData.nonce
            },
            success: function(response) {
                $('#ftt-add-member-message')
                    .removeClass('error')
                    .addClass('success')
                    .text('Invitation sent to ' + email + '!')
                    .show();
                
                // Clear form and reload
                setTimeout(function() {
                    $('#parent-email').val('');
                    $('#parent-relationship').val('co-parent');
                    $('#parent-can-manage').prop('checked', true);
                    $('#ftt-add-member-form').slideUp();
                    $('#ftt-add-member-message').hide();
                }, 2000);
            },
            error: function(xhr) {
                $('#ftt-add-member-message')
                    .removeClass('success')
                    .addClass('error')
                    .text('Failed to send invitation: ' + (xhr.responseJSON?.message || 'Unknown error'))
                    .show();
            }
        });
    }
    
    // Add member to group (after user/child creation)
    function addMemberToGroup(userId, role, relationship, canManage) {
        const memberData = {
            user_id: userId,
            role: role,
            relationship: relationship,
            can_manage_group: canManage
        };
        
        $.ajax({
            url: fttData.restUrl + 'groups/' + currentManageGroupId + '/members',
            method: 'POST',
            data: JSON.stringify(memberData),
            contentType: 'application/json',
            headers: {
                'X-WP-Nonce': fttData.nonce
            },
            success: function(response) {
                $('#ftt-add-member-message')
                    .removeClass('error')
                    .addClass('success')
                    .text('Member added successfully!')
                    .show();
                
                // Reload group data
                setTimeout(function() {
                    loadManageGroup(currentManageGroupId);
                    $('#ftt-add-member-form').slideUp();
                    // Clear child form
                    $('#child-first-name, #child-last-name, #child-age, #child-grade, #child-school, #child-email').val('');
                    $('#child-color').val('#2196F3');
                }, 1500);
            },
            error: function(xhr) {
                $('#ftt-add-member-message')
                    .removeClass('success')
                    .addClass('error')
                    .text('Failed to add member to group: ' + (xhr.responseJSON?.message || 'Unknown error'))
                    .show();
            }
        });
    }
    
    // Remove member (delegated event handler)
    $(document).on('click', '.ftt-remove-member-btn', function() {
        if (!confirm('Are you sure you want to remove this member from the group?')) {
            return;
        }
        
        const userId = $(this).data('user-id');
        
        $.ajax({
            url: fttData.restUrl + 'groups/' + currentManageGroupId + '/members/' + userId,
            method: 'DELETE',
            headers: {
                'X-WP-Nonce': fttData.nonce
            },
            success: function(response) {
                $('#ftt-manage-group-message')
                    .removeClass('error')
                    .addClass('success')
                    .text('Member removed successfully! Refreshing...')
                    .show();
                
                // Reload the page to update all counts
                setTimeout(function() {
                    window.location.reload();
                }, 1000);
            },
            error: function(xhr) {
                alert('Failed to remove member: ' + (xhr.responseJSON?.message || 'Unknown error'));
            }
        });
    });
});
</script>
