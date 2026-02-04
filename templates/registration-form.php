<?php
/**
 * Template: Registration Form
 *
 * @package Summer_Regiment_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$errors = get_transient('srt_registration_errors');
if ($errors) {
    delete_transient('srt_registration_errors');
}

// Check for invitation code in URL
$invite_code = isset($_GET['invite']) ? sanitize_text_field($_GET['invite']) : '';
$is_parent_invite = !empty($invite_code);
?>

<div class="srt-registration-form">
    <?php if ($errors) : ?>
        <div class="srt-errors">
            <ul>
                <?php foreach ($errors as $error) : ?>
                    <li><?php echo esc_html($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if ($is_parent_invite) : ?>
        <div class="srt-invite-notice" style="background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px; padding: 15px; margin-bottom: 20px; color: #0c5460;">
            <strong>🎺 Parent Registration</strong>
            <p style="margin: 5px 0 0 0;">You're registering as a parent using an invitation code from your child.</p>
        </div>
    <?php endif; ?>
    
    <form method="post" id="srt-register-form">
        <?php wp_nonce_field('srt_register', 'srt_register_nonce'); ?>
        <input type="hidden" name="redirect_to" value="<?php echo esc_url(home_url('/')); ?>">
        
        <h2><?php esc_html_e('Register for Schedule Events', 'schedule-collaboration-tracking'); ?></h2>
        
        <!-- User Type Selection -->
        <div class="srt-form-field">
            <label><?php esc_html_e('I am registering as a:', 'schedule-collaboration-tracking'); ?> <span class="required">*</span></label>
            <label class="srt-radio-label">
                <input type="radio" name="user_type" value="member" required <?php echo !$is_parent_invite ? 'checked' : ''; ?>>
                <?php esc_html_e('Member', 'schedule-collaboration-tracking'); ?>
            </label>
            <label class="srt-radio-label">
                <input type="radio" name="user_type" value="parent" required <?php echo $is_parent_invite ? 'checked' : ''; ?>>
                <?php esc_html_e('Parent/Guardian', 'schedule-collaboration-tracking'); ?>
            </label>
        </div>
        
        <!-- Basic Info -->
        <div class="srt-form-row">
            <div class="srt-form-field">
                <label for="first_name"><?php esc_html_e('First Name', 'schedule-collaboration-tracking'); ?> <span class="required">*</span></label>
                <input type="text" id="first_name" name="first_name" required value="<?php echo isset($_POST['first_name']) ? esc_attr($_POST['first_name']) : ''; ?>">
            </div>
            <div class="srt-form-field">
                <label for="last_name"><?php esc_html_e('Last Name', 'schedule-collaboration-tracking'); ?> <span class="required">*</span></label>
                <input type="text" id="last_name" name="last_name" required value="<?php echo isset($_POST['last_name']) ? esc_attr($_POST['last_name']) : ''; ?>">
            </div>
        </div>
        
        <div class="srt-form-row">
            <div class="srt-form-field">
                <label for="email"><?php esc_html_e('Email Address', 'schedule-collaboration-tracking'); ?> <span class="required">*</span></label>
                <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? esc_attr($_POST['email']) : ''; ?>">
            </div>
            <div class="srt-form-field">
                <label for="phone"><?php esc_html_e('Phone Number', 'schedule-collaboration-tracking'); ?></label>
                <input type="tel" id="phone" name="phone" value="<?php echo isset($_POST['phone']) ? esc_attr($_POST['phone']) : ''; ?>">
            </div>
        </div>
        
        <div class="srt-form-row">
            <div class="srt-form-field">
                <label for="password"><?php esc_html_e('Password', 'schedule-collaboration-tracking'); ?> <span class="required">*</span></label>
                <input type="password" id="password" name="password" required minlength="8">
                <p class="description"><?php esc_html_e('Minimum 8 characters', 'schedule-collaboration-tracking'); ?></p>
            </div>
            <div class="srt-form-field">
                <label for="password_confirm"><?php esc_html_e('Confirm Password', 'schedule-collaboration-tracking'); ?> <span class="required">*</span></label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
            </div>
        </div>
        
        <!-- Member-specific fields -->
        <div id="member-fields" class="srt-conditional-fields" style="display: <?php echo $is_parent_invite ? 'none' : 'block'; ?>;">
            <h3><?php esc_html_e('Member Information', 'schedule-collaboration-tracking'); ?></h3>
            
            <div class="srt-form-row">
                <div class="srt-form-field">
                    <label for="member_section"><?php esc_html_e('Section', 'schedule-collaboration-tracking'); ?></label>
                    <select id="member_section" name="member_section">
                        <option value=""><?php esc_html_e('Select...', 'schedule-collaboration-tracking'); ?></option>
                        <option value="brass"><?php esc_html_e('Brass', 'schedule-collaboration-tracking'); ?></option>
                        <option value="percussion"><?php esc_html_e('Percussion', 'schedule-collaboration-tracking'); ?></option>
                        <option value="color_guard"><?php esc_html_e('Color Guard', 'schedule-collaboration-tracking'); ?></option>
                        <option value="front_ensemble"><?php esc_html_e('Front Ensemble', 'schedule-collaboration-tracking'); ?></option>
                    </select>
                </div>
                <div class="srt-form-field">
                    <label for="member_instrument"><?php esc_html_e('Instrument/Position', 'schedule-collaboration-tracking'); ?></label>
                    <input type="text" id="member_instrument" name="member_instrument" placeholder="<?php esc_attr_e('e.g., Trumpet, Snare, Flag', 'schedule-collaboration-tracking'); ?>">
                </div>
            </div>
        </div>
        
        <!-- Parent-specific fields -->
        <div id="parent-fields" class="srt-conditional-fields" style="display: <?php echo $is_parent_invite ? 'block' : 'none'; ?>;">
            <h3><?php esc_html_e('Parent Information', 'schedule-collaboration-tracking'); ?></h3>
            
            <div class="srt-form-field">
                <label for="invite_code"><?php esc_html_e('Invitation Code (Recommended)', 'schedule-collaboration-tracking'); ?></label>
                <input type="text" id="invite_code" name="invite_code" placeholder="<?php esc_attr_e('M-ABC123 or INV-XXXXXXXXXX', 'schedule-collaboration-tracking'); ?>" value="<?php echo esc_attr($invite_code); ?>">
                <p class="description"><?php esc_html_e('Enter the code your child shared with you to link accounts automatically.', 'schedule-collaboration-tracking'); ?></p>
            </div>
            
            <div class="srt-form-field">
                <label for="member_email"><?php esc_html_e('Or Member\'s Email', 'schedule-collaboration-tracking'); ?></label>
                <input type="email" id="member_email" name="member_email">
                <p class="description"><?php esc_html_e('If your child is already registered but you don\'t have a code, enter their email.', 'schedule-collaboration-tracking'); ?></p>
            </div>
        </div>
        
        <div class="srt-form-field">
            <label>
                <input type="checkbox" name="agree_terms" required>
                <?php esc_html_e('I agree to receive email notifications about events and price alerts', 'schedule-collaboration-tracking'); ?> <span class="required">*</span>
            </label>
        </div>
        
        <div class="srt-form-actions">
            <button type="submit" name="srt_register_submit" class="button button-primary button-large"><?php esc_html_e('Register', 'schedule-collaboration-tracking'); ?></button>
        </div>
        
        <p class="srt-login-link">
            <?php esc_html_e('Already have an account?', 'schedule-collaboration-tracking'); ?>
            <a href="<?php echo esc_url(wp_login_url()); ?>"><?php esc_html_e('Login here', 'schedule-collaboration-tracking'); ?></a>
        </p>
    </form>
</div>

<style>
.srt-registration-form {
    max-width: 700px;
    margin: 0 auto;
    padding: 30px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.srt-registration-form h2 {
    margin-top: 0;
    text-align: center;
}
.srt-registration-form h3 {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}
.srt-errors {
    background: #f8d7da;
    border: 1px solid #f5c2c7;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
    color: #842029;
}
.srt-errors ul {
    margin: 0;
    padding-left: 20px;
}
.srt-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}
.srt-form-field {
    margin-bottom: 15px;
}
.srt-form-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}
.srt-form-field input[type="text"],
.srt-form-field input[type="email"],
.srt-form-field input[type="tel"],
.srt-form-field input[type="password"],
.srt-form-field select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 16px;
}
.srt-radio-label {
    display: block;
    margin: 10px 0;
    font-weight: normal !important;
}
.srt-radio-label input {
    margin-right: 8px;
}
.required {
    color: #d63638;
}
.description {
    font-size: 13px;
    color: #666;
    margin-top: 5px;
}
.srt-form-actions {
    margin-top: 30px;
    text-align: center;
}
.srt-login-link {
    text-align: center;
    margin-top: 20px;
    color: #666;
}
.button-primary.button-large {
    padding: 12px 40px;
    font-size: 18px;
}
@media (max-width: 600px) {
    .srt-form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle fields based on user type
    $('input[name="user_type"]').on('change', function() {
        if ($(this).val() === 'member') {
            $('#member-fields').show();
            $('#parent-fields').hide();
        } else {
            $('#member-fields').hide();
            $('#parent-fields').show();
        }
    });
    
    // Password confirmation validation
    $('#srt-register-form').on('submit', function(e) {
        var password = $('#password').val();
        var confirm = $('#password_confirm').val();
        
        if (password !== confirm) {
            e.preventDefault();
            alert('<?php esc_html_e('Passwords do not match!', 'schedule-collaboration-tracking'); ?>');
            return false;
        }
    });
});
</script>
