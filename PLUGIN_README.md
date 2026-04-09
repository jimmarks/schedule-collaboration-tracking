# Summer Regiment Tracker

A comprehensive WordPress plugin for tracking Summer Phantom Regiment schedule events with advanced travel and flight tracking capabilities.

## Description

Summer Regiment Tracker provides a complete solution for managing drum corps season events, including:

- **Event Scheduling**: Track all season events with detailed information
- **Multi-Block Time Management**: Support multiple time blocks per day (practice, meals, performances)
- **Travel Tracking**: Multi-leg itinerary support for complex travel scenarios
- **Flight Management**: Track flight details, booking status, and confirmation numbers
- **Dashboard Views**: Quick overview of upcoming flights, unbooked flights, and travel needs
- **Calendar Interface**: Visual calendar with color-coded event types
- **Front-End Management**: Add and edit events from the front-end (no wp-admin required)

## Features

### Core Features
- Custom Post Type for events with comprehensive metadata
- REST API endpoints for CRUD operations
- Front-end shortcodes for all major views
- Role-based access control
- Optional login requirement for viewing schedules

### Event Management
- Date/time ranges with timezone support
- Event type categorization (move-in, rehearsal, performance, travel, etc.)
- Location tracking with address
- Rich text notes

### Time Blocks
- Multiple time blocks per event
- Different block types (practice, travel, meal, medical, performance, etc.)
- Individual start/end times per block

### Travel & Flight Tracking
- Multi-leg itinerary support
- Flight details (airline, flight number, airports)
- Booking status tracking
- Confirmation numbers
- Baggage tracking (carry-on, checked, oversized instruments)
- Pickup plans and notes
- Support for drive, bus, shuttle, and other travel modes

### Views
- **Calendar View**: Interactive calendar with event details
- **Event Form**: Add/edit events with all features
- **Dashboard**: Three key sections:
  - Flights Needed (upcoming)
  - Flights Not Yet Booked
  - Upcoming Travel (next 14 days)
- **Event List**: Simple chronological list of events

## Installation

### Standard Installation

1. **Download the Plugin**
   - Download or clone this repository
   - If cloning, ensure the folder is named `summer-regiment-tracker`

2. **Upload to WordPress**
   ```
   wp-content/plugins/summer-regiment-tracker/
   ```

3. **Install FullCalendar Library**
   
   **Option A: Use CDN (Recommended)**
   
   Edit `summer-regiment-tracker.php` and replace the FullCalendar enqueue calls with CDN links:
   
   ```php
   // FullCalendar CSS
   wp_enqueue_style(
       'srt-fullcalendar',
       'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css',
       array(),
       '6.1.10'
   );
   
   // FullCalendar JS
   wp_enqueue_script(
       'srt-fullcalendar',
       'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js',
       array(),
       '6.1.10',
       true
   );
   ```
   
   **Option B: Local Files**
   
   - Download FullCalendar v6.1.10 from https://fullcalendar.io/
   - Extract `fullcalendar.min.js` and `fullcalendar.min.css`
   - Place in `/assets/vendor/fullcalendar/` directory

4. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Find "Summer Regiment Tracker"
   - Click "Activate"

5. **Configure Settings**
   - Go to Regiment Events → Settings
   - Set default home airport (IATA code)
   - Configure timezone
   - Choose whether to require login for viewing

## Usage

### Creating Pages

Create WordPress pages for each view and add the appropriate shortcode:

#### Calendar Page
```
[srt_calendar]
```
Shows the interactive calendar view with all events.

#### Event Management Page (Admin/Editor only)
```
[srt_event_form]
```
Displays the add/edit event form. Only visible to users with edit_posts capability.

#### Dashboard Page
```
[srt_dashboard]
```
Shows three dashboard sections:
- Flights Needed
- Not Yet Booked
- Upcoming Travel (next 14 days)

#### Simple Event List
```
[srt_event_list]
```
Displays a simple chronological list of upcoming events.

**Optional attributes:**
- `limit="10"` - Number of events to show (default: 10)
- `type="performance_day"` - Filter by event type

Example: `[srt_event_list limit="5" type="performance_day"]`

### Adding Events

1. **Via Front-End** (Recommended for non-admins)
   - Navigate to the page with `[srt_event_form]` shortcode
   - Fill in event details
   - Add time blocks as needed
   - Add travel legs if applicable
   - Click "Save Event"

2. **Via WordPress Admin**
   - Go to Regiment Events → Add New
   - Fill in the title and content
   - Event metadata can be added via the form page

### Managing Travel

For events with travel:

1. Check "Travel Needed"
2. Select primary travel mode
3. If flying, check "Flight Needed"
4. Add travel legs:
   - Each leg can be a different mode
   - Enter departure and arrival locations/airports
   - Add flight details (airline, flight number)
   - Mark as booked when confirmed
   - Enter confirmation number
   - Select baggage types
   - Add pickup plan and notes

### Event Types

