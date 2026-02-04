# Navigation & User Experience Guide

## Overview
The Summer Regiment Tracker now uses a **Dashboard-Centric** navigation model where the Regiment Dashboard serves as the main hub for all users.

## Navigation Structure

### 🏠 Main Hub: Regiment Dashboard
**URL**: `/regiment-dashboard` (set as homepage)

**What Users See:**
- **Not Logged In**: Welcome message, register/login buttons, link to public calendar
- **Members**: Personalized dashboard showing their events, travel, and price alerts
- **Parents**: Family dashboard showing all children's events, travel, and price alerts
- **Admins**: Full dashboard + admin quick links

### Top Navigation Bar
Every page includes a consistent navigation menu:

```
🎺 Regiment Dashboard
   📅 Calendar | 📋 All Events | ➕ Manage Events* | 👥 Manage Users* | 🚪 Logout
   
   * Admin only
```

## User Journeys

### First-Time Visitor (Not Logged In)
1. **Lands on**: Dashboard homepage
2. **Sees**: Welcome message explaining the system
3. **Actions**: 
   - Click "Register" to create account
   - Click "Login" if already registered
   - Click "View Full Calendar" to browse events

### New User Registration
1. Click **Register** button on dashboard
2. Select user type: **Member** or **Parent**
3. Fill out form:
   - **Members**: Add section/instrument
   - **Parents**: Optionally link to child by email
4. Submit → Auto-login → Redirected to personalized dashboard

### Regiment Member (Student)
1. **Logs in** → Sees personalized dashboard
2. **Dashboard shows**:
   - Welcome header with name and section/instrument
   - My Travel Overview with 3 panels:
     - Flights Needed
     - Not Yet Booked
     - Upcoming Travel (14 days)
3. **Navigation options**:
   - View full calendar
   - See all events (list view)
   - Logout

### Parent/Guardian
1. **Logs in** → Sees family dashboard
2. **Dashboard shows**:
   - Welcome header
   - Children cards showing linked members
   - Family Travel Overview (aggregates all children's travel)
3. **Receives**: Price alerts for all children's flights
4. **Navigation**: Same as members

### Administrator
1. **Logs in** → Sees full dashboard + admin tools
2. **Dashboard shows**: All travel data (entire regiment)
3. **Additional navigation**:
   - **Manage Events**: Create/edit events
   - **Manage Users**: Add members/parents, link relationships
   - **Settings**: Configure API keys, alerts, etc.
4. **Can also be**: Member and/or Parent (multi-role)

## Page Hierarchy

```
🏠 Regiment Dashboard (Homepage)
   ├── 📅 Regiment Calendar
   │   └── Visual calendar view of all events
   │
   ├── 📋 All Events
   │   └── List view with filters/search
   │
   ├── ➕ Manage Events (Admin Only)
   │   └── Event creation/editing form
   │
   └── 👥 Manage Users (Admin Only)
       ├── Members tab
       ├── Parents tab
       ├── All Users tab
       └── Relationships tab
```

## Page Purposes

### Dashboard (`/regiment-dashboard`)
- **Primary landing page** for all users
- **Role-aware content**: Shows different views based on login status and role
- **Quick actions**: Register, login, view calendar
- **Travel overview**: Personalized to user type

### Calendar (`/regiment-calendar`)
- **Visual calendar**: FullCalendar month/week/day views
- **All events**: Public or filtered by user
- **Click events**: Opens details modal with travel info

### All Events (`/regiment-events`)
- **List view**: Alternative to calendar
- **Filters**: By date, type, location
- **Searchable**: Find specific events

### Manage Events (`/manage-regiment-events`)
- **Admin only**: Create/edit events
- **Event form**: Title, dates, location, travel legs
- **Travel builder**: Add departure/arrival flights
- **Price tracking**: Enable alerts for specific legs

### Manage Users (Admin Menu)
- **User management**: Make users members/parents
- **Relationships**: Link parents to children
- **View all**: See complete user roster

## Settings Integration

### User Settings (Profile)
Users can update their profile:
- Name, email, password
- Section/instrument (members)
- Notification preferences

### Admin Settings
- **API Keys**: Amadeus, Google Places, Mapbox
- **Email Settings**: Alert templates, sender info
- **Page Creation**: Auto-create/delete plugin pages
- **Price Tracking**: Alert thresholds, check frequency

## Mobile Experience
All navigation and dashboards are fully responsive:
- **Stacked navigation** on mobile
- **Single-column layouts** for cards/sections
- **Touch-friendly buttons** and links
- **Responsive calendar** views

## Key Features

### Automatic Page Setup
When plugin activates, it can auto-create all pages:
1. Dashboard (set as homepage)
2. Calendar
3. All Events
4. Manage Events

### Smart Redirects
- After registration → Dashboard
- After login → Dashboard (personalized)
- After logout → Dashboard (public view)

### Contextual Links
- Members see: Their events
- Parents see: Children's events
- Admins see: All events + management tools

### Notification Flow
1. Event created with travel
2. Price tracking enabled
3. Price drops detected
4. Alerts sent to:
   - Member assigned to flight
   - All parents of that member
5. Email includes: Flight details, old/new price, booking link

## Best Practices

### For Admins
1. **Create events** from dashboard → Manage Events
2. **Add users** from dashboard → Manage Users
3. **Link relationships** immediately after registration
4. **Enable price tracking** on popular routes

### For Members
1. **Check dashboard** regularly for travel updates
2. **Book flights** when price alerts arrive
3. **Update profile** if section/instrument changes

### For Parents
1. **Link to children** during registration
2. **Monitor dashboard** for family travel
3. **Act on price alerts** quickly (deals expire)

### For Setup
1. **Install plugin** → Activate
2. **Create pages** (automatic or manual with shortcodes)
3. **Set homepage** to Dashboard
4. **Add menu items** to WordPress menu
5. **Configure API keys** in settings
6. **Create first event** to test

## Shortcode Reference

Use these shortcodes to place content anywhere:

- `[srt_dashboard]` - Main dashboard (role-aware)
- `[srt_calendar]` - Event calendar
- `[srt_event_list]` - Event list view
- `[srt_event_form]` - Event management form (admin)
- `[srt_register]` - Registration form
- `[srt_calendar_subscribe]` - iCal subscription links

## Future Enhancements
- [ ] User dashboard widget for WordPress admin
- [ ] Mobile app with push notifications
- [ ] Bulk event import from CSV
- [ ] Parent approval for minor registrations
- [ ] Group travel discounts tracking
- [ ] Carpool coordination
- [ ] Hotel booking integration
- [ ] Equipment/uniform checklist per event
