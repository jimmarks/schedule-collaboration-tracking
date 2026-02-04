# Mapbox Autocomplete Troubleshooting Guide

## Quick Diagnostics

### Step 1: Check Browser Console
Open your browser's Developer Tools (F12) and look at the Console tab while typing in the Location Name field.

**What You Should See:**
```
Mapbox API key not configured. Address autocomplete disabled.
```
OR
```
Searching Mapbox for: Southington
Mapbox results: 5 found
```

### Step 2: If "API key not configured"

1. Go to WordPress Admin → Regiment Events → Settings
2. Scroll to "General Settings"
3. Look for "Mapbox API Key" field
4. Make sure you have pasted your API key (starts with `pk.eyJ...`)
5. Click "Save Settings"
6. **Important**: Refresh your event form page (Ctrl+F5 or Cmd+Shift+R)

### Step 3: If API Key is Set But No Results

Check console for error messages:

**Error: "401 Unauthorized"**
- Your API key is invalid or expired
- Get a new key from https://account.mapbox.com/access-tokens/

**Error: "403 Forbidden"**
- Your API key has URL restrictions that block your WordPress site
- Solution: Create a new token without URL restrictions, OR
- Add your WordPress URL to the allowed URLs

**No Error, Just "0 found"**
- The location might not exist in Mapbox database
- Try searching for a nearby city or well-known landmark instead
- Example: Instead of "Southington High School", try "Southington Connecticut"

### Step 4: Test with Known Location

Try these guaranteed-to-work searches:
- "New York"
- "Los Angeles"
- "Chicago"
- "Times Square"
- "Central Park"

If these work, your API is configured correctly. The specific school might just not be in Mapbox's database.

## Common Issues

### Issue: Dropdown Covers Input Field
**Fixed in v0.2.9** - Update to latest version

### Issue: Nothing Happens When Typing
**Checks:**
1. Are you typing at least 3 characters?
2. Is there a 300ms delay (intentional debouncing)?
3. Check browser console for JavaScript errors
4. Make sure jQuery is loaded (check console for errors)

### Issue: Search Returns Cities Instead of Schools

**Why This Happens:**
Not all schools are in Mapbox's database. Mapbox prioritizes major landmarks and addresses.

**Workarounds:**
1. Search for the city first: "Southington Connecticut"
2. Then manually type the school name in Location Name
3. Or search for the full address: "720 Pleasant St Southington CT"
4. Use the Address field for full details

**Better Results:**
- Large venues: "Lucas Oil Stadium" ✅
- Major cities: "Indianapolis" ✅
- Famous landmarks: "Golden Gate Bridge" ✅
- Small schools: Might not be found ❌

## Manual Entry Still Works

**Remember:** Autocomplete is optional!

If you can't find a location through autocomplete:
1. Type the name manually in "Location Name"
2. Type the address manually in "Location Address"
3. Everything still works perfectly - you just won't have coordinates

The plugin works 100% without Mapbox. It's purely a convenience feature.

## API Key Security

### Creating a Restricted Token (Recommended)

1. Go to https://account.mapbox.com/access-tokens/
2. Click "Create a token"
3. Name: "Regiment Tracker"
4. Token scopes - Select ONLY:
   - ✅ `styles:tiles`
   - ✅ `geocoding:read`
5. URL restrictions:
   - Add: `https://yoursite.com/*`
   - Add: `http://yoursite.com/*` (if testing locally)
6. Click "Create token"
7. Copy and paste into WordPress settings

### If URL Restrictions Block You

**Symptoms:**
- Console shows: "403 Forbidden"
- Works on one domain but not another

**Solution:**
1. Go to https://account.mapbox.com/access-tokens/
2. Find your token
3. Click to edit
4. Add your WordPress URL to allowed URLs
5. Or create a new token without URL restrictions (less secure but easier)

## Still Not Working?

### Debug Checklist
- [ ] Mapbox API key pasted in Settings
- [ ] Settings saved (click Save Settings button)
- [ ] Page refreshed after saving settings (Ctrl+F5)
- [ ] Browser console open (F12)
- [ ] Typed at least 3 characters in Location Name
- [ ] Waited 300ms after typing
- [ ] Tested with "New York" or "Chicago"
- [ ] No JavaScript errors in console
- [ ] Using a modern browser (Chrome, Firefox, Safari, Edge)

### Get API Key Details
In browser console, type:
```javascript
console.log(srtData.mapboxApiKey);
```

**Should show:**
- `pk.eyJ...` (your key) = Configured ✅
- `""` (empty string) = Not configured ❌
- `undefined` = JavaScript error ❌

### Test API Key Manually
Open this URL in your browser (replace YOUR_KEY):
```
https://api.mapbox.com/geocoding/v5/mapbox.places/chicago.json?access_token=YOUR_KEY
```

**Should return:**
- JSON with results = Key works ✅
- 401 error = Invalid key ❌
- 403 error = URL restrictions blocking ❌

## Contact Support

If you've tried everything above and still having issues:

1. **Gather Info:**
   - WordPress version
   - Plugin version (check Regiment Events menu)
   - Browser console errors (screenshot)
   - API key status (configured? valid?)

2. **Report Issue:**
   - GitHub Issues (if using GitHub version)
   - Or contact your WordPress admin

---

**Last Updated**: v0.2.9 - January 23, 2026
