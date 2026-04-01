<?php
/**
 * Cookie Consent Banner
 *
 * Renders a styled consent bar on every front-end page, stores the visitor's
 * choice in a browser cookie (ftt_cookie_consent), and emits Google Consent
 * Mode v2 defaults so GA4 honours the user's decision.
 *
 * Settings (all inside ftt_settings):
 *   cookie_consent_enabled   bool    Master on/off switch
 *   cookie_consent_position  string  'bottom' (default) | 'top'
 *   cookie_consent_message   string  Banner body text
 *   cookie_consent_accept    string  Accept button label
 *   cookie_consent_decline   string  Decline button label (empty = hide button)
 *   cookie_consent_days      int     Days until consent cookie expires (default 365)
 *
 * @package Family_Travel_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FTT_Cookie_Consent {

    /** Option key inside ftt_settings */
    const SETTINGS_KEY = 'ftt_settings';

    /** Browser cookie name written by JS */
    const JS_COOKIE = 'ftt_cookie_consent';

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    public static function init() {
        // Front-end
        add_action( 'wp_head',         [ __CLASS__, 'output_gcm_defaults'  ], 1 ); // Before gtag fires
        add_action( 'wp_footer',       [ __CLASS__, 'render_banner'        ]    );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets'    ]    );

        // Admin settings (rendered via render_settings_section())
        add_action( 'admin_post_ftt_save_cookie_consent', [ __CLASS__, 'handle_save' ] );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function get_settings() {
        $s = get_option( self::SETTINGS_KEY, [] );
        return [
            'enabled'  => ! empty( $s['cookie_consent_enabled'] ),
            'position' => $s['cookie_consent_position'] ?? 'bottom',
            'message'  => $s['cookie_consent_message']  ?? 'We use cookies to improve your experience, analyse site usage, and personalise content. By clicking "Accept", you consent to our use of cookies.',
            'accept'   => $s['cookie_consent_accept']   ?? 'Accept',
            'decline'  => $s['cookie_consent_decline']  ?? 'Decline',
            'days'     => intval( $s['cookie_consent_days'] ?? 365 ),
            'policy_page' => absint( $s['policy_cookie_page'] ?? 0 ),
        ];
    }

    // -------------------------------------------------------------------------
    // Google Consent Mode v2 — must run before gtag.js
    // -------------------------------------------------------------------------

    public static function output_gcm_defaults() {
        $cfg = self::get_settings();
        if ( ! $cfg['enabled'] || is_admin() ) {
            return;
        }
        ?>
<script>
/* FTT – Google Consent Mode v2 defaults (denied until user accepts) */
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent','default',{
    'ad_storage':             'denied',
    'analytics_storage':      'denied',
    'ad_user_data':           'denied',
    'ad_personalization':     'denied',
    'wait_for_update':        500
});
gtag('set','ads_data_redaction',true);
gtag('set','url_passthrough',true);
</script>
        <?php
    }

    // -------------------------------------------------------------------------
    // Enqueue assets
    // -------------------------------------------------------------------------

    public static function enqueue_assets() {
        $cfg = self::get_settings();
        if ( ! $cfg['enabled'] || is_admin() ) {
            return;
        }

        // Inline the banner CSS so it loads on every page without depending
        // on ftt-styles (which is only enqueued on FTT app pages).
        wp_register_style( 'ftt-cookie-consent', false );
        wp_enqueue_style( 'ftt-cookie-consent' );
        wp_add_inline_style( 'ftt-cookie-consent', self::get_banner_css() );

        wp_enqueue_script(
            'ftt-cookie-consent',
            FTT_PLUGIN_URL . 'assets/js/cookie-consent.js',
            [],
            FTT_VERSION,
            true  // Footer
        );

        wp_localize_script( 'ftt-cookie-consent', 'fttCookieConsent', [
            'cookieName' => self::JS_COOKIE,
            'days'       => $cfg['days'],
            'position'   => $cfg['position'],
        ] );
    }

    private static function get_banner_css() {
        return '
.ftt-cookie-banner{position:fixed;left:0;right:0;z-index:99999;background:#6A3E8E;color:#fff;box-shadow:0 -2px 12px rgba(0,0,0,.18);transition:transform .35s ease}
.ftt-cookie-banner.ftt-cc-bottom{bottom:0;transform:translateY(100%)}
.ftt-cookie-banner.ftt-cc-top{top:0;transform:translateY(-100%)}
.ftt-cookie-banner.ftt-cc-visible{transform:translateY(0)}
.ftt-cc-inner{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;max-width:1200px;margin:0 auto;padding:16px 24px}
.ftt-cc-message{flex:1 1 280px;margin:0;font-size:14px;line-height:1.55;color:#fff}
.ftt-cc-policy-link{color:#E9E3F2;text-decoration:underline}
.ftt-cc-policy-link:hover{color:#fff}
.ftt-cc-actions{display:flex;gap:10px;flex-shrink:0}
.ftt-cc-btn{padding:9px 22px;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;transition:background .2s,transform .15s;white-space:nowrap}
.ftt-cc-btn-accept{background:#F05A5A;color:#fff}
.ftt-cc-btn-accept:hover{background:#E84E4E;transform:translateY(-1px)}
.ftt-cc-btn-decline{background:transparent;color:#E9E3F2;border:1px solid rgba(255,255,255,.45)}
.ftt-cc-btn-decline:hover{background:rgba(255,255,255,.12);color:#fff}
@media(max-width:600px){.ftt-cc-inner{flex-direction:column;align-items:flex-start;padding:14px 16px}.ftt-cc-actions{width:100%}.ftt-cc-btn{flex:1;text-align:center}}
        ';
    }

    // -------------------------------------------------------------------------
    // Banner HTML (wp_footer)
    // -------------------------------------------------------------------------

    public static function render_banner() {
        $cfg = self::get_settings();
        if ( ! $cfg['enabled'] ) {
            return;
        }

        $policy_url = $cfg['policy_page'] ? get_permalink( $cfg['policy_page'] ) : '';

        $message = wp_kses_post( $cfg['message'] );
        if ( $policy_url ) {
            $message .= ' <a href="' . esc_url( $policy_url ) . '" target="_blank" rel="noopener" class="ftt-cc-policy-link">'
                . esc_html__( 'Cookie Policy', 'schedule-collaboration-tracking' )
                . '</a>';
        }

        $position_class = $cfg['position'] === 'top' ? 'ftt-cc-top' : 'ftt-cc-bottom';
        ?>
<div id="ftt-cookie-banner" class="ftt-cookie-banner <?php echo esc_attr( $position_class ); ?>" role="dialog" aria-label="<?php esc_attr_e( 'Cookie consent', 'schedule-collaboration-tracking' ); ?>" aria-live="polite" hidden>
    <div class="ftt-cc-inner">
        <p class="ftt-cc-message"><?php echo $message; // Already escaped above ?></p>
        <div class="ftt-cc-actions">
            <button type="button" id="ftt-cc-accept" class="ftt-cc-btn ftt-cc-btn-accept">
                <?php echo esc_html( $cfg['accept'] ); ?>
            </button>
            <?php if ( ! empty( $cfg['decline'] ) ) : ?>
            <button type="button" id="ftt-cc-decline" class="ftt-cc-btn ftt-cc-btn-decline">
                <?php echo esc_html( $cfg['decline'] ); ?>
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Admin settings UI (called via render_settings_section())
    // -------------------------------------------------------------------------

    /**
     * Render the Cookie Consent card inside the Policy & Communications tab.
     * Uses its own <form> so it saves independently.
     */
    public static function render_settings_section() {
        $s   = get_option( self::SETTINGS_KEY, [] );
        $cfg = self::get_settings();

        $nonce = wp_create_nonce( 'ftt_save_cookie_consent' );
        ?>
<div class="ftt-card" style="margin-top:24px;">
    <h2 class="ftt-card-title"><?php esc_html_e( 'Cookie Consent Banner', 'schedule-collaboration-tracking' ); ?></h2>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'ftt_save_cookie_consent', 'ftt_cookie_consent_nonce' ); ?>
        <input type="hidden" name="action" value="ftt_save_cookie_consent">

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e( 'Enable Banner', 'schedule-collaboration-tracking' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="cookie_consent_enabled" value="1" <?php checked( $cfg['enabled'] ); ?>>
                        <?php esc_html_e( 'Show cookie consent banner on all front-end pages', 'schedule-collaboration-tracking' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Position', 'schedule-collaboration-tracking' ); ?></th>
                <td>
                    <select name="cookie_consent_position">
                        <option value="bottom" <?php selected( $cfg['position'], 'bottom' ); ?>><?php esc_html_e( 'Bottom of page', 'schedule-collaboration-tracking' ); ?></option>
                        <option value="top"    <?php selected( $cfg['position'], 'top'    ); ?>><?php esc_html_e( 'Top of page',    'schedule-collaboration-tracking' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Banner Message', 'schedule-collaboration-tracking' ); ?></th>
                <td>
                    <textarea name="cookie_consent_message" rows="4" class="large-text"><?php echo esc_textarea( $cfg['message'] ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Plain text or simple HTML. A "Cookie Policy" link is appended automatically if you have set a Cookie Policy page above.', 'schedule-collaboration-tracking' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Accept Button Label', 'schedule-collaboration-tracking' ); ?></th>
                <td><input type="text" name="cookie_consent_accept" value="<?php echo esc_attr( $cfg['accept'] ); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Decline Button Label', 'schedule-collaboration-tracking' ); ?></th>
                <td>
                    <input type="text" name="cookie_consent_decline" value="<?php echo esc_attr( $cfg['decline'] ); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e( 'Leave blank to hide the Decline button.', 'schedule-collaboration-tracking' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Consent Duration (days)', 'schedule-collaboration-tracking' ); ?></th>
                <td>
                    <input type="number" name="cookie_consent_days" value="<?php echo esc_attr( $cfg['days'] ); ?>" min="1" max="730" class="small-text">
                    <p class="description"><?php esc_html_e( 'How long to remember the visitor\'s choice before asking again. Default: 365.', 'schedule-collaboration-tracking' ); ?></p>
                </td>
            </tr>
        </table>

        <?php submit_button( __( 'Save Cookie Consent Settings', 'schedule-collaboration-tracking' ) ); ?>
    </form>
</div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Save handler
    // -------------------------------------------------------------------------

    public static function handle_save() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'schedule-collaboration-tracking' ), 403 );
        }

        check_admin_referer( 'ftt_save_cookie_consent', 'ftt_cookie_consent_nonce' );

        $s = get_option( self::SETTINGS_KEY, [] );

        $s['cookie_consent_enabled']  = ! empty( $_POST['cookie_consent_enabled'] );
        $s['cookie_consent_position'] = in_array( $_POST['cookie_consent_position'] ?? '', [ 'bottom', 'top' ], true )
            ? sanitize_key( $_POST['cookie_consent_position'] )
            : 'bottom';
        $s['cookie_consent_message']  = wp_kses_post( wp_unslash( $_POST['cookie_consent_message'] ?? '' ) );
        $s['cookie_consent_accept']   = sanitize_text_field( wp_unslash( $_POST['cookie_consent_accept']  ?? 'Accept'  ) );
        $s['cookie_consent_decline']  = sanitize_text_field( wp_unslash( $_POST['cookie_consent_decline'] ?? '' ) );
        $s['cookie_consent_days']     = max( 1, intval( $_POST['cookie_consent_days'] ?? 365 ) );

        update_option( self::SETTINGS_KEY, $s );

        wp_safe_redirect( add_query_arg( [
            'post_type'       => 'ftt_event',
            'page'            => 'ftt-settings',
            'tab'             => 'policy-comms',
            'settings-updated' => '1',
        ], admin_url( 'edit.php' ) ) );
        exit;
    }
}
