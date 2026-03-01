<?php
/**
 * Template: Billing - Checkout Canceled
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ftt-checkout-cancel-container">
    <div class="ftt-cancel-icon">ℹ️</div>
    
    <h1><?php esc_html_e('Checkout Canceled', 'schedule-collaboration-tracking'); ?></h1>
    
    <p class="ftt-cancel-message">
        <?php esc_html_e('No worries! Your checkout was canceled and you have not been charged.', 'schedule-collaboration-tracking'); ?>
    </p>
    
    <p>
        <?php esc_html_e('Ready to try again? Our 14-day free trial is still waiting for you.', 'schedule-collaboration-tracking'); ?>
    </p>
    
    <div class="ftt-help-section">
        <h3><?php esc_html_e('Have Questions?', 'schedule-collaboration-tracking'); ?></h3>
        <p><?php esc_html_e('We\'re here to help! Contact us if you have any questions about pricing or features.', 'schedule-collaboration-tracking'); ?></p>
    </div>
    
    <div class="ftt-cta-buttons">
        <a href="<?php echo esc_url(home_url('/pricing/')); ?>" class="button button-primary button-large">
            <?php esc_html_e('View Pricing Again', 'schedule-collaboration-tracking'); ?>
        </a>
        <a href="<?php echo esc_url(home_url('/')); ?>" class="button button-secondary">
            <?php esc_html_e('Go Home', 'schedule-collaboration-tracking'); ?>
        </a>
    </div>
</div>

<style>
.ftt-checkout-cancel-container {
    max-width: 600px;
    margin: 60px auto;
    padding: 40px;
    text-align: center;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.1);
}

.ftt-cancel-icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.ftt-checkout-cancel-container h1 {
    font-size: 32px;
    margin-bottom: 20px;
    color: #2c3e50;
}

.ftt-cancel-message {
    font-size: 18px;
    color: #7f8c8d;
    margin-bottom: 20px;
}

.ftt-help-section {
    margin: 30px 0;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.ftt-help-section h3 {
    font-size: 20px;
    margin-bottom: 10px;
    color: #2c3e50;
}

.ftt-cta-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 30px;
}

.button-secondary {
    background: #e0e0e0;
    color: #2c3e50;
}
</style>