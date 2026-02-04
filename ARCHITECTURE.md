# 🎺 Summer Regiment Tracker - Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                      SUMMER REGIMENT TRACKER                             │
│                         WordPress Plugin v0.3.7                          │
│                 Dashboard-Centric User Management System                 │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│                           MAIN PLUGIN FILE                               │
│  summer-regiment-tracker.php                                            │
│  ├─ Plugin initialization                                               │
│  ├─ Load dependencies (12 modules)                                      │
│  ├─ Hook registration                                                   │
│  ├─ Asset enqueueing                                                    │
│  ├─ User management integration                                         │
│  └─ Activation/Deactivation handlers                                    │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                ┌───────────────────┴───────────────────┐
                │                                       │
                ▼                                       ▼
┌─────────────────────────────────┐   ┌─────────────────────────────────┐
│       BACKEND (PHP)             │   │     FRONTEND (JS/CSS)           │
│                                 │   │                                 │
│  includes/                      │   │  assets/                        │
│  ├─ cpt.php                     │   │  ├─ js/main.js                  │
│  │  └─ Custom Post Type         │   │  │  ├─ Calendar init            │
│  │     - srt_event              │   │  │  ├─ Event form               │
│  │     - Event types (11)       │   │  │  ├─ Dashboard               │
│  │     - Block types (7)        │   │  │  ├─ REST API calls          │
│  │     - Travel modes (5)       │   │  │  ├─ Modal display           │
│  │                              │   │  │  └─ Flight search links     │
│  ├─ meta.php                    │   │  │                              │
│  │  └─ Metadata Management      │   │  ├─ css/styles.css             │
│  │     - Core fields            │   │  │  ├─ Responsive design       │
│  │     - Time blocks            │   │  │  ├─ Event type colors       │
│  │     - Travel legs (NEW)      │   │  │  ├─ Form styling            │
│  │       * Date + time-of-day   │   │  │  ├─ Modal styling           │
│  │       * Backward compatible  │   │  │  └─ Dashboard cards         │
│  │     - Sanitization           │   │  │                              │
│  │     - JSON validation        │   │  └─ vendor/fullcalendar/       │
│  │                              │   │     └─ Calendar library         │
│  ├─ roles.php (NEW)             │   │                                 │
│  │  └─ User Management          │   └─────────────────────────────────┘
│  │     - Member designation     │
│  │     - Parent designation     │   ┌─────────────────────────────────┐
│  │     - Parent/child links     │   │       TEMPLATES (PHP)           │
│  │     - Helper functions       │   │                                 │
│  │     - Profile integration    │   │  templates/                     │
│  │     - Admin interface        │   │  ├─ dashboard.php (ENHANCED)    │
│  │                              │   │  │  ├─ Role-based views         │
│  ├─ registration.php (NEW)      │   │  │  ├─ Main navigation bar     │
│  │  └─ Public Registration      │   │  │  ├─ Member dashboard        │
│  │     - Shortcode handler      │   │  │  ├─ Parent dashboard        │
│  │     - Form processing        │   │  │  ├─ Admin dashboard         │
│  │     - User creation          │   │  │  └─ Public welcome          │
│  │     - Auto-login             │   │  │                              │
│  │     - Email notifications    │   │  ├─ registration-form.php (NEW) │
│  │     - Relationship linking   │   │  │  ├─ User type selection     │
│  │                              │   │  │  ├─ Member fields            │
│  ├─ price-tracking.php (NEW)    │   │  │  ├─ Parent fields           │
│  │  └─ Flight Price Tracking    │   │  │  └─ Auto-linking            │
│  │     - Database tables        │   │  │                              │
│  │     - Price history          │   │  ├─ admin-manage-users.php (NEW)│
│  │     - Alert system           │   │  │  ├─ Members tab             │
│  │     - Email notifications    │   │  │  ├─ Parents tab             │
│  │     - Cron integration       │   │  │  ├─ All Users tab           │
│  │                              │   │  │  └─ Relationships tab       │
│  ├─ rest.php                    │   │  │                              │
│  │  └─ REST API                 │   │  ├─ calendar.php               │
│  │     - GET /events            │   │  │  └─ Calendar view            │
│  │     - GET /events/{id}       │   │  │                              │
│  │     - POST /events           │   │  ├─ event-form.php             │
│  │     - PUT /events/{id}       │   │  │  └─ Add/Edit form            │
│  │     - DELETE /events/{id}    │   │  │                              │
│  │     - GET /dashboard         │   │  ├─ event-list.php             │
│  │     - Auth & permissions     │   │  │  └─ Simple list view         │
│  │                              │   │  │                              │
│  ├─ pages.php                   │   │  └─ calendar-subscribe.php     │
│  │  └─ Page Management          │   │     └─ iCal subscription        │
│  │     - Auto-create pages      │   │                                 │
│  │     - Set homepage (NEW)     │   └─────────────────────────────────┘
│  │     - Page hierarchy         │
│  │                              │
│  ├─ menu.php                    │   ┌─────────────────────────────────┐
│  │  └─ Menu Management          │   │      DATABASE SCHEMA            │
│  │     - Login/Logout links     │   │                                 │
│  │     - Menu meta box          │   │  Custom Tables:                 │
│  │                              │   │  ├─ wp_srt_price_history        │
│  ├─ ical.php                    │   │  │  ├─ id                       │
│  │  └─ Calendar Export          │   │  │  ├─ route_hash              │
│  │     - iCal generation        │   │  │  ├─ origin, destination     │
│  │     - Private feeds          │   │  │  ├─ depart_date             │
│  │     - Subscribe links        │   │  │  ├─ price, currency         │
│  │                              │   │  │  ├─ airline, flight_number  │
│  ├─ settings.php                │   │  │  └─ checked_at              │
│  │  └─ Admin Settings           │   │  │                              │
│  │     - Default airport        │   │  └─ wp_srt_price_alerts        │
│  │     - Timezone               │   │     ├─ id                       │
│  │     - Require login          │   │     ├─ user_id, event_id       │
│  │     - Enable login menu      │   │     ├─ route_hash              │
│  │     - API keys (future)      │   │     ├─ alert_type              │
│  │                              │   │     ├─ threshold_price         │
│  └─ shortcodes.php              │   │     ├─ threshold_percent       │
│     └─ Shortcode Handlers       │   │     ├─ last_notified          │
│        - [srt_calendar]         │   │     └─ created_at              │
│        - [srt_event_form]       │   │                                 │
│        - [srt_dashboard]        │   │  User Meta:                     │
│        - [srt_event_list]       │   │  ├─ srt_is_member              │
│        - [srt_register] (NEW)   │   │  ├─ srt_is_parent              │
│        - [srt_calendar_subscribe]│  │  ├─ srt_parent_of (array)      │
│                                 │   │  ├─ srt_parents (array)        │
└─────────────────────────────────┘   │  ├─ srt_section                │
                                      │  └─ srt_instrument             │
                                      │                                 │
                                      └─────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│                         DATA FLOW                                        │
