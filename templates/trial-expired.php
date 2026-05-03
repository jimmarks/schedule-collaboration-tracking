<?php
/**
 * Template: Trial Expired — Billing Required
 *
 * Shown when a user's card-free trial has passed trial_ends_at with no Stripe subscription.
 * Displays pricing options and routes to Stripe Checkout.
 *
 * @package Family_Travel_Tracker
 */

if ( ! is_user_logged_in() ) {
    wp_safe_redirect( home_url( '/ftt-login/' ) );
    exit;
}

$user_id = get_current_user_id();
?>
<div class="ftt-trial-expired-wrapper">

    <div class="ftt-trial-expired-header" id="ftt-trial-header">
        <div class="ftt-trial-expired-icon">⏰</div>
        <h2><?php esc_html_e( 'Your free trial has ended', 'schedule-collaboration-tracking' ); ?></h2>
        <p id="ftt-trial-message">
            <?php esc_html_e( 'Your data is safe — nothing has been deleted.', 'schedule-collaboration-tracking' ); ?>
        </p>
        <p><?php esc_html_e( 'Choose a plan below to restore access instantly.', 'schedule-collaboration-tracking' ); ?></p>
    </div>

    <div class="ftt-trial-expired-pricing">

        <div class="ftt-pricing-card">
            <div class="ftt-pricing-interval"><?php esc_html_e( 'Monthly', 'schedule-collaboration-tracking' ); ?></div>
            <div class="ftt-pricing-price">$9.99<span class="ftt-price-per">/month</span></div>
            <ul class="ftt-pricing-features">
                <li><?php esc_html_e( 'Shared family calendar', 'schedule-collaboration-tracking' ); ?></li>
                <li><?php esc_html_e( '1 child included', 'schedule-collaboration-tracking' ); ?></li>
                <li><?php esc_html_e( '+ $5/mo per additional child', 'schedule-collaboration-tracking' ); ?></li>
                <li><?php esc_html_e( 'Cancel any time', 'schedule-collaboration-tracking' ); ?></li>
            </ul>
            <button type="button" class="button button-secondary ftt-expired-start-billing" data-interval="month">
                <?php esc_html_e( 'Continue — Monthly', 'schedule-collaboration-tracking' ); ?>
            </button>
        </div>

        <div class="ftt-pricing-card ftt-pricing-featured">
            <div class="ftt-pricing-featured-badge"><?php esc_html_e( 'Best Value', 'schedule-collaboration-tracking' ); ?></div>
            <div class="ftt-pricing-interval"><?php esc_html_e( 'Annual', 'schedule-collaboration-tracking' ); ?></div>
            <div class="ftt-pricing-price">$99<span class="ftt-price-per">/year</span></div>
            <ul class="ftt-pricing-features">
                <li><?php esc_html_e( 'Shared family calendar', 'schedule-collaboration-tracking' ); ?></li>
                <li><?php esc_html_e( '1 child included', 'schedule-collaboration-tracking' ); ?></li>
                <li><?php esc_html_e( '+ $50/yr per additional child', 'schedule-collaboration-tracking' ); ?></li>
                <li><?php esc_html_e( 'Save 17% vs monthly', 'schedule-collaboration-tracking' ); ?></li>
            </ul>
            <button type="button" class="button button-primary ftt-expired-start-billing" data-interval="year">
                <?php esc_html_e( 'Continue — Annual', 'schedule-collaboration-tracking' ); ?>
            </button>
        </div>

    </div><!-- /.ftt-trial-expired-pricing -->

    <div id="ftt-expired-billing-msg" class="ftt-onboard-msg" style="display:none;"></div>

    <p class="ftt-trial-expired-contact">
        <?php printf(
            wp_kses(
                /* translators: %s: support email address */
                __( 'Questions? <a href="mailto:%s">Contact us</a>.', 'schedule-collaboration-tracking' ),
                [ 'a' => [ 'href' => [] ] ]
            ),
            'info@familytraveltracker.app'
        ); ?>
    </p>

</div><!-- /.ftt-trial-expired-wrapper -->

<script>
(function ($) {
    var restUrl = <?php echo wp_json_encode( rest_url( 'ftt/v1/' ) ); ?>;
    var nonce   = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
    var groupId = null;

    // Load group data via REST API
    $.ajax({
        url: restUrl + 'groups',
        method: 'GET',
        beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', nonce); },
        success: function (res) {
            if (res.primary_group_id) {
                groupId = res.primary_group_id;
                
                // Find the primary group details
                var primaryGroup = res.groups.find(g => g.id === groupId);
                
                // Update message if we have trial end date
                if (primaryGroup && primaryGroup.billing && primaryGroup.billing.trial_end) {
                    var endDate = new Date(primaryGroup.billing.trial_end);
                    var dateStr = endDate.toLocaleDateString(undefined, { 
                        year: 'numeric', month: 'long', day: 'numeric' 
                    });
                    $('#ftt-trial-message').html(
                        <?php
                        /* translators: %s: trial end date (filled by JS) */
                        echo wp_json_encode( __( 'Your trial ended on <strong>{{DATE}}</strong>. Your data is safe and waiting — nothing has been deleted.', 'schedule-collaboration-tracking' ) );
                        ?>.replace('{{DATE}}', dateStr)
                    );
                }
            }
        },
        error: function () {
            console.error('Failed to load group data');
        }
    });

    $('.ftt-expired-start-billing').on('click', function () {
        var interval = $(this).data('interval');
        var $msg     = $('#ftt-expired-billing-msg');

        if (!groupId) {
            $msg.removeClass('ftt-msg-ok').addClass('ftt-msg-error')
                .text(<?php echo wp_json_encode( __( 'Could not find your group. Please contact support.', 'schedule-collaboration-tracking' ) ); ?>)
                .show();
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
                    $msg.removeClass('ftt-msg-ok').addClass('ftt-msg-error')
                        .text(res.message || <?php echo wp_json_encode( __( 'Could not create billing session. Please try again.', 'schedule-collaboration-tracking' ) ); ?>)
                        .show();
                    $('.ftt-expired-start-billing').prop('disabled', false);
                }
            },
            error: function () {
                $msg.removeClass('ftt-msg-ok').addClass('ftt-msg-error')
                    .text(<?php echo wp_json_encode( __( 'Request failed. Please try again.', 'schedule-collaboration-tracking' ) ); ?>)
                    .show();
                $('.ftt-expired-start-billing').prop('disabled', false);
            }
        });
    });

}(jQuery));
</script>
