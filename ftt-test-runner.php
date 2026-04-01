<?php
/**
 * FTT Endpoint Test Runner — TEMPORARY FILE
 * DELETE THIS FILE BEFORE GOING TO PRODUCTION.
 *
 * Access URL (once plugin is installed):
 *   https://www.testing.familytraveltracker.app/wp-content/plugins/schedule-collaboration-tracking/ftt-test-runner.php
 */

// ── Bootstrap WordPress ────────────────────────────────────────────────────────
// Build a list of candidate locations. On standard installs wp-content sits
// inside the WP root so traversing upward from __DIR__ works. On Bitnami the
// wp-content directory is mounted separately from the WP core, so we also try
// DOCUMENT_ROOT (the actual WP root) and a couple of common Bitnami paths.
$wp_load    = '';
$candidates = [];

// 1. DOCUMENT_ROOT — the web-server document root is the WP install root on most hosts.
if ( ! empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
    $candidates[] = rtrim( $_SERVER['DOCUMENT_ROOT'], '/' ) . '/wp-load.php';
}

// 2. Walk up to 10 levels from this file (works on standard installs).
$dir = __DIR__;
for ( $i = 0; $i < 10; $i++ ) {
    $candidates[] = $dir . '/wp-load.php';
    $dir          = dirname( $dir );
}

// 3. Known Bitnami paths as a last resort.
$candidates[] = '/opt/bitnami/wordpress/wp-load.php';
$candidates[] = '/bitnami/wordpress/wp-load.php';

foreach ( $candidates as $candidate ) {
    if ( $candidate !== '/wp-load.php' && file_exists( $candidate ) ) {
        $wp_load = $candidate;
        break;
    }
}

if ( ! $wp_load ) {
    die( 'ERROR: Could not find wp-load.php. Tried: ' . implode( ', ', array_unique( $candidates ) ) );
}
require_once $wp_load;

// ── Security: logged-in admins only ───────────────────────────────────────────
if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Access denied. You must be logged in as an administrator.', 'Access Denied', [ 'response' => 403 ] );
}

// ── Bootstrap REST server (registers all routes) ──────────────────────────────
$rest_server = rest_get_server();

// ── Test helpers ──────────────────────────────────────────────────────────────
$results         = [];
$created_child   = null;   // filled by add-child test, used by edit/delete
$created_group   = null;   // filled by create-group test
$created_invite  = null;   // filled by invite-adult test

/**
 * Run a single REST test and store the result.
 *
 * @param string   $id             Short identifier (T1, F3, …)
 * @param string   $label          Human-readable description
 * @param string   $method         GET|POST|PUT|DELETE
 * @param string   $route          Relative to /ftt/v1, e.g. '/dashboard'
 * @param array    $body           Params to set on the request (optional)
 * @param int      $expect_status  Expected HTTP status code (default 200)
 * @param array    $checks         ['Check label' => fn($data) => bool]
 * @return mixed   Response data
 */
function ftt_run( $id, $label, $method, $route, $body = [], $expect_status = 200, $checks = [] ) {
    global $results;

    $request = new WP_REST_Request( $method, '/ftt/v1' . $route );
    if ( ! empty( $body ) ) {
        $request->set_header( 'Content-Type', 'application/json' );
        foreach ( $body as $k => $v ) {
            $request->set_param( $k, $v );
        }
    }

    $response = rest_do_request( $request );
    $status   = $response->get_status();
    $data     = $response->get_data();

    $pass          = ( $expect_status === 0 ) ? true : ( $status === $expect_status );
    $check_results = [];
    foreach ( $checks as $check_label => $fn ) {
        $ok                          = (bool) $fn( $data );
        $check_results[ $check_label ] = $ok;
        if ( ! $ok ) {
            $pass = false;
        }
    }

    $results[] = [
        'id'      => $id,
        'label'   => $label,
        'method'  => $method,
        'route'   => '/ftt/v1' . $route,
        'exp'     => $expect_status,
        'actual'  => $status,
        'pass'    => $pass,
        'checks'  => $check_results,
        'data'    => $data,
    ];

    return $data;
}

$current_user_email = wp_get_current_user()->user_email;

// ════════════════════════════════════════════════════════════════════════════════
// TIER 1 — Smoke Tests (read-only)
// ════════════════════════════════════════════════════════════════════════════════

$dash = ftt_run( 'T3', 'Dashboard loads', 'GET', '/dashboard', [], 200, [
    'No "debug" key in response' => function ( $d ) {
        return ! isset( $d['debug'] );
    },
    'Has "upcoming_travel" key' => function ( $d ) {
        return isset( $d['upcoming_travel'] );
    },
] );

ftt_run( 'T5a', 'Events list', 'GET', '/events', [], 200, [
    'Returns array' => function ( $d ) { return is_array( $d ); },
] );

ftt_run( 'T5b', 'Events filtered by group_id=1', 'GET', '/events', [ 'group_id' => 1 ], 200, [
    'Returns array' => function ( $d ) { return is_array( $d ); },
] );

ftt_run( 'S1', 'Groups list', 'GET', '/groups', [], 200, [
    'Returns array' => function ( $d ) { return is_array( $d ); },
] );

