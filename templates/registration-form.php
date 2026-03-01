<?php
/**
 * Template: Enhanced Registration Form
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$errors = get_transient('ftt_registration_errors');
if ($errors) {
    delete_transient('ftt_registration_errors');
}
?>

<div class="ftt-registration-wrapper">
    <div class="ftt-registration-form">
        <?php if ($errors) : ?>
            <div class="ftt-errors">
                <div class="ftt-error-icon">⚠️</div>
                <ul>
                    <?php foreach ($errors as $error) : ?>
                        <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="ftt-form-header">
            <h1><?php esc_html_e('Start Your Free Trial', 'schedule-collaboration-tracking'); ?></h1>
            <p class="ftt-form-subtitle"><?php esc_html_e('Join families who never miss a moment', 'schedule-collaboration-tracking'); ?></p>
        </div>
        
        <form method="post" id="ftt-register-form">
            <?php wp_nonce_field('ftt_register', 'ftt_register_nonce'); ?>
            <input type="hidden" name="redirect_to" value="<?php echo esc_url(home_url('/pricing/')); ?>">
            <input type="hidden" name="user_type" value="parent">
            
            <!-- Personal Information -->
            <div class="ftt-form-section">
                <h3><?php esc_html_e('Your Information', 'schedule-collaboration-tracking'); ?></h3>
                
                <div class="ftt-form-row">
                    <div class="ftt-form-field">
                        <label for="first_name"><?php esc_html_e('First Name', 'schedule-collaboration-tracking'); ?> <span class="required">*</span></label>
                        <input type="text" id="first_name" name="first_name" required value="<?php echo isset($_POST['first_name']) ? esc_attr($_POST['first_name']) : ''; ?>" placeholder="John">
                    </div>
                    <div class="ftt-form-field">
                        <label for="last_name"><?php esc_html_e('Last Name', 'schedule-collaboration-tracking'); ?> <span class="required">*</span></label>
                        <input type="text" id="last_name" name="last_name" required value="<?php echo isset($_POST['last_name']) ? esc_attr($_POST['last_name']) : ''; ?>" placeholder="Smith">
                    </div>
                </div>
                
                <div class="ftt-form-field">
                    <label for="email"><?php esc_html_e('Email Address', 'schedule-collaboration-tracking'); ?> <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? esc_attr($_POST['email']) : ''; ?>" placeholder="john@example.com">
                </div>
                
                <div class="ftt-form-row">
                    <div class="ftt-form-field">
                        <label for="password"><?php esc_html_e('Password', 'schedule-collaboration-tracking'); ?> <span class="required">*</span></label>
                        <input type="password" id="password" name="password" required minlength="8" placeholder="Minimum 8 characters">
                    </div>
                    <div class="ftt-form-field">
                        <label for="password_confirm"><?php esc_html_e('Confirm Password', 'schedule-collaboration-tracking'); ?> <span class="required">*</span></label>
                        <input type="password" id="password_confirm" name="password_confirm" required minlength="8" placeholder="Re-enter password">
                    </div>
                </div>
            </div>
            
            <!-- Pricing Preview Section -->
            <div class="ftt-form-section ftt-pricing-preview">
                <h3><?php esc_html_e('How many children will you track?', 'schedule-collaboration-tracking'); ?></h3>
                <p class="ftt-pricing-explanation"><?php esc_html_e('Each child gets their own color-coded calendar. You can add more later.', 'schedule-collaboration-tracking'); ?></p>
                
                <div class="ftt-child-selector">
                    <select id="child-count" name="planned_children">
                        <option value="1">1 child</option>
                        <option value="2">2 children</option>
                        <option value="3">3 children</option>
                        <option value="4">4 children</option>
                        <option value="5">5 children</option>
                        <option value="6">6+ children</option>
                    </select>
                </div>
                
                <div class="ftt-pricing-breakdown">
                    <div class="ftt-price-row">
                        <span><?php esc_html_e('Base subscription (1 child)', 'schedule-collaboration-tracking'); ?></span>
                        <span class="ftt-price">$9.99/mo</span>
                    </div>
                    <div class="ftt-price-row ftt-addon-row">
                        <span id="addon-label"><?php esc_html_e('Additional children', 'schedule-collaboration-tracking'); ?></span>
                        <span class="ftt-price" id="addon-price">$0.00/mo</span>
                    </div>
                    <div class="ftt-price-row ftt-total-row">
                        <span><?php esc_html_e('Monthly Total', 'schedule-collaboration-tracking'); ?></span>
                        <span class="ftt-price ftt-total-price" id="total-price">$9.99/mo</span>
                    </div>
                </div>
                
                <div class="ftt-trial-notice">
                    <div class="ftt-trial-icon">🎉</div>
                    <div class="ftt-trial-text">
                        <strong><?php esc_html_e('14-Day Free Trial', 'schedule-collaboration-tracking'); ?></strong><br>
                        <?php esc_html_e('No payment required now. Add payment method after you love it!', 'schedule-collaboration-tracking'); ?>
                    </div>
                </div>
            </div>
            
            <!-- Terms -->
            <div class="ftt-form-field ftt-checkbox-field">
                <label>
                    <input type="checkbox" name="agree_terms" required>
                    <?php esc_html_e('I agree to receive email notifications about events and price alerts', 'schedule-collaboration-tracking'); ?> <span class="required">*</span>
                </label>
            </div>
            
            <?php
            // hCaptcha integration
            $settings = get_option('ftt_settings', array());
            $enable_hcaptcha = $settings['enable_hcaptcha'] ?? false;
            $hcaptcha_site_key = $settings['hcaptcha_site_key'] ?? '';
            
            if ($enable_hcaptcha && !empty($hcaptcha_site_key)) :
            ?>
            <!-- hCaptcha -->
            <div class="ftt-form-field">
                <div class="h-captcha" data-sitekey="<?php echo esc_attr($hcaptcha_site_key); ?>"></div>
            </div>
            <?php endif; ?>
            
            <!-- Submit Button -->
            <div class="ftt-form-actions">
                <button type="submit" name="ftt_register_submit" class="ftt-btn-primary ftt-btn-large">
                    <?php esc_html_e('Create Account & Continue', 'schedule-collaboration-tracking'); ?>
                </button>
            </div>
            
            <p class="ftt-login-link">
                <?php esc_html_e('Already have an account?', 'schedule-collaboration-tracking'); ?>
                <a href="<?php echo esc_url(home_url('/ftt-login/')); ?>"><?php esc_html_e('Sign in', 'schedule-collaboration-tracking'); ?></a>
            </p>
        </form>
        
        <div class="ftt-trust-badges">
            <div class="ftt-badge">✓ <?php esc_html_e('14-day free trial', 'schedule-collaboration-tracking'); ?></div>
            <div class="ftt-badge">✓ <?php esc_html_e('No credit card required', 'schedule-collaboration-tracking'); ?></div>
            <div class="ftt-badge">✓ <?php esc_html_e('Cancel anytime', 'schedule-collaboration-tracking'); ?></div>
        </div>
    </div>
</div>

<style>
/* Enhanced Registration Form - Astra Colors */
.ftt-registration-wrapper {
    min-height: 100vh;
    background: linear-gradient(135deg, #F8F5FB 0%, #E9E3F2 100%);
    padding: 40px 20px;
}

.ftt-registration-form {
    max-width: 600px;
    margin: 0 auto;
    padding: 50px;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(106, 62, 142, 0.1);
}

.ftt-form-header {
    text-align: center;
    margin-bottom: 40px;
}

.ftt-form-header h1 {
    font-size: 32px;
    font-weight: 700;
    color: #6A3E8E;
    margin: 0 0 10px 0;
}

.ftt-form-subtitle {
    font-size: 16px;
    color: #666;
    margin: 0;
}

/* Error Messages */
.ftt-errors {
    background: #FFE5E5;
    border: 2px solid #F05A5A;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
    display: flex;
    gap: 15px;
    align-items: flex-start;
}

.ftt-error-icon {
    font-size: 24px;
    flex-shrink: 0;
}

.ftt-errors ul {
    margin: 0;
    padding-left: 20px;
    color: #721c24;
}

/* Form Sections */
.ftt-form-section {
    margin-bottom: 35px;
    padding-bottom: 35px;
    border-bottom: 2px solid #E9E3F2;
}

.ftt-form-section:last-of-type {
    border-bottom: none;
}

.ftt-form-section h3 {
    font-size: 20px;
    font-weight: 600;
    color: #6A3E8E;
    margin: 0 0 20px 0;
}

/* Form Fields */
.ftt-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.ftt-form-field {
    margin-bottom: 20px;
}

.ftt-form-field label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
    font-size: 14px;
}

