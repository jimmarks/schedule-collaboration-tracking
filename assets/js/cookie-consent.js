/**
 * FTT Cookie Consent Banner
 *
 * - Shows the banner if no consent cookie is set yet
 * - On Accept: sets cookie + updates Google Consent Mode v2 to 'granted'
 * - On Decline: sets cookie (value = 'declined') + leaves GCM denied
 * - Hides the banner in either case
 */
(function () {
    'use strict';

    var cfg = window.fttCookieConsent || {};
    var COOKIE_NAME = cfg.cookieName || 'ftt_cookie_consent';
    var DAYS        = parseInt(cfg.days, 10) || 365;
    var POSITION    = cfg.position || 'bottom';

    // ------------------------------------------------------------------
    // Cookie helpers
    // ------------------------------------------------------------------

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }

    function setCookie(name, value, days) {
        var expires = new Date(Date.now() + days * 864e5).toUTCString();
        document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=/; SameSite=Lax';
    }

    // ------------------------------------------------------------------
    // Google Consent Mode v2 helper
    // ------------------------------------------------------------------

    function updateGCM(granted) {
        if (typeof window.gtag !== 'function') {
            // gtag not loaded yet — push directly to dataLayer
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({
                event: 'consent_update',
                'ad_storage':          granted ? 'granted' : 'denied',
                'analytics_storage':   granted ? 'granted' : 'denied',
                'ad_user_data':        granted ? 'granted' : 'denied',
                'ad_personalization':  granted ? 'granted' : 'denied',
            });
            return;
        }
        window.gtag('consent', 'update', {
            'ad_storage':         granted ? 'granted' : 'denied',
            'analytics_storage':  granted ? 'granted' : 'denied',
            'ad_user_data':       granted ? 'granted' : 'denied',
            'ad_personalization': granted ? 'granted' : 'denied',
        });
    }

    // ------------------------------------------------------------------
    // Banner show / hide
    // ------------------------------------------------------------------

    function hideBanner(banner) {
        banner.setAttribute('hidden', '');
        banner.style.transform  = '';
        banner.style.transition = '';
    }

    function showBanner(banner) {
        banner.removeAttribute('hidden');
        // Small delay so CSS transition fires
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                banner.classList.add('ftt-cc-visible');
            });
        });
    }

    // ------------------------------------------------------------------
    // Global: re-open the consent banner
    // Called by [ftt_manage_cookies] shortcode button and the cookie policy
    // page.  GDPR Art. 7(3) requires withdrawal to be as easy as giving
    // consent — this clears the stored choice and re-shows the banner.
    // ------------------------------------------------------------------

    window.fttManageCookies = function () {
        // Expire the consent cookie immediately
        document.cookie = COOKIE_NAME + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; SameSite=Lax';
        // Reset Google Consent Mode back to denied
        updateGCM(false);
        // Re-show the banner
        var banner = document.getElementById('ftt-cookie-banner');
        if (banner) {
            banner.classList.remove('ftt-cc-visible');
            banner.removeAttribute('hidden');
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    banner.classList.add('ftt-cc-visible');
                });
            });
        }
    };

    // ------------------------------------------------------------------
    // Main
    // ------------------------------------------------------------------

    document.addEventListener('DOMContentLoaded', function () {
        var banner  = document.getElementById('ftt-cookie-banner');
        var btnAccept  = document.getElementById('ftt-cc-accept');
        var btnDecline = document.getElementById('ftt-cc-decline');

        if (!banner) return;

        var existing = getCookie(COOKIE_NAME);

        // If consent already recorded, update GCM state and stay hidden
        if (existing) {
            if (existing === 'accepted') {
                updateGCM(true);
            }
            return;
        }

        // No prior consent — show the banner
        showBanner(banner);

        if (btnAccept) {
            btnAccept.addEventListener('click', function () {
                setCookie(COOKIE_NAME, 'accepted', DAYS);
                updateGCM(true);
                hideBanner(banner);
            });
        }

        if (btnDecline) {
            btnDecline.addEventListener('click', function () {
                setCookie(COOKIE_NAME, 'declined', DAYS);
                updateGCM(false);
                hideBanner(banner);
            });
        }
    });
}());
