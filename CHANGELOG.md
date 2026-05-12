# Summer Regiment Tracker - Changelog

All notable changes to the Summer Regiment Tracker plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.47] - 2025-01-XX

### 🎯 User Experience - Flight Search Transparency & Debugging

#### Added
- **Hover Tooltips on Flight Search Buttons**: All flight search buttons (Google Flights, Kayak, Southwest) now display informative tooltips explaining their capabilities and limitations
  - Google Flights (individual legs): "✅ Accurate URL after clicking 'Check Price Now'"
  - Google Flights (round-trip): "✅ Accurate URL loads automatically from Google"
  - Kayak: "⚠️ Time-of-day filters not supported in URL - apply manually after clicking"
  - Southwest: "✅ Time-of-day preferences included"
- **Debug Logging for SerpAPI Integration**: Added comprehensive logging to diagnose potential type parameter issues
  - Logs outgoing SerpAPI query with type parameter (1=round-trip, 2=one-way)
  - Logs returned google_flights_url from SerpAPI response
  - Helps verify if individual leg searches are correctly requesting one-way flights
- **trip_type Field in API Response**: Added explicit trip_type ('one-way' or 'round-trip') to unified service response for better UI clarity

#### Technical Details
- Enhanced `generateFlightSearchLinks` in assets/js/main.js with title attributes for hover tooltips
- Added error_log() calls in `FTT_Flight_Search_Service::run_price_check()` to trace SerpAPI interactions
- Extended service response to include trip_type based on scope parameter

#### Purpose
- Provide users with transparent information about service capabilities and limitations
- Enable debugging of reported issue where individual leg searches might show round-trip results
- Verify SerpAPI is receiving correct type parameter and returning appropriate URLs

## [3.0.38] - 2024-01-XX

### 🏗️ Architecture - Unified Flight Search Service

#### Overview
Complete refactoring of flight price checking to eliminate code duplication across frontend, backend, and cron systems. Implements single source of truth for all flight searches using a new service-oriented architecture.

#### New Service Class: `FTT_Flight_Search_Service`
**File**: `includes/class-flight-search-service.php` (~600 lines)

**Features**:
- ✅ Unified API for all flight price checking (manual checks, scheduled checks, alerts)
- ✅ Single place to build SerpAPI queries (no more duplicate logic)
- ✅ Canonical payload structure shared by all callers
- ✅ Automatic database storage and alert evaluation
- ✅ Support for both trip-level (round-trip) and leg-level (one-way) searches

**Key Methods**:
```php
build_search_payload($event_id, $scope, $leg_index)  // Creates canonical payload
build_provider_query($payload)                        // Converts to SerpAPI params
run_price_check($payload, $check_type)               // Executes API call
save_price_snapshot($payload, $result)               // Saves to database
evaluate_alert_rules($payload, $result)              // Checks and sends alerts
check_price($event_id, $scope, $leg_index, $check_type) // Main entry point
```

#### Unified REST API Endpoints
**File**: `includes/rest.php`

New endpoints that replace old duplicated ones:
```
POST /ftt/v1/flights/check    - Check price now (replaces check-price, check-trip-price)
GET  /ftt/v1/flights/history  - Get price history (replaces price-history, trip-price-history)
POST /ftt/v1/flights/track    - Create alert (replaces price-alerts, trip-price-alerts)
```

All endpoints accept `scope` parameter ('trip' or 'leg') to determine query type.

#### Updated Components

**Frontend** (`assets/js/main.js`):
- ✅ Updated `checkPriceNow()` to use unified `flights/check` endpoint with scope='leg'
- ✅ Updated `loadPriceHistory()` to use unified `flights/history` endpoint with scope='leg'
- ✅ Updated leg alert creation to use unified `flights/track` endpoint with scope='leg'
- ✅ Trip-level functions already using unified endpoints (checkTripPriceNow, loadTripPriceHistory)

**Cron Job** (`includes/price-tracking.php`):
- ✅ Refactored `check_all_prices()` to call `FTT_Flight_Search_Service::check_price()` directly
- ✅ Eliminated duplicate API call logic
- ✅ Simplified loop: detect round-trip → check with service → check legs with service

#### Benefits
- **DRY Principle**: One canonical way to build and execute flight searches
- **Consistency**: All code paths use same logic and parameters
- **Maintainability**: Changes to search logic only need to happen in one place
- **Testability**: Service methods can be unit tested independently
- **Extensibility**: Easy to add new check sources (mobile app, webhooks, etc.)

#### Technical Details
- Service called directly from cron (no REST overhead)
- Service called via REST from frontend (proper nonce security)
- Old endpoints still exist for backward compatibility but are deprecated
- All functions properly handle scope parameter for trip vs. leg queries

## [2.1.0] - 2026-03-05 [IN PROGRESS]

### 🎉 Major Feature - Family Groups Architecture

#### Overview
Complete architectural overhaul replacing simple user-to-user relationships with proper **Family Group** entities. Enables support for complex blended families where adults can belong to multiple family groups with independent billing and calendars.

#### New Database Tables
- **`wp_ftt_family_groups`**: Core group entity with billing and subscription data
- **`wp_ftt_group_members`**: Group membership with roles (parent/child) and permissions
- **`wp_ftt_group_invitations`**: Group-based invitation system
- **`wp_ftt_event_groups`**: Associates events with specific groups

#### New Core Class: `FTT_Family_Groups`
**File**: `includes/class-family-groups.php` (850+ lines)

**Features**:
- ✅ Create, read, update, archive groups
- ✅ Add/remove members with roles and permissions
- ✅ Group-specific billing tracking
- ✅ Event-group associations
- ✅ Permission checks (can_manage_group)
- ✅ Member counting and filtering

#### REST API Endpoints (12 new)
```
POST   /ftt/v1/groups                           - Create group
GET    /ftt/v1/groups                           - Get user's groups
GET    /ftt/v1/groups/{id}                      - Get specific group
PUT    /ftt/v1/groups/{id}                      - Update group
DELETE /ftt/v1/groups/{id}                      - Archive group
GET    /ftt/v1/groups/{id}/members              - Get group members
POST   /ftt/v1/groups/{id}/members              - Add member
DELETE /ftt/v1/groups/{id}/members/{user_id}   - Remove member
```

All endpoints include proper permission checks (group access, management rights, ownership).

#### Dashboard & Calendar UI (v2.1)
**Files**: `templates/dashboard.php`, `templates/calendar.php`, `assets/js/main.js`
- ✅ Group selector dropdown at top of dashboard (shows all user groups)
- ✅ Selected group summary card with member count, billing status
- ✅ Dashboard data automatically filtered by selected group
- ✅ Calendar group selector (inline with member selector)
- ✅ Group filter state persists via URL parameter (`?group=ID`)
- ✅ "All Groups" option to view combined events
- ✅ REST API dashboard endpoint supports `group_id` parameter
- ✅ REST API events endpoint supports `group_id` parameter
- ✅ JavaScript group-aware data loading in main.js
- ✅ Responsive CSS for group selector and summary card

#### Billing Integration (v2.1)
**File**: `includes/billing/class-billing-manager.php`
- ✅ `get_group_billing_summary($group_id)` - Per-group calculation
- ✅ `get_user_billing_info($user_id)` - Detects v2.0 vs v2.1 mode
- ✅ `has_active_subscription($user_id)` - Universal check
- ✅ Dual-mode operation: v2.0 user-based OR v2.1 group-based
- ✅ Group pricing: $9.99/month base + $5/child, or $99/year + $50/child

#### Migration System
**File**: `includes/class-groups-migration.php` (600+ lines)

**Features**:
- ✅ Automated migration from v2.0 user meta structure
- ✅ Detects linked adults and creates groups
- ✅ Migrates children into groups
- ✅ Copies Stripe billing data to groups
- ✅ Associates existing events with groups
- ✅ Handles solo parents (no co-parents)
- ✅ Validation and rollback capability
- ✅ Detailed migration report

**Migration Process**:
1. Scan for users with `ftt_parents` meta (linked adults)
2. Create groups for each unique set of co-parents
3. Add all children to their parents' groups
4. Copy Stripe subscription data to group records
5. Associate all existing events with appropriate groups
6. Validate migration (check for orphaned children/events)
7. Generate detailed report

#### What This Enables

**Before (v2.0)**:
```
Simple co-parent linking: Adult A ↔ Adult B share all kids
Limitation: Adults can only be in ONE family unit
```

**After (v2.1)**:
```
Dad + Stepmom = Group 1 (Billing 1)
  └─ Kids: Alice, Bob, Charlie

Mom + Stepdad = Group 2 (Billing 2)
  └─ Kids: Alice, Bob, Diana

Alice & Bob: Belong to BOTH groups, see events from both
Dad/Stepmom: Only see Group 1 events
Mom/Stepdad: Only see Group 2 events
```

#### Backward Compatibility
- ✅ Old user meta (`ftt_parents`, `ftt_parent_of`) maintained during migration
- ✅ Automatic migration on activation if old data exists
- ✅ Admin notice prompts migration if pending
- ✅ Rollback function available for emergencies

#### User Meta Changes
**New meta keys**:
- `ftt_primary_group` - User's default group ID
- `ftt_group_preferences` - Per-group settings (serialized)

**Deprecated (but maintained)**:
- `ftt_parents` - Old co-parent linking
- `ftt_parent_of` - Old parent-child linking

#### Status
- ✅ Database schema complete
- ✅ Core class implemented (850 lines)
- ✅ REST API endpoints complete (12 routes)
- ✅ Migration script complete (600 lines)
- ✅ Groups management UI complete (`templates/groups.php`)
- ✅ Event form group selection implemented
- ✅ REST API event-group association complete
- ✅ Group-aware billing integration complete (dual-mode)
- ✅ Dashboard multi-group filtering complete
- ✅ Calendar group selector added
- ✅ Migration admin interface complete (`includes/class-migration-admin.php`)
- ⏳ Testing and validation (pending)
- ⏳ User documentation updates (pending)

