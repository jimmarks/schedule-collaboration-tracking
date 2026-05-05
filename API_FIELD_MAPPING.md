# REST API Field Mapping - Complete Reference

## Purpose
This document defines the EXACT field names returned by each REST API endpoint
and consumed by JavaScript. Every field must match 1:1.

## Field Naming Standards
- Use **snake_case** for all field names (backend and frontend)
- Use `display_name` for user/child display names (NOT `name` alone)
- Use `id` for primary IDs within object context
- Use `{entity}_id` only when referencing from another entity (e.g., `member_id` in event)
- Use ISO 8601 datetime format: `YYYY-MM-DD HH:MM:SS`
- Boolean fields: return actual booleans, not strings

---

## GET /ftt/v1/events

### Response Format
```json
[
  {
    "id": 100,
    "title": "Soccer Practice",
    "content": "Weekly practice session",
    "start_datetime": "2026-05-10 15:00:00",
    "end_datetime": "2026-05-10 16:30:00",
    "timezone": "America/New_York",
    "all_day": false,
    "event_type": "sports",
    "location_name": "Lincoln Park",
    "location_address": "123 Main St",
    "location_latitude": "40.7128",
    "location_longitude": "-74.0060",
    "notes": null,
    "travel_needed": false,
    "travel_mode": null,
    "flight_needed": false,
    "time_blocks": [],
    "travel_legs": [],
    "member_id": 50,
    "member_name": "John Doe",
    "color": "#FF5733",
    "textColor": "#FFFFFF",
    "className": "child-50",
    "group_id": 14,
    "group_name": "Smith Family"
  }
]
```

### JavaScript Consumer Fields
- **main.js calendar**: `event.title`, `event.start_datetime`, `event.end_datetime`, `event.member_id`
- **main.js loadEvent()**: ALL fields listed above
- **templates/event-view.php**: `event.location_name`, `event.location_address`, etc.

### Status: ✅ VERIFIED

---

## GET /ftt/v1/children

### Response Format
```json
{
  "children": [
    {
      "id": 50,
      "display_name": "John Doe",
      "name": "John Doe",
      "first_name": "John",
      "last_name": "Doe",
      "email": "john@example.com",
      "age": 10,
      "grade": "5th",
      "school": "Lincoln Elementary",
      "color": "#FF5733"
    }
  ],
  "is_member": false,
  "user_id": 2
}
```

### JavaScript Consumer Fields
- **main.js loadEventFormData()**: `child.display_name`, `child.id`
- **main.js loadChildrenFilter()**: `child.display_name`, `child.id`, `child.color`

### Status: ✅ FIXED

---

## GET /ftt/v1/groups

### Response Format
```json
{
  "groups": [
    {
      "id": 14,
      "name": "Smith Family",
      "description": "Our family group",
      "color": "#6A3E8E",
      "child_count": 2,
      "parent_count": 2,
      "member_count": 4,
      "planned_children": 3,
      "group_token": "abc123xyz",
      "is_primary": true,
      "can_manage": true,
      "billing_owner": 2,
      "billing": {
        "subscription_status": "trialing",
        "trial_ends_at": "2026-05-18 12:00:00",
        "child_limit": 3,
        "current_children": 2,
        "next_billing_date": null,
        "plan_interval": "month",
        "is_trialing": true,
        "days_remaining": 14
      }
    }
  ],
  "primary_group_id": 14
}
```

### JavaScript Consumer Fields
- **templates/groups.php renderGroupCard()**: ALL fields above
- **templates/event-view.php**: `group.name`, `group.id`

### Status: ⚠️ NEEDS VERIFICATION - Check billing object structure

---

## GET /ftt/v1/dashboard-context

### Response Format
```json
{
  "is_member": false,
  "is_parent": true,
  "is_admin": false,
  "groups": [...],
  "selected_group": {...},
  "children": [...],
  "billing_info": {...}
}
```

### Status: ⏳ TO BE DOCUMENTED

---

## GET /ftt/v1/get-family-members

### Response Format
```json
{
  "children": [{...}],
  "adults": [{...}]
}
```

### Status: ⏳ TO BE DOCUMENTED

---

## Validation Checklist

- [x] GET /ftt/v1/events - format_event() returns correct structure 
- [x] GET /ftt/v1/children - display_name field added
- [ ] GET /ftt/v1/groups - verify billing object structure
- [ ] GET /ftt/v1/dashboard-context - document and verify
- [ ] All template inline scripts using REST APIs verified

---

## Common Issues Found

1. **Groups billing object**: REST API may return incomplete billing data
2. **Date formats**: Ensure all datetimes use snake_case (`start_datetime`, not `startDateTime`)
3. **Boolean values**: Return actual booleans, not string "true"/"false"

