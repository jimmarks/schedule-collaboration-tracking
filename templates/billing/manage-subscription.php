<?php
/**
 * Template: Billing - Manage Subscription
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Require login
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(home_url('/billing/manage/')));
    exit;
}

$user_id = get_current_user_id();

// Check if user has subscription
if (!class_exists('FTT_Billing_Manager')) {
    wp_die('Billing system not configured.');
}

$billing = FTT_Billing_Manager::get_billing_summary($user_id);

if (!$billing || empty($billing['status'])) {
    wp_redirect(home_url('/billing/pricing/'));
    exit;
}
?>

<div class="ftt-billing-manage-container">
    <div class="ftt-billing-header">
        <h1><?php esc_html_e('Manage Subscription', 'schedule-collaboration-tracking'); ?></h1>
        <a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="ftt-back-link">
            ← <?php esc_html_e('Back to Dashboard', 'schedule-collaboration-tracking'); ?>
        </a>
    </div>
    
    <!-- Subscription Status -->
    <div class="ftt-billing-status <?php echo esc_attr('status-' . $billing['status']); ?>">
        <div class="ftt-status-badge">
            <?php echo esc_html($billing['status_label']); ?>
        </div>
        
        <?php if ($billing['in_trial']) : ?>
            <div class="ftt-trial-info">
                <p>
                    <strong><?php esc_html_e('Your Free Trial', 'schedule-collaboration-tracking'); ?></strong><br>
                    <?php printf(esc_html__('First charge in %d days', 'schedule-collaboration-tracking'), $billing['days_until_charge']); ?>
                </p>
            </div>
        <?php endif; ?>
        
        <?php if ($billing['cancel_at_end']) : ?>
            <div class="ftt-cancel-notice">
                <p>
                    <strong><?php esc_html_e('Subscription Canceled', 'schedule-collaboration-tracking'); ?></strong><br>
                    <?php printf(esc_html__('Access ends on %s', 'schedule-collaboration-tracking'), $billing['next_billing_date']); ?>
                </p>
                <button class="ftt-reactivate-button" id="ftt-reactivate-subscription">
                    <?php esc_html_e('Reactivate Subscription', 'schedule-collaboration-tracking'); ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Current Plan -->
    <div class="ftt-billing-plan">
        <h2><?php esc_html_e('Current Plan', 'schedule-collaboration-tracking'); ?></h2>
        
        <div class="ftt-plan-details">
            <div class="ftt-detail-row">
                <span class="ftt-detail-label"><?php esc_html_e('Base Subscription', 'schedule-collaboration-tracking'); ?></span>
                <span class="ftt-detail-value"><?php echo esc_html($billing['base_price'] . '/' . $billing['period']); ?></span>
            </div>
            
            <?php if ($billing['addon_quantity'] > 0) : ?>
                <div class="ftt-detail-row">
                    <span class="ftt-detail-label">
                        <?php printf(esc_html__('Additional Children (%d)', 'schedule-collaboration-tracking'), $billing['addon_quantity']); ?>
                    </span>
                    <span class="ftt-detail-value">
                        <?php echo esc_html($billing['addon_quantity'] . ' × ' . $billing['addon_price'] . '/' . $billing['period']); ?>
                    </span>
                </div>
            <?php endif; ?>
            
            <div class="ftt-detail-row ftt-total">
                <span class="ftt-detail-label"><strong><?php esc_html_e('Total', 'schedule-collaboration-tracking'); ?></strong></span>
                <span class="ftt-detail-value"><strong><?php echo esc_html($billing['total_price'] . '/' . $billing['period']); ?></strong></span>
            </div>
        </div>
        
        <div class="ftt-plan-info">
            <p>
                <strong><?php esc_html_e('Children:', 'schedule-collaboration-tracking'); ?></strong>
                <?php printf(esc_html__('%d of %d used', 'schedule-collaboration-tracking'), $billing['children_count'], $billing['allowed_children']); ?>
            </p>
            <?php if (!$billing['in_trial']) : ?>
                <p>
                    <strong><?php esc_html_e('Next Billing Date:', 'schedule-collaboration-tracking'); ?></strong>
                    <?php echo esc_html($billing['next_billing_date']); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Actions -->
    <div class="ftt-billing-actions">
        <h2><?php esc_html_e('Manage Subscription', 'schedule-collaboration-tracking'); ?></h2>
        
        <?php if ($billing['children_count'] < $billing['allowed_children']) : ?>
            <div class="ftt-action-card">
                <h3><?php esc_html_e('Add a Child', 'schedule-collaboration-tracking'); ?></h3>
                <p><?php printf(esc_html__('You can add %d more children with your current plan.', 'schedule-collaboration-tracking'), $billing['allowed_children'] - $billing['children_count']); ?></p>
                <a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="button button-primary">
                    <?php esc_html_e('Go to Dashboard', 'schedule-collaboration-tracking'); ?>
                </a>
            </div>
        <?php else : ?>
            <div class="ftt-action-card">
                <h3><?php esc_html_e('Need More Children?', 'schedule-collaboration-tracking'); ?></h3>
                <p><?php printf(esc_html__('Add another child for %s/%s', 'schedule-collaboration-tracking'), $billing['addon_price'], $billing['period']); ?></p>
                <button class="button button-primary" id="ftt-add-child-addon">
                    <?php esc_html_e('Add Child Slot', 'schedule-collaboration-tracking'); ?>
                </button>
            </div>
        <?php endif; ?>
        
        <div class="ftt-action-card">
            <h3><?php esc_html_e('Update Payment Method', 'schedule-collaboration-tracking'); ?></h3>
            <p><?php esc_html_e('Manage your payment information, view invoices, and billing history.', 'schedule-collaboration-tracking'); ?></p>
            <button class="button" id="ftt-billing-portal">
                <?php esc_html_e('Manage Payment', 'schedule-collaboration-tracking'); ?>
            </button>
        </div>
        
        <?php if (!$billing['cancel_at_end']) : ?>
            <div class="ftt-action-card ftt-danger">
                <h3><?php esc_html_e('Cancel Subscription', 'schedule-collaboration-tracking'); ?></h3>
                <p><?php esc_html_e('Cancel your subscription. You\'ll keep access until the end of your billing period.', 'schedule-collaboration-tracking'); ?></p>
                <button class="button button-danger" id="ftt-cancel-subscription">
                    <?php esc_html_e('Cancel Subscription', 'schedule-collaboration-tracking'); ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.ftt-billing-manage-container {
    max-width: 800px;
    margin: 40px auto;
    padding: 20px;
}

.ftt-billing-header {
    margin-bottom: 30px;
}

.ftt-billing-header h1 {
    font-size: 32px;
    margin-bottom: 10px;
}

.ftt-back-link {
    color: #2196F3;
    text-decoration: none;
}

.ftt-billing-status {
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 30px;
}

.ftt-billing-status.status-trialing {
    border-color: #2196F3;
    background: #E3F2FD;
}

.ftt-billing-status.status-past_due,
.ftt-billing-status.status-suspended {
    border-color: #f44336;
    background: #FFEBEE;
}

.ftt-status-badge {
    display: inline-block;
    padding: 6px 16px;
    border-radius: 20px;
    font-weight: bold;
    margin-bottom: 15px;
}

.status-active .ftt-status-badge {
    background: #4CAF50;
    color: white;
}

.status-trialing .ftt-status-badge {
    background: #2196F3;
    color: white;
}

.status-past_due .ftt-status-badge,
.status-suspended .ftt-status-badge {
    background: #f44336;
    color: white;
}

.status-canceled .ftt-status-badge {
    background: #9E9E9E;
    color: white;
}

.ftt-trial-info,
.ftt-cancel-notice {
    margin-top: 15px;
}

.ftt-reactivate-button {
    margin-top: 10px;
    padding: 10px 20px;
    background: #4CAF50;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

.ftt-billing-plan,
.ftt-billing-actions {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 30px;
}

.ftt-billing-plan h2,
.ftt-billing-actions h2 {
    font-size: 24px;
    margin-bottom: 20px;
    color: #2c3e50;
}

.ftt-detail-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
}

.ftt-detail-row.ftt-total {
    border-bottom: none;
    border-top: 2px solid #2196F3;
    margin-top: 10px;
    padding-top: 15px;
    font-size: 18px;
}

.ftt-plan-info {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
}

.ftt-plan-info p {
    margin: 8px 0;
}

.ftt-action-card {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 15px;
}

.ftt-action-card h3 {
    font-size: 18px;
    margin-bottom: 10px;
    color: #2c3e50;
}

.ftt-action-card p {
    color: #7f8c8d;
    margin-bottom: 15px;
}

.ftt-action-card.ftt-danger {
    border-color: #f44336;
}

.button-danger {
    background: #f44336;
    color: white;
    border: none;
}

.button-danger:hover {
    background: #d32f2f;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Add child addon
    $('#ftt-add-child-addon').on('click', function() {
        if (!confirm('<?php esc_html_e('Add another child slot? You will be charged a prorated amount today.', 'schedule-collaboration-tracking'); ?>')) {
            return;
        }
        
        const $button = $(this);
        $button.prop('disabled', true).text('<?php esc_html_e('Processing...', 'schedule-collaboration-tracking'); ?>');
        
        $.ajax({
            url: '<?php echo esc_url(rest_url('ftt/v1/add-child-addon')); ?>',
            method: 'POST',
            headers: {
                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
            },
            success: function() {
                location.reload();
            },
            error: function() {
                alert('<?php esc_html_e('Error adding child addon', 'schedule-collaboration-tracking'); ?>');
                $button.prop('disabled', false).text('<?php esc_html_e('Add Child Slot', 'schedule-collaboration-tracking'); ?>');
            }
        });
    });
    
    // Billing portal
    $('#ftt-billing-portal').on('click', function() {
        const $button = $(this);
        $button.prop('disabled', true).text('<?php esc_html_e('Loading...', 'schedule-collaboration-tracking'); ?>');
        
        // Create portal session (will need backend endpoint)
window.location.href = '<?php echo esc_url(rest_url('ftt/v1/billing-portal')); ?>?_wpnonce=<?php echo wp_create_nonce('wp_rest'); ?>';
    });
    
    // Cancel subscription
    $('#ftt-cancel-subscription').on('click', function() {
        if (!confirm('<?php esc_html_e('Are you sure you want to cancel? You\'ll keep access until the end of your billing period.', 'schedule-collaboration-tracking'); ?>')) {
            return;
        }
        
        const $button = $(this);
        $button.prop('disabled', true).text('<?php esc_html_e('Canceling...', 'schedule-collaboration-tracking'); ?>');
        
        $.ajax({
            url: '<?php echo esc_url(rest_url('ftt/v1/cancel-subscription')); ?>',
            method: 'POST',
            headers: {
                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
            },
            success: function() {
                location.reload();
            },
            error: function() {
                alert('<?php esc_html_e('Error canceling subscription', 'schedule-collaboration-tracking'); ?>');
                $button.prop('disabled', false).text('<?php esc_html_e('Cancel Subscription', 'schedule-collaboration-tracking'); ?>');
            }
        });
    });
    
    // Reactivate subscription
    $('#ftt-reactivate-subscription').on('click', function() {
        const $button = $(this);
        $button.prop('disabled', true).text('<?php esc_html_e('Reactivating...', 'schedule-collaboration-tracking'); ?>');
        
        $.ajax({
            url: '<?php echo esc_url(rest_url('ftt/v1/reactivate-subscription')); ?>',
            method: 'POST',
            headers: {
                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
            },
            success: function() {
                location.reload();
            },
            error: function() {
                alert('<?php esc_html_e('Error reactivating subscription', 'schedule-collaboration-tracking'); ?>');
                $button.prop('disabled', false).text('<?php esc_html_e('Reactivate Subscription', 'schedule-collaboration-tracking'); ?>');
            }
        });
    });
});
</script>