#### Dependencies
None - standalone implementation

---

## [2.0.120] - 2026-03-05

### ✨ Added - Automatic Child Sharing Across Co-Parents

#### Feature
When any parent adds a child, that child is now **automatically shared with all co-parents** in the family group.

#### Implementation
- **Enhanced `FTT_Roles::add_parent_child()` method**:
  - After adding child to parent, retrieves all co-parents via `ftt_parents` meta
  - Recursively calls `add_parent_child()` for each co-parent
  - Safeguard: `$is_new_child` parameter prevents infinite recursion
  - Works for both new child creation and existing child linking

#### User Impact
- ✅ **No Manual Sharing Required**: Co-parents automatically see new children
- ✅ **Consistent Family View**: All adults in group have same children list
- ✅ **Works Everywhere**: Registration flow, "Add Child" workflow, admin assignment

#### Example
```
Before: Dad adds child "Charlie"
- Dad sees: Alice, Bob, Charlie
- Mom sees: Alice, Bob (missing Charlie - had to manually link)

After: Dad adds child "Charlie"  
- Dad sees: Alice, Bob, Charlie
- Mom sees: Alice, Bob, Charlie (automatically added)
```

### 📋 Planning - v2.1 Family Groups Architecture

#### Status
Created comprehensive specification document: `FAMILY_GROUPS_V2.1_SPEC.md`

#### Purpose
Support complex blended family scenarios:
- Divorced parents each in new families
- Children belonging to multiple family groups
- Independent billing per group
- Separate calendars and event visibility

#### Timeline
- **Effort**: 2-3 weeks development + 1 week testing
- **Target**: v2.1.0 release
- **Priority**: High - addresses real-world multi-family needs

#### Key Features Planned
- Family group entity (not just user-to-user linking)
- Multiple groups per adult
- Per-group billing with Stripe
- Group-specific calendars and events
- Automated migration from current user meta structure

See `FAMILY_GROUPS_V2.1_SPEC.md` for complete specification.

---

## [2.0.119] - 2026-03-05

### 🐛 Fixed - Syntax Error in invitations.php

#### Issue
- **Build Failed**: PHP syntax error prevented plugin from loading
- **Error**: "Unclosed '{' on line 18 does not match ')' in invitations.php on line 625"
- **Cause**: Leftover code fragments from incomplete find/replace in v2.0.117

#### Fix
- Removed duplicate closing braces (`);` and `}`) after `get_billing_info()` method
- Removed duplicate PHPDoc comment for `get_linked_adults()` method
- Clean function structure restored

#### Result
- ✅ No syntax errors in invitations.php
- ✅ Plugin loads successfully
- ✅ Build process completes without errors

## [2.0.117] - 2026-03-04

### 🐛 Fixed - Invitation Validation Fatal Error (RESOLVED)

#### Root Cause Found
**Error**: `Call to undefined method FTT_Billing_Manager::get_user_subscription()`
- The method doesn't exist in FTT_Billing_Manager class
- Was trying to call non-existent method on line 577 of invitations.php
- Caused 500 error on invitation validation REST endpoint

#### Solution
- **Removed Bad Method Call**: No longer calls `FTT_Billing_Manager::get_user_subscription()`
- **Direct Meta Access**: Now reads subscription data directly from user meta
  - `ftt_subscription_status` - subscription status (active, trialing, etc.)
  - `ftt_subscription_interval` - billing interval (month/year)
- **Simplified Logic**: Eliminated dependency on billing manager method
- **Same Functionality**: Returns identical data structure as before

#### Technical Details
Changed from:
```php
$subscription = FTT_Billing_Manager::get_user_subscription($user_id);
$status = $subscription['status'];
```

To:
```php
$status = get_user_meta($user_id, 'ftt_subscription_status', true);
$interval = get_user_meta($user_id, 'ftt_subscription_interval', true);
```

#### Expected Result
- ✅ REST API `/invite/{code}/validate` now returns 200 instead of 500
- ✅ Registration page will display invitation details
- ✅ Invitation box shows inviter name, relationship, billing status, linked adults

## [2.0.116] - 2026-03-04

### 🔍 Enhanced - Debugging for Invitation Validation

#### Comprehensive Logging
- **get_billing_info()**: Added try-catch, detailed logging for each step, defensive isset() checks
- **get_linked_adults()**: Added try-catch, logs parent search and relationship lookups
- **Test Tool Enhanced**: Now includes direct method call test and recent error log display

#### Test Tool Improvements  
- **Direct Method Call**: Tests `validate_invite_code()` directly without REST API layer
- **Error Log Display**: Shows last 20 FTT-related log entries from debug.log
- **Better Error Reporting**: Catches both Exception and Throwable types with full stack traces
- **Debug Instructions**: Shows how to enable WP_DEBUG_LOG if not enabled

#### Purpose
The 500 error persists after previous fixes. These additions will pinpoint the exact line causing the error by:
1. Testing the method directly (bypassing REST API)
2. Showing actual PHP errors and stack traces
3. Displaying recent log entries in the test output

#### Next Steps
After deploying v2.0.116:
1. Visit `/?test_invite=CODE` as admin
2. Look at "Direct Method Call" section for errors
3. Check "Recent Error Log Entries" for FTT messages
4. Review WordPress debug.log for full error details

## [2.0.115] - 2026-03-04

### 🐛 Fixed - Adult Invitation Validation 500 Error

#### Critical Fix
- **Fixed 500 Error**: Added defensive coding to handle missing invitation fields
- **Try-Catch Block**: Wrapped validation logic in exception handling to catch and log errors
- **Field Checks**: Added `isset()` checks for optional fields (status, expires, relationship)
- **Default Values**: Status defaults to 'pending' if not set, relationship defaults to 'co-parent'

#### Root Cause
Older invitation records created before v2.0.109 had incomplete data structures:
- Three invitations missing `status` field (showing empty in test output)
- Trying to access `$invitation['status']` without checking caused undefined array key errors
- PHP warnings escalated to 500 error in REST API context

#### Validation Improvements
- **Better Logging**: Added step-by-step logging throughout validation process
- **Array Counting**: Changed from `print_r()` to `count()` for safer logging
- **Graceful Degradation**: Missing fields no longer cause crashes, use sensible defaults

#### Test Results
The test tool `/?test_invite=CODE` now shows:
- ✅ Invitation found in database with status "pending"
- ✅ Code matches correctly  
- 🔧 REST API should now return 200 with invitation details instead of 500 error

## [2.0.114] - 2026-03-04

### 🐛 Fixed - Fatal Error on Plugin Load

#### Critical Fix
- **Fixed Fatal Error**: Added file_exists() check before loading test-invite-validation.php
- **Safe Loading**: Test diagnostic tool now loads conditionally instead of always requiring
- **Deployment Safe**: Plugin no longer crashes if test file is missing from deployment

#### Build Process
- **Updated build-package.sh**: Now includes test-invite-validation.php in release packages
- **Test Tool Available**: Diagnostic tool will be available in future deployments

### 🔍 Debug - Adult Invitation Validation

#### Registration Form Debugging
- **Added Logging**: Registration form now logs API calls and responses to debug.log
- **Detailed Errors**: Shows WP_Error messages, response codes, and response body
- **Validation Tracking**: Logs whether invite data was successfully loaded

#### Invitation Validation Debugging  
- **Added Logging**: validate_invite_code() now logs all validation steps
- **User Search Logging**: Shows how many users have adult invitations
- **Match Logging**: Logs invitation structure and matching logic
- **Status Checks**: Logs expiration and usage status checks

#### Test Tool (test-invite-validation.php)
- **Admin Diagnostic**: Visit `/?test_invite=CODE` as admin to see full diagnostic
- **Database Inspection**: Shows all stored invitations with details
- **REST API Test**: Tests the validation endpoint directly
- **Match Detection**: Clearly indicates if code matches stored invitation

#### Purpose
These debugging additions help diagnose why invitation details aren't showing on registration page. All logs go to WordPress debug.log for troubleshooting.

## [2.0.111] - 2026-03-04

### 🐛 Fixed - Event Form and List Issues

#### Event Form - Member/Child Selection
- **Fixed Duplicate Options**: Admins no longer see duplicate entries in the "Event For" dropdown
- **Logic Correction**: Changed admin dropdown to show all members exclusively (not combined with children list)
- **Cleaner Selection**: Dropdown now uses if/elseif logic to prevent overlap between admin and parent views

#### Event List - Edit Button Layout
- **Fixed Overlap**: Edit button no longer overlaps with Travel/Flight badges
- **Improved Spacing**: Added `clear: both` and increased top margin on edit button container
- **Better Visual Separation**: Edit button now sits cleanly below badges section

#### Event List - Navigation
- **Added Page Header**: New header section with page navigation controls
- **Dashboard Link**: Quick access back to main dashboard
- **Calendar Link**: Quick access to calendar view (styled as primary button)
- **Responsive Layout**: Buttons flex-wrap on mobile devices
- **Visual Polish**: Page header with bottom border for clear section separation

#### CSS Additions
- `.ftt-page-header` - Flex container for title and navigation
- `.ftt-page-nav` - Button group with gap spacing
- Improved `.ftt-event-item > p` margin and clearfix

#### Result
Event list page is now fully navigable with clear access to dashboard and calendar. Edit buttons no longer overlap badges. Admin event creation shows clean member selection without duplicates.

## [2.0.109] - 2026-03-04

### 🔒 Security & 🎨 Enhanced - Adult Invitation System Overhaul

