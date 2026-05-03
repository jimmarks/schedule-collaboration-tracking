# REST API Refactoring Audit
**Date:** May 3, 2026  
**Objective:** Identify all user-facing templates making direct PHP database calls instead of REST APIs

---

## Summary Statistics

- **Total Templates Audited:** 11 user-facing pages
- **Templates with Direct DB Calls:** 11 (100%)
- **Total Direct DB Call Instances:** 71+
- **Estimated Refactoring Effort:** High

---

## 1. 🔴 calendar.php - Calendar View
**Current Architecture:** Hybrid (partially refactored)  
**Direct DB Calls:** 5 instances

### PHP Calls Made On Page Load:
```php
✅ REFACTORED (now via JS REST API):
- Children list (was: get_user_children)

❌ STILL DIRECT PHP:
- FTT_Family_Groups::get_user_groups($user_id)                    [line 28]
- FTT_Family_Groups::resolve_group_token($raw_group)              [line 32]
- get_user_meta($user_id, 'ftt_visible_event_categories', true)  [line 68]
- get_user_meta($user->ID, 'ftt_calendar_token', true)           [line 107]
```

### Recommended REST Endpoints:
- `GET /ftt/v1/groups` - Get user's groups
- `GET /ftt/v1/user/preferences` - Get user preferences (categories, timezone, etc.)
- `GET /ftt/v1/user/calendar-token` - Get calendar subscription token

### Priority: **MEDIUM** (partially complete)

---

## 2. ✅ event-form.php - Add/Edit Event Form [COMPLETE]
**Current Architecture:** REST API ✅  
**Direct DB Calls:** 0 (was 6)

### Status: **REFACTORED** (Commit: d7659e3)
All data now loaded via REST APIs in JavaScript (loadEventFormData method).

### Previous PHP Calls (Now Eliminated):
```php
✅ ELIMINATED:
- get_user_meta($user->ID, 'ftt_home_airport', true)             [REMOVED - unused dead code]
- FTT_Family_Groups::get_user_children($user_id)                 [NOW: GET /ftt/v1/children]
- FTT_Roles::is_member($user_id)                                 [NOW: GET /ftt/v1/children]
- FTT_Family_Groups::get_user_groups($user_id)                   [NOW: GET /ftt/v1/groups]
- get_user_meta($user_id, 'ftt_primary_group', true)             [NOW: GET /ftt/v1/groups]
```

### REST APIs Used:
```javascript
✅ USING REST:
- GET /ftt/v1/children - Returns child array or is_member flag
- GET /ftt/v1/groups - Returns groups with primary_group_id
- GET /ftt/v1/events/{id} - Load event for editing
- POST /ftt/v1/events - Save event
```

### Implementation:
- Template renders skeleton HTML only
- loadEventFormData() in main.js fetches data on page load
- Dynamically populates member checkboxes and group dropdown
- Security enforced at REST layer (no PHP bypasses)

### Priority: **HIGH** (security and architecture consistency) - ✅ COMPLETE

---

## 3. 🔴 dashboard.php - Main Dashboard
**Current Architecture:** Direct PHP  
**Direct DB Calls:** 17 instances

### PHP Calls Made On Page Load:
```php
❌ DIRECT PHP:
- FTT_Roles::is_member($user_id)                                 [line 15]
- FTT_Family_Groups::is_parent($user_id)                         [line 16]
- get_users(array('meta_key' => 'ftt_adult_invitations'))        [line 39]
- get_user_meta($inviter->ID, 'ftt_adult_invitations', true)     [line 43]
- get_user_meta($inviter_id, 'ftt_parents', true)                [line 62] LEGACY
- get_user_meta($user_id, 'ftt_parents', true)                   [line 71] LEGACY
- FTT_Family_Groups::get_user_groups($user_id)                   [line 111]
- FTT_Family_Groups::resolve_group_token($raw_group)             [line 118]
- get_user_meta($user_id, 'ftt_primary_group', true)             [line 137]
- FTT_Family_Groups::get_user_children($user_id)                 [line 393]
- get_user_meta($child_id, 'ftt_section', true)                  [line 402]
- get_user_meta($child_id, 'ftt_instrument', true)               [line 403]
- get_user_meta($user_id, 'ftt_section', true)                   [line 491]
- get_user_meta($user_id, 'ftt_instrument', true)                [line 492]
```

### Recommended REST Endpoints:
- `GET /ftt/v1/dashboard` - Single endpoint returning all dashboard data:
  - User role info (parent/member)
  - User's groups
  - Children list with details
  - Pending invitations
  - Quick stats

### Priority: **HIGH** (most complex page, many queries)

---

## 4. 🔴 family-management.php - Manage Family
**Current Architecture:** Direct PHP  
**Direct DB Calls:** 15 instances