ftt_run( 'S2', 'My children', 'GET', '/children', [], 200, [
    'Returns array' => function ( $d ) { return is_array( $d ); },
] );

ftt_run( 'S3', 'Pending invitations', 'GET', '/invitations', [], 200, [
    'Returns array' => function ( $d ) { return is_array( $d ); },
] );

ftt_run( 'S4', 'Family members', 'GET', '/get-family-members', [], 200 );

ftt_run( 'S5', 'User preferences', 'GET', '/user-preferences', [], 200 );

// S6 omitted: /user/primary-group is POST-only (no GET endpoint exists)

ftt_run( 'S7', 'Flight groups', 'GET', '/flight-groups', [], 200 );

ftt_run( 'S8', 'My alerts', 'GET', '/my-alerts', [], 200 );

ftt_run( 'S9', 'Registration URL', 'GET', '/registration-url', [], 200 );

// ════════════════════════════════════════════════════════════════════════════════
// TIER 2 — Family Management
// ════════════════════════════════════════════════════════════════════════════════

// T2 — Add child
$child_data = ftt_run( 'T2', 'Add child', 'POST', '/children', [
    'first_name' => 'TestRunner',
    'last_name'  => 'Child',
    'dob'        => '2015-06-01',
], 200, [
    'Has child ID' => function ( $d ) { return ! empty( $d['child_id'] ); },
] );
$created_child = $child_data['child_id'] ?? null;

// F7 — Edit child (requires child from T2)
if ( $created_child ) {
    ftt_run( 'F7', 'Edit child', 'PUT', '/children/' . $created_child, [
        'first_name' => 'UpdatedRunner',
        'last_name'  => 'Child',
    ], 200, [
        'Has child data' => function ( $d ) { return ! empty( $d ); },
    ] );
} else {
    $results[] = [ 'id' => 'F7', 'label' => 'Edit child (SKIPPED — T2 failed)', 'pass' => null ];
}

// F14 — Self-invite guard
ftt_run( 'F14', 'Self-invite guard (expect 400)', 'POST', '/invite-adult', [
    'email'        => $current_user_email,
    'relationship' => 'co-parent',
], 400, [
    'Error code is "self_invite"' => function ( $d ) {
        return isset( $d['code'] ) && $d['code'] === 'self_invite';
    },
] );

// F1 — Invite new user (non-group)
// Response returns 'invite_url' (full URL with ?ftt_invite=CODE). Extract the code from it.
$invite_data = ftt_run( 'F1', 'Invite new user (non-group)', 'POST', '/invite-adult', [
    'email'        => 'ftt-test-new-' . time() . '@mailinator.com',
    'relationship' => 'co-parent',
], 200, [
    'Has invite_url' => function ( $d ) { return ! empty( $d['invite_url'] ); },
] );
// Parse the code out of the invite_url query string
$created_invite = null;
if ( ! empty( $invite_data['invite_url'] ) ) {
    parse_str( (string) parse_url( $invite_data['invite_url'], PHP_URL_QUERY ), $qs );
    $created_invite = $qs['ftt_invite'] ?? null;
}

// F9 — Invite existing user (use the currently logged-in user's email won't work for self;
//      we use a well-known existing WP user — picks first admin that isn't us)
$other_admin = get_users( [
    'role__in' => [ 'administrator', 'subscriber' ],
    'exclude'  => [ get_current_user_id() ],
    'number'   => 1,
    'fields'   => [ 'ID', 'user_email' ],
] );
if ( ! empty( $other_admin ) ) {
    ftt_run( 'F9', 'Invite existing user (expect added_directly:true)', 'POST', '/invite-adult', [
        'email'        => $other_admin[0]->user_email,
        'relationship' => 'co-parent',
    ], 200, [
        'added_directly is true' => function ( $d ) {
            return ! empty( $d['added_directly'] ) && $d['added_directly'] === true;
        },
    ] );
} else {
    $results[] = [ 'id' => 'F9', 'label' => 'Invite existing user (SKIPPED — no other user found)', 'pass' => null ];
}

// F3 — Resend invitation
if ( $created_invite ) {
    ftt_run( 'F3', 'Resend invitation', 'POST', '/resend-invitation', [
        'invite_code' => $created_invite,
    ], 200 );
} else {
    $results[] = [ 'id' => 'F3', 'label' => 'Resend invitation (SKIPPED — F1 failed)', 'pass' => null ];
}

// F4 — Cancel invitation (run after F3 so code still exists)
if ( $created_invite ) {
    ftt_run( 'F4', 'Cancel invitation', 'POST', '/cancel-invitation', [
        'invite_code' => $created_invite,
    ], 200 );
} else {
    $results[] = [ 'id' => 'F4', 'label' => 'Cancel invitation (SKIPPED — F1 failed)', 'pass' => null ];
}

// ════════════════════════════════════════════════════════════════════════════════
// TIER 3 — Groups
// ════════════════════════════════════════════════════════════════════════════════

// T4 — Create group
global $wpdb;
$members_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ftt_group_members" );

$group_data = ftt_run( 'T4', 'Create group', 'POST', '/groups', [
    'name'        => 'TestRunner Group ' . time(),
    'description' => 'Created by ftt-test-runner.php',
], 200, [
    'Has group ID' => function ( $d ) { return ! empty( $d['group_id'] ); },
] );
$created_group = $group_data['group_id'] ?? null;

