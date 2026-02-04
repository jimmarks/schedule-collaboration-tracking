# Mapbox Autocomplete Setup Guide

## ⚠️ Important Limitation

**Mapbox has incomplete coverage of schools and local venues.**

If you search for "Southington High School", Mapbox might return:
- ❌ "High Street" (a street name, not the school)
- ❌ Results from other countries
- ❌ Generic addresses instead of venue names

**Recommendation**: Use **Google Places API** instead (see GOOGLE_PLACES_SETUP.md)

Google Places has comprehensive school and venue data. Cost: ~$2 per season.

## When to Use Mapbox

✅ **Good For**:
- General address entry (street addresses)
- Major landmarks and venues
- Cities and neighborhoods
- Free tier (100,000 requests/month)

❌ **Not Good For**:
- Schools (primary use case for drum corps)
- Local parks
- Small venues
- Community centers

## Overview

Version 0.2.7 adds optional Mapbox address autocomplete to help you quickly find and add event locations. This feature is **completely optional** - the plugin works perfectly without it.

## Benefits

- **Faster Event Creation**: Type a few letters, select from suggestions
- **Accurate Addresses**: Get properly formatted, verified addresses
- **Coordinates Stored**: Latitude/longitude saved for potential future features (maps, distance calculations)
- **Venue Discovery**: Search by name (e.g., "Lucas Oil Stadium") and get full address

## Free Tier

Mapbox offers a generous free tier:
- **100,000 requests per month** (free)
- For a typical drum corps: ~30 events/season × 2-3 searches each = ~90 requests
- You'll use less than 0.1% of the free tier

## Setup Instructions

### 1. Create Mapbox Account

1. Go to [https://account.mapbox.com/auth/signup/](https://account.mapbox.com/auth/signup/)
2. Sign up with your email (no credit card required for free tier)
3. Verify your email address
4. Log in to your account

### 2. Get Your API Key

1. Go to [https://account.mapbox.com/access-tokens/](https://account.mapbox.com/access-tokens/)
2. You'll see a **Default public token** already created
3. Copy this token (starts with `pk.eyJ...`)

**Optional: Create a Restricted Token** (Recommended)
1. Click "Create a token"
2. Name it: "Regiment Tracker"
3. Under "Token scopes", select ONLY:
   - ✅ `styles:tiles`
   - ✅ `geocoding:read`
4. Under "URL restrictions", add your WordPress site URL:
   - Example: `https://yoursite.com/*`
5. Click "Create token"
6. Copy the new token

### 3. Add Token to WordPress

1. Log in to WordPress admin
2. Navigate to **Regiment Events → Settings**
3. Scroll to **General Settings** section
4. Find **Mapbox API Key** field
5. Paste your token
6. Click **Save Settings**

### 4. Test It Out

1. Go to your **Manage Events** page
2. Start typing in the **Location Name** field:
   - Try: "Lucas Oil Stadium"
   - Try: "Indianapolis Motor Speedway"
   - Try: Your hometown venue name
3. You should see suggestions appear after typing 3+ characters
4. Click a suggestion to auto-fill the address

## How It Works

### User Experience
1. You type in the Location Name field (e.g., "Lucas")
2. After 3 characters, autocomplete searches Mapbox
3. Suggestions appear in a dropdown
4. Click a suggestion:
   - Location Name fills with venue name
   - Location Address fills with full address
   - Coordinates saved in background (for future features)

### Technical Details
- **Debouncing**: 300ms delay prevents excessive API calls
- **Smart Search**: Only searches after 3+ characters typed
- **Types**: Searches POIs (venues), addresses, and places
- **Limit**: Shows top 5 results
- **Coordinates**: Longitude/latitude stored as hidden fields

## Without Mapbox (Default Behavior)

If you don't add an API key:
- Location Name and Address fields work as regular text inputs
- You manually type addresses (like before)
- No autocomplete suggestions
- No coordinates stored
- **Everything else works perfectly**

## Troubleshooting

### No Suggestions Appearing

1. **Check API Key**: Settings → Regiment Events → Settings → Mapbox API Key
2. **Browser Console**: Open DevTools (F12) → Console tab → Look for errors
3. **Common Issues**:
   - API key not saved
   - API key has wrong permissions (needs `geocoding:read`)
   - URL restrictions blocking your site
   - Typed less than 3 characters

### API Key Not Working

1. **Verify Key Format**: Should start with `pk.eyJ...`
2. **Check Token Scopes**: Needs `geocoding:read` permission
3. **Remove URL Restrictions**: For testing, create a token with no restrictions
4. **Regenerate Token**: Delete old token, create new one

### Rate Limit Errors

If you somehow exceed 100,000 requests/month:
1. Review usage at [https://account.mapbox.com/](https://account.mapbox.com/)
2. Check for JavaScript errors causing repeated requests
3. Consider upgrading plan (unlikely needed)

## Privacy Considerations

When using Mapbox autocomplete:
- Search queries sent to Mapbox servers
- IP address logged by Mapbox (standard API behavior)
- No personal information sent (only location search terms)
- Mapbox Privacy Policy: [https://www.mapbox.com/privacy/](https://www.mapbox.com/privacy/)

## Cost Information

### Free Tier (Recommended for Most)
- **100,000 requests/month** - FREE
- More than enough for typical drum corps use

### If You Somehow Exceed Free Tier
- **Next 100,000 requests**: $0.50
- **After that**: $0.0005 per request

**Reality Check**: At 30 events/season with 3 searches each = 90 requests total. You'd need to create 1,111 events per month to exceed the free tier.

## Future Features (Potential)

With coordinates stored, future versions could add:
- Interactive maps showing all event locations
- Distance calculations between events
- Route planning for travel
- Geographic visualizations

## Support

### Mapbox Support
- Documentation: [https://docs.mapbox.com/api/search/geocoding/](https://docs.mapbox.com/api/search/geocoding/)
- Support: [https://support.mapbox.com/](https://support.mapbox.com/)

### Plugin Support
- GitHub Issues: Report bugs or request features
- PLUGIN_README.md: Full plugin documentation

---

**Last Updated**: Version 0.2.7 - January 23, 2026
