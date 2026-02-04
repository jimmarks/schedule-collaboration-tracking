# Google Places API Setup Guide

## Why Google Places?

**Problem**: Mapbox doesn't have schools and local venues in its database. When you search for "Southington High School", it returns "High Street" (a street name) instead of the actual school.

**Solution**: Google Places has comprehensive coverage of schools, parks, and local venues - exactly what drum corps need.

## Cost Comparison

### Mapbox
- ✅ Free: 100,000 requests/month
- ❌ Missing schools and many local venues
- **Result**: Doesn't work for your use case

### Google Places
- 💰 Cost: ~$0.017-0.032 per search
- ✅ Finds schools, parks, and all venues accurately
- **Reality**: ~$2 per season for 30 events
- **Result**: Works perfectly for drum corps

## Setup Instructions

### Step 1: Create Google Cloud Account

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Sign in with your Google account
3. Accept terms of service
4. **Note**: Requires credit card, but won't charge without your permission

### Step 2: Create a Project

1. Click the project dropdown at the top
2. Click "New Project"
3. Name: "Regiment Tracker" (or your corps name)
4. Click "Create"
5. Wait for project creation (30 seconds)

### Step 3: Enable Required APIs

**IMPORTANT**: You need to enable TWO APIs:

#### 3a. Enable Places API

1. Go to [APIs & Services → Library](https://console.cloud.google.com/apis/library)
2. Search for "Places API"
3. Click "Places API" (not "Places API (New)")
4. Click "Enable"
5. Wait for activation

#### 3b. Enable Maps JavaScript API

1. In the same API Library, search for "Maps JavaScript API"
2. Click "Maps JavaScript API"
3. Click "Enable"
4. Wait for activation

⚠️ **Both APIs are required** - the Places Autocomplete widget uses the Maps JavaScript API to render the results.

### Step 4: Enable Billing (Required)

1. Go to [Billing](https://console.cloud.google.com/billing)
2. Click "Link a billing account" or "Create billing account"
3. Enter credit card information
4. ✅ **Don't worry**: $200 free credit per month
5. ✅ **Safety**: Set up budget alerts (recommended)

### Step 5: Create API Key

1. Go to [APIs & Services → Credentials](https://console.cloud.google.com/apis/credentials)
2. Click "+ CREATE CREDENTIALS"
3. Select "API key"
4. Copy the key (starts with `AIza...`)

### Step 6: Restrict API Key (Security)

**Important**: Restrict your key to prevent unauthorized use!

1. Click "Edit API key" (or click the key you just created)
2. Under "API restrictions":
   - Select "Restrict key"
   - Check BOTH:
     - ✅ "Places API"
     - ✅ "Maps JavaScript API"
3. Under "Website restrictions":
   - Select "HTTP referrers (websites)"
   - Add: `https://yoursite.com/*`
   - Add: `http://yoursite.com/*`
4. Click "Save"

### Step 7: Add to WordPress

1. Go to WordPress Admin → Regiment Events → Settings
2. Find "Address Autocomplete Provider" dropdown
3. Select **"Google Places (Best for Schools/Venues)"**
4. Paste your API key in "Google Places API Key" field
5. Click "Save Settings"

### Step 8: Test It!

1. Go to your event form page
2. Click in "Location Name" field
3. Type: "Southington High School"
4. You should see actual autocomplete with the school!

## Cost Management

### Set Up Budget Alert (Recommended)

1. Go to [Billing → Budgets & alerts](https://console.cloud.google.com/billing/budgets)
2. Click "Create Budget"
3. Set budget amount: $10 (way more than you'll use)
4. Set alert at: 50%, 90%, 100%
5. Add your email
6. Click "Finish"

### Expected Costs

**Typical Drum Corps Season**:
- 30 events
- Average 3 searches per event to find right location
- 90 total searches
- Cost: 90 × $0.017 = **$1.53 per season**

**Google's Free Tier**:
- $200 credit per month
- Covers ~11,764 searches per month
- Way more than you'll ever use

### API Pricing Breakdown

**Autocomplete - Per Session**:
- $0.017 per session (typing and selecting)
- A "session" = typing → selecting → done
- Creating one event = typically 1 session

**Place Details** (if needed):
- $0.017 per request
- Usually not needed with our implementation

**Total per event**: ~$0.017-0.034

## Troubleshooting

### "This API project is not authorized to use this API"

**Solution**:
1. Make sure you enabled "Places API" (not "Places API (New)")
2. Wait 5 minutes for activation
3. Try again

### "You must enable Billing on the Google Cloud Project"

**Solution**:
1. Go to [Billing](https://console.cloud.google.com/billing)
2. Link a billing account
3. Enter credit card (won't charge without permission)

### "ApiNotActivatedMapError" or "Maps JavaScript API error"

**Problem**: The Maps JavaScript API isn't enabled (required for Places Autocomplete)

**Solution**:
1. Go to [APIs & Services → Library](https://console.cloud.google.com/apis/library)
2. Search for "Maps JavaScript API"
3. Click "Maps JavaScript API"
4. Click "Enable"
5. Edit your API key and make sure it includes:
   - ✅ Places API
   - ✅ Maps JavaScript API
6. Wait 2-5 minutes for changes to propagate
7. Refresh your page (Ctrl+F5)

### Autocomplete Not Showing Up

**Check**:
1. Browser console (F12) for errors
2. Make sure provider is set to "Google Places"
3. Make sure API key is saved
4. Refresh page (Ctrl+F5)
5. Check API key restrictions don't block your domain

### "RefererNotAllowedMapError"

**Solution**:
1. Edit your API key
2. Add your WordPress URL to HTTP referrers
3. Format: `https://yoursite.com/*`
4. Save and wait 5 minutes

## Security Best Practices

### ✅ DO:
- Restrict API key to Places API only
- Add website restrictions
- Set up budget alerts
- Monitor usage monthly

### ❌ DON'T:
- Share your API key publicly
- Leave key unrestricted
- Use the same key for multiple projects

## Monitoring Usage

### Check Your Usage

1. Go to [APIs & Services → Dashboard](https://console.cloud.google.com/apis/dashboard)
2. Click "Places API"
3. View requests and costs

### Typical Monthly Usage
- **Light use**: 20-50 searches = $0.34-0.85
- **Active season**: 100-200 searches = $1.70-3.40
- **Heavy use**: 500 searches = $8.50

## Alternative: Stay with Mapbox

If you don't want to pay:
1. Keep provider set to "Mapbox"
2. Search for full address: "720 Pleasant St Southington CT"
3. Or search for city: "Southington Connecticut"
4. Manually type school name in Location Name field

Mapbox is free but won't find schools. Google Places costs ~$2/season but finds everything.

## Support

### Google Cloud Support
- Documentation: [https://developers.google.com/maps/documentation/places/](https://developers.google.com/maps/documentation/places/)
- Support: [https://support.google.com/googleapi/](https://support.google.com/googleapi/)

### Plugin Support
- Check MAPBOX_TROUBLESHOOTING.md for general autocomplete issues
- GitHub Issues for plugin-specific problems

---

**Last Updated**: Version 0.3.0 - January 23, 2026
