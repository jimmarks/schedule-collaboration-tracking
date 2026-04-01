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

// Check for invite code in URL
$invite_code = isset($_GET['ftt_invite']) ? sanitize_text_field($_GET['ftt_invite']) : '';
$invite_data = null;

if (!empty($invite_code)) {
    // Fetch invite details via REST API
    $api_url = rest_url('ftt/v1/invite/' . $invite_code . '/validate');
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('FTT Registration: Fetching invite data from: ' . $api_url);
    }
    
    $response = wp_remote_get(
        $api_url,
        array('timeout' => 10)
    );
    
    if (is_wp_error($response)) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('FTT Registration: WP Error - ' . $response->get_error_message());
        }
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('FTT Registration: Response code - ' . $response_code);
            error_log('FTT Registration: Response body - ' . $body);
        }
        
        if ($response_code === 200) {
            $data = json_decode($body, true);
            
            if ($data && isset($data['valid']) && $data['valid'] === true && $data['type'] === 'adult_invitation') {
                $invite_data = $data;
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('FTT Registration: Invite data loaded successfully');
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('FTT Registration: Invalid invite data or wrong type - ' . print_r($data, true));
                }
            }
        }
    }
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
            <?php if ($invite_data) : ?>
                <h1><?php esc_html_e('Join Family Calendar', 'schedule-collaboration-tracking'); ?></h1>
                <p class="ftt-form-subtitle"><?php echo esc_html(sprintf(__('%s has invited you to join their family calendar', 'schedule-collaboration-tracking'), $invite_data['inviter']['name'])); ?></p>
            <?php else : ?>
                <h1><?php esc_html_e('Start Your Free Trial', 'schedule-collaboration-tracking'); ?></h1>
                <p class="ftt-form-subtitle"><?php esc_html_e('Join families who never miss a moment', 'schedule-collaboration-tracking'); ?></p>
            <?php endif; ?>
        </div>
        
        <?php if ($invite_data) : ?>
        <!-- Invitation Details Box -->
        <div class="ftt-invitation-details-box">
            <div class="ftt-invite-header">
                <span class="ftt-invite-icon">✉️</span>
                <h3><?php esc_html_e('Invitation Details', 'schedule-collaboration-tracking'); ?></h3>
            </div>
            
            <div class="ftt-invite-info">
                <div class="ftt-invite-row">
                    <strong><?php esc_html_e('From:', 'schedule-collaboration-tracking'); ?></strong>
                    <span><?php echo esc_html($invite_data['inviter']['name']); ?></span>
                </div>
                
                <div class="ftt-invite-row">
                    <strong><?php esc_html_e('Relationship:', 'schedule-collaboration-tracking'); ?></strong>
                    <span><?php echo esc_html(ucfirst($invite_data['relationship'])); ?></span>
                </div>
                
                <div class="ftt-invite-row">
                    <strong><?php esc_html_e('Group Members:', 'schedule-collaboration-tracking'); ?></strong>
                    <span>
                        <?php 
                        // Always show the inviter first
                        $group_members = array($invite_data['inviter']['name']);
                        
                        // Add any other linked adults
                        if (!empty($invite_data['linked_adults'])) {
                            foreach ($invite_data['linked_adults'] as $adult) {
                                $group_members[] = $adult['name'] . ' (' . $adult['relationship'] . ')';
                            }
                        }
                        
                        echo esc_html(implode(', ', $group_members));
                        
                        // Show count
                        $total_members = count($group_members);
                        echo ' <span style="color: #666;">(' . sprintf(_n('%d member', '%d members', $total_members, 'schedule-collaboration-tracking'), $total_members) . ')</span>';
                        ?>
                    </span>
                </div>
                
                <?php if (isset($invite_data['billing']['message'])) : ?>
                <div class="ftt-invite-row ftt-billing-status">
                    <strong><?php esc_html_e('Billing Status:', 'schedule-collaboration-tracking'); ?></strong>
                    <span class="ftt-billing-<?php echo esc_attr(strtolower($invite_data['billing']['status'])); ?>">
                        <?php echo esc_html($invite_data['billing']['message']); ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <div class="ftt-invite-note">
                    <p><?php esc_html_e('By joining, you\'ll share access to the family calendar and can manage events together. You won\'t be charged - the group owner handles billing.', 'schedule-collaboration-tracking'); ?></p>
                </div>
                
                <div class="ftt-invite-expires">
                    <small><?php echo esc_html(sprintf(__('This invitation expires on %s', 'schedule-collaboration-tracking'), $invite_data['expires'])); ?></small>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <form method="post" id="ftt-register-form">
            <?php wp_nonce_field('ftt_register', 'ftt_register_nonce'); ?>
            <input type="hidden" name="user_type" value="parent">
            <input type="hidden" name="ftt_invite_code" id="ftt-invite-code-field" value="<?php echo esc_attr($invite_code); ?>">
            
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
                    <?php if ($invite_data && !empty($invite_data['invitee_email'])) : ?>
                        <input type="email" id="email" name="email" required readonly value="<?php echo esc_attr($invite_data['invitee_email']); ?>" style="background-color: #f5f5f5; cursor: not-allowed;" placeholder="john@example.com">
                        <small style="color: #666; display: block; margin-top: 4px;"><?php esc_html_e('This invitation was sent to this email address', 'schedule-collaboration-tracking'); ?></small>
                    <?php else : ?>
                        <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? esc_attr($_POST['email']) : ''; ?>" placeholder="john@example.com">
                    <?php endif; ?>
                </div>
                
                <div class="ftt-form-field">
                    <label for="phone"><?php esc_html_e('Phone Number', 'schedule-collaboration-tracking'); ?> <span class="required">*</span></label>
                    <input type="tel" id="phone" name="phone" required
                        value="<?php echo isset($_POST['phone']) ? esc_attr($_POST['phone']) : ''; ?>"
                        placeholder="+1 (555) 555-5555">
                    <p class="ftt-field-description" style="margin:4px 0 0;font-size:12px;color:#666;"><?php esc_html_e('Used for important account notifications.', 'schedule-collaboration-tracking'); ?></p>
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
            
            <?php if (!$invite_data) : ?>
            <!-- Family Group Setup -->
            <div class="ftt-form-section">
                <h3><?php esc_html_e('Your Family Group', 'schedule-collaboration-tracking'); ?></h3>
                
                <div class="ftt-form-field">
                    <label for="group_name"><?php esc_html_e('Group Name', 'schedule-collaboration-tracking'); ?> <span class="required">*</span></label>
                    <input type="text" id="group_name" name="group_name" required value="<?php echo isset($_POST['group_name']) ? esc_attr($_POST['group_name']) : ''; ?>" placeholder="The Smiths">
                    <small style="color: #666; display: block; margin-top: 4px;"><?php esc_html_e('This will appear on your shared calendar', 'schedule-collaboration-tracking'); ?></small>
                </div>
                
                <div class="ftt-form-field">
                    <label for="child-count"><?php esc_html_e('How many children will you track?', 'schedule-collaboration-tracking'); ?> <span class="required">*</span></label>
                    <select id="child-count" name="planned_children" required>
                        <option value="1">1 child</option>
                        <option value="2">2 children</option>
                        <option value="3">3 children</option>
                        <option value="4">4 children</option>
                        <option value="5">5 children</option>
                        <option value="6">6 children</option>
                        <option value="7">7 children</option>
                        <option value="8">8 children</option>
                        <option value="9">9 children</option>
                        <option value="10">10+ children</option>
                    </select>
                    <small style="color: #666; display: block; margin-top: 4px;"><?php esc_html_e('Each child gets their own color-coded calendar', 'schedule-collaboration-tracking'); ?></small>
                </div>
            </div>
            
            <!-- Billing Setup -->
            <div class="ftt-form-section ftt-pricing-preview">
                <h3><?php esc_html_e('Choose Your Plan', 'schedule-collaboration-tracking'); ?></h3>
                
                <div class="ftt-billing-interval-selector">
                    <label class="ftt-interval-option">
                        <input type="radio" name="billing_interval" value="month" checked>
                        <div class="ftt-interval-card">
                            <div class="ftt-interval-label"><?php esc_html_e('Monthly', 'schedule-collaboration-tracking'); ?></div>
                            <div class="ftt-interval-price" id="monthly-price">$9.99/mo</div>
                            <div class="ftt-interval-note"><?php esc_html_e('Billed monthly', 'schedule-collaboration-tracking'); ?></div>
                        </div>
                    </label>
                    <label class="ftt-interval-option">
                        <input type="radio" name="billing_interval" value="year">
                        <div class="ftt-interval-card ftt-popular">
                            <div class="ftt-popular-badge"><?php esc_html_e('Save 17%', 'schedule-collaboration-tracking'); ?></div>
                            <div class="ftt-interval-label"><?php esc_html_e('Yearly', 'schedule-collaboration-tracking'); ?></div>
                            <div class="ftt-interval-price" id="yearly-price">$99/yr</div>
                            <div class="ftt-interval-note"><?php esc_html_e('$8.25/month', 'schedule-collaboration-tracking'); ?></div>
                        </div>
                    </label>
                </div>
                
                <div class="ftt-pricing-breakdown">
                    <div class="ftt-price-row">
                        <span id="base-label"><?php esc_html_e('Base subscription (1 child)', 'schedule-collaboration-tracking'); ?></span>
                        <span class="ftt-price" id="base-price">$9.99/mo</span>
                    </div>
                    <div class="ftt-price-row ftt-addon-row" id="addon-row" style="display: none;">
                        <span id="addon-label"></span>
                        <span class="ftt-price" id="addon-price"></span>
                    </div>
                    <div class="ftt-price-row ftt-total-row">
                        <span id="total-label"><?php esc_html_e('Total', 'schedule-collaboration-tracking'); ?></span>
                        <span class="ftt-price ftt-total-price" id="total-price">$9.99/mo</span>
                    </div>
                </div>
                
                <div class="ftt-trial-notice">
                    <div class="ftt-trial-icon">🎉</div>
                    <div class="ftt-trial-text">
                        <strong><?php esc_html_e('14-Day Free Trial', 'schedule-collaboration-tracking'); ?></strong><br>
                        <?php esc_html_e('Start your trial now. Add payment details after you love it!', 'schedule-collaboration-tracking'); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Terms -->
            <div class="ftt-form-field ftt-checkbox-field">
                <label>
                    <input type="checkbox" name="agree_terms" required>
                    <span><?php
                    $ftt_s = get_option('ftt_settings', []);
                    $ftt_wording = $ftt_s['policy_acceptance_wording'] ?? '';
                    if ( ! empty( $ftt_wording ) ) {
                        echo wp_kses_post( do_shortcode( $ftt_wording ) );
                    } else {
                        esc_html_e('I agree to receive email notifications about events and price alerts', 'schedule-collaboration-tracking');
                    }
                    ?> <span class="required">*</span></span>
                </label>
            </div>
            
            <?php
            // reCAPTCHA v3 integration — invisible, token injected on submit
            $settings = get_option('ftt_settings', array());
            $enable_recaptcha = $settings['enable_recaptcha'] ?? false;
            $recaptcha_site_key = $settings['recaptcha_site_key'] ?? '';
            
            if ($enable_recaptcha && !empty($recaptcha_site_key)) :
            ?>
            <!-- Google reCAPTCHA v3 hidden token field -->
            <input type="hidden" name="g-recaptcha-response" id="ftt-reg-recaptcha-token" value="">
            <?php endif; ?>
            
            <!-- Submit Button -->
            <div class="ftt-form-actions">
                <button type="submit" class="ftt-btn-primary ftt-btn-large">
                    <?php if ($invite_data) : ?>
                        <?php esc_html_e('Create Account & Join Group', 'schedule-collaboration-tracking'); ?>
                    <?php else : ?>
                        <?php esc_html_e('Start Free Trial →', 'schedule-collaboration-tracking'); ?>
                    <?php endif; ?>
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
    padding: 2px 17px;
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

