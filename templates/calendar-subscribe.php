<?php
/**
 * Template: Calendar Subscription Instructions
 *
 * Variables available from shortcode:
 *   $https_url  – authenticated https:// feed URL
 *   $webcal_url – webcal:// version (auto-opens calendar apps)
 *   $google_url – Google Calendar "add from URL" deep-link
 *   $qr_url     – QR code image src
 *   $is_mobile  – server-side mobile hint (bool)
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$settings    = get_option('ftt_settings', array());
$site_name   = esc_html( get_bloginfo('name') );
$token_valid = !empty($https_url);
?>

<div class="ftt-container ftt-subscribe-page" id="ftt-subscribe-root">

    <h2><?php esc_html_e('Add Your Family Calendar', 'schedule-collaboration-tracking'); ?></h2>

    <p class="ftt-subscribe-intro">
        <?php printf(
            esc_html__( 'Your %s travel schedule is live. Add it to the calendar you already use — it stays in sync automatically whenever plans change.', 'schedule-collaboration-tracking' ),
            '<strong>' . $site_name . '</strong>'
        ); ?>
    </p>

    <?php if ( !$token_valid ) : ?>
        <div class="ftt-notice ftt-notice-warning">
            <p><strong><?php esc_html_e('Please log in to get your personal calendar link.', 'schedule-collaboration-tracking'); ?></strong></p>
        </div>
    <?php else : ?>

    <?php // ── MOBILE: One-tap subscribe buttons ──────────────────────────────── ?>
    <div class="ftt-cal-actions" id="ftt-cal-actions">

        <?php // Shown on all screens; JS hides/shows the right set ?>

        <div class="ftt-cal-action-mobile" id="ftt-actions-mobile" style="display:none;">
            <p class="ftt-cal-action-label"><?php esc_html_e('One tap to subscribe:', 'schedule-collaboration-tracking'); ?></p>
            <div class="ftt-cal-buttons-row">
                <a href="<?php echo esc_url($webcal_url); ?>" class="ftt-cal-btn ftt-cal-btn-apple">
                    <span class="ftt-cal-btn-icon">🍎</span>
                    <?php esc_html_e('Apple Calendar', 'schedule-collaboration-tracking'); ?>
                </a>
                <a href="<?php echo esc_url($google_url); ?>" class="ftt-cal-btn ftt-cal-btn-google" target="_blank" rel="noopener">
                    <span class="ftt-cal-btn-icon">📅</span>
                    <?php esc_html_e('Google Calendar', 'schedule-collaboration-tracking'); ?>
                </a>
                <a href="<?php echo esc_url($webcal_url); ?>" class="ftt-cal-btn ftt-cal-btn-other">
                    <span class="ftt-cal-btn-icon">📆</span>
                    <?php esc_html_e('Other Calendar App', 'schedule-collaboration-tracking'); ?>
                </a>
            </div>
            <p class="ftt-cal-action-note"><?php esc_html_e('Tapping a button will open your calendar app and ask if you want to subscribe.', 'schedule-collaboration-tracking'); ?></p>
        </div>

        <div class="ftt-cal-action-desktop" id="ftt-actions-desktop" style="display:none;">
            <div class="ftt-url-section">
                <label class="ftt-url-label"><strong><?php esc_html_e('Your personal calendar URL:', 'schedule-collaboration-tracking'); ?></strong></label>
                <div class="ftt-url-copy">
                    <input type="text" readonly id="ftt-calendar-url" value="<?php echo esc_attr($webcal_url); ?>" onclick="this.select();" />
                    <button type="button" class="button button-primary ftt-copy-btn" id="ftt-copy-btn" onclick="fttCopyUrl()"><?php esc_html_e('Copy', 'schedule-collaboration-tracking'); ?></button>
                </div>
                <p class="ftt-url-hint"><?php esc_html_e('This URL is unique to your account — do not share it publicly.', 'schedule-collaboration-tracking'); ?></p>
            </div>
            <div class="ftt-desktop-quick">
                <a href="<?php echo esc_url($google_url); ?>" class="ftt-cal-btn ftt-cal-btn-google" target="_blank" rel="noopener">
                    <span class="ftt-cal-btn-icon">📅</span>
                    <?php esc_html_e('Add to Google Calendar', 'schedule-collaboration-tracking'); ?>
                </a>
                <button type="button" class="ftt-cal-btn ftt-cal-btn-qr" id="ftt-qr-toggle">
                    <span class="ftt-cal-btn-icon">📱</span>
                    <?php esc_html_e('Add to Phone via QR', 'schedule-collaboration-tracking'); ?>
                </button>
            </div>
            <div class="ftt-qr-panel" id="ftt-qr-panel" style="display:none;">
                <p><?php esc_html_e('Scan with your phone\'s camera to subscribe instantly:', 'schedule-collaboration-tracking'); ?></p>
                <img src="<?php echo esc_url($qr_url); ?>" alt="<?php esc_attr_e('Calendar subscription QR code', 'schedule-collaboration-tracking'); ?>" width="180" height="180" class="ftt-qr-img" />
                <p class="ftt-qr-note"><?php esc_html_e('iOS: camera notifies you to subscribe. Android: tap the link, choose Google Calendar.', 'schedule-collaboration-tracking'); ?></p>
            </div>
        </div>

    </div><!-- .ftt-cal-actions -->

    <hr class="ftt-section-divider" />

    <?php // ── PLATFORM INSTRUCTIONS ────────────────────────────────────────── ?>
    <div class="ftt-platform-instructions">

        <h3><?php esc_html_e('Step-by-step instructions', 'schedule-collaboration-tracking'); ?></h3>

        <?php // ── Google Calendar ─────────────────────────────────────────── ?>
        <details class="ftt-platform" id="instructions-google">
            <summary>
                <span class="ftt-platform-icon">📅</span>
                <span class="ftt-platform-name"><?php esc_html_e('Google Calendar', 'schedule-collaboration-tracking'); ?></span>
                <span class="ftt-platform-badge ftt-badge-recommended"><?php esc_html_e('Recommended', 'schedule-collaboration-tracking'); ?></span>
            </summary>
            <div class="ftt-platform-body">
                <p class="ftt-platform-intro"><?php esc_html_e('Once added via Google Calendar web, your schedule automatically appears on every Android phone, iPhone, iPad, and computer signed into your Google account.', 'schedule-collaboration-tracking'); ?></p>

                <div class="ftt-version-tabs">
                    <button type="button" class="ftt-ver-tab ftt-ver-active" data-ver="gcal-web"><?php esc_html_e('Web (google.com)', 'schedule-collaboration-tracking'); ?></button>
                    <button type="button" class="ftt-ver-tab" data-ver="gcal-android"><?php esc_html_e('Android app', 'schedule-collaboration-tracking'); ?></button>
                    <button type="button" class="ftt-ver-tab" data-ver="gcal-ios"><?php esc_html_e('iPhone / iPad app', 'schedule-collaboration-tracking'); ?></button>
                </div>

                <div class="ftt-ver-pane" id="gcal-web">
                    <ol>
                        <li><?php esc_html_e('Go to', 'schedule-collaboration-tracking'); ?> <strong>calendar.google.com</strong> <?php esc_html_e('on a computer', 'schedule-collaboration-tracking'); ?></li>
                        <li><?php esc_html_e('In the left sidebar, click the', 'schedule-collaboration-tracking'); ?> <strong>"+"</strong> <?php esc_html_e('next to "Other calendars"', 'schedule-collaboration-tracking'); ?></li>
                        <li><?php esc_html_e('Choose', 'schedule-collaboration-tracking'); ?> <strong>"From URL"</strong></li>
                        <li><?php esc_html_e('Paste your calendar URL (copy it from the box above)', 'schedule-collaboration-tracking'); ?></li>
                        <li><?php esc_html_e('Click', 'schedule-collaboration-tracking'); ?> <strong>"Add calendar"</strong></li>
                    </ol>
                    <p class="ftt-platform-tip">💡 <?php esc_html_e('Or use the "Add to Google Calendar" button at the top of this page — it fills in the URL for you.', 'schedule-collaboration-tracking'); ?></p>
                    <p class="ftt-platform-note"><?php esc_html_e('⏱ Google checks for updates roughly every 24 hours. If you need the latest events immediately, open the calendar in a browser — it refreshes on page load.', 'schedule-collaboration-tracking'); ?></p>
                </div>

                <div class="ftt-ver-pane" id="gcal-android" style="display:none;">
                    <p class="ftt-platform-note"><?php esc_html_e('The Google Calendar Android app does not support adding URL subscriptions directly. Add it via the web steps above — it will sync to your phone automatically within a few hours.', 'schedule-collaboration-tracking'); ?></p>
                    <ol>
                        <li><?php esc_html_e('Complete the "Web (google.com)" steps on any browser', 'schedule-collaboration-tracking'); ?></li>
                        <li><?php esc_html_e('Open the Google Calendar app on your Android phone', 'schedule-collaboration-tracking'); ?></li>
                        <li><?php esc_html_e('Pull down to refresh or wait up to 24 hours for the calendar to appear', 'schedule-collaboration-tracking'); ?></li>
                        <li><?php esc_html_e('The calendar will show under "Other calendars" with its own color', 'schedule-collaboration-tracking'); ?></li>
                    </ol>
                </div>

                <div class="ftt-ver-pane" id="gcal-ios" style="display:none;">
                    <p><?php esc_html_e('The Google Calendar iPhone/iPad app syncs from your Google account. Add it via web first:', 'schedule-collaboration-tracking'); ?></p>
                    <ol>
                        <li><?php esc_html_e('Follow the "Web (google.com)" steps above on any browser', 'schedule-collaboration-tracking'); ?></li>
                        <li><?php esc_html_e('Open the Google Calendar app on your iPhone or iPad', 'schedule-collaboration-tracking'); ?></li>
                        <li><?php esc_html_e('Tap the ☰ menu → scroll to "Other calendars" → your new calendar will appear', 'schedule-collaboration-tracking'); ?></li>
                    </ol>
                    <p class="ftt-platform-tip">💡 <?php esc_html_e('Alternatively, use the "Apple Calendar" one-tap button at the top of this page — it adds a separate subscription directly to your iPhone without going through Google.', 'schedule-collaboration-tracking'); ?></p>
                </div>
            </div>
        </details>

        <?php // ── Microsoft Outlook ────────────────────────────────────────── ?>
        <details class="ftt-platform" id="instructions-outlook">
            <summary>
                <span class="ftt-platform-icon">💼</span>
                <span class="ftt-platform-name"><?php esc_html_e('Microsoft Outlook', 'schedule-collaboration-tracking'); ?></span>
            </summary>
            <div class="ftt-platform-body">
                <p class="ftt-platform-intro"><?php esc_html_e('Outlook lets you subscribe to external calendars in all versions. The steps vary slightly by version — choose yours below.', 'schedule-collaboration-tracking'); ?></p>

                <div class="ftt-version-tabs">
                    <button type="button" class="ftt-ver-tab ftt-ver-active" data-ver="olk-365web"><?php esc_html_e('Microsoft 365 (web)', 'schedule-collaboration-tracking'); ?></button>
                    <button type="button" class="ftt-ver-tab" data-ver="olk-win"><?php esc_html_e('Outlook for Windows', 'schedule-collaboration-tracking'); ?></button>
                    <button type="button" class="ftt-ver-tab" data-ver="olk-mac"><?php esc_html_e('Outlook for Mac', 'schedule-collaboration-tracking'); ?></button>
                    <button type="button" class="ftt-ver-tab" data-ver="olk-mobile"><?php esc_html_e('Outlook Mobile', 'schedule-collaboration-tracking'); ?></button>
                </div>

                <div class="ftt-ver-pane" id="olk-365web">
                    <ol>
                        <li><?php esc_html_e('Go to', 'schedule-collaboration-tracking'); ?> <strong>outlook.office.com</strong> <?php esc_html_e('and click', 'schedule-collaboration-tracking'); ?> <strong>Calendar</strong></li>
                        <li><?php esc_html_e('In the left panel, click', 'schedule-collaboration-tracking'); ?> <strong>"Add calendar"</strong></li>
                        <li><?php esc_html_e('Choose', 'schedule-collaboration-tracking'); ?> <strong>"Subscribe from web"</strong></li>
                        <li><?php esc_html_e('Paste your calendar URL into the field', 'schedule-collaboration-tracking'); ?></li>
                        <li><?php esc_html_e('Give it a name (e.g., "Family Travel"), choose a color, click', 'schedule-collaboration-tracking'); ?> <strong>"Import"</strong></li>
                    </ol>
                    <p class="ftt-platform-note"><?php esc_html_e('⏱ Outlook 365 syncs roughly every 3 hours. If events look stale, remove and re-add the calendar.', 'schedule-collaboration-tracking'); ?></p>
                </div>

                <div class="ftt-ver-pane" id="olk-win" style="display:none;">
                    <p class="ftt-platform-tip">💡 <?php esc_html_e('These steps apply to Outlook 2016, 2019, 2021, and Microsoft 365 desktop app on Windows.', 'schedule-collaboration-tracking'); ?></p>
                    <ol>
                        <li><?php esc_html_e('Open Outlook and switch to the', 'schedule-collaboration-tracking'); ?> <strong>Calendar</strong> <?php esc_html_e('view (bottom of the left panel)', 'schedule-collaboration-tracking'); ?></li>
                        <li><?php esc_html_e('In the "Home" ribbon, click', 'schedule-collaboration-tracking'); ?> <strong>"Open Calendar"</strong> → <strong>"From Internet…"</strong></li>
                        <li><?php esc_html_e('Paste your calendar URL and click', 'schedule-collaboration-tracking'); ?> <strong>OK</strong></li>
                        <li><?php esc_html_e('Click', 'schedule-collaboration-tracking'); ?> <strong>"Yes"</strong> <?php esc_html_e('when asked if you want to add and subscribe to the calendar', 'schedule-collaboration-tracking'); ?></li>
                    </ol>
                    <p class="ftt-platform-note"><?php esc_html_e('⚠ If you don\'t see "From Internet," try: File → Account Settings → Internet Calendars → New.', 'schedule-collaboration-tracking'); ?></p>
                </div>

                <div class="ftt-ver-pane" id="olk-mac" style="display:none;">
                    <ol>
                        <li><?php esc_html_e('Open Outlook for Mac and click', 'schedule-collaboration-tracking'); ?> <strong>Calendar</strong> <?php esc_html_e('in the toolbar', 'schedule-collaboration-tracking'); ?></li>
                        <li><?php esc_html_e('From the menu bar, choose', 'schedule-collaboration-tracking'); ?> <strong>File → New Calendar Subscription</strong></li>
                        <li><?php esc_html_e('Paste your calendar URL and click', 'schedule-collaboration-tracking'); ?> <strong>"Subscribe"</strong></li>
                    </ol>
                    <p class="ftt-platform-note"><?php esc_html_e('⚠ Older versions of Outlook for Mac have limited iCal support. If subscription doesn\'t work, try adding the calendar via Apple Calendar (macOS) instead — it shares with Outlook automatically when synced through iCloud.', 'schedule-collaboration-tracking'); ?></p>
                </div>

                <div class="ftt-ver-pane" id="olk-mobile" style="display:none;">
                    <p class="ftt-platform-note"><?php esc_html_e('The Outlook mobile app (iOS & Android) does not currently support adding iCal URL subscriptions directly.', 'schedule-collaboration-tracking'); ?></p>
                    <p><?php esc_html_e('Recommended options:', 'schedule-collaboration-tracking'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Add via Outlook web (steps above) — it will sync to your phone.', 'schedule-collaboration-tracking'); ?></li>
                        <li><?php esc_html_e('On iPhone: use the "Apple Calendar" one-tap button at the top of this page.', 'schedule-collaboration-tracking'); ?></li>
                        <li><?php esc_html_e('On Android: add via Google Calendar web — it syncs to all apps on your phone.', 'schedule-collaboration-tracking'); ?></li>
                    </ul>
                </div>
            </div>
        </details>

        <?php // ── Apple Calendar ───────────────────────────────────────────── ?>
        <details class="ftt-platform" id="instructions-apple">
            <summary>
                <span class="ftt-platform-icon">🍎</span>
                <span class="ftt-platform-name"><?php esc_html_e('Apple Calendar', 'schedule-collaboration-tracking'); ?></span>
            </summary>
            <div class="ftt-platform-body">
                <p class="ftt-platform-intro"><?php esc_html_e('Apple Calendar works great for iPhone, iPad, and Mac. A single subscription syncs across all your Apple devices via iCloud.', 'schedule-collaboration-tracking'); ?></p>

                <div class="ftt-version-tabs">
                    <button type="button" class="ftt-ver-tab ftt-ver-active" data-ver="apple-ios"><?php esc_html_e('iPhone / iPad', 'schedule-collaboration-tracking'); ?></button>
                    <button type="button" class="ftt-ver-tab" data-ver="apple-mac"><?php esc_html_e('Mac', 'schedule-collaboration-tracking'); ?></button>
                </div>

                <div class="ftt-ver-pane" id="apple-ios">
                    <p class="ftt-platform-tip">💡 <?php esc_html_e('The easiest way on iPhone/iPad is to tap the "Apple Calendar" button at the top of this page. It goes straight to the subscription dialog.', 'schedule-collaboration-tracking'); ?></p>
                    <p><?php esc_html_e('Or manually:', 'schedule-collaboration-tracking'); ?></p>
                    <ol>
                        <li><?php esc_html_e('Open', 'schedule-collaboration-tracking'); ?> <strong>Settings</strong> → <strong>Calendar</strong> → <strong>Accounts</strong> → <strong>"Add Account"</strong></li>
                        <li><?php esc_html_e('Scroll down and tap', 'schedule-collaboration-tracking'); ?> <strong>"Other"</strong></li>
                        <li><?php esc_html_e('Tap', 'schedule-collaboration-tracking'); ?> <strong>"Add Subscribed Calendar"</strong></li>
                        <li><?php esc_html_e('Paste your calendar URL and tap', 'schedule-collaboration-tracking'); ?> <strong>"Next"</strong></li>
                        <li><?php esc_html_e('Review and tap', 'schedule-collaboration-tracking'); ?> <strong>"Save"</strong></li>
                    </ol>
                    <p class="ftt-platform-note"><?php esc_html_e('⏱ iOS syncs subscribed calendars roughly every hour. To force a refresh: open Calendar app → pull down on the event list.', 'schedule-collaboration-tracking'); ?></p>
                </div>

                <div class="ftt-ver-pane" id="apple-mac" style="display:none;">
                    <ol>
                        <li><?php esc_html_e('Open the', 'schedule-collaboration-tracking'); ?> <strong>Calendar</strong> <?php esc_html_e('app', 'schedule-collaboration-tracking'); ?></li>
                        <li><?php esc_html_e('From the menu bar, choose', 'schedule-collaboration-tracking'); ?> <strong>File → New Calendar Subscription</strong></li>
                        <li><?php esc_html_e('Paste your calendar URL and click', 'schedule-collaboration-tracking'); ?> <strong>"Subscribe"</strong></li>
                        <li><?php esc_html_e('Set the refresh frequency to "Every hour" for best results, then click', 'schedule-collaboration-tracking'); ?> <strong>"OK"</strong></li>
                        <li><?php esc_html_e('When asked where to store it, choose', 'schedule-collaboration-tracking'); ?> <strong>"iCloud"</strong> <?php esc_html_e('to keep it synced across all Apple devices', 'schedule-collaboration-tracking'); ?></li>
                    </ol>
                </div>
            </div>
        </details>

        <?php // ── Other apps ───────────────────────────────────────────────── ?>
        <details class="ftt-platform" id="instructions-other">
            <summary>
                <span class="ftt-platform-icon">📆</span>
                <span class="ftt-platform-name"><?php esc_html_e('OurFamilyWizard, TeamSnap & other apps', 'schedule-collaboration-tracking'); ?></span>
            </summary>
            <div class="ftt-platform-body">
                <p class="ftt-platform-intro"><?php esc_html_e('Do you already use OurFamilyWizard, TeamSnap, Cozi, Fantastical, or another calendar? Your travel schedule can be added to any app that supports iCal URL subscriptions (.ics).', 'schedule-collaboration-tracking'); ?></p>
                <p><?php esc_html_e('The general steps are the same in most apps:', 'schedule-collaboration-tracking'); ?></p>
                <ol>
                    <li><?php esc_html_e('Look for: "Add calendar", "Subscribe to calendar", "Add from URL", or "Import .ics"', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Choose the "from web URL" or "subscribe" option (not a one-time import)', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Paste your calendar URL from the box at the top of this page', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Save — the calendar will appear and stay updated automatically', 'schedule-collaboration-tracking'); ?></li>
                </ol>
                <div class="ftt-app-notes">
                    <p><strong><?php esc_html_e('OurFamilyWizard:', 'schedule-collaboration-tracking'); ?></strong> <?php esc_html_e('Go to your OFW calendar → Settings → "Sync Calendar" → paste the URL under "Subscribe to external calendar."', 'schedule-collaboration-tracking'); ?></p>
                    <p><strong><?php esc_html_e('TeamSnap:', 'schedule-collaboration-tracking'); ?></strong> <?php esc_html_e('TeamSnap exports its own .ics feed that you can subscribe to from here. TeamSnap does not currently support importing external calendars.', 'schedule-collaboration-tracking'); ?></p>
                    <p><strong><?php esc_html_e('Fantastical:', 'schedule-collaboration-tracking'); ?></strong> <?php esc_html_e('Settings → Accounts → Add Account → Other → CalDAV/iCal Subscriptions → paste the URL.', 'schedule-collaboration-tracking'); ?></p>
                    <p><strong><?php esc_html_e('Cozi Family Organizer:', 'schedule-collaboration-tracking'); ?></strong> <?php esc_html_e('Cozi does not support external iCal subscriptions. We recommend using Google Calendar alongside Cozi.', 'schedule-collaboration-tracking'); ?></p>
                </div>
                <p class="ftt-platform-note"><?php esc_html_e('⚠ If an app asks for a "".ics file" rather than a URL, that creates a one-time snapshot — not a live subscription. Events added later won\'t appear. Always look for the "subscribe" or "URL" option.', 'schedule-collaboration-tracking'); ?></p>
            </div>
        </details>

    </div><!-- .ftt-platform-instructions -->

    <hr class="ftt-section-divider" />

    <?php // ── WHY OUR CALENDAR / READ-ONLY NOTICE ─────────────────────────── ?>
    <div class="ftt-cal-why">
        <h3><?php esc_html_e('A few things worth knowing', 'schedule-collaboration-tracking'); ?></h3>
        <div class="ftt-cal-why-grid">
            <div class="ftt-why-card ftt-why-good">
                <span class="ftt-why-icon">✅</span>
                <div>
                    <strong><?php esc_html_e('Always up to date', 'schedule-collaboration-tracking'); ?></strong>
                    <p><?php esc_html_e('When a trip leg, event time, or location changes in the app, your subscribed calendar updates automatically on the next sync.', 'schedule-collaboration-tracking'); ?></p>
                </div>
            </div>
            <div class="ftt-why-card ftt-why-good">
                <span class="ftt-why-icon">✅</span>
                <div>
                    <strong><?php esc_html_e('Everyone travels with context', 'schedule-collaboration-tracking'); ?></strong>
                    <p><?php printf( esc_html__( 'Your %s calendar includes all children across all your groups — one feed covers your whole family.', 'schedule-collaboration-tracking' ), $site_name ); ?></p>
                </div>
            </div>
            <div class="ftt-why-card ftt-why-limit">
                <span class="ftt-why-icon">ℹ️</span>
                <div>
                    <strong><?php esc_html_e('The subscribed calendar is read-only', 'schedule-collaboration-tracking'); ?></strong>
                    <p><?php esc_html_e('Events shown in your calendar app cannot be edited there — changes must be made in the app. Edits made in your calendar will be overwritten on the next sync.', 'schedule-collaboration-tracking'); ?></p>
                </div>
            </div>
            <div class="ftt-why-card ftt-why-limit">
                <span class="ftt-why-icon">ℹ️</span>
                <div>
                    <strong><?php esc_html_e('Adding events from your phone', 'schedule-collaboration-tracking'); ?></strong>
                    <p><?php printf(
                        esc_html__( 'To add or edit a trip from your phone, open %s in your mobile browser — the site is mobile-friendly and all changes save instantly.', 'schedule-collaboration-tracking' ),
                        '<a href="' . esc_url( home_url('/') ) . '">' . $site_name . '</a>'
                    ); ?></p>
                </div>
            </div>
        </div>
    </div>

    <hr class="ftt-section-divider" />

    <?php // ── FAQ ───────────────────────────────────────────────────────────── ?>
    <div class="ftt-faq">
        <h3><?php esc_html_e('Frequently asked questions', 'schedule-collaboration-tracking'); ?></h3>

        <details>
            <summary><?php esc_html_e('How often does the calendar update?', 'schedule-collaboration-tracking'); ?></summary>
            <p><?php esc_html_e('It depends on your calendar app: Apple Calendar syncs every 1 hour, Google Calendar every 24 hours, Outlook 365 every 3 hours. Most apps also let you manually refresh. For real-time accuracy, open the app directly.', 'schedule-collaboration-tracking'); ?></p>
        </details>

        <details>
            <summary><?php esc_html_e('Will this break if I change my password?', 'schedule-collaboration-tracking'); ?></summary>
            <p><?php esc_html_e('No — your calendar link uses a separate security token that is not connected to your password. It will keep working after a password change. The only way to break it is if you explicitly regenerate your calendar token (contact support to do that).', 'schedule-collaboration-tracking'); ?></p>
        </details>

        <details>
            <summary><?php esc_html_e('Does it use my phone data?', 'schedule-collaboration-tracking'); ?></summary>
            <p><?php esc_html_e('Calendar sync uses very little data — typically a few kilobytes per update. It syncs in the background only when connected to the internet.', 'schedule-collaboration-tracking'); ?></p>
        </details>

        <details>
            <summary><?php esc_html_e('How do I remove the calendar?', 'schedule-collaboration-tracking'); ?></summary>
            <p><?php esc_html_e('In your calendar app, find the calendar named after this site and delete it from your subscriptions. This only removes it from your device — your family\'s events remain in the app.', 'schedule-collaboration-tracking'); ?></p>
        </details>

        <details>
            <summary><?php esc_html_e('The calendar shows "could not load" or is empty — what do I do?', 'schedule-collaboration-tracking'); ?></summary>
            <p><?php esc_html_e('This usually means the calendar app can\'t reach the server, or the URL was entered incorrectly. Try these steps:', 'schedule-collaboration-tracking'); ?></p>
            <ol>
                <li><?php esc_html_e('Remove the calendar from your app', 'schedule-collaboration-tracking'); ?></li>
                <li><?php esc_html_e('Come back to this page and use the one-tap button or copy the URL fresh', 'schedule-collaboration-tracking'); ?></li>
                <li><?php esc_html_e('Re-subscribe using the fresh URL', 'schedule-collaboration-tracking'); ?></li>
            </ol>
            <p><?php esc_html_e('If the problem continues, contact support — your account token may need to be regenerated.', 'schedule-collaboration-tracking'); ?></p>
        </details>
    </div>

    <?php endif; // token_valid ?>

</div><!-- .ftt-subscribe-page -->

<script>
(function() {
    // Detect mobile vs desktop and show the right action panel
    var isMobile = /iPhone|iPad|iPod|Android|Mobile|BlackBerry|IEMobile/i.test(navigator.userAgent);
    document.getElementById('ftt-actions-mobile').style.display  = isMobile  ? 'block' : 'none';
    document.getElementById('ftt-actions-desktop').style.display = !isMobile ? 'block' : 'none';

    // QR code toggle
    var qrBtn = document.getElementById('ftt-qr-toggle');
    var qrPanel = document.getElementById('ftt-qr-panel');
    if (qrBtn && qrPanel) {
        qrBtn.addEventListener('click', function() {
            var open = qrPanel.style.display !== 'none';
            qrPanel.style.display = open ? 'none' : 'block';
        });
    }

    // Version tabs inside each platform block
    document.querySelectorAll('.ftt-version-tabs').forEach(function(tabGroup) {
        tabGroup.querySelectorAll('.ftt-ver-tab').forEach(function(tab) {
            tab.addEventListener('click', function() {
                var platform = tab.closest('.ftt-platform-body');
                // Deactivate all tabs in this group
                tabGroup.querySelectorAll('.ftt-ver-tab').forEach(function(t) {
                    t.classList.remove('ftt-ver-active');
                });
                // Hide all panes in this platform
                platform.querySelectorAll('.ftt-ver-pane').forEach(function(p) {
                    p.style.display = 'none';
                });
                // Activate clicked tab and its pane
                tab.classList.add('ftt-ver-active');
                var target = document.getElementById(tab.dataset.ver);
                if (target) target.style.display = 'block';
            });
        });
    });

    // Auto-open the right instruction section based on detected device
    if (typeof isMobile !== 'undefined') {
        if (isMobile) {
            var isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent);
            var targetId = isIOS ? 'instructions-apple' : 'instructions-google';
            var el = document.getElementById(targetId);
            if (el) el.open = true;
        }
    }
})();

function fttCopyUrl() {
    var input = document.getElementById('ftt-calendar-url');
    if (!input) return;
    input.select();
    input.setSelectionRange(0, 99999);
    var btn = document.getElementById('ftt-copy-btn');
    navigator.clipboard.writeText(input.value).then(function() {
        if (btn) {
            var orig = btn.textContent;
            btn.textContent = '<?php echo esc_js( __('Copied!', 'schedule-collaboration-tracking') ); ?>';
            btn.style.background = '#4CAF50';
            setTimeout(function() { btn.textContent = orig; btn.style.background = ''; }, 2500);
        }
    }).catch(function() {
        // Fallback for older browsers
        document.execCommand('copy');
    });
}
</script>


<div class="ftt-container ftt-subscribe-page">
    <h2><?php esc_html_e('Subscribe to Calendar', 'schedule-collaboration-tracking'); ?></h2>
    
    <p><?php esc_html_e('Subscribe to your family calendar in your calendar app to automatically receive updates when events are added or changed.', 'schedule-collaboration-tracking'); ?></p>
    
    <?php if ($requires_auth && empty($feed_url)) : ?>
        <div class="ftt-notice ftt-notice-info">
            <p><strong><?php esc_html_e('Authentication Required', 'schedule-collaboration-tracking'); ?></strong></p>
            <p><?php esc_html_e('This calendar requires an authentication token. Please contact your administrator for a calendar subscription URL.', 'schedule-collaboration-tracking'); ?></p>
        </div>
    <?php else : ?>
        
        <div class="ftt-subscribe-url-box">
            <label><strong><?php esc_html_e('Calendar URL:', 'schedule-collaboration-tracking'); ?></strong></label>
            <div class="ftt-url-copy">
                <input type="text" readonly value="<?php echo esc_attr($feed_url); ?>" id="ftt-calendar-url" onclick="this.select();">
                <button type="button" class="button button-primary" onclick="fttCopyUrl()"><?php esc_html_e('Copy URL', 'schedule-collaboration-tracking'); ?></button>
            </div>
        </div>
        
        <div class="ftt-instructions">
            <h3><?php esc_html_e('How to Subscribe:', 'schedule-collaboration-tracking'); ?></h3>
            
            <!-- iOS / iPhone / iPad -->
            <details class="ftt-device-instructions">
                <summary><strong>📱 iPhone / iPad (Apple Calendar)</strong></summary>
                <ol>
                    <li><?php esc_html_e('Copy the calendar URL above', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Open the Settings app', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Tap "Calendar" → "Accounts" → "Add Account"', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Tap "Other" → "Add Subscribed Calendar"', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Paste the calendar URL', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Tap "Next" → "Save"', 'schedule-collaboration-tracking'); ?></li>
                </ol>
                <p class="ftt-note"><?php esc_html_e('The calendar will now appear in your Calendar app and sync automatically.', 'schedule-collaboration-tracking'); ?></p>
            </details>
            
            <!-- Android / Google Calendar -->
            <details class="ftt-device-instructions">
                <summary><strong>📱 Android (Google Calendar)</strong></summary>
                <ol>
                    <li><?php esc_html_e('Copy the calendar URL above', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Open Google Calendar on your computer (calendar.google.com)', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Click the "+" next to "Other calendars"', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Select "From URL"', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Paste the calendar URL', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Click "Add calendar"', 'schedule-collaboration-tracking'); ?></li>
                </ol>
                <p class="ftt-note"><?php esc_html_e('The calendar will sync to all devices signed into your Google account.', 'schedule-collaboration-tracking'); ?></p>
            </details>
            
            <!-- macOS Calendar -->
            <details class="ftt-device-instructions">
                <summary><strong>💻 Mac (Calendar App)</strong></summary>
                <ol>
                    <li><?php esc_html_e('Copy the calendar URL above', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Open Calendar app', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Go to File → New Calendar Subscription', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Paste the calendar URL', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Click "Subscribe"', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Configure refresh frequency and click "OK"', 'schedule-collaboration-tracking'); ?></li>
                </ol>
                <p class="ftt-note"><?php esc_html_e('Calendar will sync with iCloud if enabled.', 'schedule-collaboration-tracking'); ?></p>
            </details>
            
            <!-- Outlook -->
            <details class="ftt-device-instructions">
                <summary><strong>💼 Outlook (Windows / macOS / Web)</strong></summary>
                <ol>
                    <li><?php esc_html_e('Copy the calendar URL above', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('In Outlook, go to Calendar view', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Right-click "My Calendars" → "Add Calendar" → "From Internet"', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Paste the calendar URL', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Click "OK"', 'schedule-collaboration-tracking'); ?></li>
                </ol>
                <p class="ftt-note"><?php esc_html_e('Outlook checks for updates every few hours.', 'schedule-collaboration-tracking'); ?></p>
            </details>
        </div>
        
        <div class="ftt-faq">
            <h3><?php esc_html_e('Frequently Asked Questions', 'schedule-collaboration-tracking'); ?></h3>
            
            <details>
                <summary><?php esc_html_e('How often does the calendar update?', 'schedule-collaboration-tracking'); ?></summary>
                <p><?php esc_html_e('Most calendar apps check for updates every few hours to once per day. Changes to the schedule will appear automatically on your device.', 'schedule-collaboration-tracking'); ?></p>
            </details>
            
            <details>
                <summary><?php esc_html_e('Can I edit events in my calendar app?', 'schedule-collaboration-tracking'); ?></summary>
                <p><?php esc_html_e('No, subscribed calendars are read-only. Any changes must be made on the website. Your edits would be overwritten on the next sync.', 'schedule-collaboration-tracking'); ?></p>
            </details>
            
            <details>
                <summary><?php esc_html_e('How do I unsubscribe?', 'schedule-collaboration-tracking'); ?></summary>
                <p><?php esc_html_e('In your calendar app, find the "Schedule" calendar and delete/remove it from your subscriptions.', 'schedule-collaboration-tracking'); ?></p>
            </details>
            
            <details>
                <summary><?php esc_html_e('Will this use my phone data?', 'schedule-collaboration-tracking'); ?></summary>
                <p><?php esc_html_e('Calendar syncing uses very little data (typically a few KB per update). It syncs in the background when you have an internet connection.', 'schedule-collaboration-tracking'); ?></p>
            </details>
        </div>
        
    <?php endif; ?>
</div>

<style>
.ftt-subscribe-page {
    max-width: 800px;
}

.ftt-subscribe-url-box {
    background: #f5f5f5;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.ftt-subscribe-url-box label {
    display: block;
    margin-bottom: 10px;
}

.ftt-url-copy {
    display: flex;
    gap: 10px;
}

.ftt-url-copy input {
    flex: 1;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: monospace;
    font-size: 14px;
}

.ftt-device-instructions {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 10px;
}

.ftt-device-instructions summary {
    cursor: pointer;
    padding: 5px;
    font-size: 16px;
}

.ftt-device-instructions summary:hover {
    background: #f9f9f9;
}

.ftt-device-instructions ol {
    margin: 15px 0 10px 20px;
}

.ftt-device-instructions li {
    margin: 8px 0;
}

.ftt-note {
    background: #e7f5ff;
    border-left: 4px solid #2196F3;
    padding: 10px 15px;
    margin: 10px 0;
    font-style: italic;
}

.ftt-faq {
    margin-top: 30px;
}

.ftt-faq details {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 10px;
}

.ftt-faq summary {
    cursor: pointer;
    font-weight: bold;
    padding: 5px;
}

.ftt-faq summary:hover {
    background: #f9f9f9;
}

.ftt-faq p {
    margin: 10px 0 0 0;
    padding-left: 10px;
}

.ftt-notice {
    padding: 15px;
    border-radius: 4px;
    margin: 20px 0;
}

.ftt-notice-info {
    background: #e7f5ff;
    border-left: 4px solid #2196F3;
}
</style>

<script>
function fttCopyUrl() {
    var input = document.getElementById('ftt-calendar-url');
    input.select();
    input.setSelectionRange(0, 99999); // For mobile
    
    navigator.clipboard.writeText(input.value).then(function() {
        var btn = event.target;
        var originalText = btn.textContent;
        btn.textContent = '<?php esc_js(__('Copied!', 'schedule-collaboration-tracking')); ?>';
        btn.style.background = '#4CAF50';
        
        setTimeout(function() {
            btn.textContent = originalText;
            btn.style.background = '';
        }, 2000);
    });
}
</script>
