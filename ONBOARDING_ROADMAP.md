# User Onboarding Roadmap (Phase 2)

## Overview
Implement a guided onboarding flow that collects essential user information after registration and subscription signup, increasing activation rates and personalizing the experience.

## Current State (Phase 1 Complete)
- ✅ User registration collects: name, email, phone, password
- ✅ Stripe checkout for subscription
- ✅ Home airport moved to user-level meta (editable in profile)
- ✅ Trial period: 14 days
- ✅ Child color coding implemented

## Phase 2 Goals
- **Increase activation rate**: Users who complete setup are more likely to remain active
- **Collect critical data**: Home airport, timezone, first child
- **Reduce friction**: Guide users through essential setup steps
- **Personalization**: Enable personalized features from day 1
- **Upsell opportunity**: Suggest adding multiple children during setup

---

## Onboarding Flow Design

### Entry Points
1. **After Registration** → Redirect to onboarding (before subscription)
2. **After Stripe Checkout Success** → Redirect to onboarding (after subscription)
3. **First Login** → Check if onboarding complete, redirect if not

### Onboarding Steps

#### Step 1: Welcome
**Page:** `/onboarding/welcome/`
- Welcome message with user's name
- Brief value proposition (30 seconds to read)
- "Let's get you set up" CTA button
- Progress indicator: 1 of 4

**Data Collected:** None (orientation only)

#### Step 2: Home Airport
**Page:** `/onboarding/airport/`
- Question: "Where do you typically travel from?"
- Autocomplete airport search (using `airports.json`)
- Popular airports shown as quick-select buttons (ORD, JFK, LAX, ATL, DFW)
- Optional: "I travel from multiple airports" checkbox
- Progress indicator: 2 of 4

**Data Collected:**
- `srt_home_airport` (user meta)
- `srt_has_multiple_airports` (user meta, optional)

**Skip Logic:** Can skip, but reminded later in dashboard

#### Step 3: Timezone
**Page:** `/onboarding/timezone/`
- Question: "What's your timezone?"
- Auto-detected from browser (pre-filled)
- Dropdown to change if needed
- Explanation: "Used for event reminders and calendar exports"
- Progress indicator: 3 of 4

**Data Collected:**
- `srt_user_timezone` (user meta)

**Skip Logic:** Auto-fills from browser, can change anytime in profile

#### Step 4: Add First Child
**Page:** `/onboarding/child/`
- Question: "Let's add your first child"
- Fields:
  - Child's name (required)
  - Age or grade (optional)
  - Activities/interests (optional free text)
- Automatically assigns calendar color
- Show color preview
- Buttons:
  - "Add Child" (primary)
  - "+ Add Another Child" (secondary)
  - "Skip for Now" (tertiary)
- Progress indicator: 4 of 4

**Data Collected:**
- Creates parent-child relationship
- Assigns color via `FTT_Child_Colors::assign_color()`

**Business Logic:**
- If subscription has slots available, allow adding
- If base plan (1 child), show upsell: "Need more than one child? Add slots for $5/mo each"
- Link to billing page to add slots

#### Step 5: Complete
**Page:** `/onboarding/complete/`
- Success message: "You're all set!"
- Quick tour of features:
  - "Create your first event" (link to event form)
  - "View calendar" (link to calendar)
  - "Invite co-parent" (link to invitations)
- Mark onboarding as complete:
  - `srt_onboarding_complete` = true (user meta)
  - `srt_onboarding_completed_at` = timestamp

**CTA:** "Go to Dashboard" (primary button)

---

## Technical Implementation

### Database Schema
**User Meta Keys:**
- `srt_home_airport` (string, 3 letters) - Already implemented ✅
- `srt_has_multiple_airports` (bool) - Phase 2
- `srt_user_timezone` (string) - Phase 2
- `srt_onboarding_complete` (bool) - Phase 2
- `srt_onboarding_completed_at` (timestamp) - Phase 2
- `srt_onboarding_step` (int, 1-5) - Track progress if abandoned

### Files to Create

#### 1. `includes/onboarding.php`
```php
class FTT_Onboarding {
    public static function init();
    public static function is_complete($user_id);
    public static function mark_complete($user_id);
    public static function get_current_step($user_id);
    public static function set_current_step($user_id, $step);
    public static function should_redirect($user_id);
    public static function get_redirect_url($user_id);
}
```

#### 2. `templates/onboarding/welcome.php`
- Welcome screen with value prop

#### 3. `templates/onboarding/airport.php`
- Airport selection form with autocomplete

#### 4. `templates/onboarding/timezone.php`
- Timezone selection with auto-detect

#### 5. `templates/onboarding/child.php`
- Add first child form

#### 6. `templates/onboarding/complete.php`
- Completion screen with next steps

#### 7. `assets/js/onboarding.js`
- Form validation
- Airport autocomplete
- Timezone auto-detection
- Progress tracking

#### 8. `assets/css/onboarding.css`
- Clean, focused UI
- Progress indicator styles
- Mobile-responsive

