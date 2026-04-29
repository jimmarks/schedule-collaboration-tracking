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
        
        // Show subscription status banners
        add_action('wp_body_open', [__CLASS__, 'show_subscription_status_banner']);
        add_action('wp_head', [__CLASS__, 'add_status_banner_styles']);
        
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
        $mode = $stripe_settings['mode'] ?? 'test';
        $secret_key = $mode === 'live' 
            ? ($stripe_settings['live_secret_key'] ?? '')
            : ($stripe_settings['test_secret_key'] ?? '');
        
        if (empty($secret_key) || empty($stripe_settings['price_base_monthly'])) {
            error_log('FTT BILLING DEBUG: Stripe not configured - allowing access (secret_key: ' . (empty($secret_key) ? 'EMPTY' : 'SET') . ', price_base_monthly: ' . (empty($stripe_settings['price_base_monthly']) ? 'EMPTY' : 'SET') . ')');
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

        // Check admin-granted billing exemptions (per-user or per-group)
        if (self::is_billing_exempt($user_id)) {
            error_log('FTT BILLING DEBUG: User is billing-exempt - allowing access');
            return;
        }
        
        // v2.1: Groups-only billing - check if user has active group access
        if (class_exists('FTT_Family_Groups') && method_exists('FTT_Family_Groups', 'user_has_group_access')) {
            if (FTT_Family_Groups::user_has_group_access($user_id)) {
                error_log('FTT BILLING DEBUG: User has active group billing access - allowing');
                return;
            }
            error_log('FTT BILLING DEBUG: User has no active group billing - redirecting');
            $redirect = method_exists('FTT_Family_Groups', 'get_access_redirect_url')
                ? FTT_Family_Groups::get_access_redirect_url($user_id)
                : add_query_arg('reason', 'no_subscription', home_url('/ftt-groups/'));
            wp_redirect($redirect);
            exit;
        }
        
        // Fallback if FTT_Family_Groups not available
        error_log('FTT BILLING DEBUG: FTT_Family_Groups not available - redirecting to groups');
        wp_redirect(home_url('/ftt-groups/'));
        exit;

    }
    
    /**
     * Check if a user is exempt from billing requirements.
     *
     * Site admins are always exempt. Individual users can be granted
     * exemption via user meta, and entire groups can be exempted
     * via the ftt_billing_exempt_groups option — both managed from
     * the admin Manage Users → Billing Overrides screen.
     *
     * @param int $user_id User ID
     * @return bool
     */
    public static function is_billing_exempt($user_id) {
        // Site admins are always exempt
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        // Per-user exemption granted by admin
        if (get_user_meta($user_id, 'ftt_billing_exempt', true)) {
            return true;
        }

        // Per-group exemption: user belongs to a group marked exempt
        $exempt_groups = get_option('ftt_billing_exempt_groups', []);
        if (!empty($exempt_groups) && class_exists('FTT_Family_Groups')) {
            $user_groups = FTT_Family_Groups::get_user_groups($user_id);
            $exempt_ids  = array_map('intval', (array) $exempt_groups);
            foreach ($user_groups as $group) {
                if (in_array((int) $group->id, $exempt_ids, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if current page is a billing page
     */
    private static function is_billing_page() {
        global $post;
        
        // Check by post slug
        if (is_object($post) && isset($post->post_name)) {
            $billing_slugs = ['pricing', 'manage-subscription', 'checkout-success', 'checkout-cancel', 'ftt-onboarding', 'ftt-trial-expired'];
            if (in_array($post->post_name, $billing_slugs)) {
                return true;
            }
        }
        
        // Fallback: check by REQUEST_URI
        if (isset($_SERVER['REQUEST_URI'])) {
            $uri = $_SERVER['REQUEST_URI'];
            $billing_paths = ['/pricing', '/manage-subscription', '/checkout-success', '/checkout-cancel', '/ftt-groups', '/ftt-onboarding', '/ftt-trial-expired'];
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
        // Admins and billing-exempt users have no limits
        if (self::is_billing_exempt($parent_id)) {
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
        
        $addon_price = $interval === 'year' ? 20.00 : 2.00;
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
            $tpl_key = 'trial_reminder_7';
        } elseif ($days_remaining == 2) {
            $tpl_key = 'trial_reminder_2';
        } else {
            return; // No email for other day values
        }

        $tokens = [
            'display_name'            => $user->display_name,
            'price'                   => $price,
            'interval'                => $period,
            'manage_subscription_url' => home_url('/manage-subscription/'),
        ];
        $subject = FTT_Email_Templates::render_subject($tpl_key, $tokens);
        $message = FTT_Email_Templates::render_body($tpl_key, $tokens);
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
        
        $subject = FTT_Email_Templates::render_subject('access_suspended', []);
        $message = FTT_Email_Templates::render_body('access_suspended', [
            'display_name'            => $user->display_name,
            'manage_subscription_url' => home_url('/manage-subscription/'),
        ]);
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
    
    /**
     * Get group billing summary (v2.1)
     * 
     * @param int $group_id Group ID
     * @return array|null Billing summary or null if group not found
     */
    public static function get_group_billing_summary($group_id) {
        $group = FTT_Family_Groups::get_group($group_id);
        
        if (!$group) {
            return null;
        }
        
        $child_count = FTT_Family_Groups::get_member_count($group_id, 'child');
        $addon_quantity = max(0, $child_count - 1); // First child included in base
        
        $interval = $group->subscription_interval ?: 'month';
        $period = $interval === 'year' ? 'year' : 'month';
        
        $base_price = $interval === 'year' ? 59.90 : 5.99;
        $addon_price = $interval === 'year' ? 20.00 : 2.00;
        $total_price = $base_price + ($addon_quantity * $addon_price);
        
        $in_trial = ($group->subscription_status === 'trialing');
        $days_until_charge = 0;
        
        if ($in_trial && $group->trial_ends_at) {
            $days_until_charge = ceil((strtotime($group->trial_ends_at) - time()) / DAY_IN_SECONDS);
        }
        
        // Determine if billing is set up (has a subscription status)
        $has_billing = !empty($group->subscription_status) && $group->subscription_status !== 'incomplete';
        
        return [
            'group_id' => $group_id,
            'group_name' => $group->name,
            'has_billing' => $has_billing,
            'status' => $group->subscription_status,
            'status_label' => self::get_status_label($group->subscription_status),
            'in_trial' => $in_trial,
            'days_until_charge' => $days_until_charge,
            'trial_ends_at' => $group->trial_ends_at,
            'interval' => $interval,
            'period' => $period,
            'base_price' => '$' . number_format($base_price, 2),
            'addon_quantity' => $addon_quantity,
            'addon_price' => '$' . number_format($addon_price, 2),
            'total_price' => '$' . number_format($total_price, 2),
            'children_count' => $child_count,
            'allowed_children' => $child_count,
            'next_billing_date' => $group->next_billing_date ? date('F j, Y', strtotime($group->next_billing_date)) : 'N/A',
            'cancel_at_end' => false, // Groups don't support cancel-at-period-end yet; Stripe webhook will update this field when needed
            'has_stripe' => !empty($group->stripe_subscription_id),
            'billing_owner' => $group->billing_owner,
            'is_owner' => (get_current_user_id() == $group->billing_owner),
        ];
    }
    
    /**
     * Get user's billing info (checks both old user-based and new group-based)
     * 
     * @param int $user_id User ID
     * @return array Billing information
     */
    public static function get_user_billing_info($user_id) {
        // Check if user has groups (v2.1)
        $groups = FTT_Family_Groups::get_user_groups($user_id);
        
        if (!empty($groups)) {
            // New group-based billing
            $billing_groups = [];
            $total_children = 0;
            
            foreach ($groups as $group) {
                $summary = self::get_group_billing_summary($group->id);
                if ($summary) {
                    $billing_groups[] = $summary;
                    $total_children += $summary['children_count'];
                }
            }
            
            return [
                'mode' => 'groups',
                'groups' => $billing_groups,
                'total_children' => $total_children,
                'has_active_billing' => !empty(array_filter($billing_groups, function($g) {
                    return in_array($g['status'], ['active', 'trialing']);
                })),
            ];
        } else {
            // Old user-based billing (v2.0)
            $summary = self::get_billing_summary($user_id);
            
            return [
                'mode' => 'user',
                'summary' => $summary,
                'has_active_billing' => in_array($summary['status'], ['active', 'trialing']),
            ];
        }
    }
    
    /**
     * Check if user has active subscription (works with both user and group billing)
     * 
     * @param int $user_id User ID
     * @return bool
     */
    public static function has_active_subscription($user_id) {
        $billing_info = self::get_user_billing_info($user_id);
        return $billing_info['has_active_billing'];
    }
    
    /**
     * Add CSS for status banners
     */
    public static function add_status_banner_styles() {
        if (!is_user_logged_in() || current_user_can('manage_options')) {
            return;
        }
        ?>
        <style>
            .ftt-subscription-status-banner {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 999999;
                padding: 12px 20px;
                text-align: center;
                font-size: 14px;
                line-height: 1.5;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .ftt-subscription-status-banner a {
                color: inherit;
                text-decoration: underline;
                font-weight: 600;
            }
            .ftt-subscription-status-banner a:hover {
                text-decoration: none;
            }
            .ftt-status-canceled {
                background: #fff3cd;
                color: #856404;
                border-bottom: 2px solid #ffc107;
            }
            .ftt-status-trialing {
                background: #d1ecf1;
                color: #0c5460;
                border-bottom: 2px solid #17a2b8;
            }
            .ftt-status-past_due {
                background: #f8d7da;
                color: #721c24;
                border-bottom: 2px solid #dc3545;
            }
            body.ftt-has-status-banner {
                padding-top: 50px;
            }
        </style>
        <?php
    }
    
    /**
     * Show subscription status banner at top of page
     */
    public static function show_subscription_status_banner() {
        // Only show on frontend for logged-in non-admin users
        if (!is_user_logged_in() || is_admin() || current_user_can('manage_options')) {
            return;
        }
        
        // Skip if Stripe not configured
        $stripe_settings = get_option('ftt_stripe_settings', []);
        $mode = $stripe_settings['mode'] ?? 'test';
        $secret_key = $mode === 'live' 
            ? ($stripe_settings['live_secret_key'] ?? '')
            : ($stripe_settings['test_secret_key'] ?? '');
        
        if (empty($secret_key)) {
            return;
        }
        
        // Don't show on billing pages
        if (self::is_billing_page()) {
            return;
        }
        
        $user_id     = get_current_user_id();
        $banner_html = '';
        $banner_class = '';

        // ── v2.3+: Read billing status from the user's primary group (group-based billing) ──
        $primary_group_id = get_user_meta($user_id, 'ftt_primary_group', true);
        if ($primary_group_id && class_exists('FTT_Family_Groups')) {
            $group = FTT_Family_Groups::get_group($primary_group_id);
            if ($group) {
                $g_status     = $group->subscription_status;
                $g_trial_end  = $group->trial_ends_at;
                $g_has_stripe = !empty($group->stripe_subscription_id);
                $g_next_bill  = $group->next_billing_date ?? null;

                if ($g_status === 'trialing' && !empty($g_trial_end)) {
                    $days_remaining = (int) ceil((strtotime($g_trial_end) - time()) / DAY_IN_SECONDS);
                    $end_date       = date_i18n(get_option('date_format'), strtotime($g_trial_end));

                    if ($days_remaining > 0) {
                        if ($g_has_stripe) {
                            // Stripe-managed trial — card already on file
                            $banner_html = sprintf(
                                __('🎉 Free trial active &mdash; ends <strong>%s</strong> (%d days remaining). Your card will be charged automatically. <a href="%s">Manage billing</a>', 'schedule-collaboration-tracking'),
                                $end_date, $days_remaining, home_url('/manage-subscription/')
                            );
                            $banner_class = 'ftt-status-trialing';
                        } else {
                            // Card-free trial — need to add payment before trial ends
                            $banner_html = sprintf(
                                __('⏳ Free trial active &mdash; <strong>%d days remaining</strong> (ends %s). Add billing info before your trial ends to keep access. <a href="%s">Set up billing</a>', 'schedule-collaboration-tracking'),
                                $days_remaining, $end_date, home_url('/ftt-onboarding/?step=2')
                            );
                            $banner_class = 'ftt-status-trial-no-card';
                        }
                    }
                } elseif (($g_status === 'canceled' || $g_status === 'active') && !empty($g_next_bill) && strtotime($g_next_bill) > time()) {
                    // Active but scheduled to cancel
                    $days_remaining = (int) ceil((strtotime($g_next_bill) - time()) / DAY_IN_SECONDS);
                    $end_date       = date_i18n(get_option('date_format'), strtotime($g_next_bill));
                    if ($g_status === 'canceled') {
                        $banner_html = sprintf(
                            __('⚠️ Your subscription has been canceled and will expire on <strong>%s</strong> (%d days remaining). <a href="%s">Reactivate</a>', 'schedule-collaboration-tracking'),
                            $end_date, $days_remaining, home_url('/manage-subscription/')
                        );
                        $banner_class = 'ftt-status-canceled';
                    }
                } elseif ($g_status === 'past_due') {
                    $banner_html = sprintf(
                        __('❌ Payment failed. Please <a href="%s">update your payment method</a> to avoid losing access.', 'schedule-collaboration-tracking'),
                        home_url('/manage-subscription/')
                    );
                    $banner_class = 'ftt-status-past_due';
                }
            }
        }

        // ── Fallback: legacy per-user subscription meta ──
        if (!$banner_html) {
            $status        = get_user_meta($user_id, 'ftt_subscription_status', true);
            $period_end    = get_user_meta($user_id, 'ftt_current_period_end', true);
            $cancel_at_end = get_user_meta($user_id, 'ftt_cancel_at_period_end', true);

            if ($status === 'trialing' && !empty($period_end) && strtotime($period_end) > time()) {
                $days_remaining = (int) ceil((strtotime($period_end) - time()) / DAY_IN_SECONDS);
                $end_date       = date_i18n(get_option('date_format'), strtotime($period_end));
                $banner_html  = sprintf(
                    __('🎉 Free trial active &mdash; ends <strong>%s</strong> (%d days remaining). <a href="%s">View billing</a>', 'schedule-collaboration-tracking'),
                    $end_date, $days_remaining, home_url('/manage-subscription/')
                );
                $banner_class = 'ftt-status-trialing';
            } elseif (($status === 'canceled' || ($status === 'active' && $cancel_at_end)) && !empty($period_end) && strtotime($period_end) > time()) {
                $days_remaining = (int) ceil((strtotime($period_end) - time()) / DAY_IN_SECONDS);
                $end_date       = date_i18n(get_option('date_format'), strtotime($period_end));
                $banner_html  = sprintf(
                    __('⚠️ Subscription cancels <strong>%s</strong> (%d days remaining). <a href="%s">Reactivate</a>', 'schedule-collaboration-tracking'),
                    $end_date, $days_remaining, home_url('/manage-subscription/')
                );
                $banner_class = 'ftt-status-canceled';
            } elseif ($status === 'past_due') {
                $grace_end = get_user_meta($user_id, 'ftt_grace_period_end', true);
                if (!empty($grace_end) && strtotime($grace_end) > time()) {
                    $days_remaining = (int) ceil((strtotime($grace_end) - time()) / DAY_IN_SECONDS);
                    $banner_html  = sprintf(
                        __('❌ Payment failed. <a href="%s">Update payment method</a> within %d days.', 'schedule-collaboration-tracking'),
                        home_url('/manage-subscription/'), $days_remaining
                    );
                    $banner_class = 'ftt-status-past_due';
                }
            }
        }

        // Display banner if we have content
        if ($banner_html) {
            echo '<div class="ftt-subscription-status-banner ' . esc_attr($banner_class) . '">' . $banner_html . '</div>';
            echo '<script>document.body.classList.add("ftt-has-status-banner");</script>';
        }
    }
}

// Initialize
FTT_Billing_Manager::init();
