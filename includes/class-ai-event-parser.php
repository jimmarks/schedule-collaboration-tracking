<?php
/**
 * AI Event Parser
 *
 * Converts natural-language prompts into structured FTT event data using
 * the OpenAI Chat Completions API (gpt-4o-mini).
 *
 * REST endpoint: POST /wp-json/ftt/v1/ai/parse-event
 *   body: { "prompt": "Emma needs to go to Salem MA on the 25th..." }
 *
 * Also hooks into ftt_event_meta_saved to auto-create price alerts for
 * any unbooked flight legs present when an event is created or updated.
 *
 * @package Family_Travel_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FTT_AI_Event_Parser {

    const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
    const OPENAI_MODEL   = 'gpt-4o-mini';

    /**
     * Register hooks.
     */
    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
        add_action( 'ftt_event_meta_saved', [ __CLASS__, 'maybe_auto_create_alerts' ], 10, 2 );
    }

    /**
     * Register the REST route.
     */
    public static function register_routes() {
        register_rest_route( 'ftt/v1', '/ai/parse-event', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'parse_event_prompt' ],
            'permission_callback' => [ 'FTT_REST', 'check_read_permission' ],
            'args'                => [
                'prompt' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                    'validate_callback' => function ( $value ) {
                        return is_string( $value ) && strlen( trim( $value ) ) >= 10;
                    },
                ],
            ],
        ] );
    }

    /**
     * Handle the parse-event request.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function parse_event_prompt( $request ) {
        $settings = get_option( 'ftt_settings', [] );
        $api_key  = $settings['openai_api_key'] ?? '';

        if ( empty( $api_key ) ) {
            return new WP_Error(
                'openai_not_configured',
                __( 'OpenAI API key is not configured. Go to Settings → API Keys to add it.', 'schedule-collaboration-tracking' ),
                [ 'status' => 503 ]
            );
        }

        $user_id = get_current_user_id();
        $prompt  = $request->get_param( 'prompt' );
        $context = self::build_user_context( $user_id );
        $messages = [
            [ 'role' => 'system', 'content' => self::build_system_prompt( $context ) ],
            [ 'role' => 'user',   'content' => $prompt ],
        ];

        $parsed = self::call_openai( $messages, $api_key );

        if ( is_wp_error( $parsed ) ) {
            return $parsed;
        }

        // Validate the returned structure minimally before sending to the client.
        if ( empty( $parsed['title'] ) ) {
            return new WP_Error(
                'ai_parse_failed',
                __( 'AI could not extract enough information from your prompt. Try including a destination, date, and traveler name.', 'schedule-collaboration-tracking' ),
                [ 'status' => 422 ]
            );
        }

        // --- Member resolution ---
        // 1. Self-reference: detect "I", "me", "my", "myself" with no other name.
        if ( empty( $parsed['member_id'] ) ) {
            $self_pattern = '/\b(I|me|my|myself)\b/i';
            $member_name  = trim( $parsed['member_name'] ?? '' );
            if ( preg_match( $self_pattern, $prompt ) && ( empty( $member_name ) || preg_match( $self_pattern, $member_name ) ) ) {
                // Check if the current user is in the members list.
                foreach ( $context['members'] as $member ) {
                    if ( (int) $member['id'] === $user_id ) {
                        $parsed['member_id']   = $user_id;
                        $parsed['member_name'] = $member['name'];
                        break;
                    }
                }
            }
        }

        // 2. Name matching: exact, then first-name-only, then fuzzy contains.
        if ( empty( $parsed['member_id'] ) && ! empty( $parsed['member_name'] ) && ! empty( $context['members'] ) ) {
            $search = strtolower( trim( $parsed['member_name'] ) );
            $found  = null;
            // Exact display name match.
            foreach ( $context['members'] as $member ) {
                if ( strtolower( $member['name'] ) === $search ) { $found = $member; break; }
            }
            // First-name match.
            if ( ! $found ) {
                foreach ( $context['members'] as $member ) {
                    $first = strtolower( explode( ' ', $member['name'] )[0] );
                    if ( $first === $search || strpos( $search, $first ) !== false ) { $found = $member; break; }
                }
            }
            // Contains match.
            if ( ! $found ) {
                foreach ( $context['members'] as $member ) {
                    if ( stripos( $member['name'], $search ) !== false || stripos( $search, strtolower( explode( ' ', $member['name'] )[0] ) ) !== false ) {
                        $found = $member; break;
                    }
                }
            }
            if ( $found ) {
                $parsed['member_id'] = $found['id'];
            }
        }

        // 3. Enforce confidence: unresolved member = max "medium" + clarification.
        if ( empty( $parsed['member_id'] ) && ! empty( $parsed['member_name'] ) ) {
            if ( ( $parsed['confidence'] ?? '' ) === 'high' ) {
                $parsed['confidence'] = 'medium';
            }
            $name = $parsed['member_name'];
            $clarifications = $parsed['clarifications_needed'] ?? [];
            $has_member_note = false;
            foreach ( $clarifications as $c ) {
                if ( stripos( $c, $name ) !== false || stripos( $c, 'member' ) !== false || stripos( $c, 'traveler' ) !== false ) {
                    $has_member_note = true; break;
                }
            }
            if ( ! $has_member_note ) {
                $clarifications[] = "\"{$name}\" is not a recognized group member — please select the traveler manually.";
                $parsed['clarifications_needed'] = $clarifications;
            }
        }

        // 4. NEVER auto-save home airport. suggest_save_home_airport stays as a
        //    suggestion for the client to act on with a Yes/No prompt.
        //    save_home_airport is left in the payload so the client can handle it
        //    with a confirmation message, but we do NOT write meta here.
        unset( $parsed['save_home_airport'] ); // prevents accidental server-side writes

        // Normalise dates to YYYY-MM-DD for the event form.
        foreach ( [ 'start_date', 'end_date' ] as $key ) {
            if ( ! empty( $parsed[ $key ] ) ) {
                $ts = strtotime( $parsed[ $key ] );
                if ( $ts ) {
                    $parsed[ $key ] = gmdate( 'Y-m-d', $ts );
                }
            }
        }

        // Normalise travel leg dates.
        if ( ! empty( $parsed['travel_legs'] ) && is_array( $parsed['travel_legs'] ) ) {
            foreach ( $parsed['travel_legs'] as &$leg ) {
                foreach ( [ 'depart_date', 'arrive_date' ] as $dk ) {
                    if ( ! empty( $leg[ $dk ] ) ) {
                        $ts = strtotime( $leg[ $dk ] );
                        if ( $ts ) { $leg[ $dk ] = gmdate( 'Y-m-d', $ts ); }
                    }
                }
            }
            unset( $leg );
        }

        return rest_ensure_response( $parsed );
    }

    // -------------------------------------------------------------------------
    // Auto price-alert on event save
    // -------------------------------------------------------------------------

    /**
     * After an event's meta is saved, auto-create a "good_deal" price alert for
     * every unbooked flight leg that does not already have an active alert.
     *
     * Hooked into ftt_event_meta_saved (fired from FTT_REST::update_event_meta).
     *
     * @param int             $post_id
     * @param WP_REST_Request $request
     */
    public static function maybe_auto_create_alerts( $post_id, $request ) {
        if ( ! class_exists( 'FTT_Price_Tracking' ) ) {
            return;
        }

        // Only act if flight tracking is involved.
        $flight_needed = get_post_meta( $post_id, 'flight_needed', true );
        if ( ! $flight_needed ) {
            return;
        }

        $travel_legs = json_decode( get_post_meta( $post_id, 'travel_legs', true ) ?: '[]', true );
        if ( empty( $travel_legs ) ) {
            return;
        }

        // The alert owner is the event author if set, otherwise the current user.
        $post    = get_post( $post_id );
        $user_id = $post ? (int) $post->post_author : get_current_user_id();
        if ( ! $user_id ) {
            return;
        }

        global $wpdb;
        $alerts_table = $wpdb->prefix . 'ftt_price_alerts';

        foreach ( $travel_legs as $index => $leg ) {
            if ( ( $leg['mode'] ?? '' ) !== 'fly' ) {
                continue;
            }
            if ( ! empty( $leg['booked'] ) ) {
                continue; // Already booked — no point tracking.
            }
            if ( empty( $leg['depart_airport'] ) || empty( $leg['arrive_airport'] ) || empty( $leg['depart_date'] ) ) {
                continue;
            }

            // Skip if an active good_deal alert already exists for this leg.
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$alerts_table}
                 WHERE user_id = %d AND event_id = %d AND leg_index = %d
                   AND alert_type = 'good_deal' AND is_active = 1
                 LIMIT 1",
                $user_id, $post_id, $index
            ) );

            if ( $existing ) {
                continue;
            }

            FTT_Price_Tracking::create_alert( $user_id, $post_id, $index, 'good_deal' );
        }
    }

    // -------------------------------------------------------------------------
    // OpenAI helpers
    // -------------------------------------------------------------------------

    /**
     * Build the user context array (members, home airport, today's date).
     *
     * @param int $user_id
     * @return array
     */
    private static function build_user_context( $user_id ) {
        // Prefer the primary airport from ftt_home_airports (array), fall back to ftt_home_airport (single string).
        $home_airports_raw = get_user_meta( $user_id, 'ftt_home_airports', true );
        $home_airports     = is_array( $home_airports_raw ) ? $home_airports_raw
                           : ( $home_airports_raw ? json_decode( $home_airports_raw, true ) : [] );
        $home_airport      = ! empty( $home_airports[0] ) ? $home_airports[0]
                           : ( get_user_meta( $user_id, 'ftt_home_airport', true ) ?: '' );

        // Current user info so AI can handle self-reference.
        $current_user = get_userdata( $user_id );
        $current_user_info = $current_user ? [
            'id'   => $user_id,
            'name' => $current_user->display_name,
        ] : null;

        // Get group members so the AI can resolve names to IDs.
        $members  = [];
        $group_id = (int) get_user_meta( $user_id, 'ftt_primary_group', true );

        if ( $group_id && class_exists( 'FTT_Family_Groups' ) ) {
            $group_members = FTT_Family_Groups::get_group_members( $group_id );
            if ( is_array( $group_members ) ) {
                foreach ( $group_members as $m ) {
                    $wp_user = get_userdata( $m->user_id );
                    if ( $wp_user ) {
                        $members[] = [
                            'id'           => (int) $m->user_id,
                            'name'         => $wp_user->display_name,
                            'role'         => $m->role,
                            'relationship' => $m->relationship ?? '',
                            'is_self'      => ( (int) $m->user_id === $user_id ),
                        ];
                    }
                }
            }
        }

        // Event types so the AI can pick an appropriate one.
        $event_types = [];
        if ( class_exists( 'FTT_CPT' ) ) {
            foreach ( FTT_CPT::get_event_types() as $key => $type ) {
                $event_types[ $key ] = $type['label'] ?? $key;
            }
        }

        return [
            'today'             => gmdate( 'Y-m-d' ),
            'home_airport'      => $home_airport,
            'members'           => $members,
            'current_user'      => $current_user_info,
            'event_types'       => $event_types,
        ];
    }

    /**
     * Build the system prompt sent to OpenAI.
     *
     * @param array $context
     * @return string
     */
    private static function build_system_prompt( $context ) {
        $members_json     = wp_json_encode( $context['members'], JSON_PRETTY_PRINT );
        $event_types_json = wp_json_encode( $context['event_types'], JSON_PRETTY_PRINT );
        $home_airport     = $context['home_airport'];
        $home_airport_str = $home_airport ?: 'not set';
        $today            = $context['today'];
        $self_name        = $context['current_user']['name'] ?? 'the logged-in user';

        return <<<PROMPT
You are an event parser for a family travel tracking application.

Your job is to parse a natural-language description of a trip or travel need and return ONLY a valid JSON object — no prose, no markdown, just the JSON.

Today's date is {$today}.
The user's home airport is {$home_airport_str} (IATA code). If it is "not set" and no departure airport can be determined from the prompt, add "Home airport not set — please specify your departure airport" to clarifications_needed and leave depart_airport empty.
The logged-in user's name is "{$self_name}". If the prompt uses "I", "me", "my", or "myself" as the traveler, set member_name to "{$self_name}".
Family members (use these IDs when you identify the traveler):
{$members_json}

Available event types (use the key, not the label):
{$event_types_json}

Return a JSON object with exactly these fields:
- "title" (string): a short event title, e.g. "Emma - Salem Trip"
- "member_id" (int|null): ID from the members list above if you can identify the traveler; null if unclear
- "member_name" (string): the traveler's name as mentioned in the prompt
- "destination" (string): city and state/country of the destination
- "start_date" (string): departure date in YYYY-MM-DD format
- "end_date" (string): return/end date in YYYY-MM-DD format
- "event_type" (string): the best matching key from the event types list above, or "" if none fit
- "notes" (string): any additional context from the prompt not captured elsewhere
- "flight_needed" (bool): true if any flights are mentioned or implied
- "travel_legs" (array): each leg is an object with keys:
    - "leg_name" (string): e.g. "Outbound", "Return"
    - "mode" (string): always "fly" for flights
    - "depart_airport" (string): IATA code
    - "arrive_airport" (string): IATA code
    - "depart_date" (string): YYYY-MM-DD
    - "depart_time_of_day" (string): one of "morning", "midday", "afternoon", "night", or "" if unspecified
    - "arrive_date" (string): YYYY-MM-DD (same as depart for same-day flights)
    - "arrive_time_of_day" (string): one of "morning", "midday", "afternoon", "night", or ""
    - "booked" (bool): always false for newly parsed events
- "needs_return_clarification" (bool): true if flight is needed, no return leg was included, and the trip has an end_date different from start_date. The client UI will ask the user.
- "track_prices" (bool): true if flights are present
- "confidence" (string): "high" ONLY when member_id is non-null AND start_date is known. "medium" if member_id is resolved but other details are unclear. "low" if member_id is null OR start_date is unknown.
- "clarifications_needed" (array of strings): list any ambiguous details
- "suggest_save_home_airport" (string|null): ONLY set this if (a) home airport is "not set" AND (b) a clear, unambiguous departure airport appears in the prompt AND (c) nothing suggests it is a one-off origin. Otherwise null.
- "save_home_airport_confirmation" (string): if suggest_save_home_airport is set, a short natural-language confirmation for if the user says yes. Otherwise "".

Rules:
- For relative dates like "the 25th", use the current month unless that date has passed, in which case use next month.
- If a home airport is not mentioned in the prompt but a return flight is requested, use {$home_airport_str} as both outbound departure and return arrival. If "not set", add to clarifications_needed instead.
- "Logan Airport" = BOS. "Bradley" or "Hartford" = BDL. Resolve common airport names to IATA codes.
- If you cannot determine a start_date, set confidence to "low" and add to clarifications_needed.
- Include a return flight leg ONLY if the user explicitly says "flight home", "return flight", "fly back", or similar.
- Set needs_return_clarification=true when flight_needed is true, no return leg was added, and end_date differs from start_date.
- confidence must never be "high" when member_id is null.
- suggest_save_home_airport must be null if the user's home airport is already set.
- Never include markdown, code fences, or any text outside the JSON object.
PROMPT;
    }

    /**
     * Call the OpenAI Chat Completions API.
     *
     * @param array  $messages
     * @param string $api_key
     * @return array|WP_Error Decoded JSON array on success, WP_Error on failure.
     */
    private static function call_openai( $messages, $api_key ) {
        $body = wp_json_encode( [
            'model'           => self::OPENAI_MODEL,
            'messages'        => $messages,
            'response_format' => [ 'type' => 'json_object' ],
            'temperature'     => 0.2,
            'max_tokens'      => 1000,
        ] );

        $response = wp_remote_post( self::OPENAI_API_URL, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => $body,
        ] );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'openai_request_failed',
                sprintf(
                    /* translators: %s: error message */
                    __( 'OpenAI request failed: %s', 'schedule-collaboration-tracking' ),
                    $response->get_error_message()
                ),
                [ 'status' => 502 ]
            );
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $data      = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $http_code !== 200 ) {
            $error_msg = $data['error']['message'] ?? __( 'OpenAI returned an unexpected response.', 'schedule-collaboration-tracking' );
            return new WP_Error( 'openai_api_error', $error_msg, [ 'status' => 502 ] );
        }

        $content = $data['choices'][0]['message']['content'] ?? '';
        $parsed  = json_decode( $content, true );

        if ( ! is_array( $parsed ) ) {
            return new WP_Error(
                'openai_invalid_json',
                __( 'AI returned an unparseable response. Please try rephrasing your prompt.', 'schedule-collaboration-tracking' ),
                [ 'status' => 502 ]
            );
        }

        return $parsed;
    }
}
