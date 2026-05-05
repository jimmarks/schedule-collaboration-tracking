# Groups REST API Debugging Plan - v3.0.24

## v3.0.24 Changes (Enhanced Debugging)

### Fixes Applied (from v3.0.23)
1. **Added `parent_count` field** to `FTT_Family_Groups::get_user_groups()` database method
2. **Added explicit type casting** (int) for all numeric fields in REST endpoint response
3. **Added comprehensive debug logging** to REST endpoint method execution

### New in v3.0.24 (Diagnostic Enhancements)
4. **Added registration debug logging** - Logs when `register_routes()` is called
5. **Added route registration confirmation** - Logs when `/groups` endpoint is registered
6. **Added custom permission callback** with detailed logging:
   - Logs when permission check is called
   - Logs user login status
   - Logs current user ID
   - Logs whether permission granted/denied
7. **Added JavaScript URL debugging** - Console logs showing:
   - `fttData.restUrl` value
   - Full URL being requested
   - Nonce value being sent

## Testing Steps

### 1. Deploy v3.0.24
```bash
# Upload download/schedule-collaboration-tracking-v3.0.24.zip
# Install via WordPress Plugins > Add New > Upload Plugin
```

### 2. Clear All Caches
**CRITICAL**: Before testing, clear:
- WordPress object cache
- WordPress REST API cache  
- Browser cache (hard refresh: Ctrl+Shift+R or Cmd+Shift+R)
- CDN cache if applicable

### 3. Test Groups Page
1. Navigate to the Groups page
2. Open browser console (F12) BEFORE loading the page
3. Refresh page (hard refresh: Ctrl+Shift+R)
4. Check console output

## Expected Debug Output

### Browser Console (JavaScript)
```javascript
=== GROUPS REST DEBUG ===
fttData.restUrl: https://www.familytraveltracker.app/remove-legacy/wp-json/ftt/v1/
Full URL: https://www.familytraveltracker.app/remove-legacy/wp-json/ftt/v1/groups
Nonce: abc123def456...
========================
Groups loaded: {...}
```

### WordPress Debug Log (PHP)
Location: `wp-content/debug.log`

**Expected sequence:**
```
[timestamp] FTT REST: register_routes() called - registering all endpoints
[timestamp] FTT REST: /groups endpoint registered
[timestamp] FTT REST: check_groups_permission() called
[timestamp] FTT REST: is_user_logged_in = true
[timestamp] FTT REST: current_user_id = 2
[timestamp] FTT REST: Permission GRANTED
[timestamp] FTT REST: get_user_groups() called
[timestamp] FTT REST: user_id = 2
[timestamp] FTT REST: Got 1 groups from database
[timestamp] FTT REST: primary_group_id = 14
[timestamp] FTT REST: Formatting group 14 - Marks Home
[timestamp] FTT REST: Formatted group data: {"id":14,"name":"Marks Home",...,"billing":{...}}
[timestamp] FTT REST: Returning response: {"groups":[...],"primary_group_id":14}
```

## What to Look For

### Expected Browser Console Output
```javascript
Groups loaded: {
    groups: [
        {
            id: 14,  // INTEGER not string
            name: "Marks Home",
            description: "",
            color: "#6A3E8E",
            child_count: 2,
            parent_count: 2,
            member_count: 4,
            planned_children: 0,
            group_token: "abc123...",
            is_primary: true,
            can_manage: true,
            billing_owner: 2,
            billing: {
                status: "active",
                // ... billing details
            }
        }
    ],
    primary_group_id: 14
}
```

### RED FLAGS - Should NOT See
- ❌ `created_by` field in response
- ❌ `created_at` field in response
- ❌ `{success: true}` wrapper around response
- ❌ `id` as string `"14"` instead of integer `14`
- ❌ Missing `billing` object
- ❌ Missing `group_token` field
- ❌ JavaScript error: "Cannot read properties of undefined"

## Troubleshooting Scenarios

### Scenario A: No REST Logs Appear At All
**Symptoms:** No "FTT REST:" messages in debug.log

**Possible Causes:**
1. REST endpoint not registered (look for "register_routes() called" log)
2. Different URL being called (check browser console URL debug output)
3. Request not reaching WordPress REST API
4. PHP fatal error preventing REST class from loading

**Actions:**
1. Check if browser console shows the correct URL: `/wp-json/ftt/v1/groups`
2. Manually test REST endpoint: Visit `https://yoursite.com/wp-json/ftt/v1/groups` in browser
3. Check for PHP fatal errors earlier in debug.log
4. Verify `FTT_REST::init()` is being called

### Scenario B: Permission Logs Appear But get_user_groups() Doesn't
**Symptoms:** See "check_groups_permission() called" but NOT "get_user_groups() called"

**Possible Causes:**
1. Permission callback returning false
2. Route callback not properly configured
3. PHP error in get_user_groups() method

**Actions:**
1. Check permission logs - does it say "Permission GRANTED"?
2. If "Permission DENIED", check user login status
3. If granted but still not running, check for PHP errors

### Scenario C: All Logs Appear But Browser Shows Wrong Data
**Symptoms:** Debug log shows formatted data, browser console shows raw data

**Possible Causes:**
1. **Old cached response** being served
2. WordPress filter modifying response
3. Different endpoint being called
4. JavaScript receiving old AJAX response

**Actions:**
1. **HARD REFRESH browser** (Ctrl+Shift+R or Cmd+Shift+R)
2. Clear WordPress object cache
3. Compare URL in console vs. log timestamp
4. Check for `rest_post_dispatch` or `rest_pre_serve_request` filters

### Scenario D: {success: true} Wrapper Still Present
**Symptoms:** Response has `{success: true, groups: [...]}`

**Possible Causes:**
1. Not using REST endpoint (using old AJAX handler)
2. jQuery AJAX success/error wrapper
3. WordPress filter adding wrapper

**Actions:**
1. Check browser Network tab - is request going to `/wp-json/` or `/wp-admin/admin-ajax.php`?
2. Verify no `wp_ajax_` actions registered for groups
3. Check response in Network tab vs. console.log output

## Critical Checks

### Check 1: Verify REST URL
In browser console, you should see:
```
Full URL: https://yoursite.com/wp-json/ftt/v1/groups
```

NOT:
- ❌ `https://yoursite.com/wp-admin/admin-ajax.php?action=get_groups`
- ❌ `https://yoursite.com/ftt/v1/groups` (missing /wp-json/)
- ❌ Any URL with `/wp-json/ftt/v1//groups` (double slash)

### Check 2: Verify Endpoint Registration
First log entry should be:
```
FTT REST: register_routes() called - registering all endpoints
FTT REST: /groups endpoint registered
```

If missing: REST routes not being registered at all.

### Check 3: Verify Method Execution
Look for the complete sequence:
```
check_groups_permission() → get_user_groups() → Formatting group → Returning response
```

If broken at any point, that's where the issue is.

## Expected Outcome
After deploying v3.0.24:
1. Browser console shows correct REST URL being called
2. Debug log shows complete execution trace from permission check to response
3. Browser receives formatted response with all required fields
4. Groups page renders without errors
5. All numeric fields are integers, not strings
6. No raw database fields (created_by, created_at) in response
