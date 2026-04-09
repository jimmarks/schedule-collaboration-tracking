<?php
/**
 * API Usage Tracker
 *
 * Records daily call counts for SerpAPI and Google Places API.
 * Data lives in wp_options (autoload = false), one row per calendar day.
 * Entries older than RETENTION_DAYS are pruned automatically.
 *
 * @package Family_Travel_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FTT_API_Tracker {

    const OPTION_PREFIX  = 'ftt_api_day_';
    const RETENTION_DAYS = 90;

    /**
     * Register hooks — piggybacks on the existing flight-price cron for cleanup.
     */
    public static function init() {
        add_action( 'ftt_check_flight_prices', array( __CLASS__, 'maybe_cleanup' ) );
    }

    // -------------------------------------------------------------------------
    // Recording
    // -------------------------------------------------------------------------

    /**
     * Record one API call.
     *
     * @param string $api     API identifier: 'serpapi' or 'google_places'.
     * @param bool   $success Whether the call returned a usable result.
     */
    public static function record( $api, $success = true ) {
        $key  = self::OPTION_PREFIX . gmdate( 'Y-m-d' );
        $data = get_option( $key, array() );

        if ( ! isset( $data[ $api ] ) ) {
            $data[ $api ] = array( 'calls' => 0, 'success' => 0, 'errors' => 0 );
        }

        $data[ $api ]['calls']++;
        if ( $success ) {
            $data[ $api ]['success']++;
        } else {
            $data[ $api ]['errors']++;
        }

        update_option( $key, $data, false ); // false = do not autoload
    }

    // -------------------------------------------------------------------------
    // Retrieval
    // -------------------------------------------------------------------------

    /**
     * Return raw daily data for the past $days calendar days (including today).
     *
     * @param  int   $days Number of days to return.
     * @return array Keyed by 'YYYY-MM-DD', value is per-API associative array.
     */
    public static function get_stats( $days = 7 ) {
        $stats = array();
        for ( $i = $days - 1; $i >= 0; $i-- ) {
            $date          = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
            $stats[ $date ] = get_option( self::OPTION_PREFIX . $date, array() );
        }
        return $stats;
    }

    /**
     * Return aggregate summary for display.
     *
     * Keys per API: today, yesterday, last_7, last_30, errors_today, errors_7.
     *
     * @return array Keyed by api name.
     */
    public static function get_summary() {
        $apis      = array( 'serpapi', 'google_places' );
        $today     = gmdate( 'Y-m-d' );
        $yesterday = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
        $week_ago  = strtotime( '-6 days' ); // last 7 days inclusive of today

        $summary = array();
        foreach ( $apis as $api ) {
            $summary[ $api ] = array(
                'today'       => 0,
                'yesterday'   => 0,
                'last_7'      => 0,
                'last_30'     => 0,
                'errors_today' => 0,
                'errors_7'    => 0,
            );
        }

        $stats_30 = self::get_stats( 30 );
        foreach ( $stats_30 as $date => $day_data ) {
            foreach ( $apis as $api ) {
                if ( empty( $day_data[ $api ] ) ) {
                    continue;
                }
                $calls  = (int) ( $day_data[ $api ]['calls']  ?? 0 );
                $errors = (int) ( $day_data[ $api ]['errors'] ?? 0 );

                $summary[ $api ]['last_30'] += $calls;

                if ( strtotime( $date ) >= $week_ago ) {
                    $summary[ $api ]['last_7']   += $calls;
                    $summary[ $api ]['errors_7'] += $errors;
                }
                if ( $date === $today ) {
                    $summary[ $api ]['today']        += $calls;
                    $summary[ $api ]['errors_today'] += $errors;
                }
                if ( $date === $yesterday ) {
                    $summary[ $api ]['yesterday'] += $calls;
                }
            }
        }

        return $summary;
    }

    // -------------------------------------------------------------------------
    // Maintenance
    // -------------------------------------------------------------------------

    /**
     * Delete wp_options rows older than RETENTION_DAYS.
     * Called on the flight-price cron hook; self-throttles to once per day.
     */
    public static function maybe_cleanup() {
        $today        = gmdate( 'Y-m-d' );
        $last_cleanup = get_option( 'ftt_api_tracker_last_cleanup', '' );
        if ( $last_cleanup === $today ) {
            return;
        }
        update_option( 'ftt_api_tracker_last_cleanup', $today, false );

        global $wpdb;
        $cutoff = gmdate( 'Y-m-d', strtotime( '-' . self::RETENTION_DAYS . ' days' ) );
        // Both values are constructed from gmdate(), never user input — safe to use
        // in a LIKE + less-than comparison.
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE %s
               AND option_name < %s",
            $wpdb->esc_like( self::OPTION_PREFIX ) . '%',
            self::OPTION_PREFIX . $cutoff
        ) );
    }
}
