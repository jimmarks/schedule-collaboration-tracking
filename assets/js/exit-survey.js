/**
 * FTT Exit Survey / Retention Modal
 *
 * Reads window.fttExitSurveys (injected via wp_localize_script) and sets up
 * triggers for each active survey on the current page.
 */
(function ($) {
    'use strict';

    if (!window.fttExitSurveys || !fttExitSurveys.surveys || !fttExitSurveys.surveys.length) {
        return;
    }

    var surveys    = fttExitSurveys.surveys;
    var restUrl    = fttExitSurveys.restUrl;
    var nonce      = fttExitSurveys.nonce;
    var pageUrl    = fttExitSurveys.pageUrl;
    var modalOpen  = false;
    var activeSurvey = null;
    var currentStep  = 0;
    var answers = {};

    // -------------------------------------------------------------------------
    // Modal HTML
    // -------------------------------------------------------------------------

    function buildModal() {
        if ($('#ftt-survey-modal').length) return;

        var html =
            '<div id="ftt-survey-overlay" class="ftt-survey-overlay" role="dialog" aria-modal="true" aria-labelledby="ftt-survey-title">' +
            '  <div class="ftt-survey-modal">' +
            '    <button class="ftt-survey-close" id="ftt-survey-close" aria-label="Close">&times;</button>' +
            '    <div class="ftt-survey-header">' +
            '      <h2 id="ftt-survey-title" class="ftt-survey-heading"></h2>' +
            '    </div>' +
            '    <div class="ftt-survey-body" id="ftt-survey-body"></div>' +
            '    <div class="ftt-survey-footer" id="ftt-survey-footer"></div>' +
            '  </div>' +
            '</div>';

        $('body').append(html);

        $('#ftt-survey-close, #ftt-survey-overlay').on('click', function (e) {
            if (e.target === this) closeModal();
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });
    }

    // -------------------------------------------------------------------------
    // Modal control
    // -------------------------------------------------------------------------

    function openSurvey(survey) {
        if (modalOpen) return;
        activeSurvey = survey;
        currentStep  = 0;
        answers      = {};
        buildModal();

        // Headline: use configured headline if set, otherwise fall back to survey name
        var headline = survey.headline || survey.name || '';
        $('#ftt-survey-title').text(headline);

        // Sub-headline
        $('#ftt-survey-subheadline').remove();
        if (survey.subheadline) {
            $('#ftt-survey-title').after(
                '<p id="ftt-survey-subheadline" class="ftt-survey-subheadline">' + escHtml(survey.subheadline) + '</p>'
            );
        }

        renderStep();
        $('#ftt-survey-overlay').addClass('ftt-survey-visible');
        modalOpen = true;
        $('body').addClass('ftt-survey-open');
    }

    function closeModal() {
        $('#ftt-survey-overlay').removeClass('ftt-survey-visible');
        modalOpen = false;
        $('body').removeClass('ftt-survey-open');
    }

    // -------------------------------------------------------------------------
    // Step rendering
    // -------------------------------------------------------------------------

    function renderStep() {
        var questions = activeSurvey.questions || [];
        var $body     = $('#ftt-survey-body');
        var $footer   = $('#ftt-survey-footer');

        if (currentStep >= questions.length) {
            // All questions answered — submit
            submitAnswers();
            return;
        }

        var q       = questions[currentStep];
        var total   = questions.length;
        var stepNum = currentStep + 1;
        var label   = q.label || '';
        var type    = q.type  || 'radio';
        var options = q.options || [];

        var progressPct = Math.round(((currentStep) / total) * 100);

        var bodyHtml =
            '<div class="ftt-survey-progress">' +
            '  <div class="ftt-survey-progress-bar" style="width:' + progressPct + '%"></div>' +
            '</div>' +
            '<p class="ftt-survey-step-label">' + escHtml('Question ' + stepNum + ' of ' + total) + '</p>' +
            '<p class="ftt-survey-question">' + escHtml(label) + '</p>' +
            '<div class="ftt-survey-answers" id="ftt-survey-answers">';

        if (type === 'text') {
            bodyHtml += '<textarea class="ftt-survey-textarea" id="ftt-survey-text-answer" rows="4" placeholder="Your answer…"></textarea>';
        } else {
            var inputType = (type === 'checkbox') ? 'checkbox' : 'radio';
            options.forEach(function (opt, i) {
                // Support both legacy plain strings and new {label, response} objects
                var optLabel = (typeof opt === 'object' && opt !== null) ? (opt.label || '') : String(opt);
                var id = 'ftt-ans-' + i;
                bodyHtml +=
                    '<label class="ftt-survey-option" for="' + id + '">' +
                    '  <input type="' + inputType + '" name="ftt_survey_ans" id="' + id + '" value="' + escAttr(optLabel) + '" data-opt-index="' + i + '">' +
                    '  <span>' + escHtml(optLabel) + '</span>' +
                    '</label>';
            });
        }

        bodyHtml += '</div>';

        $body.html(bodyHtml);

        // Footer buttons
        var footerHtml = '';
        if (currentStep > 0) {
            footerHtml += '<button class="ftt-survey-btn ftt-survey-btn-secondary" id="ftt-survey-back">← Back</button>';
        }
        var isLast = (currentStep === total - 1);
        footerHtml += '<button class="ftt-survey-btn ftt-survey-btn-primary" id="ftt-survey-next">' +
            (isLast ? 'Submit' : 'Next →') + '</button>';

        $footer.html(footerHtml);

        $('#ftt-survey-back').on('click', function () {
            currentStep--;
            renderStep();
        });

        $('#ftt-survey-next').on('click', function () {
            if (!collectAnswer(q)) return;

            // For radio questions: check whether the chosen option has a conditional response
            if (type === 'radio') {
                var $checked  = $('input[name="ftt_survey_ans"]:checked');
                var selIdx    = $checked.length ? parseInt($checked.data('opt-index'), 10) : -1;
                var selOpt    = (selIdx >= 0 && options[selIdx]) ? options[selIdx] : null;
                var condResp  = (selOpt && typeof selOpt === 'object') ? (selOpt.response || '') : '';
                if (condResp) {
                    renderConditionalResponse(condResp, function () {
                        currentStep++;
                        renderStep();
                    });
                    return;
                }
            }

            currentStep++;
            renderStep();
        });
    }

    function collectAnswer(q) {
        var type  = q.type || 'radio';
        var label = q.label || '';

        if (type === 'text') {
            var val = $('#ftt-survey-text-answer').val().trim();
            if (q.required && !val) {
                alert('Please enter an answer before continuing.');
                return false;
            }
            answers[label] = val;
        } else if (type === 'checkbox') {
            var checked = [];
            $('input[name="ftt_survey_ans"]:checked').each(function () {
                checked.push($(this).val());
            });
            if (q.required && !checked.length) {
                alert('Please select at least one option.');
                return false;
            }
            answers[label] = checked;
        } else {
            var sel = $('input[name="ftt_survey_ans"]:checked').val();
            if (q.required && !sel) {
                alert('Please select an option.');
                return false;
            }
            answers[label] = sel || '';
        }
        return true;
    }

    // -------------------------------------------------------------------------
    // Conditional response step
    // -------------------------------------------------------------------------

    function renderConditionalResponse(responseText, onContinue) {
        var $body   = $('#ftt-survey-body');
        var $footer = $('#ftt-survey-footer');

        $body.html(
            '<div class="ftt-survey-cond-response">' +
            '  <div class="ftt-survey-cond-icon">&#128161;</div>' +
            '  <p class="ftt-survey-cond-text">' + escHtml(responseText) + '</p>' +
            '</div>'
        );

        $footer.html(
            '<button class="ftt-survey-btn ftt-survey-btn-primary" id="ftt-survey-cond-continue">Continue &rarr;</button>'
        );

        $('#ftt-survey-cond-continue').on('click', onContinue);
    }

    // -------------------------------------------------------------------------
    // Submit
    // -------------------------------------------------------------------------

    function submitAnswers() {
        $('#ftt-survey-body').html('<p class="ftt-survey-submitting">Submitting…</p>');
        $('#ftt-survey-footer').html('');

        $.ajax({
            url: restUrl,
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce },
            contentType: 'application/json',
            data: JSON.stringify({
                survey_id: activeSurvey.id,
                answers:   answers,
                page_url:  pageUrl,
            }),
            success: function () {
                showThankYou();
            },
            error: function () {
                showThankYou(); // still show thank you even if server error
            },
        });
    }

    function showThankYou() {
        var msg = (activeSurvey && activeSurvey.thank_you_message)
            ? activeSurvey.thank_you_message
            : 'Thank you for your feedback!';

        $('#ftt-survey-body').html(
            '<div class="ftt-survey-thankyou">' +
            '  <div class="ftt-survey-thankyou-icon">✓</div>' +
            '  <p>' + escHtml(msg) + '</p>' +
            '</div>'
        );
        $('#ftt-survey-footer').html(
            '<button class="ftt-survey-btn ftt-survey-btn-secondary" id="ftt-survey-done">Close</button>'
        );
        $('#ftt-survey-done').on('click', closeModal);

        // Auto-close after 4s
        setTimeout(closeModal, 4000);
    }

    // -------------------------------------------------------------------------
    // Trigger setup
    // -------------------------------------------------------------------------

    function setupTriggers() {
        surveys.forEach(function (survey) {
            var trigger = survey.trigger || 'cancel_button';

            if (trigger === 'cancel_button') {
                // Intercept the #ftt-cancel-subscription button
                $(document).on('click', '#ftt-cancel-subscription', function (e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    openSurvey(survey);

                    // After survey closes, allow the actual cancel to proceed
                    $(document).one('fttSurveyClosed', function () {
                        // Re-trigger click without our intercept
                        $('#ftt-cancel-subscription').off('click.fttSurvey').trigger('click');
                    });
                });
                // Namespace the handler so we can remove just ours
                $(document).off('click', '#ftt-cancel-subscription');
                $(document).on('click.fttSurvey', '#ftt-cancel-subscription', function (e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    openSurvey(survey);
                });

            } else if (trigger === 'exit_intent') {
                var firedExitIntent = false;
                $(document).on('mouseleave.fttExitIntent', function (e) {
                    if (firedExitIntent || modalOpen) return;
                    if (e.clientY <= 5) {
                        firedExitIntent = true;
                        openSurvey(survey);
                    }
                });

            } else if (trigger === 'page_load') {
                var delay = parseInt(survey.delay || 5, 10) * 1000;
                setTimeout(function () {
                    if (!modalOpen) {
                        openSurvey(survey);
                    }
                }, delay);
            }
        });
    }

    // Close modal fires custom event so cancel_button trigger can continue
    var _originalClose = closeModal;
    closeModal = function () {
        _originalClose();
        $(document).trigger('fttSurveyClosed');
    };

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escAttr(str) {
        return escHtml(str);
    }

    // -------------------------------------------------------------------------
    // Init
    // -------------------------------------------------------------------------

    $(function () {
        setupTriggers();
    });

}(jQuery));
