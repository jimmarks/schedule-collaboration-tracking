# REST API Refactoring - Testing Plan
**Branch:** rest-refactor  
**Date:** May 3, 2026  
**Objective:** Validate all REST refactoring changes before merging to main

---

## 🎯 Testing Objectives

### Primary Goals
1. ✅ **Security Validation** - Verify no admin bypasses, group-based access enforced
2. ✅ **Functionality** - All features work as before (no regressions)
3. ✅ **Performance** - Pages load correctly, no JavaScript errors
4. ✅ **Data Integrity** - Correct data displayed for each user/role

---

## 🔐 Security Testing (CRITICAL)

### Test 1: Admin Role Restrictions
**Scenario:** Admin user should NOT see all children/events on frontend

**Test Steps:**
1. Login as admin user (ID=2)
2. Navigate to dashboard
3. Verify: Only sees children from their groups (IDs 50, 51)
4. Navigate to event form
5. Verify: Only sees their children in dropdown (50, 51)
6. Navigate to calendar
7. Verify: Filter shows only their children (50, 51)

**Expected Result:** Admin restricted to group membership, no "everyone else" option

**Files to Check:**
- templates/dashboard.php (line 14: is_admin = false)
- templates/event-form.php (loads via REST)
- assets/js/main.js (loadEventFormData, loadChildrenFilter)

**Debug Commands:**
```bash
# Check browser console for:
# "FTT REST get_events - User ID: 2, is_parent: true"
# "Children loaded: [50, 51]"
```

---

### Test 2: REST API Security Enforcement
**Scenario:** REST endpoints enforce group-based access

**Test API Calls:**
```bash
# Get events (should only show user's group events)
curl -X GET "http://localhost/wp-json/ftt/v1/events" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  --cookie "wordpress_logged_in_HASH=YOUR_COOKIE"

# Get children (should only show user's children)
curl -X GET "http://localhost/wp-json/ftt/v1/children" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  --cookie "wordpress_logged_in_HASH=YOUR_COOKIE"

# Get groups (should only show user's groups)
curl -X GET "http://localhost/wp-json/ftt/v1/groups" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  --cookie "wordpress_logged_in_HASH=YOUR_COOKIE"
```

**Expected Results:**
- Events: Only events from user's groups
- Children: Only user's actual children (if parent) or self (if member)
- Groups: Only groups user belongs to

**Debug Logging:**
Check error_log for:
```
FTT REST get_events - User ID: X, is_parent: true/false
FTT get_user_children: user_id=X, children=Array([...])
FTT is_parent: user_id=X, count=1
```

---

## 🧪 Functional Testing

### Test 3: Event Form (templates/event-form.php)
**Status:** Fully refactored to REST

**Test Steps:**
1. Navigate to /ftt-manage-events/
2. Verify children checkboxes appear (Loading → actual names)
3. Verify group dropdown populated
4. If multiple children: Verify "Family Event" option appears
5. Create new event
6. Verify event saves correctly
7. Edit existing event
8. Verify data loads correctly

**Expected Behavior:**
- Children load via GET /ftt/v1/children
- Groups load via GET /ftt/v1/groups
- Form submits via POST /ftt/v1/events
- No PHP errors, no JavaScript console errors

**Files Changed:**
- templates/event-form.php (lines 85-105)
- assets/js/main.js (lines 305-400, loadEventFormData method)

---

### Test 4: Calendar (templates/calendar.php)
**Status:** Partially refactored (children filter via REST)

**Test Steps:**
1. Navigate to /ftt-calendar/
2. Verify child filter dropdown appears
3. Verify filter populated with user's children
4. Select a child filter
5. Verify events filtered correctly
6. Verify events load and display

**Expected Behavior:**
- Child filter loads dynamically via GET /ftt/v1/children
- Calendar renders after filter loads (renderCalendar called)
- Events fetch shows correct filtering in console logs
- No "all children" option for admin users

**Files Changed:**
- templates/calendar.php (lines 156-170 - empty filter div)
- assets/js/main.js (lines 64-135 - loadChildrenFilter method)

---

### Test 5: Dashboard (templates/dashboard.php)
**Status:** Security fix only (admin bypass removed)

**Test Steps:**
1. Navigate to /ftt-dashboard/ or /
2. As admin: Verify no special access
3. As parent: Verify children list shows
4. As member: Verify member view
5. Verify group selector works (if multiple groups)
6. Verify quick actions work

**Expected Behavior:**
- Admin sees same view as regular parent (no bypass)
- Children card shows only user's children
- Travel dashboard shows correct data
- No errors in console

**Files Changed:**
- templates/dashboard.php (line 14: is_admin = false)

---

### Test 6: Trial Expired Page (templates/trial-expired.php)
**Status:** Fully refactored

**Test Steps:**
1. Navigate to /ftt-trial-expired/
2. Verify trial end date loads (may show "Loading..." briefly)
3. Verify pricing cards display
4. Click "Continue - Monthly" or "Continue - Annual"
5. Verify redirects to Stripe checkout

