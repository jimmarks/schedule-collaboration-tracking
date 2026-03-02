<?php
/**
 * Billing Manager
 *
 * High-level interface for managing subscriptions, enforcing limits,
 * and coordinating between Stripe and WordPress data.
 *
 * @package FamilyTravelTracker
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTT_Billing_Manager {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        // Restrict access based on subscription
        add_action('init', [__CLASS__, 'check_access']);
        
        // Prevent adding children beyond limit
        add_action('ftt_before_add_child', [__CLASS__, 'check_child_limit'], 10, 2);
        
        // Cron for trial reminders
        add_action('ftt_check_trial_reminders', [__CLASS__, 'send_trial_reminders']);
        add_action('ftt_check_grace_period_expiry', [__CLASS__, 'check_grace_periods']);
        
        // Schedule cron if not scheduled
        if (!wp_next_scheduled('ftt_check_trial_reminders')) {
            wp_schedule_event(time(), 'hourly', 'ftt_check_trial_reminders');
        }
        if (!wp_next_scheduled('ftt_check_grace_period_expiry')) {
            wp_schedule_event(time(), 'hourly', 'ftt_check_grace_period_expiry');
        }
    }
    
    /**
     * Check if user has access
     * Redirects to billing page if subscription required
     */
    public static function check_access() {
        // Only check on frontend page loads
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }
        
        if (!is_user_logged_in()) {
            return;
        }
        
        error_log('FTT BILLING DEBUG: check_access called for user ID: ' . get_current_user_id());
        
        // Skip checks for admins
        if (current_user_can('manage_options')) {
            return;
        }
        
        // Skip checks if Stripe is not configured
        $stripe_settings = get_option('ftt_stripe_settings', []);
        if (empty($stripe_settings['secret_key']) || empty($stripe_settings['price_base_monthly'])) {
            error_log('FTT BILLING DEBUG: Stripe not configured - allowing access (secret_key: ' . (empty($stripe_settings['secret_key']) ? 'EMPTY' : 'SET') . ', price_base_monthly: ' . (empty($stripe_settings['price_base_monthly']) ? 'EMPTY' : 'SET') . ')');
            // Stripe not configured, allow access
            return;
        }
        
        error_log('FTT BILLING DEBUG: Stripe IS configured, checking subscription status');
        
        // Skip checks on billing pages
        if (self::is_billing_page()) {
            error_log('FTT BILLING DEBUG: Current page is billing page - allowing access');
            return;
        }
        
        error_log('FTT BILLING DEBUG: Not on billing page, checking subscription');
        
        $user_id = get_current_user_id();
        $status = get_user_meta($user_id, 'ftt_subscription_status', true);
        
        error_log('FTT BILLING DEBUG: Subscription status: ' . ($status ?: 'EMPTY'));
        
        // Check for admin-imposed access denial
        $access_denied = get_user_meta($user_id, 'ftt_access_denied', true);
        if ($access_denied) {
            error_log('FTT BILLING DEBUG: Access manually denied by admin - redirecting to /pricing/');
            wp_redirect(add_query_arg('reason', 'admin_denied', home_url('/pricing/')));
            exit;
        }
        
        // Block access for invalid subscription statuses
        $blocked_statuses = ['suspended', 'incomplete', 'incomplete_expired'];
        
        if (empty($status)) {
            error_log('FTT BILLING DEBUG: No subscription - redirecting to /pricing/');
            wp_redirect(add_query_arg('reason', 'no_subscription', home_url('/pricing/')));
            exit;
        }
        
        if (in_array($status, $blocked_statuses)) {
            error_log('FTT BILLING DEBUG: Invalid subscription status - redirecting to /pricing/');
            wp_redirect(add_query_arg('reason', $status, home_url('/pricing/')));
            exit;
        }
        
        // Check if subscription period has ended (for canceled or active subscriptions)
        $period_end = get_user_meta($user_id, 'ftt_current_period_end', true);
        if (!empty($period_end) && strtotime($period_end) < time()) {
            error_log('FTT BILLING DEBUG: Subscription period ended - redirecting to /pricing/');
            wp_redirect(add_query_arg('reason', 'expired', home_url('/pricing/')));
            exit;
        }
        
        error_log('FTT BILLING DEBUG: User has valid subscription - allowing access');
        
        // Grace period expired - show warning
        if ($status === 'past_due') {
            $grace_end = get_user_meta($user_id, 'ftt_grace_period_end', true);
            if ($grace_end && strtotime($grace_end) < time()) {
                // Grace period expired, restrict access
                add_action('wp_footer', function() {
                    echo '<div class="ftt-access-warning">Your subscription payment failed. Please update your payment method to continue using Family Travel Tracker. <a href="' . home_url('/manage-subscription/') . '">Update Payment Method</a></div>';
                });
            }
        }
    }
    
    /**
     * Check if current page is a billing page
     */
    private static function is_billing_page() {
        global $post;
        
        // Check by post slug
        if (is_object($post) && isset($post->post_name)) {
            $billing_slugs = ['pricing', 'manage-subscription', 'checkout-success', 'checkout-cancel'];
            if (in_array($post->post_name, $billing_slugs)) {
                return true;
            }
        }
        
        // Fallback: check by REQUEST_URI
        if (isset($_SERVER['REQUEST_URI'])) {
            $uri = $_SERVER['REQUEST_URI'];
            $billing_paths = ['/pricing', '/manage-subscription', '/checkout-success', '/checkout-cancel'];
            foreach ($billing_paths as $path) {
                if (strpos($uri, $path) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if user can add another child
     *
     * @param int $parent_id Parent user ID
     * @param int $child_id Child user ID
     * @throws Exception if limit reached
     */
    public static function check_child_limit($parent_id, $child_id) {
        // Admins have no limits
        if (user_can($parent_id, 'manage_options')) {
            return;
        }
        
        // Check subscription status
        $status = get_user_meta($parent_id, 'ftt_subscription_status', true);
        if (!in_array($status, ['active', 'trialing'])) {
            throw new Exception('Active subscription required to add children.');
        }
        
        $children_count = (int) get_user_meta($parent_id, 'ftt_children_count', true);
        $addon_quantity = (int) get_user_meta($parent_id, 'ftt_addon_quantity', true);
        
        // Base subscription includes 1 child
        $allowed_children = 1 + $addon_quantity;
        
        if ($children_count >= $allowed_children) {
            throw new Exception('Please add a child subscription to continue. Each additional child is $5/month.');
        }
    }
    
    /**
     * Prompt user to add child addon
     *
     * @param int $user_id User ID
     * @return array Prompt data
     */
    public static function get_add_child_prompt($user_id) {
        $children_count = (int) get_user_meta($user_id, 'ftt_children_count', true);
        $addon_quantity = (int) get_user_meta($user_id, 'ftt_addon_quantity', true);
        $interval = get_user_meta($user_id, 'ftt_subscription_interval', true);
        $current_price = get_user_meta($user_id, 'ftt_subscription_price', true);
        
        $addon_price = $interval === 'year' ? 50.00 : 5.00;
        $new_price = $current_price + $addon_price;
        
        $period = $interval === 'year' ? 'year' : 'month';
        
        return [
            'current_children' => $children_count,
            'allowed_children' => 1 + $addon_quantity,
            'current_price' => '$' . number_format($current_price, 2) . '/' . $period,
            'addon_price' => '+$' . number_format($addon_price, 2) . '/' . $period,
            'new_price' => '$' . number_format($new_price, 2) . '/' . $period,
            'proration' => '$' . number_format(self::calculate_proration($user_id, $addon_price), 2),
        ];
    }
    
    /**
     * Calculate prorated charge for addon
     *
     * @param int $user_id User ID
     * @param float $addon_price Monthly/yearly addon price
     * @return float Prorated amount
     */
    private static function calculate_proration($user_id, $addon_price) {
        $period_start = get_user_meta($user_id, 'ftt_current_period_start', true);
        $period_end = get_user_meta($user_id, 'ftt_current_period_end', true);
        
        if (empty($period_start) || empty($period_end)) {
            return $addon_price; // Full price if can't calculate
        }
        
        $total_seconds = strtotime($period_end) - strtotime($period_start);
        $remaining_seconds = strtotime($period_end) - time();
        
        if ($remaining_seconds <= 0 || $total_seconds <= 0) {
            return $addon_price;
        }
        
        $proration_factor = $remaining_seconds / $total_seconds;
        return $addon_price * $proration_factor;
    }
    
    /**
     * Add child and handle billing
     *
     * @param int $parent_id Parent user ID
     * @param int $child_id Child user ID
     * @return bool Success
     */
    public static function add_child_with_billing($parent_id, $child_id) {
        try {
            // Check if addon needed
            self::check_child_limit($parent_id, $child_id);
            
            // Add child relationship
            FTT_Roles::add_parent_child($parent_id, $child_id);
            
            // Update count
            $children_count = (int) get_user_meta($parent_id, 'ftt_children_count', true);
            update_user_meta($parent_id, 'ftt_children_count', $children_count + 1);
            
            return true;
            
        } catch (Exception $e) {
            // Limit reached, need to add addon
            if (strpos($e->getMessage(), 'subscription') !== false) {
                // User needs to add addon first
                return false;
            }
            
            throw $e;
        }
    }
    
    /**
     * Remove child and handle billing
     *
     * @param int $parent_id Parent user ID
     * @param int $child_id Child user ID
     * @return bool Success
     */
    public static function remove_child_with_billing($parent_id, $child_id) {
        // Remove relationship
        FTT_Roles::remove_parent_child($parent_id, $child_id);
        
        // Update count
        $children_count = (int) get_user_meta($parent_id, 'ftt_children_count', true);
        $new_count = max(0, $children_count - 1);
        update_user_meta($parent_id, 'ftt_children_count', $new_count);
        
        // Remove addon if needed
        $addon_quantity = (int) get_user_meta($parent_id, 'ftt_addon_quantity', true);
        $required_addons = max(0, $new_count - 1);
        
        if ($addon_quantity > $required_addons) {
            FTT_Stripe_Integration::remove_child_addon($parent_id);
        }
        
        return true;
    }
    
    /**
     * Send trial reminder emails
     * Called hourly via cron
     */
    public static function send_trial_reminders() {
        // Get all users in trial
        $users = get_users([
            'meta_key' => 'ftt_subscription_status',
            'meta_value' => 'trialing',
            'number' => -1,
        ]);
        
        foreach ($users as $user) {
            $trial_end = get_user_meta($user->ID, 'ftt_trial_end', true);
            if (empty($trial_end)) continue;
            
            $days_remaining = ceil((strtotime($trial_end) - time()) / DAY_IN_SECONDS);
            
            // 7-day reminder
            if ($days_remaining == 7) {
                $sent = get_user_meta($user->ID, 'ftt_trial_reminder_sent_7', true);
                if (!$sent) {
                    self::send_trial_reminder_email($user->ID, 7);
                    update_user_meta($user->ID, 'ftt_trial_reminder_sent_7', true);
                }
            }
            
            // 2-day reminder
            if ($days_remaining == 2) {
                $sent = get_user_meta($user->ID, 'ftt_trial_reminder_sent_12', true);
                if (!$sent) {
                    self::send_trial_reminder_email($user->ID, 2);
                    update_user_meta($user->ID, 'ftt_trial_reminder_sent_12', true);
                }
            }
            
            // Final day reminder
            if ($days_remaining == 0) {
                $sent = get_user_meta($user->ID, 'ftt_trial_reminder_sent_14', true);
                if (!$sent) {
                    self::send_trial_reminder_email($user->ID, 0);
                    update_user_meta($user->ID, 'ftt_trial_reminder_sent_14', true);
                }
            }
        }
    }
    
    /**
     * Send trial reminder email
     */
    private static function send_trial_reminder_email($user_id, $days_remaining) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $price = get_user_meta($user_id, 'ftt_subscription_price', true);
        $interval = get_user_meta($user_id, 'ftt_subscription_interval', true);
        $period = $interval === 'year' ? 'year' : 'month';
        
        if ($days_remaining == 7) {
            $subject = 'Your trial ends in 7 days';
            $message = "Hi {$user->display_name},\n\n";
            $message .= "Just a friendly reminder that your 14-day free trial ends in 7 days.\n\n";
        } elseif ($days_remaining == 2) {
            $subject = 'Your trial ends in 2 days';
            $message = "Hi {$user->display_name},\n\n";
            $message .= "Your free trial ends in 2 days. Your first charge will be \${$price}/{$period}.\n\n";
        } else {
            $subject = 'Your trial ends today';
            $message = "Hi {$user->display_name},\n\n";
            $message .= "Your free trial ends today. You'll be charged \${$price}/{$period} tomorrow.\n\n";
        }
        
        $message .= "Cancel anytime: " . home_url('/manage-subscription/') . "\n\n";
        $message .= "Thanks for using Family Travel Tracker!\n";
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Check grace periods for expired accounts
     */
    public static function check_grace_periods() {
        $users = get_users([
            'meta_key' => 'ftt_subscription_status',
            'meta_value' => 'past_due',
            'number' => -1,
        ]);
        
        foreach ($users as $user) {
            $grace_end = get_user_meta($user->ID, 'ftt_grace_period_end', true);
            if (empty($grace_end)) continue;
            
            // Grace period expired
            if (strtotime($grace_end) < time()) {
                // Suspend access
                update_user_meta($user->ID, 'ftt_subscription_status', 'suspended');
                
                // Invalidate calendar token
                self::invalidate_calendar_access($user->ID);
                
                // Send final notice
                self::send_grace_period_expired_email($user->ID);
                
                do_action('ftt_grace_period_expired', $user->ID);
            }
        }
    }
    
    /**
     * Send grace period expired email
     */
    private static function send_grace_period_expired_email($user_id) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $subject = 'Access Suspended - Payment Required';
        $message = "Hi {$user->display_name},\n\n";
        $message .= "Your Family Travel Tracker access has been suspended due to payment failure.\n\n";
        $message .= "To restore access, please update your payment method:\n";
        $message .= home_url('/manage-subscription/') . "\n\n";
        $message .= "Questions? Reply to this email.\n";
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Invalidate calendar access when subscription becomes invalid
     *
     * @param int $user_id User ID
     */
    public static function invalidate_calendar_access($user_id) {
        if (class_exists('FTT_ICal')) {
            FTT_ICal::invalidate_user_token($user_id);
        }
    }
    
    /**
     * Get user's billing summary for display
     *
     * @param int $user_id User ID
     * @return array Billing summary
     */
    public static function get_billing_summary($user_id) {
        $status = get_user_meta($user_id, 'ftt_subscription_status', true);
        $interval = get_user_meta($user_id, 'ftt_subscription_interval', true);
        $base_price = (float) get_user_meta($user_id, 'ftt_base_price', true);
        $addon_quantity = (int) get_user_meta($user_id, 'ftt_addon_quantity', true);
        $total_price = (float) get_user_meta($user_id, 'ftt_subscription_price', true);
        $children_count = (int) get_user_meta($user_id, 'ftt_children_count', true);
        $trial_end = get_user_meta($user_id, 'ftt_trial_end', true);
        $period_end = get_user_meta($user_id, 'ftt_current_period_end', true);
        $cancel_at_end = (bool) get_user_meta($user_id, 'ftt_cancel_at_period_end', true);
        
        $period = $interval === 'year' ? 'year' : 'month';
        $addon_price = $interval === 'year' ? 50.00 : 5.00;
        
        $in_trial = ($status === 'trialing');
        $days_until_charge = 0;
        
        if ($in_trial && $trial_end) {
            $days_until_charge = ceil((strtotime($trial_end) - time()) / DAY_IN_SECONDS);
        }
        
        return [
            'status' => $status,
            'status_label' => self::get_status_label($status),
            'in_trial' => $in_trial,
            'days_until_charge' => $days_until_charge,
            'interval' => $interval,
            'period' => $period,
            'base_price' => '$' . number_format($base_price, 2),
            'addon_quantity' => $addon_quantity,
            'addon_price' => '$' . number_format($addon_price, 2),
            'total_price' => '$' . number_format($total_price, 2),
            'children_count' => $children_count,
            'allowed_children' => 1 + $addon_quantity,
            'next_billing_date' => $period_end ? date('F j, Y', strtotime($period_end)) : 'N/A',
            'cancel_at_end' => $cancel_at_end,
        ];
    }
    
    /**
     * Get human-readable status label
     */
    private static function get_status_label($status) {
        $labels = [
            'active' => 'Active',
            'trialing' => 'Free Trial',
            'past_due' => 'Payment Failed',
            'canceled' => 'Canceled',
            'suspended' => 'Suspended',
            'incomplete' => 'Incomplete',
        ];
        
        return $labels[$status] ?? ucfirst($status);
    }
}

// Initialize
FTT_Billing_Manager::init();
