<?php
/**
 * Newsletter Sync
 *
 * Syncs FTT members to Newsletter plugin lists based on their
 * Stripe billing status. Runs daily via WP-Cron.
 *
 * @package Family_Travel_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FTT_Newsletter_Sync {

    /** Option key for last-sync metadata */
    const LAST_SYNC_OPTION = 'ftt_newsletter_sync_last_run';

    /**
     * Billing statuses tracked for list mapping.
     */
    public static function get_billing_statuses() {
        return [
            'active'   => __( 'Active', 'schedule-collaboration-tracking' ),
            'trialing' => __( 'Trialing', 'schedule-collaboration-tracking' ),
            'past_due' => __( 'Past Due', 'schedule-collaboration-tracking' ),
            'canceled' => __( 'Canceled', 'schedule-collaboration-tracking' ),
            'none'     => __( 'No Subscription', 'schedule-collaboration-tracking' ),
        ];
    }

    /**
     * Register hooks.
     */
    public static function init() {
        add_action( 'ftt_newsletter_sync', [ __CLASS__, 'run_sync' ] );
        add_action( 'plugins_loaded', [ __CLASS__, 'maybe_schedule_cron' ] );
        add_action( 'admin_post_ftt_newsletter_sync_now', [ __CLASS__, 'handle_sync_now' ] );
    }

    /**
     * Schedule or clear the daily cron based on the enabled setting.
     */
    public static function maybe_schedule_cron() {
        $settings = get_option( 'ftt_settings', [] );
        $enabled  = ! empty( $settings['newsletter_sync_enabled'] );

        if ( $enabled ) {
            if ( ! wp_next_scheduled( 'ftt_newsletter_sync' ) ) {
                wp_schedule_event( time(), 'daily', 'ftt_newsletter_sync' );
            }
        } else {
            wp_clear_scheduled_hook( 'ftt_newsletter_sync' );
        }
    }

    /**
     * Handle the "Sync Now" admin POST action.
     */
    public static function handle_sync_now() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'schedule-collaboration-tracking' ), 403 );
        }
        // Nonce arrives via GET (_wpnonce) from wp_nonce_url().
        check_admin_referer( 'ftt_newsletter_sync_now' );

        // Force=true: bypass the "Enable Daily Sync" checkbox for manual runs.
        $result = self::run_sync( true );

        $redirect = add_query_arg( [
            'post_type'        => 'ftt_event',
            'page'             => 'ftt-settings',
            'tab'              => 'newsletter',
            'ftt_nl_sync_done' => 1,
            'ftt_nl_synced'    => $result['synced'],
            'ftt_nl_skipped'   => $result['skipped'],
            'ftt_nl_created'   => $result['created'],
            'ftt_nl_note'      => rawurlencode( $result['note'] ?? '' ),
        ], admin_url( 'edit.php' ) );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Run the sync: iterate all FTT users, apply list mapping.
     *
     * @param bool $force If true, runs even when the "Enable Daily Sync" setting is off.
     * @return array { synced: int, skipped: int, created: int, note: string }
     */
    public static function run_sync( $force = false ) {
        $result = [ 'synced' => 0, 'skipped' => 0, 'created' => 0, 'note' => '' ];

        if ( ! class_exists( 'Newsletter' ) ) {
            $result['note'] = 'Newsletter plugin not active or not found.';
            return $result;
        }

        $settings = get_option( 'ftt_settings', [] );

        // Automated cron respects the enabled setting; manual "Sync Now" bypasses it.
        if ( ! $force && empty( $settings['newsletter_sync_enabled'] ) ) {
            $result['note'] = 'Daily sync is disabled.';
            return $result;
        }

        // Build status → list_id mapping (only entries with a list selected).
        $mapping = [];
        foreach ( array_keys( self::get_billing_statuses() ) as $status ) {
            $list_id = isset( $settings[ 'newsletter_list_' . $status ] )
                ? absint( $settings[ 'newsletter_list_' . $status ] )
                : 0;
            if ( $list_id > 0 ) {
                $mapping[ $status ] = $list_id;
            }
        }

        if ( empty( $mapping ) ) {
            $result['note'] = 'No lists mapped — open Settings → Newsletter and choose a list for each billing status.';
            update_option( self::LAST_SYNC_OPTION, array_merge( $result, [ 'time' => current_time( 'mysql' ) ] ) );
            return $result;
        }

        // All mapped list IDs (for removing users from lists they no longer belong to).
        $all_mapped_ids = array_values( $mapping );

        global $wpdb;
        $groups_table = $wpdb->prefix . 'ftt_family_groups';

        // Pull every non-archived group's billing owner + subscription status.
        // Billing owners are the primary account holders; they live in the groups
        // table (billing_owner column), not in ftt_family_group_members.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT billing_owner, subscription_status
             FROM {$groups_table}
             WHERE is_archived = 0 AND billing_owner > 0"
        );

        // Build user_id → best status (most favorable wins when a user owns multiple groups).
        $priority   = [ 'active' => 4, 'trialing' => 3, 'past_due' => 2, 'canceled' => 1 ];
        $user_status = [];
        foreach ( $rows as $row ) {
            $uid = (int) $row->billing_owner;
            $st  = $row->subscription_status ?: 'none';
            if ( ! isset( $user_status[ $uid ] ) ) {
                $user_status[ $uid ] = $st;
            } else {
                if ( ( $priority[ $st ] ?? 0 ) > ( $priority[ $user_status[ $uid ] ] ?? 0 ) ) {
                    $user_status[ $uid ] = $st;
                }
            }
        }

        $newsletter = Newsletter::instance();

        foreach ( $user_status as $user_id => $status ) {
            $wp_user = get_userdata( $user_id );
            if ( ! $wp_user ) {
                $result['skipped']++;
                continue;
            }

            // If no list is mapped for this status, leave the user's lists untouched.
            if ( ! isset( $mapping[ $status ] ) ) {
                $result['skipped']++;
                continue;
            }

            // Find or create the Newsletter subscriber.
            $nl_user = $newsletter->get_user_by_wp_user_id( $wp_user->ID );

            if ( ! $nl_user ) {
                // Fall back to email lookup (handles subscribers who pre-existed).
                $nl_user = $newsletter->get_user( $wp_user->user_email );
            }

            if ( ! $nl_user ) {
                // Create subscriber as confirmed (no opt-in email) — admin-driven sync.
                $new = $newsletter->save_user( [
                    'email'      => $wp_user->user_email,
                    'name'       => $wp_user->display_name,
                    'status'     => 'C',
                    'wp_user_id' => $wp_user->ID,
                ] );
                if ( $new ) {
                    $nl_user = $new;
                    $result['created']++;
                }
            }

            if ( ! $nl_user ) {
                $result['skipped']++;
                continue;
            }

            $target_list_id = $mapping[ $status ];

            // Add to the correct list.
            $newsletter->set_user_list( $nl_user, $target_list_id, 1 );

            // Remove from every other mapped list so memberships stay exclusive.
            foreach ( $all_mapped_ids as $other_id ) {
                if ( $other_id !== $target_list_id ) {
                    $newsletter->set_user_list( $nl_user, $other_id, 0 );
                }
            }

            $result['synced']++;
        }

        update_option( self::LAST_SYNC_OPTION, array_merge(
            $result,
            [ 'time' => current_time( 'mysql' ) ]
        ) );

        return $result;
    }

    /**
     * Register settings section and fields (called from FTT_Settings::register_settings).
     */
    public static function register_settings_fields() {
        add_settings_section(
            'ftt_newsletter_section',
            __( 'Newsletter List Sync', 'schedule-collaboration-tracking' ),
            [ __CLASS__, 'render_section_intro' ],
            'ftt-settings-newsletter'
        );

        add_settings_field(
            'newsletter_sync_enabled',
            __( 'Enable Daily Sync', 'schedule-collaboration-tracking' ),
            [ __CLASS__, 'render_enabled_field' ],
            'ftt-settings-newsletter',
            'ftt_newsletter_section'
        );

        foreach ( self::get_billing_statuses() as $status => $label ) {
            add_settings_field(
                'newsletter_list_' . $status,
                /* translators: %s: billing status label */
                sprintf( __( '%s → List', 'schedule-collaboration-tracking' ), $label ),
                [ __CLASS__, 'render_list_field' ],
                'ftt-settings-newsletter',
                'ftt_newsletter_section',
                [ 'status' => $status ]
            );
        }
    }

    /**
     * Section intro text.
     */
    public static function render_section_intro() {
        if ( ! class_exists( 'Newsletter' ) ) {
            echo '<div class="notice notice-warning inline"><p>';
            echo esc_html__( 'The Newsletter plugin is not active. Install and activate it to enable list sync.', 'schedule-collaboration-tracking' );
            echo '</p></div>';
            return;
        }

        echo '<p>';
        esc_html_e(
            'Map each Stripe billing status to a Newsletter list. The cron runs daily and keeps membership exclusive — a user is added to exactly one mapped list and removed from the others.',
            'schedule-collaboration-tracking'
        );
        echo '</p>';
        echo '<p>';
        esc_html_e(
            'Note: Users are added as confirmed subscribers (no opt-in email). Ensure you have the appropriate legal basis to send emails to these subscribers.',
            'schedule-collaboration-tracking'
        );
        echo '</p>';

        self::render_sync_status();
    }

    /**
     * Render last-sync status + "Sync Now" button.
     */
    private static function render_sync_status() {
        $last = get_option( self::LAST_SYNC_OPTION );

        // Build a GET-based nonce URL so the link works even when embedded inside
        // the outer settings <form> (nested <form> elements are invalid HTML and
        // browsers would submit the outer form instead).
        $sync_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=ftt_newsletter_sync_now' ),
            'ftt_newsletter_sync_now'
        );
        ?>
        <div style="background:#f6f7f7;border:1px solid #e2e4e7;border-radius:6px;padding:14px 18px;margin:14px 0;">
            <strong><?php esc_html_e( 'Last Sync', 'schedule-collaboration-tracking' ); ?>:</strong>
            <?php if ( $last && ! empty( $last['time'] ) ) : ?>
                <?php echo esc_html( $last['time'] ); ?> &mdash;
                <?php
                printf(
                    /* translators: 1: synced, 2: created, 3: skipped */
                    esc_html__( '%1$d synced, %2$d created, %3$d skipped', 'schedule-collaboration-tracking' ),
                    (int) $last['synced'],
                    (int) $last['created'],
                    (int) $last['skipped']
                );
                if ( ! empty( $last['note'] ) ) {
                    echo ' &mdash; ' . esc_html( $last['note'] );
                }
                ?>
            <?php else : ?>
                <?php esc_html_e( 'Never', 'schedule-collaboration-tracking' ); ?>
            <?php endif; ?>
            &nbsp;
            <a href="<?php echo esc_url( $sync_url ); ?>" class="button button-secondary">
                <?php esc_html_e( 'Sync Now', 'schedule-collaboration-tracking' ); ?>
            </a>

            <?php
            // Show result from a just-completed manual sync.
            // phpcs:ignore WordPress.Security.NonceVerification
            if ( ! empty( $_GET['ftt_nl_sync_done'] ) ) {
                $note = ! empty( $_GET['ftt_nl_note'] ) ? rawurldecode( sanitize_text_field( wp_unslash( $_GET['ftt_nl_note'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
                if ( $note ) {
                    printf(
                        '<p style="color:#b32d2e;margin:6px 0 0;">%s</p>',
                        esc_html( $note )
                    );
                } else {
                    printf(
                        '<p style="color:green;margin:6px 0 0;"><strong>%1$d</strong> ' .
                        esc_html__( 'synced, %2$d created, %3$d skipped.', 'schedule-collaboration-tracking' ) . '</p>',
                        (int) $_GET['ftt_nl_synced'],  // phpcs:ignore WordPress.Security.NonceVerification
                        (int) $_GET['ftt_nl_created'], // phpcs:ignore WordPress.Security.NonceVerification
                        (int) $_GET['ftt_nl_skipped']  // phpcs:ignore WordPress.Security.NonceVerification
                    );
                }
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render the "Enable Daily Sync" checkbox.
     */
    public static function render_enabled_field() {
        $settings = get_option( 'ftt_settings', [] );
        $checked  = ! empty( $settings['newsletter_sync_enabled'] );
        ?>
        <label>
            <input type="checkbox"
                   name="ftt_settings[newsletter_sync_enabled]"
                   value="1"
                   <?php checked( $checked ); ?>>
            <?php esc_html_e( 'Run list sync automatically every day', 'schedule-collaboration-tracking' ); ?>
        </label>
        <?php
    }

    /**
     * Render a list-selection dropdown for a given billing status.
     *
     * @param array $args ['status' => string]
     */
    public static function render_list_field( $args ) {
        $status   = $args['status'];
        $settings = get_option( 'ftt_settings', [] );
        $selected = isset( $settings[ 'newsletter_list_' . $status ] )
            ? (int) $settings[ 'newsletter_list_' . $status ]
            : 0;

        if ( ! class_exists( 'Newsletter' ) ) {
            echo '<em>' . esc_html__( 'Newsletter plugin not active.', 'schedule-collaboration-tracking' ) . '</em>';
            return;
        }

        $lists = Newsletter::instance()->get_lists();
        ?>
        <select name="<?php echo esc_attr( 'ftt_settings[newsletter_list_' . $status . ']' ); ?>">
            <option value="0"><?php esc_html_e( '— No list (skip this status) —', 'schedule-collaboration-tracking' ); ?></option>
            <?php foreach ( $lists as $list ) : ?>
                <?php if ( empty( $list->name ) ) continue; ?>
                <option value="<?php echo esc_attr( $list->id ); ?>"
                    <?php selected( $selected, (int) $list->id ); ?>>
                    <?php echo esc_html( $list->name ); ?> (ID: <?php echo (int) $list->id; ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Sanitize newsletter-related settings keys.
     * Called from FTT_Settings::sanitize_settings().
     *
     * @param array $input   Raw POST input.
     * @param array $current Current merged-settings array (modified in place).
     * @return array Updated $current.
     */
    public static function sanitize_settings_fields( $input, $current ) {
        // Enable toggle (absent = unchecked).
        $current['newsletter_sync_enabled'] = ! empty( $input['newsletter_sync_enabled'] );

        // List ID mapping.
        foreach ( array_keys( self::get_billing_statuses() ) as $status ) {
            $key = 'newsletter_list_' . $status;
            if ( isset( $input[ $key ] ) ) {
                $current[ $key ] = absint( $input[ $key ] );
            }
        }

        // Reschedule / clear cron immediately after save.
        if ( $current['newsletter_sync_enabled'] ) {
            if ( ! wp_next_scheduled( 'ftt_newsletter_sync' ) ) {
                wp_schedule_event( time(), 'daily', 'ftt_newsletter_sync' );
            }
        } else {
            wp_clear_scheduled_hook( 'ftt_newsletter_sync' );
        }

        return $current;
    }
}
