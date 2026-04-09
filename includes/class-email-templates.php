<?php
/**
 * Email Template Manager & Policy / Communications Settings
 *
 * Stores editable subject + body templates for all transactional emails.
 * Policy page assignments and acceptance wording are saved in ftt_settings.
 *
 * @package Family_Travel_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FTT_Email_Templates {

    const OPTION_KEY   = 'ftt_email_templates';
    const NONCE_ACTION = 'ftt_policy_save';
    const NONCE_FIELD  = 'ftt_policy_nonce';
    const AJAX_NONCE   = 'ftt_email_templates';

    // -------------------------------------------------------------------------
    // Boot
    // -------------------------------------------------------------------------

    public static function init() {
        add_action( 'admin_post_ftt_save_policy_settings', [ __CLASS__, 'handle_save_policy' ] );
        add_action( 'wp_ajax_ftt_save_email_template',   [ __CLASS__, 'ajax_save_template'   ] );
        add_action( 'wp_ajax_ftt_reset_email_template',  [ __CLASS__, 'ajax_reset_template'  ] );
        add_action( 'wp_ajax_ftt_send_test_email',       [ __CLASS__, 'ajax_send_test_email' ] );
    }

    // -------------------------------------------------------------------------
    // Default template definitions
    // -------------------------------------------------------------------------

    public static function get_defaults() {
        return [
            'trial_start' => [
                'name'        => 'Trial Started',
                'description' => 'Sent when a new user\'s 14-day trial begins.',
                'subject'     => 'Welcome to Family Travel Tracker - Your 14-Day Trial Starts Now!',
                'body'        => "Hi {display_name},\n\nWelcome to Family Travel Tracker! Your 14-day free trial has started.\n\nYour first payment will be charged on: {trial_end_date}\nYou can cancel anytime before then with no charge.\n\nGet started: {dashboard_url}\n\nQuestions? Reply to this email anytime.\n\nThanks,\nThe Family Travel Tracker Team",
                'tokens'      => [ 'display_name', 'trial_end_date', 'dashboard_url' ],
                'type'        => 'plain',
            ],
            'first_payment' => [
                'name'        => 'First Payment Received',
                'description' => 'Sent when the first subscription payment is processed.',
                'subject'     => 'Payment Received - Thank You!',
                'body'        => "Hi {display_name},\n\nYour trial has ended and we've successfully charged your payment method.\n\nAmount: \${amount}\nInvoice: {invoice_url}\n\nThank you for being a Family Travel Tracker subscriber!",
                'tokens'      => [ 'display_name', 'amount', 'invoice_url' ],
                'type'        => 'plain',
            ],
            'payment_failed' => [
                'name'        => 'Payment Failed',
                'description' => 'Sent when a subscription payment fails.',
                'subject'     => 'Payment Failed - Action Required',
                'body'        => "Hi {display_name},\n\nWe were unable to process your payment for Family Travel Tracker.\n\nPlease update your payment method by {grace_end_date} to avoid service interruption.\n\nUpdate payment method: {manage_subscription_url}\n\nQuestions? Reply to this email.",
                'tokens'      => [ 'display_name', 'grace_end_date', 'manage_subscription_url' ],
                'type'        => 'plain',
            ],
            'subscription_canceled' => [
                'name'        => 'Subscription Canceled',
                'description' => 'Sent when a user cancels their subscription.',
                'subject'     => 'Subscription Canceled',
                'body'        => "Hi {display_name},\n\nYour subscription has been canceled.\n\nYou'll continue to have access until: {period_end_date}\n\nWe're sorry to see you go. If you have feedback, we'd love to hear it.",
                'tokens'      => [ 'display_name', 'period_end_date' ],
                'type'        => 'plain',
            ],
            'trial_reminder_7' => [
                'name'        => 'Trial Reminder — 7 Days',
                'description' => 'Sent 7 days before the trial expires.',
                'subject'     => 'Your trial ends in 7 days',
                'body'        => "Hi {display_name},\n\nJust a friendly reminder that your 14-day free trial ends in 7 days.\n\nCancel anytime: {manage_subscription_url}\n\nThanks for using Family Travel Tracker!",
                'tokens'      => [ 'display_name', 'manage_subscription_url' ],
                'type'        => 'plain',
            ],
            'trial_reminder_2' => [
                'name'        => 'Trial Reminder — 2 Days',
                'description' => 'Sent 2 days before the trial expires.',
                'subject'     => 'Your trial ends in 2 days',
                'body'        => "Hi {display_name},\n\nYour free trial ends in 2 days. Your first charge will be \${price}/{interval}.\n\nCancel anytime: {manage_subscription_url}\n\nThanks for using Family Travel Tracker!",
                'tokens'      => [ 'display_name', 'price', 'interval', 'manage_subscription_url' ],
                'type'        => 'plain',
            ],
            'access_suspended' => [
                'name'        => 'Access Suspended',
                'description' => 'Sent when an account is suspended after the grace period expires.',
                'subject'     => 'Access Suspended - Payment Required',
                'body'        => "Hi {display_name},\n\nYour Family Travel Tracker access has been suspended due to payment failure.\n\nTo restore access, please update your payment method:\n{manage_subscription_url}\n\nQuestions? Reply to this email.",
                'tokens'      => [ 'display_name', 'manage_subscription_url' ],
                'type'        => 'plain',
            ],
            'alert_confirmation' => [
                'name'        => 'Price Alert Confirmation',
                'description' => 'Sent when a user sets up a new price alert. Body is auto-generated HTML.',
                'subject'     => '[{site_name}] Price Alert Confirmation - {event_title}',
                'body'        => '',
                'tokens'      => [ 'site_name', 'event_title' ],
                'type'        => 'html_auto',
            ],
            'daily_digest' => [
                'name'        => 'Daily Price Digest',
                'description' => 'Sent daily with price updates for tracked flights. Body is auto-generated HTML.',
                'subject'     => 'Daily Flight Price Digest - {flight_count} flights tracked',
                'body'        => '',
                'tokens'      => [ 'flight_count' ],
                'type'        => 'html_auto',
            ],
            'parent_linked' => [
                'name'        => 'Parent Account Linked',
                'description' => 'Sent when a parent account is automatically linked to a child account.',
                'subject'     => '[{site_name}] Parent Account Linked',
                'body'        => "Your parent account has been automatically linked to {child_name}.\n\nYou can now view their events and receive price alerts.",
                'tokens'      => [ 'site_name', 'child_name' ],
                'type'        => 'plain',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Template retrieval & rendering
    // -------------------------------------------------------------------------

    /**
     * Get a single template, merging stored overrides onto the default.
     */
    public static function get_template( $key ) {
        $defaults = self::get_defaults();
        if ( ! isset( $defaults[ $key ] ) ) {
            return null;
        }
        $stored    = get_option( self::OPTION_KEY, [] );
        $overrides = $stored[ $key ] ?? [];

        return array_merge( $defaults[ $key ], $overrides );
    }

    /**
     * Returns true if the stored template differs from the default on this key.
     */
    public static function is_customized( $key ) {
        $stored = get_option( self::OPTION_KEY, [] );
        return ! empty( $stored[ $key ] );
    }

    /**
     * Replace {token} placeholders in a string.
     */
    private static function replace_tokens( $text, array $tokens ) {
        foreach ( $tokens as $k => $v ) {
            $text = str_replace( '{' . $k . '}', $v, $text );
        }
        return $text;
    }

    /**
     * Render the subject line for a given template with supplied tokens.
     */
    public static function render_subject( $key, array $tokens = [] ) {
        $tpl = self::get_template( $key );
        if ( ! $tpl ) { return ''; }
        return self::replace_tokens( $tpl['subject'], $tokens );
    }

    /**
     * Render the body for a given plain-text template with supplied tokens.
     * For html_auto templates this returns an empty string (body is computed separately).
     */
    public static function render_body( $key, array $tokens = [] ) {
        $tpl = self::get_template( $key );
        if ( ! $tpl || $tpl['type'] === 'html_auto' ) { return ''; }
        return self::replace_tokens( $tpl['body'], $tokens );
    }

    // -------------------------------------------------------------------------
    // Policy settings helpers
    // -------------------------------------------------------------------------

    private static function get_policy_settings() {
        $s = get_option( 'ftt_settings', [] );
        return [
            'privacy_page'       => $s['policy_privacy_page']      ?? 0,
            'terms_page'         => $s['policy_terms_page']         ?? 0,
            'cookie_page'        => $s['policy_cookie_page']        ?? 0,
            'sms_page'           => $s['policy_sms_page']           ?? 0,
            'acceptance_wording' => $s['policy_acceptance_wording'] ?? '',
            'test_address'       => $s['email_test_address']        ?? '',
        ];
    }

    /**
     * Return the URL for a policy page ID, or '' if not set.
     */
    public static function get_policy_url( $type ) {
        $s   = get_option( 'ftt_settings', [] );
        $key = 'policy_' . $type . '_page';
        $id  = absint( $s[ $key ] ?? 0 );
        return $id ? get_permalink( $id ) : '';
    }

    // -------------------------------------------------------------------------
    // admin-post.php save handler for policy settings
    // -------------------------------------------------------------------------

    public static function handle_save_policy() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized', 403 );
        }
        check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

        $raw      = isset( $_POST['ftt_policy'] ) ? (array) $_POST['ftt_policy'] : [];
        $settings = get_option( 'ftt_settings', [] );

        $settings['policy_privacy_page']      = absint( $raw['privacy_page']       ?? 0 );
        $settings['policy_terms_page']        = absint( $raw['terms_page']         ?? 0 );
        $settings['policy_cookie_page']       = absint( $raw['cookie_page']        ?? 0 );
        $settings['policy_sms_page']          = absint( $raw['sms_page']           ?? 0 );
        $settings['policy_acceptance_wording'] = wp_kses_post( wp_unslash( $raw['acceptance_wording'] ?? '' ) );
        $settings['email_test_address']       = sanitize_email( $raw['test_address'] ?? '' );

        update_option( 'ftt_settings', $settings );

        wp_redirect( add_query_arg( [
            'post_type'    => 'ftt_event',
            'page'         => 'ftt-settings',
            'tab'          => 'policy-comms',
            'policy-saved' => '1',
        ], admin_url( 'edit.php' ) ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // AJAX: save a single template
    // -------------------------------------------------------------------------

    public static function ajax_save_template() {
        check_ajax_referer( self::AJAX_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $key     = sanitize_key( $_POST['template_key'] ?? '' );
        $subject = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
        $body    = sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) );

        $defaults = self::get_defaults();
        if ( ! isset( $defaults[ $key ] ) ) {
            wp_send_json_error( 'Unknown template key.' );
        }

        $stored          = get_option( self::OPTION_KEY, [] );
        $stored[ $key ]  = [ 'subject' => $subject, 'body' => $body ];
        update_option( self::OPTION_KEY, $stored );

        wp_send_json_success( [ 'message' => 'Template saved.' ] );
    }

    // -------------------------------------------------------------------------
    // AJAX: reset a template to its default
    // -------------------------------------------------------------------------

    public static function ajax_reset_template() {
        check_ajax_referer( self::AJAX_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $key    = sanitize_key( $_POST['template_key'] ?? '' );
        $stored = get_option( self::OPTION_KEY, [] );
        unset( $stored[ $key ] );
        update_option( self::OPTION_KEY, $stored );

        $defaults = self::get_defaults();
        wp_send_json_success( [
            'message' => 'Reset to default.',
            'subject' => $defaults[ $key ]['subject'] ?? '',
            'body'    => $defaults[ $key ]['body']    ?? '',
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX: send a test email for a template
    // -------------------------------------------------------------------------

    public static function ajax_send_test_email() {
        check_ajax_referer( self::AJAX_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $key = sanitize_key( $_POST['template_key'] ?? '' );
        $tpl = self::get_template( $key );
        if ( ! $tpl ) {
            wp_send_json_error( 'Unknown template.' );
        }

        // Determine test recipient
        $settings = get_option( 'ftt_settings', [] );
        $to = sanitize_email( $settings['email_test_address'] ?? '' );
        if ( empty( $to ) || ! is_email( $to ) ) {
            $to = get_option( 'admin_email' );
        }

        // Demo tokens for every possible placeholder
        $demo = self::get_demo_tokens();

        $subject = self::replace_tokens( $tpl['subject'], $demo );
        $body    = $tpl['type'] === 'html_auto'
            ? '[This email has auto-generated HTML content. Subject-only preview sent.]'
            : self::replace_tokens( $tpl['body'], $demo );

        $subject = '[TEST] ' . $subject;
        $result  = wp_mail( $to, $subject, $body );

        if ( $result ) {
            wp_send_json_success( [ 'message' => 'Test email sent to ' . esc_html( $to ) . '.' ] );
        } else {
            wp_send_json_error( 'wp_mail() returned false. Check your server email configuration.' );
        }
    }

    /**
     * Demo token values used when sending test emails.
     */
    private static function get_demo_tokens() {
        return [
            'display_name'           => 'Jane Smith',
            'trial_end_date'         => date( 'F j, Y', strtotime( '+14 days' ) ),
            'dashboard_url'          => home_url( '/ftt-dashboard/' ),
            'manage_subscription_url'=> home_url( '/manage-subscription/' ),
            'price'                  => '9.99',
            'interval'               => 'month',
            'grace_end_date'         => date( 'F j, Y', strtotime( '+7 days' ) ),
            'period_end_date'        => date( 'F j, Y', strtotime( '+30 days' ) ),
            'amount'                 => '9.99',
            'invoice_url'            => home_url( '/' ),
            'site_name'              => get_bloginfo( 'name' ),
            'event_title'            => 'Family Vacation 2026',
            'flight_count'           => '3',
            'child_name'             => 'Emma Smith',
            'depart_airport'         => 'LAX',
            'arrive_airport'         => 'JFK',
            'depart_date'            => 'June 15, 2026',
            'alert_type'             => 'Price Drop',
        ];
    }

    // -------------------------------------------------------------------------
    // Admin UI
    // -------------------------------------------------------------------------

    public static function render_settings_page() {
        $policy   = self::get_policy_settings();
        $defaults = self::get_defaults();
        $saved_msg = isset( $_GET['policy-saved'] ) && $_GET['policy-saved'] === '1';
        $nonce    = wp_create_nonce( self::AJAX_NONCE );

        // Build page-list for dropdowns (publish + private pages)
        $pages = get_pages( [ 'post_status' => [ 'publish', 'private' ], 'sort_column' => 'post_title' ] );

        $page_options = '<option value="0">— None —</option>';
        foreach ( $pages as $p ) {
            $page_options .= sprintf(
                '<option value="%d">%s</option>',
                $p->ID,
                esc_html( $p->post_title )
            );
        }
        ?>
        <div class="wrap ftt-policy-comms-wrap" style="max-width:1000px;">

        <?php if ( $saved_msg ) : ?>
            <div class="notice notice-success is-dismissible" style="margin:16px 0 0;">
                <p><?php esc_html_e( 'Policy settings saved.', 'schedule-collaboration-tracking' ); ?></p>
            </div>
        <?php endif; ?>

        <!-- ================================================================ -->
        <!-- Section 1: Policy Pages & Acceptance                             -->
        <!-- ================================================================ -->
        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;margin-top:50px;box-shadow:0 1px 1px rgba(0,0,0,.04);">
            <div style="padding:12px 16px;border-bottom:1px solid #c3c4c7;">
                <h2 style="margin:0;padding:0;font-size:15px;font-weight:600;line-height:1.4;">
                    <?php esc_html_e( 'Policy Pages & Acceptance Wording', 'schedule-collaboration-tracking' ); ?>
                </h2>
            </div>
            <div style="padding:16px 24px;">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
                    <input type="hidden" name="action" value="ftt_save_policy_settings">

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Privacy Policy Page', 'schedule-collaboration-tracking' ); ?></th>
                            <td>
                                <select name="ftt_policy[privacy_page]" class="regular-text">
                                    <?php
                                    // phpcs:ignore WordPress.Security.EscapeOutput -- select options pre-escaped above
                                    echo self::_page_select_options( $pages, $policy['privacy_page'] );
                                    ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Shortcode: [ftt_privacy_url]', 'schedule-collaboration-tracking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Terms of Service Page', 'schedule-collaboration-tracking' ); ?></th>
                            <td>
                                <select name="ftt_policy[terms_page]" class="regular-text">
                                    <?php echo self::_page_select_options( $pages, $policy['terms_page'] ); // phpcs:ignore ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Shortcode: [ftt_terms_url]', 'schedule-collaboration-tracking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Cookie Policy Page', 'schedule-collaboration-tracking' ); ?></th>
                            <td>
                                <select name="ftt_policy[cookie_page]" class="regular-text">
                                    <?php echo self::_page_select_options( $pages, $policy['cookie_page'] ); // phpcs:ignore ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Shortcode: [ftt_cookie_url]', 'schedule-collaboration-tracking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'SMS Policy Page', 'schedule-collaboration-tracking' ); ?></th>
                            <td>
                                <select name="ftt_policy[sms_page]" class="regular-text">
                                    <?php echo self::_page_select_options( $pages, $policy['sms_page'] ); // phpcs:ignore ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Shortcode: [ftt_sms_url]', 'schedule-collaboration-tracking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Acceptance Wording', 'schedule-collaboration-tracking' ); ?></th>
                            <td>
                                <textarea name="ftt_policy[acceptance_wording]" rows="4" class="large-text"><?php echo esc_textarea( $policy['acceptance_wording'] ); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e( 'Displayed as the registration form agreement checkbox label. Use shortcodes to link policy pages. HTML is allowed.', 'schedule-collaboration-tracking' ); ?><br>
                                    <?php esc_html_e( 'Available shortcodes:', 'schedule-collaboration-tracking' ); ?>
                                    <code>[ftt_privacy_url]</code>
                                    <code>[ftt_terms_url]</code>
                                    <code>[ftt_cookie_url]</code>
                                    <code>[ftt_sms_url]</code><br>
                                    <?php esc_html_e( 'Example:', 'schedule-collaboration-tracking' ); ?>
                                    <code>I agree to the &lt;a href="[ftt_terms_url]"&gt;Terms of Service&lt;/a&gt; and &lt;a href="[ftt_privacy_url]"&gt;Privacy Policy&lt;/a&gt;.</code>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button( __( 'Save Policy Settings', 'schedule-collaboration-tracking' ), 'primary', 'submit', false ); ?>
                </form>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- Section 1b: Test Email Address                                    -->
        <!-- ================================================================ -->
        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;margin-top:16px;box-shadow:0 1px 1px rgba(0,0,0,.04);">
            <div style="padding:12px 16px;border-bottom:1px solid #c3c4c7;">
                <h2 style="margin:0;padding:0;font-size:15px;font-weight:600;line-height:1.4;">
                    <?php esc_html_e( 'Test Email Address', 'schedule-collaboration-tracking' ); ?>
                </h2>
            </div>
            <div style="padding:16px 24px;">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
                    <input type="hidden" name="action" value="ftt_save_policy_settings">
                    <input type="hidden" name="ftt_policy[privacy_page]" value="<?php echo esc_attr( $policy['privacy_page'] ); ?>">
                    <input type="hidden" name="ftt_policy[terms_page]" value="<?php echo esc_attr( $policy['terms_page'] ); ?>">
                    <input type="hidden" name="ftt_policy[cookie_page]" value="<?php echo esc_attr( $policy['cookie_page'] ); ?>">
                    <input type="hidden" name="ftt_policy[sms_page]" value="<?php echo esc_attr( $policy['sms_page'] ); ?>">
                    <input type="hidden" name="ftt_policy[acceptance_wording]" value="<?php echo esc_attr( $policy['acceptance_wording'] ); ?>">
                    <table class="form-table" role="presentation" style="margin-top:0;">
                        <tr>
                            <th scope="row" style="width:220px;"><?php esc_html_e( 'Test Recipient', 'schedule-collaboration-tracking' ); ?></th>
                            <td>
                                <input type="email" name="ftt_policy[test_address]" class="regular-text"
                                    value="<?php echo esc_attr( $policy['test_address'] ); ?>"
                                    placeholder="admin@example.com">
                                <p class="description"><?php esc_html_e( 'Where "Send Test" emails are delivered. Defaults to the site admin email if blank.', 'schedule-collaboration-tracking' ); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( __( 'Save Test Address', 'schedule-collaboration-tracking' ), 'secondary', 'submit', false ); ?>
                </form>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- Section 2: Email Templates                                        -->
        <!-- ================================================================ -->
        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;margin-top:16px;box-shadow:0 1px 1px rgba(0,0,0,.04);">
            <div style="padding:12px 16px;border-bottom:1px solid #c3c4c7;">
                <h2 style="margin:0;padding:0;font-size:15px;font-weight:600;line-height:1.4;">
                    <?php esc_html_e( 'Email Templates', 'schedule-collaboration-tracking' ); ?>
                </h2>
            </div>
            <div style="padding:0;">
                <p style="margin:12px 16px 0;color:#666;font-size:13px;">
                    <?php esc_html_e( 'Customize the subject and body of each transactional email. Use {token} placeholders where shown. Click "Reset" to restore the original text.', 'schedule-collaboration-tracking' ); ?>
                </p>

                <table class="wp-list-table widefat striped" style="margin-top:12px;">
                    <thead>
                        <tr>
                            <th style="width:22%;"><?php esc_html_e( 'Template', 'schedule-collaboration-tracking' ); ?></th>
                            <th style="width:30%;"><?php esc_html_e( 'Description', 'schedule-collaboration-tracking' ); ?></th>
                            <th><?php esc_html_e( 'Subject', 'schedule-collaboration-tracking' ); ?></th>
                            <th style="width:18%;text-align:right;"><?php esc_html_e( 'Actions', 'schedule-collaboration-tracking' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="ftt-template-table-body">
                    <?php foreach ( $defaults as $key => $def ) :
                        $tpl        = self::get_template( $key );
                        $customized = self::is_customized( $key );
                        $is_html    = ( $def['type'] === 'html_auto' );
                        $tokens_str = implode( ', ', array_map( fn($t) => '{' . $t . '}', $def['tokens'] ) );
                    ?>
                        <tr id="ftt-trow-<?php echo esc_attr( $key ); ?>">
                            <td>
                                <strong><?php echo esc_html( $def['name'] ); ?></strong>
                                <?php if ( $customized ) : ?>
                                    <span style="display:inline-block;background:#0073aa;color:#fff;font-size:10px;padding:1px 6px;border-radius:8px;margin-left:6px;">
                                        <?php esc_html_e( 'Customized', 'schedule-collaboration-tracking' ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="color:#555;font-size:13px;"><?php echo esc_html( $def['description'] ); ?></td>
                            <td style="font-size:12px;word-break:break-word;"><?php echo esc_html( $tpl['subject'] ); ?></td>
                            <td style="text-align:right;white-space:nowrap;">
                                <button type="button"
                                    class="button button-small ftt-et-edit"
                                    data-key="<?php echo esc_attr( $key ); ?>">
                                    <?php esc_html_e( 'Edit', 'schedule-collaboration-tracking' ); ?>
                                </button>
                                <button type="button"
                                    class="button button-small ftt-et-test"
                                    data-key="<?php echo esc_attr( $key ); ?>"
                                    style="margin-left:4px;">
                                    <?php esc_html_e( 'Send Test', 'schedule-collaboration-tracking' ); ?>
                                </button>
                                <?php if ( $customized ) : ?>
                                <button type="button"
                                    class="button button-small ftt-et-reset"
                                    data-key="<?php echo esc_attr( $key ); ?>"
                                    style="margin-left:4px;color:#b32d2e;border-color:#b32d2e;">
                                    <?php esc_html_e( 'Reset', 'schedule-collaboration-tracking' ); ?>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Inline edit row (hidden by default) -->
                        <tr id="ftt-tedit-<?php echo esc_attr( $key ); ?>" class="ftt-template-editor-row" style="display:none;background:#f9f9f9;">
                            <td colspan="4" style="padding:16px 20px;">
                                <div style="max-width:780px;">

                                    <label style="font-weight:600;display:block;margin-bottom:4px;">
                                        <?php esc_html_e( 'Subject', 'schedule-collaboration-tracking' ); ?>
                                    </label>
                                    <input type="text"
                                        id="ftt-subject-<?php echo esc_attr( $key ); ?>"
                                        class="large-text ftt-et-subject-input"
                                        value="<?php echo esc_attr( $tpl['subject'] ); ?>"
                                        style="margin-bottom:12px;">

                                    <?php if ( $is_html ) : ?>
                                        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:4px;padding:10px 14px;margin-bottom:12px;font-size:13px;color:#856404;">
                                            <?php esc_html_e( 'The body of this email is auto-generated HTML and cannot be edited here. Only the subject line is customizable.', 'schedule-collaboration-tracking' ); ?>
                                        </div>
                                    <?php else : ?>
                                        <label style="font-weight:600;display:block;margin-bottom:4px;">
                                            <?php esc_html_e( 'Body', 'schedule-collaboration-tracking' ); ?>
                                        </label>
                                        <textarea
                                            id="ftt-body-<?php echo esc_attr( $key ); ?>"
                                            class="large-text ftt-et-body-input"
                                            rows="8"
                                            style="font-family:monospace;font-size:13px;margin-bottom:8px;"><?php echo esc_textarea( $tpl['body'] ); ?></textarea>
                                    <?php endif; ?>

                                    <?php if ( ! empty( $def['tokens'] ) ) : ?>
                                        <p style="font-size:12px;color:#555;margin-bottom:12px;">
                                            <?php esc_html_e( 'Available tokens:', 'schedule-collaboration-tracking' ); ?>
                                            <code><?php echo esc_html( $tokens_str ); ?></code>
                                        </p>
                                    <?php endif; ?>

                                    <div>
                                        <button type="button"
                                            class="button button-primary ftt-et-save"
                                            data-key="<?php echo esc_attr( $key ); ?>"
                                            data-is-html="<?php echo $is_html ? '1' : '0'; ?>">
                                            <?php esc_html_e( 'Save Template', 'schedule-collaboration-tracking' ); ?>
                                        </button>
                                        <button type="button"
                                            class="button ftt-et-cancel"
                                            data-key="<?php echo esc_attr( $key ); ?>"
                                            style="margin-left:8px;">
                                            <?php esc_html_e( 'Cancel', 'schedule-collaboration-tracking' ); ?>
                                        </button>
                                        <span class="ftt-et-msg" id="ftt-msg-<?php echo esc_attr( $key ); ?>"
                                            style="margin-left:12px;font-size:13px;display:none;"></span>
                                    </div>

                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        </div><!-- .ftt-policy-comms-wrap -->

        <script>
        (function($) {
            var nonce = <?php echo wp_json_encode( $nonce ); ?>;

            // -----------------------------------------------------------------
            // Open / close edit rows
            // -----------------------------------------------------------------
            $(document).on('click', '.ftt-et-edit', function() {
                var key  = $(this).data('key');
                var $row = $('#ftt-tedit-' + key);

                // Close all other open editors
                $('.ftt-template-editor-row').not($row).hide();

                // Toggle this one
                $row.toggle();

                // Scroll into view if opening
                if ( $row.is(':visible') ) {
                    $row[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });

            $(document).on('click', '.ftt-et-cancel', function() {
                var key = $(this).data('key');
                $('#ftt-tedit-' + key).hide();
            });

            // -----------------------------------------------------------------
            // Save template
            // -----------------------------------------------------------------
            $(document).on('click', '.ftt-et-save', function() {
                var key    = $(this).data('key');
                var isHtml = $(this).data('is-html') === 1 || $(this).data('is-html') === '1';
                var subj   = $('#ftt-subject-' + key).val();
                var body   = isHtml ? '' : $('#ftt-body-' + key).val();
                var $msg   = $('#ftt-msg-' + key);
                var $btn   = $(this);

                $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Saving…', 'schedule-collaboration-tracking' ) ); ?>');
                $msg.hide();

                $.post(ajaxurl, {
                    action:        'ftt_save_email_template',
                    nonce:          nonce,
                    template_key:  key,
                    subject:       subj,
                    body:          body
                }, function(resp) {
                    $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Save Template', 'schedule-collaboration-tracking' ) ); ?>');
                    if (resp.success) {
                        $msg.css('color', '#007a32').text(resp.data.message).show();
                        // Update subject preview in list row
                        $('#ftt-trow-' + key + ' td:nth-child(3)').text(subj);
                        // Show "Customized" badge if not already there
                        if ( ! $('#ftt-trow-' + key + ' span.ftt-customized-badge').length ) {
                            $('#ftt-trow-' + key + ' td:first-child strong').after(
                                '<span class="ftt-customized-badge" style="display:inline-block;background:#0073aa;color:#fff;font-size:10px;padding:1px 6px;border-radius:8px;margin-left:6px;"><?php echo esc_js( __( 'Customized', 'schedule-collaboration-tracking' ) ); ?></span>'
                            );
                        }
                        // Add Reset button if not present
                        if ( ! $('#ftt-trow-' + key + ' .ftt-et-reset').length ) {
                            $('#ftt-trow-' + key + ' .ftt-et-test').after(
                                ' <button type="button" class="button button-small ftt-et-reset" data-key="' + key + '" style="margin-left:4px;color:#b32d2e;border-color:#b32d2e;"><?php echo esc_js( __( 'Reset', 'schedule-collaboration-tracking' ) ); ?></button>'
                            );
                        }
                        setTimeout(function(){ $msg.hide(); }, 3000);
                    } else {
                        $msg.css('color', '#b32d2e').text(resp.data || 'Error saving template.').show();
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Save Template', 'schedule-collaboration-tracking' ) ); ?>');
                    $msg.css('color', '#b32d2e').text('Request failed. Please try again.').show();
                });
            });

            // -----------------------------------------------------------------
            // Reset template
            // -----------------------------------------------------------------
            $(document).on('click', '.ftt-et-reset', function() {
                var key = $(this).data('key');
                if ( ! confirm('<?php echo esc_js( __( 'Reset this template to its default? Any customizations will be lost.', 'schedule-collaboration-tracking' ) ); ?>') ) {
                    return;
                }
                var $btn = $(this);
                $btn.prop('disabled', true);

                $.post(ajaxurl, {
                    action:       'ftt_reset_email_template',
                    nonce:         nonce,
                    template_key: key
                }, function(resp) {
                    $btn.prop('disabled', false);
                    if (resp.success) {
                        var d = resp.data;
                        // Restore inputs
                        $('#ftt-subject-' + key).val(d.subject);
                        $('#ftt-body-'    + key).val(d.body);
                        // Update list preview
                        $('#ftt-trow-' + key + ' td:nth-child(3)').text(d.subject);
                        // Remove Customized badge
                        $('#ftt-trow-' + key + ' .ftt-customized-badge').remove();
                        // Remove Reset button
                        $btn.remove();
                        // Show message in edit row
                        var $msg = $('#ftt-msg-' + key);
                        $msg.css('color', '#007a32').text(d.message).show();
                        setTimeout(function(){ $msg.hide(); }, 3000);
                    }
                });
            });

            // -----------------------------------------------------------------
            // Send test email
            // -----------------------------------------------------------------
            $(document).on('click', '.ftt-et-test', function() {
                var key  = $(this).data('key');
                var $btn = $(this);
                $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Sending…', 'schedule-collaboration-tracking' ) ); ?>');

                $.post(ajaxurl, {
                    action:       'ftt_send_test_email',
                    nonce:         nonce,
                    template_key: key
                }, function(resp) {
                    $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Send Test', 'schedule-collaboration-tracking' ) ); ?>');
                    if (resp.success) {
                        alert(resp.data.message);
                    } else {
                        alert('Error: ' + (resp.data || 'Could not send test email.'));
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Send Test', 'schedule-collaboration-tracking' ) ); ?>');
                    alert('Request failed. Please try again.');
                });
            });

        })(jQuery);
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // Internal helper: build <option> list for a page dropdown
    // -------------------------------------------------------------------------

    private static function _page_select_options( array $pages, $selected_id ) {
        $out = '<option value="0">' . esc_html__( '— None —', 'schedule-collaboration-tracking' ) . '</option>';
        foreach ( $pages as $p ) {
            $out .= sprintf(
                '<option value="%d"%s>%s</option>',
                $p->ID,
                selected( (int) $selected_id, $p->ID, false ),
                esc_html( $p->post_title )
            );
        }
        return $out;
    }
}

FTT_Email_Templates::init();