### PHP Calls Made On Page Load:
```php
❌ DIRECT PHP:
- FTT_Family_Groups::resolve_group_token($raw)                   [line 34]
- FTT_Family_Groups::get_group($group_id)                        [line 45]
- FTT_Family_Groups::can_manage_group($group_id, $user_id)       [line 48]
- FTT_Family_Groups::get_group_members($group_id)                [line 54]
- FTT_Family_Groups::is_parent($user_id)                         [line 59]
- FTT_Family_Groups::get_user_children($user_id)                 [line 60]
- FTT_Family_Groups::get_user_parents($user_id)                  [line 66]
- FTT_Family_Groups::get_group_token($group->id)                 [line 111]
- get_user_meta($child_id, 'child_age', true)                    [line 135]
- get_user_meta($child_id, 'child_grade', true)                  [line 136]
- get_user_meta($child_id, 'child_school', true)                 [line 137]
- get_user_meta($child_id, 'child_color', true)                  [line 150]
- get_user_meta($parent_id, 'relationship_to_' . $user_id)       [line 205]
- get_user_meta($user_id, 'ftt_adult_invitations', true)         [line 238]
- get_user_meta($user_id, 'ftt_visible_event_categories', true)  [line 315]
- get_user_meta($user_id, 'ftt_home_airport', true)              [line 376]
- get_user_meta($user_id, 'ftt_timezone', true)                  [line 396]
```

### JavaScript Already Makes:
```javascript
- fetch('/ftt/v1/get-family-members') - For updating UI (line 88 in main.js)
```

### Recommended Refactoring:
```javascript
GET /ftt/v1/groups/{group_id}/details - Single endpoint returning:
  - Group info
  - All members with full details (age, grade, color, etc.)
  - User's permissions (can_manage)
  - Invitation codes
  - Shared preferences
```

### Priority: **HIGH** (many redundant queries, user-facing)

---

## 5. 🔴 groups.php - Groups List
**Current Architecture:** Direct PHP  
**Direct DB Calls:** 8 instances

### PHP Calls Made On Page Load:
```php
❌ DIRECT PHP:
- FTT_Family_Groups::get_user_groups($user_id)                   [line 18]
- get_user_meta($user_id, 'ftt_primary_group', true)             [line 19]
- get_user_meta($user_id, 'ftt_primary_group_id', true)          [line 23]
- get_user_meta($group->billing_owner, 'ftt_subscription_status') [line 148]
- get_user_meta($group->billing_owner, 'ftt_trial_end', true)    [line 149]
- FTT_Family_Groups::get_group_token($group->id)                 [line 182]
- FTT_Family_Groups::can_manage_group($group_id, $user_id)       [line 189]
- FTT_Family_Groups::get_group_token($group->id)                 [line 190]
```

### Recommended REST Endpoint:
```javascript
GET /ftt/v1/groups - Returns:
  - All user's groups with member counts
  - Billing status for each group
  - Permissions (can_manage)
  - Calendar tokens
  - Primary group indicator
```

### Priority: **MEDIUM** (important but less complex)

---

## 6. 🔴 onboarding.php - New User Setup
**Current Architecture:** Direct PHP  
**Direct DB Calls:** 7 instances

### PHP Calls Made On Page Load:
```php
❌ DIRECT PHP:
- get_user_meta($user_id, 'ftt_primary_group', true)             [line 22]
- FTT_Family_Groups::get_group($primary_group_id)                [line 24]
- get_user_meta($user_id, 'ftt_home_airport', true)              [line 45]
- get_user_meta($user_id, 'ftt_home_airports', true)             [line 48]
- get_user_meta($user_id, 'ftt_timezone', true)                  [line 53]
- get_user_meta($user_id, 'ftt_calendar_token', true)            [line 62]
```

### Recommended REST Endpoint:
```javascript
GET /ftt/v1/onboarding/status - Returns:
  - User setup progress
  - Primary group info
  - Saved preferences
  - What steps remain
```

### Priority: **LOW** (onboarding is one-time, not performance-critical)

---

## 7. 🔴 trial-expired.php - Trial Expiration Notice
**Current Architecture:** Direct PHP  
**Direct DB Calls:** 2 instances

### PHP Calls Made On Page Load:
```php
❌ DIRECT PHP:
- get_user_meta($user_id, 'ftt_primary_group', true)             [line 17]
- FTT_Family_Groups::get_group($primary_group_id)                [line 19]
```

### Recommended: Use existing billing endpoints
```javascript
GET /ftt/v1/billing/status - Should include trial status
```

### Priority: **LOW** (simple page, infrequent access)

---

## 8. 🔴 event-view.php - View Single Event
**Current Architecture:** Direct PHP  
**Direct DB Calls:** 1 instance

### PHP Calls Made On Page Load:
```php
❌ DIRECT PHP:
- FTT_Family_Groups::get_group($group_id)                        [line 63]
```

### JavaScript Already Makes:
```javascript
- Likely fetches event data via /ftt/v1/events/{id}
```

### Recommended: Include group info in event response
```javascript
GET /ftt/v1/events/{id} - Should include:
  - Event details
  - Associated group info
  - Member details
```