### REST API Endpoints
```
POST /ftt/v1/onboarding/airport
POST /ftt/v1/onboarding/timezone
POST /ftt/v1/onboarding/child
POST /ftt/v1/onboarding/complete
GET  /ftt/v1/onboarding/status
```

### Shortcodes
```
[ftt_onboarding_welcome]
[ftt_onboarding_airport]
[ftt_onboarding_timezone]
[ftt_onboarding_child]
[ftt_onboarding_complete]
```

### Pages to Create (Auto-created on activation)
- `/onboarding/welcome/` → `[ftt_onboarding_welcome]`
- `/onboarding/airport/` → `[ftt_onboarding_airport]`
- `/onboarding/timezone/` → `[ftt_onboarding_timezone]`
- `/onboarding/child/` → `[ftt_onboarding_child]`
- `/onboarding/complete/` → `[ftt_onboarding_complete]`

---

## User Experience Flow

### New User Journey
1. User visits `familytraveltracker.app`
2. Clicks "Start Free Trial" on pricing page
3. Fills registration form → Account created
4. Redirected to Stripe checkout → Subscription created
5. **Redirected to `/onboarding/welcome/`** ← Entry to onboarding
6. Completes 4-step onboarding (2-3 minutes)
7. Lands on dashboard with personalized content

### Returning User (Incomplete Onboarding)
1. User logs in
2. System checks: `srt_onboarding_complete` === false
3. Redirect to: `/onboarding/{current_step}/`
4. Resume onboarding where they left off

### Completed User
1. User logs in
2. No redirect, normal dashboard experience

---

## Metrics to Track

### Completion Rates
- Registration → Onboarding started: %
- Onboarding started → Completed: %
- Per-step drop-off rates
- Time to complete onboarding
- Skip rates per step

### Activation Indicators
- Users who add airport: %
- Users who add ≥1 child: %
- Average children added during onboarding
- Users who create first event within 7 days: %

### Business Metrics
- Upsell click-through rate (on child add step)
- Add-on purchases during onboarding
- Trial conversion rate (onboarded vs non-onboarded)

---

## Optional Enhancements (Future)

### Personalization
- Show events near user's airport
- Suggest popular destinations from their airport
- Recommend activities based on children's ages

### Gamification
- Progress bar with celebration animation
- "Achievement unlocked" badge for completing setup
- Email: "You're 80% done setting up your account!"

### A/B Testing Opportunities
- Step order (airport first vs child first)
- Number of steps (combine vs separate)
- Skip button visibility
- Progress indicator style

### Integrations
- Import calendar events from Google/Apple
- Sync timezone with device automatically
- Connect with flight booking APIs for suggestions

---

## Success Criteria

**Must Have:**
- [ ] 80%+ users complete onboarding within first session
- [ ] <5 minute average completion time
- [ ] <10% skip rate on airport selection
- [ ] Users with completed onboarding have 2x higher retention

**Nice to Have:**
- [ ] Mobile onboarding optimized
- [ ] Email reminders for incomplete onboarding
- [ ] Dashboard shows "Complete setup" nudge

---

## Implementation Timeline

**Week 1: Core Infrastructure**
- Create `includes/onboarding.php` class
- Set up REST API endpoints
- Create page templates
- Implement redirect logic

**Week 2: UI/UX**
- Design onboarding screens
- Implement progress indicator
- Build airport autocomplete
- Add timezone auto-detection

**Week 3: Integration**
- Connect to Stripe checkout success page
- Integrate with registration flow
- Link to billing for upsells
- Add child creation logic

**Week 4: Polish & Testing**
- Mobile responsiveness
- Error handling
- Analytics integration
- User testing

---

## Migration Strategy

### For Existing Users
- Mark all existing users as `srt_onboarding_complete = true`
- Send email: "We've added new features! Update your profile"
- Add "Complete your profile" banner in dashboard (dismissible)
- Link to profile page to fill in airport/timezone

### For New Installs
- Onboarding runs automatically after first signup

---

## Dependencies

**Required:**
- ✅ Stripe integration (complete)
- ✅ User registration system (complete)
- ✅ Parent-child relationships (complete)
- ✅ Child color coding (complete)
- ✅ User-level airport storage (complete)

**Optional:**
- Airport autocomplete data (`airports.json`) - already exists ✅
- Email notification system - exists ✅
- Billing manager for upsells - exists ✅

---

## Next Steps

1. **Review this document** - Confirm approach and flow
2. **Design mockups** - Visual design of 5 onboarding screens
3. **Priority decision** - Implement now or after Stripe testing?
4. **Resource allocation** - 1-2 week development effort

---

## Questions to Resolve

- [ ] Should onboarding happen before or after subscription?
- [ ] Required vs optional steps? (e.g., can they skip airport?)
- [ ] Email reminders for incomplete onboarding?
- [ ] Show onboarding progress on dashboard if incomplete?
- [ ] Allow users to restart onboarding from settings?

---

**Status:** 📋 Roadmap - Not Started  
**Priority:** Medium (After Stripe testing)  
**Effort:** 1-2 weeks  
**Impact:** High (activation & retention)