**Expected Behavior:**
- Trial end date populated via GET /ftt/v1/groups
- Buttons work correctly
- Group ID determined from REST response

**Files Changed:**
- templates/trial-expired.php (lines 15-28, script section)

---

### Test 7: Event View (templates/event-view.php)
**Status:** Group name loaded via REST

**Test Steps:**
1. Navigate to specific event: /ftt-view-event/?event_id=123
2. Verify event details display
3. Verify "Family Group" field shows group name (not "Loading...")
4. Verify all other fields display correctly

**Expected Behavior:**
- Group name loads via GET /ftt/v1/groups
- Replaces "Loading..." with actual group name
- If not in group, field hides

**Files Changed:**
- templates/event-view.php (lines 61, 132-135, end script)
- includes/rest.php (format_event method - adds group_id/group_name)

---

### Test 8: Onboarding (templates/onboarding.php)
**Status:** Fully refactored

**Test Steps:**
1. Navigate to /ftt-onboarding/
2. Step 1: Verify calendar setup options
3. Step 2: Verify pricing displays with trial info
4. Step 3: Verify profile fields pre-populated (if data exists)
5. Complete onboarding flow
6. Verify redirect to groups page

**Expected Behavior:**
- Group name placeholder becomes actual name
- Trial end date updates from REST data
- Airport and timezone pre-filled from GET /ftt/v1/user-preferences
- No errors during data loading

**Files Changed:**
- templates/onboarding.php (lines 20-28, 30-38, 45-52, end script)

---

### Test 9: Groups Page (templates/groups.php)
**Status:** Simplified (security confirmed)

**Test Steps:**
1. Navigate to /ftt-groups/
2. Verify groups list displays
3. Verify billing status shows correctly
4. Verify "View Calendar" links work
5. Verify "Manage" button appears only for group managers
6. Create new group
7. Verify new group appears in list