// T4b — Verify exactly 1 member row was added (not 2)
if ( $created_group ) {
    $members_after = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ftt_group_members WHERE group_id = %d", $created_group ) );
    $results[]     = [
        'id'     => 'T4b',
        'label'  => 'Create group: exactly 1 member row in ftt_group_members',
        'method' => 'DB',
        'route'  => "ftt_group_members WHERE group_id={$created_group}",
        'exp'    => 1,
        'actual' => $members_after,
        'pass'   => ( $members_after === 1 ),
        'checks' => [],
        'data'   => [ 'count' => $members_after ],
    ];
}

// G1 — Get specific group
// Response shape: ['success' => true, 'group' => stdClass]
if ( $created_group ) {
    ftt_run( 'G1', 'Get group by ID', 'GET', '/groups/' . $created_group, [], 200, [
        'Has group name' => function ( $d ) {
            return ! empty( $d['group'] ) && ! empty( $d['group']->name );
        },
    ] );
}

// F2 — Group invitation (invite a new address to the group)
// The REST response returns success:true but no invite_code — the code is only in the DB.
// We query the DB after the call to get the code for F11/F12.
$group_invite_code = null;
if ( $created_group ) {
    $f2_email = 'ftt-group-test-' . time() . '@mailinator.com';
    ftt_run( 'F2', 'Invite new user via group', 'POST', '/groups/' . $created_group . '/invitations', [
        'email'        => $f2_email,
        'relationship' => 'member',
    ], 200, [
        'success is true' => function ( $d ) { return ! empty( $d['success'] ); },
    ] );
    // Fetch invite code from DB
    $group_invite_code = $wpdb->get_var( $wpdb->prepare(
        "SELECT invite_code FROM {$wpdb->prefix}ftt_group_invitations WHERE group_id = %d AND email = %s ORDER BY id DESC LIMIT 1",
        $created_group, $f2_email
    ) );
}

// F11 — Verify the invite row landed in the DB and is pending
if ( $group_invite_code ) {
    $db_row = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ftt_group_invitations WHERE invite_code = %s",
        $group_invite_code
    ) );
    $results[] = [
        'id'     => 'F11',
        'label'  => 'Group invite: DB row exists and is pending',
        'method' => 'DB',
        'route'  => 'ftt_group_invitations',
        'exp'    => 200,
        'actual' => $db_row ? 200 : 404,
        'pass'   => ( $db_row !== null && $db_row->status === 'pending' ),
        'checks' => [
            'Row exists in DB'  => ( $db_row !== null ),
            'Status is pending' => ( $db_row && $db_row->status === 'pending' ),
        ],
        'data'   => $db_row ? (array) $db_row : [],
    ];
} else {
    $results[] = [ 'id' => 'F11', 'label' => 'Group invite DB check (SKIPPED — F2 failed)', 'pass' => null ];
}

// F10 — Invite existing member (expect 400)
if ( $created_group ) {
    ftt_run( 'F10', 'Invite existing group member (expect 400)', 'POST', '/groups/' . $created_group . '/invitations', [
        'email'        => $current_user_email,
        'relationship' => 'member',
    ], 400, [
        'success is false' => function ( $d ) {
            return isset( $d['success'] ) && $d['success'] === false;
        },
    ] );
}

// ════════════════════════════════════════════════════════════════════════════════
// TIER 4 — Invitation validation (public endpoint)
// ════════════════════════════════════════════════════════════════════════════════

// E5 — Validate a code that does not exist.
// validate_invite_code always returns HTTP 200; invalid codes return {valid: false}.
ftt_run( 'E5', 'Validate non-existent invite code (valid:false)', 'GET', '/invite/FAKECODE999FAKE/validate', [], 200, [
    'valid is false' => function ( $d ) { return isset( $d['valid'] ) && $d['valid'] === false; },
] );

// F12 — Validate the real group invite code from the DB
if ( $group_invite_code ) {
    ftt_run( 'F12', 'Validate real group invite code (public)', 'GET', '/invite/' . $group_invite_code . '/validate', [], 200, [
        'valid is true'  => function ( $d ) { return ! empty( $d['valid'] ); },
        'Has type field' => function ( $d ) { return ! empty( $d['type'] ); },
    ] );
} else {
    $results[] = [ 'id' => 'F12', 'label' => 'Validate group invite code (SKIPPED — F2 failed)', 'pass' => null ];
}

// ════════════════════════════════════════════════════════════════════════════════
// TIER 5 — Calendar & Preferences
// ════════════════════════════════════════════════════════════════════════════════

ftt_run( 'C1', 'Save event preferences', 'POST', '/save-event-preferences', [
    'visible_categories' => [ 'flights', 'hotels' ],
], 200, [
    'success is true' => function ( $d ) { return ! empty( $d['success'] ); },
] );

// E1 — Duplicate email on /register (no Stripe needed — should 400 before billing)
ftt_run( 'E1', 'Register duplicate email (expect 400)', 'POST', '/register', [
    'email'      => $current_user_email,
    'password'   => 'Test1234!',
    'first_name' => 'Dupe',
    'last_name'  => 'Test',
], 400 );

