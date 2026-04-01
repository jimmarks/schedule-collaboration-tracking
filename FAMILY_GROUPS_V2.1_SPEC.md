# Family Groups Architecture - v2.1 Specification

**Status**: Planning  
**Target Release**: v2.1.0  
**Effort Estimate**: 2-3 weeks development + 1 week testing  
**Priority**: High - Critical for complex family scenarios

---

## Executive Summary

Move from individual parent-child relationships to a **Family Group** model that supports:
- Multiple billing groups per adult
- Shared children across divorced/blended families
- Independent billing per group
- Clean separation of calendar/event visibility by group

---

## Problem Statement

### Current Limitations
1. **Rigid Structure**: Adult A ↔ Adult B share all children, single billing
2. **No Multi-Group Support**: Adult can't be in multiple family groups
3. **Billing Confusion**: Who pays when child belongs to 2 divorced parents?
4. **Dashboard Clutter**: No way to separate "Dad's family" from "Mom's family" events

### Real-World Scenarios Not Supported

**Scenario 1: Divorced Parents**
```
Dad + Stepmom (Group 1, Billing A)
  └─ Kids: Alice, Bob (from Dad's first marriage)
  └─ Kid: Charlie (Dad + Stepmom together)

Mom + Stepdad (Group 2, Billing B)
  └─ Kids: Alice, Bob (from Mom's first marriage)
  └─ Kid: Diana (Mom + Stepdad together)
```

**Current System**: Can't represent this. Alice/Bob would need to choose one billing group.

**v2.1 System**: 
- Alice/Bob belong to both Group 1 and Group 2
- Dad pays for Group 1 access (3 kids)
- Mom pays for Group 2 access (3 kids)
- Alice/Bob see events from both groups
- Parents only see their respective group's events (unless shared)

---

## Database Schema

### New Tables

```sql
-- Family Groups (core entity)
CREATE TABLE {prefix}_ftt_family_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    billing_owner BIGINT UNSIGNED NOT NULL,
    stripe_customer_id VARCHAR(255),
    stripe_subscription_id VARCHAR(255),
    subscription_status VARCHAR(50), -- active, trialing, past_due, canceled
    subscription_interval VARCHAR(20), -- month, year
    next_billing_date DATETIME,
    trial_ends_at DATETIME,
    color VARCHAR(7), -- Group color for calendar display
    is_archived BOOLEAN DEFAULT 0,
    INDEX idx_billing_owner (billing_owner),
    INDEX idx_created_by (created_by),
    INDEX idx_status (subscription_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Group Membership (who belongs to which groups)
CREATE TABLE {prefix}_ftt_group_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('parent', 'child') NOT NULL,
    added_by BIGINT UNSIGNED NOT NULL,
    added_at DATETIME NOT NULL,
    relationship VARCHAR(100), -- e.g., "Father", "Mother", "Stepparent", "Child"
    can_manage_group BOOLEAN DEFAULT 0, -- Can edit group settings
    UNIQUE KEY unique_membership (group_id, user_id),
    INDEX idx_group (group_id),
    INDEX idx_user (user_id),
    INDEX idx_role (role),
    FOREIGN KEY (group_id) REFERENCES {prefix}_ftt_family_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Group Invitations (invite adults to join a group)
CREATE TABLE {prefix}_ftt_group_invitations (
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
    FOREIGN KEY (group_id) REFERENCES {prefix}_ftt_family_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event-Group Association (which group does an event belong to)
CREATE TABLE {prefix}_ftt_event_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id BIGINT UNSIGNED NOT NULL, -- ftt_event post ID
    group_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY unique_event_group (post_id, group_id),
    INDEX idx_post (post_id),
    INDEX idx_group (group_id),
    FOREIGN KEY (group_id) REFERENCES {prefix}_ftt_family_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Modified User Meta Structure

**Keep existing meta for backward compatibility:**
- `ftt_is_member` - Still used for user type
- `ftt_parents` - Deprecated, but maintained during migration
- `ftt_parent_of` - Deprecated, but maintained during migration

**New meta:**
- `ftt_primary_group` - Default group ID for this user
- `ftt_group_preferences` - Serialized array of per-group settings

---

## API Endpoints (New)

### Group Management

```php
// Create a new family group
POST /ftt/v1/groups
{
  "name": "Smith Family",
  "description": "Dad and kids",
  "color": "#2196F3"
}