**Expected Behavior:**
- Groups load (may still use PHP method - that's OK)
- No unauthorized access to other groups
- Calendar tokens generated correctly
- Billing status displays accurately

**Files Changed:**
- templates/groups.php (lines 18-23 - initialization)

---

## 🚀 Performance Testing

### Test 10: Page Load Times
**Metrics to Check:**

| Page | Before | After | Target |
|------|--------|-------|--------|
| Calendar | ? | ? | < 2s |
| Event Form | ? | ? | < 2s |
| Dashboard | ? | ? | < 2s |
| Event View | ? | ? | < 1s |

**Measurement:**
1. Open browser DevTools → Network tab
2. Clear cache
3. Navigate to page
4. Record "DOMContentLoaded" time
5. Record "Load" time
6. Check for slow API calls (> 500ms)

**Expected:**
- Calendar may be slightly slower (JS data loading)
- Event form should be faster (no PHP queries)
- Dashboard unchanged (still uses PHP)
- Event view slightly slower (group name fetch)

---

### Test 11: Browser Console Errors
**Check for JavaScript Errors:**

1. Open all refactored pages with DevTools console open
2. Look for any errors or warnings
3. Common issues to watch for:
   - `Cannot read property 'X' of undefined`
   - `fttData is not defined`
   - `Uncaught TypeError`
   - Failed fetch requests (404, 401, 500)

**Expected:** Zero errors on all pages

---

## 🔍 Database Query Testing

### Test 12: Query Count Comparison

**Before Refactoring:**
```sql
-- Check query log for direct calls
-- event-form.php: 6 queries (children, groups, meta)
-- calendar.php: 5 queries (children, groups, categories)
```

**After Refactoring:**
```sql
-- Check query log
-- event-form.php: 0 direct PHP queries (all REST)
-- calendar.php: 3 queries (groups, categories - not refactored yet)
```

**How to Test:**
1. Install Query Monitor plugin
2. Navigate to refactored pages
3. Check "Queries" panel
4. Count queries that mention:
   - `wp_usermeta` with `ftt_` keys
   - `wp_ftt_family_groups`
   - `wp_ftt_group_members`

---

## 🐛 Known Issues & Edge Cases

### Edge Case 1: No Groups
**Scenario:** User not in any groups

**Expected Behavior:**
- Event form: No group selector, shows error/info message
- Calendar: Shows no events
- Dashboard: Shows "create group" CTA

**Test:** Create new user with no group membership

---

### Edge Case 2: Multiple Groups
**Scenario:** User in 3+ groups

**Expected Behavior:**
- Groups dropdown shows all groups
- Primary group selected by default
- Can switch between groups
- Events filtered by selected group

**Test:** Add user to multiple groups, verify switching

---

### Edge Case 3: Both Parent & Member
**Scenario:** User is parent in one group, member in another

**Expected Behavior:**
- Sees both parent view (children) and member view (self)
- Correct role detection in REST APIs
- No data leakage between roles

**Test:** Complex group setup, verify data isolation

---

## 📝 Test Checklist

### Pre-Merge Validation

- [ ] All security tests pass
- [ ] No admin bypass present
- [ ] REST endpoints return correct data
- [ ] Event form works (create, edit, delete)
- [ ] Calendar displays and filters correctly
- [ ] Dashboard shows correct user data
- [ ] All refactored pages load without errors
- [ ] No JavaScript console errors
- [ ] Browser DevTools shows correct API calls
- [ ] Database queries reduced on refactored pages
- [ ] Error logs show correct debug messages
- [ ] Edge cases handled gracefully

### User Acceptance Testing

- [ ] Admin user (ID=2) only sees their children (50, 51)
- [ ] Parent user can create events for their children
- [ ] Member user can create events for themselves
- [ ] Group filtering works correctly
- [ ] Trial expired page shows correct data
- [ ] Onboarding flow completes successfully
- [ ] Groups page displays billing correctly
- [ ] Event view shows all details including group name

---

## 🚦 Go/No-Go Criteria

### ✅ MERGE TO MAIN IF:
1. All security tests pass (CRITICAL)
2. No admin bypass exists (CRITICAL)
3. Core functionality works (event CRUD, calendar, dashboard)
4. No breaking JavaScript errors
5. User (ID=2) only sees children 50, 51

### ❌ DO NOT MERGE IF:
1. Admin can see all children
2. REST APIs return unauthorized data
3. Event form broken (can't create/edit events)
4. Calendar doesn't load
5. JavaScript errors block functionality

---

## 🔧 Debugging Tools

### Browser Console Commands
```javascript
// Check REST endpoint data
fetch(fttData.restUrl + 'children', {
  headers: { 'X-WP-Nonce': fttData.nonce }
}).then(r => r.json()).then(console.log);

// Check current user context
console.log('User ID:', fttData.userId);
console.log('Is Admin:', fttData.isAdmin);
console.log('REST URL:', fttData.restUrl);

// Check loaded data
console.log('Children:', FTT.children);
console.log('Groups:', FTT.groups);
```

### PHP Debug Logging
```php
// Add to functions to debug
error_log('FTT DEBUG: ' . print_r($variable, true));

// Check logs
tail -f /var/log/apache2/error.log
# or
tail -f wp-content/debug.log
```

### SQL Query Logging
```php
// Add to wp-config.php
define('SAVEQUERIES', true);

// View queries
global $wpdb;
print_r($wpdb->queries);
```

---

## 📊 Test Results Template

```markdown
### Test Run: [Date]
**Tester:** [Name]
**Branch:** rest-refactor
**Commit:** [hash]

#### Security Tests
- [ ] Test 1: Admin restrictions - PASS/FAIL
- [ ] Test 2: REST API security - PASS/FAIL

#### Functional Tests  
- [ ] Test 3: Event form - PASS/FAIL
- [ ] Test 4: Calendar - PASS/FAIL
- [ ] Test 5: Dashboard - PASS/FAIL
- [ ] Test 6: Trial expired - PASS/FAIL
- [ ] Test 7: Event view - PASS/FAIL
- [ ] Test 8: Onboarding - PASS/FAIL
- [ ] Test 9: Groups page - PASS/FAIL

#### Performance Tests
- [ ] Test 10: Load times - ACCEPTABLE/SLOW
- [ ] Test 11: No JS errors - PASS/FAIL

#### Edge Cases
- [ ] No groups scenario - PASS/FAIL
- [ ] Multiple groups - PASS/FAIL
- [ ] Parent & member - PASS/FAIL

**Issues Found:** [List any issues]

**Recommendation:** MERGE / DO NOT MERGE / NEEDS FIXES
```

---

## 🎓 Testing Priority

### P0 (Must Pass)
1. Security Test 1 - Admin restrictions
2. Security Test 2 - REST API enforcement
3. Functional Test 3 - Event form CRUD

### P1 (Should Pass)
4. Functional Test 4 - Calendar
5. Functional Test 5 - Dashboard
6. Performance Test 11 - No JS errors

### P2 (Nice to Have)
7. All other functional tests
8. Performance metrics
9. Edge case testing

---

## 📞 Support & Escalation

**If Tests Fail:**
1. Check error logs (browser console + PHP error_log)
2. Review commit history: `git log --oneline rest-refactor`
3. Compare with main: `git diff main...rest-refactor`
4. Check specific file changes: `git diff main -- templates/event-form.php`

**Critical Issues:**
- Security bypass found → DO NOT MERGE
- Event creation broken → FIX REQUIRED
- REST API 500 errors → FIX REQUIRED

**Non-Critical Issues:**
- Slow load times → Document and optimize later
- Missing group name → Acceptable degradation
- UI glitches → Log as future enhancement

---

## ✅ Final Sign-Off

**Security Lead:** _______________ Date: _______  
**QA Lead:** _______________ Date: _______  
**Product Owner:** _______________ Date: _______  

**Approval to Merge:** YES / NO

**Notes:**
