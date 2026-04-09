<?php
/**
 * Template: Onboarding Wizard
 *
 * Step 1 – Calendar Setup: app calendar is ready; optionally connect an external iCal feed.
 * Step 2 – Billing Offer: show pricing; user can set up Stripe or skip to use card-free trial.
 *
 * @package Family_Travel_Tracker
 */

if ( ! is_user_logged_in() ) {
    wp_safe_redirect( home_url( '/ftt-login/' ) );
    exit;
}

$current_user     = wp_get_current_user();
$user_id          = get_current_user_id();
$step             = max( 1, min( 2, intval( $_GET['step'] ?? 1 ) ) );
$stripe_settings  = get_option( 'ftt_stripe_settings', [] );
$trial_days       = max( 1, intval( $stripe_settings['trial_days'] ?? 14 ) );
$primary_group_id = get_user_meta( $user_id, 'ftt_primary_group', true );
$group            = $primary_group_id && class_exists( 'FTT_Family_Groups' )
                    ? FTT_Family_Groups::get_group( $primary_group_id )
                    : null;

$first_name = $current_user->first_name ?: $current_user->display_name;
$group_name = $group ? $group->name : __( 'your family', 'schedule-collaboration-tracking' );

// Use the actual trial end date stored on the group so returning users see
// the *remaining* days, not the full configured trial length again.
$trial_ends_at   = ( $group && ! empty( $group->trial_ends_at ) ) ? $group->trial_ends_at : null;
$trial_days_left = $trial_ends_at
    ? max( 0, (int) ceil( ( strtotime( $trial_ends_at ) - time() ) / DAY_IN_SECONDS ) )
    : $trial_days; // fall back to full length if no end date yet
$trial_end_label = $trial_ends_at
    ? date_i18n( get_option( 'date_format' ), strtotime( $trial_ends_at ) )
    : date_i18n( get_option( 'date_format' ), strtotime( "+{$trial_days} days" ) );

$step2_url  = esc_url( add_query_arg( 'step', 2, home_url( '/ftt-onboarding/' ) ) );
$finish_url = esc_url( home_url( '/ftt-groups/?welcome=1' ) );