Available event types:
- **Move In** - Initial move-in day
- **Move Out** - Season end move-out
- **Camp Weekend** - Weekend camp sessions
- **Rehearsal Block** - Multi-day rehearsal periods
- **Travel Day** - Dedicated travel days
- **Performance Day** - Competition or show days
- **Housing Check-In** - Hotel/housing check-in
- **Medical** - Medical appointments or checks
- **Uniform Fitting** - Uniform fitting sessions
- **Admin Deadline** - Important deadlines
- **Other** - Miscellaneous events

### Time Block Types

Available time block types:
- Practice
- Travel
- Admin
- Meal
- Medical
- Performance
- Other

### Travel Modes

Available travel modes:
- Fly
- Drive
- Bus
- Shuttle
- Other

## Access Control

### User Roles

- **Administrator**: Full access to all features
- **Editor**: Can create, edit, and delete events
- **Other Roles**: View-only (unless "Require Login" is enabled in settings)

### Settings

Go to Regiment Events → Settings:

- **Require Login**: Force users to log in to view schedules
- **Default Home Airport**: Set default IATA airport code (e.g., ORD)
- **Default Timezone**: Set default timezone for new events

## REST API

The plugin provides REST API endpoints at `/wp-json/srt/v1/`:

### Endpoints

- `GET /events` - List events
  - Query params: `start_date`, `end_date`, `event_type`
- `GET /events/{id}` - Get single event
- `POST /events` - Create event (requires authentication)
- `PUT /events/{id}` - Update event (requires authentication)
- `DELETE /events/{id}` - Delete event (requires authentication)
- `GET /dashboard` - Get dashboard data

### Authentication

API requests require WordPress nonce authentication:
```javascript
headers: {
    'X-WP-Nonce': wpApiSettings.nonce
}
```

## Technical Details

### File Structure

```
summer-regiment-tracker/
├── summer-regiment-tracker.php    # Main plugin file
├── includes/
│   ├── cpt.php                    # Custom post type registration
│   ├── meta.php                   # Meta field registration & sanitization
│   ├── rest.php                   # REST API endpoints
│   ├── settings.php               # Settings page
│   └── shortcodes.php             # Shortcode handlers
├── assets/
│   ├── js/
│   │   └── main.js                # Frontend JavaScript
│   ├── css/
│   │   └── styles.css             # Frontend styles
│   └── vendor/
│       └── fullcalendar/          # FullCalendar library
├── templates/
│   ├── calendar.php               # Calendar view template
│   ├── event-form.php             # Event form template
│   ├── dashboard.php              # Dashboard template
│   └── event-list.php             # Event list template
└── README.md                      # This file
```

### Data Storage

All event data is stored as WordPress post meta:

**Core Fields:**
- `start_datetime` - ISO8601 datetime string
- `end_datetime` - ISO8601 datetime string
- `timezone` - Timezone identifier
- `event_type` - Event type enum
- `location_name` - Location name
- `location_address` - Location address
- `notes` - Event notes

**Travel Fields:**
- `travel_needed` - Boolean
- `travel_mode` - Travel mode enum
- `flight_needed` - Boolean

**Complex Fields (JSON):**
- `time_blocks` - Array of time block objects
- `travel_legs` - Array of travel leg objects

### Browser Compatibility

- Modern browsers (Chrome, Firefox, Safari, Edge)
- IE11 not supported (uses modern JavaScript)

### WordPress Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher

## Customization

### Styling

Override plugin styles in your theme:

```css
/* Override event type colors */
.ftt-event-type-performance_day {
    background-color: #your-color !important;
}

/* Customize dashboard items */
.ftt-dashboard-item {
    /* Your custom styles */
}
```

### Extending

The plugin uses WordPress hooks for extensibility:

```php
// Modify event types
add_filter('srt_event_types', function($types) {
    $types['custom_type'] = 'Custom Type';
    return $types;
});

// Add custom meta fields
add_action('srt_save_event_meta', function($post_id, $request) {
    // Save custom meta
}, 10, 2);
```

## Troubleshooting

### Calendar Not Showing

1. Check that FullCalendar library is loaded (check browser console)
2. Ensure shortcode is on the page: `[srt_calendar]`
3. Verify REST API is accessible: Visit `/wp-json/srt/v1/events`

### Events Not Saving

1. Check browser console for JavaScript errors
2. Verify user has `edit_posts` capability
3. Check REST API nonce is valid

### Permission Errors

1. Verify user role and capabilities
2. Check "Require Login" setting
3. Ensure nonce is being passed correctly

## Support

For issues, feature requests, or contributions:

- GitHub: https://github.com/jimmarks/phantom-regiment-tracker
- Report bugs via GitHub Issues

## License

GPL v2 or later

## Credits

- Built for Summer Phantom Regiment season management
- Uses FullCalendar library (MIT License) - https://fullcalendar.io/
- Developed by Jim Marks

## Changelog

### Version 1.0.0
- Initial release
- Event management with custom post type
- Time blocks support
- Multi-leg travel tracking
- Flight booking status
- Calendar view
- Dashboard view
- Event list view
- Front-end event form
- REST API endpoints
- Settings page
- Role-based access control

## Roadmap

Potential future features:
- iCalendar export
- Email notifications for unbooked flights
- Google Maps integration
- Member assignment to events
- Attendance tracking
- Equipment tracking
- Mobile app integration