.ftt-form-field input[type="text"],
.ftt-form-field input[type="email"],
.ftt-form-field input[type="password"],
.ftt-form-field select {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid #E9E3F2;
    border-radius: 6px;
    font-size: 16px;
    transition: all 0.3s;
    box-sizing: border-box;
}

.ftt-form-field input:focus,
.ftt-form-field select:focus {
    outline: none;
    border-color: #6A3E8E;
    box-shadow: 0 0 0 3px rgba(106, 62, 142, 0.1);
}

.ftt-checkbox-field {
    background: #F8F5FB;
    padding: 16px;
    border-radius: 6px;
    margin-bottom: 25px;
}

.ftt-checkbox-field label {
    display: flex;
    align-items: flex-start;
    font-weight: 400;
    gap: 10px;
    cursor: pointer;
}

.ftt-checkbox-field input[type="checkbox"] {
    margin-top: 2px;
    flex-shrink: 0;
}

.required {
    color: #F05A5A;
}

/* Pricing Preview */
.ftt-pricing-preview {
    background: #F8F5FB;
    padding: 30px;
    border-radius: 8px;
    border: 2px solid #E9E3F2;
}

.ftt-pricing-explanation {
    color: #666;
    margin: 0 0 20px 0;
    font-size: 14px;
}

