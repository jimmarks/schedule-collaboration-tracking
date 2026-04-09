#!/bin/bash

###############################################################################
# Rebrand Script: SRT/SC → FTT (Family Travel Tracker)
#
# This script performs a comprehensive rebrand of the plugin from
# Summer Regiment Tracker (SRT/SC) to Family Travel Tracker (FTT)
###############################################################################

set -e

echo "╔════════════════════════════════════════════════════════════╗"
echo "║   Family Travel Tracker Rebrand Script                    ║"
echo "║   Converting SRT/SC prefixes to FTT                        ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Files to update (exclude lib, vendor, package, download)
FILES=$(find . -type f \( -name "*.php" -o -name "*.js" -o -name "*.css" \) \
    ! -path "./lib/*" \
    ! -path "./node_modules/*" \
    ! -path "./vendor/*" \
    ! -path "./package/*" \
    ! -path "./download/*")

echo "Found $(echo "$FILES" | wc -l) files to update"
echo ""

# 1. Constants
echo "→ Updating constants (SRT_* → FTT_*)"
echo "$FILES" | xargs sed -i 's/SRT_VERSION/FTT_VERSION/g'
echo "$FILES" | xargs sed -i 's/SRT_PLUGIN_DIR/FTT_PLUGIN_DIR/g'
echo "$FILES" | xargs sed -i 's/SRT_PLUGIN_URL/FTT_PLUGIN_URL/g'
echo "$FILES" | xargs sed -i 's/SRT_PLUGIN_BASENAME/FTT_PLUGIN_BASENAME/g'

# 2. Class names
echo "→ Updating class names (SRT_* → FTT_*, Summer_Regiment_Tracker → Family_Travel_Tracker)"
echo "$FILES" | xargs sed -i 's/class SRT_/class FTT_/g'
echo "$FILES" | xargs sed -i 's/SRT_CPT/FTT_CPT/g'
echo "$FILES" | xargs sed -i 's/SRT_Meta/FTT_Meta/g'
echo "$FILES" | xargs sed -i 's/SRT_REST/FTT_REST/g'
echo "$FILES" | xargs sed -i 's/SRT_Settings/FTT_Settings/g'
echo "$FILES" | xargs sed -i 's/SRT_Shortcodes/FTT_Shortcodes/g'
echo "$FILES" | xargs sed -i 's/SRT_Pages/FTT_Pages/g'
echo "$FILES" | xargs sed -i 's/SRT_iCal/FTT_iCal/g'
echo "$FILES" | xargs sed -i 's/SRT_Menu/FTT_Menu/g'
echo "$FILES" | xargs sed -i 's/SRT_Roles/FTT_Roles/g'
echo "$FILES" | xargs sed -i 's/SRT_Registration/FTT_Registration/g'
echo "$FILES" | xargs sed -i 's/SRT_Invitations/FTT_Invitations/g'
echo "$FILES" | xargs sed -i 's/SRT_Price_Tracking/FTT_Price_Tracking/g'
echo "$FILES" | xargs sed -i 's/SRT_Cron_Setup/FTT_Cron_Setup/g'
echo "$FILES" | xargs sed -i 's/SRT_Flight_Linking/FTT_Flight_Linking/g'
echo "$FILES" | xargs sed -i 's/Summer_Regiment_Tracker/Family_Travel_Tracker/g'

# 3. Custom Post Type
echo "→ Updating custom post type (srt_event → ftt_event)"
echo "$FILES" | xargs sed -i "s/'srt_event'/'ftt_event'/g"
echo "$FILES" | xargs sed -i 's/"srt_event"/"ftt_event"/g'
echo "$FILES" | xargs sed -i 's/post_type=srt_event/post_type=ftt_event/g'

# 4. Page slugs
echo "→ Updating page slugs (sc-* → ftt-*)"
echo "$FILES" | xargs sed -i "s/'slug' => 'sc-/'slug' => 'ftt-/g"
echo "$FILES" | xargs sed -i "s/home_url('\/sc-/home_url('\/ftt-/g"
echo "$FILES" | xargs sed -i 's/\/sc-dashboard/\/ftt-dashboard/g'
echo "$FILES" | xargs sed -i 's/\/sc-calendar/\/ftt-calendar/g'
echo "$FILES" | xargs sed -i 's/\/sc-events/\/ftt-events/g'
echo "$FILES" | xargs sed -i 's/\/sc-event-form/\/ftt-event-form/g'

