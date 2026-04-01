<?php
/**
 * Event Migration Tool
 * 
 * Tool for assigning unassigned events to users
 *
 * @package Schedule_Collaboration_Tracking
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SRT Event Migration Class
 */
class FTT_Event_Migration {
    
    /**
     * Initialize
     */
    public static function init() {
        add_action('admin_post_ftt_migrate_events', array(__CLASS__, 'handle_migration'));
    }
    
    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=ftt_event',
            __('Migrate Events', 'schedule-collaboration-tracking'),
            __('Migrate Events', 'schedule-collaboration-tracking'),
            'manage_options',
            'ftt-migrate-events',
            array(__CLASS__, 'render_page')
        );
    }
    
    /**
     * Get unassigned events
     */
    public static function get_unassigned_events() {
        $args = array(
            'post_type' => 'ftt_event',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'author' => 0, // Events with no author
            'orderby' => 'date',
            'order' => 'DESC',
        );
        
        return get_posts($args);
    }
    
    /**
     * Get all members
     */
    public static function get_all_members() {
        return FTT_Roles::get_all_members();
    }
    
    /**
     * Render migration page
     */
    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.'));
        }
        
        $unassigned = self::get_unassigned_events();
        $members = self::get_all_members();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Migrate Unassigned Events', 'schedule-collaboration-tracking'); ?></h1>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php printf(esc_html__('%d event(s) successfully assigned!', 'schedule-collaboration-tracking'), intval($_GET['count'])); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (empty($unassigned)): ?>
                <div class="notice notice-success">
                    <p><?php esc_html_e('✓ All events are assigned! No migration needed.', 'schedule-collaboration-tracking'); ?></p>
                </div>
            <?php else: ?>
                <div class="notice notice-info">
                    <p><?php printf(esc_html__('Found %d unassigned event(s). Assign them to members below.', 'schedule-collaboration-tracking'), count($unassigned)); ?></p>
                </div>
                
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="ftt-migration-form">
                    <?php wp_nonce_field('ftt_migrate_events', 'ftt_migration_nonce'); ?>
                    <input type="hidden" name="action" value="ftt_migrate_events">
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 50px;">
                                    <input type="checkbox" id="select-all">
                                </th>
                                <th><?php esc_html_e('Event Title', 'schedule-collaboration-tracking'); ?></th>
                                <th><?php esc_html_e('Event Type', 'schedule-collaboration-tracking'); ?></th>
                                <th><?php esc_html_e('Date', 'schedule-collaboration-tracking'); ?></th>
                                <th><?php esc_html_e('Assign To', 'schedule-collaboration-tracking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unassigned as $event): 
                                $event_type = get_post_meta($event->ID, 'event_type', true);
                                $start_date = get_post_meta($event->ID, 'start_datetime', true);
                                $event_types = FTT_CPT::get_event_types();
                                $type_label = isset($event_types[$event_type]) ? $event_types[$event_type] : $event_type;
                            ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" 
                                               name="event_ids[]" 
                                               value="<?php echo esc_attr($event->ID); ?>"
                                               class="event-checkbox">
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($event->post_title); ?></strong>
                                        <br>
                                        <small>ID: <?php echo $event->ID; ?></small>
                                    </td>
                                    <td><?php echo esc_html($type_label); ?></td>
                                    <td>
                                        <?php 
                                        if ($start_date) {
                                            echo esc_html(date('M j, Y', strtotime($start_date)));
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <select name="assign_to[<?php echo esc_attr($event->ID); ?>]" 
                                                class="member-select"
                                                style="width: 100%; max-width: 300px;">
                                            <option value=""><?php esc_html_e('— Select Member —', 'schedule-collaboration-tracking'); ?></option>
                                            <?php foreach ($members as $member): ?>
                                                <option value="<?php echo esc_attr($member->ID); ?>">
                                                    <?php echo esc_html($member->display_name); ?>
                                                    (<?php echo esc_html($member->user_email); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div style="margin-top: 20px;">
                        <h3><?php esc_html_e('Bulk Actions', 'schedule-collaboration-tracking'); ?></h3>
                        <p>
                            <label>
                                <?php esc_html_e('Assign all selected events to:', 'schedule-collaboration-tracking'); ?>
                                <select id="bulk-assign-member" style="margin-left: 10px;">
                                    <option value=""><?php esc_html_e('— Select Member —', 'schedule-collaboration-tracking'); ?></option>
                                    <?php foreach ($members as $member): ?>
                                        <option value="<?php echo esc_attr($member->ID); ?>">
                                            <?php echo esc_html($member->display_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button type="button" id="apply-bulk" class="button button-secondary" style="margin-left: 10px;">
                                <?php esc_html_e('Apply to Selected', 'schedule-collaboration-tracking'); ?>
                            </button>
                        </p>
                    </div>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary button-large">
                            <?php esc_html_e('Save Assignments', 'schedule-collaboration-tracking'); ?>
                        </button>
                    </p>
                </form>
                
                <script>
                jQuery(document).ready(function($) {
                    // Select all checkbox
                    $('#select-all').on('change', function() {
                        $('.event-checkbox').prop('checked', this.checked);
                    });
                    
                    // Bulk assign
                    $('#apply-bulk').on('click', function() {
                        var memberId = $('#bulk-assign-member').val();
                        if (!memberId) {
                            alert('<?php esc_js(_e('Please select a member first.', 'schedule-collaboration-tracking')); ?>');
                            return;
                        }
                        
                        $('.event-checkbox:checked').each(function() {
                            var eventId = $(this).val();
                            $('select[name="assign_to[' + eventId + ']"]').val(memberId);
                        });
                        
                        alert('<?php esc_js(_e('Bulk assignment applied! Click "Save Assignments" to save.', 'schedule-collaboration-tracking')); ?>');
                    });
                    
                    // Form validation
                    $('#ftt-migration-form').on('submit', function(e) {
                        var hasSelection = false;
                        $('.member-select').each(function() {
                            if ($(this).val()) {
                                hasSelection = true;
                                return false;
                            }
                        });
                        
                        if (!hasSelection) {
                            alert('<?php esc_js(_e('Please assign at least one event to a member.', 'schedule-collaboration-tracking')); ?>');
                            e.preventDefault();
                            return false;
                        }
                    });
                });
                </script>
                
                <style>
                .wp-list-table tbody tr:hover {
                    background: #f6f7f7;
                }
                .member-select {
                    font-size: 14px;
                    padding: 5px;
                }
                </style>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Handle migration form submission
     */
    public static function handle_migration() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.'));
        }
        
        if (!isset($_POST['ftt_migration_nonce']) || !wp_verify_nonce($_POST['ftt_migration_nonce'], 'ftt_migrate_events')) {
            wp_die(__('Security check failed.'));
        }
        
        $assigned_count = 0;
        
        if (isset($_POST['assign_to']) && is_array($_POST['assign_to'])) {
            foreach ($_POST['assign_to'] as $event_id => $member_id) {
                if (empty($member_id)) {
                    continue;
                }
                
                $event_id = intval($event_id);
                $member_id = intval($member_id);
                
                // Verify event exists
                $event = get_post($event_id);
                if (!$event || $event->post_type !== 'ftt_event') {
                    continue;
                }
                
                // Verify member exists and is a member
                if (!FTT_Roles::is_member($member_id)) {
                    continue;
                }
                
                // Update post author
                wp_update_post(array(
                    'ID' => $event_id,
                    'post_author' => $member_id,
                ));
                
                $assigned_count++;
            }
        }
        
        // Redirect back with success message
        $redirect = add_query_arg(
            array(
                'page' => 'ftt-migrate-events',
                'success' => '1',
                'count' => $assigned_count,
            ),
            admin_url('edit.php?post_type=ftt_event')
        );
        
        wp_safe_redirect($redirect);
        exit;
    }
}

// Initialize
FTT_Event_Migration::init();