### Priority: **LOW** (single call, not complex)

---

## 9. ⚠️ admin-manage-users.php - Admin User Management
**Type:** Admin Backend Page  
**Direct DB Calls:** 13 instances  
**Recommendation:** Keep as-is (admin pages can use direct PHP)

---

## Architectural Issues Identified

### 1. **Redundant Data Fetching**
Multiple templates fetch the same data:
- `get_user_groups()` called in 5+ templates
- `get_user_children()` called in 6+ templates
- User preferences queried separately on every page

### 2. **N+1 Query Problems**
```php
// dashboard.php - loops through children, queries each individually
foreach ($children as $child_id) {
    get_user_meta($child_id, 'ftt_section', true);      // DB Query
    get_user_meta($child_id, 'ftt_instrument', true);   // DB Query
}
```

### 3. **Security Inconsistency**
- REST API has security checks and filtering
- PHP templates bypass these checks
- Mixed enforcement of group-based access

### 4. **No Caching**
- Every page load = fresh DB queries
- REST API responses can be cached by browser
- PHP output is never cached

### 5. **Duplicate Logic**
- Children filtering logic in templates vs REST API
- Group permission checks duplicated
- User role detection scattered

---

## Recommended Refactoring Priorities

### Phase 1 - Critical (Security & High Traffic) ⏳ IN PROGRESS
1. ✅ **calendar.php** - COMPLETE (children via REST)
2. ✅ **REST API security** - COMPLETE (group-based access enforced)
3. ✅ **event-form.php** - COMPLETE (all data via REST APIs)
4. ❌ **dashboard.php** - Most complex, highest traffic [NEXT]

### Phase 2 - High Value
5. **family-management.php** - Many redundant queries
6. **groups.php** - Central to navigation

### Phase 3 - Polish
7. **onboarding.php** - One-time use
8. **trial-expired.php** - Infrequent
9. **event-view.php** - Simple, low impact

---

## New REST Endpoints Needed

### User Context Endpoint
```
GET /ftt/v1/user/context
Returns:
  - user_id, name, email
  - is_parent, is_member
  - primary_group_id
  - permissions
```

### User Preferences Endpoint
```
GET /ftt/v1/user/preferences
Returns:
  - home_airport(s)
  - timezone
  - visible_event_categories
  - calendar_token
```

### Dashboard Data Endpoint
```
GET /ftt/v1/dashboard
Returns:
  - User context
  - Groups summary
  - Children list with details
  - Pending invitations
  - Recent events
  - Quick stats
```

### Group Details Endpoint (already exists partially)
```
GET /ftt/v1/groups/{id}/details
Enhance to include:
  - Full member details (age, grade, color, etc.)
  - Billing status
  - Permissions
  - Calendar tokens
```

---

## Benefits of Completing Refactoring

### Performance
- Reduce page load queries from 15+ to 2-3 REST calls
- Enable browser caching of API responses
- Single optimized query vs multiple scattered queries

### Security
- All data access through authenticated REST endpoints
- Consistent group-based filtering
- Centralized permission checks

### Maintainability
- Single source of truth for business logic
- Easier to test (REST endpoints vs PHP templates)
- Clear separation of concerns (API vs presentation)

### User Experience
- Faster page loads (cached API responses)
- Instant UI updates without page reload
- Progressive enhancement possible

### Developer Experience
- REST APIs self-documenting
- Easy to add new features (just update endpoints)
- Frontend developers can work independently

---

## Estimated Effort

- **Phase 1 (Critical):** 16-20 hours (⏳ 8-12 hours remaining)
  - ✅ event-form.php refactor: 4 hours [COMPLETE]
  - ❌ dashboard.php refactor: 8 hours [NEXT]
  - ✅ New REST endpoint (GET /ftt/v1/groups): 2 hours [COMPLETE]
  - ❌ Dashboard REST endpoint: 2 hours
  - ❌ Testing: 4 hours

- **Phase 2 (High Value):** 8-12 hours
  - family-management.php: 6 hours
  - groups.php: 4 hours
  - Testing: 2 hours

- **Phase 3 (Polish):** 4-6 hours
  - Remaining simple pages: 3 hours
  - Testing: 2 hours

**Total Estimated Effort:** 28-38 hours  
**Completed So Far:** ~6 hours  
**Remaining:** ~22-32 hours

---

## Current Status Summary

✅ **Completed:**
- calendar.php children loading (via REST)
- REST API security fixes (3 locations)
- Security audit complete
- event-form.php refactor complete (GET /ftt/v1/groups endpoint added)

⏳ **In Progress:**
- Phase 1 refactoring (3 of 4 complete)

❌ **Remaining:**
- 9 templates still using direct PHP calls
- ~59 direct database call instances (down from 71+)
- 3-4 new REST endpoints needed

**Recommendation:** Continue with dashboard.php refactoring as next critical step.
