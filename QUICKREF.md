# Summer Regiment Tracker - Quick Reference Card

## Shortcodes

### Calendar View
```
[srt_calendar]
```
Shows monthly calendar with all events

---

### Event Form
```
[srt_event_form]
```
Create new event

```
[srt_event_form id="123"]
```
Edit event with ID 123

---

### Dashboard
```
[srt_dashboard]
```
View flight booking statistics

---

## Event Form Fields

### Required
- **Event Title**
- **Event Date**

### Optional
- Location
- Description
- Time Blocks (unlimited)
- Flight Information (multi-leg support)

---

## Time Block Types

- 🎵 **Practice** - Rehearsals
- ✈️ **Travel** - Transportation
- 📋 **Admin** - Administrative tasks
- 🎭 **Performance** - Shows/competitions
- 🍽️ **Meal** - Meal breaks
- 📝 **Other** - Custom activities

---

## Flight Information Fields

- Departure Airport (e.g., ORD)
- Arrival Airport (e.g., LAX)
- Departure Date/Time
- Arrival Date/Time
- ☑️ Booked (checkbox)
- Booking Reference #
- Notes

---

## Quick Actions

### Calendar
- Click **← Previous** or **Next →** to change months
- Click event to view details

### Event Form
- Click **Add Time Block** to add activity
- Click **Add Flight Leg** to add flight segment
- Click **Remove** to delete items
- Click **Save Event** to save changes
- Click **Delete Event** to remove (confirmation required)

### Dashboard
- Click **Mark as Booked** to update flight status
- View total/booked/unbooked counts at top

---

## Installation Quick Steps

1. Upload plugin folder to `/wp-content/plugins/`
2. Activate in WordPress admin
3. Create pages with shortcodes
4. Start adding events!

---

## Support Files

- **README-PLUGIN.md** - Full documentation
- **INSTALL.md** - Installation guide
- **EXAMPLES.md** - Usage examples
- **VERIFICATION.md** - Feature checklist
- **SUMMARY.md** - Implementation details

---

## Requirements

- WordPress 5.0+
- PHP 7.2+
- MySQL 5.6+
- JavaScript enabled browser

---

## Common Page Setup

**Schedule Page:**
```
[srt_calendar]
```

**Event Manager:**
```
[srt_event_form]
```

**Flight Dashboard:**
```
[srt_dashboard]
```

**All-in-One:**
```
<h2>Calendar</h2>
[srt_calendar]

<h2>Add Event</h2>
[srt_event_form]

<h2>Flights</h2>
[srt_dashboard]
```

---

## Tips

💡 Add multiple time blocks for complex schedules
💡 Use multi-leg flights for trips with connections
💡 Mark flights as booked from dashboard for quick updates
💡 Use descriptive event titles for easy calendar viewing
💡 Add location info for easy reference

---

**Plugin Version:** 1.0.0  
**License:** GPL v2 or later
