# 🎺 Summer Regiment Tracker - Quick Reference

## Shortcodes

### Calendar View
```
[srt_calendar]
```
Displays interactive calendar with all events. Color-coded by event type.

### Event Management Form
```
[srt_event_form]
```
Add/edit event form (admin/editor only). Supports time blocks and travel legs.

### Travel Dashboard
```
[srt_dashboard]
```
Three sections: Flights Needed, Not Booked, Upcoming Travel (14 days).

### Event List
```
[srt_event_list]
[srt_event_list limit="20"]
[srt_event_list type="performance_day"]
```
Simple chronological list. Optional limit and type filter.

---

## REST API Endpoints

Base URL: `/wp-json/srt/v1/`

### GET /events
List events with optional filters
```javascript
?start_date=2026-06-01&end_date=2026-08-31&event_type=performance_day
```

### GET /events/{id}
Get single event details

### POST /events
Create new event (requires auth)

### PUT /events/{id}
Update event (requires auth)

### DELETE /events/{id}
Delete event (requires auth)

### GET /dashboard
Get dashboard data (flights needed, not booked, upcoming travel)

---

## Event Types

- `move_in` - Move In Day
- `move_out` - Move Out Day
- `camp_weekend` - Camp Weekend
- `rehearsal_block` - Rehearsal Block
- `travel_day` - Travel Day
- `performance_day` - Performance Day
- `housing_checkin` - Housing Check-In
- `medical` - Medical Appointment
- `uniform_fitting` - Uniform Fitting
- `admin_deadline` - Admin Deadline
- `other` - Other

---

## Time Block Types

- `practice` - Practice Session
- `travel` - Travel Time
- `admin` - Administrative
- `meal` - Meal Break
- `medical` - Medical
- `performance` - Performance
- `other` - Other

---

## Travel Modes

- `fly` - Air Travel
- `drive` - Car/Van
- `bus` - Bus/Charter
- `shuttle` - Shuttle Service
- `other` - Other Mode

---

## Baggage Types

- `carry_on` - Carry-On Luggage
- `checked` - Checked Luggage
- `oversized_instrument` - Oversized Instrument

---

## User Capabilities

| Role | View | Create | Edit | Delete | Settings |
|------|------|--------|------|--------|----------|
| Admin | ✅ | ✅ | ✅ | ✅ | ✅ |
| Editor | ✅ | ✅ | ✅ | ✅ | ❌ |
| Other | ✅* | ❌ | ❌ | ❌ | ❌ |

*If "Require Login" is disabled in settings

---

## Quick Setup

1. Install plugin → Activate
2. Configure FullCalendar (CDN recommended)
3. Go to Regiment Events → Settings
4. Set default airport & timezone
5. Create pages with shortcodes
6. Start adding events!

---

## File Locations

```
wp-content/plugins/summer-regiment-tracker/
├── summer-regiment-tracker.php    (Main file)
├── includes/                      (Backend PHP)
├── assets/js/                     (Frontend JS)
├── assets/css/                    (Styles)
├── templates/                     (HTML templates)
└── [Documentation files]
```

---

## Troubleshooting Quick Fixes

**Calendar not showing?**
→ Install FullCalendar via CDN (see INSTALL.md)

**Can't save events?**
→ Check user has edit_posts capability

**REST API errors?**
→ Resave permalinks (Settings → Permalinks → Save)

**Permission denied?**
→ Check "Require Login" setting + user role

---

## Color Coding

Events are automatically color-coded on the calendar:
- 🟢 Move In/Out
- 🔵 Camp/Rehearsal
- 🟠 Travel
- 🔴 Performance/Medical
- 🟣 Admin
- ⚪ Other

---

## Common Workflows

### Add Multi-Day Camp Weekend
1. Create event "Camp Weekend"
2. Set start: Friday 6 PM
3. Set end: Sunday 5 PM
4. Add time blocks for each practice session
5. Add travel legs if needed

### Track Flight Booking
1. Create event with travel
2. Check "Flight Needed"
3. Add travel leg with flight details
4. Leave "Booked" unchecked
5. Event appears in "Not Yet Booked" dashboard
6. When booked: Edit event → Check "Booked" → Add confirmation #

### View Upcoming Travel
1. Go to dashboard page
2. Check "Upcoming Travel (Next 14 Days)" section
3. Click event for full details
4. Edit if needed

---

## Support

📖 **Full Docs**: PLUGIN_README.md  
🚀 **Installation**: INSTALL.md  
📊 **Summary**: SUMMARY.md  
🔧 **GitHub**: jimmarks/phantom-regiment-tracker

---

**Version**: 1.0.0  
**Updated**: January 2026
