<?php
/**
 * Admin Billing Dashboard
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FTT_Admin_Billing_Dashboard {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }
    
    /**
     * Add menu page
     */
    public static function add_menu_page() {
        add_submenu_page(
            'edit.php?post_type=ftt_event',
            __('Group Billing Overview', 'schedule-collaboration-tracking'),
            __('Billing Dashboard', 'schedule-collaboration-tracking'),
            'manage_options',
            'ftt-billing-dashboard',
            [__CLASS__, 'render_dashboard']
        );
    }
    
    /**
     * Enqueue scripts
     */
    public static function enqueue_scripts($hook) {
        if ($hook !== 'ftt_event_page_ftt-billing-dashboard') {
            return;
        }
        
        wp_enqueue_style('ftt-billing-admin', plugins_url('assets/css/billing-admin.css', dirname(__FILE__)));
        wp_enqueue_script('ftt-billing-admin', plugins_url('assets/js/billing-admin.js', dirname(__FILE__)), ['jquery'], '1.0', true);
    }
    
    /**
     * Render dashboard
     */
    public static function render_dashboard() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.'));
        }
        
        // Get all groups with billing data
        global $wpdb;
        $groups_table = $wpdb->prefix . 'ftt_family_groups';
        
        $groups = $wpdb->get_results("
            SELECT 
                id,
                name,
                billing_owner,
                stripe_customer_id,
                stripe_subscription_id,
                subscription_status,
                subscription_interval,
                next_billing_date,
                trial_ends_at,
                updated_at
            FROM {$groups_table}
            WHERE is_archived = 0
            ORDER BY 
                CASE subscription_status
                    WHEN 'active' THEN 1
                    WHEN 'trialing' THEN 2
                    WHEN 'past_due' THEN 3
                    WHEN 'canceled' THEN 4
                    ELSE 5
                END,
                name ASC
        ");
        
        // Calculate MRR (Monthly Recurring Revenue)
        $stripe_settings = get_option('ftt_stripe_settings', []);
        $base_monthly = 9.99;
        $addon_monthly = 5.00;
        $base_annual = 99.00;
        $addon_annual = 50.00;
        
        $total_mrr = 0;
        $active_groups = 0;
        $trialing_groups = 0;
        $past_due_groups = 0;
        
        foreach ($groups as $group) {
            if (in_array($group->subscription_status, ['active', 'trialing'])) {
                // Get child count for this group
                $members_table = $wpdb->prefix . 'ftt_family_group_members';
                $child_count = $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(*) 
                    FROM {$members_table}
                    WHERE group_id = %d AND role = 'child'
                ", $group->id));
                
                // Calculate MRR for this group
                if ($group->subscription_interval === 'year') {
                    $group_mrr = ($base_annual + (max(0, $child_count - 1) * $addon_annual)) / 12;
                } else {
                    $group_mrr = $base_monthly + (max(0, $child_count - 1) * $addon_monthly);
                }
                
                $total_mrr += $group_mrr;
                
                if ($group->subscription_status === 'active') {
                    $active_groups++;
                } else if ($group->subscription_status === 'trialing') {
                    $trialing_groups++;
                }
            }
            
            if ($group->subscription_status === 'past_due') {
                $past_due_groups++;
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Group Billing Dashboard', 'schedule-collaboration-tracking'); ?></h1>
            
            <div class="ftt-billing-stats">
                <div class="ftt-stat-card">
                    <div class="ftt-stat-value">$<?php echo number_format($total_mrr, 2); ?></div>
                    <div class="ftt-stat-label">Monthly Recurring Revenue</div>
                </div>
                <div class="ftt-stat-card">
                    <div class="ftt-stat-value"><?php echo $active_groups; ?></div>
                    <div class="ftt-stat-label">Active Subscriptions</div>
                </div>
                <div class="ftt-stat-card">
                    <div class="ftt-stat-value"><?php echo $trialing_groups; ?></div>
                    <div class="ftt-stat-label">In Trial Period</div>
                </div>
                <div class="ftt-stat-card <?php echo $past_due_groups > 0 ? 'alert' : ''; ?>">
                    <div class="ftt-stat-value"><?php echo $past_due_groups; ?></div>
                    <div class="ftt-stat-label">Payment Issues</div>
                </div>
            </div>
            
            <div class="ftt-billing-filters">
                <select id="status-filter">
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="trialing">Trialing</option>
                    <option value="past_due">Past Due</option>
                    <option value="canceled">Canceled</option>
                    <option value="none">No Subscription</option>
                </select>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="20%">Group Name</th>
                        <th width="15%">Billing Owner</th>
                        <th width="10%">Status</th>
                        <th width="10%">Interval</th>
                        <th width="15%">Next Billing / Trial End</th>
                        <th width="10%">Children</th>
                        <th width="10%">MRR</th>
                        <th width="10%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $group): 
                        $billing_owner = get_userdata($group->billing_owner);
                        $billing_owner_name = $billing_owner ? $billing_owner->display_name : 'Unknown';
                        
                        // Get child count
                        $members_table = $wpdb->prefix . 'ftt_family_group_members';
                        $child_count = $wpdb->get_var($wpdb->prepare("
                            SELECT COUNT(*) 
                            FROM {$members_table}
                            WHERE group_id = %d AND role = 'child'
                        ", $group->id));
                        
                        // Calculate MRR
                        $group_mrr = 0;
                        if (in_array($group->subscription_status, ['active', 'trialing'])) {
                            if ($group->subscription_interval === 'year') {
                                $group_mrr = ($base_annual + (max(0, $child_count - 1) * $addon_annual)) / 12;
                            } else {
                                $group_mrr = $base_monthly + (max(0, $child_count - 1) * $addon_monthly);
                            }
                        }
                        
                        // Status indicator
                        $status = $group->subscription_status ?: 'none';
                        $status_class = 'status-' . $status;
                        $status_text = ucfirst($status);
                        
                        // Next billing/trial date
                        $next_date = '';
                        if ($status === 'trialing' && $group->trial_ends_at) {
                            $next_date = date('M j, Y', strtotime($group->trial_ends_at));
                        } else if (in_array($status, ['active', 'past_due']) && $group->next_billing_date) {
                            $next_date = date('M j, Y', strtotime($group->next_billing_date));
                        }
                    ?>
                    <tr data-status="<?php echo esc_attr($status); ?>">
                        <td><strong><?php echo esc_html($group->name); ?></strong></td>
                        <td><?php echo esc_html($billing_owner_name); ?>
                            <?php if ($billing_owner): ?>
                                <br><small><?php echo esc_html($billing_owner->user_email); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><span class="ftt-status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                        <td><?php echo ucfirst($group->subscription_interval ?: '-'); ?></td>
                        <td><?php echo $next_date ?: '-'; ?></td>
                        <td><?php echo $child_count; ?></td>
                        <td>$<?php echo number_format($group_mrr, 2); ?></td>
                        <td>
                            <?php if ($group->stripe_customer_id): ?>
                                <a href="https://dashboard.stripe.com/customers/<?php echo esc_attr($group->stripe_customer_id); ?>" 
                                   target="_blank" 
                                   class="button button-small">View in Stripe</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($groups)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            No groups found. Groups will appear here once they're created.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="ftt-billing-notes">
                <h3>Notes</h3>
                <ul>
                    <li><strong>MRR</strong> = Monthly Recurring Revenue (annual subscriptions are divided by 12)</li>
                    <li><strong>Pricing</strong> = $9.99/month base ($99/year) + $5/month per additional child ($50/year)</li>
                    <li><strong>Trial Period</strong> = 14 days free with payment method required</li>
                    <li><strong>Grace Period</strong> = 7 days after payment failure before access is restricted</li>
                </ul>
            </div>

            <?php
            // ---- API Usage -------------------------------------------------------
            if ( class_exists('FTT_API_Tracker') ) :
                $api_stats   = FTT_API_Tracker::get_stats(7);
                $api_summary = FTT_API_Tracker::get_summary();
                $api_dates   = array_keys($api_stats);
            ?>
            <h2 style="margin-top:30px;">API Usage</h2>

            <div class="ftt-billing-stats" style="grid-template-columns:repeat(4,1fr);">
                <div class="ftt-stat-card">
                    <div class="ftt-stat-value"><?php echo (int) $api_summary['serpapi']['today']; ?></div>
                    <div class="ftt-stat-label">SerpAPI Today</div>
                </div>
                <div class="ftt-stat-card">
                    <div class="ftt-stat-value"><?php echo (int) $api_summary['serpapi']['last_7']; ?></div>
                    <div class="ftt-stat-label">SerpAPI Last 7 Days</div>
                </div>
                <div class="ftt-stat-card">
                    <div class="ftt-stat-value"><?php echo (int) $api_summary['google_places']['today']; ?></div>
                    <div class="ftt-stat-label">Google Places Today</div>
                </div>
                <div class="ftt-stat-card">
                    <div class="ftt-stat-value"><?php echo (int) $api_summary['google_places']['last_7']; ?></div>
                    <div class="ftt-stat-label">Google Places Last 7 Days</div>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped" style="margin-top:20px;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>SerpAPI Calls</th>
                        <th>SerpAPI Errors</th>
                        <th style="color:#1a73e8;">Google Places Calls</th>
                        <th style="color:#1a73e8;">Google Places Errors</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( array_reverse($api_dates) as $date ) :
                        $day      = $api_stats[$date];
                        $s_calls  = (int) ( $day['serpapi']['calls']        ?? 0 );
                        $s_errors = (int) ( $day['serpapi']['errors']       ?? 0 );
                        $g_calls  = (int) ( $day['google_places']['calls']  ?? 0 );
                        $g_errors = (int) ( $day['google_places']['errors'] ?? 0 );
                    ?>
                    <tr>
                        <td><?php echo esc_html( date('D, M j', strtotime($date)) ); ?></td>
                        <td><?php echo $s_calls; ?></td>
                        <td><?php echo $s_errors > 0 ? '<span style="color:#dc3232;">⚠ ' . $s_errors . '</span>' : '—'; ?></td>
                        <td><?php echo $g_calls; ?></td>
                        <td><?php echo $g_errors > 0 ? '<span style="color:#dc3232;">⚠ ' . $g_errors . '</span>' : '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight:600;background:#f6f7f7;">
                        <td>30-Day Total</td>
                        <td><?php echo (int) $api_summary['serpapi']['last_30']; ?></td>
                        <td><?php echo (int) $api_summary['serpapi']['errors_7']; ?> errors (7d)</td>
                        <td><?php echo (int) $api_summary['google_places']['last_30']; ?></td>
                        <td><?php echo (int) $api_summary['google_places']['errors_7']; ?> errors (7d)</td>
                    </tr>
                </tfoot>
            </table>

            <div class="ftt-billing-notes" style="margin-top:16px;">
                <h3>API Cost Reference</h3>
                <ul>
                    <li><strong>SerpAPI:</strong> ~$0.015 per search (Google Flights engine). Free tier: 100/month. 
                        At 4× daily price checks × 30 flights ≈ 3,600 calls/month (paid plan needed).</li>
                    <li><strong>Google Places:</strong> $0.017 per Autocomplete request (first $200/month free via Google Cloud credit).
                        Each location field typed by a user = 1 call.</li>
                </ul>
            </div>
            <?php endif; // FTT_API_Tracker ?>

        </div>
        
        <style>
            .ftt-billing-stats {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 20px;
                margin: 20px 0;
            }
            .ftt-stat-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                text-align: center;
            }
            .ftt-stat-card.alert {
                border-color: #dc3232;
                background: #fff5f5;
            }
            .ftt-stat-value {
                font-size: 32px;
                font-weight: bold;
                color: #2271b1;
                margin-bottom: 8px;
            }
            .ftt-stat-card.alert .ftt-stat-value {
                color: #dc3232;
            }
            .ftt-stat-label {
                font-size: 13px;
                color: #646970;
            }
            .ftt-billing-filters {
                margin: 20px 0;
            }
            .ftt-billing-filters select {
                padding: 6px 10px;
                font-size: 13px;
            }
            .ftt-status-badge {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .ftt-status-badge.status-active {
                background: #d4edda;
                color: #155724;
            }
            .ftt-status-badge.status-trialing {
                background: #d1ecf1;
                color: #0c5460;
            }
            .ftt-status-badge.status-past_due {
                background: #f8d7da;
                color: #721c24;
            }
            .ftt-status-badge.status-canceled {
                background: #f8f9fa;
                color: #6c757d;
            }
            .ftt-status-badge.status-none {
                background: #e2e8f0;
                color: #475569;
            }
            .ftt-billing-notes {
                margin-top: 30px;
                padding: 20px;
                background: #f6f7f7;
                border-radius: 4px;
            }
            .ftt-billing-notes h3 {
                margin-top: 0;
            }
            .ftt-billing-notes ul {
                margin: 10px 0;
                padding-left: 20px;
            }
            .ftt-billing-notes li {
                margin: 5px 0;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Status filter
            $('#status-filter').on('change', function() {
                const status = $(this).val();
                if (status === '') {
                    $('table tbody tr').show();
                } else {
                    $('table tbody tr').hide();
                    $('table tbody tr[data-status="' + status + '"]').show();
                }
            });
        });
        </script>
        <?php
    }
}

// Initialize
FTT_Admin_Billing_Dashboard::init();