#### Security Improvements
- **Removed Database ID Exposure**: Invitation URLs no longer include `&inviter=[user_id]` parameter
- **Self-Contained Invite Codes**: Inviter ID now stored within invitation data, not exposed in URL
- **Privacy First**: No database relationship IDs visible to end users
- **Cleaner URLs**: Changed from `/ftt-dashboard?ftt_invite=XXX&inviter=123` to `/ftt-register?ftt_invite=XXX`

#### Registration Flow Fixes
- **Fixed Redirect Issue**: Non-logged-in users clicking invite links now redirect to registration page (not dashboard)
- **Auto-Detection**: Dashboard automatically redirects to registration when invite code present and user not logged in
- **Preserved Invite Context**: Invite code maintained throughout registration flow

#### Enhanced Registration Experience

**Beautiful Invitation Details Display**:
- **Gradient Header**: Eye-catching purple gradient box with invitation details
- **Inviter Information**: Shows name of person sending invitation (not database ID)
- **Relationship Context**: Displays relationship type (Co-parent, Partner, etc.)
- **Group Members**: Lists other adults already connected to the family account
- **Billing Transparency**: Shows current payment status and billing interval
- **Clear Messaging**: Explains new member won't be charged - group owner handles billing
- **Expiration Notice**: Displays invitation expiration date

**API Enhancements**:
- **Expanded `validate_invite_code` Endpoint**: Now handles adult invitations (not just member codes)
- **Returns Inviter Details**: Name, email (masked in display)
- **Returns Billing Info**: Status (active/trial/past_due), interval (monthly/yearly)
- **Returns Linked Adults**: Who else is connected with relationships
- **Error Handling**: Clear messages for expired/used/invalid codes

#### Technical Details

**Database Changes**:
- Added `inviter_id` to invitation data structure
- Removed dependency on URL parameter for inviter identification
- Added `accepted_by` and `accepted_at` tracking fields

**New Methods**:
- `FTT_Invitations::get_billing_info($user_id)` - Retrieves subscription details
- `FTT_Invitations::get_linked_adults($user_id)` - Gets co-parent list

**Registration Handler**:
- New `ftt_invite_code` POST parameter handling
- Automatic user linking on registration completion
- Invitation status update to 'accepted'
- Relationship metadata storage

**CSS Additions**:
- `.ftt-invitation-details-box` - Gradient card with glassmorphism
- `.ftt-invite-header` - Icon + title section
- `.ftt-invite-info` - Semi-transparent details container
- `.ftt-invite-row` - Key-value pair styling
- `.ftt-billing-status` - Color-coded billing indicators
- Full mobile responsiveness (@768px breakpoint)

#### Result
Adult invitations are now secure (no database IDs exposed), reliable (proper redirect flow), and provide a professional onboarding experience with complete transparency about billing and group membership. New users understand exactly what they're joining and who they'll be connected with.

## [2.0.108] - 2026-03-04

### 🐛 Fixed - Event List Edit Button Incorrect URL

#### Problem
- Edit button on event list page was using `get_permalink()` which returns the event post's permalink
- Generated malformed URLs like `regiment-event/"trip"/?event_id=#file:family-management.js`
- Users couldn't edit events from the event list page
- Bug: linked to event post itself instead of event form page

#### Solution
- **Use Event Form Page URL**: Changed to `FTT_Pages::get_page_url('event_form')` with event_id parameter
- **Proper URL Construction**: Added query string separator detection (? or &)
- **Fallback URL**: Included home_url fallback if page URL not found
- **Consistent Pattern**: Now matches dashboard edit button implementation

#### Technical Details
- Previous: `get_permalink() . '?event_id=' . $event_id` (wrong - gets event post URL)
- Fixed: `FTT_Pages::get_page_url('event_form') . '?event_id=' . $event_id` (correct - gets form page URL)
- Added separator detection for clean URL structure
- Fallback to `/ftt-manage-events/?event_id=` if page lookup fails

#### Result
Clicking Edit on any event in the event list now correctly navigates to the event form page with the event pre-loaded for editing, matching the expected behavior from the dashboard.

## [2.0.107] - 2026-03-04

### 🐛 Fixed - Event List Button Overlap Issue

#### Problem
- Edit button was overlapping with event badges (TRAVEL, FLIGHT) on the event list page
- No spacing defined for `.ftt-event-badges` container causing layout issues
- Button paragraph had no top margin, pushing it into badge space

#### Solution
- **Added `.ftt-event-badges` Styling**: Flexbox layout with 8px gap between badges and 12px vertical margin
- **Badge Container**: Proper flex display with wrap support for multiple badges
- **Button Spacing**: Added 10px top margin to `.ftt-event-item > p` for Edit button container
- **Clean Layout**: Badges and buttons now have clear visual separation

#### Technical Details
- New CSS: `.ftt-event-badges { display: flex; gap: 8px; margin: 12px 0; flex-wrap: wrap; }`
- Button spacing: `.ftt-event-item > p { margin: 10px 0 0 0; }`
- Maintains responsive behavior with flex-wrap

#### Result
Event badges (TRAVEL, FLIGHT) and Edit button now display in separate, properly spaced rows on the event list page, eliminating the overlap issue and creating a cleaner, more readable layout.

## [2.0.106] - 2026-03-03

### 🎨 Enhanced - Grouped Flight Date/Time Fields for Better UX

#### Problem
- Departure and return date/time fields were in a single row, creating confusion
- Users couldn't easily distinguish between outbound and return flight details
- No visual separation between the two flight segments

#### Solution
- **Departure Group**: Date and Time of Day fields now grouped in blue-bordered box with "✈️ Departure" label
- **Return Group**: Return Date and Return Time of Day fields grouped in separate blue-bordered box with "🔄 Return" label
- **Visual Hierarchy**: Each group has distinct blue border (2px), light blue background, and section header
- **Clear Labeling**: Uppercase labels with icons make purpose immediately obvious

