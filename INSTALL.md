# Family Travel Tracker - Installation & Quick Start Guide

## 🚀 Quick Installation

### 1. Install the Plugin

**Option A: Direct Upload to WordPress**
```bash
# Zip the plugin directory
cd /path/to/parent-directory
zip -r family-travel-tracker.zip schedule-collaboration-tracking/

# Then upload via WordPress Admin:
# Plugins → Add New → Upload Plugin → Choose File → Install Now → Activate
```

**Option B: Manual Installation**
```bash
# Copy to your WordPress plugins directory
cp -r schedule-collaboration-tracking /path/to/wordpress/wp-content/plugins/

# Then activate via WordPress Admin:
# Plugins → Installed Plugins → Family Travel Tracker → Activate
```

### 2. Install FullCalendar Library

**IMPORTANT**: The plugin requires FullCalendar for the calendar view.

**Option A: Use CDN (Recommended - No Download Required)**

Edit `summer-regiment-tracker.php` around line 85 and replace the FullCalendar enqueue calls:

```php
// Replace these lines (around line 85-95):
wp_enqueue_style(
    'srt-fullcalendar',
    SRT_PLUGIN_URL . 'assets/vendor/fullcalendar/fullcalendar.min.css',
    array(),
    '6.1.10'
);

wp_enqueue_script(
    'srt-fullcalendar',
    SRT_PLUGIN_URL . 'assets/vendor/fullcalendar/fullcalendar.min.js',
    array(),
    '6.1.10',
    true
);

// With these CDN links:
wp_enqueue_style(
    'srt-fullcalendar',
    'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css',
    array(),
    '6.1.10'
);

wp_enqueue_script(
    'srt-fullcalendar',
    'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js',
    array(),
    '6.1.10',
    true
);
```

**Option B: Local Files**
1. Download FullCalendar from https://fullcalendar.io/
2. Extract these files to `/assets/vendor/fullcalendar/`:
   - `fullcalendar.min.js`
   - `fullcalendar.min.css`

### 3. Configure Settings

1. Go to **Regiment Events → Settings**
2. Configure:
   - ✅ **Default Home Airport**: Enter IATA code (e.g., `ORD`)
   - ✅ **Default Timezone**: Select your timezone
   - ✅ **Require Login**: Check if you want to restrict viewing

## 📄 Create Pages with Shortcodes

### Calendar Page
1. Create new page: "Regiment Calendar"
2. Add shortcode: `[srt_calendar]`
3. Publish

### Event Management Page (Admin Only)
1. Create new page: "Manage Events"
2. Add shortcode: `[srt_event_form]`
3. Publish
4. Note: Only editors/admins can access this

### Dashboard Page
1. Create new page: "Travel Dashboard"
2. Add shortcode: `[srt_dashboard]`
3. Publish

### Event List Page
1. Create new page: "Event List"
2. Add shortcode: `[srt_event_list]`
3. Optional attributes:
   - `[srt_event_list limit="20"]` - Show 20 events
   - `[srt_event_list type="performance_day"]` - Filter by type

## 🎯 Quick Start: Adding Your First Event

### Step 1: Navigate to Event Form
- Go to the page with `[srt_event_form]` shortcode
- Or use WordPress Admin: Regiment Events → Add New (then use the form page)

### Step 2: Fill Basic Information
- **Event Title**: "Move-In Weekend"
- **Start Date/Time**: Select date and time
- **End Date/Time**: Select date and time
- **Event Type**: "Move In"
- **Location Name**: "Camp Location"
- **Location Address**: Full address
- **Notes**: Any additional details

### Step 3: Add Time Blocks (Optional)
Click "+ Add Time Block" for multiple activities:
- **Block 1**: Practice, 9:00 AM - 12:00 PM
- **Block 2**: Meal, 12:00 PM - 1:00 PM
- **Block 3**: Practice, 1:00 PM - 5:00 PM

### Step 4: Add Travel (Optional)
If travel is needed:
1. Check "Travel Needed"
2. Select "Primary Travel Mode"
3. If flying, check "Flight Needed"
4. Click "+ Add Travel Leg"
5. Fill in:
   - Leg Name: "Home to Camp"
   - Mode: "Fly"
   - Depart: Home city + airport code
   - Arrive: Camp city + airport code
   - Times, airline, flight number
   - Check "Booked" when confirmed
   - Add confirmation number

