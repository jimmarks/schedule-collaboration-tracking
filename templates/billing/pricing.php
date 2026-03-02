<?php
/**
 * Template: Billing - Pricing Page
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Debug logging (comment out after testing)
if (isset($_GET['debug'])) {
    $user_id = get_current_user_id();
    echo "<!-- DEBUG: User ID: $user_id -->";
    if ($user_id) {
        $subscription_status = get_user_meta($user_id, 'ftt_subscription_status', true);
        echo "<!-- DEBUG: Subscription Status: " . ($subscription_status ?: 'none') . " -->";
    }
}

// Redirect if already has subscription
$user_id = get_current_user_id();
if ($user_id) {
    $subscription_status = get_user_meta($user_id, 'ftt_subscription_status', true);
    if (!empty($subscription_status) && in_array($subscription_status, ['active', 'trialing'])) {
        wp_redirect(home_url('/manage-subscription/'));
        exit;
    }
}

// Show access denied notice if redirected
$access_denied_notice = '';
if (isset($_GET['reason'])) {
    $reason = sanitize_text_field($_GET['reason']);
    $messages = array(
        'admin_denied' => array(
            'title' => __('Access Denied', 'schedule-collaboration-tracking'),
            'message' => __('Your account access has been restricted by an administrator. Please contact support for assistance.', 'schedule-collaboration-tracking')
        ),
        'no_subscription' => array(
            'title' => __('Subscription Required', 'schedule-collaboration-tracking'),
            'message' => __('You need an active subscription to access Family Travel Tracker. Please select a plan below to get started.', 'schedule-collaboration-tracking')
        ),
        'suspended' => array(
            'title' => __('Subscription Suspended', 'schedule-collaboration-tracking'),
            'message' => __('Your subscription has been suspended. Please contact support or update your payment method to restore access.', 'schedule-collaboration-tracking')
        ),
        'incomplete' => array(
            'title' => __('Payment Incomplete', 'schedule-collaboration-tracking'),
            'message' => __('Your subscription payment could not be completed. Please update your payment method below.', 'schedule-collaboration-tracking')
        ),
        'incomplete_expired' => array(
            'title' => __('Payment Expired', 'schedule-collaboration-tracking'),
            'message' => __('Your payment attempt has expired. Please select a plan below to continue.', 'schedule-collaboration-tracking')
        ),
        'expired' => array(
            'title' => __('Subscription Expired', 'schedule-collaboration-tracking'),
            'message' => __('Your subscription has expired. Please renew your subscription below to continue using Family Travel Tracker.', 'schedule-collaboration-tracking')
        )
    );
    
    if (isset($messages[$reason])) {
        $msg = $messages[$reason];
        $access_denied_notice = '<div class="ftt-notice ftt-notice-error" style="margin: 20px auto; max-width: 800px; padding: 15px 20px; background: #fee; border: 1px solid #c33; border-radius: 4px; color: #c33; text-align: center;">
            <strong>' . esc_html($msg['title']) . ':</strong> ' . esc_html($msg['message']) . '
        </div>';
    }
}

// Backward compatibility with old access_denied parameter
if (isset($_GET['access_denied']) && $_GET['access_denied'] == '1' && empty($access_denied_notice)) {
    $access_denied_notice = '<div class="ftt-notice ftt-notice-error" style="margin: 20px auto; max-width: 800px; padding: 15px 20px; background: #fee; border: 1px solid #c33; border-radius: 4px; color: #c33; text-align: center;">
        <strong>' . esc_html__('Access Denied', 'schedule-collaboration-tracking') . ':</strong> ' . 
        esc_html__('Your account access has been restricted. Please contact support or select a subscription plan below.', 'schedule-collaboration-tracking') . '
    </div>';
}

$settings = get_option('ftt_stripe_settings', []);
$trial_days = $settings['trial_days'] ?? 14;
?>

<?php echo $access_denied_notice; ?>

<div class="ftt-pricing-container">
    <div class="ftt-pricing-header">
        <h1><?php esc_html_e('Simple, Transparent Pricing', 'schedule-collaboration-tracking'); ?></h1>
        <p class="ftt-pricing-subtitle">
            <?php printf(esc_html__('Start your %d-day free trial today. No credit card required.', 'schedule-collaboration-tracking'), $trial_days); ?>
        </p>
    </div>
    
    <div class="ftt-billing-toggle">
        <label class="ftt-toggle-option">
            <input type="radio" name="billing_interval" value="month" checked>
            <span><?php esc_html_e('Monthly', 'schedule-collaboration-tracking'); ?></span>
        </label>
        <label class="ftt-toggle-option">
            <input type="radio" name="billing_interval" value="year">
            <span><?php esc_html_e('Yearly', 'schedule-collaboration-tracking'); ?> <span class="ftt-save-badge">Save 17%</span></span>
        </label>
    </div>
    
    <div class="ftt-pricing-cards">
        <!-- Monthly Pricing -->
        <div class="ftt-pricing-card" data-interval="month">
            <div class="ftt-card-header">
                <h2><?php esc_html_e('Base Subscription', 'schedule-collaboration-tracking'); ?></h2>
                <p class="ftt-card-subtitle"><?php esc_html_e('Perfect for single-child families', 'schedule-collaboration-tracking'); ?></p>
            </div>
            
            <div class="ftt-card-price">
                <span class="ftt-price-amount">$9.99</span>
                <span class="ftt-price-period">/month</span>
            </div>
            
            <div class="ftt-card-features">
                <ul>
                    <li>✓ <?php esc_html_e('1 child included', 'schedule-collaboration-tracking'); ?></li>
                    <li>✓ <?php esc_html_e('Up to 4 parent accounts', 'schedule-collaboration-tracking'); ?></li>
                    <li>✓ <?php esc_html_e('Unlimited events', 'schedule-collaboration-tracking'); ?></li>
                    <li>✓ <?php esc_html_e('Calendar sync (iCal)', 'schedule-collaboration-tracking'); ?></li>
                    <li>✓ <?php esc_html_e('Flight tracking', 'schedule-collaboration-tracking'); ?></li>
                    <li>✓ <?php esc_html_e('Travel planning', 'schedule-collaboration-tracking'); ?></li>
                    <li>✓ <?php printf(esc_html__('%d-day free trial', 'schedule-collaboration-tracking'), $trial_days); ?></li>
                </ul>
            </div>
            
            <?php if ($user_id) : ?>
            <div class="ftt-addon-info">
                <p><strong><?php esc_html_e('Additional children?', 'schedule-collaboration-tracking'); ?></strong></p>
                <p><?php esc_html_e('Add more children for just $5/month each.', 'schedule-collaboration-tracking'); ?></p>
                
                <div class="ftt-addon-selector">
                    <label for="addon-qty-month"><?php esc_html_e('Additional children:', 'schedule-collaboration-tracking'); ?></label>
                    <div class="ftt-quantity-control">
                        <button type="button" class="ftt-qty-btn ftt-qty-minus" data-target="addon-qty-month">-</button>
                        <input type="number" id="addon-qty-month" class="ftt-addon-qty" min="0" max="20" value="0" readonly>
                        <button type="button" class="ftt-qty-btn ftt-qty-plus" data-target="addon-qty-month">+</button>
                    </div>
                    <span class="ftt-addon-total">+$0/month</span>
                </div>
            </div>
            
            <?php endif; ?>
            
            <?php if ($user_id) : ?>
                <button class="ftt-cta-button" data-interval="month">
                    <?php esc_html_e('Start Free Trial', 'schedule-collaboration-tracking'); ?>
                </button>
            <?php else : ?>
                <a href="<?php echo esc_url(home_url('/ftt-register/')); ?>" class="ftt-cta-button">
                    <?php esc_html_e('Sign Up Free', 'schedule-collaboration-tracking'); ?>
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Yearly Pricing -->
        <div class="ftt-pricing-card ftt-popular" data-interval="year" style="display: none;">
            <div class="ftt-popular-badge"><?php esc_html_e('Best Value', 'schedule-collaboration-tracking'); ?></div>
            
            <div class="ftt-card-header">
                <h2><?php esc_html_e('Base Subscription', 'schedule-collaboration-tracking'); ?></h2>
                <p class="ftt-card-subtitle"><?php esc_html_e('Save 17% with annual billing', 'schedule-collaboration-tracking'); ?></p>
            </div>
            
            <div class="ftt-card-price">
                <span class="ftt-price-amount">$99</span>
                <span class="ftt-price-period">/year</span>
                <span class="ftt-price-compare"><?php esc_html_e('$119.88 monthly', 'schedule-collaboration-tracking'); ?></span>
            </div>
            
            <div class="ftt-card-features">
                <ul>
                    <li>✓ <?php esc_html_e('1 child included', 'schedule-collaboration-tracking'); ?></li>
                    <li>✓ <?php esc_html_e('Up to 4 parent accounts', 'schedule-collaboration-tracking'); ?></li>
                    <li>✓ <?php esc_html_e('Unlimited events', 'schedule-collaboration-tracking'); ?></li>
                    <li>✓ <?php esc_html_e('Calendar sync (iCal)', 'schedule-collaboration-tracking'); ?></li>
                    <li>✓ <?php esc_html_e('Flight tracking', 'schedule-collaboration-tracking'); ?></li>
                    <li>✓ <?php esc_html_e('Travel planning', 'schedule-collaboration-tracking'); ?></li>
                    <li>✓ <?php printf(esc_html__('%d-day free trial', 'schedule-collaboration-tracking'), $trial_days); ?></li>
                    <li>✅ <strong><?php esc_html_e('Save $20/year', 'schedule-collaboration-tracking'); ?></strong></li>
                </ul>
            </div>
            
            <?php if ($user_id) : ?>
            <div class="ftt-addon-info">
                <p><strong><?php esc_html_e('Additional children?', 'schedule-collaboration-tracking'); ?></strong></p>
                <p><?php esc_html_e('Add more children for just $50/year each.', 'schedule-collaboration-tracking'); ?></p>
                
                <div class="ftt-addon-selector">
                    <label for="addon-qty-year"><?php esc_html_e('Additional children:', 'schedule-collaboration-tracking'); ?></label>
                    <div class="ftt-quantity-control">
                        <button type="button" class="ftt-qty-btn ftt-qty-minus" data-target="addon-qty-year">-</button>
                        <input type="number" id="addon-qty-year" class="ftt-addon-qty" min="0" max="20" value="0" readonly>
                        <button type="button" class="ftt-qty-btn ftt-qty-plus" data-target="addon-qty-year">+</button>
                    </div>
                    <span class="ftt-addon-total">+$0/year</span>
                </div>
            </div>
            
            <?php endif; ?>
            
            <?php if ($user_id) : ?>
                <button class="ftt-cta-button" data-interval="year">
                    <?php esc_html_e('Start Free Trial', 'schedule-collaboration-tracking'); ?>
                </button>
            <?php else : ?>
                <a href="<?php echo esc_url(home_url('/ftt-register/')); ?>" class="ftt-cta-button">
                    <?php esc_html_e('Sign Up Free', 'schedule-collaboration-tracking'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="ftt-pricing-faq">
        <h3><?php esc_html_e('Frequently Asked Questions', 'schedule-collaboration-tracking'); ?></h3>
        
        <div class="ftt-faq-item">
            <h4><?php esc_html_e('When will I be charged?', 'schedule-collaboration-tracking'); ?></h4>
            <p><?php printf(esc_html__('Your %d-day free trial starts today. Your first charge will be %d days from now. You can cancel anytime before then with no charge.', 'schedule-collaboration-tracking'), $trial_days, $trial_days); ?></p>
        </div>
        
        <div class="ftt-faq-item">
            <h4><?php esc_html_e('Can I add more children later?', 'schedule-collaboration-tracking'); ?></h4>
            <p><?php esc_html_e('Absolutely! You can add additional children anytime for $5/month or $50/year each. There\'s no limit.', 'schedule-collaboration-tracking'); ?></p>
        </div>
        
        <div class="ftt-faq-item">
            <h4><?php esc_html_e('Can multiple parents share the same account?', 'schedule-collaboration-tracking'); ?></h4>
            <p><?php esc_html_e('Yes! Each child can have up to 4 parent accounts linked to their schedule. Perfect for divorced/separated parents.', 'schedule-collaboration-tracking'); ?></p>
        </div>
        
        <div class="ftt-faq-item">
            <h4><?php esc_html_e('Can I cancel anytime?', 'schedule-collaboration-tracking'); ?></h4>
            <p><?php esc_html_e('Yes! Cancel anytime. During your trial, you won\'t be charged. After trial, you keep access until your current billing period ends.', 'schedule-collaboration-tracking'); ?></p>
        </div>
        
        <div class="ftt-faq-item">
            <h4><?php esc_html_e('Is my payment information secure?', 'schedule-collaboration-tracking'); ?></h4>
            <p><?php esc_html_e('Absolutely. We use Stripe, which processes billions in payments securely. We never see or store your credit card number.', 'schedule-collaboration-tracking'); ?></p>
        </div>
    </div>
</div>

<style>
.ftt-pricing-container {
    max-width: 900px;
    margin: 40px auto;
    padding: 20px;
}

.ftt-pricing-header {
    text-align: center;
    margin-bottom: 40px;
}

.ftt-pricing-header h1 {
    font-size: 36px;
    margin-bottom: 10px;
    color: #2c3e50;
}

.ftt-pricing-subtitle {
    font-size: 18px;
    color: #7f8c8d;
}

.ftt-billing-toggle {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-bottom: 40px;
}

.ftt-toggle-option {
    padding: 12px 24px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.ftt-toggle-option input {
    margin-right: 8px;
}

.ftt-toggle-option:has(input:checked) {
    border-color: #2196F3;
    background: #E3F2FD;
}

.ftt-save-badge {
    background: #4CAF50;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}

.ftt-pricing-cards {
    display: flex;
    justify-content: center;
    gap: 30px;
    margin-bottom: 60px;
}

.ftt-pricing-card {
    flex: 1;
    max-width: 400px;
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    padding: 30px;
    position: relative;
}

.ftt-pricing-card.ftt-popular {
    border-color: #2196F3;
    box-shadow: 0 4px 20px rgba(33, 150, 243, 0.2);
}

.ftt-popular-badge {
    position: absolute;
    top: -12px;
    left: 50%;
    transform: translateX(-50%);
    background: #2196F3;
    color: white;
    padding: 4px 16px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: bold;
}

.ftt-card-header h2 {
    font-size: 24px;
    margin-bottom: 5px;
    color: #2c3e50;
}

.ftt-card-subtitle {
    color: #7f8c8d;
    font-size: 14px;
    margin-bottom: 20px;
}

.ftt-card-price {
    margin: 20px 0;
}

.ftt-price-amount {
    font-size: 48px;
    font-weight: bold;
    color: #2196F3;
}

.ftt-price-period {
    font-size: 18px;
    color: #7f8c8d;
}

.ftt-price-compare {
    display: block;
    font-size: 14px;
    color: #95a5a6;
    text-decoration: line-through;
    margin-top: 5px;
}

.ftt-card-features ul {
    list-style: none;
    padding: 0;
    margin: 20px 0;
}

.ftt-card-features li {
    padding: 8px 0;
    color: #34495e;
}

.ftt-addon-info {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin: 20px 0;
}

.ftt-addon-info p {
    margin: 5px 0;
    font-size: 14px;
}

.ftt-addon-selector {
    margin-top: 15px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.ftt-addon-selector label {
    font-weight: 600;
    font-size: 14px;
    color: #2c3e50;
}

.ftt-quantity-control {
    display: flex;
    align-items: center;
    gap: 10px;
}

.ftt-qty-btn {
    width: 36px;
    height: 36px;
    border: 2px solid #2196F3;
    background: white;
    color: #2196F3;
    border-radius: 6px;
    font-size: 20px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.2s;
    padding: 0;
    line-height: 1;
}

.ftt-qty-btn:hover {
    background: #2196F3;
    color: white;
}

.ftt-qty-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.ftt-addon-qty {
    width: 60px;
    height: 36px;
    text-align: center;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 600;
}

.ftt-addon-total {
    font-size: 18px;
    font-weight: bold;
    color: #2196F3;
    margin-left: 10px;
}

.ftt-cta-button {
    display: block;
    width: 100%;
    padding: 16px;
    background: #2196F3;
    color: white;
    text-align: center;
    border: none;
    border-radius: 8px;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.2s;
    text-decoration: none;
}

.ftt-cta-button:hover {
    background: #1976D2;
}

.ftt-pricing-faq {
    margin-top: 60px;
}

.ftt-pricing-faq h3 {
    text-align: center;
    font-size: 28px;
    margin-bottom: 30px;
    color: #2c3e50;
}

.ftt-faq-item {
    margin-bottom: 25px;
}

.ftt-faq-item h4 {
    font-size: 18px;
    color: #2c3e50;
    margin-bottom: 8px;
}

.ftt-faq-item p {
    color: #7f8c8d;
    line-height: 1.6;
}
</style>
