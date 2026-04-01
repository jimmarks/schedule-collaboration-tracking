/**
 * Profile / Personal Settings – JavaScript
 *
 * Handles:
 *  - Airport typeahead on all .ftt-airport-search inputs
 *  - Saving each settings section via REST
 *  - Child profile expand/collapse + child save
 *
 * Depends on: fttProfileData injected by FTT_User_Profile::render_shortcode()
 *   fttProfileData.restUrl  – e.g. "/wp-json/ftt/v1/"
 *   fttProfileData.nonce    – WP REST nonce
 *   fttProfileData.airports – { "ORD": "Chicago, IL", … }
 *   fttProfileData.profile  – current user profile object
 *
 * @package Family_Travel_Tracker
 */

(function ($) {
    'use strict';

    /* ------------------------------------------------------------------ */
    /* Config                                                               */
    /* ------------------------------------------------------------------ */

    const rest    = fttProfileData.restUrl;
    const nonce   = fttProfileData.nonce;
    const airports = fttProfileData.airports || {};

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Show a status message to the user.
     * @param {string} text
     * @param {boolean} isError
     */
    function showMessage(text, isError) {
        const $msg = $('#ftt-profile-message');
        $msg.text(text)
            .removeClass('ftt-msg-success ftt-msg-error')
            .addClass(isError ? 'ftt-msg-error' : 'ftt-msg-success')
            .show();
        // Auto-hide after 5 s
        clearTimeout($msg.data('timer'));
        $msg.data('timer', setTimeout(function () { $msg.fadeOut(); }, 5000));
        // Scroll to message
        $('html, body').animate({ scrollTop: $msg.offset().top - 80 }, 300);
    }

    /**
     * POST to a REST endpoint.
     * @param {string} endpoint  – appended to fttProfileData.restUrl
     * @param {object} payload
     * @returns {Promise}
     */
    function restPost(endpoint, payload) {
        return fetch(rest + endpoint, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce':   nonce,
            },
            body: JSON.stringify(payload),
        }).then(res => res.json());
    }

    /* ------------------------------------------------------------------ */
    /* Airport typeahead                                                   */
    /* ------------------------------------------------------------------ */

    /** Build display string: "Chicago, IL (ORD)" */
    function airportLabel(code) {
        return airports[code] ? airports[code] + ' (' + code + ')' : code;
    }

    /**
     * Attach typeahead behaviour to an .ftt-airport-search input.
     * The sibling .ftt-airport-code input stores the chosen IATA code.
     * The sibling .ftt-airport-suggestions <ul> shows matches.
     */
    function initAirportPicker($input) {
        const $wrap   = $input.closest('.ftt-airport-picker-wrap');
        const $code   = $wrap.find('.ftt-airport-code');
        const $list   = $wrap.find('.ftt-airport-suggestions');

        // On page-load: if a code is already saved, show its full label
        const existing = $code.val();
        if (existing && airports[existing]) {
            $input.val(airportLabel(existing));
        }

        $input.on('input', function () {
            const q = $(this).val().trim().toUpperCase();
            if (q.length < 1) {
                $list.hide().empty();
                $code.val('');
                return;
            }

            const matches = [];
            $.each(airports, function (iata, city) {
                if (
                    iata.indexOf(q) === 0 ||                          // code starts with
                    city.toUpperCase().indexOf(q) !== -1              // city contains
                ) {
                    matches.push({ iata, city });
                }
            });

            // Sort: exact-code matches first, then alphabetical
            matches.sort(function (a, b) {
                if (a.iata === q) return -1;
                if (b.iata === q) return  1;
                return a.city.localeCompare(b.city);
            });

            $list.empty();
            if (matches.length === 0) {
                $list.hide();
                return;
            }

            matches.slice(0, 8).forEach(function (m) {
                $('<li>')
                    .text(m.city + ' (' + m.iata + ')')
                    .attr({ role: 'option', 'data-iata': m.iata })
                    .appendTo($list);
            });
            $list.show();
        });

        // Click on a suggestion
        $list.on('mousedown', 'li', function (e) {
            e.preventDefault();
            const iata = $(this).data('iata');
            $input.val(airportLabel(iata));
            $code.val(iata);
            $list.hide();
        });

        // Hide on blur
        $input.on('blur', function () {
            setTimeout(function () { $list.hide(); }, 200);
        });
    }

    /* ------------------------------------------------------------------ */
    /* Collect helpers                                                     */
    /* ------------------------------------------------------------------ */

    /** Collect personal info fields */
    function collectPersonal() {
        return {
            first_name:   $('#ftt-first-name').val().trim(),
            last_name:    $('#ftt-last-name').val().trim(),
            display_name: $('#ftt-display-name').val().trim(),
            user_email:   $('#ftt-email').val().trim(),
            phone:        $('#ftt-phone').val().trim(),
        };
    }

    /** Collect password change fields */
    function collectPassword() {
        return {
            current_password: $('#ftt-current-pass').val(),
            new_password:     $('#ftt-new-pass').val(),
            confirm_password: $('#ftt-confirm-pass').val(),
        };
    }

    /** Collect home airports from a container */
    function collectAirports($container) {
        const codes = [];
        $container.find('.ftt-airport-code').each(function () {
            const v = $(this).val().trim();
            if (v) codes.push(v);
        });
        return codes;
    }

    /** Collect calendar + timezone fields */
    function collectCalendar() {
        return {
            ftt_timezone:     $('#ftt-timezone').val(),
            ftt_calendar_view: $('input[name="ftt_calendar_view"]:checked').val() || 'month',
        };
    }

    /** Collect notification preferences */
    function collectNotifications() {
        return {
            ftt_digest_enabled:   $('#ftt-digest-enabled').is(':checked'),
            ftt_digest_frequency: $('input[name="ftt_digest_frequency"]:checked').val() || 'daily',
        };
    }

    /* ------------------------------------------------------------------ */
    /* Save handlers                                                       */
    /* ------------------------------------------------------------------ */

    $('[data-action="save-personal"]').on('click', function () {
        const $btn = $(this).prop('disabled', true).text('Saving…');
        restPost('profile/save', collectPersonal())
            .then(function (data) {
                if (data.success) {
                    showMessage('Personal information saved!', false);
                } else {
                    showMessage(data.message || 'Could not save. Please try again.', true);
                }
            })
            .catch(function () { showMessage('Network error. Please try again.', true); })
            .finally(function () { $btn.prop('disabled', false).text('Save Personal Info'); });
    });

    $('[data-action="save-password"]').on('click', function () {
        const payload = collectPassword();
        if (!payload.new_password && !payload.current_password) {
            showMessage('Enter your current and new password to make a change.', true);
            return;
        }
        const $btn = $(this).prop('disabled', true).text('Saving…');
        restPost('profile/save', payload)
            .then(function (data) {
                if (data.success) {
                    showMessage('Password updated! You may need to log in again.', false);
                    $('#ftt-current-pass, #ftt-new-pass, #ftt-confirm-pass').val('');
                } else {
                    showMessage(data.message || 'Password change failed.', true);
                }
            })
            .catch(function () { showMessage('Network error. Please try again.', true); })
            .finally(function () { $btn.prop('disabled', false).text('Update Password'); });
    });

    $('[data-action="save-travel"]').on('click', function () {
        const $btn = $(this).prop('disabled', true).text('Saving…');
        const payload = {
            ftt_home_airports: collectAirports($('#ftt-home-airports-container')),
        };
        restPost('profile/save', payload)
            .then(function (data) {
                if (data.success) {
                    showMessage('Travel preferences saved!', false);
                } else {
                    showMessage(data.message || 'Could not save. Please try again.', true);
                }
            })
            .catch(function () { showMessage('Network error. Please try again.', true); })
            .finally(function () { $btn.prop('disabled', false).text('Save Travel Preferences'); });
    });

    $('[data-action="save-calendar"]').on('click', function () {
        const $btn = $(this).prop('disabled', true).text('Saving…');
        restPost('profile/save', collectCalendar())
            .then(function (data) {
                if (data.success) {
                    showMessage('Calendar settings saved!', false);
                } else {
                    showMessage(data.message || 'Could not save. Please try again.', true);
                }
            })
            .catch(function () { showMessage('Network error. Please try again.', true); })
            .finally(function () { $btn.prop('disabled', false).text('Save Calendar Settings'); });
    });

    $('[data-action="save-notifications"]').on('click', function () {
        const $btn = $(this).prop('disabled', true).text('Saving…');
        restPost('profile/save', collectNotifications())
            .then(function (data) {
                if (data.success) {
                    showMessage('Notification settings saved!', false);
                } else {
                    showMessage(data.message || 'Could not save. Please try again.', true);
                }
            })
            .catch(function () { showMessage('Network error. Please try again.', true); })
            .finally(function () { $btn.prop('disabled', false).text('Save Notification Settings'); });
    });

    /* ------------------------------------------------------------------ */
    /* Child profile cards                                                 */
    /* ------------------------------------------------------------------ */

    // Toggle open/close
    $(document).on('click', '.ftt-child-toggle', function () {
        const $card = $(this).closest('.ftt-child-profile-card');
        const $body = $card.find('.ftt-child-profile-body');
        const open  = $body.is(':visible');
        $body.slideToggle(200);
        $(this)
            .attr('aria-expanded', String(!open))
            .html(open ? 'Edit settings ▾' : 'Close ▴');
    });

    // Save a child's profile
    $(document).on('click', '.ftt-save-child', function () {
        const $btn     = $(this).prop('disabled', true).text('Saving…');
        const childId  = $(this).data('child-id');
        const $card    = $(this).closest('.ftt-child-profile-card');
        const firstName = $card.find('[name="first_name"]').val().trim();

        const payload = {
            first_name:   firstName,
            last_name:    $card.find('[name="last_name"]').val().trim(),
            display_name: $card.find('[name="display_name"]').val().trim(),
            ftt_timezone: $card.find('[name="ftt_timezone"]').val(),
            ftt_home_airports: collectAirports($card.find('.ftt-home-airports-container')),
        };

        restPost('profile/child/' + childId + '/save', payload)
            .then(function (data) {
                if (data.success) {
                    showMessage(
                        (firstName || 'Child') + "'s settings saved!",
                        false
                    );
                    // Update the card header name
                    if (data.profile && data.profile.display_name) {
                        $card.find('.ftt-child-name').text(data.profile.display_name);
                    }
                } else {
                    showMessage(data.message || 'Could not save. Please try again.', true);
                }
            })
            .catch(function () { showMessage('Network error. Please try again.', true); })
            .finally(function () {
                const label = firstName ? "Save " + firstName + "'s Settings" : 'Save Child Settings';
                $btn.prop('disabled', false).text(label);
            });
    });

    /* ------------------------------------------------------------------ */
    /* Digest toggle                                                       */
    /* ------------------------------------------------------------------ */

    $('#ftt-digest-enabled').on('change', function () {
        if ($(this).is(':checked')) {
            $('#ftt-digest-freq-row').slideDown(150);
        } else {
            $('#ftt-digest-freq-row').slideUp(150);
        }
    });

    /* ------------------------------------------------------------------ */
    /* Connected Calendars (external iCal feed overlay)                   */
    /* ------------------------------------------------------------------ */

    const EXT_MAX = 5;

    /** Return current count of non-empty feed rows */
    function extFeedCount() {
        return $('#ftt-ext-feed-list .ftt-ext-feed-row').length;
    }

    /** Update the count note + Add button state */
    function extUpdateUI() {
        const count = extFeedCount();
        $('.ftt-ext-feed-count-note').text(count + ' / ' + EXT_MAX + ' feeds');
        $('#ftt-ext-add-feed').prop('disabled', count >= EXT_MAX);
    }

    /** Collect all feed rows into a plain array */
    function collectExtCalendars() {
        const feeds = [];
        $('#ftt-ext-feed-list .ftt-ext-feed-row').each(function () {
            const url   = $(this).find('.ftt-ext-feed-url').val().trim();
            const label = $(this).find('.ftt-ext-feed-label').val().trim();
            const color = $(this).find('.ftt-ext-feed-color').val() || '#7986CB';
            if (url) {
                feeds.push({ url, label, color });
            }
        });
        return feeds;
    }

    /** Wire up colour swatch picker in a given row */
    function initSwatchPicker($row) {
        $row.find('.ftt-color-swatch').on('click', function () {
            const $btn = $(this);
            $btn.closest('.ftt-color-swatch-picker')
                .find('.ftt-color-swatch').removeClass('is-selected');
            $btn.addClass('is-selected');
            $btn.siblings('.ftt-ext-feed-color').val($btn.data('color'));
        });
    }

    // Init existing rows
    $('#ftt-ext-feed-list .ftt-ext-feed-row').each(function () {
        initSwatchPicker($(this));
    });

    // Add new row via <template>
    $('#ftt-ext-add-feed').on('click', function () {
        if (extFeedCount() >= EXT_MAX) return;
        const tpl  = document.getElementById('ftt-ext-feed-row-tpl');
        const $row = $(tpl.content.cloneNode(true)).find('.ftt-ext-feed-row');
        $('#ftt-ext-feed-list').append($row);
        initSwatchPicker($row);
        extUpdateUI();
        $row.find('.ftt-ext-feed-url').trigger('focus');
    });

    // Remove row
    $(document).on('click', '.ftt-ext-remove-btn', function () {
        $(this).closest('.ftt-ext-feed-row').remove();
        extUpdateUI();
    });

    // Save feeds
    $('[data-action="save-ext-calendars"]').on('click', function () {
        const feeds = collectExtCalendars();
        const $btn  = $(this).prop('disabled', true).text('Saving…');

        // Basic URL validation
        for (const f of feeds) {
            try {
                const u = new URL(f.url);
                if (!['http:', 'https:', 'webcal:'].includes(u.protocol)) {
                    showMessage('Feed URL must start with http://, https://, or webcal://', true);
                    $btn.prop('disabled', false).text('Save Connected Calendars');
                    return;
                }
            } catch (e) {
                showMessage('Invalid feed URL: ' + f.url, true);
                $btn.prop('disabled', false).text('Save Connected Calendars');
                return;
            }
        }

        fetch(rest + 'external-calendars/save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
            body: JSON.stringify({ feeds }),
        })
        .then(r => r.json())
        .then(function (data) {
            if (data.success) {
                showMessage('Connected calendars saved! Events will appear on your calendar within a minute.', false);
                extUpdateUI();
            } else {
                showMessage(data.message || 'Could not save. Please try again.', true);
            }
        })
        .catch(function () { showMessage('Network error. Please try again.', true); })
        .finally(function () { $btn.prop('disabled', false).text('Save Connected Calendars'); });
    });

    // Force refresh
    $('[data-action="refresh-ext-calendars"]').on('click', function () {
        const $btn = $(this).prop('disabled', true).text('Refreshing…');
        fetch(rest + 'external-calendars/refresh', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
            body: JSON.stringify({}),
        })
        .then(r => r.json())
        .then(function (data) {
            if (data.success) {
                const msgs = data.status.map(s =>
                    s.error
                        ? `⚠ ${s.label}: ${s.error}`
                        : `✓ ${s.label}: ${s.count} event(s)`
                );
                showMessage('Refreshed! ' + msgs.join(' | '), false);
            } else {
                showMessage(data.message || 'Refresh failed.', true);
            }
        })
        .catch(function () { showMessage('Network error. Please try again.', true); })
        .finally(function () { $btn.prop('disabled', false).text('Refresh Now'); });
    });

    extUpdateUI();

    /* ------------------------------------------------------------------ */
    /* Init all airport pickers                                            */
    /* ------------------------------------------------------------------ */

    $('.ftt-airport-search').each(function () {
        initAirportPicker($(this));
    });

}(jQuery));
