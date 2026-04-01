<?php
/**
 * External Calendar Feed Overlay
 *
 * Lets users paste iCal feed URLs (Google, Apple, OurFamilyWizard, etc.)
 * into their profile.  URLs are stored server-side and fetched via the WP
 * HTTP API.  Results are cached in WP transients (30 min TTL) and served
 * to FullCalendar as a second event source.
 *
 * User-meta key:
 *   ftt_external_calendars – PHP array of up to MAX_FEEDS entries:
 *     [ ['url'=>'…', 'label'=>'…', 'color'=>'#hex'], … ]
 *
 * REST endpoints:
 *   GET  /ftt/v1/external-events          – returns all user feeds merged as FC events
 *   POST /ftt/v1/external-calendars/save  – saves feed list
 *   POST /ftt/v1/external-calendars/refresh – clears transients, re-fetches
 *
 * @package Family_Travel_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FTT_External_Calendars {

	const MAX_FEEDS     = 5;
	const CACHE_TTL     = 1800; // seconds
	const FETCH_TIMEOUT = 12;   // seconds

	// Allowed colour palette for feed events
	const ALLOWED_COLORS = array(
		'#7986CB', // indigo
		'#33B679', // sage
		'#8E24AA', // grape
		'#E67C73', // flamingo
		'#F6BF26', // banana
		'#F4511E', // tangerine
		'#039BE5', // peacock
		'#616161', // graphite
		'#3F9142', // basil
		'#D50000', // tomato
	);

	/* ------------------------------------------------------------------ */
	/* Bootstrap                                                           */
	/* ------------------------------------------------------------------ */

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
	}

	/* ------------------------------------------------------------------ */
	/* REST Routes                                                         */
	/* ------------------------------------------------------------------ */

	public static function register_rest_routes() {
		// Fetch events for FullCalendar
		register_rest_route( 'ftt/v1', '/external-events', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'rest_get_events' ),
			'permission_callback' => function() { return is_user_logged_in(); },
		) );

		// Save feed list
		register_rest_route( 'ftt/v1', '/external-calendars/save', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'rest_save_feeds' ),
			'permission_callback' => function() { return is_user_logged_in(); },
		) );

		// Force refresh (clears cache)
		register_rest_route( 'ftt/v1', '/external-calendars/refresh', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'rest_refresh_feeds' ),
			'permission_callback' => function() { return is_user_logged_in(); },
		) );
	}

	/* ------------------------------------------------------------------ */
	/* REST Handlers                                                       */
	/* ------------------------------------------------------------------ */

	/**
	 * Return all external feed events merged into a FullCalendar-compatible
	 * JSON array.  FullCalendar calls this with ?start=…&end=… params; we
	 * ignore them for simplicity since iCal feeds export the whole calendar.
	 */
	public static function rest_get_events( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$feeds   = self::get_feeds( $user_id );

		if ( empty( $feeds ) ) {
			return rest_ensure_response( array() );
		}

		$all_events = array();
		foreach ( $feeds as $index => $feed ) {
			$events = self::get_cached_events( $user_id, $index, $feed );
			$all_events = array_merge( $all_events, $events );
		}

		return rest_ensure_response( $all_events );
	}

	/**
	 * Save the user's feed list.
	 * Expects JSON body: { "feeds": [ {url, label, color}, … ] }
	 */
	public static function rest_save_feeds( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$params  = $request->get_json_params();

		$raw_feeds = isset( $params['feeds'] ) && is_array( $params['feeds'] )
			? $params['feeds']
			: array();

		$error = self::save_feeds( $user_id, $raw_feeds );
		if ( $error ) {
			return new WP_Error( 'validation_error', $error, array( 'status' => 400 ) );
		}

		// Bust cache for all old feed slots
		self::clear_all_cache( $user_id );

		return rest_ensure_response( array(
			'success' => true,
			'feeds'   => self::get_feeds( $user_id ),
		) );
	}

	/**
	 * Force-refresh all feeds (clears transients then re-fetches).
	 */
	public static function rest_refresh_feeds( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$feeds   = self::get_feeds( $user_id );

		self::clear_all_cache( $user_id );

		$status = array();
		foreach ( $feeds as $index => $feed ) {
			$events = self::fetch_and_parse_feed( $user_id, $index, $feed );
			$status[] = array(
				'index'  => $index,
				'label'  => $feed['label'],
				'count'  => count( $events ),
				'error'  => get_transient( self::error_key( $user_id, $index ) ) ?: null,
			);
		}

		return rest_ensure_response( array(
			'success' => true,
			'status'  => $status,
		) );
	}

	/* ------------------------------------------------------------------ */
	/* Data Layer                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Get the sanitized feed list for a user.
	 *
	 * @param int $user_id
	 * @return array  Array of ['url','label','color','last_fetched','last_error'] entries.
	 */
	public static function get_feeds( $user_id ) {
		$raw = get_user_meta( $user_id, 'ftt_external_calendars', true );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		// Re-attach live status from transients
		foreach ( $raw as $i => $feed ) {
			$raw[ $i ]['last_fetched'] = get_transient( self::cache_key( $user_id, $i ) ) !== false
				? get_option( 'ftt_extcal_fetched_' . $user_id . '_' . $i, '' )
				: '';
			$raw[ $i ]['last_error'] = get_transient( self::error_key( $user_id, $i ) ) ?: '';
		}
		return $raw;
	}

	/**
	 * Validate and persist feed list.
	 *
	 * @param int   $user_id
	 * @param array $raw_feeds
	 * @return string  Empty string on success, error message on failure.
	 */
	public static function save_feeds( $user_id, array $raw_feeds ) {
		$clean = array();
		foreach ( array_slice( $raw_feeds, 0, self::MAX_FEEDS ) as $feed ) {
			if ( empty( $feed['url'] ) ) {
				continue; // skip blank rows
			}

			$url = esc_url_raw( wp_unslash( $feed['url'] ) );

			// Only allow http/https/webcal schemes
			$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
			if ( ! in_array( $scheme, array( 'http', 'https', 'webcal' ), true ) ) {
				return sprintf(
					/* translators: %s: invalid URL */
					__( 'Invalid feed URL scheme: %s', 'schedule-collaboration-tracking' ),
					esc_html( $url )
				);
			}

			// Convert webcal:// → https:// for server-side fetch
			$url = preg_replace( '/^webcal:\/\//i', 'https://', $url );

			$label = sanitize_text_field( wp_unslash( $feed['label'] ?? '' ) );
			if ( $label === '' ) {
				$label = __( 'External Calendar', 'schedule-collaboration-tracking' );
			}

			$color = sanitize_hex_color( $feed['color'] ?? '' );
			if ( ! $color || ! in_array( $color, self::ALLOWED_COLORS, true ) ) {
				$color = self::ALLOWED_COLORS[0]; // default to indigo
			}

			$clean[] = array(
				'url'   => $url,
				'label' => $label,
				'color' => $color,
			);
		}

		update_user_meta( $user_id, 'ftt_external_calendars', $clean );
		return '';
	}

	/* ------------------------------------------------------------------ */
	/* Fetch + Parse                                                       */
	/* ------------------------------------------------------------------ */

	/**
	 * Return events from cache, or fetch + parse + cache them.
	 */
	private static function get_cached_events( $user_id, $index, $feed ) {
		$cache_key = self::cache_key( $user_id, $index );
		$cached    = get_transient( $cache_key );
		if ( $cached !== false ) {
			return $cached;
		}
		return self::fetch_and_parse_feed( $user_id, $index, $feed );
	}

	/**
	 * Fetch the iCal feed over HTTP, parse VEVENTs, cache, return events.
	 */
	private static function fetch_and_parse_feed( $user_id, $index, $feed ) {
		$url = $feed['url'];

		$response = wp_remote_get( $url, array(
			'timeout'   => self::FETCH_TIMEOUT,
			'sslverify' => true,
			'headers'   => array(
				'User-Agent' => 'FamilyTravelTracker/' . FTT_VERSION . ' (iCal reader)',
			),
		) );

		if ( is_wp_error( $response ) ) {
			$error_msg = $response->get_error_message();
			set_transient( self::error_key( $user_id, $index ), $error_msg, self::CACHE_TTL );
			return array();
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			$error_msg = sprintf( 'HTTP %d', $status_code );
			set_transient( self::error_key( $user_id, $index ), $error_msg, self::CACHE_TTL );
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			set_transient( self::error_key( $user_id, $index ), 'Empty response', self::CACHE_TTL );
			return array();
		}

		// Clear any previous error
		delete_transient( self::error_key( $user_id, $index ) );

		$events = self::parse_ical( $body, $feed['color'], $feed['label'] );

		// Cache the event array
		set_transient( self::cache_key( $user_id, $index ), $events, self::CACHE_TTL );

		// Record fetch timestamp
		update_option(
			'ftt_extcal_fetched_' . $user_id . '_' . $index,
			current_time( 'mysql' ),
			false // don't autoload
		);

		return $events;
	}

	/**
	 * Parse an iCal string and return FullCalendar-compatible event objects.
	 *
	 * Handles:
	 *   DTSTART:20260315T090000Z           (UTC datetime)
	 *   DTSTART;TZID=America/Chicago:…    (zoned datetime)
	 *   DTSTART;VALUE=DATE:20260315        (all-day)
	 *
	 * @param string $ical   Raw iCal text.
	 * @param string $color  Hex color for events.
	 * @param string $source Feed label (stored as extended prop).
	 * @return array
	 */
	public static function parse_ical( $ical, $color = '#7986CB', $source = '' ) {
		$events = array();

		// Unfold long lines (RFC 5545 §3.1)
		$ical = preg_replace( '/\r\n[ \t]/', '', $ical );
		$ical = preg_replace( '/\r/', '', $ical );

		// Split into VEVENT blocks
		if ( ! preg_match_all( '/BEGIN:VEVENT(.+?)END:VEVENT/s', $ical, $matches ) ) {
			return $events;
		}

		foreach ( $matches[1] as $block ) {
			$event = self::parse_vevent_block( $block, $color, $source );
			if ( $event ) {
				$events[] = $event;
			}
		}

		return $events;
	}

	/**
	 * Parse a single VEVENT block into a FullCalendar event array.
	 */
	private static function parse_vevent_block( $block, $color, $source ) {
		$lines = array();
		foreach ( explode( "\n", $block ) as $line ) {
			$line = trim( $line );
			if ( $line !== '' ) {
				$lines[] = $line;
			}
		}

		$props = array();
		foreach ( $lines as $line ) {
			// Each line: PROPERTY;PARAMS:VALUE  or  PROPERTY:VALUE
			if ( preg_match( '/^([A-Z\-]+)(;[^:]+)?:(.*)$/s', $line, $m ) ) {
				$key     = $m[1];
				$params  = $m[2]; // e.g. ";TZID=America/Chicago" or ";VALUE=DATE"
				$val     = rtrim( $m[3] );
				$props[ $key ] = array( 'value' => $val, 'params' => $params );
			}
		}

		// Required: DTSTART
		if ( empty( $props['DTSTART'] ) ) {
			return null;
		}

		$summary     = isset( $props['SUMMARY'] )     ? self::unescape_ical_text( $props['SUMMARY']['value'] )     : '(No title)';
		$description = isset( $props['DESCRIPTION'] ) ? self::unescape_ical_text( $props['DESCRIPTION']['value'] ) : '';
		$location    = isset( $props['LOCATION'] )    ? self::unescape_ical_text( $props['LOCATION']['value'] )    : '';
		$uid         = isset( $props['UID'] )         ? $props['UID']['value']                                      : wp_generate_uuid4();

		$start_raw    = $props['DTSTART']['value'];
		$start_params = $props['DTSTART']['params'];
		$end_raw      = isset( $props['DTEND'] ) ? $props['DTEND']['value'] : '';
		$end_params   = isset( $props['DTEND'] ) ? $props['DTEND']['params'] : '';

		$all_day = ( strpos( $start_params, 'VALUE=DATE' ) !== false )
		        || ( ! strpos( $start_raw, 'T' ) );

		$start = self::ical_date_to_iso( $start_raw, $start_params );
		$end   = $end_raw ? self::ical_date_to_iso( $end_raw, $end_params ) : null;

		if ( ! $start ) {
			return null;
		}

		$fc_event = array(
			'id'            => 'ext-' . md5( $uid ),
			'title'         => $summary,
			'start'         => $start,
			'allDay'        => $all_day,
			'color'         => $color,
			'textColor'     => '#ffffff',
			'classNames'    => array( 'ftt-ext-event' ),
			'editable'      => false,
			'extendedProps' => array(
				'source'      => $source,
				'description' => $description,
				'location'    => $location,
				'external'    => true,
			),
		);

		if ( $end ) {
			$fc_event['end'] = $end;
		}

		return $fc_event;
	}

	/**
	 * Convert an iCal date/datetime string to ISO 8601 for FullCalendar.
	 *
	 * @param string $value   e.g. "20260315T090000Z" or "20260315"
	 * @param string $params  e.g. ";TZID=America/Chicago"
	 * @return string|null  ISO string, or null on parse failure.
	 */
	private static function ical_date_to_iso( $value, $params ) {
		$value = trim( $value );

		// All-day date: YYYYMMDD
		if ( preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $value, $m ) ) {
			return $m[1] . '-' . $m[2] . '-' . $m[3];
		}

		// Datetime with Z suffix (UTC): YYYYMMDDTHHmmssZ
		if ( preg_match( '/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})Z$/', $value, $m ) ) {
			return $m[1] . '-' . $m[2] . '-' . $m[3] . 'T' . $m[4] . ':' . $m[5] . ':' . $m[6] . 'Z';
		}

		// Floating or zoned datetime: YYYYMMDDTHHmmss
		if ( preg_match( '/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})$/', $value, $m ) ) {
			$iso_base = $m[1] . '-' . $m[2] . '-' . $m[3] . 'T' . $m[4] . ':' . $m[5] . ':' . $m[6];

			// Extract TZID if present
			if ( preg_match( '/TZID=([^;:]+)/', $params, $tm ) ) {
				$tzid = $tm[1];
				if ( in_array( $tzid, timezone_identifiers_list(), true ) ) {
					try {
						$tz  = new DateTimeZone( $tzid );
						$dt  = new DateTime( $iso_base, $tz );
						return $dt->format( DateTime::ATOM ); // includes offset
					} catch ( Exception $e ) {
						// fall through to returning base
					}
				}
			}

			return $iso_base; // floating — let FullCalendar interpret per display timezone
		}

		return null;
	}

	/**
	 * Unescape iCal text property values (\\, \n, \,, \;)
	 */
	private static function unescape_ical_text( $text ) {
		$text = str_replace( array( '\\\\', '\\n', '\\N', '\\,', '\\;' ), array( '\\', "\n", "\n", ',', ';' ), $text );
		return sanitize_text_field( $text );
	}

	/* ------------------------------------------------------------------ */
	/* Cache Keys                                                          */
	/* ------------------------------------------------------------------ */

	private static function cache_key( $user_id, $index ) {
		return 'ftt_extcal_' . intval( $user_id ) . '_' . intval( $index );
	}

	private static function error_key( $user_id, $index ) {
		return 'ftt_extcal_err_' . intval( $user_id ) . '_' . intval( $index );
	}

	/**
	 * Delete all cached event transients for a user (up to MAX_FEEDS slots).
	 */
	public static function clear_all_cache( $user_id ) {
		for ( $i = 0; $i < self::MAX_FEEDS; $i++ ) {
			delete_transient( self::cache_key( $user_id, $i ) );
			delete_transient( self::error_key( $user_id, $i ) );
		}
	}
}