// Get user's groups
GET /ftt/v1/groups

// Get specific group details
GET /ftt/v1/groups/{group_id}

// Update group
PUT /ftt/v1/groups/{group_id}
{
  "name": "Updated Name",
  "description": "New description"
}

// Archive group (soft delete)
DELETE /ftt/v1/groups/{group_id}

// Get group members
GET /ftt/v1/groups/{group_id}/members

// Add child to group
POST /ftt/v1/groups/{group_id}/children
{
  "first_name": "Alice",
  "last_name": "Smith",
  "email": "alice@example.com"
}

// Remove member from group
DELETE /ftt/v1/groups/{group_id}/members/{user_id}

// Invite adult to group
POST /ftt/v1/groups/{group_id}/invite
{
  "email": "parent@example.com",
  "relationship": "Stepmother"
}

// Accept group invitation
POST /ftt/v1/groups/invitations/{code}/accept

// Transfer billing ownership
POST /ftt/v1/groups/{group_id}/transfer-billing
{
  "new_owner_id": 123
}
```

### Event Association

```php
// Create event for specific group
POST /ftt/v1/events
{
  "title": "Soccer Practice",
  "group_id": 5,
  "member_id": 10,
  ...
}

// Get events for a group
GET /ftt/v1/events?group_id=5

// Get all events across all user's groups
GET /ftt/v1/events?all_groups=true
```

---

## UI/UX Changes

### Dashboard Redesign

**Header: Group Switcher**
```
┌─────────────────────────────────────────┐
│ [Smith Family ▼]  👤 John Smith  🔔  ⚙️ │
├─────────────────────────────────────────┤
│  • Smith Family (3 kids) - Active       │
│  • Jones-Brown Blended (2 kids) - Active│
│  • + Create New Group                   │
└─────────────────────────────────────────┘
```

**Main Dashboard Cards (per-group view)**
```
┌─────────────────────────────────────────┐
│ Smith Family                             │
│ ─────────────────────────────────────   │
│ Members: John (Dad), Sarah (Mom)        │
│ Children: Alice (12), Bob (9)           │
│ Billing: Active - $14.99/mo             │
│                                          │
│ [Manage Group] [Add Child] [Invite]     │
└─────────────────────────────────────────┘
```

**Calendar View**
- Group selector at top
- "All Groups" option shows combined calendar
- Events color-coded by group
- Filter by group in sidebar

### New Pages

**1. Group Management (/ftt-groups/)**
- List all user's groups
- Create new group
- Archive old groups
- See group members and billing status

**2. Group Settings (/ftt-groups/{id}/settings)**
- Edit group name, description, color
- Manage members
- Billing management
- Invite co-parents
- Calendar subscription links (per-group)

**3. Multi-Group Event Form**
- Group selector at top of form
- Member dropdown filtered by selected group
- "Share with other groups" checkbox (for shared custody)

---

## Migration Strategy

### Phase 1: Data Migration (Automated Script)

```php
// includes/migration-v2.1.php

class FTT_Migration_Groups {
    public static function migrate() {
        global $wpdb;
        
        // Step 1: Create groups from existing parent pairs
        $result = self::create_groups_from_linked_adults();
        
        // Step 2: Migrate children into groups
        $result = self::migrate_children_to_groups();
        
        // Step 3: Migrate billing data
        $result = self::migrate_billing_to_groups();
        
        // Step 4: Migrate events to groups
        $result = self::migrate_events_to_groups();
        
        // Step 5: Validate migration
        $report = self::validate_migration();
        
        return $report;
    }
    
