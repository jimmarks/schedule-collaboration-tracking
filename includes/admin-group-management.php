<?php
/**
 * Admin Group Management Page
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FTT_Admin_Group_Management {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        add_action('admin_post_ftt_delete_group', array(__CLASS__, 'handle_delete_group'));
        add_action('admin_post_ftt_edit_group', array(__CLASS__, 'handle_edit_group'));
        add_action('admin_post_ftt_cleanup_orphaned_members', array(__CLASS__, 'handle_cleanup_orphaned'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
    }
    
    /**
     * Add admin menu page
     */
    public static function add_admin_page() {
        add_submenu_page(
            'edit.php?post_type=ftt_event',
            __('Manage Groups', 'schedule-collaboration-tracking'),
            __('Manage Groups', 'schedule-collaboration-tracking'),
            'manage_options',
            'ftt-manage-groups',
            array(__CLASS__, 'render_page')
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public static function enqueue_scripts($hook) {
        if ($hook !== 'ftt_event_page_ftt-users-groups') {
            return;
        }
        
        wp_enqueue_style(
            'ftt-admin-groups',
            FTT_PLUGIN_URL . 'assets/css/admin-groups.css',
            array(),
            FTT_VERSION
        );
    }
    
    /**
     * Render the admin page
     */
    public static function render_page() {
        global $wpdb;
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Get all groups
        $table_groups = $wpdb->prefix . 'ftt_family_groups';
        $table_members = $wpdb->prefix . 'ftt_group_members';
        
        $groups = $wpdb->get_results("
            SELECT g.*, 
                   COUNT(DISTINCT m.id) as member_count,
                   SUM(CASE WHEN m.role = 'parent' THEN 1 ELSE 0 END) as parent_count,
                   SUM(CASE WHEN m.role = 'child' THEN 1 ELSE 0 END) as child_count,
                   u.display_name as owner_name,
                   u.user_email as owner_email
            FROM {$table_groups} g
            LEFT JOIN {$table_members} m ON g.id = m.group_id
            LEFT JOIN {$wpdb->users} u ON g.billing_owner = u.ID
            WHERE g.is_archived = 0
            GROUP BY g.id
            ORDER BY g.created_at DESC
        ");
        
        // Dispatch to detail view if action=view
        $action   = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
        $group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;

        if ($action === 'view' && $group_id > 0) {
            self::render_group_detail($group_id);
            return;
        }

        // Handle messages
        $message = '';
        $message_type = '';
        
        if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
            $message = __('Group deleted successfully.', 'schedule-collaboration-tracking');
            $message_type = 'success';
        } elseif (isset($_GET['error'])) {
            $message = __('Error deleting group: ', 'schedule-collaboration-tracking') . urldecode($_GET['error']);
            $message_type = 'error';
        }
        
        ?>
        <div class="wrap ftt-admin-groups-wrap">
            <h1 class="wp-heading-inline">
                <?php esc_html_e('Manage Family Groups', 'schedule-collaboration-tracking'); ?>
            </h1>
            
            <hr class="wp-header-end">
            
            <?php if ($message) : ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="ftt-groups-stats">
                <div class="ftt-stat-card">
                    <div class="ftt-stat-number"><?php echo count($groups); ?></div>
                    <div class="ftt-stat-label"><?php esc_html_e('Total Groups', 'schedule-collaboration-tracking'); ?></div>
                </div>
                <div class="ftt-stat-card">
                    <div class="ftt-stat-number">
                        <?php 
                        $active_subs = array_filter($groups, function($g) {
                            return in_array($g->subscription_status, ['active', 'trialing']);
                        });
                        echo count($active_subs);
                        ?>
                    </div>
                    <div class="ftt-stat-label"><?php esc_html_e('Active Subscriptions', 'schedule-collaboration-tracking'); ?></div>
                </div>
                <div class="ftt-stat-card">
                    <div class="ftt-stat-number">
                        <?php 
                        $total_members = array_sum(array_column($groups, 'member_count'));
                        echo $total_members;
                        ?>
                    </div>
                    <div class="ftt-stat-label"><?php esc_html_e('Total Members', 'schedule-collaboration-tracking'); ?></div>
                </div>
            </div>
            
            <!-- Maintenance Tools -->
            <div class="ftt-maintenance-tools" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                <h2 style="margin-top: 0;"><?php esc_html_e('Maintenance Tools', 'schedule-collaboration-tracking'); ?></h2>
                <p><?php esc_html_e('Clean up orphaned member records from deleted users.', 'schedule-collaboration-tracking'); ?></p>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
                    <?php wp_nonce_field('ftt_cleanup_orphaned', 'ftt_cleanup_nonce'); ?>
                    <input type="hidden" name="action" value="ftt_cleanup_orphaned_members">
                    <button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e('This will remove group member records for users that no longer exist. Continue?', 'schedule-collaboration-tracking'); ?>');">
                        <?php esc_html_e('Clean Up Orphaned Records', 'schedule-collaboration-tracking'); ?>
                    </button>
                </form>
                <?php if (isset($_GET['cleaned']) && isset($_GET['count'])) : ?>
                    <span style="margin-left: 10px; color: #46b450;">
                        ✓ <?php printf(esc_html__('Cleaned up %d orphaned record(s)', 'schedule-collaboration-tracking'), intval($_GET['count'])); ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <?php if (empty($groups)) : ?>
                <div class="ftt-no-groups">
                    <p><?php esc_html_e('No groups found.', 'schedule-collaboration-tracking'); ?></p>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped ftt-groups-table">
                    <thead>
                        <tr>
                            <th class="column-id"><?php esc_html_e('ID', 'schedule-collaboration-tracking'); ?></th>
                            <th class="column-name"><?php esc_html_e('Group Name', 'schedule-collaboration-tracking'); ?></th>
                            <th class="column-owner"><?php esc_html_e('Billing Owner', 'schedule-collaboration-tracking'); ?></th>
                            <th class="column-members"><?php esc_html_e('Members', 'schedule-collaboration-tracking'); ?></th>
                            <th class="column-billing"><?php esc_html_e('Billing', 'schedule-collaboration-tracking'); ?></th>
                            <th class="column-created"><?php esc_html_e('Created', 'schedule-collaboration-tracking'); ?></th>
                            <th class="column-actions"><?php esc_html_e('Actions', 'schedule-collaboration-tracking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groups as $group) : ?>
                            <tr>
                                <td class="column-id">
                                    <strong>#<?php echo esc_html($group->id); ?></strong>
                                </td>
                                <td class="column-name">
                                    <strong><?php echo esc_html(wp_unslash($group->name)); ?></strong>
                                    <?php if ($group->description) : ?>
                                        <br><small class="description"><?php echo esc_html(wp_unslash($group->description)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="column-owner">
                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $group->billing_owner); ?>">
                                        <?php echo esc_html($group->owner_name); ?>
                                    </a>
                                    <br><small><?php echo esc_html($group->owner_email); ?></small>
                                </td>
                                <td class="column-members">
                                    <span class="ftt-member-badge ftt-parent-badge" title="<?php esc_attr_e('Parents', 'schedule-collaboration-tracking'); ?>">
                                        👤 <?php echo intval($group->parent_count); ?>
                                    </span>
                                    <span class="ftt-member-badge ftt-child-badge" title="<?php esc_attr_e('Children', 'schedule-collaboration-tracking'); ?>">
                                        👶 <?php echo intval($group->child_count); ?>
                                    </span>
                                </td>
                                <td class="column-billing">
                                    <?php 
                                    $status = $group->subscription_status;
                                    $status_class = 'ftt-status-' . str_replace('_', '-', strtolower($status));
                                    $status_label = ucfirst(str_replace('_', ' ', $status));
                                    
                                    if (empty($status)) {
                                        $status_class = 'ftt-status-none';
                                        $status_label = __('No Subscription', 'schedule-collaboration-tracking');
                                    }
                                    ?>
                                    <span class="ftt-status-badge <?php echo esc_attr($status_class); ?>">
                                        <?php echo esc_html($status_label); ?>
                                    </span>
                                    <?php if ($group->subscription_interval) : ?>
                                        <br><small><?php echo esc_html(ucfirst($group->subscription_interval) . 'ly'); ?></small>
                                    <?php endif; ?>
                                    <?php if ($group->trial_ends_at && $status === 'trialing') : ?>
                                        <br><small><?php 
                                            $trial_end = strtotime($group->trial_ends_at);
                                            $days_left = max(0, ceil(($trial_end - time()) / DAY_IN_SECONDS));
                                            echo sprintf(__('%d days left', 'schedule-collaboration-tracking'), $days_left);
                                        ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="column-created">
                                    <?php echo date_i18n(get_option('date_format'), strtotime($group->created_at)); ?>
                                    <br><small><?php echo human_time_diff(strtotime($group->created_at), time()) . ' ago'; ?></small>
                                </td>
                                <td class="column-actions">
                                    <a href="<?php echo admin_url('edit.php?post_type=ftt_event&page=ftt-users-groups&tab=groups&action=view&group_id=' . $group->id); ?>" 
                                       class="button button-small">
                                        <?php esc_html_e('View / Edit', 'schedule-collaboration-tracking'); ?>
                                    </a>
                                    <button type="button" 
                                            class="button button-small button-link-delete ftt-delete-group" 
                                            data-group-id="<?php echo esc_attr($group->id); ?>"
                                            data-group-name="<?php echo esc_attr(wp_unslash($group->name)); ?>"
                                            data-member-count="<?php echo esc_attr($group->member_count); ?>">
                                        <?php esc_html_e('Delete', 'schedule-collaboration-tracking'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <!-- Delete Confirmation Modal -->
            <div id="ftt-delete-modal" class="ftt-modal" style="display: none;">
                <div class="ftt-modal-content">
                    <span class="ftt-modal-close">&times;</span>
                    <h2><?php esc_html_e('Delete Group', 'schedule-collaboration-tracking'); ?></h2>
                    <div class="ftt-modal-body">
                        <p class="ftt-warning-text">
                            ⚠️ <?php esc_html_e('Are you sure you want to delete this group?', 'schedule-collaboration-tracking'); ?>
                        </p>
                        <div class="ftt-delete-details">
                            <p><strong><?php esc_html_e('Group:', 'schedule-collaboration-tracking'); ?></strong> <span id="ftt-delete-group-name"></span></p>
                            <p><strong><?php esc_html_e('Members:', 'schedule-collaboration-tracking'); ?></strong> <span id="ftt-delete-member-count"></span></p>
                        </div>
                        <p class="ftt-danger-text">
                            <?php esc_html_e('This action cannot be undone. All group data, memberships, and associations will be permanently deleted.', 'schedule-collaboration-tracking'); ?>
                        </p>
                    </div>
                    <div class="ftt-modal-footer">
                        <button type="button" class="button button-secondary ftt-modal-cancel">
                            <?php esc_html_e('Cancel', 'schedule-collaboration-tracking'); ?>
                        </button>
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
                            <input type="hidden" name="action" value="ftt_delete_group">
                            <input type="hidden" name="group_id" id="ftt-delete-group-id" value="">
                            <?php wp_nonce_field('ftt_delete_group', 'ftt_delete_group_nonce'); ?>
                            <button type="submit" class="button button-primary button-danger">
                                <?php esc_html_e('Delete Group', 'schedule-collaboration-tracking'); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var modal = $('#ftt-delete-modal');
            
            // Open delete modal
            $('.ftt-delete-group').on('click', function() {
                var groupId = $(this).data('group-id');
                var groupName = $(this).data('group-name');
                var memberCount = $(this).data('member-count');
                
                $('#ftt-delete-group-id').val(groupId);
                $('#ftt-delete-group-name').text(groupName);
                $('#ftt-delete-member-count').text(memberCount);
                
                modal.fadeIn(200);
            });
            
            // Close modal
            $('.ftt-modal-close, .ftt-modal-cancel').on('click', function() {
                modal.fadeOut(200);
            });
            
            // Close on outside click
            $(window).on('click', function(event) {
                if (event.target.id === 'ftt-delete-modal') {
                    modal.fadeOut(200);
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle group deletion
     */
    public static function handle_delete_group() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.'));
        }
        
        // Verify nonce
        if (!isset($_POST['ftt_delete_group_nonce']) || !wp_verify_nonce($_POST['ftt_delete_group_nonce'], 'ftt_delete_group')) {
            wp_die(__('Security check failed.'));
        }
        
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        
        if ($group_id <= 0) {
            wp_redirect(admin_url('edit.php?post_type=ftt_event&page=ftt-users-groups&tab=groups&error=' . urlencode('Invalid group ID')));
            exit;
        }
        
        global $wpdb;
        $table_groups = $wpdb->prefix . 'ftt_family_groups';
        
        // Delete the group (CASCADE will handle related tables)
        $deleted = $wpdb->delete($table_groups, array('id' => $group_id), array('%d'));
        
        if ($deleted === false) {
            $error = $wpdb->last_error ? $wpdb->last_error : __('Unknown error', 'schedule-collaboration-tracking');
            wp_redirect(admin_url('edit.php?post_type=ftt_event&page=ftt-users-groups&tab=groups&error=' . urlencode($error)));
        } else {
            wp_redirect(admin_url('edit.php?post_type=ftt_event&page=ftt-users-groups&tab=groups&deleted=1'));
        }
        exit;
    }
    
    /**
     * Handle cleanup of orphaned member records
     */
    public static function handle_cleanup_orphaned() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.'));
        }
        
        // Verify nonce
        if (!isset($_POST['ftt_cleanup_nonce']) || !wp_verify_nonce($_POST['ftt_cleanup_nonce'], 'ftt_cleanup_orphaned')) {
            wp_die(__('Security check failed.'));
        }
        
        // Run cleanup
        $result = FTT_Family_Groups::cleanup_orphaned_members();
        
        // Redirect back with success message
        wp_redirect(admin_url('edit.php?post_type=ftt_event&page=ftt-users-groups&tab=groups&cleaned=1&count=' . $result['orphaned_records']));
        exit;
    }

    /**
     * Render group detail and edit form
     */
    private static function render_group_detail($group_id) {
        global $wpdb;

        $table_groups  = $wpdb->prefix . 'ftt_family_groups';
        $table_members = $wpdb->prefix . 'ftt_group_members';

        $group = $wpdb->get_row($wpdb->prepare(
            "SELECT g.*, u.display_name as owner_name, u.user_email as owner_email
             FROM {$table_groups} g
             LEFT JOIN {$wpdb->users} u ON g.billing_owner = u.ID
             WHERE g.id = %d",
            $group_id
        ));

        if (!$group) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Group not found.', 'schedule-collaboration-tracking') . '</p></div>';
            return;
        }

        $members = $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, u.display_name, u.user_email
             FROM {$table_members} m
             LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
             WHERE m.group_id = %d
             ORDER BY m.role, u.display_name",
            $group_id
        ));

        $back_url = admin_url('edit.php?post_type=ftt_event&page=ftt-users-groups&tab=groups');

        if (isset($_GET['updated']) && $_GET['updated'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Group updated successfully.', 'schedule-collaboration-tracking') . '</p></div>';
        }
        ?>
        <div class="ftt-group-detail">
            <p><a href="<?php echo esc_url($back_url); ?>" class="button button-secondary">&larr; <?php esc_html_e('Back to Groups', 'schedule-collaboration-tracking'); ?></a></p>

            <h2><?php echo esc_html(wp_unslash($group->name)); ?> <span style="font-size:14px;color:#646970;">#<?php echo intval($group->id); ?></span></h2>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:30px;margin-top:20px;">
                <!-- Edit Form -->
                <div>
                    <h3><?php esc_html_e('Group Settings', 'schedule-collaboration-tracking'); ?></h3>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('ftt_edit_group_' . $group_id, 'ftt_edit_group_nonce'); ?>
                        <input type="hidden" name="action" value="ftt_edit_group">
                        <input type="hidden" name="group_id" value="<?php echo intval($group_id); ?>">
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="ftt-gname"><?php esc_html_e('Name', 'schedule-collaboration-tracking'); ?></label></th>
                                <td><input type="text" id="ftt-gname" name="name" value="<?php echo esc_attr(wp_unslash($group->name)); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ftt-gdesc"><?php esc_html_e('Description', 'schedule-collaboration-tracking'); ?></label></th>
                                <td><textarea id="ftt-gdesc" name="description" rows="3" class="regular-text"><?php echo esc_textarea(wp_unslash($group->description ?? '')); ?></textarea></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ftt-gstatus"><?php esc_html_e('Subscription Status', 'schedule-collaboration-tracking'); ?></label></th>
                                <td>
                                    <select id="ftt-gstatus" name="subscription_status">
                                        <?php
                                        $statuses = ['' => 'None', 'trialing' => 'Trialing', 'active' => 'Active', 'past_due' => 'Past Due', 'canceled' => 'Canceled', 'incomplete' => 'Incomplete'];
                                        foreach ($statuses as $val => $label) {
                                            printf(
                                                '<option value="%s"%s>%s</option>',
                                                esc_attr($val),
                                                selected($group->subscription_status, $val, false),
                                                esc_html($label)
                                            );
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ftt-gtrial"><?php esc_html_e('Trial Ends At', 'schedule-collaboration-tracking'); ?></label></th>
                                <td><input type="date" id="ftt-gtrial" name="trial_ends_at" value="<?php echo $group->trial_ends_at ? esc_attr(date('Y-m-d', strtotime($group->trial_ends_at))) : ''; ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ftt-gmaxc"><?php esc_html_e('Max Children', 'schedule-collaboration-tracking'); ?></label></th>
                                <td><input type="number" id="ftt-gmaxc" name="max_children" value="<?php echo intval($group->max_children); ?>" min="0" class="small-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ftt-garchived"><?php esc_html_e('Archived', 'schedule-collaboration-tracking'); ?></label></th>
                                <td><input type="checkbox" id="ftt-garchived" name="is_archived" value="1" <?php checked($group->is_archived, 1); ?>></td>
                            </tr>
                        </table>
                        <?php submit_button(__('Save Changes', 'schedule-collaboration-tracking'), 'primary', 'submit', false); ?>
                    </form>
                </div>

                <!-- Billing Info -->
                <div>
                    <h3><?php esc_html_e('Billing Info', 'schedule-collaboration-tracking'); ?></h3>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Owner', 'schedule-collaboration-tracking'); ?></th>
                            <td>
                                <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . intval($group->billing_owner))); ?>">
                                    <?php echo $group->owner_name ? esc_html($group->owner_name) : '&mdash;'; ?>
                                </a><br>
                                <small><?php echo $group->owner_email ? esc_html($group->owner_email) : '&mdash;'; ?></small>
                            </td>
                        </tr>
                        <?php if (!empty($group->stripe_customer_id)) : ?>
                        <tr>
                            <th scope="row"><?php esc_html_e('Stripe Customer', 'schedule-collaboration-tracking'); ?></th>
                            <td><code><?php echo esc_html($group->stripe_customer_id); ?></code></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($group->stripe_subscription_id)) : ?>
                        <tr>
                            <th scope="row"><?php esc_html_e('Stripe Subscription', 'schedule-collaboration-tracking'); ?></th>
                            <td><code><?php echo esc_html($group->stripe_subscription_id); ?></code></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th scope="row"><?php esc_html_e('Interval', 'schedule-collaboration-tracking'); ?></th>
                            <td><?php echo $group->subscription_interval ? esc_html(ucfirst($group->subscription_interval) . 'ly') : '&mdash;'; ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Created', 'schedule-collaboration-tracking'); ?></th>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($group->created_at))); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Members Table -->
            <h3 style="margin-top:30px;"><?php printf(esc_html__('Members (%d)', 'schedule-collaboration-tracking'), count($members)); ?></h3>
            <?php if (empty($members)) : ?>
                <p><?php esc_html_e('No members found for this group.', 'schedule-collaboration-tracking'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped" style="max-width:700px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', 'schedule-collaboration-tracking'); ?></th>
                            <th><?php esc_html_e('Email', 'schedule-collaboration-tracking'); ?></th>
                            <th><?php esc_html_e('Role', 'schedule-collaboration-tracking'); ?></th>
                            <th><?php esc_html_e('Added', 'schedule-collaboration-tracking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member) : ?>
                            <tr>
                                <td>
                                    <?php if ($member->user_id) : ?>
                                        <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . intval($member->user_id))); ?>">
                                            <?php echo esc_html($member->display_name ?: '(User #' . $member->user_id . ')'); ?>
                                        </a>
                                        <?php if (intval($member->user_id) === intval($group->billing_owner)) : ?>
                                            <span style="color:#2a9d49;font-size:11px;"> &#9733; <?php esc_html_e('Billing Owner', 'schedule-collaboration-tracking'); ?></span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <em style="color:#999;"><?php esc_html_e('(deleted user)', 'schedule-collaboration-tracking'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $member->user_email ? esc_html($member->user_email) : '&mdash;'; ?></td>
                                <td><span class="ftt-status-badge ftt-status-<?php echo esc_attr($member->role); ?>"><?php echo esc_html(ucfirst($member->role)); ?></span></td>
                                <td><?php echo $member->added_at ? esc_html(date_i18n(get_option('date_format'), strtotime($member->added_at))) : '&mdash;'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Handle group edit form submission
     */
    public static function handle_edit_group() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.'));
        }

        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;

        if (!isset($_POST['ftt_edit_group_nonce']) || !wp_verify_nonce($_POST['ftt_edit_group_nonce'], 'ftt_edit_group_' . $group_id)) {
            wp_die(__('Security check failed.'));
        }

        if ($group_id <= 0) {
            wp_redirect(admin_url('edit.php?post_type=ftt_event&page=ftt-users-groups&tab=groups'));
            exit;
        }

        global $wpdb;
        $table_groups = $wpdb->prefix . 'ftt_family_groups';

        $trial_ends_at = !empty($_POST['trial_ends_at']) ? sanitize_text_field(wp_unslash($_POST['trial_ends_at'])) . ' 00:00:00' : null;

        $wpdb->update(
            $table_groups,
            [
                'name'                => sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
                'description'         => sanitize_textarea_field(wp_unslash($_POST['description'] ?? '')),
                'subscription_status' => sanitize_key($_POST['subscription_status'] ?? ''),
                'trial_ends_at'       => $trial_ends_at,
                'max_children'        => intval($_POST['max_children'] ?? 0),
                'is_archived'         => isset($_POST['is_archived']) ? 1 : 0,
            ],
            ['id' => $group_id],
            ['%s', '%s', '%s', '%s', '%d', '%d'],
            ['%d']
        );

        wp_redirect(admin_url('edit.php?post_type=ftt_event&page=ftt-users-groups&tab=groups&action=view&group_id=' . $group_id . '&updated=1'));
        exit;
    }
}

// Initialize
FTT_Admin_Group_Management::init();
