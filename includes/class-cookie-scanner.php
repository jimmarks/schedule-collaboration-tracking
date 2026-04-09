<?php
/**
 * Cookie Scanner
 *
 * Provides two complementary scanning modes so admins can build a complete,
 * accurate cookie inventory for their privacy/cookie policy:
 *
 *  1. Server scan  — fetches site pages via wp_remote_get() and collects all
 *                    Set-Cookie response headers. Catches PHP-set cookies on
 *                    bare page loads (no login required on the scanned pages).
 *
 *  2. Browser scan — when a logged-in admin visits any front-end URL with the
 *                    query string '?ftt_cookie_scan' appended, a small inline
 *                    script waits 3 seconds (giving GA4, Stripe JS, etc. time
 *                    to run) then reads document.cookie and POSTs the full list
 *                    back to the admin page via AJAX.
 *
 * Results are stored in the wp_option 'ftt_cookie_scan_results' and displayed
 * on the Cookie Scanner page under FTT Events → Cookie Scanner.
 *
 * @package Family_Travel_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FTT_Cookie_Scanner {

    const MENU_SLUG   = 'ftt-cookie-scanner';
    const OPT_RESULTS = 'ftt_cookie_scan_results';
    const SCAN_PARAM  = 'ftt_cookie_scan';

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'wp_ajax_ftt_cookie_scan_report', [ __CLASS__, 'ajax_report'      ] );
        add_action( 'wp_ajax_ftt_cookie_scan_clear',  [ __CLASS__, 'ajax_clear'       ] );
        add_action( 'wp_ajax_ftt_cookie_scan_server', [ __CLASS__, 'ajax_server_scan' ] );
        add_action( 'wp_footer',                      [ __CLASS__, 'maybe_inject_scanner' ] );
    }

    // -------------------------------------------------------------------------
    // Admin menu
    // -------------------------------------------------------------------------

    public static function add_menu() {
        add_submenu_page(
            'edit.php?post_type=ftt_event',
            __( 'Cookie Scanner', 'schedule-collaboration-tracking' ),
            __( 'Cookie Scanner', 'schedule-collaboration-tracking' ),
            'manage_options',
            self::MENU_SLUG,
            [ __CLASS__, 'render_page' ]
        );
    }

    // -------------------------------------------------------------------------
    // Admin page
    // -------------------------------------------------------------------------

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Access denied.', 'schedule-collaboration-tracking' ) );
        }

        $results = (array) get_option( self::OPT_RESULTS, [] );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Cookie Scanner', 'schedule-collaboration-tracking' ); ?></h1>
            <p style="max-width:700px;">
                <?php esc_html_e( 'Discover every cookie your site sets — including third-party JS cookies from Google Analytics, Stripe, etc. Use the results to build an accurate cookie policy.', 'schedule-collaboration-tracking' ); ?>
            </p>

            <!-- Browser scan — primary, full width -->
            <div style="background:#fff;border:2px solid #6A3E8E;border-radius:4px;padding:20px;margin-bottom:16px;max-width:900px;">
                <h2 style="margin-top:0;font-size:15px;color:#6A3E8E;">🌐 <?php esc_html_e( 'Browser Scan — recommended', 'schedule-collaboration-tracking' ); ?></h2>
                <p style="color:#444;font-size:13px;line-height:1.5;margin-bottom:14px;">
                    <?php esc_html_e( 'Most cookies (Google Analytics, Stripe, consent banner, etc.) are set by JavaScript and invisible to server-side fetches. Click any page below to open it in a new tab — a 3-second timer runs, all JS fires normally, then every cookie in your browser is automatically reported back here.', 'schedule-collaboration-tracking' ); ?>
                </p>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                <?php
                $scan_pages = self::get_pages_to_scan();
                foreach ( $scan_pages as $label => $url ) :
                    $scan_url = add_query_arg( self::SCAN_PARAM, '1', $url );
                ?>
                    <a href="<?php echo esc_url( $scan_url ); ?>" target="_blank" class="button button-primary"
                       style="background:#6A3E8E;border-color:#5B347A;text-decoration:none;">
                        <?php echo esc_html( $label ); ?> ↗
                    </a>
                <?php endforeach; ?>
                </div>
                <p style="font-size:12px;color:#999;margin:0;">
                    <?php esc_html_e( 'Tip: append ?ftt_cookie_scan to your pricing, checkout, or any page you want to scan.', 'schedule-collaboration-tracking' ); ?>
                </p>
            </div>

            <!-- Server scan — secondary, collapsed -->
            <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;margin-bottom:24px;max-width:900px;">
                <details>
                    <summary style="cursor:pointer;font-size:13px;font-weight:600;color:#666;user-select:none;">
                        🖥️ <?php esc_html_e( 'Server Scan (PHP / Set-Cookie headers only)', 'schedule-collaboration-tracking' ); ?>
                    </summary>
                    <p style="color:#888;font-size:13px;line-height:1.5;margin:12px 0;">
                        <?php esc_html_e( 'Fetches your pages server-to-server and records HTTP Set-Cookie response headers. Only catches cookies set by PHP before the page is sent — cannot see JavaScript-set cookies (GA4, Stripe, consent banner). Most sites return 0 results here, which is normal.', 'schedule-collaboration-tracking' ); ?>
                    </p>
                    <button id="ftt-server-scan-btn" class="button">
                        <?php esc_html_e( 'Run Server Scan', 'schedule-collaboration-tracking' ); ?>
                    </button>
                    <span id="ftt-server-scan-status" style="margin-left:10px;color:#666;font-size:13px;"></span>
                </details>
            </div>

            <!-- Results -->
            <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px;max-width:1200px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h2 style="margin:0;font-size:15px;">
                        <?php esc_html_e( 'Discovered Cookies', 'schedule-collaboration-tracking' ); ?>
                        &nbsp;<span id="ftt-scan-count" style="font-weight:normal;color:#666;">(<?php echo count( $results ); ?> unique)</span>
                    </h2>
                    <div style="display:flex;gap:8px;">
                        <button id="ftt-scan-export-btn" class="button"><?php esc_html_e( 'Export CSV', 'schedule-collaboration-tracking' ); ?></button>
                        <button id="ftt-scan-clear-btn" class="button"><?php esc_html_e( 'Clear All', 'schedule-collaboration-tracking' ); ?></button>
                    </div>
                </div>
                <div id="ftt-scan-results-wrap">
                    <?php self::render_results_table( $results ); ?>
                </div>
            </div>
        </div>

        <script>
        (function ($) {
            var nonce   = '<?php echo wp_create_nonce( 'ftt_cookie_scanner' ); ?>';
            var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

            // -----------------------------------------------------------------
            // Server scan
            // -----------------------------------------------------------------
            $('#ftt-server-scan-btn').on('click', function () {
                var $btn    = $(this);
                var $status = $('#ftt-server-scan-status');
                $btn.prop('disabled', true).text('Scanning…');
                $status.css('color', '#666').text('Fetching pages…');

                $.post(ajaxUrl, { action: 'ftt_cookie_scan_server', nonce: nonce }, function (res) {
                    $btn.prop('disabled', false).text('Run Server Scan');
                    if (res.success) {
                        if (res.data.found === 0) {
                            $status.css('color', '#888').html(
                                'Done &mdash; 0 PHP-set cookies found across ' + res.data.pages + ' page(s). ' +
                                'Normal for JS-heavy sites &mdash; use the <strong>Browser Scan</strong> above.'
                            );
                        } else {
                            $status.css('color', '#46b450').text(
                                'Done — ' + res.data.found + ' new cookie(s) found across ' + res.data.pages + ' page(s).'
                            );
                        }
                        refreshTable(res.data.table_html, res.data.count);
                    } else {
                        $status.css('color', '#d63638').text('Failed: ' + (res.data || 'unknown error'));
                    }
                }).fail(function () {
                    $btn.prop('disabled', false).text('Run Server Scan');
                    $status.css('color', '#d63638').text('Request failed. Check the browser console.');
                });
            });

            // -----------------------------------------------------------------
            // Auto-poll every 6 seconds to pick up browser scan results
            // -----------------------------------------------------------------
            var lastCount = <?php echo count( $results ); ?>;
            setInterval(function () {
                $.post(ajaxUrl, { action: 'ftt_cookie_scan_server', nonce: nonce, poll_only: 1 }, function (res) {
                    if (res.success && res.data.count !== lastCount) {
                        lastCount = res.data.count;
                        refreshTable(res.data.table_html, res.data.count);
                    }
                });
            }, 6000);

            // -----------------------------------------------------------------
            // Clear
            // -----------------------------------------------------------------
            $('#ftt-scan-clear-btn').on('click', function () {
                if (!confirm('Clear all discovered cookies?')) return;
                $.post(ajaxUrl, { action: 'ftt_cookie_scan_clear', nonce: nonce }, function () {
                    refreshTable('<p style="color:#666;">Results cleared. Run a scan to discover cookies.</p>', 0);
                });
            });

            // -----------------------------------------------------------------
            // CSV export
            // -----------------------------------------------------------------
            $('#ftt-scan-export-btn').on('click', function () {
                var rows = [['Cookie Name', 'Likely Source', 'Discovered On', 'Scan Type', 'Value (preview)', 'First Seen']];
                $('#ftt-scan-results-wrap tr[data-cookie]').each(function () {
                    var cells = $(this).find('td');
                    rows.push([
                        $(this).data('cookie'),
                        cells.eq(1).text().trim(),
                        cells.eq(2).text().trim(),
                        cells.eq(3).text().trim(),
                        cells.eq(4).text().trim(),
                        cells.eq(5).text().trim(),
                    ]);
                });
                var csv = rows.map(function (r) {
                    return r.map(function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; }).join(',');
                }).join('\n');
                var blob = new Blob([csv], { type: 'text/csv' });
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'cookie-scan-' + new Date().toISOString().slice(0, 10) + '.csv';
                a.click();
            });

            function refreshTable(html, count) {
                $('#ftt-scan-results-wrap').html(html);
                $('#ftt-scan-count').text('(' + count + ' unique)');
            }

        }(jQuery));
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // Results table partial
    // -------------------------------------------------------------------------

    private static function render_results_table( $results ) {
        if ( empty( $results ) ) {
            echo '<p style="color:#666;">No cookies discovered yet. Run a server scan or visit pages using the browser scan links above.</p>';
            return;
        }
        ?>
        <table class="wp-list-table widefat fixed striped" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="width:200px;"><?php esc_html_e( 'Cookie Name', 'schedule-collaboration-tracking' ); ?></th>
                    <th style="width:150px;"><?php esc_html_e( 'Likely Source', 'schedule-collaboration-tracking' ); ?></th>
                    <th><?php esc_html_e( 'Page Found On', 'schedule-collaboration-tracking' ); ?></th>
                    <th style="width:110px;"><?php esc_html_e( 'Scan Method', 'schedule-collaboration-tracking' ); ?></th>
                    <th><?php esc_html_e( 'Value Preview', 'schedule-collaboration-tracking' ); ?></th>
                    <th style="width:130px;"><?php esc_html_e( 'First Seen', 'schedule-collaboration-tracking' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $results as $name => $entry ) : ?>
                <tr data-cookie="<?php echo esc_attr( $name ); ?>">
                    <td><code style="background:#f3f0f8;padding:2px 5px;border-radius:3px;"><?php echo esc_html( $name ); ?></code></td>
                    <td><?php echo esc_html( self::guess_source( $name ) ); ?></td>
                    <td style="font-size:12px;word-break:break-all;"><?php echo esc_html( $entry['url'] ?? '—' ); ?></td>
                    <td>
                        <?php if ( ( $entry['scan_type'] ?? '' ) === 'browser' ) : ?>
                            <span style="color:#1565c0;font-weight:600;">🌐 Browser</span>
                        <?php else : ?>
                            <span style="color:#2e7d32;font-weight:600;">🖥️ Server</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-family:monospace;font-size:11px;color:#555;word-break:break-all;">
                        <?php
                        $val = $entry['value'] ?? '';
                        echo esc_html( strlen( $val ) > 80 ? substr( $val, 0, 80 ) . '…' : $val );
                        ?>
                    </td>
                    <td style="font-size:12px;color:#888;"><?php echo esc_html( $entry['found_at'] ?? '—' ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    // -------------------------------------------------------------------------
    // Source guesser
    // -------------------------------------------------------------------------

    private static function guess_source( string $name ): string {
        $prefixes = [
            'wordpress_logged_in'    => 'WordPress (auth)',
            'wordpress_sec'          => 'WordPress (auth)',
            'wp-settings'            => 'WordPress (prefs)',
            'wp_lang'                => 'WordPress',
            'wordpress_test_cookie'  => 'WordPress',
            'comment_author'         => 'WordPress (comments)',
            'ftt_cookie_consent'     => 'FTT Plugin',
            'ftt_ads_conversion'     => 'FTT Plugin',
            '_ga'                    => 'Google Analytics',
            '_gid'                   => 'Google Analytics',
            '_gat'                   => 'Google Analytics',
            '__utma'                 => 'Google Analytics (UA)',
            '__utmz'                 => 'Google Analytics (UA)',
            '__stripe_mid'           => 'Stripe',
            '__stripe_sid'           => 'Stripe',
            '_fbp'                   => 'Meta Pixel',
            '_fbc'                   => 'Meta Pixel',
            'PHPSESSID'              => 'PHP Session',
            'woocommerce'            => 'WooCommerce',
            'wp_woocommerce'         => 'WooCommerce',
        ];
        foreach ( $prefixes as $prefix => $source ) {
            if ( strpos( $name, $prefix ) === 0 ) {
                return $source;
            }
        }
        return 'Unknown';
    }

    // -------------------------------------------------------------------------
    // AJAX — browser scan report (called from injected front-end JS)
    // -------------------------------------------------------------------------

    public static function ajax_report() {
        check_ajax_referer( 'ftt_cookie_scanner', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $page_url = esc_url_raw( wp_unslash( $_POST['page_url'] ?? '' ) );
        $raw      = wp_unslash( $_POST['cookies'] ?? '[]' );
        $cookies  = json_decode( $raw, true );

        if ( ! is_array( $cookies ) ) {
            wp_send_json_error( 'Invalid payload' );
        }

        $results   = (array) get_option( self::OPT_RESULTS, [] );
        $recorded  = 0;

        foreach ( $cookies as $c ) {
            $name = sanitize_text_field( $c['name'] ?? '' );
            if ( ! $name ) {
                continue;
            }
            if ( ! isset( $results[ $name ] ) ) {
                $results[ $name ] = [
                    'value'     => sanitize_text_field( substr( $c['value'] ?? '', 0, 200 ) ),
                    'url'       => $page_url,
                    'scan_type' => 'browser',
                    'found_at'  => current_time( 'mysql' ),
                ];
                $recorded++;
            }
        }

        update_option( self::OPT_RESULTS, $results );
        wp_send_json_success( [ 'recorded' => $recorded ] );
    }

    // -------------------------------------------------------------------------
    // AJAX — server scan (and poll-only mode for auto-refresh)
    // -------------------------------------------------------------------------

    public static function ajax_server_scan() {
        check_ajax_referer( 'ftt_cookie_scanner', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $results = (array) get_option( self::OPT_RESULTS, [] );

        // Poll-only mode: just return the current table HTML + count (used by auto-refresh)
        if ( ! empty( $_POST['poll_only'] ) ) {
            ob_start();
            self::render_results_table( $results );
            wp_send_json_success( [
                'table_html' => ob_get_clean(),
                'count'      => count( $results ),
            ] );
        }

        // --- Full server scan ---
        $pages     = self::get_pages_to_scan();
        $found_new = 0;

        foreach ( $pages as $url ) {
            try {
                $response = wp_remote_get( $url, [
                    'timeout'    => 15,
                    'sslverify'  => false,
                    'user-agent' => 'FTT-Cookie-Scanner/1.0 (site admin tool)',
                ] );

                if ( is_wp_error( $response ) ) {
                    continue;
                }

                // Extract all Set-Cookie headers safely across WordPress versions.
                // wp_remote_retrieve_header() only returns the last value when multiple
                // Set-Cookie headers are present, so we access the headers object directly.
                $raw_headers = [];
                $headers_obj = wp_remote_retrieve_headers( $response );

                if ( is_object( $headers_obj ) && method_exists( $headers_obj, 'getValues' ) ) {
                    // WP 6.2+ / WpOrg\Requests 2.x
                    $raw_headers = (array) $headers_obj->getValues( 'set-cookie' );
                } elseif ( is_object( $headers_obj ) ) {
                    // Older WordPress: iterate the headers object manually
                    foreach ( $headers_obj as $hname => $hval ) {
                        if ( strtolower( (string) $hname ) === 'set-cookie' ) {
                            if ( is_array( $hval ) ) {
                                $raw_headers = array_merge( $raw_headers, $hval );
                            } else {
                                $raw_headers[] = $hval;
                            }
                        }
                    }
                }

                // Last-resort fallback for simple single-cookie responses
                if ( empty( $raw_headers ) ) {
                    $fallback = wp_remote_retrieve_header( $response, 'set-cookie' );
                    if ( $fallback ) {
                        $raw_headers = is_array( $fallback ) ? $fallback : [ $fallback ];
                    }
                }

                foreach ( $raw_headers as $header_value ) {
                    // Set-Cookie: name=value; Path=/; HttpOnly; SameSite=Lax
                    $parts = explode( ';', (string) $header_value );
                    $pair  = explode( '=', trim( $parts[0] ), 2 );
                    $name  = trim( $pair[0] );
                    $value = trim( $pair[1] ?? '' );

                    if ( ! $name || isset( $results[ $name ] ) ) {
                        continue;
                    }

                    $results[ $name ] = [
                        'value'     => substr( $value, 0, 200 ),
                        'url'       => $url,
                        'scan_type' => 'server',
                        'found_at'  => current_time( 'mysql' ),
                    ];
                    $found_new++;
                }
            } catch ( \Throwable $e ) {
                // Skip this page on error, continue scanning others
                continue;
            }
        }

        update_option( self::OPT_RESULTS, $results );

        ob_start();
        self::render_results_table( $results );

        wp_send_json_success( [
            'found'      => $found_new,
            'pages'      => count( $pages ),
            'count'      => count( $results ),
            'table_html' => ob_get_clean(),
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX — clear results
    // -------------------------------------------------------------------------

    public static function ajax_clear() {
        check_ajax_referer( 'ftt_cookie_scanner', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        delete_option( self::OPT_RESULTS );
        wp_send_json_success();
    }

    // -------------------------------------------------------------------------
    // Front-end injection — browser scan mode
    // -------------------------------------------------------------------------

    public static function maybe_inject_scanner() {
        if ( ! isset( $_GET[ self::SCAN_PARAM ] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return; // Only admins can trigger a scan — visitors see nothing different
        }

        $nonce    = wp_create_nonce( 'ftt_cookie_scanner' );
        $ajax_url = admin_url( 'admin-ajax.php' );
        // Build the page URL without the scan param so it reads cleanly in results
        $page_url = remove_query_arg( self::SCAN_PARAM, home_url( add_query_arg( [] ) ) );
        ?>
        <script>
        (function () {
            // Wait 3 seconds so all third-party JS (GA4, Stripe, etc.) has had time
            // to set its cookies before we collect the snapshot.
            setTimeout(function () {
                var cookies = [];
                document.cookie.split(';').forEach(function (c) {
                    var idx = c.indexOf('=');
                    if (idx < 0) return;
                    var name  = c.substring(0, idx).trim();
                    var value = c.substring(idx + 1).trim();
                    if (name) cookies.push({ name: name, value: value });
                });

                var body = [
                    'action=ftt_cookie_scan_report',
                    'nonce=' + encodeURIComponent('<?php echo esc_js( $nonce ); ?>'),
                    'page_url=' + encodeURIComponent('<?php echo esc_js( $page_url ); ?>'),
                    'cookies=' + encodeURIComponent(JSON.stringify(cookies)),
                ].join('&');

                var xhr = new XMLHttpRequest();
                xhr.open('POST', '<?php echo esc_js( $ajax_url ); ?>');
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function () {
                    document.getElementById('ftt-scan-badge').textContent =
                        '🍪 Scan complete — ' + cookies.length + ' cookie(s) reported. You can close this tab.';
                };
                xhr.send(body);
            }, 3000);
        })();
        </script>
        <div id="ftt-scan-badge" style="
            position: fixed;
            bottom: 70px;
            right: 20px;
            background: #6A3E8E;
            color: #fff;
            padding: 10px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            z-index: 99999;
            box-shadow: 0 4px 16px rgba(0,0,0,.3);
            max-width: 320px;
            line-height: 1.4;
        ">
            🍪 <?php esc_html_e( 'Cookie scan active — collecting in 3 seconds…', 'schedule-collaboration-tracking' ); ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Pages to scan (server scan)
    // -------------------------------------------------------------------------

    private static function get_pages_to_scan(): array {
        $urls = [ __( 'Homepage', 'schedule-collaboration-tracking' ) => home_url( '/' ) ];

        // All FTT page keys with human labels, in display order
        $page_keys = [
            'login'               => __( 'Login',               'schedule-collaboration-tracking' ),
            'register'            => __( 'Register',            'schedule-collaboration-tracking' ),
            'dashboard'           => __( 'Dashboard',           'schedule-collaboration-tracking' ),
            'calendar'            => __( 'Calendar',            'schedule-collaboration-tracking' ),
            'event_list'          => __( 'Events List',         'schedule-collaboration-tracking' ),
            'event_form'          => __( 'Event Form',          'schedule-collaboration-tracking' ),
            'pricing'             => __( 'Pricing',             'schedule-collaboration-tracking' ),
            'manage_subscription' => __( 'Manage Subscription', 'schedule-collaboration-tracking' ),
            'checkout_success'    => __( 'Checkout Success',    'schedule-collaboration-tracking' ),
            'checkout_cancel'     => __( 'Checkout Cancelled',  'schedule-collaboration-tracking' ),
            'family_management'   => __( 'Manage Family',       'schedule-collaboration-tracking' ),
            'groups'              => __( 'Family Groups',       'schedule-collaboration-tracking' ),
            'profile'             => __( 'My Settings',         'schedule-collaboration-tracking' ),
            'onboarding'          => __( 'Onboarding',          'schedule-collaboration-tracking' ),
            'trial_expired'       => __( 'Trial Ended',         'schedule-collaboration-tracking' ),
        ];

        foreach ( $page_keys as $key => $label ) {
            $url = class_exists( 'FTT_Pages' ) ? FTT_Pages::get_page_url( $key ) : false;
            if ( $url ) {
                $urls[ $label ] = $url;
            }
        }

        return $urls;
    }
}