    private static function create_groups_from_linked_adults() {
        // Find all users with ftt_parents meta
        // Create a group for each unique set of linked adults
        // Set billing_owner to first adult (can be changed later)
        
        $adults_with_links = get_users(array(
            'meta_key' => 'ftt_parents',
            'fields' => 'ID'
        ));
        
        $processed_groups = array();
        
        foreach ($adults_with_links as $user_id) {
            // Get all linked adults for this user
            $linked_adults = get_user_meta($user_id, 'ftt_parents', true);
            $group_members = array_merge(array($user_id), $linked_adults);
            sort($group_members);
            
            $group_key = implode('-', $group_members);
            
            if (!isset($processed_groups[$group_key])) {
                // Create new group
                $group_data = array(
                    'name' => self::generate_group_name($group_members),
                    'billing_owner' => $user_id,
                    // ... migrate billing data
                );
                
                $group_id = self::create_group($group_data);
                
                // Add all adults to group
                foreach ($group_members as $member_id) {
                    self::add_to_group($group_id, $member_id, 'parent');
                }
                
                $processed_groups[$group_key] = $group_id;
            }
        }
        
        return $processed_groups;
    }
    
    // ... more migration methods
}
```

### Phase 2: Backward Compatibility Mode

**Keep old endpoints working:**
- `/ftt/v1/add-child` → Adds to user's primary group
- Dashboard without group: Show primary group by default
- Old calendar links: Redirect to primary group calendar

**Deprecation warnings:**
- Log when old meta is accessed
- Show admin notice about migration
- 6-month deprecation period

### Phase 3: User Communication

**Migration Email:**
```
Subject: Important Update: Family Groups Feature

We've enhanced your Family Travel Tracker to support multiple family groups!

What's New:
✓ Support for blended families and divorced parents
✓ Separate billing for different family groups  
✓ Better organization of events and calendars