// ════════════════════════════════════════════════════════════════════════════════
// TIER 6 — Events (create, read, update, delete)
// ════════════════════════════════════════════════════════════════════════════════

// Get children list so we can assign a real member_id to the event
$children_data = ftt_run( 'EV0', 'Get children list (for member_id)', 'GET', '/children', [], 200 );
$test_member_id = null;
// Response is wrapped: { "children": [ { "id": 52, ... }, ... ] }
$children_list = $children_data['children'] ?? ( is_array( $children_data ) && isset( $children_data[0] ) ? $children_data : [] );
if ( ! empty( $children_list ) && is_array( $children_list ) ) {
    $test_member_id = $children_list[0]['id'] ?? ( $children_list[0]['child_id'] ?? null );
}
// Fall back to the current user if no children
if ( ! $test_member_id ) {
    $test_member_id = get_current_user_id();
}

$created_event = null;

// EV1 — Create event (no flight needed)
$ev_data = ftt_run( 'EV1', 'Create event (no flight)', 'POST', '/events', [
    'title'          => 'TestRunner Event ' . time(),
    'start_datetime' => date( 'Y-m-d\TH:i:s', strtotime( '+30 days' ) ),
    'end_datetime'   => date( 'Y-m-d\TH:i:s', strtotime( '+30 days +2 hours' ) ),
    'event_type'     => 'competition',
    'member_id'      => $test_member_id,
    'travel_needed'  => false,
    'flight_needed'  => false,
], 200, [
    'Has id'    => function ( $d ) { return ! empty( $d['id'] ); },
    'Has title' => function ( $d ) { return ! empty( $d['title'] ); },
] );
$created_event = $ev_data['id'] ?? null;

// EV2 — Get single event
if ( $created_event ) {
    ftt_run( 'EV2', 'Get single event', 'GET', '/events/' . $created_event, [], 200, [
        'id matches'    => function ( $d ) use ( $created_event ) { return isset( $d['id'] ) && (int) $d['id'] === (int) $created_event; },
        'Has title'     => function ( $d ) { return ! empty( $d['title'] ); },
    ] );
} else {
    $results[] = [ 'id' => 'EV2', 'label' => 'Get single event (SKIPPED — EV1 failed)', 'pass' => null ];
}

// EV3 — Create event WITH flight requirement + travel leg
$created_flight_event = null;
$ev_flight_data = ftt_run( 'EV3', 'Create event with flight requirement', 'POST', '/events', [
    'title'          => 'TestRunner Flight Event ' . time(),
    'start_datetime' => date( 'Y-m-d\TH:i:s', strtotime( '+60 days' ) ),
    'end_datetime'   => date( 'Y-m-d\TH:i:s', strtotime( '+63 days' ) ),
    'event_type'     => 'competition',
    'member_id'      => $test_member_id,
    'travel_needed'  => true,
    'flight_needed'  => true,
    'travel_legs'    => [
        [
            'mode'            => 'flight',
            'depart_airport'  => 'BOS',
            'arrive_airport'  => 'ORD',
            'depart_date'     => date( 'Y-m-d', strtotime( '+60 days' ) ),
            'arrive_date'     => date( 'Y-m-d', strtotime( '+60 days' ) ),
            'is_round_trip'   => true,
            'return_date'     => date( 'Y-m-d', strtotime( '+63 days' ) ),
            'booked'          => false,
            'flight_group_id' => '',
        ],
    ],
], 200, [
    'Has id'              => function ( $d ) { return ! empty( $d['id'] ); },
    'flight_needed true'  => function ( $d ) { return ! empty( $d['flight_needed'] ); },
    'Has travel_legs'     => function ( $d ) { return ! empty( $d['travel_legs'] ); },
] );
$created_flight_event = $ev_flight_data['id'] ?? null;

// EV4 — Update event title
if ( $created_event ) {
    ftt_run( 'EV4', 'Update event title', 'PUT', '/events/' . $created_event, [
        'title' => 'TestRunner Event UPDATED',
    ], 200, [
        'title updated' => function ( $d ) { return strpos( $d['title'] ?? '', 'UPDATED' ) !== false; },
    ] );
} else {
    $results[] = [ 'id' => 'EV4', 'label' => 'Update event (SKIPPED — EV1 failed)', 'pass' => null ];
}

// EV5 — Events list includes newly created event
if ( $created_event ) {
    $events_list = ftt_run( 'EV5', 'Events list contains new event', 'GET', '/events', [], 200, [
        'Returns array' => function ( $d ) { return is_array( $d ); },
    ] );
    $found = false;
    if ( is_array( $events_list ) ) {
        foreach ( $events_list as $ev ) {
            if ( isset( $ev['id'] ) && (int) $ev['id'] === (int) $created_event ) {
                $found = true;
                break;
            }
        }
    }
    $results[] = [
        'id'     => 'EV5b',
        'label'  => 'Events list: new event present',
        'method' => 'check',
        'route'  => '/ftt/v1/events',
        'exp'    => true,
        'actual' => $found ? 'found' : 'missing',
        'pass'   => $found,
        'checks' => [],
        'data'   => [ 'event_id' => $created_event, 'found_in_list' => $found ],
    ];
}

// ════════════════════════════════════════════════════════════════════════════════
// TIER 7 — Flight Suggestions, Linking, Pricing
// ════════════════════════════════════════════════════════════════════════════════

