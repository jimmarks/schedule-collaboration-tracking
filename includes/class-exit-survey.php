<?php
/**
 * Exit Survey / Retention Modal
 *
 * Provides:
 *  - Admin menu page (under ftt_event CPT) for building surveys:
 *      • Multiple surveys, each with a name, trigger pages, questions, and answer options
 *  - Responses tab showing all submitted answers
 *  - REST endpoint  POST /ftt/v1/exit-survey/respond   for saving answers
 *  - Front-end enqueue (survey JS + inline JSON config) on configured pages
 *
 * Storage:
 *  - Surveys config  →  wp_option  ftt_exit_surveys   (array of survey objects)
 *  - Responses       →  wp_option  ftt_exit_responses  (append-only array, capped at 500)
 *
 * @package Family_Travel_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FTT_Exit_Survey {

    const OPT_SURVEYS   = 'ftt_exit_surveys';
    const OPT_RESPONSES = 'ftt_exit_responses';
    const CAP           = 'manage_options';
    const MENU_SLUG     = 'ftt-exit-surveys';
    const MAX_RESPONSES = 500;

    // -------------------------------------------------------------------------
    // Boot
    // -------------------------------------------------------------------------

    public static function init() {
        add_action( 'admin_menu',        [ __CLASS__, 'register_admin_menu' ] );
        add_action( 'admin_init',        [ __CLASS__, 'handle_admin_post' ] );
        add_action( 'wp_enqueue_scripts',[ __CLASS__, 'maybe_enqueue' ] );
        add_action( 'rest_api_init',     [ __CLASS__, 'register_rest' ] );
    }

    // -------------------------------------------------------------------------
    // Admin menu
    // -------------------------------------------------------------------------

    public static function register_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=ftt_event',
            __( 'Exit Surveys', 'schedule-collaboration-tracking' ),
            __( 'Exit Surveys', 'schedule-collaboration-tracking' ),
            self::CAP,
            self::MENU_SLUG,
            [ __CLASS__, 'render_admin_page' ]
        );
    }

    // -------------------------------------------------------------------------
    // Admin page render
    // -------------------------------------------------------------------------

    public static function render_admin_page() {
        if ( ! current_user_can( self::CAP ) ) {
            return;
        }

        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'surveys';
        $surveys = self::get_surveys();

        // Editing a specific survey?
        $edit_id  = isset( $_GET['edit'] ) ? sanitize_key( $_GET['edit'] ) : null;
        $edit_survey = null;
        if ( $edit_id ) {
            foreach ( $surveys as $s ) {
                if ( $s['id'] === $edit_id ) {
                    $edit_survey = $s;
                    break;
                }
            }
        }
        ?>
        <div class="wrap ftt-exit-surveys-wrap">
            <h1><?php esc_html_e( 'Exit Surveys', 'schedule-collaboration-tracking' ); ?></h1>

            <nav class="nav-tab-wrapper" style="margin-bottom:20px;">
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=ftt_event&page=' . self::MENU_SLUG . '&tab=surveys' ) ); ?>"
                   class="nav-tab <?php echo $tab === 'surveys' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Surveys', 'schedule-collaboration-tracking' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=ftt_event&page=' . self::MENU_SLUG . '&tab=responses' ) ); ?>"
                   class="nav-tab <?php echo $tab === 'responses' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Responses', 'schedule-collaboration-tracking' ); ?>
                    <?php
                    $count = count( self::get_responses() );
                    if ( $count ) {
                        echo '<span class="update-plugins" style="margin-left:4px;"><span>' . esc_html( $count ) . '</span></span>';
                    }
                    ?>
                </a>
            </nav>

            <?php if ( $tab === 'surveys' ) : ?>
                <?php if ( $edit_survey || isset( $_GET['new'] ) ) : ?>
                    <?php self::render_survey_editor( $edit_survey ); ?>
                <?php else : ?>
                    <?php self::render_surveys_list( $surveys ); ?>
                <?php endif; ?>
            <?php else : ?>
                <?php self::render_responses_tab(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Survey list
    // -------------------------------------------------------------------------

    private static function render_surveys_list( $surveys ) {
        ?>
        <p>
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=ftt_event&page=' . self::MENU_SLUG . '&tab=surveys&new=1' ) ); ?>"
               class="button button-primary">
                <?php esc_html_e( '+ New Survey', 'schedule-collaboration-tracking' ); ?>
            </a>
        </p>

        <?php if ( empty( $surveys ) ) : ?>
            <p style="color:#666;"><?php esc_html_e( 'No surveys yet. Create one above.', 'schedule-collaboration-tracking' ); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Survey Name', 'schedule-collaboration-tracking' ); ?></th>
                        <th><?php esc_html_e( 'Trigger', 'schedule-collaboration-tracking' ); ?></th>
                        <th><?php esc_html_e( 'Questions', 'schedule-collaboration-tracking' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'schedule-collaboration-tracking' ); ?></th>
                        <th><?php esc_html_e( 'Responses', 'schedule-collaboration-tracking' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'schedule-collaboration-tracking' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $responses = self::get_responses();
                    foreach ( $surveys as $s ) :
                        $resp_count = count( array_filter( $responses, fn($r) => $r['survey_id'] === $s['id'] ) );
                        $trigger_label = self::trigger_label( $s['trigger'] ?? 'cancel_button' );
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html( $s['name'] ); ?></strong></td>
                        <td><?php echo esc_html( $trigger_label ); ?></td>
                        <td><?php echo esc_html( count( $s['questions'] ?? [] ) ); ?></td>
                        <td>
                            <?php if ( ! empty( $s['enabled'] ) ) : ?>
                                <span style="color:#46b450;">● <?php esc_html_e( 'Active', 'schedule-collaboration-tracking' ); ?></span>
                            <?php else : ?>
                                <span style="color:#999;">○ <?php esc_html_e( 'Inactive', 'schedule-collaboration-tracking' ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $resp_count ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=ftt_event&page=' . self::MENU_SLUG . '&tab=surveys&edit=' . $s['id'] ) ); ?>">
                                <?php esc_html_e( 'Edit', 'schedule-collaboration-tracking' ); ?>
                            </a>
                            &nbsp;|&nbsp;
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'edit.php?post_type=ftt_event&page=' . self::MENU_SLUG . '&tab=surveys&delete_survey=' . $s['id'] ), 'ftt_delete_survey_' . $s['id'] ) ); ?>"
                               onclick="return confirm('<?php esc_attr_e( 'Delete this survey?', 'schedule-collaboration-tracking' ); ?>');"
                               style="color:#a00;">
                                <?php esc_html_e( 'Delete', 'schedule-collaboration-tracking' ); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
    }

    // -------------------------------------------------------------------------
    // Survey editor
    // -------------------------------------------------------------------------

    private static function render_survey_editor( $survey = null ) {
        $is_new  = $survey === null;
        $id      = $is_new ? '' : esc_attr( $survey['id'] );
        $name    = $is_new ? '' : esc_attr( $survey['name'] );
        $enabled = ! $is_new && ! empty( $survey['enabled'] );
        $trigger = $is_new ? 'cancel_button' : ( $survey['trigger'] ?? 'cancel_button' );
        $pages   = $is_new ? [] : ( $survey['pages'] ?? [] );
        $questions = $is_new ? [] : ( $survey['questions'] ?? [] );
        $notify_email = $is_new ? get_option( 'admin_email' ) : ( $survey['notify_email'] ?? '' );
        $thank_you_message = $is_new ? __( 'Thank you for your feedback!', 'schedule-collaboration-tracking' ) : ( $survey['thank_you_message'] ?? '' );
        $headline    = $is_new ? '' : ( $survey['headline']    ?? '' );
        $subheadline = $is_new ? '' : ( $survey['subheadline'] ?? '' );

        $trigger_options = [
            'cancel_button' => __( 'Cancel Subscription button click', 'schedule-collaboration-tracking' ),
            'exit_intent'   => __( 'Exit intent (mouse leaves viewport)', 'schedule-collaboration-tracking' ),
            'page_load'     => __( 'Page load (show after X seconds)', 'schedule-collaboration-tracking' ),
        ];
        ?>
        <form method="post" id="ftt-survey-editor">
            <?php wp_nonce_field( 'ftt_save_survey', 'ftt_survey_nonce' ); ?>
            <input type="hidden" name="ftt_survey_action" value="save_survey">
            <input type="hidden" name="survey_id" value="<?php echo esc_attr( $id ); ?>">

            <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">

                <!-- Main column -->
                <div>
                    <!-- Basic settings card -->
                    <div class="ftt-card" style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px;margin-bottom:20px;">
                        <h2 style="margin-top:0;"><?php echo $is_new ? esc_html__( 'New Survey', 'schedule-collaboration-tracking' ) : esc_html__( 'Edit Survey', 'schedule-collaboration-tracking' ); ?></h2>

                        <table class="form-table" style="margin:0;">
                            <tr>
                                <th><label for="survey_name"><?php esc_html_e( 'Survey Name', 'schedule-collaboration-tracking' ); ?></label></th>
                                <td><input type="text" id="survey_name" name="survey_name" value="<?php echo esc_attr( $name ); ?>" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="survey_headline"><?php esc_html_e( 'Headline', 'schedule-collaboration-tracking' ); ?></label></th>
                                <td>
                                    <input type="text" id="survey_headline" name="survey_headline" value="<?php echo esc_attr( $headline ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Wait! Before you cancel…', 'schedule-collaboration-tracking' ); ?>">
                                    <p class="description"><?php esc_html_e( 'Bold attention-grabbing title shown at the top of the survey modal.', 'schedule-collaboration-tracking' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="survey_subheadline"><?php esc_html_e( 'Sub-headline', 'schedule-collaboration-tracking' ); ?></label></th>
                                <td>
                                    <input type="text" id="survey_subheadline" name="survey_subheadline" value="<?php echo esc_attr( $subheadline ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Your feedback helps us improve for families like yours.', 'schedule-collaboration-tracking' ); ?>">
                                    <p class="description"><?php esc_html_e( 'Optional supporting line shown beneath the headline.', 'schedule-collaboration-tracking' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="survey_trigger"><?php esc_html_e( 'Trigger', 'schedule-collaboration-tracking' ); ?></label></th>
                                <td>
                                    <select name="survey_trigger" id="survey_trigger" onchange="fttSurveyTriggerChange(this.value)">
                                        <?php foreach ( $trigger_options as $val => $label ) : ?>
                                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $trigger, $val ); ?>>
                                                <?php echo esc_html( $label ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr id="ftt-delay-row" style="<?php echo $trigger !== 'page_load' ? 'display:none;' : ''; ?>">
                                <th><label for="survey_delay"><?php esc_html_e( 'Delay (seconds)', 'schedule-collaboration-tracking' ); ?></label></th>
                                <td>
                                    <input type="number" id="survey_delay" name="survey_delay" value="<?php echo esc_attr( $survey['delay'] ?? 5 ); ?>" min="1" max="120" style="width:80px;">
                                    <p class="description"><?php esc_html_e( 'How many seconds after page load before the modal appears.', 'schedule-collaboration-tracking' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Show On Pages', 'schedule-collaboration-tracking' ); ?></th>
                                <td>
                                    <?php
                                    $all_pages = get_pages( [ 'post_status' => 'publish', 'sort_column' => 'menu_order', 'sort_order' => 'ASC' ] );
                                    foreach ( $all_pages as $p ) :
                                    ?>
                                    <label style="display:block;margin-bottom:4px;">
                                        <input type="checkbox" name="survey_pages[]"
                                               value="<?php echo esc_attr( $p->ID ); ?>"
                                               <?php checked( in_array( (string) $p->ID, array_map( 'strval', $pages ), true ) ); ?>>
                                        <?php echo esc_html( $p->post_title ); ?>
                                        <span style="color:#aaa;font-size:12px;">/<?php echo esc_html( $p->post_name ); ?>/</span>
                                    </label>
                                    <?php endforeach; ?>
                                    <p class="description"><?php esc_html_e( 'Leave all unchecked to show on all pages (not recommended).', 'schedule-collaboration-tracking' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="survey_thank_you"><?php esc_html_e( 'Thank You Message', 'schedule-collaboration-tracking' ); ?></label></th>
                                <td>
                                    <input type="text" id="survey_thank_you" name="survey_thank_you" value="<?php echo esc_attr( $thank_you_message ); ?>" class="large-text">
                                </td>
                            </tr>
                            <tr>
                                <th><label for="survey_notify_email"><?php esc_html_e( 'Notify Email', 'schedule-collaboration-tracking' ); ?></label></th>
                                <td>
                                    <input type="email" id="survey_notify_email" name="survey_notify_email" value="<?php echo esc_attr( $notify_email ); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e( 'Send an email notification for each response. Leave blank to disable.', 'schedule-collaboration-tracking' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Questions builder card -->
                    <div class="ftt-card" style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px;margin-bottom:20px;">
                        <h2 style="margin-top:0;"><?php esc_html_e( 'Questions', 'schedule-collaboration-tracking' ); ?></h2>
                        <p class="description" style="margin-bottom:16px;"><?php esc_html_e( 'Questions are shown in order. Users step through them one at a time.', 'schedule-collaboration-tracking' ); ?></p>

                        <div id="ftt-questions-list">
                            <?php foreach ( $questions as $qi => $q ) : ?>
                                <?php self::render_question_row( $qi, $q ); ?>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" class="button" onclick="fttAddQuestion()" style="margin-top:10px;">
                            + <?php esc_html_e( 'Add Question', 'schedule-collaboration-tracking' ); ?>
                        </button>
                    </div>
                </div>

                <!-- Sidebar -->
                <div>
                    <div class="ftt-card" style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px;margin-bottom:16px;">
                        <h3 style="margin-top:0;"><?php esc_html_e( 'Status', 'schedule-collaboration-tracking' ); ?></h3>
                        <label>
                            <input type="checkbox" name="survey_enabled" value="1" <?php checked( $enabled ); ?>>
                            <?php esc_html_e( 'Survey active', 'schedule-collaboration-tracking' ); ?>
                        </label>
                        <p class="description" style="margin-top:8px;"><?php esc_html_e( 'Inactive surveys are saved but not shown to visitors.', 'schedule-collaboration-tracking' ); ?></p>
                    </div>

                    <div style="display:flex;gap:8px;flex-direction:column;">
                        <?php submit_button( $is_new ? __( 'Create Survey', 'schedule-collaboration-tracking' ) : __( 'Save Survey', 'schedule-collaboration-tracking' ), 'primary', 'submit', false ); ?>
                        <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=ftt_event&page=' . self::MENU_SLUG ) ); ?>" class="button">
                            <?php esc_html_e( '← Back to Surveys', 'schedule-collaboration-tracking' ); ?>
                        </a>
                    </div>
                </div>

            </div><!-- /grid -->
        </form>

        <!-- Question row template (hidden) -->
        <template id="ftt-question-template">
            <?php self::render_question_row( '__INDEX__', [] ); ?>
        </template>

        <script>
        var fttQIndex = <?php echo count( $questions ); ?>;

        function fttSurveyTriggerChange(val) {
            document.getElementById('ftt-delay-row').style.display = val === 'page_load' ? '' : 'none';
        }

        function fttAddQuestion() {
            var tpl = document.getElementById('ftt-question-template').innerHTML;
            tpl = tpl.replace(/__INDEX__/g, fttQIndex);
            var div = document.createElement('div');
            div.innerHTML = tpl;
            document.getElementById('ftt-questions-list').appendChild(div.firstElementChild);
            fttQIndex++;
        }

        function fttRemoveQuestion(btn) {
            btn.closest('.ftt-question-row').remove();
        }

        function fttQuestionTypeChange(sel) {
            var row = sel.closest('.ftt-question-row');
            var optionsBlock = row.querySelector('.ftt-question-options');
            optionsBlock.style.display = (sel.value === 'text') ? 'none' : '';
            // Show/hide conditional response textareas — only meaningful for radio
            var responseFields = row.querySelectorAll('.ftt-option-response');
            responseFields.forEach(function(el) {
                el.style.display = (sel.value === 'radio') ? '' : 'none';
            });
        }

        function fttAddOption(btn) {
            var list = btn.previousElementSibling;
            var idx  = list.dataset.qindex;
            var qRow = btn.closest('.ftt-question-row');
            var qType = qRow.querySelector('select[name^="questions["][name$="[type]"]').value;
            var responseDisplay = (qType === 'radio') ? '' : 'none';
            var html = '<div class="ftt-option-row" style="margin-bottom:8px;border-left:3px solid #E9E3F2;padding-left:10px;">'
                + '<div style="display:flex;gap:8px;margin-bottom:4px;">'
                + '<input type="text" name="questions[' + idx + '][options][label][]" class="regular-text" placeholder="<?php echo esc_js( __( 'Option label', 'schedule-collaboration-tracking' ) ); ?>">'
                + '<button type="button" class="button button-small" onclick="this.closest(\'.ftt-option-row\').remove()">✕</button>'
                + '</div>'
                + '<div class="ftt-option-response" style="display:' + responseDisplay + ';">'
                + '<textarea name="questions[' + idx + '][options][response][]" rows="2" class="large-text" placeholder="<?php echo esc_js( __( 'Optional: message/offer shown when this option is selected (radio only)', 'schedule-collaboration-tracking' ) ); ?>" style="font-size:12px;"></textarea>'
                + '</div>'
                + '</div>';
            list.insertAdjacentHTML('beforeend', html);
        }
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // Question row partial (reused for existing questions and the JS template)
    // -------------------------------------------------------------------------

    private static function render_question_row( $index, $q ) {
        $qtype   = $q['type']  ?? 'radio';
        $label   = $q['label'] ?? '';
        $options = $q['options'] ?? [];
        $required = ! empty( $q['required'] );
        $types = [
            'radio'    => __( 'Single choice (radio)', 'schedule-collaboration-tracking' ),
            'checkbox' => __( 'Multiple choice (checkboxes)', 'schedule-collaboration-tracking' ),
            'text'     => __( 'Open text', 'schedule-collaboration-tracking' ),
        ];
        ?>
        <div class="ftt-question-row" style="background:#f9f9f9;border:1px solid #ddd;border-radius:4px;padding:16px;margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <strong style="color:#6A3E8E;"><?php esc_html_e( 'Question', 'schedule-collaboration-tracking' ); ?></strong>
                <button type="button" class="button button-small" onclick="fttRemoveQuestion(this)">
                    <?php esc_html_e( 'Remove', 'schedule-collaboration-tracking' ); ?>
                </button>
            </div>

            <table class="form-table" style="margin:0;">
                <tr>
                    <th style="width:140px;"><label><?php esc_html_e( 'Question Text', 'schedule-collaboration-tracking' ); ?></label></th>
                    <td>
                        <input type="text" name="questions[<?php echo esc_attr( $index ); ?>][label]"
                               value="<?php echo esc_attr( $label ); ?>"
                               class="large-text" required
                               placeholder="<?php esc_attr_e( 'e.g. What is the main reason you are canceling?', 'schedule-collaboration-tracking' ); ?>">
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Answer Type', 'schedule-collaboration-tracking' ); ?></label></th>
                    <td>
                        <select name="questions[<?php echo esc_attr( $index ); ?>][type]"
                                onchange="fttQuestionTypeChange(this)">
                            <?php foreach ( $types as $val => $lbl ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $qtype, $val ); ?>>
                                    <?php echo esc_html( $lbl ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Required', 'schedule-collaboration-tracking' ); ?></label></th>
                    <td><input type="checkbox" name="questions[<?php echo esc_attr( $index ); ?>][required]" value="1" <?php checked( $required ); ?>></td>
                </tr>
            </table>

            <!-- Answer options (hidden for text type) -->
            <div class="ftt-question-options" style="margin-top:12px;<?php echo $qtype === 'text' ? 'display:none;' : ''; ?>">
                <label style="font-weight:600;display:block;margin-bottom:8px;"><?php esc_html_e( 'Answer Options', 'schedule-collaboration-tracking' ); ?></label>
                <div class="ftt-options-list" data-qindex="<?php echo esc_attr( $index ); ?>">
                    <?php foreach ( $options as $opt ) :
                        // Support both legacy plain strings and new {label, response} objects
                        $opt_label    = is_array( $opt ) ? ( $opt['label']    ?? '' ) : $opt;
                        $opt_response = is_array( $opt ) ? ( $opt['response'] ?? '' ) : '';
                    ?>
                    <div class="ftt-option-row" style="margin-bottom:8px;border-left:3px solid #E9E3F2;padding-left:10px;">
                        <div style="display:flex;gap:8px;margin-bottom:4px;">
                            <input type="text" name="questions[<?php echo esc_attr( $index ); ?>][options][label][]"
                                   value="<?php echo esc_attr( $opt_label ); ?>"
                                   class="regular-text"
                                   placeholder="<?php esc_attr_e( 'Option label', 'schedule-collaboration-tracking' ); ?>">
                            <button type="button" class="button button-small" onclick="this.closest('.ftt-option-row').remove()">✕</button>
                        </div>
                        <div class="ftt-option-response" style="<?php echo $qtype !== 'radio' ? 'display:none;' : ''; ?>">
                            <textarea name="questions[<?php echo esc_attr( $index ); ?>][options][response][]"
                                      rows="2"
                                      class="large-text"
                                      placeholder="<?php esc_attr_e( 'Optional: message/offer shown when this option is selected (radio only)', 'schedule-collaboration-tracking' ); ?>"
                                      style="font-size:12px;"><?php echo esc_textarea( $opt_response ); ?></textarea>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button button-small" onclick="fttAddOption(this)" style="margin-top:4px;">
                    + <?php esc_html_e( 'Add Option', 'schedule-collaboration-tracking' ); ?>
                </button>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Responses tab
    // -------------------------------------------------------------------------

    private static function render_responses_tab() {
        $responses = array_reverse( self::get_responses() ); // newest first
        $surveys   = self::get_surveys();

        // Build survey name lookup
        $names = [];
        foreach ( $surveys as $s ) {
            $names[ $s['id'] ] = $s['name'];
        }

        // Filter by survey
        $filter_id = isset( $_GET['survey_filter'] ) ? sanitize_key( $_GET['survey_filter'] ) : '';
        if ( $filter_id ) {
            $responses = array_values( array_filter( $responses, fn($r) => $r['survey_id'] === $filter_id ) );
        }

        // Bulk clear button
        ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <form method="get" style="display:flex;gap:8px;align-items:center;">
                <input type="hidden" name="post_type" value="ftt_event">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>">
                <input type="hidden" name="tab" value="responses">
                <select name="survey_filter">
                    <option value=""><?php esc_html_e( '— All Surveys —', 'schedule-collaboration-tracking' ); ?></option>
                    <?php foreach ( $surveys as $s ) : ?>
                        <option value="<?php echo esc_attr( $s['id'] ); ?>" <?php selected( $filter_id, $s['id'] ); ?>>
                            <?php echo esc_html( $s['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button( __( 'Filter', 'schedule-collaboration-tracking' ), 'secondary', '', false ); ?>
            </form>

            <form method="post">
                <?php wp_nonce_field( 'ftt_clear_responses', 'ftt_survey_nonce' ); ?>
                <input type="hidden" name="ftt_survey_action" value="clear_responses">
                <input type="hidden" name="survey_id" value="<?php echo esc_attr( $filter_id ); ?>">
                <button type="submit" class="button" onclick="return confirm('<?php esc_attr_e( 'Clear all responses?', 'schedule-collaboration-tracking' ); ?>');">
                    <?php esc_html_e( 'Clear Responses', 'schedule-collaboration-tracking' ); ?>
                </button>
            </form>
        </div>

        <?php if ( empty( $responses ) ) : ?>
            <p style="color:#666;"><?php esc_html_e( 'No responses yet.', 'schedule-collaboration-tracking' ); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:160px;"><?php esc_html_e( 'Date', 'schedule-collaboration-tracking' ); ?></th>
                        <th style="width:140px;"><?php esc_html_e( 'Survey', 'schedule-collaboration-tracking' ); ?></th>
                        <th style="width:160px;"><?php esc_html_e( 'User', 'schedule-collaboration-tracking' ); ?></th>
                        <th><?php esc_html_e( 'Answers', 'schedule-collaboration-tracking' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $responses as $r ) :
                        $user    = $r['user_id'] ? get_userdata( $r['user_id'] ) : null;
                        $username = $user ? esc_html( $user->display_name . ' (' . $user->user_email . ')' ) : esc_html__( 'Anonymous', 'schedule-collaboration-tracking' );
                    ?>
                    <tr>
                        <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $r['created_at'] ) ) ); ?></td>
                        <td><?php echo esc_html( $names[ $r['survey_id'] ] ?? $r['survey_id'] ); ?></td>
                        <td><?php echo $username; ?></td>
                        <td>
                            <?php foreach ( $r['answers'] as $question => $answer ) : ?>
                                <div style="margin-bottom:6px;">
                                    <span style="font-weight:600;color:#6A3E8E;"><?php echo esc_html( $question ); ?>:</span>
                                    <?php
                                    if ( is_array( $answer ) ) {
                                        echo esc_html( implode( ', ', $answer ) );
                                    } else {
                                        echo esc_html( $answer );
                                    }
                                    ?>
                                </div>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="color:#666;font-size:12px;">
                <?php printf(
                    esc_html__( 'Showing %d response(s). Responses are capped at %d total.', 'schedule-collaboration-tracking' ),
                    count( $responses ),
                    self::MAX_RESPONSES
                ); ?>
            </p>
        <?php endif; ?>
        <?php
    }

    // -------------------------------------------------------------------------
    // Admin POST handler
    // -------------------------------------------------------------------------

    public static function handle_admin_post() {
        if ( ! isset( $_POST['ftt_survey_action'] ) ) {
            return;
        }

        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Permission denied.', 'schedule-collaboration-tracking' ) );
        }

        if ( ! isset( $_POST['ftt_survey_nonce'] ) || ! wp_verify_nonce( $_POST['ftt_survey_nonce'], 'ftt_save_survey' ) ) {
            // also check clear nonce
            if ( ! isset( $_POST['ftt_survey_nonce'] ) || ! wp_verify_nonce( $_POST['ftt_survey_nonce'], 'ftt_clear_responses' ) ) {
                wp_die( esc_html__( 'Security check failed.', 'schedule-collaboration-tracking' ) );
            }
        }

        $action = sanitize_key( $_POST['ftt_survey_action'] );

        if ( $action === 'save_survey' ) {
            self::handle_save_survey();
        } elseif ( $action === 'clear_responses' ) {
            self::handle_clear_responses();
        }

        // Handle delete via GET
        if ( isset( $_GET['delete_survey'] ) && isset( $_GET['_wpnonce'] ) ) {
            $del_id = sanitize_key( $_GET['delete_survey'] );
            if ( wp_verify_nonce( $_GET['_wpnonce'], 'ftt_delete_survey_' . $del_id ) ) {
                $surveys = self::get_surveys();
                $surveys = array_values( array_filter( $surveys, fn($s) => $s['id'] !== $del_id ) );
                update_option( self::OPT_SURVEYS, $surveys );
            }
            wp_redirect( admin_url( 'edit.php?post_type=ftt_event&page=' . self::MENU_SLUG ) );
            exit;
        }
    }

    private static function handle_save_survey() {
        $id      = sanitize_key( $_POST['survey_id'] ?? '' );
        $is_new  = empty( $id );
        if ( $is_new ) {
            $id = 'survey_' . time() . '_' . wp_rand( 1000, 9999 );
        }

        // Sanitize page list
        $pages = [];
        if ( ! empty( $_POST['survey_pages'] ) && is_array( $_POST['survey_pages'] ) ) {
            $pages = array_map( 'absint', $_POST['survey_pages'] );
        }

        // Sanitize questions
        $questions = [];
        // wp_unslash the entire questions array — WordPress magic-quotes all $_POST data,
        // so without this apostrophes save as \' and display back with backslashes.
        $raw_questions = wp_unslash( $_POST['questions'] ?? [] );
        if ( ! empty( $raw_questions ) && is_array( $raw_questions ) ) {
            foreach ( $raw_questions as $q ) {
                $type    = sanitize_key( $q['type'] ?? 'radio' );
                $options = [];
                if ( isset( $q['options']['label'] ) && is_array( $q['options']['label'] ) ) {
                    $labels    = $q['options']['label'];
                    $responses = $q['options']['response'] ?? [];
                    foreach ( $labels as $li => $lv ) {
                        $lv = sanitize_text_field( $lv );
                        if ( $lv === '' ) {
                            continue;
                        }
                        $options[] = [
                            'label'    => $lv,
                            'response' => sanitize_textarea_field( $responses[ $li ] ?? '' ),
                        ];
                    }
                }
                $questions[] = [
                    'label'    => sanitize_text_field( $q['label'] ?? '' ),
                    'type'     => in_array( $type, [ 'radio', 'checkbox', 'text' ], true ) ? $type : 'radio',
                    'options'  => $options,
                    'required' => ! empty( $q['required'] ),
                ];
            }
        }

        $survey = [
            'id'               => $id,
            'name'             => sanitize_text_field( wp_unslash( $_POST['survey_name'] ?? '' ) ),
            'headline'         => sanitize_text_field( wp_unslash( $_POST['survey_headline'] ?? '' ) ),
            'subheadline'      => sanitize_text_field( wp_unslash( $_POST['survey_subheadline'] ?? '' ) ),
            'enabled'          => ! empty( $_POST['survey_enabled'] ),
            'trigger'          => sanitize_key( $_POST['survey_trigger'] ?? 'cancel_button' ),
            'delay'            => absint( $_POST['survey_delay'] ?? 5 ),
            'pages'            => $pages,
            'questions'        => $questions,
            'thank_you_message'=> sanitize_text_field( wp_unslash( $_POST['survey_thank_you'] ?? '' ) ),
            'notify_email'     => sanitize_email( wp_unslash( $_POST['survey_notify_email'] ?? '' ) ),
        ];

        $surveys = self::get_surveys();
        $updated = false;
        foreach ( $surveys as &$s ) {
            if ( $s['id'] === $id ) {
                $s      = $survey;
                $updated = true;
                break;
            }
        }
        unset( $s );
        if ( ! $updated ) {
            $surveys[] = $survey;
        }
        update_option( self::OPT_SURVEYS, $surveys );

        wp_redirect( admin_url( 'edit.php?post_type=ftt_event&page=' . self::MENU_SLUG . '&saved=1' ) );
        exit;
    }

    private static function handle_clear_responses() {
        $survey_id = sanitize_key( $_POST['survey_id'] ?? '' );
        if ( $survey_id ) {
            $responses = self::get_responses();
            $responses = array_values( array_filter( $responses, fn($r) => $r['survey_id'] !== $survey_id ) );
            update_option( self::OPT_RESPONSES, $responses );
        } else {
            delete_option( self::OPT_RESPONSES );
        }
        wp_redirect( admin_url( 'edit.php?post_type=ftt_event&page=' . self::MENU_SLUG . '&tab=responses' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // REST endpoint — save a response
    // -------------------------------------------------------------------------

    public static function register_rest() {
        register_rest_route( 'ftt/v1', '/exit-survey/respond', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'rest_save_response' ],
            'permission_callback' => '__return_true',  // public — user may not be logged in
        ] );
    }

    public static function rest_save_response( WP_REST_Request $request ) {
        $survey_id = sanitize_key( $request->get_param( 'survey_id' ) );
        $raw_answers = $request->get_param( 'answers' );

        if ( ! $survey_id || ! is_array( $raw_answers ) ) {
            return new WP_Error( 'bad_request', 'Missing survey_id or answers.', [ 'status' => 400 ] );
        }

        // Validate survey exists
        $survey = null;
        foreach ( self::get_surveys() as $s ) {
            if ( $s['id'] === $survey_id ) {
                $survey = $s;
                break;
            }
        }
        if ( ! $survey ) {
            return new WP_Error( 'not_found', 'Survey not found.', [ 'status' => 404 ] );
        }

        // Sanitize answers: key = question label (string), value = string or array of strings
        $clean = [];
        foreach ( $raw_answers as $question => $answer ) {
            $question = sanitize_text_field( wp_unslash( (string) $question ) );
            if ( is_array( $answer ) ) {
                $answer = array_map( function( $v ) { return sanitize_text_field( wp_unslash( (string) $v ) ); }, $answer );
            } else {
                $answer = sanitize_text_field( wp_unslash( (string) $answer ) );
            }
            $clean[ $question ] = $answer;
        }

        $user_id = get_current_user_id(); // 0 if not logged in

        $response_entry = [
            'survey_id'  => $survey_id,
            'user_id'    => $user_id,
            'answers'    => $clean,
            'page_url'   => esc_url_raw( $request->get_param( 'page_url' ) ?? '' ),
            'created_at' => current_time( 'mysql' ),
        ];

        // Save to option (append, cap at MAX_RESPONSES)
        $responses = self::get_responses();
        $responses[] = $response_entry;
        if ( count( $responses ) > self::MAX_RESPONSES ) {
            $responses = array_slice( $responses, -self::MAX_RESPONSES );
        }
        update_option( self::OPT_RESPONSES, $responses );

        // Also save on user meta for quick lookup
        if ( $user_id ) {
            update_user_meta( $user_id, 'ftt_last_survey_response_' . $survey_id, $clean );
        }

        // Email notification
        if ( ! empty( $survey['notify_email'] ) ) {
            $subject = sprintf( __( '[FTT] New survey response: %s', 'schedule-collaboration-tracking' ), $survey['name'] );
            $body    = sprintf( __( "Survey: %s\nUser: %s\n\n", 'schedule-collaboration-tracking' ),
                $survey['name'],
                $user_id ? ( get_userdata( $user_id )->display_name . ' <' . get_userdata( $user_id )->user_email . '>' ) : 'Anonymous'
            );
            foreach ( $clean as $q => $a ) {
                $body .= $q . ":\n" . ( is_array( $a ) ? implode( ', ', $a ) : $a ) . "\n\n";
            }
            wp_mail( $survey['notify_email'], $subject, $body );
        }

        return rest_ensure_response( [ 'success' => true ] );
    }

    // -------------------------------------------------------------------------
    // Front-end enqueue
    // -------------------------------------------------------------------------

    public static function maybe_enqueue() {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) ) {
            return;
        }

        $surveys = self::get_surveys();
        $active  = [];
        foreach ( $surveys as $s ) {
            if ( empty( $s['enabled'] ) || empty( $s['questions'] ) ) {
                continue;
            }
            // Check page targeting
            if ( ! empty( $s['pages'] ) ) {
                if ( ! in_array( $post->ID, array_map( 'intval', $s['pages'] ), true ) ) {
                    continue;
                }
            }
            $active[] = $s;
        }

        if ( empty( $active ) ) {
            return;
        }

        wp_enqueue_style( 'dashicons' );

        wp_enqueue_script(
            'ftt-exit-survey',
            FTT_PLUGIN_URL . 'assets/js/exit-survey.js',
            [ 'jquery' ],
            FTT_VERSION,
            true
        );

        // Use wp_add_inline_script + wp_json_encode instead of wp_localize_script
        // to avoid WordPress HTML-encoding string values (&#039; &amp; etc.) in the output.
        wp_add_inline_script(
            'ftt-exit-survey',
            'window.fttExitSurveys = ' . wp_json_encode( [
                'surveys'  => $active,
                'restUrl'  => rest_url( 'ftt/v1/exit-survey/respond' ),
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'pageUrl'  => get_permalink( $post->ID ),
            ] ) . ';',
            'before'
        );
    }

    // -------------------------------------------------------------------------
    // Data helpers
    // -------------------------------------------------------------------------

    public static function get_surveys() {
        return (array) get_option( self::OPT_SURVEYS, [] );
    }

    public static function get_responses() {
        return (array) get_option( self::OPT_RESPONSES, [] );
    }

    private static function trigger_label( $trigger ) {
        $labels = [
            'cancel_button' => __( 'Cancel button click', 'schedule-collaboration-tracking' ),
            'exit_intent'   => __( 'Exit intent', 'schedule-collaboration-tracking' ),
            'page_load'     => __( 'Page load delay', 'schedule-collaboration-tracking' ),
        ];
        return $labels[ $trigger ] ?? $trigger;
    }
}