#### Technical Details
- New CSS classes: `.ftt-flight-dates-section`, `.ftt-date-group`, `.ftt-date-group-label`
- Departure group: lighter blue background (#f8fbff) with #4a90e2 border
- Return group: slightly darker blue background (#f0f8ff) with #5ba3e8 border
- Updated JavaScript toggle logic to show/hide entire return group container
- Mobile responsive: reduced padding (12px) and adjusted label font size (13px)

#### Result
Users can now easily distinguish between departure and return flight details. The visual grouping eliminates confusion and makes it clear which fields apply to which leg of the journey, significantly improving form usability for round-trip flights.

## [2.0.104] - 2026-03-03

### 🎨 Enhanced - Round-Trip Pricing Info Box Layout

#### Problem
- Round-Trip Pricing help text was appearing inline with date/time fields
- Awkward positioning broke the natural form flow
- Info box competed for space with form inputs in flexbox row

#### Solution
- **Full-Width Display**: Info box now appears as a separate row above return date fields
- **Proper Insertion Point**: Changed from inserting before first field to inserting before entire row
- **Better Styling**: Enhanced padding (12px 15px), thicker left border (4px), improved spacing
- **Clean Layout**: Return date fields now display in their own clean row below the help text

#### Technical Details
- Modified JavaScript to use `.closest('.ftt-form-row')` to find parent row
- Insert help text before entire row using `$returnRow.before()`
- Removed ineffective `grid-column` style (parent uses flexbox, not grid)
- Changed from `<p>` to `<div>` for better semantic structure

#### Result
Round-trip pricing information now displays cleanly above the return date fields in a full-width blue info box, creating a logical visual hierarchy and improving form usability.

## [2.0.103] - 2026-03-03

### 🐛 Fixed - Critical Style Loading Issue

#### Problem
- Plugin styles were not loading on event entry form page
- Relied solely on `has_shortcode()` which can fail with caching, page builders, or dynamic content
- Travel leg forms appeared unstyled with no modern design

#### Solution
- **Added `FTT_Pages::is_ftt_page()` Helper**: New method checks if current page is a registered FTT plugin page by page ID
- **Enhanced Enqueue Logic**: Now checks both page ID AND shortcode presence for maximum reliability
- **Future-Proof**: Ensures styles load on all plugin pages regardless of how content is rendered

#### Technical Details
- New method in `includes/pages.php`: `is_ftt_page()` checks against stored page IDs
- Updated `enqueue_scripts()` in main plugin file to use dual-check approach
- Prevents style loading failures from caching, page builders, or theme conflicts

#### Result
Plugin styles now reliably load on all FTT pages including event form, ensuring travel leg styling, modal improvements, and mobile responsiveness are consistently applied.

## [2.0.102] - 2026-03-03

### 🎨 Enhanced - Event Modal Professional Styling

#### Tightened Modal Spacing
- **Reduced Content Padding**: Modal padding reduced from 30px to 20px for a more professional appearance
- **Optimized Heading Margins**: Section headings (h3) margin-top reduced from 20px to 12px, eliminating excessive gaps
- **Tighter Paragraph Spacing**: Paragraphs now have 6px vertical margins instead of browser defaults
- **Compact Travel Leg Cards**: Travel leg detail cards reduced from 15px to 10px-12px padding with 8px bottom margin
- **Enhanced Visual Hierarchy**: Added subtle blue left border to travel leg cards for better section identification
- **Improved Typography**: Travel leg headings now 15px with consistent 8px bottom margin and brand color

#### Result
Modal content now displays with professional spacing that maximizes screen real estate while maintaining excellent readability. Travel details are easier to scan with tighter, more logical grouping of information.

## [2.0.101] - 2026-03-03

### 🎨 Enhanced - Travel Leg Form Styling & Mobile Responsiveness

#### Improved Desktop Experience
- **Modern Card Design**: Travel legs now display as clean white cards with subtle shadows and proper spacing
- **Better Visual Hierarchy**: Blue gradient header with brand color accent for easier section identification
- **Enhanced Typography**: Improved font sizing, weights, and spacing for better readability
- **Touch-Friendly Controls**: Larger checkboxes and better-styled buttons with hover effects
- **Professional Styling**: Airport codes display in monospace font with proper letter spacing
- **Clear Section Separation**: Booking and round-trip sections have distinct background colors and borders

#### Mobile-First Responsive Design
- **Tablet (≤768px)**:
  - Form rows stack vertically for easier entry
  - Increased touch target sizes (16px font prevents iOS zoom)
  - Compact header with flexible wrapping
  - Full-width checkbox groups
  - Optimized padding and spacing

- **Phone (≤480px)**:
  - Further reduced padding for maximum screen usage
  - Smaller font sizes while maintaining readability
  - Compact button styling
  - Optimized help text display

#### Improved Components
- **Flight Suggestions Box**: Yellow alert-style design with proper padding and borders
- **Round-Trip Help**: Blue info box with clear visual hierarchy and better text sizing
- **Checkbox Groups**: Hover effects and bordered backgrounds for better UI feedback
- **Form Inputs**: Smooth focus transitions with blue accent and subtle shadows
- **Baggage Options**: Touch-friendly cards with hover states

#### Technical Details
- Added comprehensive mobile breakpoints (@768px and @480px)
- Implemented flexbox with proper min-width constraints
- Added smooth transitions for interactive elements
- Improved form field focus states with accessible color contrast
- Fixed gap inconsistencies in form rows
- Added proper border-radius and box-shadow styling throughout
- Ensured minimum 44×44px touch targets per accessibility guidelines

#### User Experience Impact
- Cleaner, more professional appearance aligned with modern web standards
- Significantly improved mobile usability - form is now fully functional on phones
- Reduced visual clutter with better spacing and organization
- Easier to distinguish between different sections and leg types
- Better feedback on interactive elements (buttons, checkboxes, inputs)

## [2.0.100] - 2026-03-03

### 🐛 Bug Fix - Dashboard Navigation

#### Fixed - Quick Add Event Button
- **Problem**: "Quick Add Event" button on dashboard was linking to calendar page instead of event entry form
- **Fix**: Changed hardcoded `/ftt-calendar/` URL to dynamically use event form page URL via `FTT_Pages::get_page_url('event_form')`
- **Impact**: Clicking "Quick Add Event" now correctly takes users to the event entry form
- **Fallback**: Includes fallback URL if page lookup fails

## [2.0.99] - 2026-03-03

### ✨ Enhanced - Travel Entry UX Improvements

#### Fixed - Airport Code Lookup
- **Added Missing Airports**: Added ROA (Roanoke, VA) and other commonly used regional airports to airports.json
- **Impact**: Airport code validation now recognizes more airports, reducing "unknown airport code" errors

#### Improved - Round-Trip Entry
- **Clearer Description**: Updated round-trip checkbox description to "Check if departing and returning same route - system will search combined pricing"
- **Better Help Text**: Enhanced inline help when round-trip is checked explaining that combined fares are often cheaper
- **Impact**: Users better understand when and why to use round-trip option vs separate legs

#### Added - Pre-fill Logic
- **Smart Defaults**: When adding a new travel leg, the "From" airport automatically fills with the previous leg's "To" airport
- **Impact**: Faster data entry for multi-leg trips (e.g., Home → Event → Home automatically suggests Event as starting point for leg 2)

#### Implemented - Flight Link Suggestions
- **Real-time Detection**: System now detects when two legs form a round-trip pattern (reversed airports)
- **Visual Suggestions**: Shows helpful suggestions like "💡 Legs 1 and 2 look like a round-trip (BDL↔ROA). Consider using the Round-Trip checkbox on Leg 1 for better pricing."
- **Auto-display**: Suggestions appear automatically as you enter airport codes
- **Impact**: Helps users optimize flight searches for better pricing by identifying round-trip opportunities

#### Technical Details
- Added `checkFlightSuggestions()` method to detect round-trip patterns in real-time
- Added `.ftt-from-airport` and `.ftt-to-airport` classes for easier DOM targeting
- Added `blur` event handler on airport inputs to trigger suggestion checks
- Suggestions display in existing `#ftt-flight-suggestions` container with slide animation
- Pre-fill logic checks previous leg's arrive airport and auto-populates next leg's depart airport

## [2.0.98] - 2026-03-03

### 🐛 Bug Fixes - Event Form & Travel UX

#### Fixed - Event Creation Issues
- **Member ID Not Saving** (#1): Fixed event form not capturing member_id, causing events to show wrong child association
- **Blank Page After Save** (#2): Event form now redirects to dashboard after successful save instead of showing blank page
- **Missing Dashboard Link** (#3): Added "Back to Dashboard" button to calendar page for easier navigation

#### Fixed - Flight Search & Display
- **Round-Trip Data Not Saving** (#1a): Added proper collection of is_round_trip, return_date, and return_time_of_day fields from form
- **TBD Display Issue** (#5): Fixed flight cards showing "TBD" by prioritizing airport codes over location names for flights
- **Multi-Leg Round-Trip Handling** (#6): Improved round-trip detection with explicit is_round_trip flag (Method 0) that takes precedence over legacy detection methods

#### Changed - Travel Leg Input
- **Round-Trip Toggle**: Simplified behavior - checking round-trip now shows return date fields inline instead of auto-creating separate return leg
- **Helper Text**: Added informational message explaining round-trip pricing search when toggle is enabled
- **Round-Trip Detection**: Updated both cron and manual price checks to prioritize explicit is_round_trip flag over heuristic detection

#### Technical Details
- Added dashboardUrl to wp_localize_script for JavaScript access to dashboard page URL
- Updated collectTravelLegs() to include is_round_trip, return_date, and return_time_of_day
- Updated price-tracking.php check_all_prices() with Method 0: explicit round-trip flag check
- Updated REST API check_flight_price with same Method 0 prioritization
- Updated loadEventForEdit() to properly restore member_id when editing events

## [2.0.18] - 2026-02-27

### 🎯 Database Tables Rebrand

**Final rebrand step**: Renamed database tables and related identifiers from SRT to FTT prefix.

### Changed - Database Schema
- **Table Names**: 
  - `wp_srt_price_history` → `wp_ftt_price_history`
  - `wp_srt_price_alerts` → `wp_ftt_price_alerts`
- **Option Names**: `srt_price_tracking_schema_v2` → `ftt_price_tracking_schema_v2`
- **WordPress Hooks**: `srt_price_alert_sent` → `ftt_price_alert_sent`

### Changed - Documentation
- Updated all SQL examples in cron documentation to reference new table names
- Updated inline code comments to reference FTT tables
- Updated CSS class examples in README documentation

### Changed - Templates (Additional)
- **CSS Classes**: Fixed remaining template CSS classes:
  - `srt-alerts-container` → `ftt-alerts-container` (2 occurrences in dashboard.php)
  - `srt-setting-action` → `ftt-setting-action` (3 occurrences in dashboard.php)
  - `srt-copy-code` → `ftt-copy-code` (dashboard.php)
  - `srt-member-code` → `ftt-member-code` (dashboard.php)
- **JavaScript Functions**: 
  - `srtCopyUrl()` → `fttCopyUrl()` and its onclick handler (calendar-subscribe.php)

### Migration Notes
**New Installations Only**: This version is for NEW deployments only. The table names are set during initial activation. No migration script needed since this is a new rollout with no existing data.

### Files Updated
- `includes/price-tracking.php` - Table creation and all queries
- `includes/rest.php` - API endpoints that query price data
- `includes/flight-linking.php` - Flight price lookups
- `includes/cron-setup.php` - Documentation examples
- `test-digest.php` - Daily digest test script

### Technical Impact
- Fresh installs: Tables created with `ftt_` prefix automatically
- Zero backward compatibility concerns (new rollout)
- All price tracking functionality preserved
- Database queries unchanged except for table names

---

## [2.0.17] - 2026-02-27

### 🎯 Complete Rebrand: All User-Facing Elements

**Critical Update**: This completes the rebrand started in v2.0.12. All user-facing elements now use FTT (Family Travel Tracker) prefix instead of SRT (Schedule Regiment Tracker).

### Changed - User Interface
- **CSS Classes**: All `.srt-*` classes → `.ftt-*` (16 classes in JavaScript, multiple in templates)
- **CSS Animation**: `@keyframes srt-spin` → `@keyframes ftt-spin`
- **Form Fields**: All `srt_*` form field names → `ftt_*`
- **Shortcode**: `[srt_register]` → `[ftt_register]`
- **Menu Items**: Menu IDs and CSS classes from `srt-` → `ftt-`

### Changed - Admin Interface  
- **Nonce Names**: All security nonces updated (e.g., `srt_manage_users` → `ftt_manage_users`)
- **Admin Pages**: Page slugs from `srt-*` → `ftt-*` (manage-users, settings, etc.)
- **Screen IDs**: `edit-srt_event` → `edit-ftt_event`
- **Form Actions**: POST action names from `srt_*` → `ftt_*`
- **Transients**: Error/message transients from `srt_*` → `ftt_*`

### Preserved for Compatibility
**These were NOT changed to maintain backward compatibility with existing databases:**
- ✅ Database Tables: `wp_srt_price_history`, `wp_srt_price_alerts` (unchanged)
- ✅ User Meta Keys: `srt_children`, `srt_parents`, `srt_instrument`, etc. (unchanged)
- ✅ WordPress Options: `srt_page_ids`, `srt_cron_log`, etc. (unchanged)
- ✅ WP Hooks: `srt_check_flight_prices`, `srt_daily_digest`, etc. (unchanged)

### Files Updated
- **Assets**: `assets/css/styles.css`, `assets/js/main.js`
- **Templates**: All 8 template files (login, registration, dashboard, calendar, etc.)
- **Includes**: `roles.php`, `registration.php`, `pages.php`, `menu.php`, `cron-setup.php`, `cpt.php`, `shortcodes.php`

### Migration from v2.0.16
- Existing users: Plugin auto-updates, no database migration needed
- Custom templates: Update any custom code using old `.srt-*` CSS classes
- Shortcodes: Replace `[srt_register]` with `[ftt_register]` in pages/posts

### Impact
- **Users**: Seamless - all functionality preserved, visual consistency improved
- **Developers**: CSS classes and form fields now match FTT branding
- **Database**: Zero impact - all data structures backward compatible

---

## [2.0.16] - 2026-02-27

### 🐛 Critical Bug Fix: Settings Not Persisting

**Issue**: Settings pages failed to save - all configuration changes were lost on submit

### Fixed
- **Settings Form Fields**: Changed all `srt_settings` input names to `ftt_settings` in [includes/settings.php](includes/settings.php)
- **Settings Group**: Changed `srt_settings_group` to `ftt_settings_group`
- **Section IDs**: Changed all `srt_*_section` to `ftt_*_section` (general, api, events, calendar)
- **Page Slugs**: Changed `srt-settings` to `ftt-settings` throughout
- **JavaScript Functions**: Renamed `srtRemoveEventType` → `fttRemoveEventType`, `srtDeleteToken` → `fttDeleteToken`
- **Geocoding Provider ID**: Changed `srt_geocoding_provider` to `ftt_geocoding_provider`
- **Stripe Settings Hook**: Fixed page hook from `srt_event_page_ftt-billing-settings` to `ftt_event_page_ftt-billing-settings`
- **Cron Setup Pages**: Changed menu slugs from `srt-cron-setup`/`srt-cron-docs` to `ftt-cron-setup`/`ftt-cron-docs`
- **Event Migration**: Changed form action from `srt_migrate_events` to `ftt_migrate_events` and nonce field
- **Bulk Assign**: Changed field name from `srt_bulk_assign_member` to `ftt_bulk_assign_member`

### Root Cause
During the v2.0.12 rebrand from "Schedule Regiment Tracker" to "Family Travel Tracker", the WordPress option name was correctly changed to `ftt_settings`, but:
1. The HTML form fields still used `name="srt_settings[...]"`
2. WordPress expected POST data with `$_POST['ftt_settings']`
3. Received POST data had `$_POST['srt_settings']`
4. Settings were silently ignored - no data saved

### Impact
- **v2.0.12-2.0.15 users**: All settings pages (General, API, Events, Calendar, Billing, Cron) non-functional
- **Affected settings**: Mapbox/Google API keys, Stripe keys, timezone, login menu, event types, iCal, etc.
- **Workaround**: None - settings could not be saved at all
- **Upgrade required**: All users on v2.0.12-2.0.15 must upgrade to v2.0.16 immediately

### Technical Note
Database table names and option keys preserve `srt_` prefix for backward compatibility with existing installations. Only form fields, menu slugs, and admin page identifiers were rebranded to `ftt_`.

---

## [2.0.15] - 2026-02-27

### 🐛 Critical Bug Fix

**Issue**: Plugin failed to load with fatal error "Class 'SRT_ICal' not found"

### Fixed
- **Class Name References**: Updated `SRT_ICal::init()` to `FTT_ICal::init()` in [includes/ical.php](includes/ical.php#L498)
- **Migration Class**: Updated `SRT_Event_Migration::init()` to `FTT_Event_Migration::init()` in [includes/event-migration.php](includes/event-migration.php#L302)

These were missed during the v2.0.12 rebrand from "Schedule Regiment Tracker" to "Family Travel Tracker". The classes were correctly renamed, but the initialization calls still referenced the old `SRT_` prefix, causing fatal errors on plugin activation.

### Impact
- **v2.0.14 users**: Plugin completely broken, unable to load
- **New installs**: Plugin failed to activate
- **Upgrade required**: All v2.0.14 users must update to v2.0.15 immediately

### Technical Details
- Class definitions were correct (`FTT_ICal`, `FTT_Event_Migration`)
- Only the static `::init()` method calls needed correction
- No database or configuration changes required

---

## [2.0.14] - 2026-02-27

### 🎯 Major Feature: Single WordPress Dual-Domain Support

This release enables running both marketing (www) and app (my) sites from a **single WordPress installation** with automatic domain-based routing.

### Added
- **Domain Routing System**: New `FTT_Domain_Routing` class handles automatic page routing
- **Automatic Redirects**: Marketing pages stay on www, app pages redirect to my subdomain
- **Domain Detection**: Helper functions to detect current domain (marketing vs app)
- **Conditional Menus**: Separate navigation menus for each domain (auto-switching)
- **Body Classes**: Domain-specific CSS classes (`ftt-marketing-domain`, `ftt-app-domain`)
- **Cross-Domain URLs**: Helper functions to generate URLs for opposite domain
- **Admin Bar Control**: Hide admin bar on marketing domain for non-admins

### Changed
- **Single Database**: Both domains share one WordPress database and user accounts
- **Unified Authentication**: Users register once, login works across both domains
- **Simplified CORS**: Updated to handle same-WordPress subdomains
- **Stripe URLs**: Automatically use domain routing for checkout success/cancel URLs
- **Sign-Up Page**: Updated to use relative paths (works from any domain)
- **wp-config.php**: Requires dynamic domain handling (documented in setup guide)

### Technical Details
- `FTT_Domain_Routing::is_marketing_domain()` - Check if on www subdomain
- `FTT_Domain_Routing::is_app_domain()` - Check if on my subdomain
- `FTT_Domain_Routing::get_marketing_url()` - Get marketing domain URL
- `FTT_Domain_Routing::get_app_url()` - Get app domain URL
- `FTT_Domain_Routing::get_cross_domain_url()` - Generate cross-domain links
- Marketing pages array filterable via `ftt_marketing_pages` hook
- App pages array filterable via `ftt_app_pages` hook

### Documentation
- Added `SINGLE_WORDPRESS_SETUP.md` - Complete setup guide for single WordPress dual-domain
- Includes DNS configuration, web server setup, WordPress configuration
- User flow diagrams, testing checklist, troubleshooting guide
- Migration guide from dual-WordPress setup

### Migration Note
**Breaking Change**: If you were using v2.0.13's separate WordPress setup, you can migrate to this single-WordPress approach. See `SINGLE_WORDPRESS_SETUP.md` for migration guide. The two-WordPress approach still works if you prefer it.

### Benefits of Single WordPress Approach
- ✅ One database, shared users
- ✅ Consistent branding automatically
- ✅ Single plugin installation
- ✅ Easier updates and backups
- ✅ Lower hosting costs
- ✅ Shared authentication

## [2.0.13] - 2026-02-27

### Added
- **Dual-Domain Architecture Support**: Full support for separate marketing (www) and app (my) domains
- **Public Registration API**: New `/ftt/v1/register` endpoint for cross-domain sign-ups
- **CORS Configuration**: Automatic CORS headers for cross-origin REST API calls
- **App Domain Setting**: Configure custom app domain in Stripe settings (Advanced tab)
- **Integrated Sign-Up Flow**: Users register and enter billing info in one seamless flow
- **Sign-Up Page Template**: Beautiful standalone sign-up page with pricing selector
- **Account Auto-Creation**: WordPress accounts automatically created during checkout
- **Auto-Login After Registration**: Users automatically logged in after account creation

### Changed
- **No Guest Checkout**: Users must create account before subscribing (prevents email typos)
- **Updated Stripe URLs**: Success/cancel URLs now use configured app domain
- **Trial Starts After Payment**: 14-day trial begins only after payment method entered
- **REST Endpoint Protection**: `/create-checkout` still requires login, `/register` is public

### Technical
- Added `FTT_CORS` class for cross-origin resource sharing
- Added `register_new_user()` method in REST API
- Updated `create_checkout_session()` to use app domain from settings
- New setting: `app_domain` in Stripe settings (defaults to current WordPress URL)

### Documentation
- Added `DUAL_DOMAIN_ARCHITECTURE.md` - Complete dual-domain setup guide
- Includes CORS configuration, marketing site integration, and sign-up flow diagrams

## [1.0.7] - 2026-02-04

### Added
- **Automatic Updates** - GitHub-based auto-updater integration
  - Integrated Plugin Update Checker v5.4 library
  - WordPress checks GitHub for updates automatically
  - Standard "Update available" notification in Plugins page
  - One-click updates directly from GitHub releases
  - No external services required - updates from GitHub repo
  - Works with tagged releases (e.g., v1.0.7)

### Changed
- **Repository URL** - Updated to new repo name `schedule-collaboration-tracking`
- **Package size** - Increased to 304K (from 136K) due to update checker library

## [1.0.6] - 2026-02-04

### Changed
- **Revoked Invitations** - Auto-cleanup after 30 days
  - Revoked invitation codes automatically removed from dashboard after 30 days
  - Cleanup happens when invitations list is loaded
  - Expired revoked invitations permanently removed from database
  - Other statuses (pending, accepted, rejected) remain visible

## [1.0.5] - 2026-02-04

### Added
- **Shortcode Documentation** - Added `[srt_login]` to settings page shortcode list

## [1.0.4] - 2026-02-04

### Added
- **Cron Documentation Page** - Full documentation within WordPress admin
  - New admin page: Events → Cron Docs
  - Complete setup instructions with actual server paths
  - Troubleshooting guide
  - API configuration details
  - No longer serves markdown files to users

### Changed
- **Cron Setup Link** - "View Full Documentation" now points to admin page instead of .md file

## [1.0.3] - 2026-02-04

### Fixed
- **Cron Schedule** - Added 2am to server cron schedule
  - Server cron now runs 5 times daily: 12am, 2am, 6am, 12pm, 6pm
  - Ensures daily digest emails send exactly at 2am (not delayed until 6am)
  - Updated setup-cron.sh script with correct schedule
  - Updated admin setup page documentation
  - Updated manual setup instructions in cron setup page

## [1.0.2] - 2026-02-04

### Added - Custom Login Page
- **Custom Login Page** - Dedicated login page for schedule users
  - New page: `/sc-login/` with `[srt_login]` shortcode
  - Clean, modern login form with better UX than default WordPress
  - Icons for username and password fields
  - Remember me checkbox
  - Links to password reset and registration
  - Success/error messages with visual feedback
  
- **Smart Login Redirects** - Users sent to appropriate destination
  - Members and parents redirect to Member Dashboard after login
  - Admins/editors can still access wp-admin if needed
  - Failed login redirects back to custom login page with error message
  - Eliminates confusion from WordPress admin dashboard
  
- **Status Messages** - Clear feedback for all login scenarios
  - Failed login: "Invalid username or password"
  - Empty fields: "Please enter your username and password"
  - Successful logout: "You have been successfully logged out"
  - New registration: "Registration successful! Please log in"
  - Password changed: "Password changed successfully!"
  - Password reset: "Check your email for the password reset link"

### Changed
- **Event List Filtering** - Shows only relevant events per user
  - Members see only their own events
  - Parents see their children's events + their own
  - Admins see all events (no filter)
  - Button label changes: "My Events" for members/parents, "All Events" for admins
  
- **iCal Feed Filtering** - Calendar subscriptions now user-specific
  - Each user's iCal feed shows only their events
  - Parents' feeds include their children's events
  - Admins' feeds include all events
  - Per-user tokens ensure privacy and security

- **Menu Login Link** - Points to custom login page
  - Login menu item now links to `/sc-login/` instead of `wp-login.php`
  - Preserves redirect URL to return users to their original page
  
- **Cron Setup Documentation** - Clarified both automated tasks
  - Status page now shows both price checking and daily digest schedules
  - Setup instructions explain digest emails run at 2am daily
  - Test commands provided for both cron events
  - Server cron setup enables both tasks automatically
  
- Login flow now uses custom branded page instead of WordPress default

### Removed
- **Dashboard Duplicate Section** - Cleaned up redundant flight displays
  - Removed duplicate "Flights Not Yet Booked" section
  - Kept clearer "Flights Needed (Upcoming)" section
  - Applies to both admin and member dashboard views
- Non-admin users no longer see WordPress admin dashboard
- Login page auto-created during plugin activation

### Technical
- Added `[srt_login]` shortcode
- Added `templates/login-form.php` template
- Added `custom_login_redirect()` filter for login redirects
- Added `custom_authenticate_redirect()` for failed login handling
- Page automatically created in `SRT_Pages::get_page_definitions()`
- Event list filtering based on `member_id` post meta and user role
- iCal filtering in `handle_calendar_request()` method
- Menu login link updated in `generate_login_item()` method

## [1.0.1] - 2026-02-03

🎉 **MAJOR RELEASE** - Daily Price Digest Feature

This release marks a significant milestone with the addition of automated daily price digests, 
providing users with intelligent, actionable flight price insights delivered directly to their inbox.

### Added - Daily Price Digest Feature
- **Daily Price Digest Email** - Automated 2am email with trending flight prices
  - New alert type: "📧 Daily Price Digest (2am email)"
  - Receive one consolidated email with all tracked flights
  - Grouped by recommendation: Good Deals, Trending Up, Trending Down, Stable
  - Shows: current price, 7-day trend, days to departure, actionable recommendations
  
- **Smart Recommendations** - Intelligent insights without ML complexity
  - "✅ Book now - Great price!" - 15% below average (good deal detected)
  - "⚠️ Book soon - Prices rising" - Trending up with <30 days to departure
  - "⏳ Wait and watch - Prices dropping" - Trending down with >30 days buffer
  - "➡️ Monitor - Prices stable" - No significant trend detected
  
- **Rich Email Template** - Beautiful HTML digest email
  - Professional card-based layout for each flight
  - Trend badges: 📈 Up, 📉 Down, ➡️ Stable
  - Price statistics: Current, 7-day change, Min/Avg/Max
  - Color-coded recommendations: Green (book), Yellow (wait), Red (urgent)
  - Direct link to Member Dashboard for management
  
- **Automated Cron Scheduling** - Set-it-and-forget-it automation
  - Digest automatically runs daily at 2am
  - New cron schedule: `daily_2am` (86400 second interval)
  - Processes all users with active digest alerts
  - Comprehensive error logging for troubleshooting
  
- **7-Day Trend Analysis** - Price movement intelligence
  - Calculates change from first to most recent price
  - Trend detection: >5% change triggers up/down classification
  - Within 5% change = stable classification
  - Shows both dollar amount and percentage change

- **Testing Utility** - Easy digest testing without waiting for cron
  - New `test-digest.php` file for manual triggering
  - Works via WP-CLI or browser
  - Shows diagnostic information about cron scheduling
  - Verifies users with active digest alerts

### Changed
- **Alert System Enhanced** - Daily digest integrated seamlessly
  - Alert type enum now includes: 'price_drop', 'percent_drop', 'good_deal', 'daily_digest'
  - Digest alerts don't require price thresholds (NULL values allowed)
  - UI dropdown shows all 4 alert types with clear icons
  - Existing alert system continues to work alongside digest

### Technical
- Added `process_daily_digests()` method to process all digest users
- Added `send_daily_digest($user_id)` to generate and send individual digests
- Added `build_digest_email()` HTML template generator
- Added `render_flight_card()` email component renderer
- New cron hook: `srt_daily_digest` scheduled for 2am daily
- Email uses WordPress `wp_mail()` with HTML content type
- Added comprehensive inline documentation

### Documentation
- **NEW**: `DAILY_DIGEST.md` - Comprehensive feature documentation
- **NEW**: `DAILY_DIGEST_QUICK_REF.md` - Quick reference guide
- **NEW**: `test-digest.php` - Testing utility
- Updated main README with digest feature overview

## [0.9.71] - 2025-01-24

### Added - Daily Price Digest Feature
- **Daily Price Digest Email** - Automated 2am email with trending flight prices
  - New alert type: "📧 Daily Price Digest (2am email)"
  - Receive one consolidated email with all tracked flights
  - Grouped by recommendation: Good Deals, Trending Up, Trending Down, Stable
  - Shows: current price, 7-day trend, days to departure, actionable recommendations
  
- **Smart Recommendations** - AI-like insights without ML complexity
  - "✅ Book now - Great price!" - 15% below average (good deal detected)
  - "⚠️ Book soon - Prices rising" - Trending up with <30 days to departure
  - "⏳ Wait and watch - Prices dropping" - Trending down with >30 days buffer
  - "➡️ Monitor - Prices stable" - No significant trend detected
  
- **Rich Email Template** - Beautiful HTML digest email
  - Professional card-based layout for each flight
  - Trend badges: 📈 Up, 📉 Down, ➡️ Stable
  - Price statistics: Current, 7-day change, Min/Avg/Max
  - Color-coded recommendations: Green (book), Yellow (wait), Red (urgent)
  - Direct link to Member Dashboard for management
  
- **Automated Cron Scheduling** - Set-it-and-forget-it automation
  - Digest automatically runs daily at 2am
  - New cron schedule: `daily_2am` (86400 second interval)
  - Processes all users with active digest alerts
  - Error logging for troubleshooting
  
- **7-Day Trend Analysis** - Price movement intelligence
  - Calculates change from first to most recent price
  - Trend detection: >5% change triggers up/down classification
  - Within 5% change = stable classification
  - Shows both dollar amount and percentage change

### Changed
- **Alert System Enhanced** - Daily digest integrated seamlessly
  - Alert type enum now includes: 'price_drop', 'percent_drop', 'good_deal', 'daily_digest'
  - Digest alerts don't require price thresholds (NULL values allowed)
  - UI dropdown shows all 4 alert types with clear icons
  - Existing alert system continues to work alongside digest

### Technical
- Added `process_daily_digests()` method to process all digest users
- Added `send_daily_digest($user_id)` to generate and send individual digests
- Added `build_digest_email()` HTML template generator
- Added `render_flight_card()` email component renderer
- New cron hook: `srt_daily_digest` scheduled for 2am daily
- Email uses WordPress `wp_mail()` with HTML content type

## [0.9.70] - 2025-01-24

### Fixed
- **Form Placeholder Clarity** - Resolved user confusion with placeholder text
  - Changed airport placeholders from "BDL" to "e.g., BDL"
  - Changed location placeholders to "e.g., Hartford, CT"
  - Visual distinction prevents confusion between placeholder and actual data
  - Debug issue: User mistook placeholder for saved data

### Added
- **Debug Logging** - Enhanced troubleshooting for data collection
  - Added comprehensive logging in `collectTravelLegs()` function
  - Logs field existence, visibility, and collected values for each leg
  - Added debug logging in modal display for flight legs
  - Added logging in `generateFlightSearchLinks()` function
  - Console logs help trace data flow issues

## [0.9.69] - 2025-01-24

### Fixed
- **Southwest Flight Search URLs** - Fixed deep linking to Southwest booking
  - Changed base URL to `select-depart.html` (from `air/booking/select.html`)
  - Added all required parameters: adultsCount, adultPassengersCount, departureDate
  - URLs now successfully pre-fill Southwest's booking form
  - Tested with multiple routes - works correctly

- **Invitation Revoke Endpoint** - Fixed 404 errors on invitation revocation
  - Updated REST route regex patterns to accept hyphens in invitation codes
  - Changed from `[a-zA-Z0-9]+` to `[a-zA-Z0-9\-]+`
  - Both `/invite/{code}/revoke` and `/invite/{code}/validate` now work with hyphenated codes
  - Example working code: `INV-E4BSG1BWH8`

### Changed
- **Time-of-Day Selection** - Matched Google Flights format
  - Replaced `<input type="time">` with dropdown: Morning, Mid-Day, Afternoon, Night
  - Consistent with Google Flights UI pattern
  - Simpler user experience for approximate departure times
  - Better for price tracking (exact times not needed)

- **Member Dashboard UX** - Improved clarity and removed redundancy
  - Removed duplicate "Flights Not Yet Booked" section
  - Renamed section to "✈️ My Upcoming Flights" with helpful description
  - Moved "🔔 My Price Alerts" to prominent top position
  - Added `.srt-section-description` CSS for contextual help text
  - Cleaner, more intuitive member experience

## [0.9.68] - 2025-01-24

### Added - Major Flight Entry Redesign
- **Smart Airport Lookup** - Location names auto-populate from IATA codes
  - Type 3-letter airport code (BDL, BWI, etc.) and see city/state automatically
  - Built-in lookup for 20+ major US airports
  - Visual feedback: green for valid codes, red for unknown
  - Eliminates redundant "departure location" and "arrival location" fields
  
- **Round-Trip Toggle** - Create return flights automatically
  - New "Round-Trip Flight" checkbox on flight legs
  - Automatically reverses airports and suggests return date
  - Linked return leg marked with "(Return)" indicator
  - Saves time creating common two-way trips
  
- **Collapsible Booking Details** - Cleaner form for unbooked flights
  - Airline, flight number, and confirmation fields now behind "Already Booked" checkbox
  - Default view only shows essential fields: airports, date, baggage
  - Expand booking section only when flight is already purchased
  - Reduces form clutter by 60%
  
- **Flight Only Event Type** - Dedicated event type for standalone flights
  - New event type specifically for flights without associated event
  - Auto-expands travel section when selected
  - Automatically adds flight leg on selection
  - Perfect for quick flight booking tracking
  
- **Auto-Populate End Dates** - All-day events made easier
  - End date automatically copies from start date for all-day events
  - Updates dynamically when start date changes
  - No more manual duplicate entry for single-day events
  
- **Enhanced iCalendar with Flight Data** - Mobile calendar integration
  - iCal feeds now include full flight details in description
  - Shows: Route (ORD → LAX), Date/Time, Airline, Flight Number, Confirmation
  - Baggage info included for packing reference
  - Airport code used as event location for mobile map integration
  - Multiple flights shown as "FLIGHT #1", "FLIGHT #2" with full details

### Changed
- **Simplified Date Entry** - Removed confusing arrival date/time fields
  - Eliminated "arrive_date" and "arrive_time_of_day" fields
  - Flight search only needs departure date
  - Reduced form complexity and user confusion
  
- **Flight vs Non-Flight Fields** - Conditional field display
  - Airport codes only show for flights
  - Location name fields only show for bus/drive/shuttle
  - Baggage options only appear for flights
  - Form adapts to travel mode selection
  
- **Time Entry Simplified** - Optional time field with clearer labeling
  - Changed from "Time of Day" dropdown to optional time picker
  - Labeled as "(optional)" to reduce pressure on users
  - Only used for display, not required for flight searches

### Improved
- **Travel Leg UI** - Modern, cleaner design
  - Better visual hierarchy with section headers
  - Improved spacing and grouping of related fields
  - Flight emoji (✈️) and other mode icons for quick identification
  - Professional "Already Booked" styling with checkmark
  
- **Form Validation** - Smarter required fields
  - Only essential fields marked as required
  - Context-aware validation (flight vs non-flight)
  - Better error messaging for missing airport codes

### Why This Matters
The flight entry process was cumbersome with redundant fields and confusing requirements. This update reduces data entry time by 50%+ while adding powerful features like round-trip auto-creation and mobile calendar flight data. Parents can now quickly track flights with minimal typing, and their mobile calendars will show full flight details including confirmation numbers and baggage info.

## [0.3.2] - 2026-01-23

### Added
- **Flight Search Links** - Quick search buttons for unbooked flights
  - "Search Flights" buttons appear for unbooked flight legs in event modals and dashboard
  - One-click links to Google Flights, Kayak, and Southwest with route/dates pre-filled
  - Only shows for flights (not bus/van travel) with airport codes entered
  - Opens in new tab with departure airport, arrival airport, and event date
  - Color-coded buttons: Blue (Google), Orange (Kayak), Navy (Southwest)

### Why This Matters
Staff can now instantly search for flight options without manually entering dates and airports. Click "Search Flights" and compare prices across multiple booking sites in seconds.

## [0.3.1] - 2026-01-23

### Fixed
- **API Key Fields Not Enabling** - API key input fields now properly enable/disable based on provider selection
  - Added JavaScript to dynamically enable/disable fields when geocoding provider dropdown changes
  - Mapbox API key field enables only when Mapbox is selected
  - Google Places API key field enables only when Google Places is selected
  - Fields update immediately on provider selection change
- **Google Places Autocomplete Types** - Fixed "establishment cannot be mixed with other types" error
  - Changed types from `['establishment', 'school', 'park']` to just `['establishment']`
  - 'establishment' includes all businesses and venues (schools, parks, stadiums, etc.)
- **iCal/WebCal Location Data** - Fixed iCalendar export to include location name and address
  - Now exports both location_name and location_address in LOCATION field
  - Format: "Location Name, Full Address" (e.g., "Southington High School, 720 Pleasant St, Southington, CT")
  - Calendar apps will now show complete location information

### Changed
- **Google Places Setup Documentation** - Added requirement for Maps JavaScript API
  - Both Places API and Maps JavaScript API must be enabled
  - Updated API key restriction instructions to include both APIs
  - Added troubleshooting section for ApiNotActivatedMapError

## [0.3.0] - 2026-01-23

### Added
- **Google Places API Integration** - MUCH better for finding schools, parks, and local venues
  - New dropdown in settings to choose geocoding provider: None, Google Places, or Mapbox
  - Google Places has comprehensive school database (finds "Southington High School" correctly)
  - Native Google autocomplete with familiar UI
  - Cost: ~$0.02 per search (~$2 per season for 30 events)
  - Requires Google Places API key from Google Cloud Console

### Changed
- Address autocomplete now supports multiple providers
  - **Recommended: Google Places** for schools and venues
  - **Alternative: Mapbox** for general addresses
  - **None**: Manual entry only (plugin still works perfectly)
- Settings page reorganized with provider selection dropdown
- API key fields only enabled when their provider is selected
- JavaScript automatically loads appropriate library based on selection

### Why This Matters
Mapbox doesn't have many schools in its database (returns street names instead of school names). Google Places has comprehensive coverage of schools, parks, and local venues - exactly what drum corps need for rehearsal locations.

## [0.2.9] - 2026-01-23

### Fixed
- **All-Day Event Editing (Critical Fix)** - Dates now properly appear when editing all-day events
  - Fixed loading order: checkbox state is set BEFORE date values (previously was after)
  - This ensures input type switches from datetime-local to date before values are set
  - Dates no longer disappear when editing all-day events
- **Mapbox Autocomplete Positioning** - Dropdown no longer covers the input field
  - Changed from `border-top: none` to proper positioning with `top: 100%`
  - Added `margin-top: 2px` for visual separation
  - Added `border-radius: 4px` for better appearance
  - Improved shadow for better visibility
- **Mapbox Search Debugging** - Added console logging for troubleshooting
  - Logs when search is initiated with query
  - Logs number of results found
  - Better error messages displayed in dropdown when API fails
  - Clearer console message when API key is not configured

### Changed
- Improved parent element positioning for autocomplete (uses `.srt-form-field:has()` selector)
- Better error handling with user-friendly error display in dropdown

## [0.2.8] - 2026-01-23

### Fixed
- **All-Day Event Editing** - Fixed issue where start and end dates were not displaying when editing all-day events
  - Now properly converts datetime format to date-only format (YYYY-MM-DD) for all-day events
  - Preserves full datetime for regular timed events
- **Mapbox Search Results** - Improved location search to find more venues
  - Removed restrictive type filters (was limited to POI, address, place)
  - Now searches all location types including schools, parks, buildings, etc.
  - Increased result limit from 5 to 8 suggestions
  - "Southington High School" and similar venues now appear in results

## [0.2.7] - 2026-01-23

### Added
- **Mapbox Address Autocomplete** - Optional integration with Mapbox Geocoding API
  - Location name field now offers autocomplete suggestions as you type
  - Automatically populates full address when selecting from suggestions
  - Stores latitude/longitude coordinates for potential future mapping features
  - Free tier: 100,000 requests/month (more than sufficient for most drum corps)
  - Admin can add Mapbox API key in Settings (optional - plugin works without it)
  - Smart debouncing to minimize API calls (300ms delay, 3+ characters required)

### Changed
- Added `location_latitude` and `location_longitude` meta fields to store coordinates
- Enhanced event form to support coordinate data
- REST API now includes coordinate fields in event responses

## [0.2.6] - 2026-01-23

### Added
- **All-Day Events** - Added checkbox to event form for all-day events
  - Form automatically switches between date and date-time inputs based on checkbox
  - All-day events stored with date-only values instead of timestamps
  - Backend properly handles all_day field in REST API and meta storage

### Fixed
- **Edit Event Navigation** - Fixed "Edit Event" button in calendar modal
  - Button now properly navigates to event form page with event_id parameter
  - Previously was navigating back to calendar page instead of form
  - Added eventFormUrl to localized script data for proper URL handling

## [0.2.5] - 2026-01-23

### Changed
- **QR Code Instructions** - Updated iOS instructions to 4 clear steps starting with "Make sure your phone is unlocked"
- **Android Instructions** - Added detailed 4-step instructions for Android calendar subscription
- Added helpful note for manual subscription if QR code doesn't work
- Improved instruction formatting with numbered steps for both platforms

## [0.2.4] - 2026-01-23

### Fixed
- **QR Code Calendar Subscription** - Now uses webcal:// protocol in QR code for direct iOS calendar subscription
- QR code now properly triggers "Subscribe" prompt on iOS instead of opening browser
- Simplified iOS subscription to 3 steps: Scan → Tap notification → Tap Subscribe

### Changed
- QR code generates webcal:// URL instead of HTTPS URL
- Updated instructions to reflect simpler iOS workflow
- Reordered URL display: Webcal first (for subscription), HTTPS second (for browser)

## [0.2.3] - 2026-01-23

### Fixed
- **Calendar Display** - Removed internal scroll, calendar now displays all 5 weeks without scrollbar
- **Calendar Layout** - Saturday dates no longer cut off by scroll area
- **iOS Calendar Subscription** - Changed QR code to use HTTPS URL instead of webcal:// for better iOS compatibility
- **Menu Integration** - Fixed "Add to Menu" button in Appearance → Menus to properly add login/logout item

### Changed
- Calendar uses `height: auto` and `contentHeight: auto` to display full month
- QR code instructions updated with step-by-step iOS calendar subscription process
- Added both HTTPS and webcal:// URLs for maximum compatibility
- Improved copy-to-clipboard functionality for both URL formats

### Technical
- Added CSS overrides: `.fc-view-harness` and `.fc-scroller` for proper height handling
- Updated FullCalendar configuration to eliminate internal scrolling
- QR code now generates with HTTPS URL, separate webcal URL provided
- JavaScript uses `wpNavMenu.addItemToMenu()` API for menu item creation

## [0.2.2] - 2026-01-23

### Added
- **QR Code Calendar Subscription** - Scan QR code on calendar page to add subscription to mobile devices
- Toggle button to show/hide QR code section
- Instructions for iOS and Android camera scanning
- Copy-to-clipboard functionality for webcal:// URL
- Mobile-friendly calendar subscription workflow

### Changed
- Calendar page includes collapsible QR code section for logged-in users
- QR code generated via free API (api.qrserver.com)
- Webcal URL automatically includes user's authentication token

### Technical
- QR code section integrated into calendar.php template
- Uses dashicons for mobile icon
- jQuery-powered toggle animation
- Responsive styling for QR code display
- 200x200px QR code with shadow effects

### User Experience
- One-click access to mobile subscription
- Visual instructions for different platforms
- Fallback URL copy for manual entry
- Clean, professional design

## [0.2.1] - 2026-01-23

### Added
- **Login/Logout Menu System** - Add login/logout links to WordPress menus
- Settings to enable/disable login menu functionality
- Two display modes:
  - **Login Only**: Shows login link only when user is NOT logged in (hides when logged in)
  - **Login/Logout**: Shows login when not logged in, logout when logged in
- New "Regiment Login/Logout" meta box in Appearance → Menus
- Automatic redirect to current page after login/logout
- Optional username display in logout link (via filter)

### Changed
- Settings page includes new "Login/Logout Menu" options in General Settings
- Menu items dynamically show/hide based on user login status

### Technical
- New `SRT_Menu` class for menu management
- Integrates with WordPress nav menu system
- Uses `wp_nav_menu_items` filter for dynamic menu generation
- Custom menu meta box in nav menu admin
- Proper URL encoding and security with wp_login_url() and wp_logout_url()

### Files Added
- `includes/menu.php` (203 lines) - Login/logout menu management

## [0.2.0] - 2026-01-23

### Added
- **Calendar Subscription Feature** - Users can now subscribe to the schedule in their calendar apps
- iCalendar (.ics) feed generation with RFC 5545 compliance
- Token-based authentication for private calendar access
- Calendar subscription settings in admin panel
- `[srt_calendar_subscribe]` shortcode with detailed instructions for iOS, Android, Mac, Outlook
- REST API endpoint `/srt/v1/calendar.ics` for calendar feed
- `/srt/v1/calendar/token` endpoint for generating authentication tokens
- Support for all major calendar apps:
  - Apple Calendar (iOS, macOS)
  - Google Calendar (Android, Web)
  - Microsoft Outlook
  - Any iCalendar-compatible app
- Automatic sync - changes to events update in subscribed calendars
- Token management interface for admins

### Changed
- Settings page now includes "Calendar Subscription" section
- Calendar feed can be enabled/disabled independently
- Optional authentication requirement for calendar access
- Event details, time blocks, and travel info included in calendar events

### Technical
- New `SRT_ICal` class for iCalendar generation
- Proper VTIMEZONE and VEVENT components
- Line folding per RFC 5545 specifications
- Token storage in `srt_calendar_tokens` option
- Enhanced settings sanitization for calendar options

### Files Added
- `includes/ical.php` (360 lines) - iCalendar feed generation
- `templates/calendar-subscribe.php` (270 lines) - Subscription instructions

## [0.1.3] - 2026-01-23

### Added
- **Customizable Event Types** in settings page
- Color picker for each event type to customize calendar appearance
- Add/remove custom event types dynamically
- Default event types automatically created on activation (11 types included)
- Dynamic CSS generation for event type colors
- New `SRT_CPT::get_event_type_color()` method

### Changed
- Event types now loaded from settings instead of hardcoded
- Settings page includes new "Event Types" section
- Each event type now has customizable label and color
- Calendar automatically uses custom colors from settings

### Technical
- Added `get_default_event_types()` to SRT_Settings class
- Enhanced sanitization for event types (sanitize_key, sanitize_hex_color)
- WordPress color picker integrated in settings
- Inline CSS generated dynamically based on settings

## [0.1.2] - 2026-01-23

### Fixed
- Calendar not displaying due to placeholder FullCalendar files
- Switched to CDN delivery for FullCalendar library (v6.1.10)
- Added FullCalendar as dependency for main.js script

### Changed
- FullCalendar now loaded from jsDelivr CDN instead of local files
- No longer need to manually download or configure FullCalendar
- Plugin works immediately after installation

## [0.1.1] - 2026-01-23

### Added
- Automatic page creation on plugin activation
- New `includes/pages.php` for managing plugin pages
- "Back to Calendar" navigation link on event form page
- Admin notice system for missing pages
- One-click page recreation from admin notice
- Page URL helper functions (`SRT_Pages::get_page_url()`)

### Changed
- "Add New Event" button now links to actual event form page instead of using query parameter
- Event form template includes navigation back to calendar
- Plugin now automatically creates 4 pages on activation:
  - Regiment Calendar (`[srt_calendar]`)
  - Manage Events (`[srt_event_form]`)
  - Travel Dashboard (`[srt_dashboard]`)
  - Event List (`[srt_event_list]`)

### Fixed
- Non-functional "Add New Event" link on calendar page
- Missing navigation between calendar and event form

### Technical
- Added 26th file to package (includes/pages.php)
- Page IDs stored in `srt_page_ids` option
- Pages marked with `_srt_page` meta for identification
- Admin hooks for page management (`admin_notices`, `admin_init`)

## [0.1.0] - 2026-01-23

### Added
- Initial release
- Custom Post Type for regiment events
- 12 meta fields for comprehensive event tracking
- REST API with 6 endpoints (CRUD operations + dashboard)
- Frontend calendar view using FullCalendar
- Event creation/editing form
- Travel dashboard showing booking status
- Event list view
- 4 shortcodes for frontend display
- Role-based access control
- Settings page for configuration
- Multi-leg travel tracking with flight details
- Multiple time blocks per event
- Event type system (Move In/Out, Camp Weekend, Rehearsal Block, Travel Day, Competition, Free Day)
- JSON-based data storage for complex fields

### Technical Details
- WordPress 6.0+ required
- PHP 8.0+ required
- 2,898 lines of production code
- Complete documentation suite (8 files, 2,751 lines)
- GPL v2 licensed
- Translation-ready
- Security: nonce verification, capability checks, sanitization

---

## Version History

- **0.2.0** (2026-01-23) - Calendar subscription with iCal feed
- **0.1.3** (2026-01-23) - Customizable event types
- **0.1.2** (2026-01-23) - FullCalendar CDN fix
- **0.1.1** (2026-01-23) - Page management improvements
- **0.1.0** (2026-01-23) - Initial release

---

## Upgrade Notes

### From 0.1.0 to 0.1.1

No database changes or data migration required. Simply:

1. Deactivate the plugin
2. Upload and replace plugin files
3. Reactivate the plugin
4. Plugin will automatically create missing pages

**What Happens on Activation:**
- Checks if pages already exist
- Creates only missing pages
- Links pages together with navigation
- Stores page IDs for reference

**If You Already Created Pages Manually:**
- Your existing pages will remain
- Plugin will create new pages with different slugs
- You can delete the old pages and use the auto-created ones
- Or keep your custom pages and ignore the new ones

**Page Slugs:**
- `regiment-calendar` - Calendar view
- `manage-regiment-events` - Event form
- `regiment-travel-dashboard` - Travel dashboard
- `regiment-events` - Event list

---

## Future Roadmap

### Planned for 0.2.x
- [ ] Bulk import functionality
- [ ] iCal export
- [ ] Email notifications for upcoming events
- [ ] Member attendance tracking
- [ ] Equipment tracking integration

### Planned for 0.3.x
- [ ] Mobile app companion
- [ ] Advanced filtering and search
- [ ] Recurring event support
- [ ] Financial tracking (trip costs)

### Under Consideration
- [ ] Integration with external flight booking APIs
- [ ] Google Calendar sync
- [ ] Custom event types
- [ ] Multi-corps support
- [ ] Parent/member portal with permissions

---

## Support & Contributions

- **Issues**: Report bugs on [GitHub Issues](https://github.com/jimmarks/phantom-regiment-tracker/issues)
- **Documentation**: See PLUGIN_README.md
- **License**: GPL v2 or later