### Step 5: Save
Click "Save Event"

## 📊 Using the Dashboard

The dashboard shows three critical views:

### Flights Needed
All upcoming events marked with "Flight Needed"

### Not Yet Booked
Events with flights that haven't been marked as "Booked"
- **Action**: Click event to view details and add booking info

### Upcoming Travel (Next 14 Days)
All travel happening in the next two weeks
- **Use**: Quick reference for imminent travel

## 🎨 Customizing Event Types

Events are color-coded on the calendar:

- 🟢 **Move In** - Green
- 🔴 **Move Out** - Red
- 🔵 **Camp Weekend** - Blue
- 🟣 **Rehearsal Block** - Purple
- 🟠 **Travel Day** - Orange
- 🔴 **Performance Day** - Pink
- 🔵 **Housing Check-In** - Cyan
- 🔴 **Medical** - Red
- 🟤 **Uniform Fitting** - Brown
- ⚫ **Admin Deadline** - Gray
- ⚪ **Other** - Light Gray

## 🔐 User Permissions

### Administrator
- ✅ Create events
- ✅ Edit events
- ✅ Delete events
- ✅ View all events
- ✅ Access settings

### Editor
- ✅ Create events
- ✅ Edit events
- ✅ Delete events
- ✅ View all events

### Other Roles (Subscriber, etc.)
- ✅ View events (if "Require Login" is disabled)
- ❌ Cannot create/edit events

## 🧪 Testing the Plugin

### Test Calendar
1. Add 2-3 test events
2. Navigate to calendar page
3. Verify events appear
4. Click event to view details modal

### Test Event Form
1. Navigate to event form page
2. Add a complete event with time blocks and travel
3. Save and verify in calendar

### Test Dashboard
1. Add event with flight marked "Not Booked"
2. Navigate to dashboard
3. Verify it appears in "Not Yet Booked" section
4. Edit event, mark as "Booked"
5. Refresh dashboard - should move to "Flights Needed" only

## 🔧 Troubleshooting

### Calendar Not Displaying
- ✅ Check browser console for errors
- ✅ Verify FullCalendar is loaded (see CDN option above)
- ✅ Ensure you're on a page with `[srt_calendar]` shortcode

### Events Not Saving
- ✅ Check you're logged in as Admin or Editor
- ✅ Verify all required fields are filled (Title, Start, End)
- ✅ Check browser console for JavaScript errors

### REST API Errors
- ✅ Verify WordPress REST API is enabled
- ✅ Check permalink settings (Settings → Permalinks → Save)
- ✅ Test API directly: `yoursite.com/wp-json/srt/v1/events`

### Permission Denied
- ✅ Verify user role has correct permissions
- ✅ Check "Require Login" setting if not logged in
- ✅ Ensure user has `edit_posts` capability for forms

## 📱 Mobile Considerations

The plugin is responsive and works on mobile, but:
- Calendar works best on tablets and desktops
- Event forms are fully functional on mobile
- Dashboard and event lists are optimized for all screen sizes

## 🎓 Best Practices

### Event Organization
- ✅ Use consistent naming: "Event Type - Location - Date"
- ✅ Always set timezone for events in different regions
- ✅ Add detailed notes for complex events

### Travel Management
- ✅ Add travel legs as soon as itinerary is planned
- ✅ Mark as "Booked" immediately after confirming
- ✅ Include confirmation numbers for easy reference
- ✅ Add pickup plans for coordination

### Time Blocks
- ✅ Break long days into distinct blocks
- ✅ Include meal times for planning
- ✅ Add buffer times between activities

## 📚 Next Steps

1. ✅ Install and activate plugin
2. ✅ Configure settings
3. ✅ Create pages with shortcodes
4. ✅ Add test events
5. ✅ Train staff on event management
6. ✅ Import season schedule
7. ✅ Monitor dashboard for booking needs

## 🆘 Getting Help

- 📖 Full documentation: See PLUGIN_README.md
- 🐛 Report bugs: GitHub Issues
- 💬 Questions: GitHub Discussions

## 🚀 You're Ready!

The plugin is now installed and ready to track your summer regiment season!

Start by adding your first few events and exploring the calendar and dashboard views.
