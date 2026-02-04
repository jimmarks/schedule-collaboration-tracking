<?php
/**
 * Template: Admin - Manage Users
 *
 * @package Summer_Regiment_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$all_users = get_users(array('orderby' => 'display_name'));
$members = SRT_Roles::get_all_members();
$parents = SRT_Roles::get_all_parents();
?>

<div class="wrap">
    <h1><?php esc_html_e('Manage Users', 'schedule-collaboration-tracking'); ?></h1>
    
    <?php settings_errors('srt_messages'); ?>
    
    <div class="srt-admin-tabs">
        <nav class="nav-tab-wrapper">
            <a href="#members" class="nav-tab nav-tab-active"><?php esc_html_e('Members', 'schedule-collaboration-tracking'); ?> (<?php echo count($members); ?>)</a>
            <a href="#parents" class="nav-tab"><?php esc_html_e('Parents', 'schedule-collaboration-tracking'); ?> (<?php echo count($parents); ?>)</a>
            <a href="#all-users" class="nav-tab"><?php esc_html_e('All Users', 'schedule-collaboration-tracking'); ?></a>
            <a href="#relationships" class="nav-tab"><?php esc_html_e('Manage Relationships', 'schedule-collaboration-tracking'); ?></a>
        </nav>
        
        <!-- Members Tab -->
        <div id="members" class="tab-content" style="display: block;">
            <h2><?php esc_html_e('Members', 'schedule-collaboration-tracking'); ?></h2>
            <p><?php esc_html_e('Users marked as active members who travel on events.', 'schedule-collaboration-tracking'); ?></p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('Email', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('Role', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('Parents', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('Member Since', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('Actions', 'schedule-collaboration-tracking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)) : ?>
                        <tr>
                            <td colspan="6"><?php esc_html_e('No members found. Add members by editing user profiles.', 'schedule-collaboration-tracking'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($members as $member) : ?>
                            <?php
                            $parents_list = SRT_Roles::get_parents($member->ID);
                            $parent_names = array();
                            foreach ($parents_list as $parent_id) {
                                $parent = get_user_by('id', $parent_id);
                                if ($parent) {
                                    $parent_names[] = $parent->display_name;
                                }
                            }
                            $member_since = get_user_meta($member->ID, 'srt_member_since', true);
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($member->display_name); ?></strong></td>
                                <td><?php echo esc_html($member->user_email); ?></td>
                                <td><?php echo esc_html(implode(', ', $member->roles)); ?></td>
                                <td><?php echo $parent_names ? esc_html(implode(', ', $parent_names)) : '—'; ?></td>
                                <td><?php echo $member_since ? esc_html(date('M j, Y', strtotime($member_since))) : '—'; ?></td>
                                <td>
                                    <a href="<?php echo esc_url(get_edit_user_link($member->ID)); ?>" class="button button-small"><?php esc_html_e('Edit', 'schedule-collaboration-tracking'); ?></a>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('srt_manage_users'); ?>
                                        <input type="hidden" name="srt_action" value="remove_member">
                                        <input type="hidden" name="user_id" value="<?php echo esc_attr($member->ID); ?>">
                                        <button type="submit" class="button button-small" onclick="return confirm('Remove member status?');"><?php esc_html_e('Remove Member', 'schedule-collaboration-tracking'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Parents Tab -->
        <div id="parents" class="tab-content" style="display: none;">
            <h2><?php esc_html_e('Parents & Guardians', 'schedule-collaboration-tracking'); ?></h2>
            <p><?php esc_html_e('Users who are parents/guardians of members and receive alerts.', 'schedule-collaboration-tracking'); ?></p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('Email', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('Role', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('Children', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('Actions', 'schedule-collaboration-tracking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($parents)) : ?>
                        <tr>
                            <td colspan="5"><?php esc_html_e('No parents found. Add parent relationships by editing user profiles.', 'schedule-collaboration-tracking'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($parents as $parent) : ?>
                            <?php
                            $children_list = SRT_Roles::get_children($parent->ID);
                            $children_names = array();
                            foreach ($children_list as $child_id) {
                                $child = get_user_by('id', $child_id);
                                if ($child) {
                                    $children_names[] = $child->display_name;
                                }
                            }
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($parent->display_name); ?></strong></td>
                                <td><?php echo esc_html($parent->user_email); ?></td>
                                <td><?php echo esc_html(implode(', ', $parent->roles)); ?></td>
                                <td><?php echo $children_names ? esc_html(implode(', ', $children_names)) : '—'; ?></td>
                                <td>
                                    <a href="<?php echo esc_url(get_edit_user_link($parent->ID)); ?>" class="button button-small"><?php esc_html_e('Edit', 'schedule-collaboration-tracking'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- All Users Tab -->
        <div id="all-users" class="tab-content" style="display: none;">
            <h2><?php esc_html_e('All Users', 'schedule-collaboration-tracking'); ?></h2>
            <p><?php esc_html_e('Quick actions for all WordPress users.', 'schedule-collaboration-tracking'); ?></p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('Email', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('Role', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('Member?', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('Parent?', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('Actions', 'schedule-collaboration-tracking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_users as $user) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($user->display_name); ?></strong></td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td><?php echo esc_html(implode(', ', $user->roles)); ?></td>
                            <td><?php echo SRT_Roles::is_member($user->ID) ? '✓' : '—'; ?></td>
                            <td><?php echo SRT_Roles::is_parent($user->ID) ? '✓' : '—'; ?></td>
                            <td>
                                <a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>" class="button button-small"><?php esc_html_e('Edit', 'schedule-collaboration-tracking'); ?></a>
                                <?php if (!SRT_Roles::is_member($user->ID)) : ?>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('srt_manage_users'); ?>
                                        <input type="hidden" name="srt_action" value="make_member">
                                        <input type="hidden" name="user_id" value="<?php echo esc_attr($user->ID); ?>">
                                        <button type="submit" class="button button-small button-primary"><?php esc_html_e('Make Member', 'schedule-collaboration-tracking'); ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Relationships Tab -->
        <div id="relationships" class="tab-content" style="display: none;">
            <h2><?php esc_html_e('Manage Parent/Child Relationships', 'schedule-collaboration-tracking'); ?></h2>
            <p><?php esc_html_e('Add or remove parent/guardian relationships.', 'schedule-collaboration-tracking'); ?></p>
            
            <div class="card">
                <h3><?php esc_html_e('Add Parent Relationship', 'schedule-collaboration-tracking'); ?></h3>
                <form method="post">
                    <?php wp_nonce_field('srt_manage_users'); ?>
                    <input type="hidden" name="srt_action" value="add_parent">
                    <table class="form-table">
                        <tr>
                            <th><label for="parent_id"><?php esc_html_e('Parent/Guardian', 'schedule-collaboration-tracking'); ?></label></th>
                            <td>
                                <select name="parent_id" id="parent_id" required>
                                    <option value=""><?php esc_html_e('Select parent...', 'schedule-collaboration-tracking'); ?></option>
                                    <?php foreach ($all_users as $user) : ?>
                                        <option value="<?php echo esc_attr($user->ID); ?>"><?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="child_id"><?php esc_html_e('Child (Member)', 'schedule-collaboration-tracking'); ?></label></th>
                            <td>
                                <select name="child_id" id="child_id" required>
                                    <option value=""><?php esc_html_e('Select member...', 'schedule-collaboration-tracking'); ?></option>
                                    <?php foreach ($all_users as $user) : ?>
                                        <option value="<?php echo esc_attr($user->ID); ?>"><?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Add Relationship', 'schedule-collaboration-tracking'); ?></button>
                    </p>
                </form>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h3><?php esc_html_e('Current Relationships', 'schedule-collaboration-tracking'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Parent', 'schedule-collaboration-tracking'); ?></th>
                            <th><?php esc_html_e('Child', 'schedule-collaboration-tracking'); ?></th>
                            <th><?php esc_html_e('Actions', 'schedule-collaboration-tracking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $relationships = array();
                        foreach ($all_users as $user) {
                            $children = SRT_Roles::get_children($user->ID);
                            foreach ($children as $child_id) {
                                $child = get_user_by('id', $child_id);
                                if ($child) {
                                    $relationships[] = array(
                                        'parent' => $user,
                                        'child' => $child
                                    );
                                }
                            }
                        }
                        
                        if (empty($relationships)) :
                        ?>
                            <tr>
                                <td colspan="3"><?php esc_html_e('No relationships found.', 'schedule-collaboration-tracking'); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($relationships as $rel) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html($rel['parent']->display_name); ?></strong> (<?php echo esc_html($rel['parent']->user_email); ?>)</td>
                                    <td><strong><?php echo esc_html($rel['child']->display_name); ?></strong> (<?php echo esc_html($rel['child']->user_email); ?>)</td>
                                    <td>
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field('srt_manage_users'); ?>
                                            <input type="hidden" name="srt_action" value="remove_parent">
                                            <input type="hidden" name="parent_id" value="<?php echo esc_attr($rel['parent']->ID); ?>">
                                            <input type="hidden" name="child_id" value="<?php echo esc_attr($rel['child']->ID); ?>">
                                            <button type="submit" class="button button-small" onclick="return confirm('Remove this relationship?');"><?php esc_html_e('Remove', 'schedule-collaboration-tracking'); ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.srt-admin-tabs .tab-content {
    margin-top: 20px;
}
.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.card h3 {
    margin-top: 0;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.tab-content').hide();
        $(target).show();
    });
});
</script>
