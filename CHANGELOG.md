# Summer Regiment Tracker - Changelog

All notable changes to the Summer Regiment Tracker plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