/* Billing Interval Selector */
.ftt-billing-interval-selector {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 25px;
}

.ftt-interval-option {
    cursor: pointer;
    margin: 0;
}

.ftt-interval-option input[type="radio"] {
    position: absolute;
    opacity: 0;
}

.ftt-interval-card {
    background: white;
    border: 2px solid #E9E3F2;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
}

.ftt-interval-option input[type="radio"]:checked + .ftt-interval-card {
    border-color: #6A3E8E;
    background: #F8F5FB;
    box-shadow: 0 4px 12px rgba(106, 62, 142, 0.15);
}

.ftt-interval-card:hover {
    border-color: #6A3E8E;
    transform: translateY(-2px);
}

.ftt-popular-badge {
    position: absolute;
    top: -10px;
    right: 10px;
    background: #F05A5A;
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.ftt-interval-label {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
}

.ftt-interval-price {
    font-size: 24px;
    font-weight: 700;
    color: #6A3E8E;
    margin-bottom: 5px;
}

.ftt-interval-note {
    font-size: 13px;
    color: #666;
}

.ftt-child-selector {
    margin-bottom: 25px;
}

.ftt-child-selector select {
    width: 100%;
    height: 50px;
    font-size: 16px;
    padding: 5px;
    border: 2px solid #6A3E8E;
    border-radius: 6px;
    background: white;
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
// Password Encryption Module (AES-256-GCM)
var FTTPasswordEncryption = (function() {
    var encryptionKey = null;
    var encryptionNonce = '<?php echo FTT_Password_Encryption::get_encryption_nonce(); ?>';
    
    // Fetch encryption key from server
    async function fetchEncryptionKey() {
        if (encryptionKey) {
            return encryptionKey;
        }
        
        try {
            const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=ftt_get_encryption_key&nonce=' + encryptionNonce
            });
            
            const data = await response.json();
            
            if (data.success && data.data.key) {
                encryptionKey = data.data.key;
                return encryptionKey;
            } else {
                console.error('Failed to get encryption key:', data);
                return null;
            }
        } catch (error) {
            console.error('Error fetching encryption key:', error);
            return null;
        }
    }
    
    // Convert hex string to Uint8Array
    function hexToBytes(hex) {
        var bytes = new Uint8Array(hex.length / 2);
        for (var i = 0; i < hex.length; i += 2) {
            bytes[i / 2] = parseInt(hex.substr(i, 2), 16);
        }
        return bytes;
    }
    
    // Encrypt password using AES-256-GCM
    async function encryptPassword(password) {
        try {
            console.log('FTT ENCRYPTION: Starting encryption for password of length:', password.length);
            
            // Get encryption key
            var keyHex = await fetchEncryptionKey();
            if (!keyHex) {
                console.error('FTT ENCRYPTION: Failed to get encryption key');
                throw new Error('Failed to get encryption key');
            }
            
            console.log('FTT ENCRYPTION: Got encryption key (length:', keyHex.length, ')');
            
            // Convert hex key to bytes
            var keyBytes = hexToBytes(keyHex);
            console.log('FTT ENCRYPTION: Converted key to bytes (length:', keyBytes.length, ')');
            
            // Import key for AES-GCM
            var cryptoKey = await window.crypto.subtle.importKey(
                'raw',
                keyBytes,
                { name: 'AES-GCM' },
                false,
                ['encrypt']
            );
            console.log('FTT ENCRYPTION: Imported crypto key');
            
            // Generate random IV (12 bytes for GCM)
            var iv = window.crypto.getRandomValues(new Uint8Array(12));
            console.log('FTT ENCRYPTION: Generated IV');
            
            // Convert password to bytes
            var encoder = new TextEncoder();
            var passwordBytes = encoder.encode(password);
            console.log('FTT ENCRYPTION: Encoded password to bytes');
            
            // Encrypt
            var encrypted = await window.crypto.subtle.encrypt(
                {
                    name: 'AES-GCM',
                    iv: iv,
                    tagLength: 128
                },
                cryptoKey,
                passwordBytes
            );
            console.log('FTT ENCRYPTION: Encryption complete, output length:', encrypted.byteLength);
            
            // Split encrypted data into ciphertext and auth tag
            var encryptedArray = new Uint8Array(encrypted);
            var ciphertext = encryptedArray.slice(0, encryptedArray.length - 16);
            var tag = encryptedArray.slice(encryptedArray.length - 16);
            console.log('FTT ENCRYPTION: Split ciphertext and tag');
            
            // Convert to base64: iv:ciphertext:tag (already base64 encoded)
            var ivBase64 = btoa(String.fromCharCode.apply(null, iv));
            var ciphertextBase64 = btoa(String.fromCharCode.apply(null, ciphertext));
            var tagBase64 = btoa(String.fromCharCode.apply(null, tag));
            console.log('FTT ENCRYPTION: Converted components to base64');
            
            // Combine with colons - server expects base64(iv:ciphertext:tag)
            var combined = ivBase64 + ':' + ciphertextBase64 + ':' + tagBase64;
            
            // Base64 encode the combined string for transmission
            var final = btoa(combined);
            console.log('FTT ENCRYPTION: Final encrypted string length:', final.length);
            
            return final;
            
        } catch (error) {
            console.error('FTT ENCRYPTION: Encryption error:', error);
            return null;
        }
    }
    
    return {
        encrypt: encryptPassword
    };
})();