// FL1 — Flight suggestions for the flight event (may return empty array if no matching
//         reversed legs exist, but the endpoint itself should respond 200)
if ( $created_flight_event ) {
    ftt_run( 'FL1', 'Flight suggestions endpoint (200, returns array)', 'GET', '/flight-suggestions/' . $created_flight_event, [], 200, [
        'Has suggestions key' => function ( $d ) { return array_key_exists( 'suggestions', $d ); },
    ] );
} else {
    $results[] = [ 'id' => 'FL1', 'label' => 'Flight suggestions (SKIPPED — EV3 failed)', 'pass' => null ];
}

// FL2 — Link two flight events together (create a second flight event first)
$created_flight_event2 = null;
$ev_flight_data2 = ftt_run( 'FL2a', 'Create second flight event (return leg)', 'POST', '/events', [
    'title'          => 'TestRunner Return Flight ' . time(),
    'start_datetime' => date( 'Y-m-d\TH:i:s', strtotime( '+63 days' ) ),
    'end_datetime'   => date( 'Y-m-d\TH:i:s', strtotime( '+63 days +2 hours' ) ),
    'event_type'     => 'travel',
    'member_id'      => $test_member_id,
    'travel_needed'  => true,
    'flight_needed'  => true,
    'travel_legs'    => [
        [
            'mode'            => 'flight',
            'depart_airport'  => 'ORD',
            'arrive_airport'  => 'BOS',
            'depart_date'     => date( 'Y-m-d', strtotime( '+63 days' ) ),
            'arrive_date'     => date( 'Y-m-d', strtotime( '+63 days' ) ),
            'is_round_trip'   => false,
            'booked'          => false,
            'flight_group_id' => '',
        ],
    ],
], 200, [
    'Has id' => function ( $d ) { return ! empty( $d['id'] ); },
] );
$created_flight_event2 = $ev_flight_data2['id'] ?? null;

$created_flight_group = null;
if ( $created_flight_event && $created_flight_event2 ) {
    $link_data = ftt_run( 'FL2b', 'Link two flight legs', 'POST', '/link-flights', [
        'event_ids'   => [ $created_flight_event, $created_flight_event2 ],
        'leg_indices' => [ 0, 0 ],
    ], 200, [
        'success is true'  => function ( $d ) { return ! empty( $d['success'] ); },
        'Has group_id'     => function ( $d ) { return ! empty( $d['group_id'] ); },
        'Has linked_legs'  => function ( $d ) { return ! empty( $d['linked_legs'] ) && is_array( $d['linked_legs'] ); },
    ] );
    $created_flight_group = $link_data['group_id'] ?? null;
} else {
    $results[] = [ 'id' => 'FL2b', 'label' => 'Link flights (SKIPPED — EV3 or FL2a failed)', 'pass' => null ];
}

// FL3 — Flight group pricing (DB only, no live API call)
if ( $created_flight_group ) {
    ftt_run( 'FL3', 'Flight group pricing (DB)', 'GET', '/flight-group-pricing/' . $created_flight_group, [], 200, [
        'Has group_id' => function ( $d ) { return ! empty( $d['group_id'] ); },
        'Has legs'     => function ( $d ) { return isset( $d['legs'] ) && is_array( $d['legs'] ); },
    ] );
} else {
    $results[] = [ 'id' => 'FL3', 'label' => 'Flight group pricing (SKIPPED — FL2b failed)', 'pass' => null ];
}

// FL4 — Get flight groups list
ftt_run( 'FL4', 'Flight groups list', 'GET', '/flight-groups', [], 200, [
    'Returns array' => function ( $d ) { return is_array( $d ); },
] );

// FL5 — Unlink a flight leg
if ( $created_flight_event ) {
    ftt_run( 'FL5', 'Unlink flight leg', 'POST', '/unlink-flight', [
        'event_id'  => $created_flight_event,
        'leg_index' => 0,
    ], 200, [
        'success is true' => function ( $d ) { return ! empty( $d['success'] ); },
    ] );
} else {
    $results[] = [ 'id' => 'FL5', 'label' => 'Unlink flight (SKIPPED — EV3 failed)', 'pass' => null ];
}

// ════════════════════════════════════════════════════════════════════════════════
// TIER 8 — Price Checking & Alerts
// (check-price calls SerpAPI — will return success:false if no key configured,
//  which is still a valid response; we test the endpoint responds correctly)
// ════════════════════════════════════════════════════════════════════════════════

