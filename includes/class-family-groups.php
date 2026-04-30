<?php
/**
 * Family Groups Core Class
 *
 * Manages family group entities, membership, and relationships.
 * Replaces simple user-to-user parent linking with proper group structure.
 *
 * @package FamilyTravelTracker
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTT_Family_Groups {
    
    /**
     * Database table names (without prefix)
     */
    const TABLE_GROUPS = 'ftt_family_groups';
    const TABLE_MEMBERS = 'ftt_group_members';
    const TABLE_INVITATIONS = 'ftt_group_invitations';
    const TABLE_EVENT_GROUPS = 'ftt_event_groups';
    
    /**
     * Initialize hooks
     */
    public static function init() {
        // Register activation hook for table creation
        register_activation_hook(FTT_PLUGIN_FILE, [__CLASS__, 'create_tables']);
        
        // REST API endpoints
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
        
        // Clean up group memberships when a user is deleted
        add_action('delete_user', [__CLASS__, 'cleanup_deleted_user_memberships']);
    }
    
    /**
     * Create database tables for family groups
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Family Groups table
        $table_groups = $wpdb->prefix . self::TABLE_GROUPS;
        $sql_groups = "CREATE TABLE IF NOT EXISTS {$table_groups} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME,
            billing_owner BIGINT UNSIGNED NOT NULL,
            stripe_customer_id VARCHAR(255),
            stripe_subscription_id VARCHAR(255),
            subscription_status VARCHAR(50),
            subscription_interval VARCHAR(20),
            next_billing_date DATETIME,
            trial_ends_at DATETIME,
            color VARCHAR(7),
            group_token VARCHAR(16) UNIQUE,
            is_archived BOOLEAN DEFAULT 0,
            INDEX idx_billing_owner (billing_owner),
            INDEX idx_created_by (created_by),
            INDEX idx_status (subscription_status),
            INDEX idx_token (group_token)
        ) $charset_collate;";
        
        // Group Members table
        $table_members = $wpdb->prefix . self::TABLE_MEMBERS;
        $sql_members = "CREATE TABLE IF NOT EXISTS {$table_members} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            group_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            role ENUM('parent', 'child') NOT NULL,
            added_by BIGINT UNSIGNED NOT NULL,
            added_at DATETIME NOT NULL,
            relationship VARCHAR(100),
            can_manage_group BOOLEAN DEFAULT 0,
            UNIQUE KEY unique_membership (group_id, user_id),
            INDEX idx_group (group_id),
            INDEX idx_user (user_id),
            INDEX idx_role (role),
            FOREIGN KEY (group_id) REFERENCES {$table_groups}(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Group Invitations table
        $table_invitations = $wpdb->prefix . self::TABLE_INVITATIONS;
        $sql_invitations = "CREATE TABLE IF NOT EXISTS {$table_invitations} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            group_id BIGINT UNSIGNED NOT NULL,
            invited_by BIGINT UNSIGNED NOT NULL,
            invite_code VARCHAR(12) UNIQUE NOT NULL,
            email VARCHAR(255) NOT NULL,
            relationship VARCHAR(100),
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            status ENUM('pending', 'accepted', 'expired', 'revoked') DEFAULT 'pending',
            accepted_by BIGINT UNSIGNED,
            accepted_at DATETIME,
            INDEX idx_code (invite_code),
            INDEX idx_status (status),
            INDEX idx_group (group_id),
            FOREIGN KEY (group_id) REFERENCES {$table_groups}(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Event-Group Association table
        $table_event_groups = $wpdb->prefix . self::TABLE_EVENT_GROUPS;
        $sql_event_groups = "CREATE TABLE IF NOT EXISTS {$table_event_groups} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            post_id BIGINT UNSIGNED NOT NULL,
            group_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY unique_event_group (post_id, group_id),
            INDEX idx_post (post_id),
            INDEX idx_group (group_id),
            FOREIGN KEY (group_id) REFERENCES {$table_groups}(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Execute table creation
        dbDelta($sql_groups);
        dbDelta($sql_members);
        dbDelta($sql_invitations);
        dbDelta($sql_event_groups);
        
        // Check if migration is needed
        if (self::needs_migration()) {
            // Set flag for admin notice
            update_option('ftt_groups_migration_pending', true);
        }
    }
    
    /**
     * Check if migration from old structure is needed
     */
    public static function needs_migration() {
        global $wpdb;
        
        // Check if there are any users with old ftt_parents meta
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'ftt_parents'"
        );
        
        return $count > 0;
    }
    
    /**
     * Create a new family group
     *
     * @param array $args Group data
     * @return int|WP_Error Group ID or error
     */
    public static function create_group($args) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('unauthorized', 'Must be logged in to create a group');
        }
        
        $defaults = [
            'name' => '',
            'description' => '',
            'color' => '#2196F3',
            'billing_owner' => $user_id,
            'created_by' => $user_id,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Validate required fields
        if (empty($args['name'])) {
            return new WP_Error('missing_name', 'Group name is required');
        }
        
        $table = $wpdb->prefix . self::TABLE_GROUPS;
        
        $data = [
            'name' => sanitize_text_field($args['name']),
            'description' => sanitize_textarea_field($args['description']),
            'created_by' => absint($args['created_by']),
            'created_at' => current_time('mysql'),
            'billing_owner' => absint($args['billing_owner']),
            'color' => sanitize_hex_color($args['color']),
        ];
        
        $inserted = $wpdb->insert($table, $data);
        
        if ($inserted === false) {
            return new WP_Error('db_error', 'Failed to create group');
        }
        
        $group_id = $wpdb->insert_id;
        
        // Add creator as first member with management privileges
        self::add_member($group_id, $user_id, 'parent', [
            'relationship' => 'Parent',
            'can_manage_group' => true,
            'added_by' => $user_id,
        ]);
        
        // Set as user's primary group if they don't have one
        if (!get_user_meta($user_id, 'ftt_primary_group', true)) {
            update_user_meta($user_id, 'ftt_primary_group', $group_id);
        }
        
        return $group_id;
    }
    
    /**
     * Get a group by ID
     *
     * @param int $group_id
     * @return object|null Group data or null
     */
    public static function get_group($group_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . self::TABLE_GROUPS;
        
        $group = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND is_archived = 0",
            $group_id
        ));
        
        if (!$group) {
            return null;
        }
        
        // Strip slashes from text fields for proper display
        $group->name = stripslashes($group->name);
        if (!empty($group->description)) {
            $group->description = stripslashes($group->description);
        }
        
        // Get members
        $group->members = self::get_group_members($group_id);
        
        // Calculate member counts
        $group->member_count = self::get_member_count($group_id);
        $group->child_count = self::get_member_count($group_id, 'child');
        
        // Get planned children from billing owner's user meta
        if (!empty($group->billing_owner)) {
            $planned = get_user_meta($group->billing_owner, 'ftt_planned_children', true);
            $group->planned_children = !empty($planned) ? intval($planned) : 0;
        } else {
            $group->planned_children = 0;
        }
        
        // Ensure an opaque URL token is always present on the group object
        if ( empty( $group->group_token ) ) {
            $group->group_token = self::get_group_token( $group_id );
        }

        return $group;
    }

    /**
     * Get all groups for a user
     *
     * @param int $user_id
     * @return array Array of group objects
     */
    public static function get_user_groups($user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return [];
        }
        
        $table_groups = $wpdb->prefix . self::TABLE_GROUPS;
        $table_members = $wpdb->prefix . self::TABLE_MEMBERS;
        
        $groups = $wpdb->get_results($wpdb->prepare(
            "SELECT g.* 
             FROM {$table_groups} g
             INNER JOIN {$table_members} m ON g.id = m.group_id
             WHERE m.user_id = %d AND g.is_archived = 0
             ORDER BY g.created_at DESC",
            $user_id
        ));
        
        // Attach member counts to each group and strip slashes from text fields
        foreach ($groups as $group) {
            $group->member_count = self::get_member_count($group->id);
            $group->child_count = self::get_member_count($group->id, 'child');
            
            // Strip slashes for proper display
            $group->name = stripslashes($group->name);
            if (!empty($group->description)) {
                $group->description = stripslashes($group->description);
            }
            
            // Get planned children from billing owner's user meta
            if (!empty($group->billing_owner)) {
                $planned = get_user_meta($group->billing_owner, 'ftt_planned_children', true);
                $group->planned_children = !empty($planned) ? intval($planned) : 0;
            } else {
                $group->planned_children = 0;
            }
        }
        
        return $groups;
    }

    /**
     * Get all family groups (admin use)
     *
     * @param bool $include_archived Whether to include archived groups
     * @return array Array of group objects
     */
    public static function get_all_groups($include_archived = false) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_GROUPS;
        $where = $include_archived ? '' : 'WHERE is_archived = 0';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results("SELECT * FROM {$table} {$where} ORDER BY name ASC");
    }
    
    /**
     * Update a group
     *
     * @param int $group_id
     * @param array $args Updated data
     * @return bool|WP_Error Success or error
     */
    public static function update_group($group_id, $args) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        
        // Check if user can manage this group
        if (!current_user_can('manage_options') && !self::can_manage_group($group_id, $user_id)) {
            return new WP_Error('unauthorized', 'You do not have permission to manage this group');
        }
        
        $table = $wpdb->prefix . self::TABLE_GROUPS;
        
        $data = [
            'updated_at' => current_time('mysql'),
        ];
        
        if (isset($args['name'])) {
            $data['name'] = sanitize_text_field($args['name']);
        }
        
        if (isset($args['description'])) {
            $data['description'] = sanitize_textarea_field($args['description']);
        }
        
        if (isset($args['color'])) {
            $data['color'] = sanitize_hex_color($args['color']);
        }
        
        $updated = $wpdb->update(
            $table,
            $data,
            ['id' => $group_id],
            null,
            ['%d']
        );
        
        return $updated !== false;
    }
    
    /**
     * Archive a group (soft delete)
     *
     * @param int $group_id
     * @return bool|WP_Error Success or error
     */
    public static function archive_group($group_id) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        
        // Only billing owner (or admin) can archive
        $group = self::get_group($group_id);
        if (!$group || (!current_user_can('manage_options') && $group->billing_owner != $user_id)) {
            return new WP_Error('unauthorized', 'Only the billing owner can archive a group');
        }
        
        $table = $wpdb->prefix . self::TABLE_GROUPS;
        
        $updated = $wpdb->update(
            $table,
            ['is_archived' => 1, 'updated_at' => current_time('mysql')],
            ['id' => $group_id],
            ['%d', '%s'],
            ['%d']
        );
        
        return $updated !== false;
    }
    
    /**
     * Add a member to a group
     *
     * @param int $group_id
     * @param int $user_id
     * @param string $role 'parent' or 'child'
     * @param array $args Additional member data
     * @return int|WP_Error Member ID or error
     */
    public static function add_member($group_id, $user_id, $role, $args = []) {
        global $wpdb;
        
        $current_user = get_current_user_id();
        
        $defaults = [
            'relationship' => '',
            'can_manage_group' => false,
            'added_by' => $current_user,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $table = $wpdb->prefix . self::TABLE_MEMBERS;
        
        $data = [
            'group_id' => absint($group_id),
            'user_id' => absint($user_id),
            'role' => $role,
            'added_by' => absint($args['added_by']),
            'added_at' => current_time('mysql'),
            'relationship' => sanitize_text_field($args['relationship']),
            'can_manage_group' => (bool) $args['can_manage_group'],
        ];
        
        // Check if already a member
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE group_id = %d AND user_id = %d",
            $group_id,
            $user_id
        ));
        
        if ($existing) {
            return new WP_Error('already_member', 'User is already a member of this group');
        }
        
        $inserted = $wpdb->insert($table, $data);
        
        if ($inserted === false) {
            return new WP_Error('db_error', 'Failed to add member');
        }
        
        $member_id = $wpdb->insert_id;
        
        // LEGACY SUPPORT: Also update user meta relationships for backward compatibility
        if (class_exists('FTT_Roles')) {
            // Get all other members in the group
            $group_members = self::get_group_members($group_id);
            
            $parent_ids = [];
            $child_ids = [];
            foreach ($group_members as $member) {
                if ($member->user_id == $user_id) continue; // Skip the newly added user
                
                if ($member->role === 'parent') {
                    $parent_ids[] = $member->user_id;
                } elseif ($member->role === 'child') {
                    $child_ids[] = $member->user_id;
                }
            }
            
            if ($role === 'parent') {
                // New parent being added
                
                // Link to other parents (co-parents)
                if (!empty($parent_ids)) {
                    update_user_meta($user_id, 'ftt_parents', $parent_ids);
                    
                    // Add this parent to other parents' ftt_parents
                    foreach ($parent_ids as $parent_id) {
                        $existing_parents = get_user_meta($parent_id, 'ftt_parents', true);
                        if (!is_array($existing_parents)) {
                            $existing_parents = [];
                        }
                        if (!in_array($user_id, $existing_parents)) {
                            $existing_parents[] = $user_id;
                            update_user_meta($parent_id, 'ftt_parents', $existing_parents);
                        }
                    }
                }
                
                // Link to children
                if (!empty($child_ids)) {
                    update_user_meta($user_id, 'ftt_parent_of', $child_ids);
                    
                    // Add this parent to each child's ftt_parents
                    foreach ($child_ids as $child_id) {
                        $child_parents = get_user_meta($child_id, 'ftt_parents', true);
                        if (!is_array($child_parents)) {
                            $child_parents = [];
                        }
                        if (!in_array($user_id, $child_parents)) {
                            $child_parents[] = $user_id;
                            update_user_meta($child_id, 'ftt_parents', $child_parents);
                        }
                        
                        // Store relationship if provided
                        if (!empty($args['relationship'])) {
                            update_user_meta($child_id, 'relationship_to_' . $user_id, $args['relationship']);
                        }
                    }
                }
            } elseif ($role === 'child') {
                // New child being added
                
                // Link to all parents
                if (!empty($parent_ids)) {
                    update_user_meta($user_id, 'ftt_parents', $parent_ids);
                    
                    // Add this child to each parent's ftt_parent_of
                    foreach ($parent_ids as $parent_id) {
                        $parent_children = get_user_meta($parent_id, 'ftt_parent_of', true);
                        if (!is_array($parent_children)) {
                            $parent_children = [];
                        }
                        if (!in_array($user_id, $parent_children)) {
                            $parent_children[] = $user_id;
                            update_user_meta($parent_id, 'ftt_parent_of', $parent_children);
                        }
                        
                        // Store relationship from child's perspective
                        if (!empty($args['relationship'])) {
                            update_user_meta($user_id, 'relationship_to_' . $parent_id, $args['relationship']);
                        }
                    }
                }
            }
        }
        
        return $member_id;
    }
    
    /**
     * Remove a member from a group
     *
     * @param int $group_id
     * @param int $user_id
     * @return bool|WP_Error Success or error
     */
    public static function remove_member($group_id, $user_id) {
        global $wpdb;
        
        $current_user = get_current_user_id();
        
        // Check permissions
        if (!self::can_manage_group($group_id, $current_user) && $current_user != $user_id) {
            return new WP_Error('unauthorized', 'You do not have permission to remove this member');
        }
        
        // Don't allow removing billing owner
        $group = self::get_group($group_id);
        if ($group && $group->billing_owner == $user_id) {
            return new WP_Error('cannot_remove_owner', 'Cannot remove billing owner. Transfer billing first.');
        }
        
        // Get member info before deletion for legacy cleanup
        $member_info = $wpdb->get_row($wpdb->prepare(
            "SELECT role FROM {$wpdb->prefix}ftt_group_members WHERE group_id = %d AND user_id = %d",
            $group_id,
            $user_id
        ));
        
        $table = $wpdb->prefix . self::TABLE_MEMBERS;
        
        $deleted = $wpdb->delete(
            $table,
            ['group_id' => $group_id, 'user_id' => $user_id],
            ['%d', '%d']
        );
        
        // LEGACY SUPPORT: Clean up user meta relationships
        if ($deleted !== false && $member_info && class_exists('FTT_Roles')) {
            // Get remaining members in the group (after deletion)
            $group_members = self::get_group_members($group_id);
            
            $parent_ids = [];
            $child_ids = [];
            foreach ($group_members as $member) {
                if ($member->role === 'parent') {
                    $parent_ids[] = $member->user_id;
                } elseif ($member->role === 'child') {
                    $child_ids[] = $member->user_id;
                }
            }
            
            if ($member_info->role === 'parent') {
                // Parent was removed - clean up relationships with remaining members
                
                // Remove from other parents' ftt_parents
                foreach ($parent_ids as $parent_id) {
                    $existing_parents = get_user_meta($parent_id, 'ftt_parents', true);
                    if (is_array($existing_parents)) {
                        $existing_parents = array_diff($existing_parents, [$user_id]);
                        update_user_meta($parent_id, 'ftt_parents', $existing_parents);
                    }
                }
                
                // Remove from children's ftt_parents
                foreach ($child_ids as $child_id) {
                    $child_parents = get_user_meta($child_id, 'ftt_parents', true);
                    if (is_array($child_parents)) {
                        $child_parents = array_diff($child_parents, [$user_id]);
                        update_user_meta($child_id, 'ftt_parents', $child_parents);
                    }
                    
                    // Remove relationship meta
                    delete_user_meta($child_id, 'relationship_to_' . $user_id);
                }
                
                // Clean up removed parent's meta
                delete_user_meta($user_id, 'ftt_parents');
                delete_user_meta($user_id, 'ftt_parent_of');
                
            } elseif ($member_info->role === 'child') {
                // Child was removed - clean up relationships with parents
                
                foreach ($parent_ids as $parent_id) {
                    $parent_children = get_user_meta($parent_id, 'ftt_parent_of', true);
                    if (is_array($parent_children)) {
                        $parent_children = array_diff($parent_children, [$user_id]);
                        update_user_meta($parent_id, 'ftt_parent_of', $parent_children);
                    }
                    
                    // Remove relationship meta
                    delete_user_meta($user_id, 'relationship_to_' . $parent_id);
                }
                
                // Clean up removed child's meta
                delete_user_meta($user_id, 'ftt_parents');
            }
        }
        
        return $deleted !== false;
    }
    
    /**
     * Get all members of a group
     *
     * @param int $group_id
     * @param string $role Optional filter by role
     * @return array Array of member objects
     */
    public static function get_group_members($group_id, $role = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . self::TABLE_MEMBERS;
        
        $sql = "SELECT m.*, u.display_name, u.user_email 
                FROM {$table} m
                INNER JOIN {$wpdb->users} u ON m.user_id = u.ID
                WHERE m.group_id = %d";
        
        $params = [$group_id];
        
        if ($role) {
            $sql .= " AND m.role = %s";
            $params[] = $role;
        }
        
        $sql .= " ORDER BY m.added_at ASC";
        
        $members = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        // Strip slashes from text fields for proper display
        foreach ($members as $member) {
            if (!empty($member->relationship)) {
                $member->relationship = stripslashes($member->relationship);
            }
        }
        
        return $members;
    }
    
    /**
     * Get member count for a group
     *
     * @param int $group_id
     * @param string $role Optional filter by role
     * @return int Count
     */
    public static function get_member_count($group_id, $role = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . self::TABLE_MEMBERS;
        
        // Use INNER JOIN to only count members with valid WordPress user accounts
        // This prevents counting orphaned records from deleted users
        if ($role) {
            return $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$table} m
                 INNER JOIN {$wpdb->users} u ON m.user_id = u.ID
                 WHERE m.group_id = %d AND m.role = %s",
                $group_id,
                $role
            ));
        } else {
            return $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$table} m
                 INNER JOIN {$wpdb->users} u ON m.user_id = u.ID
                 WHERE m.group_id = %d",
                $group_id
            ));
        }
    }
    
    /**
     * Check if user is a member of a group
     *
     * @param int $group_id
     * @param int $user_id
     * @return bool
     */
    public static function is_member($group_id, $user_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . self::TABLE_MEMBERS;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
             WHERE group_id = %d AND user_id = %d",
            $group_id,
            $user_id
        ));
        
        return (int) $count > 0;
    }
    
    /**
     * Check if user can manage a group
     *
     * @param int $group_id
     * @param int $user_id
     * @return bool
     */
    public static function can_manage_group($group_id, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        $table = $wpdb->prefix . self::TABLE_MEMBERS;
        
        $can_manage = $wpdb->get_var($wpdb->prepare(
            "SELECT can_manage_group FROM {$table} 
             WHERE group_id = %d AND user_id = %d",
            $group_id,
            $user_id
        ));
        
        return (bool) $can_manage;
    }

    /**
     * Get or create an opaque URL token for a group.
     *
     * The token is a short, stable, non-guessable string derived from the
     * group ID and the site's auth salt.  It is cached in group meta so that
     * the same token is always returned for the same group and can be
     * resolved back to an integer ID via resolve_group_token().
     *
     * @param  int    $group_id
     * @return string 16-char hex token
     */
    public static function get_group_token( $group_id ) {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_GROUPS;

        // Check for a previously stored token.
        $stored = $wpdb->get_var( $wpdb->prepare(
            "SELECT group_token FROM {$table} WHERE id = %d",
            $group_id
        ) );

        if ( ! empty( $stored ) ) {
            return $stored;
        }

        // Generate and persist a new token.
        $token = substr( hash_hmac( 'sha256', (string) $group_id, wp_salt( 'auth' ) ), 0, 16 );
        $wpdb->update(
            $table,
            [ 'group_token' => $token ],
            [ 'id' => $group_id ],
            [ '%s' ],
            [ '%d' ]
        );

        return $token;
    }

    /**
     * Resolve an opaque group token back to an integer group ID.
     *
     * @param  string $token
     * @return int|null  Group ID, or null if the token is unknown.
     */
    public static function resolve_group_token( $token ) {
        global $wpdb;

        $token = sanitize_text_field( $token );
        if ( empty( $token ) ) {
            return null;
        }

        $table = $wpdb->prefix . self::TABLE_GROUPS;

        $group_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE group_token = %s AND is_archived = 0",
            $token
        ) );

        return $group_id ? (int) $group_id : null;
    }

    /**
     * Clean up orphaned group memberships when a WordPress user is deleted
     *
     * @param int $user_id User ID being deleted
     */
    public static function cleanup_deleted_user_memberships($user_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . self::TABLE_MEMBERS;
        
        // Remove all group memberships for the deleted user
        $wpdb->delete(
            $table,
            ['user_id' => $user_id],
            ['%d']
        );
        
        // Clean up legacy user meta for other users that referenced this deleted user
        if (class_exists('FTT_Roles')) {
            // Get all users to clean up their references
            $all_users = get_users(['fields' => 'ID']);
            
            foreach ($all_users as $other_user_id) {
                // Remove from ftt_parents arrays
                $parents = get_user_meta($other_user_id, 'ftt_parents', true);
                if (is_array($parents) && in_array($user_id, $parents)) {
                    $parents = array_diff($parents, [$user_id]);
                    update_user_meta($other_user_id, 'ftt_parents', $parents);
                }
                
                // Remove from ftt_parent_of arrays
                $children = get_user_meta($other_user_id, 'ftt_parent_of', true);
                if (is_array($children) && in_array($user_id, $children)) {
                    $children = array_diff($children, [$user_id]);
                    update_user_meta($other_user_id, 'ftt_parent_of', $children);
                }
                
                // Remove relationship meta
                delete_user_meta($other_user_id, 'relationship_to_' . $user_id);
            }
        }
    }
    
    /**
     * Clean up all orphaned member records (one-time maintenance)
     * 
     * Removes group member records where the user_id no longer exists in wp_users
     *
     * @return array Report of cleaned records
     */
    public static function cleanup_orphaned_members() {
        global $wpdb;
        
        $table = $wpdb->prefix . self::TABLE_MEMBERS;
        
        // Find all orphaned records
        $orphaned = $wpdb->get_results(
            "SELECT m.* 
             FROM {$table} m
             LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
             WHERE u.ID IS NULL"
        );
        
        $count = count($orphaned);
        
        if ($count > 0) {
            // Delete orphaned records
            $wpdb->query(
                "DELETE m 
                 FROM {$table} m
                 LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
                 WHERE u.ID IS NULL"
            );
        }
        
        return [
            'orphaned_records' => $count,
            'records' => $orphaned,
        ];
    }
    
    /**
     * Associate an event with a group
     *
     * @param int $post_id Event post ID
     * @param int $group_id Group ID
     * @return bool Success
     */
    public static function add_event_to_group($post_id, $group_id) {
        global $wpdb;

        $post_id  = absint($post_id);
        $group_id = absint($group_id);
        $table    = $wpdb->prefix . self::TABLE_EVENT_GROUPS;

        // Use INSERT IGNORE so repeated calls (e.g. on event edit) are safe.
        $result = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table} (post_id, group_id, created_at) VALUES (%d, %d, %s)",
            $post_id, $group_id, current_time('mysql')
        ));

        return $result !== false;
    }
    
    /**
     * Get all events for a group
     *
     * @param int $group_id
     * @return array Array of post IDs
     */
    public static function get_group_events($group_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . self::TABLE_EVENT_GROUPS;
        
        return $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$table} WHERE group_id = %d",
            $group_id
        ));
    }
    
    /**
     * Get groups for an event
     *
     * @param int $post_id Event post ID
     * @return array Array of group IDs
     */
    public static function get_event_groups($post_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . self::TABLE_EVENT_GROUPS;
        
        return $wpdb->get_col($wpdb->prepare(
            "SELECT group_id FROM {$table} WHERE post_id = %d",
            $post_id
        ));
    }
    
    /**
     * Register REST API routes
     */
    public static function register_rest_routes() {
        // Create group
        register_rest_route('ftt/v1', '/groups', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_create_group'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);
        
        // Get user's groups
        register_rest_route('ftt/v1', '/groups', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_get_user_groups'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);
        
        // Get specific group
        register_rest_route('ftt/v1', '/groups/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_get_group'],
            'permission_callback' => [__CLASS__, 'rest_check_group_access']
        ]);
        
        // Update group
        register_rest_route('ftt/v1', '/groups/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [__CLASS__, 'rest_update_group'],
            'permission_callback' => [__CLASS__, 'rest_check_group_management']
        ]);
        
        // Archive group
        register_rest_route('ftt/v1', '/groups/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [__CLASS__, 'rest_archive_group'],
            'permission_callback' => [__CLASS__, 'rest_check_group_ownership']
        ]);
        
        // Get group members
        register_rest_route('ftt/v1', '/groups/(?P<id>\d+)/members', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_get_group_members'],
            'permission_callback' => [__CLASS__, 'rest_check_group_access']
        ]);
        
        // Add member to group
        register_rest_route('ftt/v1', '/groups/(?P<id>\d+)/members', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_add_member'],
            'permission_callback' => [__CLASS__, 'rest_check_group_management']
        ]);
        
        // Remove member from group
        register_rest_route('ftt/v1', '/groups/(?P<id>\d+)/members/(?P<user_id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [__CLASS__, 'rest_remove_member'],
            'permission_callback' => [__CLASS__, 'rest_check_group_management']
        ]);
        
        // Invite adult to group by email
        register_rest_route('ftt/v1', '/groups/(?P<id>\d+)/invitations', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_invite_to_group'],
            'permission_callback' => [__CLASS__, 'rest_check_group_management']
        ]);
        
        // Create checkout session for group
        register_rest_route('ftt/v1', '/groups/(?P<id>\d+)/checkout', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_create_checkout'],
            'permission_callback' => [__CLASS__, 'rest_check_billing_owner']
        ]);
        
        // Create billing portal session for group
        register_rest_route('ftt/v1', '/groups/(?P<id>\d+)/portal', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_create_portal'],
            'permission_callback' => [__CLASS__, 'rest_check_billing_owner']
        ]);
        
        // Get group billing summary
        register_rest_route('ftt/v1', '/groups/(?P<id>\d+)/billing', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_get_billing'],
            'permission_callback' => [__CLASS__, 'rest_check_group_access']
        ]);
    }
    
    /**
     * REST: Create group
     */
    public static function rest_create_group($request) {
        $params = $request->get_json_params();
        $user_id = get_current_user_id();
        
        $result = self::create_group($params);
        
        if (is_wp_error($result)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message()
            ], 400);
        }
        
        $group_id = $result;

        // Admins and billing-exempt users do not need to complete Stripe checkout
        $needs_billing = !user_can($user_id, 'manage_options')
            && !(class_exists('FTT_Billing_Manager') && FTT_Billing_Manager::is_billing_exempt($user_id));

        return new WP_REST_Response([
            'success' => true,
            'group_id' => $group_id,
            'group' => self::get_group($group_id),
            'needs_billing' => $needs_billing
        ]);
    }
    
    /**
     * REST: Get user's groups
     */
    public static function rest_get_user_groups($request) {
        $groups = self::get_user_groups();
        
        return new WP_REST_Response([
            'success' => true,
            'groups' => $groups
        ]);
    }
    
    /**
     * REST: Get specific group
     */
    public static function rest_get_group($request) {
        $group_id = $request->get_param('id');
        $group = self::get_group($group_id);
        
        if (!$group) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Group not found'
            ], 404);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'group' => $group
        ]);
    }
    
    /**
     * REST: Update group
     */
    public static function rest_update_group($request) {
        $group_id = $request->get_param('id');
        $params = $request->get_json_params();
        
        $result = self::update_group($group_id, $params);
        
        if (is_wp_error($result)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message()
            ], 400);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'group' => self::get_group($group_id)
        ]);
    }
    
    /**
     * REST: Archive group
     */
    public static function rest_archive_group($request) {
        $group_id = $request->get_param('id');
        
        $result = self::archive_group($group_id);
        
        if (is_wp_error($result)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message()
            ], 400);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Group archived successfully'
        ]);
    }
    
    /**
     * REST: Get group members
     */
    public static function rest_get_group_members($request) {
        $group_id = $request->get_param('id');
        $members = self::get_group_members($group_id);
        
        return new WP_REST_Response([
            'success' => true,
            'members' => $members
        ]);
    }
    
    /**
     * REST: Add member
     */
    public static function rest_add_member($request) {
        $group_id = $request->get_param('id');
        $params = $request->get_json_params();
        
        $user_id = isset($params['user_id']) ? absint($params['user_id']) : 0;
        $role = isset($params['role']) ? $params['role'] : 'parent';
        
        if (!$user_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'User ID is required'
            ], 400);
        }
        
        $result = self::add_member($group_id, $user_id, $role, $params);
        
        if (is_wp_error($result)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message()
            ], 400);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'member_id' => $result
        ]);
    }
    
    /**
     * REST: Remove member
     */
    public static function rest_remove_member($request) {
        $group_id = $request->get_param('id');
        $user_id = $request->get_param('user_id');
        
        $result = self::remove_member($group_id, $user_id);
        
        if (is_wp_error($result)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message()
            ], 400);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Member removed successfully'
        ]);
    }
    
    /**
     * REST: Invite adult to group by email
     */
    public static function rest_invite_to_group($request) {
        $group_id = $request->get_param('id');
        $params = $request->get_json_params();
        $user_id = get_current_user_id();
        
        $email = isset($params['email']) ? sanitize_email($params['email']) : '';
        $relationship = isset($params['relationship']) ? sanitize_text_field($params['relationship']) : 'co-parent';
        
        if (!is_email($email)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Valid email is required'
            ], 400);
        }
        
        // Check if user with this email already exists
        $existing_user = get_user_by('email', $email);
        if ($existing_user) {
            // User exists, check if already a member
            $is_member = self::is_member($group_id, $existing_user->ID);
            if ($is_member) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'This user is already a member of the group'
                ], 400);
            }
            
            // Add them directly
            $result = self::add_member($group_id, $existing_user->ID, 'parent', [
                'relationship' => $relationship,
                'can_manage_group' => false
            ]);
            
            if (is_wp_error($result)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => $result->get_error_message()
                ], 400);
            }
            
            // TODO: Send notification email to existing user
            
            return new WP_REST_Response([
                'success' => true,
                'message' => 'User added to group successfully'
            ]);
        }
        
        // User doesn't exist, create invitation
        $invitation_code = wp_generate_password(12, false);
        $group = self::get_group($group_id);
        
        if (!$group) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Group not found'
            ], 404);
        }
        
        // Store invitation
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_INVITATIONS;
        
        $inserted = $wpdb->insert($table, [
            'group_id' => $group_id,
            'invited_by' => $user_id,
            'email' => $email,
            'relationship' => $relationship,
            'invite_code' => $invitation_code,
            'created_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'status' => 'pending'
        ]);
        
        if (!$inserted) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Failed to create invitation'
            ], 500);
        }
        
        // Send invitation email — route to the standard registration page with the invite code.
        // registration.php (handle_registration) will look up the code in ftt_group_invitations
        // and join the user to this group, skipping billing.
        $inviter = get_userdata($user_id);
        $register_url = FTT_Pages::get_page_url('register');
        if (!$register_url) {
            $register_url = home_url('/ftt-register/');
        }
        $accept_url = add_query_arg('ftt_invite', $invitation_code, $register_url);
        
        $subject = sprintf(__('%s invited you to join %s on Family Travel Tracker', 'schedule-collaboration-tracking'), 
            $inviter->display_name, $group->name);
        
        $message = sprintf(
            "Hi,\n\n%s has invited you to join the group \"%s\" on Family Travel Tracker.\n\n" .
            "Click here to accept the invitation:\n%s\n\n" .
            "This invitation expires in 30 days.\n\n" .
            "If you don't have an account yet, you'll be able to create one when you accept the invitation.",
            $inviter->display_name,
            $group->name,
            $accept_url
        );
        
        wp_mail($email, $subject, $message);
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Invitation sent successfully'
        ]);
    }
    
    /**
     * REST permission: Check group access
     */
    public static function rest_check_group_access($request) {
        if (current_user_can('manage_options')) {
            return true;
        }
        $group_id = $request->get_param('id');
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return false;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_MEMBERS;
        
        $is_member = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE group_id = %d AND user_id = %d",
            $group_id,
            $user_id
        ));
        
        return $is_member > 0;
    }
    
    /**
     * REST permission: Check group management rights
     */
    public static function rest_check_group_management($request) {
        if (current_user_can('manage_options')) {
            return true;
        }
        $group_id = $request->get_param('id');
        return self::can_manage_group($group_id);
    }
    
    /**
     * REST permission: Check group ownership
     */
    public static function rest_check_group_ownership($request) {
        if (current_user_can('manage_options')) {
            return true;
        }
        $group_id = $request->get_param('id');
        $user_id = get_current_user_id();
        
        $group = self::get_group($group_id);
        
        return $group && $group->billing_owner == $user_id;
    }
    
    /**
     * REST permission: Check billing owner
     */
    public static function rest_check_billing_owner($request) {
        if (current_user_can('manage_options')) {
            return true;
        }
        $group_id = $request->get_param('id');
        $user_id = get_current_user_id();
        
        $group = self::get_group($group_id);
        
        // Must be billing owner ONLY - billing management is restricted
        if ($group && $group->billing_owner == $user_id) {
            return true;
        }
        
        return false;
    }
    
    /**
     * REST: Create checkout session
     */
    public static function rest_create_checkout($request) {
        $group_id = $request->get_param('id');
        $params = $request->get_json_params();
        $interval = $params['interval'] ?? 'month';
        
        $url = self::create_checkout_session($group_id, $interval);
        
        if (is_wp_error($url)) {
            return new WP_Error('checkout_failed', $url->get_error_message(), ['status' => 400]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'url' => $url
        ]);
    }
    
    /**
     * REST: Create billing portal session
     */
    public static function rest_create_portal($request) {
        $group_id = $request->get_param('id');
        
        $url = self::create_portal_session($group_id);
        
        if (is_wp_error($url)) {
            return new WP_Error('portal_failed', $url->get_error_message(), ['status' => 400]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'url' => $url
        ]);
    }
    
    /**
     * REST: Get billing summary
     */
    public static function rest_get_billing($request) {
        $group_id = $request->get_param('id');
        
        $billing = self::get_billing_summary($group_id);
        
        if (is_wp_error($billing)) {
            return new WP_Error('billing_failed', $billing->get_error_message(), ['status' => 400]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'billing' => $billing
        ]);
    }
    
    /**
     * ========== BILLING METHODS (v2.1) ==========
     */
    
    /**
     * Get group by Stripe subscription ID
     *
     * @param string $subscription_id Stripe subscription ID
     * @return object|null Group object or null
     */
    public static function get_group_by_subscription_id($subscription_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . self::TABLE_GROUPS;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE stripe_subscription_id = %s AND is_archived = 0",
            $subscription_id
        ));
    }
    
    /**
     * Get group by Stripe customer ID
     *
     * @param string $customer_id Stripe customer ID
     * @return object|null Group object or null
     */
    public static function get_group_by_customer_id($customer_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . self::TABLE_GROUPS;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE stripe_customer_id = %s AND is_archived = 0",
            $customer_id
        ));
    }
    
    /**
     * Update group billing data
     *
     * @param int $group_id Group ID
     * @param array $billing_data Billing data to update
     * @return bool Success
     */
    public static function update_group_billing($group_id, $billing_data) {
        global $wpdb;
        
        $table = $wpdb->prefix . self::TABLE_GROUPS;
        
        $allowed_fields = [
            'stripe_customer_id',
            'stripe_subscription_id',
            'subscription_status',
            'subscription_interval',
            'next_billing_date',
            'trial_ends_at',
        ];
        
        $update_data = [];
        foreach ($billing_data as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                $update_data[$key] = $value;
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $update_data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $table,
            $update_data,
            ['id' => $group_id],
            null,
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Check if group has active billing
     *
     * @param int $group_id Group ID
     * @return bool
     */
    public static function has_active_billing($group_id) {
        $group = self::get_group($group_id);

        if (!$group) {
            return false;
        }

        // Card-free trial: if trial_ends_at has passed and no Stripe subscription exists, treat as expired
        if ($group->subscription_status === 'trialing'
            && empty($group->stripe_subscription_id)
            && !empty($group->trial_ends_at)
            && strtotime($group->trial_ends_at) < time()) {
            return false;
        }

        $active_statuses = ['active', 'trialing'];
        return in_array($group->subscription_status, $active_statuses);
    }
    
    /**
     * Get the appropriate redirect URL when a user lacks active billing.
     *
     * Returns the trial-expired page if the user has a card-free trial that has ended,
     * or the groups page with no_subscription reason for all other cases.
     *
     * @param int $user_id
     * @return string URL
     */
    public static function get_access_redirect_url($user_id) {
        $groups = self::get_user_groups($user_id);
        foreach ($groups as $group_row) {
            $group = self::get_group($group_row->id);
            if ($group
                && $group->subscription_status === 'trialing'
                && empty($group->stripe_subscription_id)
                && !empty($group->trial_ends_at)
                && strtotime($group->trial_ends_at) < time()) {
                return home_url('/ftt-trial-expired/');
            }
        }
        return add_query_arg('reason', 'no_subscription', home_url('/ftt-groups/'));
    }

    /**
     * Check if user has access via any group
     *
     * @param int $user_id User ID
     * @return bool
     */
    public static function user_has_group_access($user_id) {
        // Site admins and billing-exempt users always have access
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        if (class_exists('FTT_Billing_Manager') && FTT_Billing_Manager::is_billing_exempt($user_id)) {
            return true;
        }

        $groups = self::get_user_groups($user_id);
        
        foreach ($groups as $group) {
            if (self::has_active_billing($group->id)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get billing summary for a group
     *
     * @param int $group_id Group ID
     * @return array|null Billing summary
     */
    public static function get_billing_summary($group_id) {
        $group = self::get_group($group_id);
        
        if (!$group) {
            return null;
        }
        
        $child_count = self::get_member_count($group_id, 'child');
        
        // If no children yet, check for planned children from registration
        $planned_children = 0;
        if ($child_count === 0 && !empty($group->billing_owner)) {
            $planned = get_user_meta($group->billing_owner, 'ftt_planned_children', true);
            if (!empty($planned)) {
                $planned_children = intval($planned);
                $child_count = $planned_children; // Use planned for pricing calculation
            }
        } else {
            // If children exist, get planned from group property
            $planned_children = $group->planned_children ?? 0;
        }
        
        $addon_quantity = max(0, $child_count - 1);
        
        $interval = $group->subscription_interval ?: 'month';
        $base_price = $interval === 'year' ? 99.00 : 9.99;
        $addon_price = $interval === 'year' ? 20.00 : 2.00;
        $total_price = $base_price + ($addon_quantity * $addon_price);
        
        return [
            'group_id' => $group_id,
            'group_name' => $group->name,
            'status' => $group->subscription_status ?: 'none',
            'interval' => $interval,
            'child_count' => $child_count,
            'planned_children' => $planned_children,
            'addon_quantity' => $addon_quantity,
            'base_price' => $base_price,
            'addon_price' => $addon_price,
            'total_price' => $total_price,
            'next_billing_date' => $group->next_billing_date,
            'trial_ends_at' => $group->trial_ends_at,
            'billing_owner' => $group->billing_owner,
        ];
    }
    
    /**
     * Create Stripe checkout session for group
     */
    public static function create_checkout_session($group_id, $interval = 'month') {
        $group = self::get_group($group_id);
        if (!$group || !$group->billing_owner) {
            return new WP_Error('invalid_group', 'Invalid group or no billing owner');
        }
        
        // Ensure Stripe is initialized
        if (!class_exists('Stripe\Stripe')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'lib/stripe-php/init.php';
        }
        
        $stripe_settings = get_option('ftt_stripe_settings', []);
        $mode = $stripe_settings['mode'] ?? 'test';
        $secret_key = $mode === 'live' 
            ? ($stripe_settings['live_secret_key'] ?? '')
            : ($stripe_settings['test_secret_key'] ?? '');
        
        if (empty($secret_key)) {
            return new WP_Error('stripe_not_configured', 'Stripe is not configured');
        }
        
        \Stripe\Stripe::setApiKey($secret_key);
        
        // Get pricing
        $price_id = $interval === 'year' 
            ? $stripe_settings['price_base_yearly']
            : $stripe_settings['price_base_monthly'];
        
        if (empty($price_id)) {
            return new WP_Error('pricing_not_configured', 'Pricing not configured for ' . $interval);
        }
        
        // Get addon pricing (for additional children)
        $addon_price_id = $interval === 'year'
            ? $stripe_settings['price_addon_yearly']
            : $stripe_settings['price_addon_monthly'];
        
        // Count children in group to calculate addon quantity
        // For new signups, use planned_children from user meta; otherwise count actual members
        $child_count = intval(self::get_member_count($group_id, 'child'));
        
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('FTT DEBUG: Actual child members in group: ' . $child_count . ', billing_owner: ' . ($group->billing_owner ?? 'NONE'));
        }
        
        // If no children yet, check for planned children from registration
        if ($child_count == 0 && !empty($group->billing_owner)) {
            $planned = get_user_meta($group->billing_owner, 'ftt_planned_children', true);
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT DEBUG: Retrieved planned_children from user meta: ' . var_export($planned, true));
            }
            if (!empty($planned)) {
                $child_count = intval($planned);
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('FTT DEBUG: Using planned_children: ' . $child_count);
                }
            }
        }
        
        $addon_quantity = max(0, $child_count - 1); // Base includes 1 child, so additional = count - 1
        
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('FTT DEBUG: Checkout - base price: ' . $price_id . ', addon price: ' . ($addon_price_id ?: 'NONE') . ', addon qty: ' . $addon_quantity);
        }
        
        // Get or create customer
        $customer_id = $group->stripe_customer_id;
        $billing_owner = get_userdata($group->billing_owner);
        
        if (empty($customer_id)) {
            try {
                $customer = \Stripe\Customer::create([
                    'email' => $billing_owner->user_email,
                    'name' => $billing_owner->display_name,
                    'metadata' => [
                        'wordpress_user_id' => $group->billing_owner,
                        'group_id' => $group_id,
                        'group_name' => $group->name,
                    ],
                ]);
                $customer_id = $customer->id;
                
                // Save customer ID
                self::update_group_billing($group_id, [
                    'stripe_customer_id' => $customer_id,
                ]);
            } catch (\Exception $e) {
                error_log('FTT: Failed to create Stripe customer: ' . $e->getMessage());
                return new WP_Error('stripe_error', $e->getMessage());
            }
        }
        
        // Build line items - base price + addon for additional children
        $line_items = [
            [
                'price' => $price_id,
                'quantity' => 1,
            ]
        ];
        
        // Add addon line item if there are additional children (beyond the first)
        if ($addon_quantity > 0 && !empty($addon_price_id)) {
            $line_items[] = [
                'price' => $addon_price_id,
                'quantity' => $addon_quantity,
            ];
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT DEBUG: Added addon line item - price: ' . $addon_price_id . ', qty: ' . $addon_quantity);
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT DEBUG: No addon added - addon_qty: ' . $addon_quantity . ', addon_price_id: ' . ($addon_price_id ?: 'EMPTY'));
            }
        }
        
        // Create checkout session
        try {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT DEBUG: Creating Stripe checkout with customer: ' . $customer_id);
            }
            
            // Get trial period from settings
            $trial_days = $stripe_settings['trial_days'] ?? 14;
            
            $session = \Stripe\Checkout\Session::create([
                'customer' => $customer_id,
                'payment_method_types' => ['card'],
                'line_items' => $line_items,
                'mode' => 'subscription',
                'subscription_data' => [
                    'trial_period_days' => $trial_days,
                    'metadata' => [
                        'group_id' => $group_id,
                        'wordpress_user_id' => $group->billing_owner,
                    ],
                ],
                'metadata' => [
                    'group_id' => $group_id,
                    'wordpress_user_id' => $group->billing_owner,
                ],
                'success_url' => home_url('/ftt-groups/?checkout=success&session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url' => home_url('/ftt-groups/?checkout=cancel'),
            ]);
            
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FTT DEBUG: Stripe session created successfully, URL: ' . $session->url);
            }
            
            return $session->url;
        } catch (\Exception $e) {
            error_log('FTT: Failed to create checkout session: ' . $e->getMessage());
            return new WP_Error('stripe_error', $e->getMessage());
        }
    }
    
    /**
     * Create Stripe billing portal session for group
     */
    public static function create_portal_session($group_id) {
        $group = self::get_group($group_id);
        if (!$group || empty($group->stripe_customer_id)) {
            return new WP_Error('invalid_group', 'Invalid group or no customer ID');
        }
        
        // Ensure Stripe is initialized
        if (!class_exists('Stripe\Stripe')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'lib/stripe-php/init.php';
        }
        
        $stripe_settings = get_option('ftt_stripe_settings', []);
        $mode = $stripe_settings['mode'] ?? 'test';
        $secret_key = $mode === 'live' 
            ? ($stripe_settings['live_secret_key'] ?? '')
            : ($stripe_settings['test_secret_key'] ?? '');
        
        if (empty($secret_key)) {
            return new WP_Error('stripe_not_configured', 'Stripe is not configured');
        }
        
        \Stripe\Stripe::setApiKey($secret_key);
        
        try {
            $session = \Stripe\BillingPortal\Session::create([
                'customer' => $group->stripe_customer_id,
                'return_url' => home_url('/ftt-groups/'),
            ]);
            
            return $session->url;
        } catch (\Exception $e) {
            error_log('FTT: Failed to create portal session: ' . $e->getMessage());
            return new WP_Error('stripe_error', $e->getMessage());
        }
    }
}

// Initialize
FTT_Family_Groups::init();
