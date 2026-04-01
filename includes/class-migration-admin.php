<?php
/**
 * Migration Admin Interface - Family Groups v2.1
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FTT_Migration_Admin {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        // Migration v2.1 page removed — consolidated into admin menu cleanup
        add_action('admin_notices', array(__CLASS__, 'migration_notice'));
        add_action('admin_post_ftt_run_migration', array(__CLASS__, 'handle_run_migration'));
        add_action('admin_post_ftt_rollback_migration', array(__CLASS__, 'handle_rollback_migration'));
        add_action('admin_post_ftt_dismiss_migration_notice', array(__CLASS__, 'handle_dismiss_notice'));
    }
    
    /**
     * Check if migration is needed
     */
    public static function is_migration_needed() {
        // Check if v2.1 migration has been run
        if (get_option('ftt_v21_migration_completed')) {
            return false;
        }
        
        // Check if there's v2.0 data to migrate
        global $wpdb;
        
        // Check for users with old parent linking
        $has_old_data = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} 
             WHERE meta_key = 'ftt_parents' AND meta_value != '' AND meta_value != 'a:0:{}'
             LIMIT 1"
        );
        
        return (bool) $has_old_data;
    }
    
    /**
     * Display admin notice if migration is needed
     */
    public static function migration_notice() {
        // Only show to admins
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Check if notice was dismissed
        if (get_transient('ftt_migration_notice_dismissed')) {
            return;
        }
        
        // Check if migration is needed
        if (!self::is_migration_needed()) {
            return;
        }
        
        // Don't show on migration page itself
        $screen = get_current_screen();
        if ($screen && $screen->id === 'ftt_event_page_ftt-migration') {
            return;
        }
        
        $migration_url = admin_url('edit.php?post_type=ftt_event&page=ftt-migration');
        $dismiss_url = wp_nonce_url(
            admin_url('admin-post.php?action=ftt_dismiss_migration_notice'),
            'ftt_dismiss_migration'
        );
        
        ?>
        <div class="notice notice-warning is-dismissible" style="position: relative;">
            <h3>🎉 Family Groups v2.1 - Migration Available</h3>
            <p>
                <strong>Great news!</strong> Family Travel Tracker v2.1 introduces powerful <strong>Family Groups</strong> 
                that enable support for blended families, multiple billing groups, and independent calendars.
            </p>
            <p>
                Your existing data (co-parents, children, events, billing) will be automatically converted to the new 
                group-based structure. <strong>This is completely safe</strong> - we maintain your old data and offer 
                a rollback option.
            </p>
            <p>
                <a href="<?php echo esc_url($migration_url); ?>" class="button button-primary">
                    <span class="dashicons dashicons-groups" style="margin-top: 3px;"></span>
                    Run Migration to v2.1
                </a>
                <a href="<?php echo esc_url($dismiss_url); ?>" class="button button-secondary" style="margin-left: 10px;">
                    Remind Me Later
                </a>
            </p>
            <p style="color: #666; font-size: 12px;">
                ⏱️ <em>Migration takes 1-5 seconds depending on data size. No downtime required.</em>
            </p>
        </div>
        <?php
    }
    
    /**
     * Add migration page to admin menu
     */
    public static function add_migration_page() {
        add_submenu_page(
            'edit.php?post_type=ftt_event',
            __('Family Groups Migration', 'schedule-collaboration-tracking'),
            __('Migration v2.1', 'schedule-collaboration-tracking'),
            'manage_options',
            'ftt-migration',
            array(__CLASS__, 'render_migration_page')
        );
    }
    
    /**
     * Render migration page
     */
    public static function render_migration_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $migration_completed = get_option('ftt_v21_migration_completed');
        $migration_report = get_option('ftt_v21_migration_report');
        $is_needed = self::is_migration_needed();
        
        ?>
        <div class="wrap">
            <h1>
                <span class="dashicons dashicons-groups" style="font-size: 32px; margin-right: 10px;"></span>
                Family Groups v2.1 Migration
            </h1>
            
            <?php if (isset($_GET['migrated']) && $_GET['migrated'] === 'success'): ?>
                <div class="notice notice-success is-dismissible">
                    <h3>✅ Migration Completed Successfully!</h3>
                    <p>Your data has been successfully migrated to Family Groups v2.1.</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['rolled_back']) && $_GET['rolled_back'] === 'success'): ?>
                <div class="notice notice-info is-dismissible">
                    <h3>↩️ Rollback Completed</h3>
                    <p>All v2.1 group data has been removed. Your v2.0 data remains intact.</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="notice notice-error is-dismissible">
                    <h3>❌ Migration Error</h3>
                    <p><?php echo esc_html(urldecode($_GET['error'])); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="ftt-migration-container" style="max-width: 900px; margin-top: 30px;">
                
                <!-- Migration Status Card -->
                <div class="card" style="margin-bottom: 30px;">
                    <h2 style="margin-top: 0;">Migration Status</h2>
                    
                    <?php if ($migration_completed): ?>
                        <div style="background: #d4edda; border-left: 4px solid #28a745; padding: 20px; margin-bottom: 20px;">
                            <h3 style="margin-top: 0; color: #155724;">
                                <span class="dashicons dashicons-yes-alt" style="font-size: 24px;"></span>
                                Migration Complete
                            </h3>
                            <p style="color: #155724; margin: 0;">
                                Your data has been successfully migrated to Family Groups v2.1. 
                                Users can now manage multiple family groups with independent billing and calendars.
                            </p>
                        </div>
                        
                        <?php if ($migration_report): ?>
                            <h3>Migration Report</h3>
                            <table class="widefat" style="margin-bottom: 20px;">
                                <tbody>
                                    <tr>
                                        <td><strong>Started:</strong></td>
                                        <td><?php echo esc_html(date('Y-m-d H:i:s', $migration_report['started'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Completed:</strong></td>
                                        <td><?php echo esc_html(date('Y-m-d H:i:s', $migration_report['completed'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Duration:</strong></td>
                                        <td><?php echo esc_html($migration_report['completed'] - $migration_report['started']); ?> seconds</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Groups Created:</strong></td>
                                        <td><?php echo esc_html($migration_report['groups_created']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Members Added:</strong></td>
                                        <td><?php echo esc_html($migration_report['members_added']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Children Migrated:</strong></td>
                                        <td><?php echo esc_html($migration_report['children_migrated']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Events Associated:</strong></td>
                                        <td><?php echo esc_html($migration_report['events_associated']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Billing Records:</strong></td>
                                        <td><?php echo esc_html($migration_report['billing_migrated']); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                            
                            <?php if (!empty($migration_report['warnings'])): ?>
                                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px;">
                                    <h4 style="margin-top: 0;">⚠️ Warnings</h4>
                                    <ul style="margin: 0;">
                                        <?php foreach ($migration_report['warnings'] as $warning): ?>
                                            <li><?php echo esc_html($warning); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($migration_report['errors'])): ?>
                                <div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin-bottom: 20px;">
                                    <h4 style="margin-top: 0;">❌ Errors</h4>
                                    <ul style="margin: 0;">
                                        <?php foreach ($migration_report['errors'] as $error): ?>
                                            <li><?php echo esc_html($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- Rollback Option -->
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin-top: 20px;">
                            <h3 style="margin-top: 0;">🔄 Rollback Migration</h3>
                            <p>
                                If you experience any issues with the new Family Groups system, you can rollback to v2.0. 
                                This will delete all group data but preserve your original user relationships.
                            </p>
                            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" 
                                  onsubmit="return confirm('Are you sure you want to rollback the migration? This will delete all Family Groups data.');">
                                <?php wp_nonce_field('ftt_rollback_migration'); ?>
                                <input type="hidden" name="action" value="ftt_rollback_migration">
                                <button type="submit" class="button button-secondary" style="background: #dc3545; color: white; border-color: #dc3545;">
                                    <span class="dashicons dashicons-undo" style="margin-top: 3px;"></span>
                                    Rollback to v2.0
                                </button>
                            </form>
                        </div>
                        
                    <?php elseif ($is_needed): ?>
                        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; margin-bottom: 20px;">
                            <h3 style="margin-top: 0; color: #856404;">
                                <span class="dashicons dashicons-info" style="font-size: 24px;"></span>
                                Migration Required
                            </h3>
                            <p style="color: #856404; margin: 0;">
                                You have v2.0 data that needs to be migrated to the new Family Groups v2.1 architecture.
                            </p>
                        </div>
                    <?php else: ?>
                        <div style="background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 20px; margin-bottom: 20px;">
                            <h3 style="margin-top: 0; color: #0c5460;">
                                <span class="dashicons dashicons-info" style="font-size: 24px;"></span>
                                No Migration Needed
                            </h3>
                            <p style="color: #0c5460; margin: 0;">
                                No v2.0 data found that requires migration. You can start using Family Groups immediately.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!$migration_completed && $is_needed): ?>
                    <!-- What Will Happen -->
                    <div class="card" style="margin-bottom: 30px;">
                        <h2 style="margin-top: 0;">What Will Happen During Migration?</h2>
                        
                        <ol style="line-height: 1.8;">
                            <li><strong>Detect Co-Parent Relationships</strong> - Scan for users linked via <code>ftt_parents</code> meta</li>
                            <li><strong>Create Family Groups</strong> - Create a group for each unique set of co-parents</li>
                            <li><strong>Add Children to Groups</strong> - Assign all children to their parents' groups</li>
                            <li><strong>Migrate Billing Data</strong> - Copy Stripe customer/subscription IDs to groups</li>
                            <li><strong>Associate Events</strong> - Link existing events to appropriate family groups</li>
                            <li><strong>Validate Data</strong> - Check for orphaned children or events</li>
                            <li><strong>Generate Report</strong> - Create detailed migration summary</li>
                        </ol>
                        
                        <div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin-top: 20px;">
                            <h4 style="margin-top: 0;">✅ Safety Features</h4>
                            <ul style="margin-bottom: 0;">
                                <li><strong>Non-Destructive:</strong> Your old v2.0 data is preserved</li>
                                <li><strong>Rollback Available:</strong> You can undo the migration if needed</li>
                                <li><strong>Fast:</strong> Typically completes in 1-5 seconds</li>
                                <li><strong>Automatic:</strong> No manual data entry required</li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Run Migration -->
                    <div class="card" style="background: linear-gradient(135deg, #F8F5FB 0%, #E9E3F2 100%);">
                        <h2 style="margin-top: 0;">Ready to Migrate?</h2>
                        <p>Click the button below to start the migration. This process is automatic and typically completes in a few seconds.</p>
                        
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" 
                              onsubmit="return confirm('Ready to migrate to Family Groups v2.1? This is safe and can be rolled back if needed.');">
                            <?php wp_nonce_field('ftt_run_migration'); ?>
                            <input type="hidden" name="action" value="ftt_run_migration">
                            <button type="submit" class="button button-primary button-hero" 
                                    style="background: linear-gradient(135deg, #6A3E8E 0%, #5B347A 100%); border: none; text-shadow: 0 1px 2px rgba(0,0,0,0.2);">
                                <span class="dashicons dashicons-update" style="margin-top: 8px; font-size: 20px;"></span>
                                Run Migration to v2.1
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
                
            </div>
        </div>
        
        <style>
        .ftt-migration-container .card {
            background: white;
            padding: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        .button-hero {
            font-size: 16px;
            height: auto;
            padding: 15px 30px;
        }
        </style>
        <?php
    }
    
    /**
     * Handle migration run
     */
    public static function handle_run_migration() {
        // Verify nonce
        check_admin_referer('ftt_run_migration');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.'));
        }
        
        // Check if already migrated
        if (get_option('ftt_v21_migration_completed')) {
            wp_redirect(add_query_arg(
                array('page' => 'ftt-migration', 'error' => urlencode('Migration already completed')),
                admin_url('edit.php?post_type=ftt_event')
            ));
            exit;
        }
        
        try {
            // Run migration
            $migration = new FTT_Groups_Migration();
            $report = $migration->run_migration();
            
            // Store report
            update_option('ftt_v21_migration_report', $report);
            update_option('ftt_v21_migration_completed', true);
            update_option('ftt_v21_migration_date', current_time('mysql'));
            
            // Redirect with success
            wp_redirect(add_query_arg(
                array('page' => 'ftt-migration', 'migrated' => 'success'),
                admin_url('edit.php?post_type=ftt_event')
            ));
            exit;
            
        } catch (Exception $e) {
            // Redirect with error
            wp_redirect(add_query_arg(
                array('page' => 'ftt-migration', 'error' => urlencode($e->getMessage())),
                admin_url('edit.php?post_type=ftt_event')
            ));
            exit;
        }
    }
    
    /**
     * Handle migration rollback
     */
    public static function handle_rollback_migration() {
        // Verify nonce
        check_admin_referer('ftt_rollback_migration');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.'));
        }
        
        try {
            // Run rollback
            $migration = new FTT_Groups_Migration();
            $migration->rollback_migration();
            
            // Clear migration flags
            delete_option('ftt_v21_migration_report');
            delete_option('ftt_v21_migration_completed');
            delete_option('ftt_v21_migration_date');
            
            // Redirect with success
            wp_redirect(add_query_arg(
                array('page' => 'ftt-migration', 'rolled_back' => 'success'),
                admin_url('edit.php?post_type=ftt_event')
            ));
            exit;
            
        } catch (Exception $e) {
            // Redirect with error
            wp_redirect(add_query_arg(
                array('page' => 'ftt-migration', 'error' => urlencode($e->getMessage())),
                admin_url('edit.php?post_type=ftt_event')
            ));
            exit;
        }
    }
    
    /**
     * Handle dismissing migration notice
     */
    public static function handle_dismiss_notice() {
        // Verify nonce
        check_admin_referer('ftt_dismiss_migration');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.'));
        }
        
        // Set transient to hide notice for 7 days
        set_transient('ftt_migration_notice_dismissed', true, 7 * DAY_IN_SECONDS);
        
        // Redirect back
        wp_redirect(wp_get_referer() ?: admin_url());
        exit;
    }
}