// Build this user's personal calendar subscribe URLs for the Step 1 modal.
$cal_webcal_url = '';
$cal_google_url = '';
$cal_qr_url     = '';
$cal_https_url  = '';
$ical_enabled   = ! empty( get_option( 'ftt_settings', [] )['enable_ical_feed'] );
if ( $ical_enabled ) {
    $cal_token = get_user_meta( $user_id, 'ftt_calendar_token', true );
    if ( empty( $cal_token ) ) {
        $cal_token = wp_generate_password( 32, false );
        update_user_meta( $user_id, 'ftt_calendar_token', $cal_token );
    }
    $cal_https_url  = add_query_arg(
        [ 'ftt_calendar' => '1', 'token' => $cal_token, 'user_id' => $user_id ],
        home_url( '/' )
    );
    $cal_webcal_url = preg_replace( '/^https?:\/\//', 'webcal://', $cal_https_url );
    $cal_google_url = 'https://calendar.google.com/calendar/r?cid=' . rawurlencode( $cal_webcal_url );
    $cal_qr_url     = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query( [ 'size' => '180x180', 'data' => $cal_webcal_url ] );
}
?>
<div class="ftt-onboarding-wrapper">

    <!-- ── Progress indicator ── -->
    <div class="ftt-onboarding-progress">
        <div class="ftt-progress-step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'done' : ''; ?>">
            <span class="ftt-step-num"><?php echo $step > 1 ? '✓' : '1'; ?></span>
            <span class="ftt-step-label"><?php esc_html_e( 'Calendar Setup', 'schedule-collaboration-tracking' ); ?></span>
        </div>
        <div class="ftt-progress-divider"></div>
        <div class="ftt-progress-step <?php echo $step >= 2 ? 'active' : ''; ?>">
            <span class="ftt-step-num">2</span>
            <span class="ftt-step-label"><?php esc_html_e( 'Billing', 'schedule-collaboration-tracking' ); ?></span>
        </div>
    </div>

    <?php if ( $step === 1 ) : ?>
    <!-- ══════════════ STEP 1 – CALENDAR SETUP ══════════════ -->
    <div class="ftt-onboarding-step" id="ftt-onboard-step-1">

        <div class="ftt-onboarding-icon">🎉</div>
        <h2><?php printf(
            /* translators: %s: first name */
            esc_html__( 'Welcome, %s! Your calendar is ready.', 'schedule-collaboration-tracking' ),
            esc_html( $first_name )
        ); ?></h2>
        <p class="ftt-onboarding-subtitle">
            <?php printf(
                /* translators: %s: group name */
                esc_html__( '"%s" has been created and your shared family calendar is ready to use right now — no extra setup needed.', 'schedule-collaboration-tracking' ),
                esc_html( $group_name )
            ); ?>
        </p>

        <!-- Primary CTA: go straight to the dashboard -->
        <div class="ftt-onboard-primary-cta">
            <?php if ( $ical_enabled && $cal_webcal_url ) : ?>
            <p class="ftt-onboard-cal-teaser">
                <a href="#" class="ftt-onboard-cal-modal-link" id="ftt-open-cal-modal">
                    📅 <?php esc_html_e( 'Connect calendar to your phone or device first', 'schedule-collaboration-tracking' ); ?>
                </a>
            </p>
            <?php endif; ?>
            <a href="<?php echo $step2_url; ?>" class="ftt-btn ftt-btn-primary ftt-btn-lg">
                <?php esc_html_e( 'Take me to my dashboard →', 'schedule-collaboration-tracking' ); ?>
            </a>
        </div>

        <!-- Optional: import an existing calendar -->
        <div class="ftt-onboard-optional-divider">
            <span><?php esc_html_e( 'Optional — you can always do this later', 'schedule-collaboration-tracking' ); ?></span>
        </div>

        <div class="ftt-onboarding-card ftt-onboard-optional-card">
            <h3><?php esc_html_e( 'Already have a calendar? Bring it with you.', 'schedule-collaboration-tracking' ); ?></h3>
            <p class="ftt-onboard-lead">
                <?php esc_html_e( 'If you use Google Calendar, Apple Calendar, Outlook, or any iCal-compatible app, you can overlay it here. Your personal events will appear alongside your shared family events in their own colour — nothing gets mixed up.', 'schedule-collaboration-tracking' ); ?>
            </p>

            <div class="ftt-onboard-feed-row">
                <input type="url"
                       class="ftt-onboard-feed-url regular-text"
                       placeholder="<?php esc_attr_e( 'Paste your calendar URL here (ends in .ics)', 'schedule-collaboration-tracking' ); ?>"
                       autocomplete="off" />
                <input type="text"
                       class="ftt-onboard-feed-label"
                       placeholder="<?php esc_attr_e( 'Label (e.g. My Google Calendar)', 'schedule-collaboration-tracking' ); ?>"
                       maxlength="40" />
            </div>

            <details class="ftt-onboard-how-to">
                <summary><?php esc_html_e( 'How do I find my iCal URL?', 'schedule-collaboration-tracking' ); ?></summary>
                <ul>
                    <li><strong><?php esc_html_e( 'Google Calendar:', 'schedule-collaboration-tracking' ); ?></strong> <?php esc_html_e( 'Settings → (calendar name) → Integrate calendar → "Secret address in iCal format"', 'schedule-collaboration-tracking' ); ?></li>
                    <li><strong><?php esc_html_e( 'Apple Calendar:', 'schedule-collaboration-tracking' ); ?></strong> <?php esc_html_e( 'Calendar app → Edit → Share Calendar → Public Calendar → copy URL', 'schedule-collaboration-tracking' ); ?></li>
                    <li><strong><?php esc_html_e( 'Outlook:', 'schedule-collaboration-tracking' ); ?></strong> <?php esc_html_e( 'Settings → View all Outlook settings → Calendar → Shared calendars → Publish → copy ICS link', 'schedule-collaboration-tracking' ); ?></li>
                </ul>
            </details>

            <div class="ftt-onboarding-actions">
                <button type="button" class="ftt-btn ftt-btn-primary" id="ftt-onboard-connect-cal">
                    <?php esc_html_e( 'Connect this calendar →', 'schedule-collaboration-tracking' ); ?>
                </button>
            </div>
            <div id="ftt-onboard-cal-msg" class="ftt-onboard-msg" style="display:none;"></div>
        </div>

    </div><!-- /step-1 -->

    <?php if ( $ical_enabled && $cal_webcal_url ) : ?>
    <!-- ══ Calendar Connect Modal ══ -->
    <div id="ftt-cal-connect-modal" class="ftt-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="ftt-cal-modal-title">
        <div class="ftt-modal-content ftt-cal-modal-content">
            <span class="ftt-modal-close" id="ftt-close-cal-modal" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Close', 'schedule-collaboration-tracking' ); ?>">&times;</span>

            <h2 id="ftt-cal-modal-title">📅 <?php esc_html_e( 'Add Calendar to Your Device', 'schedule-collaboration-tracking' ); ?></h2>
            <p><?php esc_html_e( 'Subscribe to your personal family calendar so your events always stay in sync — automatically.', 'schedule-collaboration-tracking' ); ?></p>

            <div class="ftt-cal-modal-buttons">
                <a href="<?php echo esc_url( $cal_webcal_url ); ?>" class="ftt-cal-btn ftt-cal-btn-apple">
                    <span class="ftt-cal-btn-icon">🍎</span> <?php esc_html_e( 'Apple Calendar', 'schedule-collaboration-tracking' ); ?>
                </a>
                <a href="<?php echo esc_url( $cal_google_url ); ?>" class="ftt-cal-btn ftt-cal-btn-google" target="_blank" rel="noopener noreferrer">
                    <span class="ftt-cal-btn-icon">📅</span> <?php esc_html_e( 'Google Calendar', 'schedule-collaboration-tracking' ); ?>
                </a>
                <button type="button" class="ftt-cal-btn ftt-cal-btn-outlook" id="ftt-cal-outlook-toggle">
                    <span class="ftt-cal-btn-icon">💼</span> <?php esc_html_e( 'Outlook', 'schedule-collaboration-tracking' ); ?>
                </button>
                <a href="<?php echo esc_url( $cal_webcal_url ); ?>" class="ftt-cal-btn ftt-cal-btn-other">
                    <span class="ftt-cal-btn-icon">📆</span> <?php esc_html_e( 'Other App', 'schedule-collaboration-tracking' ); ?>
                </a>
            </div>

            <!-- Outlook panel: shown when the Outlook button is clicked -->
            <div id="ftt-cal-outlook-panel" class="ftt-cal-outlook-panel" style="display:none;">
                <ol class="ftt-cal-outlook-steps">
                    <li><?php esc_html_e( 'Open Outlook — click the Calendar icon on the ribbon', 'schedule-collaboration-tracking' ); ?></li>
                    <li><?php esc_html_e( 'Click', 'schedule-collaboration-tracking' ); ?> <strong><?php esc_html_e( 'Add Calendar → From Internet', 'schedule-collaboration-tracking' ); ?></strong></li>
                    <li><?php esc_html_e( 'Copy the URL below, paste it in, and click', 'schedule-collaboration-tracking' ); ?> <strong><?php esc_html_e( 'Open', 'schedule-collaboration-tracking' ); ?></strong></li>
                </ol>
                <div class="ftt-cal-outlook-copy-row">
                    <input type="text" readonly id="ftt-cal-outlook-url"
                           value="<?php echo esc_attr( $cal_webcal_url ); ?>"
                           onclick="this.select();" />
                    <button type="button" class="ftt-btn ftt-btn-secondary ftt-cal-copy-btn" id="ftt-cal-copy-outlook-url"><?php esc_html_e( 'Copy', 'schedule-collaboration-tracking' ); ?></button>
                </div>
            </div>

            <div class="ftt-cal-modal-qr">
                <p class="ftt-cal-modal-qr-label"><?php esc_html_e( 'Or scan this QR code with your phone camera:', 'schedule-collaboration-tracking' ); ?></p>
                <img src="<?php echo esc_url( $cal_qr_url ); ?>"
                     alt="<?php esc_attr_e( 'Calendar QR code', 'schedule-collaboration-tracking' ); ?>"
                     width="180" height="180" class="ftt-cal-modal-qr-img" />
                <p class="ftt-cal-modal-qr-note"><?php esc_html_e( 'iOS: camera will prompt you to subscribe. Android: tap the link and choose Google Calendar.', 'schedule-collaboration-tracking' ); ?></p>
            </div>

            <div class="ftt-cal-modal-private-notice">
                🔒 <?php esc_html_e( 'Your calendar link is private and unique to you. Each family member gets their own link when they register — please don\'t share it.', 'schedule-collaboration-tracking' ); ?>
            </div>

            <div style="text-align:center;margin-top:20px;">
                <a href="<?php echo $step2_url; ?>" class="ftt-btn ftt-btn-primary">
                    <?php esc_html_e( 'Continue to dashboard →', 'schedule-collaboration-tracking' ); ?>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php elseif ( $step === 2 ) : ?>
    <!-- ══════════════ STEP 2 – BILLING OFFER ══════════════ -->
    <div class="ftt-onboarding-step" id="ftt-onboard-step-2">

        <div class="ftt-onboarding-icon">🎉</div>
        <h2><?php printf(
            /* translators: %d: number of trial days remaining */
            esc_html__( 'You have %d days of free access remaining!', 'schedule-collaboration-tracking' ),
            $trial_days_left
        ); ?></h2>
        <p class="ftt-onboarding-subtitle">
            <?php printf(
                /* translators: %s: trial end date */
                wp_kses(
                    __( 'Your trial runs until <strong>%s</strong>. You have full access to everything — no credit card required yet.', 'schedule-collaboration-tracking' ),
                    [ 'strong' => [] ]
                ),
                esc_html( $trial_end_label )
            ); ?>
        </p>

        <div class="ftt-onboarding-pricing-row">

            <div class="ftt-pricing-card">
                <div class="ftt-pricing-interval"><?php esc_html_e( 'Monthly', 'schedule-collaboration-tracking' ); ?></div>
                <div class="ftt-pricing-price">$9.99<span class="ftt-price-per">/month</span></div>
                <div class="ftt-pricing-note"><?php esc_html_e( '+ $5/mo per additional child', 'schedule-collaboration-tracking' ); ?></div>
                <button type="button" class="button button-secondary ftt-onboard-start-billing" data-interval="month">
                    <?php printf(
                        /* translators: %d: number of trial days remaining */
                        esc_html__( 'Set up billing — free for %d days', 'schedule-collaboration-tracking' ),
                        $trial_days_left
                    ); ?>
                </button>
            </div>

            <div class="ftt-pricing-card ftt-pricing-featured">
                <div class="ftt-pricing-featured-badge"><?php esc_html_e( 'Best Value', 'schedule-collaboration-tracking' ); ?></div>
                <div class="ftt-pricing-interval"><?php esc_html_e( 'Annual', 'schedule-collaboration-tracking' ); ?></div>
                <div class="ftt-pricing-price">$99<span class="ftt-price-per">/year</span></div>
                <div class="ftt-pricing-note"><?php esc_html_e( 'Save 17% — $50/yr per additional child', 'schedule-collaboration-tracking' ); ?></div>
                <button type="button" class="button button-primary ftt-onboard-start-billing" data-interval="year">
                    <?php printf(
                        /* translators: %d: number of trial days remaining */
                        esc_html__( 'Set up billing — free for %d days', 'schedule-collaboration-tracking' ),
                        $trial_days_left
                    ); ?>
                </button>
            </div>

        </div><!-- /pricing-row -->

        <div id="ftt-onboard-billing-msg" class="ftt-onboard-msg" style="display:none;"></div>

        <div class="ftt-onboard-skip-billing">
            <p class="ftt-onboard-reassurance">
                <?php esc_html_e( 'No charge during your trial. You can cancel any time.', 'schedule-collaboration-tracking' ); ?>
            </p>
            <a href="<?php echo $finish_url; ?>" class="ftt-onboard-skip-billing-link">
                <?php printf(
                    /* translators: %s: trial end date */
                    esc_html__( 'Remind me later — I\'ll decide before my trial ends on %s', 'schedule-collaboration-tracking' ),
                    $trial_end_label
                ); ?>
            </a>
        </div>

    </div><!-- /step-2 -->
    <?php endif; ?>

