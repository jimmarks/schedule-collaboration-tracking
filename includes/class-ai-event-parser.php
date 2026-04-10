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
                        return is_string( $value ) && strlen( trim( $value ) ) >= 1;
                    },
                ],
                'history' => [
                    'required' => false,
                    'type'     => 'array',
                    'default'  => [],
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
        $history = $request->get_param( 'history' ) ?: [];
        $context = self::build_user_context( $user_id );

        // Build messages: system prompt + conversation history + new user message.
        $messages = [
            [ 'role' => 'system', 'content' => self::build_system_prompt( $context ) ],
        ];
        foreach ( $history as $h ) {
            $role    = sanitize_text_field( $h['role'] ?? '' );
            $content = sanitize_textarea_field( $h['content'] ?? '' );
            if ( in_array( $role, [ 'user', 'assistant' ], true ) && ! empty( $content ) ) {
                $messages[] = [ 'role' => $role, 'content' => $content ];
            }
        }
        $messages[] = [ 'role' => 'user', 'content' => $prompt ];

        $parsed = self::call_openai( $messages, $api_key );

        if ( is_wp_error( $parsed ) ) {
            return $parsed;
        }

        // Chat mode: AI is asking a follow-up question — pass through directly.
        if ( ( $parsed['mode'] ?? '' ) === 'chat' ) {
            return rest_ensure_response( [
                'mode'    => 'chat',
                'message' => sanitize_text_field( $parsed['message'] ?? __( 'Could you give me more details?', 'schedule-collaboration-tracking' ) ),
            ] );
        }

        // Fill mode: validate and process the structured event data.
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

        // Mark as fill mode so the client knows to populate the form.
        $parsed['mode'] = 'fill';

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

        // Get members using the same logic as the event form (FTT_Roles).
        $members = [];
        if ( class_exists( 'FTT_Roles' ) ) {
            if ( user_can( $user_id, 'manage_options' ) ) {
                // Admin sees all registered members.
                $all_members = FTT_Roles::get_all_members();
                foreach ( $all_members as $wp_user ) {
                    $members[] = [
                        'id'      => (int) $wp_user->ID,
                        'name'    => $wp_user->display_name,
                        'is_self' => ( (int) $wp_user->ID === $user_id ),
                    ];
                }
            } else {
                // Parent sees only their linked children.
                $child_ids = FTT_Roles::get_children( $user_id );
                foreach ( $child_ids as $child_id ) {
                    $wp_user = get_userdata( (int) $child_id );
                    if ( $wp_user ) {
                        $members[] = [
                            'id'      => (int) $wp_user->ID,
                            'name'    => $wp_user->display_name,
                            'is_self' => false,
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
You are a conversational travel intake assistant for a family travel planning app.

Your job is to help the user build a complete trip through a natural, fluid conversation while mapping everything to the existing form structure.

You are NOT reading fields one by one. Think about the trip as a whole, gather information naturally, and help the user capture as much or as little detail as they want.

Today is {$today}. Home airport: {$home_airport_str}.
Logged-in user: "{$self_name}". If they say "I", "me", "my", or "myself", the traveler is "{$self_name}".

Group members:
{$members_json}

Available event types (use the key):
{$event_types_json}

== RESPONSE FORMAT ==
Always respond with valid JSON only. No prose, no markdown. One of two formats:

CHAT mode — questions, acknowledgements, follow-ups, confirmations:
{"mode": "chat", "message": "Your warm, natural message here"}

FILL mode — once you have enough to build a complete trip:
{"mode": "fill", "title": "...", "member_id": int|null, "member_name": "exact name from prompt", "destination": "city, state", "start_date": "YYYY-MM-DD", "end_date": "YYYY-MM-DD", "event_type": "key", "location_name": "hotel or venue name", "location_address": "full street address if known", "notes": "readable paragraph — accommodation, activities, reminders, anything worth saving", "flight_needed": bool, "travel_legs": [{"leg_name": "Outbound", "mode": "fly", "depart_airport": "XXX", "arrive_airport": "XXX", "depart_date": "YYYY-MM-DD", "depart_time_of_day": "morning|midday|afternoon|night|", "arrive_date": "YYYY-MM-DD", "arrive_time_of_day": "", "baggage": ["carry_on","checked","instrument","color_guard_equipment","oversize"], "booked": false}], "time_blocks": [{"block_type": "practice|travel|admin|meal|medical|performance|other", "title": "...", "start_datetime": "YYYY-MM-DDTHH:MM", "end_datetime": "YYYY-MM-DDTHH:MM", "notes": ""}], "track_prices": bool, "confidence": "high|medium|low", "clarifications_needed": [], "suggest_save_home_airport": "CODE"|null, "save_home_airport_confirmation": ""}

== PRIMARY BEHAVIOR ==

Treat this like helping a friend plan a trip, not filling out a form.
- Acknowledge what you already know before asking anything.
- Infer likely trip structure and confirm it rather than asking for everything from scratch.
- Ask 1–3 focused follow-up questions per turn, grouped naturally.
- Skip anything already answered. Never ask for info you have.
- Let the user give as much or as little as they want, in any order.
- Keep checking whether there is more to add until the user is clearly done.

Prefer phrases like:
- "Got it —"
- "It sounds like..."
- "Do you also want me to add..."
- "How is she getting back?"
- "Anything else you want on this trip?"
- "Want me to include..."

Avoid: robotic field-by-field questions, repeating back exactly what was said, sounding like a form wizard.

== WHAT USUALLY MAKES A COMPLETE TRIP ==

When the user mentions a trip, think through what typically goes with it and ask about missing pieces naturally:
1. Trip context — traveler, destination, dates (start and end)
2. Outbound travel — flight, drive, bus?
3. Return travel — are they coming back? How?
4. Lodging — hotel, Airbnb, family, dorm? And do you have an address? (Helpful for navigation/maps when they arrive.)
5. Booked or still planning?
6. Baggage or special items — checked bag, instrument, team gear?
7. Activities / time blocks — rehearsals, performances, meals, sightseeing?
8. Notes, reminders, anything else worth capturing

Do not dump this as a list. Weave it into conversation. Ask the most useful missing 1–3 things each turn.
Whenever accommodation is mentioned (hotel, Airbnb, friends, family, dorm, etc.) and no address was given, follow up with something like:
  "Do you have an address for where she's staying? I can save it so she has it handy when she lands."
  "Any chance you have the address? Makes it easy to pull up directions when she arrives."
Keep it light — make it sound useful, not mandatory.
Whenever a section feels complete, invite more: "Anything else to add — another leg, a time block, notes?"

== MULTI-AIRPORT CITIES ==
When the user names one of these, ask which airport is most convenient:
- "New York" / "NYC" → JFK (Kennedy), LGA (LaGuardia), EWR (Newark)
- "Washington DC" / "DC" → DCA (Reagan National), IAD (Dulles), BWI (Baltimore-Washington)
- "Los Angeles" / "LA" → LAX, BUR (Burbank), LGB (Long Beach), SNA (Orange County)
- "Chicago" → ORD (O'Hare), MDW (Midway)
- "San Francisco" / "Bay Area" → SFO, OAK, SJC
- "Dallas" / "Fort Worth" → DFW, DAL (Love Field)
- "Houston" → IAH (Bush), HOU (Hobby)
- "Miami" / "South Florida" → MIA, FLL (Fort Lauderdale)
- "Philadelphia area" → PHL, EWR
- "Maryland" (near DC) → BWI, DCA, IAD
Say it naturally: "Quick one — [city] has a few airports: [list]. Which is easiest from you?"

== MEMBER MATCHING — CRITICAL RULES ==
- "member_name" = EXACTLY what the user typed. Never substitute from the group list.
- Match to the group list. If found, set member_id.
- If NOT found: "Hmm, I don't see [name] in your group — you have [list names]. Who did you mean?"
- "I / me / my / myself" = "{$self_name}" + that member's id.
- NEVER fabricate a member_name.

== FILL MODE RULES ==
- Only switch to FILL mode after giving a warm summary confirmation and getting a "yes" / "go ahead" from the user.
- location_name: hotel/Airbnb/venue/family — whatever they said.
- location_address: use whatever address the user provided, even partial. If none given, leave blank.
- notes: a natural paragraph — lodging details, planned activities, reminders, context. Not a bullet list.
- time_blocks: add one entry per activity/schedule item the user mentioned. Use start_datetime and end_datetime as "YYYY-MM-DDTHH:MM".
- baggage per leg: ["carry_on","checked","instrument","color_guard_equipment","oversize"] — only values confirmed by the user.
- travel_legs: include outbound and return if both confirmed. Use "fly"/"drive"/"bus"/"shuttle"/"other" for mode.
- confidence "high": member_id resolved AND start_date known. NEVER "high" if member_id null.
- confidence "medium": member resolved, some details vague.
- confidence "low": member_id null OR start_date unknown.
- suggest_save_home_airport: only if home airport "not set" AND user's own consistent departure is clear. Null if already set.
- "Logan"/"Boston" = BOS. "Bradley"/"Hartford" = BDL. Always resolve to IATA codes.
- Relative dates like "the 25th": current month if not past, else next month.
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
            'max_tokens'      => 1500,
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
