# Summer Regiment Tracker WordPress Plugin

A comprehensive WordPress plugin for managing summer drum corps schedules with front-end calendar, event editor, and flight tracking capabilities.

## Features

- **Front-End Calendar**: Display events in a monthly calendar view without requiring wp-admin access
- **Event Management**: Create and edit events with multiple time blocks per day
- **Time Blocks**: Support for different activity types:
  - Practice
  - Travel
  - Admin
  - Performance
  - Meal
  - Other
- **Flight Tracking**: Track multi-leg flights with:
  - Departure and arrival airports
  - Flight times
  - Booking status
  - Booking reference numbers
- **Dashboard**: View flight booking status at a glance

## Installation

1. Upload the `summer-regiment-tracker` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will automatically create the necessary database tables

## Usage

### Shortcodes

The plugin provides three shortcodes for front-end display:

#### Calendar View
```
[srt_calendar]
```
Displays a monthly calendar with all scheduled events. Users can navigate between months using previous/next buttons.

#### Event Form
```
[srt_event_form]
```
Displays a form to create a new event.

To edit an existing event:
```
[srt_event_form id="123"]
```
Replace `123` with the event ID.

#### Dashboard
```
[srt_dashboard]
```
Displays a dashboard showing:
- Total number of flights
- Number of booked flights
- Number of flights needing booking
- Detailed list of unbooked flights

### Using the Event Form

1. **Basic Event Information**:
   - Event Title (required)
   - Event Date (required)
   - Location (optional)
   - Description (optional)

2. **Time Blocks**:
   - Click "Add Time Block" to add a new time block
   - Select the block type (Practice, Travel, Admin, etc.)
   - Set start and end times
   - Add optional notes
   - Click "Remove" to delete a time block

3. **Flight Information** (optional):
   - Click "Add Flight Leg" to add a flight
   - Enter departure and arrival airports (e.g., ORD, LAX)
   - Set departure and arrival times
   - Check "Booked" if the flight is already booked
   - Enter booking reference if available
   - Click "Remove" to delete a flight leg

4. Click "Save Event" to save the event

### Managing Flights

From the dashboard:
- View all unbooked flights
- Click "Mark as Booked" to update flight status
- Flights are organized by event and leg number

## Database Tables

The plugin creates three custom tables:

1. `wp_srt_events`: Stores event information
2. `wp_srt_time_blocks`: Stores time blocks associated with events
3. `wp_srt_flights`: Stores flight information with multi-leg support

## Technical Details

### Files Structure
```
summer-regiment-tracker/
├── summer-regiment-tracker.php (Main plugin file)
├── includes/
│   ├── database.php (Database schema and creation)
│   ├── events.php (Event management functions)
│   ├── flights.php (Flight tracking functions)
│   ├── shortcodes.php (Shortcode implementations)
│   └── ajax-handlers.php (AJAX request handlers)
├── assets/
│   ├── css/
│   │   └── styles.css (Frontend styles)
│   └── js/
│       └── scripts.js (Frontend JavaScript)
└── README.md (This file)
```

### Requirements
- WordPress 5.0 or higher
- PHP 7.2 or higher
- MySQL 5.6 or higher

### Security
- All AJAX requests are protected with WordPress nonces
- User input is sanitized and validated
- SQL queries use prepared statements

## Support

For issues or feature requests, please visit the plugin repository.

## License

GPL v2 or later

## Changelog

### Version 1.0.0
- Initial release
- Calendar view shortcode
- Event form shortcode
- Dashboard shortcode
- Multiple time blocks per event
- Multi-leg flight tracking
- Front-end event management
