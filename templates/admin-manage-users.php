<?php
/**
 * Template: Admin - Manage Users
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$all_users = get_users(array('orderby' => 'display_name'));
$members = FTT_Roles::get_all_members();
$adults  = FTT_Roles::get_all_adults();
?>

<div class="wrap">
    <h1><?php esc_html_e('Manage Users', 'schedule-collaboration-tracking'); ?></h1>
    
    <?php settings_errors('ftt_messages'); ?>
    
    <div class="ftt-admin-tabs">
        <nav class="nav-tab-wrapper">
            <a href="#members" class="nav-tab nav-tab-active"><?php esc_html_e('Children', 'schedule-collaboration-tracking'); ?> (<?php echo count($members); ?>)</a>
            <a href="#adults" class="nav-tab"><?php esc_html_e('Adults', 'schedule-collaboration-tracking'); ?> (<?php echo count($adults); ?>)</a>
            <a href="#all-users" class="nav-tab"><?php esc_html_e('All Users', 'schedule-collaboration-tracking'); ?></a>
            <a href="#relationships" class="nav-tab"><?php esc_html_e('Manage Relationships', 'schedule-collaboration-tracking'); ?></a>
            <a href="#billing-overrides" class="nav-tab"><?php esc_html_e('Billing Overrides', 'schedule-collaboration-tracking'); ?></a>
        </nav>
        
        <!-- Members Tab -->
        <div id="members" class="tab-content" style="display: block;">
            <h2><?php esc_html_e('Children', 'schedule-collaboration-tracking'); ?></h2>
            <p><?php esc_html_e('Children registered in the system who have activities and travel schedules.', 'schedule-collaboration-tracking'); ?></p>
            
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
                            <td colspan="6"><?php esc_html_e('No children found. Add children by editing user profiles.', 'schedule-collaboration-tracking'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($members as $member) : ?>
                            <?php
                            $parents_list = FTT_Roles::get_parents($member->ID);
                            $parent_names = array();
                            foreach ($parents_list as $parent_id) {
                                $parent = get_user_by('id', $parent_id);
                                if ($parent) {
                                    $parent_names[] = $parent->display_name;
                                }
                            }
                            $member_since = get_user_meta($member->ID, 'ftt_member_since', true);
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
                                        <?php wp_nonce_field('ftt_manage_users'); ?>
                                        <input type="hidden" name="ftt_action" value="remove_member">
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
        
        <!-- Adults Tab -->
        <div id="adults" class="tab-content" style="display: none;">
            <h2><?php esc_html_e('Adults', 'schedule-collaboration-tracking'); ?></h2>
            <p><?php esc_html_e('Adult accounts registered in the system. Adults can manage children, book travel, and receive alerts.', 'schedule-collaboration-tracking'); ?></p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('Email', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('WP Role', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('Children Linked', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('Member Since', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('Actions', 'schedule-collaboration-tracking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($adults)) : ?>
                        <tr>
                            <td colspan="6"><?php esc_html_e('No adult accounts found.', 'schedule-collaboration-tracking'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($adults as $adult) : ?>
                            <?php
                            $children_list  = FTT_Roles::get_children($adult->ID);
                            $children_names = array();
                            foreach ($children_list as $child_id) {
                                $child = get_user_by('id', $child_id);
                                if ($child) {
                                    $children_names[] = $child->display_name;
                                }
                            }
                            $adult_since = get_user_meta($adult->ID, 'ftt_member_since', true);
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($adult->display_name); ?></strong></td>
                                <td><?php echo esc_html($adult->user_email); ?></td>
                                <td><?php echo esc_html(implode(', ', $adult->roles)); ?></td>
                                <td><?php echo $children_names ? esc_html(implode(', ', $children_names)) : '—'; ?></td>
                                <td><?php echo $adult_since ? esc_html(date('M j, Y', strtotime($adult_since))) : '—'; ?></td>
                                <td>
                                    <a href="<?php echo esc_url(get_edit_user_link($adult->ID)); ?>" class="button button-small"><?php esc_html_e('Edit', 'schedule-collaboration-tracking'); ?></a>
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
                        <th><?php esc_html_e('Child?', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('Adult?', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('Actions', 'schedule-collaboration-tracking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_users as $user) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($user->display_name); ?></strong></td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td><?php echo esc_html(implode(', ', $user->roles)); ?></td>
                            <td><?php echo FTT_Roles::is_member($user->ID) ? '✓' : '—'; ?></td>
                            <td><?php echo FTT_Roles::is_adult($user->ID) ? '✓' : '—'; ?></td>
                            <td>
                                <a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>" class="button button-small"><?php esc_html_e('Edit', 'schedule-collaboration-tracking'); ?></a>
                                <?php if (!FTT_Roles::is_member($user->ID)) : ?>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('ftt_manage_users'); ?>
                                        <input type="hidden" name="ftt_action" value="make_member">
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
            <h2><?php esc_html_e('Manage Family Relationships', 'schedule-collaboration-tracking'); ?></h2>
            <p><?php esc_html_e('Link adult accounts to children or remove existing links.', 'schedule-collaboration-tracking'); ?></p>
            
            <div class="card">
                <h3><?php esc_html_e('Link Adult to Child', 'schedule-collaboration-tracking'); ?></h3>
                <form method="post">
                    <?php wp_nonce_field('ftt_manage_users'); ?>
                    <input type="hidden" name="ftt_action" value="add_parent">
                    <table class="form-table">
                        <tr>
                            <th><label for="parent_id"><?php esc_html_e('Adult / Guardian', 'schedule-collaboration-tracking'); ?></label></th>
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
                            <th><label for="child_id"><?php esc_html_e('Child', 'schedule-collaboration-tracking'); ?></label></th>
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
                            <th><?php esc_html_e('Adult', 'schedule-collaboration-tracking'); ?></th>
                            <th><?php esc_html_e('Child', 'schedule-collaboration-tracking'); ?></th>
                            <th><?php esc_html_e('Actions', 'schedule-collaboration-tracking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $relationships = array();
                        foreach ($all_users as $user) {
                            $children = FTT_Roles::get_children($user->ID);
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
                                            <?php wp_nonce_field('ftt_manage_users'); ?>
                                            <input type="hidden" name="ftt_action" value="remove_parent">
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

        <!-- Billing Overrides Tab -->
        <div id="billing-overrides" class="tab-content" style="display: none;">
            <h2><?php esc_html_e('Billing Overrides', 'schedule-collaboration-tracking'); ?></h2>
            <p><?php esc_html_e('Grant or remove billing exemptions. Exempt users and groups bypass subscription checks entirely — no Stripe subscription required.', 'schedule-collaboration-tracking'); ?></p>

            <!-- Per-user exemptions -->
            <h3><?php esc_html_e('User Exemptions', 'schedule-collaboration-tracking'); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('Email', 'schedule-collaboration-tracking'); ?></th>
                        <th style="width:140px;"><?php esc_html_e('Billing Status', 'schedule-collaboration-tracking'); ?></th>
                        <th style="width:160px;"><?php esc_html_e('Action', 'schedule-collaboration-tracking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_users as $user) :
                        // Skip site admins — they're always exempt
                        if (user_can($user->ID, 'manage_options')) {
                            continue;
                        }
                        $is_exempt = (bool) get_user_meta($user->ID, 'ftt_billing_exempt', true);
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($user->display_name); ?></strong></td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td>
                                <?php if ($is_exempt) : ?>
                                    <span style="color:#1d6f42;font-weight:600;">&#10003; <?php esc_html_e('Exempt', 'schedule-collaboration-tracking'); ?></span>
                                <?php else : ?>
                                    <span style="color:#666;">&#8212; <?php esc_html_e('Normal', 'schedule-collaboration-tracking'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('ftt_manage_users'); ?>
                                    <input type="hidden" name="ftt_action" value="toggle_billing_exempt_user">
                                    <input type="hidden" name="user_id" value="<?php echo esc_attr($user->ID); ?>">
                                    <input type="hidden" name="exempt_value" value="<?php echo $is_exempt ? '0' : '1'; ?>">
                                    <?php if ($is_exempt) : ?>
                                        <button type="submit" class="button button-small"
                                            onclick="return confirm('<?php esc_attr_e('Remove billing exemption for this user?', 'schedule-collaboration-tracking'); ?>');">
                                            <?php esc_html_e('Remove Exemption', 'schedule-collaboration-tracking'); ?>
                                        </button>
                                    <?php else : ?>
                                        <button type="submit" class="button button-small button-primary">
                                            <?php esc_html_e('Grant Exemption', 'schedule-collaboration-tracking'); ?>
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (class_exists('FTT_Family_Groups')) :
                $all_groups   = FTT_Family_Groups::get_all_groups();
                $exempt_group_ids = array_map('intval', (array) get_option('ftt_billing_exempt_groups', []));
            ?>
            <!-- Per-group exemptions -->
            <h3 style="margin-top:30px;"><?php esc_html_e('Group Exemptions', 'schedule-collaboration-tracking'); ?></h3>
            <p><?php esc_html_e('All members of an exempt group bypass billing, regardless of their individual subscription status.', 'schedule-collaboration-tracking'); ?></p>

            <?php if (empty($all_groups)) : ?>
                <p><?php esc_html_e('No groups found.', 'schedule-collaboration-tracking'); ?></p>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Group Name', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('Members', 'schedule-collaboration-tracking'); ?></th>
                        <th><?php esc_html_e('Subscription Status', 'schedule-collaboration-tracking'); ?></th>
                        <th style="width:140px;"><?php esc_html_e('Billing Override', 'schedule-collaboration-tracking'); ?></th>
                        <th style="width:160px;"><?php esc_html_e('Action', 'schedule-collaboration-tracking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_groups as $group) :
                        $is_group_exempt  = in_array((int) $group->id, $exempt_group_ids, true);
                        $member_count = FTT_Family_Groups::get_member_count($group->id);
                        $status_label = $group->subscription_status ?: __('None', 'schedule-collaboration-tracking');
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($group->name); ?></strong></td>
                            <td><?php echo (int) $member_count; ?></td>
                            <td><?php echo esc_html($status_label); ?></td>
                            <td>
                                <?php if ($is_group_exempt) : ?>
                                    <span style="color:#1d6f42;font-weight:600;">&#10003; <?php esc_html_e('Exempt', 'schedule-collaboration-tracking'); ?></span>
                                <?php else : ?>
                                    <span style="color:#666;">&#8212; <?php esc_html_e('Normal', 'schedule-collaboration-tracking'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('ftt_manage_users'); ?>
                                    <input type="hidden" name="ftt_action" value="toggle_billing_exempt_group">
                                    <input type="hidden" name="group_id" value="<?php echo esc_attr($group->id); ?>">
                                    <input type="hidden" name="exempt_value" value="<?php echo $is_group_exempt ? '0' : '1'; ?>">
                                    <?php if ($is_group_exempt) : ?>
                                        <button type="submit" class="button button-small"
                                            onclick="return confirm('<?php esc_attr_e('Remove billing exemption for this group?', 'schedule-collaboration-tracking'); ?>');">
                                            <?php esc_html_e('Remove Exemption', 'schedule-collaboration-tracking'); ?>
                                        </button>
                                    <?php else : ?>
                                        <button type="submit" class="button button-small button-primary">
                                            <?php esc_html_e('Grant Exemption', 'schedule-collaboration-tracking'); ?>
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; // empty $all_groups ?>
            <?php endif; // class_exists FTT_Family_Groups ?>
        </div>

    </div>
</div>

<style>
.ftt-admin-tabs .tab-content {
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
    // Tab switching — scoped to .ftt-admin-tabs and only anchor-style hrefs
    // so that the outer URL-based "Users & Groups" nav tabs are not intercepted.
    $('.ftt-admin-tabs .nav-tab[href^="#"]').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        var $container = $(this).closest('.ftt-admin-tabs');

        $container.find('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $container.find('.tab-content').hide();
        $container.find(target).show();
    });
});
</script>