</div><!-- /.ftt-onboarding-wrapper -->

<script>
(function ($) {
    var restUrl  = <?php echo wp_json_encode( rest_url( 'ftt/v1/' ) ); ?>;
    var nonce    = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
    var step2Url = <?php echo wp_json_encode( add_query_arg( 'step', 2, home_url( '/ftt-onboarding/' ) ) ); ?>;
    var groupId  = <?php echo intval( $primary_group_id ?: 0 ); ?>;

    /* ── Calendar connect modal ── */
    var $calModal = $('#ftt-cal-connect-modal');
    $('#ftt-open-cal-modal').on('click', function (e) {
        e.preventDefault();
        $calModal.fadeIn(200);
    });
    $('#ftt-close-cal-modal').on('click keydown', function (e) {
        if (e.type === 'click' || e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $calModal.fadeOut(200);
        }
    });
    $calModal.on('click', function (e) {
        if (e.target === this) { $calModal.fadeOut(200); }
    });

    /* ── Outlook toggle panel ── */
    $('#ftt-cal-outlook-toggle').on('click', function () {
        var $panel = $('#ftt-cal-outlook-panel');
        var open   = $panel.is(':visible');
        $panel.slideToggle(180);
        $(this).toggleClass('ftt-cal-btn-outlook-active', !open);
    });

    /* ── Outlook URL copy button ── */
    $('#ftt-cal-copy-outlook-url').on('click', function () {
        var $input = $('#ftt-cal-outlook-url');
        $input.select();
        try {
            document.execCommand('copy');
            $(this).text(<?php echo wp_json_encode( __( 'Copied!', 'schedule-collaboration-tracking' ) ); ?>);
            var self = this;
            setTimeout(function () { $(self).text(<?php echo wp_json_encode( __( 'Copy', 'schedule-collaboration-tracking' ) ); ?>); }, 2000);
        } catch (err) {
            $input[0].select();
        }
    });

    /* ── Step 1: connect external calendar ── */
    $('#ftt-onboard-connect-cal').on('click', function () {
        var url   = $.trim($('.ftt-onboard-feed-url').val());
        var label = $.trim($('.ftt-onboard-feed-label').val()) || 'My Calendar';
        var $msg  = $('#ftt-onboard-cal-msg');
        var $btn  = $(this);

        if (!url) {
            showMsg($msg, 'error', <?php echo wp_json_encode( __( 'Please enter a calendar URL first.', 'schedule-collaboration-tracking' ) ); ?>);
            return;
        }
        if (!/^(https?|webcal):\/\//i.test(url)) {
            showMsg($msg, 'error', <?php echo wp_json_encode( __( 'URL must start with http://, https://, or webcal://', 'schedule-collaboration-tracking' ) ); ?>);
            return;
        }

        $btn.prop('disabled', true).text(<?php echo wp_json_encode( __( 'Connecting…', 'schedule-collaboration-tracking' ) ); ?>);

        $.ajax({
            url:         restUrl + 'external-calendars/save',
            method:      'POST',
            beforeSend:  function (xhr) { xhr.setRequestHeader('X-WP-Nonce', nonce); },
            contentType: 'application/json',
            data:        JSON.stringify({ feeds: [{ url: url, label: label, color: '#039BE5' }] }),
            success: function (res) {
                if (res.success) {
                    showMsg($msg, 'ok', <?php echo wp_json_encode( __( 'Calendar connected! Continuing…', 'schedule-collaboration-tracking' ) ); ?>);
                    setTimeout(function () { window.location.href = step2Url; }, 800);
                } else {
                    showMsg($msg, 'error', res.message || <?php echo wp_json_encode( __( 'Could not save the feed. Please check the URL and try again.', 'schedule-collaboration-tracking' ) ); ?>);
                    $btn.prop('disabled', false).text(<?php echo wp_json_encode( __( 'Connect this calendar →', 'schedule-collaboration-tracking' ) ); ?>);
                }
            },
            error: function () {
                showMsg($msg, 'error', <?php echo wp_json_encode( __( 'Request failed. Please try again.', 'schedule-collaboration-tracking' ) ); ?>);
                $btn.prop('disabled', false).text(<?php echo wp_json_encode( __( 'Connect this calendar →', 'schedule-collaboration-tracking' ) ); ?>);
            }
        });
    });

    /* ── Step 2: start Stripe billing ── */
    $('.ftt-onboard-start-billing').on('click', function () {
        var interval = $(this).data('interval');
        var $msg     = $('#ftt-onboard-billing-msg');

        if (!groupId) {
            showMsg($msg, 'error', <?php echo wp_json_encode( __( 'Could not find your group. Please contact support.', 'schedule-collaboration-tracking' ) ); ?>);
            return;
        }

        $(this).prop('disabled', true).text(<?php echo wp_json_encode( __( 'Redirecting to billing…', 'schedule-collaboration-tracking' ) ); ?>);

        $.ajax({
            url:         restUrl + 'groups/' + groupId + '/checkout',
            method:      'POST',
            beforeSend:  function (xhr) { xhr.setRequestHeader('X-WP-Nonce', nonce); },
            contentType: 'application/json',
            data:        JSON.stringify({ interval: interval }),
            success: function (res) {
                if (res.url) {
                    window.location.href = res.url;
                } else {
                    showMsg($msg, 'error', res.message || <?php echo wp_json_encode( __( 'Could not create checkout session. Please try again.', 'schedule-collaboration-tracking' ) ); ?>);
                    $('.ftt-onboard-start-billing').prop('disabled', false);
                }
            },
            error: function () {
                showMsg($msg, 'error', <?php echo wp_json_encode( __( 'Request failed. Please try again.', 'schedule-collaboration-tracking' ) ); ?>);
                $('.ftt-onboard-start-billing').prop('disabled', false);
            }
        });
    });

    function showMsg($el, type, text) {
        $el.removeClass('ftt-msg-ok ftt-msg-error')
           .addClass(type === 'ok' ? 'ftt-msg-ok' : 'ftt-msg-error')
           .text(text)
           .show();
    }

}(jQuery));
</script>
