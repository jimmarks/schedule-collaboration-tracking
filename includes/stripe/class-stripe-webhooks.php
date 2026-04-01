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
        $group_id = $session->metadata->group_id ?? null;
        
        if (!$user_id && !$group_id) {
            error_log('FTT: No user ID or group ID in checkout session metadata');
            return;
        }
        
        // Store customer ID
        if ($group_id && !empty($session->customer)) {
            // Group billing (v2.1)
            FTT_Family_Groups::update_group_billing($group_id, [
                'stripe_customer_id' => $session->customer,
            ]);
        } elseif ($user_id && !empty($session->customer)) {
            // User billing (v2.0)
            update_user_meta($user_id, 'ftt_stripe_customer_id', $session->customer);
        }
        
        // Subscription will be handled by subscription.created event
        if ($user_id) {
            delete_user_meta($user_id, 'ftt_pending_checkout_session');
        }
        
        do_action('ftt_checkout_completed', $user_id, $group_id, $session);
    }
    
    /**
     * Handle subscription created
     */
    private static function handle_subscription_created($subscription) {
        $user_id = $subscription->metadata->wordpress_user_id ?? null;
        $group_id = $subscription->metadata->group_id ?? null;
        
        // Try to find by customer ID if no metadata
        if (!$group_id) {
            $group = FTT_Family_Groups::get_group_by_customer_id($subscription->customer);
            if ($group) {
                $group_id = $group->id;
            }
        }
        
        if (!$group_id) {
            error_log('FTT: Could not find group for subscription: ' . $subscription->id);
            return;
        }
        
        // Extract data
        $status = $subscription->status;
        $interval = $subscription->items->data[0]->plan->interval ?? 'month';
        
        // v2.1: Group billing only
        $billing_data = [
            'stripe_subscription_id' => $subscription->id,
            'subscription_status' => $status,
            'subscription_interval' => $interval,
        ];
        
        if ($subscription->trial_end) {
            $billing_data['trial_ends_at'] = date('Y-m-d H:i:s', $subscription->trial_end);
        }
        
        if ($subscription->current_period_end) {
            $billing_data['next_billing_date'] = date('Y-m-d H:i:s', $subscription->current_period_end);
        }
        
        FTT_Family_Groups::update_group_billing($group_id, $billing_data);
        
        // Send welcome email to billing owner
        $group = FTT_Family_Groups::get_group($group_id);
        if ($group && $group->billing_owner) {
            self::send_trial_start_email($group->billing_owner);
        }
        
        do_action('ftt_group_subscription_created', $group_id, $subscription);
    }
    
    /**
     * Handle subscription updated
     */
    private static function handle_subscription_updated($subscription) {
        // v2.1: Groups-only billing
        $group = FTT_Family_Groups::get_group_by_subscription_id($subscription->id);
        
        if (!$group) {
            error_log('FTT: Could not find group for subscription update: ' . $subscription->id);
            return;
        }
        
        $billing_data = [
            'subscription_status' => $subscription->status,
            'subscription_interval' => $subscription->items->data[0]->plan->interval ?? 'month',
        ];
        
        if ($subscription->current_period_end) {
            $billing_data['next_billing_date'] = date('Y-m-d H:i:s', $subscription->current_period_end);
        }
        
        FTT_Family_Groups::update_group_billing($group->id, $billing_data);
        
        do_action('ftt_group_subscription_updated', $group->id, $subscription);
    }
    
    /**
     * Handle subscription deleted
     */
    private static function handle_subscription_deleted($subscription) {
        // v2.1: Groups-only billing
        $group = FTT_Family_Groups::get_group_by_subscription_id($subscription->id);
        
        if (!$group) {
            error_log('FTT: Could not find group for subscription deletion: ' . $subscription->id);
            return;
        }
        
        FTT_Family_Groups::update_group_billing($group->id, [
            'subscription_status' => 'canceled',
        ]);
        
        // Send cancellation email to billing owner
        if ($group->billing_owner) {
            self::send_cancellation_email($group->billing_owner);
        }
        
        do_action('ftt_group_subscription_deleted', $group->id, $subscription);
    }
    
    /**
     * Handle trial ending soon
     */
    private static function handle_trial_will_end($subscription) {
        // v2.1: Groups-only billing
        $group = FTT_Family_Groups::get_group_by_subscription_id($subscription->id);
        
        if (!$group) {
            error_log('FTT: Could not find group for trial ending: ' . $subscription->id);
            return;
        }
        
        if ($group->billing_owner) {
            // Trial ending email removed — 7-day and 2-day cron reminders cover this.
        }
        
        do_action('ftt_group_trial_ending', $group->id, $subscription);
    }
    
    /**
     * Handle successful payment
     */
    private static function handle_payment_succeeded($invoice) {
        if (!$invoice->subscription) {
            return; // One-time payment, not subscription
        }
        
        // v2.1: Groups-only billing
        $group = FTT_Family_Groups::get_group_by_subscription_id($invoice->subscription);
        
        if (!$group) {
            error_log('FTT: Could not find group for payment: ' . $invoice->subscription);
            return;
        }
        
        // Update status to active if it was past_due
        if ($group->subscription_status === 'past_due') {
            FTT_Family_Groups::update_group_billing($group->id, [
                'subscription_status' => 'active',
            ]);
        }
        
        do_action('ftt_group_payment_succeeded', $group->id, $invoice);
    }
    
    /**
     * Handle failed payment
     */
    private static function handle_payment_failed($invoice) {
        if (!$invoice->subscription) {
            return;
        }
        
        // v2.1: Groups-only billing
        $group = FTT_Family_Groups::get_group_by_subscription_id($invoice->subscription);
        
        if (!$group) {
            error_log('FTT: Could not find group for failed payment: ' . $invoice->subscription);
            return;
        }
        
        // Mark group subscription as past_due
        FTT_Family_Groups::update_group_billing($group->id, [
            'subscription_status' => 'past_due',
        ]);
        
        // Send failure notification to billing owner
        if ($group->billing_owner) {
            self::send_payment_failed_email($group->billing_owner);
        }
        
        do_action('ftt_group_payment_failed', $group->id, $invoice);
    }
    
    /**
     * Send trial start email
     */
    private static function send_trial_start_email($user_id) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $trial_end = get_user_meta($user_id, 'ftt_trial_end', true);
        $trial_end_formatted = date('F j, Y', strtotime($trial_end));
        
        $subject = FTT_Email_Templates::render_subject('trial_start', [
            'display_name'   => $user->display_name,
        ]);
        $message = FTT_Email_Templates::render_body('trial_start', [
            'display_name'   => $user->display_name,
            'trial_end_date' => $trial_end_formatted,
            'dashboard_url'  => home_url('/dashboard/'),
        ]);
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
        
        $subject = FTT_Email_Templates::render_subject('trial_ending', []);
        $message = FTT_Email_Templates::render_body('trial_ending', [
            'display_name'            => $user->display_name,
            'trial_end_date'          => date('F j, Y', strtotime($trial_end)),
            'price'                   => $price,
            'interval'                => ($interval === 'year' ? 'year' : 'month'),
            'manage_subscription_url' => home_url('/manage-subscription/'),
        ]);
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Send first payment email
     */
    private static function send_first_payment_email($user_id, $invoice) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $amount = number_format($invoice->amount_paid / 100, 2);
        
        $subject = FTT_Email_Templates::render_subject('first_payment', []);
        $message = FTT_Email_Templates::render_body('first_payment', [
            'display_name' => $user->display_name,
            'amount'       => $amount,
            'invoice_url'  => $invoice->hosted_invoice_url,
        ]);
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Send payment failed email
     */
    private static function send_payment_failed_email($user_id) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $grace_end = get_user_meta($user_id, 'ftt_grace_period_end', true);
        
        $subject = FTT_Email_Templates::render_subject('payment_failed', []);
        $message = FTT_Email_Templates::render_body('payment_failed', [
            'display_name'            => $user->display_name,
            'grace_end_date'          => date('F j, Y', strtotime($grace_end)),
            'manage_subscription_url' => home_url('/manage-subscription/'),
        ]);
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Send cancellation email
     */
    private static function send_cancellation_email($user_id) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $period_end = get_user_meta($user_id, 'ftt_current_period_end', true);
        
        $subject = FTT_Email_Templates::render_subject('subscription_canceled', []);
        $message = FTT_Email_Templates::render_body('subscription_canceled', [
            'display_name'  => $user->display_name,
            'period_end_date' => date('F j, Y', strtotime($period_end)),
        ]);
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