document.addEventListener('DOMContentLoaded', function() {
    console.log('FTT REGISTRATION: DOMContentLoaded fired');
    
    var childCount = document.getElementById('child-count');
    var firstName = document.getElementById('first_name');
    var groupName = document.getElementById('group_name');
    var monthlyRadio = document.querySelector('input[name="billing_interval"][value="month"]');
    var yearlyRadio = document.querySelector('input[name="billing_interval"][value="year"]');
    var basePrice = document.getElementById('base-price');
    var addonLabel = document.getElementById('addon-label');
    var addonPrice = document.getElementById('addon-price');
    var addonRow = document.getElementById('addon-row');
    var totalPrice = document.getElementById('total-price');
    var totalLabel = document.getElementById('total-label');
    var monthlyPriceDisplay = document.getElementById('monthly-price');
    var yearlyPriceDisplay = document.getElementById('yearly-price');
    var registerForm = document.getElementById('ftt-register-form');
    
    console.log('FTT REGISTRATION: Form elements found:', {
        childCount: !!childCount,
        firstName: !!firstName,
        groupName: !!groupName,
        monthlyRadio: !!monthlyRadio,
        yearlyRadio: !!yearlyRadio
    });
    
    function updatePricing() {
        // Skip pricing updates if elements don't exist (e.g., invited user flow)
        if (!childCount || !yearlyRadio || !monthlyRadio) {
            return;
        }
        
        var count = parseInt(childCount.value) || 1;
        var isYearly = yearlyRadio && yearlyRadio.checked;
        
        // Pricing structure
        var baseMonthly = 9.99;
        var addonMonthly = 5.00;
        var baseYearly = 99.00;
        var addonYearly = 50.00;
        
        var base = isYearly ? baseYearly : baseMonthly;
        var addonPerChild = isYearly ? addonYearly : addonMonthly;
        var additionalChildren = Math.max(0, count - 1);
        var addonTotal = additionalChildren * addonPerChild;
        var total = base + addonTotal;
        
        var suffix = isYearly ? '/yr' : '/mo';
        
        console.log('FTT REGISTRATION: Pricing updated - children:', count, 'interval:', isYearly ? 'yearly' : 'monthly', 'total:', total);
        
        // Update base price
        if (basePrice) {
            basePrice.textContent = '$' + base.toFixed(2) + suffix;
        }
        
        // Update addon row
        if (additionalChildren > 0) {
            if (addonRow) addonRow.style.display = '';
            if (addonLabel) {
                var childText = additionalChildren > 1 ? 'children' : 'child';
                addonLabel.textContent = additionalChildren + ' additional ' + childText + ' × $' + addonPerChild.toFixed(2);
            }
            if (addonPrice) {
                addonPrice.textContent = '$' + addonTotal.toFixed(2) + suffix;
            }
        } else {
            if (addonRow) addonRow.style.display = 'none';
        }
        
        // Update total
        if (totalPrice) {
            totalPrice.textContent = '$' + total.toFixed(2) + suffix;
        }
        if (totalLabel) {
            totalLabel.textContent = isYearly ? 'Yearly Total' : 'Monthly Total';
        }
        
        // Update interval card displays
        if (monthlyPriceDisplay) {
            var monthlyTotal = baseMonthly + (additionalChildren * addonMonthly);
            monthlyPriceDisplay.textContent = '$' + monthlyTotal.toFixed(2) + '/mo';
        }
        if (yearlyPriceDisplay) {
            var yearlyTotal = baseYearly + (additionalChildren * addonYearly);
            yearlyPriceDisplay.textContent = '$' + yearlyTotal.toFixed(0) + '/yr';
        }
    }
    
    // Only initialize pricing if elements exist (not for invited users)
    if (childCount && monthlyRadio && yearlyRadio) {
        console.log('FTT REGISTRATION: Initial pricing calculation');
        updatePricing();
    }
    
    // Attach listeners
    if (childCount) {
        childCount.addEventListener('change', function() {
            console.log('FTT REGISTRATION: Child count changed to:', this.value);
            updatePricing();
        });
    }
    
    if (monthlyRadio) {
        monthlyRadio.addEventListener('change', updatePricing);
    }
    if (yearlyRadio) {
        yearlyRadio.addEventListener('change', updatePricing);
    }
    
    console.log('FTT REGISTRATION: Attaching submit listener to form');
    if (registerForm) {
        registerForm.addEventListener('submit', async function(e) {
            e.preventDefault(); // Always prevent default first
            console.log('FTT REGISTRATION: Form submission started');
            
            var password = document.getElementById('password');
            var passwordConfirm = document.getElementById('password_confirm');
            var email = document.getElementById('email');
            var submitButton = registerForm.querySelector('button[type="submit"]');
            
            console.log('FTT REGISTRATION: Form data:', {
                email: email ? email.value : 'N/A',
                firstName: firstName ? firstName.value : 'N/A',
                groupName: groupName ? groupName.value : 'N/A',
                childCount: childCount ? childCount.value : 'N/A',
                passwordsMatch: password && passwordConfirm ? password.value === passwordConfirm.value : 'N/A'
            });
            
            // Validate passwords match
            if (password && passwordConfirm && password.value !== passwordConfirm.value) {
                console.error('FTT REGISTRATION: Password mismatch - preventing submission');
                alert('Passwords do not match!');
                return false;
            }
            
            // Validate password not empty
            if (!password || !password.value || !passwordConfirm || !passwordConfirm.value) {
                alert('Please enter a password');
                return false;
            }
            
            try {
                // Disable submit button during encryption
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Encrypting...';
                }
                
                console.log('FTT REGISTRATION: Encrypting passwords...');
                
                // Encrypt both password fields
                var encryptedPassword = await FTTPasswordEncryption.encrypt(password.value);
                var encryptedPasswordConfirm = await FTTPasswordEncryption.encrypt(passwordConfirm.value);
                
                if (!encryptedPassword || !encryptedPasswordConfirm) {
                    throw new Error('Password encryption failed');
                }
                
                console.log('FTT REGISTRATION: Passwords encrypted successfully');
                
                // Replace password values with encrypted versions
                password.value = encryptedPassword;
                passwordConfirm.value = encryptedPasswordConfirm;
                
                // Add hidden input for submit button (since we're bypassing the actual button click)
                var submitInput = document.createElement('input');
                submitInput.type = 'hidden';
                submitInput.name = 'ftt_register_submit';
                submitInput.value = '1';
                registerForm.appendChild(submitInput);
                
                // Update button text
                if (submitButton) {
                    submitButton.textContent = 'Submitting...';
                }
                
                console.log('FTT REGISTRATION: Submitting form with encrypted passwords');
                
                // Submit the form
                registerForm.submit();
                
            } catch (error) {
                console.error('FTT REGISTRATION: Encryption error:', error);
                alert('Failed to encrypt password. Please try again or contact support.');
                
                // Re-enable button
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Start Free Trial →';
                }
                
                return false;
            }
        });
    }
    
    console.log('FTT REGISTRATION: Script initialization complete');
    
    // Check for invitation code in URL and ensure it's in the hidden field
    var urlParams = new URLSearchParams(window.location.search);
    var inviteCode = urlParams.get('ftt_invite');
    if (inviteCode) {
        console.log('FTT REGISTRATION: Found invite code in URL:', inviteCode);
        var inviteField = document.getElementById('ftt-invite-code-field');
        if (inviteField) {
            inviteField.value = inviteCode;
            console.log('FTT REGISTRATION: Set hidden field value to:', inviteCode);
        }
    } else {
        console.log('FTT REGISTRATION: No invite code in URL');
    }
});
</script>
<?php
// Load reCAPTCHA v3 script if enabled
$settings = get_option('ftt_settings', array());
$enable_recaptcha = $settings['enable_recaptcha'] ?? false;
$recaptcha_site_key = $settings['recaptcha_site_key'] ?? '';

if ($enable_recaptcha && !empty($recaptcha_site_key)) :
?>
<script src="https://www.google.com/recaptcha/api.js?render=<?php echo esc_attr($recaptcha_site_key); ?>" async defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.querySelector('.ftt-registration-form');
    if (!form) return;
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;
        grecaptcha.ready(function() {
            grecaptcha.execute('<?php echo esc_js($recaptcha_site_key); ?>', {action: 'register'}).then(function(token) {
                document.getElementById('ftt-reg-recaptcha-token').value = token;
                form.submit();
            });
        });
    });
});
</script>
<?php endif; ?>