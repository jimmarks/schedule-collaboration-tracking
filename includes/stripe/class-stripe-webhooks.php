<?php
/**
 * Stripe Webhook Handler
 *
 * Processes incoming webhook events from Stripe to keep local
 * subscription data in sync with Stripe's records.
 *
 * @package FamilyTravelTracker
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTT_Stripe_Webhooks {
    
    /**
     * Register webhook endpoint
     */
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_webhook_endpoint']);
    }
    
    /**
     * Register REST API endpoint for webhooks
     */
    public static function register_webhook_endpoint() {
        register_rest_route('ftt/v1', '/stripe-webhook', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_webhook'],
            'permission_callback' => '__return_true', // Stripe signature verification handles auth
        ]);
    }
    
    /**
     * Handle incoming webhook
     */
    public static function handle_webhook($request) {
        $payload = $request->get_body();
        $sig_header = $request->get_header('stripe-signature');
        
        $settings = get_option('ftt_stripe_settings', []);
        $webhook_secret = $settings['webhook_secret'] ?? '';
        
        if (empty($webhook_secret)) {
            error_log('FTT: Webhook secret not configured');
            return new WP_Error('config_error', 'Webhook not configured', ['status' => 500]);
        }
        
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                $webhook_secret
            );
        } catch (\UnexpectedValueException $e) {
            error_log('FTT: Invalid webhook payload: ' . $e->getMessage());
            return new WP_Error('invalid_payload', 'Invalid payload', ['status' => 400]);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            error_log('FTT: Invalid webhook signature: ' . $e->getMessage());
            return new WP_Error('invalid_signature', 'Invalid signature', ['status' => 400]);
        }
        
        // Log webhook for debugging
        self::log_webhook($event);
        
        // Route to appropriate handler
        switch ($event->type) {
            case 'checkout.session.completed':
                self::handle_checkout_completed($event->data->object);
                break;
                
            case 'customer.subscription.created':
                self::handle_subscription_created($event->data->object);
                break;
                
            case 'customer.subscription.updated':
                self::handle_subscription_updated($event->data->object);
                break;
                
            case 'customer.subscription.deleted':
                self::handle_subscription_deleted($event->data->object);
                break;
                
            case 'customer.subscription.trial_will_end':
                self::handle_trial_will_end($event->data->object);
                break;
                
            case 'invoice.payment_succeeded':
                self::handle_payment_succeeded($event->data->object);
                break;
                
            case 'invoice.payment_failed':
                self::handle_payment_failed($event->data->object);
                break;
                
            default:
                error_log('FTT: Unhandled webhook event type: ' . $event->type);
        }
        
        return rest_ensure_response(['received' => true]);
    }
    
    /**
     * Handle checkout session completed
     */
    private static function handle_checkout_completed($session) {
        $user_id = $session->metadata->wordpress_user_id ?? null;
        
        if (!$user_id) {
            error_log('FTT: No user ID in checkout session metadata');
            return;
        }
        
        // Store customer ID
        if (!empty($session->customer)) {
            update_user_meta($user_id, 'ftt_stripe_customer_id', $session->customer);
        }
        
        // Subscription will be handled by subscription.created event
        delete_user_meta($user_id, 'ftt_pending_checkout_session');
        
        do_action('ftt_checkout_completed', $user_id, $session);
    }
    
    /**
     * Handle subscription created
     */
    private static function handle_subscription_created($subscription) {
        $user_id = $subscription->metadata->wordpress_user_id ?? null;
        
        if (!$user_id) {
            // Try to find user by customer ID
            $users = get_users([
                'meta_key' => 'ftt_stripe_customer_id',
                'meta_value' => $subscription->customer,
                'number' => 1,
            ]);
            
            if (empty($users)) {
                error_log('FTT: Could not find user for subscription: ' . $subscription->id);
                return;
            }
            
            $user_id = $users[0]->ID;
        }
        
        // Extract data
        $status = $subscription->status;
        $interval = $subscription->items->data[0]->plan->interval ?? 'month';
        $addon_quantity = 0;
        
        // Count addon items
        $settings = get_option('ftt_stripe_settings', []);
        $addon_price_monthly = $settings['price_addon_monthly'] ?? '';
        $addon_price_yearly = $settings['price_addon_yearly'] ?? '';
        
        foreach ($subscription->items->data as $item) {
            if ($item->price->id === $addon_price_monthly || $item->price->id === $addon_price_yearly) {
                $addon_quantity = $item->quantity;
                break;
            }
        }
        
        // Calculate prices
        $base_price = $interval === 'year' ? 89.99 : 9.99;
        $addon_price = $interval === 'year' ? 50.00 : 5.00;
        $total_price = $base_price + ($addon_quantity * $addon_price);
        
        // Update user meta
        update_user_meta($user_id, 'ftt_stripe_subscription_id', $subscription->id);
        update_user_meta($user_id, 'ftt_subscription_status', $status);
        update_user_meta($user_id, 'ftt_subscription_interval', $interval);
        update_user_meta($user_id, 'ftt_base_price', number_format($base_price, 2, '.', ''));
        update_user_meta($user_id, 'ftt_addon_quantity', $addon_quantity);
        update_user_meta($user_id, 'ftt_subscription_price', number_format($total_price, 2, '.', ''));
        
        // Trial dates
        if ($subscription->trial_end) {
            update_user_meta($user_id, 'ftt_trial_start', date('Y-m-d H:i:s', $subscription->trial_start));
            update_user_meta($user_id, 'ftt_trial_end', date('Y-m-d H:i:s', $subscription->trial_end));
        }
        
        // Current period
        update_user_meta($user_id, 'ftt_subscription_start', date('Y-m-d H:i:s', $subscription->start_date));
        update_user_meta($user_id, 'ftt_current_period_start', date('Y-m-d H:i:s', $subscription->current_period_start));
        update_user_meta($user_id, 'ftt_current_period_end', date('Y-m-d H:i:s', $subscription->current_period_end));
        update_user_meta($user_id, 'ftt_cancel_at_period_end', $subscription->cancel_at_period_end);
        
        // Send welcome email
        self::send_trial_start_email($user_id);
        
        do_action('ftt_subscription_created', $user_id, $subscription);
    }
    
    /**
     * Handle subscription updated
     */
    private static function handle_subscription_updated($subscription) {
        // Find user
        $users = get_users([
            'meta_key' => 'ftt_stripe_subscription_id',
            'meta_value' => $subscription->id,
            'number' => 1,
        ]);
        
        if (empty($users)) {
            error_log('FTT: Could not find user for subscription update: ' . $subscription->id);
            return;
        }
        
        $user_id = $users[0]->ID;
        
        // Extract data
        $status = $subscription->status;
        $interval = $subscription->items->data[0]->plan->interval ?? 'month';
        $addon_quantity = 0;
        
        // Count addon items
        $settings = get_option('ftt_stripe_settings', []);
        $addon_price_monthly = $settings['price_addon_monthly'] ?? '';
        $addon_price_yearly = $settings['price_addon_yearly'] ?? '';
        
        foreach ($subscription->items->data as $item) {
            if ($item->price->id === $addon_price_monthly || $item->price->id === $addon_price_yearly) {
                $addon_quantity = $item->quantity;
                break;
            }
        }
        
        // Calculate prices
        $base_price = $interval === 'year' ? 89.99 : 9.99;
        $addon_price = $interval === 'year' ? 50.00 : 5.00;
        $total_price = $base_price + ($addon_quantity * $addon_price);
        
        // Update user meta
        update_user_meta($user_id, 'ftt_subscription_status', $status);
        update_user_meta($user_id, 'ftt_subscription_interval', $interval);
        update_user_meta($user_id, 'ftt_base_price', number_format($base_price, 2, '.', ''));
        update_user_meta($user_id, 'ftt_addon_quantity', $addon_quantity);
        update_user_meta($user_id, 'ftt_subscription_price', number_format($total_price, 2, '.', ''));
        update_user_meta($user_id, 'ftt_current_period_start', date('Y-m-d H:i:s', $subscription->current_period_start));
        update_user_meta($user_id, 'ftt_current_period_end', date('Y-m-d H:i:s', $subscription->current_period_end));
        update_user_meta($user_id, 'ftt_cancel_at_period_end', $subscription->cancel_at_period_end);
        
        do_action('ftt_subscription_updated', $user_id, $subscription);
    }
    
    /**
     * Handle subscription deleted
     */
    private static function handle_subscription_deleted($subscription) {
        $users = get_users([
            'meta_key' => 'ftt_stripe_subscription_id',
            'meta_value' => $subscription->id,
            'number' => 1,
        ]);
        
        if (empty($users)) {
            return;
        }
        
        $user_id = $users[0]->ID;
        
        update_user_meta($user_id, 'ftt_subscription_status', 'canceled');
        
        // Send cancellation confirmation email
        self::send_cancellation_email($user_id);
        
        do_action('ftt_subscription_deleted', $user_id, $subscription);
    }
    
    /**
     * Handle trial ending soon
     */
    private static function handle_trial_will_end($subscription) {
        $users = get_users([
            'meta_key' => 'ftt_stripe_subscription_id',
            'meta_value' => $subscription->id,
            'number' => 1,
        ]);
        
        if (empty($users)) {
            return;
        }
        
        $user_id = $users[0]->ID;
        
        // Send trial ending email
        self::send_trial_ending_email($user_id);
        
        do_action('ftt_trial_ending', $user_id, $subscription);
    }
    
    /**
     * Handle successful payment
     */
    private static function handle_payment_succeeded($invoice) {
        if (!$invoice->subscription) {
            return; // One-time payment, not subscription
        }
        
        $users = get_users([
            'meta_key' => 'ftt_stripe_subscription_id',
            'meta_value' => $invoice->subscription,
            'number' => 1,
        ]);
        
        if (empty($users)) {
            return;
        }
        
        $user_id = $users[0]->ID;
        
        // Clear any failed payment flags
        delete_user_meta($user_id, 'ftt_payment_failed_date');
        delete_user_meta($user_id, 'ftt_grace_period_end');
        delete_user_meta($user_id, 'ftt_payment_retry_count');
        delete_user_meta($user_id, 'ftt_payment_failed_notified');
        
        // Send receipt if first payment after trial
        $trial_end = get_user_meta($user_id, 'ftt_trial_end', true);
        if ($trial_end && strtotime($trial_end) > strtotime('-1 day')) {
            self::send_first_payment_email($user_id, $invoice);
        }
        
        do_action('ftt_payment_succeeded', $user_id, $invoice);
    }
    
    /**
     * Handle failed payment
     */
    private static function handle_payment_failed($invoice) {
        if (!$invoice->subscription) {
            return;
        }
        
        $users = get_users([
            'meta_key' => 'ftt_stripe_subscription_id',
            'meta_value' => $invoice->subscription,
            'number' => 1,
        ]);
        
        if (empty($users)) {
            return;
        }
        
        $user_id = $users[0]->ID;
        
        // Mark payment as failed
        $retry_count = (int) get_user_meta($user_id, 'ftt_payment_retry_count', true);
        $failed_date = get_user_meta($user_id, 'ftt_payment_failed_date', true);
        
        if (empty($failed_date)) {
            $failed_date = current_time('mysql');
            update_user_meta($user_id, 'ftt_payment_failed_date', $failed_date);
            
            // Set grace period (7 days)
            $settings = get_option('ftt_stripe_settings', []);
            $grace_days = $settings['grace_period_days'] ?? 7;
            $grace_end = date('Y-m-d H:i:s', strtotime($failed_date . ' +' . $grace_days . ' days'));
            update_user_meta($user_id, 'ftt_grace_period_end', $grace_end);
        }
        
        update_user_meta($user_id, 'ftt_payment_retry_count', $retry_count + 1);
        update_user_meta($user_id, 'ftt_subscription_status', 'past_due');
        
        // Send failure notification
        $notified = get_user_meta($user_id, 'ftt_payment_failed_notified', true);
        if (!$notified) {
            self::send_payment_failed_email($user_id);
            update_user_meta($user_id, 'ftt_payment_failed_notified', true);
        }
        
        do_action('ftt_payment_failed', $user_id, $invoice);
    }
    
    /**
     * Send trial start email
     */
    private static function send_trial_start_email($user_id) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $trial_end = get_user_meta($user_id, 'ftt_trial_end', true);
        $trial_end_formatted = date('F j, Y', strtotime($trial_end));
        
        $subject = 'Welcome to Family Travel Tracker - Your 14-Day Trial Starts Now!';
        $message = "Hi {$user->display_name},\n\n";
        $message .= "Welcome to Family Travel Tracker! Your 14-day free trial has started.\n\n";
        $message .= "Your first payment will be charged on: {$trial_end_formatted}\n";
        $message .= "You can cancel anytime before then with no charge.\n\n";
        $message .= "Get started: " . home_url('/dashboard/') . "\n\n";
        $message .= "Questions? Reply to this email anytime.\n\n";
        $message .= "Thanks,\nThe Family Travel Tracker Team";
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Send trial ending email
     */
    private static function send_trial_ending_email($user_id) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $trial_end = get_user_meta($user_id, 'ftt_trial_end', true);
        $price = get_user_meta($user_id, 'ftt_subscription_price', true);
        $interval = get_user_meta($user_id, 'ftt_subscription_interval', true);
        
        $subject = 'Your Free Trial Ends Soon';
        $message = "Hi {$user->display_name},\n\n";
        $message .= "Just a reminder that your 14-day free trial ends on " . date('F j, Y', strtotime($trial_end)) . ".\n\n";
        $message .= "Your subscription will automatically continue at \${$price}/" . ($interval === 'year' ? 'year' : 'month') . ".\n\n";
        $message .= "Want to cancel? You can do so anytime in your billing settings:\n";
        $message .= home_url('/manage-subscription/') . "\n\n";
        $message .= "Thanks for using Family Travel Tracker!\n";
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Send first payment email
     */
    private static function send_first_payment_email($user_id, $invoice) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $amount = number_format($invoice->amount_paid / 100, 2);
        
        $subject = 'Payment Received - Thank You!';
        $message = "Hi {$user->display_name},\n\n";
        $message .= "Your trial has ended and we've successfully charged your payment method.\n\n";
        $message .= "Amount: \${$amount}\n";
        $message .= "Invoice: {$invoice->hosted_invoice_url}\n\n";
        $message .= "Thank you for being a Family Travel Tracker subscriber!\n";
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Send payment failed email
     */
    private static function send_payment_failed_email($user_id) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $grace_end = get_user_meta($user_id, 'ftt_grace_period_end', true);
        
        $subject = 'Payment Failed - Action Required';
        $message = "Hi {$user->display_name},\n\n";
        $message .= "We were unable to process your payment for Family Travel Tracker.\n\n";
        $message .= "Please update your payment method by " . date('F j, Y', strtotime($grace_end)) . " to avoid service interruption.\n\n";
        $message .= "Update payment method: " . home_url('/manage-subscription/') . "\n\n";
        $message .= "Questions? Reply to this email.\n";
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Send cancellation email
     */
    private static function send_cancellation_email($user_id) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $period_end = get_user_meta($user_id, 'ftt_current_period_end', true);
        
        $subject = 'Subscription Canceled';
        $message = "Hi {$user->display_name},\n\n";
        $message .= "Your subscription has been canceled.\n\n";
        $message .= "You'll continue to have access until: " . date('F j, Y', strtotime($period_end)) . "\n\n";
        $message .= "We're sorry to see you go. If you have feedback, we'd love to hear it.\n";
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Log webhook for debugging
     */
    private static function log_webhook($event) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('FTT Webhook: ' . $event->type . ' - ' . $event->id);
        }
    }
}

// Initialize
FTT_Stripe_Webhooks::init();
