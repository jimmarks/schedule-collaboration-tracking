# Legacy System Status

## Overview

Family Travel Tracker v2.1+ uses a **Family Groups** system with dedicated database tables. However, the legacy user meta-based relationship system (v2.0 and earlier) is **still maintained** for backward compatibility.

## Current Status (as of v2.2.0)

### ✅ MAINTAINED - Legacy User Meta System

The following user meta keys are **actively maintained** and updated alongside the new groups system:

#### Parent-Child Relationships

- **`ftt_parents`** (array of user IDs)
  - For parents: Lists other co-parents they're linked to
  - For children: Lists all parents/guardians linked to them
  - **Updated automatically** when members are added/removed from groups

- **`ftt_parent_of`** (array of user IDs)
  - For parents: Lists all children they're linked to
  - **Updated automatically** when members are added/removed from groups

- **`relationship_to_{user_id}`** (string)
  - Stores relationship type (e.g., "mother", "father", "co-parent", "guardian")
  - **Updated automatically** when members are added to groups with relationship data

#### Other User Meta

- **`ftt_is_member`** (boolean) - Still used to identify child/traveler accounts
- **`ftt_primary_group`** (integer) - Default group ID for the user (v2.1+)

### 🔄 DUAL MAINTENANCE - Why Both Systems Exist

The legacy system is maintained because:

1. **Backward Compatibility**: Existing features and templates still read legacy meta
2. **Gradual Migration**: Not all users have migrated to v2.1 groups yet
3. **API Compatibility**: External integrations may rely on legacy data structure
4. **Admin Interface**: The WordPress user profile editor still shows legacy relationships

### 📁 Files That Use Legacy System

#### Templates
- **`templates/family-management.php`** - Shows "Linked Adults" and "Linked Children"
- **`templates/dashboard.php`** - Reads legacy relationships for display
- **`templates/event-form.php`** - Uses `FTT_Roles::get_children()` to populate dropdowns

#### Core Classes
- **`includes/roles.php`** - `FTT_Roles` class manages legacy user meta
  - `get_parents($user_id)` - Returns `ftt_parents` array
  - `get_children($user_id)` - Returns `ftt_parent_of` array
  - `add_parent_child($parent_id, $child_id)` - Creates legacy relationships
  - `remove_parent_child($parent_id, $child_id)` - Removes legacy relationships
  
- **`includes/invitations.php`** - `get_linked_adults()` reads `ftt_parents` meta

#### Admin Interface
- **`includes/admin-manage-users.php`** - Shows parent-child relationships
- **`templates/admin-manage-users.php`** - UI for managing legacy relationships

### ⚠️ DEPRECATED - Scheduled for Removal

The following are not maintained and will be removed:

- **`ftt_adult_invitations`** - Now stored in groups invitation tables (v2.1+)
- Old invitation code structure (replaced by invitation system in v2.1)

## Synchronization Logic

### When Adding Members to Groups

**Location**: `includes/class-family-groups.php` - `add_member()` function

When a member is added to a group (parent or child), the system automatically:

1. **For new parent**:
   - Adds all existing parents to this parent's `ftt_parents`
   - Adds this parent to all existing parents' `ftt_parents`
   - Adds all children to this parent's `ftt_parent_of`
   - Adds this parent to all children's `ftt_parents`
   - Creates `relationship_to_{parent_id}` for each child

2. **For new child**:
   - Adds all parents to this child's `ftt_parents`
   - Adds this child to all parents' `ftt_parent_of`
   - Creates `relationship_to_{parent_id}` from child's perspective

### When Removing Members from Groups

**Location**: `includes/class-family-groups.php` - `remove_member()` function

When a member is removed from a group, the system automatically:

1. **For removed parent**:
   - Removes from all co-parents' `ftt_parents` arrays
   - Removes from all children's `ftt_parents` arrays
   - Deletes all `relationship_to_{parent_id}` meta from children
   - Clears this parent's `ftt_parents` and `ftt_parent_of`

2. **For removed child**:
   - Removes from all parents' `ftt_parent_of` arrays
   - Deletes all `relationship_to_{parent_id}` meta from child
   - Clears this child's `ftt_parents`

### During Registration with Invitation

**Location**: `includes/registration.php` - `handle_registration()` function

When a new parent accepts an invitation:

1. Adds them to the group (v2.1 system)
2. Then synchronizes legacy relationships:
   - Links to all parents in group (`ftt_parents`)
   - Links to all children in group (`ftt_parent_of` and reverse)
   - Creates relationship meta for all children

## Migration Path

### Current Phase: v2.2.0 - Dual Maintenance

Both systems are fully maintained and synchronized. All new operations update both.

### Planned Phases

- **v2.3.0** (Target: Q2 2026): Mark legacy functions as deprecated
  - Add deprecation notices to `FTT_Roles` methods
  - Update templates to use Groups API exclusively
  - Log warnings when legacy meta is accessed directly

- **v2.4.0** (Target: Q3 2026): Add migration tool
  - One-click migration for remaining v2.0 users
  - Admin dashboard warning for unmigrated accounts
  - Data validation and cleanup tools

- **v3.0.0** (Target: Q4 2026): Remove legacy system
  - Delete `FTT_Roles` class (except for basic member checks)
  - Remove legacy meta from database
  - Clean up templates and remove backward compatibility code
  - Performance improvements from simplified codebase

## Developer Guidelines

### When Building New Features

**DO**:
- ✅ Use the Groups API (`FTT_Family_Groups` class) for all new features
- ✅ Test that both systems stay synchronized
- ✅ Document any edge cases where sync might fail

**DON'T**:
- ❌ Create new features that only update legacy meta
- ❌ Read legacy meta directly - use Groups API wrapper functions
- ❌ Assume legacy relationships exist - always check groups first

### Testing Checklist

When modifying member/relationship code:

1. ✅ Add member to group → Verify legacy meta updated
2. ✅ Remove member from group → Verify legacy meta cleaned up
3. ✅ Accept invitation → Verify both systems populated
4. ✅ Check "Linked Adults" in family-management.php displays correctly
5. ✅ Check WordPress user profile shows correct relationships
6. ✅ Verify event form shows correct children dropdown

## Known Issues

### Edge Cases

1. **Multiple Groups with Same Person**: If User A and User B are in multiple groups together, removing from one group will break the legacy link even though they're still in another group together.
   - **Impact**: Low - Most users only share one group
   - **Mitigation**: Full migration to v2.1+ groups resolves this
   - **Planned Fix**: v2.3.0 will only maintain legacy meta for primary group

2. **Orphaned Relationships**: Direct user deletion (outside plugin) may leave orphaned IDs in meta arrays
   - **Impact**: Low - Doesn't cause errors, just stale data
   - **Mitigation**: Migration validation tool (v2.4.0) will clean these up

3. **Performance**: Dual writes to both systems adds overhead
   - **Impact**: Low - Only affects write operations (rare)
   - **Planned Fix**: v3.0.0 removes dual maintenance

## Related Documentation

- `FAMILY_GROUPS_V2.1_SPEC.md` - Full v2.1 specification
- `CHANGELOG.md` - Version history and deprecation timeline
- `includes/class-groups-migration.php` - Migration code from v2.0 to v2.1
- `REGISTRATION_GUIDE.md` - User management and relationships

## Summary

**The legacy user meta system is NOT deprecated - it is actively maintained for backward compatibility.**

All group operations automatically synchronize legacy relationships. This ensures:
- Existing features continue to work
- Admin interfaces show correct data
- Gradual migration path for users
- No breaking changes for existing installations

The legacy system will remain fully functional until at least v3.0.0 (estimated Q4 2026).