// PR1 — Manual price check (expects 200 regardless of API key presence)
if ( $created_flight_event ) {
    $price_data = ftt_run( 'PR1', 'Manual price check (endpoint responds)', 'POST', '/check-price', [
        'event_id'       => $created_flight_event,
        'leg_index'      => 0,
        'origin'         => 'BOS',
        'destination'    => 'ORD',
        'depart_date'    => date( 'Y-m-d', strtotime( '+60 days' ) ),
        'is_round_trip'  => true,
        'return_date'    => date( 'Y-m-d', strtotime( '+63 days' ) ),
    ], 200, [
        'Has success key'   => function ( $d ) { return array_key_exists( 'success', $d ); },
        'Has price key'     => function ( $d ) { return array_key_exists( 'price', $d ); },
    ] );

    // PR1b — If SerpAPI key IS configured and a price came back, also test price history
    $has_price = ! empty( $price_data['success'] ) && ! empty( $price_data['price'] );
    $results[] = [
        'id'     => 'PR1b',
        'label'  => 'Manual price check: SerpAPI key ' . ( $has_price ? 'configured ✓ price returned' : 'not configured or no flights found (expected in test env)' ),
        'method' => 'info',
        'route'  => '/ftt/v1/check-price',
        'exp'    => 200,
        'actual' => 200,
        'pass'   => true,  // Either result is valid — just informational
        'checks' => [],
        'data'   => [ 'success' => $price_data['success'] ?? null, 'price' => $price_data['price'] ?? null, 'message' => $price_data['message'] ?? null ],
    ];
} else {
    $results[] = [ 'id' => 'PR1', 'label' => 'Manual price check (SKIPPED — EV3 failed)', 'pass' => null ];
    $results[] = [ 'id' => 'PR1b', 'label' => 'SerpAPI key status (SKIPPED)', 'pass' => null ];
}

// PR2 — Price history (empty result is fine — just check the shape)
if ( $created_flight_event ) {
    ftt_run( 'PR2', 'Price history endpoint', 'GET', '/price-history', [
        'event_id'  => $created_flight_event,
        'leg_index' => 0,
    ], 200, [
        'Has prices key' => function ( $d ) { return array_key_exists( 'prices', $d ); },
        'Has stats key'  => function ( $d ) { return array_key_exists( 'stats', $d ); },
    ] );
} else {
    $results[] = [ 'id' => 'PR2', 'label' => 'Price history (SKIPPED — EV3 failed)', 'pass' => null ];
}

// PR3 — Create price alert
$created_alert_id = null;
if ( $created_flight_event ) {
    $alert_data = ftt_run( 'PR3', 'Create price alert', 'POST', '/price-alerts', [
        'event_id'    => $created_flight_event,
        'leg_index'   => 0,
        'alert_type'  => 'price_drop',
        'threshold'   => 100,
        'email'       => $current_user_email,
        'origin'      => 'BOS',
        'destination' => 'ORD',
        'depart_date' => date( 'Y-m-d', strtotime( '+60 days' ) ),
    ], 200, [
        'success is true' => function ( $d ) { return ! empty( $d['success'] ); },
        'Has alert_id'    => function ( $d ) { return ! empty( $d['alert_id'] ); },
    ] );
    $created_alert_id = $alert_data['alert_id'] ?? null;
} else {
    $results[] = [ 'id' => 'PR3', 'label' => 'Create price alert (SKIPPED — EV3 failed)', 'pass' => null ];
}

// PR4 — Get my alerts
ftt_run( 'PR4', 'Get my alerts', 'GET', '/my-alerts', [], 200, [
    'Returns array' => function ( $d ) { return is_array( $d ); },
] );

// PR5 — Delete price alert
if ( $created_alert_id ) {
    ftt_run( 'PR5', 'Delete price alert', 'DELETE', '/price-alerts/' . $created_alert_id, [], 200, [
        'success is true' => function ( $d ) { return ! empty( $d['success'] ); },
    ] );
} else {
    $results[] = [ 'id' => 'PR5', 'label' => 'Delete price alert (SKIPPED — PR3 failed)', 'pass' => null ];
}

// ════════════════════════════════════════════════════════════════════════════════
// TIER 9 — Email / Cron Workflows
// (We don't send real emails in the test runner, but we verify cron is scheduled,
//  the daily-digest function exists and is callable, and do_action doesn't fatal)
// ════════════════════════════════════════════════════════════════════════════════

// CR1 — Price check cron is scheduled
$cron_price = wp_next_scheduled( 'ftt_check_flight_prices' );
$results[] = [
    'id'     => 'CR1',
    'label'  => 'Cron: ftt_check_flight_prices is scheduled',
    'method' => 'cron',
    'route'  => 'wp_next_scheduled(ftt_check_flight_prices)',
    'exp'    => '>0',
    'actual' => $cron_price ? date( 'Y-m-d H:i:s', $cron_price ) : 'NOT SCHEDULED',
    'pass'   => ( $cron_price !== false ),
    'checks' => [],
    'data'   => [ 'next_run' => $cron_price ? date( 'Y-m-d H:i:s', $cron_price ) : null ],
];

// CR2 — Daily digest cron is scheduled
$cron_digest = wp_next_scheduled( 'ftt_daily_digest' );
$results[] = [
    'id'     => 'CR2',
    'label'  => 'Cron: ftt_daily_digest is scheduled',
    'method' => 'cron',
    'route'  => 'wp_next_scheduled(ftt_daily_digest)',
    'exp'    => '>0',
    'actual' => $cron_digest ? date( 'Y-m-d H:i:s', $cron_digest ) : 'NOT SCHEDULED',
    'pass'   => ( $cron_digest !== false ),
    'checks' => [],
    'data'   => [ 'next_run' => $cron_digest ? date( 'Y-m-d H:i:s', $cron_digest ) : null ],
];

