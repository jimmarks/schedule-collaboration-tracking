<?php
/**
 * Template: Billing - Checkout Success
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

if (!is_user_logged_in()) {
    wp_redirect(home_url('/'));
    exit;
}

// Only fire the Google Ads conversion snippet once per session
// (protects against page refresh double-counting).
$already_tracked = isset($_COOKIE['ftt_ads_conversion_fired']);
$is_real_checkout = isset($_GET['ftt_checkout']) && $_GET['ftt_checkout'] === 'success';
?>

<?php if ($is_real_checkout && !$already_tracked): ?>
<!-- Google tag (gtag.js) event - fires once on successful checkout -->
<script>
  gtag('event', 'conversion_event_purchase', {
      'value': 9.99,
      'currency': 'USD',
      'transaction_id': '<?php echo esc_js( sanitize_text_field( $_GET['session_id'] ?? '' ) ); ?>'
  });
</script>
<?php
    // Set a short-lived cookie so a page refresh doesn't re-fire the event.
    if (!headers_sent()) {
        setcookie('ftt_ads_conversion_fired', '1', time() + 300, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
    }
endif;
?>

<div class="ftt-checkout-success-container">
    <div class="ftt-success-icon">✓</div>
    
    <h1><?php esc_html_e('Welcome to Family Travel Tracker!', 'schedule-collaboration-tracking'); ?></h1>
    
    <p class="ftt-success-message">
        <?php esc_html_e('Your free trial has started. You now have full access to all features.', 'schedule-collaboration-tracking'); ?>
    </p>
    
    <div class="ftt-next-steps">
        <h2><?php esc_html_e('Get Started:', 'schedule-collaboration-tracking'); ?></h2>
        <ul>
            <li>Add your first child to the system</li>
            <li>Create events on your calendar</li>
            <li>Invite co-parents if needed</li>
            <li>Set up calendar sync on your phone</li>
        </ul>
    </div>
    
    <div class="ftt-cta-buttons">
        <a href="<?php echo esc_url(home_url('/ftt-dashboard/')); ?>" class="button button-primary button-large">
            <?php esc_html_e('Go to Dashboard', 'schedule-collaboration-tracking'); ?>
        </a>
        <a href="<?php echo esc_url(home_url('/manage-subscription/')); ?>" class="button button-secondary">
            <?php esc_html_e('View Subscription', 'schedule-collaboration-tracking'); ?>
        </a>
    </div>
</div>

<style>
.ftt-checkout-success-container {
    max-width: 600px;
    margin: 60px auto;
    padding: 40px;
    text-align: center;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.1);
}

.ftt-success-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 30px;
    background: #4CAF50;
    color: white;
    border-radius: 50%;
    font-size: 48px;
    line-height: 80px;
}

.ftt-checkout-success-container h1 {
    font-size: 32px;
    margin-bottom: 20px;
    color: #2c3e50;
}

.ftt-success-message {
    font-size: 18px;
    color: #7f8c8d;
    margin-bottom: 40px;
}

.ftt-next-steps {
    text-align: left;
    margin: 30px 0;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.ftt-next-steps h2 {
    font-size: 20px;
    margin-bottom: 15px;
}

.ftt-next-steps ul {
    list-style: none;
    padding: 0;
}

.ftt-next-steps li {
    padding: 8px 0;
    padding-left: 30px;
    position: relative;
}

.ftt-next-steps li:before {
    content: "→";
    position: absolute;
    left: 0;
    color: #2196F3;
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