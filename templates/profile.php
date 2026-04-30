<?php
/**
 * Template: Personal Settings / Profile
 *
 * Rendered by [ftt_profile] via FTT_User_Profile::render_shortcode().
 * Variables available from the shortcode method:
 *   $profile   – associative array (see FTT_User_Profile::get_profile_data)
 *   $timezones – result of timezone_identifiers_list()
 *   $children  – array of WP_User objects (parent's children)
 *   $airports_json – raw JSON string from airports.json
 *
 * @package Family_Travel_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dashboard_url = FTT_Pages::get_page_url( 'dashboard' );
?>

<div class="ftt-profile-page" id="ftt-profile-page">
    <?php
    $ftt_page_title  = __('My Settings', 'schedule-collaboration-tracking');
    $ftt_active_slug = 'profile';
    include FTT_PLUGIN_DIR . 'templates/partials/nav.php';
    ?>

	<h1 class="ftt-profile-heading">
		<?php esc_html_e( 'My Settings', 'schedule-collaboration-tracking' ); ?>
	</h1>

	<!-- Flash messages -->
	<div id="ftt-profile-message" class="ftt-profile-message" style="display:none;" role="alert" aria-live="polite"></div>

	<!-- ================================================================ -->
	<!-- Section 1 – Personal Information                                 -->
	<!-- ================================================================ -->
	<section class="ftt-profile-section" id="ftt-section-personal">
		<h2 class="ftt-profile-section-title">
			<span class="ftt-profile-section-icon">👤</span>
			<?php esc_html_e( 'Personal Information', 'schedule-collaboration-tracking' ); ?>
		</h2>

		<div class="ftt-profile-form-grid">

			<div class="ftt-form-row">
				<div class="ftt-form-field">
					<label for="ftt-first-name"><?php esc_html_e( 'First Name', 'schedule-collaboration-tracking' ); ?> <span class="required">*</span></label>
					<input type="text" id="ftt-first-name" name="first_name"
					       value="<?php echo esc_attr( $profile['first_name'] ); ?>"
					       autocomplete="given-name" required>
				</div>
				<div class="ftt-form-field">
					<label for="ftt-last-name"><?php esc_html_e( 'Last Name', 'schedule-collaboration-tracking' ); ?> <span class="required">*</span></label>
					<input type="text" id="ftt-last-name" name="last_name"
					       value="<?php echo esc_attr( $profile['last_name'] ); ?>"
					       autocomplete="family-name" required>
				</div>
			</div>

			<div class="ftt-form-field">
				<label for="ftt-display-name">
					<?php esc_html_e( 'Display Name', 'schedule-collaboration-tracking' ); ?>
				</label>
				<input type="text" id="ftt-display-name" name="display_name"
				       value="<?php echo esc_attr( $profile['display_name'] ); ?>"
				       autocomplete="nickname">
				<p class="ftt-field-hint"><?php esc_html_e( 'This is the name other family members see — e.g. "Dad", "Sarah", or your full name.', 'schedule-collaboration-tracking' ); ?></p>
			</div>

			<div class="ftt-form-field">
				<label for="ftt-email"><?php esc_html_e( 'Email Address', 'schedule-collaboration-tracking' ); ?> <span class="required">*</span></label>
				<input type="email" id="ftt-email" name="user_email"
				       value="<?php echo esc_attr( $profile['user_email'] ); ?>"
				       autocomplete="email" required>
			</div>

			<div class="ftt-form-field">
				<label for="ftt-phone"><?php esc_html_e( 'Phone Number', 'schedule-collaboration-tracking' ); ?></label>
				<input type="tel" id="ftt-phone" name="phone"
				       value="<?php echo esc_attr( $profile['phone'] ); ?>"
				       autocomplete="tel"
				       placeholder="+1 (555) 555-5555">
				<p class="ftt-field-hint"><?php esc_html_e( 'Used for important account notifications.', 'schedule-collaboration-tracking' ); ?></p>
			</div>

		</div>

		<div class="ftt-profile-actions">
			<button type="button" class="ftt-btn ftt-btn-primary" data-action="save-personal">
				<?php esc_html_e( 'Save Personal Info', 'schedule-collaboration-tracking' ); ?>
			</button>
		</div>
	</section>

	<!-- ================================================================ -->
	<!-- Section 2 – Password Change                                      -->
	<!-- ================================================================ -->
	<section class="ftt-profile-section" id="ftt-section-password">
		<h2 class="ftt-profile-section-title">
			<span class="ftt-profile-section-icon">🔒</span>
			<?php esc_html_e( 'Change Password', 'schedule-collaboration-tracking' ); ?>
		</h2>

		<div class="ftt-profile-form-grid">

			<div class="ftt-form-field">
				<label for="ftt-current-pass"><?php esc_html_e( 'Current Password', 'schedule-collaboration-tracking' ); ?></label>
				<input type="password" id="ftt-current-pass" name="current_password" autocomplete="current-password">
			</div>

			<div class="ftt-form-row">
				<div class="ftt-form-field">
					<label for="ftt-new-pass"><?php esc_html_e( 'New Password', 'schedule-collaboration-tracking' ); ?></label>
					<input type="password" id="ftt-new-pass" name="new_password"
					       minlength="8" autocomplete="new-password"
					       placeholder="<?php esc_attr_e( 'Minimum 8 characters', 'schedule-collaboration-tracking' ); ?>">
				</div>
				<div class="ftt-form-field">
					<label for="ftt-confirm-pass"><?php esc_html_e( 'Confirm New Password', 'schedule-collaboration-tracking' ); ?></label>
					<input type="password" id="ftt-confirm-pass" name="confirm_password"
					       minlength="8" autocomplete="new-password">
				</div>
			</div>

		</div>

		<div class="ftt-profile-actions">
			<button type="button" class="ftt-btn ftt-btn-primary" data-action="save-password">
				<?php esc_html_e( 'Update Password', 'schedule-collaboration-tracking' ); ?>
			</button>
		</div>
	</section>

	<!-- ================================================================ -->
	<!-- Section 3 – Travel Preferences                                   -->
	<!-- ================================================================ -->
	<section class="ftt-profile-section" id="ftt-section-travel">
		<h2 class="ftt-profile-section-title">
			<span class="ftt-profile-section-icon">✈️</span>
			<?php esc_html_e( 'Travel Preferences', 'schedule-collaboration-tracking' ); ?>
		</h2>

		<div class="ftt-profile-form-grid">

			<div class="ftt-form-field">
				<label><?php esc_html_e( 'Home Airport(s)', 'schedule-collaboration-tracking' ); ?></label>
				<p class="ftt-field-hint">
					<?php esc_html_e( 'Your primary airport is automatically used as the departure when you add a flight. You can save up to 3 home airports.', 'schedule-collaboration-tracking' ); ?>
				</p>
				<div id="ftt-home-airports-container">
					<?php
					$saved_airports = $profile['ftt_home_airports'];
					$labels = array(
						__( 'Primary Airport', 'schedule-collaboration-tracking' ),
						__( 'Secondary Airport', 'schedule-collaboration-tracking' ),
						__( 'Tertiary Airport', 'schedule-collaboration-tracking' ),
					);
					for ( $i = 0; $i < 3; $i++ ) :
						$iata = $saved_airports[ $i ] ?? '';
					?>
					<div class="ftt-airport-row" data-slot="<?php echo esc_attr( $i ); ?>">
						<span class="ftt-airport-slot-label"><?php echo esc_html( $labels[ $i ] ); ?></span>
						<div class="ftt-airport-picker-wrap" style="position:relative;">
							<input type="text"
							       class="ftt-airport-search"
							       data-slot="<?php echo esc_attr( $i ); ?>"
							       value="<?php echo $iata ? esc_attr( $iata ) : ''; ?>"
							       placeholder="<?php esc_attr_e( 'Type city or code…', 'schedule-collaboration-tracking' ); ?>"
							       autocomplete="off">
							<input type="hidden"
							       class="ftt-airport-code"
							       data-slot="<?php echo esc_attr( $i ); ?>"
							       value="<?php echo esc_attr( $iata ); ?>">
							<ul class="ftt-airport-suggestions" style="display:none;"></ul>
						</div>
					</div>
					<?php endfor; ?>
				</div>
			</div>

		</div>

		<div class="ftt-profile-actions">
			<button type="button" class="ftt-btn ftt-btn-primary" data-action="save-travel">
				<?php esc_html_e( 'Save Travel Preferences', 'schedule-collaboration-tracking' ); ?>
			</button>
		</div>
	</section>

	<!-- ================================================================ -->
	<!-- Section 4 – Calendar & Timezone                                  -->
	<!-- ================================================================ -->
	<section class="ftt-profile-section" id="ftt-section-calendar">
		<h2 class="ftt-profile-section-title">
			<span class="ftt-profile-section-icon">🗓</span>
			<?php esc_html_e( 'Calendar & Timezone', 'schedule-collaboration-tracking' ); ?>
		</h2>

		<div class="ftt-profile-form-grid">

			<div class="ftt-form-field">
				<label for="ftt-timezone"><?php esc_html_e( 'My Timezone', 'schedule-collaboration-tracking' ); ?></label>
				<select id="ftt-timezone" name="ftt_timezone">
					<option value=""><?php esc_html_e( '— Use site default —', 'schedule-collaboration-tracking' ); ?></option>
					<?php
					// Group timezones by continent for readability
					$grouped = array();
					foreach ( $timezones as $tz ) {
						$parts  = explode( '/', $tz, 2 );
						$prefix = isset( $parts[1] ) ? $parts[0] : 'Other';
						$grouped[ $prefix ][] = $tz;
					}
					ksort( $grouped );
					foreach ( $grouped as $continent => $zones ) :
					?>
					<optgroup label="<?php echo esc_attr( $continent ); ?>">
						<?php foreach ( $zones as $tz ) : ?>
						<option value="<?php echo esc_attr( $tz ); ?>"
						        <?php selected( $profile['ftt_timezone'], $tz ); ?>>
							<?php echo esc_html( str_replace( '_', ' ', $tz ) ); ?>
						</option>
						<?php endforeach; ?>
					</optgroup>
					<?php endforeach; ?>
				</select>
				<p class="ftt-field-hint">
					<?php esc_html_e( 'Event times, email digests, and reminders will all respect your timezone.', 'schedule-collaboration-tracking' ); ?>
				</p>
			</div>

			<div class="ftt-form-field">
				<label><?php esc_html_e( 'Default Calendar View', 'schedule-collaboration-tracking' ); ?></label>
				<div class="ftt-radio-group">
					<?php
					$views = array(
						'month'  => __( 'Month', 'schedule-collaboration-tracking' ),
						'week'   => __( 'Week', 'schedule-collaboration-tracking' ),
						'agenda' => __( 'Agenda (list)', 'schedule-collaboration-tracking' ),
					);
					foreach ( $views as $val => $label ) :
					?>
					<label class="ftt-radio-label">
						<input type="radio" name="ftt_calendar_view" value="<?php echo esc_attr( $val ); ?>"
						       <?php checked( $profile['ftt_calendar_view'], $val ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
					<?php endforeach; ?>
				</div>
			</div>

		</div>

		<div class="ftt-profile-actions">
			<button type="button" class="ftt-btn ftt-btn-primary" data-action="save-calendar">
				<?php esc_html_e( 'Save Calendar Settings', 'schedule-collaboration-tracking' ); ?>
			</button>
		</div>
	</section>

	<!-- ================================================================ -->
	<!-- Section 5 – Notification Preferences                             -->
	<!-- ================================================================ -->
	<section class="ftt-profile-section" id="ftt-section-notifications">
		<h2 class="ftt-profile-section-title">
			<span class="ftt-profile-section-icon">🔔</span>
			<?php esc_html_e( 'Notifications', 'schedule-collaboration-tracking' ); ?>
		</h2>

		<div class="ftt-profile-form-grid">

			<div class="ftt-form-field">
				<label class="ftt-toggle-label">
					<span class="ftt-toggle-switch">
						<input type="checkbox" id="ftt-digest-enabled" name="ftt_digest_enabled"
						       value="1" <?php checked( $profile['ftt_digest_enabled'] ); ?>>
						<span class="ftt-toggle-slider"></span>
					</span>
					<span class="ftt-toggle-text"><?php esc_html_e( 'Email digest', 'schedule-collaboration-tracking' ); ?></span>
				</label>
				<p class="ftt-field-hint"><?php esc_html_e( "Send me a summary of upcoming events and schedule changes.", 'schedule-collaboration-tracking' ); ?></p>
			</div>

			<div class="ftt-form-field" id="ftt-digest-freq-row" <?php echo $profile['ftt_digest_enabled'] ? '' : 'style="display:none;"'; ?>>
				<label><?php esc_html_e( 'Digest Frequency', 'schedule-collaboration-tracking' ); ?></label>
				<div class="ftt-radio-group">
					<label class="ftt-radio-label">
						<input type="radio" name="ftt_digest_frequency" value="daily"
						       <?php checked( $profile['ftt_digest_frequency'], 'daily' ); ?>>
						<?php esc_html_e( 'Daily', 'schedule-collaboration-tracking' ); ?>
					</label>
					<label class="ftt-radio-label">
						<input type="radio" name="ftt_digest_frequency" value="weekly"
						       <?php checked( $profile['ftt_digest_frequency'], 'weekly' ); ?>>
						<?php esc_html_e( 'Weekly', 'schedule-collaboration-tracking' ); ?>
					</label>
				</div>
			</div>

		</div>

		<div class="ftt-profile-actions">
			<button type="button" class="ftt-btn ftt-btn-primary" data-action="save-notifications">
				<?php esc_html_e( 'Save Notification Settings', 'schedule-collaboration-tracking' ); ?>
			</button>
		</div>
	</section>

	<!-- ================================================================ -->
	<!-- Section 6 – Connected Calendars                                  -->
	<!-- ================================================================ -->
	<section class="ftt-profile-section" id="ftt-section-ext-calendars">
		<h2 class="ftt-profile-section-title">
			<span class="ftt-profile-section-icon">🔗</span>
			<?php esc_html_e( 'Connected Calendars', 'schedule-collaboration-tracking' ); ?>
		</h2>
		<p class="ftt-field-hint" style="margin-bottom:1.25rem;">
			<?php esc_html_e( 'Paste a Google Calendar, Apple iCloud, OurFamilyWizard, or any iCal (.ics) feed URL here. Events will appear on your Family Travel Tracker calendar in a distinct colour — read-only, no login required.', 'schedule-collaboration-tracking' ); ?>
		</p>

		<div id="ftt-ext-feed-list">
			<?php
			$ext_feeds  = FTT_External_Calendars::get_feeds( get_current_user_id() );
			$colors     = FTT_External_Calendars::ALLOWED_COLORS;
			$color_names = array(
				'#7986CB' => __( 'Indigo',     'schedule-collaboration-tracking' ),
				'#33B679' => __( 'Sage',       'schedule-collaboration-tracking' ),
				'#8E24AA' => __( 'Grape',      'schedule-collaboration-tracking' ),
				'#E67C73' => __( 'Flamingo',   'schedule-collaboration-tracking' ),
				'#F6BF26' => __( 'Banana',     'schedule-collaboration-tracking' ),
				'#F4511E' => __( 'Tangerine',  'schedule-collaboration-tracking' ),
				'#039BE5' => __( 'Peacock',    'schedule-collaboration-tracking' ),
				'#616161' => __( 'Graphite',   'schedule-collaboration-tracking' ),
				'#3F9142' => __( 'Basil',      'schedule-collaboration-tracking' ),
				'#D50000' => __( 'Tomato',     'schedule-collaboration-tracking' ),
			);

			// Render saved feeds + one blank row if under the limit
			$render_feeds = $ext_feeds;
			if ( count( $render_feeds ) < FTT_External_Calendars::MAX_FEEDS ) {
				$render_feeds[] = array( 'url' => '', 'label' => '', 'color' => $colors[0] );
			}

			foreach ( $render_feeds as $idx => $feed ) :
				$saved_color = $feed['color'] ?: $colors[0];
				$has_error   = ! empty( $feed['last_error'] );
			?>
			<div class="ftt-ext-feed-row" data-index="<?php echo esc_attr( $idx ); ?>">
				<div class="ftt-ext-feed-fields">

					<!-- URL -->
					<div class="ftt-form-field ftt-ext-url-field">
						<label><?php esc_html_e( 'Calendar Feed URL', 'schedule-collaboration-tracking' ); ?></label>
						<input type="url"
						       class="ftt-ext-feed-url"
						       placeholder="https://calendar.google.com/calendar/ical/…"
						       value="<?php echo esc_attr( $feed['url'] ); ?>"
						       autocomplete="off">
					</div>

					<!-- Label -->
					<div class="ftt-form-field ftt-ext-label-field">
						<label><?php esc_html_e( 'Label', 'schedule-collaboration-tracking' ); ?></label>
						<input type="text"
						       class="ftt-ext-feed-label"
						       placeholder="<?php esc_attr_e( 'e.g. Google Work', 'schedule-collaboration-tracking' ); ?>"
						       value="<?php echo esc_attr( $feed['label'] ); ?>"
						       maxlength="40">
					</div>

					<!-- Color swatch picker -->
					<div class="ftt-form-field ftt-ext-color-field">
						<label><?php esc_html_e( 'Color', 'schedule-collaboration-tracking' ); ?></label>
						<div class="ftt-color-swatch-picker">
							<?php foreach ( $colors as $hex ) : ?>
							<button type="button"
							        class="ftt-color-swatch <?php echo $hex === $saved_color ? 'is-selected' : ''; ?>"
							        data-color="<?php echo esc_attr( $hex ); ?>"
							        style="background-color:<?php echo esc_attr( $hex ); ?>;"
							        aria-label="<?php echo esc_attr( $color_names[ $hex ] ?? $hex ); ?>"
							        title="<?php echo esc_attr( $color_names[ $hex ] ?? $hex ); ?>">
							</button>
							<?php endforeach; ?>
							<input type="hidden" class="ftt-ext-feed-color" value="<?php echo esc_attr( $saved_color ); ?>">
						</div>
					</div>

				</div><!-- .ftt-ext-feed-fields -->

				<!-- Status + remove -->
				<div class="ftt-ext-feed-meta">
					<?php if ( ! empty( $feed['last_fetched'] ) ) : ?>
					<span class="ftt-ext-badge ftt-ext-badge-ok">
						<?php
						/* translators: %s: date/time string */
						printf( esc_html__( 'Synced %s', 'schedule-collaboration-tracking' ), esc_html( $feed['last_fetched'] ) );
						?>
					</span>
					<?php endif; ?>
					<?php if ( $has_error ) : ?>
					<span class="ftt-ext-badge ftt-ext-badge-error" title="<?php echo esc_attr( $feed['last_error'] ); ?>">
						⚠ <?php esc_html_e( 'Fetch error', 'schedule-collaboration-tracking' ); ?>
					</span>
					<?php endif; ?>
					<button type="button" class="ftt-btn ftt-btn-ghost ftt-ext-remove-btn"
					        aria-label="<?php esc_attr_e( 'Remove this feed', 'schedule-collaboration-tracking' ); ?>">
						✕ <?php esc_html_e( 'Remove', 'schedule-collaboration-tracking' ); ?>
					</button>
				</div>

			</div><!-- .ftt-ext-feed-row -->
			<?php endforeach; ?>
		</div><!-- #ftt-ext-feed-list -->

		<div class="ftt-ext-feed-footer">
			<button type="button" id="ftt-ext-add-feed"
			        class="ftt-btn ftt-btn-ghost"
			        <?php echo count( $ext_feeds ) >= FTT_External_Calendars::MAX_FEEDS ? 'disabled' : ''; ?>>
				+ <?php esc_html_e( 'Add Another Calendar', 'schedule-collaboration-tracking' ); ?>
			</button>
			<span class="ftt-ext-feed-count-note">
				<?php
				printf(
					/* translators: 1: current count, 2: max */
					esc_html__( '%1$d / %2$d feeds', 'schedule-collaboration-tracking' ),
					count( $ext_feeds ),
					FTT_External_Calendars::MAX_FEEDS
				);
				?>
			</span>
		</div>

		<div class="ftt-profile-actions">
			<button type="button" class="ftt-btn ftt-btn-primary" data-action="save-ext-calendars">
				<?php esc_html_e( 'Save Connected Calendars', 'schedule-collaboration-tracking' ); ?>
			</button>
			<?php if ( ! empty( $ext_feeds ) ) : ?>
			<button type="button" class="ftt-btn ftt-btn-ghost" data-action="refresh-ext-calendars">
				<?php esc_html_e( 'Refresh Now', 'schedule-collaboration-tracking' ); ?>
			</button>
			<?php endif; ?>
		</div>

		<!-- Template row (hidden, cloned by JS) -->
		<template id="ftt-ext-feed-row-tpl">
			<div class="ftt-ext-feed-row">
				<div class="ftt-ext-feed-fields">
					<div class="ftt-form-field ftt-ext-url-field">
						<label><?php esc_html_e( 'Calendar Feed URL', 'schedule-collaboration-tracking' ); ?></label>
						<input type="url" class="ftt-ext-feed-url"
						       placeholder="https://calendar.google.com/calendar/ical/…"
						       autocomplete="off">
					</div>
					<div class="ftt-form-field ftt-ext-label-field">
						<label><?php esc_html_e( 'Label', 'schedule-collaboration-tracking' ); ?></label>
						<input type="text" class="ftt-ext-feed-label"
						       placeholder="<?php esc_attr_e( 'e.g. Google Work', 'schedule-collaboration-tracking' ); ?>"
						       maxlength="40">
					</div>
					<div class="ftt-form-field ftt-ext-color-field">
						<label><?php esc_html_e( 'Color', 'schedule-collaboration-tracking' ); ?></label>
						<div class="ftt-color-swatch-picker">
							<?php foreach ( $colors as $hex ) : ?>
							<button type="button"
							        class="ftt-color-swatch <?php echo $hex === $colors[0] ? 'is-selected' : ''; ?>"
							        data-color="<?php echo esc_attr( $hex ); ?>"
							        style="background-color:<?php echo esc_attr( $hex ); ?>;"
							        aria-label="<?php echo esc_attr( $color_names[ $hex ] ?? $hex ); ?>"
							        title="<?php echo esc_attr( $color_names[ $hex ] ?? $hex ); ?>">
							</button>
							<?php endforeach; ?>
							<input type="hidden" class="ftt-ext-feed-color" value="<?php echo esc_attr( $colors[0] ); ?>">
						</div>
					</div>
				</div>
				<div class="ftt-ext-feed-meta">
					<button type="button" class="ftt-btn ftt-btn-ghost ftt-ext-remove-btn"
					        aria-label="<?php esc_attr_e( 'Remove this feed', 'schedule-collaboration-tracking' ); ?>">
						✕ <?php esc_html_e( 'Remove', 'schedule-collaboration-tracking' ); ?>
					</button>
				</div>
			</div>
		</template>

	</section>

	<?php if ( ! empty( $children ) ) : ?>
	<!-- ================================================================ -->
	<!-- Section 7 – Children's Profiles (parents only)                  -->
	<!-- ================================================================ -->
	<section class="ftt-profile-section" id="ftt-section-children">
		<h2 class="ftt-profile-section-title">
			<span class="ftt-profile-section-icon">👨‍👩‍👧‍👦</span>
			<?php esc_html_e( "Children's Profiles", 'schedule-collaboration-tracking' ); ?>
		</h2>
		<p class="ftt-field-hint" style="margin-bottom:1.5rem;">
			<?php esc_html_e( 'Set each child\'s timezone and home airports so their calendars and trip legs use the right settings.', 'schedule-collaboration-tracking' ); ?>
		</p>

		<?php foreach ( $children as $child ) :
			$cp = FTT_User_Profile::get_profile_data( $child->ID );
			$child_airports = $cp['ftt_home_airports'];
		?>
		<div class="ftt-child-profile-card" data-child-id="<?php echo esc_attr( $child->ID ); ?>">

			<div class="ftt-child-profile-header">
				<strong class="ftt-child-name"><?php echo esc_html( $child->display_name ); ?></strong>
				<button type="button" class="ftt-btn ftt-btn-ghost ftt-child-toggle" aria-expanded="false">
					<?php esc_html_e( 'Edit settings', 'schedule-collaboration-tracking' ); ?> ▾
				</button>
			</div>

			<div class="ftt-child-profile-body" style="display:none;">

				<div class="ftt-profile-form-grid">

					<!-- Name -->
					<div class="ftt-form-row">
						<div class="ftt-form-field">
							<label><?php esc_html_e( 'First Name', 'schedule-collaboration-tracking' ); ?></label>
							<input type="text" class="ftt-child-field" name="first_name"
							       value="<?php echo esc_attr( $cp['first_name'] ); ?>">
						</div>
						<div class="ftt-form-field">
							<label><?php esc_html_e( 'Last Name', 'schedule-collaboration-tracking' ); ?></label>
							<input type="text" class="ftt-child-field" name="last_name"
							       value="<?php echo esc_attr( $cp['last_name'] ); ?>">
						</div>
					</div>

					<div class="ftt-form-field">
						<label><?php esc_html_e( 'Display Name', 'schedule-collaboration-tracking' ); ?></label>
						<input type="text" class="ftt-child-field" name="display_name"
						       value="<?php echo esc_attr( $cp['display_name'] ); ?>">
						<p class="ftt-field-hint"><?php esc_html_e( "What shows on the family calendar — e.g. 'Emma' or 'Emma S.'", 'schedule-collaboration-tracking' ); ?></p>
					</div>

					<!-- Timezone -->
					<div class="ftt-form-field">
						<label><?php esc_html_e( 'Timezone', 'schedule-collaboration-tracking' ); ?></label>
						<select class="ftt-child-field" name="ftt_timezone">
							<option value=""><?php esc_html_e( '— Same as parent —', 'schedule-collaboration-tracking' ); ?></option>
							<?php
							$grouped_child = array();
							foreach ( $timezones as $tz ) {
								$parts  = explode( '/', $tz, 2 );
								$prefix = isset( $parts[1] ) ? $parts[0] : 'Other';
								$grouped_child[ $prefix ][] = $tz;
							}
							ksort( $grouped_child );
							foreach ( $grouped_child as $continent => $zones ) :
							?>
							<optgroup label="<?php echo esc_attr( $continent ); ?>">
								<?php foreach ( $zones as $tz ) : ?>
								<option value="<?php echo esc_attr( $tz ); ?>"
								        <?php selected( $cp['ftt_timezone'], $tz ); ?>>
									<?php echo esc_html( str_replace( '_', ' ', $tz ) ); ?>
								</option>
								<?php endforeach; ?>
							</optgroup>
							<?php endforeach; ?>
						</select>
					</div>

					<!-- Home Airports -->
					<div class="ftt-form-field">
						<label><?php esc_html_e( 'Home Airport(s)', 'schedule-collaboration-tracking' ); ?></label>
						<div class="ftt-home-airports-container">
							<?php
							$child_labels = array(
								__( 'Primary Airport', 'schedule-collaboration-tracking' ),
								__( 'Secondary Airport', 'schedule-collaboration-tracking' ),
								__( 'Tertiary Airport', 'schedule-collaboration-tracking' ),
							);
							for ( $i = 0; $i < 3; $i++ ) :
								$iata_child = $child_airports[ $i ] ?? '';
							?>
							<div class="ftt-airport-row" data-slot="<?php echo esc_attr( $i ); ?>">
								<span class="ftt-airport-slot-label"><?php echo esc_html( $child_labels[ $i ] ); ?></span>
								<div class="ftt-airport-picker-wrap" style="position:relative;">
									<input type="text"
									       class="ftt-airport-search"
									       data-slot="<?php echo esc_attr( $i ); ?>"
									       value="<?php echo $iata_child ? esc_attr( $iata_child ) : ''; ?>"
									       placeholder="<?php esc_attr_e( 'Type city or code…', 'schedule-collaboration-tracking' ); ?>"
									       autocomplete="off">
									<input type="hidden"
									       class="ftt-airport-code"
									       data-slot="<?php echo esc_attr( $i ); ?>"
									       value="<?php echo esc_attr( $iata_child ); ?>">
									<ul class="ftt-airport-suggestions" style="display:none;"></ul>
								</div>
							</div>
							<?php endfor; ?>
						</div>
					</div>

				</div><!-- .ftt-profile-form-grid -->

				<div class="ftt-profile-actions">
					<button type="button" class="ftt-btn ftt-btn-primary ftt-save-child"
					        data-child-id="<?php echo esc_attr( $child->ID ); ?>">
						<?php
						/* translators: %s: child's first name */
						echo esc_html( sprintf( __( "Save %s's Settings", 'schedule-collaboration-tracking' ), $cp['first_name'] ?: $child->display_name ) );
						?>
					</button>
				</div>

			</div><!-- .ftt-child-profile-body -->
		</div><!-- .ftt-child-profile-card -->
		<?php endforeach; ?>

	</section>
	<?php endif; ?>

</div><!-- .ftt-profile-page -->
