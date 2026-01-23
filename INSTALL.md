# Quick Installation Guide

## Installing the Summer Regiment Tracker Plugin

### Method 1: Direct Upload (Recommended)

1. **Prepare the Plugin**
   - Download or zip the entire `summer-regiment-tracker` folder
   - Ensure all files are included

2. **Upload to WordPress**
   - Log into your WordPress admin panel
   - Navigate to `Plugins → Add New`
   - Click `Upload Plugin`
   - Choose the zip file
   - Click `Install Now`
   - Click `Activate Plugin`

3. **Verify Installation**
   - The plugin should now appear in your Plugins list
   - Database tables will be created automatically

### Method 2: FTP/Manual Upload

1. **Connect to Your Server**
   - Use FTP client or file manager
   - Navigate to `/wp-content/plugins/`

2. **Upload Plugin**
   - Upload the entire `summer-regiment-tracker` folder
   - Ensure all subdirectories are included

3. **Activate**
   - Go to WordPress admin → Plugins
   - Find "Summer Regiment Tracker"
   - Click "Activate"

## Setting Up Pages

### Create Schedule Page

1. Go to `Pages → Add New`
2. Title: "Regiment Schedule"
3. Content:
   ```
   [srt_calendar]
   ```
4. Publish

### Create Event Manager Page

1. Go to `Pages → Add New`
2. Title: "Add Event"
3. Content:
   ```
   [srt_event_form]
   ```
4. Publish

### Create Flight Dashboard Page

1. Go to `Pages → Add New`
2. Title: "Flight Dashboard"
3. Content:
   ```
   [srt_dashboard]
   ```
4. Publish

## Creating Your First Event

1. Navigate to your "Add Event" page
2. Fill in the form:
   - **Event Title**: "Summer Practice Session"
   - **Event Date**: Select a date
   - **Location**: "Central High School"
   - **Description**: "Morning rehearsal"

3. Add a time block:
   - Click "Add Time Block"
   - Type: "Practice"
   - Start: 09:00
   - End: 12:00
   - Notes: "Music rehearsal"

4. (Optional) Add a flight:
   - Click "Add Flight Leg"
   - Fill in flight details

5. Click "Save Event"

6. Visit your calendar page to see the event displayed

## Next Steps

- Read EXAMPLES.md for more usage examples
- Read README-PLUGIN.md for detailed documentation
- Customize CSS if needed

## Troubleshooting

**Calendar not showing?**
- Verify the shortcode is correct: `[srt_calendar]`
- Make sure plugin is activated
- Check browser console for JavaScript errors

**Events not saving?**
- Check browser console for errors
- Verify all required fields are filled (Title and Date)
- Ensure you have proper WordPress permissions

**Database errors?**
- Deactivate and reactivate the plugin
- Check MySQL/MariaDB error logs
- Verify database user has CREATE TABLE permissions

## Support

For issues or questions, refer to the documentation files:
- README-PLUGIN.md - Full documentation
- EXAMPLES.md - Usage examples
- VERIFICATION.md - Feature checklist
