# Registration & User Management Guide

## Overview
The Summer Regiment Tracker now includes a comprehensive user management system that allows:
- Public registration for members and parents
- Admin management of user relationships
- Automatic linking of parents to their children
- Price alert notifications to all associated users

## User Types

### Members
- Regiment members (students) who will be traveling to events
- Can view their own events and travel information
- Can receive price alerts for flights they're assigned to
- Profile includes: section, instrument/position

### Parents
- Parents/guardians of regiment members
- Can be linked to one or more children
- Receive price alerts for their children's travel
- Can view all events their children are assigned to

### Administrators
- WordPress admins can also be parents and members
- Full access to manage all user relationships
- Can manually add/remove member/parent designations
- Can link and unlink parent-child relationships

## Public Registration

### Setup
1. Create a page in WordPress (e.g., "Register")
2. Add the shortcode: `[srt_register]`
3. Publish the page

### Registration Process
**For Members:**
1. Select "Regiment Member"
2. Enter name, email, phone, password
3. Optional: Add section and instrument
4. Submit to create account and auto-login

**For Parents:**
1. Select "Parent/Guardian"
2. Enter name, email, phone, password
3. Optional: Enter their child's email to auto-link
4. Submit to create account and auto-login

### Features
- Automatic password validation (minimum 8 characters)
- Email uniqueness checking
- Auto-login after successful registration
- Email notification to admins when new users register
- Automatic relationship linking if child's email provided

## Admin Management

### Access
Navigate to **Dashboard → Regiment Users** in the WordPress admin menu.

### Managing Members
**Add Member Status:**
1. Go to the "Members" tab
2. Select any WordPress user from dropdown
3. Click "Make Member"

**Remove Member Status:**
1. Find user in the members list
2. Click "Remove Member"

### Managing Parents
**Add Parent Status:**
1. Go to the "Parents" tab
2. Select any WordPress user from dropdown
3. Click "Make Parent"

**Remove Parent Status:**
1. Find user in the parents list
2. Click "Remove Parent"

### Managing Relationships
**Link Parent to Child:**
1. Go to the "Relationships" tab
2. Select parent from first dropdown
3. Select member (child) from second dropdown
4. Click "Add Relationship"

**Unlink Parent from Child:**
1. Find the relationship in the list
2. Click "Remove"

### All Users View
- See complete list of all users
- Quick view of their status (Member/Parent/Both)
- See all parent-child relationships
- Quick access to WordPress user profiles

## Helper Functions (For Developers)

### Member Management
```php
// Make user a member
SRT_Roles::make_member($user_id);

// Remove member status
SRT_Roles::remove_member($user_id);

// Check if user is a member
if (SRT_Roles::is_member($user_id)) {
    // User is a member
}
```

### Parent Management
```php
// Make user a parent
SRT_Roles::make_parent($user_id);

// Remove parent status
SRT_Roles::remove_parent($user_id);

// Check if user is a parent
if (SRT_Roles::is_parent($user_id)) {
    // User is a parent
}
```

### Relationship Management
```php
// Add parent-child relationship
SRT_Roles::add_parent_child($parent_id, $child_id);

// Remove parent-child relationship
SRT_Roles::remove_parent_child($parent_id, $child_id);

// Get all children of a parent
$children = SRT_Roles::get_children($parent_id);
// Returns array of user IDs

// Get all parents of a member
$parents = SRT_Roles::get_parents($member_id);
// Returns array of user IDs
```

## Integration with Price Alerts

When price alerts are triggered:
1. Member receives notification email
2. All parents linked to that member also receive notification
3. Emails include flight details and price information
4. Members can be assigned to specific events/travel legs

## Data Storage

### User Meta Keys
- `srt_is_member`: Boolean indicating member status
- `srt_is_parent`: Boolean indicating parent status
- `srt_parent_of`: Array of user IDs (children)
- `srt_parents`: Array of user IDs (parents)
- `srt_section`: Member's section (brass, percussion, etc.)
- `srt_instrument`: Member's instrument or position

### Database Tables
User relationships are stored in WordPress user meta table (`wp_usermeta`) for:
- Easy WordPress integration
- Simple backup/restore
- No custom table maintenance
- Compatibility with user deletion

## Best Practices

1. **Always use helper functions** instead of direct meta access
2. **Verify relationships** before sending notifications
3. **Clean up orphaned relationships** when users are deleted
4. **Validate user IDs** before creating relationships
5. **Check for circular relationships** (a parent cannot be a child of their child)

## Troubleshooting

### Registration form not appearing
- Check shortcode is `[srt_register]` exactly
- Verify user is logged out (logged-in users see different message)
- Check WordPress error log for PHP errors

### Relationships not saving
- Verify both users exist in WordPress
- Check user IDs are valid integers
- Ensure helper functions are being called correctly

### Emails not sending
- Check WordPress email configuration
- Verify email addresses are valid
- Check spam folder
- Test with WP Mail SMTP plugin

### Users can't see their role
- User meta-based system doesn't show in "Role" dropdown
- Check user profile page for member/parent badges
- Use helper functions to verify status programmatically

## Future Enhancements
- [ ] Parent dashboard to view all children's events
- [ ] Member dashboard to view assigned events
- [ ] Bulk user import from CSV
- [ ] Family group management
- [ ] Multi-child registration in one form
- [ ] Parent approval workflow for minor accounts
