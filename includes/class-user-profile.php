<?php
/**
 * User Profile & Personal Settings
 *
 * Manages per-user preferences: name, email, phone, home airports, timezone,
 * calendar view, and notification options.  Parents can also edit their
 * children's travel-related preferences (airport, timezone) from the same
 * front-end page.
 *
 * User-meta keys used:
 *   phone               – phone number (shared with registration)
 *   ftt_timezone        – IANA timezone string, e.g. "America/Chicago"
 *   ftt_home_airports   – JSON-encoded array of up to 3 IATA codes, first = primary
 *   ftt_calendar_view   – "month" | "week" | "agenda"
 *   ftt_digest_enabled  – "1" | ""   (email digest on/off)
 *   ftt_digest_frequency– "daily" | "weekly"
 *
 * @package Family_Travel_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FTT_User_Profile {

	/* ------------------------------------------------------------------ */
	/* Bootstrap                                                           */
	/* ------------------------------------------------------------------ */

	public static function init() {
		// Shortcode
		add_shortcode( 'ftt_profile', array( __CLASS__, 'render_shortcode' ) );

		// REST endpoints
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );

		// WP admin profile page – show FTT fields
		add_action( 'show_user_profile',        array( __CLASS__, 'render_wp_admin_fields' ) );
		add_action( 'edit_user_profile',         array( __CLASS__, 'render_wp_admin_fields' ) );
		add_action( 'personal_options_update',   array( __CLASS__, 'save_wp_admin_fields' ) );
		add_action( 'edit_user_profile_update',  array( __CLASS__, 'save_wp_admin_fields' ) );
	}

	/* ------------------------------------------------------------------ */
	/* REST Routes                                                         */
	/* ------------------------------------------------------------------ */

	public static function register_rest_routes() {
		// Get own profile
		register_rest_route( 'ftt/v1', '/profile', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'rest_get_profile' ),
			'permission_callback' => function() { return is_user_logged_in(); },
		) );

		// Save own profile
		register_rest_route( 'ftt/v1', '/profile/save', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'rest_save_profile' ),
			'permission_callback' => function() { return is_user_logged_in(); },
		) );

		// Parent saves a child's travel/timezone settings
		register_rest_route( 'ftt/v1', '/profile/child/(?P<child_id>\d+)/save', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'rest_save_child_profile' ),
			'permission_callback' => function( $request ) {
				if ( ! is_user_logged_in() ) {
					return false;
				}
				$child_id = (int) $request['child_id'];
				return self::current_user_is_parent_of( $child_id );
			},
		) );
	}

	/* ------------------------------------------------------------------ */
	/* REST Handlers                                                       */
	/* ------------------------------------------------------------------ */

	public static function rest_get_profile( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		return rest_ensure_response( self::get_profile_data( $user_id ) );
	}

	public static function rest_save_profile( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$params  = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$errors = self::save_profile_data( $user_id, $params );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'validation_error', implode( ' ', $errors ), array( 'status' => 400 ) );
		}

		return rest_ensure_response( array(
			'success' => true,
			'profile' => self::get_profile_data( $user_id ),
		) );
	}

	public static function rest_save_child_profile( WP_REST_Request $request ) {
		$child_id = (int) $request['child_id'];
		$params   = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		// Parents can only update travel preferences and display name for a child
		$allowed = array( 'first_name', 'last_name', 'display_name', 'ftt_timezone', 'ftt_home_airports' );
		$filtered = array_intersect_key( $params, array_flip( $allowed ) );

		$errors = self::save_profile_data( $child_id, $filtered, true );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'validation_error', implode( ' ', $errors ), array( 'status' => 400 ) );
		}

		return rest_ensure_response( array(
			'success' => true,
			'profile' => self::get_profile_data( $child_id ),
		) );
	}

	/* ------------------------------------------------------------------ */
	/* Data layer                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Build a safe profile data array for a user.
	 */
	public static function get_profile_data( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array();
		}

		$home_airports_raw = get_user_meta( $user_id, 'ftt_home_airports', true );
		$home_airports     = is_array( $home_airports_raw ) ? $home_airports_raw
		                   : ( $home_airports_raw ? json_decode( $home_airports_raw, true ) : array() );
		if ( ! is_array( $home_airports ) ) {
			$home_airports = array();
		}

		$site_tz = get_option( 'ftt_settings' )['default_timezone'] ?? wp_timezone_string();

		return array(
			'user_id'          => $user_id,
			'first_name'       => $user->first_name,
			'last_name'        => $user->last_name,
			'display_name'     => $user->display_name,
			'user_email'       => $user->user_email,
			'phone'            => get_user_meta( $user_id, 'phone', true ),
			'ftt_timezone'     => get_user_meta( $user_id, 'ftt_timezone', true ) ?: $site_tz,
			'ftt_home_airports'=> $home_airports,
			'ftt_calendar_view'=> get_user_meta( $user_id, 'ftt_calendar_view', true ) ?: 'month',
			'ftt_digest_enabled'   => (bool) get_user_meta( $user_id, 'ftt_digest_enabled', true ),
			'ftt_digest_frequency' => get_user_meta( $user_id, 'ftt_digest_frequency', true ) ?: 'daily',
		);
	}

	/**
	 * Persist profile data for a user.  Returns array of error strings (empty = success).
	 *
	 * @param int   $user_id
	 * @param array $params
	 * @param bool  $child_mode  When true, skip email/password/phone fields.
	 */
	public static function save_profile_data( $user_id, array $params, $child_mode = false ) {
		$errors = array();

		// ----- Name fields -----
		if ( isset( $params['first_name'] ) ) {
			update_user_meta( $user_id, 'first_name', sanitize_text_field( wp_unslash( $params['first_name'] ) ) );
		}
		if ( isset( $params['last_name'] ) ) {
			update_user_meta( $user_id, 'last_name', sanitize_text_field( wp_unslash( $params['last_name'] ) ) );
		}
		if ( isset( $params['display_name'] ) ) {
			$display = sanitize_text_field( wp_unslash( $params['display_name'] ) );
			if ( $display !== '' ) {
				wp_update_user( array( 'ID' => $user_id, 'display_name' => $display ) );
			}
		}

		if ( ! $child_mode ) {
			// ----- Email -----
			if ( isset( $params['user_email'] ) ) {
				$new_email = sanitize_email( wp_unslash( $params['user_email'] ) );
				if ( ! is_email( $new_email ) ) {
					$errors[] = __( 'Please enter a valid email address.', 'schedule-collaboration-tracking' );
				} elseif ( $new_email !== get_userdata( $user_id )->user_email ) {
					// Make sure no one else uses this email
					if ( email_exists( $new_email ) ) {
						$errors[] = __( 'That email address is already in use.', 'schedule-collaboration-tracking' );
					} else {
						wp_update_user( array( 'ID' => $user_id, 'user_email' => $new_email ) );
					}
				}
			}

			// ----- Phone -----
			if ( isset( $params['phone'] ) ) {
				$phone = sanitize_text_field( wp_unslash( $params['phone'] ) );
				if ( $phone !== '' && ! preg_match( '/^[0-9\+\-\(\)\s\.ext]{7,20}$/i', $phone ) ) {
					$errors[] = __( 'Please enter a valid phone number.', 'schedule-collaboration-tracking' );
				} else {
					update_user_meta( $user_id, 'phone', $phone );
				}
			}

			// ----- Password -----
			if ( ! empty( $params['new_password'] ) ) {
				$new_pass    = $params['new_password'];       // NOT sanitized – passwords can contain special chars
				$confirm     = $params['confirm_password'] ?? '';
				$current_raw = $params['current_password']  ?? '';

				if ( strlen( $new_pass ) < 8 ) {
					$errors[] = __( 'New password must be at least 8 characters.', 'schedule-collaboration-tracking' );
				} elseif ( $new_pass !== $confirm ) {
					$errors[] = __( 'New passwords do not match.', 'schedule-collaboration-tracking' );
				} else {
					$user = get_userdata( $user_id );
					if ( ! wp_check_password( $current_raw, $user->user_pass, $user_id ) ) {
						$errors[] = __( 'Current password is incorrect.', 'schedule-collaboration-tracking' );
					} else {
						wp_set_password( $new_pass, $user_id );
					}
				}
			}
		}

		// ----- Timezone -----
		if ( isset( $params['ftt_timezone'] ) ) {
			$tz = sanitize_text_field( wp_unslash( $params['ftt_timezone'] ) );
			// Validate against PHP timezone list
			if ( $tz !== '' && ! in_array( $tz, timezone_identifiers_list(), true ) ) {
				$errors[] = __( 'Please select a valid timezone.', 'schedule-collaboration-tracking' );
			} else {
				update_user_meta( $user_id, 'ftt_timezone', $tz );
			}
		}

		// ----- Home Airports -----
		if ( isset( $params['ftt_home_airports'] ) ) {
			$raw = $params['ftt_home_airports'];
			if ( is_string( $raw ) ) {
				$raw = json_decode( $raw, true );
			}
			if ( ! is_array( $raw ) ) {
				$raw = array();
			}
			$clean = array();
			foreach ( array_slice( $raw, 0, 3 ) as $code ) {
				$code = strtoupper( sanitize_text_field( wp_unslash( $code ) ) );
				if ( preg_match( '/^[A-Z]{3}$/', $code ) ) {
					$clean[] = $code;
				}
			}
			update_user_meta( $user_id, 'ftt_home_airports', $clean );
		}

		// ----- Calendar view preference -----
		if ( isset( $params['ftt_calendar_view'] ) ) {
			$view = sanitize_text_field( wp_unslash( $params['ftt_calendar_view'] ) );
			if ( in_array( $view, array( 'month', 'week', 'agenda' ), true ) ) {
				update_user_meta( $user_id, 'ftt_calendar_view', $view );
			}
		}

		// ----- Digest settings -----
		if ( isset( $params['ftt_digest_enabled'] ) ) {
			update_user_meta( $user_id, 'ftt_digest_enabled', (bool) $params['ftt_digest_enabled'] ? '1' : '' );
		}
		if ( isset( $params['ftt_digest_frequency'] ) ) {
			$freq = sanitize_text_field( wp_unslash( $params['ftt_digest_frequency'] ) );
			if ( in_array( $freq, array( 'daily', 'weekly' ), true ) ) {
				update_user_meta( $user_id, 'ftt_digest_frequency', $freq );
			}
		}

		return $errors;
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Check whether the current user is a parent/guardian of a given child.
	 */
	private static function current_user_is_parent_of( $child_id ) {
		$current_id = get_current_user_id();
		if ( current_user_can( 'manage_options' ) ) {
			return true; // admins can always edit
		}
		$parents = get_user_meta( $child_id, 'ftt_parents', true );
		return is_array( $parents ) && in_array( $current_id, array_map( 'intval', $parents ), true );
	}

	/**
	 * Get the effective timezone for a user (falls back to site default).
	 */
	public static function get_user_timezone( $user_id ) {
		$tz = get_user_meta( $user_id, 'ftt_timezone', true );
		if ( $tz && in_array( $tz, timezone_identifiers_list(), true ) ) {
			return $tz;
		}
		$settings = get_option( 'ftt_settings', array() );
		return $settings['default_timezone'] ?? wp_timezone_string();
	}

	/**
	 * Get primary home airport IATA code for a user (or empty string).
	 */
	public static function get_primary_airport( $user_id ) {
		$airports = get_user_meta( $user_id, 'ftt_home_airports', true );
		if ( ! is_array( $airports ) && $airports ) {
			$airports = json_decode( $airports, true );
		}
		return ( is_array( $airports ) && ! empty( $airports[0] ) ) ? $airports[0] : '';
	}

	/* ------------------------------------------------------------------ */
	/* WP Admin Profile Fields                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Render FTT-specific fields on the WP admin user profile/edit page.
	 */
	public static function render_wp_admin_fields( WP_User $user ) {
		$data      = self::get_profile_data( $user->ID );
		$timezones = timezone_identifiers_list();
		?>
		<h2><?php esc_html_e( 'Family Travel Tracker Settings', 'schedule-collaboration-tracking' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th><label for="ftt_timezone"><?php esc_html_e( 'Personal Timezone', 'schedule-collaboration-tracking' ); ?></label></th>
				<td>
					<?php wp_nonce_field( 'ftt_admin_profile_' . $user->ID, 'ftt_admin_profile_nonce' ); ?>
					<select name="ftt_timezone" id="ftt_timezone">
						<option value=""><?php esc_html_e( '— Use site default —', 'schedule-collaboration-tracking' ); ?></option>
						<?php foreach ( $timezones as $tz ) : ?>
							<option value="<?php echo esc_attr( $tz ); ?>" <?php selected( $data['ftt_timezone'], $tz ); ?>>
								<?php echo esc_html( $tz ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Controls how event times are displayed and when emails are sent for this user.', 'schedule-collaboration-tracking' ); ?></p>
				</td>
			</tr>

			<tr>
				<th><label for="ftt_home_airports"><?php esc_html_e( 'Home Airports', 'schedule-collaboration-tracking' ); ?></label></th>
				<td>
					<?php
					$airports = $data['ftt_home_airports'];
					for ( $i = 0; $i < 3; $i++ ) :
						$val = $airports[ $i ] ?? '';
					?>
					<p>
						<label>
							<?php echo esc_html( $i === 0 ? __( 'Primary', 'schedule-collaboration-tracking' ) : sprintf( __( 'Secondary %d', 'schedule-collaboration-tracking' ), $i ) ); ?>:&nbsp;
							<input type="text" name="ftt_home_airports[]"
							       value="<?php echo esc_attr( $val ); ?>"
							       maxlength="3"
							       style="width:80px;text-transform:uppercase;"
							       placeholder="e.g. ORD">
						</label>
					</p>
					<?php endfor; ?>
					<p class="description"><?php esc_html_e( 'IATA airport codes. The primary airport auto-fills as the origin when creating a new flight.', 'schedule-collaboration-tracking' ); ?></p>
				</td>
			</tr>

			<tr>
				<th><label for="ftt_calendar_view"><?php esc_html_e( 'Default Calendar View', 'schedule-collaboration-tracking' ); ?></label></th>
				<td>
					<select name="ftt_calendar_view" id="ftt_calendar_view">
						<option value="month" <?php selected( $data['ftt_calendar_view'], 'month' ); ?>><?php esc_html_e( 'Month', 'schedule-collaboration-tracking' ); ?></option>
						<option value="week"  <?php selected( $data['ftt_calendar_view'], 'week' ); ?>><?php esc_html_e( 'Week', 'schedule-collaboration-tracking' ); ?></option>
						<option value="agenda" <?php selected( $data['ftt_calendar_view'], 'agenda' ); ?>><?php esc_html_e( 'Agenda', 'schedule-collaboration-tracking' ); ?></option>
					</select>
				</td>
			</tr>

		</table>
		<?php
	}

	/**
	 * Save FTT-specific fields from the WP admin user profile/edit page.
	 */
	public static function save_wp_admin_fields( $user_id ) {
		if ( ! isset( $_POST['ftt_admin_profile_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ftt_admin_profile_nonce'] ) ), 'ftt_admin_profile_' . $user_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$params = array();

		if ( isset( $_POST['ftt_timezone'] ) ) {
			$params['ftt_timezone'] = sanitize_text_field( wp_unslash( $_POST['ftt_timezone'] ) );
		}

		if ( isset( $_POST['ftt_calendar_view'] ) ) {
			$params['ftt_calendar_view'] = sanitize_text_field( wp_unslash( $_POST['ftt_calendar_view'] ) );
		}

		if ( isset( $_POST['ftt_home_airports'] ) && is_array( $_POST['ftt_home_airports'] ) ) {
			$params['ftt_home_airports'] = array_map( function( $v ) {
				return strtoupper( sanitize_text_field( wp_unslash( $v ) ) );
			}, $_POST['ftt_home_airports'] );
		}

		self::save_profile_data( $user_id, $params );
	}

	/* ------------------------------------------------------------------ */
	/* Shortcode / front-end                                               */
	/* ------------------------------------------------------------------ */

	public static function render_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			$login_url = FTT_Pages::get_page_url( 'login' );
			return '<p>' . sprintf(
				/* translators: %s: login URL */
				wp_kses( __( 'Please <a href="%s">log in</a> to manage your settings.', 'schedule-collaboration-tracking' ), array( 'a' => array( 'href' => array() ) ) ),
				esc_url( $login_url ?: wp_login_url() )
			) . '</p>';
		}

		$user_id   = get_current_user_id();
		$profile   = self::get_profile_data( $user_id );
		$timezones = timezone_identifiers_list();
		$children  = FTT_Family_Groups::get_user_children( $user_id );

		// Airports data for JS (PHP side passes it so no additional AJAX needed)
		$airports_path = plugin_dir_path( __FILE__ ) . '../assets/js/airports.json';
		$airports_json = file_exists( $airports_path ) ? file_get_contents( $airports_path ) : '{}';  // phpcs:ignore WordPress.WP.AlternativeFunctions

		ob_start();
		include plugin_dir_path( __FILE__ ) . '../templates/profile.php';
		$html = ob_get_clean();

		// Inline script for profile JS bootstrap
		$nonce     = wp_create_nonce( 'wp_rest' );
		$rest_url  = esc_url( rest_url( 'ftt/v1/' ) );
		$inline = '<script>
var fttProfileData = ' . wp_json_encode( array(
			'restUrl'   => rest_url( 'ftt/v1/' ),
			'nonce'     => $nonce,
			'profile'   => $profile,
			'airports'  => json_decode( $airports_json, true ) ?: array(),
			'timezones' => $timezones,
		) ) . ';
</script>';

		wp_enqueue_script( 'ftt-profile', plugin_dir_url( __FILE__ ) . '../assets/js/profile.js', array( 'jquery' ), FTT_VERSION, true );
		wp_enqueue_style( 'ftt-main' ); // already registered by main plugin

		return $inline . $html;
	}
}
