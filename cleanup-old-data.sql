-- ============================================================================
-- Family Travel Tracker - Database Cleanup Script
-- ============================================================================
-- This script removes ALL old SRT/SC data and FTT data from your WordPress
-- database. Use this for a CLEAN INSTALL of the rebranded v2.0.12 plugin.
--
-- ⚠️  WARNING: THIS WILL DELETE ALL DATA! BACKUP YOUR DATABASE FIRST!
--
-- Run this BEFORE installing the new v2.0.12 plugin.
-- ============================================================================

-- Remove old SRT custom post type events
DELETE FROM wp_posts WHERE post_type = 'srt_event';
DELETE FROM wp_postmeta WHERE post_id IN (
    SELECT ID FROM wp_posts WHERE post_type = 'srt_event'
);

-- Remove FTT custom post type events (if any exist)
DELETE FROM wp_posts WHERE post_type = 'ftt_event';
DELETE FROM wp_postmeta WHERE post_id IN (
    SELECT ID FROM wp_posts WHERE post_type = 'ftt_event'
);

-- Remove old user metadata (SRT prefixes)
DELETE FROM wp_usermeta WHERE meta_key LIKE 'srt_%';
DELETE FROM wp_usermeta WHERE meta_key LIKE 'sc_%';

-- Remove FTT user metadata (clean slate)
DELETE FROM wp_usermeta WHERE meta_key LIKE 'ftt_%';

-- Remove old pages created by plugin (SRT/SC versions)
DELETE FROM wp_posts WHERE post_name IN (
    'sc-dashboard',
    'sc-calendar',
    'sc-events',
    'sc-event',
    'sc-login',
    'sc-register',
    'sc-subscribe',
    'billing',
    'checkout-success',
    'checkout-cancel'
);

-- Remove FTT pages (will be recreated on activation)
DELETE FROM wp_posts WHERE post_name IN (
    'ftt-dashboard',
    'ftt-calendar',
    'ftt-events',
    'ftt-event',
    'ftt-login',
    'ftt-register',
    'ftt-subscribe',
    'ftt-billing',
    'ftt-checkout-success',
    'ftt-checkout-cancel'
);

-- Remove options (SRT/SC/FTT prefixes)
DELETE FROM wp_options WHERE option_name LIKE 'srt_%';
DELETE FROM wp_options WHERE option_name LIKE 'sc_%';
DELETE FROM wp_options WHERE option_name LIKE 'ftt_%';

-- Remove transients
DELETE FROM wp_options WHERE option_name LIKE '_transient_srt_%';
DELETE FROM wp_options WHERE option_name LIKE '_transient_timeout_srt_%';
DELETE FROM wp_options WHERE option_name LIKE '_transient_sc_%';
DELETE FROM wp_options WHERE option_name LIKE '_transient_timeout_sc_%';
DELETE FROM wp_options WHERE option_name LIKE '_transient_ftt_%';
DELETE FROM wp_options WHERE option_name LIKE '_transient_timeout_ftt_%';

-- Remove user roles (will be recreated on activation)
DELETE FROM wp_options WHERE option_name = 'wp_user_roles' AND option_value LIKE '%regiment_parent%';

-- ============================================================================
-- Cleanup Complete!
-- ============================================================================
-- Next steps:
-- 1. Delete old plugin folder: wp-content/plugins/schedule-collaboration-tracking
-- 2. Upload new v2.0.12 plugin
-- 3. Activate plugin (pages and roles will auto-create)
-- 4. Reconfigure Stripe settings
-- 5. Test the checkout flow
-- ============================================================================