# 5. Shortcodes
echo "→ Updating shortcodes ([srt_* → [ftt_*)"
echo "$FILES" | xargs sed -i 's/\[srt_/[ftt_/g'
echo "$FILES" | xargs sed -i "s/'srt_dashboard'/'ftt_dashboard'/g"
echo "$FILES" | xargs sed -i "s/'srt_calendar'/'ftt_calendar'/g"
echo "$FILES" | xargs sed -i "s/'srt_event_form'/'ftt_event_form'/g"
echo "$FILES" | xargs sed -i "s/'srt_event_list'/'ftt_event_list'/g"
echo "$FILES" | xargs sed -i "s/'srt_calendar_subscribe'/'ftt_calendar_subscribe'/g"
echo "$FILES" | xargs sed -i "s/'srt_login'/'ftt_login'/g"
echo "$FILES" | xargs sed -i "s/'srt_registration'/'ftt_registration'/g"

# 6. Function names
echo "→ Updating function names (srt_* → ftt_*)"
echo "$FILES" | xargs sed -i 's/function srt_/function ftt_/g'
echo "$FILES" | xargs sed -i 's/srt_init(/ftt_init(/g'
echo "$FILES" | xargs sed -i 's/srt_get_/ftt_get_/g'

# 7. Options and meta keys
echo "→ Updating option/meta keys (srt_* → ftt_*)"
echo "$FILES" | xargs sed -i "s/'srt_settings'/'ftt_settings'/g"
echo "$FILES" | xargs sed -i "s/'srt_is_member'/'ftt_is_member'/g"
echo "$FILES" | xargs sed -i "s/'srt_parent_children'/'ftt_parent_children'/g"
echo "$FILES" | xargs sed -i "s/'srt_member_id_migration_done'/'ftt_member_id_migration_done'/g"
echo "$FILES" | xargs sed -i "s/'srt_member_caps_upgraded_v1_0_8'/'ftt_member_caps_upgraded_v1_0_8'/g"
echo "$FILES" | xargs sed -i "s/'srt_home_airport'/'ftt_home_airport'/g"

# 8. REST API namespace
echo "→ Updating REST API namespace (srt/v1 → ftt/v1)"
echo "$FILES" | xargs sed -i "s|'srt/v1|'ftt/v1|g"
echo "$FILES" | xargs sed -i 's|srt/v1/|ftt/v1/|g'
echo "$FILES" | xargs sed -i 's|restUrl.*srt/v1|restUrl: rest_url("ftt/v1")|g'

# 9. CSS/JS handles and classes
echo "→ Updating CSS/JS handles and classes (srt-* → ftt-*)"
echo "$FILES" | xargs sed -i "s/'srt-styles'/'ftt-styles'/g"
echo "$FILES" | xargs sed -i "s/'srt-fullcalendar'/'ftt-fullcalendar'/g"
echo "$FILES" | xargs sed -i "s/'srt-main'/'ftt-main'/g"
echo "$FILES" | xargs sed -i "s/'srtData'/'fttData'/g"
echo "$FILES" | xargs sed -i 's/\.srt-/\.ftt-/g'
echo "$FILES" | xargs sed -i 's/class="srt-/class="ftt-/g'
echo "$FILES" | xargs sed -i 's/id="srt-/id="ftt-/g'
echo "$FILES" | xargs sed -i 's/#srt-/#ftt-/g'

# 10. Variables
echo "→ Updating variable names"
echo "$FILES" | xargs sed -i 's/\$srtUpdateChecker/\$fttUpdateChecker/g'
echo "$FILES" | xargs sed -i 's/\$srtData/\$fttData/g'

# 11. Admin menu slugs
echo "→ Updating admin menu slugs"
echo "$FILES" | xargs sed -i "s/'edit.php?post_type=srt_event'/'edit.php?post_type=ftt_event'/g"

echo ""
echo "✓ Rebrand complete!"
echo ""
echo "Files updated: $(echo "$FILES" | wc -l)"
echo ""
echo "Please review the changes and test the plugin."