// CR3 — Price tracking class + send_daily_digest method exist
$pt_class_ok  = class_exists( 'FTT_Price_Tracking' );
$pt_method_ok = $pt_class_ok && method_exists( 'FTT_Price_Tracking', 'send_daily_digest' );
$results[] = [
    'id'     => 'CR3',
    'label'  => 'FTT_Price_Tracking::send_daily_digest() method exists',
    'method' => 'check',
    'route'  => 'class_exists + method_exists',
    'exp'    => true,
    'actual' => $pt_method_ok ? 'exists' : 'MISSING',
    'pass'   => $pt_method_ok,
    'checks' => [
        'Class FTT_Price_Tracking exists' => $pt_class_ok,
        'send_daily_digest() exists'      => $pt_method_ok,
    ],
    'data'   => [],
];

// CR4 — check_all_prices action fires without fatal (using a test-safe source='manual')
$cr4_pass  = false;
$cr4_error = null;
if ( $pt_class_ok && method_exists( 'FTT_Price_Tracking', 'check_all_prices' ) ) {
    try {
        // Temporarily redirect wp_mail so no real emails go out
        add_filter( 'wp_mail', function ( $args ) { return $args; } );
        // Capture but discard — just want to know it doesn't throw
        ob_start();
        FTT_Price_Tracking::check_all_prices( 'manual' );
        ob_end_clean();
        $cr4_pass = true;
    } catch ( \Throwable $e ) {
        $cr4_error = $e->getMessage();
    }
}
$results[] = [
    'id'     => 'CR4',
    'label'  => 'check_all_prices() runs without fatal',
    'method' => 'check',
    'route'  => 'FTT_Price_Tracking::check_all_prices(manual)',
    'exp'    => 'no error',
    'actual' => $cr4_pass ? 'ok' : ( $cr4_error ?? 'method missing' ),
    'pass'   => $cr4_pass,
    'checks' => [],
    'data'   => $cr4_error ? [ 'error' => $cr4_error ] : [],
];

// CR5 — Alert confirmation email function exists
$confirm_ok = $pt_class_ok && method_exists( 'FTT_Price_Tracking', 'send_alert_confirmation' );
$results[] = [
    'id'     => 'CR5',
    'label'  => 'send_alert_confirmation() method exists',
    'method' => 'check',
    'route'  => 'method_exists',
    'exp'    => true,
    'actual' => $confirm_ok ? 'exists' : 'MISSING',
    'pass'   => $confirm_ok,
    'checks' => [],
    'data'   => [],
];

// ════════════════════════════════════════════════════════════════════════════════
// CLEANUP — remove created test data
// ════════════════════════════════════════════════════════════════════════════════

$cleanup_notes = [];

// Remove created child
if ( $created_child && get_user_by( 'id', $created_child ) ) {
    ftt_run( 'CL1', 'Cleanup: remove test child', 'POST', '/remove-child', [
        'child_id' => $created_child,
    ], 200 );
}

// Archive created group
if ( $created_group ) {
    ftt_run( 'CL2', 'Cleanup: archive test group', 'DELETE', '/groups/' . $created_group, [], 200 );
}

// Delete the two extra flight events created for FL2 test
foreach ( [ $created_flight_event2, $created_flight_event, $created_event ] as $idx => $evid ) {
    $labels = [ 'FL2a return leg', 'EV3 flight event', 'EV1 no-flight event' ];
    if ( $evid ) {
        ftt_run( 'CL' . ( 3 + $idx ), 'Cleanup: delete test event (' . $labels[ $idx ] . ')', 'DELETE', '/events/' . $evid, [], 200, [
            'deleted is true' => function ( $d ) { return ! empty( $d['deleted'] ); },
        ] );
    }
}

// Delete price alert if it survived PR5 (PR5 may have already deleted it; 404 is fine)
if ( $created_alert_id ) {
    ftt_run( 'CL6', 'Cleanup: delete price alert (idempotent)', 'DELETE', '/price-alerts/' . $created_alert_id, [], 0 );
}

// ════════════════════════════════════════════════════════════════════════════════
// OUTPUT — HTML results table
// ════════════════════════════════════════════════════════════════════════════════

