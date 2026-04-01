<?php
/**
 * Migration Script: v2.0 to v2.1 Family Groups
 *
 * Converts existing parent-child relationships (user meta) to family group structure.
 *
 * @package FamilyTravelTracker
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTT_Groups_Migration {
    
    /**
     * Run the complete migration
     *
     * @return array Migration report
     */
    public static function run_migration() {
        global $wpdb;
        
        $report = [
            'started_at' => current_time('mysql'),
            'groups_created' => 0,
            'members_migrated' => 0,
            'events_migrated' => 0,
            'errors' => [],
            'warnings' => [],
        ];
        
        // Step 1: Create groups from linked adults
        $groups_result = self::migrate_linked_adults();
        $report['groups_created'] = $groups_result['count'];
        $report['group_map'] = $groups_result['map'];
        $report['errors'] = array_merge($report['errors'], $groups_result['errors']);
        
        // Step 2: Migrate children into groups
        $children_result = self::migrate_children_to_groups($groups_result['map']);
        $report['members_migrated'] = $children_result['count'];
        $report['errors'] = array_merge($report['errors'], $children_result['errors']);
        
        // Step 3: Migrate billing data
        $billing_result = self::migrate_billing_data($groups_result['map']);
        $report['warnings'] = array_merge($report['warnings'], $billing_result['warnings']);
        
        // Step 4: Migrate events to groups
        $events_result = self::migrate_events_to_groups($groups_result['map']);
        $report['events_migrated'] = $events_result['count'];
        $report['errors'] = array_merge($report['errors'], $events_result['errors']);
        
        // Step 5: Validate migration
        $validation = self::validate_migration();
        $report['validation'] = $validation;
        
        $report['completed_at'] = current_time('mysql');
        $report['success'] = empty($report['errors']);
        
        // Store migration report
        update_option('ftt_groups_migration_report', $report);
        update_option('ftt_groups_migration_complete', true);
        delete_option('ftt_groups_migration_pending');
        
        return $report;
    }
    
    /**
     * Create groups from existing linked adults
     *
     * @return array Result with group map and count
     */
    private static function migrate_linked_adults() {
        global $wpdb;
        
        $result = [
            'count' => 0,
            'map' => [], // Maps user IDs to group IDs
            'errors' => [],
        ];
        
        // Get all users with ftt_parents meta (linked adults)
        $adults_with_links = $wpdb->get_results(
            "SELECT user_id, meta_value 
             FROM {$wpdb->usermeta} 
             WHERE meta_key = 'ftt_parents'"
        );
        
        $processed_groups = [];
        
        foreach ($adults_with_links as $row) {
            $user_id = $row->user_id;
            $linked_adults = maybe_unserialize($row->meta_value);
            
            if (!is_array($linked_adults)) {
                $linked_adults = [];
            }
            
            // Create a unique group for this set of adults
            $group_members = array_merge([$user_id], $linked_adults);
            sort($group_members);
            $group_key = implode('-', $group_members);
            
            if (!isset($processed_groups[$group_key])) {
                // Generate group name from first adult
                $first_user = get_user_by('ID', $user_id);
                $group_name = self::generate_group_name($first_user, $group_members);
                
                // Create group
                $group_id = FTT_Family_Groups::create_group([
                    'name' => $group_name,
                    'description' => 'Migrated from v2.0',
                    'billing_owner' => $user_id,
                    'created_by' => $user_id,
                    'color' => self::generate_group_color($result['count']),
                ]);
                
                if (is_wp_error($group_id)) {
                    $result['errors'][] = "Failed to create group for user {$user_id}: " . $group_id->get_error_message();
                    continue;
                }
                
                $processed_groups[$group_key] = $group_id;
                $result['count']++;
                
                // Add all adults as members
                foreach ($group_members as $member_id) {
                    if ($member_id == $user_id) {
                        // Creator already added in create_group
                        $result['map'][$member_id] = $group_id;
                        continue;
                    }
                    
                    // Add other co-parents
                    $member_result = FTT_Family_Groups::add_member($group_id, $member_id, 'parent', [
                        'relationship' => 'Co-parent',
                        'can_manage_group' => true,
                        'added_by' => $user_id,
                    ]);
                    
                    if (!is_wp_error($member_result)) {
                        $result['map'][$member_id] = $group_id;
                    }
                }
                
                // Migrate Stripe subscription data to group
                self::copy_stripe_data_to_group($user_id, $group_id);
            } else {
                // Group already exists for this set of adults
                $result['map'][$user_id] = $processed_groups[$group_key];
            }
        }
        
        // Handle users with no linked adults (solo parents)
        $solo_parents = $wpdb->get_results(
            "SELECT DISTINCT u.ID, u.display_name, u.user_email
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} pm ON u.ID = pm.user_id
             WHERE pm.meta_key = 'ftt_parent_of'
             AND u.ID NOT IN (
                 SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'ftt_parents'
             )"
        );
        
        foreach ($solo_parents as $parent) {
            $group_id = FTT_Family_Groups::create_group([
                'name' => $parent->display_name . "'s Family",
                'description' => 'Migrated from v2.0',
                'billing_owner' => $parent->ID,
                'created_by' => $parent->ID,
                'color' => self::generate_group_color($result['count']),
            ]);
            
            if (!is_wp_error($group_id)) {
                $result['map'][$parent->ID] = $group_id;
                $result['count']++;
                self::copy_stripe_data_to_group($parent->ID, $group_id);
            }
        }
        
        return $result;
    }
    
    /**
     * Migrate children into their parents' groups
     *
     * @param array $group_map Mapping of parent IDs to group IDs
     * @return array Result with count
     */
    private static function migrate_children_to_groups($group_map) {
        global $wpdb;
        
        $result = [
            'count' => 0,
            'errors' => [],
        ];
        
        // Get all parent-child relationships
        $parent_children = $wpdb->get_results(
            "SELECT user_id as parent_id, meta_value 
             FROM {$wpdb->usermeta} 
             WHERE meta_key = 'ftt_parent_of'"
        );
        
        $processed_children = []; // Track which children we've added to which groups
        
        foreach ($parent_children as $row) {
            $parent_id = $row->parent_id;
            $child_ids = maybe_unserialize($row->meta_value);
            
            if (!is_array($child_ids)) {
                continue;
            }
            
            // Get this parent's group
            $group_id = $group_map[$parent_id] ?? null;
            
            if (!$group_id) {
                $result['errors'][] = "No group found for parent {$parent_id}";
                continue;
            }
            
            // Add each child to the group
            foreach ($child_ids as $child_id) {
                // Check if child already added to this group
                $key = "{$group_id}-{$child_id}";
                if (isset($processed_children[$key])) {
                    continue;
                }
                
                // Get child color
                $child_color = get_user_meta($child_id, 'ftt_child_color', true);
                
                $member_result = FTT_Family_Groups::add_member($group_id, $child_id, 'child', [
                    'relationship' => 'Child',
                    'can_manage_group' => false,
                    'added_by' => $parent_id,
                ]);
                
                if (!is_wp_error($member_result)) {
                    $result['count']++;
                    $processed_children[$key] = true;
                } else {
                    // Check if error is just "already a member"
                    if ($member_result->get_error_code() !== 'already_member') {
                        $result['errors'][] = "Failed to add child {$child_id} to group {$group_id}: " . $member_result->get_error_message();
                    } else {
                        $processed_children[$key] = true;
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Copy Stripe billing data from user meta to group
     *
     * @param int $user_id
     * @param int $group_id
     * @return bool Success
     */
    private static function copy_stripe_data_to_group($user_id, $group_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . FTT_Family_Groups::TABLE_GROUPS;
        
        $stripe_data = [
            'stripe_customer_id' => get_user_meta($user_id, 'ftt_stripe_customer_id', true),
            'stripe_subscription_id' => get_user_meta($user_id, 'ftt_stripe_subscription_id', true),
            'subscription_status' => get_user_meta($user_id, 'ftt_subscription_status', true),
            'subscription_interval' => get_user_meta($user_id, 'ftt_subscription_interval', true),
        ];
        
        // Only update if we have stripe data
        if ($stripe_data['stripe_customer_id'] || $stripe_data['stripe_subscription_id']) {
            $wpdb->update(
                $table,
                $stripe_data,
                ['id' => $group_id],
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Migrate billing data to groups
     *
     * @param array $group_map
     * @return array Result with warnings
     */
    private static function migrate_billing_data($group_map) {
        $result = [
            'warnings' => [],
        ];
        
        // Billing data is copied during group creation
        // This step is mainly for validation
        
        foreach ($group_map as $user_id => $group_id) {
            $has_stripe = get_user_meta($user_id, 'ftt_stripe_customer_id', true);
            if ($has_stripe) {
                $group = FTT_Family_Groups::get_group($group_id);
                if (!$group || !$group->stripe_customer_id) {
                    $result['warnings'][] = "Billing data may not have migrated for user {$user_id}, group {$group_id}";
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Migrate events to groups
     *
     * @param array $group_map Mapping of parent IDs to group IDs
     * @return array Result with count
     */
    private static function migrate_events_to_groups($group_map) {
        global $wpdb;
        
        $result = [
            'count' => 0,
            'errors' => [],
        ];
        
        // Get all events
        $events = get_posts([
            'post_type' => 'ftt_event',
            'posts_per_page' => -1,
            'post_status' => 'any',
        ]);
        
        foreach ($events as $event) {
            // Get the member this event belongs to
            $member_id = get_post_meta($event->ID, 'member_id', true);
            
            if (!$member_id) {
                continue;
            }
            
            // Find which group(s) this member belongs to
            $member_groups = self::find_member_groups($member_id, $group_map);
            
            if (empty($member_groups)) {
                $result['errors'][] = "No groups found for event {$event->ID} (member {$member_id})";
                continue;
            }
            
            // Add event to all relevant groups (child might be in multiple groups)
            foreach ($member_groups as $group_id) {
                $added = FTT_Family_Groups::add_event_to_group($event->ID, $group_id);
                if ($added) {
                    $result['count']++;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Find which groups a member belongs to
     *
     * @param int $member_id
     * @param array $group_map Parent to group mapping
     * @return array Array of group IDs
     */
    private static function find_member_groups($member_id, $group_map) {
        global $wpdb;
        
        $groups = [];
        
        // Check if member is directly in a group (parent)
        if (isset($group_map[$member_id])) {
            $groups[] = $group_map[$member_id];
        }
        
        // Check if member is a child - find their parents
        $parents = get_user_meta($member_id, 'ftt_parents', true);
        if (is_array($parents)) {
            foreach ($parents as $parent_id) {
                if (isset($group_map[$parent_id])) {
                    $groups[] = $group_map[$parent_id];
                }
            }
        }
        
        return array_unique($groups);
    }
    
    /**
     * Validate migration results
     *
     * @return array Validation report
     */
    private static function validate_migration() {
        global $wpdb;
        
        $validation = [
            'total_groups' => 0,
            'total_members' => 0,
            'orphaned_children' => 0,
            'orphaned_events' => 0,
        ];
        
        // Count groups
        $table_groups = $wpdb->prefix . FTT_Family_Groups::TABLE_GROUPS;
        $validation['total_groups'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_groups} WHERE is_archived = 0");
        
        // Count members
        $table_members = $wpdb->prefix . FTT_Family_Groups::TABLE_MEMBERS;
        $validation['total_members'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_members}");
        
        // Check for orphaned children (children not in any group)
        $all_children = get_users(['meta_key' => 'ftt_is_member', 'meta_value' => 'child']);
        foreach ($all_children as $child) {
            $groups = FTT_Family_Groups::get_user_groups($child->ID);
            if (empty($groups)) {
                $validation['orphaned_children']++;
            }
        }
        
        // Check for orphaned events (events not in any group)
        $all_events = get_posts(['post_type' => 'ftt_event', 'posts_per_page' => -1]);
        foreach ($all_events as $event) {
            $groups = FTT_Family_Groups::get_event_groups($event->ID);
            if (empty($groups)) {
                $validation['orphaned_events']++;
            }
        }
        
        return $validation;
    }
    
    /**
     * Generate a group name from user info
     *
     * @param WP_User $user
     * @param array $member_ids
     * @return string
     */
    private static function generate_group_name($user, $member_ids) {
        if (count($member_ids) == 1) {
            return $user->display_name . "'s Family";
        }
        
        // Get last name from display name
        $names = explode(' ', $user->display_name);
        $last_name = end($names);
        
        return $last_name . " Family";
    }
    
    /**
     * Generate a color for a group
     *
     * @param int $index
     * @return string Hex color
     */
    private static function generate_group_color($index) {
        $colors = [
            '#2196F3', // Blue
            '#4CAF50', // Green
            '#FF9800', // Orange
            '#9C27B0', // Purple
            '#F44336', // Red
            '#00BCD4', // Cyan
            '#FFEB3B', // Yellow
            '#795548', // Brown
            '#607D8B', // Blue Grey
            '#E91E63', // Pink
        ];
        
        return $colors[$index % count($colors)];
    }
    
    /**
     * Rollback migration (emergency use only)
     */
    public static function rollback_migration() {
        global $wpdb;
        
        // Delete all group data
        $table_event_groups = $wpdb->prefix . FTT_Family_Groups::TABLE_EVENT_GROUPS;
        $table_invitations = $wpdb->prefix . FTT_Family_Groups::TABLE_INVITATIONS;
        $table_members = $wpdb->prefix . FTT_Family_Groups::TABLE_MEMBERS;
        $table_groups = $wpdb->prefix . FTT_Family_Groups::TABLE_GROUPS;
        
        $wpdb->query("DELETE FROM {$table_event_groups}");
        $wpdb->query("DELETE FROM {$table_invitations}");
        $wpdb->query("DELETE FROM {$table_members}");
        $wpdb->query("DELETE FROM {$table_groups}");
        
        // Mark as rolled back
        delete_option('ftt_groups_migration_complete');
        update_option('ftt_groups_migration_pending', true);
        update_option('ftt_groups_migration_rolled_back', current_time('mysql'));
        
        return true;
    }
}
