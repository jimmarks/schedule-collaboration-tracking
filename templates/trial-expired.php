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

$user_id          = get_current_user_id();
$primary_group_id = get_user_meta( $user_id, 'ftt_primary_group', true );
$group            = $primary_group_id && class_exists( 'FTT_Family_Groups' )
                    ? FTT_Family_Groups::get_group( $primary_group_id )
                    : null;
$trial_end_date   = $group ? $group->trial_ends_at : null;
$end_display      = $trial_end_date
                    ? date_i18n( get_option( 'date_format' ), strtotime( $trial_end_date ) )
                    : '';
?>
<div class="ftt-trial-expired-wrapper">

    <div class="ftt-trial-expired-header">
        <div class="ftt-trial-expired-icon">⏰</div>
        <h2><?php esc_html_e( 'Your free trial has ended', 'schedule-collaboration-tracking' ); ?></h2>
        <p>
            <?php if ( $end_display ) :
                printf(
                    /* translators: %s: trial end date */
                    esc_html__( 'Your trial ended on %s. Your data is safe and waiting — nothing has been deleted.', 'schedule-collaboration-tracking' ),
                    '<strong>' . esc_html( $end_display ) . '</strong>'
                );
            else :
                esc_html_e( 'Your trial has ended. Your data is safe — nothing has been deleted.', 'schedule-collaboration-tracking' );
            endif; ?>
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
    var groupId = <?php echo intval( $primary_group_id ?: 0 ); ?>;

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