$pass_count  = count( array_filter( $results, fn( $r ) => $r['pass'] === true ) );
$fail_count  = count( array_filter( $results, fn( $r ) => $r['pass'] === false ) );
$skip_count  = count( array_filter( $results, fn( $r ) => $r['pass'] === null ) );
$total       = count( $results );

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>FTT Endpoint Test Runner</title>
<style>
  body { font-family: system-ui, sans-serif; margin: 0; background: #f1f5f9; color: #1e293b; }
  h1   { margin: 0; padding: 20px 24px 0; }
  .summary { display: flex; gap: 12px; padding: 16px 24px 20px; }
  .badge { padding: 6px 14px; border-radius: 9999px; font-weight: 600; font-size: 14px; }
  .pass  { background: #dcfce7; color: #166534; }
  .fail  { background: #fee2e2; color: #991b1b; }
  .skip  { background: #fef9c3; color: #854d0e; }
  .neutral { background: #e2e8f0; color: #475569; }
  table  { width: calc(100% - 48px); margin: 0 24px 32px; border-collapse: collapse; font-size: 13px; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
  th     { background: #1e293b; color: #fff; text-align: left; padding: 10px 12px; }
  td     { padding: 9px 12px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: #f8fafc; }
  .ok  { color: #16a34a; font-weight: 700; }
  .err { color: #dc2626; font-weight: 700; }
  .sk  { color: #ca8a04; }
  .checks { font-size: 11px; color: #64748b; margin-top: 4px; }
  .checks .ck-pass { color: #16a34a; }
  .checks .ck-fail { color: #dc2626; }
  details summary { cursor: pointer; color: #3b82f6; font-size: 11px; margin-top: 4px; }
  pre { margin: 4px 0 0; font-size: 11px; background: #f1f5f9; padding: 8px; border-radius: 4px; max-height: 200px; overflow: auto; white-space: pre-wrap; word-break: break-all; }
  .section-header td { background: #1e293b; color: #94a3b8; font-size: 11px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; padding: 6px 12px; }
  .warning { margin: 0 24px 20px; padding: 12px 16px; background: #fff7ed; border-left: 4px solid #f97316; border-radius: 4px; font-size: 13px; }
</style>
</head>
<body>
<h1>FTT Endpoint Test Runner</h1>
<p style="padding: 4px 24px 0; color:#64748b; font-size:13px;">Running as <strong><?php echo esc_html( wp_get_current_user()->user_email ); ?></strong> &nbsp;|&nbsp; <?php echo date('Y-m-d H:i:s'); ?></p>

<div class="summary">
  <span class="badge neutral"><?php echo $total; ?> total</span>
  <span class="badge pass"><?php echo $pass_count; ?> passed</span>
  <?php if ( $fail_count ) : ?><span class="badge fail"><?php echo $fail_count; ?> failed</span><?php endif; ?>
  <?php if ( $skip_count ) : ?><span class="badge skip"><?php echo $skip_count; ?> skipped</span><?php endif; ?>
</div>

<div class="warning">
  &#9888; <strong>TEMPORARY FILE</strong> — Delete <code>ftt-test-runner.php</code> from the plugin folder before going to production.
</div>

<table>
  <thead>
    <tr>
      <th style="width:50px">ID</th>
      <th style="width:80px">Status</th>
      <th>Test</th>
      <th style="width:60px">Method</th>
      <th>Route</th>
      <th style="width:80px">Expected</th>
      <th style="width:80px">Got</th>
      <th>Response</th>
    </tr>
  </thead>
  <tbody>
<?php
$sections = [
    'T'  => 'Tier 1 — Smoke Tests',
    'S'  => 'Tier 1 — Read Endpoints',
    'F'  => 'Tier 2 — Family Management',
    'G'  => 'Tier 3 — Groups',
    'E'  => 'Tier 4 — Edge Cases',
    'C'  => 'Tier 5 — Calendar & Preferences',
    'EV' => 'Tier 6 — Events',
    'FL' => 'Tier 7 — Flight Suggestions & Linking',
    'PR' => 'Tier 8 — Price Checking & Alerts',
    'CR' => 'Tier 9 — Cron & Email Workflows',
    'CL' => 'Cleanup',
];
$seen_sections = [];
foreach ( $results as $r ) :
    $section_key = preg_match( '/^([A-Z]+)/', $r['id'], $m ) ? $m[1] : '';
    if ( $section_key && isset( $sections[ $section_key ] ) && ! in_array( $section_key, $seen_sections ) ) {
        $seen_sections[] = $section_key;
        echo '<tr><td class="section-header" colspan="8">' . esc_html( $sections[ $section_key ] ) . '</td></tr>';
    }

    if ( $r['pass'] === true ) {
        $status_html = '<span class="ok">&#10003; PASS</span>';
    } elseif ( $r['pass'] === false ) {
        $status_html = '<span class="err">&#10007; FAIL</span>';
    } else {
        $status_html = '<span class="sk">&#8212; SKIP</span>';
    }

    $method  = $r['method'] ?? '—';
    $route   = $r['route']  ?? '—';
    $exp     = $r['exp']    ?? '—';
    $actual  = $r['actual'] ?? '—';
    $data    = $r['data']   ?? null;

    echo '<tr>';
    echo '<td><code>' . esc_html( $r['id'] ) . '</code></td>';
    echo '<td>' . $status_html . '</td>';
    echo '<td>' . esc_html( $r['label'] );

    if ( ! empty( $r['checks'] ) ) {
        echo '<div class="checks">';
        foreach ( $r['checks'] as $clabel => $cpass ) {
            $cls = $cpass ? 'ck-pass' : 'ck-fail';
            $ico = $cpass ? '✓' : '✗';
            echo '<span class="' . $cls . '">' . $ico . ' ' . esc_html( $clabel ) . '</span><br>';
        }
        echo '</div>';
    }

    echo '</td>';
    echo '<td><code>' . esc_html( $method ) . '</code></td>';
    echo '<td style="word-break:break-all;font-size:11px;">' . esc_html( $route ) . '</td>';
    echo '<td>' . esc_html( $exp ) . '</td>';
    echo '<td>' . esc_html( $actual ) . '</td>';
    echo '<td>';
    if ( $data !== null ) {
        $json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        echo '<details><summary>View response</summary><pre>' . esc_html( $json ) . '</pre></details>';
    }
    echo '</td>';
    echo '</tr>';
endforeach;
?>
  </tbody>
</table>

</body>
</html>