.ftt-child-selector {
    margin-bottom: 25px;
}

.ftt-child-selector select {
    width: 100%;
    font-size: 18px;
    font-weight: 600;
    color: #6A3E8E;
    padding: 16px;
    border: 2px solid #6A3E8E;
    border-radius: 6px;
    cursor: pointer;
    background-color: white;
    appearance: auto;
    -webkit-appearance: menulist;
    -moz-appearance: menulist;
    box-sizing: border-box;
    pointer-events: auto;
    position: relative;
    z-index: 10;
}

.ftt-pricing-breakdown {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.ftt-price-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    color: #555;
}

.ftt-addon-row {
    color: #666;
    font-size: 14px;
}

.ftt-total-row {
    border-top: 2px solid #E9E3F2;
    margin-top: 10px;
    padding-top: 15px;
    font-weight: 700;
    color: #333;
    font-size: 18px;
}

.ftt-price {
    font-weight: 600;
    color: #6A3E8E;
}

.ftt-total-price {
    font-size: 24px;
    color: #F05A5A;
}

/* Trial Notice */
.ftt-trial-notice {
    display: flex;
    gap: 15px;
    background: linear-gradient(135deg, #6A3E8E, #5B347A);
    color: white;
    padding: 20px;
    border-radius: 8px;
    align-items: center;
}

.ftt-trial-icon {
    font-size: 32px;
    flex-shrink: 0;
}

.ftt-trial-text {
    font-size: 14px;
    line-height: 1.6;
}

.ftt-trial-text strong {
    font-size: 16px;
}

/* Submit Button */
.ftt-form-actions {
    margin-top: 30px;
    text-align: center;
}

.ftt-btn-primary {
    display: inline-block;
    width: 100%;
    padding: 18px 40px;
    background: #F05A5A;
    color: white !important;
    border: none;
    border-radius: 8px;
    font-size: 18px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
}

.ftt-btn-primary:hover {
    background: #E84E4E;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(240, 90, 90, 0.3);
}

.ftt-btn-large {
    font-size: 18px;
}

/* Footer Links */
.ftt-login-link {
    text-align: center;
    margin-top: 25px;
    color: #666;
    font-size: 14px;
}

.ftt-login-link a {
    color: #6A3E8E;
    text-decoration: none;
    font-weight: 600;
}

.ftt-login-link a:hover {
    text-decoration: underline;
}

/* Trust Badges */
.ftt-trust-badges {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.ftt-badge {
    color: #6A3E8E;
    font-size: 13px;
    font-weight: 600;
}

/* Responsive */
@media (max-width: 768px) {
    .ftt-registration-form {
        padding: 30px 25px;
    }
    
    .ftt-form-header h1 {
        font-size: 26px;
    }
    
    .ftt-form-row {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .ftt-trust-badges {
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Live pricing calculator
    function updatePricing() {
        var childCount = parseInt($('#child-count').val());
        var basePrice = 9.99;
        var addonPrice = 5.00;
        
        var addons = Math.max(0, childCount - 1);
        var addonTotal = addons * addonPrice;
        var total = basePrice + addonTotal;
        
        // Update addon label
        if (addons === 0) {
            $('#addon-label').text('Additional children');
            $('#addon-price').text('$0.00/mo');
        } else {
            var childText = (addons > 1) ? 'children' : 'child';
            $('#addon-label').text(addons + ' additional ' + childText + ' × $5');
            $('#addon-price').text('$' + addonTotal.toFixed(2) + '/mo');
        }
        
        // Update total
        $('#total-price').text('$' + total.toFixed(2) + '/mo');
    }
    
    // Initialize pricing
    updatePricing();
    
    // Update pricing on child count change
    $('#child-count').on('change', updatePricing);
    
    // Password confirmation validation
    $('#ftt-register-form').on('submit', function(e) {
        var password = $('#password').val();
        var confirm = $('#password_confirm').val();
        
        if (password !== confirm) {
            e.preventDefault();
            alert('Passwords do not match!');
            $('#password_confirm').focus();
            return false;
        }
    });
});
</script>
<?php
// Load hCaptcha script if enabled
$settings = get_option('ftt_settings', array());
$enable_hcaptcha = $settings['enable_hcaptcha'] ?? false;
$hcaptcha_site_key = $settings['hcaptcha_site_key'] ?? '';

if ($enable_hcaptcha && !empty($hcaptcha_site_key)) :
?>
<script src="https://js.hcaptcha.com/1/api.js" async defer></script>
<?php endif; ?>