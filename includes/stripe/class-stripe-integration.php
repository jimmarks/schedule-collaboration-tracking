<?php
/**
 * Stripe Integration Core
 *
 * Main class for Stripe API integration, handling customer creation,
 * subscription management, and payment processing.
 *
 * @package FamilyTravelTracker
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include Stripe PHP library (install via composer or manually)
// For now, we'll use Stripe's official PHP library
// Run: composer require stripe/stripe-php
// Or download from: https://github.com/stripe/stripe-php
if (!class_exists('\Stripe\Stripe')) {
    require_once FTT_PLUGIN_DIR . 'lib/stripe-php/init.php';
}

class FTT_Stripe_Integration {
    
    /**
     * Stripe API instance
     */
    private static $stripe_initialized = false;
    
    /**
     * Initialize Stripe SDK
     */
    public static function init() {
        if (self::$stripe_initialized) {
            return;
        }
        
        $settings = get_option('ftt_stripe_settings', []);
        $mode = $settings['mode'] ?? 'test';
        
        if ($mode === 'live') {
            $secret_key = $settings['live_secret_key'] ?? '';
        } else {
            $secret_key = $settings['test_secret_key'] ?? '';
        }
        
        if (empty($secret_key)) {
            error_log('FTT: Stripe secret key not configured');
            return false;
        }
        
        \Stripe\Stripe::setApiKey($secret_key);
        \Stripe\Stripe::setApiVersion('2023-10-16');
        
        self::$stripe_initialized = true;
        return true;
    }
    
    /**
     * Get publishable key for frontend
     */
    public static function get_publishable_key() {
        $settings = get_option('ftt_stripe_settings', []);
        $mode = $settings['mode'] ?? 'test';
        
        if ($mode === 'live') {
            return $settings['live_publishable_key'] ?? '';
        } else {
            return $settings['test_publishable_key'] ?? '';
        }
    }
    
    /**
     * Create or retrieve Stripe customer for WordPress user
     *
     * @param int $user_id WordPress user ID
     * @return string|false Stripe customer ID or false on failure
     */
    public static function get_or_create_customer($user_id) {
        self::init();
        
        // Check if customer already exists
        $customer_id = get_user_meta($user_id, 'ftt_stripe_customer_id', true);
        
        if ($customer_id) {
            // Verify customer still exists in Stripe
            try {
                $customer = \Stripe\Customer::retrieve($customer_id);
                if ($customer && !$customer->deleted) {
                    return $customer_id;
                }
            } catch (Exception $e) {
                error_log('FTT: Error retrieving Stripe customer: ' . $e->getMessage());
            }
        }
        
        // Create new customer
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        try {
            $customer = \Stripe\Customer::create([
                'email' => $user->user_email,
                'name' => $user->display_name,
                'metadata' => [
                    'wordpress_user_id' => $user_id,
                    'site_url' => home_url(),
                ],
            ]);
            
            update_user_meta($user_id, 'ftt_stripe_customer_id', $customer->id);
            
            do_action('ftt_stripe_customer_created', $customer->id, $user_id);
            
            return $customer->id;
            
        } catch (Exception $e) {
            error_log('FTT: Error creating Stripe customer: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create checkout session for new subscription
     *
     * @param int $user_id WordPress user ID
     * @param string $interval 'month' or 'year'
     * @param int $addon_quantity Number of additional children (default 0)
     * @return array|false Session data or false on failure
     */
    public static function create_checkout_session($user_id, $interval = 'month', $addon_quantity = 0) {
        self::init();
        
        $customer_id = self::get_or_create_customer($user_id);
        if (!$customer_id) {
            return false;
        }
        
        $settings = get_option('ftt_stripe_settings', []);
        $trial_days = $settings['trial_days'] ?? 14;
        
        // Get price IDs
        $base_price_id = $interval === 'year' 
            ? ($settings['price_base_yearly'] ?? '') 
            : ($settings['price_base_monthly'] ?? '');
        
        $addon_price_id = $interval === 'year'
            ? ($settings['price_addon_yearly'] ?? '')
            : ($settings['price_addon_monthly'] ?? '');
        
        if (empty($base_price_id)) {
            error_log('FTT: Base price ID not configured for interval: ' . $interval);
            return false;
        }
        
        // Build line items
        $line_items = [
            [
                'price' => $base_price_id,
                'quantity' => 1,
            ],
        ];
        
        // Add addon line items if needed
        if ($addon_quantity > 0 && !empty($addon_price_id)) {
            $line_items[] = [
                'price' => $addon_price_id,
                'quantity' => $addon_quantity,
            ];
        }
        
        // Success/Cancel URLs - use domain routing for app domain
        $app_url = class_exists('FTT_Domain_Routing') 
            ? FTT_Domain_Routing::get_app_url() 
            : ($settings['app_domain'] ?? home_url());
        $app_url = rtrim($app_url, '/');
        
        $success_url = add_query_arg(
            ['ftt_checkout' => 'success', 'session_id' => '{CHECKOUT_SESSION_ID}'],
            $app_url . '/ftt-checkout-success/'
        );
        
        $cancel_url = add_query_arg(
            ['ftt_checkout' => 'cancel'],
            $app_url . '/ftt-checkout-cancel/'
        );
        
        try {
            $session = \Stripe\Checkout\Session::create([
                'customer' => $customer_id,
                'mode' => 'subscription',
                'line_items' => $line_items,
                'success_url' => $success_url,
                'cancel_url' => $cancel_url,
                'subscription_data' => [
                    'trial_period_days' => $trial_days,
                    'metadata' => [
                        'wordpress_user_id' => $user_id,
                        'interval' => $interval,
                        'addon_quantity' => $addon_quantity,
                    ],
                ],
                'metadata' => [
                    'wordpress_user_id' => $user_id,
                ],
            ]);
            
            // Store session ID for verification
            update_user_meta($user_id, 'ftt_pending_checkout_session', $session->id);
            
            return [
                'session_id' => $session->id,
                'url' => $session->url,
            ];
            
        } catch (Exception $e) {
            error_log('FTT: Error creating checkout session: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add an additional child subscription addon
     *
     * @param int $user_id WordPress user ID
     * @return bool Success
     */
    public static function add_child_addon($user_id) {
        self::init();
        
        $subscription_id = get_user_meta($user_id, 'ftt_stripe_subscription_id', true);
        if (empty($subscription_id)) {
            return false;
        }
        
        $current_addon_qty = (int) get_user_meta($user_id, 'ftt_addon_quantity', true);
        $interval = get_user_meta($user_id, 'ftt_subscription_interval', true);
        
        $settings = get_option('ftt_stripe_settings', []);
        $addon_price_id = $interval === 'year'
            ? ($settings['price_addon_yearly'] ?? '')
            : ($settings['price_addon_monthly'] ?? '');
        
        if (empty($addon_price_id)) {
            error_log('FTT: Addon price ID not configured');
            return false;
        }
        
        try {
            $subscription = \Stripe\Subscription::retrieve($subscription_id);
            
            // Find or add the addon line item
            $addon_item = null;
            foreach ($subscription->items->data as $item) {
                if ($item->price->id === $addon_price_id) {
                    $addon_item = $item;
                    break;
                }
            }
            
            if ($addon_item) {
                // Update existing addon quantity
                \Stripe\SubscriptionItem::update($addon_item->id, [
                    'quantity' => $addon_item->quantity + 1,
                ]);
            } else {
                // Add new addon line item
                \Stripe\SubscriptionItem::create([
                    'subscription' => $subscription_id,
                    'price' => $addon_price_id,
                    'quantity' => 1,
                ]);
            }
            
            // Update local meta
            update_user_meta($user_id, 'ftt_addon_quantity', $current_addon_qty + 1);
            
            do_action('ftt_child_addon_added', $user_id);
            
            return true;
            
        } catch (Exception $e) {
            error_log('FTT: Error adding child addon: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove a child subscription addon
     *
     * @param int $user_id WordPress user ID
     * @return bool Success
     */
    public static function remove_child_addon($user_id) {
        self::init();
        
        $subscription_id = get_user_meta($user_id, 'ftt_stripe_subscription_id', true);
        $current_addon_qty = (int) get_user_meta($user_id, 'ftt_addon_quantity', true);
        
        if (empty($subscription_id) || $current_addon_qty <= 0) {
            return false;
        }
        
        $interval = get_user_meta($user_id, 'ftt_subscription_interval', true);
        $settings = get_option('ftt_stripe_settings', []);
        $addon_price_id = $interval === 'year'
            ? ($settings['price_addon_yearly'] ?? '')
            : ($settings['price_addon_monthly'] ?? '');
        
        try {
            $subscription = \Stripe\Subscription::retrieve($subscription_id);
            
            // Find addon line item
            $addon_item = null;
            foreach ($subscription->items->data as $item) {
                if ($item->price->id === $addon_price_id) {
                    $addon_item = $item;
                    break;
                }
            }
            
            if (!$addon_item) {
                return false;
            }
            
            if ($addon_item->quantity > 1) {
                // Decrease quantity
                \Stripe\SubscriptionItem::update($addon_item->id, [
                    'quantity' => $addon_item->quantity - 1,
                ]);
            } else {
                // Remove item entirely
                \Stripe\SubscriptionItem::delete($addon_item->id);
            }
            
            // Update local meta
            update_user_meta($user_id, 'ftt_addon_quantity', $current_addon_qty - 1);
            
            do_action('ftt_child_addon_removed', $user_id);
            
            return true;
            
        } catch (Exception $e) {
            error_log('FTT: Error removing child addon: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cancel subscription at period end
     *
     * @param int $user_id WordPress user ID
     * @return bool Success
     */
    public static function cancel_subscription($user_id) {
        self::init();
        
        $subscription_id = get_user_meta($user_id, 'ftt_stripe_subscription_id', true);
        if (empty($subscription_id)) {
            return false;
        }
        
        try {
            $subscription = \Stripe\Subscription::update($subscription_id, [
                'cancel_at_period_end' => true,
            ]);
            
            update_user_meta($user_id, 'ftt_cancel_at_period_end', true);
            update_user_meta($user_id, 'ftt_subscription_status', 'canceled');
            
            do_action('ftt_subscription_canceled', $user_id);
            
            return true;
            
        } catch (Exception $e) {
            error_log('FTT: Error canceling subscription: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reactivate a canceled subscription
     *
     * @param int $user_id WordPress user ID
     * @return bool Success
     */
    public static function reactivate_subscription($user_id) {
        self::init();
        
        $subscription_id = get_user_meta($user_id, 'ftt_stripe_subscription_id', true);
        if (empty($subscription_id)) {
            return false;
        }
        
        try {
            $subscription = \Stripe\Subscription::update($subscription_id, [
                'cancel_at_period_end' => false,
            ]);
            
            update_user_meta($user_id, 'ftt_cancel_at_period_end', false);
            update_user_meta($user_id, 'ftt_subscription_status', 'active');
            
            do_action('ftt_subscription_reactivated', $user_id);
            
            return true;
            
        } catch (Exception $e) {
            error_log('FTT: Error reactivating subscription: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Change billing interval (monthly <-> yearly)
     *
     * @param int $user_id WordPress user ID
     * @param string $new_interval 'month' or 'year'
     * @return bool Success
     */
    public static function change_billing_interval($user_id, $new_interval) {
        self::init();
        
        $subscription_id = get_user_meta($user_id, 'ftt_stripe_subscription_id', true);
        $addon_quantity = (int) get_user_meta($user_id, 'ftt_addon_quantity', true);
        
        if (empty($subscription_id)) {
            return false;
        }
        
        $settings = get_option('ftt_stripe_settings', []);
        $base_price_id = $new_interval === 'year'
            ? ($settings['price_base_yearly'] ?? '')
            : ($settings['price_base_monthly'] ?? '');
        
        $addon_price_id = $new_interval === 'year'
            ? ($settings['price_addon_yearly'] ?? '')
            : ($settings['price_addon_monthly'] ?? '');
        
        if (empty($base_price_id)) {
            return false;
        }
        
        try {
            $subscription = \Stripe\Subscription::retrieve($subscription_id);
            
            // Update base item
            $items = [];
            foreach ($subscription->items->data as $item) {
                $items[] = [
                    'id' => $item->id,
                    'deleted' => true,
                ];
            }
            
            // Add new base price
            $items[] = [
                'price' => $base_price_id,
                'quantity' => 1,
            ];
            
            // Add new addon price if needed
            if ($addon_quantity > 0 && !empty($addon_price_id)) {
                $items[] = [
                    'price' => $addon_price_id,
                    'quantity' => $addon_quantity,
                ];
            }
            
            \Stripe\Subscription::update($subscription_id, [
                'items' => $items,
                'proration_behavior' => 'always_invoice',
            ]);
            
            update_user_meta($user_id, 'ftt_subscription_interval', $new_interval);
            
            do_action('ftt_billing_interval_changed', $user_id, $new_interval);
            
            return true;
            
        } catch (Exception $e) {
            error_log('FTT: Error changing billing interval: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get subscription details for display
     *
     * @param int $user_id WordPress user ID
     * @return array|false Subscription data or false
     */
    public static function get_subscription_details($user_id) {
        $subscription_id = get_user_meta($user_id, 'ftt_stripe_subscription_id', true);
        
        if (empty($subscription_id)) {
            return false;
        }
        
        return [
            'status' => get_user_meta($user_id, 'ftt_subscription_status', true),
            'interval' => get_user_meta($user_id, 'ftt_subscription_interval', true),
            'base_price' => get_user_meta($user_id, 'ftt_base_price', true),
            'addon_quantity' => (int) get_user_meta($user_id, 'ftt_addon_quantity', true),
            'total_price' => get_user_meta($user_id, 'ftt_subscription_price', true),
            'children_count' => (int) get_user_meta($user_id, 'ftt_children_count', true),
            'trial_end' => get_user_meta($user_id, 'ftt_trial_end', true),
            'current_period_end' => get_user_meta($user_id, 'ftt_current_period_end', true),
            'cancel_at_period_end' => (bool) get_user_meta($user_id, 'ftt_cancel_at_period_end', true),
        ];
    }
    
    /**
     * Check if user has active subscription
     *
     * @param int $user_id WordPress user ID
     * @return bool
     */
    public static function has_active_subscription($user_id) {
        $status = get_user_meta($user_id, 'ftt_subscription_status', true);
        return in_array($status, ['active', 'trialing']);
    }
    
    /**
     * Create billing portal session
     *
     * @param int $user_id WordPress user ID
     * @return string|false Portal URL or false
     */
    public static function create_portal_session($user_id) {
        self::init();
        
        $customer_id = get_user_meta($user_id, 'ftt_stripe_customer_id', true);
        if (empty($customer_id)) {
            return false;
        }
        
        $return_url = home_url('/billing/manage/');
        
        try {
            $session = \Stripe\BillingPortal\Session::create([
                'customer' => $customer_id,
                'return_url' => $return_url,
            ]);
            
            return $session->url;
            
        } catch (Exception $e) {
            error_log('FTT: Error creating portal session: ' . $e->getMessage());
            return false;
        }
    }
}