└─────────────────────────────────────────────────────────────────────────┘

USER ACTION                    FLOW                          RESULT
═══════════                    ════                          ══════

Register Account    →  [srt_register] shortcode  →  Form displays
                        ↓
                        Select member/parent type
                        ↓
                        Fill form & submit
                        ↓
                        POST registration handler
                        ↓
                        Create WP user + set meta
                        ↓
                        Auto-login + redirect dashboard

View Dashboard      →  Access /regiment-dashboard  →  Role-based view
                        ↓
                        Check authentication
                        ↓
                        ┌─ Not logged in → Welcome screen
                        ├─ Member → My Travel dashboard
                        ├─ Parent → Family dashboard (children's travel)
                        └─ Admin → Full dashboard + admin links
                        ↓
                        JavaScript loads
                        ↓
                        GET /wp-json/srt/v1/dashboard
                        ↓
                        Three sections rendered:
                        - Flights Needed
                        - Not Yet Booked  
                        - Upcoming Travel

View Calendar       →  [srt_calendar] shortcode  →  Calendar displays
                        ↓
                        JavaScript loads
                        ↓
                        GET /wp-json/srt/v1/events
                        ↓
                        Events rendered on calendar

Click Event         →  Event click handler  →  Modal opens
                        ↓
                        Show event details
                        (time blocks, travel legs)
                        ↓
                        Flight search links (date + time-of-day)

Add Event          →  [srt_event_form] shortcode  →  Form displays
                        ↓
                        Fill form + add blocks/legs
                        ↓
                        Add travel legs (date + time-of-day)
                        ↓
                        POST /wp-json/srt/v1/events
                        ↓
                        Validation & sanitization
                        ↓
                        Save to wp_postmeta
                        ↓
                        Success message

Track Prices       →  Cron job (twicedaily)  →  Check prices
                        ↓
                        Query srt_price_alerts table
                        ↓
                        For each active alert:
                        ├─ Fetch current price (API or manual)
                        ├─ Compare to history
                        ├─ Check threshold conditions
                        └─ If triggered:
                           ├─ Get notification recipients
                           │  (member + all parents)
                           ├─ Send email alerts
                           └─ Update last_notified

Manage Users       →  Admin menu → Regiment Users  →  Tabbed interface
(Admin)                 ↓
                        Select tab:
                        ├─ Members → Add/remove member status
                        ├─ Parents → Add/remove parent status
                        ├─ All Users → View roster
                        └─ Relationships → Link parent/child
                        ↓
                        POST action handler
                        ↓
                        Update user meta
                        ↓
                        Success message

┌─────────────────────────────────────────────────────────────────────────┐
│                      DATA MODEL                                          │
└─────────────────────────────────────────────────────────────────────────┘

WordPress Post (srt_event)
├─ post_title              → Event title
├─ post_content            → Event description
└─ post_meta               → Custom fields
   │
   ├─ Core Fields (8)
   │  ├─ start_datetime          [ISO8601]
   │  ├─ end_datetime            [ISO8601]
   │  ├─ timezone                [string]
   │  ├─ event_type              [enum: 11 types]
   │  ├─ location_name           [string]
   │  ├─ location_address        [text]
   │  └─ notes                   [rich text]
   │
   ├─ Time Blocks (JSON array)
   │  └─ Each block:
   │     ├─ block_type           [enum: 7 types]
   │     ├─ start_datetime       [ISO8601]
   │     ├─ end_datetime         [ISO8601]
   │     ├─ title                [string]
   │     └─ notes                [text]
   │
   └─ Travel (4 fields)
      ├─ travel_needed           [boolean]
      ├─ travel_mode             [enum: 5 modes]
      ├─ flight_needed           [boolean]
      └─ travel_legs             [JSON array]
         └─ Each leg (NEW FORMAT):
            ├─ leg_name           [string]
            ├─ mode               [enum]
            ├─ depart_location    [string]
            ├─ depart_airport     [IATA]
            ├─ arrive_location    [string]
            ├─ arrive_airport     [IATA]
            ├─ depart_date        [YYYY-MM-DD] ← NEW
            ├─ depart_time_of_day [morning|midday|evening|night] ← NEW
            ├─ arrive_date        [YYYY-MM-DD] ← NEW
            ├─ arrive_time_of_day [morning|midday|evening|night] ← NEW
            ├─ depart_datetime    [ISO8601] (backward compat)
            ├─ arrive_datetime    [ISO8601] (backward compat)
            ├─ airline            [string]
            ├─ flight_number      [string]
            ├─ booked             [boolean]
            ├─ confirmation       [string]
            ├─ baggage            [array of enums]
            ├─ pickup_plan        [text]
            └─ notes              [text]

WordPress Users (wp_users)
└─ user_meta               → User metadata
   │
   ├─ User Roles (Meta-based)
   │  ├─ srt_is_member           [boolean]
   │  ├─ srt_is_parent           [boolean]
   │  ├─ srt_parent_of           [array of user IDs]
   │  └─ srt_parents             [array of user IDs]
   │
   └─ Member Info
      ├─ srt_section             [enum: brass|percussion|color_guard|front_ensemble]
      └─ srt_instrument          [string]

Custom Tables (wp_srt_*)
│
├─ price_history
│  └─ Tracks flight prices over time
│     ├─ id (PK)
│     ├─ route_hash            [MD5 of origin-destination-date]
│     ├─ origin_airport        [IATA]
│     ├─ destination_airport   [IATA]
│     ├─ depart_date           [DATE]
│     ├─ price                 [DECIMAL]
│     ├─ currency              [VARCHAR]
│     ├─ airline               [VARCHAR]
│     ├─ flight_number         [VARCHAR]
│     ├─ source                [enum: api|manual|scrape]
│     └─ checked_at            [DATETIME]
│
└─ price_alerts
   └─ User price alert subscriptions
      ├─ id (PK)
      ├─ user_id              [INT → wp_users]
      ├─ event_id             [INT → wp_posts]
      ├─ leg_index            [INT]
      ├─ route_hash           [VARCHAR]
      ├─ alert_type           [enum: price_drop|percent_drop|good_deal]
      ├─ threshold_price      [DECIMAL]
      ├─ threshold_percent    [INT]
      ├─ is_active            [BOOLEAN]
      ├─ last_notified        [DATETIME]
      └─ created_at           [DATETIME]

┌─────────────────────────────────────────────────────────────────────────┐
│                      SECURITY LAYERS                                     │
└─────────────────────────────────────────────────────────────────────────┘

Layer 1: WordPress Core
├─ User authentication
├─ Session management
└─ HTTPS (recommended)

Layer 2: Plugin Capability Checks
├─ is_user_logged_in() → View permission
├─ current_user_can('edit_posts') → Create/Edit
└─ current_user_can('delete_posts') → Delete

Layer 3: REST API Nonce
├─ wp_create_nonce('wp_rest')
├─ X-WP-Nonce header validation
└─ Permission callbacks on each endpoint

Layer 4: Input Sanitization
├─ sanitize_text_field()
├─ sanitize_textarea_field()
├─ wp_kses_post()
├─ rest_sanitize_boolean()
└─ Custom JSON validators

Layer 5: Output Escaping
├─ esc_html()
├─ esc_attr()
├─ esc_url()
└─ wp_json_encode()

Layer 6: SQL Injection Prevention
└─ WP_Query (prepared statements)

┌─────────────────────────────────────────────────────────────────────────┐
│                    USER ROLES & PERMISSIONS                              │
└─────────────────────────────────────────────────────────────────────────┘

┌──────────────┬──────┬────────┬──────┬────────┬──────────┬──────────────┐
│     Role     │ View │ Create │ Edit │ Delete │ Settings │ Manage Users │
├──────────────┼──────┼────────┼──────┼────────┼──────────┼──────────────┤
│ Admin        │  ✅  │   ✅   │  ✅  │   ✅   │    ✅    │      ✅      │
│ Editor       │  ✅  │   ✅   │  ✅  │   ✅   │    ❌    │      ❌      │
│ Author       │  ✅  │   ❌   │  ❌  │   ❌   │    ❌    │      ❌      │
│ Member**     │  ✅* │   ❌   │  ❌  │   ❌   │    ❌    │      ❌      │
│ Parent**     │  ✅* │   ❌   │  ❌  │   ❌   │    ❌    │      ❌      │
│ Subscriber   │  ✅* │   ❌   │  ❌  │   ❌   │    ❌    │      ❌      │
│ Public       │  ✅* │   ❌   │  ❌  │   ❌   │    ❌    │      ❌      │
└──────────────┴──────┴────────┴──────┴────────┴──────────┴──────────────┘

* If "Require Login" setting is disabled
** Member/Parent are user meta designations, not WordPress roles

User Meta-Based System (NEW):
├─ Members (srt_is_member = true)
│  ├─ Can view personalized dashboard showing their events
│  ├─ Receive price alerts for their assigned flights
│  └─ Profile shows section/instrument
│
├─ Parents (srt_is_parent = true)
│  ├─ Linked to one or more member children
│  ├─ View family dashboard showing children's events
│  ├─ Receive price alerts for all children's flights
│  └─ Can be both parent AND member simultaneously
│
└─ Admin (WordPress capability)
   ├─ All member/parent capabilities
   ├─ Can manage events (create/edit/delete)
   ├─ Can manage users (make member/parent, link relationships)
   └─ Access to admin interface and settings

Parent-Child Relationships:
├─ Stored in user meta (srt_parent_of / srt_parents)
├─ Many-to-many (one parent can have multiple children)
├─ Automatic notification cascade (child + all parents)
└─ Admin can link/unlink via Manage Users interface

┌─────────────────────────────────────────────────────────────────────────┐
│                      INTEGRATION POINTS                                  │
└─────────────────────────────────────────────────────────────────────────┘

WordPress Integration
├─ Custom Post Type (wp_posts table)
├─ Post Meta (wp_postmeta table)
├─ REST API (wp-json/srt/v1/*)
├─ Shortcodes (content integration)
├─ Admin Menu (settings page)
└─ Enqueue System (scripts/styles)

External Libraries
└─ FullCalendar v6.1.10 (MIT License)
   ├─ CDN option (recommended)
   └─ Local bundle option

Extensibility
├─ WordPress Hooks
│  ├─ add_filter('srt_event_types', ...)
│  ├─ add_action('srt_save_event', ...)
│  └─ Custom filters available
├─ REST API
│  └─ Full CRUD via JSON
└─ Template Override
   └─ Copy templates to theme folder

┌─────────────────────────────────────────────────────────────────────────┐
│                        PERFORMANCE                                       │
└─────────────────────────────────────────────────────────────────────────┘

Database Queries
├─ Optimized WP_Query usage
├─ Meta query with date filters
├─ Custom tables for price tracking (indexed)
├─ User meta for relationships (efficient lookups)
└─ Indexes on meta_key and route_hash

Asset Loading
├─ Conditional enqueuing (only on shortcode pages)
├─ Minified library references
├─ Single main.js file (~800 lines with backward compatibility)
└─ Inline CSS in dashboard template (reduces HTTP requests)

Caching Considerations
├─ WordPress object cache compatible
├─ Transient API ready
├─ Page cache friendly (REST API)
└─ Price history caching (reduces API calls)

Scaling
├─ Events: Works well 100-500, good 500-1000, paginate 1000+
├─ Users: Handles 100s of members/parents efficiently
├─ Price History: 30-day rolling window prevents bloat
├─ Dashboard queries date-limited (14 days)
└─ Cron: Scheduled price checks (twicedaily) vs real-time

Background Processing
├─ WordPress Cron for price checks
├─ Email queue integration (WP mail)
├─ Batch processing for multiple alerts
└─ Rate limiting for API calls (future)

┌─────────────────────────────────────────────────────────────────────────┐
│                         FILE STRUCTURE                                   │
└─────────────────────────────────────────────────────────────────────────┘

summer-regiment-tracker/
│
├── summer-regiment-tracker.php    ← Main plugin file (v0.3.7)
│
├── includes/                      ← Backend logic (12 modules)
│   ├── cpt.php                    ← Custom Post Type
│   ├── meta.php                   ← Metadata handling
│   ├── rest.php                   ← REST API
│   ├── settings.php               ← Admin settings
│   ├── shortcodes.php             ← Shortcode handlers
│   ├── pages.php                  ← Page management & auto-creation
│   ├── menu.php                   ← Menu management (login/logout)
│   ├── ical.php                   ← iCal export & subscriptions
│   ├── roles.php                  ← User management (NEW)
│   ├── registration.php           ← Public registration (NEW)
│   └── price-tracking.php         ← Flight price tracking (NEW)
│
├── assets/                        ← Frontend assets
│   ├── js/
│   │   └── main.js                ← JavaScript (date/time-of-day logic)
│   ├── css/
│   │   └── styles.css             ← Styles
│   └── vendor/
│       └── fullcalendar/          ← Calendar library
│           ├── README.txt
│           ├── fullcalendar.min.css
│           └── fullcalendar.min.js
│
├── templates/                     ← Frontend templates (7 files)
│   ├── dashboard.php              ← Main hub (role-based views)
│   ├── calendar.php               ← Calendar view
│   ├── event-form.php             ← Event creation/editing
│   ├── event-list.php             ← List view
│   ├── calendar-subscribe.php     ← iCal subscription
│   ├── registration-form.php      ← Public registration (NEW)
│   └── admin-manage-users.php     ← User management interface (NEW)
│
├── download/                      ← Release packages (gitignored)
│   └── summer-regiment-tracker-v*.zip
│
├── package/                       ← Build directory (gitignored)
│   └── summer-regiment-tracker/   (staging for zip creation)
│
└── [Documentation]                ← Guides & references
    ├── ARCHITECTURE.md            ← This file (system architecture)
    ├── PLUGIN_README.md           ← Main documentation
    ├── NAVIGATION_GUIDE.md        ← User journey & navigation (NEW)
    ├── REGISTRATION_GUIDE.md      ← User management guide (NEW)
    ├── INSTALL.md                 ← Installation guide
    ├── SUMMARY.md                 ← Technical summary
    ├── QUICK_REFERENCE.md         ← Quick reference
    ├── DEPLOYMENT_CHECKLIST.md    ← Deployment guide
    ├── PROJECT_COMPLETE.md        ← Completion report
    ├── build-package.sh           ← Build script
    ├── .gitignore                 ← Git exclusions (NEW)
    └── LICENSE                    ← GPL v2 license

Total Files:
├─ PHP Code: 22 files (12 includes + 7 templates + 3 root)
├─ JavaScript: 2 files (main.js + fullcalendar)
├─ CSS: 2 files (styles.css + fullcalendar.css)
├─ Documentation: 11 markdown files
├─ Build/Config: 2 files (build-package.sh, .gitignore)
└─ Total: ~40 files (excluding build artifacts)

Estimated Lines of Code: ~4,500+ lines

┌─────────────────────────────────────────────────────────────────────────┐
│                       DEPLOYMENT WORKFLOW                                │
└─────────────────────────────────────────────────────────────────────────┘

Development
    ↓
1. Run build script: ./build-package.sh
   - Auto-increments version
   - Validates PHP syntax
   - Creates package in download/
    ↓
2. Upload download/summer-regiment-tracker-v*.zip to WordPress
    ↓
3. Activate plugin
    ↓
4. Configure settings (Settings → Regiment Tracker)
   - Default airport
   - Timezone
   - Login requirements
   - Page creation
    ↓
5. Create plugin pages (automatic)
   - Dashboard (set as homepage)
   - Calendar
   - All Events
   - Manage Events
   - Registration page (add [srt_register] shortcode)
    ↓
6. Configure navigation
   - Add pages to WordPress menu
   - Dashboard shows navigation automatically
    ↓
7. Set up user management (Admin → Regiment Users)
   - Make initial users members/parents
   - Link parent-child relationships
   - Test registration flow
    ↓
8. Add test events
   - Create event with travel legs
   - Use date + time-of-day format
   - Test flight search links
    ↓
9. Test price tracking (optional future feature)
   - Configure API keys
   - Enable price alerts
   - Test notifications
    ↓
10. Verify functionality
    - Test as member
    - Test as parent
    - Test as admin
    - Test registration
    ↓
11. Load season schedule
    ↓
12. Train staff & families
    ↓
Production Ready! ✅

Page Hierarchy (NEW):
1. Dashboard (Homepage) - Main hub, role-based views
2. Calendar - Visual event calendar
3. All Events - List view
4. Manage Events - Admin only
5. Register - Public registration form

Navigation Structure:
├─ Top navigation bar (on dashboard)
│  ├─ Calendar
│  ├─ All Events
│  ├─ Manage Events (admin)
│  ├─ Manage Users (admin)
│  └─ Login/Logout
└─ Welcome screen (not logged in)
   ├─ Login button
   ├─ Register button
   └─ View Calendar link

═══════════════════════════════════════════════════════════════════════════

PLUGIN STATUS: ✅ IN ACTIVE DEVELOPMENT

Version: 0.3.7
Last Updated: January 25, 2026
Architecture: Dashboard-Centric User Management System
License: GPL v2

Core Features:
├─ ✅ Event management with time blocks
├─ ✅ Travel legs (date + time-of-day format)
├─ ✅ Full calendar integration
├─ ✅ Dashboard with 3 travel panels
├─ ✅ User registration system
├─ ✅ Parent/child relationship tracking
├─ ✅ Role-based dashboard views
├─ ✅ Admin user management interface
├─ ✅ iCal export & subscriptions
├─ ✅ Flight search link generation
└─ ✅ Backward compatibility for old data

In Development:
├─ 🔄 Price tracking database schema (ready)
├─ 🔄 Alert system logic (ready)
├─ 🔄 Email notifications (ready)
└─ ⏳ API integration (Amadeus) or manual price entry

Future Enhancements:
├─ 📋 User assignment to specific events/legs
├─ 📊 Price history visualization (charts)
├─ 🔔 Real-time price alerts
├─ 📱 Mobile app integration
├─ 📦 Bulk event import
└─ 🚗 Carpool coordination

System Highlights:
├─ Meta-based user system (not custom roles)
├─ Dashboard as main entry point (homepage)
├─ Automatic page creation
├─ Built-in navigation system
├─ Public registration workflow
├─ Multi-role support (admin can be parent)
├─ Notification cascade (member + all parents)
└─ Clean build system with download/ directory

Ready for regiment travel management! 🎺
```
