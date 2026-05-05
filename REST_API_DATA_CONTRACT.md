# REST API Data Contract Audit

## Purpose
Document the 1:1 field mapping between REST API responses and JavaScript consumers.
Every field returned by the API must match exactly what JavaScript expects.

## Endpoints Audit

### GET /ftt/v1/children
**REST API Returns:**
```json
{
  "children": [
    {
      "id": 123,
      "display_name": "John Doe",
      "name": "John Doe",  // alias
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

**JavaScript Expects:**
- main.js loadEventFormData(): `child.display_name`
- Status: ✅ FIXED

---

### GET /ftt/v1/groups
**REST API Returns:**
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
      "group_token": "abc123",
      "is_primary": true,
      "can_manage": true,
      "billing_owner": 2,
      "billing": {
        "subscription_status": "trialing",
        "trial_ends_at": "2026-05-18 12:00:00"
      }
    }
  ],
  "primary_group_id": 14
}
```

**JavaScript Expects:**
- templates/groups.php renderGroupCard(): Uses all fields above
- Status: ⚠️ NEEDS VERIFICATION

---

### GET /ftt/v1/events
**REST API Returns:**
```json
[
  {
    "id": 100,
    "title": "Soccer Practice",
    "start": "2026-05-10 15:00:00",
    "end": "2026-05-10 16:30:00",
    "location": "Lincoln Park",
    "member_id": 50,
    "member_name": "Child Name",
    "group_id": 14,
    "group_name": "Smith Family",
    "description": "Weekly practice",
    "color": "#FF5733"
  }
]
```

**JavaScript Expects:**
- assets/js/main.js calendar: event.title, event.start, event.end, etc.
- Status: ⚠️ NEEDS AUDIT

---

### GET /ftt/v1/dashboard-context
**REST API Returns:**
```json
{
  "is_member": false,
  "is_parent": true,
  "is_admin": false,
  "groups": [...],
  "selected_group": {...},
  "children": [...],
  "upcoming_events": [...],
  "billing_info": {...}
}
```

**JavaScript Expects:**
- templates/dashboard.php: Various fields
- Status: ⚠️ NEEDS AUDIT

---

## Action Items

### HIGH PRIORITY
1. [ ] Audit GET /ftt/v1/groups response vs JavaScript consumer
2. [ ] Audit GET /ftt/v1/events response vs JavaScript consumers
3. [ ] Audit GET /ftt/v1/dashboard-context response vs consumers
4. [ ] Audit all template inline scripts for REST API calls

### MEDIUM PRIORITY
5. [ ] Audit GET /ftt/v1/get-family-members
6. [ ] Audit POST/PUT/DELETE endpoints for request/response consistency
7. [ ] Document all field names in code comments

### Standards
- Use `display_name` for user/child names (not `name`)
- Use `id` for all entity IDs (not `user_id`, `child_id` in object context)
- Use `group_id` only when referencing a group from another entity
- Use ISO 8601 datetime format for all timestamps
- Always include both snake_case (backend) and camelCase (optional frontend) if needed

## Current Mismatches Found

### 1. Groups Page Issue
- **Problem**: JavaScript receiving `{success: true, groups: []}` instead of `{groups: [], primary_group_id: 14}`
- **Status**: INVESTIGATING
- **Files**: templates/groups.php, includes/rest.php get_user_groups()