What You Need to Do:
1. Review your family groups (we've set them up for you)
2. Invite any additional co-parents to your groups
3. Update group names if needed

Questions? Visit our help center or reply to this email.
```

**In-App Tutorial:**
- Dashboard banner: "Welcome to Family Groups!"
- Step-by-step walkthrough
- Video tutorial link

---

## Billing Changes

### Current Model
- One subscription per user
- Includes 1 child, $5/mo per additional child
- Co-parents don't pay (just linked)

### v2.1 Model
- One subscription **per group**
- Billing owner pays for that group's access
- Includes all children in the group
- Pricing: $9.99/mo base + $5/mo per child over 1

### Migration Strategy
```
Before: John pays $14.99/mo (3 kids)
- John is linked to Sarah
- They share 3 kids

After Migration:
Group: "John & Sarah Family"
- Billing Owner: John
- Price: $14.99/mo (3 kids) - NO CHANGE
- Sarah sees everything, doesn't pay
```

### New Scenario Support
```
Dad's Group: "Smith Family"
- Billing: Dad pays $14.99/mo
- Members: Dad, Stepmom
- Kids: Alice, Bob, Charlie (3 kids)

Mom's Group: "Jones-Smith Family" 
- Billing: Mom pays $14.99/mo
- Members: Mom, Stepdad
- Kids: Alice, Bob, Diana (3 kids)

Alice and Bob:
- Belong to both groups
- See events from both groups
- Each parent pays for their group
```

---

## Implementation Phases

### Phase 1: Database & Core API (Week 1)
- [ ] Create database tables
- [ ] Build FTT_Family_Groups class
- [ ] Implement CRUD operations
- [ ] Create REST endpoints
- [ ] Write migration script
- [ ] Unit tests for core functionality

### Phase 2: UI Components (Week 1-2)
- [ ] Group selector component
- [ ] Group management page
- [ ] Update dashboard to be group-aware
- [ ] Modify event form for groups
- [ ] Update family management page
- [ ] Calendar group filtering

### Phase 3: Billing Integration (Week 2)
- [ ] Update Stripe integration for per-group billing
- [ ] Billing transfer functionality
- [ ] Subscription management per group
- [ ] Update pricing calculations
- [ ] Billing admin interface

### Phase 4: Migration & Testing (Week 2-3)
- [ ] Run migration script on test data
- [ ] Validate data integrity
- [ ] Backward compatibility testing
- [ ] User acceptance testing
- [ ] Performance testing
- [ ] Documentation updates

### Phase 5: Deployment (Week 3)
- [ ] Staged rollout plan
- [ ] User communication
- [ ] Support documentation
- [ ] Monitor for issues
- [ ] Iterate based on feedback

---

## Backward Compatibility

### Must Maintain
- Existing parent-child relationships work
- Old calendar URLs continue to function
- API endpoints don't break for existing integrations
- Billing continues without interruption

### Deprecation Path
- **v2.1.0**: Groups introduced, old meta maintained
- **v2.2.0**: Push users to adopt groups (dashboard prompts)
- **v2.3.0**: Old meta marked deprecated (still functional)
- **v3.0.0**: Remove old meta structure completely

---

## Testing Checklist

### Unit Tests
- [ ] Group CRUD operations
- [ ] Member addition/removal
- [ ] Invitation flow
- [ ] Billing calculations
- [ ] Migration script accuracy

### Integration Tests
- [ ] Create event in group
- [ ] View events across multiple groups
- [ ] Accept group invitation
- [ ] Transfer billing ownership
- [ ] Calendar subscription per group

### User Scenarios
- [ ] Divorced parent with 2 groups
- [ ] Blended family scenario
- [ ] Single parent upgrading existing account
- [ ] New user creating first group
- [ ] Billing owner transferring ownership

### Migration Tests
- [ ] Legacy single-parent account
- [ ] Legacy linked co-parents
- [ ] Complex multi-child scenario
- [ ] Billing migration accuracy
- [ ] Event association correctness

---

## Risk Assessment

### High Risk
1. **Data Loss During Migration**
   - Mitigation: Full backup before migration, dry-run mode, rollback plan
   
2. **Billing Disruptions**
   - Mitigation: Migrate billing without interrupting Stripe subscriptions
   
3. **User Confusion**
   - Mitigation: Clear communication, tutorials, support documentation

### Medium Risk
1. **Performance Issues** (more complex queries)
   - Mitigation: Proper indexing, query optimization, caching
   
2. **Calendar Sync Complexity**
   - Mitigation: Per-group calendar tokens, clear documentation

### Low Risk
1. **Feature Adoption** (users don't understand groups)
   - Mitigation: Default behavior works without understanding groups

---

## Success Metrics

### Technical
- Migration completion rate: 100%
- Data integrity: 0 data loss events
- API response time: < 200ms for group queries
- Zero billing interruptions

### User Experience
- Support tickets related to migration: < 5%
- User adoption of multi-group feature: > 30% within 3 months
- Dashboard loading time: < 2 seconds
- User satisfaction score: > 4.5/5

### Business
- Churn rate during migration: < 2%
- Revenue growth from multi-group families: Track new subscriptions
- Feature usage: % of users with 2+ groups

---

## Open Questions

1. **Should children be able to see all their groups' events?**
   - Probably yes, but a privacy setting could control this
   
2. **Can a child be in a group without any parents from that group?**
   - Edge case: Grandparent group? Probably allow with warning
   
3. **Group limits per user?**
   - Suggest: Max 3-5 groups to prevent abuse
   
4. **What happens when billing owner leaves the group?**
   - Force transfer or auto-transfer to oldest member
   
5. **Historical events handling:**
   - Migrate all old events to primary group or leave unassigned?

---

## Next Steps

1. **Review this spec** - Get team/stakeholder feedback
2. **Prioritize open questions** - Make decisions on edge cases
3. **Create detailed task breakdown** - Add to project management tool
4. **Schedule development sprint** - Allocate 3 weeks when ready
5. **Prepare test environment** - Clone production data for testing
6. **Draft user communication** - Email and in-app messaging

---

## Resources Needed

### Development
- Backend developer: 2-3 weeks full-time
- Frontend developer: 1-2 weeks part-time
- Database migration: 3-5 days

### Design
- UI/UX mockups: 2-3 days
- User flow diagrams: 1 day

### Testing
- QA engineer: 1 week
- Beta users: 20-30 families

### Documentation
- API documentation update
- User help articles: 5-10 articles
- Migration guide for admins

---

## Related Documentation

- `ARCHITECTURE.md` - Current system architecture
- `STRIPE_BILLING_IMPLEMENTATION.md` - Current billing system
- `DUAL_DOMAIN_ARCHITECTURE.md` - Multi-tenant considerations
- `PRICE_TRACKING_IMPLEMENTATION.md` - Event tracking current state

---

**Document Version**: 1.0  
**Last Updated**: March 5, 2026  
**Author**: AI Assistant  
**Reviewers**: [To be filled]  
**Status**: Awaiting Review
