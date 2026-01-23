# Summer Regiment Tracker - Example Usage

This document provides examples of how to use the Summer Regiment Tracker plugin on your WordPress site.

## Creating Pages with Shortcodes

### 1. Calendar Page

Create a new page in WordPress (or use an existing one) and add the calendar shortcode:

```
[srt_calendar]
```

This will display a full monthly calendar view showing all scheduled events.

**Example Page Title**: "Regiment Schedule"

---

### 2. Add/Edit Event Page

Create a page for adding new events:

```
<h2>Add New Event</h2>
[srt_event_form]
```

To create a page for editing a specific event, use:

```
<h2>Edit Event</h2>
[srt_event_form id="1"]
```

**Example Page Title**: "Manage Events"

**Tip**: You can create a dynamic page that accepts event IDs via URL parameter by using custom code or a plugin that allows shortcode parameters from URL.

---

### 3. Dashboard Page

Create a page to display flight booking status:

```
[srt_dashboard]
```

This shows an overview of all flights and their booking status.

**Example Page Title**: "Flight Dashboard"

---

## Complete Example Page

Here's an example of a comprehensive page that combines multiple elements:

```html
<h1>Summer Regiment 2024</h1>

<h2>Schedule Overview</h2>
[srt_calendar]

<hr>

<h2>Add New Event</h2>
[srt_event_form]

<hr>

<h2>Flight Booking Status</h2>
[srt_dashboard]
```

---

## Sample Event Creation Workflow

### Example 1: Practice Day

**Event Details:**
- Title: "Morning Rehearsal - Music"
- Date: 2024-06-15
- Location: "Lincoln High School Stadium"
- Description: "Full ensemble music rehearsal focusing on opener and closer"

**Time Blocks:**
1. Block Type: Admin
   - Start: 08:00
   - End: 08:30
   - Notes: "Check-in and equipment setup"

2. Block Type: Practice
   - Start: 08:30
   - End: 12:00
   - Notes: "Music rehearsal - opener"

3. Block Type: Meal
   - Start: 12:00
   - End: 13:00
   - Notes: "Lunch break"

4. Block Type: Practice
   - Start: 13:00
   - End: 17:00
   - Notes: "Music rehearsal - closer"

**Flights:** None

---

### Example 2: Competition Day with Travel

**Event Details:**
- Title: "DCI Regional Championship"
- Date: 2024-07-20
- Location: "Indianapolis, IN"
- Description: "Regional championship competition"

**Time Blocks:**
1. Block Type: Travel
   - Start: 06:00
   - End: 07:00
   - Notes: "Bus to airport"

2. Block Type: Travel
   - Start: 09:00
   - End: 13:00
   - Notes: "Flight to Indianapolis"

3. Block Type: Admin
   - Start: 14:00
   - End: 15:00
   - Notes: "Check into hotel, unpack equipment"

4. Block Type: Practice
   - Start: 15:00
   - End: 17:00
   - Notes: "Warm-up rehearsal"

5. Block Type: Meal
   - Start: 17:00
   - End: 18:00
   - Notes: "Dinner"

6. Block Type: Performance
   - Start: 19:00
   - End: 21:00
   - Notes: "Competition performance"

**Flights:**
1. **Leg 1:**
   - Departure Airport: ORD
   - Arrival Airport: IND
   - Departure Time: 2024-07-20 09:00
   - Arrival Time: 2024-07-20 11:00
   - Booked: Yes
   - Booking Reference: AA1234

2. **Leg 2 (Return):**
   - Departure Airport: IND
   - Arrival Airport: ORD
   - Departure Time: 2024-07-21 14:00
   - Arrival Time: 2024-07-21 16:00
   - Booked: No
   - Booking Reference: (leave empty)

---

## Sample Multi-Leg Flight Example

For a complex travel scenario with a connection:

**Event:** "DCI Finals - Bloomington, IN"

**Flight Leg 1:**
- Departure: LAX (Los Angeles)
- Arrival: DEN (Denver)
- Departure Time: 2024-08-10 08:00
- Arrival Time: 2024-08-10 11:00
- Booked: Yes
- Booking Reference: UA5678

**Flight Leg 2:**
- Departure: DEN (Denver)
- Arrival: IND (Indianapolis)
- Departure Time: 2024-08-10 13:30
- Arrival Time: 2024-08-10 17:00
- Booked: Yes
- Booking Reference: UA5679

**Flight Leg 3 (Return):**
- Departure: IND (Indianapolis)
- Arrival: LAX (Los Angeles)
- Departure Time: 2024-08-11 15:00
- Arrival Time: 2024-08-11 20:00
- Booked: No
- Booking Reference: (needs booking)

---

## Tips for Using the Plugin

1. **Calendar Navigation**: Use the Previous and Next buttons to navigate between months.

2. **Event Editing**: Click on event indicators in the calendar to view event IDs (check browser console), then use `[srt_event_form id="X"]` to edit.

3. **Time Blocks**: Add as many time blocks as needed for each day. They will be displayed in chronological order.

4. **Flight Tracking**: Use the dashboard to quickly see which flights need to be booked.

5. **Booking Status**: Use the "Mark as Booked" button in the dashboard to update flight status without editing the entire event.

6. **Airport Codes**: Use standard IATA airport codes (3 letters) for consistency.

---

## Creating a Custom Workflow

You can create multiple pages for different purposes:

- **Public Schedule** (`/schedule`): Calendar view only for public viewing
- **Staff Event Manager** (`/manage-events`): Event form for adding/editing events
- **Travel Coordinator** (`/flights`): Dashboard for managing flight bookings
- **All-in-One Admin** (`/regiment-admin`): Combined view with all three shortcodes

---

## Styling Customization

If you want to customize the appearance, you can add custom CSS to your theme's style.css or use the WordPress Customizer:

```css
/* Example: Change calendar header color */
.srt-calendar-header h2 {
    color: #your-color;
}

/* Example: Change event indicator colors */
.srt-event-indicator {
    background: #your-color;
}

/* Example: Customize stat cards */
.srt-stat-card {
    border: 2px solid #your-color;
}
```

---

## Support and Troubleshooting

If shortcodes don't display:
1. Make sure the plugin is activated
2. Check that you're using the correct shortcode syntax
3. Verify the page is published, not in draft mode

If events don't save:
1. Check browser console for JavaScript errors
2. Verify all required fields are filled
3. Ensure you have proper permissions

For database issues:
1. Deactivate and reactivate the plugin to recreate tables
2. Check that your WordPress database has proper permissions
